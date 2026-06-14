# Payload Builder Service

The `payload_builder` class (`local_shula\service\payload_builder`) is responsible for constructing secure, standardized, hierarchical JSON representations of Moodle courses, sections, and activities. This structure matches the input schemas expected by the Shula AI Django backend.

---

## 1. Structure Mapping (Schemas)

`payload_builder` builds three primary objects corresponding to the core components of a Moodle course structure:

### 1.1. Course Item (`MoodleCourseItem`)
Formed via `build_course_item($course)`. It returns:
* **`moodle_course_id` (int):** The unique course ID.
* **`fullname` (string):** Full name of the course.
* **`categoryid` (int):** Course category ID.
* **`is_visible` (int):** Visibility setting (0/1).
* **`summary` (string):** The course description.
* **`summaryformat` (int):** Format of the description.
* **`lang` (string):** The course language code (defaults to Moodle standard `$CFG->lang` or `'en'`).
* **`format` (string):** The layout format (e.g., `topics`, `weeks`).
* **`current_marker` (int):** Highlighting marker for the active topic/week section.

### 1.2. Section Item (`MoodleSectionItem`)
Formed via `build_section_item($section_info, $modinfo, $include_modules)`. It includes:
* **`moodle_section_id` (int):** The unique section database ID.
* **`section_number` (int):** The sequential section index (0, 1, 2, ...).
* **`name` (string):** Section title (custom or default).
* **`summary` (string):** HTML introduction of the section.
* **`is_visible` (int):** Visibility setting of the section (0/1).
* **`unlock_date` (int|null):** Extracted start restriction timestamp.
* **`availability_rule` (array|null):** The full availability JSON AST.
* **`modules` (array):** Array of nested Module items (only included when `$include_modules` is true).

### 1.3. Module Item
Formed via `build_module_item($cm)`. It combines database-specific fields, virtual HTML, and physical files:
* **`course_module_id` (int):** Moodle's unique Course Module ID (`cmid`).
* **`instance_id` (int):** The database ID of the specific activity (e.g., specific `page` ID, `url` ID).
* **`name` (string):** The activity name.
* **`modname` (string):** The module type name (e.g., `resource`, `page`, `folder`, `assign`, `url`).
* **`is_visible` (int):** Visibility toggle (0/1).
* **`unlock_date` (int|null):** Extracted availability timestamp.
* **`ai_restricted` (bool):** Set to true if an opt-out tag is detected.
* **`availability_rule` (array|null):** Detailed availability rule array.
* **`files_count` (int):** Combined count of attached files.
* **`files_size` (int):** Combined byte size of attached files.
* **`files` (array):** Consolidated content schema records (representing physical and virtual files).

---

## 2. Zero-PII Privacy Guardrails

Student data security is enforced directly at the payload construction layer to ensure that student records never reach the AI:

### 2.1. Teacher-Authored Filearea Allowlist
The plugin uses a strict, closed mapping representing allowed, secure teacher-authored file areas (`get_safe_teacher_fileareas()`):
```php
public static function get_safe_teacher_fileareas(): array {
    return [
        'mod_resource'  => ['content', 'intro'],
        'mod_page'      => ['content', 'intro'],
        'mod_folder'    => ['content', 'intro'],
        'mod_assign'    => ['introattachment', 'intro'], 
        'mod_book'      => ['chapter', 'intro'],
        'mod_scorm'     => ['package', 'intro'],
        'mod_imscp'     => ['content', 'intro'],
        'mod_lesson'    => ['page_contents', 'intro'],
        'mod_url'       => ['intro'],
        'mod_forum'     => ['intro'], 
        'mod_quiz'      => ['intro'],
        'mod_label'     => ['intro']
    ];
}
```
* **Student-contributed files** (such as forum posts, assignment submissions, wiki attachments, or database submissions) are **completely omitted** because their respective fileareas are not listed on the allowlist.

### 2.2. AI Opt-Out Tag (`no-shula`)
Teachers can exclude individual activities or complete sections from AI access by adding a configured opt-out tag (configured in settings, defaults to `no-shula`):
1. **Detection:** The builder queries Moodle's Core Tag API (`\core_tag_tag::get_item_tags()`) for the course module.
2. **Exclusion:** If the tag matches, `ai_restricted` is set to `true` and the `files` array is completely wiped before dispatch, preventing file uploads from reaching the AI tutor.

---

## 3. Advanced Availability Date Extraction

Moodle stores availability constraints (conditional release rules) in a nested, Boolean logical AST structure (e.g. `{"op":"&","c":[{"type":"date","d":">=","t":1718064000}]}`).

To feed the AI tutor with accurate start times, the `extract_unlock_date()` helper dynamically resolves this structure using a **recursive AST walker**:
- It recursively parses nested rule objects.
- It filters by type `date` and direction `>=` or `>` (meaning "available from").
- In cases of multiple restrictive date rules (e.g. complex nested logical blocks), it keeps the **most restrictive (latest) date** as the ultimate `unlock_date` timestamp.
