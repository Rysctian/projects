<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketActivity> */
class TicketActivityFactory extends Factory
{
    public function definition(): array
    {
        return ['ticket_id' => Ticket::factory(), 'actor_id' => User::factory(), 'activity_type' => 'created', 'payload' => ['source' => 'factory'], 'created_at' => now()];
    }
}
