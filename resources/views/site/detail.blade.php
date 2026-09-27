@extends('layouts.site')
@section('title', $item->title.' | '.($settings['company_name'] ?? 'الزهراء للمقاولات'))
@section('content')
<section class="detail-hero" style="background-image:url('{{ asset('storage/'.ltrim($item->image ?? 'site/1.jpeg','/')) }}')">
    <div class="container">
        <span class="eyebrow">{{ $kind === 'project' ? __('نموذج مشروع تصوري') : __('خدماتنا الهندسية') }}</span>
        <h1>{{ $item->title }}</h1><p>{{ $item->description }}</p>
    </div>
</section>
<section class="detail-main">
    <div class="container detail-layout">
        <article class="detail-copy">
            <p>{{ $item->body ?: $item->description }}</p>
            @if($kind === 'project' && $item->is_demo)
                <div class="demo-notice"><strong>{{ __('نموذج تصوري تجريبي:') }}</strong> {{ __('بيانات وصورة هذا المشروع للعرض الأولي، ولا تمثل سجلًا موثقًا لأعمال منفذة من الشركة.') }}</div>
            @endif
            @if($kind === 'project' && count($item->data['gallery'] ?? []))
                <div class="gallery-grid mt-4">
                    @foreach($item->data['gallery'] as $image)
                        <img loading="lazy" src="{{ asset('storage/'.ltrim($image,'/')) }}" alt="{{ $item->alt ?: $item->title }}">
                    @endforeach
                </div>
            @endif
            <a class="btn btn-dark" href="{{ \App\Support\SiteUrl::route('contact') }}">{{ __('ناقش مشروعك معنا') }} <i class="bi bi-arrow-left"></i></a>
        </article>
        <aside class="detail-aside">
            @if($kind === 'project')
                <div class="project-meta"><span>{{ $item->location }}</span></div>
                <p><strong>{{ __('نوع المشروع') }}</strong>{{ $item->category }}</p>
                <p><strong>{{ __('سنة النموذج') }}</strong>{{ $item->project_year }}</p>
                @if($item->area)<p><strong>{{ __('المساحة') }}</strong>{{ number_format($item->area) }} {{ __('م²') }}</p>@endif
                <span class="chip">{{ __('نموذج تصوري') }}</span>
            @else
                <p><strong>{{ __('مجال الخدمة') }}</strong>{{ $item->category ?: __('مقاولات وتشييد') }}</p>
                <p>{{ $item->description }}</p>
            @endif
        </aside>
    </div>
</section>
@endsection
