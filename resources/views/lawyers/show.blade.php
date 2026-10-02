@extends('layouts.site')

@section('title', $lawyer->display_name.' | Legal Connect')

@section('site-content')
<section class="view active">
<div class="page-top">
<div class="container">
<div class="breadcrumb"><a href="{{ route('home') }}">Home</a><i class="fa-solid fa-chevron-right"></i><a href="{{ route('lawyers.index') }}">Find Lawyers</a><i class="fa-solid fa-chevron-right"></i><span>{{ $lawyer->display_name }}</span></div>
<h1>Professional profile</h1>
</div>
</div>

<div class="container profile-wrap">
<section class="profile-hero">
@if ($lawyer->profile_image)
<img class="profile-img" src="{{ asset('storage/'.$lawyer->profile_image) }}" alt="Portrait of {{ $lawyer->display_name }}">
@else
<div class="profile-img" style="display:flex;align-items:center;justify-content:center;background:var(--canvas);color:var(--muted);"><i class="fa-solid fa-user" style="font-size:40px;"></i></div>
@endif
<div>
<h1>{{ $lawyer->display_name }} <span class="verify"><i class="fa-solid fa-circle-check"></i> APPROVED</span></h1>
<p class="profile-title">{{ $lawyer->title }}</p>
<div class="profile-details">
<span><i class="fa-solid fa-location-dot"></i> {{ $lawyer->city }}, Pakistan</span>
<span><i class="fa-solid fa-briefcase"></i> {{ $lawyer->years_of_experience }} years experience</span>
<span><i class="fa-solid fa-sack-dollar"></i> Rs. {{ number_format((float) $lawyer->consultation_fee, 0) }} consultation</span>
@if ($reputation['count'] > 0)
<span class="rating"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ number_format($reputation['average'], 1) }} ({{ $reputation['count'] }} {{ Str::plural('review', $reputation['count']) }})</span>
@else
<span><i class="fa-regular fa-star"></i> No reviews yet</span>
@endif
</div>
</div>
<div class="profile-actions">
@auth
    @if (auth()->user()->isCustomer())
        <a class="btn gold" href="{{ route('customer.appointments.create', $lawyer->id) }}"><i class="fa-solid fa-calendar-check"></i> Request appointment</a>
        <form method="POST" action="{{ route('customer.messages.start', $lawyer->id) }}" style="display:flex;flex:1;">
            @csrf
            <button class="btn outline" type="submit" style="width:100%;"><i class="fa-regular fa-comments"></i> Message</button>
        </form>
    @endif
@else
    <a class="btn gold" href="{{ route('login') }}"><i class="fa-solid fa-calendar-check"></i> Log in to request an appointment</a>
@endauth
</div>
</section>

<div class="profile-content">
<div>
@if ($lawyer->bio)
<section class="profile-section">
<h2>ABOUT</h2>
<p>{{ $lawyer->bio }}</p>
</section>
@endif

@if ($lawyer->services->isNotEmpty())
<section class="profile-section">
<h2>SERVICES</h2>
<div class="specialties">
@foreach ($lawyer->services as $service)
<span class="specialty">{{ $service->name }}</span>
@endforeach
</div>
</section>
@endif

@if ($lawyer->specializations->isNotEmpty())
<section class="profile-section">
<h2>SPECIALIZATIONS</h2>
<div class="specialties">
@foreach ($lawyer->specializations as $specialization)
<span class="specialty">{{ $specialization->name }}</span>
@endforeach
</div>
</section>
@endif

<section class="profile-section">
<h2>CLIENT REVIEWS</h2>

<div class="rating-summary" style="margin-bottom:14px;">
@if ($reputation['count'] > 0)
<span class="rating" style="font-size:20px;"><i class="fa-solid fa-star" aria-hidden="true"></i> {{ number_format($reputation['average'], 1) }} / 5</span>
<span>{{ $reputation['count'] }} {{ Str::plural('review', $reputation['count']) }}</span>
@else
<span class="subtext">No published reviews yet -- be the first client to leave one after a completed appointment.</span>
@endif
</div>

@if ($reputation['count'] > 0)
<div class="rating-breakdown" style="margin-bottom:18px;">
@for ($star = 5; $star >= 1; $star--)
@php $starCount = $reputation['breakdown'][$star] ?? 0; @endphp
<div class="rating-breakdown-row">
<span>{{ $star }} star</span>
<div class="rating-breakdown-bar"><span style="width:{{ round(($starCount / $reputation['count']) * 100) }}%"></span></div>
<span>{{ $starCount }}</span>
</div>
@endfor
</div>

@foreach ($reviews as $review)
<div class="review-item">
<span class="rating">
<i class="fa-solid fa-star" aria-hidden="true"></i> {{ $review->rating }}/5
</span>
@if ($review->comment)
<p>{{ $review->comment }}</p>
@endif
<span class="subtext">{{ $review->customer->name ?? 'Verified client' }} &middot; {{ $review->created_at->format('M j, Y') }}</span>
</div>
@endforeach

<div style="margin-top:16px;">{{ $reviews->links() }}</div>
@endif
</section>
</div>
</div>
</div>
</section>
@endsection
