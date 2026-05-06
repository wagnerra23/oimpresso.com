@extends('layouts.app')

@section('title', __('pontowr2::ponto.module_label'))

@section('content')
    {{--
        Navbar horizontal do módulo — padrão UltimatePOS (ref.: Modules/Repair/Resources/views/layouts/nav.blade.php).
        AdminLTE 2.x + Bootstrap 3 + FontAwesome 5.
        Nome das rotas conforme Modules/PontoWr2/Http/routes.php.
    --}}
    <section class="no-print">
        <nav class="navbar navbar-default bg-white m-4">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed"
                            data-toggle="collapse" data-target="#ponto-navbar-collapse"
                            aria-expanded="false">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="{{ route('ponto.dashboard') }}">
                        <i class="fa fas fa-business-time"></i>
                        {{ __('pontowr2::ponto.module_label') }}
                    </a>
                </div>

                <div class="collapse navbar-collapse" id="ponto-navbar-collapse">
                    @php
                        $current = optional(request()->route())->getName();
                        $tabs = [
                            ['route' => 'ponto.dashboard',             'seg2' => null,              'icon' => 'fa-tachometer-alt',      'label' => __('pontowr2::ponto.menu.dashboard')],
                            ['route' => 'ponto.espelho.index',         'seg2' => 'espelho',         'icon' => 'fa-clipboard-list',      'label' => __('pontowr2::ponto.menu.espelho')],
                            ['route' => 'ponto.aprovacoes.index',      'seg2' => 'aprovacoes',      'icon' => 'fa-check-double',        'label' => __('pontowr2::ponto.menu.aprovacoes'), 'badge' => $badgeAprovacoes ?? null],
                            ['route' => 'ponto.intercorrencias.index', 'seg2' => 'intercorrencias', 'icon' => 'fa-exclamation-triangle','label' => __('pontowr2::ponto.menu.intercorrencias')],
                            ['route' => 'ponto.banco-horas.index',     'seg2' => 'banco-horas',     'icon' => 'fa-piggy-bank',          'label' => __('pontowr2::ponto.menu.banco_horas')],
                            ['route' => 'ponto.escalas.index',         'seg2' => 'escalas',         'icon' => 'fa-calendar-alt',        'label' => __('pontowr2::ponto.menu.escalas')],
                            ['route' => 'ponto.importacoes.index',     'seg2' => 'importacoes',     'icon' => 'fa-file-import',         'label' => __('pontowr2::ponto.menu.importacoes')],
                            ['route' => 'ponto.relatorios.index',      'seg2' => 'relatorios',      'icon' => 'fa-chart-bar',           'label' => __('pontowr2::ponto.menu.relatorios')],
                            ['route' => 'ponto.colaboradores.index',   'seg2' => 'colaboradores',   'icon' => 'fa-users',               'label' => __('pontowr2::ponto.menu.colaboradores')],
                            ['route' => 'ponto.configuracoes.index',   'seg2' => 'configuracoes',   'icon' => 'fa-cog',                 'label' => __('pontowr2::ponto.menu.configuracoes')],
                        ];
                    @endphp

                    <ul class="nav navbar-nav">
                        @foreach ($tabs as $tab)
                            @php
                                // Ativo quando estamos no /ponto/{seg2} correspondente, ou no dashboard (seg2 null).
                                $seg1 = request()->segment(1);
                                $seg2 = request()->segment(2);
                                $active = $seg1 === 'ponto' && $seg2 === $tab['seg2'];
                            @endphp
                            <li @if($active) class="active" @endif>
                                <a href="{{ route($tab['route']) }}">
                                    <i class="fa fas {{ $tab['icon'] }}"></i>
                                    {{ $tab['label'] }}
                                    @if (!empty($tab['badge']))
                                        <span class="label label-danger">{{ $tab['badge'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </nav>
    </section>

    @yield('module_content')
@endsection
