@extends('layouts.app')

@section('title', 'Copiloto')

@section('content')
<section class="content-header"><h1>Copiloto <small>chat com IA</small></h1></section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            @if(session('status'))
                <div class="alert alert-info">{{ session('status') }}</div>
            @endif

            <div class="alert alert-warning">
                <strong>STUB spec-ready.</strong> Módulo Copiloto scaffoldado em 2026-04-24.
                Esta view Blade é placeholder — será substituída pela Page React
                <code>Pages/Copiloto/Chat.tsx</code> quando o ui/0001 for implementado.
            </div>

            <h4>Conversa atual: {{ $conversa->titulo ?? 'Nova conversa' }}</h4>

            <div style="max-height:400px;overflow:auto;border:1px solid #eee;padding:1em;margin-bottom:1em;">
                @forelse($mensagens as $m)
                    <p><strong>[{{ $m->role }}]</strong> {{ $m->content }}</p>
                @empty
                    <p><em>Nenhuma mensagem ainda. Envie a primeira pra começar.</em></p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('copiloto.conversas.mensagens.store', $conversa->id) }}">
                @csrf
                <div class="form-group">
                    <textarea name="content" class="form-control" rows="3" placeholder="Converse com o Copiloto..."></textarea>
                </div>
                <button class="btn btn-primary" type="submit">Enviar</button>
            </form>

            <hr>
            <a href="{{ route('copiloto.dashboard.index') }}" class="btn btn-default">Ir pro Dashboard</a>
            <a href="{{ route('copiloto.metas.index') }}" class="btn btn-default">Ver Metas</a>
        </div>
    </div>
</section>
@stop
