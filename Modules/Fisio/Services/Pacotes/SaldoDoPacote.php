<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Pacotes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Pacote;
use Modules\Fisio\Entities\PacoteLancamento;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Exceptions\PacoteIndisponivelException;

/**
 * Sessões do pacote (US-FISIO-020, etapa F3 §3.4.2). Único caminho de escrita no ledger:
 * o saldo é sempre a soma dos lançamentos e a correção é lançamento inverso.
 *
 * Não toca em dinheiro. O preço do pacote é da venda do núcleo; quem liga a venda ao pacote
 * é a US-FISIO-022. A Agenda (lote 3) chama `consumir()` com `ref = agendamento:<id>`.
 */
final class SaldoDoPacote
{
    public function abrir(Patient $paciente, string $titulo, int $sessoes, ?string $validade, ?int $porSemana, int $userId): Pacote
    {
        if ($sessoes < 1) {
            throw new \InvalidArgumentException('Pacote precisa de ao menos 1 sessão.');
        }

        return DB::transaction(function () use ($paciente, $titulo, $sessoes, $validade, $porSemana, $userId) {
            $pacote = Pacote::create([
                'business_id' => $paciente->business_id,
                'patient_id' => $paciente->id,
                'title' => $titulo,
                'sessions_total' => $sessoes,
                'sessions_per_week' => $porSemana,
                'valid_until' => $validade,
                'status' => Pacote::STATUS_ATIVO,
                'created_by' => $userId,
            ]);
            $this->lancar($pacote, PacoteLancamento::CREDITO, $sessoes, $userId, 'abertura');

            return $pacote;
        });
    }

    public function saldo(Pacote $pacote): int
    {
        return (int) $this->doPacote($pacote)->sum('sessions');
    }

    /** Validade efetiva: a última extensão registrada, senão a inicial. */
    public function validade(Pacote $pacote): ?Carbon
    {
        $extensao = $this->doPacote($pacote)
            ->where('kind', PacoteLancamento::EXTENSAO)->orderByDesc('id')->value('valid_until');

        return $extensao !== null ? Carbon::parse($extensao) : $pacote->valid_until;
    }

    /** Debita 1 sessão. Mesmo `ref` não debita duas vezes enquanto o débito não foi estornado. */
    public function consumir(Pacote $pacote, int $userId, ?string $ref = null, ?string $nota = null): PacoteLancamento
    {
        return DB::transaction(function () use ($pacote, $userId, $ref, $nota) {
            $pacote = Pacote::whereKey($pacote->id)->where('business_id', $pacote->business_id)->lockForUpdate()->firstOrFail();

            if ($ref !== null && ($existente = $this->debitoVigente($pacote, $ref)) !== null) {
                return $existente;
            }
            if ($pacote->status !== Pacote::STATUS_ATIVO) {
                throw PacoteIndisponivelException::encerrado();
            }
            $validade = $this->validade($pacote);
            if ($validade !== null && $validade->copy()->endOfDay()->isPast()) {
                throw PacoteIndisponivelException::vencido($validade->format('d/m/Y'));
            }
            if ($this->saldo($pacote) < 1) {
                throw PacoteIndisponivelException::semSaldo();
            }

            return $this->lancar($pacote, PacoteLancamento::DEBITO, -1, $userId, $ref, $nota);
        });
    }

    public function estornar(PacoteLancamento $debito, int $userId, string $motivo): PacoteLancamento
    {
        return DB::transaction(function () use ($debito, $userId, $motivo) {
            $pacote = Pacote::whereKey($debito->package_id)->where('business_id', $debito->business_id)->lockForUpdate()->firstOrFail();
            $jaEstornado = $this->doPacote($pacote)
                ->where('ref', 'estorno:'.$debito->id)->exists();
            if ($debito->kind !== PacoteLancamento::DEBITO || $jaEstornado) {
                throw PacoteIndisponivelException::estornoInvalido();
            }

            return $this->lancar($pacote, PacoteLancamento::ESTORNO, 1, $userId, 'estorno:'.$debito->id, $motivo);
        });
    }

    public function estender(Pacote $pacote, string $novaValidade, int $userId, string $motivo): PacoteLancamento
    {
        return $this->lancar($pacote, PacoteLancamento::EXTENSAO, 0, $userId, 'manual', $motivo, $novaValidade);
    }

    /**
     * Lançamentos do pacote, com a empresa explícita além do escopo global (ADR 0093).
     *
     * @return \Illuminate\Database\Eloquent\Builder<PacoteLancamento>
     */
    private function doPacote(Pacote $pacote): \Illuminate\Database\Eloquent\Builder
    {
        return PacoteLancamento::where('business_id', $pacote->business_id)->where('package_id', $pacote->id);
    }

    private function debitoVigente(Pacote $pacote, string $ref): ?PacoteLancamento
    {
        $debito = $this->doPacote($pacote)
            ->where('kind', PacoteLancamento::DEBITO)->where('ref', $ref)->orderByDesc('id')->first();
        if ($debito === null) {
            return null;
        }
        $estornado = $this->doPacote($pacote)->where('ref', 'estorno:'.$debito->id)->exists();

        return $estornado ? null : $debito;
    }

    private function lancar(Pacote $pacote, string $kind, int $sessoes, int $userId, ?string $ref, ?string $nota = null, ?string $validade = null): PacoteLancamento
    {
        return PacoteLancamento::create([
            'business_id' => $pacote->business_id,
            'package_id' => $pacote->id,
            'kind' => $kind,
            'sessions' => $sessoes,
            'valid_until' => $validade,
            'ref' => $ref,
            'note' => $nota,
            'created_by' => $userId,
        ]);
    }
}
