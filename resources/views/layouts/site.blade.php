<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', (($page ?? null)?->meta_title ?? ($settings['company_name'] ?? 'الزهراء للمقاولات')))</title>
    <meta name="description" content="@yield('description', (($page ?? null)?->meta_description ?? ($settings['tagline'] ?? 'مقاولات وتشييد وبناء'))) ">
    @if(!empty($preview))<meta name="robots" content="noindex,nofollow">@endif
    <meta property="og:title" content="@yield('title', (($page ?? null)?->meta_title ?? ($settings['company_name'] ?? 'الزهراء للمقاولات'))) ">
    <meta property="og:description" content="@yield('description', (($page ?? null)?->meta_description ?? ($settings['tagline'] ?? 'مقاولات وتشييد وبناء'))) ">
    <meta property="og:type" content="website"><meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_EG' : 'en_US' }}">
    <link rel="alternate" hreflang="ar" href="{{ \App\Support\SiteUrl::language('ar') }}"><link rel="alternate" hreflang="en" href="{{ \App\Support\SiteUrl::language('en') }}"><link rel="alternate" hreflang="x-default" href="{{ \App\Support\SiteUrl::language('ar') }}">
    @if(!empty(($page ?? null)?->share_image))<meta property="og:image" content="{{ asset('storage/'.ltrim($page->share_image, '/')) }}">@endif
    @if(!empty($settings['favicon']))<link rel="icon" href="{{ asset('storage/'.ltrim($settings['favicon'], '/')) }}">@endif
    
    
    <link rel="stylesheet" href="{{ asset('assets/site/site.css') }}">
    <script src="{{ asset('assets/site/site.js') }}" defer></script>
    <style>:root{--brand:{{ $settings['primary_color'] ?? '#102332' }};--gold:{{ $settings['accent_color'] ?? '#c59d5f' }};--font:'{{ $settings['font'] ?? 'Cairo' }}',sans-serif}</style>
</head>
<body class="site-body">
@php
    $navItems = $settings['nav'] ?? [];
    $currentPath = preg_replace('#^en(?:/|$)#', '', trim(request()->path(), '/')) ?? trim(request()->path(), '/');
    $waDigits = preg_replace('/\D+/', '', (string)($settings['whatsapp'] ?? ''));
    $validWhatsApp = strlen($waDigits) >= 10 && strlen($waDigits) <= 15;
    $phoneDigits = preg_replace('/\D+/', '', (string)($settings['phone'] ?? ''));
    $validPhone = strlen($phoneDigits) >= 8 && strlen($phoneDigits) <= 15 && ! str_contains((string)($settings['phone'] ?? ''), 'x');
