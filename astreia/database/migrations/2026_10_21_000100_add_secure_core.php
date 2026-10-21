<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('process_model', 20)->default('legacy');
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedInteger('lock_version')->default(1);
        });
        foreach (['work_types', 'priorities', 'resolutions'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->foreignId('project_id')->constrained()->restrictOnDelete();
                $table->string('code', $name === 'priorities' ? 10 : 30);
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                if ($name === 'priorities') {
                    $table->unsignedInteger('rank');
                }
                if ($name === 'resolutions') {
                    $table->string('outcome', 20);
                }
                $table->timestamps(6);
                $table->unique(['project_id', 'code']);
                $table->unique(['id', 'project_id']);
            });
        }
        Schema::table('tickets', function (Blueprint $table) {
            foreach (['work_type' => 'work_types', 'priority' => 'priorities', 'resolution' => 'resolutions'] as $field => $catalog) {
                $table->unsignedBigInteger($field.'_id')->nullable();
                $table->foreign([$field.'_id', 'project_id'], 'ticket_'.$field.'_catalog_fk')->references(['id', 'project_id'])->on($catalog)->restrictOnDelete();
            }
        });
        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->uuid('correlation_id')->nullable();
            $table->dateTime('occurred_at', 6)->nullable();
            $table->dateTime('recorded_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_activities', fn (Blueprint $table) => $table->dropColumn(['correlation_id', 'occurred_at', 'recorded_at']));
        Schema::table('tickets', function (Blueprint $table) {
            foreach (['work_type', 'priority', 'resolution'] as $field) {
                $table->dropForeign('ticket_'.$field.'_catalog_fk');
                $table->dropColumn($field.'_id');
            }
        });
        foreach (['resolutions', 'priorities', 'work_types'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['process_model', 'timezone', 'lock_version']));
    }
};
