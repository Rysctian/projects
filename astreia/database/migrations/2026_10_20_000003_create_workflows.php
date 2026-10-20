<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('state')->default('draft');
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['project_id', 'version_number']);
            $table->unique(['id', 'project_id']);
        });
        Schema::table('projects', fn (Blueprint $table) => $table->foreign('current_workflow_id')->references('id')->on('project_workflows')->restrictOnDelete());
        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('project_workflows')->restrictOnDelete();
            $table->string('key', 60);
            $table->string('name', 100);
            $table->string('type', 30);
            $table->unsignedInteger('sort_order');
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['workflow_id', 'key']);
            $table->unique(['id', 'workflow_id']);
        });
        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('project_workflows')->restrictOnDelete();
            $table->unsignedBigInteger('from_stage_id');
            $table->unsignedBigInteger('to_stage_id');
            $table->foreignId('required_permission_id')->nullable()->constrained('permissions')->restrictOnDelete();
            $table->boolean('requires_reason')->default(false);
            $table->boolean('requires_signoff')->default(false);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['workflow_id', 'from_stage_id', 'to_stage_id'], 'workflow_transition_unique');
            $table->foreign(['from_stage_id', 'workflow_id'], 'transition_from_fk')->references(['id', 'workflow_id'])->on('workflow_stages')->restrictOnDelete();
            $table->foreign(['to_stage_id', 'workflow_id'], 'transition_to_fk')->references(['id', 'workflow_id'])->on('workflow_stages')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_stages');
        Schema::table('projects', fn (Blueprint $table) => $table->dropForeign(['current_workflow_id']));
        Schema::dropIfExists('project_workflows');
    }
};
