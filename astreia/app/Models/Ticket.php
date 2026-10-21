<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|null $due_date
 * @property list<string>|null $acceptance_criteria
 * @property int|null $work_type_id
 * @property int|null $priority_id
 * @property int|null $resolution_id
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    public const CATEGORIES = ['new_feature', 'enhancement', 'bug_fix', 'documentation', 'support'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const DEVELOPMENT_CATEGORIES = ['new_feature', 'enhancement', 'bug_fix'];

    protected $guarded = ['id'];

    protected $appends = ['key', 'needs_reassignment'];

    public function getNeedsReassignmentAttribute(): bool
    {
        return $this->closed_at === null && $this->assigned_to !== null
            && (! $this->assignee || ! $this->assignee->is_active || ! $this->assignee->hasProjectPermission($this->project, 'tickets.work'));
    }

    /** @return BelongsTo<Resolution, $this> */
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class);
    }

    protected $attributes = ['revision_number' => 1, 'lock_version' => 1, 'stage_visit' => 1, 'verification_cycle' => 1, 'is_blocked' => false, 'is_cancelled' => false, 'is_intake_stub' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['acceptance_criteria' => 'array', 'is_intake_stub' => 'boolean', 'is_blocked' => 'boolean', 'is_cancelled' => 'boolean', 'closed_at' => 'immutable_datetime', 'due_date' => 'immutable_date:Y-m-d'];
    }

    public function getKeyAttribute(): string
    {
        return $this->project->code.'-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ProjectWorkflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkflow::class, 'workflow_id');
    }

    /** @return BelongsTo<WorkflowStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /** @return HasMany<TicketActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    /** @return HasMany<TicketStageSignoff, $this> */
    public function signoffs(): HasMany
    {
        return $this->hasMany(TicketStageSignoff::class);
    }

    /** @return HasMany<TicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /** @return BelongsToMany<ProjectLabel, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ProjectLabel::class, 'ticket_labels')->withPivot('project_id');
    }
}
