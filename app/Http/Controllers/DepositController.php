<?php

namespace App\Http\Controllers;

use App\Models\DepositRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DepositController extends Controller
{
    public function index(Request $request): View
    {
        return view('wallet.index', [
            'deposits' => $request->user()->deposits()->latest()->paginate(10),
            'journals' => $request->user()->walletJournals()->latest('created_at')->limit(20)->get(),
        ]);
    }

    public function create(): View { return view('wallet.create', ['bank' => config('services.bank')]); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount_irr' => ['required', 'integer', 'min:100000', 'max:100000000000'],
            'tracking_code' => ['nullable', 'string', 'max:100'],
            'receipt' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'required_without:tracking_code'],
        ]);
        $path = $request->file('receipt')?->store('receipts/'.date('Y/m'));
        DepositRequest::query()->create([
            'public_id' => (string) Str::ulid(), 'reseller_id' => $request->user()->id, 'amount_irr' => $data['amount_irr'],
            'tracking_code' => $data['tracking_code'] ?? null, 'receipt_path' => $path, 'status' => 'pending',
        ]);
        return redirect()->route('wallet.index')->with('success', 'درخواست شارژ ثبت شد و پس از بررسی مدیر اعمال می‌شود.');
    }
}
