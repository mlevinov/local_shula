# Testing Guidelines

The `local_shula` plugin prioritizes reliability and security. It includes both high-coverage PHPUnit tests and Behat acceptance tests to guarantee security lockouts, privacy compliance, and proper background execution.

---

## 1. PHPUnit Unit Testing

The PHPUnit suite validates class methods, caches, and security guardrails.

### 1.1. Setup & Execution
To run unit tests, ensure your Moodle development environment has PHPUnit configured:

```bash
# 1. Initialize PHPUnit environment (run from Moodle web root directory)
php admin/tool/phpunit/cli/init.php

# 2. Run the local_shula test group
vendor/bin/phpunit --group local_shula
```

### 1.2. Key Test Cases

#### API Client Tests (`tests/client_test.php`)
* **`test_send_aborts_without_secret`:** Confirms that the client aborts sending and returns `false` if the webhook secret is missing from settings.
* **`test_send_throws_on_http_400`:** Verifies that a `\moodle_exception` is thrown when the Shula endpoint returns a client error (HTTP 400), ensuring the calling task handles bad payloads gracefully.

#### Event Observer Tests (`tests/observer_test.php`)
* **`test_is_restoring_returns_false_normally`:** Asserts that under normal runtime conditions, the restore check is inactive.
* **`test_active_check_caches_result`:** Proves that checking LTI activation inside a course caches the result in memory, preventing duplicate database lookups during single page life cycles.
* **`test_like_escape_neutralizes_wildcards`:** Validates that if an administrator sets a wild wildcard (like `%`) as the LTI tool identifier, it is securely escaped and does not match random courses, mitigating database search injection vectors.

#### Payload Builder & Privacy Tests (`tests/payload_builder_test.php`)
This is the security gatekeeper suite. It verifies that **no student PII** can ever leak:
* **`test_high_risk_modules_are_secured`:** Ensures that for modules like Forum and Assignment, only safe teacher-created fields (such as `introattachment`) are allowed, while student-contributed file areas (like forum `post`, forum `attachment`, assignment `submission_files`, or `feedback`) are strictly excluded.
* **`test_student_driven_modules_are_excluded`:** Asserts that student-driven modules (such as Wikis, Glossaries, Workshops, and Databases) are entirely absent from the file-mapping configuration, preventing unintended student file leaks.
* **`test_allowlist_size_is_locked`:** Locks the total size of the teacher filearea allowlist to exactly `12` components. If a developer attempts to add a new module support, they must intentionally modify this test, forcing a conscious security/privacy audit.
* **`test_no_shula_tag_excludes_files`:** Validates that applying the `no-shula` tag to an activity successfully triggers `ai_restricted = true` and wipes its files from the generated JSON payload.
* **`test_extract_unlock_date_handles_nested_rules`:** Ensures that recursive parsing of Moodle availability constraints correctly walks nested rule trees and extracts the correct Unix timestamp.

---

## 2. Behat Acceptance Testing

Behat tests verify end-to-end integration flows (e.g., verifying that making an edit in the Moodle UI correctly triggers and queues an ad-hoc webhook task).

### Setup & Execution

```bash
# 1. Initialize Behat environment (run from Moodle web root directory)
php admin/tool/behat/cli/init.php

# 2. Run Behat scenarios tagged for local_shula
vendor/bin/behat --tags @local_shula
```

Our Behat feature (`tests/behat/webhook_dispatch.feature`) tests end-to-end UI actions, ensuring observers fire and tasks are scheduled cleanly in the background.
