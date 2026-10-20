<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketAttachment> */
class TicketAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return ['ticket_id' => Ticket::factory(), 'uploaded_by' => User::factory(), 'storage_disk' => 'private', 'storage_path' => 'tickets/'.fake()->uuid().'/'.fake()->uuid().'.txt', 'original_filename' => 'notes.txt', 'mime_type' => 'text/plain', 'size_bytes' => fake()->numberBetween(100, 10240)];
    }
}
