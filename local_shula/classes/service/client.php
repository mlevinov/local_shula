<?php
/**
 * API client for the local_shula plugin.
 *
 * This class handles the low-level communication with the Shula AI tutor backend,
 * including HMAC signature generation and HTTP requests.
 *
 * @package    local_shula
 * @copyright  2024 Shula AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shula\service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Class client
 *
 * Sends JSON payloads to the configured Shula webhook endpoint.
 * Plugin: local_shula
 * Version: 2026051901 (Release 1.2.6)
 */
class client {
    
    /**
     * Sends a JSON payload to the Shula AI tutor backend.
     *
     * Generates an HMAC-SHA256 signature for the payload and sends it via POST.
     *
     * @param array $payload The data to be sent.
     * @return bool True on success, false otherwise.
     * @throws \moodle_exception If the webhook request fails.
     */
    public static function send($payload) {
        global $CFG;

        $secret = get_config('local_shula', 'shula_webhook_secret');
        $endpoint = get_config('local_shula', 'shula_webhook_endpoint');

        if (!$secret || !$endpoint) {
            mtrace('Shula Webhook: Configuration missing. Aborting sync.');
            return false;
        }

        $payload['issuer'] = $CFG->wwwroot;
        $payload['sent_at'] = time();
        $json_payload = json_encode($payload);
        $signature = hash_hmac('sha256', $json_payload, $secret);

        // ====================================================================
        // SHULA DEBUG: Only log full JSON payload if Developer Debugging is ON
        // ====================================================================
        if (debugging('', DEBUG_DEVELOPER)) {
            $pretty_payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            mtrace("\n=======================================================");
            mtrace("[SHULA] [" . date('Y-m-d H:i:s') . "] DISPATCH: {$payload['event_type']}");
            mtrace("=======================================================");
            mtrace($pretty_payload);
            mtrace("=======================================================\n");
        } else {
            // Lightweight log for production
            mtrace("Shula Webhook: Dispatching '{$payload['event_type']}' event to Django at {$endpoint}...");
        }
        // ====================================================================

        // Standard Moodle cURL (SSRF protection active)
        $curl = new \curl();
        
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('X-Signature-Version: v1'); // Future-proofing
        $curl->setHeader('X-Moodle-Signature: ' . $signature);
        
         // Strict production connection options
        $options = [
            'CURLOPT_TIMEOUT'        => 60,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_SSL_VERIFYPEER' => true, 
            'CURLOPT_SSL_VERIFYHOST' => 2,
        ];
        
        $response = $curl->post($endpoint, $json_payload, $options);
        
        $info = $curl->get_info();
        if ($info['http_code'] >= 400) {
            mtrace("Shula Webhook Error[HTTP {$info['http_code']}]: " . $response);
            throw new \moodle_exception('webhook_failed', 'local_shula', '', $info['http_code']);
        }
        
        return true;
    }
}