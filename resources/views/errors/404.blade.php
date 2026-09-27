@extends('layouts.site')
@php $settings = \App\Models\SiteSetting::value('general', []); $page = null; @endphp
@section('title',__('الصفحة غير موجودة').' | '.($settings['company_name'] ?? 'الزهراء'))
@section('content')
<section class="section"><div class="container text-center"><span class="eyebrow">404</span><h1>{{ __('الصفحة غير موجودة') }}</h1><p class="text-secondary">{{ __('قد يكون الرابط تغيّر أو لم تعد هذه الصفحة متاحة.') }}</p><a class="btn btn-dark mt-3" href="{{ \App\Support\SiteUrl::route('home') }}">{{ __('العودة للرئيسية') }}</a></div></section>
@endsection
