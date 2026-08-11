@extends('layouts.app')
@section('title', 'Copiloto — Config alertas')
@section('content')
<section class="content-header"><h1>Configurar alertas</h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <div class="callout callout-warning">
            <p><strong>Esta tela ainda não salva.</strong> Os valores abaixo são os que a Jana
            usa hoje, fixos no código — o formulário validava e descartava o que você digitasse.</p>
            <p>Enquanto a gravação não entra, o alerta dispara com desvio de <strong>10%</strong>
            e chega <strong>in-app</strong>. Para mudar isso agora, fale com o suporte.</p>
        </div>
        <form method="POST" action="{{ route('jana.alertas.config.update') }}">
            @csrf @method('PATCH')
            <div class="form-group"><label>Desvio aceitável (%)</label>
                <input type="number" name="desvio_threshold" class="form-control" value="10" disabled>
            </div>
            <div class="form-group"><label>Canais</label>
                <label><input type="checkbox" value="in_app" checked disabled> In-app</label>
                <label><input type="checkbox" value="email" disabled> Email</label>
                <label><input type="checkbox" value="whatsapp" disabled> WhatsApp</label>
            </div>
            <button class="btn btn-primary" disabled
                    title="A gravação da configuração de alertas ainda não foi implementada.">Salvar</button>
        </form>
    </div></div>
</section>
@stop
