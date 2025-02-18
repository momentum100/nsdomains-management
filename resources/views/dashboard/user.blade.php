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
                        
                        <!-- Add more cards here for future features -->
                    </div>

                    <!-- Add any additional user information or features here -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 