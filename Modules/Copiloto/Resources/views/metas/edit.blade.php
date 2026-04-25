@extends('layouts.app')
@section('title', 'Copiloto — Editar meta')
@section('content')
<section class="content-header"><h1>Editar: {{ $meta->nome }}</h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <form method="POST" action="{{ route('copiloto.metas.update', $meta->id) }}">
            @csrf @method('PATCH')
            <div class="form-group"><label>Nome</label><input name="nome" class="form-control" value="{{ $meta->nome }}" required></div>
            <div class="form-group"><label>Unidade</label>
                <select name="unidade" class="form-control">
                    @foreach(['R$','qtd','%','dias'] as $u)
                        <option value="{{ $u }}" @selected($meta->unidade === $u)>{{ $u }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary">Salvar</button>
            <a href="{{ route('copiloto.metas.show', $meta->id) }}" class="btn btn-default">Cancelar</a>
        </form>
    </div></div>
</section>
@stop
