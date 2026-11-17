<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 7.0f: Google and GitHub accounts linked to users; provider is a plain string so others can follow without a
 * migration. No provider tokens are kept. users.password becomes nullable (owner's decision, PR 7): someone who signs up
 * with a provider has no password until they set one by email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id');
            $table->string('email');
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });

        Schema::table('users', fn (Blueprint $table) => $table->string('password')->nullable()->change());
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
        // Accounts made with a provider have no password; give them an unusable one so the column can be required again.
        DB::table('users')->whereNull('password')->update(['password' => '!']);
        Schema::table('users', fn (Blueprint $table) => $table->string('password')->nullable(false)->change());
    }
};
