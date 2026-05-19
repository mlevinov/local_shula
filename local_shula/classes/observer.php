<?php
/**
 * Event observer for the local_shula plugin.
 *
 * This class handles various Moodle events and dispatches adhoc tasks to synchronize
 * content with the Shula AI tutor backend.
 *
 * @package    local_shula
 * @copyright  2024 Shula AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shula;

defined('MOODLE_INTERNAL') || die();

/**
 * Class observer
 *
 * Handles Moodle events related to courses, modules, and sections.
 * Plugin: local_shula
 * Version: 2026051803 (Release 1.2.4)
 */
class observer {

    /** @var array Cache to store whether Shula is active in a course for the current request. */
    private static $active_course_cache = [];

    /**
     * Checks if the current process is a course restore or plugin installation.
     *
     * @return bool True if restoring or installing, false otherwise.
     */
    private static function is_restoring() {
        global $CFG;
        return (!empty($CFG->restoringcourse) || !empty($CFG->installing));
    }

    /**
     * Determines if Shula LTI tool is active within a given course.
     *
     * Scans the course for LTI activities that match the configured Shula identifier.
     *
     * @param int $courseid The Moodle course ID.
     * @param bool $clear_cache If true, ignores the static cache and forces a DB query.
     * @return bool True if Shula is active, false otherwise.
     */
    private static function is_shula_active_in_course($courseid, $clear_cache = false) {
        global $DB;
        $identifier = get_config('local_shula', 'shula_lti_identifier');
        
        if (empty($identifier)) {
            return false;
        }

        // Invalidate the cache if explicitly requested
        if ($clear_cache) {
            unset(self::$active_course_cache[$courseid]);
        }

        // Check if we already looked this up during this page load
        if (isset(self::$active_course_cache[$courseid])) {
            return self::$active_course_cache[$courseid];
        }

        // Perform a single optimized DB query
        $sql = "SELECT 1
                FROM {lti} l
            LEFT JOIN {lti_types} t        ON l.typeid = t.id
            LEFT JOIN {lti_types_config} c ON t.id = c.typeid
                WHERE l.course = :courseid
                AND (
                    l.toolurl       LIKE :id1
                    OR l.securetoolurl LIKE :id2
                    OR t.baseurl       LIKE :id3
                    OR c.value         LIKE :id4
                )";

        $search = '%' . $identifier . '%';
        $params = [
            'courseid' => $courseid,
            'id1'      => $search,
            'id2'      => $search,
            'id3'      => $search,
            'id4'      => $search
        ];

        $is_active = $DB->record_exists_sql($sql, $params);

        // Save to the static cache for the remainder of this request
        self::$active_course_cache[$courseid] = $is_active;
        return $is_active;
    }

    /**
     * Observer for course deletion events.
     *
     * @param \core\event\course_deleted $event The event object.
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        if (self::is_restoring()) return;
        
        // 1. Get the pre-deletion snapshot safely preserved by Moodle
        $course = $event->get_record_snapshot('course', $event->courseid);
        if (!$course) return;

        // 2. Pre-build the JSON schema immediately before the database is wiped
        $course_item = \local_shula\service\payload_builder::build_course_item($course);

        // 3. Queue the webhook using the exact same 'lti_tool_deleted' trigger
        $task = new \local_shula\task\send_webhook();
        $task->set_custom_data([
            'event_type'  => 'lti_tool_deleted',
            'courseid'    => $event->courseid,
            'cmid'        => null,
            'modname'     => 'course',
            'course_item' => $course_item // Pass the snapshot!
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Observer for course update events.
     *
     * @param \core\event\course_updated $event The event object.
     */
    public static function course_updated(\core\event\course_updated $event) {
        if (self::is_restoring()) return;
        if (!self::is_shula_active_in_course($event->courseid)) return;
        self::queue_course_task('course_sync', $event);
    }

