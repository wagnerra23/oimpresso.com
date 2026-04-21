<?php

namespace Modules\PontoWr2\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Entities\Importacao;

/**
 * Inspeciona um arquivo AFD (ou uma importação já persistida) e lista:
 *   - Tipos de registro encontrados (1 cabeçalho, 3 marcações, 9 trailer, etc.)
 *   - PIS distintos e quantas marcações cada um tem
 *   - Quais PIS já estão cadastrados como Colaborador e quais faltam
 *
 * Útil antes de re-importar um AFD que falhou por "PIS não cadastrado".
 *
 * Uso:
 *   php artisan ponto:afd-inspecionar --importacao=2
 *   php artisan ponto:afd-inspecionar --arquivo=/caminho/absoluto/arquivo.txt --business=1
 */
class AfdInspecionarCommand extends Command
{
    protected $signature = 'ponto:afd-inspecionar
                            {--importacao= : ID de uma Importacao já persistida}
                            {--arquivo= : Caminho absoluto de um arquivo AFD (alternativa a --importacao)}
                            {--business= : Business_id (obrigatório quando usar --arquivo)}';

    protected $description = 'Inspeciona arquivo AFD: lista tipos de registro e PIS encontrados, indicando quais não estão cadastrados.';

    public function handle()
    {
        list($caminho, $businessId, $labelOrigem) = $this->resolverFonte();
        if (!$caminho) {
            return 1;
        }

        if (!file_exists($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");
            return 1;
        }

        $this->info("Inspecionando {$labelOrigem}");
        $this->info("Caminho: {$caminho}");
        $this->info("Business: {$businessId}");
        $this->line('');

        $handle = fopen($caminho, 'r');
        if (!$handle) {
            $this->error('Não foi possível abrir o arquivo.');
            return 1;
        }

        $encoding = config('pontowr2.afd.encoding', 'ISO-8859-1');
        $tipos = [];
        $pisContagem = [];
        $totalLinhas = 0;

        while (($linha = fgets($handle)) !== false) {
            $totalLinhas++;
            $linha = mb_convert_encoding(rtrim($linha, "\r\n"), 'UTF-8', $encoding);
            if (strlen($linha) < 10) {
                continue;
            }

            $tipo = substr($linha, 9, 1);
            $tipos[$tipo] = isset($tipos[$tipo]) ? $tipos[$tipo] + 1 : 1;

            if (in_array($tipo, ['3', '7', '8'], true) && strlen($linha) >= 34) {
                $pis = trim(substr($linha, 22, 12));
                if ($pis !== '') {
                    $pisContagem[$pis] = isset($pisContagem[$pis]) ? $pisContagem[$pis] + 1 : 1;
                }
            }
        }
        fclose($handle);

        // Resumo de tipos
        $this->info('--- Tipos de registro ---');
        ksort($tipos);
        foreach ($tipos as $t => $qtd) {
            $label = $this->labelTipo($t);
            $this->line(sprintf('  Tipo %s: %6d  (%s)', $t, $qtd, $label));
        }
        $this->line("  Total de linhas: {$totalLinhas}");
        $this->line('');

        // Cross-reference PIS x Colaborador
        $this->info('--- PIS encontrados (' . count($pisContagem) . ' distintos) ---');
        arsort($pisContagem);

        $cadastrados = Colaborador::where('business_id', $businessId)
            ->whereIn('pis', array_keys($pisContagem))
            ->pluck('pis')
            ->all();
        $cadastradosSet = array_flip($cadastrados);

        $rows = [];
        foreach ($pisContagem as $pis => $qtd) {
            $status = isset($cadastradosSet[$pis]) ? 'CADASTRADO' : 'FALTA';
            $rows[] = [$pis, $qtd, $status];
        }
        $this->table(['PIS', 'Marcações', 'Status'], $rows);

        $faltando = array_diff(array_keys($pisContagem), $cadastrados);
        if (empty($faltando)) {
            $this->info('Todos os PIS já estão cadastrados como Colaborador.');
        } else {
            $this->line('');
            $this->warn('PIS sem cadastro (' . count($faltando) . '):');
            foreach ($faltando as $pis) {
                $this->line('  - ' . $pis);
            }
            $this->line('');
            $this->warn('Cadastre esses PIS em /ponto/colaboradores antes de re-importar.');
        }

        return 0;
    }

    /**
     * Decide se a fonte é uma Importacao persistida ou um arquivo direto.
     * @return array [caminho, businessId, label]
     */
    protected function resolverFonte()
    {
        $importacaoId = $this->option('importacao');
        if ($importacaoId) {
            $importacao = Importacao::find($importacaoId);
            if (!$importacao) {
                $this->error("Importacao #{$importacaoId} não encontrada.");
                return [null, null, null];
            }
            return [
                Storage::path($importacao->arquivo_path),
                $importacao->business_id,
                "importação #{$importacao->id} ({$importacao->nome_arquivo})",
            ];
        }

        $arquivo = $this->option('arquivo');
        $businessId = $this->option('business');
        if (!$arquivo || !$businessId) {
            $this->error('Informe --importacao=ID OU (--arquivo=caminho + --business=id).');
            return [null, null, null];
        }

        return [$arquivo, (int) $businessId, "arquivo {$arquivo}"];
    }

    protected function labelTipo($t)
    {
        $labels = [
            '1' => 'Cabeçalho',
            '2' => 'Empresa',
            '3' => 'Marcação',
            '4' => 'Ajuste relógio',
            '5' => 'Empregado',
            '6' => 'Evento',
            '7' => 'Retif. anterior',
            '8' => 'Retif. posterior',
            '9' => 'Trailer',
        ];
        return isset($labels[$t]) ? $labels[$t] : 'desconhecido';
    }
}
