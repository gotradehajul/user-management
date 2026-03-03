New user registered.

Name: {{ $user->name }}
Email: {{ $user->email }}
Created at: {{ $user->created_at?->toDateTimeString() }}
