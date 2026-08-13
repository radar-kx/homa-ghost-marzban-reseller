@extends('layouts.app')
@section('title','سرویس‌ها')
@section('eyebrow','مدیریت اکانت‌ها')
@section('content')
<div class="toolbar"><form class="search" method="get"><input name="q" value="{{ request('q') }}" placeholder="جستجوی نام کاربری یا مشتری…"><button>جستجو</button></form><a class="btn primary" href="{{ route('services.create') }}">+ اکانت جدید</a></div>
<section class="panel-card table-card"><div class="table-wrap"><table><thead><tr><th>اکانت</th><th>پلن</th><th>وضعیت</th><th>انقضا</th><th>حجم</th><th></th></tr></thead><tbody>
@forelse($services as $service)<tr><td><b dir="ltr">{{ $service->external_username }}</b><small>{{ $service->customer_label ?: 'بدون نام مشتری' }}</small></td><td>{{ $service->plan->name }}</td><td><span class="badge {{ $service->status }}">{{ ['active'=>'فعال','failed'=>'ناموفق','provisioning'=>'در حال ساخت','expired'=>'منقضی','disabled'=>'غیرفعال'][$service->status] ?? $service->status }}</span></td><td>{{ $service->expire_at?->format('Y/m/d') ?? '—' }}</td><td>{{ number_format($service->data_limit_bytes/1073741824,1) }} GB</td><td><a class="row-link" href="{{ route('services.show',$service->public_id) }}">مدیریت ←</a></td></tr>
@empty<tr><td colspan="6"><div class="empty">هیچ سرویسی پیدا نشد.</div></td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $services->links() }}</div></section>
@endsection
