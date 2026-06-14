# Changelog

All notable changes to the `local_shula` plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to Semantic Versioning.

## [1.2.6] - 2026-06-14

### Security

* **[S1] SQL Escape Fix:** Neutralized a potential DB wildcard matching flood by wrapping the LTI identifier in `$DB->sql_like_escape()`. Enforced `PARAM_HOST` in the admin settings to properly sanitize inputs.


* **[S5/S6] Replay Attack Defense:** Injected `sent_at` timestamps into the HMAC signing payload. Added `X-Signature-Version: v1` headers to prevent intercepted payloads from being maliciously replayed.


* **[S3] Task Debouncing:** Implemented duplicate suppression in `queue_webhook_task` and `queue_course_task` to prevent task queue floods.



### Privacy & Compliance

* **[P1] Privacy API Overhaul:** Converted the plugin from a `null_provider` to a fully compliant `metadata\provider`. Added complete `external_location_link` declarations for cross-border data transfer compliance. Added corresponding privacy API language strings in English, Hebrew, and Arabic.


* **[P2] Snapshot Deletion Guard:** Implemented a pre-deletion snapshot inspection to ensure `course_deleted` webhooks are only dispatched if the Shula LTI tool was genuinely active in the course. This prevents metadata leakage for unrelated courses.



### Fixed

* **[D1] Runtime Resilience:** Added defensive `NULL` checks for module instances in `payload_builder.php`. This prevents PHP 8 runtime errors if a teacher deletes an activity while a webhook task is pending in the queue.


* **[D4] Intelligent Task Queuing:** Wrapped the HTTP client dispatcher in `try/catch` blocks across all adhoc tasks. The queue now intelligently drops `4xx` validation errors to prevent infinite stalls, while throwing `5xx` errors back to Moodle for standard retry scheduling.


* **[S2] Translation Strings:** Added the missing `webhook_failed` error strings across the English, Hebrew, and Arabic language packs.


* **[D9] Versioning & Support:** Explicitly declared Moodle 4.1-4.5 support via `$plugin->supported = [401, 405]` in `version.php`.


* **[Pf1] Performance & Memory Limits:** Added pagination to `bulk_sync.php` to process courses in batches of 5 sections. This limits the size of the generated payload to prevent memory limit crashes.



### Changed

* **[D2] Event Optimization:** Stripped out the empty `course_created` listener from `db/events.php` to reduce site-wide processing overhead.


* **[D7] Log Cleanliness:** Removed non-standard emojis from `mtrace` logs in `client.php`, replacing them with a `[SHULA]` prefix to ensure compatibility with strict enterprise log aggregation tools.



---

### Unresolved / Pending Updates (Audit Findings Not Yet Applied)

*The following items were listed in the draft changelog or audit report but are not currently reflected in the updated codebase:*

* **[D5] Safety Defaults:** The default endpoint in `settings.php` still points to the production URL (`[https://shula-ai.com/api/v1/webhook/moodle/](https://shula-ai.com/api/v1/webhook/moodle/)`) instead of being left empty or using a safe placeholder.


* **[D3] Version Synchronization:** While `version.php` correctly declares release `1.2.6`, the PHPDoc blocks across the plugin's classes still incorrectly display `Version: 2026051803 (Release 1.2.4)`.


* **[S4] Payload Size Validation:** There is currently no `strlen()` check on the final JSON payload in `client.php` before the POST request is made.


* **[T1-T4] Automated Testing:** The recommended PHPUnit tests (`observer_test.php`, `client_test.php`) and Behat scenarios are missing from the test suite.