@extends('layouts.site')

@section('title', 'Match with a Lawyer | Legal Connect')

@section('site-content')
@php
    $meta = $result?->meta();
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $strict = (array) ($filters['strict'] ?? []);
@endphp
<section class="view active">
<div class="page-top">
<div class="container">
<div class="breadcrumb"><a href="{{ route('home') }}">Home</a><i class="fa-solid fa-chevron-right"></i><a href="{{ route('lawyers.index') }}">Find Lawyers</a><i class="fa-solid fa-chevron-right"></i><span>Match</span></div>
<h1>Match with the right lawyer.</h1>
<p>Describe your situation and preferences. Approved, active lawyers are ranked with a transparent 0-100 score computed from the details you give &mdash; every point is explained.</p>
</div>
</div>

<div class="container search-layout">
<aside class="filter-panel" id="filterPanel">
<h3>Your requirements</h3>
<form method="GET" action="{{ route('lawyers.match') }}">
<div class="filter-group">
<label for="problem">Legal problem</label>
<input id="problem" name="problem" maxlength="{{ config('lawyer_matching.problem.max_length') }}" value="{{ $filters['problem'] ?? '' }}" placeholder="e.g. divorce, property dispute, tax notice">
</div>
<div class="filter-group">
<label for="service">Service</label>
<select id="service" name="service">
<option value="">Any service</option>
@foreach ($services as $service)
<option value="{{ $service->id }}" {{ (string) ($filters['service'] ?? '') === (string) $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
@endforeach
</select>
</div>
<div class="filter-group">
<label for="specialization">Specialization</label>
<select id="specialization" name="specialization">
<option value="">Any specialization</option>
@foreach ($specializations as $specialization)
<option value="{{ $specialization->id }}" {{ (string) ($filters['specialization'] ?? '') === (string) $specialization->id ? 'selected' : '' }}>{{ $specialization->name }}</option>
@endforeach
</select>
</div>
<div class="filter-group">
<label for="city">City</label>
<select id="city" name="city">
<option value="">Any city</option>
@foreach ($cities as $city)
<option value="{{ $city }}" {{ ($filters['city'] ?? '') === $city ? 'selected' : '' }}>{{ $city }}</option>
@endforeach
</select>
</div>
<div class="filter-group">
<label for="min_budget">Minimum budget (Rs.)</label>
<input id="min_budget" name="min_budget" type="number" min="0" step="100" value="{{ $filters['min_budget'] ?? '' }}">
</div>
<div class="filter-group">
<label for="max_budget">Maximum budget (Rs.)</label>
<input id="max_budget" name="max_budget" type="number" min="0" step="100" value="{{ $filters['max_budget'] ?? '' }}">
</div>
<div class="filter-group">
<label for="min_experience">Minimum experience (years)</label>
<input id="min_experience" name="min_experience" type="number" min="0" max="70" value="{{ $filters['min_experience'] ?? '' }}">
</div>
<div class="filter-group">
<label for="available_day">Available on</label>
<select id="available_day" name="available_day">
<option value="">Any day</option>
@foreach ($days as $index => $day)
<option value="{{ $index }}" {{ (string) ($filters['available_day'] ?? '') === (string) $index ? 'selected' : '' }}>{{ $day }}</option>
@endforeach
</select>
</div>
<div class="filter-group">
<label>Must-have (hide lawyers who don't meet it)</label>
<label style="display:block;font-weight:400;"><input type="checkbox" name="strict[]" value="budget" {{ in_array('budget', $strict, true) ? 'checked' : '' }}> Within my budget</label>
<label style="display:block;font-weight:400;"><input type="checkbox" name="strict[]" value="location" {{ in_array('location', $strict, true) ? 'checked' : '' }}> In my city</label>
<label style="display:block;font-weight:400;"><input type="checkbox" name="strict[]" value="experience" {{ in_array('experience', $strict, true) ? 'checked' : '' }}> Meets my minimum experience</label>
</div>
@if ($errors->any())
<div class="filter-group" style="color:#c0392b;font-size:12px;">
@foreach ($errors->all() as $error)
<div>{{ $error }}</div>
@endforeach
</div>
@endif
<button class="btn small full" type="submit">Find matches</button>
<a class="btn outline small full" style="margin-top:8px;" href="{{ route('lawyers.match') }}">Clear</a>
</form>
</aside>

<div>
@if ($result === null)
<div class="empty">
<i class="fa-solid fa-wand-magic-sparkles"></i>
<h3>Tell us what you need</h3>
<p>Enter at least one requirement on the left &mdash; a legal problem, service, city, budget or experience level &mdash; to see ranked matches.</p>
</div>
@else
<div class="search-toolbar">
<div class="results-number">{{ $result->matches()->count() }} {{ $result->matches()->count() === 1 ? 'match' : 'matches' }} &middot; ranked by score (0-100)</div>
</div>

@if ($meta['problem'] !== null)
<p style="color:var(--muted);font-size:13px;margin:0 0 14px;">
@if (! $meta['problem']['used'])
Your description didn't contain anything we could match, so it was not used for ranking.
@elseif ($meta['problem']['catalogue_matched'])
Read from your description (simple keyword matching, not AI): {{ implode(', ', array_merge($meta['problem']['services'], $meta['problem']['specializations'])) }}.
@else
Your description didn't match a known legal area, so lawyer profiles were searched for its words instead.
@endif
</p>
@endif

<div class="result-list">
@forelse ($result->matches() as $match)
@php $lawyer = $match->lawyer; @endphp
<article class="lawyer-result">
@if ($lawyer->profile_image)
<img class="result-img" src="{{ asset('storage/'.$lawyer->profile_image) }}" alt="Portrait of {{ $lawyer->display_name }}" loading="lazy">
@else
<div class="result-img" style="display:flex;align-items:center;justify-content:center;background:var(--canvas);color:var(--muted);"><i class="fa-solid fa-user" style="font-size:28px;"></i></div>
@endif
<div>
<div class="result-title">
<h3>{{ $lawyer->display_name }}</h3>
<span class="verify"><i class="fa-solid fa-circle-check"></i> MATCH {{ $match->score }}/100</span>
</div>
<p class="result-description">{{ $lawyer->title }}</p>
<div class="tag-row">
<span class="tag"><i class="fa-solid fa-location-dot"></i> {{ $lawyer->city }}</span>
<span class="tag">{{ $lawyer->years_of_experience }} years</span>
@foreach ($lawyer->services->take(3) as $service)
<span class="tag">{{ $service->name }}</span>
@endforeach
@foreach ($lawyer->specializations->take(3) as $specialization)
<span class="tag">{{ $specialization->name }}</span>
@endforeach
</div>
@if ($match->reasons)
<ul style="margin:10px 0 0;padding-left:18px;font-size:13px;">
@foreach ($match->reasons as $reason)
<li>{{ $reason }}</li>
@endforeach
</ul>
@endif
@if ($match->gaps)
<ul style="margin:6px 0 0;padding-left:18px;font-size:12px;color:var(--muted);">
@foreach ($match->gaps as $gap)
<li>{{ $gap }}</li>
@endforeach
</ul>
@endif
</div>
<div class="result-aside">
<div>
<strong class="fee">{{ $lawyer->consultation_fee !== null ? 'Rs. '.number_format((float) $lawyer->consultation_fee, 0) : 'Fee not listed' }}</strong>
<span style="color:var(--muted);font-size:11px">consultation</span>
</div>
<div class="result-buttons">
<a class="btn outline small" href="{{ route('lawyers.show', $lawyer->id) }}">Profile</a>
</div>
</div>
</article>
@empty
<div class="empty">
<i class="fa-solid fa-magnifying-glass"></i>
<h3>No matching lawyers</h3>
<p>No approved lawyer fits these requirements. Try removing a must-have option or widening the budget.</p>
<a class="btn small" href="{{ route('lawyers.match') }}">Start again</a>
</div>
@endforelse
</div>
@endif
</div>
</div>
</section>
@endsection
