{{-- Confirmação de instalar/desinstalar/atualizar módulo: o GET só mostra isto; a ação roda no POST. --}}
@extends('layouts.app')
@section('title', $titulo)
@section('content')
<section class="content">
    <h3>{{ $titulo }}</h3>
    <p>{{ $consequencia }}</p>
    <form method="POST" action="{{ $url }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-danger">Confirmar</button>
    </form>
    <a href="{{ $voltar }}" class="btn btn-default">Cancelar</a>
</section>
@endsection
