<?php
/**
 * Admin settings for the local_shula plugin.
 *
 * Defines the configuration options available in the Moodle Site Administration
 * for the Shula AI tutor integration.
 *
 * Plugin: local_shula
 * Version: 2026051901 (Release 1.2.6)
 *
 * @package    local_shula
 * @copyright  2024 Shula AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_shula', get_string('pluginname', 'local_shula'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_shula/shula_institution_id',
        get_string('institution_id', 'local_shula'),
        get_string('institution_id_desc', 'local_shula'),
        '',
        PARAM_TEXT
    ));

    // Stored as a password/secret field so it's masked in the UI
    $settings->add(new admin_setting_configpasswordunmask(
        'local_shula/shula_webhook_secret',
        get_string('webhook_secret', 'local_shula'),
        get_string('webhook_secret_desc', 'local_shula'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_shula/shula_webhook_endpoint',
        get_string('webhook_endpoint', 'local_shula'),
        get_string('webhook_endpoint_desc', 'local_shula'),
        'https://example.shula-ai.com/api/v1/webhook/moodle/',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_shula/shula_lti_identifier',
        get_string('lti_identifier', 'local_shula'),
        get_string('lti_identifier_desc', 'local_shula'),
        'shula',
        PARAM_HOST
    ));

    // The AI Exclusion Tag Setting
    $settings->add(new admin_setting_configtext(
        'local_shula/shula_opt_out_tag',
        get_string('opt_out_tag', 'local_shula'),
        get_string('opt_out_tag_desc', 'local_shula'),
        'no-shula', // The default tag if the admin doesn't change it
        PARAM_TEXT
    ));
}