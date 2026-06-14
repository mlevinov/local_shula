# Shula AI Moodle Integration instructions (`local_shula`)

This document serves as the foundational instruction manual and context guide for developers and AI agents working on the `local_shula` Moodle plugin.

---

## 1. Project Overview

`local_shula` is a Moodle local plugin serving as the real-time bridge ("Door A") between the Moodle LMS and the Shula AI platform. Its primary role is to proactively push course structure, section information, module availability, visibility toggles, and teacher-authored material references to the Shula Django backend.

### Tech Stack & Compatibility
- **Backend:** PHP (OOP, matching Moodle standards).
- **Minimum Moodle Version:** 4.1 (`2022112800`).
- **Supported Moodle Versions:** 4.1 to 4.5.
- **Dependencies:** Standard Moodle APIs (Ad-hoc Task API, Event Observer API, Core Tag API, Privacy/GDPR API).
- **Documentation:** Python 3 + MkDocs (`mkdocs`, `mkdocs-material`).

---

## 2. Directory Structure

```text
/Users/michaellevinov/dev/local_shula/
├── .gitignore
├── GEMINI.md               <-- This Instruction & Guidelines file
├── mkdocs.yml              <-- MkDocs configuration
├── requirements.txt        <-- Python requirements for documentation
├── docs/                   <-- Source markdown files for MkDocs documentation
├── local_shula/            <-- Main plugin folder (should be symlinked or placed in Moodle's local/shula)
│   ├── CHANGELOG.md
│   ├── README.md
│   ├── settings.php        <-- Admin configuration UI page
│   ├── version.php         <-- Plugin version & metadata declarations
│   ├── classes/
│   │   ├── observer.php    <-- Core event observer callback methods
│   │   ├── privacy/
│   │   │   └── provider.php <-- Privacy API compliance (GDPR/IL)
│   │   ├── service/
│   │   │   ├── client.php  <-- HMAC-signed outbound HTTPS client wrapper
│   │   │   └── payload_builder.php <-- Secure JSON builders for courses/sections/modules/files
│   │   └── task/
│   │       ├── bulk_sync.php <-- Course-wide paginated bulk-sync task
│   │       ├── send_section_webhook.php <-- Outbound section webhook task
│   │       └── send_webhook.php <-- Outbound course/module webhook task
│   ├── db/
│   │   └── events.php      <-- Core event registration mapping
│   ├── lang/
│   │   ├── ar/             <-- Arabic language strings
│   │   ├── en/             <-- English language strings
│   │   └── he/             <-- Hebrew language strings
│   └── tests/
│       ├── client_test.php
│       ├── observer_test.php
│       ├── payload_builder_test.php
│       └── behat/
│           └── webhook_dispatch.feature
```

---

## 3. Architecture & Core Concepts

### 3.1. Asynchronous Decoupled Execution (Ad-Hoc Tasks)
To prevent blocking the Moodle UI thread during HTTP requests, operations are strictly asynchronous:
1. **Moodle Event occurs** (e.g., `course_module_created`, `course_section_updated`).
2. **`local_shula\observer` intercepts the event** and checks if Shula is active in the course (by matching the `shula_lti_identifier` configuration against LTI tools within that course).
3. **An Ad-Hoc Task is queued** to Moodle's native task manager (`\core\task\manager::queue_adhoc_task()`).
4. **Moodle Cron** processes and executes the queued task background.

### 3.2. Secure Webhook Dispatch (`local_shula\service\client`)
All outbound webhooks must be sent using the `client::send()` service:
- **HMAC Signature:** Payloads are signed with an HMAC-SHA256 signature calculated over the JSON-encoded payload and the configured `shula_webhook_secret`. The signature is transmitted in the `X-Moodle-Signature` header.
- **Replay Protection:** outbound payloads include a `sent_at` timestamp parameter and a `X-Signature-Version: v1` header.
- **SSRF Safety:** Relies on Moodle's built-in safe `curl` utility.
- **Developer Debug Logging:** Beautifully formatted, pretty printed JSON payloads are outputted to `mtrace` logs *only* when Moodle's Developer Debugging mode is enabled (`DEBUG_DEVELOPER`).

