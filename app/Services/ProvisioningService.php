<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\ProvisionOperation;
use App\Models\ServiceAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Client\RequestException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class ProvisioningService
{
    public function __construct(private readonly WalletService $wallet, private readonly MarzbanClient $marzban) {}

    public function create(User $reseller, Plan $plan, string $requestId, ?string $requestedUsername, ?string $label): ServiceAccount
    {
        try {
            return Cache::lock('homa-create-'.$requestId, 90)->block(10, fn () => $this->createLocked($reseller, $plan, $requestId, $requestedUsername, $label));
        } catch (LockTimeoutException) { throw new RuntimeException('این درخواست در حال پردازش است؛ چند لحظه دیگر صفحه را تازه کنید.'); }
    }

    private function createLocked(User $reseller, Plan $plan, string $requestId, ?string $requestedUsername, ?string $label): ServiceAccount
    {
        if (! $plan->is_active || ! $plan->panel->is_active) throw new RuntimeException('این پلن در حال حاضر فعال نیست.');
        if ($existing = ProvisionOperation::query()->where('public_id', $requestId)->where('reseller_id', $reseller->id)->first()) {
            return $existing->service()->firstOrFail();
        }

        $username = $this->username($reseller, $requestedUsername);
        try {
            if ($this->marzban->getUser($plan->panel, $username) !== null) throw new RuntimeException('این نام کاربری از قبل در مرزبان وجود دارد.');
        } catch (RuntimeException $exception) { throw $exception; }
        catch (Throwable) { throw new RuntimeException('پیش از کسر موجودی، امکان کنترل نام کاربری در مرزبان وجود نداشت.'); }
        $expire = now('UTC')->addDays($plan->duration_days)->timestamp;
        [$operation, $service] = DB::transaction(function () use ($reseller, $plan, $requestId, $username, $label, $expire) {
            $operation = ProvisionOperation::query()->create([
                'public_id' => $requestId, 'reseller_id' => $reseller->id, 'type' => 'create', 'status' => 'processing',
                'amount_irr' => $plan->price_irr, 'request_snapshot' => ['plan_id' => $plan->id, 'username' => $username, 'target_expire' => $expire, 'target_data_limit' => $plan->dataLimitBytes()],
            ]);
            $this->wallet->debit($reseller->id, $plan->price_irr, 'purchase:'.$requestId, 'service_create', $requestId, 'خرید سرویس '.$plan->name);
            $service = ServiceAccount::query()->create([
                'public_id' => (string) Str::ulid(), 'reseller_id' => $reseller->id, 'plan_id' => $plan->id,
                'panel_connection_id' => $plan->panel_connection_id, 'external_username' => $username,
                'customer_label' => $label, 'status' => 'provisioning', 'data_limit_bytes' => $plan->dataLimitBytes(),
            ]);
            $operation->update(['service_account_id' => $service->id]);
            return [$operation, $service];
        }, 3);

        $payload = [
            'username' => $username, 'status' => 'active', 'expire' => $expire, 'data_limit' => $plan->dataLimitBytes(),
            'data_limit_reset_strategy' => 'no_reset', 'proxies' => $plan->proxies, 'inbounds' => $plan->inbounds,
            'note' => 'Homa Ghost reseller #'.$reseller->id,
        ];

        try {
            $remote = $this->marzban->createUser($plan->panel, $payload);
        } catch (Throwable $exception) {
            if ($this->isDefinitiveFailure($exception)) {
                $this->failAndRefund($operation, $service, $exception);
                throw new RuntimeException('ساخت سرویس در مرزبان ناموفق بود و مبلغ به کیف پول برگشت داده شد.');
            }
            $reconciliation = $this->reconcileCreatedUser($plan, $username);
            $remote = $reconciliation['remote'];
            if ($reconciliation['known'] && $remote === null) {
                $this->failAndRefund($operation, $service, $exception);
                throw new RuntimeException('ساخت سرویس در مرزبان انجام نشد و مبلغ به کیف پول برگشت داده شد.');
            }
            if (! $reconciliation['known']) {
                $this->markUnknown($operation, $service, $exception);
                throw new RuntimeException('پاسخ مرزبان نامشخص است؛ برای جلوگیری از دو بار پرداخت، عملیات جهت بررسی نگه داشته شد.');
            }
            $operation->status = 'reconciled';
        }

        $this->complete($operation, $service, $remote, $expire, $plan->dataLimitBytes());
        return $service->fresh(['plan']);
    }

    public function renew(User $reseller, ServiceAccount $service, Plan $plan, string $requestId): ServiceAccount
    {
        try {
            return Cache::lock('homa-renew-service-'.$service->id, 90)->block(10, fn () => $this->renewLocked($reseller, $service, $plan, $requestId));
        } catch (LockTimeoutException) { throw new RuntimeException('تمدید دیگری برای این سرویس در حال پردازش است.'); }
    }

    private function renewLocked(User $reseller, ServiceAccount $service, Plan $plan, string $requestId): ServiceAccount
    {
        if ($service->reseller_id !== $reseller->id) abort(404);
        if (! $plan->is_active || $plan->panel_connection_id !== $service->panel_connection_id) throw new RuntimeException('پلن تمدید با پنل این سرویس سازگار نیست.');
        if ($existing = ProvisionOperation::query()->where('public_id', $requestId)->where('reseller_id', $reseller->id)->first()) return $existing->service()->firstOrFail();
        $service->refresh();
        $base = $service->expire_at && $service->expire_at->isFuture() ? $service->expire_at->copy() : now('UTC');
        $expire = $base->addDays($plan->duration_days)->timestamp;

        $operation = DB::transaction(function () use ($reseller, $service, $plan, $requestId, $expire) {
            $operation = ProvisionOperation::query()->create([
                'public_id' => $requestId, 'reseller_id' => $reseller->id, 'service_account_id' => $service->id,
                'type' => 'renew', 'status' => 'processing', 'amount_irr' => $plan->price_irr,
                'request_snapshot' => ['plan_id' => $plan->id, 'service_id' => $service->public_id, 'target_expire' => $expire, 'target_data_limit' => $plan->dataLimitBytes()],
            ]);
            $this->wallet->debit($reseller->id, $plan->price_irr, 'renew:'.$requestId, 'service_renew', $requestId, 'تمدید سرویس '.$service->external_username);
            return $operation;
        }, 3);

        try {
            $remote = $this->marzban->modifyUser($service->panel, $service->external_username, [
                'status' => 'active', 'expire' => $expire, 'data_limit' => $plan->dataLimitBytes(), 'data_limit_reset_strategy' => 'no_reset',
            ]);
        } catch (Throwable $exception) {
            if ($this->isDefinitiveFailure($exception)) {
                $this->failAndRefund($operation, $service, $exception, false);
                throw new RuntimeException('تمدید در مرزبان ناموفق بود و مبلغ به کیف پول برگشت داده شد.');
            }
            $reconciliation = $this->reconcileRenewal($service, $expire, $plan->dataLimitBytes());
            $remote = $reconciliation['remote'];
            if ($reconciliation['known'] && $remote === null) {
                $this->failAndRefund($operation, $service, $exception, false);
                throw new RuntimeException('تمدید در مرزبان انجام نشد و مبلغ به کیف پول برگشت داده شد.');
            }
            if (! $reconciliation['known']) {
                $this->markUnknown($operation, $service, $exception, false);
                throw new RuntimeException('نتیجه تمدید نامشخص است؛ مبلغ تا زمان تطبیق خودکار برگشت داده نمی‌شود.');
            }
            $operation->status = 'reconciled';
        }

        $resetWarning = null;
        try { $remote = $this->marzban->resetUsage($service->panel, $service->external_username); }
        catch (Throwable) { $resetWarning = 'زمان و حجم اعمال شد اما ریست مصرف نیازمند بررسی مدیر است.'; $operation->status = 'reconciled'; }

        $service->plan_id = $plan->id;
        $this->complete($operation, $service, $remote, $expire, $plan->dataLimitBytes(), $resetWarning);
        return $service->fresh(['plan']);
    }

    private function username(User $reseller, ?string $requested): string
    {
        $suffix = $requested ? strtolower(trim($requested)) : strtolower(Str::random(10));
        if (! preg_match('/^[a-z0-9_]{3,20}$/', $suffix)) throw new RuntimeException('نام کاربری باید ۳ تا ۲۰ حرف انگلیسی، عدد یا زیرخط باشد.');
        $username = strtolower((string) $reseller->reseller_prefix).'_'.$suffix;
        if (strlen($username) > 32 || ServiceAccount::query()->where('panel_connection_id', '>', 0)->where('external_username', $username)->exists()) {
            throw new RuntimeException('این نام کاربری قبلاً استفاده شده یا بیش از حد طولانی است.');
        }
        return $username;
    }

    private function reconcileCreatedUser(Plan $plan, string $username): array
    {
        try { return ['known' => true, 'remote' => $this->marzban->getUser($plan->panel, $username)]; }
        catch (Throwable) { return ['known' => false, 'remote' => null]; }
    }

    private function reconcileRenewal(ServiceAccount $service, int $expire, int $bytes): array
    {
        try {
            $remote = $this->marzban->getUser($service->panel, $service->external_username);
            $matches = $remote && (int) ($remote['expire'] ?? 0) === $expire && (int) ($remote['data_limit'] ?? 0) === $bytes;
            return ['known' => true, 'remote' => $matches ? $remote : null];
        } catch (Throwable) { return ['known' => false, 'remote' => null]; }
    }

    private function isDefinitiveFailure(Throwable $exception): bool
    {
        if (! $exception instanceof RequestException || ! $exception->response) return false;
        $status = $exception->response->status();
        return $status >= 400 && $status < 500 && ! in_array($status, [408, 429], true);
    }

    private function complete(ProvisionOperation $operation, ServiceAccount $service, array $remote, int $expire, int $bytes, ?string $warning = null): void
    {
        DB::transaction(function () use ($operation, $service, $remote, $expire, $bytes, $warning) {
            $service->fill([
                'status' => 'active', 'expire_at' => Carbon::createFromTimestampUTC($expire), 'data_limit_bytes' => $bytes,
                'subscription_url' => $remote['subscription_url'] ?? $service->subscription_url,
                'remote_snapshot' => $remote, 'last_error' => $warning,
            ])->save();
            $operation->fill([
                'status' => $operation->status === 'reconciled' ? 'reconciled' : 'succeeded',
                'response_snapshot' => $remote, 'error_message' => $warning, 'completed_at' => now(),
            ])->save();
        });
    }

    private function failAndRefund(ProvisionOperation $operation, ServiceAccount $service, Throwable $exception, bool $markServiceFailed = true): void
    {
        DB::transaction(function () use ($operation, $service, $exception, $markServiceFailed) {
            $message = Str::limit($exception->getMessage(), 500);
            if ($markServiceFailed) $service->update(['status' => 'failed', 'last_error' => $message]);
            $operation->update(['status' => 'failed', 'error_message' => $message, 'completed_at' => now()]);
            $this->wallet->credit($operation->reseller_id, $operation->amount_irr, 'refund:'.$operation->public_id, 'operation_refund', $operation->public_id, 'برگشت خودکار عملیات ناموفق');
        }, 3);
    }

    private function markUnknown(ProvisionOperation $operation, ServiceAccount $service, Throwable $exception, bool $markService = true): void
    {
        $message = 'نتیجه عملیات مرزبان نامشخص: '.Str::limit($exception->getMessage(), 400);
        $operation->update(['status' => 'unknown', 'error_message' => $message]);
        if ($markService) $service->update(['status' => 'provisioning', 'last_error' => $message]);
        else $service->update(['last_error' => $message]);
    }

    public function reconcileUnknown(ProvisionOperation $operation): bool
    {
        if ($operation->status !== 'unknown' || ! $operation->service) return false;
        $service = $operation->service;
        $snapshot = $operation->request_snapshot ?? [];
        $targetExpire = (int) ($snapshot['target_expire'] ?? 0);
        $targetBytes = (int) ($snapshot['target_data_limit'] ?? 0);
        if ($targetExpire <= 0 || $targetBytes <= 0) return false;

        try { $remote = $this->marzban->getUser($service->panel, $service->external_username); }
        catch (Throwable) { return false; }

        $matches = $remote && (int) ($remote['expire'] ?? 0) === $targetExpire && (int) ($remote['data_limit'] ?? 0) === $targetBytes;
        if (! $matches) {
            $this->failAndRefund($operation, $service, new RuntimeException('تطبیق خودکار، عدم انجام عملیات را تأیید کرد.'), $operation->type === 'create');
            return true;
        }

        $warning = null;
        if ($operation->type === 'renew') {
            try { $remote = $this->marzban->resetUsage($service->panel, $service->external_username); }
            catch (Throwable) { $warning = 'حجم و زمان تطبیق شد اما ریست مصرف نیازمند بررسی مدیر است.'; }
        }
        $operation->status = 'reconciled';
        $this->complete($operation, $service, $remote, $targetExpire, $targetBytes, $warning);
        return true;
    }
}
