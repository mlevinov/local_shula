# Background Tasks

**Namespace:** `\local_shula\task\*`

To guarantee that Moodle remains fast for teachers and students, the plugin never blocks the UI to communicate with the Shula API. Instead, it queues background tasks using Moodle's Adhoc Task API. These tasks are picked up and executed asynchronously by the Moodle cron container.

## `bulk_sync`

**Trigger:** LTI tool installation, course restoration, or manual trigger.

*   This is the heaviest task.
*   It calls `payload_builder::build_course_tree()` to construct a massive, deeply nested JSON representation of the entire course and every file inside it.
*   Dispatches an `event_type: bulk_sync` to Django.

## `send_section_webhook`

**Trigger:** A section is created, renamed, moved, or its visibility is toggled.

*   A lightweight, specialized task.
*   Builds a payload containing the Course schema and just the specific Section schema (including nested modules).
*   Significantly reduces payload size and database load compared to a full sync when only a section changes.
*   Handles `section_deleted` events gracefully by passing only the IDs, as the DB records are already gone.

## `send_webhook`

**Trigger:** A module (file, page, url) is created, updated, or deleted.

*   The workhorse task for day-to-day course edits.
*   Builds a payload containing the Course, Section (without children), and the specific Module.
*   **Deletions:** If an LTI tool is deleted, it relies on a snapshot passed by the observer, since the Moodle course record is wiped before the task runs.
