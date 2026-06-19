<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Services\Memoria\RawChatMiner;

/**
 * PR-3 da estação de ingestão de design ([plano] vectorized-badger · OPCIONAL).
 *
 * `design:mine-raw --tela=vendas` — garimpa o RAW não-curado (CHAT_TRANSCRIPT +
 * chats Cowork que citam a tela) em CANDIDATOS 🔍 (proposta, não lei) via LLM, com
 * gate PII, e escreve em `_prepared/CANDIDATOS-<tela>.md` (efêmero). O humano avalia
 * e promove pro decisoes/charter — NUNCA auto-✅. Raiz configurável (`jana.dossie_root`).
 */
class DesignMineRawCommand extends Command
{
    protected $signature = 'design:mine-raw
                            {--tela= : Tela alvo (ex: vendas)}
                            {--out= : Caminho de saída (default: _incoming/<tela>/_prepared/)}
                            {--dry-run : Mostra os candidatos e NÃO escreve}';

    protected $description = 'Minera o raw (chats Cowork) em candidatos 🔍 de design (proposta human-gated, nunca lei)';

    /** Teto de raw docs alimentados (bound de custo/prompt). */
    private const MAX_DOCS = 8;

    public function handle(RawChatMiner $miner): int
    {
        $tela = (string) $this->option('tela');
        if ($tela === '') {
            $this->error('Use --tela=<tela>.');

            return self::FAILURE;
        }

        $root = rtrim((string) config('jana.dossie_root', base_path()), '/\\');
        $raw = $this->gatherRaw($root, $tela);
        if ($raw === []) {
            $this->warn("Nenhum raw (chat/transcript) encontrado pra tela '{$tela}'.");

            return self::SUCCESS;
        }

        $out = (string) $this->option('out');
        if ($out === '') {
            $out = "{$root}/prototipo-ui/_incoming/{$tela}/_prepared/CANDIDATOS-{$tela}.md";
        }

        $r = $miner->mine($tela, $raw, $out, (bool) $this->option('dry-run'));

        match ((string) ($r['status'] ?? '?')) {
            'written' => $this->info("✓ Candidatos 🔍 escritos em {$out} (PROPOSTA — humano promove; nunca auto-✅)."),
            'dry' => $this->line((string) ($r['candidates'] ?? '')),
            'refused_pii' => $this->error('✗ RECUSADO — LLM emitiu PII (' . implode(',', array_keys($r['pii'] ?? [])) . '). Nada escrito.'),
            'no_raw' => $this->warn('Sem raw a minerar.'),
            default => $this->warn('Status inesperado.'),
        };

        return ($r['status'] ?? '') === 'refused_pii' ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Reúne o raw da tela: CHAT_TRANSCRIPT/*chat*/*transcript* do dir da tela +
     * chats Cowork (cowork-*/chats/*.md) que CITAM a tela. Cap MAX_DOCS.
     *
     * @return list<array{path:string, content:string}>
     */
    private function gatherRaw(string $root, string $tela): array
    {
        $docs = [];
        $needle = mb_strtolower($tela);

        foreach (glob("{$root}/prototipo-ui/prototipos/{$tela}/*.md") ?: [] as $f) {
            $name = mb_strtolower(basename($f));
            if (str_contains($name, 'chat') || str_contains($name, 'transcript')) {
                $docs[] = $this->doc($root, $f);
            }
        }
        foreach (glob("{$root}/prototipo-ui/cowork-*/chats/*.md") ?: [] as $f) {
            if (count($docs) >= self::MAX_DOCS) {
                break;
            }
            $content = (string) @file_get_contents($f);
            if (str_contains(mb_strtolower($content), $needle)) {
                $docs[] = ['path' => $this->rel($root, $f), 'content' => $content];
            }
        }

        return array_slice($docs, 0, self::MAX_DOCS);
    }

    /** @return array{path:string, content:string} */
    private function doc(string $root, string $abs): array
    {
        return ['path' => $this->rel($root, $abs), 'content' => (string) @file_get_contents($abs)];
    }

    private function rel(string $root, string $abs): string
    {
        return str_replace('\\', '/', ltrim(str_replace($root, '', $abs), '/\\'));
    }
}
