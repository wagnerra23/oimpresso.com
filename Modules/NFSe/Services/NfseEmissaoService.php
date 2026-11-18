<?php

namespace Modules\NFSe\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Support\Privacy\PiiRedactor;
use Modules\NFSe\Contracts\NfseCancelaComCertificado;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Exceptions\CertificadoInvalidoException;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Exceptions\ProviderTimeoutException;
use Modules\NFSe\Exceptions\RpsDuplicadoException;
use Modules\NFSe\Jobs\EmitirNfseJob;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NfeBrasil\Services\RetencoesNfseService;
use Modules\NfeBrasil\Services\Nfse\ListaServicoNacional;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use App\Scopes\ScopeByBusiness;
use Modules\NfeBrasil\Services\CertificadoService;

class NfseEmissaoService
{
    private const MAX_RETRIES = 3;

    /**
     * Contador estático per-request para RPS — formato YmdHis + sufixo 4-dig.
     * Reseta entre requests (request-scoped do PHP-FPM).
     */
    private static int $rpsCounter = 0;

    public function __construct(private readonly NfseProviderInterface $provider) {}

    /**
     * Constrói o payload a partir dos dados validados do form + business_id.
     *
     * Extrai lógica de assembly que vivia no NfseController::store() —
     * separa concern (HTTP) de regra de domínio (montar DTO + carregar cert + gerar RPS).
     *
     * @param array<string,mixed> $data Dados validados do FormRequest
     */
    public function montarPayload(array $data, int $businessId): NfseEmissaoPayload
    {
        $rpsNumero = now()->format('YmdHis') . str_pad(
            (string) ++self::$rpsCounter,
            4,
            '0',
            STR_PAD_LEFT,
        );

        // Carrega cert do DB pro payload — cert PFX vai em base64 + senha decriptada
        // SUPERADMIN: monta payload sem context tenant (chamado por Controller após auth);
        // business_id explícito como param garante scope correto
        $config = NfseProviderConfig::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at') // config apagada não conta (ver getConfig)
            ->first();

        // Certificado ATIVO da empresa (o mesmo da NF-e), decriptado pelo dono do schema
        // (NfeBrasil). O PFX vai binário → base64 uma vez; o SnNfseAdapter decodifica uma
        // vez. Empresa sem config de NFS-e monta sem certificado.
        // _Até 2026-11-18 vinha de nfse_provider_configs.cert_id, que só o nfse:importar-cert
        // atualizava: trocar o certificado na tela /fiscal/config não chegava na NFS-e._
        $certPfxBase64 = null;
        $certSenha = null;
        $certificado = $config ? $this->certificadoAtivo($businessId) : null;
        if ($certificado instanceof NfeCertificado) {
            $cert = app(CertificadoService::class)->carregarCertificado($certificado);
            $certPfxBase64 = base64_encode($cert['pfx_binary']);
            $certSenha = $cert['senha'];
        }

        // R-NFSE-003 — retenções federais. Só calcula quando quem emite marcou o serviço
        // como do art. 30 (decisão [W] 2026-10-13); sem a marcação, a nota fica como antes.
        // Regime do prestador lido da config fiscal DO PRÓPRIO tenant (ADR 0093).
        $sujeito = (bool) ($data['retencao_federal_sujeito'] ?? false);
        $ret = ['pis' => 0, 'cofins' => 0, 'csll' => 0];
        if ($sujeito) {
            $ret = app(RetencoesNfseService::class)->calcularParaBusiness(
                $businessId,
                (int) round(((float) $data['valor_servicos']) * 100),
                ! empty($data['tomador_cnpj']),
            );
        }

        return new NfseEmissaoPayload(
            businessId: $businessId,
            rpsNumero: $rpsNumero,
            competencia: Carbon::createFromFormat('Y-m', $data['competencia']),
            tomadorNome: $data['tomador_nome'],
            tomadorCnpj: $data['tomador_cnpj'] ?? null,
            tomadorCpf: $data['tomador_cpf'] ?? null,
            tomadorEmail: $data['tomador_email'] ?? null,
            descricao: $data['descricao'],
            lc116Codigo: $data['lc116_codigo'],
            valorServicos: (float) $data['valor_servicos'],
            aliquotaIss: (float) $data['aliquota_iss'],
            issRetido: (bool) ($data['iss_retido'] ?? false),
            certPfxBase64: $certPfxBase64,
            certSenha: $certSenha,
            // Ambiente POR-BUSINESS (cutover fiscal): vem de nfse_provider_configs.ambiente
            // do tenant — NÃO do bind global config('nfse.ambiente'). Ligar produção pra
            // um business (ex: biz=164) não pode afetar outros tenants (ROTA LIVRE etc).
            // Fail-safe: ausência de config → 'homologacao' (nunca emite real por acidente).
            ambiente: $config?->ambiente ?? 'homologacao',
            municipioIbge: $config?->municipio_codigo_ibge ?? config('nfse.municipio_ibge_default', '4218707'),
            prestadorCnpj: $config?->prestador_cnpj,
            prestadorIm: $config?->prestador_im,
            transactionId: !empty($data['transaction_id']) ? (int) $data['transaction_id'] : null,
            retencaoFederalSujeito: $sujeito,
            retPisCentavos: $ret['pis'],
            retCofinsCentavos: $ret['cofins'],
            retCsllCentavos: $ret['csll'],
            // R-NFSE-005: série da DPS é numérica (TSSerieDPS); o legado 'RPS' vira '1'.
            serieDps: ctype_digit((string) $config?->serie_default) ? (string) $config->serie_default : '1',
            // Regime do PRÓPRIO tenant (ADR 0093) → regTrib/opSimpNac na DPS. Lido pelo Model do
            // dono da tabela (NfeBrasil), não por DB::table — a catraca de acoplamento proíbe
            // query crua em tabela de outro módulo.
            // SUPERADMIN: business_id explícito do parâmetro (montarPayload roda também fora de
            // request); o escopo de sessão não acrescenta nada além do where abaixo.
            regimePrestador: Schema::hasTable('nfe_business_configs')
                ? NfeBusinessConfig::withoutGlobalScope(ScopeByBusiness::class)
                    ->where('business_id', $businessId)->value('regime')
                : null,
            cTribNac: $this->resolverCTribNac((string) $data['lc116_codigo'], $data['ctrib_nac'] ?? null),
        );
    }

