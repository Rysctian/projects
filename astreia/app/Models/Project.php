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

    protected $attributes = ['status' => 'active', 'process_model' => 'legacy', 'timezone' => 'UTC', 'lock_version' => 1];

    public function isCore(): bool
    {
        return $this->process_model === 'core';
    }

    /** @return HasMany<WorkType, $this> */
    public function workTypes(): HasMany
    {
        return $this->hasMany(WorkType::class);
    }

    /** @return HasMany<Priority, $this> */
    public function priorities(): HasMany
    {
        return $this->hasMany(Priority::class);
    }

    /** @return HasMany<Resolution, $this> */
    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class);
    }

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
        } else {
            $ids = $user->projectMemberships()->where('is_active', true)->pluck('project_id');
            $allowed = static::whereIn('id', $ids)->get()->filter(fn ($project) => $user->hasProjectPermission($project, 'project.view'))->pluck('id');
            $query->where(fn ($q) => $q->whereIn('id', $allowed)->when($user->is_system_admin, fn ($q) => $q->orWhere('process_model', 'legacy')));
        }
    }
}
