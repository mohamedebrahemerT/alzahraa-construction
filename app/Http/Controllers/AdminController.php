<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\ContentRevision;
use App\Models\EstimateRequest;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    private const KINDS = ['service', 'project', 'equipment', 'team'];

    private const RESERVED_PAGE_SLUGS = ['admin', 'en', 'services', 'projects', 'contact', 'equipment', 'about', 'sitemap', 'robots', 'up'];

    public function loginForm(): View
    {
        return view('admin.login', ['settings' => SiteSetting::value('general', [])]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dashboard(): View
    {
        return $this->adminView('admin.dashboard', [
            'pageCount' => ContentPage::count(), 'itemCount' => ContentItem::count(),
            'newCount' => EstimateRequest::where('status', 'new')->count(),
            'mediaCount' => MediaAsset::count(), 'latest' => EstimateRequest::latest()->limit(6)->get(),
            'logs' => AuditLog::latest()->limit(8)->get(),
        ]);
    }

    public function pages(): View
    {
        return $this->adminView('admin.pages.index', ['pages' => ContentPage::orderBy('sort_order')->get()]);
    }

    public function reorderPages(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists('content_pages', 'id')],
        ])['ids'];
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                ContentPage::whereKey($id)->update(['sort_order' => $index + 1]);
            }
            $this->audit('pages.reordered', 'page', null, 'إعادة ترتيب الصفحات.');
        });

        return response()->json(['message' => 'تم حفظ ترتيب الصفحات.']);
    }

    public function pageForm(?int $page = null): View
    {
        $record = $page ? ContentPage::findOrFail($page) : new ContentPage(['status' => 'draft', 'sections' => []]);

        return $this->adminView('admin.pages.edit', [
            'page' => $record, 'media' => MediaAsset::latest()->get(),
            'revisions' => $record->exists ? ContentRevision::where('model_type', 'page')->where('model_id', $record->id)->latest()->limit(20)->get() : collect(),
        ]);
    }

    public function pagePreview(int $page): View
    {
        return $this->adminView('admin.preview', ['page' => ContentPage::findOrFail($page)]);
    }

    public function pagePreviewFrame(int $page): View
    {
        $record = ContentPage::findOrFail($page);
        $all = ContentItem::where('is_published', true)->orderBy('sort_order')->get()->groupBy('kind');

        return view('site.page', [
            'page' => $record, 'sections' => $record->sections ?? [], 'services' => $all->get('service', collect()),
            'projects' => $all->get('project', collect()), 'equipment' => $all->get('equipment', collect()),
            'team' => $all->get('team', collect()), 'settings' => SiteSetting::value('general', []), 'preview' => true,
        ]);
    }

    public function savePage(Request $request, ?int $page = null): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => [
                'required', 'alpha_dash', 'max:180', Rule::unique('content_pages', 'slug')->ignore($page),
                Rule::notIn(self::RESERVED_PAGE_SLUGS),
            ],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'title_en' => ['nullable', 'string', 'max:180'],
            'meta_title_en' => ['nullable', 'string', 'max:180'],
            'meta_description_en' => ['nullable', 'string', 'max:320'],
            'share_image' => ['nullable', 'string', 'max:255'],
            'sections' => ['required', 'string', 'max:1000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
        $sections = json_decode($data['sections'], true);
        $sections = $this->cleanSections($sections);
        if ($sections === null) {
            return back()->withErrors(['sections' => 'تعذّر حفظ الأقسام. راجع أنواع الأقسام وترتيب البيانات.'])->withInput();
        }
        $record = $page ? ContentPage::findOrFail($page) : new ContentPage;
        DB::transaction(function () use ($record, $data, $sections, $page) {
            if ($record->exists) {
                $this->saveRevision('page', $record->id, $record->toArray());
            }
            $record->fill([
                'title' => $data['title'], 'slug' => $data['slug'], 'status' => $data['status'],
                'meta_title' => $data['meta_title'] ?? null, 'meta_description' => $data['meta_description'] ?? null,
                'share_image' => $data['share_image'] ?? null, 'sections' => $sections,
                'sort_order' => $data['sort_order'] ?? 0, 'created_by' => $record->created_by ?? auth()->id(),
            ])->save();
            $translations = $record->translations ?? [];
            $translations['en'] = array_merge($translations['en'] ?? [], [
                'title' => trim(strip_tags($data['title_en'] ?? '')),
                'meta_title' => trim(strip_tags($data['meta_title_en'] ?? '')),
                'meta_description' => trim(strip_tags($data['meta_description_en'] ?? '')),
            ]);
            $record->translations = $translations;
            $record->save();
            $this->audit($page ? 'page.updated' : 'page.created', 'page', $record->id, 'حفظ صفحة: '.$record->title);
        });

        return $this->saved($request, 'تم حفظ الصفحة.');
    }

    private function cleanSections(mixed $sections): ?array
    {
        $types = ['hero', 'page-hero', 'stats', 'text-image', 'services', 'process', 'before-after', 'features', 'projects', 'cta', 'values', 'team', 'gallery', 'equipment', 'contact'];
        if (! is_array($sections) || ! array_is_list($sections) || count($sections) > 30) {
            return null;
        }
        $clean = [];
        foreach ($sections as $section) {
            if (! is_array($section) || ! in_array($section['type'] ?? '', $types, true)) {
                return null;
            }
            $out = [
                'id' => substr((string) ($section['id'] ?? Str::random(10)), 0, 40), 'type' => $section['type'],
                'title' => Str::limit(strip_tags((string) ($section['title'] ?? '')), 180, ''),
                'text' => Str::limit(strip_tags((string) ($section['text'] ?? '')), 3000, ''),
                'image' => preg_match('/^(site|media)\/[A-Za-z0-9_\/. -]+$/', (string) ($section['image'] ?? '')) && ! str_contains((string) ($section['image'] ?? ''), '..') ? Str::limit((string) $section['image'], 255, '') : '',
                'alt' => Str::limit(strip_tags((string) ($section['alt'] ?? '')), 255, ''),
                'background' => in_array($section['background'] ?? '', ['light', 'sand', 'navy', 'white'], true) ? $section['background'] : 'light',
                'align' => in_array($section['align'] ?? '', ['right', 'center', 'left'], true) ? $section['align'] : 'right',
                'columns' => in_array((int) ($section['columns'] ?? 2), [1, 2, 3, 4], true) ? (int) $section['columns'] : 2,
                'spacing' => in_array($section['spacing'] ?? '', ['compact', 'normal', 'spacious'], true) ? $section['spacing'] : 'normal',
                'visible' => (bool) ($section['visible'] ?? true),
                'image_has_text' => (bool) ($section['image_has_text'] ?? false),
            ];
            $out['translations']['en'] = [];
            foreach (['title', 'text', 'alt', 'primary_label', 'secondary_label', 'link_label'] as $key) {
                $value = trim(strip_tags((string) ($section['translations']['en'][$key] ?? '')));
                if ($value !== '') {
                    $out['translations']['en'][$key] = Str::limit($value, $key === 'text' ? 3000 : 1000, '');
                }
            }
            foreach (['primary_label', 'primary_url', 'secondary_label', 'secondary_url', 'link_label', 'link_url'] as $key) {
                if (isset($section[$key])) {
                    $value = trim(strip_tags((string) $section[$key]));
                    $out[$key] = str_ends_with($key, '_url') ? ((str_starts_with($value, '/') && ! str_starts_with($value, '//')) || preg_match('/^https:\/\//i', $value) ? Str::limit($value, 255, '') : '') : Str::limit($value, 180, '');
                }
            }
            foreach (['items', 'images'] as $key) {
                if (isset($section[$key]) && is_array($section[$key])) {
                    $out[$key] = array_slice($section[$key], 0, 30);
                    if ($key === 'images') {
                        $out[$key] = array_values(array_filter($out[$key], fn ($image) => is_string($image) && preg_match('/^(site|media)\/[A-Za-z0-9_\/. -]+$/', $image) && ! str_contains($image, '..')));
                    }
                    if ($key === 'items') {
                        $out[$key] = array_map(function ($item) {
                            if (! is_array($item)) {
                                return ['label' => Str::limit(strip_tags((string) $item), 180, '')];
                            }
                            $cleanItem = collect($item)->only(['value', 'label', 'title', 'text'])
                                ->map(fn ($value) => is_scalar($value) ? Str::limit(strip_tags((string) $value), 1000, '') : '')
                                ->all();
                            foreach (['value', 'label', 'title', 'text'] as $field) {
                                $translated = trim(strip_tags((string) ($item['translations']['en'][$field] ?? '')));
                                if ($translated !== '') {
                                    $cleanItem['translations']['en'][$field] = Str::limit($translated, 1000, '');
                                }
                            }

                            return $cleanItem;
                        }, $out[$key]);
                    }
                }
            }
            $clean[] = $out;
        }

        return $clean;
    }

    public function deletePage(Request $request, int $page): RedirectResponse|JsonResponse
    {
        $record = ContentPage::findOrFail($page);
        abort_if($record->slug === 'home', 422, 'لا يمكن حذف الصفحة الرئيسية.');
        $this->audit('page.deleted', 'page', $record->id, 'حذف صفحة: '.$record->title);
        $record->delete();

        return $this->saved($request, 'تم حذف الصفحة.');
    }

    public function restoreRevision(Request $request, int $revision): RedirectResponse|JsonResponse
    {
        $version = ContentRevision::where('model_type', 'page')->findOrFail($revision);
        $record = ContentPage::findOrFail($version->model_id);
        $snapshot = $version->version_data;
        if (ContentPage::where('slug', $snapshot['slug'] ?? null)->where('id', '!=', $record->id)->exists()) {
            return back()->withErrors(['revision' => 'لا يمكن استعادة الإصدار لأن عنوان الصفحة مستخدم لصفحة أخرى.']);
        }

        DB::transaction(function () use ($record, $snapshot): void {
            $this->saveRevision('page', $record->id, $record->toArray());
            $record->fill(collect($snapshot)->only(['title', 'slug', 'status', 'meta_title', 'meta_description', 'share_image', 'sections', 'sort_order', 'translations'])->all())->save();
            $this->audit('page.restored', 'page', $record->id, 'استعادة إصدار للصفحة: '.$record->title);
        });

        return $this->saved($request, 'تم استعادة الإصدار.');
    }

    public function catalog(string $kind): View
    {
        $this->assertKind($kind);
        $items = ContentItem::where('kind', $kind)->orderBy('sort_order')->get();
        $revisions = ContentRevision::query()
            ->where('model_type', 'item')
            ->whereIn('model_id', $items->modelKeys())
            ->latest()
            ->get()
            ->groupBy('model_id')
            ->map(fn ($versions) => $versions->take(10));

        return $this->adminView('admin.catalog', [
            'kind' => $kind, 'items' => $items, 'revisions' => $revisions,
            'media' => MediaAsset::latest()->get(),
        ]);
    }

    public function saveItem(Request $request, string $kind, ?int $id = null): RedirectResponse|JsonResponse
    {
        $this->assertKind($kind);
        $item = $id ? ContentItem::where('kind', $kind)->findOrFail($id) : new ContentItem(['kind' => $kind]);
        $rules = [
            'title' => ['required', 'string', 'max:180'], 'slug' => ['nullable', 'alpha_dash', 'max:180', Rule::unique('content_items', 'slug')->where('kind', $kind)->ignore($id)],
            'category' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1200'],
            'body' => ['nullable', 'string', 'max:10000'], 'image' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:180'], 'category_en' => ['nullable', 'string', 'max:100'],
            'description_en' => ['nullable', 'string', 'max:1200'], 'body_en' => ['nullable', 'string', 'max:10000'],
            'alt_en' => ['nullable', 'string', 'max:255'], 'location_en' => ['nullable', 'string', 'max:120'],
            'quantity_en' => ['nullable', 'string', 'max:100'],
            'project_year' => ['nullable', 'integer', 'between:1950,2100'], 'area' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'quantity' => ['nullable', 'string', 'max:100'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_published' => ['nullable', 'boolean'], 'is_demo' => ['nullable', 'boolean'],
            'data_json' => ['nullable', 'string', 'max:200000'],
            'gallery' => ['nullable', 'array', 'max:30'], 'gallery.*' => ['string', 'distinct', Rule::exists('media_assets', 'path')],
        ];
        $data = $request->validate($rules);
        $extra = json_decode($data['data_json'] ?? '{}', true);
        if (! is_array($extra)) {
            return back()->withErrors(['data_json' => 'بيانات المعرض غير صالحة.'])->withInput();
        }
        if ($kind === 'project') {
            $extra['gallery'] = array_values($data['gallery'] ?? []);
        }
        $before = $item->exists ? $item->toArray() : null;
        DB::transaction(function () use ($item, $kind, $data, $extra, $request, $before, $id): void {
            $item->fill(collect($data)->except([
                'data_json', 'gallery', 'title_en', 'category_en', 'description_en', 'body_en', 'alt_en', 'location_en', 'quantity_en',
            ])->all());
            $item->kind = $kind;
            $item->data = $extra;
            $translations = $item->translations ?? [];
            foreach (['title', 'category', 'description', 'body', 'alt', 'location', 'quantity'] as $field) {
                $englishField = $field.'_en';
                if (array_key_exists($englishField, $data)) {
                    $translations['en'][$field] = trim(strip_tags($data[$englishField] ?? ''));
                }
            }
            $item->translations = $translations;
            $item->is_published = $request->boolean('is_published');
            $item->is_demo = $request->boolean('is_demo');
            $item->save();
            if ($before) {
                $this->saveRevision('item', $item->id, $before);
            }
            $this->audit($id ? 'item.updated' : 'item.created', $kind, $item->id, 'حفظ عنصر: '.$item->title);
        });

        return $this->saved($request, 'تم حفظ المحتوى.');
    }

    public function deleteItem(Request $request, string $kind, int $id): RedirectResponse|JsonResponse
    {
        $this->assertKind($kind);
        $item = ContentItem::where('kind', $kind)->findOrFail($id);
        $this->audit('item.deleted', $kind, $id, 'حذف عنصر: '.$item->title);
        $item->delete();

        return $this->saved($request, 'تم حذف العنصر.');
    }

    public function restoreItemRevision(Request $request, string $kind, int $id, int $revision): RedirectResponse|JsonResponse
    {
        $this->assertKind($kind);
        $item = ContentItem::where('kind', $kind)->findOrFail($id);
        $version = ContentRevision::where('model_type', 'item')
            ->where('model_id', $item->id)
            ->findOrFail($revision);
        $snapshot = $version->version_data;
        $slug = $snapshot['slug'] ?? null;

        if ($slug && ContentItem::where('kind', $kind)->where('slug', $slug)->where('id', '!=', $item->id)->exists()) {
            return back()->withErrors(['revision' => 'تعذّرت الاستعادة لأن رابط العنصر مستخدم حاليًا.']);
        }

        DB::transaction(function () use ($item, $kind, $snapshot): void {
            $this->saveRevision('item', $item->id, $item->toArray());
            $item->fill(collect($snapshot)->only([
                'title', 'slug', 'category', 'description', 'body', 'image', 'alt', 'location',
                'project_year', 'area', 'quantity', 'data', 'sort_order', 'is_published', 'is_demo', 'translations',
            ])->all());
            $item->kind = $kind;
            $item->save();
            $this->audit('item.restored', $kind, $item->id, 'استعادة إصدار للعنصر: '.$item->title);
        });

        return $this->saved($request, 'تم استعادة الإصدار.');
    }

    public function reorder(Request $request, string $kind): JsonResponse
    {
        $this->assertKind($kind);
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists('content_items', 'id')->where('kind', $kind)],
        ])['ids'];
        DB::transaction(function () use ($ids, $kind): void {
            foreach ($ids as $index => $id) {
                ContentItem::where('kind', $kind)->whereKey($id)->update(['sort_order' => $index + 1]);
            }
            $this->audit('items.reordered', $kind, null, 'إعادة ترتيب عناصر '.$kind);
        });

        return response()->json(['message' => 'تم حفظ الترتيب.']);
    }

    public function settings(): View
    {
        return $this->adminView('admin.settings', ['general' => SiteSetting::value('general', []), 'media' => MediaAsset::latest()->get()]);
    }

    public function saveSettings(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:180'], 'tagline' => ['nullable', 'string', 'max:240'],
            'phone' => ['nullable', 'string', 'max:40'], 'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'], 'address' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'string', 'max:255', Rule::exists('media_assets', 'path')], 'favicon' => ['nullable', 'string', 'max:255', Rule::exists('media_assets', 'path')],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => ['required', Rule::in(['Cairo', 'Tajawal', 'IBM Plex Sans Arabic'])],
            'facebook' => ['nullable', 'url', 'max:255'], 'instagram' => ['nullable', 'url', 'max:255'], 'linkedin' => ['nullable', 'url', 'max:255'],
            'footer_note' => ['nullable', 'string', 'max:600'], 'nav_text' => ['nullable', 'string', 'max:5000'],
            'footer_nav_text' => ['nullable', 'string', 'max:5000'], 'header_cta_label' => ['nullable', 'string', 'max:100'],
            'company_name_en' => ['nullable', 'string', 'max:180'], 'tagline_en' => ['nullable', 'string', 'max:240'],
            'address_en' => ['nullable', 'string', 'max:200'], 'footer_note_en' => ['nullable', 'string', 'max:600'],
            'header_cta_label_en' => ['nullable', 'string', 'max:100'], 'nav_text_en' => ['nullable', 'string', 'max:5000'],
            'footer_nav_text_en' => ['nullable', 'string', 'max:5000'],
            'header_cta_url' => ['nullable', 'string', 'max:255'],
            'show_demo_notice' => ['nullable', 'boolean'],
        ]);
        $nav = $this->parseMenu($data['nav_text'] ?? '');
        $footerNav = $this->parseMenu($data['footer_nav_text'] ?? '');
        $navEn = $this->parseMenu($data['nav_text_en'] ?? '');
        $footerNavEn = $this->parseMenu($data['footer_nav_text_en'] ?? '');
        $ctaUrl = trim((string) ($data['header_cta_url'] ?? '/contact'));
        if (! ((str_starts_with($ctaUrl, '/') && ! str_starts_with($ctaUrl, '//')) || preg_match('/^https:\/\//i', $ctaUrl))) {
            $ctaUrl = '/contact';
        }
        $existing = SiteSetting::value('general', []);
        $translatedSettings = $existing['translations'] ?? [];
        $translatedSettings['en'] = array_merge($translatedSettings['en'] ?? [], collect($data)->only([
            'company_name_en', 'tagline_en', 'address_en', 'footer_note_en', 'header_cta_label_en',
        ])->mapWithKeys(fn ($value, $key) => [Str::beforeLast($key, '_en') => trim(strip_tags((string) $value))])->all(), [
            'nav' => $navEn, 'footer_nav' => $footerNavEn,
        ]);
        $general = array_merge($existing, collect($data)->except([
            'nav_text', 'footer_nav_text', 'nav_text_en', 'footer_nav_text_en', 'company_name_en', 'tagline_en',
            'address_en', 'footer_note_en', 'header_cta_label_en',
        ])->all(), [
            'nav' => $nav, 'footer_nav' => $footerNav, 'header_cta_url' => $ctaUrl,
            'translations' => $translatedSettings,
            'show_demo_notice' => $request->boolean('show_demo_notice'),
        ]);
        SiteSetting::updateOrCreate(['key' => 'general'], ['value' => $general]);
        SiteSetting::refreshCache();
        $this->audit('settings.updated', 'settings', null, 'تحديث إعدادات الموقع العامة والهوية.');

        return $this->saved($request, 'تم تحديث الإعدادات.');
    }

    public function leads(Request $request): View
    {
        $query = EstimateRequest::query()->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('whatsapp', 'like', '%'.$request->q.'%'));
        }

        return $this->adminView('admin.leads', ['leads' => $query->paginate(20)->withQueryString(), 'filter' => $request->only(['q', 'status'])]);
    }

    public function updateLead(Request $request, int $lead): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'following_up', 'completed', 'closed'])], 'notes' => ['nullable', 'string', 'max:4000']]);
        $entry = EstimateRequest::findOrFail($lead);
        $entry->update($data);
        $this->audit('lead.updated', 'estimate_request', $entry->id, 'تحديث حالة طلب مقايسة: '.$entry->name);

        return $this->saved($request, 'تم تحديث الطلب.');
    }

    public function exportLeads()
    {
        $rows = EstimateRequest::orderByDesc('created_at')->get();
        $callback = static function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['الاسم', 'رقم واتساب', 'نوع المشروع', 'المساحة', 'الرسالة', 'الحالة', 'ملاحظات', 'تاريخ الطلب']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->name, $r->whatsapp, $r->project_type, $r->area, $r->message, $r->status, $r->notes, $r->created_at]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, 'alzahraa-estimates-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function media(): View
    {
        return $this->adminView('admin.media', ['media' => MediaAsset::latest()->paginate(36)]);
    }

    public function uploadMedia(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['files' => ['required', 'array', 'max:20'], 'files.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'], 'alt' => ['nullable', 'string', 'max:255']]);
        foreach ($data['files'] as $file) {
            $path = $file->store('media/'.now()->format('Y/m'), 'public');
            MediaAsset::create(['path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'alt' => $data['alt'] ?? '', 'uploaded_by' => auth()->id()]);
        }
        $this->audit('media.uploaded', 'media', null, 'رفع '.count($request->file('files', [])).' صورة.');

        return $this->saved($request, 'تم رفع الصور.');
    }

    public function updateMedia(Request $request, int $media): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['alt' => ['nullable', 'string', 'max:255'], 'alt_en' => ['nullable', 'string', 'max:255']]);
        $asset = MediaAsset::findOrFail($media);
        $translations = $asset->translations ?? [];
        $translations['en']['alt'] = trim(strip_tags($data['alt_en'] ?? ''));
        $asset->update(['alt' => $data['alt'] ?? '', 'translations' => $translations]);
        $this->audit('media.updated', 'media', $media, 'تعديل النص البديل لصورة.');

        return $this->saved($request, 'تم حفظ النص البديل.');
    }

    public function deleteMedia(Request $request, int $media): RedirectResponse|JsonResponse
    {
        $asset = MediaAsset::findOrFail($media);
        $used = ContentItem::where('image', $asset->path)->exists()
            || ContentItem::whereJsonContains('data->gallery', $asset->path)->exists()
            || ContentPage::where('share_image', $asset->path)->exists();
        if (! $used) {
            $used = ContentPage::get(['sections'])->contains(fn ($page) => $this->containsPath($page->sections, $asset->path));
        }
        if (! $used) {
            $used = $this->containsPath(SiteSetting::value('general', []), $asset->path);
        }
        if ($used) {
            return back()->withErrors(['media' => 'لا يمكن حذف صورة مستخدمة في محتوى منشور. استبدلها أولًا.']);
        }
        Storage::disk($asset->disk)->delete($asset->path);
        $asset->delete();
        $this->audit('media.deleted', 'media', $media, 'حذف صورة من مكتبة الوسائط.');

        return $this->saved($request, 'تم حذف الصورة.');
    }

    public function users(): View
    {
        return $this->adminView('admin.users', ['users' => User::orderBy('name')->get()]);
    }

    public function saveUser(Request $request, ?int $user = null): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(['manager', 'editor'])], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:12', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $record = $user ? User::findOrFail($user) : new User;
        $isActive = $request->boolean('is_active', ! $user);
        if ($record->id === auth()->id() && ! $isActive) {
            return back()->withErrors(['is_active' => 'لا يمكن إيقاف حسابك أثناء استخدامه.']);
        }
        DB::transaction(function () use ($data, $isActive, $record, $user): void {
            $activeManagers = User::where('role', 'manager')->where('is_active', true)->lockForUpdate()->get();
            $removesActiveManager = $record->exists
                && $record->role === 'manager'
                && $record->is_active
                && ($data['role'] !== 'manager' || ! $isActive);
            if ($removesActiveManager && $activeManagers->where('id', '!=', $record->id)->isEmpty()) {
                throw ValidationException::withMessages([
                    'role' => 'يجب إبقاء حساب مدير نشط واحد على الأقل حتى لا تفقد الإدارة صلاحية التحكم بالموقع.',
                ]);
            }

            $record->name = $data['name'];
            $record->email = $data['email'];
            $record->role = $data['role'];
            $record->is_active = $isActive;
            if (! empty($data['password'])) {
                $record->password = Hash::make($data['password']);
            }
            $record->save();
            $this->audit($user ? 'user.updated' : 'user.created', 'user', $record->id, 'حفظ حساب: '.$record->email);
        });

        return $this->saved($request, 'تم حفظ الحساب.');
    }

    public function activity(): View
    {
        return $this->adminView('admin.activity', ['logs' => AuditLog::with('user')->latest()->paginate(30)]);
    }

    private function assertKind(string $kind): void
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
    }

    private function parseMenu(string $lines): array
    {
        $menu = [];
        foreach (preg_split('/\R/u', $lines) as $line) {
            if (! trim($line)) {
                continue;
            }
            [$label, $url] = array_pad(explode('|', $line, 2), 2, '');
            $label = trim(strip_tags($label));
            $url = trim($url);
            if ($label !== '' && ((str_starts_with($url, '/') && ! str_starts_with($url, '//')) || preg_match('/^https:\/\//i', $url))) {
                $menu[] = ['label' => Str::limit($label, 120, ''), 'url' => Str::limit($url, 255, '')];
            }
        }

        return $menu;
    }

    private function containsPath(mixed $value, string $path): bool
    {
        if ($value === $path) {
            return true;
        }
        if (! is_array($value)) {
            return false;
        }
        foreach ($value as $child) {
            if ($this->containsPath($child, $path)) {
                return true;
            }
        }

        return false;
    }

    private function adminView(string $view, array $data = []): View
    {
        return view($view, array_merge(['settings' => SiteSetting::value('general', []), 'media' => collect()], $data));
    }

    private function saved(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    private function saveRevision(string $type, int $id, array $snapshot): void
    {
        ContentRevision::create(['model_type' => $type, 'model_id' => $id, 'version_data' => $snapshot, 'created_by' => auth()->id()]);
    }

    private function audit(string $action, ?string $subject, ?int $id, string $summary): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject_type' => $subject, 'subject_id' => $id, 'summary' => $summary]);
    }
}
