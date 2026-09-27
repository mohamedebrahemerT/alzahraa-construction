@extends('admin.layout')
@section('title','معاينة '.$page->title)
@section('content')
<div class="admin-title"><div><h1>معاينة: {{ $page->title }}</h1><p class="text-secondary mb-0">تعمل المعاينة على محتوى المسودة دون نشره للزوار.</p></div><a class="btn btn-outline-dark" href="{{ route('admin.pages.edit',$page->id) }}">عودة إلى المحرر</a></div>
<div class="preview-controls"><span class="me-auto align-self-center">حجم العرض:</span><button class="btn btn-outline-dark btn-sm" type="button" data-preview-size="desktop">كمبيوتر</button><button class="btn btn-outline-dark btn-sm" type="button" data-preview-size="mobile">موبايل</button></div>
<iframe class="preview-frame" data-preview-frame src="{{ route('admin.pages.preview.frame',$page->id) }}" title="معاينة الصفحة"></iframe>
@endsection
