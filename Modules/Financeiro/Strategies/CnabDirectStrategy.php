<?php

namespace Modules\Financeiro\Strategies;

use Carbon\Carbon;
use Eduardokum\LaravelBoleto\Pessoa;
use Modules\Financeiro\Contracts\BoletoStrategy;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;

/**
 * MVP do BoletoStrategy via lib eduardokum/laravel-boleto (fork local v0.11.1).
 *
 * Geração 100% offline (linha digitável, código de barras, render PDF).
 * Sem chamada banco — status persistido como 'gerado_mock'.
 * Idempotente por (business_id, titulo_id, conta_bancaria_id).
 *
 * Decisão: ADR ARQ-0003 (Strategy Pattern) + ADR TECH-0003 (MVP eduardokum).
 *
 * Em produção, estende para ondas seguintes:
 *  - geração CNAB 240/400 remessa
 *  - upload SFTP/API
 *  - parser CNAB retorno
 */
class CnabDirectStrategy implements BoletoStrategy
{
    /**
     * Map FEBRABAN code -> classe da lib eduardokum.
     * Apenas bancos suportados pela lib v0.11.1; ajustar se nova versão.
     */
    public const BANCO_MAP = [
        '001' => \Eduardokum\LaravelBoleto\Boleto\Banco\Bb::class,
        '004' => \Eduardokum\LaravelBoleto\Boleto\Banco\Bnb::class,
        '033' => \Eduardokum\LaravelBoleto\Boleto\Banco\Santander::class,
        '041' => \Eduardokum\LaravelBoleto\Boleto\Banco\Banrisul::class,
        '077' => \Eduardokum\LaravelBoleto\Boleto\Banco\Inter::class,
        '085' => \Eduardokum\LaravelBoleto\Boleto\Banco\Ailos::class,
        '104' => \Eduardokum\LaravelBoleto\Boleto\Banco\Caixa::class,
        '133' => \Eduardokum\LaravelBoleto\Boleto\Banco\Cresol::class,
        '136' => \Eduardokum\LaravelBoleto\Boleto\Banco\Unicred::class,
        '208' => \Eduardokum\LaravelBoleto\Boleto\Banco\Btg::class,
        '224' => \Eduardokum\LaravelBoleto\Boleto\Banco\Fibra::class,
        '237' => \Eduardokum\LaravelBoleto\Boleto\Banco\Bradesco::class,
        '336' => \Eduardokum\LaravelBoleto\Boleto\Banco\C6::class,
        '341' => \Eduardokum\LaravelBoleto\Boleto\Banco\Itau::class,
        '362' => \Eduardokum\LaravelBoleto\Boleto\Banco\Hsbc::class,
        '405' => \Eduardokum\LaravelBoleto\Boleto\Banco\Delbank::class,
        '633' => \Eduardokum\LaravelBoleto\Boleto\Banco\Rendimento::class,
        '643' => \Eduardokum\LaravelBoleto\Boleto\Banco\Pine::class,
        '712' => \Eduardokum\LaravelBoleto\Boleto\Banco\Ourinvest::class,
        '748' => \Eduardokum\LaravelBoleto\Boleto\Banco\Sicredi::class,
        '756' => \Eduardokum\LaravelBoleto\Boleto\Banco\Bancoob::class,
    ];

