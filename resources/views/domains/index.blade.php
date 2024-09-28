@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Domains</h2>
    <a href="{{ route('domains.export') }}" class="btn btn-success mb-3">Export CSV</a>
    <a href="{{ url('/upload') }}" class="btn btn-primary mb-3">Upload</a>
    <p>Total: {{ $total }} domains</p>
    <p>Active: {{ $active }} domains</p>
    <p>Sold: {{ $sold }} domains</p>

    <div class="mb-3">
        <a href="{{ route('domains.index', ['status' => 'ACTIVE']) }}" class="btn btn-info">Active</a>
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