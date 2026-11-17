<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 7.0c: per-workspace limits; no row means unlimited. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('value');
            $table->string('source', 16);
            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_entitlements');
    }
};