    public function emitir(Titulo $titulo, ContaBancaria $conta): BoletoRemessa
    {
        if (! isset(self::BANCO_MAP[$conta->banco_codigo])) {
            throw new \DomainException(
                "Banco {$conta->banco_codigo} nao suportado pela CnabDirectStrategy. ".
                'Use GatewayStrategy ou registre adapter proprio.'
            );
        }

        $idempotencyKey = sprintf('cnab:%d:%d', $titulo->id, $conta->id);

        if ($existente = BoletoRemessa::where('business_id', $titulo->business_id)
            ->where('titulo_id', $titulo->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first()) {
            return $existente;
        }

        $boleto = $this->gerarBoleto($titulo, $conta);

        return BoletoRemessa::create([
            'business_id' => $titulo->business_id,
            'titulo_id' => $titulo->id,
            'conta_bancaria_id' => $conta->id,
            'nosso_numero' => $boleto->getNossoNumero(),
            'linha_digitavel' => $boleto->getLinhaDigitavel(),
            'codigo_barras' => $boleto->getCodigoBarras(),
            'valor_total' => $titulo->valor_total,
            'vencimento' => $titulo->vencimento,
            'status' => BoletoRemessa::STATUS_GERADO_MOCK,
            'strategy' => BoletoRemessa::STRATEGY_CNAB_DIRECT,
            'idempotency_key' => $idempotencyKey,
            'metadata' => [
                'banco_codigo' => $conta->banco_codigo,
                'carteira' => $conta->carteira,
            ],
        ]);
    }

    public function cancelar(BoletoRemessa $remessa, string $motivo = ''): void
    {
        $metadata = $remessa->metadata ?? [];
        $metadata['cancelamento'] = [
            'motivo' => $motivo ?: 'sem_motivo_informado',
            'em' => now()->toIso8601String(),
        ];

        $remessa->status = BoletoRemessa::STATUS_CANCELADO;
        $remessa->metadata = $metadata;
        $remessa->save();
    }

    public function statusAtual(BoletoRemessa $remessa): string
    {
        // MVP mock: nao consulta banco, devolve estado persistido.
        // Onda futura: parsear retorno CNAB / consumir webhook gateway.
        return $remessa->status;
    }

    /**
     * Gera a instancia AbstractBoleto sem persistir BoletoRemessa.
     * Util pra contract test e pre-visualizacao (PDF on-the-fly).
     */
    public function gerarBoleto(Titulo $titulo, ContaBancaria $conta): \Eduardokum\LaravelBoleto\Boleto\AbstractBoleto
    {
        if (! isset(self::BANCO_MAP[$conta->banco_codigo])) {
            throw new \DomainException(
                "Banco {$conta->banco_codigo} nao suportado pela CnabDirectStrategy."
            );
        }

        $bancoClass = self::BANCO_MAP[$conta->banco_codigo];

        /** @var \Eduardokum\LaravelBoleto\Boleto\AbstractBoleto $boleto */
        $boleto = new $bancoClass($this->montarParams($titulo, $conta));

        $messages = '';
        if (! $boleto->isValid($messages)) {
            throw new \DomainException("Boleto invalido: {$messages}");
        }

        return $boleto;
    }

    /**
     * Monta o array de params esperado pelo construtor de cada Banco da lib.
     * Bancos individuais validam quais campos sao obrigatorios via setCamposObrigatorios().
     */
    private function montarParams(Titulo $titulo, ContaBancaria $conta): array
    {
        $beneficiario = new Pessoa([
            'nome' => $conta->beneficiario_razao_social,
            'documento' => $conta->beneficiario_documento,
            'endereco' => $conta->beneficiario_logradouro ?? 'Nao informado',
            'bairro' => $conta->beneficiario_bairro ?? 'Centro',
            'cidade' => $conta->beneficiario_cidade ?? 'Sao Paulo',
            'uf' => $conta->beneficiario_uf ?? 'SP',
            'cep' => $conta->beneficiario_cep ?? '00000-000',
        ]);

        $pagador = new Pessoa([
            'nome' => $titulo->cliente_descricao ?: 'Pagador (sem nome)',
            'documento' => $titulo->metadata['cliente_documento'] ?? '00000000000',
            'endereco' => $titulo->metadata['cliente_endereco'] ?? 'Nao informado',
            'bairro' => 'Centro',
            'cidade' => 'Sao Paulo',
            'uf' => 'SP',
            'cep' => '00000-000',
        ]);

        $sequencial = (int) ($titulo->numero ?: $titulo->id);

        $params = [
            'numero' => $sequencial,
            'numeroDocumento' => $titulo->numero ?: (string) $titulo->id,
            'carteira' => $conta->carteira,
            'convenio' => $conta->convenio,
            'agencia' => $conta->agencia,
            'agenciaDv' => $conta->agencia_dv,
            'conta' => $conta->numero_conta ?: '0',
            'contaDv' => $conta->conta_dv,
            'codigoCliente' => $conta->codigo_cedente,
            'variacaoCarteira' => $conta->variacao_carteira,
            'dataVencimento' => Carbon::parse($titulo->vencimento),
            'valor' => (float) $titulo->valor_total,
            'especieDoc' => 'DM',
            'aceite' => 'N',
            'beneficiario' => $beneficiario,
            'pagador' => $pagador,
        ];

        // Campos especificos por banco vem do metadata (ex: Inter requer 'operacao',
        // Rendimento requer 'modalidadeCarteira'). Espalhamos por cima.
        if (! empty($conta->metadata) && is_array($conta->metadata)) {
            foreach ($conta->metadata as $key => $value) {
                if ($value !== null && $value !== '') {
                    $params[$key] = $value;
                }
            }
        }

        return $params;
    }
}
