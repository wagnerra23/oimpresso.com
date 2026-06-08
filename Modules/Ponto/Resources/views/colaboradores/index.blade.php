@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.colaboradores'))

@section('module_content')
    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.colaboradores') }}</small>
        </h1>
    </section>

    <section class="content">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fas fa-check"></i> {{ session('success') }}
            </div>
        @endif

        {{-- Busca --}}
        @component('components.filters', ['title' => __('report.filters'), 'closed' => false])
            <form method="GET" action="{{ route('ponto.colaboradores.index') }}" class="form-inline">
                <div class="form-group">
                    <label for="q">Buscar:&nbsp;</label>
                    <input type="text"
                           name="q"
                           id="q"
                           class="form-control"
                           placeholder="Nome, matrícula ou CPF"
                           value="{{ $search }}">
                </div>
                &nbsp;
                <button type="submit" class="btn btn-primary">
                    <i class="fa fas fa-search"></i> Buscar
                </button>
                @if ($search)
                    <a href="{{ route('ponto.colaboradores.index') }}" class="btn btn-default">
                        <i class="fa fas fa-times"></i> Limpar
                    </a>
                @endif
            </form>
        @endcomponent

        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-users"></i>
                        Colaboradores
                        <small class="text-muted">
                            ({{ $colaboradores->total() }} encontrados)
                        </small>
                    @endslot

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Matrícula</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>CPF</th>
                                    <th>Escala</th>
                                    <th>Controla ponto</th>
                                    <th>Banco de horas</th>
                                    <th class="text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($colaboradores as $c)
                                    <tr>
                                        <td><small class="text-muted">{{ $c->matricula ?: '—' }}</small></td>
                                        <td>
                                            <i class="fa fas fa-user text-muted"></i>
                                            {{ optional($c->user)->first_name }}
                                            {{ optional($c->user)->last_name }}
                                        </td>
                                        <td><small>{{ optional($c->user)->email ?: '—' }}</small></td>
                                        <td><small>{{ $c->cpf ?: '—' }}</small></td>
                                        <td>
                                            @if ($c->escalaAtual)
                                                <span class="label label-info">{{ $c->escalaAtual->nome }}</span>
                                            @else
                                                <small class="text-muted">—</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($c->controla_ponto)
                                                <span class="label label-success">Sim</span>
                                            @else
                                                <span class="label label-default">Não</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($c->usa_banco_horas)
                                                <span class="label label-success">Sim</span>
                                            @else
                                                <span class="label label-default">Não</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('ponto.colaboradores.edit', $c->id) }}"
                                               class="btn btn-warning btn-xs">
                                                <i class="fa fas fa-edit"></i> Configurar
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted" style="padding:24px;">
                                            <i class="fa fas fa-search" style="font-size:24px;"></i><br>
                                            @if ($search)
                                                Nenhum colaborador encontrado para "<strong>{{ $search }}</strong>".
                                            @else
                                                Nenhum colaborador cadastrado neste business.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($colaboradores->hasPages())
                        <div class="text-center" style="margin-top:10px;">
                            {!! $colaboradores->appends(request()->query())->links() !!}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>

        <div class="callout callout-info">
            <h4><i class="fa fas fa-info-circle"></i> Sobre a vinculação</h4>
            <p>
                Os colaboradores são mantidos pelo módulo <strong>Essentials/HRM</strong>
                do UltimatePOS. Aqui você configura apenas os parâmetros específicos de
                ponto (matrícula, CPF/PIS, escala, flags). A edição do nome/e-mail é feita
                na tela de funcionários do HRM.
            </p>
        </div>
    </section>
@endsection
