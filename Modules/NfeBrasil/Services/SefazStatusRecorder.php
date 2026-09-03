<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use Modules\NfeBrasil\Models\NfeSefazStatus;

/**
 * US-NFE-006 / ADR TECH-0002 — persiste o resultado do ping da SEFAZ por UF.
 *
 * NÃO faz ping. O ping já tem dono: `NfeService::consultarStatusSefaz()`, que é
 * chamado pelo `nfe:health --ping-sefaz`. Este serviço só GRAVA o que aquele observou —
 * abrir um segundo mecanismo de ping seria régua paralela.
 *
 * A contagem de falhas seguidas é o que transforma um erro isolado em sinal: 1 timeout
 * é ruído de rede; 3 seguidos são a SEFAZ fora. A ADR usa esse limiar para SUGERIR
 * contingência — e apenas sugerir, porque auto-ativação foi rejeitada.
 */
class SefazStatusRecorder
{
    /**
     * Ping respondeu. `$tempoRespostaSeg` vem em SEGUNDOS de consultarStatusSefaz()
     * e é persistido em MILISSEGUNDOS (a coluna é `last_response_ms`).
     */
    public function registrarSucesso(string $uf, float $tempoRespostaSeg): NfeSefazStatus
    {
        return $this->gravar($uf, [
            'status' => NfeSefazStatus::VERDE,
            'last_response_ms' => (int) round($tempoRespostaSeg * 1000),
            'consecutive_failures' => 0,   // sucesso ZERA — senão o alarme nunca desarma
        ]);
    }

    /**
     * Ping falhou (exceção, timeout, cStat != 107).
     *
     * `last_response_ms` vai a NULL de propósito: não houve resposta, e gravar 0 seria
     * dizer "respondeu instantaneamente" — ausência de medição travestida de medição
     * (o fail-open catalogado em proibicoes.md §5, 2026-07-29).
     */
    public function registrarFalha(string $uf): NfeSefazStatus
    {
        $atual = NfeSefazStatus::find($this->normalizar($uf));
        $falhas = ((int) ($atual->consecutive_failures ?? 0)) + 1;

        return $this->gravar($uf, [
            'status' => $falhas >= NfeSefazStatus::FALHAS_PARA_SUGERIR
                ? NfeSefazStatus::VERMELHO
                : NfeSefazStatus::AMARELO,
            'last_response_ms' => null,
            'consecutive_failures' => $falhas,
        ]);
    }

    private function gravar(string $uf, array $campos): NfeSefazStatus
    {
        $uf = $this->normalizar($uf);

        // `last_check_at` é sempre AGORA e sempre gravado: é o carimbo de "eu medi".
        // Sem ele não dá pra distinguir "SEFAZ ok" de "ninguém olhou desde ontem".
        $campos['last_check_at'] = now();

        return NfeSefazStatus::updateOrCreate(['uf' => $uf], $campos);
    }

    private function normalizar(string $uf): string
    {
        return strtoupper(substr(trim($uf), 0, 2));
    }
}
