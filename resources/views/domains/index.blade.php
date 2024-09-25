<!-- resources/views/domains/index.blade.php -->

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
        <table class="table">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Expiration Date</th>
                    <th>Registrar</th>
                    <th>Days Left</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($domains as $domain)
                    <tr>
                        <td>{{ $domain->domain }}</td>
                        <td>{{ date('Y-m-d H:i:s', $domain->exp_date) }}</td>
                        <td>{{ $domain->registrar }}</td>
                        <td>{{ $domain->days_left }}</td>
                        <td>
                            <form action="{{ route('domains.destroy', $domain->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Mark as Sold</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No domains found.</p>
    @endif
</div>
@endsection