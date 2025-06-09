@inject('request', 'Illuminate\Http\Request')
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=0, minimal-ui">
    
    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    @php
        $asset_v = config('constants.asset_v');
    @endphp
    
    <!-- Styles -->
    <link href="{{ asset('css/app.css?v=' . $asset_v) }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css?v=' . $asset_v) }}">
    <style type="text/css">
        .blockquote {
            border-left: 0.25rem solid #04a9f5;
        }
        .pcoded-header{
            min-height: 0px;
        }
        img {
            max-width: 100% !important;
            width: auto !important;
            height: auto !important;
        }
    </style>
    @yield('css')
    @if(!empty($__gcse_js))
        {!!$__gcse_js!!}
    @endif
</head>
<body>
    @if((!empty(request()->segment(1)) && request()->segment(1) != 'docs'))
        @includeIf('layouts.partials.sidebar')
        @includeIf('layouts.partials.header')
    @endif
    <div @if((!empty(request()->segment(1)) && request()->segment(1) != 'docs')) class="pcoded-main-container" @endif>
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            @yield('content')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @includeIf('layouts.partials.footer')

    <!-- Scripts -->
    <script src="{{ asset('js/app.js?v=' . $asset_v) }}" defer></script>
    <script src="{{ asset('js/vendor-all.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/pcoded.min.js?v=' . $asset_v) }}"></script>
    <script type="text/javascript">
        initCommonThemeCode();
    </script>
    @includeIf('layouts.partials.doc_common_js')
    @yield('javascript')
</body>
</html>
