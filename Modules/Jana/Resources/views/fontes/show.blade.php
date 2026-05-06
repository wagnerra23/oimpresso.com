@extends('layouts.app')
@section('title', 'Copiloto — Fonte da meta')
@section('content')
<section class="content-header"><h1>Fonte: {{ $meta->nome }}</h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <div class="alert alert-warning">STUB — permissão `copiloto.fontes.edit` exigida. Editor rico (SQL com preview) entra na Page React.</div>
        <pre>{{ json_encode($meta->fonte?->config_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        <a href="{{ route('copiloto.metas.show', $meta->id) }}" class="btn btn-default">Voltar</a>
    </div></div>
</section>
@stop
