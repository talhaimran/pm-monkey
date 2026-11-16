<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable until existing rows are stamped (next migration); projects become required after that.
        Schema::table('projects', fn (Blueprint $table) => $table->foreignId('workspace_id')->nullable()->after('id')->constrained());
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->foreignId('workspace_id')->nullable()->after('tokenable_id')->constrained()->cascadeOnDelete());
        Schema::table('users', fn (Blueprint $table) => $table->foreignId('current_workspace_id')->nullable()->after('remember_token')->constrained('workspaces')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_workspace_id'));
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropConstrainedForeignId('workspace_id'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropConstrainedForeignId('workspace_id'));
    }
};
