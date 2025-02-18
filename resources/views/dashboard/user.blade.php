@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <h2 class="mb-4">Welcome, {{ auth()->user()->name }}!</h2>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Get Quote for Domain</h5>
                                    <p class="card-text">Request pricing for your desired domain.</p>
                                    <a href="{{ route('getquote.form') }}" class="btn btn-primary">
                                        Get Quote
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    Payment Details
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('user.payment.update') }}">
                                        @csrf
                                        <div class="form-group">
                                            <label for="payment_details">Your Payment Information</label>
                                            <textarea 
                                                class="form-control @error('payment_details') is-invalid @enderror" 
                                                id="payment_details" 
                                                name="payment_details" 
                                                rows="4" 
                                                placeholder="Enter your payment details (PayPal email, crypto wallet, etc.)"
                                            >{{ old('payment_details', auth()->user()->payment_details) }}</textarea>
                                            @error('payment_details')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                        <button type="submit" class="btn btn-primary mt-3">
                                            Save Payment Details
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 