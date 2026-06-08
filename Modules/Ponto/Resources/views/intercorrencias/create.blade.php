@extends('pontowr2::layouts.module')

@section('title', 'Nova intercorrência')

@section('module_content')
    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.intercorrencias') }}
            <small>Nova intercorrência</small>
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-plus-circle"></i>
                        Cadastrar intercorrência
                        <small class="text-muted">— salvar como rascunho; submeter para aprovação depois</small>
                    @endslot

                    @include('pontowr2::intercorrencias._form', [
                        'intercorrencia' => null,
                        'action'         => route('ponto.intercorrencias.store'),
                        'metodo'         => 'POST',
                    ])
                @endcomponent
            </div>
        </div>
    </section>
@endsection
