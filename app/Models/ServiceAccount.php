<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAccount extends Model
{
    protected $fillable = ['public_id', 'reseller_id', 'plan_id', 'panel_connection_id', 'external_username', 'customer_label', 'status', 'expire_at', 'data_limit_bytes', 'subscription_url', 'last_error', 'remote_snapshot'];
    protected $hidden = ['remote_snapshot'];
    protected function casts(): array { return ['expire_at' => 'datetime', 'data_limit_bytes' => 'integer', 'subscription_url' => 'encrypted', 'remote_snapshot' => 'encrypted:array']; }
    public function reseller(): BelongsTo { return $this->belongsTo(User::class, 'reseller_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function panel(): BelongsTo { return $this->belongsTo(PanelConnection::class, 'panel_connection_id'); }
}
