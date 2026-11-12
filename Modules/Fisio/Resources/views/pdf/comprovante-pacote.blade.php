{{--
    Comprovante (voucher) do pacote de sessões para entregar ao paciente (US-FISIO-021, UC-FPK-10).
    Base comum dos impressos (pdf/_base). Só sessões: valor em dinheiro é da venda do núcleo e o
    comprovante de pagamento é o recibo ou a NFS-e — ver Services/Pacotes/ComprovantePacote.php.
--}}
@extends('fisio::pdf._base', ['documento' => [
    'titulo' => 'Comprovante do pacote',
    'numero' => 'Pacote nº '.$pacote_id,
]])

@section('estilos')
<style>
    body { font-size: 12pt; line-height: 1.35; }
    .titulo-doc { font-size: 20pt; margin-bottom: 1mm; }
    .paciente { font-size: 14pt; margin-bottom: 5mm; }
    .resumo { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    .resumo td { border: 0.75pt solid #c9c7c2; padding: 3mm 4mm; text-align: center; width: 33%; }
    .resumo .num { font-size: 24pt; font-weight: bold; display: block; }
    .resumo .rot { font-size: 10pt; color: #5a5650; text-transform: uppercase; letter-spacing: 0.5pt; }
    .dados { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    .dados td { padding: 1.5mm 0; border-bottom: 0.5pt solid #e2e0dc; }
    .dados td.r { color: #5a5650; width: 45mm; }
    .aviso { border: 1.25pt solid #1d1a15; padding: 2.5mm 4mm; margin-bottom: 5mm; font-weight: bold; }
    h2 { font-size: 13pt; margin: 0 0 2mm; }
    .sessoes { width: 100%; border-collapse: collapse; }
    .sessoes th, .sessoes td { text-align: left; padding: 1.5mm 2mm; border-bottom: 0.5pt solid #e2e0dc; }
    .sessoes th { font-size: 10pt; color: #5a5650; }
    .devolvida { color: #5a5650; text-decoration: line-through; }
    .nota { font-size: 10pt; color: #5a5650; margin-top: 6mm; border-top: 0.75pt dashed #c9c7c2; padding-top: 3mm; }
</style>
@endsection

@section('conteudo')
<h1 class="titulo-doc">{{ $titulo }}</h1>
<div class="paciente">{{ $paciente['nome'] ?? '' }}</div>

@if($encerrado)
    <div class="aviso">Pacote encerrado.</div>
@elseif($vencido)
    <div class="aviso">Validade vencida em {{ $validade }}.</div>
@endif

<table class="resumo"><tr>
    <td><span class="num">{{ $contratadas }}</span><span class="rot">sessões contratadas</span></td>
    <td><span class="num">{{ $usadas }}</span><span class="rot">usadas</span></td>
    <td><span class="num">{{ $saldo }}</span><span class="rot">restam</span></td>
</tr></table>

<table class="dados">
    <tr><td class="r">Aberto em</td><td>{{ $aberto_em ?? '—' }}</td></tr>
    <tr><td class="r">Válido até</td><td>{{ $validade ?? 'sem prazo' }}</td></tr>
    @if($por_semana)<tr><td class="r">Ritmo combinado</td><td>{{ $por_semana }} por semana</td></tr>@endif
    @if($venda)<tr><td class="r">Venda</td><td>nº {{ $venda }}</td></tr>@endif
</table>

<h2>Sessões realizadas</h2>
@if(count($sessoes) === 0)
    <p>Nenhuma sessão usada ainda.</p>
@else
    <table class="sessoes">
        <thead><tr><th>Nº</th><th>Data</th><th></th></tr></thead>
        <tbody>
        @foreach($sessoes as $s)
            <tr class="{{ $s['devolvida'] ? 'devolvida' : '' }}"><td>{{ $s['n'] }}</td><td>{{ $s['data'] }}</td><td>{{ $s['devolvida'] ? 'devolvida ao pacote' : 'realizada' }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="nota">
    Comprovante das sessões do pacote, emitido em {{ $emitido_em }}. Não é recibo de pagamento nem nota fiscal.
</div>
@endsection
