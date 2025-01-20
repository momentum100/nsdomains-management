<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Domains</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Font Awesome CSS in head section -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        /* Sticky Panel Styles */
        .sticky-panel {
            position: fixed;
            bottom: 20px;  /* Distance from bottom */
            right: 20px;   /* Distance from right */
            z-index: 1000; /* Ensure it stays on top */
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 300px; /* Limit width to prevent overlap */
        }
        .sticky-panel:hover {
            transform: scale(1.02);
        }
        .domain-list {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 10px;
        }
        .domain-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
        .domain-item button {
            padding: 0 5px;
            font-size: 12px;
        }
        .clickable {
            cursor: pointer;
            color: #0d6efd;
            text-decoration: underline;
        }
        .copy-feedback {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border-radius: 4px;
            display: none;
        }
        .main-table {
            max-width: 80%; /* Reduce table width to prevent overlay */
            margin: 0 auto; /* Center the table */
        }
        .domain-cell {
            /* Remove default link styling */
            color: inherit;
            text-decoration: none;
            cursor: default;
            word-break: break-word;
            display: inline-block;
            max-width: calc(100% - 70px); /* Account for copy button */
        }
        .copy-btn {
            padding: 2px 8px;
            margin-left: 8px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .copy-btn:hover {
            background-color: #e9ecef;
        }
        
        /* Toast styling */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }
        
        /* Add these new styles for table columns */
        .table th:nth-child(1), /* Domain column */
        .table td:nth-child(1) {
            width: 25%;
            max-width: 300px;
        }
        .table th:nth-child(2), /* Expiration Date column */
        .table td:nth-child(2) {
            width: 20%;
        }
        .table th:nth-child(3), /* Registrar column */
        .table td:nth-child(3) {
            width: 20%;
        }
        .table th:nth-child(4), /* Days Left column */
        .table td:nth-child(4) {
            width: 15%;
        }
        .table th:nth-child(5), /* Price column */
        .table td:nth-child(5) {
            width: 20%;
        }
        /* Add this style for the counter */
        .domain-counter {
            background-color: #0d6efd;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            margin-left: 8px;
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
                       
                        <p class="mb-0 w-100 d-block">✓ Брошенные домены по $1.19 
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

        <!-- Sticky Panel -->
        <div class="sticky-panel">
            <h5>Selected Domains <span id="domainCounter" class="domain-counter">0</span></h5>
            <div class="domain-list" id="selectedDomains">
                <!-- Selected domains will be added here -->
            </div>
            <button class="btn btn-primary btn-sm w-100" onclick="copyAllDomains()">Copy All Domains</button>
        </div>

        <!-- Copy Feedback -->
        <div class="copy-feedback" id="copyFeedback">Copied!</div>

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
                        <td>
                            <span class="domain-cell">{{ $domain->domain }}</span>
                            <button class="copy-btn" onclick="copyToClipboard('{{ $domain->domain }}')" title="Copy domain">
                                <i class="fas fa-copy"></i>
                            </button>
                        </td>
                        <td>{{ date('Y-m-d', $domain->exp_date) }}</td>
                        <td>{{ $domain->registrar }}</td>
                        <td>{{ $domain->days_left }}</td>
                        <td>${{ $domain->suggested_price }}</td>
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
    
    <script>
        // Store selected domains
        let selectedDomains = new Set();

        // Add domain to the sticky panel
        function addDomain(domain) {
            if (!selectedDomains.has(domain)) {
                selectedDomains.add(domain);
                updateDomainList();
                showFeedback('Domain added!');
            }
        }

        // Remove domain from the sticky panel
        function removeDomain(domain) {
            selectedDomains.delete(domain);
            updateDomainList();
        }

        // Update the domain list display
        function updateDomainList() {
            const container = document.getElementById('selectedDomains');
            const counter = document.getElementById('domainCounter');
            container.innerHTML = '';
            
            // Update counter
            counter.textContent = selectedDomains.size;
            
            selectedDomains.forEach(domain => {
                const div = document.createElement('div');
                div.className = 'domain-item';
                div.innerHTML = `
                    <span>${domain}</span>
                    <button class="btn btn-danger btn-sm" onclick="removeDomain('${domain}')">×</button>
                `;
                container.appendChild(div);
            });
        }

        // Copy all selected domains
        function copyAllDomains() {
            if (selectedDomains.size === 0) {
                showFeedback('No domains selected!');
                return;
            }
            
            const domainsText = Array.from(selectedDomains).join('\n');
            navigator.clipboard.writeText(domainsText)
                .then(() => {
                    showFeedback('All domains copied!');
                })
                .catch(err => {
                    showFeedback('Failed to copy domains');
                    console.error('Failed to copy: ', err);
                });
        }

        // Show feedback message
        function showFeedback(message) {
            const feedback = document.getElementById('copyFeedback');
            feedback.textContent = message;
            feedback.style.display = 'block';
            
            setTimeout(() => {
                feedback.style.display = 'none';
            }, 2000);
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Show toast notification
                const toast = new bootstrap.Toast(document.getElementById('copyToast'));
                document.getElementById('toastMessage').textContent = 'Domain copied: ' + text;
                toast.show();
                
                // Add to selected domains
                addDomain(text);
            }).catch(err => {
                console.error('Failed to copy:', err);
                document.getElementById('toastMessage').textContent = 'Failed to copy domain';
                const toast = new bootstrap.Toast(document.getElementById('copyToast'));
                toast.show();
            });
        }
    </script>

    <!-- Add toast container before closing body tag -->
    <div class="toast-container">
        <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" id="copyToast">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="toastMessage">Domain copied!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
</body>
</html> 