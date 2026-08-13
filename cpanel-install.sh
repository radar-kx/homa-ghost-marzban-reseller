#!/usr/bin/env bash
set -Eeuo pipefail

# نصب‌کننده فارسی پنل نمایندگی مرزبان هما گوست برای cPanel
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly RED='\033[0;31m'
readonly CYAN='\033[0;36m'
readonly RESET='\033[0m'

step() { printf "\n${CYAN}==> %s${RESET}\n" "$1"; }
ok() { printf "${GREEN}✓ %s${RESET}\n" "$1"; }
warn() { printf "${YELLOW}هشدار: %s${RESET}\n" "$1" >&2; }
fail() { printf "${RED}خطا: %s${RESET}\n" "$1" >&2; exit 1; }

on_error() {
    local exit_code=$?
    printf "\n${RED}نصب در خط %s متوقف شد (کد %s).${RESET}\n" "${BASH_LINENO[0]:-نامشخص}" "$exit_code" >&2
    printf "گزارش بالا را کپی کنید؛ اجرای دوباره نصب‌کننده امن است.\n" >&2
    exit "$exit_code"
}
trap on_error ERR

prompt_required() {
    local label="$1" default_value="${2:-}" value
    while true; do
        if [[ -n "$default_value" ]]; then
            read -r -p "$label [$default_value]: " value
            value="${value:-$default_value}"
        else
            read -r -p "$label: " value
        fi
        [[ -n "$value" ]] && { printf '%s' "$value"; return; }
        warn "این مقدار نمی‌تواند خالی باشد."
    done
}

prompt_secret() {
    local label="$1" value
    read -r -s -p "$label: " value
    printf '\n' >&2
    printf '%s' "$value"
}

env_value() {
    local key="$1"
    [[ -f .env ]] || return 0
    awk -F= -v key="$key" '$1 == key {sub(/^[^=]*=/, ""); gsub(/^"|"$/, ""); print; exit}' .env
}

escape_env() {
    local value="$1"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    value="${value//$'\r'/}"
    value="${value//$'\n'/}"
    printf '"%s"' "$value"
}

write_env() {
    local output_file="$1"
    cat > "$output_file" <<EOF
APP_NAME="Homa Ghost"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=$(escape_env "$APP_URL_INPUT")
APP_LOCALE=fa
APP_FALLBACK_LOCALE=fa
APP_TIMEZONE=Asia/Tehran

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=$(escape_env "$DB_HOST_INPUT")
DB_PORT=$DB_PORT_INPUT
DB_DATABASE=$(escape_env "$DB_NAME_INPUT")
DB_USERNAME=$(escape_env "$DB_USER_INPUT")
DB_PASSWORD=$(escape_env "$DB_PASS_INPUT")

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=sync

BANK_OWNER=$(escape_env "$BANK_OWNER_INPUT")
BANK_CARD_NUMBER=$(escape_env "$BANK_CARD_INPUT")
BANK_NAME=$(escape_env "$BANK_NAME_INPUT")
MARZBAN_ALLOW_PRIVATE_IPS=false

ADMIN_NAME=$(escape_env "$ADMIN_NAME_INPUT")
ADMIN_EMAIL=$(escape_env "$ADMIN_EMAIL_INPUT")
EOF
}

clear 2>/dev/null || true
printf "${CYAN}====================================================${RESET}\n"
printf "${CYAN}  نصب‌کننده فارسی پنل نمایندگی مرزبان هما گوست${RESET}\n"
printf "${CYAN}  نسخه 0.2.0 — ویژه cPanel${RESET}\n"
printf "${CYAN}====================================================${RESET}\n"

[[ -f artisan && -f composer.json && -f .env.example ]] || fail "فایل ناقص است. اسکریپت را از پوشه اصلی بسته استخراج‌شده اجرا کنید."

step "۱ از ۷ — بررسی PHP و پیش‌نیازها"

PHP_BIN=""
for candidate in php /usr/local/bin/ea-php85 /usr/local/bin/ea-php84 /usr/local/bin/ea-php83 /usr/local/bin/ea-php82; do
    if command -v "$candidate" >/dev/null 2>&1; then
        candidate_path="$(command -v "$candidate")"
        version_id="$($candidate_path -r 'echo PHP_VERSION_ID;' 2>/dev/null || true)"
        if [[ "$version_id" =~ ^[0-9]+$ ]] && (( version_id >= 80200 )); then
            PHP_BIN="$candidate_path"
            break
        fi
    fi
