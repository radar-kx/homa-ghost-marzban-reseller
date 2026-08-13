<?php

declare(strict_types=1);

const HOMA_VERSION = '0.3.0';

@set_time_limit(180);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$basePath = __DIR__;
$installedLock = $basePath.'/storage/app/installed.lock';

if (is_file($installedLock)) {
    header('Location: login', true, 302);
    exit;
}

$https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_name('homa_installer');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

if (! isset($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function old(string $key, string $default = ''): string
{
    return h((string) ($_POST[$key] ?? $default));
}

function envQuote(string $value): string
{
    $value = str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\\"', '', ''], $value);
    return '"'.$value.'"';
}

function requirement(bool $passed, string $label, string $help = ''): array
{
    return ['passed' => $passed, 'label' => $label, 'help' => $help];
}

function prepareWritableDirectories(string $basePath): void
{
    $directories = [
        'storage/app/private/receipts',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'bootstrap/cache',
    ];

    foreach ($directories as $directory) {
        $path = $basePath.'/'.$directory;
        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        if (is_dir($path)) {
            @chmod($path, 0775);
        }
    }
}

function writeEnvironment(string $basePath, array $data): void
{
    $environment = implode("\n", [
        'APP_NAME="Homa Ghost"',
        'APP_ENV=production',
        'APP_KEY=base64:'.base64_encode(random_bytes(32)),
        'APP_DEBUG=false',
        'APP_URL='.envQuote($data['app_url']),
        'APP_LOCALE=fa',
        'APP_FALLBACK_LOCALE=fa',
        'APP_TIMEZONE=Asia/Tehran',
        '',
        'LOG_CHANNEL=stack',
        'LOG_LEVEL=warning',
        '',
        'DB_CONNECTION=mysql',
        'DB_HOST='.envQuote($data['db_host']),
        'DB_PORT='.$data['db_port'],
        'DB_DATABASE='.envQuote($data['db_name']),
        'DB_USERNAME='.envQuote($data['db_user']),
        'DB_PASSWORD='.envQuote($data['db_password']),
        '',
        'SESSION_DRIVER=database',
        'SESSION_LIFETIME=120',
        'SESSION_ENCRYPT=true',
        'SESSION_SECURE_COOKIE=true',
        'SESSION_SAME_SITE=lax',
        'CACHE_STORE=database',
        'QUEUE_CONNECTION=sync',
        '',
        'BANK_OWNER='.envQuote($data['bank_owner']),
        'BANK_CARD_NUMBER='.envQuote($data['bank_card']),
        'BANK_NAME='.envQuote($data['bank_name']),
        'MARZBAN_ALLOW_PRIVATE_IPS=false',
        '',
        'ADMIN_NAME='.envQuote($data['admin_name']),
        'ADMIN_EMAIL='.envQuote($data['admin_email']),
        '',
    ]);

    $envPath = $basePath.'/.env';
    if (is_file($envPath)) {
        $backupPath = $basePath.'/.env.backup.'.date('Ymd-His');
        if (! @copy($envPath, $backupPath)) {
            throw new RuntimeException('ساخت نسخه پشتیبان از تنظیمات قبلی ممکن نشد.');
        }
        @chmod($backupPath, 0600);
    }

    $temporary = tempnam($basePath, '.env.tmp.');
    if ($temporary === false || file_put_contents($temporary, $environment, LOCK_EX) === false) {
        throw new RuntimeException('امکان ساخت فایل تنظیمات وجود ندارد. دسترسی نوشتن پوشه را بررسی کنید.');
    }
    @chmod($temporary, 0600);
    if (! @rename($temporary, $envPath)) {
        @unlink($temporary);
        throw new RuntimeException('انتقال امن فایل تنظیمات انجام نشد.');
    }
    @chmod($envPath, 0600);
}

prepareWritableDirectories($basePath);

$requiredExtensions = ['bcmath', 'ctype', 'curl', 'fileinfo', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'tokenizer', 'xml'];
$requirements = [
    requirement(PHP_VERSION_ID >= 80200, 'PHP 8.2 یا جدیدتر', 'نسخه فعلی: '.PHP_VERSION),
    requirement(is_file($basePath.'/vendor/autoload.php'), 'بسته کامل دارای Vendor', 'فقط ZIP بخش Releases را دانلود کنید.'),
    requirement(is_writable($basePath), 'دسترسی نوشتن پوشه اصلی'),
    requirement(is_writable($basePath.'/storage'), 'دسترسی نوشتن Storage'),
    requirement(is_writable($basePath.'/bootstrap/cache'), 'دسترسی نوشتن Cache'),
];

foreach ($requiredExtensions as $extension) {
    $requirements[] = requirement(extension_loaded($extension), 'افزونه PHP: '.$extension);
}

$requirementsPassed = count(array_filter($requirements, fn (array $item): bool => ! $item['passed'])) === 0;
$errors = [];
$success = false;

$host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
$detectedUrl = ($https ? 'https://' : 'http://').($host ?: 'example.com');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! hash_equals((string) $_SESSION['install_csrf'], (string) ($_POST['_token'] ?? ''))) {
        $errors[] = 'نشست نصب منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.';
    }
    if (! $requirementsPassed) {
        $errors[] = 'همه پیش‌نیازهای نصب باید سبز باشند.';
    }

    $data = [
        'app_url' => rtrim(trim((string) ($_POST['app_url'] ?? '')), '/'),
        'db_host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
        'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
        'db_name' => trim((string) ($_POST['db_name'] ?? '')),
        'db_user' => trim((string) ($_POST['db_user'] ?? '')),
        'db_password' => (string) ($_POST['db_password'] ?? ''),
        'admin_name' => trim((string) ($_POST['admin_name'] ?? '')),
        'admin_email' => strtolower(trim((string) ($_POST['admin_email'] ?? ''))),
        'admin_password' => (string) ($_POST['admin_password'] ?? ''),
        'bank_owner' => trim((string) ($_POST['bank_owner'] ?? '')),
        'bank_card' => trim((string) ($_POST['bank_card'] ?? '')),
        'bank_name' => trim((string) ($_POST['bank_name'] ?? '')),
    ];

    if (! preg_match('~^https://[^/\s]+$~i', $data['app_url'])) {
        $errors[] = 'آدرس پنل باید با https شروع شود و مسیر اضافه نداشته باشد.';
    }
    if ($data['db_host'] === '' || ! preg_match('/^[A-Za-z0-9._:-]+$/', $data['db_host'])) {
        $errors[] = 'هاست دیتابیس معتبر نیست.';
    }
    if (! preg_match('/^[0-9]{1,5}$/', $data['db_port']) || (int) $data['db_port'] < 1 || (int) $data['db_port'] > 65535) {
        $errors[] = 'پورت دیتابیس معتبر نیست.';
    }
    if (! preg_match('/^[A-Za-z0-9_$.-]{1,64}$/', $data['db_name'])) {
        $errors[] = 'نام دیتابیس معتبر نیست.';
    }
    if (! preg_match('/^[A-Za-z0-9_$.-]{1,64}$/', $data['db_user'])) {
        $errors[] = 'نام کاربر دیتابیس معتبر نیست.';
    }
    if ($data['db_password'] === '') {
        $errors[] = 'رمز دیتابیس را وارد کنید.';
    }
    if ($data['admin_name'] === '' || strlen($data['admin_name']) > 200) {
        $errors[] = 'نام مدیر معتبر نیست.';
    }
    if (! filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ایمیل مدیر معتبر نیست.';
    }
    if (strlen($data['admin_password']) < 12) {
        $errors[] = 'رمز مدیر باید حداقل ۱۲ کاراکتر باشد.';
    }
    if (! hash_equals($data['admin_password'], (string) ($_POST['admin_password_confirmation'] ?? ''))) {
        $errors[] = 'تکرار رمز مدیر یکسان نیست.';
    }
    if ($data['bank_owner'] === '' || $data['bank_card'] === '' || $data['bank_name'] === '') {
        $errors[] = 'اطلاعات کارت بانکی را کامل کنید.';
    }

    if ($errors === []) {
        $lockHandle = fopen($basePath.'/storage/app/installing.lock', 'c+');
        if ($lockHandle === false || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $errors[] = 'یک نصب دیگر در حال اجراست. چند لحظه بعد دوباره تلاش کنید.';
        } else {
            try {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $data['db_host'], (int) $data['db_port'], $data['db_name']);
                new PDO($dsn, $data['db_user'], $data['db_password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 8,
                ]);

                writeEnvironment($basePath, $data);

                require_once $basePath.'/vendor/autoload.php';
                $app = require $basePath.'/bootstrap/app.php';
                $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
                $kernel->bootstrap();

                Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                App\Models\User::query()->updateOrCreate(
                    ['email' => $data['admin_email']],
                    [
                        'name' => $data['admin_name'],
                        'password' => $data['admin_password'],
                        'role' => 'admin',
                        'is_active' => true,
                    ]
                );

                Illuminate\Support\Facades\Artisan::call('config:cache');
                Illuminate\Support\Facades\Artisan::call('route:cache');
                Illuminate\Support\Facades\Artisan::call('view:cache');

                if (file_put_contents($installedLock, date(DATE_ATOM).' '.bin2hex(random_bytes(16)), LOCK_EX) === false) {
                    throw new RuntimeException('ساخت قفل نهایی نصب ممکن نشد.');
                }
                @chmod($installedLock, 0600);

                $success = true;
                $_SESSION = [];
                session_destroy();
            } catch (PDOException $exception) {
                $errors[] = 'اتصال دیتابیس ناموفق بود: '.$exception->getMessage();
            } catch (Throwable $exception) {
                $errors[] = 'نصب کامل نشد: '.$exception->getMessage();
            } finally {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                @unlink($basePath.'/storage/app/installing.lock');
            }
        }
    }
}

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>نصب هما گوست</title>
    <style>
        :root{color-scheme:dark;--bg:#07101f;--card:#101b2f;--line:#263653;--text:#f2f6ff;--muted:#9badc9;--brand:#6d5dfc;--brand2:#23c7b7;--ok:#38d39f;--bad:#ff6b7b;--input:#0a1426}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 20% 0,#17264b 0,transparent 36%),radial-gradient(circle at 90% 15%,#123a3e 0,transparent 27%),var(--bg);color:var(--text);font-family:Tahoma,"Segoe UI",sans-serif;line-height:1.75}.shell{width:min(1040px,calc(100% - 28px));margin:42px auto}.brand{display:flex;align-items:center;gap:14px;margin-bottom:25px}.logo{width:52px;height:52px;border-radius:17px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:grid;place-items:center;font-size:25px;box-shadow:0 15px 45px #6d5dfc44}.brand h1{font-size:20px;margin:0}.brand p{margin:1px 0 0;color:var(--muted);font-size:13px}.layout{display:grid;grid-template-columns:310px 1fr;gap:22px}.card{background:color-mix(in srgb,var(--card) 94%,transparent);border:1px solid var(--line);border-radius:24px;box-shadow:0 25px 80px #0004}.side{padding:24px;align-self:start;position:sticky;top:20px}.side h2,.main h2{font-size:17px;margin:0 0 5px}.side>p,.intro{font-size:13px;color:var(--muted);margin:0 0 20px}.check{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid #ffffff0b;font-size:13px}.check:last-child{border:0}.dot{width:20px;height:20px;border-radius:50%;display:grid;place-items:center;flex:0 0 20px;font-size:11px;font-weight:bold}.pass .dot{background:#38d39f22;color:var(--ok)}.fail .dot{background:#ff6b7b22;color:var(--bad)}.check small{display:block;color:var(--muted);line-height:1.45}.main{padding:30px}.notice{border-radius:16px;padding:13px 15px;margin:0 0 18px;font-size:13px}.notice.bad{background:#ff6b7b13;border:1px solid #ff6b7b55;color:#ffd9de}.notice.ok{background:#38d39f13;border:1px solid #38d39f55;color:#caffee}.section{margin-top:25px;padding-top:22px;border-top:1px solid var(--line)}.section-head{display:flex;gap:12px;align-items:center;margin-bottom:15px}.num{width:30px;height:30px;border-radius:10px;background:#6d5dfc22;color:#a99fff;display:grid;place-items:center;font-weight:bold}.section h3{margin:0;font-size:15px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}label{font-size:12px;color:#ced8ea;display:block}input{display:block;width:100%;margin-top:7px;border:1px solid var(--line);background:var(--input);color:var(--text);border-radius:12px;padding:12px 13px;font:inherit;font-size:13px;outline:none}input:focus{border-color:#786aff;box-shadow:0 0 0 3px #6d5dfc25}.wide{grid-column:1/-1}.actions{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:27px}.actions p{font-size:12px;color:var(--muted);margin:0}.button{border:0;border-radius:13px;background:linear-gradient(135deg,var(--brand),#8578ff);color:white;padding:13px 24px;font:inherit;font-weight:bold;cursor:pointer;box-shadow:0 12px 30px #6d5dfc45}.button:disabled{opacity:.45;cursor:not-allowed}.success{text-align:center;padding:38px 10px}.success-icon{width:72px;height:72px;border-radius:24px;background:#38d39f22;color:var(--ok);display:grid;place-items:center;font-size:34px;margin:0 auto 18px}.success h2{font-size:23px}.success p{color:var(--muted)}.login{display:inline-block;text-decoration:none;background:linear-gradient(135deg,var(--brand),#8578ff);color:#fff;padding:12px 25px;border-radius:13px;font-weight:bold;margin-top:12px}@media(max-width:780px){.shell{margin:20px auto}.layout{grid-template-columns:1fr}.side{position:static}.grid{grid-template-columns:1fr}.wide{grid-column:auto}.main{padding:21px}.actions{align-items:stretch;flex-direction:column}.button{width:100%}}
    </style>
</head>
<body>
<main class="shell">
    <header class="brand">
        <div class="logo">هـ</div>
        <div><h1>نصب پنل نمایندگی هما گوست</h1><p>نسخه <?= h(HOMA_VERSION) ?> — نصب تحت وب ویژه cPanel</p></div>
    </header>
    <div class="layout">
        <aside class="card side">
            <h2>بررسی خودکار هاست</h2>
            <p>موارد سبز برای نصب مناسب هستند.</p>
            <?php foreach ($requirements as $item): ?>
                <div class="check <?= $item['passed'] ? 'pass' : 'fail' ?>">
                    <span class="dot"><?= $item['passed'] ? '✓' : '×' ?></span>
                    <span><?= h($item['label']) ?><?php if ($item['help'] !== ''): ?><small><?= h($item['help']) ?></small><?php endif; ?></span>
                </div>
            <?php endforeach; ?>
        </aside>

        <section class="card main">
            <?php if ($success): ?>
                <div class="success">
                    <div class="success-icon">✓</div>
                    <h2>نصب با موفقیت انجام شد</h2>
                    <p>تنظیمات، جداول و حساب مدیر ساخته شدند و نصب‌کننده قفل شد.</p>
                    <a class="login" href="login">ورود به پنل مدیریت</a>
                </div>
            <?php else: ?>
                <h2>راه‌اندازی سریع</h2>
                <p class="intro">اطلاعات زیر فقط روی هاست خودتان پردازش می‌شوند. رمزها در صفحه یا گزارش نمایش داده نمی‌شوند.</p>

                <?php foreach ($errors as $error): ?><div class="notice bad"><?= h($error) ?></div><?php endforeach; ?>
                <?php if (! $requirementsPassed): ?><div class="notice bad">ابتدا موارد قرمز بخش بررسی هاست را برطرف کنید.</div><?php endif; ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="_token" value="<?= h((string) $_SESSION['install_csrf']) ?>">

                    <div class="section">
                        <div class="section-head"><span class="num">۱</span><h3>آدرس پنل</h3></div>
                        <div class="grid"><label class="wide">آدرس کامل دارای SSL<input dir="ltr" name="app_url" required value="<?= old('app_url', $detectedUrl) ?>" placeholder="https://reseller.example.com"></label></div>
                    </div>

                    <div class="section">
                        <div class="section-head"><span class="num">۲</span><h3>اطلاعات دیتابیس cPanel</h3></div>
                        <div class="grid">
                            <label>هاست دیتابیس<input dir="ltr" name="db_host" required value="<?= old('db_host', '127.0.0.1') ?>"></label>
                            <label>پورت<input dir="ltr" name="db_port" required value="<?= old('db_port', '3306') ?>"></label>
                            <label>نام کامل دیتابیس<input dir="ltr" name="db_name" required value="<?= old('db_name') ?>" placeholder="cpuser_homa"></label>
                            <label>نام کامل کاربر<input dir="ltr" name="db_user" required value="<?= old('db_user') ?>" placeholder="cpuser_homauser"></label>
                            <label class="wide">رمز دیتابیس<input dir="ltr" type="password" name="db_password" required autocomplete="new-password"></label>
                        </div>
                    </div>

                    <div class="section">
                        <div class="section-head"><span class="num">۳</span><h3>حساب مدیر اصلی</h3></div>
                        <div class="grid">
                            <label>نام مدیر<input name="admin_name" required value="<?= old('admin_name', 'مدیر هما گوست') ?>"></label>
                            <label>ایمیل مدیر<input dir="ltr" type="email" name="admin_email" required value="<?= old('admin_email') ?>"></label>
                            <label>رمز مدیر<input dir="ltr" type="password" name="admin_password" required minlength="12" autocomplete="new-password"></label>
                            <label>تکرار رمز مدیر<input dir="ltr" type="password" name="admin_password_confirmation" required minlength="12" autocomplete="new-password"></label>
                        </div>
                    </div>

                    <div class="section">
                        <div class="section-head"><span class="num">۴</span><h3>اطلاعات واریز نمایندگان</h3></div>
                        <div class="grid">
                            <label>نام صاحب کارت<input name="bank_owner" required value="<?= old('bank_owner') ?>"></label>
                            <label>نام بانک<input name="bank_name" required value="<?= old('bank_name') ?>"></label>
                            <label class="wide">شماره کارت<input dir="ltr" name="bank_card" required value="<?= old('bank_card') ?>" placeholder="0000-0000-0000-0000"></label>
                        </div>
                    </div>

                    <div class="actions">
                        <p>با شروع نصب، اتصال دیتابیس آزمایش و سپس تنظیمات ذخیره می‌شوند.</p>
                        <button class="button" type="submit" <?= $requirementsPassed ? '' : 'disabled' ?>>شروع نصب هما گوست</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
