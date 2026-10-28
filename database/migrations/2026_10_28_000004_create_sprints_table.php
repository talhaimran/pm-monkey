<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('goal')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('state', 20)->default('planned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            // Backstop for "one active sprint per project": set to project_id only while active (NULLs never collide).
            // A plain column because MySQL forbids cascading FKs on base columns of generated columns.
            $table->unsignedBigInteger('active_project_id')->nullable();
            $table->unique('active_project_id');
            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'state']);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('sprint_id')->nullable()->after('parent_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->dropConstrainedForeignId('sprint_id'));
        Schema::dropIfExists('sprints');
    }
};
