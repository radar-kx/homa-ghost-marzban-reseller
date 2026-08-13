<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositRequest extends Model
{
    protected $fillable = ['public_id', 'reseller_id', 'amount_irr', 'tracking_code', 'receipt_path', 'status', 'review_note', 'reviewed_by', 'reviewed_at'];
    protected function casts(): array { return ['amount_irr' => 'integer', 'reviewed_at' => 'datetime']; }
    public function reseller(): BelongsTo { return $this->belongsTo(User::class, 'reseller_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
