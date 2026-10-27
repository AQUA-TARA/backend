<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IoTMeasurement extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'iot_measurements';

    /**
     * Indicates if the model should be timestamped.
     * Table has no created_at or updated_at, only measured_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'node_id',
        'turbidity',

        'pressure',
        'measured_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'turbidity' => 'decimal:2',

            'pressure' => 'decimal:2',
            'measured_at' => 'datetime',
        ];
    }

    /**
     * Get the node that generated this measurement.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(IoTNode::class, 'node_id', 'id');
    }

    /**
     * Scope a query to only include valid measurements for heatmap visualization.
     * Ensures turbidity is not null, non-negative, and node is active with valid coordinates.
     */
    public function scopeValidForHeatmap($query, int $hours = 24)
    {
        return $query->join('iot_nodes as n', 'iot_measurements.node_id', '=', 'n.id')
            ->where('n.is_active', true)
            ->whereNotNull('iot_measurements.turbidity')
            ->where('iot_measurements.turbidity', '>=', 0)
            ->whereBetween('n.latitude', [-90, 90])
            ->whereBetween('n.longitude', [-180, 180])
            ->where('iot_measurements.measured_at', '>=', now()->subHours($hours));
    }
}
