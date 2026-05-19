# Event Observer

**Namespace:** `\local_shula\observer`

The Event Observer acts as the central nervous system of the plugin. It listens for core Moodle events defined in `db/events.php` and decides if and how they should be routed to the Shula backend.

## The Observation Cycle

1.  **Event Fired:** A user performs an action in Moodle (e.g., creating a module, hiding a section). Moodle fires the corresponding `\core\event\*` class.
2.  **Capture:** The `observer` class catches the event.
3.  **Restore Guard:** `is_restoring()` checks if the event is part of a bulk course restore or plugin installation. If so, individual events are suppressed to prevent "restore storms."
4.  **Active Guard:** `is_shula_active_in_course()` checks if the Shula LTI tool is present anywhere in the course. It uses a static cache to prevent redundant database queries during a single page load. It supports an optional `$clear_cache` parameter to force a fresh database check when accurate state is critical (e.g., during tool deletion).
5.  **Dispatch:** If the guards pass, the observer packages the event IDs and queues the appropriate `adhoc_task`.

## Handled Events

### Course Events
*   `course_updated` -> Queues `send_webhook` (Course Sync)
*   `course_deleted` -> Generates a pre-deletion snapshot and queues `send_webhook` (LTI Tool Deleted)
*   `course_restored` -> Suppresses file events and queues a single `bulk_sync` task.

### Section Events
*   `course_section_created`
*   `course_section_updated`
*   `course_section_deleted`
*   **Action:** Queues the lightweight `send_section_webhook` task.

### Module Events
*   `course_module_created`
*   `course_module_deleted`
*   `course_module_updated` (Crucial for detecting visibility toggles on specific files)
*   **Action:** Queues `send_webhook` (File Created, File Deleted, File Updated).
*   **LTI Edge Case:** 
    *   **Creation:** If a Shula LTI tool is created, it triggers a full `bulk_sync` task to provision the course.
    *   **Deletion:** If an LTI tool is deleted, the observer forces a cache flush to evaluate the actual remaining state. If no other Shula LTI tools exist in the course, it generates a course snapshot using `payload_builder::build_course_item` and queues a `lti_tool_deleted` teardown event to the backend. This ensures the backend can clean up vectors if the institution disconnects the tool.
