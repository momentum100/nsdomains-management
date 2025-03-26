@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Domains</h2>
    <a href="{{ route('domains.export') }}" class="btn btn-success mb-3">Export CSV</a>
    <a href="{{ url('/upload') }}" class="btn btn-primary mb-3">Upload</a>
    <a href="{{ url('/getquote') }}" class="btn btn-secondary mb-3">Get Quote</a>
    
    <div class="d-flex justify-content-between">
        <div>
            <p>Total: {{ $total }} domains</p>
            <p>Active: {{ $active }} domains</p>
            <p>Sold: {{ $sold }} domains</p>
        </div>
        <div>
            <h4>Active Domains by Registrar</h4>
            <ul>
                <li>
                    <a href="{{ route('domains.index') }}" 
                       class="registrar-link {{ !isset($registrar) ? 'fw-bold text-primary' : '' }}">
                        All Domains: {{ $total }} total
                    </a>
                </li>
                @foreach($activeDomainsByRegistrar as $reg)
                    <li>
                        <a href="{{ route('domains.byRegistrar', ['registrar' => $reg->registrar]) }}" 
                           class="registrar-link {{ isset($registrar) && $registrar == $reg->registrar ? 'fw-bold text-primary' : '' }}">
                            {{ $reg->registrar }}: {{ $reg->total }} domains
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Collapsible Textarea and Submit Button -->
    <button class="btn btn-info mb-3" type="button" data-toggle="collapse" data-target="#collapsibleTextarea" aria-expanded="false" aria-controls="collapsibleTextarea">
        Bulk Mark as Sold
    </button>
    <div class="collapse" id="collapsibleTextarea">
        <form action="{{ route('domains.markAsSold') }}" method="POST">
            @csrf
            <div class="form-group">
                <textarea class="form-control" name="domains" rows="5" placeholder="Enter domain names, one per line"></textarea>
            </div>
            <button type="submit" class="btn btn-warning mb-3">Submit</button>
        </form>
    </div>

    <div class="mb-3 mt-3">
        <a href="{{ route('domains.index', ['status' => 'ACTIVE']) }}" class="btn btn-info mr-2">Active</a>
        <a href="{{ route('domains.index', ['status' => 'SOLD']) }}" class="btn btn-secondary">Sold</a>
    </div>

    @if(isset($registrar))
        <div class="mb-3">
            <a href="{{ route('domains.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-times"></i> Clear Registrar Filter
            </a>
            <span class="ms-2">Showing domains for registrar: <strong>{{ $registrar }}</strong></span>
        </div>
    @endif

    <!-- Add this before your main domains table -->
    <div class="card mb-4">
        <div class="card-header" role="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse" style="cursor: pointer;">
            <div class="d-flex justify-content-between align-items-center">
                <span>Filter by Domain List</span>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        
        <div class="collapse" id="filterCollapse">
            <div class="card-body">
                <form action="{{ route('domains.filterByList') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label for="domain_list">Paste domains (one domain per line, without commas)</label>
                        <textarea 
                            class="form-control" 
                            id="domain_list" 
                            name="domain_list" 
                            rows="5" 
                            placeholder="domain1.com
domain2.com
domain3.com"
                        >{{ old('domain_list') }}</textarea>
                        <small class="form-text text-muted">Example format:
                            <pre class="mt-2">gorillize.com
microtaxes.com
nearbuy.org</pre>
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Filter Domains</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Add filtered results summary if exists -->
    @if(isset($filteredDomains) && $filteredDomains->count() > 0)
        <div class="alert alert-info mb-4">
            <strong>Filtered Results:</strong> 
            Found {{ $filteredDomains->count() }} domains. 
            Total Suggested Price: ${{ number_format($filteredDomains->sum('suggested_price'), 2) }}
            <div class="mt-2">
                <small>Matched domains: {{ $filteredDomains->pluck('domain')->implode(', ') }}</small>
            </div>
        </div>
    @endif

    @if($total > 0)
        <form id="bulk-action-form" action="{{ route('domains.destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>Domain</th>
                        <th>Expiration Date</th>
                        <th>Registrar</th>
                        <th>Days Left</th>
                        <th>Suggested Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($domains as $domain)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><input type="checkbox" name="domains[]" value="{{ $domain->id }}" class="domain-checkbox"></td>
                            <td>{{ $domain->domain }}</td>
                            <td>{{ date('Y-m-d H:i:s', $domain->exp_date) }}</td>
                            <td>{{ $domain->registrar }}</td>
                            <td>{{ $domain->days_left }}</td>
                            <td>${{ number_format($domain->suggested_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="submit" id="bulk-action-button" class="btn btn-warning" style="display: none; position: fixed; bottom: 20px; right: 20px;">Mark Selected as Sold</button>
        </form>
    @else
        <p>No domains found.</p>
    @endif
</div>

<script>
    document.getElementById('select-all').addEventListener('click', function(event) {
        let checkboxes = document.querySelectorAll('.domain-checkbox');
        checkboxes.forEach(checkbox => checkbox.checked = event.target.checked);
        toggleBulkActionButton();
    });

    document.querySelectorAll('.domain-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', toggleBulkActionButton);
    });

    function toggleBulkActionButton() {
        let anyChecked = document.querySelectorAll('.domain-checkbox:checked').length > 0;
        document.getElementById('bulk-action-button').style.display = anyChecked ? 'block' : 'none';
    }

    // Prevent the bulk form from submitting when clicking the individual "Mark as Sold" buttons
    document.querySelectorAll('form[action^="{{ route('domains.destroy', '') }}"]').forEach(form => {
        form.addEventListener('submit', function(event) {
            event.stopPropagation();
        });
    });
</script>

<style>
    .registrar-link {
        text-decoration: none;
        color: inherit;
    }
    .registrar-link:hover {
        text-decoration: underline;
        color: #0d6efd;
    }
    .card-header[data-bs-toggle="collapse"] .fa-chevron-down {
        transition: transform 0.2s ease-in-out;
    }

    .card-header[data-bs-toggle="collapse"][aria-expanded="true"] .fa-chevron-down {
        transform: rotate(180deg);
    }
</style>
@endsection
