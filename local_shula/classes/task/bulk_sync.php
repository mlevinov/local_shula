<?php
/**
 * Adhoc task for bulk synchronization in the local_shula plugin.
 *
 * This task is triggered to synchronize the entire content of a course with the
 * Shula AI tutor backend.
 *
 * @package    local_shula
 * @copyright  2024 Shula AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shula\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class bulk_sync
 *
 * Handles the full synchronization of a Moodle course.
 * Plugin: local_shula
 * Version: 2026051901 (Release 1.2.6)
 */
class bulk_sync extends \core\task\adhoc_task {

    /**
     * Returns the name of the task for display in the Moodle UI.
     *
     * @return string The task name.
     */
    public function get_name() {
        return get_string('task_bulk_sync', 'local_shula');
    }

    /**
     * Executes the bulk synchronization task.
     *
     * Builds the full course tree and dispatches it to the Shula backend.
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        $courseid = $data->courseid;

        $institution_id = get_config('local_shula', 'shula_institution_id');
        if (!$institution_id) return;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);
        $course_item = \local_shula\service\payload_builder::build_course_item($course);

        // Fetch all sections
        $all_sections = $modinfo->get_section_info_all();
        $chunk_size = 5; // Send 5 sections per HTTP request to prevent massive payloads
        $section_chunks = array_chunk($all_sections, $chunk_size);

        mtrace("Shula: Starting paginated bulk sync for course {$courseid} (" . count($all_sections) . " sections).");

        foreach ($section_chunks as $chunk_index => $section_chunk) {
            $sections_payload = [];
            
            foreach ($section_chunk as $section_info) {
                $sections_payload[] = \local_shula\service\payload_builder::build_section_item($section_info, $modinfo, true);
            }

            $payload = [
                'event_type'     => 'bulk_sync',
                'institution_id' => $institution_id,
                'course'         => $course_item,
                'sections'       => $sections_payload,
                'chunk_index'    => $chunk_index + 1,
                'total_chunks'   => count($section_chunks)
            ];

            try {
                \local_shula\service\client::send($payload);
            } catch (\moodle_exception $e) {
                if ($e->errorcode === 'webhook_failed' && is_numeric($e->a) && $e->a >= 400 && $e->a < 500) {
                    mtrace("Shula: Bad request payload (HTTP {$e->a}) on chunk " . ($chunk_index + 1) . ". Dropping task to prevent queue stall.");
                    return; 
                }
                throw $e; 
            }
        }
        
        mtrace("Shula: Bulk sync completed for course {$courseid}.");
    }
}