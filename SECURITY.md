# راهنمای امنیت

## گزارش آسیب‌پذیری

جزئیات آسیب‌پذیری، لاگ حساس، رمز، توکن، لینک اشتراک یا فایل `.env` را در Issue عمومی منتشر نکنید. گزارش باید شامل نسخه، اثر، مراحل بازتولید بدون داده واقعی و پیشنهاد اصلاح باشد.

## مرزهای امنیتی نسخه 0.1.0

- فقط مدیر به اطلاعات اتصال مرزبان و رسیدهای بانکی دسترسی دارد.
- رمز و نام کاربری مرزبان، Snapshot پاسخ‌ها، لینک اشتراک و Metadata مالی با `APP_KEY` رمزگذاری می‌شوند.
- همه Mutationهای وب تحت Session، CSRF و کنترل نقش هستند.
- عملیات مالی با شناسه یکتا و دفترکل دوبل ثبت می‌شود.
- آدرس مرزبان فقط HTTPS است و به‌طور پیش‌فرض IP خصوصی، Loopback و رزروشده را رد می‌کند.
- هیچ رمز، توکن یا پاسخ کامل مرزبان در UI یا لاگ عادی چاپ نمی‌شود.

## استقرار

- Document Root فقط پوشه `public` باشد.
- نسخه مرزبان را به نسخه‌ای که آخرین اصلاحات امنیتی را دارد ارتقا دهید و API را مستقیماً روی اینترنت رها نکنید؛ Firewall و Allowlist مبدا هاست توصیه می‌شود.
- `APP_DEBUG=false`، بررسی TLS و Cookie امن را حفظ کنید.
- از `APP_KEY`، دیتابیس و `.env` بکاپ امن بگیرید و دسترسی فایل‌ها را محدود کنید.
- پس از تغییر `APP_KEY`، داده‌های رمزگذاری‌شده قبلی بدون کلید قبلی قابل خواندن نیستند.

## منابع پیاده‌سازی بررسی‌شده در 2026-08-13

- Laravel 12/13 deployment and support policy: https://laravel.com/docs/13.x/releases و https://laravel.com/docs/13.x/deployment
- Marzban current user API source: https://github.com/Gozargah/Marzban/blob/master/app/routers/user.py
- Marzban repository and releases: https://github.com/Gozargah/Marzban/releases
