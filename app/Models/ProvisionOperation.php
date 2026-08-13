<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisionOperation extends Model
{
    protected $fillable = ['public_id', 'reseller_id', 'service_account_id', 'type', 'status', 'amount_irr', 'request_snapshot', 'response_snapshot', 'error_message', 'completed_at'];
    protected function casts(): array { return ['amount_irr' => 'integer', 'request_snapshot' => 'encrypted:array', 'response_snapshot' => 'encrypted:array', 'completed_at' => 'datetime']; }
    public function reseller(): BelongsTo { return $this->belongsTo(User::class, 'reseller_id'); }
    public function service(): BelongsTo { return $this->belongsTo(ServiceAccount::class, 'service_account_id'); }
}
