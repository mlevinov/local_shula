# API Client

**Namespace:** `\local_shula\service\client`

The API Client handles the low-level HTTP communication between Moodle and the Shula Django backend. It ensures all outbound payloads are securely signed and delivered reliably.

## Security & Authentication

Moodle webhooks are secured using an **HMAC-SHA256 signature**.

1.  The client takes the complete JSON payload.
2.  It generates a hash using the `shula_webhook_secret` configured in Moodle's Site Administration.
3.  The signature is attached to the outbound request via the `X-Moodle-Signature` HTTP header.
4.  The Django backend re-calculates the hash using its copy of the secret to verify the authenticity and integrity of the payload.

## Method: `send($payload)`

*   Injects the `issuer` (`$CFG->wwwroot`) into the payload so Django can identify the tenant.
*   Uses Moodle's native `\curl()` class to enforce SSRF protections.
*   Enforces strict production connection timeouts (5s connect, 60s execution) and SSL verification.
*   Throws a `\moodle_exception` if the backend returns an HTTP status code >= 400.

## Developer Debugging

If Moodle's debugging is set to `DEBUG_DEVELOPER`, the client will intercept the request and print a beautifully formatted JSON output of the exact payload being dispatched directly into the Moodle debug trace log. This is invaluable for local testing.
