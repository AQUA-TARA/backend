<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Citizen extends Authenticatable
{
    use HasApiTokens;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'citizens';

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
        'phone_number',
    ];

    /**
     * Get the reports created by the citizen.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'citizen_id', 'id');
    }
}
