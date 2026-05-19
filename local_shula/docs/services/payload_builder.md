# Payload Builder Service

**Namespace:** `\local_shula\service\payload_builder`

The payload builder is the core data transformation engine of the plugin. It translates Moodle's complex internal data structures (Courses, Sections, Modules, and Files) into clean, deeply nested JSON schemas expected by the Shula Django backend.

## Key Methods

### `build_course_tree($courseid)`
Generates the complete, nested JSON tree for a Bulk Sync event.
*   **Hierarchy:** `Course -> Section -> Module -> File/Content`
*   **Returns:** A full array representing the entire course structure.

### `build_course_item($course)`
Builds the top-level course schema, extracting metadata like visibility, summary format, and language.

### `build_section_item($section_info, $modinfo, $include_modules)`
Builds the schema for a specific course section.
*   Extracts `unlock_date` from Moodle's complex availability rules.
*   Can optionally nest child modules inside the section array.

### `build_module_item($cm)`
Builds the schema for a course module natively.
*   **AI Restriction Check:** Queries Moodle's Core Tag API to see if the module has an opt-out tag (e.g., `no-shula`). If tagged, the module's files are NOT included in the payload.
*   Aggregates both physical files and virtual content.

### `build_virtual_items_from_cm($cm, $instance)`
Dynamically constructs items for native Moodle instances that don't have physical files attached.
*   Handles `page`, `label`, and `url` modules.
*   Generates a virtual text item for module introductions/descriptions.

### `build_file_items_from_cm($cm)`
Queries Moodle's File Storage API to extract metadata and generate secure `pluginfile` URLs for physical files attached to a context.
*   **Teacher-Authored Security:** Enforces a strict allowlist of teacher-only file areas (e.g., `mod_resource/content`, `mod_assign/introattachment`). Deliberately excludes student-authored attachments from modules like Wikis, Glossaries, and Forums to protect the integrity of the AI's knowledge base.

### `get_safe_teacher_fileareas()`
Returns the hardcoded mapping of Moodle components to their "safe" (teacher-authored) fileareas. 
*   **Security Principle:** This acts as a firewall, ensuring that student privacy is maintained and the AI is not trained on potentially sensitive or irrelevant student submissions.
*   **Locked Allowlist:** Any addition of new module support requires an explicit update to this list, accompanied by a security review.

## Security & Privacy Validation

To ensure that future updates don't accidentally leak student data, the plugin includes a dedicated PHPUnit test suite: `tests/payload_builder_test.php`.

*   **Risk Mitigation:** The tests explicitly verify that high-risk areas (like `mod_forum` attachments or `mod_assign` submissions) are NOT in the allowlist.
*   **Drift Protection:** An "allowlist size lock" test ensures that the number of supported modules doesn't grow silently, forcing developers to update tests when adding new features.

## Availability Parsing

The class includes a private `extract_unlock_date($availability_json)` method that safely recursively parses Moodle's internal availability AST to find the strict "Available from" Unix timestamp.
