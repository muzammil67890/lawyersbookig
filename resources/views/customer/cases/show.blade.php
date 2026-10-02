@extends('layouts.minimal')

@section('title', 'Case '.$case->case_number.' | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Case {{ $case->case_number }}</h1>
            <p>{{ $case->title }}</p>
        </div>
        <a class="btn outline" href="{{ route('customer.cases.index') }}"><i class="fa-solid fa-arrow-left"></i> All cases</a>
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
                <span class="subtext">Lawyer</span><br>
                <strong>{{ $case->lawyerProfile->display_name ?? 'Unassigned' }}</strong>
            </div>
        </div>

        <div style="margin-top:16px;">
            <span class="subtext">Service</span><br>
            <strong>{{ $case->service->name ?? 'Not specified' }}</strong>
        </div>

        <form method="POST" action="{{ route('customer.cases.update', $case) }}" style="margin-top:18px;">
            @csrf
            @method('PUT')

            <label for="title">Title</label>
            <input type="text" id="title" name="title" maxlength="150" value="{{ old('title', $case->title) }}"
                   @disabled($case->status->isTerminal())>

            <label for="description" style="margin-top:14px;">Description</label>
            <textarea id="description" name="description" rows="5" @disabled($case->status->isTerminal())>{{ old('description', $case->description) }}</textarea>

            <label for="customer_notes" style="margin-top:14px;">Your Notes</label>
            <textarea id="customer_notes" name="customer_notes" rows="4" @disabled($case->status->isTerminal())>{{ old('customer_notes', $case->customer_notes) }}</textarea>

            @unless ($case->status->isTerminal())
                <button class="btn outline small" type="submit" style="margin-top:12px;">Save changes</button>
            @endunless
        </form>

        @if ($case->status === \App\Enums\CaseStatus::CANCELLED && $case->cancellation_reason)
            <div style="margin-top:16px;">
                <span class="subtext">Cancellation reason</span><br>
                <span>{{ $case->cancellation_reason }}</span>
            </div>
        @endif

        @if (in_array(\App\Enums\CaseAction::CANCEL, $actions, true))
            <form method="POST" action="{{ route('customer.cases.cancel', $case) }}" style="margin-top:18px;"
                  onsubmit="return confirm('Cancel this case request?');">
                @csrf
                <button class="btn danger small" type="submit"><i class="fa-solid fa-xmark"></i> Cancel case</button>
            </form>
        @endif
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
                                    <a class="btn outline small" href="{{ route('customer.cases.documents.download', [$case, $document]) }}">Download</a>
                                    @if ($document->uploaded_by === auth()->id())
                                        <form method="POST" action="{{ route('customer.cases.documents.destroy', [$case, $document]) }}"
                                              onsubmit="return confirm('Delete this document?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn danger small" type="submit">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @unless ($case->status->isTerminal())
            <form method="POST" action="{{ route('customer.cases.documents.store', $case) }}" enctype="multipart/form-data" style="margin-top:18px;">
                @csrf
                <label for="document">Upload a document (PDF, JPG, PNG, DOC, DOCX &mdash; max 10&nbsp;MB)</label>
                <input type="file" id="document" name="document" required>

                <label for="category" style="margin-top:10px;">Category</label>
                <select id="category" name="category">
                    <option value="">— General —</option>
                    @foreach (\App\Enums\CaseDocumentCategory::cases() as $category)
                        <option value="{{ $category->value }}">{{ $category->label() }}</option>
                    @endforeach
                </select>

                <button class="btn outline small" type="submit" style="margin-top:12px;">Upload</button>
            </form>
        @endunless
    </div>
</div>
@endsection
