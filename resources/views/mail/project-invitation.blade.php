<x-mail::message>
# Join {{ $invitation->workspace->name }}

@if ($invitation->project)
{{ $invitation->inviter->name }} invited you to the project **{{ $invitation->project->name }} ({{ $invitation->project->key }})** as **{{ $invitation->project_role->value }}**.
@else
{{ $invitation->inviter->name }} invited you to join as **{{ $invitation->role->value }}**.
@endif

Open the link below and sign in or register with **{{ $invitation->email }}**. The link expires in 14 days.

<x-mail::button :url="$url">
Accept the invitation
</x-mail::button>
</x-mail::message>
