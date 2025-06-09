<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=0, minimal-ui">
    
    <!-- Favicon icon -->
    <link rel="icon" href="{{ asset('/favicon.ico') }}" type="image/x-icon">

    <title>{{ config('app.name', 'Laravel') }}</title>
    @php
        $asset_v = config('constants.asset_v');
        $copyright_msg = __('messages.application_copyright',
                            [
                                'name' => config('app.name', 'Laravel'),
                                'version' => config('author.app_version'),
                                'year' => date('Y')
                            ]
                        );
    @endphp
    <script type="application/javascript">
        //common App variable to be used in front-end
        var APP = {};
        APP.APP_NAME = '{{config('app.name')}}';
        APP.APP_URL = '{{config('app.url')}}';
        APP.APP_ENV = '{{config('app.env')}}';
        APP.NOTIFICATION_REFRESH_TIME = '{{config('constants.notification_refresh_time')}}';
        APP.COPYRIGHT_MSG = "{!!$copyright_msg!!}";
        APP.TIMEZONE = '{{config('app.timezone')}}';
        window._locale = '{{ app()->getLocale() }}';
        window._translations = {!! cache('translations') !!};
    </script>
    <!-- Scripts -->
    <script src="{{ asset('js/app.js?v=' . $asset_v) }}" defer></script>
    <script src="{{ asset('js/vendor-all.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/pcoded.min.js?v=' . $asset_v) }}"></script>
    @if(!empty($__gcse_js))
        {!!$__gcse_js!!}
    @endif
    <!-- Fonts -->
    <!-- <link rel="dns-prefetch" href="//fonts.gstatic.com"> -->
    <!-- <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet"> -->

    <!-- Styles -->
    <link href="{{ asset('css/app.css?v=' . $asset_v) }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css?v=' . $asset_v) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css?v={{$asset_v}}"/>
</head>
<body>
    <div>
        @routes
        @inertia
    </div>
    
</body>
</html>
