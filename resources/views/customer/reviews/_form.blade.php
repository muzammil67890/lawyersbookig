@php
    $currentRating = old('rating', $review->rating ?? null);
    $currentComment = old('comment', $review->comment ?? '');
@endphp

<fieldset class="field" style="border:0; padding:0; margin-bottom:20px;">
    <legend style="font-weight:700; font-size:12px; margin-bottom:8px; padding:0;">Your rating</legend>
    <div class="star-input" role="radiogroup" aria-label="Star rating, 1 to 5">
        @for ($i = 5; $i >= 1; $i--)
            <input class="sr-only-input" type="radio" id="rating-{{ $i }}" name="rating" value="{{ $i }}"
                   @checked((int) $currentRating === $i) required>
            <label for="rating-{{ $i }}" aria-label="{{ $i }} star{{ $i === 1 ? '' : 's' }}">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
            </label>
        @endfor
        <span class="star-value" aria-live="polite">
            {{ $currentRating ? $currentRating.' out of 5 stars' : 'No rating selected yet' }}
        </span>
    </div>
    @error('rating')
        <div class="error" style="display:block;">{{ $message }}</div>
    @enderror
</fieldset>

<div class="field">
    <label for="comment">Your review (optional)</label>
    <textarea id="comment" name="comment" rows="5"
              maxlength="{{ config('reviews.comment_max_length', 2000) }}"
              placeholder="Share what your consultation was like -- it helps other clients choose the right lawyer.">{{ $currentComment }}</textarea>
    @error('comment')
        <div class="error" style="display:block;">{{ $message }}</div>
    @enderror
</div>
