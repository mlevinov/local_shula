<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Shula AI';
$string['institution_id'] = 'מזהה מוסד Shula';
$string['institution_id_desc'] = 'ה-UUID המדויק שהוקצה לדייר זה בשרת Django של Shula.';
$string['webhook_secret'] = 'מפתח חתימת Webhook';
$string['webhook_secret_desc'] = 'המפתח הקריפטוגרפי המשמש ליצירת חתימת HMAC-SHA256 עבור webhooks יוצאים. חייב להתאים בדיוק למפתח שנוצר עבור מוסד זה.';
$string['webhook_endpoint'] = 'כתובת Webhook';
$string['webhook_endpoint_desc'] = 'ה-URL המלא של ה-webhook של Shula (לדוגמה: <https://api.shula.ai/api/v1/webhook/moodle/>).';
$string['lti_identifier'] = 'מזהה כלי LTI של Shula';
$string['lti_identifier_desc'] = 'הדומיין או קטע ה-URL המשמש לזיהוי כלי ה-LTI של Shula בקורסים (לדוגמה: "api.shula.ai" לסביבת ייצור, או "host.docker.internal" לפיתוח).';
$string['opt_out_tag'] = 'תגית הדרה מ-AI';
$string['opt_out_tag_desc'] = 'מורים יכולים להוסיף תגית זו לכל רכיב או קובץ ב-Moodle כדי למנוע מ-Shula לקרוא את תוכנו. שימושי עבור קבצי PDF מוגני זכויות יוצרים או תשובות לבחינות פרטיות.';
$string['task_send_webhook'] = 'Shula AI: שליחת Webhook לקובץ בודד';
$string['task_bulk_sync'] = 'Shula AI: עיבוד סנכרון מלא של קורס';
$string['privacy:metadata:reason'] = 'תוסף Shula AI אינו שומר נתונים אישיים של משתמשים באופן מקומי. הוא מעביר את מבנה הקורס ותוכן הקבצים לשירות החיצוני של Shula לצורך הפעלת מדריך ה-AI.';