    /**
     * R-NFSE-006 — cTribNac pela tabela nacional. Resolve quando dá (escolha válida ou
     * subitem de desdobro único); senão devolve o que veio e o SnNfseAdapter falha na
     * emissão com a mensagem das opções — nunca um desdobro chutado.
     */
    private function resolverCTribNac(string $lc116, ?string $escolhido): ?string
    {
        try {
            return app(ListaServicoNacional::class)->resolver($lc116, $escolhido);
        } catch (\InvalidArgumentException) {
            return $escolhido ?: null;
        }
    }

    /**
     * Despacha emissão assíncrona — atalho pra Controller chamar 1 linha.
     */
    public function despacharEmissaoAsync(NfseEmissaoPayload $payload): void
    {
        // Sem o certificado: o job é serializado na fila. O envio resolve de novo pelo
        // business_id (lote 2, PR 2.3).
        EmitirNfseJob::dispatch($payload->semCertificado())->onQueue('nfse');
    }

    /**
     * Emite uma NFSe com idempotência, retry e log de erros.
     *
     * @throws NfseException se a emissão falhar após todas as tentativas
     */
    /**
     * @param bool $retomarPendente true só na retentativa do próprio EmitirNfseJob: a linha
     *        `processando` que ficou de um timeout é reenviada com o MESMO nº de DPS (lote 2,
     *        PR 2.2). Sem a flag, `processando` é emissão em andamento e é devolvida intacta.
     */
    public function emitir(NfseEmissaoPayload $payload, bool $retomarPendente = false): NfseEmissao
    {
        // OtelHelper::spanBiz — observability webservice prefeitura (HTTP externo p99 crítico).
        // D9.a Wave 14: span por emissão envolve adapter HTTP + idempotência + retries.
        return OtelHelper::spanBiz('nfse.emissao', function () use ($payload, $retomarPendente) {
            return $this->emitirInterno($payload, $retomarPendente);
        }, ['nfse.business_id' => $payload->businessId]);
    }

