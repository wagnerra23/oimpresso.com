<?php

namespace Modules\Financeiro\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Titulo;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importa títulos a receber e a pagar de planilha (US-FIN-034), em dois passos.
 *
 * 1. `previa`: valida linha a linha e marca o que já existe. Não grava nada. Guarda as linhas
 *    válidas em cache sob um token ligado à empresa.
 * 2. `gravar`: só com o token da prévia, depois da confirmação do usuário. Re-confere duplicata
 *    dentro da transação e grava com o mesmo número sequencial do lançamento manual.
 *
 * Modelo fixo de colunas (cabeçalho na 1ª linha): tipo, contraparte, documento, emissao,
 * vencimento, valor, plano_contas, observacao. Valor em centavos inteiros do começo ao fim.
 */
class ImportacaoTitulosService
{
    public const COLUNAS = ['tipo', 'contraparte', 'documento', 'emissao', 'vencimento', 'valor', 'plano_contas', 'observacao'];

    public const MAX_LINHAS = 10000;

    private const TTL_MIN = 60;

    /**
     * @param  list<array>  $rows  planilha crua, 1ª linha = cabeçalho
     * @return array{token: ?string, linhas: list<array>, resumo: array}
     */
    public function previa(int $businessId, array $rows): array
    {
        $cab = array_map(fn ($c) => $this->chave((string) $c), array_shift($rows) ?? []);
        $faltam = array_values(array_diff(['tipo', 'contraparte', 'vencimento', 'valor'], $cab));
        if ($faltam !== []) {
            throw new \InvalidArgumentException('Faltam colunas no cabeçalho: '.implode(', ', $faltam).'. Use o modelo.');
        }
        if (count($rows) > self::MAX_LINHAS) {
            throw new \InvalidArgumentException('O arquivo tem mais de '.self::MAX_LINHAS.' linhas. Divida em partes.');
        }

        $planos = DB::table('fin_planos_conta')->where('business_id', $businessId)->whereNull('deleted_at')
            ->pluck('id', 'codigo')->all();

        $linhas = [];
        $vistos = [];
        foreach ($rows as $i => $row) {
            $r = [];
            foreach ($cab as $k => $nome) {
                $r[$nome] = $row[$k] ?? null;
            }
            if (implode('', array_map(fn ($v) => trim((string) $v), $r)) === '') {
                continue; // linha em branco
            }
            $linha = ['n' => $i + 2] + $this->validar($r, $planos);
            if ($linha['status'] === 'ok') {
                $chave = $this->chaveDuplicata($linha['dados']);
                if (isset($vistos[$chave])) {
                    $linha = ['status' => 'existe', 'motivo' => 'Repetida na linha '.$vistos[$chave].' do arquivo'] + $linha;
                } elseif ($n = $this->existente($businessId, $linha['dados'])) {
                    $linha = ['status' => 'existe', 'motivo' => "Já existe: título {$n}, mesmo documento, valor e vencimento"] + $linha;
                } else {
                    $vistos[$chave] = $linha['n'];
                }
            }
            $linhas[] = $linha;
        }

        $validas = array_values(array_filter($linhas, fn ($l) => $l['status'] === 'ok'));
        $resumo = [
            'ok' => count($validas),
            'erro' => count(array_filter($linhas, fn ($l) => $l['status'] === 'erro')),
            'existe' => count(array_filter($linhas, fn ($l) => $l['status'] === 'existe')),
            'receber_centavos' => array_sum(array_map(fn ($l) => $l['dados']['tipo'] === 'receber' ? $l['dados']['centavos'] : 0, $validas)),
            'pagar_centavos' => array_sum(array_map(fn ($l) => $l['dados']['tipo'] === 'pagar' ? $l['dados']['centavos'] : 0, $validas)),
        ];

        $token = null;
        if ($validas !== []) {
            $token = hash('sha256', $businessId.'|'.json_encode(array_column($validas, 'dados')));
            Cache::put($this->cacheKey($businessId, $token), array_column($validas, 'dados'), now()->addMinutes(self::TTL_MIN));
        }

        return ['token' => $token, 'linhas' => $linhas, 'resumo' => $resumo];
    }

