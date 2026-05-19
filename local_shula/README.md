# Shula AI Moodle Integration (local_shula)

**Version:** 1.2.4 (2026051803) | **Requires:** Moodle 4.1+

This local Moodle plugin serves as the real-time bridge ("Door A") between the Moodle LMS and the Shula AI platform. It proactively "pushes" course structure, module availability, visibility toggles, and file metadata to the Shula backend using a highly optimized, asynchronous architecture.

---

## Documentation

Comprehensive developer documentation for this plugin, including architectural diagrams, service explanations, and webhook payload structures, has been generated using MkDocs.

To view the documentation locally:
```bash
cd local_shula
mkdocs serve
```
Then visit `http://127.0.0.1:8000` in your browser.

---

## Architecture & Features

This plugin implements the **Hierarchical v2.0 Ingestion Architecture**.

* **Proactive Webhooks:** Listens to native Moodle events (`course_module_created`, `course_section_updated`, etc.) and pushes changes instantly.
* **Cascading Visibility:** AI safeguards are driven by Moodle's native visibility (`is_visible`) toggles. If a teacher hides a Section or Module, the plugin alerts Django to instantly "scrub" or deactivate the associated vectors.
* **Restore Storm Protection:** Intelligently detects course restores and plugin installations, suppressing thousands of individual file events in favor of a single `bulk_sync` task to protect performance.
* **Decoupled Execution:** All HTTP webhooks are dispatched via Moodle's native Ad-Hoc Task API (`\core\task\manager`) handled by the `moodle_cron` background process. Operations never block the Moodle UI.
* **Hierarchical Payloads:** JSON payloads strictly follow the `Course -> Section -> Module -> File/Content` structure so the AI always understands the exact context.

## Security & Privacy

* **Zero User Data:** The payload builder extracts only course structure and file metadata. It does not access, read, or transmit student data, enrollments, grades, or forum posts.
* **Cryptographic Signatures:** Every webhook request is signed with an HMAC-SHA256 signature (`X-Moodle-Signature`) to guarantee authenticity between Moodle and the Shula backend.
* **AI Opt-Out Tag:** Teachers maintain full control. By adding the configured tag (default: `no-shula`) to any file or module via Moodle's Core Tag API, the plugin immediately aborts indexing its contents.
* **SSRF Protection:** External requests utilize Moodle's core `\curl` wrapper with built-in SSRF protections and strict SSL verification.

---

## Installation & Configuration

The integration requires a two-way authentication bridge: Moodle needs a secret to sign outgoing webhooks, and Shula needs a token to securely download physical files.

### Step 1: Install and Configure the Plugin (Webhook Push)

1. Ensure your Moodle platform is registered as an 'Institution' with the Shula AI team. You will receive a unique **Institution ID** and a **Shula API Token** (Webhook Signing Secret).
2. Install the plugin via the Moodle Site Administration interface or place the folder in your Moodle's `local/shula` directory.
3. Navigate to **Site Administration > Plugins > Local plugins > Shula AI** and enter the credentials:
   * **Shula Institution ID:** Your unique tenant UUID provided by Shula.
   * **Webhook Signing Secret:** The secure HMAC-SHA256 secret provided by Shula.
   * **Webhook Endpoint:** The full URL to the webhook receiver. For production, use: `https://api.shula-ai.com/api/v1/webhook/moodle/`
   * **LTI Identifier:** A domain or URL fragment used to identify the Shula LTI tool in courses (e.g., `shula` or `shula-ai.com`). The plugin uses this to know if it should sync a given course.

### Step 2: Generate the REST API Token (File Pull)

Because course files are protected behind Moodle's authentication wall, Shula requires a standard Web Services token to securely download PDFs and documents referenced in the webhooks.

1. Navigate to **Site Administration > Server > Web services > Manage tokens**.
2. Create a new token for the designated Shula API integration user.
3. Securely transmit this generated token back to your Shula onboarding representative.

---

## Debugging

In production, the plugin runs silently. If you need to inspect the outgoing JSON payloads for network troubleshooting, enable **Developer Debugging** in Moodle (`DEBUG_DEVELOPER`). 

The `client` class will intercept the request and print beautifully formatted JSON payloads directly into the Moodle debug trace log (and the scheduled task logs).