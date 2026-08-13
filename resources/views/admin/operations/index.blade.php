@extends('layouts.app')
@section('title','عملیات مرزبان')
@section('eyebrow','ساخت، تمدید و تطبیق')
@section('content')
<div class="toolbar"><p>نتیجه نامشخص به معنی قطع ارتباط هنگام پاسخ است؛ تا تعیین نتیجه، پول خودکار برنمی‌گردد.</p></div>
<section class="panel-card table-card"><div class="table-wrap"><table><thead><tr><th>عملیات</th><th>نماینده</th><th>سرویس</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th><th></th></tr></thead><tbody>
@forelse($operations as $operation)<tr><td><b>{{ $operation->type==='create'?'ساخت':'تمدید' }}</b><small><code>{{ $operation->public_id }}</code></small></td><td>{{ $operation->reseller->name }}</td><td><b dir="ltr">{{ $operation->service?->external_username ?: '—' }}</b></td><td>{{ number_format($operation->amount_irr) }} ریال</td><td><span class="badge {{ $operation->status==='succeeded'||$operation->status==='reconciled'?'active':($operation->status==='failed'?'failed':'pending') }}">{{ ['processing'=>'در حال پردازش','succeeded'=>'موفق','failed'=>'ناموفق','reconciled'=>'تطبیق‌شده','unknown'=>'نامشخص','pending'=>'در انتظار'][$operation->status] }}</span>@if($operation->error_message)<small>{{ $operation->error_message }}</small>@endif</td><td>{{ $operation->created_at->format('Y/m/d H:i') }}</td><td>@if($operation->status==='unknown')<form method="post" action="{{ route('admin.operations.reconcile',$operation) }}">@csrf<button class="btn small ghost">تطبیق اکنون</button></form>@endif</td></tr>
@empty<tr><td colspan="7"><div class="empty">عملیاتی ثبت نشده است.</div></td></tr>@endforelse
</tbody></table></div>{{ $operations->links() }}</section>
@endsection
