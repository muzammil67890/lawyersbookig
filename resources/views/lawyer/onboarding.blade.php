@extends('layouts.minimal')

@section('title', 'Complete Your Lawyer Application | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Complete your lawyer application</h1>
            <p>This information and your verification documents are reviewed by an administrator before your profile becomes visible to clients.</p>
        </div>
        <a class="btn outline" href="{{ route('lawyer.dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
    </div>

    <div class="panel" style="padding:26px;">
        <div class="demo-note" style="margin-bottom:22px;">
            <strong>Face verification is not biometric verification</strong><br>
            The photo you upload is collected as a document for manual admin review only. Legal Connect does not perform facial recognition or automated identity matching.
        </div>

        <form method="POST" action="{{ route('lawyer.onboarding.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            <h3 style="margin-bottom:14px;">Professional information</h3>

            <div class="field {{ $errors->has('title') ? 'invalid' : '' }}">
                <label for="title">Professional title</label>
                <input id="title" name="title" value="{{ old('title') }}" placeholder="e.g. Family &amp; Divorce Lawyer" required>
                <div class="error">{{ $errors->first('title') }}</div>
            </div>

            <div class="field {{ $errors->has('bio') ? 'invalid' : '' }}">
                <label for="bio">Short professional bio</label>
                <textarea id="bio" name="bio" rows="4" placeholder="A short summary of your practice and experience" required>{{ old('bio') }}</textarea>
                <div class="error">{{ $errors->first('bio') }}</div>
            </div>

            <div class="two-field">
                <div class="field {{ $errors->has('years_of_experience') ? 'invalid' : '' }}">
                    <label for="years_of_experience">Years of experience</label>
                    <input id="years_of_experience" name="years_of_experience" type="number" min="0" max="70" value="{{ old('years_of_experience') }}" required>
                    <div class="error">{{ $errors->first('years_of_experience') }}</div>
                </div>
                <div class="field {{ $errors->has('consultation_fee') ? 'invalid' : '' }}">
                    <label for="consultation_fee">Consultation fee (PKR)</label>
                    <input id="consultation_fee" name="consultation_fee" type="number" min="0" step="0.01" value="{{ old('consultation_fee') }}" required>
                    <div class="error">{{ $errors->first('consultation_fee') }}</div>
                </div>
            </div>

            <div class="two-field">
                <div class="field {{ $errors->has('city') ? 'invalid' : '' }}">
                    <label for="city">City</label>
                    <input id="city" name="city" value="{{ old('city') }}" placeholder="e.g. Karachi" required>
                    <div class="error">{{ $errors->first('city') }}</div>
                </div>
                <div class="field {{ $errors->has('phone') ? 'invalid' : '' }}">
                    <label for="phone">Professional phone <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" placeholder="+92 300 1234567">
                    <div class="error">{{ $errors->first('phone') }}</div>
                </div>
            </div>

            <div class="field {{ $errors->has('location') ? 'invalid' : '' }}">
                <label for="location">Office location / area <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                <input id="location" name="location" value="{{ old('location') }}" placeholder="e.g. Clifton, Block 5">
                <div class="error">{{ $errors->first('location') }}</div>
            </div>

            <div class="two-field">
                <div class="field {{ $errors->has('bar_council') ? 'invalid' : '' }}">
                    <label for="bar_council">Bar council <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                    <input id="bar_council" name="bar_council" value="{{ old('bar_council') }}" placeholder="e.g. Sindh Bar Council">
                    <div class="error">{{ $errors->first('bar_council') }}</div>
                </div>
                <div class="field {{ $errors->has('license_number') ? 'invalid' : '' }}">
                    <label for="license_number">License number <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                    <input id="license_number" name="license_number" value="{{ old('license_number') }}" placeholder="e.g. BC-1234-XYZ">
                    <div class="error">{{ $errors->first('license_number') }}</div>
                </div>
            </div>

            <div class="field {{ $errors->has('education') ? 'invalid' : '' }}">
                <label for="education">Education <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                <input id="education" name="education" value="{{ old('education') }}" placeholder="e.g. LL.B., University Law School">
                <div class="error">{{ $errors->first('education') }}</div>
            </div>

            <h3 style="margin:26px 0 14px;">Services you offer</h3>
            <div class="field {{ $errors->has('services') ? 'invalid' : '' }}">
                <div class="checks">
                    @foreach ($services as $service)
                        <label class="check">
                            <input type="checkbox" name="services[]" value="{{ $service->id }}"
                                {{ in_array((string) $service->id, (array) old('services', []), true) ? 'checked' : '' }}>
                            {{ $service->name }}
                        </label>
                    @endforeach
                </div>
                <div class="error">{{ $errors->first('services') }}</div>
            </div>

            @if ($specializations->isNotEmpty())
                <h3 style="margin:26px 0 14px;">Specializations <span style="font-weight:400;color:var(--muted);font-size:12px;">(optional)</span></h3>
                <div class="field {{ $errors->has('specializations') ? 'invalid' : '' }}">
                    <div class="checks">
                        @foreach ($specializations->flatten() as $specialization)
                            <label class="check">
                                <input type="checkbox" name="specializations[]" value="{{ $specialization->id }}"
                                    {{ in_array((string) $specialization->id, (array) old('specializations', []), true) ? 'checked' : '' }}>
                                {{ $specialization->name }}
                            </label>
                        @endforeach
                    </div>
                    <div class="error">{{ $errors->first('specializations') }}</div>
                </div>
            @endif

            <h3 style="margin:26px 0 14px;">Verification documents</h3>

            <div class="field {{ $errors->has('profile_image') ? 'invalid' : '' }}">
                <label for="profile_image">Profile photo <span style="font-weight:400;color:var(--muted)">(optional, shown on your public profile)</span></label>
                <input id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp">
                <div class="error">{{ $errors->first('profile_image') }}</div>
            </div>

            <div class="two-field">
                <div class="field {{ $errors->has('cnic_image') ? 'invalid' : '' }}">
                    <label for="cnic_image">CNIC image</label>
                    <input id="cnic_image" name="cnic_image" type="file" accept="image/jpeg,image/png,image/webp" required>
                    <div class="error">{{ $errors->first('cnic_image') }}</div>
                </div>
                <div class="field {{ $errors->has('face_image') ? 'invalid' : '' }}">
                    <label for="face_image">Face photo</label>
                    <input id="face_image" name="face_image" type="file" accept="image/jpeg,image/png,image/webp" required>
                    <div class="error">{{ $errors->first('face_image') }}</div>
                </div>
            </div>
            <p class="subtext" style="font-size:12px;margin:-8px 0 20px;">CNIC and face images are kept private and are only visible to you and Legal Connect administrators.</p>

            <button class="btn gold full" type="submit">Submit application for review <i class="fa-solid fa-arrow-right"></i></button>
        </form>
    </div>
</div>
@endsection
