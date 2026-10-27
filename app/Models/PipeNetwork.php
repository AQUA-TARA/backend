<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipeNetwork extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pipe_networks';

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
        'name',
        'geo_data',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'geo_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * NOTE: PipeNetwork has NO relationships.
     *
     * There are no foreign keys in pipe_networks schema pointing to
     * reports, clusters, iot_nodes, or any other table.
     * This table is a standalone GeoJSON passthrough store for Admin Map rendering.
     * (Physical Design §12, DEC-011, DEC-012)
     */
}
