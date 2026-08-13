<?php

namespace App\Services;

use InvalidArgumentException;

class PanelUrlGuard
{
    public function normalize(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            throw new InvalidArgumentException('آدرس پنل باید HTTPS و بدون مسیر اضافی باشد.');
        }
        if (! empty($parts['user']) || ! empty($parts['pass']) || ! empty($parts['query']) || ! empty($parts['fragment']) || (($parts['path'] ?? '') !== '')) {
            throw new InvalidArgumentException('آدرس پنل نباید شامل نام کاربری، مسیر یا Query باشد.');
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) throw new InvalidArgumentException('این میزبان مجاز نیست.');
        if (! config('services.marzban.allow_private_ips')) {
            $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
            if ($ips === []) throw new InvalidArgumentException('دامنه پنل قابل شناسایی نیست.');
            foreach ($ips as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new InvalidArgumentException('اتصال به IP خصوصی یا رزروشده مجاز نیست.');
                }
            }
        }
        return $url;
    }
}
