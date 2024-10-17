@extends('layouts.app')

@section('content')
<div class="container">
    <h2><a href="{{ url()->current() }}" class="text-decoration-none">Get Domain Price Quotes</a></h2> <!-- Make heading a link -->
    <form id="quote-form">
        @csrf
        <div class="form-group">
            <label for="domains">Enter Domain Names (one per line):</label>
            <textarea class="form-control" id="domains" name="domains" rows="10" placeholder="example.com
example.net
example.org">
@foreach($results as $result)
{{ $result->domain }}
@if(!$loop->last)
@endif
@endforeach
</textarea>
        </div>
        <button type="button" class="btn btn-secondary mt-3" id="clean-button">Clean</button>
        <button type="submit" class="btn btn-primary mt-3" id="send-button">Get Price Quote for my Domains</button>
    </form>

    <div id="results" class="mt-5">
        @if($results->isNotEmpty())
            <h3>Previous Price Quotes:</h3>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Registrar</th>
                        <th>Expiration Date</th>
                        <th>Days Left</th>
                        <th>Price ($)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results as $result)
                        <tr>
                            <td>{{ $result->domain }}</td>
                            <td>{{ $result->registrar }}</td>
                            <td>{{ $result->expiration_date }}</td>
                            <td>{{ $result->days_left }}</td>
                            <td>{{ $result->price }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <h4>Total Price: ${{ $total_price }}</h4>
            @if($created_at)
                <p>Results cached on: {{ $created_at }}</p>
            @endif
        @endif
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        const messageSpan = document.getElementById('copy-message');
        messageSpan.style.display = 'inline'; // Show the message
        setTimeout(() => {
            messageSpan.style.display = 'none'; // Hide the message after 2 seconds
        }, 2000);
    }).catch(err => {
        console.error('Could not copy text: ', err);
    });
}

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
    let secondsElapsed = 0;
    const quotes = [
        "Hey, your list is long, wait for more!",
        "Patience is a virtue, especially with long lists!",
        "Good things come to those who wait!",
        "Almost there, hang tight!",
        "Your patience will be rewarded soon!",
        "Just a little longer, we promise!",
        "Great things take time!",
        "Hold on, we're fetching magic!",
        "Almost done, stay with us!",
        "Fetching results, please wait!"
    ];

    resultsDiv.innerHTML = `<p>Loading... <span id="loading-counter">0</span> seconds</p><p id="funny-quote"></p>`;

    const intervalId = setInterval(() => {
        secondsElapsed++;
        document.getElementById('loading-counter').textContent = secondsElapsed;

        if (secondsElapsed % 10 === 0) {
            const quoteIndex = (secondsElapsed / 10) % quotes.length;
            document.getElementById('funny-quote').textContent = quotes[quoteIndex];
        }
    }, 1000);

    try {
        const response = await fetch("{{ route('getquote.process', [], true) }}", { // Ensure HTTPS by passing true
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ domains })
        });

        clearInterval(intervalId); // Stop the counter once the response is received

        const data = await response.json();

        if (data.status === 'success') {
            let html = '<h3>Quotes:</h3><table class="table table-bordered"><thead><tr><th>Domain</th><th>Registrar</th><th>Expiration Date</th><th>Days Left</th><th>Price ($)</th></tr></thead><tbody>';

            data.data.forEach(domain => {
                if(domain.error){
                    html += `<tr>
                                <td>${domain.domain}</td>
                                <td colspan="4" class="text-danger">${domain.error}</td>
                             </tr>`;
                } else {
                    html += `<tr>
                                <td>${domain.domain}</td>
                                <td>${domain.registrar}</td>
                                <td>${domain.expiration_date}</td>
                                <td>${domain.days_left}</td>
                                <td>${domain.price}</td>
                             </tr>`;
                }
            });

            html += `</tbody></table><h4>Total Price: $${data.total_price}</h4>`;
            html += `<a href="/getquote/${data.uuid}" class="btn btn-link mt-3">View Cached Results</a>`; // Use UUID in link
            html += `<button class="btn btn-secondary mt-3" onclick="copyToClipboard('${window.location.origin}/getquote/${data.uuid}')">Copy URL</button>`; // Add copy URL button with full URL
            html += `<span id="copy-message" class="text-success ml-2" style="display:none;">URL copied!</span>`; // Message span
            resultsDiv.innerHTML = html;
        } else {
            resultsDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    } catch (error) {
        clearInterval(intervalId); // Stop the counter in case of an error
        console.error('Error fetching quotes:', error);
        resultsDiv.innerHTML = `<div class="alert alert-danger">An error occurred while fetching quotes.</div>`;
    }
});
</script>
@endsection
