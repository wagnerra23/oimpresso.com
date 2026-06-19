<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Memoria;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Modules\Jana\Services\Privacy\PiiRedactor;

/**
 * PR-3 da estação de ingestão de design ([plano] vectorized-badger · OPCIONAL).
 *
 * Minera o RAW não-curado (chats/transcripts do Cowork) em CANDIDATOS 🔍 (ideias a
 * AVALIAR) — proposta, NUNCA lei. O humano lê e PROMOVE pro `<tela>.decisoes.md`/
 * charter (anel Adotar) ou descarta. Distingue do dossiê (PR-1): o dossiê MONTA o
 * curado existente; este garimpa o que NÃO foi curado ainda. Não reprocessa decisão
 * humana (o adversário matou isso) — só o raw bruto.
 *
 * Tier 0: LLM → PiiRedactor.detect → RECUSA escrever se PII estruturada (repo público).
 * `renderCandidates()` é PURO/testável; `mine()` chama o LLM (testado com Ai::fakeAgent).
 */
final class RawChatMiner
{
    /** Teto de chars por raw doc no prompt (chats chegam a 200KB — bound determinístico). */
    public const RAW_CHARS_CAP = 6000;

    public function __construct(private PiiRedactor $pii) {}

    /**
     * @param  list<array{path:string, content:string}>  $rawDocs
     * @return array{status:string, written:bool, candidates?:string, pii?:array<string,int>}
     *         status ∈ {written, dry, refused_pii, no_raw}
     */
    public function mine(string $tela, array $rawDocs, string $outPath, bool $dryRun = false): array
    {
        if ($rawDocs === []) {
            return ['status' => 'no_raw', 'written' => false];
        }

        $agent = new AnonymousAgent(
            instructions: $this->systemPrompt($tela),
            messages: [],
            tools: [],
        );
        $body = trim((string) $agent->prompt($this->userPrompt($rawDocs)));

        $detected = $this->pii->detect($body);
        if ($detected !== []) {
            Log::channel('copiloto-ai')->warning('RawChatMiner: recusado por PII', [
                'tela' => $tela,
                'pii_types' => array_keys($detected),
            ]);

            return ['status' => 'refused_pii', 'written' => false, 'pii' => $detected];
        }

        $content = self::renderCandidates($tela, $body, $rawDocs);

        if ($dryRun) {
            return ['status' => 'dry', 'written' => false, 'candidates' => $content];
        }

        @mkdir(dirname($outPath), 0o775, true);
        file_put_contents($outPath, $content);

        return ['status' => 'written', 'written' => true, 'candidates' => $content];
    }

    /**
     * Monta o doc de candidatos 🔍 (PROPOSTA, não lei) + proveniência do raw. PURO.
     *
     * @param  list<array{path:string, content:string}>  $rawDocs
     */
    public static function renderCandidates(string $tela, string $llmBody, array $rawDocs): string
    {
        $prov = implode("\n", array_map(
            static fn ($d) => '- `' . (string) ($d['path'] ?? '?') . '`',
            $rawDocs,
        ));

        return "---\n"
            . "tela: {$tela}\n"
            . "tipo: candidatos-de-design (🔍 AVALIAR — proposta, NÃO lei)\n"
            . "gerado_por: design:mine-raw\n"
            . "---\n\n"
            . "# Candidatos de design (🔍) — {$tela}\n\n"
            . "> **PROPOSTA, NÃO LEI.** Minerado do RAW (chats Cowork) — é sugestão pra AVALIAR, não decisão. "
            . "O humano promove pro `decisoes`/charter (anel Adotar) ou descarta. **Nunca auto-✅.** "
            . "Não reprocessa o que o charter/decisoes já curaram.\n\n"
            . "## Ideias candidatas\n\n"
            . ($llmBody === '' ? '_(o miner não extraiu candidatos)_' : $llmBody) . "\n\n"
            . "## Proveniência (raw minerado)\n\n"
            . ($prov === '' ? '_(nenhum)_' : $prov) . "\n";
    }

    private function systemPrompt(string $tela): string
    {
        return <<<PROMPT
        Você garimpa IDEIAS CANDIDATAS de design de um RAW não-curado (transcript de chat
        Cowork) sobre a tela "{$tela}". O objetivo é levantar o que merece AVALIAÇÃO — NÃO decidir.

        REGRAS DURAS:
        - Liste cada ideia como bullet começando com "🔍 " (marca de "Avaliar").
        - É PROPOSTA, não lei: NUNCA escreva "adotado"/"decidido"/"✅". Quem decide é o humano.
        - Cite a evidência do chat em 1 frase ("no chat, X sugeriu Y porque Z").
        - Português brasileiro, conciso. No máximo ~10 candidatos (os mais relevantes).
        - NUNCA inclua PII: nada de CPF, CNPJ, e-mail, telefone, CEP. Repo é PÚBLICO.
        - Não invente: se o raw não traz ideia clara, diga "sem candidatos claros".
        - Não repita o que já é óbvio/curado — foque no que está solto no chat e ainda não virou decisão.
        PROMPT;
    }

    /** @param list<array{path:string, content:string}> $rawDocs */
    private function userPrompt(array $rawDocs): string
    {
        $blocos = [];
        foreach ($rawDocs as $d) {
            $path = (string) ($d['path'] ?? '?');
            $content = (string) ($d['content'] ?? '');
            $blocos[] = "### RAW: {$path}\n\n" . mb_substr($content, 0, self::RAW_CHARS_CAP);
        }

        return "Raw a garimpar (pode estar truncado):\n\n" . implode("\n\n---\n\n", $blocos);
    }
}
