<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletJournal extends Model
{
    public $timestamps = false;
    protected $fillable = ['reseller_id', 'idempotency_key', 'direction', 'amount_irr', 'balance_after_irr', 'source_type', 'source_id', 'description', 'metadata', 'created_at'];
    protected function casts(): array { return ['amount_irr' => 'integer', 'balance_after_irr' => 'integer', 'metadata' => 'encrypted:array', 'created_at' => 'datetime']; }
    public function reseller(): BelongsTo { return $this->belongsTo(User::class, 'reseller_id'); }
}
