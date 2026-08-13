@extends('layouts.app')
@section('title','افزایش موجودی')
@section('eyebrow','ثبت واریز')
@section('content')
<div class="two-col charge-layout"><section class="bank-card"><span>شماره کارت جهت واریز</span><strong dir="ltr">{{ $bank['card_number'] }}</strong><div><b>{{ $bank['owner'] }}</b><small>{{ $bank['name'] }}</small></div><i>HOMA GHOST</i></section>
<form class="panel-card form-card" method="post" action="{{ route('wallet.store') }}" enctype="multipart/form-data">@csrf<div class="section-head"><h3>اطلاعات واریز</h3></div>
<label>مبلغ به ریال<input type="number" name="amount_irr" min="100000" max="100000000000" value="{{ old('amount_irr') }}" required placeholder="مثلاً 5000000"></label>
<label>کد رهگیری <small>در صورت نداشتن کد، تصویر رسید الزامی است</small><input name="tracking_code" maxlength="100" value="{{ old('tracking_code') }}" placeholder="کد پیگیری بانکی"></label>
<label>تصویر رسید<input type="file" name="receipt" accept="image/jpeg,image/png,image/webp"><small>JPG، PNG یا WebP تا ۴ مگابایت؛ فایل فقط برای مدیر قابل مشاهده است.</small></label>
<button class="btn primary wide" type="submit">ثبت درخواست شارژ</button></form></div>
@endsection
