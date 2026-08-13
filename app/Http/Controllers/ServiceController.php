<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\ServiceAccount;
use App\Services\ProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ServiceController extends Controller
{
    public function __construct(private readonly ProvisioningService $provisioning) {}

    public function index(Request $request): View
    {
        $services = ServiceAccount::query()->where('reseller_id', $request->user()->id)->with('plan')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($inner) => $inner->where('external_username', 'like', '%'.$request->string('q').'%')->orWhere('customer_label', 'like', '%'.$request->string('q').'%')))
            ->latest()->paginate(15)->withQueryString();
        return view('services.index', compact('services'));
    }

    public function create(): View
    {
        return view('services.create', ['plans' => Plan::query()->where('is_active', true)->whereHas('panel', fn ($q) => $q->where('is_active', true))->orderBy('price_irr')->get(), 'requestId' => (string) Str::ulid()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'ulid'], 'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
            'username' => ['nullable', 'regex:/^[a-zA-Z0-9_]{3,20}$/'], 'customer_label' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            $service = $this->provisioning->create($request->user(), Plan::query()->with('panel')->findOrFail($data['plan_id']), $data['request_id'], $data['username'] ?? null, $data['customer_label'] ?? null);
            return redirect()->route('services.show', $service->public_id)->with('success', 'اکانت با موفقیت ساخته شد.');
        } catch (RuntimeException $exception) { return back()->withInput()->withErrors(['service' => $exception->getMessage()]); }
    }

    public function show(Request $request, string $publicId): View
    {
        $service = ServiceAccount::query()->where('public_id', $publicId)->where('reseller_id', $request->user()->id)->with(['plan', 'panel'])->firstOrFail();
        $plans = Plan::query()->where('panel_connection_id', $service->panel_connection_id)->where('is_active', true)->orderBy('price_irr')->get();
        return view('services.show', ['service' => $service, 'plans' => $plans, 'requestId' => (string) Str::ulid()]);
    }

    public function renew(Request $request, string $publicId): RedirectResponse
    {
        $service = ServiceAccount::query()->where('public_id', $publicId)->where('reseller_id', $request->user()->id)->with('panel')->firstOrFail();
        $data = $request->validate(['request_id' => ['required', 'ulid'], 'plan_id' => ['required', 'integer', 'exists:plans,id']]);
        try {
            $this->provisioning->renew($request->user(), $service, Plan::query()->with('panel')->findOrFail($data['plan_id']), $data['request_id']);
            return back()->with('success', 'تمدید سرویس انجام شد.');
        } catch (RuntimeException $exception) { return back()->withErrors(['renew' => $exception->getMessage()]); }
    }
}
