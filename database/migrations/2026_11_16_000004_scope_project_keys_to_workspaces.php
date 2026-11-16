<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable(false)->change();
            $table->dropUnique(['key']);
            $table->unique(['workspace_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unique('key');
            // The composite index also serves the workspace_id foreign key, so give the key its own index first.
            $table->index('workspace_id');
            $table->dropUnique(['workspace_id', 'key']);
            $table->foreignId('workspace_id')->nullable()->change();
        });
    }
};
