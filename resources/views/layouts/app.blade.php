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
@php
    $navActive = function (string ...$patterns): string {
        foreach ($patterns as $p) {
            if (request()->routeIs($p)) {
                return 'bg-pink-600 text-white shadow-sm ring-1 ring-pink-700/20';
            }
        }

        return 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
    };
@endphp
<body class="min-h-screen bg-gradient-to-b from-slate-200 via-slate-100 to-slate-200 text-slate-900 antialiased">
    @if (session()->has('impersonator_id'))
        <div class="border-b border-amber-300/80 bg-amber-100 px-6 py-3 text-sm text-amber-950 shadow-sm">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
                <p>
                    <span class="font-semibold">Viewing as</span>
                    {{ auth()->user()->name }}
                    <span class="text-amber-900/90">— you are signed in under this account for debugging.</span>
                </p>
                <form method="POST" action="{{ route('admin.impersonate.leave') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="rounded-lg bg-amber-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-900">
                        Leave impersonation
                    </button>
                </form>
            </div>
        </div>
    @endif
    <header class="sticky top-0 z-40 border-b border-slate-300/90 bg-white/95 shadow-sm shadow-slate-900/5 backdrop-blur-md">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-3 py-2 sm:px-5 lg:flex-row lg:items-center lg:justify-between lg:gap-4 lg:py-2.5">
            <div class="flex items-center gap-2">
                <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/', 'home') }}" class="text-base font-bold tracking-tight text-slate-900 hover:text-pink-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pink-600">
                    BeautiSkin CRM
                </a>
                <span class="hidden h-4 w-px bg-slate-300 sm:block" aria-hidden="true"></span>
                <span class="hidden text-[11px] font-medium uppercase tracking-wide text-slate-500 sm:block">Clinic workspace</span>
            </div>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:gap-5">
                <nav class="flex flex-col gap-1.5 text-xs font-medium lg:min-w-0 lg:flex-row lg:flex-wrap lg:items-center lg:gap-1" aria-label="Main navigation">
                    <div class="flex flex-wrap items-center gap-1">
                        <span class="mr-0.5 hidden text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:inline">Daily</span>
                        <div class="flex flex-wrap items-center gap-1 ml-4">
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/customers', 'customers.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('customers.*') }}">Customers</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/tasks', 'tasks.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('tasks.*') }}">Tasks</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/activity', 'activity.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('activity.index') }}">Activity</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/appointments', 'appointments.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('appointments.*') }}">Appointments</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/leads', 'leads.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('leads.*') }}">Leads</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/sales/pipeline', 'sales.pipeline.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('sales.pipeline.*', 'sales.opportunities.*') }}">Pipeline</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/services', 'services.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('services.*') }}">Services</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/inventory', 'inventory.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('inventory.*') }}">Inventory</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/packages', 'packages.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('packages.*') }}">Packages</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/quotes', 'quotes.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('quotes.*', 'quote-lines.*') }}">Quotes</a>
                            <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/memberships', 'memberships.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('memberships.*') }}">Memberships</a>
                            @can('view-sales')
                                <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/sales', 'sales.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('sales.*') }}">Sales</a>
                            @endcan
                        </div>
                    
                    </div>
                    @can('access-admin-board')
                        <div class="flex flex-wrap items-center gap-1 border-t border-slate-200 pt-2 lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0">
                            <span class="mr-0.5 hidden text-[10px] font-bold uppercase tracking-wider text-slate-400 lg:inline ml-4">Admin</span>
                            <div class="flex flex-wrap items-center gap-1 ml-4">
                                <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/admin/operations', 'admin.operations.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('admin.operations.*') }}">Operations</a>
                                <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/admin/reports', 'admin.reports.index') }}" class="rounded-md px-2 py-1.5 {{ $navActive('admin.reports.*') }}">Reports</a>
                                <a href="{{ \App\Support\FrontendAppUrl::toSpaOrRoute('/admin/control-board', 'admin.control-board') }}" class="rounded-md px-2 py-1.5 {{ $navActive('admin.control-board') }}">Control board</a>
                            </div>
                        </div>
                    @endcan
                </nav>
                @php
                    $authUser = auth()->user();
                @endphp
                <div class="flex w-full min-w-0 flex-col gap-1.5 border-t border-slate-200 pt-2 text-left sm:max-w-[15rem] sm:self-end sm:text-right lg:w-auto lg:items-end lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0 lg:text-right">
                    <div class="min-w-0 ml-4">
                        <p class="truncate text-xs font-semibold text-slate-900" title="{{ $authUser->name }}">{{ $authUser->name }}</p>
                        <p class="truncate text-[11px] text-slate-500" title="{{ $authUser->email }}">{{ $authUser->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="inline-block shrink-0 sm:self-end lg:self-end ml-4">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-800 shadow-sm transition hover:border-pink-300 hover:bg-pink-50 hover:text-pink-900 active:bg-pink-100/70 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pink-500"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    @can('view-experimental-ui')
        <div class="border-b border-violet-300/80 bg-violet-100 px-6 py-2.5 text-center text-xs font-medium text-violet-950 shadow-sm">
            Experimental UI is on for administrators — you may see additional panels and tools that are not final.
        </div>
    @endcan

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        @if (session('status'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-300/80 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm" role="status">
                <span class="mt-0.5 inline-block size-2 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-rose-300/80 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert">
                <span class="mt-0.5 inline-block size-2 shrink-0 rounded-full bg-rose-500" aria-hidden="true"></span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
