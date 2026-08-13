<?php

namespace App\Services;

use App\Models\PanelConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MarzbanClient
{
    public function __construct(private readonly PanelUrlGuard $urlGuard) {}

    public function test(PanelConnection $panel): bool
    {
        $this->token($panel, true);
        return true;
    }

    public function createUser(PanelConnection $panel, array $payload): array
    {
        return $this->authenticated($panel)->post('/api/user', $payload)->throw()->json();
    }

    public function getUser(PanelConnection $panel, string $username): ?array
    {
        $response = $this->authenticated($panel)->get('/api/user/'.rawurlencode($username));
        if ($response->status() === 404) return null;
        return $response->throw()->json();
    }

    public function modifyUser(PanelConnection $panel, string $username, array $payload): array
    {
        return $this->authenticated($panel)->put('/api/user/'.rawurlencode($username), $payload)->throw()->json();
    }

    public function resetUsage(PanelConnection $panel, string $username): array
    {
        return $this->authenticated($panel)->retry(2, 350)->post('/api/user/'.rawurlencode($username).'/reset')->throw()->json();
    }

    private function authenticated(PanelConnection $panel): PendingRequest
    {
        $baseUrl = $this->urlGuard->normalize($panel->base_url);
        return Http::baseUrl($baseUrl)->acceptJson()->asJson()->withToken($this->token($panel))->timeout(15)->connectTimeout(7)->withOptions(['verify' => $panel->verify_tls]);
    }

    private function token(PanelConnection $panel, bool $fresh = false): string
    {
        $key = 'marzban-token-'.$panel->id.'-'.$panel->updated_at?->timestamp;
        if ($fresh) Cache::forget($key);
        return Cache::remember($key, now()->addMinutes(10), function () use ($panel) {
            $baseUrl = $this->urlGuard->normalize($panel->base_url);
            $response = Http::baseUrl($baseUrl)->asForm()->acceptJson()->timeout(15)->connectTimeout(7)
                ->withOptions(['verify' => $panel->verify_tls])
                ->post('/api/admin/token', ['username' => $panel->admin_username, 'password' => $panel->admin_password]);
            return (string) $response->throw()->json('access_token');
        });
    }
}
