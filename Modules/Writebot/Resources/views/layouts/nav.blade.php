<section class="no-print">
    <nav class="navbar navbar-default bg-white m-4">
        <div class="container-fluid">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{{action('\Modules\Boleto\Http\Controllers\RecipeController@index')}}"><i class="fas fa-industry"></i> {{__('boleto::lang.boleto')}}</a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    @can('boleto.access_recipe')
                        <li @if(request()->segment(1) == 'boleto' && in_array(request()->segment(2), ['recipe', 'add-ingredient'])) class="active" @endif><a href="{{action('\Modules\Boleto\Http\Controllers\RecipeController@index')}}">@lang('boleto::lang.recipe')</a></li>
                    @endcan

                    @can('boleto.access_production')
                        <li @if(request()->segment(2) == 'production') class="active" @endif><a href="{{action('\Modules\Boleto\Http\Controllers\ProductionController@index')}}">@lang('boleto::lang.production')</a></li>

                        <li @if(request()->segment(1) == 'boleto' && request()->segment(2) == 'settings') class="active" @endif><a href="{{action('\Modules\Boleto\Http\Controllers\SettingsController@index')}}">@lang('messages.settings')</a></li>

                        <li @if(request()->segment(2) == 'report') class="active" @endif><a href="{{action('\Modules\Boleto\Http\Controllers\ProductionController@getBoletoReport')}}">@lang('boleto::lang.boleto_report')</a></li>
                    @endcan
                </ul>

            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>