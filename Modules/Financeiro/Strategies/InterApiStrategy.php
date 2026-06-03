<?php

namespace Modules\Financeiro\Strategies;

use Carbon\Carbon;
use Eduardokum\LaravelBoleto\Api\Banco\Inter as InterApi;
use Eduardokum\LaravelBoleto\Boleto\Banco\Inter as InterBoleto;
use Eduardokum\LaravelBoleto\Pessoa;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Contracts\BoletoStrategy;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;

/**
 * Strategy real pra Banco Inter — registra cobrança via API v3.
 *
 * Base URL prod: https://cdpj.partners.bancointer.com.br
 * Auth: OAuth2 client_credentials + mTLS (cert+key obrigatórios).
 * Scopes: boleto-cobranca.read boleto-cobranca.write boleto-cobranca.webhook
 *
 * Decisão arquitetural: usa eduardokum/laravel-boleto Api\Banco\Inter
 * (lib já testada em produção por terceiros, cobre auth/cert/payload).
 * NÃO reimplementar HTTP/OAuth do zero.
 *
 * Pré-requisitos na ContaBancaria:
 *  - banco_codigo = '077'
 *  - certificado_path (relative ao storage local — .crt)
 *  - certificado_chave_path (.key)
 *  - inter_client_id_encrypted (cast 'encrypted' resolve)
 *  - inter_client_secret_encrypted
 *  - account.account_number (a conta corrente do Inter)
 *
 * Reaproveita CnabDirectStrategy::montarParams() pra gerar o objeto Boleto
 * (mesmo formato, só muda quem persiste no banco do Inter).
 */
class InterApiStrategy implements BoletoStrategy
{
    public function __construct(
        private CnabDirectStrategy $cnabHelper
    ) {
    }

