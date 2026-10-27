<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskResult extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'risk_results';

    /**
     * Indicates if the model should be timestamped.
     * Table has no timestamps — analytical output, not an operational entity.
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
        'report_id',
        'risk_score',
        'sensor_score',
        'citizen_score',
        'node_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
            'sensor_score' => 'float',
            'citizen_score' => 'float',
            'node_id' => 'integer',
        ];
    }

    /**
     * Get the ML analysis run that produced this risk result.
     * FK: risk_results.analysis_id → ml_analyses.id
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(MLAnalysis::class, 'analysis_id', 'id');
    }

    /**
     * Get the report this risk result evaluates.
     * FK: risk_results.report_id → reports.id (LOCKED: Decision A, Gate 1)
     *
     * NOTE: UNIQUE(report_id) does NOT exist — multiple runs are allowed.
     * Latest active result is determined at query/application layer.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id', 'id');
    }

    /**
     * Get the derived Risk Level string based on the score.
     * Centralized logic mapping score (0-100) to human-readable severity.
     */
    public function getRiskLevelAttribute(): string
    {
        $score = $this->risk_score;
        if ($score <= 30) {
            return 'Jernih';
        }
        if ($score <= 70) {
            return 'Waspada';
        }
        return 'Bahaya';
    }
}
