<?php

use App\Services\WorkspaceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Spec 7.0a: an existing install becomes one workspace named after APP_NAME. Lowest user id is Owner,
 * anyone who is Admin of a project is Admin, everyone else Member. Projects, tokens and users are stamped;
 * pending project invitations move to workspace_invitations. A fresh install (no users, no projects) gets
 * no workspace: the first person to register creates it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $userIds = DB::table('users')->orderBy('id')->pluck('id');

            if ($userIds->isEmpty() && ! DB::table('projects')->exists()) {
                return;
            }

            $now = now();
            $name = trim((string) config('app.name')) ?: 'Workspace';
            $workspaceId = DB::table('workspaces')->insertGetId([
                'name' => Str::limit($name, 100, ''),
                'slug' => app(WorkspaceService::class)->slugFor($name),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $admins = DB::table('project_user')->where('role', 'admin')->distinct()->pluck('user_id')->flip();
            DB::table('workspace_user')->insert($userIds->map(fn ($id, $i) => [
                'workspace_id' => $workspaceId,
                'user_id' => $id,
                'role' => $i === 0 ? 'owner' : ($admins->has($id) ? 'admin' : 'member'),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            DB::table('projects')->update(['workspace_id' => $workspaceId]);
            DB::table('personal_access_tokens')->update(['workspace_id' => $workspaceId]);
            DB::table('users')->update(['current_workspace_id' => $workspaceId]);

            // The new table holds one invitation per workspace and email: the newest one wins (decided 2026-11-16).
            $pending = DB::table('project_invitations')->orderByDesc('updated_at')->orderByDesc('id')->get()
                ->unique(fn ($i) => strtolower($i->email));
            DB::table('workspace_invitations')->insert($pending->map(fn ($i) => [
                'workspace_id' => $workspaceId,
                'email' => strtolower($i->email),
                'role' => 'member',
                'project_id' => $i->project_id,
                'project_role' => $i->role,
                'invited_by' => $i->invited_by,
                // Nobody holds this token: the invitee accepts while signed in, or an admin resends a new one.
                'token' => hash('sha256', Str::random(40)),
                'expires_at' => $now->copy()->addDays(14),
                'created_at' => $i->created_at,
                'updated_at' => $i->updated_at,
            ])->values()->all());

            if ($dropped = DB::table('project_invitations')->count() - $pending->count()) {
                Log::warning("Workspace migration: dropped {$dropped} older invitation(s) for emails invited to more than one project.");
            }
        });

        Schema::drop('project_invitations');
    }

    public function down(): void
    {
        Schema::create('project_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20);
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'email']);
            $table->index('email');
        });

        DB::transaction(function () {
            foreach (DB::table('workspace_invitations')->whereNotNull('project_id')->get() as $i) {
                DB::table('project_invitations')->insert(['project_id' => $i->project_id, 'email' => $i->email, 'role' => $i->project_role, 'invited_by' => $i->invited_by, 'created_at' => $i->created_at, 'updated_at' => $i->updated_at]);
            }
            DB::table('users')->update(['current_workspace_id' => null]);
            DB::table('personal_access_tokens')->update(['workspace_id' => null]);
            DB::table('projects')->update(['workspace_id' => null]);
            DB::table('workspace_invitations')->delete();
            DB::table('workspace_user')->delete();
            DB::table('workspaces')->delete();
        });
    }
};
