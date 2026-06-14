# Secure Webhook API Client

The `client` class (`local_shula\service\client`) handles the outbound network transport layer of `local_shula`. It ensures that all JSON payloads are delivered to the Shula AI Django backend securely, utilizing cryptographic signatures, strict TLS validation, and replay protections.

---

## 1. Webhook Signature Generation

Every outbound HTTP POST request is signed with a cryptographic signature to verify authenticity and prevent tampering:

### 1.1. HMAC-SHA256 Algorithm
1. The JSON payload is constructed and packed:
   ```php
   $payload['issuer'] = $CFG->wwwroot;
   $payload['sent_at'] = time(); // Replay protection timestamp
   $json_payload = json_encode($payload);
   ```
2. A hash-based message authentication code (HMAC-SHA256) is calculated over the raw, unescaped JSON string using the shared `shula_webhook_secret` as the key:
   ```php
   $signature = hash_hmac('sha256', $json_payload, $secret);
   ```
3. The resulting signature is sent in the custom **`X-Moodle-Signature`** HTTP header.

### 1.2. HTTP Header Headers
* **`Content-Type`:** `application/json`
* **`X-Signature-Version`:** `v1` (Provides backward compatibility during updates)
* **`X-Moodle-Signature`:** The calculated HMAC-SHA256 signature string.

---

## 2. Secure Transport Parameters

The client utilizes Moodle's built-in `\curl` utility, which natively enforces internal safety checks (like preventing SSRF attacks against local loopback interfaces).

To ensure enterprise-grade network security, the client applies the following rigid curl options:
* **Connection Timeout (`CURLOPT_CONNECTTIMEOUT`):** `5 seconds` (Prevents blocking background threads if the Django backend is offline).
* **Execution Timeout (`CURLOPT_TIMEOUT`):** `60 seconds` (Provides sufficient time for large file-sync payloads to complete).
* **TLS Peer Verification (`CURLOPT_SSL_VERIFYPEER`):** `true` (Enforces TLS certificate chain validation).
* **TLS Host Verification (`CURLOPT_SSL_VERIFYHOST`):** `2` (Verifies that the target hostname matches the SSL certificate common name).

---

## 3. Developer Debug Logging

Logging full JSON bodies in production logs can clutter system diagnostics. `local_shula` implements **Dynamic Level Logging**:

* **Standard Mode:** Outputs a lightweight single-line trace to Moodle's `mtrace` log:
  ```text
  Shula Webhook: Dispatching 'file_created' event to Django at https://api.shula.ai/webhook/...
  ```
* **Developer Debug Mode:** When Moodle's Developer Debugging option is active (`DEBUG_DEVELOPER`), the client pretty-prints the complete formatted JSON schema payload directly to `mtrace` logs. This allows developers to easily inspect payload boundaries and file metadata structures during testing or local development.

---

## 4. Response & Retries Handling

Upon receiving the response, the client checks the HTTP status code:
* **HTTP 2xx:** Considered a success.
* **HTTP 4xx (Client Errors):** Treated as a fatal/unrecoverable schema error. The calling task captures this exception, logs the event details, and discards the task to prevent infinite retry loops in Moodle's background queue.
* **HTTP 5xx / Connection Timeout:** Treated as temporary server-side issues. The client throws a `\moodle_exception` which causes Moodle's task manager to reschedule and retry the dispatch later with exponential backoff.
