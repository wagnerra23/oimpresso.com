<?php

declare(strict_types=1);

namespace Modules\Forja\Console\Commands;

use App\Support\Privacy\CredentialShapes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Varre (e opcionalmente redige) CREDENCIAL em `mcp_cc_messages` + `mcp_cc_blobs`.
 *
 * ── POR QUE EXISTE ───────────────────────────────────────────────────────────
 * A redação do `scripts/cc-watcher/redact.mjs` protege o que entra DAQUI PRA
 * FRENTE. Ela não diz nada sobre o que já foi ingerido — e em 2026-09-16 a
 * primeira varredura achou 24 linhas com credencial, de julho/agosto, incluindo
 * senha de PayPal de produção, numa tabela que o time lê pelo `cc-search`.
 *
 * Aquela limpeza foi feita por script solto via SSH, e a regra Tier 0 de
 * `memory/proibicoes.md` ("mexeu, registra") manda que UPDATE direto em prod
 * vire comando idempotente commitado. Este comando é essa dívida paga: a mesma
 * operação, repetível, testada e auditável.
 *
 * ⚠️ Redigir NÃO desfaz exposição. Quem tinha acesso ao `cc-search` pôde ler o
 * valor enquanto ele esteve lá. O remédio é ROTACIONAR a credencial; este
 * comando só impede leitura futura, e não deve fingir que faz mais que isso.
 *
 * Dry-run por padrão. `--apply` escreve em transação. Idempotente: rodar de
 * novo depois de aplicar acha 0.
 *
 * @see \App\Support\Privacy\CredentialShapes    vocabulário (paridade com redact.mjs)
 * @see \App\Console\Commands\SecretsScanCommand irmão, outro corpus (arquivos do repo)
 */
class CcSecretSweepCommand extends Command
{
    protected $signature = 'cc:secret-sweep
                            {--apply : escreve as redacoes (sem esta flag e dry-run)}
                            {--fail-on-find : exit 1 se achar credencial (uso como gate)}';

