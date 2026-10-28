<x-mail::message>
# Join {{ $invitation->project->name }} ({{ $invitation->project->key }})

{{ $invitation->inviter->name }} invited you to join as **{{ $invitation->role->value }}**.

Sign in or register with **{{ $invitation->email }}**, then accept the invitation on your Projects page.

<x-mail::button :url="$url">
Open Projects
</x-mail::button>
</x-mail::message>
