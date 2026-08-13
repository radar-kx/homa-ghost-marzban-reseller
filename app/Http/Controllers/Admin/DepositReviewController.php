<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepositRequest;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepositReviewController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}
    public function index(): View { return view('admin.deposits.index', ['deposits' => DepositRequest::query()->with(['reseller', 'reviewer'])->latest()->paginate(20)]); }
    public function receipt(DepositRequest $deposit): StreamedResponse { abort_unless($deposit->receipt_path && Storage::exists($deposit->receipt_path), 404); return Storage::download($deposit->receipt_path, 'receipt-'.$deposit->public_id.'.'.pathinfo($deposit->receipt_path, PATHINFO_EXTENSION)); }

    public function approve(Request $request, DepositRequest $deposit): RedirectResponse
    {
        DB::transaction(function () use ($request, $deposit) {
            $locked = DepositRequest::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') return;
            $locked->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->string('review_note')->limit(500)->toString() ?: null]);
            $this->wallet->credit($locked->reseller_id, $locked->amount_irr, 'deposit:'.$locked->public_id, 'deposit_approval', $locked->public_id, 'تأیید درخواست شارژ');
        }, 3);
        return back()->with('success', 'درخواست تأیید و کیف پول شارژ شد.');
    }

    public function reject(Request $request, DepositRequest $deposit): RedirectResponse
    {
        $data = $request->validate(['review_note' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $deposit, $data) {
            $locked = DepositRequest::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') return;
            $locked->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note']]);
        });
        return back()->with('success', 'درخواست رد شد.');
    }
}
