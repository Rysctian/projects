<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before_json' => 'array', 'after_json' => 'array', 'created_at' => 'immutable_datetime', 'occurred_at' => 'immutable_datetime', 'recorded_at' => 'immutable_datetime'];
    }

    /**
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>|null  $after
     */
    public static function record(User $actor, Model $entity, string $action, ?array $before = null, ?array $after = null, ?int $projectId = null): self
    {
        return static::create([
            'actor_id' => $actor->id,
            'project_id' => $projectId,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => $entity->getKey(),
            'action' => $action,
            'before_json' => $before,
            'after_json' => $after,
            'created_at' => now(),
            'occurred_at' => now(),
            'recorded_at' => now(),
            'correlation_id' => (string) Str::uuid(),
        ]);
    }
}
