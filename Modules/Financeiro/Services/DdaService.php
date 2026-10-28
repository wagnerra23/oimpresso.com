<?php

namespace Modules\Financeiro\Services;

use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Dto\BoletoDdaRecebido;
use Modules\Financeiro\Events\TituloCriado;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\DdaBoleto;
use Modules\Financeiro\Models\Titulo;

/**
 * DDA → contas a pagar sugeridas (US-FIN-078).
 *
 * O boleto que chega da fonte vira SUGESTÃO casada com o fornecedor pelo CNPJ/CPF do
 * beneficiário. Título a pagar só nasce quando um usuário aceita — nunca automático.
 * `business_id` vem sempre do chamador (job/controller), nunca do payload da fonte.
 */
class DdaService
{
    /** contacts.type que conta como fornecedor (enum do core, fora do dicionário do Financeiro). */
    private const TIPOS_CONTATO_FORNECEDOR = ['supplier', 'both'];

    /** Grava a sugestão; idempotente por (business_id, codigo_barras). */
    public function registrar(int $businessId, string $fonte, BoletoDdaRecebido $boleto): DdaBoleto
    {
        $codigo = preg_replace('/\D/', '', $boleto->codigoBarras);
        $documento = preg_replace('/\D/', '', $boleto->beneficiarioDocumento);

        // SUPERADMIN: roda em job sem sessão; o filtro é o where business_id explícito.
        $existente = DdaBoleto::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)->where('codigo_barras', $codigo)->first();
        if ($existente) {
            return $existente;
        }

        return DdaBoleto::withoutGlobalScope(BusinessScopeImpl::class)->create([
            'business_id' => $businessId,
            'fonte' => $fonte,
            'codigo_barras' => $codigo,
            'beneficiario_documento' => $documento,
            'beneficiario_nome' => $boleto->beneficiarioNome,
            'valor' => round((float) $boleto->valor, 2),
            'vencimento' => $boleto->vencimento->toDateString(),
            'contact_id' => $this->fornecedorPorDocumento($businessId, $documento),
            'status' => DdaBoleto::STATUS_SUGERIDO,
            'raw_payload' => $boleto->raw,
        ]);
    }

    /** Fornecedor (supplier/both) da própria empresa cujo CNPJ/CPF bate com o beneficiário. */
    public function fornecedorPorDocumento(int $businessId, string $documento): ?int
    {
        if ($documento === '') {
            return null;
        }

        $id = DB::table('contacts')
            ->where('business_id', $businessId)
            ->whereIn('type', self::TIPOS_CONTATO_FORNECEDOR)
            ->whereNull('deleted_at')
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(tax_number, '.', ''), '/', ''), '-', ''), ' ', '') = ?", [$documento])
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /** Aceita a sugestão: cria o título a pagar (aberto) e liga os dois. */
    public function aceitar(int $businessId, int $ddaId, int $userId): Titulo
    {
        $titulo = DB::transaction(function () use ($businessId, $ddaId, $userId) {
            $dda = DdaBoleto::withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $businessId)->lockForUpdate()->findOrFail($ddaId);

            if ($dda->status !== DdaBoleto::STATUS_SUGERIDO) {
                throw new \DomainException('Este boleto DDA já foi decidido.');
            }

            $max = (int) Titulo::withoutGlobalScope(BusinessScopeImpl::class)
                ->where('business_id', $businessId)->where('numero', 'like', 'P-%')->lockForUpdate()
                ->selectRaw('MAX(CAST(SUBSTRING(numero, 3) AS UNSIGNED)) as max_n')->value('max_n');

            $titulo = Titulo::create([
                'business_id' => $businessId,
                'numero' => sprintf('P-%05d', max(1, $max + 1)),
                'tipo' => 'pagar',
                'status' => 'aberto',
                'cliente_id' => $dda->contact_id,
                'cliente_descricao' => $dda->beneficiario_nome,
                'valor_total' => $dda->valor,
                'valor_aberto' => $dda->valor,
                'moeda' => 'BRL',
                'emissao' => now()->toDateString(),
                'vencimento' => $dda->vencimento->toDateString(),
                'competencia_mes' => $dda->vencimento->format('Y-m'),
                'origem' => 'manual',
                'forma_pagamento' => 'boleto',
                'metadata' => ['dda_boleto_id' => $dda->id, 'linha_digitavel' => $dda->codigo_barras],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $dda->update([
                'status' => DdaBoleto::STATUS_ACEITO,
                'titulo_id' => $titulo->id,
                'decidido_por' => $userId,
                'decidido_em' => now(),
            ]);

            return $titulo;
        });

        TituloCriado::dispatch($titulo);

        return $titulo;
    }

    public function descartar(int $businessId, int $ddaId, int $userId): void
    {
        $afetadas = DdaBoleto::withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $businessId)->where('id', $ddaId)
            ->where('status', DdaBoleto::STATUS_SUGERIDO)
            ->update(['status' => DdaBoleto::STATUS_DESCARTADO, 'decidido_por' => $userId, 'decidido_em' => now()]);

        if ($afetadas === 0) {
            throw new \DomainException('Boleto DDA não encontrado ou já decidido.');
        }
    }
}
