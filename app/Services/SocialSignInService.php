<?php

namespace App\Services;

use App\Http\Controllers\Web\WorkspaceInvitationController;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Spec 7.0f: Google and GitHub sign-in. An account links to a user only through a verified provider email that
 * matches; new people follow the sign-up rules of 7.0a. Provider tokens are never stored or logged.
 */
class SocialSignInService
{
    public const PROVIDERS = ['google' => 'Google', 'github' => 'GitHub'];

    public function __construct(private WorkspaceService $workspaces, private InvitationService $invitations, private AuditLogger $audit) {}

    /** @return list<string> providers with a client id and secret set */
    public function configured(): array
    {
        return array_values(array_filter(array_keys(self::PROVIDERS), fn ($p) => filled(config("services.{$p}.client_id")) && filled(config("services.{$p}.client_secret"))));
    }

    /**
     * The user this provider account signs in as: already linked, linked now by a verified matching email, or new.
     *
     * @return array{0: User, 1: ?string} the user and, for a new user, where to land
     */
    public function signIn(string $provider, SocialiteUser $profile, ?string $invitationToken = null): array
    {
        $linked = SocialAccount::with('user')->where(['provider' => $provider, 'provider_user_id' => (string) $profile->getId()])->first();
        if ($linked) {
            return [$linked->user, null];
        }

        $email = $this->verifiedEmail($provider, $profile);
        if ($user = User::firstWhere('email', $email)) {
            $this->attach($user, $provider, $profile, $email);

            return [$user, null];
        }

        return $this->createUser($provider, $profile, $email, $invitationToken);
    }

    /** Signed in and linking from the profile: the provider's verified email must be the user's. */
    public function link(User $user, string $provider, SocialiteUser $profile): void
    {
        $name = self::PROVIDERS[$provider];
        $taken = SocialAccount::where(['provider' => $provider, 'provider_user_id' => (string) $profile->getId()])->value('user_id');
        if ($taken === $user->id) {
            return;
        }
        if ($taken) {
            throw ValidationException::withMessages(['social' => "That {$name} account is linked to another person. Unlink it there first."]);
        }
        $email = $this->verifiedEmail($provider, $profile, 'social');
        if ($email !== strtolower($user->email)) {
            throw ValidationException::withMessages(['social' => "That {$name} account’s verified email is {$email}, not {$user->email}. Sign in to {$name} with {$user->email} to link it."]);
        }
        $this->attach($user, $provider, $profile, $email);
    }

    /** Never the last way in: a password, or another linked provider, must remain. */
    public function unlink(User $user, string $provider): void
    {
        $account = $user->socialAccounts()->where('provider', $provider)->first();
        if (! $account) {
            return;
        }
        if (! $user->hasPassword() && $user->socialAccounts()->count() === 1) {
            $name = self::PROVIDERS[$provider];
            $other = self::PROVIDERS[$provider === 'google' ? 'github' : 'google'];
            throw ValidationException::withMessages(['social' => "{$name} is your only way to sign in. Set a password or link {$other} before unlinking it."]);
        }
        $account->delete();
        $this->audit->forEveryWorkspaceOf($user, 'auth.social_unlinked', ['provider' => $provider]);
    }

    /** "a password only", "a password or Google", "Google or GitHub": how a workspace's sign-in methods read. */
    public static function describe(array $methods): string
    {
        $names = array_map(fn ($m) => $m === 'password' ? 'a password' : (self::PROVIDERS[$m] ?? $m), array_values($methods));

        return count($names) === 1 ? "{$names[0]} only" : implode(', ', array_slice($names, 0, -1)).' or '.end($names);
    }

    /** Google flags the email; Socialite gives GitHub's email only when it is the primary verified one. */
    private function verifiedEmail(string $provider, SocialiteUser $profile, string $field = 'email'): string
    {
        $email = strtolower(trim((string) $profile->getEmail()));
        // Only a real true counts: (bool) "false" would be true.
        $verified = $provider === 'google'
            ? filter_var($profile->getRaw()['email_verified'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true
            : $email !== '';

        if ($email === '' || ! $verified) {
            throw ValidationException::withMessages([$field => $provider === 'github'
                ? 'Your GitHub account has no verified primary email address, so it can’t be used to sign in. Verify one on GitHub, or sign in with your password.'
                : 'Your Google email address isn’t verified, so it can’t be used to sign in. Verify it with Google, or sign in with your password.']);
        }

        return $email;
    }

    private function attach(User $user, string $provider, SocialiteUser $profile, string $email): void
    {
        $user->socialAccounts()->create(['provider' => $provider, 'provider_user_id' => (string) $profile->getId(), 'email' => $email]);
        $this->audit->forEveryWorkspaceOf($user, 'auth.social_linked', ['provider' => $provider, 'email' => $email]);
    }

    /** 7.0a's rules: an invitation to this address, else Cloud or the very first self-hosted user creates a workspace. */
    private function createUser(string $provider, SocialiteUser $profile, string $email, ?string $invitationToken): array
    {
        $invitation = $invitationToken ? $this->invitations->findByToken($invitationToken) : null;
        $invited = $invitation && ! $this->invitations->refusal($invitation, null) && $invitation->email === $email;
        if (! $invited && ! $this->workspaces->canCreate()) {
            throw ValidationException::withMessages(['email' => 'Ask an admin of '.Workspace::value('name').' to invite you.']);
        }

        return DB::transaction(function () use ($provider, $profile, $email, $invited, $invitation) {
            $name = trim((string) ($profile->getName() ?: $profile->getNickname())) ?: Str::before($email, '@');
            // No password: they sign in with the provider, or set one later through "Forgot password".
            $user = User::forceCreate(['name' => $name, 'email' => $email, 'password' => null, 'email_verified_at' => now()]);

            if ($invited) {
                $landing = WorkspaceInvitationController::landing($this->invitations->accept($invitation, $user), $invitation->project);
            } else {
                config('pm.edition') === 'cloud'
                    ? $this->workspaces->create($user, Str::before($name, ' ')."'s workspace")
                    : $this->workspaces->create($user, config('app.name') ?: 'Workspace');
                $landing = '/dashboard';
            }
            // After joining, so auth.social_linked lands in the workspace.
            $this->attach($user, $provider, $profile, $email);

            return [$user, $landing];
        });
    }
}
