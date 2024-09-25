<!-- resources/views/domains/index.blade.php -->

@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Domains</h2>
    <a href="{{ route('domains.export') }}"
       class="btn btn-success mb-3">Export CSV</a>
    <a href="{{ url('/upload') }}"
       class="btn btn-primary mb-3">Upload</a> <!-- Added button -->
    <p>Total: {{ $total }} domains</p> <!-- Display total number of domains -->

    @if($total > 0)
        <table class="table">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Expiration Date</th>
                    <th>Registrar</th>
                    <th>Days Left</th> <!-- New column for days left -->
                    <th>Actions</th> <!-- New column for actions -->
                </tr>
            </thead>
            <tbody>
                @foreach($domains as $domain)
                    <tr>
                        <td>{{ $domain->domain }}</td>
                        <td>{{ date('Y-m-d H:i:s', $domain->exp_date) }}</td>
                        <td>{{ $domain->registrar }}</td>
                        <td>{{ $domain->days_left }}</td> <!-- Display days left -->
                        <td>
                            <form action="{{ route('domains.destroy', $domain->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td> <!-- Delete button -->
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No domains found.</p>
    @endif
</div>
@endsection