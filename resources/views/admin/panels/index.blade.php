@extends('layouts.app')
@section('title','اتصال مرزبان')
@section('eyebrow','Panel Adapter')
@section('content')
<div class="toolbar"><p>اطلاعات ورود رمزگذاری و فقط سمت سرور استفاده می‌شود.</p><a class="btn primary" href="{{ route('admin.panels.create') }}">+ اتصال جدید</a></div><div class="cards-grid">@forelse($panels as $panel)<article class="panel-card connection-card"><div><span class="status-dot {{ $panel->is_active?'active':'disabled' }}"></span><h3>{{ $panel->name }}</h3><code dir="ltr">{{ $panel->base_url }}</code></div><dl><div><dt>پلن</dt><dd>{{ $panel->plans_count }}</dd></div><div><dt>SSL</dt><dd>{{ $panel->verify_tls?'بررسی می‌شود':'خاموش' }}</dd></div></dl><div class="card-actions"><form method="post" action="{{ route('admin.panels.test',$panel) }}">@csrf<button class="btn ghost">تست اتصال</button></form><a class="btn ghost" href="{{ route('admin.panels.edit',$panel) }}">ویرایش</a></div></article>@empty<div class="empty panel-card">هنوز اتصال مرزبانی تعریف نشده است.</div>@endforelse</div>
@endsection
