@extends('pontowr2::layouts.module')

@section('title', 'Nova escala')

@section('module_content')
    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.escalas') }}
            <small>Nova escala</small>
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-plus-circle"></i>
                        Cadastrar nova escala
                    @endslot

                    @include('pontowr2::escalas._form', [
                        'escala' => null,
                        'action' => route('ponto.escalas.store'),
                        'metodo' => 'POST',
                    ])
                @endcomponent
            </div>
        </div>
    </section>
@endsection
