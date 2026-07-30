@php
    // Espelha LicencaComputadorController::authorizeAccess(): a LEITURA da gestão
    // de licenças é delegável ao suporte via `officeimpresso.access` desde o
    // #5044 — não é mais superadmin-only.
    //
    // Esta nav é a TERCEIRA fonte de menu do módulo (as outras duas são
    // Resources/menus/topnav.php, do shell Inertia, e DataController::modifyAdminMenu,
    // da sidebar). O #5044 corrigiu as duas primeiras e passou por esta, que é
    // justamente a que as telas Blade legacy renderizam — resultado: o suporte
    // ABRIA /officeimpresso/computadores (o controller aceita `access`) mas não
    // via link nenhum pra navegar. Menu e guarda precisam contar a mesma história.
    $podeVerLicencas = auth()->user()->can('superadmin')
        || auth()->user()->can('officeimpresso.access');
@endphp

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
                <a class="navbar-brand" href="{{ $podeVerLicencas ? action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'computadores']) : action([\Modules\Officeimpresso\Http\Controllers\ClientController::class, 'index']) }}">
                    <i class="fa fas fa-plug"></i> {{ __('officeimpresso::lang.officeimpresso') }}
                </a>
            </div>

            <div class="collapse navbar-collapse" id="officeimpresso-nav-collapse">
                <ul class="nav navbar-nav">
                    @if($podeVerLicencas)
                        <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'businessall') class="active" @endif>
                            <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessall']) }}">
                                <i class="fa fas fa-network-wired"></i> @lang('officeimpresso::lang.businessall')
                            </a>
                        </li>
                    @endif

                    @if($podeVerLicencas)
                        <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'computadores') class="active" @endif>
                            <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'computadores']) }}">
                                <i class="fa fas fa-desktop"></i> @lang('officeimpresso::lang.computadores')
                            </a>
                        </li>
                    @endif

                    @if($podeVerLicencas)
                        <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'licenca_computador') class="active" @endif>
                            <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'index']) }}">
                                <i class="fa fas fa-key"></i> @lang('officeimpresso::lang.licencas')
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->can('superadmin') || auth()->user()->can('officeimpresso.clientes.liberar'))
                        <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'client') class="active" @endif>
                            <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\ClientController::class, 'index']) }}">
                                <i class="fa fas fa-user-tag"></i> @lang('officeimpresso::lang.clients')
                            </a>
                        </li>
                    @endif

                    @if($podeVerLicencas)
                        <li @if(request()->segment(1) == 'officeimpresso' && request()->segment(2) == 'licenca_log') class="active" @endif>
                            <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\LicencaLogController::class, 'index']) }}">
                                <i class="fa fas fa-clipboard-list"></i> Log de Acesso
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
</section>
