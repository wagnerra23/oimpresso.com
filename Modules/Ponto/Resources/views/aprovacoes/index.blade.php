@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.aprovacoes'))

@section('module_content')
    {{--
        Lista completa de intercorrências para aprovação.
        Padrão UltimatePOS/AdminLTE 2.x + Bootstrap 3.
        Controller: AprovacaoController@index.
    --}}

    {{-- Cabeçalho da tela --}}
    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.aprovacoes') }}</small>
        </h1>
    </section>

    <section class="content">

        {{-- Mensagens flash --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="fa fas fa-check"></i> {{ session('success') }}
            </div>
        @endif

        {{-- Filtros --}}
        @component('components.filters', ['title' => __('report.filters'), 'closed' => false])
            <form method="GET" action="{{ route('ponto.aprovacoes.index') }}">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select name="estado" id="estado" class="form-control">
                            <option value="">Todos</option>
                            @foreach (__('pontowr2::ponto.intercorrencia.estados') as $k => $v)
                                <option value="{{ $k }}" @if(($filtroEstado ?? '') === $k) selected @endif>
                                    {{ $v }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="tipo">Tipo</label>
                        <select name="tipo" id="tipo" class="form-control">
                            <option value="">Todos</option>
                            @foreach (__('pontowr2::ponto.intercorrencia.tipos') as $k => $v)
                                <option value="{{ $k }}" @if(($filtroTipo ?? '') === $k) selected @endif>
                                    {{ $v }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>&nbsp;</label><br>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fas fa-filter"></i> Filtrar
                        </button>
                        <a href="{{ route('ponto.aprovacoes.index') }}" class="btn btn-default">
                            <i class="fa fas fa-times"></i> Limpar
                        </a>
                    </div>
                </div>
            </form>
        @endcomponent

        {{-- Lista em widget AdminLTE --}}
        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-check-double"></i>
                        {{ __('pontowr2::ponto.menu.aprovacoes') }}
                        <small class="text-muted">
                            ({{ $aprovacoes->total() }} {{ $aprovacoes->total() === 1 ? 'item' : 'itens' }})
                        </small>
                    @endslot

                    @include('pontowr2::aprovacoes._tabela', ['aprovacoes' => $aprovacoes])

                    {{-- Paginação --}}
                    @if ($aprovacoes->hasPages())
                        <div class="text-center" style="margin-top:10px;">
                            {!! $aprovacoes->appends(request()->query())->links() !!}
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>

    </section>
@endsection
