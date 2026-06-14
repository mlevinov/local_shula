# Asynchronous Background Tasks

`local_shula` delegates HTTP webhooks to Moodle's native **Ad-hoc Task API**. This offloads outbound network operations from the user's interactive session to the background Moodle Cron scheduler, preserving frontend responsiveness.

All task classes are located under `local_shula\task` namespace and inherit from Moodle's core class `\core\task\adhoc_task`.

---

## 1. Paginated Course Bulk Sync Task (`bulk_sync`)

The `bulk_sync` task handles full course synchronizations. These typically occur when Shula is first activated in a course, or when a course is restored from a backup.

### Pagination Mechanism
For medium to large courses, the payload representing all sections, modules, and file metadata can be massive. This risks PHP memory exhaustions, server timeout failures, or exceeding Django payload size limits.

To prevent this, `bulk_sync` segments sections into chunks:
* **Chunk Size:** 5 sections per HTTP request.
* **Payload Metadata:** The payload includes indexing properties so the Django backend can identify and assemble the chunks sequentially:
  - **`chunk_index` (int):** The current chunk sequence number (1-indexed).
  - **`total_chunks` (int):** The total number of chunks being dispatched.

```php
// Section chunking logic in bulk_sync::execute()
$all_sections = $modinfo->get_section_info_all();
$chunk_size = 5; 
$section_chunks = array_chunk($all_sections, $chunk_size);

foreach ($section_chunks as $chunk_index => $section_chunk) {
    // ... Build sections payload and send ...
}
```

---

## 2. Lightweight Section Sync Task (`send_section_webhook`)

The `send_section_webhook` task manages individual section creations, name updates, visibility toggles, or release-date restrictions.

* **Section Deleted Early Exit:** When a section is deleted, Moodle deletes its record from the database before the task runs. If the action is `section_deleted`, the task exits database lookup routines early and dispatches a lightweight payload containing only the `section_id` to notify Shula to wipe the section cache.
* **Section Created/Updated:** Fetches the section info from Moodle's `get_fast_modinfo()` and builds a complete representation of the section, including all nested modules.

---

## 3. General Event Sync Task (`send_webhook`)

The `send_webhook` task manages module-level events (`file_created`, `file_updated`, `file_deleted`) and specific course-wide events (`lti_tool_deleted`, `course_sync`).

* **Database Preservation:** For module-level creations/updates, it relies on Moodle's fast modinfo database caching to assemble the `module` item and its attached files.
* **Snapshot Integration:** For deletions, it automatically checks if a pre-built course snapshot was provided in custom data, using it instead of trying to read from a deleted database entry.

---

## 4. Queue Stall Prevention

If a webhook payload is structurally malformed or causes a schema validation error on the Django backend (HTTP 400 - 499), retrying it will always fail. In standard Moodle setups, throwing exceptions causes Moodle's task manager to retry the task indefinitely, stalling the background cron queue.

`local_shula` tasks resolve this by catching `webhook_failed` exceptions and checking the HTTP status code:
- **HTTP 4xx (Client Error):** The task intercepts the exception, logs a warning to the cron trace (`mtrace`), and exits gracefully. This automatically and safely removes the failing task from Moodle's queue, preventing queue congestion.
- **HTTP 5xx (Server Error) or Connection Timeout:** The task re-throws the exception, triggering Moodle's native retry system with exponential backoff.

```php
try {
    \local_shula\service\client::send($payload);
} catch (\moodle_exception $e) {
    if ($e->errorcode === 'webhook_failed' && is_numeric($e->a) && $e->a >= 400 && $e->a < 500) {
        mtrace("Shula: Bad request payload (HTTP {$e->a}). Dropping task to prevent queue stall.");
        return; // Graceful exit removes task from queue
    }
    throw $e; // Re-throw triggers cron retry
}
```
