<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tickets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'report_id',
        'ticket_code',
        'cluster_code',
        'status',
    ];

    /**
     * Generate the next sequential Ticket ID (AQT-YYMMDD-NNNN) concurrently safe.
     * Should be called within a database transaction.
     */
    public static function generateNextTicketCode(?\Carbon\Carbon $date = null): string
    {
        $targetDate = $date ?: now();
        $datePrefix = $targetDate->format('ymd');
        $prefix = "AQT-{$datePrefix}-";

        // Lock existing tickets matching prefix to safely get the current maximum sequence
        $latestCode = static::where('ticket_code', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderBy('ticket_code', 'desc')
            ->value('ticket_code');

        $sequence = 1;
        if ($latestCode && preg_match('/AQT-\d{6}-(\d{4})/', $latestCode, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        $seqPadded = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        return "{$prefix}{$seqPadded}";
    }

    /**
     * Scope or helper to find a ticket by numeric primary key or ticket_code.
     */
    public static function findByCodeOrId($identifier)
    {
        if (is_numeric($identifier)) {
            return static::where('id', $identifier)
                ->orWhere('ticket_code', (string) $identifier)
                ->first();
        }

        return static::where('ticket_code', $identifier)->first();
    }

    /**
     * Override find to safely support string ticket_code alongside integer ID without SQL syntax error.
     */
    public static function find($id, $columns = ['*'])
    {
        if (is_array($id) || is_numeric($id)) {
            return static::query()->find($id, $columns);
        }

        return static::where('ticket_code', $id)->first($columns);
    }


    /**
     * Resolve route binding by ticket_code or id.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            return $this->where('id', $value)->orWhere('ticket_code', (string) $value)->first();
        }

        return $this->where('ticket_code', $value)->first();
    }

    /**
     * Get the report associated with the ticket.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id', 'id');
    }

    /**
     * Get the status histories for the ticket.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class, 'ticket_id', 'id');
    }
}

