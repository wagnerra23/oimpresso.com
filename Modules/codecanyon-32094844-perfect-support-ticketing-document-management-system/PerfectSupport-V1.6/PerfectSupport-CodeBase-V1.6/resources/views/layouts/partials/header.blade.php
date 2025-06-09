<header class="navbar pcoded-header navbar-expand-lg navbar-light">
    <div class="m-header">
        <a class="mobile-menu" id="mobile-collapse1" href="#!">
            <span></span>
        </a>
        <a href="{{ route('documentation-index') }}" class="b-brand">
            <div class="b-bg">
                <i class="fas fa-hands-helping"></i>
            </div>
            <span class="b-title">
               {{ config('app.name', 'Laravel') }}
            </span>
        </a>
    </div>
    <a class="mobile-menu" id="mobile-header" href="#!">
        <i class="feather icon-more-horizontal"></i>
    </a>
</header>