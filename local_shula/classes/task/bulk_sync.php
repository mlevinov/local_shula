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
 * Version: 2026051803 (Release 1.2.4)
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
        $data = $this->get_custom_data();
        $courseid = $data->courseid;

        $institution_id = get_config('local_shula', 'shula_institution_id');
        if (!$institution_id) return;

        // 1. Generate the fully nested JSON tree
        $payload = \local_shula\service\payload_builder::build_course_tree($courseid);
        
        // 2. Attach the routing metadata
        $payload['event_type']     = 'bulk_sync';
        $payload['institution_id'] = $institution_id;

        // 3. Dispatch to Django
        \local_shula\service\client::send($payload);
    }
}