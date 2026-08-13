<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PanelConnection extends Model
{
    protected $fillable = ['name', 'base_url', 'admin_username', 'admin_password', 'verify_tls', 'is_active'];
    protected $hidden = ['admin_password'];
    protected function casts(): array { return ['admin_username' => 'encrypted', 'admin_password' => 'encrypted', 'verify_tls' => 'boolean', 'is_active' => 'boolean']; }
    public function plans(): HasMany { return $this->hasMany(Plan::class); }
}
