# Testing Strategy

The `local_shula` plugin maintains a robust testing suite to ensure data integrity, security, and performance. 

## PHPUnit Tests

Moodle's native PHPUnit framework is used for unit and integration testing.

### Security Validation (`tests/payload_builder_test.php`)

This test suite focuses on the `payload_builder` and ensures that the security boundaries defined for AI ingestion are strictly enforced.

*   **Student Privacy Protection:** Asserts that student-authored fileareas (like forum attachments and assignment submissions) are NEVER included in the ingestion allowlist.
*   **Allowlist Lock:** A structural test that monitors the size of the allowed module list. If a developer adds support for a new module, this test fails, forcing a conscious security review of the new module's fileareas.
*   **Module Exclusion:** Verifies that fully student-driven modules (Wikis, Glossaries, Databases) are explicitly excluded from physical file ingestion.

## Running Tests

To run the plugin's test suite, you must have a configured Moodle PHPUnit environment.

```bash
# From your Moodle root directory
vendor/bin/phpunit --group local_shula
```

## Manual Verification

For manual verification of outgoing payloads:
1. Enable **Developer Debugging** in Moodle.
2. Trigger an event (e.g., update a module description).
3. Check the Moodle cron logs or the Ad-Hoc task output in the site administration. The `client` service will print the full, signed JSON payload to the debug trace.