    private function emitirInterno(NfseEmissaoPayload $payload, bool $retomarPendente): NfseEmissao
    {
        // Idempotência por chave (UNIQUE na tabela): a mesma nota tem uma linha só.
        // SUPERADMIN: service de emissão NFSe sem context tenant — business_id vem do payload (DTO recebido); idempotência cross-session
        $existente = NfseEmissao::withoutGlobalScopes()
            ->where('idempotency_key', $payload->idempotencyKey())
            ->where('business_id', $payload->businessId)
            ->whereIn('status', ['emitida', 'processando', 'erro'])
            ->latest('id')
            ->first();

        if ($existente && ($existente->isEmitida() || ($existente->isPendente() && ! $retomarPendente))) {
            return $existente;
        }

        $dados = [
            'serie'           => $payload->serieDps,
            'competencia'     => $payload->competencia,
            'tomador_cnpj'    => $payload->tomadorCnpj,
            'tomador_cpf'     => $payload->tomadorCpf,
            'tomador_nome'    => $payload->tomadorNome,
            'tomador_email'   => $payload->tomadorEmail,
            'lc116_codigo'    => $payload->lc116Codigo,
            'ctrib_nac'       => $payload->cTribNac,
            'descricao'       => $payload->descricao,
            'valor_servicos'  => $payload->valorServicos,
            'aliquota_iss'    => $payload->aliquotaIss,
            'valor_iss'       => $payload->valorIss(),
            'iss_retido'      => $payload->issRetido,
            'valor_iss_retido' => $payload->valorIssRetido(),
            'retencao_federal_sujeito' => $payload->retencaoFederalSujeito,
            'valor_ret_pis'    => $payload->retPisCentavos / 100,
            'valor_ret_cofins' => $payload->retCofinsCentavos / 100,
            'valor_ret_csll'   => $payload->retCsllCentavos / 100,
            'transaction_id'  => $payload->transactionId,
        ];

        if ($existente === null) {
            $emissao = NfseEmissao::create($dados + [
                'business_id'     => $payload->businessId,
                'rps_numero'      => $payload->rpsNumero,
                'status'          => 'processando',
                'idempotency_key' => $payload->idempotencyKey(),
            ]);
        } elseif ($existente->isErro()) {
            // Reenvio de nota recusada (Sefin, config ou certificado): a MESMA linha volta a
            // `processando`. Criar outra estourava o UNIQUE de idempotency_key.
            $existente->update($dados + ['status' => 'processando', 'erro_mensagem' => null]);
            $emissao = $existente;
        } else {
            // Retentativa do job depois de timeout: a Sefin pode ter recebido a DPS. Reenvia
            // a mesma linha com o mesmo nº de DPS — se já existir lá, volta duplicada e é
            // tratada em resolverDuplicado(), sem virar uma segunda nota.
            $emissao = $existente;
        }

        // R-NFSE-005: nDPS = id da emissão (único, sequencial, cabe nos 15 dígitos do
        // leiaute). O número provisório YmdHis+contador tinha 18 dígitos e não é único.
        $payload = $payload->comNumeroDps((string) $emissao->id);
        $emissao->update(['rps_numero' => $payload->rpsNumero]);

        // Config e certificado depois de gravar a linha: sem eles a nota fica `erro` com o
        // motivo e aparece em /nfse. Antes a validação vinha antes do create e a nota sumia.
        try {
            $config = $this->getConfig($payload->businessId);
            $this->validarCertificado($payload->businessId);
        } catch (NfseException $e) {
            $this->marcarErro($emissao, $e);
            throw $e;
        }
        $emissao->update(['cnae' => $config->cnae]);
        $payload = $this->comCertificadoDaEmpresa($payload);

        return $this->enviar($emissao, $payload);
    }

