<?php
namespace local_shula\privacy;
defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata:reason'; // You will need to add this string to your lang file
    }
}