<!-- resources/views/partials/navbar.blade.php -->

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="#">Domain Manager</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('domains.uploadForm') }}">
                    Upload Domains
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('domains.index') }}">
                    View Domains
                </a>
            </li>
        </ul>
    </div>
</nav>
