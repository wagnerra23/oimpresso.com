<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Boleto pago no banco que já existe como título no Financeiro — baixa o título em vez de criar
 * outro (Sabor Brasil, [F] 2026-11-04: "o Ricardo esquece de baixar alguns boletos; financeiro não
 * pode ficar desorganizado").
 *
 * O título veio da migração do Delphi com `metadata.boleto_nosso_numero` e `metadata.boleto_banco`
 * (import-financeiro.py). Ordem de casamento, sempre dentro da empresa (ADR 0093) e só em título a
 * receber ainda em aberto:
 *   1. nosso número do Delphi = `seuNumero` ou `nossoNumero` do banco, ou o código Delphi
 *      (`legacy_id`) = `seuNumero`; banco do boleto vazio ou o do recebimento; e o mesmo valor
 *      nominal (o nosso número do Delphi é curto e se repete entre carteiras);
 *   2. sem isso: mesmo pagador (CPF/CNPJ do cliente), mesmo vencimento e mesmo valor nominal.
 * Mais de um candidato = ambíguo, não baixa nada.
 *
 * Valor (REGRA MESTRE): `valor_baixa` é o principal abatido (o aberto inteiro); o que o cliente
 * pagou = valor_baixa + juros − desconto, a mesma convenção do OnCobrancaPagaCreateFinanceiroTitulo.
 *   pago ≥ aberto → juros = pago − aberto;
 *   pago < aberto → desconto = aberto − pago (desconto do boleto, ex. pontualidade P-086), só até
 *   10% do aberto; acima disso é divergência e vai para conferência humana, sem baixa.
 * Idempotente: a baixa usa uma chave derivada da referência do banco.
 */
final class BaixaDeBoletoMigrado
{
    public const BAIXADO = 'baixado';
    public const AMBIGUO = 'ambiguo';
    public const DIVERGENTE = 'divergente';
    public const NENHUM = 'nenhum';

    public const DESCONTO_MAXIMO = 0.10;

    /**
     * @param array{ref:string, seu_numero:string, nosso_numero:string, banco:string, valor_nominal:float,
     *              valor_pago:float, vencimento:?string, pago_em:string, pagador_doc:?string} $rec
     * @return array{resultado:string, titulo_id:?int, juros:float, desconto:float}
     */
    public function baixar(int $businessId, array $rec, ?int $contaId, int $autor = 1): array
    {
        $candidatos = $this->candidatos($businessId, $rec);
        if (count($candidatos) > 1) {
            return ['resultado' => self::AMBIGUO, 'titulo_id' => null, 'juros' => 0.0, 'desconto' => 0.0];
        }
        if ($candidatos === []) {
            return ['resultado' => self::NENHUM, 'titulo_id' => null, 'juros' => 0.0, 'desconto' => 0.0];
        }
        $tituloId = $candidatos[0];

        return DB::transaction(function () use ($businessId, $tituloId, $rec, $contaId, $autor) {
            // SUPERADMIN: roda em CLI/fila sem sessão; o escopo é o business_id explícito.
            $titulo = Titulo::withoutGlobalScopes()->where('business_id', $businessId)->lockForUpdate()->findOrFail($tituloId);
            $aberto = round((float) $titulo->valor_aberto, 2);
            $pago = round($rec['valor_pago'], 2);
            $juros = $pago > $aberto ? round($pago - $aberto, 2) : 0.0;
            $desconto = $pago < $aberto ? round($aberto - $pago, 2) : 0.0;

            if ($desconto > round($aberto * self::DESCONTO_MAXIMO, 2) || ! in_array($titulo->status, ['aberto', 'parcial'], true)) {
                return ['resultado' => self::DIVERGENTE, 'titulo_id' => $tituloId, 'juros' => $juros, 'desconto' => $desconto];
            }

            if ($contaId !== null) {
                TituloBaixa::create([
                    'business_id'       => $businessId,
                    'titulo_id'         => $titulo->id,
                    'conta_bancaria_id' => $contaId,
                    'valor_baixa'       => $aberto,
                    'juros'             => $juros,
                    'multa'             => 0,
                    'desconto'          => $desconto,
                    'data_baixa'        => substr($rec['pago_em'], 0, 10),
                    'meio_pagamento'    => 'boleto',
                    'idempotency_key'   => self::chave($rec['ref']),
                    'observacoes'       => 'Boleto pago no banco ('.$rec['banco'].') · '.$rec['ref'],
                    'created_by'        => $autor,
                ]);
            }

            $titulo->fill([
                'valor_aberto'      => $contaId !== null ? 0 : $aberto,
                'status'            => $contaId !== null ? 'quitado' : $titulo->status,
                'conta_bancaria_id' => $contaId ?? $titulo->conta_bancaria_id,
                'forma_pagamento'   => 'boleto',
                'metadata'          => array_merge((array) ($titulo->metadata ?? []), [
                    'inter_ref' => $rec['ref'],
                    'baixa_banco' => ['banco' => $rec['banco'], 'pago_em' => $rec['pago_em'], 'valor_pago' => $pago],
                ]),
            ])->save();

            return ['resultado' => self::BAIXADO, 'titulo_id' => $tituloId, 'juros' => $juros, 'desconto' => $desconto];
        });
    }

    /** @return list<int> */
    private function candidatos(int $businessId, array $rec): array
    {
        $refs = array_values(array_unique(array_filter([
            self::semZeros($rec['seu_numero']), self::semZeros($rec['nosso_numero']),
        ], fn ($v) => $v !== '')));
        $banco = self::semZeros($rec['banco']);

        $base = fn () => DB::table('fin_titulos')->where('fin_titulos.business_id', $businessId)
            ->where('fin_titulos.tipo', 'receber')->whereIn('fin_titulos.status', ['aberto', 'parcial'])
            ->whereNull('fin_titulos.deleted_at');
        $temLegacy = \Illuminate\Support\Facades\Schema::hasColumn('fin_titulos', 'legacy_id');

        // Filtro portátil no SQL (empresa, aberto, valor nominal); o nosso número e o banco são
        // conferidos aqui, lendo o metadata — roda igual no MySQL e no SQLite da lane de teste.
        // O nosso número do Delphi é sequencial curto e se repete entre carteiras: o valor nominal
        // tem de bater também, senão o '52' de um banco baixaria o '52' de outro.
        $ids = [];
        if ($refs !== []) {
            $seu = trim((string) $rec['seu_numero']);
            foreach ($base()->whereRaw('ABS(valor_total - ?) < 0.005', [$rec['valor_nominal']])
                ->get($temLegacy ? ['id', 'legacy_id', 'metadata'] : ['id', 'metadata']) as $t) {
                $meta = json_decode((string) $t->metadata, true) ?: [];
                $nosso = self::semZeros(isset($meta['boleto_nosso_numero']) ? (string) $meta['boleto_nosso_numero'] : '');
                $bancoTit = self::semZeros(isset($meta['boleto_banco']) ? (string) $meta['boleto_banco'] : '');
                $casa = ($nosso !== '' && in_array($nosso, $refs, true))
                    || ($seu !== '' && (string) ($t->legacy_id ?? '') === $seu);
                if ($casa && ($bancoTit === '' || $bancoTit === $banco)) {
                    $ids[] = (int) $t->id;
                }
            }
        }

        $doc = preg_replace('/\D/', '', (string) ($rec['pagador_doc'] ?? ''));
        if ($ids === [] && $doc !== '' && ! empty($rec['vencimento']) && \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
            $ids = $base()->join('contacts as c', function ($j) {
                $j->on('c.id', '=', 'fin_titulos.cliente_id')->on('c.business_id', '=', 'fin_titulos.business_id');
            })
                ->whereRaw("REPLACE(REPLACE(REPLACE(COALESCE(NULLIF(c.tax_number, ''), c.cpf_cnpj), '.', ''), '/', ''), '-', '') = ?", [$doc])
                ->whereDate('fin_titulos.vencimento', substr((string) $rec['vencimento'], 0, 10))
                ->whereRaw('ABS(fin_titulos.valor_total - ?) < 0.005', [$rec['valor_nominal']])
                ->pluck('fin_titulos.id')->map(fn ($i) => (int) $i)->all();
        }

        return array_values(array_unique($ids));
    }

    private static function semZeros(?string $v): string
    {
        $v = trim((string) $v);

        return $v === '' ? '' : (ltrim($v, '0') === '' ? '0' : ltrim($v, '0'));
    }

    /** UUID determinístico pela referência do banco — a mesma baixa não entra duas vezes. */
    public static function chave(string $ref): string
    {
        $h = md5('boleto.migrado.'.$ref);

        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
    }
}
