# موقع شركة الزهراء للمقاولات

موقع عربي RTL قابل لإدارة محتواه من لوحة تحكم، مبني على Laravel 12 وBlade وMySQL، مع صفحات إنجليزية اختيارية تحت `/en`.

## تشغيل المشروع محليًا

يتطلب PHP 8.2 أو أحدث مع امتدادات `pdo_mysql` و`zip`، وComposer، وNode.js. أنشئ قاعدة MySQL باسم `alzahraa-construction` ثم اضبط بيانات الاتصال في `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=alzahraa-construction
DB_USERNAME=root
DB_PASSWORD=
```

في خادم الاستضافة استخدم مستخدم MySQL محدود الصلاحيات بدل `root`. إذا لم تكن أداتا `mysqldump` و`mysql` ضمن `PATH`، عيّن `DB_DUMP_BINARY` و`DB_CLIENT_BINARY` إلى مساريهما الكاملين؛ تحتاجهما ميزة النسخ والاستعادة.

بعد إعداد `.env`:

```powershell
composer install
php artisan key:generate --no-interaction
php artisan migrate --seed --no-interaction
php artisan storage:link --no-interaction
npm ci
npm run build
php artisan serve
```

تستخدم الاختبارات SQLite منفصلًا ولا تعدّل قاعدة MySQL المحلية.

## حساب المدير ولوحة التحكم

أنشئ حساب المدير الأول من سطر الأوامر؛ سيطلب الاسم والبريد وكلمة مرور لا تقل عن 12 حرفًا، ولا توجد كلمة مرور افتراضية:

```powershell
php artisan admin:create
```

افتح `/admin/login` لتسجيل الدخول. من اللوحة يمكنك إدارة الصفحات والأقسام والمسودات والإصدارات، والخدمات والمشاريع والمعدات والفريق، والإعدادات والقوائم والصور وطلبات المقايسة والمستخدمين.

## البريد وطلبات المقايسة

تُحفظ الطلبات في MySQL قبل إظهار تأكيد الإرسال. إعداد `MAIL_MAILER=log` الافتراضي لا يرسل بريدًا خارجيًا؛ فعّل SMTP في `.env` قبل النشر لتصل إشعارات الطلب إلى بريد الشركة المضبوط من إعدادات الموقع. تعذّر البريد لا يحذف طلب المقايسة.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mail.example.com
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=website@example.com
MAIL_FROM_NAME="Al Zahraa Construction"
```

استبدل رقم الهاتف `010xxxxxxxx` وبقية بيانات التواصل التجريبية من إعدادات الموقع قبل نشره. الأزرار لا تنشئ رابط اتصال أو واتساب ما دام الرقم غير صالح.

## النسخ الاحتياطي والاستعادة

للمدير صلاحية تنزيل نسخة من قاعدة البيانات والوسائط واستعادتها من لوحة التحكم. مع MySQL يحتاج الخادم إلى أداتي `mysqldump` و`mysql`، واضبط مساريهما عبر `DB_DUMP_BINARY` و`DB_CLIENT_BINARY` عند الحاجة. تحفظ الملفات المؤقتة والنسخ داخل `storage/app/backups` خارج مسار النشر العام.

## محتوى العرض

المشاريع والصور المعروضة في البداية نماذج تصورية وليست سجلات أعمال موثقة. استبدلها ببيانات وصور الشركة الحقيقية قبل الإعلان عن الموقع. صورة «قبل وبعد» الحالية مركبة توضيحية وموسومة بذلك.

## الاستضافة

اختر PHP 8.2 أو أحدث للويب وCLI، واضبط قاعدة MySQL وبيانات SMTP ومتغيرات `.env` على الخادم، ثم شغّل `php artisan migrate --force` وابنِ أصول الواجهة بـ`npm run build`. لا ترفع `.env` أو تضع أسرارًا في المستودع.
