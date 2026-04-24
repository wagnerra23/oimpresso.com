<section class="no-print">
    <nav class="navbar navbar-default bg-white m-4">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#officeimpresso-nav-collapse" aria-expanded="false">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{{ action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'computadores']) }}">
                    <i class="fa fas fa-plug"></i> {{ __('officeimpresso::lang.officeimpresso') }}
                </a>
            </div>

            <div class="collapse navbar-collapse" id="officeimpresso-nav-collapse">
                <ul class="nav navbar-nav">
                    @can('superadmin')
                        <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'businessall') class="active" @endif>
                            <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessall']) }}">
                                <i class="fa fas fa-network-wired"></i> @lang('officeimpresso::lang.businessall')
                            </a>
                        </li>
                    @endcan

                    <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'computadores') class="active" @endif>
                        <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'computadores']) }}">
                            <i class="fa fas fa-desktop"></i> @lang('officeimpresso::lang.computadores')
                        </a>
                    </li>

                    <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'licenca_computador') class="active" @endif>
                        <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'index']) }}">
                            <i class="fa fas fa-key"></i> @lang('officeimpresso::lang.licencas')
                        </a>
                    </li>

                    @can('superadmin')
                        <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'client') class="active" @endif>
                            <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\ClientController::class, 'index']) }}">
                                <i class="fa fas fa-user-tag"></i> @lang('officeimpresso::lang.clients')
                            </a>
                        </li>
                    @endcan

                    <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'licenca_log') class="active" @endif>
                        <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\LicencaLogController::class, 'index']) }}">
                            <i class="fa fas fa-clipboard-list"></i> Log de Acesso
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</section>
