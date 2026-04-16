@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Edit Customer</h1>
        <p class="mt-1 text-sm text-slate-600">Update customer details.</p>
    </div>

    <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-5">
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