### 3.3. Student Privacy & Security Guardrails
We operate under a strict **Zero-PII leakage policy**.
- **No Student Data:** The plugin does not read, cache, or transmit names, grades, emails, or student-submitted content.
- **Teacher-Authored Whitelist:** The payload builder implements a strict allowlist of "safe" teacher-authored fileareas (e.g., `mod_resource/content`, `mod_assign/introattachment`). It ignores student-contributed areas like forum attachments, database submissions, wiki edits, and assignment submissions.
- **AI Opt-Out Tag:** Teachers can attach a core Moodle tag (default `no-shula`, configurable) to any activity/file. The payload builder detects this tag, sets `ai_restricted = true`, and completely excludes any material files from the payload.
- **GDPR Compliance:** Extends `\core_privacy\local\metadata\provider` to correctly document all data transfer and transparency compliance requirements.

### 3.4. Restore Storm Protection
During course restores or plugin installations, thousands of event triggers can occur simultaneously. To prevent crashing or spamming the Shula Django backend:
- The observer utilizes the `is_restoring()` helper to check Moodle's active `$CFG->restoringcourse` and `$CFG->installing` states.
- Normal module and section event hooks are immediately aborted during restores.
- A single `local_shula\task\bulk_sync` task is queued on the `course_restored` event to sync the entire course layout in one operation.

### 3.5. Paginated Course Bulk Sync
For full course synchronizations, the payload can become extremely large:
- The `bulk_sync` task segments course sections into chunks of **5 sections per HTTP request**.
- Webhook payloads include metadata indicating the `chunk_index` and `total_chunks` so the backend can assemble the structure sequentially.

---

## 4. Building, Running, and Testing

### 4.1. Requirements
Ensure the development environment has:
- PHP 8.1+
- Moodle 4.1 - 4.5
- Python 3+ (with `requirements.txt` installed via `pip install -r requirements.txt`)

### 4.2. Running Documentation
The comprehensive docs are compiled using MkDocs:
```bash
# Run local dev server for docs
cd local_shula
mkdocs serve
```
Then open `http://127.0.0.1:8000` in your web browser.

### 4.3. Run PHPUnit Unit Tests
Make sure PHPUnit is initialized in your Moodle development environment:
```bash
# Initialize PHPUnit (run from Moodle root)
php admin/tool/phpunit/cli/init.php

# Run local_shula unit tests
vendor/bin/phpunit --group local_shula
```

### 4.4. Run Behat Acceptance Tests
Acceptance tests ensure end-to-end integration flows work correctly:
```bash
# Initialize Behat (run from Moodle root)
php admin/tool/behat/cli/init.php

# Run Behat tests for local_shula
vendor/bin/behat --tags @local_shula
```

---

## 5. Coding Conventions & Best Practices

- **Strict Moodle Coding Style:** Follow Moodle's coding guidelines (e.g., naming classes lower_case, spaces instead of tabs, specific PHPDoc headers, and no inline HTML in processing classes).
- **Namespacing:** All class files must match the autoloading structure: `local_shula\<subnamespace>`.
- **Database Safety:** Always use placeholder parameter binding in SQL queries. Securely escape all user/admin-provided search wildcards using `$DB->sql_like_escape()` to prevent DB matching manipulation.
- **Task Debouncing:** Before queuing a task, always query Moodle's `task_adhoc` table to ensure an identical task is not already pending for the same parameters.
- **Graceful Error Handling:**
  - Connection or 5xx server issues throw exceptions so Moodle's task manager retries them.
  - Client-side payload validation issues (HTTP 400-499) are treated as non-recoverable: they are caught, logged, and the task exits gracefully to prevent stalling Moodle's background task runner with infinite retries.
- **Static Request Caching:** Cache database queries (like `is_shula_active_in_course`) using static variables to avoid redundant queries during the lifecycle of a single HTTP request.
- **Snapshot Usage on Deletions:** Because deletions wipe records from the database before the adhoc task runs, observers must extract database snapshots (e.g., using `$event->get_record_snapshot()`) and pass them inside the adhoc task custom data to allow payloads to be accurately constructed.
