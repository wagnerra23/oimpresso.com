<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Pacotes;

use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Pacote;
use Modules\Fisio\Entities\PacoteLancamento;
use Modules\Fisio\Services\IdentidadeClinica;

/**
 * Comprovante (voucher) do pacote de sessões para imprimir e entregar ao paciente (US-FISIO-021,
 * UC-FPK-10; pedido do [W] em 2026-11-12: "o voucher é o comprovante do pacote de sessões").
 *
 * Mostra só SESSÕES: título, contratadas, usadas, saldo, validade efetiva, ritmo e as sessões já
 * realizadas (as devolvidas aparecem marcadas). Valor em dinheiro NÃO entra: o preço é da venda do
 * núcleo e o comprovante de pagamento é o recibo/NFS-e (charter da tela, Non-Goals). Da venda vem só
 * o número, para o paciente conferir com o recibo.
 */
final class ComprovantePacote
{
    public function __construct(
        private readonly SaldoDoPacote $saldo,
        private readonly IdentidadeClinica $identidade,
    ) {}

    /** @return array<string, mixed> */
    public function montar(Pacote $pacote, int $biz): array
    {
        $pacote->loadMissing('patient.contact:id,name');
        $lancamentos = PacoteLancamento::query()->where('business_id', $biz)->where('package_id', $pacote->id);
        $estornados = (clone $lancamentos)->where('kind', PacoteLancamento::ESTORNO)->pluck('ref')
            ->map(fn ($r) => (int) str_replace('estorno:', '', (string) $r))->all();
        $sessoes = (clone $lancamentos)->where('kind', PacoteLancamento::DEBITO)->orderBy('id')->get(['id', 'created_at'])
            ->values()
            ->map(fn (PacoteLancamento $l, int $i) => [
                'n' => $i + 1,
                'data' => $l->created_at?->format('d/m/Y'),
                'devolvida' => in_array((int) $l->id, $estornados, true),
            ])->all();

        $saldo = $this->saldo->saldo($pacote);
        $validade = $this->saldo->validade($pacote);
        $venda = $pacote->transaction_id === null ? null
            : DB::table('transactions')->where('business_id', $biz)->where('id', $pacote->transaction_id)->value('invoice_no');

        return [
            'clinica' => $this->identidade->montar($biz),
            'pacote_id' => (int) $pacote->id,
            'paciente' => ['nome' => $pacote->patient?->contact?->name],
            'titulo' => $pacote->title,
            'contratadas' => (int) $pacote->sessions_total,
            'usadas' => (int) $pacote->sessions_total - $saldo,
            'saldo' => $saldo,
            'por_semana' => $pacote->sessions_per_week,
            'aberto_em' => $pacote->created_at?->format('d/m/Y'),
            'validade' => $validade?->format('d/m/Y'),
            'vencido' => $validade !== null && $validade->isBefore(now()->startOfDay()),
            'encerrado' => $pacote->status === Pacote::STATUS_ENCERRADO,
            'venda' => $venda !== null ? (string) $venda : null,
            'sessoes' => $sessoes,
            'emitido_em' => now()->format('d/m/Y H:i'),
        ];
    }
}
