<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Entrada;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeDfeItem;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Support\CnpjDoEmitente;

/**
 * Upload do XML de uma NF-e de compra (ADR `proposals/2026-10-06-entrada-xml-compra.md` D2, fase 2).
 *
 * Grava em `nfe_dfe_recebidos` com `origem=upload`, pelo mesmo leitor da DF-e por NSU. Só aceita
 * quando o destinatário do XML é a própria empresa (Tier 0, ADR 0093) e a chave ainda não existe
 * para ela. Não cria compra: isso é do Compras (US-COM-003).
 *
 * Atrás da flag `nfebrasil.entrada_xml_enabled` (desligada por padrão).
 */
final class ImportarXmlEntradaService
{
    /** @throws EntradaXmlRecusadaException com a razão em linguagem de gente */
    public function importar(int $businessId, string $xml): NfeDfeRecebido
    {
        if (! config('nfebrasil.entrada_xml_enabled')) {
            throw new EntradaXmlRecusadaException('A entrada por XML está desligada para esta instalação.');
        }

        try {
            $nota = LeitorNfeEntrada::ler($xml);
        } catch (\InvalidArgumentException $e) {
            throw new EntradaXmlRecusadaException($e->getMessage());
        }

        $cnpjEmpresa = CnpjDoEmitente::resolver($businessId);
        if ($cnpjEmpresa === null) {
            throw new EntradaXmlRecusadaException('A empresa não tem CNPJ cadastrado — preencha em Fiscal › Configuração.');
        }
        if (preg_replace('/\D/', '', $nota['cnpj_destinatario']) !== $cnpjEmpresa) {
            throw new EntradaXmlRecusadaException('Esta nota foi emitida para outro CNPJ, não para esta empresa.');
        }
        if (strlen($nota['chave_44']) !== 44) {
            throw new EntradaXmlRecusadaException('A chave de acesso da nota não tem 44 dígitos.');
        }
        if (NfeDfeRecebido::where('business_id', $businessId)->where('chave_44', $nota['chave_44'])->exists()) {
            throw new EntradaXmlRecusadaException('Esta nota já foi importada.');
        }

        return DB::transaction(function () use ($businessId, $nota, $xml) {
            $path = sprintf('nfe-brasil/%d/dfes-recebidos/%s.xml', $businessId, $nota['chave_44']);
            Storage::disk(config('nfebrasil.dfes_recebidos_disk', 'local'))->put($path, $xml);

            $recebido = NfeDfeRecebido::create([
                'business_id'         => $businessId,
                'chave_44'            => $nota['chave_44'],
                'nsu'                 => null,
                'origem'              => NfeDfeRecebido::ORIGEM_UPLOAD,
                'cnpj_emitente'       => $nota['cnpj_emitente'],
                'nome_emitente'       => $nota['nome_emitente'],
                'valor_total'         => $nota['valor_total'],
                'num_protocolo'       => $nota['num_protocolo'] ?: null,
                'data_emissao'        => $nota['data_emissao'] ?? now(),
                'xml_path'            => $path,
                'status_manifestacao' => NfeDfeRecebido::STATUS_PENDENTE,
            ]);
            NfeDfeItem::gravarDaNota($businessId, $recebido->id, $nota['itens']);

            return $recebido;
        });
    }
}
