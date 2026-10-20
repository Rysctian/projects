<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketStageSignoff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketStageSignoff> */
class TicketStageSignoffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory()->inStage('qa'),
            'stage_id' => fn (array $attributes) => Ticket::query()->whereKey($attributes['ticket_id'])->sole()->getAttribute('stage_id'),
            'actor_id' => User::factory(),
            'kind' => 'qa',
            'result' => 'pass',
            'note' => fake()->sentence(),
            'verified_revision' => 1,
            'stage_visit' => 1,
            'verification_cycle' => 1,
            'created_at' => now(),
        ];
    }
}
