<?php

declare(strict_types=1);

namespace App\Backup\ExportacaoEmpresa;

/**
 * Gera o zip com os dados de UMA empresa: um CSV por tabela + `_MANIFEST.json` (US-BKP-001).
 *
 * Recebe `$businessId` explícito — roda em job/CLI, onde não há sessão (ADR 0093).
 * As linhas vêm SÓ de `TabelasDaEmpresa::consulta()`, que nunca devolve consulta sem filtro.
 * CSV em UTF-8 com BOM e `;`, que abre direto no Excel em português (padrão do Financeiro).
 * Zip sem cifra, por decisão [W] 2026-10-27: a proteção é a permissão e o link que expira.
 */
final class ExportadorDaEmpresa
{
    private const LOTE = 1000;

    public function __construct(private readonly TabelasDaEmpresa $tabelas) {}

    /** @return array{business_id: int, gerado_em: string, tabelas: array<string, int>} */
    public function exportar(int $businessId, string $caminhoZip): array
    {
        if ($businessId < 1) {
            throw new \InvalidArgumentException('business_id obrigatório.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($caminhoZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Não foi possível criar o zip em {$caminhoZip}.");
        }

        $contagem = [];
        $temporarios = [];
        foreach ($this->tabelas->tabelas() as $tabela) {
            $arquivo = tempnam(sys_get_temp_dir(), 'bkp');
            $temporarios[] = $arquivo;
            $contagem[$tabela] = $this->escreverCsv($tabela, $businessId, $arquivo);
            $zip->addFile($arquivo, "{$tabela}.csv");
        }

        $manifesto = [
            'business_id' => $businessId,
            'gerado_em' => now()->toIso8601String(),
            'tabelas' => $contagem,
        ];
        $zip->addFromString('_MANIFEST.json', json_encode($manifesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        foreach ($temporarios as $t) {
            @unlink($t);
        }

        return $manifesto;
    }

    private function escreverCsv(string $tabela, int $businessId, string $arquivo): int
    {
        $colunas = $this->tabelas->colunas($tabela);
        $h = fopen($arquivo, 'wb');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, $colunas, ';', '"', '');

        $linhas = 0;
        $consulta = $this->tabelas->consulta($tabela, $businessId);
        // chunk() pagina por OFFSET: a ordem tem de ser total, senão linha repete ou some entre lotes.
        // Por id quando existe; senão por todas as colunas (product_locations, group_sub_taxes).
        foreach (in_array('id', $colunas, true) ? ['id'] : $colunas as $c) {
            $consulta->orderBy($c);
        }
        $consulta->chunk(self::LOTE, function ($lote) use ($h, $colunas, &$linhas) {
            foreach ($lote as $linha) {
                $valores = [];
                foreach ($colunas as $c) {
                    $valores[] = $linha->{$c};
                }
                fputcsv($h, $valores, ';', '"', '');
                $linhas++;
            }
        });
        fclose($h);

        return $linhas;
    }
}
