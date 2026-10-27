<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Report;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'report_id' => function () {
                return Report::factory()->create()->id;
            },
            'status' => 'open',
        ];
    }
}
