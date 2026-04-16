<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'BeautiSkin CRM' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    @if (session()->has('impersonator_id'))
        <div class="border-b border-amber-200 bg-amber-50 px-6 py-3 text-sm text-amber-950">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
                <p>
                    <span class="font-semibold">Viewing as</span>
                    {{ auth()->user()->name }}
                    <span class="text-amber-800">— you are signed in under this account for debugging.</span>
                </p>
                <form method="POST" action="{{ route('admin.impersonate.leave') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="rounded-md bg-amber-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800">Leave impersonation</button>
                </form>
            </div>
        </div>
    @endif
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ url('/') }}" class="text-lg font-bold">BeautiSkin CRM</a>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-8">
                <nav class="flex flex-wrap items-center gap-4 text-sm font-medium text-slate-600 sm:gap-5">
                    <a href="{{ url('/') }}" class="hover:text-slate-900">Home</a>
                    <a href="{{ route('customers.index') }}" class="hover:text-slate-900">Customers</a>
                    <a href="{{ route('appointments.index') }}" class="hover:text-slate-900">Appointments</a>
                    <a href="{{ route('services.index') }}" class="hover:text-slate-900">Services</a>
                    <a href="{{ route('memberships.index') }}" class="hover:text-slate-900">Memberships</a>
                    @can('access-admin-board')
                        <a href="{{ route('admin.operations.index') }}" class="hover:text-slate-900">Operations</a>
                        <a href="{{ route('admin.reports.index') }}" class="hover:text-slate-900">Reports</a>
                        <a href="{{ route('admin.control-board') }}" class="hover:text-slate-900">Admin</a>
                    @endcan
                </nav>
                <div class="flex items-center gap-3 border-t border-slate-100 pt-3 text-sm sm:border-t-0 sm:pt-0">
                    <span class="text-slate-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    @can('view-experimental-ui')
        <div class="border-b border-violet-200 bg-violet-50 px-6 py-2 text-center text-xs font-medium text-violet-900">
            Experimental UI is on for administrators — you may see additional panels and tools that are not final.
        </div>
    @endcan

    <main class="mx-auto max-w-6xl px-6 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
