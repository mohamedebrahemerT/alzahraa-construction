<?php

namespace Database\Seeders;

use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Storage::disk('public')->makeDirectory('site');
        $brandAssets = [
            'banner.webp' => ['banner.webp', 'image/webp', 'البانر الرئيسي لشركة الزهراء للمقاولات والبناء'],
            'logo-mark.webp' => ['logo-mark.webp', 'image/webp', 'علامة شعار شركة الزهراء بالذهبي والأسود'],
            'favicon.png' => ['favicon.png', 'image/png', 'أيقونة موقع شركة الزهراء'],
            'banner-source.jpeg' => ['bnr.jpeg', 'image/jpeg', 'نسخة المصدر الأصلية لبانر الشركة'],
            'logo-source.jpeg' => ['logo.jpeg', 'image/jpeg', 'نسخة المصدر الأصلية لشعار الشركة'],
        ];
        foreach ($brandAssets as $filename => [$originalName, $mimeType, $alt]) {
            $path = 'site/'.$filename;
            $source = public_path('images/brand/'.$filename);
            if (is_file($source) && ! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, file_get_contents($source));
            }
            if (Storage::disk('public')->exists($path)) {
                MediaAsset::firstOrCreate(['path' => $path], [
                    'original_name' => $originalName, 'mime_type' => $mimeType,
                    'size' => Storage::disk('public')->size($path), 'alt' => $alt,
                ]);
            }
        }
        foreach (range(1, 17) as $number) {
            $filename = $number.'.jpeg';
            $source = base_path('photos/'.$filename);
            $destination = 'site/'.$filename;
            if (is_file($source) && ! Storage::disk('public')->exists($destination)) {
                Storage::disk('public')->put($destination, file_get_contents($source));
            }
            if (Storage::disk('public')->exists($destination)) {
                MediaAsset::firstOrCreate(['path' => $destination], [
                    'original_name' => $filename, 'mime_type' => 'image/jpeg',
                    'size' => Storage::disk('public')->size($destination),
                    'alt' => $this->imageAlt($number),
                ]);
            }
        }

        SiteSetting::firstOrCreate(['key' => 'general'], ['value' => [
            'company_name' => 'الزهراء للمقاولات والتشييد والبناء',
            'tagline' => 'نبني بثقة، ونُسلّم بإتقان',
            'phone' => '010xxxxxxxx', 'email' => 'info@alzahraa.construction',
            'address' => 'القاهرة الجديدة، مصر', 'whatsapp' => '',
            'logo' => 'site/logo-mark.webp', 'favicon' => 'site/favicon.png', 'primary_color' => '#102332', 'accent_color' => '#c59d5f',
            'font' => 'Cairo', 'facebook' => '', 'instagram' => '', 'linkedin' => '',
            'nav' => [
                ['label' => 'الرئيسية', 'url' => '/'], ['label' => 'من نحن', 'url' => '/about'],
                ['label' => 'خدماتنا', 'url' => '/services'], ['label' => 'مشاريعنا', 'url' => '/projects'],
                ['label' => 'معداتنا', 'url' => '/equipment'], ['label' => 'اتصل بنا', 'url' => '/contact'],
            ],
            'footer_nav' => [
                ['label' => 'من نحن', 'url' => '/about'], ['label' => 'خدماتنا', 'url' => '/services'],
                ['label' => 'مشاريعنا', 'url' => '/projects'], ['label' => 'معداتنا', 'url' => '/equipment'],
                ['label' => 'اتصل بنا', 'url' => '/contact'],
            ],
            'header_cta_label' => 'مقايسة مجانية', 'header_cta_url' => '/contact',
            'footer_note' => 'من أول حفر الأساس إلى تسليم المفتاح، شريكك الهندسي في كل خطوة.',
            'show_demo_notice' => true,
        ]]);

        $this->seedItems();
        $this->seedPages();
        $this->seedEnglishTranslations();
    }

    private function imageAlt(int $number): string
    {
        return [1 => 'فيلا عصرية بواجهة حجرية وحديقة ومسبح', 2 => 'عاملون ومهندسون في موقع بناء وسقالات',
            3 => 'معدات حفر وتجهيز قواعد خرسانية', 4 => 'مبنى إداري قيد الإنشاء ورافعات برجية',
            5 => 'رافعة في موقع بناء عند الغروب', 6 => 'عمال يركبون أرضيات بورسلين داخلية',
            7 => 'فريق مهندسين وعمال يراجع مخططًا في الموقع', 8 => 'صب قاعدة خرسانية بخلاطة أسمنت',
            9 => 'تمديدات كهرباء وسباكة وتكييف داخل مبنى', 10 => 'اجتماع فريق هندسي حول مخطط بناء',
            11 => 'أعمال تشطيب بلاط ودهان داخلي', 12 => 'أسطول حفارات ورافعة وخلاطات',
            13 => 'صب خرسانة مسلحة للأساسات', 14 => 'معدات رصف طريق بالأسفلت',
            15 => 'صورة توضيحية مركبة قبل وبعد لبناء فيلا', 16 => 'تركيب هيكل معدني لمستودع',
            17 => 'فريق هندسي يناقش مخططًا في موقع مشروع'][$number] ?? 'صورة من أعمال المقاولات';
    }

    private function seedItems(): void
    {
        $services = [
            ['concrete', 'الهيكل الخرساني والأساسات', 'حفر وإحلال وقواعد وأعمدة وأسقف خرسانية، بتنفيذ مضبوط واختبارات جودة.', '3.jpeg', 'تنفيذ الأساسات والخرسانة المسلحة', 'جسات تربة، حفر، إحلال، لبشة، قواعد منفصلة، سملات، أعمدة، فلات سلاب وسوليد سلاب، معالجة الخرسانة واختبارات المكعبات. نستخدم حديد عز وسويدي وأسمنت السويس.'],
            ['finishing', 'التشطيبات السوبر لوكس', 'تشطيبات داخلية راقية بتفاصيل مدروسة، من المحارة والعزل حتى التسليم على المفتاح.', '6.jpeg', 'فني يركب بلاط بورسلين داخل مبنى', 'محارة بؤج وأوتار، عزل حمامات، سيراميك وبورسلين ورخام، دهانات جوتن وGLC، جبس بورد كناوف، ألوميتال وأبواب مصفحة. تسليم على المفتاح مع تصور ثلاثي الأبعاد قبل التنفيذ.'],
            ['mep', 'أعمال MEP', 'شبكات كهرباء وسباكة وتكييف وأنظمة حريق متكاملة، مع اختبارات قبل الإغلاق.', '9.jpeg', 'تمديدات كهرباء وسباكة وتكييف', 'تصميم وتنفيذ شبكات الكهرباء والسباكة والتكييف. لوحات شنايدر، مواسير BR، كابلات السويدي، وتكييف كاريير وLG. يشمل العمل اختبار ضغط وضمان.'],
            ['metal', 'المنشآت المعدنية والمصانع', 'تصميم وتوريد وتركيب الهناجر والمستودعات بهياكل فولاذية مناسبة للاستخدام.', '16.jpeg', 'هيكل معدني لمستودع قيد التنفيذ', 'تنفيذ مصانع وهناجر بمساحات حتى 5000 متر مربع، مع توريد وتركيب جمالونات بقطاعات S355 ودهان مقاوم للصدأ.'],
            ['infrastructure', 'البنية التحتية والطرق', 'تنفيذ أعمال الطرق والشبكات من تجهيز الموقع إلى طبقات الرصف والتسليم.', '14.jpeg', 'معدات تنفيذ أعمال رصف الأسفلت', 'رصف طرق وأسفلت وبردورة وإنترلوك، وشبكات صرف ومياه وغرف تفتيش.'],
            ['landscape', 'اللاندسكيب والواجهات الحجرية', 'مساحات خارجية متناسقة تجمع الحدائق والمسابح والإنارة والواجهات.', '1.jpeg', 'فيلا بواجهة حجرية وحديقة ومسبح', 'تصميم وتنفيذ الحدائق وحمامات السباحة والشلالات والإضاءة الخارجية والواجهات الحجرية.'],
        ];
        foreach ($services as $i => [$slug, $title, $description, $image, $alt, $body]) {
            ContentItem::firstOrCreate(['kind' => 'service', 'slug' => $slug], [
                'title' => $title, 'category' => 'خدمات', 'description' => $description, 'body' => $body,
                'image' => 'site/'.$image, 'alt' => $alt, 'sort_order' => $i + 1, 'is_published' => true,
            ]);
        }

        $projects = [
            ['villa-zayed', 'فيلا عصرية – الشيخ زايد', 'فيلا', 'الشيخ زايد', 2024, 600, '1.jpeg', 'فيلا عصرية بواجهة حجرية وحديقة ومسبح', 'نموذج تصوري لمشروع فيلا متكامل، من أعمال الهيكل والتشطيبات إلى تنسيق الموقع الخارجي.'],
            ['residential-building', 'عمارة سكنية – التجمع الخامس', 'عمارة سكنية', 'التجمع الخامس', 2023, 1250, '4.jpeg', 'مبنى سكني قيد الإنشاء ورافعات', 'نموذج تصوري لمبنى سكني متعدد الطوابق بأعمال خرسانية وواجهات حديثة.'],
            ['obour-factory', 'مصنع – العبور', 'مصنع', 'العبور', 2024, 2000, '16.jpeg', 'هيكل فولاذي لمصنع أو مستودع', 'نموذج تصوري لمنشأة صناعية بهيكل معدني ومساحات تشغيل مرنة.'],
            ['new-capital-finishing', 'تشطيب شقة – العاصمة الإدارية', 'تشطيب', 'العاصمة الإدارية', 2025, 180, '11.jpeg', 'أعمال تشطيب داخلي للحوائط والأرضيات', 'نموذج تصوري لأعمال تشطيب شقة سكنية بمستوى سوبر لوكس.'],
            ['road-network', 'شبكة طرق – القاهرة الجديدة', 'بنية تحتية', 'القاهرة الجديدة', 2025, 1200, '14.jpeg', 'أعمال رصف أسفلت في موقع طريق', 'نموذج تصوري لأعمال تجهيز ورصف طريق وشبكات البنية التحتية.'],
            ['foundation-works', 'أساسات مبنى إداري', 'هيكل خرساني', 'العاصمة الإدارية', 2024, 850, '13.jpeg', 'صب خرسانة مسلحة للقواعد والأساسات', 'نموذج تصوري لأعمال حفر وتسليح وصب أساسات مبنى إداري.'],
        ];
        foreach ($projects as $i => [$slug, $title, $category, $location, $year, $area, $image, $alt, $description]) {
            ContentItem::firstOrCreate(['kind' => 'project', 'slug' => $slug], [
                'title' => $title, 'category' => $category, 'location' => $location, 'project_year' => $year,
                'area' => $area, 'description' => $description, 'image' => 'site/'.$image, 'alt' => $alt,
                'sort_order' => $i + 1, 'is_published' => true, 'is_demo' => true,
                'data' => ['gallery' => ['site/'.$image]],
            ]);
        }

        $equipment = [
            ['excavator', 'حفار كات', 'حفارات لأعمال الحفر وتجهيز المواقع والأساسات.', '2 حفار', '3.jpeg'],
            ['loader', 'لودر', 'تحميل ونقل مواد البناء داخل مواقع التنفيذ.', '1 لودر', '12.jpeg'],
            ['mixer', 'خلاطة خرسانة', 'خلاطات لدعم أعمال الصب والخرسانة.', '3 خلاطات', '8.jpeg'],
            ['tower-crane', 'ونش برجي', 'رفع ونقل المواد للمباني متعددة الطوابق.', '1 ونش برجي', '5.jpeg'],
            ['scaffolding', 'سقالات', 'سقالات آمنة لأعمال الواجهات والتشطيبات المرتفعة.', 'سقالات متعددة', '2.jpeg'],
            ['vibrators', 'هزازات خرسانة', 'هزازات لدمك الخرسانة وتحسين تجانس الصب.', 'معدات متعددة', '13.jpeg'],
            ['survey', 'أجهزة مساحة Total Station', 'أجهزة مساحة وضبط مناسيب ومحاور التنفيذ.', 'أجهزة مساحة', '7.jpeg'],
        ];
        foreach ($equipment as $i => [$slug, $title, $description, $quantity, $image]) {
            ContentItem::firstOrCreate(['kind' => 'equipment', 'slug' => $slug], [
                'title' => $title, 'description' => $description, 'quantity' => $quantity,
                'image' => 'site/'.$image, 'alt' => $title.' في موقع العمل', 'sort_order' => $i + 1, 'is_published' => true,
            ]);
        }
        $team = [
            ['civil', 'مهندسو الهندسة المدنية', '3 مهندسين', 'يشرفون على أعمال الأساسات والهيكل والجودة الإنشائية.'],
            ['architecture', 'مهندسو العمارة', '2 مهندس', 'يتابعون التصميم المعماري وتفاصيل التنفيذ والتشطيب.'],
            ['electrical', 'مهندسو الكهرباء', '2 مهندس', 'يديرون أنظمة الكهرباء والتيار الخفيف والتنسيق الفني.'],
            ['supervisors', 'مشرفو المواقع', '5 مشرفين', 'يتابعون فرق التنفيذ اليومية وخطط السلامة والجودة.'],
            ['craftspeople', 'الفنيون والعمال', '40+ فني وعامل', 'كوادر مدربة تغطي تخصصات البناء والتشطيبات.'],
        ];
        foreach ($team as $i => [$slug, $title, $quantity, $description]) {
            ContentItem::firstOrCreate(['kind' => 'team', 'slug' => $slug], [
                'title' => $title, 'quantity' => $quantity, 'description' => $description,
                'image' => 'site/7.jpeg', 'alt' => 'فريق هندسي وعمال في موقع تنفيذ', 'sort_order' => $i + 1, 'is_published' => true,
            ]);
        }
    }

    private function seedPages(): void
    {
        $pageData = [
            'home' => ['الرئيسية', [
                $this->section('hero', 'الزهراء للمقاولات والتشييد والبناء', 'نحوّل أرضك إلى مشروع فاخر.. بهيكل قوي، وتشطيب راقٍ، والتزام بالموعد.', 'site/banner.webp', ['alt' => 'بانر الزهراء للمقاولات والتشييد والبناء بالعربية والإنجليزية', 'image_has_text' => true, 'primary_label' => 'احصل على مقايسة مجانية', 'primary_url' => '/contact', 'secondary_label' => 'شاهد مشاريعنا', 'secondary_url' => '/projects']),
                $this->section('stats', 'أرقام تعكس خبرتنا', '', '', ['columns' => 4, 'items' => [['value' => '+15', 'label' => 'سنة خبرة'], ['value' => '+280', 'label' => 'مشروع تم تسليمه'], ['value' => '+120', 'label' => 'عميل يثق بنا'], ['value' => '50+', 'label' => 'معدة مملوكة للشركة']]]),
                $this->section('text-image', 'شريكك الهندسي من أول الحفر حتى تسليم المفتاح', 'شركة الزهراء للمقاولات هي شريكك الهندسي من أول حفر الأساس لحد تسليم المفتاح. نحن لا نبني جدرانًا فقط، نحن نبني ثقة. نمتلك فريقًا هندسيًا متكاملًا ومكتبًا فنيًا ومشرفي مواقع وأسطول معدات خاصًا بنا، مما يضمن لك جودة عالية وسعرًا منافسًا دون مقاول باطن.', 'site/10.jpeg', ['link_label' => 'تعرف علينا', 'link_url' => '/about']),
                $this->section('services', 'خدمات متكاملة.. من الأساس إلى المفتاح', 'فريق واحد يدير تفاصيل مشروعك الهندسية والتنفيذية.'),
                $this->section('process', 'كيف نعمل', 'خطوات واضحة تحفظ وقتك وميزانيتك.', '', ['items' => [['value' => '01', 'label' => 'معاينة ومقايسة مجانية'], ['value' => '02', 'label' => 'تصميم ومخططات تنفيذية'], ['value' => '03', 'label' => 'تنفيذ بإشراف هندسي يومي'], ['value' => '04', 'label' => 'تسليم مفتاح وضمان']]]),
                $this->section('before-after', 'شاهد الفرق الذي نصنعه', 'تصور لتحول أرض فضاء إلى فيلا عصرية متكاملة.', 'site/15.jpeg'),
                $this->section('features', 'لماذا الزهراء؟', 'جودة التنفيذ تبدأ من وضوح الاتفاق وتنتهي بمتابعة ما بعد التسليم.', '', ['items' => [['label' => 'التزام زمني بعقد وشرط جزائي'], ['label' => 'إشراف مهندس مقيم يوميًا'], ['label' => 'مواد بفواتير وضمان'], ['label' => 'نظام التكلفة + نسبة أو تسليم مفتاح'], ['label' => 'سلامة مهنية في مواقعنا'], ['label' => 'متابعة بعد التسليم']]]),
                $this->section('projects', 'نماذج من مشاريعنا', 'أمثلة تصورية على أنواع الأعمال التي ننفذها.'),
                $this->section('cta', 'جاهز تبدأ مشروعك؟', 'اطلب معاينة ومقايسة مجانية، وسنساعدك في اختيار الخطوة الأولى.', '', ['primary_label' => 'ابدأ طلبك الآن', 'primary_url' => '/contact']),
            ]],
            'about' => ['من نحن', [
                $this->section('page-hero', 'من نحن – 15 سنة من البناء بثقة', 'شركة الزهراء للمقاولات والتشييد والبناء.', 'site/7.jpeg'),
                $this->section('text-image', 'نبني ثقة قبل أن نبني جدرانًا', 'تأسست شركة الزهراء للمقاولات والتشييد والبناء بهدف سد الفجوة بين المقاول التقليدي والشركة الهندسية المحترفة. نحن شركة مقاولات عامة مصنفة، نعمل في القاهرة الجديدة، الشيخ زايد، العاصمة الإدارية، والساحل الشمالي.', 'site/17.jpeg'),
                $this->section('values', 'رؤيتنا ورسالتنا وقيمنا', '', '', ['items' => [['title' => 'رؤيتنا', 'text' => 'أن نكون ضمن أفضل 10 شركات مقاولات في مصر بحلول 2030.'], ['title' => 'رسالتنا', 'text' => 'تقديم مشروع متكامل بأعلى جودة، بأقل هدر، وفي الوقت المحدد.'], ['title' => 'قيمنا', 'text' => 'الأمانة في المواد، الشفافية في التكلفة، الجودة في التنفيذ.']]]),
                $this->section('team', 'فريق هندسي متكامل', 'كوادر متخصصة تشرف على كل مرحلة من مراحل التنفيذ.'),
                $this->section('gallery', 'من قلب مواقع العمل', '', '', ['images' => ['site/2.jpeg', 'site/7.jpeg', 'site/10.jpeg', 'site/17.jpeg']]),
            ]],
            'services' => ['خدماتنا', [$this->section('page-hero', 'خدمات متكاملة لمشروع ناجح', 'حلول المقاولات والتشييد والبناء من الحفر إلى التسليم.', 'site/8.jpeg'), $this->section('services', 'مجالات عملنا الستة', 'ننسق بين التخصصات الهندسية ضمن خطة تنفيذ واضحة.')]],
            'projects' => ['مشاريعنا', [$this->section('page-hero', 'مشاريعنا', 'نماذج تصورية للأعمال والمجالات التي تغطيها الشركة.', 'site/4.jpeg'), $this->section('projects', 'معرض المشاريع', 'جميع الصور والبيانات المعروضة للمشاريع نماذج تجريبية وليست سجلات تنفيذ موثقة.')]],
            'equipment' => ['معداتنا', [$this->section('page-hero', 'معداتنا.. جاهزية من اليوم الأول', 'نمتلك أسطول المعدات الذي يدعم التنفيذ ويعزز جودة العمل.', 'site/12.jpeg'), $this->section('equipment', 'أسطولنا المملوك', 'معداتنا وأعدادها قابلة للتحديث من لوحة الإدارة.')]],
            'contact' => ['اتصل بنا', [$this->section('page-hero', 'جاهز تبدأ مشروعك؟ كلمنا', 'اطلب مقايسة مجانية، وفريقنا يتواصل معك لترتيب الخطوة التالية.', 'site/5.jpeg'), $this->section('contact', 'اطلب مقايسة مجانية', 'اكتب تفاصيل مشروعك وسنتواصل معك.')]],
        ];
        foreach ($pageData as $slug => [$title, $sections]) {
            ContentPage::firstOrCreate(['slug' => $slug], [
                'title' => $title, 'status' => 'published', 'meta_title' => $title.' | الزهراء للمقاولات',
                'meta_description' => 'الزهراء للمقاولات والتشييد والبناء – شريكك الهندسي في مصر.',
                'sections' => $sections, 'sort_order' => array_search($slug, array_keys($pageData), true),
            ]);
        }
    }

    private function section(string $type, string $title, string $text = '', string $image = '', array $extra = []): array
    {
        return array_merge(['id' => bin2hex(random_bytes(5)), 'type' => $type, 'title' => $title, 'text' => $text,
            'image' => $image, 'background' => 'light', 'align' => 'right', 'columns' => 2,
            'spacing' => 'normal', 'visible' => true], $extra);
    }

    private function seedEnglishTranslations(): void
    {
        $itemTranslations = [
            'concrete' => ['title' => 'Concrete Structures & Foundations', 'category' => 'Construction', 'description' => 'Excavation, soil replacement, foundations, columns and reinforced concrete slabs, delivered with careful workmanship and quality checks.', 'body' => 'Soil testing, excavation, ground improvement, raft and isolated foundations, tie beams, columns, flat slabs and solid slabs, concrete curing and cube testing.', 'alt' => 'Foundation and reinforced concrete construction'],
            'finishing' => ['title' => 'Premium Finishing', 'category' => 'Finishing', 'description' => 'Refined interior finishing with considered details, from plastering and waterproofing through to key handover.', 'body' => 'Plastering, bathroom waterproofing, ceramic, porcelain and marble, paint, gypsum board, aluminium windows and security doors. Turnkey handover with a 3D concept before work begins.', 'alt' => 'Craftsman installing porcelain floor tiles indoors'],
            'mep' => ['title' => 'MEP Works', 'category' => 'Building Systems', 'description' => 'Integrated electrical, plumbing, air conditioning and fire protection networks, tested before concealment.', 'body' => 'Design and installation of electrical, plumbing and air conditioning networks, distribution boards, pipes and cables, including pressure testing and warranty.', 'alt' => 'Electrical, plumbing and air conditioning installations'],
            'metal' => ['title' => 'Steel Structures & Industrial Buildings', 'category' => 'Construction', 'description' => 'Design, supply and installation of warehouses and industrial buildings with steel structures suited to their use.', 'body' => 'Industrial buildings and warehouses up to 5,000 square metres, including steel trusses and corrosion-resistant finishes.', 'alt' => 'Steel structure for a warehouse under construction'],
            'infrastructure' => ['title' => 'Infrastructure & Roads', 'category' => 'Infrastructure', 'description' => 'Road and utility works from site preparation through paving layers and handover.', 'body' => 'Road paving, asphalt, kerbs and interlock, as well as drainage and water networks and inspection chambers.', 'alt' => 'Equipment paving an asphalt road'],
            'landscape' => ['title' => 'Landscaping & Stone Facades', 'category' => 'Exterior Works', 'description' => 'Coordinated outdoor spaces combining gardens, pools, lighting and facades.', 'body' => 'Design and construction of gardens, swimming pools, water features, outdoor lighting and stone facades.', 'alt' => 'Modern villa with a stone facade, garden and pool'],
            'villa-zayed' => ['title' => 'Modern Villa — Sheikh Zayed', 'category' => 'Villa', 'location' => 'Sheikh Zayed', 'description' => 'Concept design for a complete villa, from structural work and finishing to the outdoor landscape.', 'alt' => 'Modern villa with a stone facade, garden and pool'],
            'residential-building' => ['title' => 'Residential Building — Fifth Settlement', 'category' => 'Residential Building', 'location' => 'Fifth Settlement', 'description' => 'Concept design for a multi-storey residential building with concrete works and contemporary facades.', 'alt' => 'Residential building under construction with tower cranes'],
            'obour-factory' => ['title' => 'Factory — Obour City', 'category' => 'Factory', 'location' => 'Obour City', 'description' => 'Concept design for an industrial facility with a steel structure and flexible operating spaces.', 'alt' => 'Steel structure for a factory or warehouse'],
            'new-capital-finishing' => ['title' => 'Apartment Finishing — New Administrative Capital', 'category' => 'Finishing', 'location' => 'New Administrative Capital', 'description' => 'Concept example of premium finishing works for a residential apartment.', 'alt' => 'Interior wall and floor finishing work'],
            'road-network' => ['title' => 'Road Network — New Cairo', 'category' => 'Infrastructure', 'location' => 'New Cairo', 'description' => 'Concept example of road preparation, paving and infrastructure networks.', 'alt' => 'Asphalt paving work on a road site'],
            'foundation-works' => ['title' => 'Office Building Foundations', 'category' => 'Concrete Structures', 'location' => 'New Administrative Capital', 'description' => 'Concept example of excavation, reinforcement and foundation concrete for an office building.', 'alt' => 'Reinforced concrete foundations being poured'],
            'excavator' => ['title' => 'CAT Excavators', 'description' => 'Excavators for earthworks, site preparation and foundations.', 'quantity' => '2 excavators', 'alt' => 'Excavator at a construction site'],
            'loader' => ['title' => 'Wheel Loader', 'description' => 'Loading and moving construction materials around the site.', 'quantity' => '1 loader', 'alt' => 'Construction equipment at a work site'],
            'mixer' => ['title' => 'Concrete Mixer Trucks', 'description' => 'Mixer trucks supporting concrete placement and casting work.', 'quantity' => '3 mixer trucks', 'alt' => 'Concrete being poured from a mixer truck'],
            'tower-crane' => ['title' => 'Tower Crane', 'description' => 'Lifting and moving materials on multi-storey buildings.', 'quantity' => '1 tower crane', 'alt' => 'Tower crane lifting materials at sunset'],
            'scaffolding' => ['title' => 'Scaffolding', 'description' => 'Safe access for facade work and elevated finishing.', 'quantity' => 'Multiple sets', 'alt' => 'Workers and engineers on construction scaffolding'],
            'vibrators' => ['title' => 'Concrete Vibrators', 'description' => 'Vibrators for compacting concrete and improving the consistency of each pour.', 'quantity' => 'Multiple units', 'alt' => 'Reinforced concrete foundation work'],
            'survey' => ['title' => 'Total Station Surveying Equipment', 'description' => 'Surveying equipment for setting levels, axes and construction control points.', 'quantity' => 'Surveying instruments', 'alt' => 'Engineering team reviewing a construction plan'],
            'civil' => ['title' => 'Civil Engineers', 'quantity' => '3 engineers', 'description' => 'Oversee foundations, structural work and construction quality.'],
            'architecture' => ['title' => 'Architects', 'quantity' => '2 architects', 'description' => 'Coordinate architectural design, construction details and finishing.'],
            'electrical' => ['title' => 'Electrical Engineers', 'quantity' => '2 engineers', 'description' => 'Manage electrical systems, low-current networks and technical coordination.'],
            'supervisors' => ['title' => 'Site Supervisors', 'quantity' => '5 supervisors', 'description' => 'Coordinate daily site teams, safety plans and quality checks.'],
            'craftspeople' => ['title' => 'Skilled Tradespeople', 'quantity' => '40+ tradespeople', 'description' => 'Trained teams covering construction and finishing specialties.'],
        ];

        foreach ($itemTranslations as $slug => $english) {
            $item = ContentItem::where('slug', $slug)->first();
            if (! $item) {
                continue;
            }
            $translations = $item->translations ?? [];
            $translations['en'] = $this->fillMissingTranslations($english, $translations['en'] ?? []);
            $item->translations = $translations;
            $item->save();
        }

        $pages = [
            'home' => [
                'title' => 'Home', 'meta_title' => 'Al Zahraa Contracting | Construction & Building',
                'meta_description' => 'Al Zahraa Contracting, Construction & Building — your engineering partner in Egypt.',
                'sections' => [
                    ['title' => 'Al Zahraa for Contracting, Construction & Building', 'text' => 'We turn your land into a refined project with a strong structure, quality finishing and a clear commitment to schedule.', 'alt' => 'Al Zahraa Contracting and Construction banner', 'primary_label' => 'Request a free estimate', 'secondary_label' => 'Explore our projects'],
                    ['title' => 'Experience in Numbers', 'items' => [['label' => 'Years of experience'], ['label' => 'Projects delivered'], ['label' => 'Clients who trust us'], ['label' => 'Company-owned machines']]],
                    ['title' => 'Your engineering partner from excavation to handover', 'text' => 'Al Zahraa Contracting is your engineering partner from the first foundation excavation to key handover. We build trust as well as structures. Our integrated engineering team, technical office, site supervisors and company-owned equipment help us deliver quality and value with direct oversight.', 'link_label' => 'Get to know us'],
                    ['title' => 'Complete services, from foundations to handover', 'text' => 'One team coordinates every engineering and construction detail of your project.'],
                    ['title' => 'How We Work', 'text' => 'Clear steps that protect your time and budget.', 'items' => [['label' => 'Site visit and free estimate'], ['label' => 'Design and construction drawings'], ['label' => 'Daily engineering supervision'], ['label' => 'Key handover and warranty']]],
                    ['title' => 'See the transformation we can make', 'text' => 'A concept showing an empty plot transformed into a contemporary villa.', 'alt' => 'Illustrative before and after composite of a plot transformed into a villa'],
                    ['title' => 'Why Al Zahraa?', 'text' => 'Quality starts with a clear agreement and continues with support after handover.', 'items' => [['label' => 'Schedule commitment in the contract'], ['label' => 'Daily resident engineer supervision'], ['label' => 'Materials supplied with invoices and warranty'], ['label' => 'Cost-plus or turnkey options'], ['label' => 'Professional site safety'], ['label' => 'Follow-up after handover']]],
                    ['title' => 'Selected project concepts', 'text' => 'Illustrative examples of the project types and services we offer.'],
                    ['title' => 'Ready to start your project?', 'text' => 'Request a free site visit and estimate. We will help you choose the next step.', 'primary_label' => 'Start your request'],
                ],
            ],
            'about' => [
                'title' => 'About Us', 'meta_title' => 'About Al Zahraa Contracting', 'meta_description' => 'Meet Al Zahraa Contracting, Construction & Building and our engineering team.',
                'sections' => [
                    ['title' => 'About Us — 15 Years of Building with Confidence', 'text' => 'Al Zahraa Contracting, Construction & Building.'],
                    ['title' => 'We build trust before we build walls', 'text' => 'Al Zahraa was established to bridge the gap between traditional contracting and professional engineering. We are a general contracting company serving New Cairo, Sheikh Zayed, the New Administrative Capital and the North Coast.'],
                    ['title' => 'Our Vision, Mission and Values', 'items' => [['title' => 'Our vision', 'text' => 'To become one of Egypt’s leading contracting companies by 2030.'], ['title' => 'Our mission', 'text' => 'Deliver complete projects to a high standard, with less waste and on schedule.'], ['title' => 'Our values', 'text' => 'Honest materials, transparent costs and quality workmanship.']]],
                    ['title' => 'An Integrated Engineering Team', 'text' => 'Specialists oversee every stage of construction.'],
                    ['title' => 'At the Heart of the Worksite'],
                ],
            ],
            'services' => [
                'title' => 'Our Services', 'meta_title' => 'Construction & Engineering Services | Al Zahraa', 'meta_description' => 'Integrated contracting, construction and building solutions from excavation through handover.',
                'sections' => [['title' => 'Integrated Services for Successful Projects', 'text' => 'Contracting, construction and building solutions from excavation to handover.'], ['title' => 'Our Six Areas of Work', 'text' => 'We coordinate engineering disciplines within a clear construction plan.']],
            ],
            'projects' => [
                'title' => 'Our Projects', 'meta_title' => 'Project Portfolio | Al Zahraa Contracting', 'meta_description' => 'Illustrative project concepts by Al Zahraa Contracting, Construction & Building.',
                'sections' => [['title' => 'Our Projects', 'text' => 'Concept examples of the work and sectors covered by the company.'], ['title' => 'Project Gallery', 'text' => 'All project images and details shown here are illustrative concepts, not verified records of completed company work.']],
            ],
            'equipment' => [
                'title' => 'Our Equipment', 'meta_title' => 'Construction Equipment | Al Zahraa', 'meta_description' => 'Company-owned equipment that supports construction and quality on site.',
                'sections' => [['title' => 'Our Equipment, Ready from Day One', 'text' => 'Our equipment fleet supports construction and helps maintain quality.'], ['title' => 'Our Company-Owned Fleet', 'text' => 'Equipment and quantities can be updated from the administration panel.']],
            ],
            'contact' => [
                'title' => 'Contact Us', 'meta_title' => 'Contact Al Zahraa Contracting', 'meta_description' => 'Request a free estimate and our team will contact you to plan the next step.',
                'sections' => [['title' => 'Ready to start your project? Let’s talk', 'text' => 'Request a free estimate and our team will contact you to arrange the next step.'], ['title' => 'Request a Free Estimate', 'text' => 'Tell us about your project and we will be in touch.']],
            ],
        ];

        foreach ($pages as $slug => $english) {
            $page = ContentPage::where('slug', $slug)->first();
            if (! $page) {
                continue;
            }
            $translations = $page->translations ?? [];
            $translations['en'] = $this->fillMissingTranslations(collect($english)->except('sections')->all(), $translations['en'] ?? []);
            $sections = $page->sections ?? [];
            foreach ($english['sections'] as $index => $sectionTranslation) {
                if (! isset($sections[$index])) {
                    continue;
                }
                $sections[$index]['translations']['en'] = $this->fillMissingTranslations(collect($sectionTranslation)->except('items')->all(), $sections[$index]['translations']['en'] ?? []);
                foreach ($sectionTranslation['items'] ?? [] as $itemIndex => $itemTranslation) {
                    if (! isset($sections[$index]['items'][$itemIndex])) {
                        continue;
                    }
                    $sections[$index]['items'][$itemIndex]['translations']['en'] = $this->fillMissingTranslations(
                        $itemTranslation,
                        $sections[$index]['items'][$itemIndex]['translations']['en'] ?? [],
                    );
                }
            }
            $page->translations = $translations;
            $page->sections = $sections;
            $page->save();
        }

        $generalSetting = SiteSetting::where('key', 'general')->first();
        if ($generalSetting) {
            $general = $generalSetting->value ?? [];
            $translations = $general['translations'] ?? [];
            $translations['en'] = $this->fillMissingTranslations([
                'company_name' => 'Al Zahraa Contracting, Construction & Building',
                'tagline' => 'Building with confidence. Delivering with care.',
                'address' => 'New Cairo, Egypt',
                'footer_note' => 'From foundation excavation to key handover, your engineering partner at every step.',
                'header_cta_label' => 'Free Estimate',
                'nav' => [['label' => 'Home', 'url' => '/'], ['label' => 'About Us', 'url' => '/about'], ['label' => 'Services', 'url' => '/services'], ['label' => 'Projects', 'url' => '/projects'], ['label' => 'Equipment', 'url' => '/equipment'], ['label' => 'Contact', 'url' => '/contact']],
                'footer_nav' => [['label' => 'About Us', 'url' => '/about'], ['label' => 'Services', 'url' => '/services'], ['label' => 'Projects', 'url' => '/projects'], ['label' => 'Equipment', 'url' => '/equipment'], ['label' => 'Contact', 'url' => '/contact']],
            ], $translations['en'] ?? []);
            $general['translations'] = $translations;
            $generalSetting->value = $general;
            $generalSetting->save();
            SiteSetting::refreshCache();
        }
    }

    private function fillMissingTranslations(array $defaults, array $translations): array
    {
        foreach ($defaults as $key => $value) {
            if (is_array($value) && is_array($translations[$key] ?? null)) {
                foreach ($value as $index => $child) {
                    if (! filled($translations[$key][$index]['label'] ?? null) && is_array($child) && filled($child['label'] ?? null)) {
                        $translations[$key][$index]['label'] = $child['label'];
                    }
                    if (! filled($translations[$key][$index]['url'] ?? null) && is_array($child) && filled($child['url'] ?? null)) {
                        $translations[$key][$index]['url'] = $child['url'];
                    }
                }

                continue;
            }
            if (! filled($translations[$key] ?? null)) {
                $translations[$key] = $value;
            }
        }

        return $translations;
    }
}
