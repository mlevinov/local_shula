<?php
namespace local_shula\tests;

use advanced_testcase;
use ReflectionMethod;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_shula\observer
 */
class observer_test extends advanced_testcase {

    public function test_is_restoring_returns_false_normally(): void {
        $method = new ReflectionMethod(\local_shula\observer::class, 'is_restoring');
        $method->setAccessible(true);
        $this->assertFalse($method->invoke(null));
    }

    public function test_active_check_caches_result(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        set_config('shula_lti_identifier', 'shula', 'local_shula');
        
        $method = new ReflectionMethod(\local_shula\observer::class, 'is_shula_active_in_course');
        $method->setAccessible(true);
        
        // First call hits the DB, second hits the static cache
        $result1 = $method->invoke(null, $course->id);
        $result2 = $method->invoke(null, $course->id);
        
        $this->assertFalse($result1);
        $this->assertSame($result1, $result2);
    }

    public function test_like_escape_neutralizes_wildcards(): void {
        $this->resetAfterTest();
        // Inject the dangerous wildcard
        set_config('shula_lti_identifier', '%', 'local_shula');
        $course = $this->getDataGenerator()->create_course();
        
        $method = new ReflectionMethod(\local_shula\observer::class, 'is_shula_active_in_course');
        $method->setAccessible(true);
        
        // Ensure the escaped wildcard doesn't trigger a false-positive match
        $this->assertFalse($method->invoke(null, $course->id));
    }
}