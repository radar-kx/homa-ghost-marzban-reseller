@extends('layouts.app')
@section('title','جزئیات سرویس')
@section('eyebrow',$service->customer_label ?: 'اکانت مرزبان')
@section('content')
<div class="service-hero"><div><span class="badge {{ $service->status }}">{{ $service->status==='active'?'فعال':$service->status }}</span><h2 dir="ltr">{{ $service->external_username }}</h2><p>{{ $service->plan->name }} • {{ number_format($service->data_limit_bytes/1073741824,1) }} گیگ</p></div><div class="expiry"><small>تاریخ انقضا</small><strong>{{ $service->expire_at?->format('Y/m/d') ?? '—' }}</strong><span>{{ $service->expire_at?->diffForHumans() }}</span></div></div>
@if($service->last_error)<div class="alert warning">{{ $service->last_error }}</div>@endif
<div class="two-col service-cols"><section class="panel-card"><div class="section-head"><div><span class="eyebrow">دسترسی مشتری</span><h3>لینک اشتراک</h3></div></div>
@if($service->subscription_url)<div class="copy-box"><input id="sub-url" dir="ltr" readonly value="{{ $service->subscription_url }}"><button type="button" data-copy="#sub-url">کپی</button></div><p class="hint">این لینک محرمانه است؛ فقط برای مشتری ارسال کنید.</p>@else<div class="empty">لینک اشتراک از پاسخ مرزبان دریافت نشده است.</div>@endif
</section>
<section class="panel-card"><div class="section-head"><div><span class="eyebrow">تمدید</span><h3>انتخاب پلن تمدید</h3></div></div><form method="post" action="{{ route('services.renew',$service->public_id) }}">@csrf<input type="hidden" name="request_id" value="{{ $requestId }}"><label>پلن<select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} — {{ number_format($plan->price_irr) }} ریال</option>@endforeach</select></label><button class="btn primary wide" type="submit" {{ $plans->isEmpty()?'disabled':'' }}>تمدید و ریست مصرف</button></form></section></div>
@endsection
