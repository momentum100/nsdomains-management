<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Domains</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Custom styles for better readability */
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
        }
        .info-card {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
            margin-bottom: 20px;
        }
        .registrar-list {
            columns: 2;
            -webkit-columns: 2;
            -moz-columns: 2;
        }
        .contact-links a {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- New Information Section -->
        <div class="card info-card p-4 mb-4">
            <div class="row">
                <div class="col-md-6">
                    <h4 class="mb-3">Как купить / How to Buy:</h4>
                    <div class="contact-links">
                        <a href="https://t.me/CheapNamesSupport" target="_blank" class="btn btn-primary mb-2">
                            <i class="bi bi-telegram"></i> ПОКУПАТЬ ЧЕРЕЗ ПОДДЕРЖКУ BUY HERE @CheapNamesSupport!
                        </a>
          
                    </div>

                    <h4 class="mt-4">Регистраторы / Registrars:</h4>
                    <div class="registrar-list">
                        <ul class="list-unstyled">
                            <li>✓ Godaddy</li>
                            <li>✓ Sav</li>
                            <li>✓ Namebright</li>
                            <li>✓ Dynadot</li>
                            <li>✓ Cosmotown</li>
                            <li>✓ Namesilo</li>
                            <li> ... </li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <h4 class="mb-3">Преимущества / Benefits:</h4>
                    <ul class="list-group">
                        <li class="list-group-item">✓ Передаем на ваш аккаунт (моментально) - ПОЛНОСТЬЮ ВАШИ</li>
                        <li class="list-group-item">✓ Срок действия осталось 2-4 недели и больше</li>
                        <li class="list-group-item">✓ Смена NS на любые - CLOUDFLARE например</li>
                        <li class="list-group-item">✓ Можно продлить на своём аккаунте</li>
                    </ul>
                    
                    <div class="alert alert-info mt-3">
                        <p class="mb-2">✓ От 10 доменов по $3.5 на дату окончания меньше 1 месяца</p>
                        <p class="mb-0">✓ Брошенные домены по $1.19 
                            <a href="https://t.me/CheapNamesBot" target="_blank">@CheapNamesbot</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <h1 class="mb-4">Available Domains</h1>
        
        <!-- Add counter and last updated info -->
        <p class="text-muted">
            Showing {{ $domains->count() }} domains
            <br>
            Last updated: {{ now()->format('Y-m-d H:i:s') }}
        </p>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Domain</th>
                        <th>Expiration Date</th>
                        <th>Registrar</th>
                        <th>Days Left</th>
                        <th>Price ($)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($domains as $domain)
                    <tr>
                        <td>{{ $domain->domain }}</td>
                        <td>{{ date('Y-m-d', $domain->exp_date) }}</td>
                        <td>{{ $domain->registrar }}</td>
                        <td>{{ $domain->days_left }}</td>
                        <td>${{ number_format($domain->suggested_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Page break and Footer -->
    <div class="container-fluid mt-5">
        <hr class="my-5"> <!-- Page break -->
        <footer class="text-center text-muted py-4">
            <p class="mb-1">© 2024 CheapNames. All rights reserved.</p>
            <p class="mb-0">Contact: <a href="https://t.me/CheapNamesSupport" target="_blank">@CheapNamesSupport</a></p>
        </footer>
    </div>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 