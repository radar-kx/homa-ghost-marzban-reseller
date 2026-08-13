<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'wallet_balance_irr', 'reseller_prefix'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'wallet_balance_irr' => 'integer']; }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isReseller(): bool { return $this->role === 'reseller'; }
    public function services(): HasMany { return $this->hasMany(ServiceAccount::class, 'reseller_id'); }
    public function deposits(): HasMany { return $this->hasMany(DepositRequest::class, 'reseller_id'); }
    public function walletJournals(): HasMany { return $this->hasMany(WalletJournal::class, 'reseller_id'); }
}
