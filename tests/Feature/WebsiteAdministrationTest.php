<?php

namespace Tests\Feature;

use App\Mail\EstimateReceived;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\ContentRevision;
use App\Models\EstimateRequest;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebsiteAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed();
    }

    public function test_homepage_uses_the_saved_brand_assets(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('storage/site/banner.webp')
            ->assertSee('storage/site/logo-mark.webp')
            ->assertSee('storage/site/favicon.png')
            ->assertSee('بانر الزهراء للمقاولات والتشييد والبناء');
    }

    public function test_english_site_renders_translated_content_with_ltr_navigation(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('lang="en" dir="ltr"', false)
            ->assertSee('Request a free estimate')
            ->assertSee('Years of experience')
            ->assertSee('Projects')
            ->assertSee('--section-cols:4')
            ->assertSee('All rights reserved.');

        $this->get('/en/services/concrete')
            ->assertOk()
            ->assertSee('Concrete Structures & Foundations')
            ->assertSee('Excavation, soil replacement');
    }

    public function test_english_estimate_form_posts_to_english_route_and_returns_to_english_contact(): void
    {
        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('Request a Free Estimate')
            ->assertSee('/en/estimate')
            ->assertSee('WhatsApp number');

        $this->post('/en/estimate', [
            'name' => 'New Client', 'whatsapp' => '+201012345678', 'project_type' => 'villa',
            'area' => '250', 'message' => 'I would like an estimate for a villa.', 'company_website' => '',
        ])->assertRedirect('/en/contact')->assertSessionHas('estimate_success', true);

        $this->assertDatabaseHas('estimate_requests', ['name' => 'New Client', 'status' => 'new']);
    }

    public function test_unknown_public_page_displays_arabic_404_page(): void
    {
        $this->get('/page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('الصفحة غير موجودة');
    }

    public function test_quote_request_is_saved_before_success_is_shown(): void
    {
        $this->from('/contact')->post('/estimate', [
            'name' => 'عميل جديد', 'whatsapp' => '01012345678', 'project_type' => 'villa',
            'area' => '250', 'message' => 'أرغب في إنشاء فيلا في القاهرة الجديدة.', 'company_website' => '',
        ])->assertRedirect('/contact')->assertSessionHas('estimate_success', true);

        $this->assertDatabaseHas('estimate_requests', [
            'name' => 'عميل جديد', 'project_type' => 'villa', 'status' => 'new',
        ]);
        $this->assertSame(1, EstimateRequest::count());
    }

    public function test_quote_request_notifies_the_company_when_mail_is_configured(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);

        $this->post('/estimate', [
            'name' => 'عميل البريد', 'whatsapp' => '01012345678', 'project_type' => 'villa',
            'area' => '250', 'message' => 'أرغب في إنشاء فيلا في القاهرة الجديدة.', 'company_website' => '',
        ])->assertRedirect('/contact')->assertSessionHas('estimate_success', true);

        $this->assertDatabaseHas('estimate_requests', ['name' => 'عميل البريد', 'status' => 'new']);
        Mail::assertSent(EstimateReceived::class, fn (EstimateReceived $mail) => $mail->hasTo('info@alzahraa.construction') && $mail->estimate->name === 'عميل البريد');
    }

    public function test_quote_request_does_not_claim_mail_delivery_for_the_log_mailer(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);

        $this->post('/estimate', [
            'name' => 'عميل دون بريد', 'whatsapp' => '01012345678', 'project_type' => 'villa',
            'area' => '250', 'message' => 'أرغب في إنشاء فيلا في القاهرة الجديدة.', 'company_website' => '',
        ])->assertRedirect('/contact')->assertSessionHas('estimate_success', true);

        $this->assertDatabaseHas('estimate_requests', ['name' => 'عميل دون بريد', 'status' => 'new']);
        Mail::assertNothingOutgoing();
    }

    public function test_editor_can_save_a_project_gallery_but_cannot_open_manager_settings(): void
    {
        $editor = User::create([
            'name' => 'Content Editor', 'email' => 'editor@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'editor', 'is_active' => true,
        ]);
        $this->actingAs($editor)->get('/admin/settings')->assertForbidden();

        $this->post('/admin/catalog/project', [
            'title' => 'نموذج مبنى جديد', 'slug' => 'sample-building', 'category' => 'مبنى',
            'description' => 'وصف المشروع التجريبي.', 'body' => 'تفاصيل النموذج.', 'image' => 'site/4.jpeg',
            'alt' => 'مبنى قيد الإنشاء', 'location' => 'القاهرة الجديدة', 'project_year' => '2026', 'area' => '800',
            'title_en' => 'Sample Building', 'category_en' => 'Office Building',
            'description_en' => 'An illustrative office building.', 'body_en' => 'Concept project details.',
            'alt_en' => 'Office building under construction', 'location_en' => 'New Cairo',
            'quantity' => '', 'sort_order' => '1', 'is_published' => '1', 'is_demo' => '1',
            'data_json' => '{}', 'gallery' => ['site/4.jpeg', 'site/15.jpeg'],
        ])->assertSessionHasNoErrors();

        $project = ContentItem::where('slug', 'sample-building')->firstOrFail();
        $this->assertSame(['site/4.jpeg', 'site/15.jpeg'], $project->data['gallery']);
        $this->assertSame('Sample Building', $project->translations['en']['title']);
        $this->assertSame('New Cairo', $project->translations['en']['location']);
    }

    public function test_editor_can_restore_a_previous_catalog_item_version(): void
    {
        $editor = User::create([
            'name' => 'Content Editor', 'email' => 'restore-editor@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'editor', 'is_active' => true,
        ]);
        $project = ContentItem::where('kind', 'project')->firstOrFail();
        $originalTitle = $project->title;

        $this->actingAs($editor)->put(route('admin.catalog.update', ['kind' => 'project', 'id' => $project->id]), [
            'title' => 'عنوان مشروع مؤقت', 'slug' => 'temporary-project', 'category' => $project->category,
            'description' => 'وصف مؤقت.', 'body' => 'تفاصيل مؤقتة.', 'image' => $project->image,
            'alt' => $project->alt, 'location' => $project->location, 'project_year' => $project->project_year,
            'area' => $project->area, 'quantity' => '', 'title_en' => 'Temporary Project',
            'category_en' => 'Temporary Category', 'description_en' => 'Temporary description.',
            'body_en' => 'Temporary details.', 'alt_en' => 'Temporary image', 'location_en' => 'Temporary location',
            'quantity_en' => '', 'sort_order' => $project->sort_order, 'is_published' => '1', 'is_demo' => '1',
            'data_json' => json_encode($project->data ?? [], JSON_UNESCAPED_UNICODE),
            'gallery' => $project->data['gallery'] ?? [],
        ])->assertSessionHasNoErrors();

        $revision = ContentRevision::where('model_type', 'item')->where('model_id', $project->id)->firstOrFail();
        $this->assertSame($originalTitle, $revision->version_data['title']);

        $this->post(route('admin.catalog.revisions.restore', ['kind' => 'project', 'id' => $project->id, 'revision' => $revision->id]))
            ->assertSessionHas('status', 'تم استعادة الإصدار.');

        $this->assertSame($originalTitle, $project->fresh()->title);
        $this->assertSame(2, ContentRevision::where('model_type', 'item')->where('model_id', $project->id)->count());
    }

    public function test_manager_can_update_branding_and_builder_flags(): void
    {
        $manager = User::create([
            'name' => 'Site Manager', 'email' => 'manager@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'manager', 'is_active' => true,
        ]);
        $home = ContentPage::where('slug', 'home')->firstOrFail();
        $hero = collect($home->sections)->firstWhere('type', 'hero');
        $hero['image_has_text'] = true;
        $hero['image'] = 'site/banner.webp';
        $hero['translations']['en']['title'] = 'Al Zahraa for Construction';

        $this->actingAs($manager)->put('/admin/pages/'.$home->id, [
            'title' => $home->title, 'slug' => $home->slug, 'status' => 'published',
            'meta_title' => $home->meta_title, 'meta_description' => $home->meta_description,
            'title_en' => 'Home page English title', 'meta_title_en' => 'Home page search title',
            'meta_description_en' => 'An English search description.',
            'share_image' => '', 'sort_order' => '0', 'sections' => json_encode([$hero], JSON_UNESCAPED_UNICODE),
        ])->assertSessionHasNoErrors();
        $this->assertTrue(ContentPage::findOrFail($home->id)->sections[0]['image_has_text']);
        $this->assertSame('Home page English title', ContentPage::findOrFail($home->id)->translations['en']['title']);
        $this->assertSame('Al Zahraa for Construction', ContentPage::findOrFail($home->id)->sections[0]['translations']['en']['title']);

        $this->put('/admin/settings', [
            'company_name' => 'الزهراء للاختبار', 'tagline' => 'نبني بثقة', 'phone' => '010xxxxxxxx',
            'whatsapp' => '', 'email' => 'info@alzahraa.construction', 'address' => 'القاهرة الجديدة، مصر',
            'logo' => 'site/logo-mark.webp', 'favicon' => 'site/favicon.png',
            'primary_color' => '#112233', 'accent_color' => '#c59d5f', 'font' => 'Cairo',
            'footer_note' => 'نرافق مشروعك من البداية.', 'nav_text' => "الرئيسية|/\nاتصل بنا|/contact",
            'footer_nav_text' => 'الخدمات|/services', 'header_cta_label' => 'اطلب تسعيرًا',
            'company_name_en' => 'Al Zahraa Testing', 'tagline_en' => 'Build with confidence',
            'address_en' => 'New Cairo, Egypt', 'footer_note_en' => 'We build with care.',
            'header_cta_label_en' => 'Get an estimate', 'nav_text_en' => "Home|/\nContact|/contact",
            'footer_nav_text_en' => 'Services|/services',
            'header_cta_url' => '/contact', 'show_demo_notice' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('#112233', SiteSetting::value('general')['primary_color']);
        $this->assertSame('اطلب تسعيرًا', SiteSetting::value('general')['header_cta_label']);
        $this->assertSame('Al Zahraa Testing', SiteSetting::value('general')['translations']['en']['company_name']);
    }
}
