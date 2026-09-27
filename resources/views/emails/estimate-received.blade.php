<div dir="rtl" lang="ar">
    <h1>طلب مقايسة جديد</h1>
    <p><strong>الاسم:</strong> {{ $estimate->name }}</p>
    <p><strong>رقم الواتساب:</strong> {{ $estimate->whatsapp }}</p>
    <p><strong>نوع المشروع:</strong> {{ $estimate->project_type }}</p>
    <p><strong>المساحة:</strong> {{ $estimate->area ?: 'غير محددة' }}</p>
    <p><strong>الرسالة:</strong></p>
    <p>{{ $estimate->message }}</p>
</div>
