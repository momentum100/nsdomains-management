@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Get Domain Quotes</h2>
    <form id="quote-form">
        @csrf
        <div class="form-group">
            <label for="domains">Enter Domain Names (one per line):</label>
            <textarea class="form-control" id="domains" name="domains" rows="10" placeholder="example.com
example.net
example.org">@foreach($results as $result){{ $result->domain }}@if(!$loop->last)
@endif @endforeach</textarea>
        </div>
        <button type="button" class="btn btn-secondary mt-3" id="clean-button">Clean</button>
        <button type="submit" class="btn btn-primary mt-3" id="send-button">Send</button>
    </form>

    <div id="results" class="mt-5">
        @if($results->isNotEmpty())
            <h3>Previous Quotes:</h3>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Registrant</th>
                        <th>Expiration Date</th>
                        <th>Days Left</th>
                        <th>Price ($)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results as $result)
                        <tr>
                            <td>{{ $result->domain }}</td>
                            <td>{{ $result->registrant }}</td>
                            <td>{{ $result->expiration_date }}</td>
                            <td>{{ $result->days_left }}</td>
                            <td>{{ $result->price }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<script>
document.getElementById('clean-button').addEventListener('click', function() {
    // Parse the input to extract domain names using a refined regular expression
    const rawInput = document.getElementById('domains').value;
    const domainRegex = /^[a-z0-9-]+\.[a-z]{2,}(?:\.[a-z]{2,})?/gim; // Matches domain names with extensions
    const domains = Array.from(rawInput.matchAll(domainRegex), match => match[0]).join('\n');
    document.getElementById('domains').value = domains;
});

document.getElementById('quote-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const domains = document.getElementById('domains').value;
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = '<p>Loading...</p>';

    try {
        const response = await fetch("{{ route('getquote.process', [], true) }}", { // Ensure HTTPS by passing true
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ domains })
        });

        const data = await response.json();

        if (data.status === 'success') {
            let html = '<h3>Quotes:</h3><table class="table table-bordered"><thead><tr><th>Domain</th><th>Registrant</th><th>Expiration Date</th><th>Days Left</th><th>Price ($)</th></tr></thead><tbody>';

            data.data.forEach(domain => {
                if(domain.error){
                    html += `<tr>
                                <td>${domain.domain}</td>
                                <td colspan="4" class="text-danger">${domain.error}</td>
                             </tr>`;
                } else {
                    html += `<tr>
                                <td>${domain.domain}</td>
                                <td>${domain.registrant}</td>
                                <td>${domain.expiration_date}</td>
                                <td>${domain.days_left}</td>
                                <td>${domain.price}</td>
                             </tr>`;
                }
            });

            html += `</tbody></table><h4>Total Price: $${data.total_price}</h4>`;
            html += `<a href="${data.link}" class="btn btn-link mt-3">View Full Results</a>`; // Add link to results
            resultsDiv.innerHTML = html;
        } else {
            resultsDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    } catch (error) {
        console.error('Error fetching quotes:', error);
        resultsDiv.innerHTML = `<div class="alert alert-danger">An error occurred while fetching quotes.</div>`;
    }
});
</script>
@endsection
