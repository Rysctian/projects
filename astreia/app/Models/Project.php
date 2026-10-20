<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $guarded = ['id', 'code'];

    protected $attributes = ['status' => 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }

    /** @return HasMany<ProjectMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /** @return HasMany<Module, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    /** @return HasMany<ProjectWorkflow, $this> */
    public function workflows(): HasMany
    {
        return $this->hasMany(ProjectWorkflow::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<ProjectLabel, $this> */
    public function labels(): HasMany
    {
        return $this->hasMany(ProjectLabel::class);
    }

    /** @param Builder<static> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_active) {
            $query->whereRaw('1 = 0');
        } elseif (! $user->is_system_admin) {
            $ids = $user->projectMemberships()->where('is_active', true)->pluck('project_id');
            $allowed = static::whereIn('id', $ids)->get()->filter(fn ($project) => $user->hasProjectPermission($project, 'project.view'))->pluck('id');
            $query->whereIn('id', $allowed);
        }
    }
}