    protected $description = 'Varre/redige shapes de credencial em mcp_cc_messages e mcp_cc_blobs (dry-run por padrao)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');

        $this->info($aplicar
            ? '[cc:secret-sweep] APLICANDO'
            : '[cc:secret-sweep] dry-run (nada sera escrito)');

        $plano = array_merge($this->planejarMensagens(), $this->planejarBlobs());

        if ($plano === []) {
            $this->info('[cc:secret-sweep] nenhuma credencial encontrada');

            return self::SUCCESS;
        }

        $this->relatar($plano);

        $colisoes = array_filter($plano, static fn (array $p): bool => $p['colide'] ?? false);
        if ($colisoes !== []) {
            $this->error('[cc:secret-sweep] ABORTADO: '.count($colisoes).' blob(s) colidiriam com hash existente');

            return self::FAILURE;
        }

        if (! $aplicar) {
            $this->warn('[cc:secret-sweep] dry-run — rode com --apply para escrever');

            return $this->option('fail-on-find') ? self::FAILURE : self::SUCCESS;
        }

        $this->aplicar($plano);

        $this->info('[cc:secret-sweep] escrito. Redigir NAO desfaz exposicao — rotacione as credenciais afetadas.');

        return self::SUCCESS;
    }

    /** @return list<array<string,mixed>> */
    private function planejarMensagens(): array
    {
        $plano = [];
        $ultimo = 0;

        while (true) {
            $rows = DB::table('mcp_cc_messages')
                ->select('id', 'session_id', 'content_text', 'content_json')
                ->where('id', '>', $ultimo)
                ->orderBy('id')
                ->limit(500)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $r) {
                $ultimo = (int) $r->id;
                $hits = [];
                $ct = (string) ($r->content_text ?? '');
                $cj = (string) ($r->content_json ?? '');
                $ctNovo = CredentialShapes::redigir($ct, $hits);
                $cjNovo = CredentialShapes::redigir($cj, $hits);

                if ($hits === []) {
                    continue;
                }

                $plano[] = [
                    'tipo' => 'msg',
                    'id' => (int) $r->id,
                    'sessao' => (string) $r->session_id,
                    'hits' => $hits,
                    'antes' => strlen($ct) + strlen($cj),
                    'depois' => strlen($ctNovo) + strlen($cjNovo),
                    'ct' => $ctNovo,
                    'cj' => $cjNovo,
                    'mudouCt' => $ct !== $ctNovo,
                    'mudouCj' => $cj !== $cjNovo,
                ];
            }
        }

        return $plano;
    }

    /**
     * O conteúdo do blob vive COMPRIMIDO (`zlib_encode`). Procurar uma coluna
     * `content`/`body` não acha nada e reporta 0 — não-medição travestida de
     * "nenhuma", que foi exatamente o erro da primeira varredura em 2026-09-16.
     *
     * @return list<array<string,mixed>>
     */
    private function planejarBlobs(): array
    {
        $plano = [];
        $ultimo = 0;

        while (true) {
            $rows = DB::table('mcp_cc_blobs')
                ->select('id', 'compressed_data', 'hash_sha256', 'refs_count')
                ->where('id', '>', $ultimo)
                ->orderBy('id')
                ->limit(50)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $r) {
                $ultimo = (int) $r->id;
                $texto = @zlib_decode($r->compressed_data);

                if ($texto === false || $texto === null) {
                    $this->warn('  blob #'.$r->id.': NAO descomprimiu — nao foi medido');

                    continue;
                }

                $hits = [];
                $novo = CredentialShapes::redigir($texto, $hits);

                if ($hits === []) {
                    continue;
                }

                $novoHash = hash('sha256', $novo);

                $plano[] = [
                    'tipo' => 'blob',
                    'id' => (int) $r->id,
                    'sessao' => '-',
                    'hits' => $hits,
                    'antes' => strlen($texto),
                    'depois' => strlen($novo),
                    'novo' => $novo,
                    'hashNovo' => $novoHash,
                    'refs' => (int) $r->refs_count,
                    'colide' => DB::table('mcp_cc_blobs')
                        ->where('hash_sha256', $novoHash)
                        ->where('id', '!=', $r->id)
                        ->exists(),
                ];
            }
        }

        return $plano;
    }

    /** @param  list<array<string,mixed>>  $plano */
    private function relatar(array $plano): void
    {
        $totalPorShape = [];

        foreach ($plano as $p) {
            foreach ($p['hits'] as $shape => $n) {
                $totalPorShape[$shape] = ($totalPorShape[$shape] ?? 0) + $n;
            }

            $shapes = implode(', ', array_map(
                static fn (string $k, int $v): string => $k.' x'.$v,
                array_keys($p['hits']),
                $p['hits']
            ));

            $extra = $p['tipo'] === 'blob'
                ? '  refs='.$p['refs'].(($p['colide'] ?? false) ? '  *** COLIDE ***' : '')
                : '';

            $this->line(sprintf(
                '  [%s] #%d sess=%s  %d->%d bytes  %s%s',
                $p['tipo'],
                $p['id'],
                $p['sessao'],
                $p['antes'],
                $p['depois'],
                $shapes,
                $extra
            ));
        }

        $this->newLine();

        foreach ($totalPorShape as $shape => $n) {
            $this->line(sprintf('  %-16s %d', $shape, $n));
        }
    }

    /** @param  list<array<string,mixed>>  $plano */
    private function aplicar(array $plano): void
    {
        DB::transaction(function () use ($plano): void {
            foreach ($plano as $p) {
                if ($p['tipo'] === 'msg') {
                    $upd = [];

                    if ($p['mudouCt']) {
                        $upd['content_text'] = $p['ct'];
                    }

                    if ($p['mudouCj']) {
                        $upd['content_json'] = $p['cj'];
                    }

                    if ($upd !== []) {
                        DB::table('mcp_cc_messages')->where('id', $p['id'])->update($upd);
                    }

                    continue;
                }

                // Os QUATRO campos do blob andam juntos ou o registro fica
                // incoerente: hash_sha256 é do conteúdo DESCOMPRIMIDO, e os dois
                // tamanhos são lidos por quem audita dedup.
                $comprimido = zlib_encode($p['novo'], ZLIB_ENCODING_DEFLATE, 6);

                DB::table('mcp_cc_blobs')->where('id', $p['id'])->update([
                    'compressed_data' => $comprimido,
                    'hash_sha256' => $p['hashNovo'],
                    'size_original_bytes' => strlen($p['novo']),
                    'size_compressed_bytes' => strlen((string) $comprimido),
                ]);
            }
        });
    }
}
