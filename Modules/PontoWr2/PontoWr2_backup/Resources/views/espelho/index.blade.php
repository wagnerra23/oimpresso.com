@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.espelho'))

@section('module_content')
    {{--
        Lista de colaboradores com acesso ao Espelho de Ponto.
        Seletor de mês é propagado para o show via querystring.
        Padrão AdminLTE 2.x + Bootstrap 3.
        Controller: EspelhoController@index.
    --}}

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.espelho') }}</small>
        </h1>
    </section>

    <section class="content">

        {{-- Filtro / seletor de mês --}}
        @component('components.filters', ['title' => __('report.filters'), 'closed' => false])
            <form method="GET" action="{{ route('ponto.espelho.index') }}" class="form-inline">
                <div class="form-group">
                    <label for="mes">Mês de referência:&nbsp;</label>
                    <input type="month"
                           id="mes"
                           name="mes"
                           class="form-control"
                           value="{{ $mes }}">
                </div>
                &nbsp;
                <button type="submit" class="btn btn-primary">
                    <i class="fa fas fa-filter"></i> Aplicar
                </button>
            </form>
        @endcomponent

        {{-- Lista de colaboradores --}}
        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-users"></i>
                        {{ __('pontowr2::ponto.menu.colaboradores') }}
                        <small class="text-muted">
                            ({{ $colaboradores->total() }} {{ $colaboradores->total() === 1 ? 'ativo' : 'ativos' }})
                        </small>
                    @endslot

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Matrícula</th>
                                    <th>Colaborador</th>
                                    <th>E-mail</th>
                                    <th>Controla ponto</th>
                                    <th class="text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($colaboradores as $c)
                                    <tr>
                                        <td>
                                            <small class="text-muted">{{ $c->matricula ?: '—' }}</small>
                                        </td>
                                        <td>
                                            <i class="fa fas fa-user text-muted"></i>
                                            {{ optional($c->user)->first_name }} {{ optional($c->user)->last_name }}
                                        </td>
                                        <td>
                                            <small>{{ optional($c->user)->email ?: '—' }}</small>
                                        </td>
                                        <td>
                                            @if ($c->controla_ponto)
                                                <span class="label label-success">Sim</span>
                                            @else
                                                <span class="label label-default">Não</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('ponto.espelho.show', ['colaborador' => $c->id, 'mes' => $mes]) }}"
                                               class="btn btn-primary btn-xs">
                                                <i class="fa fas fa-eye"></i> Ver espelho
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted" style="padding:24px;">
                                            <i class="fa fas fa-inbox" style="font-size:24px;"></i><br>
                                            Nenhum colaborador com controle de ponto encontrado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Paginação --}}
                    @if ($colaboradores->hasPages())
                        <div class="text-center" style="margin-top:10px;">
                            {!! $colaboradores->appends(request()->query())->links() !!}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>

    </section>
@endsection
