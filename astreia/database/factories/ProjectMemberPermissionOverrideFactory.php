<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\ProjectMember;
use App\Models\ProjectMemberPermissionOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectMemberPermissionOverride> */
class ProjectMemberPermissionOverrideFactory extends Factory
{
    public function definition(): array
    {
        return ['project_member_id' => ProjectMember::factory(), 'permission_id' => Permission::factory(), 'effect' => 'allow'];
    }
}
