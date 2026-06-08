<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Cnab;

use Carbon\Carbon;
use Eduardokum\LaravelBoleto\Boleto\AbstractBoleto;
use Eduardokum\LaravelBoleto\Pessoa;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\DriverHealth;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Fundação compartilhada dos drivers CNAB (Bradesco/Itaú/BB/Santander/Caixa).
 *
 * Ponte entre o contract canônico `PaymentDriverContract` (REST-style) e a
 * lib `eduardokum/laravel-boleto` (file-based CNAB 240/400).
 *
 * Drivers concretos (Onda 4f.cnab — 5 paralelos) herdam esta classe e
 * implementam APENAS 3 métodos abstratos:
 *
 *   - getBoletoClass(): FQCN da classe `Eduardokum\LaravelBoleto\Boleto\Banco\{X}`
 *   - getLayoutVersion(): 240 ou 400 (depende do banco/convênio)
 *   - camposObrigatoriosCnab(): subset de [agencia, conta, conta_dv, convenio,
 *       carteira, cedente_nome, cedente_documento, codigo_cliente, modalidade]
 *
 * Decisões arquiteturais:
 *   - CNAB é boleto registrado APENAS — `supports()` só aceita 'boleto'
 *   - PIX / cartão / consulta real-time / webhook → DriverNotSupportedException
 *     (CNAB usa upload de arquivo retorno, processado pelo Job CnabRetornoProcessor)
 *   - emitirBoleto monta `Boleto` instance da lib, grava remessa em
 *     Storage::disk('local')/cnab-remessas/biz-{id}/cred-{id}/{idem}.rem
 *     e retorna nossoNumero como `gateway_external_id`
 *   - cancelar gera CNAB de instrução com ocorrência PEDIDO_CANCELAMENTO
 *     (CNAB240 código '02' / CNAB400 código '31')
 *   - healthCheck valida APENAS config_json + instancia Boleto sem exceção
 *     (CNAB não tem endpoint de health real — file-based)
 *
 * Multi-tenant Tier 0 honrado: business_id flui via $cred->business_id em
 * tudo que é persistido (Storage path + Cobranca lookup no Job).
 *
 * Refs: ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.0
 */
abstract class CnabBoletoAdapter implements PaymentDriverContract
{
    /**
     * Disco onde remessa CNAB é gravada (Onda 4f.0 default: local).
     *
     * Driver concreto pode sobrescrever pra 's3' / 'sftp' se cliente
     * tiver SFTP configurado (Onda 4f.cnab opcional, fora do escopo agora).
     */
    protected function remessaDisk(): string
    {
        return 'local';
    }

    // ─── métodos abstratos (driver concreto implementa) ──────────────────

    /**
     * FQCN da classe da lib `Eduardokum\LaravelBoleto\Boleto\Banco\{X}`.
     * Exemplo (BradescoCnabDriver):
     *   return \Eduardokum\LaravelBoleto\Boleto\Banco\Bradesco::class;
     */
    abstract protected function getBoletoClass(): string;

    /** Layout CNAB usado pelo banco/convênio. Aceitos: 240 ou 400. */
    abstract protected function getLayoutVersion(): int;

    /**
     * Campos do `config_json` obrigatórios pra este banco.
     *
     * Subset comum:
     *   - agencia, conta, conta_dv
     *   - convenio (BB/Santander/Caixa)
     *   - carteira (todos)
     *   - cedente_nome, cedente_documento (todos)
     *   - codigo_cliente (Santander/Caixa)
     *   - modalidade (Caixa)
     *
     * @return array<int, string>
     */
    abstract protected function camposObrigatoriosCnab(): array;

    // ─── contract methods ────────────────────────────────────────────────

    /**
     * key() é abstract — cada driver concreto retorna sua chave
     * (`bradesco_cnab`, `itau_cnab`, etc).
     */
    abstract public function key(): string;

