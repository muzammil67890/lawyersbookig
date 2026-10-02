@extends('layouts.site')

@section('title', 'Find Lawyers | Legal Connect')

@section('site-content')
<section class="view active">
<div class="page-top">
<div class="container">
<div class="breadcrumb"><a href="{{ route('home') }}">Home</a><i class="fa-solid fa-chevron-right"></i><span>Find Lawyers</span></div>
<h1>Find legal expertise with confidence.</h1>
<p>Search verified, approved lawyer profiles by service, specialization, location and consultation fee.</p>
</div>
</div>

<div class="container search-layout">
<aside class="filter-panel" id="filterPanel">
<h3>Filter lawyers</h3>
<form method="GET" action="{{ route('lawyers.index') }}">
<div class="filter-group">
<label for="q">Search keyword</label>
<input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, title or bio">
</div>
<div class="filter-group">
<label for="service">Service</label>
<select id="service" name="service">
<option value="">All services</option>
@foreach ($services as $service)
<option value="{{ $service->slug }}" {{ ($filters['service'] ?? '') === $service->slug ? 'selected' : '' }}>{{ $service->name }}</option>
@endforeach
</select>
</div>
<div class="filter-group">
<label for="specialization">Specialization</label>
<select id="specialization" name="specialization">
<option value="">All specializations</option>
@foreach ($specializations as $specialization)
<option value="{{ $specialization->slug }}" {{ ($filters['specialization'] ?? '') === $specialization->slug ? 'selected' : '' }}>{{ $specialization->name }}</option>
@endforeach
</select>
</div>
<div class="filter-group">
<label for="city">Location</label>
<select id="city" name="city">
<option value="">All locations</option>
@foreach ($cities as $city)
<option value="{{ $city }}" {{ ($filters['city'] ?? '') === $city ? 'selected' : '' }}>{{ $city }}</option>
@endforeach
</select>
</div>
<div class="filter-group">
<label for="min_experience">Minimum experience</label>
<select id="min_experience" name="min_experience">
<option value="">Any experience</option>
<option value="5" {{ ($filters['min_experience'] ?? '') == 5 ? 'selected' : '' }}>5+ years</option>
<option value="10" {{ ($filters['min_experience'] ?? '') == 10 ? 'selected' : '' }}>10+ years</option>
<option value="15" {{ ($filters['min_experience'] ?? '') == 15 ? 'selected' : '' }}>15+ years</option>
</select>
</div>
<div class="filter-group">
<label for="min_fee">Minimum fee (Rs.)</label>
<input id="min_fee" name="min_fee" type="number" min="0" step="100" value="{{ $filters['min_fee'] ?? '' }}">
</div>
<div class="filter-group">
<label for="max_fee">Maximum fee (Rs.)</label>
<input id="max_fee" name="max_fee" type="number" min="0" step="100" value="{{ $filters['max_fee'] ?? '' }}">
</div>
@if ($errors->any())
<div class="filter-group" style="color:#c0392b;font-size:12px;">
{{ $errors->first() }}
</div>
@endif
<button class="btn small full" type="submit">Apply filters</button>
<a class="btn outline small full" style="margin-top:8px;" href="{{ route('lawyers.index') }}">Clear filters</a>
</form>
</aside>

<div>
<div class="search-toolbar">
<button class="btn outline small filter-mobile" type="button" onclick="document.getElementById('filterPanel').classList.toggle('open')"><i class="fa-solid fa-sliders"></i> Filters</button>
<div class="results-number">{{ $lawyers->total() }} {{ $lawyers->total() === 1 ? 'lawyer' : 'lawyers' }} found</div>
<form method="GET" action="{{ route('lawyers.index') }}" id="sortForm">
@foreach ($filters as $key => $value)
@if ($key !== 'sort' && $key !== 'page' && $value !== null && $value !== '')
<input type="hidden" name="{{ $key }}" value="{{ $value }}">
@endif
@endforeach
<select class="sort-select" name="sort" onchange="document.getElementById('sortForm').submit()">
<option value="recent" {{ $sort === 'recent' ? 'selected' : '' }}>Sort: Recently updated</option>
<option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Name</option>
<option value="fee" {{ $sort === 'fee' ? 'selected' : '' }}>Consultation fee</option>
<option value="experience" {{ $sort === 'experience' ? 'selected' : '' }}>Experience</option>
</select>
</form>
</div>

<div class="result-list">
@forelse ($lawyers as $lawyer)
<article class="lawyer-result">
@if ($lawyer->profile_image)
<img class="result-img" src="{{ asset('storage/'.$lawyer->profile_image) }}" alt="Portrait of {{ $lawyer->display_name }}" loading="lazy">
@else
<div class="result-img" style="display:flex;align-items:center;justify-content:center;background:var(--canvas);color:var(--muted);"><i class="fa-solid fa-user" style="font-size:28px;"></i></div>
@endif
<div>
<div class="result-title">
<h3>{{ $lawyer->display_name }}</h3>
<span class="verify"><i class="fa-solid fa-circle-check"></i> APPROVED</span>
</div>
<p class="result-description">{{ $lawyer->title }}</p>
<div class="tag-row">
<span class="tag"><i class="fa-solid fa-location-dot"></i> {{ $lawyer->city }}</span>
<span class="tag">{{ $lawyer->years_of_experience }} years</span>
@foreach ($lawyer->services->take(3) as $service)
<span class="tag">{{ $service->name }}</span>
@endforeach
</div>
<div class="rating-summary" style="margin-top:8px;">
@if ($lawyer->reviews_count > 0)
<span class="rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ number_format($lawyer->reviews_avg_rating, 1) }}</span>
<span>({{ $lawyer->reviews_count }} {{ Str::plural('review', $lawyer->reviews_count) }})</span>
@else
<span class="subtext">No reviews yet</span>
@endif
</div>
</div>
<div class="result-aside">
<div>
<strong class="fee">Rs. {{ number_format((float) $lawyer->consultation_fee, 0) }}</strong>
<span style="color:var(--muted);font-size:11px">consultation</span>
</div>
<div class="result-buttons">
<a class="btn outline small" href="{{ route('lawyers.show', $lawyer) }}">Profile</a>
</div>
</div>
</article>
@empty
<div class="empty">
<i class="fa-solid fa-magnifying-glass"></i>
<h3>No lawyers found</h3>
<p>Try widening your filters or explore another legal service.</p>
<a class="btn small" href="{{ route('lawyers.index') }}">Clear filters</a>
</div>
@endforelse
</div>

<div style="margin-top:22px;">
{{ $lawyers->links() }}
</div>
</div>
</div>
</section>
@endsection
