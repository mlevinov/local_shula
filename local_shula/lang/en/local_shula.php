<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Shula AI';
$string['institution_id'] = 'Shula Institution ID';
$string['institution_id_desc'] = 'The exact UUID assigned to this tenant in the Shula Django backend.';
$string['webhook_secret'] = 'Webhook Signing Secret';
$string['webhook_secret_desc'] = 'The cryptographic secret used to generate the HMAC-SHA256 signature for outgoing webhooks. This must exactly match the Webhook Signing Secret generated for this institution.';
$string['webhook_endpoint'] = 'Webhook Endpoint';
$string['webhook_endpoint_desc'] = 'The full URL of the Shula webhook (e.g., <https://api.shula.ai/api/v1/webhook/moodle/>).';
$string['lti_identifier'] = 'Shula LTI Tool Identifier';
$string['lti_identifier_desc'] = 'The domain or URL fragment used to identify the Shula LTI tool in courses (e.g., "api.shula.ai" for production, or "host.docker.internal" for dev).';
$string['opt_out_tag'] = 'AI Exclusion Tag';
$string['opt_out_tag_desc'] = 'Teachers can add this specific tag to any Moodle module or file to block Shula from reading its content. Useful for copyrighted PDFs or private exam answers.';
$string['task_send_webhook'] = 'Shula AI: Send Individual File Webhook';
$string['task_bulk_sync'] = 'Shula AI: Process Course Bulk Sync';
$string['privacy:metadata:reason'] = 'The Shula AI plugin does not store personal user data locally. It transmits course structure and file context to the Shula external service to power the AI tutor.';