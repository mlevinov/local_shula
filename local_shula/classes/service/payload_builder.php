<?php
/**
 * Payload builder for the local_shula plugin.
 *
 * This class is responsible for constructing the JSON payloads sent to the Shula AI
 * tutor backend, ensuring they match the expected schema.
 *
 * @package    local_shula
 * @copyright  2024 Shula AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shula\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Class payload_builder
 *
 * Constructs hierarchical JSON structures representing Moodle courses, sections, and modules.
 * Plugin: local_shula
 * Version: 2026051803 (Release 1.2.4)
 */
class payload_builder {

    /**
     * Generates the complete, nested JSON tree for a Bulk Sync event.
     * Hierarchy: Course -> Section -> Module -> File/Content
     *
     * @param int $courseid The Moodle course ID.
     * @return array The complete course tree payload.
     */
    public static function build_course_tree($courseid) {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);

        $payload = [
            'course' => self::build_course_item($course),
            'sections' => []
        ];

        // Iterate over all sections in the course
        foreach ($modinfo->get_section_info_all() as $section_info) {
            $section_item = self::build_section_item($section_info, $modinfo);
            $payload['sections'][] = $section_item;
        }

        return $payload;
    }

    /**
     * Builds the top-level MoodleCourseItem schema.
     *
     * @param \stdClass $course The Moodle course record.
     * @return array The course item payload.
     */
    public static function build_course_item($course) {
        global $CFG; 

        $effective_lang = !empty($CFG->lang) ? $CFG->lang : 'en';

        if (!empty($course->lang) && $course->lang !== 'null') { // Added check for string "null"
            $effective_lang = $course->lang;
        }
        
        return [
            'moodle_course_id' => (int)$course->id,
            'fullname'         => $course->fullname,
            'categoryid'       => (int)$course->category,
            'is_visible'          => (int)$course->visible,
            'summary'          => $course->summary,
            'summaryformat'    => (int)$course->summaryformat,
            'lang'             => $effective_lang,
            'format'           => $course->format,
            // Marker is used in 'topics' or 'weeks' formats to highlight the current active section
            'current_marker'   => isset($course->marker) ? (int)$course->marker : 0, 
        ];
    }

    /**
     * Builds the MoodleSectionItem schema.
     *
     * @param \section_info $section_info The section info from modinfo.
     * @param \course_modinfo $modinfo The fast_modinfo object for the course.
     * @param bool $include_modules If false, skips building child modules to save payload size.
     * @return array The section item payload.
     */
    public static function build_section_item($section_info, $modinfo, $include_modules = true) {
        $section_item = [
            'moodle_section_id' => (int)$section_info->id,
            'section_number'    => (int)$section_info->section,
            'name'              => $section_info->name,
            'summary'           => $section_info->summary,
            'is_visible'           => (int)$section_info->visible,
            // Extract the timestamp when this section becomes available
            'unlock_date'       => self::extract_unlock_date($section_info->availability),
            // Pass the raw rule just in case Django needs to see group locks, grade locks, etc.
            'availability_rule' => $section_info->availability ? json_decode($section_info->availability, true) : null,
            'modules'           => []
        ];

        if ($include_modules && !empty($modinfo->sections[$section_info->section])) {
            foreach ($modinfo->sections[$section_info->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                $section_item['modules'][] = self::build_module_item($cm);
            }
        }

        return $section_item;
    }

    /**
     * Builds the Module item natively. 
     * Retrieves generic data, instance ID, and attached physical files.
     *
     * @param \cm_info $cm The course module object from modinfo.
     * @return array The module item payload.
     */
    public static function build_module_item($cm) {
        global $DB;

        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance]);

        // Fetch the configured opt-out tag (fallback to 'no-shula')
        $target_tag = get_config('local_shula', 'shula_opt_out_tag');
        if (empty($target_tag)) {
            $target_tag = 'no-shula';
        }

        // Query Moodle's Core Tag API for this specific course module
        $is_opted_out = false;
        
        // Ensure the Moodle tag class is available
        if (class_exists('\core_tag_tag')) {
            $tags = \core_tag_tag::get_item_tags('core', 'course_modules', $cm->id);
            foreach ($tags as $tag) {
                if (strtolower(trim($tag->rawname)) === strtolower(trim($target_tag))) {
                    $is_opted_out = true;
                    break;
                }
            }
        }

        $module_item =[
            'course_module_id' => (int)$cm->id,
            'instance_id'      => (int)$cm->instance,
            'name'             => $cm->name,
            'modname'          => $cm->modname,
            'is_visible'       => (int)$cm->visible,
            'unlock_date'      => self::extract_unlock_date($cm->availability),
            'ai_restricted'    => $is_opted_out,
            'availability_rule'=> $cm->availability ? json_decode($cm->availability, true) : null,
            'files_count'      => 0,
            'files_size'       => 0,
            'files'            => []
        ];

        // Only query and attach items if NOT opted out
        if (!$is_opted_out) {
            // 1. Get Physical Files
            $physical_files = self::build_file_items_from_cm($cm);
            
            // 2. Get Virtual Content Items (Descriptions, Pages, Labels, URLs)
            $virtual_items = self::build_virtual_items_from_cm($cm, $instance);
            
            // 3. Merge them into the unified Generalized Array
            $all_items = array_merge($physical_files, $virtual_items);

            foreach ($all_items as $item) {
                $module_item['files_size'] += $item['file_size'];
                $module_item['files'][] = $item;
            }
            $module_item['files_count'] = count($all_items);
        }

        return $module_item;
    }

    /**
     * Helper to dynamically construct ContentItemSchemas for native Moodle instances.
     * Evaluates descriptions, pages, and URLs, filtering out empty HTML noise.
     *
     * @param \cm_info $cm The course module object.
     * @param \stdClass $instance The module instance record.
     * @return array Array of virtual content items.
     */
    public static function build_virtual_items_from_cm($cm, $instance) {
        $virtual_items = [];

        // 1. Description Edge Case: Generate virtual text item for descriptions
        if (!empty($instance->intro)) {
            $clean_intro = trim(strip_tags($instance->intro));
            if ($clean_intro !== '') {
                $virtual_items[] = [
                    'item_id'        => (int)$cm->instance,
                    'type'           => 'description', // Distinct type avoids ID collisions with the instance itself
                    'name'           => $cm->name . ' (Description)',
                    'time_created'   => isset($instance->timecreated) ? (int)$instance->timecreated : 0,
                    'time_modified'  => isset($instance->timemodified) ? (int)$instance->timemodified : 0,
                    'mime_type'      => 'text/html',
                    'file_size'      => strlen($instance->intro),
                    'fileurl'        => null,
                    'content'        => $instance->intro,
                    'license'        => null
                ];
            }
        }

        // 2. Native Instance Content Logic
        $has_content = false;
        $content_val = null;
        $fileurl_val = null;

        // Extract HTML for Text Instances
        if ($cm->modname === 'page' || $cm->modname === 'label') {
            if (!empty($instance->content)) {
                $clean_content = trim(strip_tags($instance->content));
                if ($clean_content !== '') {
                    $has_content = true;
                    $content_val = $instance->content;
                }
            }
        } 
        // Extract URLs for Link Instances
        elseif ($cm->modname === 'url') {
            if (!empty($instance->externalurl)) {
                $has_content = true;
                $fileurl_val = $instance->externalurl;
            }
        } 
        elseif ($cm->modname === 'lti') {
            $url = !empty($instance->securetoolurl) ? $instance->securetoolurl : (!empty($instance->toolurl) ? $instance->toolurl : null);
            if ($url) {
                $has_content = true;
                $fileurl_val = $url;
            }
        }

        // Package the validated native content
        if ($has_content) {
            $virtual_items[] = [
                'item_id'        => (int)$cm->instance,
                'type'           => $cm->modname, // e.g., 'page', 'label', 'url'
                'name'           => $cm->name,
                'time_created'   => isset($instance->timecreated) ? (int)$instance->timecreated : 0,
                'time_modified'  => isset($instance->timemodified) ? (int)$instance->timemodified : 0,
                'mime_type'      => $content_val ? 'text/html' : 'url',
                'file_size'      => $content_val ? strlen($content_val) : 0,
                'fileurl'        => $fileurl_val,
                'content'        => $content_val,
                'license'        => null
            ];
        }

        return $virtual_items;
    }

    /**
     * Returns the strict allowlist of fileareas that only contain teacher-authored content.
     *
     * This mapping ensures that the AI tutor only ingests content provided by educators
     * (e.g., resource contents, assignment instructions) while explicitly ignoring
     * student-contributed files (e.g., forum attachments, assignment submissions, wiki edits).
     *
     * Separated for PHPUnit testing and security auditing to prevent silent regressions.
     *
     * @return array<string, string[]> Associative array where keys are Moodle components (mod_*)
     *                                 and values are arrays of allowed fileareas.
     */
    public static function get_safe_teacher_fileareas(): array {
        return [
            'mod_resource'  => ['content', 'intro'],
            'mod_page'      => ['content', 'intro'],
            'mod_folder'    => ['content', 'intro'],
            'mod_assign'    => ['introattachment', 'intro'], 
            'mod_book'      => ['chapter', 'intro'],
            'mod_scorm'     => ['package', 'intro'],
            'mod_imscp'     => ['content', 'intro'],
            'mod_lesson'    => ['page_contents', 'intro'],
            'mod_url'       => ['intro'],
            'mod_forum'     => ['intro'], 
            'mod_quiz'      => ['intro'],
            'mod_label'     => ['intro']
        ];
    }

    /**
     * Queries Moodle's File Storage API to build the files attached to the context.
     *
     * This method filters files based on the security allowlist defined in
     * get_safe_teacher_fileareas() to prevent accidental ingestion of student data.
     *
     * @param \cm_info $cm The course module object from modinfo.
     * @return array Array of file items matching the Django ContentItemSchema.
     */
    public static function build_file_items_from_cm($cm) {
        global $DB;
        $fs = get_file_storage();
        $context = \context_module::instance($cm->id);

        $component = 'mod_' . $cm->modname;

        // Use the extracted method
        $teacher_content_areas = self::get_safe_teacher_fileareas();

        // If this module type has no known safe content areas, skip file ingestion entirely.
        if (!isset($teacher_content_areas[$component])) {
            debugging("local_shula: skipping files for unsupported module type '$component'", DEBUG_DEVELOPER);
            return [];
        }

        $filearea_list = $teacher_content_areas[$component];
        list($in_sql, $in_params) = $DB->get_in_or_equal($filearea_list, SQL_PARAMS_QM);

        // Filter strictly by context, component, AND filearea
        $sql = "SELECT * FROM {files}
                WHERE contextid = ?
                  AND component = ?
                  AND filename != ?
                  AND filearea {$in_sql}";

        $params = array_merge([$context->id, $component, '.'], $in_params);
        $file_records = $DB->get_records_sql($sql, $params);
        
        $file_items =[];
        
        foreach ($file_records as $record) {
            $file = $fs->get_file_instance($record);

            $url = \moodle_url::make_webservice_pluginfile_url(
                $file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename(), false 
            );

            // Map strictly to Django's ContentItemSchema
            $file_items[] =[
                'item_id'        => (int)$file->get_id(),
                'type'           => 'file', // Critical for Django validation
                'name'           => $file->get_filename(),
                'fileurl'        => $url->out(false),
                'mime_type'      => $file->get_mimetype(),
                'file_size'      => (int)$file->get_filesize(),
                'time_created'   => (int)$file->get_timecreated(),
                'time_modified'  => (int)$file->get_timemodified(),
                'license'        => $file->get_license()
            ];
        }

        return $file_items;
    }


    // ==============================================================================
    // AVAILABILITY PARSING LOGIC
    // ==============================================================================

    /**
     * Safely and recursively parses Moodle's availability JSON to extract 
     * the strict "Available from" Unix timestamp.
     * 
     * Moodle stores rules like: {"op":"&","c":[{"type":"date","d":">=","t":1718064000}]}
     *
     * @param string $availability_json The raw JSON from Moodle's availability column.
     * @return int|null The unlock timestamp or null if none found.
     */
    private static function extract_unlock_date($availability_json) {
        if (empty($availability_json)) {
            return null;
        }
        
        $data = json_decode($availability_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            return null;
        }

        $unlock_date = null;
        
        // Use a recursive closure to walk the nested Moodle Availability AST (Tree)
        $walk = function($node) use (&$walk, &$unlock_date) {
            // If the node has children conditions ('c'), traverse them
            if (isset($node['c']) && is_array($node['c'])) {
                foreach ($node['c'] as $child) {
                    $walk($child);
                }
            } 
            // If this node is a date condition
            elseif (isset($node['type']) && $node['type'] === 'date') {
                // "d" is the direction. ">=" means "Available From"
                if (isset($node['d']) && ($node['d'] === '>=' || $node['d'] === '>')) {
                    $t = (int)$node['t'];
                    // If multiple start dates exist (e.g., AND logic), the most restrictive (latest) date applies.
                    if ($unlock_date === null || $t > $unlock_date) {
                        $unlock_date = $t;
                    }
                }
            }
        };

        $walk($data);
        return $unlock_date;
    }
}

