@extends('layouts.app')

@section('title', 'Copiloto — Dashboard')

@section('content')
<section class="content-header">
    <h1>Copiloto — Dashboard <small>metas ativas</small></h1>
    <a href="{{ route('copiloto.chat.index') }}" class="btn btn-primary">Conversar com Copiloto</a>
</section>

<section class="content">
    <div class="box">
        <div class="box-body">
            <div class="alert alert-warning">STUB spec-ready — Pages/Copiloto/Dashboard.tsx substitui esta view.</div>

            <table class="table table-striped">
                <thead>
                    <tr><th>Meta</th><th>Unidade</th><th>Tipo</th><th>Ativo</th></tr>
                </thead>
                <tbody>
                    @forelse($metas as $m)
                        <tr>
                            <td><a href="{{ route('copiloto.metas.show', $m->id) }}">{{ $m->nome }}</a></td>
                            <td>{{ $m->unidade }}</td>
                            <td>{{ $m->tipo_agregacao }}</td>
                            <td>{{ $m->ativo ? 'sim' : 'não' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><em>Sem metas ativas.</em></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@stop
