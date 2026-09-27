@extends('layouts.site')
@section('content')
    @foreach($sections as $section)
        @if(($section['visible'] ?? true) !== false)
            @include('site.sections.block', ['s' => $section])
        @endif
    @endforeach
    @if(!empty($settings['show_demo_notice']) && ($page->slug ?? '') === 'projects')
        <div class="container"><div class="demo-notice"><strong>{{ __('تنويه:') }}</strong> {{ __('المشاريع والصور المعروضة هنا نماذج تصورية تجريبية للتصميم، وليست سجلات موثقة لأعمال منفذة من الشركة.') }}</div></div>
    @endif
@endsection
