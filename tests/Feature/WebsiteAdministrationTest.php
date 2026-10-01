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

    public function test_public_pages_and_machine_readable_routes_are_available(): void
    {
        foreach (['/', '/about', '/services', '/projects', '/equipment', '/contact', '/en', '/en/about', '/sitemap.xml', '/robots.txt', '/admin/login'] as $path) {
            $this->get($path)->assertOk();
        }

        $this->get('/robots.txt')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: '.url('/sitemap.xml'));
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_web_responses_include_baseline_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
    }

    public function test_guest_is_redirected_to_login_from_the_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_active_manager_can_sign_in_to_the_admin_dashboard(): void
    {
        $manager = User::create([
            'name' => 'Site Manager', 'email' => 'login-manager@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'manager', 'is_active' => true,
        ]);

        $this->from(route('login'))->post(route('admin.login'), [
            'email' => $manager->email,
            'password' => 'A-long-safe-test-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($manager);
    }

    public function test_inactive_manager_cannot_sign_in(): void
    {
        $manager = User::create([
            'name' => 'Inactive Manager', 'email' => 'inactive-manager@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'manager', 'is_active' => false,
        ]);

        $this->from(route('login'))->post(route('admin.login'), [
            'email' => $manager->email,
            'password' => 'A-long-safe-test-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
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

    public function test_quote_request_rejects_the_spam_honeypot_without_saving(): void
    {
        $this->from('/contact')->post('/estimate', [
            'name' => 'Spam Submission', 'whatsapp' => '01012345678', 'project_type' => 'villa',
            'area' => '250', 'message' => 'Please send me a project estimate.', 'company_website' => 'https://spam.example',
        ])->assertRedirect('/contact')->assertSessionHasErrors('company_website');

        $this->assertDatabaseMissing('estimate_requests', ['name' => 'Spam Submission']);
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

    public function test_page_cannot_claim_a_reserved_route_slug(): void
    {
        $manager = User::create([
            'name' => 'Site Manager', 'email' => 'reserved-manager@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'manager', 'is_active' => true,
        ]);

        $this->actingAs($manager)->post('/admin/pages', [
            'title' => 'Reserved Page', 'slug' => 'services', 'status' => 'published', 'sections' => '[]',
        ])->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('content_pages', ['title' => 'Reserved Page']);
    }

    public function test_catalog_order_rejects_items_from_another_kind_without_reordering(): void
    {
        $manager = User::create([
            'name' => 'Site Manager', 'email' => 'order-manager@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'manager', 'is_active' => true,
        ]);
        $service = ContentItem::where('kind', 'service')->firstOrFail();
        $project = ContentItem::where('kind', 'project')->firstOrFail();
        $originalOrder = $service->sort_order;

        $this->actingAs($manager)
            ->postJson(route('admin.catalog.order', ['kind' => 'service']), ['ids' => [$service->id, $project->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids.1');

        $this->assertSame($originalOrder, $service->fresh()->sort_order);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'items.reordered']);
    }

    public function test_page_revision_restore_refuses_an_occupied_slug_without_side_effects(): void
    {
        $manager = User::create([
            'name' => 'Site Manager', 'email' => 'restore-manager@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'manager', 'is_active' => true,
        ]);
        $page = ContentPage::where('slug', 'about')->firstOrFail();
        $occupiedPage = ContentPage::where('slug', 'contact')->firstOrFail();
        $revision = ContentRevision::create([
            'model_type' => 'page',
            'model_id' => $page->id,
            'version_data' => array_merge($page->toArray(), ['slug' => $occupiedPage->slug, 'title' => 'Older About Page']),
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->from('/admin/pages/'.$page->id.'/edit')
            ->post(route('admin.revisions.restore', ['revision' => $revision->id]))
            ->assertRedirect('/admin/pages/'.$page->id.'/edit')
            ->assertSessionHasErrors('revision');

        $this->assertSame('about', $page->fresh()->slug);
        $this->assertSame(1, ContentRevision::where('model_type', 'page')->where('model_id', $page->id)->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'page.restored', 'subject_id' => $page->id]);
    }

    public function test_page_revision_restore_saves_revision_and_audit_together(): void
    {
        $manager = User::create([
            'name' => 'Site Manager', 'email' => 'restore-success-manager@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'manager', 'is_active' => true,
        ]);
        $page = ContentPage::where('slug', 'about')->firstOrFail();
        $revision = ContentRevision::create([
            'model_type' => 'page',
            'model_id' => $page->id,
            'version_data' => array_merge($page->toArray(), ['slug' => 'about-previous', 'title' => 'Older About Page']),
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.revisions.restore', ['revision' => $revision->id]))
            ->assertSessionHas('status', 'تم استعادة الإصدار.');

        $this->assertSame('about-previous', $page->fresh()->slug);
        $this->assertSame(2, ContentRevision::where('model_type', 'page')->where('model_id', $page->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'page.restored', 'subject_id' => $page->id]);
    }

    public function test_last_active_manager_cannot_be_demoted(): void
    {
        $manager = User::create([
            'name' => 'Only Manager', 'email' => 'only-manager@example.test', 'password' => 'A-long-safe-test-password',
            'role' => 'manager', 'is_active' => true,
        ]);

        $this->actingAs($manager)->put(route('admin.users.update', ['user' => $manager->id]), [
            'name' => $manager->name,
            'email' => $manager->email,
            'role' => 'editor',
            'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.updated', 'subject_id' => $manager->id]);
    }
}
