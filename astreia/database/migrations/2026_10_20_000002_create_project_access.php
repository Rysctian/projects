<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('archived_at', 6)->nullable();
            $table->unsignedBigInteger('current_workflow_id')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        Schema::table('audit_logs', fn (Blueprint $table) => $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete());
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('status')->default('active');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['project_id', 'code']);
            $table->unique(['id', 'project_id']);
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('description');
            $table->string('scope')->default('project');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['project_id', 'user_id']);
            $table->unique(['id', 'project_id']);
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['project_id', 'name']);
            $table->unique(['id', 'project_id']);
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('project_member_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('project_member_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('project_id');
            $table->primary(['project_member_id', 'role_id']);
            $table->foreign(['project_member_id', 'project_id'], 'pmr_member_fk')->references(['id', 'project_id'])->on('project_members')->restrictOnDelete();
            $table->foreign(['role_id', 'project_id'], 'pmr_role_fk')->references(['id', 'project_id'])->on('roles')->restrictOnDelete();
        });
        Schema::create('project_member_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_member_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->string('effect', 5);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['project_member_id', 'permission_id'], 'member_override_unique');
        });
    }

    public function down(): void
    {
        foreach (['project_member_permission_overrides', 'project_member_roles', 'role_permissions', 'roles', 'project_members', 'permissions', 'modules'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropForeign(['project_id']));
        Schema::dropIfExists('projects');
    }
};
