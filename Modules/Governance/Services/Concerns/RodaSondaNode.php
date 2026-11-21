<?php

declare(strict_types=1);

namespace Modules\Governance\Services\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Shell-out para uma sonda Node das linhas do Daily Brief, guardando POR QUE não mediu.
 *
 * Antes, cada *BriefLineService devolvia null quando `node` não existia ou a sonda não
 * produzia JSON — e null também é "nada digno de nota". A linha sumia calada e o brief
 * não distinguia ausência de medição de saúde (§5 proibicoes 2026-07-29, LC-33). No cron
 * do Hostinger, onde `node` não está no PATH (Kernel.php, medido 2026-08-08), 8 linhas
 * sumiam assim. Agora quem chama usa {@see linhaNaoMedida()} e o brief diz "não medido".
 */
trait RodaSondaNode
{
    private ?string $motivoNaoMedido = null;

    /**
     * Roda `node <args>` na raiz e devolve o JSON decodificado; null quando não mediu,
     * com o motivo em $motivoNaoMedido. O exit code sozinho não decide: as sondas saem
     * ≠ 0 quando acham problema e ainda imprimem o JSON — o que decide é haver JSON.
     *
     * @param  array<int, string>  $args
     * @return array<string, mixed>|null
     */
    private function rodarSondaNode(array $args, int $timeoutSegundos, string $tag): ?array
    {
        $this->motivoNaoMedido = null;

        try {
            $result = Process::path(base_path())->timeout($timeoutSegundos)->run(['node', ...$args]);
        } catch (Throwable $e) {
            Log::debug("[{$tag}] shell-out falhou: ".$e->getMessage());
            $this->motivoNaoMedido = 'node indisponível: '.mb_substr($e->getMessage(), 0, 80);

            return null;
        }

        $saida = trim($result->output());
        $decoded = $saida === '' ? null : json_decode($saida, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $exit = $result->exitCode();
        $this->motivoNaoMedido = match (true) {
            $exit === 127 => 'node ausente (exit 127)',
            $saida === '' => "sonda sem saída (exit {$exit})",
            default => "sonda sem JSON legível (exit {$exit})",
        };

        return null;
    }

    /** "⚪ <rótulo>: não medido (<motivo>)", ou null se a última sonda não falhou. */
    private function linhaNaoMedida(string $rotulo): ?string
    {
        return $this->motivoNaoMedido === null
            ? null
            : "⚪ {$rotulo}: não medido ({$this->motivoNaoMedido})";
    }
}
