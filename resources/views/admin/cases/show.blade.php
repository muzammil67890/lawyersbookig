@extends('layouts.minimal')

@section('title', 'Case '.$case->case_number.' | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Case {{ $case->case_number }}</h1>
            <p>{{ $case->title }}</p>
        </div>
        <a class="btn outline" href="{{ route('admin.cases.index') }}"><i class="fa-solid fa-arrow-left"></i> All cases</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="demo-note" style="margin-bottom:18px;border-color:var(--red);color:var(--red);">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="panel" style="padding:22px;max-width:720px;margin-bottom:18px;">
        <div class="two-field">
            <div>
                <span class="subtext">Status</span><br>
                <span class="status {{ $case->status->value }}">{{ $case->status->label() }}</span>
            </div>
            <div>
                <span class="subtext">Customer</span><br>
                <strong>{{ $case->customer->name ?? '—' }}</strong>
            </div>
        </div>

        <div style="margin-top:16px;">
            <span class="subtext">Description</span>
            <p>{{ $case->description }}</p>
        </div>

        @if ($case->customer_notes)
            <div style="margin-top:16px;">
                <span class="subtext">Customer Notes</span>
                <p style="white-space:pre-line;">{{ $case->customer_notes }}</p>
            </div>
        @endif

        @if ($case->notes)
            <div style="margin-top:16px;">
                <span class="subtext">Lawyer Notes</span>
                <p style="white-space:pre-line;">{{ $case->notes }}</p>
            </div>
        @endif

        @if ($case->status === \App\Enums\CaseStatus::CANCELLED && $case->cancellation_reason)
            <div style="margin-top:16px;">
                <span class="subtext">Cancellation reason</span>
                <p style="white-space:pre-line;">{{ $case->cancellation_reason }}</p>
            </div>
        @endif

        <div style="margin-top:16px;">
            <span class="subtext">Currently assigned lawyer</span><br>
            <strong>{{ $case->lawyerProfile->display_name ?? 'Unassigned' }}</strong>
        </div>

        @unless ($case->status->isTerminal())
            <form method="POST" action="{{ route('admin.cases.assign-lawyer', $case) }}" style="margin-top:16px;">
                @csrf
                <label for="lawyer_profile_id">Reassign to lawyer (profile ID)</label>
                <input type="number" id="lawyer_profile_id" name="lawyer_profile_id" required>
                <button class="btn outline small" type="submit" style="margin-top:10px;">Reassign</button>
            </form>
        @endunless

        <div class="row-actions" style="margin-top:20px;gap:8px;flex-wrap:wrap;">
            @if ($case->status->value === 'completed')
                <form method="POST" action="{{ route('admin.cases.close', $case) }}">
                    @csrf
                    <button class="btn outline small" type="submit">Close case</button>
                </form>
            @endif
            @if (! $case->status->isTerminal())
                <form method="POST" action="{{ route('admin.cases.cancel', $case) }}" onsubmit="return confirm('Cancel this case?');">
                    @csrf
                    <button class="btn danger small" type="submit">Cancel case</button>
                </form>
            @endif
            @if ($case->status->value === 'cancelled' && $case->documents->isEmpty())
                <form method="POST" action="{{ route('admin.cases.destroy', $case) }}" onsubmit="return confirm('Permanently delete this case?');">
                    @csrf
                    @method('DELETE')
                    <button class="btn danger small" type="submit">Delete case</button>
                </form>
            @endif
        </div>
    </div>

    <div class="panel" style="padding:22px;max-width:720px;">
        <h3>Documents</h3>

        @if ($case->documents->isEmpty())
            <p class="subtext">No documents uploaded yet.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>File</th><th>Category</th><th>Uploaded by</th><th>Size</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($case->documents as $document)
                            <tr>
                                <td>{{ $document->original_filename }}</td>
                                <td>{{ $document->category?->label() ?? '—' }}</td>
                                <td>{{ $document->uploader->name ?? '—' }}</td>
                                <td>{{ number_format($document->size_bytes / 1024, 1) }} KB</td>
                                <td class="row-actions" style="gap:6px;">
                                    <a class="btn outline small" href="{{ route('admin.cases.documents.download', [$case, $document]) }}">Download</a>
                                    <form method="POST" action="{{ route('admin.cases.documents.destroy', [$case, $document]) }}"
                                          onsubmit="return confirm('Delete this document?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn danger small" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
