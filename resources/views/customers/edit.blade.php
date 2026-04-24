@extends('layouts.app')

@php
    $countryRows = json_decode(file_get_contents(resource_path('data/countries-raw.json')), true, 512, JSON_THROW_ON_ERROR);
    usort($countryRows, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
    $usStates = config('us_states');
    asort($usStates);
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Edit Customer</h1>
        <p class="mt-1 text-sm text-slate-600">Update customer details.</p>
    </div>

    <section class="max-w-2xl crm-panel p-5">
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-3">
            @csrf
            @method('PUT')
            <div>
                <label class="mb-1 block text-sm font-medium">First name</label>
                <input name="first_name" value="{{ old('first_name', $customer->first_name) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Last name</label>
                <input name="last_name" value="{{ old('last_name', $customer->last_name) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Email</label>
                <input name="email" type="email" value="{{ old('email', $customer->email) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Phone</label>
                <input name="phone" value="{{ old('phone', $customer->phone) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Date of birth</label>
                <input name="date_of_birth" type="date" value="{{ old('date_of_birth', optional($customer->date_of_birth)->format('Y-m-d')) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('date_of_birth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Gender</label>
                <input name="gender" value="{{ old('gender', $customer->gender) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('gender') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-3">
                <p class="text-sm font-semibold text-slate-800">Address</p>
                <div>
                    <label class="mb-1 block text-sm font-medium">Street line 1</label>
                    <input name="address_line1" value="{{ old('address_line1', $customer->address_line1) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" autocomplete="address-line1">
                    @error('address_line1') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Street line 2</label>
                    <input name="address_line2" value="{{ old('address_line2', $customer->address_line2) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" autocomplete="address-line2">
                    @error('address_line2') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">City</label>
                        <input name="city" value="{{ old('city', $customer->city) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" autocomplete="address-level2">
                        @error('city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">State</label>
                        <select name="state_region" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" autocomplete="address-level1">
                            <option value="">—</option>
                            @foreach ($usStates as $code => $name)
                                <option value="{{ $code }}" @selected(old('state_region', $customer->state_region) === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('state_region') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Postal code</label>
                        <input name="postal_code" value="{{ old('postal_code', $customer->postal_code) }}" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" autocomplete="postal-code">
                        @error('postal_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Country</label>
                        <select name="country" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" autocomplete="country-name">
                            <option value="">—</option>
                            @foreach ($countryRows as $row)
                                @php
                                    $code = $row['alpha-2'];
                                    $label = $code === 'US' ? 'United States' : $row['name'];
                                @endphp
                                <option value="{{ $code }}" @selected(old('country', $customer->country) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('country') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Notes</label>
                <textarea name="notes" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $customer->notes) }}</textarea>
                @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3 pt-2">
                <button class="rounded-md bg-pink-600 px-4 py-2 text-sm font-semibold text-white hover:bg-pink-700">Update customer</button>
                <a href="{{ route('customers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </section>
@endsection
