# Shula AI Moodle Integration (`local_shula`)

Welcome to the official developer and administrator documentation for the `local_shula` Moodle plugin.

`local_shula` serves as the real-time, asynchronous bridge ("Door A") between the Moodle Learning Management System (LMS) and the Shula AI platform. Its primary role is to proactively push course structure, section information, module availability, visibility toggles, and teacher-authored material references to the Shula Django backend.

---

## Core Features

- **Asynchronous Decoupled Execution:** Avoids blocking the Moodle UI thread by offloading HTTP dispatches to Moodle's native Ad-hoc Task API.
- **Secure Webhook Dispatch:** All outbound payloads are HMAC-SHA256 signed with replay protection and safe DNS/SSRF resolution using Moodle's built-in curl utility.
- **Strict Student Privacy Guardrails:** Strict zero-PII leakage policy. The plugin never reads, caches, or transmits student names, grades, emails, or student-submitted content.
- **Teacher-Authored Whitelist:** Only syncs folders and files uploaded by teachers, completely ignoring forums, wikis, glossaries, database submissions, and assignment uploads from students.
- **AI Opt-Out Tag:** Teachers can attach a native Moodle tag (default `no-shula`) to any activity or section to set `ai_restricted = true` and exclude its files from ingestion.
- **Restore Storm Protection:** Prevents spamming the Shula backend with event webhooks during course restore or plugin installation, seamlessly falling back to a single course-wide paginated bulk-sync task.
- **Paginated Course Bulk Sync:** Syncs full course hierarchies in optimized chunks of 5 sections per HTTP request to ensure massive courses do not hit timeout or payload limits.

---

## System Requirements

- **PHP:** 8.1 or higher.
- **Moodle:** 4.1 to 4.5.
- **Moodle PHPUnit Environment** (optional, for running tests).
- **Python 3+** (optional, for running this MkDocs documentation site).

---

## Installation & Setup

1. Copy or symlink the `local_shula` folder into your Moodle installation's `local/` directory:
   ```bash
   ln -s /path/to/local_shula /path/to/moodle/local/shula
   ```
2. Run Moodle database upgrades via the admin interface or CLI:
   ```bash
   php admin/cli/upgrade.php
   ```
3. Navigate to **Site administration** > **Plugins** > **Local plugins** > **Shula AI Integration** (`/local/shula/settings.php`) to configure:
   - **Institution ID:** Your unique Shula AI account identifier.
   - **LTI Tool Identifier:** The name or URL matching the Shula LTI activity in courses.
   - **Webhook Endpoint:** The Django receiver URL.
   - **Webhook Secret:** The secret key used for HMAC signature generation.
   - **Opt-Out Tag:** The tag used by teachers to restrict content (defaults to `no-shula`).
