<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\NfeBrasil\Models\NfeFiscalRule;
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
        {--apply : grava (sem isto é só a conferência)}';

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

        $this->table(['NCM', 'regime', 'CFOP', 'hoje', 'fica'], array_map(
            fn (array $l) => [$l['ncm'], $l['crt'] ?? '-', $l['cfop'], $this->hoje($businessId, $l), $this->descrever($l)],
            $resultado['linhas'],
        ));

        if (! $this->option('apply')) {
            $this->info(count($resultado['linhas']) . ' regra(s) conferida(s). DRY-RUN: nada gravado. Use --apply para gravar.');

            return self::SUCCESS;
        }

        $totais = DB::transaction(fn () => $service->aplicar($businessId, $resultado['linhas']));
        $this->info("criadas {$totais['criadas']} · atualizadas {$totais['atualizadas']} · falhas {$totais['falhas']}");

        return $totais['falhas'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function hoje(int $businessId, array $linha): string
    {
        $regra = NfeFiscalRule::where('business_id', $businessId)
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
