<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cluster extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clusters';

    /**
     * Indicates if the model should be timestamped.
     * Table has no timestamps — transient analytical output.
     * Timing is tracked on ml_analyses.generated_at.
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
        'analysis_id',
        'centroid_lat',
        'centroid_lng',
        'report_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'centroid_lat' => 'decimal:8',
            'centroid_lng' => 'decimal:8',
            'report_count' => 'integer',
        ];
    }

    /**
     * Get the ML analysis run that produced this cluster.
     * FK: clusters.analysis_id → ml_analyses.id
     *
     * NOTE: No FK to reports or iot_measurements (Decision C1).
     * Cluster has NO persistent membership to Reports or IoT Nodes.
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(MLAnalysis::class, 'analysis_id', 'id');
    }
}
