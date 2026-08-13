@extends('layouts.app')
@section('title','ورود')
@section('guest')
<div class="auth-page">
    <section class="auth-visual"><div class="auth-logo">H</div><div><span class="eyebrow">HOMA GHOST</span><h1>مدیریت فروش،<br>ساده و مطمئن.</h1><p>پنل اختصاصی نمایندگان برای ساخت و تمدید سرویس‌های مرزبان.</p></div><div class="auth-orbit"><i></i><i></i><i></i></div></section>
    <section class="auth-form-wrap"><button class="icon-btn auth-theme" type="button" data-theme-toggle>◐</button><form class="auth-card" method="post" action="{{ route('login.store') }}">@csrf
        <div class="brand mobile-brand"><span class="brand-mark">H</span><span><b>هما گوست</b><small>پنل نمایندگی</small></span></div>
        <span class="eyebrow">خوش آمدید</span><h2>ورود به حساب</h2><p>اطلاعات حساب نمایندگی خود را وارد کنید.</p>
        @if($errors->any())<div class="alert danger">{{ $errors->first() }}</div>@endif
        <label>ایمیل<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="name@example.com"></label>
        <label>رمز عبور<input type="password" name="password" required autocomplete="current-password" placeholder="••••••••••••"></label>
        <label class="check"><input type="checkbox" name="remember" value="1"><span>مرا به خاطر بسپار</span></label>
        <button class="btn primary wide" type="submit">ورود به پنل <span>←</span></button>
        <small class="secure-note">اتصال امن و نشست رمزگذاری‌شده</small>
    </form></section>
</div>
@endsection