    /**
     * Envia a DPS da linha, com retentativa só em timeout e sempre com o nº de DPS da linha.
     *
     * Erro terminal (certificado, ISS, tomador, código de serviço...) marca `erro` e sobe:
     * o job não retenta. Timeout esgotado deixa a linha `processando` — a nota pode ter
     * sido aceita — para a retentativa do job reenviar o mesmo nº de DPS.
     */
    private function enviar(NfseEmissao $emissao, NfseEmissaoPayload $payload): NfseEmissao
    {
        $tentativa = 0;
        $ultimoErro = null;

        while ($tentativa < self::MAX_RETRIES) {
            $tentativa++;

            try {
                $resultado = $this->provider->emitir($payload);

                $emissao->update([
                    'status'                    => 'emitida',
                    'numero'                    => $resultado->numero,
                    'provider_protocolo'        => $resultado->protocolo,
                    'provider_codigo_verificacao' => $resultado->codigoVerificacao,
                    'pdf_url'                   => $resultado->pdfUrl,
                    'xml_retorno'               => $resultado->xmlRetorno,
                    'erro_mensagem'             => null,
                ]);

                Log::channel('nfse')->info('NFSe emitida', [
                    'business_id' => $payload->businessId,
                    'numero'      => $resultado->numero,
                    'valor'       => $payload->valorServicos,
                ]);

                return $emissao->fresh();
            } catch (RpsDuplicadoException $e) {
                // A Sefin já tem uma nota para este nº de DPS (típico do reenvio depois de
                // timeout). Antes a linha virava `emitida` sem número. Agora consulta a nota.
                return $this->resolverDuplicado($emissao, $payload);
            } catch (CertificadoInvalidoException $e) {
                // Cert inválido = não adianta retry
                $this->marcarErro($emissao, $e);
                throw $e;
            } catch (ProviderTimeoutException $e) {
                $ultimoErro = $e;
                // Backoff exponencial: 1s, 2s, 4s (base em config; os testes usam 0).
                sleep((int) config('nfse.backoff_base_segundos', 1) * 2 ** ($tentativa - 1));
                continue;
            } catch (NfseException $e) {
                $this->marcarErro($emissao, $e);
                throw $e;
            }
        }

        // Esgotou as retentativas de timeout. A nota pode ter sido aceita: fica `processando`
        // (não `erro`), e a retentativa do job reenvia o mesmo nº de DPS.
        $timeout = $ultimoErro ?? new ProviderTimeoutException($tentativa);
        $emissao->update([
            'status'        => 'processando',
            'erro_mensagem' => app(PiiRedactor::class)->redact($timeout->getMessage()),
        ]);
        Log::channel('nfse')->warning('NFSe sem resposta da Sefin', [
            'business_id' => $emissao->business_id,
            'emissao_id'  => $emissao->id,
            'tentativas'  => $tentativa,
        ]);

        throw $timeout;
    }