    /**
     * Observer for course module creation events.
     *
     * @param \core\event\course_module_created $event The event object.
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        if (self::is_restoring()) return;
        $modname = $event->other['modulename'] ?? '';
        
        if ($modname === 'lti') {
            if (self::is_shula_active_in_course($event->courseid)) {
                $task = new \local_shula\task\bulk_sync();
                $task->set_custom_data(['courseid' => $event->courseid]);
                \core\task\manager::queue_adhoc_task($task);
                return; 
            }
        }

        if (!self::is_shula_active_in_course($event->courseid)) return;
        self::queue_webhook_task('file_created', $event);
    }

    /**
     * Observer for course module deletion events.
     *
     * @param \core\event\course_module_deleted $event The event object.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $DB;
        if (self::is_restoring()) return;
        $modname = $event->other['modulename'] ?? 'UNKNOWN';
        
        if ($modname === 'lti') {
            // Force cache flush to evaluate the actual DB state post-deletion
            if (!self::is_shula_active_in_course($event->courseid, true)) {
                
                // Fetch course to build snapshot
                $course = $DB->get_record('course', ['id' => $event->courseid]);
                $course_item = $course ? \local_shula\service\payload_builder::build_course_item($course) : null;

                $task = new \local_shula\task\send_webhook();
                $task->set_custom_data([
                    'event_type'  => 'lti_tool_deleted',
                    'courseid'    => $event->courseid,
                    'cmid'        => null,
                    'modname'     => 'lti',
                    'course_item' => $course_item // Pass the snapshot!
                ]);
                \core\task\manager::queue_adhoc_task($task);
                
                // Only exit if the entire course needs to be wiped
                return; 
            }
            // Fall-through happens here if a duplicate Shula LTI tool was deleted but others remain.
        }

        // Standard deletion handling (now safely catches duplicate LTI tools too)
        if (!self::is_shula_active_in_course($event->courseid)) return;
        self::queue_webhook_task('file_deleted', $event);
    }

    /**
     * Observer for course restore events.
     *
     * @param \core\event\course_restored $event The event object.
     */
    public static function course_restored(\core\event\course_restored $event) {
        if (!self::is_shula_active_in_course($event->courseid)) return;
        $task = new \local_shula\task\bulk_sync();
        $task->set_custom_data(['courseid' => $event->courseid]);
        \core\task\manager::queue_adhoc_task($task);
    }
    
    /**
     * Placeholder observer for course creation events.
     *
     * @param \core\event\course_created $event The event object.
     */
    public static function course_created(\core\event\course_created $event) {
        return;
    }

    /**
     * Helper to queue a generic module-level webhook task.
     *
     * @param string $event_type The type of event (e.g., 'file_created').
     * @param object $event The Moodle event object.
     */
    private static function queue_webhook_task($event_type, $event) {
        $task = new \local_shula\task\send_webhook();
        $task->set_custom_data([
            'event_type' => $event_type,
            'cmid'       => $event->contextinstanceid,
            'courseid'   => $event->courseid,
            'modname'    => $event->other['modulename'] ?? 'unknown'
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Helper to queue a course-level sync task.
     *
     * @param string $event_type The type of event (e.g., 'course_sync').
     * @param object $event The Moodle event object.
     */
    private static function queue_course_task($event_type, $event) {
        $task = new \local_shula\task\send_webhook();
        $task->set_custom_data([
            'event_type' => $event_type,
            'courseid'   => $event->courseid,
            'cmid'       => null, 
            'modname'    => null
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    // ==============================================================================
    // SECTION OBSERVERS
    // ==============================================================================

    /**
     * Observer for course section creation events.
     *
     * @param \core\event\course_section_created $event The event object.
     */
    public static function course_section_created(\core\event\course_section_created $event) {
        self::handle_section_event($event, 'section_created');
    }

    /**
     * Observer for course section update events.
     *
     * @param \core\event\course_section_updated $event The event object.
     */
    public static function course_section_updated(\core\event\course_section_updated $event) {
        self::handle_section_event($event, 'section_updated');
    }

    /**
     * Observer for course section deletion events.
     *
     * @param \core\event\course_section_deleted $event The event object.
     */
    public static function course_section_deleted(\core\event\course_section_deleted $event) {
        self::handle_section_event($event, 'section_deleted');
    }

    /**
     * Helper method to process section events cleanly.
     *
     * @param object $event The Moodle event object.
     * @param string $action The action performed (e.g., 'section_created').
     */
    private static function handle_section_event($event, $action) {
        // 1. Guard: Is Shula actively tracking this course?
        if (!self::is_shula_active_in_course($event->courseid)) {
            return;
        }

        // 2. Guard: Prevent "Restore Storm" spam
        if (self::is_restoring()) { 
            return;
        }

        // 3. Dispatch the lightweight section task
        $task = new \local_shula\task\send_section_webhook();
        $task->set_custom_data([
            'courseid'  => $event->courseid,
            'sectionid' => $event->objectid, // Moodle stores the section ID in objectid
            'action'    => $action
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    // ==============================================================================
    // MODULE OBSERVER
    // ==============================================================================

    /**
     * Crucial for detecting when a teacher clicks the "Hide" eyeball on a specific folder/page.
     *
     * @param \core\event\course_module_updated $event The event object.
     */
    public static function course_module_updated(\core\event\course_module_updated $event) {
        // 1. Guard: Prevent Restore Storms (using your safe helper)
        if (self::is_restoring()) return;
        
        // 2. Guard: Check if Shula is active (using the correct class scope)
        if (!self::is_shula_active_in_course($event->courseid)) return;

        // 3. Queue the task using the existing DRY helper, which safely sets 'event_type'
        self::queue_webhook_task('file_updated', $event);
    }
}
