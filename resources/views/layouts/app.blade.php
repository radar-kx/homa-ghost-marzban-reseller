<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="color-scheme" content="light dark">
    <title>@yield('title', 'پنل') | هما گوست</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body>
@auth
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">H</span><span><b>هما گوست</b><small>{{ auth()->user()->isAdmin() ? 'مدیریت نمایندگان' : 'پنل نمایندگی' }}</small></span></a>
        <nav class="nav">
            <a class="{{ request()->routeIs('dashboard','admin.dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>⌂</span> داشبورد</a>
            @if(auth()->user()->isReseller())
                <a class="{{ request()->routeIs('services.*') ? 'active' : '' }}" href="{{ route('services.index') }}"><span>◈</span> سرویس‌ها</a>
                <a class="{{ request()->routeIs('wallet.*') ? 'active' : '' }}" href="{{ route('wallet.index') }}"><span>◉</span> کیف پول</a>
            @else
                <a class="{{ request()->routeIs('admin.resellers.*') ? 'active' : '' }}" href="{{ route('admin.resellers.index') }}"><span>♙</span> نمایندگان</a>
                <a class="{{ request()->routeIs('admin.panels.*') ? 'active' : '' }}" href="{{ route('admin.panels.index') }}"><span>◇</span> اتصال مرزبان</a>
                <a class="{{ request()->routeIs('admin.plans.*') ? 'active' : '' }}" href="{{ route('admin.plans.index') }}"><span>▦</span> پلن‌ها</a>
                <a class="{{ request()->routeIs('admin.deposits.*') ? 'active' : '' }}" href="{{ route('admin.deposits.index') }}"><span>◎</span> درخواست‌های شارژ</a>
                <a class="{{ request()->routeIs('admin.operations.*') ? 'active' : '' }}" href="{{ route('admin.operations.index') }}"><span>↻</span> عملیات مرزبان</a>
            @endif
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip"><span>{{ mb_substr(auth()->user()->name,0,1) }}</span><div><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->email }}</small></div></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="nav-logout" type="submit">خروج امن</button></form>
        </div>
    </aside>
    <div class="page-wrap">
        <header class="topbar">
            <button class="icon-btn mobile-menu" type="button" data-sidebar aria-label="نمایش منو">☰</button>
            <div class="page-heading"><small>@yield('eyebrow','هما گوست')</small><h1>@yield('title','داشبورد')</h1></div>
            <div class="top-actions">
                @if(auth()->user()->isReseller())<div class="wallet-pill"><span>موجودی</span><b>{{ number_format(auth()->user()->fresh()->wallet_balance_irr) }} ریال</b></div>@endif
                <button class="icon-btn" type="button" data-theme-toggle aria-label="تغییر پوسته">◐</button>
            </div>
        </header>
        <main class="content">
            @if(session('success'))<div class="alert success">✓ {{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert danger"><b>لطفاً موارد زیر را بررسی کنید:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
@else
    @yield('guest')
@endauth
</body></html>
