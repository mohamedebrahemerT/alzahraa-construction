<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>دخول الإدارة | الزهراء</title><link rel="stylesheet" href="{{ asset('assets/site/site.css') }}"><script src="{{ asset('assets/site/site.js') }}" defer></script></head><body class="login-page"><main class="login-card">
    <a class="brand" href="{{ route('home') }}"><img class="brand-logo" src="{{ asset('images/brand/logo-mark.webp') }}" alt=""><span class="brand-copy"><strong>{{ $settings['company_name'] ?? 'الزهراء للمقاولات' }}</strong><small>إدارة الموقع</small></span></a>
    <h1>تسجيل الدخول إلى لوحة الإدارة</h1><p class="text-secondary">أدخل بيانات حسابك للمتابعة.</p>
    @if($errors->any())<div class="form-errors mb-3" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('admin.login') }}">@csrf
        <div class="form-field mb-3"><label for="email">البريد الإلكتروني</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></div>
        <div class="form-field mb-3"><label for="password">كلمة المرور</label><input class="form-control" id="password" type="password" name="password" required autocomplete="current-password"></div>
        <label class="d-flex gap-2 align-items-center mb-3"><input type="checkbox" name="remember" value="1"> تذكرني على هذا الجهاز</label>
        <button class="btn btn-dark w-100" type="submit">دخول آمن</button>
    </form>
</main></body></html>
