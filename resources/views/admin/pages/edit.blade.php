@extends('admin.layout')
@php $isNew = !$page->exists; $pageTitle = $isNew ? 'إنشاء صفحة' : 'تحرير: '.$page->title; $english = $page->translations['en'] ?? []; $sectionValue = old('sections', json_encode($page->sections ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT)); @endphp
@section('title',$pageTitle)
@section('content')
<div class="admin-title"><div><h1>{{ $pageTitle }}</h1><p class="text-secondary mb-0">حرر محتوى الصفحة من الأقسام، ثم احتفظ بها مسودة أو انشرها.</p></div><div class="admin-actions">@if(!$isNew)<a class="btn btn-outline-dark" href="{{ route('admin.pages.preview',$page->id) }}">معاينة</a>@endif<a class="btn btn-outline-dark" href="{{ route('admin.pages.index') }}">عودة للصفحات</a></div></div>
<form method="post" action="{{ $isNew ? route('admin.pages.store') : route('admin.pages.update',$page->id) }}" data-ajax-form data-reload="true">
    @csrf @unless($isNew) @method('PUT') @endunless
    <div class="admin-panel"><h2>إعدادات الصفحة</h2><div class="input-group-admin">
        <div class="form-field"><label>اسم الصفحة</label><input class="form-control" name="title" value="{{ old('title',$page->title) }}" required maxlength="180"></div>
        <div class="form-field"><label>الرابط (slug)</label><input class="form-control" name="slug" value="{{ old('slug',$page->slug) }}" required maxlength="180" dir="ltr" placeholder="مثال: about-us"><small class="help-text">الرابط العام: /{{ $page->slug }}</small></div>
        <div class="form-field"><label>حالة النشر</label><select class="form-control" name="status"><option value="draft" @selected(old('status',$page->status)==='draft')>مسودة</option><option value="published" @selected(old('status',$page->status)==='published')>منشورة</option></select></div>
        <div class="form-field"><label>ترتيب الصفحة</label><input class="form-control" type="number" name="sort_order" min="0" value="{{ old('sort_order',$page->sort_order ?? 0) }}"></div>
        <div class="form-field"><label>عنوان محركات البحث</label><input class="form-control" name="meta_title" value="{{ old('meta_title',$page->meta_title) }}" maxlength="180"></div>
        <div class="form-field"><label>اسم الصفحة بالإنجليزية (اختياري)</label><input class="form-control" name="title_en" dir="ltr" value="{{ old('title_en',$english['title']??'') }}" maxlength="180"></div>
        <div class="form-field"><label>عنوان محركات البحث بالإنجليزية</label><input class="form-control" name="meta_title_en" dir="ltr" value="{{ old('meta_title_en',$english['meta_title']??'') }}" maxlength="180"></div>
        <div class="form-field"><label>صورة المشاركة</label><select class="form-control" name="share_image"><option value="">بدون صورة</option>@foreach($media as $asset)<option value="{{ $asset->path }}" @selected(old('share_image',$page->share_image)===$asset->path)>{{ $asset->alt ?: $asset->original_name }}</option>@endforeach</select></div>
        <div class="form-field full"><label>وصف محركات البحث</label><textarea class="form-control" name="meta_description" maxlength="320">{{ old('meta_description',$page->meta_description) }}</textarea></div>
        <div class="form-field full"><label>وصف محركات البحث بالإنجليزية</label><textarea class="form-control" name="meta_description_en" dir="ltr" maxlength="320">{{ old('meta_description_en',$english['meta_description']??'') }}</textarea></div>
    </div></div>
    <section class="admin-panel" data-sections-editor><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><h2 class="mb-1">محرر الأقسام</h2><p class="help-text mb-0">اسحب الأقسام لترتيبها أو استخدم الأسهم. الحقول المتاحة تختلف حسب بيانات القسم.</p></div><button class="btn btn-outline-dark btn-sm" type="button" data-add-section>إضافة قسم</button></div>
        <textarea name="sections" data-sections-json hidden>{{ $sectionValue }}</textarea>
        <div class="section-editor-list mt-3" data-sections-list></div>
    </section>
    <div class="d-flex gap-2 flex-wrap"><button class="btn btn-dark" type="submit">حفظ التعديلات</button>@if(!$isNew)<a class="btn btn-outline-dark" href="{{ route('admin.pages.preview',$page->id) }}">معاينة نسخة الصفحة</a>@endif</div><div class="form-feedback mt-3" data-form-feedback role="status"></div>
</form>
<script>window.siteMedia = @json($media->map(fn($m)=>['path'=>$m->path,'name'=>$m->original_name,'alt'=>$m->alt])->values());</script>
@if(!$isNew && $revisions->isNotEmpty())<section class="admin-panel mt-4"><h2>الإصدارات السابقة</h2><p class="help-text">يحفظ النظام محتوى الصفحة السابق تلقائيًا عند كل تعديل. استعادة إصدار تنشئ بدورها نسخة قابلة للاستعادة.</p><div class="table-wrap"><table class="admin-table"><thead><tr><th>التاريخ</th><th>المنفذ</th><th>الصفحة</th><th></th></tr></thead><tbody>@foreach($revisions as $revision)<tr><td>{{ $revision->created_at->format('Y-m-d H:i') }}</td><td>{{ $revision->created_by }}</td><td>{{ $revision->version_data['title'] ?? '' }}</td><td><form method="post" action="{{ route('admin.revisions.restore',$revision->id) }}" onsubmit="return confirm('استعادة هذا الإصدار؟ سيتم حفظ المحتوى الحالي كإصدار أيضًا.')">@csrf<button class="btn btn-outline-dark btn-sm">استعادة</button></form></td></tr>@endforeach</tbody></table></div></section>@endif
@endsection
