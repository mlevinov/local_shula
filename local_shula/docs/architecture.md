# Architecture & Workflow

The `local_shula` plugin operates using a proactive **"push"** architecture (referred to as `Door A` in the project specs). It listens for native Moodle events and pushes contextual data to the Shula Django backend.

## The Ingestion Workflow

The workflow is designed to be asynchronous to ensure Moodle's UI performance is never impacted by API latency.

1.  **Event Observation (`db/events.php` & `classes/observer.php`)**
    *   Moodle broadcasts events (e.g., `course_module_created`, `course_section_updated`).
    *   The `\local_shula\observer` catches these events.
    *   The observer checks if the Shula LTI tool is actively deployed in the given course.
    *   If active, it immediately queues an Adhoc Task and returns control to the user.

2.  **Background Processing (`classes/task/`)**
    *   Moodle's cron engine picks up the queued Adhoc Task (e.g., `send_webhook`, `send_section_webhook`, `bulk_sync`).
    *   The task runs asynchronously in the background.

3.  **Payload Construction (`classes/service/payload_builder.php`)**
    *   The task calls the `payload_builder` to construct a deeply nested JSON schema representing the Moodle objects.
    *   This schema includes availability rules, visibility toggles, and metadata for physical files and virtual content (like HTML pages or descriptions).
    *   It checks for AI exclusion tags (e.g., `no-shula`) to restrict specific content from being indexed.

4.  **Secure Dispatch (`classes/service/client.php`)**
    *   The constructed payload is passed to the API client.
    *   The client generates an HMAC-SHA256 signature using the configured webhook secret.
    *   The payload is POSTed to the Shula Django backend (`/api/v1/webhook/moodle/`).

## Hierarchical Structure

The JSON payloads strictly follow a hierarchical ingestion model to maintain Moodle's structural depth in the Shula AI vector database:

`Course -> Section -> Module -> File/Content`

This ensures that the AI tutor always understands the exact context of the material it is referencing.