done
[[ -n "$PHP_BIN" ]] || fail "PHP CLI نسخه 8.2 یا جدیدتر پیدا نشد. در cPanel بخش Select PHP Version آن را فعال کنید."
ok "PHP $($PHP_BIN -r 'echo PHP_VERSION;') پیدا شد: $PHP_BIN"

COMPOSER_BIN=""
if command -v composer >/dev/null 2>&1; then
    COMPOSER_BIN="$(command -v composer)"
elif [[ -x /opt/cpanel/composer/bin/composer ]]; then
    COMPOSER_BIN=/opt/cpanel/composer/bin/composer
fi
[[ -n "$COMPOSER_BIN" ]] || fail "Composer 2 پیدا نشد. از پشتیبانی هاست بخواهید Composer را برای Terminal فعال کند."
ok "Composer پیدا شد: $COMPOSER_BIN"

MODULES="$($PHP_BIN -m | tr '[:upper:]' '[:lower:]')"
missing_extensions=()
for extension in bcmath ctype curl fileinfo mbstring openssl pdo pdo_mysql tokenizer xml; do
    grep -qx "$extension" <<< "$MODULES" || missing_extensions+=("$extension")
done
(( ${#missing_extensions[@]} == 0 )) || fail "افزونه‌های PHP زیر فعال نیستند: ${missing_extensions[*]}"
ok "همه افزونه‌های ضروری PHP فعال هستند."

free_kb="$(df -Pk "$PROJECT_DIR" | awk 'NR==2 {print $4}')"
(( free_kb >= 204800 )) || fail "حداقل ۲۰۰ مگابایت فضای خالی لازم است."
[[ -w "$PROJECT_DIR" ]] || fail "پوشه پروژه اجازه نوشتن ندارد."

step "۲ از ۷ — دریافت تنظیمات دامنه و دیتابیس"

current_url="$(env_value APP_URL)"
[[ "$current_url" == "https://panel.example.com" ]] && current_url=""
APP_URL_INPUT="$(prompt_required 'آدرس کامل پنل با https' "$current_url")"
[[ "$APP_URL_INPUT" =~ ^https://[^/[:space:]]+/?$ ]] || fail "آدرس پنل باید مانند https://reseller.example.com و بدون مسیر اضافه باشد."
APP_URL_INPUT="${APP_URL_INPUT%/}"

current_db_host="$(env_value DB_HOST || true)"
current_db_port="$(env_value DB_PORT || true)"
DB_HOST_INPUT="$(prompt_required 'هاست دیتابیس' "${current_db_host:-127.0.0.1}")"
DB_PORT_INPUT="$(prompt_required 'پورت دیتابیس' "${current_db_port:-3306}")"
[[ "$DB_PORT_INPUT" =~ ^[0-9]{1,5}$ ]] || fail "پورت دیتابیس معتبر نیست."
DB_NAME_INPUT="$(prompt_required 'نام کامل دیتابیس cPanel' "$(env_value DB_DATABASE || true)")"
DB_USER_INPUT="$(prompt_required 'نام کامل کاربر دیتابیس cPanel' "$(env_value DB_USERNAME || true)")"
DB_PASS_INPUT="$(prompt_secret 'رمز کاربر دیتابیس')"
[[ -n "$DB_PASS_INPUT" ]] || fail "رمز دیتابیس نمی‌تواند خالی باشد."

step "۳ از ۷ — دریافت اطلاعات مدیر و کارت بانکی"

ADMIN_NAME_INPUT="$(prompt_required 'نام مدیر پنل' "$(env_value ADMIN_NAME || true)")"
ADMIN_EMAIL_INPUT="$(prompt_required 'ایمیل مدیر' "$(env_value ADMIN_EMAIL || true)")"
[[ "$ADMIN_EMAIL_INPUT" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] || fail "ایمیل مدیر معتبر نیست."
ADMIN_PASSWORD_INPUT="$(prompt_secret 'رمز مدیر (حداقل ۱۲ کاراکتر)')"
(( ${#ADMIN_PASSWORD_INPUT} >= 12 )) || fail "رمز مدیر باید حداقل ۱۲ کاراکتر باشد."
ADMIN_PASSWORD_CONFIRM="$(prompt_secret 'تکرار رمز مدیر')"
[[ "$ADMIN_PASSWORD_INPUT" == "$ADMIN_PASSWORD_CONFIRM" ]] || fail "تکرار رمز مدیر یکسان نیست."

BANK_OWNER_INPUT="$(prompt_required 'نام صاحب کارت' "$(env_value BANK_OWNER || true)")"
BANK_CARD_INPUT="$(prompt_required 'شماره کارت' "$(env_value BANK_CARD_NUMBER || true)")"
BANK_NAME_INPUT="$(prompt_required 'نام بانک' "$(env_value BANK_NAME || true)")"

printf '\nخلاصه تنظیمات:\n'
printf '  آدرس پنل: %s\n  دیتابیس: %s@%s:%s\n  مدیر: %s <%s>\n' "$APP_URL_INPUT" "$DB_NAME_INPUT" "$DB_HOST_INPUT" "$DB_PORT_INPUT" "$ADMIN_NAME_INPUT" "$ADMIN_EMAIL_INPUT"
read -r -p 'نصب با این اطلاعات شروع شود؟ [y/N]: ' confirmation
[[ "$confirmation" =~ ^[yY]$ ]] || { warn "نصب لغو شد و تغییری اعمال نشد."; exit 0; }

step "۴ از ۷ — ساخت تنظیمات امن"

if [[ -f .env ]]; then
    backup_file=".env.backup.$(date +%Y%m%d-%H%M%S)"
    cp .env "$backup_file"
    chmod 600 "$backup_file"
    ok "نسخه پشتیبان تنظیمات قبلی ساخته شد: $backup_file"
fi
temp_env="$(mktemp "$PROJECT_DIR/.env.tmp.XXXXXX")"
write_env "$temp_env"
chmod 600 "$temp_env"
mv "$temp_env" .env
ok "فایل تنظیمات با دسترسی محدود ساخته شد."

step "۵ از ۷ — بررسی اتصال دیتابیس"

DB_HOST_INPUT="$DB_HOST_INPUT" DB_PORT_INPUT="$DB_PORT_INPUT" DB_NAME_INPUT="$DB_NAME_INPUT" DB_USER_INPUT="$DB_USER_INPUT" DB_PASS_INPUT="$DB_PASS_INPUT" \
"$PHP_BIN" -r '
$dsn = "mysql:host=".getenv("DB_HOST_INPUT").";port=".getenv("DB_PORT_INPUT").";dbname=".getenv("DB_NAME_INPUT").";charset=utf8mb4";
try { new PDO($dsn, getenv("DB_USER_INPUT"), getenv("DB_PASS_INPUT"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
catch (Throwable $e) { fwrite(STDERR, "اتصال دیتابیس ناموفق بود: ".$e->getMessage().PHP_EOL); exit(1); }
'
ok "اتصال به دیتابیس موفق بود."

step "۶ از ۷ — نصب وابستگی‌ها و ساخت جداول"

mkdir -p storage/app/private/receipts storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache
"$PHP_BIN" "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$PHP_BIN" artisan key:generate --force

export ADMIN_NAME="$ADMIN_NAME_INPUT"
export ADMIN_EMAIL="$ADMIN_EMAIL_INPUT"
export HOMA_ADMIN_PASSWORD="$ADMIN_PASSWORD_INPUT"
"$PHP_BIN" artisan homa:install --no-interaction
unset ADMIN_NAME ADMIN_EMAIL HOMA_ADMIN_PASSWORD

step "۷ از ۷ — بهینه‌سازی و کنترل نهایی"

"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan migrate:status --no-ansi >/dev/null
[[ -f storage/app/installed.lock ]] || fail "قفل نصب ساخته نشد."

ok "نصب پنل هما گوست با موفقیت کامل شد."
printf "\n${GREEN}آدرس ورود: %s/login${RESET}\n" "$APP_URL_INPUT"
printf "ایمیل مدیر: %s\n" "$ADMIN_EMAIL_INPUT"
printf "\nDocument Root دامنه باید دقیقاً روی این مسیر باشد:\n%s/public\n" "$PROJECT_DIR"
printf "\nCron Job پیشنهادی (هر دقیقه):\n* * * * * cd %q && %q artisan schedule:run >/dev/null 2>&1\n" "$PROJECT_DIR" "$PHP_BIN"
printf "\nبعد از ورود، ابتدا اتصال مرزبان و سپس پلن‌ها و نمایندگان را بسازید.\n"