    /**
     * DPS duplicada: a nota existe na Sefin. Com a chave de acesso da linha, consulta e grava
     * número e status reais. Sem a chave, a linha fica `processando` com o aviso — nunca
     * `emitida` sem número. A consulta pelo nº de DPS depende do endpoint do manual da Sefin,
     * que não está no repo (lote 2, PR 2.2).
     */
    private function resolverDuplicado(NfseEmissao $emissao, NfseEmissaoPayload $payload): NfseEmissao
    {
        $chave = (string) $emissao->provider_protocolo;
        if ($chave !== '') {
            $consulta = $this->provider->consultar($chave);
            if ($consulta->sucesso && ! empty($consulta->numero)) {
                $emissao->update([
                    'status'        => 'emitida',
                    'numero'        => $consulta->numero,
                    'pdf_url'       => $consulta->pdfUrl ?? $emissao->pdf_url,
                    'erro_mensagem' => null,
                ]);

                return $emissao->fresh();
            }
        }

        $emissao->update([
            'status'        => 'processando',
            'erro_mensagem' => "A Sefin já recebeu a DPS nº {$payload->rpsNumero}. Consulte a nota antes de emitir de novo.",
        ]);
        Log::channel('nfse')->warning('NFSe com DPS duplicada', [
            'business_id' => $emissao->business_id,
            'emissao_id'  => $emissao->id,
        ]);

        return $emissao->fresh();
    }

    public function cancelar(NfseEmissao $emissao, string $motivo): void
    {
        // OtelHelper::spanBiz — observability webservice prefeitura SOAP cancelamento
        // (HTTP externo p99 crítico igual emissão). D9 Wave 28: span `nfse.cancelar`
        // na mesma família `nfse.*` do emitir, business_id do tenant (Tier 0).
        // 3º arg = $extras (array<string,mixed>): tenant_id base já é auto-resolvido
        // dentro do spanBiz; aqui anexamos o business_id da nota (cancelar pode rodar
        // em job sem sessão, onde a auto-resolução cai em 0).
        OtelHelper::spanBiz('nfse.cancelar', function () use ($emissao, $motivo) {
            $this->cancelarInterno($emissao, $motivo);
        }, ['nfse.business_id' => $emissao->business_id]);
    }

    private function cancelarInterno(NfseEmissao $emissao, string $motivo): void
    {
        if ($emissao->isCancelada()) {
            throw new \Modules\NFSe\Exceptions\NfseJaCanceladaException($emissao->numero ?? '');
        }
        // Só nota emitida (com número) cancela. Antes, nota em `erro` chegava aqui e ia ao
        // provedor com número nulo.
        if (! $emissao->isEmitida() || empty($emissao->numero)) {
            throw new NfseException('Só nota emitida pode ser cancelada.', 'STATUS_NAO_CANCELAVEL');
        }
        if (! $this->provider instanceof NfseCancelaComCertificado) {
            throw new NfseException('Provedor sem cancelamento com o certificado da empresa.', 'CANCELAMENTO_SEM_CONTEXTO');
        }

        // Ambiente e certificado da EMPRESA da nota (não o global), igual à emissão.
        $config = $this->getConfig((int) $emissao->business_id);
        $certificado = $this->certificadoAtivo((int) $emissao->business_id);
        if (! $certificado instanceof NfeCertificado) {
            throw new CertificadoInvalidoException('Nenhum certificado A1 configurado.');
        }
        try {
            $cert = app(CertificadoService::class)->carregarCertificado($certificado);
        } catch (\RuntimeException $e) {
            throw new CertificadoInvalidoException('Certificado A1 da empresa indisponível: ' . $e->getMessage());
        }

        // O evento de cancelamento identifica a nota pela chave de acesso que a Sefin
        // devolveu na emissão (provider_protocolo), não pelo número. O autor é o prestador,
        // o mesmo CNPJ que foi na DPS.
        $confirmado = $this->provider->cancelarNoAmbiente(
            (string) $emissao->provider_protocolo,
            $motivo,
            $config->ambiente ?? 'homologacao',
            base64_encode($cert['pfx_binary']),
            (string) $cert['senha'],
            (string) $config->prestador_cnpj,
        );

        // Só grava `cancelada` com a confirmação do provedor. A troca de status estorna a
        // retenção no Financeiro (NfseEmissaoRetencaoObserver): falso "cancelada" mexia em
        // dinheiro com a nota ainda válida na Sefin.
        if (! $confirmado) {
            throw new NfseException('O provedor não confirmou o cancelamento. A nota continua emitida.', 'CANCELAMENTO_NAO_CONFIRMADO');
        }

        $emissao->update(['status' => 'cancelada']);

        Log::channel('nfse')->info('NFSe cancelada', [
            'business_id' => $emissao->business_id,
            'numero'      => $emissao->numero,
            // O motivo é texto livre do usuário e pode trazer CPF/CNPJ do tomador.
            'motivo'      => app(PiiRedactor::class)->redact($motivo),
        ]);
    }

