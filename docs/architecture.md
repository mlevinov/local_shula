# Architecture Overview

The `local_shula` plugin acts as a proactive, real-time, event-driven bridge ("Door A") between a Moodle LMS instance and the Shula AI Django backend. It ensures that the Shula AI platform is constantly synchronized with course structures, sections, and module modifications, without introducing performance overhead to Moodle's frontend interface.

---

## 1. Asynchronous Event-Driven Flow

To prevent slow HTTP requests from blocking the Moodle UI and degrading the teacher or student experience, `local_shula` employs a strictly asynchronous, decoupled architecture:

```
[ Moodle Event Occurs ]
      │
      ▼
[ local_shula\observer ]
      │
      ├─► (Verify Shula LTI is active in course)
      │
      ▼
[ Moodle Ad-hoc Task Queued ] ──► [ Moodle Database (task_adhoc) ]
                                          │
                    (Moodle Cron Runs) ───┘
                                          ▼
                                [ local_shula\task\* ]
                                          │
                                          ▼
                                [ local_shula\service\client ]
                                          │
                                          ▼ (HMAC Signed POST Request)
                                [ Shula AI Django Backend ]
```

### Flow Lifecycle
1. **User Action:** A teacher makes a change (e.g., adds a folder, hides a section, deletes a URL).
2. **Event Interception:** Moodle fires an event (e.g., `\core\event\course_module_created`), which is caught by `local_shula\observer`.
3. **LTI Validation:** The observer verifies if Shula is active in that course (by searching for configured Shula LTI activities in the course). If inactive, execution terminates instantly.
4. **Task Queueing:** An ad-hoc background task is queued with minimal required variables (such as `courseid` and `cmid`) using Moodle's native task manager:
   ```php
   \core\task\manager::queue_adhoc_task($task);
   ```
5. **Background Dispatch:** The Moodle Cron engine executes the task asynchronously, building the required secure JSON payload via `payload_builder` and dispatching it to Shula via `client::send()`.

---

## 2. Key Architectural Resilience Safeguards

### 2.1. Restore Storm Protection
During course restores, imports, or plugin installation, thousands of events (such as module and section creations) can occur simultaneously. If left unhandled, this would spawn thousands of HTTP requests, stalling Moodle's cron runner and crashing or rate-limiting the Shula backend.

To prevent this, `local_shula` implements **Restore Storm Protection**:
- The event observer checks if Moodle is currently restoring a course or installing a plugin using `$CFG->restoringcourse` and `$CFG->installing`.
- If a restore is active, normal module/section creation/update event hooks are immediately aborted.
- On the `course_restored` event (triggered at the very end of a course restoration), a single `local_shula\task\bulk_sync` task is queued to synchronize the entire course in one structured, paginated transaction.

### 2.2. Task Debouncing
To prevent duplicate requests for the same event type occurring in quick succession, the helper methods in `local_shula\observer` query Moodle's database to check if an identical ad-hoc task is already pending execution:
```php
if ($DB->record_exists('task_adhoc', ['classname' => $classname, 'customdata' => $json_data])) {
    return; // Skip duplicate queueing
}
```
This guarantees that multiple rapid clicks or fast edits to the same course or module do not result in redundant back-to-back background jobs.

### 2.3. Request-Level Static Caching
Determining if Shula is active in a course requires evaluating LTI activity instances. Since Moodle's event subsystem may fire several observers during a single page load, the plugin caches the result of the LTI check in a static property (`self::$active_course_cache`):
```php
if (isset(self::$active_course_cache[$courseid])) {
    return self::$active_course_cache[$courseid];
}
```
This completely avoids duplicate DB lookups for the LTI tool configuration during a single request lifecycle.

### 2.4. Snapshot Preservation on Deletions
When an item is deleted in Moodle, its database records are wiped **before** the background cron job has a chance to execute. To ensure the background client knows *what* was deleted, the event observer extracts a pre-deletion database snapshot (or pre-builds a payload snapshot) and encapsulates it directly into the ad-hoc task's custom data:
```php
$course_item = \local_shula\service\payload_builder::build_course_item($course);
$task->set_custom_data([
    'event_type'  => 'lti_tool_deleted',
    'courseid'    => $event->courseid,
    'course_item' => $course_item
]);
```
This enables the background task to accurately reconstruct and dispatch deletion payloads even after Moodle has cleared the records from its database.
