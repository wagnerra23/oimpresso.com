<?php

declare(strict_types=1);

namespace Modules\Arquivos\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Entities\Arquivo;

/**
 * RetencaoStatsReader — a 4ª vista da tela de Arquivos (US-ARQ-013, onda 1 · PR-3).
 *
 * Responde o que o charter pede da vista **Retenção**: *"o que vence em 30/90 dias por
 * `sub_destination`, com a base legal ao lado do prazo, o que está no grace de 30 dias, e o
 * que passou do prazo sem ser apagado (o WARN do `HealthCheckCommand` check #4)"*.
 *
 * **Leitura pura.** Não apaga, não avisa titular, não enfileira, não roda o comando. Isso é
 * a onda 3 e depende da proposta de ADR `arquivos-retencao-ui-aviso-titular`.
 *
 * ## Por que conta em PHP e não em SQL
 *
 * O prazo de um arquivo NÃO é um número só: é `retention_days` da própria linha quando
 * existe e, quando não, o prazo do `sub_destination` na policy — exatamente a regra que o
 * `ArquivosAdminController::linha()` já aplica no acervo. Reproduzir isso em SQL exigiria
 * aritmética de data com CASE sobre 8 contextos, em dialeto (`DATE_ADD` do MySQL não
 * existe no SQLite da outra lane).
 *
 * A alternativa seria usar `ArquivosRetentionService::summary()`, que já existe — mas ele
 * responde por **prazo global** (`retention_days_default`), e o charter pede POR CONTEXTO.
 * Um número global apresentado como se fosse por contexto seria o mesmo defeito que a
 * coluna "Vence em" tinha antes: número certo respondendo a pergunta errada.
 *
 * Então lê 4 colunas por `chunkById` e conta em PHP, com a MESMA regra do acervo. É exato
 * e portável; o custo é linear no acervo do business, que é o mesmo conjunto que a vista
 * do Cofre já percorre.
 *
 * @see resources/js/Pages/Arquivos/Index.charter.md
 * @see Modules/Arquivos/Console/Commands/RetentionCleanupCommand.php  (quem de fato apaga)
 */
class RetencaoStatsReader
{
    /** Tamanho do lote do `chunkById`. Só 4 colunas viajam por linha. */
    private const LOTE = 1000;

    /**
     * @return array{
     *   disponivel: bool,
     *   grace_dias: int,
     *   notice_dias: int,
     *   estrategia: string,
     *   agendado: bool,
     *   kpis: array{vence_30:int, vence_90:int, no_grace:int, passou_do_prazo:int},
     *   por_contexto: array<string, int>
     * }
     */
    public function fetch(): array
    {
        if ($this->businessIdDaSessao() === null || ! Schema::hasTable('arquivos')) {
            return $this->vazio();
        }

        // Span porque este e o metodo mais caro da tela: percorre o acervo INTEIRO por
        // chunk pra aplicar o prazo linha a linha. Num tenant grande e onde a Retencao
        // vai doer primeiro, e o trace e o que separa "a tela esta lenta" de "o chunk
        // esta lento". Zero-cost com `otel.enabled=false`.
        return OtelHelper::spanBiz('arquivos.retencao.fetch', function () {
            return $this->calcular();
        }, ['module' => 'Arquivos', 'component' => 'arquivos.retencao']);
    }

