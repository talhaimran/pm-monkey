<?php

use App\Enums\WorkspaceRole;
use App\Mail\ProjectInvitationMail;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Mail;

/** Step 0 PR 8 scan, F7/F12: names in the invitation email are text, never Markdown links or images. */
test('workspace and inviter names cannot inject links or images into the invitation email', function () {
    Mail::fake();
    $owner = User::factory()->create(['name' => '![x](https://tracker.example/p.png)']);
    $ws = makeWorkspace($owner, 'acme');
    $ws->forceFill(['name' => '[Confirm your account](https://evil.example/login)'])->save();

    app(InvitationService::class)->invite($ws, $owner, 'victim@example.com', WorkspaceRole::Member);

    Mail::assertSent(ProjectInvitationMail::class, function (ProjectInvitationMail $mail) {
        $html = $mail->render();
        expect($html)->not->toContain('href="https://evil.example/login"')
            ->not->toContain('src="https://tracker.example/p.png"')
            ->toContain('Confirm your account');

        return true;
    });
});