    public function supports(string $tipo): bool
    {
        // CNAB suporta APENAS boleto registrado.
        return $tipo === 'boleto';
    }

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);
        $config = $cred->config_json ?? [];

        $boletoClass = $this->getBoletoClass();
        if (! class_exists($boletoClass)) {
            throw new CredentialMisconfiguredException(
                "Classe Boleto não encontrada: {$boletoClass}. Verifique composer + lib-custom/laravel-boleto."
            );
        }

        try {
            /** @var AbstractBoleto $boleto */
            $boleto = new $boletoClass($this->buildBoletoData($input, $config));
        } catch (\Throwable $e) {
            throw new InvalidPayerException(
                "Erro ao montar Boleto {$this->key()}: " . $e->getMessage()
            );
        }

        $nossoNumero = (string) $boleto->getNossoNumero();
        if ($nossoNumero === '') {
            throw new InvalidPayerException(
                "Driver {$this->key()} não conseguiu gerar nossoNumero — verifique config (carteira/agencia/conta/numero)."
            );
        }

        // Grava arquivo remessa (1 boleto por arquivo nesta onda; bulk fica
        // pra Onda futura de envio agendado por SFTP).
        $remessaPath = $this->gravarRemessa($boleto, $input, $cred);

        return new CobrancaEmitidaResult(
            cobrancaId: 0, // setado pelo PaymentGatewayService após persistência
            gatewayExternalId: $nossoNumero,
            tipo: 'boleto',
            emitidaEm: new \DateTimeImmutable(),
            linhaDigitavel: $this->safeCall($boleto, 'getLinhaDigitavel'),
            codigoBarras: $this->safeCall($boleto, 'getCodigoBarras'),
            nossoNumero: $nossoNumero,
            payloadGateway: [
                'cnab_remessa_path' => $remessaPath,
                'layout'            => $this->getLayoutVersion(),
                'gateway_key'       => $this->key(),
            ],
        );
    }

    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            "Driver {$this->key()} (CNAB) não suporta PIX — use o driver REST API correspondente."
        );
    }

    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            "Driver {$this->key()} (CNAB) não suporta PIX Automático — use bcb_pix ou driver REST API."
        );
    }

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            "Driver {$this->key()} (CNAB) não suporta cartão — use asaas ou pagarme."
        );
    }

    public function cancelar(object $cobranca, object $cred, string $motivo): void
    {
        $this->assertCredential($cred);
        $nossoNumero = (string) ($cobranca->gateway_external_id ?? '');
        if ($nossoNumero === '') {
            throw new InvalidPayerException(
                "Cobranca sem gateway_external_id pra cancelar via {$this->key()}."
            );
        }

        // CNAB de instrução com ocorrência PEDIDO_CANCELAMENTO:
        //   - Layout 240: código '02' (Pedido de Baixa)
        //   - Layout 400: código '31' (Cancelamento — varia por banco; alguns usam '34')
        //
        // Nesta onda fundação, geramos apenas um arquivo "instrucao" no Storage
        // pra cliente enviar via SFTP/portal. O driver concreto pode override
        // pra usar lib remessa-instrucao (não-trivial — fora do escopo 4f.0).

        $instrucaoPath = sprintf(
            'cnab-instrucoes/biz-%d/cred-%d/cancelar-%s-%s.txt',
            (int) $cred->business_id,
            (int) $cred->id,
            $nossoNumero,
            now()->format('Ymd-His')
        );

        Storage::disk($this->remessaDisk())->put(
            $instrucaoPath,
            sprintf(
                "# CNAB %d INSTRUÇÃO PEDIDO_CANCELAMENTO\n# Banco: %s\n# Nosso Número: %s\n# Motivo: %s\n# Gerado: %s\n# Onda 4f.0 — gerar binário CNAB real fica pra driver concreto\n",
                $this->getLayoutVersion(),
                $this->key(),
                $nossoNumero,
                substr($motivo, 0, 80),
                now()->toIso8601String()
            )
        );
    }

    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void
    {
        throw new DriverNotSupportedException(
            "Driver {$this->key()} (CNAB) não suporta refund. Boleto registrado pago já caiu na conta " .
            'do cedente — devolução é via TED reverso operado pelo titular.'
        );
    }

    public function consultar(object $cobranca, object $cred): CobrancaStatus
    {
        throw new DriverNotSupportedException(
            "Driver {$this->key()} (CNAB) não suporta consulta real-time. " .
            'Aguarde upload do arquivo de retorno em /settings/payment-gateways/' .
            ($cred->id ?? '{cred}') . '/cnab-retorno — Job CnabRetornoProcessor atualiza Cobranca + dispatch CobrancaPaga.'
        );
    }

    public function healthCheck(object $cred): DriverHealth
    {
        $start = microtime(true);

        try {
            $this->assertCredential($cred);
            $config = $cred->config_json ?? [];

            // Tenta instanciar o Boleto com dados fake só pra detectar
            // problema gritante de config (carteira inválida pro banco, etc).
            $boletoClass = $this->getBoletoClass();
            if (! class_exists($boletoClass)) {
                throw new CredentialMisconfiguredException("Classe {$boletoClass} não encontrada");
            }

            // Smoke test: monta com dados mínimos. Se a lib levantar exceção
            // (ex: carteira fora do array $carteiras), apanhamos aqui.
            new $boletoClass(array_merge(
                [
                    'logo'           => null,
                    'dataVencimento' => new Carbon(),
                    'valor'          => 1.00,
                    'numero'         => 1,
                    'numeroDocumento' => '1',
                    'pagador'        => new Pessoa([
                        'nome'      => 'Teste Health',
                        'documento' => '00000000000',
                    ]),
                    'beneficiario'   => new Pessoa([
                        'nome'      => $config['cedente_nome'] ?? 'Teste',
                        'documento' => $config['cedente_documento'] ?? '00000000000000',
                    ]),
                ],
                $this->configToBoletoArgs($config)
            ));

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return new DriverHealth(
                ok: true,
                status: 'ok',
                latencyMs: $latencyMs,
                checkedAt: new \DateTimeImmutable(),
            );
        } catch (\Throwable $e) {
            return new DriverHealth(
                ok: false,
                status: 'down',
                latencyMs: (int) round((microtime(true) - $start) * 1000),
                checkedAt: new \DateTimeImmutable(),
                errorMessage: substr($e->getMessage(), 0, 200),
            );
        }
    }

    /**
     * CNAB não tem webhook — retorno chega via upload arquivo.
     *
     * Conforme contract, retorno never. Logicamente nunca chamado
     * (WebhookProcessor não roteia gateway_key CNAB).
     */
    public function processWebhook(array $payload, object $cred): ?object
    {
        throw new DriverNotSupportedException(
            "Driver {$this->key()} (CNAB) não tem webhook. Retorno vem via upload " .
            'arquivo CNAB (240/400) processado por CnabRetornoProcessor job.'
        );
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    /**
     * Validações compartilhadas — driver concreto não precisa duplicar.
     *
     * - Tipo PaymentGatewayCredential
     * - gateway_key bate com $this->key()
     * - Campos obrigatórios CNAB presentes em config_json
     */
    protected function assertCredential(object $cred): void
    {
        if (! $cred instanceof PaymentGatewayCredential) {
            throw new CredentialMisconfiguredException(
                'Credential precisa ser PaymentGatewayCredential, recebeu: ' . get_class($cred)
            );
        }
        if ($cred->gateway_key !== $this->key()) {
            throw new CredentialMisconfiguredException(
                "Credential gateway_key='{$cred->gateway_key}' não bate com driver {$this->key()}"
            );
        }
        $config = $cred->config_json ?? [];
        $faltando = [];
        foreach ($this->camposObrigatoriosCnab() as $campo) {
            if (! isset($config[$campo]) || $config[$campo] === '') {
                $faltando[] = $campo;
            }
        }
        if (! empty($faltando)) {
            throw new CredentialMisconfiguredException(
                "Credential {$this->key()} faltando campos obrigatórios em config_json: " .
                implode(', ', $faltando)
            );
        }
    }

    /**
     * Mapeia (input + config) → array aceito pelo construtor da classe Boleto da lib.
     *
     * Driver concreto pode override pra mapear campos específicos do banco.
     *
     * @return array<string, mixed>
     */
    protected function buildBoletoData(EmitirCobrancaInput $input, array $config): array
    {
        $vencimento = Carbon::instance($input->vencimento);
        $valorReais = round($input->valorCentavos / 100, 2);

        $pagador = new Pessoa([
            'nome'      => $input->meta['payer_name'] ?? 'Pagador',
            'documento' => $input->meta['payer_cpf_cnpj'] ?? '00000000000',
            'endereco'  => $input->meta['payer_address'] ?? 'Não informado',
            'cep'       => preg_replace('/\D/', '', $input->meta['payer_cep'] ?? '00000000'),
            'uf'        => $input->meta['payer_uf'] ?? 'SP',
            'cidade'    => $input->meta['payer_city'] ?? 'São Paulo',
        ]);

        $beneficiario = new Pessoa([
            'nome'      => $config['cedente_nome'] ?? 'Cedente',
            'documento' => $config['cedente_documento'] ?? '00000000000000',
            'endereco'  => $config['cedente_endereco'] ?? 'Não informado',
            'cep'       => preg_replace('/\D/', '', $config['cedente_cep'] ?? '00000000'),
            'uf'        => $config['cedente_uf'] ?? 'SP',
            'cidade'    => $config['cedente_cidade'] ?? 'São Paulo',
        ]);

        // `numero` (sequencial do nosso número) — driver concreto deve
        // sobrescrever buildBoletoData se quiser gerar sequencial persistente.
        // Default: derivado do idempotencyKey (estável + único per business).
        $numeroSequencial = (int) abs(crc32($input->idempotencyKey)) % 99999999;

        return array_merge(
            $this->configToBoletoArgs($config),
            [
                'pagador'        => $pagador,
                'beneficiario'   => $beneficiario,
                'dataVencimento' => $vencimento,
                'valor'          => $valorReais,
                'numero'         => $numeroSequencial,
                'numeroDocumento' => substr($input->idempotencyKey, 0, 15),
                'descricaoDemonstrativo' => [substr($input->descricao, 0, 80)],
                'instrucoes'     => array_filter([
                    $input->instrucoesPagador ?? 'Não receber após o vencimento',
                ]),
            ]
        );
    }

    /**
     * Mapeia config_json → args da lib (agencia, conta, carteira, etc).
     *
     * @return array<string, mixed>
     */
    protected function configToBoletoArgs(array $config): array
    {
        $args = [];
        foreach (['agencia', 'agenciaDv', 'conta', 'contaDv', 'carteira', 'convenio', 'codigoCliente', 'modalidade'] as $k) {
            $snake = strtolower(preg_replace('/([A-Z])/', '_$1', $k));
            if (isset($config[$k])) {
                $args[$k] = $config[$k];
            } elseif (isset($config[$snake])) {
                $args[$k] = $config[$snake];
            }
        }

        return $args;
    }

    /**
     * Grava remessa CNAB (binário) em Storage disk.
     *
     * Atenção: na Onda 4f.0 fundação, gravamos um snapshot "boleto-instance"
     * em formato txt-debug. Driver concreto Onda 4f.cnab pode override pra
     * usar `Eduardokum\LaravelBoleto\Cnab\Remessa\Cnab{240|400}\Banco\{X}`
     * e gerar arquivo CNAB binário real (somando vários boletos por arquivo).
     */
    protected function gravarRemessa(AbstractBoleto $boleto, EmitirCobrancaInput $input, object $cred): string
    {
        $path = sprintf(
            'cnab-remessas/biz-%d/cred-%d/%s.rem',
            (int) $cred->business_id,
            (int) $cred->id,
            substr(preg_replace('/[^a-z0-9_-]/i', '-', $input->idempotencyKey), 0, 60)
        );

        $linha = sprintf(
            "# CNAB %d REMESSA — %s\n# Nosso Número: %s\n# Linha Digitável: %s\n# Vencimento: %s\n# Valor: R$ %.2f\n# Pagador: %s (%s)\n# Gerado: %s\n",
            $this->getLayoutVersion(),
            $this->key(),
            (string) $boleto->getNossoNumero(),
            $this->safeCall($boleto, 'getLinhaDigitavel') ?? '(não disponível)',
            Carbon::instance($input->vencimento)->toDateString(),
            $input->valorCentavos / 100,
            $input->meta['payer_name'] ?? 'Pagador',
            $this->maskDocumento($input->meta['payer_cpf_cnpj'] ?? ''),
            now()->toIso8601String()
        );

        Storage::disk($this->remessaDisk())->put($path, $linha);

        return $path;
    }

    /** Mascara documento pra log (LGPD — não vaza CPF/CNPJ inteiro). */
    protected function maskDocumento(string $doc): string
    {
        $clean = preg_replace('/\D/', '', $doc);
        if (strlen((string) $clean) < 6) {
            return '***';
        }

        return substr((string) $clean, 0, 3) . '***' . substr((string) $clean, -2);
    }

    /** Chama método do Boleto sem propagar exceção (alguns getters quebram sem dados completos). */
    private function safeCall(AbstractBoleto $boleto, string $method): ?string
    {
        try {
            $r = $boleto->{$method}();

            return $r === null || $r === '' ? null : (string) $r;
        } catch (\Throwable) {
            return null;
        }
    }
}
