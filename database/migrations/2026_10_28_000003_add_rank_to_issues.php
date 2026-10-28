<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->bigInteger('rank')->default(0)->after('parent_id');
            $table->index(['project_id', 'rank']);
        });

        // Existing issues keep creation order.
        DB::table('issues')->update(['rank' => DB::raw('id * 1024')]);
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'rank']);
            $table->dropColumn('rank');
        });
    }
};
