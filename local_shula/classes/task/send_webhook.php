<?php
/**
 * Adhoc task for sending module-level webhooks in the local_shula plugin.
 *
 * This task synchronizes module-level changes (created, updated, deleted)
 * and specific course-level events with the Shula AI tutor backend.
 *
 * @package    local_shula
 * @copyright  2024 Shula AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shula\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class send_webhook
 *
 * Handles the dispatching of various webhook events to the Shula backend.
 * Plugin: local_shula
 * Version: 2026051901 (Release 1.2.6)
 */
class send_webhook extends \core\task\adhoc_task {

    /**
     * Returns the name of the task for display in the Moodle UI.
     *
     * @return string The task name.
     */
    public function get_name() {
        return get_string('task_send_webhook', 'local_shula');
    }

    /**
     * Executes the webhook dispatch task.
     *
     * Processes module-level and course-level events and dispatches the relevant data to the Shula backend.
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        $event_type = $data->event_type;
        $cmid = $data->cmid;
        $courseid = $data->courseid;
        $modname = $data->modname ?? 'unknown';

        $institution_id = get_config('local_shula', 'shula_institution_id');
        if (!$institution_id) return;

        // Check if a course snapshot was passed (used during deletions)
        if (isset($data->course_item) && $data->course_item) {
            $course_item = (array)$data->course_item;
        } else {
            // Standard fetch for normal events
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $course_item = \local_shula\service\payload_builder::build_course_item($course);
        }

        $payload = null;

        // --- 1. Course-Level Events ---
        if ($event_type === 'lti_tool_deleted' || $event_type === 'course_sync') {
            $payload = [
                'event_type'     => $event_type,
                'institution_id' => $institution_id,
                'course'         => $course_item
            ];
        }
        // --- 2. Deletion Event ---
        elseif ($event_type === 'file_deleted') {
            $payload = [
                'event_type'     => 'file_deleted',
                'institution_id' => $institution_id,
                'course'         => $course_item,
                'cmid'           => (int)$cmid,
                'modname'        => $modname
            ];
        }
        // --- 3. Creation & Update Events ---
        else {
            // (Must use the DB because these rely on the items still existing)
            $course_record = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $modinfo = get_fast_modinfo($course_record);
            if (!isset($modinfo->cms[$cmid])) {
                mtrace("Shula: cmid {$cmid} not found in modinfo, skipping.");
                return;
            }
            
            $cm = $modinfo->cms[$cmid];
            mtrace("Shula: Processing webhook for module '{$cm->modname}' (cmid: {$cmid}).");

            $section_info = $modinfo->get_section_info($cm->sectionnum);
            
            // Build the section node WITHOUT children to save payload size
            $section_item = \local_shula\service\payload_builder::build_section_item($section_info, $modinfo, false);
            
            // Build the nested module node (contains the files/html content)
            $module_item = \local_shula\service\payload_builder::build_module_item($cm);

            $payload = [
                'event_type'     => $event_type, // 'file_created' or 'file_updated'
                'institution_id' => $institution_id,
                'course'         => $course_item,
                'section'        => $section_item,
                'module'         => $module_item
            ];
        }

        // --- 4. Centralized Dispatch with Try/Catch ---
        if ($payload) {
            try {
                \local_shula\service\client::send($payload);
            } catch (\moodle_exception $e) {
                // Moodle exceptions store the passed variable in $e->a (which we set to the HTTP code)
                if ($e->errorcode === 'webhook_failed' && is_numeric($e->a) && $e->a >= 400 && $e->a < 500) {
                    mtrace("Shula: Bad request payload (HTTP {$e->a}). Dropping task to prevent queue stall.");
                    return; // Gracefully exit without throwing, removing it from the retry queue
                }
                
                // It's a 5xx error or connection timeout. Throw it so Moodle's cron retries it later.
                throw $e; 
            }
        }
    }
}