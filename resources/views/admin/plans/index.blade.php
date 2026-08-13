@extends('layouts.app')
@section('title','پلن‌ها')
@section('eyebrow','تعرفه نمایندگان')
@section('content')
<div class="toolbar"><p>قیمت هر خرید و تمدید مستقیماً از کیف پول نماینده کسر می‌شود.</p><a class="btn primary" href="{{ route('admin.plans.create') }}">+ پلن جدید</a></div><section class="panel-card table-card"><div class="table-wrap"><table><thead><tr><th>پلن</th><th>مرزبان</th><th>حجم</th><th>زمان</th><th>قیمت</th><th>وضعیت</th><th></th></tr></thead><tbody>@forelse($plans as $plan)<tr><td><b>{{ $plan->name }}</b></td><td>{{ $plan->panel->name }}</td><td>{{ $plan->data_limit_gb }} GB</td><td>{{ $plan->duration_days }} روز</td><td>{{ number_format($plan->price_irr) }} ریال</td><td><span class="badge {{ $plan->is_active?'active':'disabled' }}">{{ $plan->is_active?'فعال':'غیرفعال' }}</span></td><td><a href="{{ route('admin.plans.edit',$plan) }}">ویرایش</a></td></tr>@empty<tr><td colspan="7"><div class="empty">پلنی ساخته نشده است.</div></td></tr>@endforelse</tbody></table></div>{{ $plans->links() }}</section>
@endsection
