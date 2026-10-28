<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('issue_counter')->default(0)->after('description');
        });

        Schema::create('columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        // Projects created before columns existed get the defaults.
        foreach (DB::table('projects')->pluck('id') as $projectId) {
            foreach (['To Do', 'In Progress', 'In Review', 'Done'] as $position => $name) {
                DB::table('columns')->insert(['project_id' => $projectId, 'name' => $name, 'position' => $position, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('type', 20);
            $table->string('priority', 20);
            $table->foreignId('column_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('story_points')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('epic_id')->nullable()->constrained('issues')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('issues')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'number']);
            $table->index(['project_id', 'column_id']);
        });

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('color', 7)->default('#6b7280');
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::create('issue_label', function (Blueprint $table) {
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->primary(['issue_id', 'label_id']);
            $table->index('label_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->json('changes')->nullable();
            $table->string('via', 10);
            $table->string('token_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['issue_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('issue_label');
        Schema::dropIfExists('labels');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('columns');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('issue_counter'));
    }
};