    /**
     * O calculo em si — extraido do `fetch()` pra que o span envolva a operacao inteira
     * sem aninhar o corpo num closure gigante.
     *
     * @return array<string, mixed>
     */
    private function calcular(): array
    {
        $policy  = (array) config('arquivos.retention_days_policy', []);
        $default = (int) (config('arquivos.retention_days_default', 90) ?: 90);
        $grace   = $this->graceDias();

        $hoje = now()->startOfDay();

        $vence30 = $vence90 = $passou = $noGrace = 0;
        $porContexto = [];

        // `withTrashed`: o grace só existe para quem JÁ foi soft-deleted, e o scope padrão
        // esconderia exatamente essas linhas. As outras contagens ignoram as apagadas —
        // arquivo no grace não "vence", ele já saiu.
        Arquivo::query()
            ->withTrashed()
            ->select(['id', 'sub_destination', 'retention_days', 'created_at', 'deleted_at'])
            ->chunkById(self::LOTE, function ($linhas) use (
                $policy, $default, $grace, $hoje,
                &$vence30, &$vence90, &$passou, &$noGrace, &$porContexto
            ) {
                foreach ($linhas as $a) {
                    if ($a->deleted_at !== null) {
                        // Dentro do grace = ainda dá pra restaurar. Passado ele, é o job
                        // que apaga de verdade — e aí a linha some desta contagem.
                        if ($a->deleted_at->copy()->addDays($grace)->greaterThanOrEqualTo($hoje)) {
                            $noGrace++;
                        }

                        continue;
                    }

                    $sub = (string) ($a->sub_destination ?? 'default');
                    $porContexto[$sub] = ($porContexto[$sub] ?? 0) + 1;

                    if ($a->created_at === null) {
                        continue;
                    }

                    // MESMA regra do acervo (`linha()`): a linha manda, a policy completa.
                    $dias = (int) ($a->retention_days ?: ($policy[$sub] ?? $default));
                    $restam = (int) $hoje->diffInDays($a->created_at->copy()->addDays($dias), false);

                    if ($restam <= 0) {
                        $passou++;
                    } elseif ($restam <= 30) {
                        $vence30++;
                        $vence90++;   // 30 dias É um subconjunto de 90 — o card de 90 conta os dois
                    } elseif ($restam <= 90) {
                        $vence90++;
                    }
                }
            });

        return [
            'disponivel'   => true,
            'grace_dias'   => $grace,
            'notice_dias'  => (int) (config('arquivos_retention.notice_period_days', 30) ?: 30),
            'estrategia'   => (string) (config('arquivos_retention.strategy', 'hard_delete') ?: 'hard_delete'),
            'agendado'     => $this->agendado(),
            'kpis'         => [
                'vence_30'        => $vence30,
                'vence_90'        => $vence90,
                'no_grace'        => $noGrace,
                'passou_do_prazo' => $passou,
            ],
            'por_contexto' => $porContexto,
        ];
    }

    /**
     * O `arquivos:retention-cleanup` está AGENDADO?
     *
     * A tela precisa dizer isto porque, sem agendamento, ela estaria mostrando "o que o job
     * faria hoje" para um job que ninguém marcou — e a própria proposta de ADR exige que a
     * vista declare que a execução é manual. Medido em 2026-08-24 e reconfirmado aqui: não
     * há entrada de schedule para o comando.
     *
     * Pergunta ao RUNTIME (`Schedule::events()`), nunca ao fonte: "quem roda" é pergunta de
     * runtime, e deduzir isso lendo Kernel/ServiceProvider é a lápide de 2026-07-17 — num
     * app modular o schedule tem várias fontes e grep é incompleto por construção.
     */
    private function agendado(): bool
    {
        try {
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

            foreach ($schedule->events() as $evento) {
                if (str_contains((string) $evento->command, 'arquivos:retention-cleanup')) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Sem scheduler resolvível (contexto de teste puro), a resposta honesta é
            // "não sei" — e a tela trata como não-agendado, que é o estado conservador:
            // ela avisa que a execução é manual em vez de prometer automação.
            return false;
        }

        return false;
    }

    /** Grace do `retention.php` — o arquivo que o provider passou a registrar em 2026-08-25. */
    private function graceDias(): int
    {
        $g = (int) config('arquivos_retention.grace_period_days', 30);

        return $g > 0 ? $g : 30;
    }

    /**
     * MESMA fonte do global scope do `Arquivo`. Portão fail-closed: sem business resolvido,
     * o scope não filtra (`if ($businessId !== null)`) e a vista contaria o sistema inteiro.
     */
    private function businessIdDaSessao(): ?int
    {
        $id = session('user.business_id') ?? session('business.id');

        return $id === null ? null : (int) $id;
    }

    /** @return array<string, mixed> */
    private function vazio(): array
    {
        return [
            'disponivel'   => false,
            'grace_dias'   => $this->graceDias(),
            'notice_dias'  => (int) (config('arquivos_retention.notice_period_days', 30) ?: 30),
            'estrategia'   => (string) (config('arquivos_retention.strategy', 'hard_delete') ?: 'hard_delete'),
            'agendado'     => false,
            'kpis'         => ['vence_30' => 0, 'vence_90' => 0, 'no_grace' => 0, 'passou_do_prazo' => 0],
            'por_contexto' => [],
        ];
    }
}
