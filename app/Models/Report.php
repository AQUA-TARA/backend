<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'reports';

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
        'citizen_id',
        'report_type',
        'description',
        'latitude',
        'longitude',
        'photo_path',
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
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the citizen that submitted the report (nullable for anonymous).
     */
    public function citizen(): BelongsTo
    {
        return $this->belongsTo(Citizen::class, 'citizen_id', 'id');
    }

    /**
     * Get the ticket associated with the report.
     */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class, 'report_id', 'id');
    }

    /**
     * Get the risk results associated with the report.
     */
    public function riskResults(): HasMany
    {
        return $this->hasMany(RiskResult::class, 'report_id', 'id');
    }

    /**
     * Get the latest evaluated risk result for this report.
     * Joined with ml_analyses and ordered by generated_at DESC, id DESC.
     */
    public function latestRiskResult(): HasOne
    {
        return $this->hasOne(RiskResult::class, 'report_id', 'id')
            ->join('ml_analyses', 'ml_analyses.id', '=', 'risk_results.analysis_id')
            ->orderByDesc('ml_analyses.generated_at')
            ->orderByDesc('risk_results.id')
            ->select('risk_results.*');
    }
}
