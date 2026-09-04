<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Manifestacao;

use App\Util\OtelHelper;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\NfeBrasil\Models\NfeDfeEvento;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Concerns\ResolveUfEmitente;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Tools;
use RuntimeException;

/**
 * US-NFE-050 · Manifestação do Destinatário sobre DFe recebido.
 *
 * Implementa os 4 eventos da NT 2014.002:
 *   - Ciência da Operação (210210) — "vi a nota"
 *   - Confirmação da Operação (210200) — "recebi a mercadoria" (libera transporte)
 *   - Desconhecimento (210220) — "não conheço, uso indevido CNPJ"
 *   - Operação não Realizada (210240) — "não recebi, dispensa pagamento"
 *
 * Ressaltos:
 *   - Justificativa OBRIGATÓRIA ≥15 chars pra 210220 e 210240
 *   - cStat 135 ou 136 = evento autorizado (135 = registrado e vinculado, 136 = registrado)
 *   - Idempotência por (business_id, dfe_recebido_id, tipo, nseq_evento) UNIQUE
 *   - Audit log automático via Spatie Activity Log
 *
 * Multi-tenant: business_id sempre escopa (skill multi-tenant-patterns).
 */
class ManifestacaoService
{
    use ResolveUfEmitente;

    /** Justificativa mínima exigida pela NT 2014.002 pra eventos com xJust. */
    public const JUSTIFICATIVA_MIN_CHARS = 15;

    /** cStat válidos pra evento autorizado. */
    public const CSTAT_AUTORIZADOS = ['135', '136'];

    public function __construct(
        private readonly CertificadoService $certificadoService,
        private readonly ?Closure $toolsFactory = null,
    ) {}

    public function cienciar(NfeDfeRecebido $dfe): NfeDfeEvento
    {
        return $this->aplicarEvento($dfe, NfeDfeEvento::TIPO_CIENCIA);
    }

    public function confirmar(NfeDfeRecebido $dfe): NfeDfeEvento
    {
        return $this->aplicarEvento($dfe, NfeDfeEvento::TIPO_CONFIRMACAO);
    }

    public function desconhecer(NfeDfeRecebido $dfe, string $justificativa): NfeDfeEvento
    {
        $this->validarJustificativa($justificativa);
        return $this->aplicarEvento($dfe, NfeDfeEvento::TIPO_DESCONHECIMENTO, $justificativa);
    }

    public function naoRealizada(NfeDfeRecebido $dfe, string $justificativa): NfeDfeEvento
    {
        $this->validarJustificativa($justificativa);
        return $this->aplicarEvento($dfe, NfeDfeEvento::TIPO_NAO_REALIZADA, $justificativa);
    }

    /**
     * Aplica evento — comum aos 4 tipos.
     *
     * Idempotência: se já existe evento (autorizado) do mesmo tipo pra mesma chave,
     * retorna o existente sem tocar SEFAZ.
     *
     * D9.a OTel wrap — chamada SEFAZ sefazManifesta (4 eventos NT 2014.002) é
     * hot-path crítico p99. Atributos incluem `chave_44` e `tipo` pra correlação
     * em traces (ADR 0155 module-grade-v3 D9).
     */
    private function aplicarEvento(
        NfeDfeRecebido $dfe,
        string $tipo,
        string $justificativa = '',
    ): NfeDfeEvento {
        if (! in_array($tipo, NfeDfeEvento::TIPOS, true)) {
            throw new InvalidArgumentException("Tipo de evento inválido: {$tipo}");
        }

        return OtelHelper::spanBiz('nfe.manifestar', function () use ($dfe, $tipo, $justificativa): NfeDfeEvento {
            return $this->aplicarEventoInterno($dfe, $tipo, $justificativa);
        }, [
            'module'          => 'NfeBrasil',
            'tipo_evento'     => $tipo,
            'chave_44'        => (string) ($dfe->chave_44 ?? ''),
            'dfe_recebido_id' => (int) $dfe->id,
        ]);
    }

