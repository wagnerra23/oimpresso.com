@extends('layouts.app')
@section('title', 'Copiloto — Fonte da meta')
@section('content')
<section class="content-header"><h1>Fonte: {{ $meta->nome }}</h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <div class="callout callout-warning">
            <p><strong>Esta tela é somente leitura.</strong> Abaixo está, como está gravada, a
            configuração que a Jana usa pra calcular o realizado desta meta.</p>
            <p>Editar a fonte aqui ainda não é possível — o editor com prévia do resultado
            antes de salvar é a <code>US-COPI-040</code>.</p>
        </div>
        <pre>{{ json_encode($meta->fonte?->config_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        <a href="{{ route('jana.metas.show', $meta->id) }}" class="btn btn-default">Voltar</a>
    </div></div>
</section>
@stop
