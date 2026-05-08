<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services\Banking;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente Banking API v2 do Banco Inter (saldo / extrato / pagamentos).
 *
 * Separado do `InterDriver` (boleto) porque a Banking API é diferente da
 * API de boleto/cobrança — `eduardokum/laravel-boleto` cobre apenas
 * boleto + PIX charging, não a Banking API. SoC ADR 0094 §5.
 *
 * Reusa o mesmo cert mTLS (`certificado_crt_b64` + `certificado_key_b64`)
 * gravado em `BoletoCredential.config_json`. Token OAuth é cacheado por
 * `(business_id, scope)` durante 50min (token Inter expira em 60min).
 *
 * @see US-RB-045
 */
class InterBankingClient
{
    private const BASE_URL = 'https://cdpj.partners.bancointer.com.br';
    private const TOKEN_TTL_SECONDS = 3000;

    public function __construct(
        private readonly array $config,
        private readonly int $businessId,
    ) {}

    /**
     * Saldo da conta-corrente. Chama `GET /banking/v2/saldo` com Bearer + mTLS.
     *
     * @return array{disponivel: float, bloqueado: float, limite: float}
     */
    public function getSaldo(): array
    {
        $token = $this->oauthToken('extrato.read');

        $response = $this->httpWithMtls()
            ->withToken($token)
            ->withHeaders(['x-conta-corrente' => $this->config['conta_corrente']])
            ->get(self::BASE_URL.'/banking/v2/saldo');

        if ($response->failed()) {
            Log::warning('InterBankingClient.saldo failed', [
                'business_id' => $this->businessId,
                'status'      => $response->status(),
                'body'        => '[REDACTED]',
            ]);
            $response->throw();
        }

        $data = $response->json();

        return [
            'disponivel' => (float) ($data['disponivel'] ?? 0),
            'bloqueado'  => (float) (
                ($data['bloqueadoCheque'] ?? 0)
                + ($data['bloqueadoJudicialmente'] ?? 0)
                + ($data['bloqueadoAdministrativo'] ?? 0)
            ),
            'limite'     => (float) ($data['limite'] ?? 0),
        ];
    }

    /**
     * Extrato detalhado entre `$from` e `$to`. Faz paginação (100/pg, cap 10pg).
     * Endpoint: `GET /banking/v2/extrato/completo`. Retorna todas as
     * transações concatenadas no shape bruto do Inter — `InterStatementDriver`
     * traduz pra `StatementLineDto[]`.
     *
     * @return array<int, array> Lista de transações Inter v2.
     */
    public function getExtrato(Carbon $from, Carbon $to): array
    {
        $token = $this->oauthToken('extrato.read');

        $transacoes = [];
        $pagina = 0;
        $maxPaginas = 10;

        while ($pagina < $maxPaginas) {
            $response = $this->httpWithMtls()
                ->withToken($token)
                ->withHeaders(['x-conta-corrente' => $this->config['conta_corrente']])
                ->get(self::BASE_URL.'/banking/v2/extrato/completo', [
                    'dataInicio'    => $from->toDateString(),
                    'dataFim'       => $to->toDateString(),
                    'paginacao'     => $pagina,
                    'itensPorPagina' => 100,
                ]);

            if ($response->failed()) {
                Log::warning('InterBankingClient.extrato failed', [
                    'business_id' => $this->businessId,
                    'pagina'      => $pagina,
                    'status'      => $response->status(),
                    'body'        => '[REDACTED]',
                ]);
                $response->throw();
            }

            $data = $response->json();
            $transacoes = array_merge($transacoes, $data['transacoes'] ?? []);

            if (($data['ultimaPagina'] ?? true) === true) {
                break;
            }
            $pagina++;
        }

        return $transacoes;
    }

    /**
     * Cria cobrança PIX imediata via `PUT /cobranca/v3/cob/{txid}`.
     *
     * Inter PIX v3:
     *   - txid 26-35 alfanuméricos
     *   - body: calendario.expiracao + devedor + valor.original + chave + solicitacaoPagador
     *   - response: status (ATIVA), pixCopiaECola, loc.id (pra GET qrcode)
     *
     * @param  array  $body  Inter v3 cob payload (calendario/valor/chave/devedor/solicitacaoPagador).
     * @return array  Response bruto Inter v3.
     */
    public function criarCobImediata(string $txid, array $body): array
    {
        $token = $this->oauthToken('cob.write');

        $response = $this->httpWithMtls()
            ->withToken($token)
            ->withHeaders(['x-conta-corrente' => $this->config['conta_corrente']])
            ->put(self::BASE_URL."/cobranca/v3/cob/{$txid}", $body);

        if ($response->failed()) {
            Log::warning('InterBankingClient.criarCobImediata failed', [
                'business_id' => $this->businessId,
                'txid'        => $txid,
                'status'      => $response->status(),
                'body'        => '[REDACTED]',
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Busca QR Code PNG base64 via `GET /cobranca/v3/cob/{txid}/qrcode`.
     * Usado depois de `criarCobImediata` pra renderizar imagem no UI.
     */
    public function getQrCodeBase64(string $txid): ?string
    {
        $token = $this->oauthToken('cob.read');

        $response = $this->httpWithMtls()
            ->withToken($token)
            ->withHeaders(['x-conta-corrente' => $this->config['conta_corrente']])
            ->get(self::BASE_URL."/cobranca/v3/cob/{$txid}/qrcode");

        if ($response->failed()) {
            return null;
        }

        return $response->json('imagemQrcode');
    }

    /**
     * OAuth client_credentials com mTLS, cacheado 50min por (business, scope).
     * Múltiplos escopos podem ser passados como string com espaço.
     */
    public function oauthToken(string $scope): string
    {
        $cacheKey = "inter:token:{$this->businessId}:".sha1($scope);

        return Cache::remember($cacheKey, self::TOKEN_TTL_SECONDS, function () use ($scope) {
            $response = $this->httpWithMtls()
                ->asForm()
                ->post(self::BASE_URL.'/oauth/v2/token', [
                    'client_id'     => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'grant_type'    => 'client_credentials',
                    'scope'         => $scope,
                ]);

            if ($response->failed()) {
                Log::error('InterBankingClient.oauth failed', [
                    'business_id' => $this->businessId,
                    'status'      => $response->status(),
                    'body'        => '[REDACTED]',
                ]);
                $response->throw();
            }

            return (string) $response->json('access_token');
        });
    }

    private function httpWithMtls(): PendingRequest
    {
        $crtPath = $this->writeTempCert('inter_crt', base64_decode($this->config['certificado_crt_b64']));
        $keyPath = $this->writeTempCert('inter_key', base64_decode($this->config['certificado_key_b64']));

        return Http::withOptions([
            'cert'    => $crtPath,
            'ssl_key' => $keyPath,
        ]);
    }

    /**
     * Grava conteúdo PEM em /tmp com permissão 0600. Idempotente por md5.
     * Pattern duplicado de `InterDriver::writeTempCert` (boleto) — refatorar
     * pra trait quando aparecer um terceiro consumidor mTLS.
     */
    private function writeTempCert(string $prefix, string $content): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.'_'.md5($content).'.pem';
        if (! file_exists($path)) {
            file_put_contents($path, $content, LOCK_EX);
            chmod($path, 0600);
        }

        return $path;
    }
}
