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
example.org"></textarea>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Get Quotes</button>
    </form>

    <div id="results" class="mt-5">
        <!-- Results will be displayed here -->
    </div>
</div>

<script>
document.getElementById('quote-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const domains = document.getElementById('domains').value;
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = '<p>Loading...</p>';

    try {
        const response = await fetch("{{ route('getquote.process') }}", {
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

            html += '</tbody></table>';
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
