<?php

namespace App\Models;

use Database\Factories\ProjectMemberPermissionOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMemberPermissionOverride extends Model
{
    /** @use HasFactory<ProjectMemberPermissionOverrideFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @return BelongsTo<Permission, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
