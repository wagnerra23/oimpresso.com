<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

use Illuminate\Http\UploadedFile;
use Modules\NfeBrasil\Models\NfeFiscalRule;

/**
 * US-NFE-010 fase 3 · Import CSV em massa de regras tributárias.
 *
 * Formato CSV esperado (cabeçalho na 1ª linha):
 *
 *   ncm,uf_origem,uf_destino,cfop,csosn,cst,aliquota_icms,aliquota_pis,aliquota_cofins,aliquota_ipi
 *   49019900,SP,,5102,102,,0.00,0.0065,0.03,0
 *   49019900,SP,RJ,6102,,000,0.18,0.0165,0.076,0
 *
 * Convenções:
 *   - `uf_destino` vazio = "todas as UFs" (Nível 3 do cascade)
 *   - `csosn` OU `cst` (mutex por regime; preencher só um)
 *   - alíquotas em decimal (0.18 = 18%)
 *
 * Pipeline:
 *   1. parse() — lê CSV, valida estrutura, retorna lista normalizada + erros
 *   2. preview() — primeiras 10 linhas + total + counts (validas/inválidas/duplicadas)
 *   3. aplicar() — cria/atualiza regras (idempotente por (business_id, ncm, uf_origem, uf_destino))
 *
 * Validação por linha — erros não bloqueiam linhas válidas (ADR US-NFE-010):
 *   - Linhas com erro entram em `erros[]` mas demais seguem
 *   - Resultado: "150 criadas, 12 atualizadas, 3 falharam (ver log)"
 */
class ImportRegrasCsvService
{
    /** Cabeçalho canônico — linhas válidas devem ter exatamente estes campos. */
    public const COLUNAS_OBRIGATORIAS = [
        'ncm', 'uf_origem', 'uf_destino',
        'cfop', 'csosn', 'cst',
        'aliquota_icms', 'aliquota_pis', 'aliquota_cofins', 'aliquota_ipi',
    ];

    private const UFS_VALIDAS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
        'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
        'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    /**
     * Lê CSV uploaded e retorna array com:
     *   linhas:   array<int, array> linhas validadas + normalizadas
     *   erros:    array<int, array{linha:int, motivo:string, raw:array}>
     *
     * @param UploadedFile|string $arquivoOuConteudo file upload ou conteúdo string (testes)
     * @return array{linhas: array, erros: array}
     */
    public function parse($arquivoOuConteudo): array
    {
        $conteudo = $arquivoOuConteudo instanceof UploadedFile
            ? (string) file_get_contents($arquivoOuConteudo->getRealPath())
            : (string) $arquivoOuConteudo;

        // Suporta CR/LF + LF; remove BOM se UTF-8
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? '';
        $conteudo = str_replace(["\r\n", "\r"], "\n", $conteudo);
        $rows = array_filter(explode("\n", $conteudo), fn ($r) => trim($r) !== '');

        if (count($rows) < 2) {
            return [
                'linhas' => [],
                'erros' => [['linha' => 0, 'motivo' => 'Arquivo vazio ou só com cabeçalho.', 'raw' => []]],
            ];
        }

        $header = array_map('trim', str_getcsv((string) array_shift($rows)));
        $missing = array_diff(self::COLUNAS_OBRIGATORIAS, $header);
        if (! empty($missing)) {
            return [
                'linhas' => [],
                'erros' => [[
                    'linha' => 1,
                    'motivo' => 'Cabeçalho ausente colunas: ' . implode(', ', $missing),
                    'raw' => $header,
                ]],
            ];
        }

        $linhas = [];
        $erros = [];

        foreach ($rows as $idx => $rawRow) {
            $numeroLinha = $idx + 2; // +1 pra base-1, +1 pelo header
            $cols = array_map('trim', str_getcsv($rawRow));

            if (count($cols) !== count($header)) {
                $erros[] = [
                    'linha'  => $numeroLinha,
                    'motivo' => sprintf('Esperadas %d colunas, vieram %d', count($header), count($cols)),
                    'raw'    => $cols,
                ];
                continue;
            }

            $assoc = array_combine($header, $cols);
            $erro = $this->validarLinha($assoc, $numeroLinha);

            if ($erro !== null) {
                $erros[] = ['linha' => $numeroLinha, 'motivo' => $erro, 'raw' => $assoc];
                continue;
            }

            $linhas[] = $this->normalizarLinha($assoc);
        }

        return ['linhas' => $linhas, 'erros' => $erros];
    }

