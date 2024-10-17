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
                @foreach($activeDomainsByRegistrar as $registrar)
                    <li>{{ $registrar->registrar }}: {{ $registrar->total }} domains</li>
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

    @if($total > 0)
        <form id="bulk-action-form" action="{{ route('domains.destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            <table class="table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>Domain</th>
                        <th>Expiration Date</th>
                        <th>Registrar</th>
                        <th>Days Left</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($domains as $domain)
                        <tr>
                            <td><input type="checkbox" name="domains[]" value="{{ $domain->id }}" class="domain-checkbox"></td>
                            <td>{{ $domain->domain }}</td>
                            <td>{{ date('Y-m-d H:i:s', $domain->exp_date) }}</td>
                            <td>{{ $domain->registrar }}</td>
                            <td>{{ $domain->days_left }}</td>
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
@endsection
