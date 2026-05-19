# Shula Moodle Plugin

Welcome to the documentation for the `local_shula` Moodle plugin.

## Overview

**Shula** is a multi-tenant LTI 1.3 Advantage tool designed for academic institutions. It provides an AI tutor integrated directly into Moodle courses. This local plugin acts as the bridge between the Moodle LMS and the Shula AI backend.

### Key Features

*   **Real-time Webhooks (`Door A`):** Proactively "pushes" content changes (creations, updates, deletions) to the Django backend using a Hierarchical v2.0 Ingestion model.
*   **Cascading Visibility:** AI safeguards are driven by Moodle's visibility toggles. If a Section or Module is hidden, the plugin alerts the backend to instantly "scrub" or deactivate the associated AI vectors.
*   **Restore Storm Protection:** Detects course restores and suppresses individual file events in favor of a single Bulk Sync task to prevent performance degradation.
*   **Decoupled Execution:** Utilizes Moodle's `adhoc_tasks` triggered by Event Observers to ensure the Moodle UI remains fast and responsive.

### Version Information
*   **Component:** `local_shula`
*   **Release:** 1.2.6 (2026051901)
*   **Requirements:** Moodle 4.1+

---

## Getting Started

Navigate through the documentation to understand the inner workings of the plugin:

*   [**Architecture**](architecture.md): Understand the high-level design and ingestion workflow.
*   [**Services**](services/payload_builder.md): Learn how JSON payloads are constructed and sent securely.
*   [**Tasks & Webhooks**](tasks/observer.md): Explore how Moodle events are captured and processed in the background.
