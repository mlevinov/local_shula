<?php
namespace local_shula\tests;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_shula\service\client
 */
class client_test extends advanced_testcase {

    public function test_send_aborts_without_secret(): void {
        $this->resetAfterTest();
        set_config('shula_webhook_secret', '', 'local_shula');
        
        $result = \local_shula\service\client::send(['event_type' => 'test']);
        $this->assertFalse($result);
    }

    public function test_send_throws_on_http_400(): void {
        $this->expectException(\moodle_exception::class);
        $this->resetAfterTest();
        
        set_config('shula_webhook_secret', 'test_secret', 'local_shula');
        // Point to a public mock API that returns a 400 Bad Request
        set_config('shula_webhook_endpoint', 'https://httpstat.us/400', 'local_shula'); 
        
        \local_shula\service\client::send(['event_type' => 'test']);
    }
}