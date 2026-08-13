<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PanelConnection;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JsonException;

class PlanController extends Controller
{
    public function index(): View { return view('admin.plans.index', ['plans' => Plan::query()->with('panel')->latest()->paginate(20)]); }
    public function create(): View { return view('admin.plans.form', ['plan' => new Plan(), 'panels' => PanelConnection::query()->where('is_active', true)->get()]); }
    public function store(Request $request): RedirectResponse { $data = $this->validated($request); if ($data instanceof RedirectResponse) return $data; Plan::query()->create($data); return redirect()->route('admin.plans.index')->with('success', 'پلن ساخته شد.'); }
    public function edit(Plan $plan): View { return view('admin.plans.form', ['plan' => $plan, 'panels' => PanelConnection::query()->get()]); }
    public function update(Request $request, Plan $plan): RedirectResponse { $data = $this->validated($request); if ($data instanceof RedirectResponse) return $data; $plan->update($data); return redirect()->route('admin.plans.index')->with('success', 'پلن ویرایش شد.'); }

    private function validated(Request $request): array|RedirectResponse
    {
        $data = $request->validate([
            'panel_connection_id' => ['required', 'exists:panel_connections,id'], 'name' => ['required', 'string', 'max:100'],
            'data_limit_gb' => ['required', 'numeric', 'min:0.1', 'max:100000'], 'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'price_irr' => ['required', 'integer', 'min:1'], 'proxies_json' => ['required', 'json'], 'inbounds_json' => ['required', 'json'], 'is_active' => ['required', 'boolean'],
        ]);
        try { $data['proxies'] = json_decode($data['proxies_json'], true, 32, JSON_THROW_ON_ERROR); $data['inbounds'] = json_decode($data['inbounds_json'], true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException) { return back()->withInput()->withErrors(['proxies_json' => 'ساختار JSON معتبر نیست.']); }
        if (! is_array($data['proxies']) || ! is_array($data['inbounds']) || $data['proxies'] === [] || $data['inbounds'] === []) return back()->withInput()->withErrors(['proxies_json' => 'پروتکل و اینباند نباید خالی باشند.']);
        unset($data['proxies_json'], $data['inbounds_json']);
        return $data;
    }
}
