<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_ticket_counters', function (Blueprint $table) {
            $table->foreignId('project_id')->primary()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
        });
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('number');
            $table->unsignedBigInteger('module_id')->nullable();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('stage_id');
            $table->unsignedInteger('revision_number')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->unsignedInteger('stage_visit')->default(1);
            $table->unsignedInteger('verification_cycle')->default(1);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('category', 30);
            $table->string('priority', 10)->default('normal');
            $table->boolean('is_intake_stub')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->date('due_date')->nullable();
            $table->json('acceptance_criteria')->nullable();
            $table->text('completion_notes')->nullable();
            $table->string('documentation_impact', 20)->nullable();
            $table->text('documentation_impact_note')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->boolean('is_cancelled')->default(false);
            $table->unsignedBigInteger('blocked_from_stage_id')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('closed_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['project_id', 'number']);
            $table->unique(['id', 'project_id']);
            $table->index(['project_id', 'stage_id']);
            $table->index(['project_id', 'assigned_to']);
            $table->index(['project_id', 'due_date']);
            $table->foreign(['module_id', 'project_id'], 'ticket_module_fk')->references(['id', 'project_id'])->on('modules')->restrictOnDelete();
            $table->foreign(['workflow_id', 'project_id'], 'ticket_workflow_fk')->references(['id', 'project_id'])->on('project_workflows')->restrictOnDelete();
            $table->foreign(['stage_id', 'workflow_id'], 'ticket_stage_fk')->references(['id', 'workflow_id'])->on('workflow_stages')->restrictOnDelete();
            $table->foreign(['blocked_from_stage_id', 'workflow_id'], 'ticket_blocked_stage_fk')->references(['id', 'workflow_id'])->on('workflow_stages')->restrictOnDelete();
            $table->foreign(['project_id', 'assigned_to'], 'ticket_assignee_fk')->references(['project_id', 'user_id'])->on('project_members')->restrictOnDelete();
        });
        Schema::create('ticket_stage_signoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('workflow_stages')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('result', 10);
            $table->text('note')->nullable();
            $table->unsignedInteger('verified_revision');
            $table->unsignedInteger('stage_visit');
            $table->unsignedInteger('verification_cycle');
            $table->dateTime('created_at', 6);
            $table->index(['ticket_id', 'verified_revision']);
        });
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('activity_type');
            $table->json('payload');
            $table->dateTime('created_at', 6);
            $table->index(['ticket_id', 'created_at']);
        });
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        Schema::create('project_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['project_id', 'name']);
            $table->unique(['id', 'project_id']);
        });
        Schema::create('ticket_labels', function (Blueprint $table) {
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('project_label_id');
            $table->unsignedBigInteger('project_id');
            $table->primary(['ticket_id', 'project_label_id']);
            $table->foreign(['ticket_id', 'project_id'], 'label_ticket_fk')->references(['id', 'project_id'])->on('tickets')->restrictOnDelete();
            $table->foreign(['project_label_id', 'project_id'], 'label_project_fk')->references(['id', 'project_id'])->on('project_labels')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['ticket_labels', 'project_labels', 'ticket_attachments', 'ticket_activities', 'ticket_comments', 'ticket_stage_signoffs', 'tickets', 'project_ticket_counters'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
