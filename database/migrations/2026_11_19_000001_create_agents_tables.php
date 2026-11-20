<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Spec 8 (A1): agents. Each existing workspace token becomes an agent named after it, owned by its user, in its
 * workspace, at its read/write level with its projects; the token string keeps working. Tokens with no workspace
 * (refused since step 0) are left alone (A-L12).
 */
return new class extends Migration
{
    private const LOGGED = ['activities', 'comments', 'audit_logs'];

    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('permission_level', 10);
            $table->string('status', 10)->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'owner_user_id']);
        });

        Schema::create('agent_projects', function (Blueprint $table) {
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->primary(['agent_id', 'project_id']);
        });

        foreach (['personal_access_tokens', ...self::LOGGED] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete());
        }

        DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereNotNull('workspace_id')->whereNull('agent_id')
            ->chunkById(200, fn ($tokens) => DB::transaction(fn () => $tokens->each(fn ($token) => $this->toAgent($token))));
    }

    private function toAgent(object $token): void
    {
        $abilities = json_decode($token->abilities ?? '[]', true) ?: [];
        $scope = collect($abilities)->filter(fn ($a) => str_starts_with($a, 'project:'))->map(fn ($a) => (int) substr($a, 8));
        $projects = $scope->isEmpty() ? collect() : DB::table('projects')->where('workspace_id', $token->workspace_id)->whereIn('id', $scope)->pluck('id');
        // Scoped only to projects that are gone: it reached nothing, and no projects would now mean all of them.
        $orphaned = $scope->isNotEmpty() && $projects->isEmpty();

        $agentId = DB::table('agents')->insertGetId([
            'workspace_id' => $token->workspace_id,
            'owner_user_id' => $token->tokenable_id,
            'name' => Str::limit($token->name, 100, ''),
            'permission_level' => array_intersect(['write', '*'], $abilities) ? 'write' : 'read',
            'status' => $orphaned ? 'revoked' : 'active',
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
            'updated_at' => now(),
        ]);
        DB::table('agent_projects')->insert($projects->map(fn ($id) => ['agent_id' => $agentId, 'project_id' => $id])->all());

        $orphaned
            ? DB::table('personal_access_tokens')->where('id', $token->id)->delete()
            : DB::table('personal_access_tokens')->where('id', $token->id)->update(['agent_id' => $agentId]);
    }

    public function down(): void
    {
        // Tokens go back to step 0 abilities. Step 0 has no pause, so a paused agent's token is deleted, not revived.
        // shortcut: loads every agent row at once; chunk it if a rollback ever has to handle many thousands.
        $agents = DB::table('agents')->get()->keyBy('id');
        $projects = DB::table('agent_projects')->get()->groupBy('agent_id');
        DB::table('personal_access_tokens')->whereNotNull('agent_id')->chunkById(200, function ($tokens) use ($agents, $projects) {
            foreach ($tokens as $token) {
                $agent = $agents[$token->agent_id];
                if ($agent->status !== 'active') {
                    DB::table('personal_access_tokens')->where('id', $token->id)->delete();

                    continue;
                }
                $abilities = [...($agent->permission_level === 'read' ? ['read'] : ['read', 'write']),
                    ...($projects[$agent->id] ?? collect())->map(fn ($p) => "project:{$p->project_id}")];
                DB::table('personal_access_tokens')->where('id', $token->id)->update(['abilities' => json_encode($abilities)]);
            }
        });

        foreach (['personal_access_tokens', ...self::LOGGED] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('agent_id'));
        }
        Schema::drop('agent_projects');
        Schema::drop('agents');
    }
};
