<?php
/**
 * Event observers for the local_shula plugin.
 *
 * This file registers the various Moodle events that the plugin listens to
 * and maps them to the observer class methods.
 *
 * Plugin: local_shula
 * Version: 2026051803 (Release 1.2.4)
 *
 * @package    local_shula
 * @copyright  2024 Shula AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\course_module_created',
        'callback'    => '\local_shula\observer::course_module_created',
        'internal'    => false,
    ],
    [
        'eventname'   => '\core\event\course_module_deleted',
        'callback'    => '\local_shula\observer::course_module_deleted',
        'internal'    => false,
    ],
    [
        'eventname'   => '\core\event\course_restored',
        'callback'    => '\local_shula\observer::course_restored',
        'internal'    => false,
    ],
    [
        'eventname'   => '\core\event\course_updated',
        'callback'    => '\local_shula\observer::course_updated',
        'internal'    => false,
    ],
    [
        'eventname'   => '\core\event\course_created',
        'callback'    => '\local_shula\observer::course_created',
        'internal'    => false,
    ],
    [
        'eventname'   => '\core\event\course_section_created',
        'callback'    => '\local_shula\observer::course_section_created',
        'internal'    => false,
    ],
    [
        'eventname'   => '\core\event\course_section_updated',
        'callback'    => '\local_shula\observer::course_section_updated',
        'internal'    => false,
    ],
    [
        'eventname'   => '\core\event\course_section_deleted',
        'callback'    => '\local_shula\observer::course_section_deleted',
        'internal'    => false,
    ],
    [
        'eventname'   => '\core\event\course_module_updated',
        'callback'    => '\local_shula\observer::course_module_updated',
        'internal'    => false,
    ],
    [
        'eventname'   => '\core\event\course_deleted',
        'callback'    => '\local_shula\observer::course_deleted',
        'internal'    => false,
    ],
];