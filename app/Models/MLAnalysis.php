<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MLAnalysis extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ml_analyses';

    /**
     * Indicates if the model should be timestamped.
     * Table only has generated_at, not the standard created_at/updated_at pair.
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
        'generated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Get the risk results produced by this analysis run.
     * FK: risk_results.analysis_id → ml_analyses.id
     */
    public function riskResults(): HasMany
    {
        return $this->hasMany(RiskResult::class, 'analysis_id', 'id');
    }

    /**
     * Get the clusters produced by this analysis run.
     * FK: clusters.analysis_id → ml_analyses.id
     */
    public function clusters(): HasMany
    {
        return $this->hasMany(Cluster::class, 'analysis_id', 'id');
    }
}
