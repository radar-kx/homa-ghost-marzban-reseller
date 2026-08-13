<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['panel_connection_id', 'name', 'data_limit_gb', 'duration_days', 'price_irr', 'proxies', 'inbounds', 'is_active'];
    protected function casts(): array { return ['data_limit_gb' => 'decimal:2', 'duration_days' => 'integer', 'price_irr' => 'integer', 'proxies' => 'array', 'inbounds' => 'array', 'is_active' => 'boolean']; }
    public function panel(): BelongsTo { return $this->belongsTo(PanelConnection::class, 'panel_connection_id'); }
    public function services(): HasMany { return $this->hasMany(ServiceAccount::class); }
    public function dataLimitBytes(): int { return (int) round((float) $this->data_limit_gb * 1024 * 1024 * 1024); }
}
