<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IoTNode extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'iot_nodes';

    /**
     * Indicates if the model should be timestamped.
     * Table only has created_at, no updated_at.
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
        'hardware_id',
        'latitude',
        'longitude',
        'is_active',
        'is_simulation',
        'turbidity',
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
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the measurements for this IoT node.
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(IoTMeasurement::class, 'node_id', 'id');
    }

    /**
     * Get the latest measurement for this IoT node.
     */
    public function latestMeasurement(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(IoTMeasurement::class, 'node_id', 'id')
            ->orderByDesc('measured_at')
            ->orderByDesc('id');
    }
}
