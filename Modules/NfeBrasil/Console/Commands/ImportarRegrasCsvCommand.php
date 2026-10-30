<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Models\NfeOperacaoFiscal;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;

/**
 * Carga de regras tributárias por arquivo versionado — o mesmo ImportRegrasCsvService da tela
 * /nfe-brasil/tributacao/import, sem tela. Nasceu para a migração do legado (Sabor Brasil · CSV de
 * `scripts/legacy-migration/extract-regras-fiscais.py`).
 *
 * Padrão = DRY-RUN: mostra, linha a linha, a regra de hoje e a que ficaria, e não grava nada.
 * Só `--apply` grava, numa transação. Rodar duas vezes não duplica (chave NCM+UF+UF+regime).
 */
class ImportarRegrasCsvCommand extends Command
{
    protected $signature = 'nfe:importar-regras-csv
        {business : business_id que recebe as regras}
        {arquivo : caminho do CSV}
        {--apply : grava (sem isto é só a conferência)}
        {--criar-operacoes : cria as operações citadas na coluna `operacao` e ausentes na empresa (segue o dry-run)}';

    protected $description = 'Carrega regras tributárias de um CSV (dry-run por padrão)';

    public function handle(ImportRegrasCsvService $service): int
    {
        $businessId = (int) $this->argument('business');
        $arquivo = (string) $this->argument('arquivo');

        if (! DB::table('business')->where('id', $businessId)->exists()) {
            $this->error("Business {$businessId} não existe.");

            return self::FAILURE;
        }
        if (! is_file($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        $resultado = $service->parse((string) file_get_contents($arquivo));
        foreach ($resultado['erros'] as $erro) {
            $this->warn("linha {$erro['linha']}: {$erro['motivo']}");
        }
        if ($resultado['erros'] !== []) {
            $this->error(count($resultado['erros']) . ' linha(s) com erro — nada foi gravado.');

            return self::FAILURE;
        }

        $criar = $this->option('criar-operacoes') ? $this->operacoesAusentes($businessId, $resultado['linhas']) : [];
        foreach ($criar as $slug) {
            $this->line("criaria operação {$slug} ({$this->nomeDaOperacao($slug)})");
        }

        $this->table(['NCM', 'regime', 'operação', 'CFOP', 'hoje', 'fica'], array_map(
            fn (array $l) => [$l['ncm'], $l['crt'] ?? '-', $l['operacao'] ?? 'padrão', $l['cfop'], $this->hoje($businessId, $l), $this->descrever($l)],
            $resultado['linhas'],
        ));

        if (! $this->option('apply')) {
            $this->info(count($resultado['linhas']) . ' regra(s) e ' . count($criar) . ' operação(ões) a criar conferida(s). DRY-RUN: nada gravado. Use --apply para gravar.');

            return self::SUCCESS;
        }

        $totais = DB::transaction(function () use ($service, $businessId, $resultado, $criar) {
            $agora = now();
            foreach ($criar as $slug) {
                // Mesmos campos da migration de seed da operação "venda"; regra_geral NULL = usa a regra da operação.
                NfeOperacaoFiscal::withoutGlobalScopes() // SUPERADMIN: console; o escopo é o business_id explícito
                    ->insertOrIgnore([[
                        'business_id' => $businessId, 'slug' => $slug, 'nome' => $this->nomeDaOperacao($slug),
                        'finalidade' => 1, 'cfop' => null, 'regra_geral' => null, 'padrao' => false,
                        'created_at' => $agora, 'updated_at' => $agora,
                    ]]);
            }
            if ($criar !== []) {
                $this->info('operações criadas: ' . implode(', ', $criar));
            }

            return $service->aplicar($businessId, $resultado['linhas']);
        });
        $this->info("criadas {$totais['criadas']} · atualizadas {$totais['atualizadas']} · falhas {$totais['falhas']}");

        return $totais['falhas'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> slugs citados no CSV que a empresa ainda não tem */
    private function operacoesAusentes(int $businessId, array $linhas): array
    {
        $citadas = array_values(array_unique(array_filter(array_map(fn (array $l) => $l['operacao'] ?? null, $linhas))));
        if ($citadas === []) {
            return [];
        }
        $existentes = NfeOperacaoFiscal::withoutGlobalScopes() // SUPERADMIN: console; o escopo é o where explícito
            ->where('business_id', $businessId)->whereIn('slug', $citadas)->whereNull('deleted_at')->pluck('slug')->all();

        return array_values(array_diff($citadas, $existentes));
    }

    private function nomeDaOperacao(string $slug): string
    {
        return ['bonificacao' => 'Bonificação'][$slug] ?? Str::headline($slug);
    }

    private function hoje(int $businessId, array $linha): string
    {
        $operacaoId = null;
        if (($linha['operacao'] ?? null) !== null) {
            $operacaoId = NfeOperacaoFiscal::withoutGlobalScopes() // SUPERADMIN: console; o escopo é o where explícito
                ->where('business_id', $businessId)->where('slug', $linha['operacao'])->whereNull('deleted_at')->value('id');
            if ($operacaoId === null) {
                return "(operação {$linha['operacao']} não existe — a linha vai falhar)";
            }
        }

        $regra = NfeFiscalRule::where('business_id', $businessId)
            ->when(array_key_exists('operacao', $linha) || NfeFiscalRule::temVersionamento(), fn ($q) => $operacaoId === null ? $q->whereNull('operacao_id') : $q->where('operacao_id', $operacaoId))
            ->where('ncm', $linha['ncm'])
            ->where('uf_origem', $linha['uf_origem'])
            ->when($linha['uf_destino'] === null, fn ($q) => $q->whereNull('uf_destino'), fn ($q) => $q->where('uf_destino', $linha['uf_destino']))
            ->when(array_key_exists('crt', $linha), fn ($q) => $linha['crt'] === null ? $q->whereNull('crt') : $q->where('crt', $linha['crt']))
            ->first();

        return $regra === null ? '(nova)' : $this->descrever($regra->toArray());
    }

    private function descrever(array $r): string
    {
        $codigo = ($r['cst'] ?? null) ? 'CST ' . $r['cst'] : 'CSOSN ' . ($r['csosn'] ?? '-');
        $pct = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format((float) $v * 100, 2, ',', ''), '0'), ',') . '%';

        return "{$codigo} {$r['cfop']} · ICMS {$pct($r['aliquota_icms'] ?? 0)} · red. {$pct($r['reducao_base'] ?? null)} · MVA {$pct($r['mva'] ?? null)}";
    }
}