    /** @return array{gravados: int, pulados: int, receber_centavos: int, pagar_centavos: int} */
    public function gravar(int $businessId, int $userId, string $token): array
    {
        $dados = Cache::get($this->cacheKey($businessId, $token));
        if (! is_array($dados)) {
            throw new \InvalidArgumentException('A prévia expirou ou é de outra empresa. Envie o arquivo de novo.');
        }

        $r = DB::transaction(function () use ($businessId, $userId, $token, $dados) {
            $ja = Titulo::where('business_id', $businessId)
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.importacao')) = ?", [$token])->exists();
            if ($ja) {
                throw new \InvalidArgumentException('Este arquivo já foi gravado.');
            }

            $out = ['gravados' => 0, 'pulados' => 0, 'receber_centavos' => 0, 'pagar_centavos' => 0];
            foreach ($dados as $d) {
                if ($this->existente($businessId, $d)) {
                    $out['pulados']++;

                    continue;
                }
                $valor = sprintf('%d.%02d', intdiv($d['centavos'], 100), $d['centavos'] % 100);
                Titulo::create([
                    'business_id' => $businessId,
                    'numero' => $this->proximoNumero($businessId, $d['tipo']),
                    'tipo' => $d['tipo'],
                    'status' => 'aberto',
                    'cliente_descricao' => $d['contraparte'],
                    'valor_total' => $valor,
                    'valor_aberto' => $valor,
                    'moeda' => 'BRL',
                    'emissao' => $d['emissao'],
                    'vencimento' => $d['vencimento'],
                    'competencia_mes' => substr($d['emissao'], 0, 7),
                    'origem' => 'manual',
                    'plano_conta_id' => $d['plano_conta_id'],
                    'observacoes' => $d['observacao'],
                    'metadata' => ['importacao' => $token, 'documento' => $d['documento']],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
                $out['gravados']++;
                $out[$d['tipo'].'_centavos'] += $d['centavos'];
            }

            return $out;
        });

        Cache::forget($this->cacheKey($businessId, $token));

        return $r;
    }

    /** @return array{status: string, motivo?: string, dados?: array} */
    private function validar(array $r, array $planos): array
    {
        $tipo = mb_strtolower(trim((string) ($r['tipo'] ?? '')));
        $tipo = ['receber' => 'receber', 'a receber' => 'receber', 'pagar' => 'pagar', 'a pagar' => 'pagar'][$tipo] ?? null;
        $contraparte = trim((string) ($r['contraparte'] ?? ''));
        $centavos = $this->centavos($r['valor'] ?? null);
        $venc = $this->data($r['vencimento'] ?? null);
        $emissaoCrua = $r['emissao'] ?? null;
        $emissao = ($emissaoCrua === null || trim((string) $emissaoCrua) === '') ? date('Y-m-d') : $this->data($emissaoCrua);
        $codPlano = trim((string) ($r['plano_contas'] ?? ''));

        $erro = match (true) {
            $tipo === null => 'Tipo deve ser Receber ou Pagar',
            $contraparte === '' => 'Contraparte em branco',
            $centavos === null => 'Valor inválido ('.trim((string) ($r['valor'] ?? '')).')',
            $centavos <= 0 => 'Valor deve ser maior que zero',
            $venc === null => 'Vencimento inválido ('.trim((string) ($r['vencimento'] ?? '')).')',
            $emissao === null => 'Emissão inválida ('.trim((string) $emissaoCrua).')',
            $codPlano !== '' && ! isset($planos[$codPlano]) => "Plano de contas {$codPlano} não existe",
            default => null,
        };
        if ($erro !== null) {
            return ['status' => 'erro', 'motivo' => $erro];
        }

        return ['status' => 'ok', 'dados' => [
            'tipo' => $tipo,
            'contraparte' => mb_substr($contraparte, 0, 191),
            'documento' => mb_substr(trim((string) ($r['documento'] ?? '')), 0, 60),
            'emissao' => $emissao,
            'vencimento' => $venc,
            'centavos' => $centavos,
            'plano_conta_id' => $codPlano !== '' ? (int) $planos[$codPlano] : null,
            'observacao' => trim((string) ($r['observacao'] ?? '')) ?: null,
        ]];
    }

    /** Planilha manda número (float) ou texto pt-BR ("1.840,50", "R$ 75,00"). Nunca adivinha milhar. */
    private function centavos($v): ?int
    {
        if (is_int($v) || is_float($v)) {
            return (int) round($v * 100);
        }
        $s = str_replace(['R$', ' ', "\u{00A0}"], '', trim((string) $v));
        if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d{1,2})?$/', $s) || preg_match('/^-?\d+(,\d{1,2})?$/', $s)) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (! preg_match('/^-?\d+(\.\d{1,2})?$/', $s)) {
            return null;
        }
        [$int, $dec] = array_pad(explode('.', ltrim($s, '-')), 2, '0');

        return (str_starts_with($s, '-') ? -1 : 1) * ((int) $int * 100 + (int) str_pad(substr($dec, 0, 2), 2, '0'));
    }

    /** dd/mm/aaaa, aaaa-mm-dd ou número de série do Excel → aaaa-mm-dd. */
    private function data($v): ?string
    {
        if (is_int($v) || is_float($v)) {
            return ExcelDate::excelToDateTimeObject($v)->format('Y-m-d');
        }
        $s = trim((string) $v);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $s;
        }

        return null;
    }

    private function existente(int $businessId, array $d): ?string
    {
        $q = Titulo::where('business_id', $businessId)->where('tipo', $d['tipo'])
            ->where('vencimento', $d['vencimento'])
            ->where('valor_total', sprintf('%d.%02d', intdiv($d['centavos'], 100), $d['centavos'] % 100))
            ->where('status', '!=', 'cancelado');
        $d['documento'] !== ''
            ? $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.documento')) = ?", [$d['documento']])
            : $q->where('cliente_descricao', $d['contraparte']);

        return $q->value('numero');
    }

    private function chaveDuplicata(array $d): string
    {
        return implode('|', [$d['tipo'], $d['vencimento'], $d['centavos'], $d['documento'] !== '' ? $d['documento'] : mb_strtolower($d['contraparte'])]);
    }

    /** Mesmo sequencial do lançamento manual (UnificadoController::store): R-00001 / P-00001. */
    private function proximoNumero(int $businessId, string $tipo): string
    {
        $prefixo = $tipo === 'receber' ? 'R' : 'P';
        $max = (int) Titulo::where('business_id', $businessId)->where('numero', 'like', "{$prefixo}-%")
            ->lockForUpdate()->selectRaw('MAX(CAST(SUBSTRING(numero, 3) AS UNSIGNED)) as max_n')->value('max_n');

        return sprintf('%s-%05d', $prefixo, max(1, $max + 1));
    }

    private function chave(string $c): string
    {
        $c = mb_strtolower(trim($c));
        $c = strtr($c, ['ã' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ú' => 'u']);

        $c = str_replace([' ', '-'], '_', $c);

        return ['plano_de_contas' => 'plano_contas', 'observacoes' => 'observacao'][$c] ?? $c;
    }

    private function cacheKey(int $businessId, string $token): string
    {
        return "fin:importacao-titulos:{$businessId}:{$token}";
    }
}
