@extends('layouts.app')
@section('title', 'Copiloto — Metas')
@section('content')
<section class="content-header"><h1>Metas</h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <a href="{{ route('copiloto.metas.create') }}" class="btn btn-primary">Nova meta</a>
        <hr>
        <table class="table">
            <thead><tr><th>Nome</th><th>Unidade</th><th>Origem</th><th>Ativo</th></tr></thead>
            <tbody>
            @forelse($metas as $m)
                <tr>
                    <td><a href="{{ route('copiloto.metas.show', $m->id) }}">{{ $m->nome }}</a></td>
                    <td>{{ $m->unidade }}</td>
                    <td>{{ $m->origem }}</td>
                    <td>{{ $m->ativo ? 'sim' : 'não' }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><em>Nenhuma meta cadastrada.</em></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
</section>
@stop
