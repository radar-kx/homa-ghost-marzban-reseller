@extends('layouts.app')
@section('title','کیف پول')
@section('eyebrow','حساب نمایندگی')
@section('content')
<div class="wallet-hero"><div><small>موجودی قابل استفاده</small><strong>{{ number_format(auth()->user()->wallet_balance_irr) }} <em>ریال</em></strong><p>برای خرید و تمدید سرویس استفاده می‌شود.</p></div><a class="btn light" href="{{ route('wallet.create') }}">+ افزایش موجودی</a></div>
<div class="two-col"><section class="panel-card"><div class="section-head"><h3>درخواست‌های شارژ</h3></div>@forelse($deposits as $deposit)<div class="list-row"><span class="status-dot {{ $deposit->status }}"></span><div><b>{{ number_format($deposit->amount_irr) }} ریال</b><small>{{ $deposit->created_at->format('Y/m/d H:i') }}</small></div><span class="grow"></span><span class="badge {{ $deposit->status }}">{{ ['pending'=>'در انتظار','approved'=>'تأیید شده','rejected'=>'رد شده'][$deposit->status] }}</span></div>@empty<div class="empty">درخواستی ثبت نشده است.</div>@endforelse</section>
<section class="panel-card"><div class="section-head"><h3>گردش کیف پول</h3></div>@forelse($journals as $journal)<div class="list-row"><span class="money-sign {{ $journal->direction }}">{{ $journal->direction==='credit'?'+':'−' }}</span><div><b>{{ $journal->description }}</b><small>مانده: {{ number_format($journal->balance_after_irr) }} ریال</small></div><span class="grow"></span><strong class="money {{ $journal->direction }}">{{ number_format($journal->amount_irr) }}</strong></div>@empty<div class="empty">تراکنشی ثبت نشده است.</div>@endforelse</section></div>
@endsection
