# Shula AI Moodle Integration

This is a local Moodle plugin designed to seamlessly and securely sync course structure, module availability, and file content with the Shula AI platform.

## Security & Privacy Architecture

This plugin was built with strict adherence to institutional data privacy and server performance:

* **Zero User Data:** The payload builder extracts only course structure, file metadata, and module availability dates. It does not access, read, or transmit student data, enrollments, grades, or forum posts.
* **Moodle Privacy API Compliant:** This plugin implements the `null_provider` interface, explicitly registering with Moodle's Privacy Registry that no local personal user data is stored or exported by this component.
* **Asynchronous Processing:** All HTTP webhooks are dispatched via Moodle's native Ad-Hoc Task API (`\core\task\manager`). Syncing operations will never block or slow down the Moodle UI for teachers.
* **Cryptographic Signatures:** Every webhook request is signed with an HMAC-SHA256 signature (`X-Moodle-Signature`) to guarantee authenticity between Moodle and the Shula backend.
* **AI Opt-Out Tag:** Teachers maintain full copyright control. By adding the configured tag (default: `no-shula`) to any file, page, or module, the plugin will immediately abort reading its contents and mark it as restricted from AI ingestion.
* **SSRF Protection:** External requests utilize Moodle's core `\curl` wrapper with built-in SSRF protections and strict SSL verification.

## Requirements

* Moodle 4.1 or higher (Tested up to Moodle 4.5)
* PHP 8.0+

## Installation & Configuration

The integration requires a two-way authentication bridge. Moodle needs a secret to sign outgoing webhooks, and Shula needs a token to securely download physical files.

### Step 1: Install and Configure the Plugin (Webhook Push)

1. Ensure your Moodle platform is registered as an 'Institution' with the Shula AI team. You will receive a unique **Institution ID** and a **Shula API Token** (Webhook Signing Secret).
2. Install the plugin via the Moodle Site Administration interface or extract the source folder directly into your Moodle's `local/shula` directory.
3. Navigate to **Site Administration > Plugins > Local plugins > Shula AI** and enter the required credentials. Note: There are no default values; you must configure these manually to prevent accidental staging leaks:
* **Shula Institution ID:** Your unique tenant UUID provided by Shula.
* **Webhook Signing Secret:** The secure HMAC-SHA256 secret provided by Shula.
* **Webhook Endpoint:** The full URL to the webhook receiver. For production, use: `https://api.shula-ai.com/api/v1/webhook/moodle/`
* **LTI Identifier:** A domain or URL fragment used to identify the Shula LTI tool in courses (e.g., `shula-ai` or `api.shula-ai.com`). This must match the URL used when you registered the Shula LTI 1.3 tool in Moodle.



### Step 2: Generate the REST API Token (File Pull)

Because course files are protected behind Moodle's authentication wall, Shula requires a standard Web Services token to securely download PDFs, documents, and other physical files referenced in the webhooks.

1. Navigate to **Site Administration > Server > Web services > Manage tokens**.
2. Create a new token for the designated Shula API integration user.
3. Securely transmit this generated token back to your Shula onboarding representative so it can be added to your institutional profile.

## Debugging & Logs

In production, the plugin runs silently. If you need to inspect the outgoing JSON payloads for network troubleshooting, enable **Developer Debugging** in Moodle (`DEBUG_DEVELOPER`). The plugin will then output fully formatted JSON payloads to the Moodle scheduled task logs.