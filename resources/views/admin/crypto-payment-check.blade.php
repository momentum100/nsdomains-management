@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Admin: Crypto Payment Check</h2>
    <!-- ELI15: This form lets you enter a transaction hash and (optionally) a receiver address to check a crypto payment. -->
    <form method="POST" action="{{ route('admin.crypto-payment-check.post') }}">
        @csrf
        <div class="mb-3">
            <label for="transaction_hash" class="form-label">Transaction Hash</label>
            <input type="text" class="form-control" id="transaction_hash" name="transaction_hash" value="{{ old('transaction_hash') }}" required>
        </div>
        <div class="mb-3">
            <label for="receiver_address" class="form-label">Receiver Address (optional)</label>
            <input type="text" class="form-control" id="receiver_address" name="receiver_address" value="{{ old('receiver_address') }}">
        </div>
        <button type="submit" class="btn btn-primary">Check Transaction</button>
    </form>

    <hr>
    <!-- ELI15: Show how many checks were made in this session -->
    <div class="alert alert-info mt-3">
        <strong>Checks this session:</strong> {{ $counter ?? 0 }}
    </div>

    @if(isset($humanSummary) && $humanSummary)
        <!-- ELI15: Human-readable summary of the transaction for quick understanding -->
        <div class="alert alert-success mt-3">
            <strong>Summary:</strong><br>
            {!! $humanSummary !!}
        </div>
    @endif

    @if(isset($result))
        <h4>API Result (Prettified JSON)</h4>
        <pre style="background:#222;color:#b5f;font-size:1em;padding:1em;border-radius:8px;">{{ $result }}</pre>
    @endif

    @if(isset($log))
        <h4>Log (Request/Response)</h4>
        <pre style="background:#333;color:#6f6;font-size:0.95em;padding:1em;border-radius:8px;">{{ print_r($log, true) }}</pre>
    @endif
</div>
@endsection 