@endphp
<header class="site-header">
    @if($validPhone || !empty($settings['email']))<div class="topline"><div class="container topline-inner"><span>{{ $settings['tagline'] ?? __('نبني بثقة، ونُسلّم بإتقان') }}</span><span>@if($validPhone)<a href="tel:{{ $phoneDigits }}">{{ $settings['phone'] }}</a>@endif @if($validPhone && !empty($settings['email'])) &nbsp;·&nbsp; @endif @if(!empty($settings['email']))<a href="mailto:{{ $settings['email'] }}">{{ $settings['email'] }}</a>@endif</span></div></div>@endif
    <div class="container nav-wrap">
        <a class="brand" href="{{ \App\Support\SiteUrl::route('home') }}" aria-label="{{ __('الرئيسية') }}">
            @if(!empty($settings['logo']))<img class="brand-logo" src="{{ asset('storage/'.ltrim($settings['logo'], '/')) }}" alt="{{ __('شعار :name', ['name' => $settings['company_name'] ?? 'الزهراء']) }}">@else<span class="brand-mark">ز</span>@endif
            <span class="brand-copy"><strong>{{ $settings['company_name'] ?? 'الزهراء للمقاولات' }}</strong><small>{{ $settings['tagline'] ?? __('نبني بثقة، ونُسلّم بإتقان') }}</small></span>
        </a>
        <button class="nav-toggle" type="button" data-menu-toggle aria-label="{{ __('فتح القائمة') }}" aria-expanded="false"><i class="bi bi-list"></i></button>
        <nav class="site-nav" data-site-nav aria-label="{{ __('القائمة الرئيسية') }}">
            @foreach($navItems as $nav)
                <a class="{{ $currentPath === trim($nav['url'] ?? '', '/') ? 'active' : '' }}" href="{{ \App\Support\SiteUrl::path($nav['url'] ?? '/') }}">{{ $nav['label'] ?? '' }}</a>
            @endforeach
            <a class="nav-cta" href="{{ \App\Support\SiteUrl::path($settings['header_cta_url'] ?? '/contact') }}">{{ $settings['header_cta_label'] ?? __('مقايسة مجانية') }} <i class="bi bi-arrow-left"></i></a>
            <div class="language-switch" aria-label="{{ __('اختيار اللغة') }}"><a href="{{ \App\Support\SiteUrl::language('ar') }}" lang="ar" hreflang="ar" @class(['active' => app()->getLocale() === 'ar'])>عربي</a><span aria-hidden="true">|</span><a href="{{ \App\Support\SiteUrl::language('en') }}" lang="en" hreflang="en" @class(['active' => app()->getLocale() === 'en'])>English</a></div>
        </nav>
    </div>
</header>
<main>@yield('content')</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div><a class="brand" href="{{ \App\Support\SiteUrl::route('home') }}">@if(!empty($settings['logo']))<img class="brand-logo" src="{{ asset('storage/'.ltrim($settings['logo'], '/')) }}" alt="">@else<span class="brand-mark">ز</span>@endif<span class="brand-copy"><strong>{{ $settings['company_name'] ?? 'الزهراء للمقاولات' }}</strong><small>{{ __('مقاولات وتشييد وبناء') }}</small></span></a><p style="margin-top:18px">{{ $settings['footer_note'] ?? __('من أول حفر الأساس إلى تسليم المفتاح، شريكك الهندسي في كل خطوة.') }}</p><div class="social-links">@foreach(['facebook'=>__('فيسبوك'),'instagram'=>__('إنستغرام'),'linkedin'=>__('لينكدإن')] as $key=>$label)@if(!empty($settings[$key]))<a href="{{ $settings[$key] }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>@endif @endforeach</div></div>
            <div><h3>{{ __('تصفح الموقع') }}</h3><div class="footer-links">@foreach(($settings['footer_nav'] ?? $navItems) as $nav)<a href="{{ \App\Support\SiteUrl::path($nav['url'] ?? '/') }}">{{ $nav['label'] ?? '' }}</a>@endforeach</div></div>
            <div><h3>{{ __('تواصل معنا') }}</h3><div class="footer-links"><span>{{ $settings['address'] ?? __('القاهرة الجديدة، مصر') }}</span>@if($validPhone)<a href="tel:{{ $phoneDigits }}">{{ $settings['phone'] }}</a>@else<span>{{ $settings['phone'] ?? '' }}</span>@endif @if(!empty($settings['email']))<a href="mailto:{{ $settings['email'] }}">{{ $settings['email'] }}</a>@endif</div></div>
        </div>
        <div class="footer-bottom"><span>© {{ date('Y') }} {{ $settings['company_name'] ?? 'الزهراء للمقاولات' }}. {{ __('جميع الحقوق محفوظة.') }}</span><span>{{ __('تصميم وتنفيذ شركة الزهراء') }}</span></div>
    </div>
</footer>
<a class="whatsapp-float" href="{{ $validWhatsApp ? 'https://wa.me/'.$waDigits : \App\Support\SiteUrl::route('contact') }}" aria-label="{{ __('مقايسة مجانية') }}" {{ $validWhatsApp ? 'target=_blank rel=noopener noreferrer' : '' }}><i class="bi bi-whatsapp"></i> {{ __('مقايسة مجانية') }}</a>
</body></html>
