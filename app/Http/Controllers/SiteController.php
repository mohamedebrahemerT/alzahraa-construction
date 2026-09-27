<?php

namespace App\Http\Controllers;

use App\Mail\EstimateReceived;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\EstimateRequest;
use App\Models\SiteSetting;
use App\Support\SiteUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class SiteController extends Controller
{
    public function home(): View
    {
        return $this->page('home');
    }

    public function about(): View
    {
        return $this->page('about');
    }

    public function services(): View
    {
        return $this->page('services');
    }

    public function projects(): View
    {
        return $this->page('projects');
    }

    public function equipment(): View
    {
        return $this->page('equipment');
    }

    public function contact(): View
    {
        return $this->page('contact');
    }

    public function page(string $slug): View
    {
        $page = ContentPage::where('slug', $slug)->where('status', 'published')->firstOrFail()->applyLocale();
        $all = ContentItem::where('is_published', true)->orderBy('sort_order')->get();
        $all->each(fn (ContentItem $item) => $item->applyLocale());
        $all = $all->groupBy('kind');
        $settings = SiteSetting::value('general', []);

        return view('site.page', [
            'page' => $page, 'sections' => $page->sections ?? [],
            'services' => $all->get('service', collect()), 'projects' => $all->get('project', collect()),
            'equipment' => $all->get('equipment', collect()), 'team' => $all->get('team', collect()),
            'settings' => $settings, 'media' => [],
        ]);
    }

    public function service(string $slug): View
    {
        $item = ContentItem::where('kind', 'service')->where('slug', $slug)->where('is_published', true)->firstOrFail()->applyLocale();

        return view('site.detail', ['item' => $item, 'kind' => 'service', 'settings' => SiteSetting::value('general', [])]);
    }

    public function project(string $slug): View
    {
        $item = ContentItem::where('kind', 'project')->where('slug', $slug)->where('is_published', true)->firstOrFail()->applyLocale();

        return view('site.detail', ['item' => $item, 'kind' => 'project', 'settings' => SiteSetting::value('general', [])]);
    }

    public function estimate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'whatsapp' => ['required', 'string', 'max:40', 'regex:/^[0-9+().\s-]{8,40}$/'],
            'project_type' => ['required', 'in:villa,building,factory,finishing,other'],
            'area' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'min:5', 'max:3000'],
            'company_website' => ['nullable', 'prohibited'],
        ], ['company_website.prohibited' => __('تعذّر إرسال الطلب. حاول مرة أخرى.')]);

        $estimate = EstimateRequest::create([
            'name' => $validated['name'], 'whatsapp' => $validated['whatsapp'],
            'project_type' => $validated['project_type'], 'area' => $validated['area'] ?? null,
            'message' => $validated['message'], 'status' => 'new',
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);

        $recipient = SiteSetting::value('general', [])['email'] ?? null;
        if (in_array(config('mail.default'), ['log', 'array'], true) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return redirect(SiteUrl::route('contact'))->with('estimate_success', true);
        }

        try {
            Mail::to($recipient)->send(new EstimateReceived($estimate));
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect(SiteUrl::route('contact'))->with('estimate_success', true);
    }

    public function sitemap(): Response
    {
        $pages = ContentPage::where('status', 'published')->orderBy('sort_order')->get();
        $items = ContentItem::where('is_published', true)->whereIn('kind', ['project', 'service'])->get();

        return response()->view('site.sitemap', compact('pages', 'items'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /preview\nSitemap: ".url('/sitemap.xml')."\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
