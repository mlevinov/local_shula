# Event Observer

The `observer` class (`local_shula\observer`) is the entry point for real-time synchronization. It listens directly to Moodle's core event engine, filters out non-relevant changes, prevents restore storms, and triggers the asynchronous ad-hoc tasks that send sync payloads to Shula.

---

## 1. Event Registration (`db/events.php`)

All Moodle core events captured by the plugin are mapped inside `local_shula/db/events.php`:

| Event Class | Observer Callback | Description |
|---|---|---|
| `\core\event\course_updated` | `course_updated` | Syncs course settings (fullname, visibility). |
| `\core\event\course_deleted` | `course_deleted` | Triggers immediate course-removal flow on Shula. |
| `\core\event\course_module_created` | `course_module_created` | Detects new content modules or the Shula LTI tool. |
| `\core\event\course_module_updated` | `course_module_updated` | Detects updates, name changes, or "Hide/Show" eyeball state changes. |
| `\core\event\course_module_deleted` | `course_module_deleted` | Tracks file deletions or LTI removal. |
| `\core\event\course_section_created` | `course_section_created` | Synchronizes newly created topic/week sections. |
| `\core\event\course_section_updated` | `course_section_updated` | Synchronizes section name edits, visibility, or dates. |
| `\core\event\course_section_deleted` | `course_section_deleted` | Notifies backend of section deletion. |
| `\core\event\course_restored` | `course_restored` | Triggers single-transaction full-course bulk synchronization. |

---

## 2. Core Guard Methods

The observer performs several validation checks before queueing any background tasks:

### 2.1. LTI Activation Verification (`is_shula_active_in_course`)
The plugin only tracks courses where the **Shula LTI external tool** is active.
- **Scanning Logic:** The observer queries Moodle for LTI instances in the course whose URL, secure URL, or tool config values contain the configured `shula_lti_identifier`.
- **Database Safety:** When looking up matching strings, wildcards (such as `%` or `_`) are securely escaped using `$DB->sql_like_escape()` to prevent SQL injection or search pattern manipulation:
  ```php
  $safe_identifier = $DB->sql_like_escape($identifier);
  $search = '%' . $safe_identifier . '%';
  ```
- **Static Request Cache:** Lookups are cached in a static array (`$active_course_cache`) for the duration of the current page request, avoiding redundant database queries when multiple events are fired.

### 2.2. Restore Check (`is_restoring`)
Checks Moodle's global state to see if a course is currently being restored, imported, or if the plugin is being installed:
```php
private static function is_restoring() {
    global $CFG;
    return (!empty($CFG->restoringcourse) || !empty($CFG->installing));
}
```
If this method returns `true`, the observer aborts normal event processing to prevent "Restore Storms".

---

## 3. Advanced Deletion Handling (Snapshots)

When Moodle fires deletion events, the records are wiped from the database **before** the cron queue can process the ad-hoc task. 

To solve this, the observer captures a **pre-deletion snapshot** inside the event thread and packages it in the task payload:

```php
// Course deletion snapshot logic inside observer:
$course = $event->get_record_snapshot('course', $event->courseid);
$course_item = \local_shula\service\payload_builder::build_course_item($course);

$task = new \local_shula\task\send_webhook();
$task->set_custom_data([
    'event_type'  => 'lti_tool_deleted',
    'courseid'    => $event->courseid,
    'course_item' => $course_item // Database snapshot passed in task!
]);
```
This enables the background task to build a complete course payload structure even after the DB record is gone.
