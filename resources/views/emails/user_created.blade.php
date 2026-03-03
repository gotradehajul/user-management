Hello {{ $user->name }},

Your account has been created successfully.

Email: {{ $user->email }}
Created at: {{ $user->created_at?->toDateTimeString() }}
