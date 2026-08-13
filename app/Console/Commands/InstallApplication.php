<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstallApplication extends Command
{
    protected $signature = 'homa:install {--force : اجرای دوباره نصب}';
    protected $description = 'نصب امن و اولیه پنل هما گوست';

    public function handle(): int
    {
        if (file_exists(storage_path('app/installed.lock')) && ! $this->option('force')) { $this->error('پنل قبلاً نصب شده است.'); return self::FAILURE; }
        if (! app()->environment('production')) $this->warn('APP_ENV روی production نیست.');

        Artisan::call('migrate', ['--force' => true]);
        $email = (string) env('ADMIN_EMAIL', 'admin@example.com');
        $name = (string) env('ADMIN_NAME', 'مدیر هما گوست');
        $password = env('HOMA_ADMIN_PASSWORD');
        if (! $this->option('no-interaction')) {
            $email = $this->ask('ایمیل مدیر', $email);
            $name = $this->ask('نام مدیر', $name);
            $password = $this->secret('رمز مدیر (حداقل ۱۲ کاراکتر)');
        }
        if (! is_string($password) || strlen($password) < 12) { $this->error('رمز باید حداقل ۱۲ کاراکتر باشد.'); return self::FAILURE; }

        User::query()->updateOrCreate(['email' => strtolower((string) $email)], [
            'name' => $name, 'password' => Hash::make($password), 'role' => 'admin', 'is_active' => true,
        ]);
        if (! is_dir(storage_path('app'))) mkdir(storage_path('app'), 0775, true);
        file_put_contents(storage_path('app/installed.lock'), now()->toIso8601String().' '.Str::uuid());
        Artisan::call('optimize');
        $this->info('نصب انجام شد. رمز را در محل امن نگهداری کنید.');
        return self::SUCCESS;
    }
}
