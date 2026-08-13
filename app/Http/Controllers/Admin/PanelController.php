<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelConnection;
use App\Services\MarzbanClient;
use App\Services\PanelUrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class PanelController extends Controller
{
    public function __construct(private readonly PanelUrlGuard $guard, private readonly MarzbanClient $marzban) {}
    public function index(): View { return view('admin.panels.index', ['panels' => PanelConnection::query()->withCount('plans')->latest()->get()]); }
    public function create(): View { return view('admin.panels.form', ['panel' => new PanelConnection()]); }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        try { $data['base_url'] = $this->guard->normalize($data['base_url']); } catch (InvalidArgumentException $e) { return back()->withInput()->withErrors(['base_url' => $e->getMessage()]); }
        PanelConnection::query()->create($data);
        return redirect()->route('admin.panels.index')->with('success', 'اتصال مرزبان ذخیره شد؛ اکنون تست اتصال را اجرا کنید.');
    }

    public function edit(PanelConnection $panel): View { return view('admin.panels.form', compact('panel')); }
    public function update(Request $request, PanelConnection $panel): RedirectResponse
    {
        $data = $this->validated($request, true);
        try { $data['base_url'] = $this->guard->normalize($data['base_url']); } catch (InvalidArgumentException $e) { return back()->withInput()->withErrors(['base_url' => $e->getMessage()]); }
        if (empty($data['admin_password'])) unset($data['admin_password']);
        $panel->update($data);
        return redirect()->route('admin.panels.index')->with('success', 'اتصال مرزبان ویرایش شد.');
    }

    public function test(PanelConnection $panel): RedirectResponse
    {
        try { $this->marzban->test($panel); return back()->with('success', 'اتصال و ورود به مرزبان موفق بود.'); }
        catch (Throwable) { return back()->withErrors(['panel' => 'اتصال ناموفق بود؛ آدرس، SSL و اطلاعات مدیر مرزبان را بررسی کنید.']); }
    }

    private function validated(Request $request, bool $editing = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'], 'base_url' => ['required', 'url', 'max:255'],
            'admin_username' => ['required', 'string', 'max:190'], 'admin_password' => [$editing ? 'nullable' : 'required', 'string', 'max:500'],
            'verify_tls' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
        ]);
    }
}
