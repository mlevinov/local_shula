<?php
/**
 * PHPUnit tests for the payload builder.
 *
 * @package    local_shula
 * @category   test
 * @copyright  2024 Shula AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shula;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * Class payload_builder_test
 * 
 * Ensures that data extraction does not violate student privacy by 
 * accidentally ingesting restricted file areas.
 */
class payload_builder_test extends advanced_testcase {

    /**
     * Ensure high-risk modules do not have dangerous fileareas mapped.
     */
    public function test_high_risk_modules_are_secured() {
        $safe_areas = \local_shula\service\payload_builder::get_safe_teacher_fileareas();

        // 1. Forums: Ensure we NEVER ingest 'attachment' or 'post' (student uploads)
        $this->assertArrayHasKey('mod_forum', $safe_areas);
        $this->assertNotContains('attachment', $safe_areas['mod_forum'], 'CRITICAL: mod_forum must not allow "attachment" filearea.');
        $this->assertNotContains('post', $safe_areas['mod_forum'], 'CRITICAL: mod_forum must not allow "post" filearea.');

        // 2. Assignments: Ensure we NEVER ingest 'submission' or 'feedback'
        $this->assertArrayHasKey('mod_assign', $safe_areas);
        $this->assertNotContains('submission_files', $safe_areas['mod_assign']);
        $this->assertNotContains('feedback', $safe_areas['mod_assign']);
        
        // Assignments should ONLY have teacher prompts (intro/introattachment)
        $this->assertContains('introattachment', $safe_areas['mod_assign']);
    }

    /**
     * Ensure fully student-driven modules are explicitly excluded.
     */
    public function test_student_driven_modules_are_excluded() {
        $safe_areas = \local_shula\service\payload_builder::get_safe_teacher_fileareas();

        // Wikis, Glossaries, and Databases rely heavily on student-generated content.
        $this->assertArrayNotHasKey('mod_wiki', $safe_areas, 'mod_wiki should be excluded to prevent student file leaks.');
        $this->assertArrayNotHasKey('mod_glossary', $safe_areas, 'mod_glossary should be excluded.');
        $this->assertArrayNotHasKey('mod_data', $safe_areas, 'mod_data should be excluded.');
        $this->assertArrayNotHasKey('mod_workshop', $safe_areas, 'mod_workshop should be excluded.');
    }

    /**
     * Assert the total number of allowed modules hasn't grown silently.
     * If a developer adds a new module support, they MUST update this test,
     * forcing a conscious security review.
     */
    public function test_allowlist_size_is_locked() {
        $safe_areas = \local_shula\service\payload_builder::get_safe_teacher_fileareas();
        
        $expected_count = 12; // Update this number if you intentionally add a new module
        
        $this->assertCount(
            $expected_count, 
            $safe_areas, 
            "The number of allowed modules has changed. If this is intentional, verify the new module does not leak student data, then update this test."
        );
    }
}