    /**
     * Certificado da empresa da nota, lido do disco do dono (NfeBrasil) na hora de enviar.
     * O job chega sem ele (despacharEmissaoAsync, lote 2 PR 2.3); quem chama de forma
     * síncrona com o certificado no payload segue usando o que passou.
     *
     * Se o arquivo não carrega, o payload segue sem certificado e quem precisa dele recusa:
     * o SnNfseAdapter lança CertificadoInvalidoException ("Nenhum certificado A1 no payload").
     */
    private function comCertificadoDaEmpresa(NfseEmissaoPayload $payload): NfseEmissaoPayload
    {
        if ($payload->certPfxBase64 !== null && $payload->certSenha !== null) {
            return $payload;
        }
        $certificado = $this->certificadoAtivo($payload->businessId);
        if (! $certificado instanceof NfeCertificado) {
            return $payload;
        }
        try {
            $cert = app(CertificadoService::class)->carregarCertificado($certificado);
        } catch (\RuntimeException $e) {
            Log::channel('nfse')->warning('NFSe sem certificado legível no envio', [
                'business_id' => $payload->businessId,
                'mensagem'    => app(PiiRedactor::class)->redact($e->getMessage()),
            ]);

            return $payload;
        }

        return $payload->comCertificado(base64_encode($cert['pfx_binary']), (string) $cert['senha']);
    }

    private function getConfig(int $businessId): NfseProviderConfig
    {
        // SUPERADMIN: service chamado por job sem session — business_id explícito como param
        // withoutGlobalScopes() tira também o filtro do SoftDeletes: sem o whereNull, a
        // config que a empresa apagou continuava sendo usada para emitir.
        $config = NfseProviderConfig::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->first();

        if (! $config) {
            throw new NfseException(
                'Configuração NFSe não encontrada. Acesse Configurações → NFSe.',
                'CONFIG_AUSENTE',
            );
        }

        return $config;
    }

    /**
     * Certificado A1 ativo da empresa em `nfe_certificados` — o mesmo que a NF-e usa
     * (`CertificadoService::certificadoAtivo`), trocado na tela /fiscal/config?tab=cert.
     * Filtra pelo business_id explícito (ADR 0093): nunca o certificado de outra empresa.
     */
    private function certificadoAtivo(int $businessId): ?NfeCertificado
    {
        return app(CertificadoService::class)->certificadoAtivo($businessId);
    }

    private function validarCertificado(int $businessId): void
    {
        $cert = $this->certificadoAtivo($businessId);

        if (! $cert) {
            throw new CertificadoInvalidoException('Nenhum certificado A1 configurado.');
        }

        if ($cert->isVencido()) {
            throw new CertificadoInvalidoException(
                "Certificado expirado em {$cert->valido_ate->format('d/m/Y')}."
            );
        }
    }

    private function marcarErro(NfseEmissao $emissao, ?NfseException $e): void
    {
        // D7.a Wave 14: PiiRedactor em erro_mensagem + log — webservice prefeitura pode
        // ecoar CPF/CNPJ tomador no payload SOAP de erro (LGPD Art. 6º IX — minimização).
        $mensagemSegura = $e?->getMessage()
            ? app(PiiRedactor::class)->redact($e->getMessage())
            : null;

        $emissao->update([
            'status'        => 'erro',
            'erro_mensagem' => $mensagemSegura,
        ]);

        Log::channel('nfse')->error('NFSe erro', [
            'business_id' => $emissao->business_id,
            'codigo'      => $e?->codigo,
            'mensagem'    => $mensagemSegura,
        ]);
    }
}
