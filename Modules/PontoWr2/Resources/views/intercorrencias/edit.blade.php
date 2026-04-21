@extends('pontowr2::layouts.module')

@section('title', 'Editar intercorrência')

@section('module_content')
    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.intercorrencias') }}
            <small>Editar — {{ $intercorrencia->codigo ?: substr($intercorrencia->id, 0, 8) }}</small>
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-warning'])
                    @slot('title')
                        <i class="fa fas fa-edit"></i>
                        Editar rascunho
                        <small class="text-muted">— só é possível editar enquanto estado = RASCUNHO</small>
                    @endslot

                    @include('pontowr2::intercorrencias._form', [
                        'intercorrencia' => $intercorrencia,
                        'action'         => route('ponto.intercorrencias.update', $intercorrencia->id),
                        'metodo'         => 'PUT',
                    ])
                @endcomponent
            </div>
        </div>
    </section>
@endsection
