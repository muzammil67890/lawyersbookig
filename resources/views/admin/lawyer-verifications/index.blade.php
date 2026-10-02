@extends('layouts.minimal')

@section('title', 'Lawyer Verifications | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>Lawyer verification queue</h1>
            <p>Review professional applications, CNIC and face documents, and approve or reject each lawyer.</p>
        </div>
        <a class="btn outline" href="{{ route('admin.dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    <div class="toolbar">
        <div class="checks" style="display:flex;flex-direction:row;gap:14px;">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'all' => 'All'] as $key => $label)
                <a class="btn small {{ $status === $key ? '' : 'outline' }}" href="{{ route('admin.lawyer-verifications.index', ['status' => $key]) }}">
                    {{ $label }} ({{ $counts[$key] }})
                </a>
            @endforeach
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Lawyer</th>
                    <th>City</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($verifications as $verification)
                    <tr>
                        <td>
                            <div class="table-person">
                                <div>
                                    <strong>{{ $verification->user->name }}</strong>
                                    <span>{{ $verification->user->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ $verification->user->lawyerProfile?->city ?? '&mdash;' }}</td>
                        <td>{{ $verification->submitted_at?->format('M j, Y') ?? '&mdash;' }}</td>
                        <td>{{ $verification->status->label() }}</td>
                        <td class="row-actions">
                            <a class="btn small outline" href="{{ route('admin.lawyer-verifications.show', $verification) }}">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No applications in this category.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px;">
        {{ $verifications->links() }}
    </div>
</div>
@endsection