    public function emitir(Titulo $titulo, ContaBancaria $conta): BoletoRemessa
    {
        $this->guardConta($conta);

        $idempotencyKey = sprintf('inter_api_v3:%d:%d', $titulo->id, $conta->id);

        if ($existente = BoletoRemessa::where('business_id', $titulo->business_id)
            ->where('titulo_id', $titulo->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first()) {
            return $existente;
        }

        $boleto = $this->cnabHelper->gerarBoleto($titulo, $conta);

        $api = $this->buildApi($conta);
        $boletoAtualizado = $api->createBoleto($boleto);

        $codigoCobranca = method_exists($boletoAtualizado, 'getID')
            ? $boletoAtualizado->getID()
            : null;
        $nossoNumero = $boletoAtualizado->getNossoNumero();
        $pixCopiaECola = method_exists($boletoAtualizado, 'getPixQrCode')
            ? $boletoAtualizado->getPixQrCode()
            : null;

        return BoletoRemessa::create([
            'business_id' => $titulo->business_id,
            'titulo_id' => $titulo->id,
            'conta_bancaria_id' => $conta->id,
            'nosso_numero' => $nossoNumero,
            'linha_digitavel' => $boleto->getLinhaDigitavel(),
            'codigo_barras' => $boleto->getCodigoBarras(),
            'valor_total' => $titulo->valor_total,
            'vencimento' => $titulo->vencimento,
            'status' => BoletoRemessa::STATUS_REGISTRADO,
            'strategy' => BoletoRemessa::STRATEGY_GATEWAY,
            'idempotency_key' => $idempotencyKey,
            'enviado_em' => now(),
            'metadata' => [
                'banco_codigo' => '077',
                'api_versao' => 'v3',
                'codigo_solicitacao' => $codigoCobranca,
                'pix_copia_e_cola' => $pixCopiaECola,
            ],
        ]);
    }

    public function cancelar(BoletoRemessa $remessa, string $motivo = ''): void
    {
        $conta = $remessa->contaBancaria;
        $this->guardConta($conta);

        $codigo = $remessa->metadata['codigo_solicitacao'] ?? null;
        if (! $codigo) {
            throw new \DomainException(
                "BoletoRemessa {$remessa->id} não tem codigo_solicitacao Inter — não foi registrado via API."
            );
        }

        $motivoNormalizado = $motivo ?: 'APEDIDODOCLIENTE';

        try {
            $api = $this->buildApi($conta);
            $api->cancelID($codigo, $motivoNormalizado);
        } catch (\Throwable $e) {
            Log::warning('[InterApiStrategy] cancelamento via API falhou', [
                'remessa_id' => $remessa->id,
                'codigo' => $codigo,
                'erro' => $e->getMessage(),
            ]);
            throw $e;
        }

        $metadata = $remessa->metadata ?? [];
        $metadata['cancelamento'] = [
            'motivo' => $motivoNormalizado,
            'em' => now()->toIso8601String(),
            'via' => 'inter_api_v3',
        ];

        $remessa->status = BoletoRemessa::STATUS_CANCELADO;
        $remessa->metadata = $metadata;
        $remessa->save();
    }

    public function statusAtual(BoletoRemessa $remessa): string
    {
        $conta = $remessa->contaBancaria;
        $this->guardConta($conta);

        $codigo = $remessa->metadata['codigo_solicitacao'] ?? null;
        if (! $codigo) {
            return $remessa->status;
        }

        try {
            $api = $this->buildApi($conta);
            $resposta = $api->retrieveID($codigo);
            $situacao = $resposta->cobranca->situacao ?? null;

            return match ($situacao) {
                'A_RECEBER' => BoletoRemessa::STATUS_REGISTRADO,
                'RECEBIDO', 'MARCADO_RECEBIDO', 'PAGO' => BoletoRemessa::STATUS_PAGO,
                'CANCELADO' => BoletoRemessa::STATUS_CANCELADO,
                'EXPIRADO' => BoletoRemessa::STATUS_VENCIDO,
                default => $remessa->status,
            };
        } catch (\Throwable $e) {
            Log::warning('[InterApiStrategy] consulta status falhou', [
                'remessa_id' => $remessa->id,
                'erro' => $e->getMessage(),
            ]);

            return $remessa->status;
        }
    }

    /**
     * Cadastra a URL do webhook no Inter pra essa conta.
     * Idempotente — chamar de novo sobrescreve.
     */
    public function registrarWebhook(ContaBancaria $conta, string $url): bool
    {
        $this->guardConta($conta);
        $api = $this->buildApi($conta);
        $ok = $api->createWebhook($url);

        if ($ok) {
            $conta->webhook_registered_at = now();
            $conta->save();
        }

        return $ok;
    }

    private function buildApi(ContaBancaria $conta): InterApi
    {
        if (! $conta->certificado_path || ! $conta->certificado_chave_path) {
            throw new \DomainException(
                "ContaBancaria {$conta->id}: certificado_path e certificado_chave_path são obrigatórios pra Inter API."
            );
        }

        $certPath = $this->resolverArquivo($conta->certificado_path);
        $keyPath = $this->resolverArquivo($conta->certificado_chave_path);

        $params = [
            'versao' => 3,
            'conta' => $conta->numero_conta ?: '0',
            'certificado' => $certPath,
            'certificadoChave' => $keyPath,
            'client_id' => $conta->inter_client_id_encrypted,
            'client_secret' => $conta->inter_client_secret_encrypted,
        ];

        if ($conta->certificado_password_encrypted) {
            $params['certificadoSenha'] = $conta->certificado_password_encrypted;
        }

        $beneficiario = new Pessoa([
            'nome' => $conta->beneficiario_razao_social,
            'documento' => $conta->beneficiario_documento,
            'endereco' => $conta->beneficiario_logradouro ?? 'Não informado',
            'bairro' => $conta->beneficiario_bairro ?? 'Centro',
            'cidade' => $conta->beneficiario_cidade ?? 'São Paulo',
            'uf' => $conta->beneficiario_uf ?? 'SP',
            'cep' => $conta->beneficiario_cep ?? '00000-000',
        ]);
        $params['beneficiario'] = $beneficiario;

        return new InterApi($params);
    }

    /**
     * Resolve path do certificado/chave.
     * Aceita absoluto (POSIX ou Windows) ou relativo a storage/app/private/.
     *
     * IMPORTANTE: NÃO usar Storage::disk('local') — neste projeto esse disk
     * aponta pra public/uploads/ (servido pela web). Certificados ficam em
     * storage/app/private/ que está fora do document root.
     */
    private function resolverArquivo(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Z]:\\\\/i', $path)) {
            return $path;
        }

        return storage_path('app/private/' . ltrim($path, '/'));
    }

    private function guardConta(ContaBancaria $conta): void
    {
        if ($conta->banco_codigo !== '077') {
            throw new \DomainException(
                "InterApiStrategy só atende banco 077; conta {$conta->id} tem banco {$conta->banco_codigo}."
            );
        }

        $operacao = $conta->metadata['operacao'] ?? null;
        if (! $operacao) {
            throw new \DomainException(
                "ContaBancaria {$conta->id}: metadata->operacao é obrigatório (7 dígitos do código de operação Inter). ".
                'A lib Boleto\\Banco\\Inter exige esse campo mesmo pra API v3.'
            );
        }
    }
}
