@extends('layouts.app')
@section('title','داشبورد')
@section('eyebrow','نمای کلی کسب‌وکار')
@section('content')
<div class="hero-card"><div><span class="eyebrow">سلام {{ auth()->user()->name }} 👋</span><h2>همه‌چیز برای فروش آماده است.</h2><p>سرویس تازه بسازید، اشتراک‌ها را تمدید کنید و گردش کیف پول را زیر نظر داشته باشید.</p></div><a class="btn light" href="{{ route('services.create') }}">+ ساخت اکانت جدید</a></div>
<div class="stats-grid">
    <article class="stat"><span class="stat-icon mint">◈</span><div><small>سرویس فعال</small><strong>{{ number_format($activeServices) }}</strong><em>از {{ number_format($allServices) }} سرویس</em></div></article>
    <article class="stat"><span class="stat-icon blue">◉</span><div><small>موجودی کیف پول</small><strong>{{ number_format(auth()->user()->wallet_balance_irr) }}</strong><em>ریال</em></div></article>
    <article class="stat"><span class="stat-icon amber">◎</span><div><small>شارژ در انتظار</small><strong>{{ number_format($pendingDeposits) }}</strong><em>درخواست</em></div></article>
</div>
<div class="two-col">
<section class="panel-card"><div class="section-head"><div><span class="eyebrow">آخرین فعالیت</span><h3>سرویس‌های اخیر</h3></div><a href="{{ route('services.index') }}">مشاهده همه</a></div>
@forelse($recentServices as $service)<a class="list-row" href="{{ route('services.show',$service->public_id) }}"><span class="status-dot {{ $service->status }}"></span><div><b dir="ltr">{{ $service->external_username }}</b><small>{{ $service->plan->name }}</small></div><span class="grow"></span><small>{{ $service->expire_at?->format('Y/m/d') ?? '—' }}</small><span>‹</span></a>@empty<div class="empty">هنوز سرویسی نساخته‌اید.</div>@endforelse
</section>
<section class="panel-card"><div class="section-head"><div><span class="eyebrow">گردش حساب</span><h3>تراکنش‌های اخیر</h3></div><a href="{{ route('wallet.index') }}">جزئیات</a></div>
@forelse($recentJournals as $journal)<div class="list-row"><span class="money-sign {{ $journal->direction }}">{{ $journal->direction==='credit' ? '+' : '−' }}</span><div><b>{{ $journal->description }}</b><small>{{ $journal->created_at?->diffForHumans() }}</small></div><span class="grow"></span><strong class="money {{ $journal->direction }}">{{ number_format($journal->amount_irr) }}</strong></div>@empty<div class="empty">هنوز تراکنشی ثبت نشده است.</div>@endforelse
</section></div>
@endsection
