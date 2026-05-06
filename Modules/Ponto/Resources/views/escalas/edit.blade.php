@extends('pontowr2::layouts.module')

@section('title', 'Editar escala — ' . $escala->nome)

@section('module_content')
    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.menu.escalas') }}
            <small>{{ $escala->nome }}</small>
        </h1>
    </section>

    <section class="content">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <i class="fa fas fa-check"></i> {{ session('success') }}
            </div>
        @endif

        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                @component('components.widget', ['class' => 'box-warning'])
                    @slot('title')
                        <i class="fa fas fa-edit"></i>
                        Editar escala
                    @endslot

                    @include('pontowr2::escalas._form', [
                        'escala' => $escala,
                        'action' => route('ponto.escalas.update', $escala->id),
                        'metodo' => 'PUT',
                    ])
                @endcomponent
            </div>
        </div>
    </section>
@endsection
