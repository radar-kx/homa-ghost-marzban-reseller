<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResellerController extends Controller
{
    public function index(): View { return view('admin.resellers.index', ['resellers' => User::query()->where('role', 'reseller')->withCount('services')->latest()->paginate(15)]); }
    public function create(): View { return view('admin.resellers.form', ['reseller' => new User()]); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'max:255'], 'reseller_prefix' => ['required', 'regex:/^[a-z][a-z0-9]{2,9}$/', 'unique:users,reseller_prefix'],
        ]);
        User::query()->create($data + ['role' => 'reseller', 'is_active' => true, 'wallet_balance_irr' => 0]);
        return redirect()->route('admin.resellers.index')->with('success', 'نماینده ساخته شد.');
    }

    public function edit(User $reseller): View { abort_unless($reseller->isReseller(), 404); return view('admin.resellers.form', compact('reseller')); }
    public function update(Request $request, User $reseller): RedirectResponse
    {
        abort_unless($reseller->isReseller(), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($reseller->id)],
            'password' => ['nullable', 'string', 'min:10', 'max:255'], 'reseller_prefix' => ['required', 'regex:/^[a-z][a-z0-9]{2,9}$/', Rule::unique('users')->ignore($reseller->id)],
        ]);
        if (empty($data['password'])) unset($data['password']);
        $reseller->update($data);
        return redirect()->route('admin.resellers.index')->with('success', 'اطلاعات نماینده ویرایش شد.');
    }

    public function toggle(User $reseller): RedirectResponse
    {
        abort_unless($reseller->isReseller(), 404); $reseller->update(['is_active' => ! $reseller->is_active]);
        return back()->with('success', 'وضعیت نماینده تغییر کرد.');
    }
}
