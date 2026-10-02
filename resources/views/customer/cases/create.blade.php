@extends('layouts.minimal')

@section('title', 'Open a Case | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Open a case with {{ $lawyer->display_name }}</h1>
            <p>{{ $lawyer->title ?? '' }} &middot; {{ $lawyer->city }}</p>
        </div>
        <a class="btn outline" href="{{ route('lawyers.show', $lawyer->id) }}"><i class="fa-solid fa-arrow-left"></i> Back to profile</a>
    </div>

    @if ($errors->any())
        <div class="demo-note" style="margin-bottom:18px;border-color:var(--red);color:var(--red);">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="panel" style="padding:22px;max-width:640px;">
        <form method="POST" action="{{ route('customer.cases.store', $lawyer->id) }}">
            @csrf

            <label for="title">Case title</label>
            <input type="text" id="title" name="title" maxlength="150" value="{{ old('title') }}" required>

            <label for="description" style="margin-top:14px;">Description</label>
            <textarea id="description" name="description" rows="6" required>{{ old('description') }}</textarea>

            @if ($lawyer->services->isNotEmpty())
                <label for="service_id" style="margin-top:14px;">Related service (optional)</label>
                <select id="service_id" name="service_id">
                    <option value="">— Not specified —</option>
                    @foreach ($lawyer->services as $service)
                        <option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>
                    @endforeach
                </select>
            @endif

            <button class="btn gold" type="submit" style="margin-top:20px;">
                <i class="fa-solid fa-folder-plus"></i> Submit case
            </button>
        </form>
    </div>
</div>
@endsection