    /**
     * Aplica linhas validadas em DB. Idempotente — `firstOrCreate`+`update`
     * por chave natural (business_id, ncm, uf_origem, uf_destino).
     *
     * @return array{criadas:int, atualizadas:int, falhas:int}
     */
    public function aplicar(int $businessId, array $linhas): array
    {
        $criadas = 0;
        $atualizadas = 0;
        $falhas = 0;

        foreach ($linhas as $linha) {
            try {
                $existente = NfeFiscalRule::where('business_id', $businessId)
                    ->where('ncm', $linha['ncm'])
                    ->where('uf_origem', $linha['uf_origem'])
                    ->where(function ($q) use ($linha) {
                        $linha['uf_destino'] === null
                            ? $q->whereNull('uf_destino')
                            : $q->where('uf_destino', $linha['uf_destino']);
                    })
                    ->first();

                if ($existente) {
                    $existente->update($linha);
                    $atualizadas++;
                } else {
                    NfeFiscalRule::create(array_merge($linha, ['business_id' => $businessId]));
                    $criadas++;
                }
            } catch (\Throwable $e) {
                $falhas++;
            }
        }

        return ['criadas' => $criadas, 'atualizadas' => $atualizadas, 'falhas' => $falhas];
    }

    private function validarLinha(array $row, int $numeroLinha): ?string
    {
        if (! preg_match('/^\d{8}$/', (string) $row['ncm'])) {
            return 'NCM deve ter 8 dígitos';
        }
        if (! in_array($row['uf_origem'], self::UFS_VALIDAS, true)) {
            return 'UF origem inválida';
        }
        if (! empty($row['uf_destino']) && ! in_array($row['uf_destino'], self::UFS_VALIDAS, true)) {
            return 'UF destino inválida (deixe vazio pra "todas")';
        }
        if (! preg_match('/^\d{4}$/', (string) $row['cfop'])) {
            return 'CFOP deve ter 4 dígitos';
        }
        if (empty($row['csosn']) && empty($row['cst'])) {
            return 'Informe CSOSN (Simples) ou CST (Regime Normal)';
        }
        if (! empty($row['csosn']) && ! empty($row['cst'])) {
            return 'CSOSN e CST mutuamente exclusivos — preencha só um';
        }

        foreach (['aliquota_icms', 'aliquota_pis', 'aliquota_cofins', 'aliquota_ipi'] as $field) {
            if (! is_numeric($row[$field])) {
                return "{$field} deve ser numérico (ex: 0.18 = 18%)";
            }
            $val = (float) $row[$field];
            if ($val < 0 || $val > 1) {
                return "{$field} fora do range [0, 1]";
            }
        }

        return null;
    }

    private function normalizarLinha(array $row): array
    {
        return [
            'ncm'             => (string) $row['ncm'],
            'uf_origem'       => strtoupper((string) $row['uf_origem']),
            'uf_destino'      => empty($row['uf_destino']) ? null : strtoupper((string) $row['uf_destino']),
            'cfop'            => (string) $row['cfop'],
            'csosn'           => empty($row['csosn']) ? null : (string) $row['csosn'],
            'cst'             => empty($row['cst']) ? null : (string) $row['cst'],
            'aliquota_icms'   => (float) $row['aliquota_icms'],
            'aliquota_pis'    => (float) $row['aliquota_pis'],
            'aliquota_cofins' => (float) $row['aliquota_cofins'],
            'aliquota_ipi'    => (float) $row['aliquota_ipi'],
        ];
    }
}
