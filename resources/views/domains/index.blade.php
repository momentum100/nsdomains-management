@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Domains</h2>
    {{-- Top Button Row --}}
    <div class="mb-3"> 
        <a href="{{ route('domains.export') }}" class="btn btn-success">Export CSV</a>
        <a href="{{ url('/upload') }}" class="btn btn-primary">Upload</a>
        <a href="{{ url('/getquote') }}" class="btn btn-secondary">Get Quote</a>
        {{-- Moved Histogram Toggle Button Here --}}
        <button type="button" id="toggle-histogram-link" class="btn btn-outline-info ml-2">Show Expiration Histogram</button> 
    </div>
    {{-- End Top Button Row --}}

    {{-- Histogram Popup Container (Initially Hidden) --}}
    <div id="histogram-popup-container" style="display: none; position: fixed; top: 80px; right: 20px; width: 600px; max-width: 90%; z-index: 1050; background: white; border: 1px solid #ccc; border-radius: 5px; box-shadow: 0 5px 15px rgba(0,0,0,.1); padding: 15px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h4 style="margin: 0;">Active Domain Expiration Distribution</h4>
            <button type="button" class="btn-close" aria-label="Close" id="close-histogram-popup"></button>
        </div>
        <div id="histogramChart" style="height: 400px;"></div>
    </div>
    {{-- End Histogram Popup Container --}}

    <div class="row mb-4">
        <div class="col-md-6">
            <p>Total (in view): {{ $total }} domains</p>
            <p>Total Active: {{ $active }} domains</p>
            <p>Total Sold: {{ $sold }} domains</p>
        </div>
        
        <div class="col-md-6">
            <h4>Active Domains by Registrar</h4>
            <ul>
                <li>
                    <a href="{{ route('domains.index') }}" 
                       class="registrar-link {{ !isset($registrar) ? 'fw-bold text-primary' : '' }}">
                        All Domains: {{ $active }} total
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

    <button class="btn btn-info mb-3" type="button" data-toggle="collapse" data-target="#collapsibleTextarea" aria-expanded="false" aria-controls="collapsibleTextarea">
        Bulk Mark as Sold (by Name)
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

    <div class="mb-3 mt-3"> {{-- Active/Sold Buttons --}}
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

    <div class="card mb-4">
        <div class="card-header">
            <h5>Filter by Domain List</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('domains.filter') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="domain_list">Paste Domains (one per line)</label>
                    <textarea class="form-control" id="domain_list" name="domain_list" rows="5" 
                        placeholder="example.com
example.net
example.org"></textarea>
                </div>
                <button type="submit" class="btn btn-primary mt-3">
                    <i class="fas fa-filter"></i> Filter Domains
                </button>
                @if(isset($isFiltered) && $isFiltered)
                    <a href="{{ route('domains.index') }}" class="btn btn-secondary mt-3 ml-2">
                        <i class="fas fa-times"></i> Clear Filter
                    </a>
                @endif
            </form>
            
            @if(isset($isFiltered) && $isFiltered)
                <div class="alert alert-info mt-3">
                    @php
                        $totalPrice = $domains->sum('suggested_price');
                    @endphp
                    <strong>Showing {{ $total }} filtered domains.</strong> 
                    <strong>Total suggested price: ${{ number_format($totalPrice, 2) }}</strong>
                    <div class="mt-2">
                        <a href="{{ route('domains.index') }}" class="btn btn-sm btn-outline-primary">
                            Clear filter to see all domains
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

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

    document.querySelectorAll('form[action^="{{ route('domains.destroy', '') }}"]').forEach(form => {
        form.addEventListener('submit', function(event) {
            event.stopPropagation();
        });
    });
</script>

{{-- Include Plotly always, JS will handle empty data --}}
<script src='https://cdn.plot.ly/plotly-2.32.0.min.js'></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const histogramContainer = document.getElementById('histogram-container');
        const toggleLink = document.getElementById('toggle-histogram-link');

        const histogramData = @json($histogramData);
        
        console.log('Raw Histogram Data:', histogramData);

        const trace = {
            x: histogramData.labels,
            y: histogramData.counts,
            type: 'bar',
            marker: {
                color: histogramData.counts,
                colorscale: 'Viridis'
            }
        };
        
        const layout = {
            title: 'Domain Expiration Buckets (Active)',
            xaxis: { 
                title: 'Days Until Expiration', 
                tickangle: -45
            },
            yaxis: { title: 'Number of Domains' },
            margin: { l: 50, r: 20, b: 100, t: 50 }
        };
        
        if (histogramData && histogramData.labels && histogramData.labels.length > 0) {
            Plotly.newPlot('histogramChart', [trace], layout, {responsive: true});
            console.log('2D Bar Chart rendered.');
        } else {
            console.log('No histogram data to render.');
            document.getElementById('histogramChart').innerHTML = '<p class="text-muted">Histogram data is only available for Active domains.</p>';
            toggleLink.disabled = true;
            toggleLink.textContent = 'Histogram N/A';
            toggleLink.classList.add('disabled');
        }

        const histogramPopupContainer = document.getElementById('histogram-popup-container');
        const closePopupButton = document.getElementById('close-histogram-popup');

        if (toggleLink && histogramPopupContainer) {
            toggleLink.addEventListener('click', function(event) {
                event.preventDefault();
                histogramPopupContainer.style.display = 'block';
                this.disabled = true;
                console.log('Histogram visibility toggled.');
            });
        }

        if (closePopupButton && histogramPopupContainer && toggleLink) {
            closePopupButton.addEventListener('click', function() {
                histogramPopupContainer.style.display = 'none';
                toggleLink.disabled = false;
                console.log('Histogram popup closed.');
            });
        }
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
</style>
@endsection
