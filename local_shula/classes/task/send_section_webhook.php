<?php
/**
 * Adhoc task for sending section-level webhooks in the local_shula plugin.
 *
 * This task synchronizes section-specific changes (created, updated, deleted)
 * with the Shula AI tutor backend.
 *
 * @package    local_shula
 * @copyright  2024 Shula AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shula\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class send_section_webhook
 *
 * Lightweight adhoc task to send a section-level payload (SectionUpdatedPayload) to Django.
 * Plugin: local_shula
 * Version: 2026051901 (Release 1.2.6)
 */
class send_section_webhook extends \core\task\adhoc_task {

    /**
     * Executes the section webhook task.
     *
     * Processes section-level events and dispatches the relevant data to the Shula backend.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $courseid = $data->courseid;
        $sectionid = $data->sectionid;
        $action = $data->action; // e.g., 'section_updated'

        $institution_id = get_config('local_shula', 'shula_institution_id');
        if (!$institution_id) {
            mtrace("Shula: Plugin not configured. Aborting section webhook task.");
            return;
        }

        mtrace("Shula: Starting section webhook task for section ID {$sectionid} in course {$courseid}");

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $course_item = \local_shula\service\payload_builder::build_course_item($course);

        // Handle Deletion Early (Because the record is already gone from Moodle DB!)
        if ($action === 'section_deleted') {
            $payload =[
                'event_type'     => $action,
                'institution_id' => $institution_id,
                'course'         => $course_item,
                'section_id'     => (int)$sectionid
            ];
            \local_shula\service\client::send($payload);
            return;
        }

        // --- For Creations & Updates ONLY ---
        $section_record = $DB->get_record('course_sections', ['id' => $sectionid]);
        if (!$section_record) {
            mtrace("Shula: Section not found in DB, skipping.");
            return;
        }

        $modinfo = get_fast_modinfo($course);
        $section_info = $modinfo->get_section_info($section_record->section);

        // Build the Section item (True = includes all nested modules inside this section)
        $section_item = \local_shula\service\payload_builder::build_section_item($section_info, $modinfo, true);

        $payload =[
            'event_type'     => $action, // e.g., 'section_updated'
            'institution_id' => $institution_id,
            'course'         => $course_item,
            'section'        => $section_item
        ];

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