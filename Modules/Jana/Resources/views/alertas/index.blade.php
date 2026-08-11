@extends('layouts.app')
@section('title', 'Copiloto — Alertas')
@section('content')
<section class="content-header"><h1>Alertas</h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <p><strong>A lista de alertas ainda não existe.</strong></p>
        <p>Os alertas de desvio de meta já são calculados pela Jana e aparecem no
        Painel, junto da meta correspondente. O que falta aqui é a lista consolidada,
        com filtro por severidade e status.</p>
        <p><a href="{{ route('jana.index') }}" class="btn btn-primary">Ver o Painel</a>
        <a href="{{ route('jana.alertas.config') }}" class="btn btn-default">Ver a configuração</a></p>
    </div></div>
</section>
@stop
