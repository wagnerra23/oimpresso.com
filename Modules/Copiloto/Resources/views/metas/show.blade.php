@extends('layouts.app')
@section('title', 'Copiloto — ' . $meta->nome)
@section('content')
<section class="content-header"><h1>{{ $meta->nome }} <small>{{ $meta->unidade }}</small></h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <p><strong>Slug:</strong> {{ $meta->slug }}</p>
        <p><strong>Tipo:</strong> {{ $meta->tipo_agregacao }}</p>
        <p><strong>Origem:</strong> {{ $meta->origem }}</p>
        <p><strong>Escopo:</strong> {{ $meta->business_id ? 'Business #' . $meta->business_id : 'Plataforma' }}</p>

        <hr>
        <h3>Últimas apurações</h3>
        <table class="table">
            <thead><tr><th>Data</th><th>Valor realizado</th></tr></thead>
            <tbody>
            @forelse($apuracoes as $a)
                <tr><td>{{ $a->data_ref->format('Y-m-d') }}</td><td>{{ number_format($a->valor_realizado, 2, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="2"><em>Nenhuma apuração ainda.</em></td></tr>
            @endforelse
            </tbody>
        </table>

        <form method="POST" action="{{ route('copiloto.metas.reapurar', $meta->id) }}" style="display:inline;">
            @csrf
            <button class="btn btn-default">Forçar reapuração</button>
        </form>
        <a href="{{ route('copiloto.metas.edit', $meta->id) }}" class="btn btn-default">Editar</a>
        <a href="{{ route('copiloto.fontes.show', $meta->id) }}" class="btn btn-default">Fonte</a>
    </div></div>
</section>
@stop
