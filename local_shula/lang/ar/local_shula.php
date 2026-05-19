<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Shula AI';
$string['institution_id'] = 'معرّف مؤسسة Shula';
$string['institution_id_desc'] = 'UUID المحدد المخصص لهذا المستأجر في خادم Django الخاص بـ Shula.';
$string['webhook_secret'] = 'مفتاح توقيع Webhook';
$string['webhook_secret_desc'] = 'المفتاح التشفيري المستخدم لإنشاء توقيع HMAC-SHA256 للـ webhooks الصادرة. يجب أن يتطابق تمامًا مع مفتاح توقيع الـ Webhook المُنشأ لهذه المؤسسة.';
$string['webhook_endpoint'] = 'عنوان Webhook';
$string['webhook_endpoint_desc'] = 'عنوان URL الكامل لـ webhook الخاص بـ Shula (مثال: <https://api.shula.ai/api/v1/webhook/moodle/>).';
$string['lti_identifier'] = 'معرّف أداة LTI الخاصة بـ Shula';
$string['lti_identifier_desc'] = 'النطاق أو جزء URL المستخدم لتعريف أداة LTI الخاصة بـ Shula في المقررات (مثال: "api.shula.ai" للإنتاج، أو "host.docker.internal" للتطوير).';
$string['opt_out_tag'] = 'وسم الاستبعاد من الذكاء الاصطناعي';
$string['opt_out_tag_desc'] = 'يمكن للمعلمين إضافة هذا الوسم المحدد إلى أي مكوّن أو ملف في Moodle لمنع Shula من قراءة محتواه. مفيد لملفات PDF المحمية بحقوق النشر أو إجابات الامتحانات الخاصة.';
$string['task_send_webhook'] = 'Shula AI: إرسال Webhook لملف فردي';
$string['task_bulk_sync'] = 'Shula AI: معالجة المزامنة الشاملة للمقرر';
$string['privacy:metadata:reason'] = 'لا يقوم ملحق Shula AI بتخزين أي بيانات شخصية للمستخدمين محليًا. فهو يُرسل هيكل المقرر ومحتوى الملفات إلى خدمة Shula الخارجية لتشغيل المعلم الذكي.';