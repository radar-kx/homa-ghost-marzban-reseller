@extends('layouts.app')
@section('title','ساخت اکانت')
@section('eyebrow','سرویس تازه')
@section('content')
<form method="post" action="{{ route('services.store') }}" class="form-layout">@csrf<input type="hidden" name="request_id" value="{{ $requestId }}">
<section class="panel-card form-card"><div class="section-head"><div><span class="step">۱</span><h3>انتخاب پلن</h3></div></div>
<div class="plan-grid">@forelse($plans as $plan)<label class="plan-option"><input type="radio" name="plan_id" value="{{ $plan->id }}" {{ old('plan_id')==$plan->id ? 'checked' : '' }} required><span><i></i><b>{{ $plan->name }}</b><small>{{ rtrim(rtrim(number_format($plan->data_limit_gb,2),'0'),'.') }} گیگ • {{ $plan->duration_days }} روز</small><strong>{{ number_format($plan->price_irr) }} <em>ریال</em></strong></span></label>@empty<div class="empty">پلن فعالی تعریف نشده؛ با مدیر تماس بگیرید.</div>@endforelse</div></section>
<section class="panel-card form-card"><div class="section-head"><div><span class="step">۲</span><h3>مشخصات اکانت</h3></div></div><div class="form-grid">
<label>نام مشتری <small>اختیاری، فقط برای یادآوری خودتان</small><input name="customer_label" value="{{ old('customer_label') }}" maxlength="100" placeholder="مثلاً علی رضایی"></label>
<label>نام کاربری دلخواه <small>اختیاری، حروف انگلیسی و عدد</small><input dir="ltr" name="username" value="{{ old('username') }}" maxlength="20" placeholder="خالی بگذارید تا خودکار ساخته شود"></label>
</div></section>
<div class="form-submit"><div><b>مبلغ از کیف پول کسر می‌شود</b><small>اگر ساخت در مرزبان قطعاً ناموفق باشد، مبلغ خودکار برمی‌گردد.</small></div><button class="btn primary" type="submit" {{ $plans->isEmpty() ? 'disabled' : '' }}>تأیید و ساخت اکانت</button></div>
</form>
@endsection