    /**
     * @internal Corpo real de aplicarEvento() — separado para wrap OTel.
     */
    private function aplicarEventoInterno(
        NfeDfeRecebido $dfe,
        string $tipo,
        string $justificativa,
    ): NfeDfeEvento {
        $businessId = (int) $dfe->business_id;

        $existente = NfeDfeEvento::where('business_id', $businessId)
            ->where('dfe_recebido_id', $dfe->id)
            ->where('tipo', $tipo)
            ->where('status', 'autorizado')
            ->first();

        if ($existente) {
            Log::info('nfe.manifesta.idempotente', [
                'business_id'    => $businessId,
                'chave'          => $dfe->chave_44,
                'tipo'           => $tipo,
                'evento_id'      => $existente->id,
            ]);
            return $existente;
        }

        $nSeq = $this->proximoNseq($dfe, $tipo);

        $evento = NfeDfeEvento::create([
            'business_id'     => $businessId,
            'dfe_recebido_id' => $dfe->id,
            'tipo'            => $tipo,
            'justificativa'   => $justificativa ?: null,
            'status'          => 'pendente',
            'nseq_evento'     => $nSeq,
        ]);

        try {
            $tools = $this->buildTools($businessId);
            // Conversao na fronteira com a lib. `Tools::sefazManifesta` declara
            // `(string $chave, int $tpEvento, string $xJust = '', int $nSeqEvento = 1)`, e o
            // dominio aqui carrega o tipo do BANCO: `NfeDfeEvento::TIPO_*` sao STRINGS
            // ('210210'…) porque a coluna e varchar. Passar isso cru rendia `TypeError` —
            // invisivel ate 2026-09-04 porque o `buildConfig` morria antes, na coluna
            // `business.state` que nao existe.
            // `$justificativa` NAO precisa de coalescencia: aqui dentro ela ja e `string`
            // (default ''), e quem e nullable e o parametro do `AcoesController`, uma camada
            // acima — o PHPStan pegou essa confusao de camada na 1a versao deste bloco.
            $responseXml = $tools->sefazManifesta(
                (string) $dfe->chave_44,
                (int) $tipo,
                $justificativa,
                (int) $nSeq,
            );

            $parsed = $this->parseResponse($responseXml);
            $cstat = $this->cstatDoEvento($parsed);
            $autorizado = in_array($cstat, self::CSTAT_AUTORIZADOS, true);

            $evento->update([
                'status'       => $autorizado ? 'autorizado' : 'rejeitado',
                'cstat_evento' => $cstat ?: null,
                'payload_json' => $parsed,
            ]);

            // D9.a Log estruturado retorno SEFAZ com biz/chave/cstat (chave SEFAZ
            // é o identificador fiscal canônico — sempre presente nos logs de evento).
            Log::info('nfe.manifesta.retorno_sefaz', [
                'biz'        => $businessId,
                'chave'      => (string) $dfe->chave_44,
                'tipo'       => $tipo,
                'cstat'      => $cstat,
                'autorizado' => $autorizado,
                'nseq'       => $nSeq,
                'evento_id'  => $evento->id,
            ]);

            if ($autorizado) {
                $this->atualizarStatusDfe($dfe, $tipo);
            }

            return $evento->fresh();
        } catch (\Throwable $e) {
            $evento->update([
                'status'       => 'rejeitado',
                'payload_json' => ['exception' => $e->getMessage()],
            ]);
            Log::error('nfe.manifesta.falha_sefaz', [
                'biz'   => $businessId,
                'chave' => $dfe->chave_44,
                'tipo'  => $tipo,
                'erro'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function proximoNseq(NfeDfeRecebido $dfe, string $tipo): int
    {
        $count = NfeDfeEvento::where('business_id', $dfe->business_id)
            ->where('dfe_recebido_id', $dfe->id)
            ->where('tipo', $tipo)
            ->count();
        return $count + 1;
    }

    /**
     * Atualiza status_manifestacao do DFe quando evento é autorizado.
     */
    private function atualizarStatusDfe(NfeDfeRecebido $dfe, string $tipo): void
    {
        $statusMap = [
            NfeDfeEvento::TIPO_CIENCIA          => NfeDfeRecebido::STATUS_CIENCIA,
            NfeDfeEvento::TIPO_CONFIRMACAO      => NfeDfeRecebido::STATUS_CONFIRMADA,
            NfeDfeEvento::TIPO_DESCONHECIMENTO  => NfeDfeRecebido::STATUS_DESCONHECIDA,
            NfeDfeEvento::TIPO_NAO_REALIZADA    => NfeDfeRecebido::STATUS_NAO_REALIZADA,
        ];

        $dfe->update([
            'status_manifestacao' => $statusMap[$tipo],
            'manifestado_em'      => now(),
        ]);
    }

    private function validarJustificativa(string $justificativa): void
    {
        if (mb_strlen(trim($justificativa)) < self::JUSTIFICATIVA_MIN_CHARS) {
            throw new InvalidArgumentException(sprintf(
                'Justificativa exige no mínimo %d caracteres (NT 2014.002).',
                self::JUSTIFICATIVA_MIN_CHARS,
            ));
        }
    }

    private function buildTools(int $businessId): Tools
    {
        $certData = $this->certificadoService->carregarParaSefaz($businessId);
        $configJson = json_encode($this->buildConfig($businessId), JSON_UNESCAPED_UNICODE);

        if ($this->toolsFactory) {
            return ($this->toolsFactory)($configJson, $certData);
        }

        $tools = new Tools(
            $configJson,
            Certificate::readPfx($certData['pfx_binary'], $certData['senha']),
        );
        $tools->model('55');
        $tools->setEnvironment($this->ambienteAtual($businessId));
        return $tools;
    }

    /**
     * Config mínima pra Tools sped-nfe consulta DistribuicaoDFe e manifestação.
     * UF default SP — manifestação é nacional, mas o Tools exige um cUF.
     *
     * A UF vem de `business_locations`, via `resolverUfEmitente()`. Até 2026-09-04 esta query
     * pedia `business.state`, coluna que não existe: TODA manifestação morria aqui com
     * `SQLSTATE[42S22]`. Ver o docblock do trait.
     */
    private function buildConfig(int $businessId): array
    {
        $row = DB::table('business')->select(['name', 'tax_number_1'])->where('id', $businessId)->first();
        $cnpj = $row->tax_number_1 ?? null;
        $razaoSocial = $row->name ?? '';
        $siglaUf = $this->resolverUfEmitente($businessId);

        return [
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb'       => $this->ambienteAtual($businessId),
            'razaosocial' => $razaoSocial,
            'cnpj'        => preg_replace('/\D/', '', (string) $cnpj),
            'siglaUF'     => $siglaUf,
            'schemes'     => 'PL_009_V4',
            'versao'      => '4.00',
            'tokenIBPT'   => '',
        ];
    }

    private function ambienteAtual(int $businessId): int
    {
        return (int) (config('nfebrasil.ambiente', 2)); // 2=homologação default
    }

    /**
     * cStat do EVENTO — nao o do lote.
     *
     * O `retEnvEvento` da SEFAZ carrega DOIS cStat: um na raiz, que fala do LOTE ("128 — lote
     * processado"), e outro dentro de `retEvento.infEvento`, que e o veredito do EVENTO em si
     * ("135 — evento registrado e vinculado a NF-e"). Sao independentes: o lote pode ser
     * processado com o evento rejeitado.
     *
     * Ate 2026-09-04 este metodo nao existia e o codigo lia `$parsed['cStat']`, que e o da
     * RAIZ. Como `CSTAT_AUTORIZADOS` sao 135/136, toda manifestacao BEM-SUCEDIDA era gravada
     * como `rejeitado`. Medido com o `Standardize` real: raiz='128', evento='135'.
     *
     * O fallback pra raiz cobre a resposta de erro, em que a SEFAZ recusa o lote inteiro e nao
     * devolve `retEvento` nenhum — ali o cStat da raiz e, de fato, o unico veredito que existe.
     *
     * @param array<string, mixed> $parsed
     */
    private function cstatDoEvento(array $parsed): string
    {
        $doEvento = $parsed['retEvento']['infEvento']['cStat'] ?? null;

        return (string) ($doEvento ?? $parsed['cStat'] ?? '');
    }

    private function parseResponse(string $xml): array
    {
        if ($xml === '' || $xml === null) {
            return [];
        }
        try {
            $st = new Standardize($xml);
            return $st->toArray();
        } catch (\Throwable $e) {
            return ['raw' => $xml, 'parse_error' => $e->getMessage()];
        }
    }
}
