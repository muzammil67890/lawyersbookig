@extends('layouts.minimal')

@section('title', 'My Cases | Legal Connect')

@section('content')
<div class="container section">
    <div class="dashboard-head">
        <div>
            <h1>My cases</h1>
            <p>Legal matters you've opened with lawyers on Legal Connect.</p>
        </div>
        <a class="btn outline" href="{{ route('customer.dashboard') }}"><i class="fa-solid fa-arrow-left"></i> Back to dashboard</a>
    </div>

    @if (session('status'))
        <div class="demo-note" style="margin-bottom:18px;">{{ session('status') }}</div>
    @endif

    <div class="row-actions" style="margin-bottom:18px;flex-wrap:wrap;gap:6px;">
        @php $tabs = [null => 'All'] + collect(\App\Enums\CaseStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(); @endphp
        @foreach ($tabs as $value => $label)
            <a class="btn small {{ optional($status)->value === $value ? 'gold' : 'outline' }}"
               href="{{ route('customer.cases.index', $value ? ['status' => $value] : []) }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="panel" style="padding:0;">
        @if ($cases->isEmpty())
            <div style="padding:22px;">
                <p class="subtext">No cases match this filter yet. Visit the <a href="{{ route('lawyers.index') }}">lawyer directory</a> to open a case with a lawyer.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Case #</th>
                            <th>Title</th>
                            <th>Lawyer</th>
                            <th>Documents</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cases as $case)
                            <tr>
                                <td>{{ $case->case_number }}</td>
                                <td>{{ $case->title }}</td>
                                <td>{{ $case->lawyerProfile->display_name ?? '—' }}</td>
                                <td>{{ $case->documents_count }}</td>
                                <td><span class="status {{ $case->status->value }}">{{ $case->status->label() }}</span></td>
                                <td><a class="btn outline small" href="{{ route('customer.cases.show', $case) }}">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding:18px 22px;">
                {{ $cases->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
