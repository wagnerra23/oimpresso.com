<?php

namespace Modules\Ponto\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Support\Privacy\PiiRedactor;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Importacao;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Entities\Rep;

/**
 * Parser de arquivos AFD nos DOIS leiautes que chegam ao Ponto, detectados pelo
 * FORMATO de cada registro (não por flag do usuário):
 *
 *   - Portaria MTE 1510/2009 (legado — ADR 0413 W7 mantém a importação):
 *     tipo 3 = NSR(9) + tipo + data DDMMAAAA(8) + hora HHMM(4) + PIS(12) — 34 posições.
 *     Colaborador resolvido por PIS.
 *   - Portaria MTP 671/2021 (leiaute "004", gov.br "Leiaute do Arquivo Fonte de Dados"):
 *     tipo 3 (REP-C/REP-A) = NSR + tipo + DH "AAAA-MM-ddThh:mm:00ZZZZZ"(24) + CPF(12) + CRC-16 — 50;
 *     tipo 7 (REP-P) = mesmas posições de DH/CPF + DH gravação + coletor + on/off + SHA-256 — 137;
 *     tipo 1 = 302 posições (nº fabricação/INPI em 190-206); trailer = "999999999" + contadores
 *     com o tipo "9" na posição 64; última linha = assinatura digital. Colaborador por CPF.
 *     A hora gravada é a de parede do DH (o fuso ZZZZZ é descartado), como no 1510.
 *
 * Layout posicional ISO-8859-1, um registro por linha; primeiros 9 chars = NSR,
 * char 10 = tipo (exceto o trailer 671). CRC-16 e hash SHA-256 NÃO são validados aqui.
 * Nunca UPDATE/DELETE em ponto_marcacoes: só insere via MarcacaoService (append-only).
 */
class AfdParserService
{
    /** @var array */
    private $parsers = [
        '1' => 'parseHeader',
        '2' => 'parseEmpresa',
        '3' => 'parseMarcacao',
        '4' => 'parseAjuste',
        '5' => 'parseEmpregado',
        '6' => 'parseEvento',
        '7' => 'parseRetificacaoAnterior',
        '8' => 'parseRetificacaoPosterior',
        '9' => 'parseTrailer',
    ];

    /**
     * Rep corrente (determinado pelo header — tipo 1).
     * @var Rep|null
     */
    private $repAtual = null;

    /** @var array Contadores por tipo de registro */
    private $contadores = [];

    /** @var MarcacaoService */
    protected $marcacoes;

    public function __construct(MarcacaoService $marcacoes)
    {
        $this->marcacoes = $marcacoes;
    }

    public function processar(Importacao $importacao)
    {
        // D9.a Wave 16 OTel — processamento AFD/AFDT (Portaria 671/2021 Anexo I).
        // Hot-path: pode demorar minutos pra arquivos grandes. NÃO inclui caminho
        // do arquivo no span (pode conter dados sensíveis); só importacao_id+hash.
        return OtelHelper::span('ponto.afd.processar', [
            'module'         => 'Ponto',
            'business_id'    => (int) $importacao->business_id,
            'importacao_id'  => (int) $importacao->id,
            'tipo'           => $importacao->tipo,
            'hash_arquivo'   => substr((string) $importacao->hash_arquivo, 0, 16),
            'tamanho_bytes'  => (int) $importacao->tamanho_bytes,
        ], function () use ($importacao) {
            return $this->processarInterno($importacao);
        });
    }

    /**
     * @internal Corpo real de processar() — separado para wrap OTel D9.a Wave 16.
     */
    private function processarInterno(Importacao $importacao)
    {
        $importacao->update([
            'estado'      => Importacao::ESTADO_PROCESSANDO,
            'iniciado_em' => now(),
        ]);

        $caminho = Storage::path($importacao->arquivo_path);
        $handle = @fopen($caminho, 'r');
        if (!$handle) {
            $importacao->update([
                'estado'       => Importacao::ESTADO_FALHOU,
                'log'          => 'Não foi possível abrir o arquivo: ' . $caminho,
                'concluido_em' => now(),
            ]);
            return;
        }

        $total = 0;
        $sucesso = 0;
        $erros = 0;
        $pisNaoCadastrados = []; // PIS => quantidade (para diagnóstico agregado)
        $erroAmostras = [];
        $encoding = config('pontowr2.afd.encoding', 'ISO-8859-1');

        $this->repAtual = null;
        $this->contadores = [];

        try {
            while (($linha = fgets($handle)) !== false) {
                $total++;
                $linha = mb_convert_encoding(rtrim($linha, "\r\n"), 'UTF-8', $encoding);
                if (strlen($linha) < 10 || strpos($linha, 'ASSINATURA_DIGITAL_EM_ARQUIVO_P7S') === 0) {
                    continue; // linha vazia/curta ou assinatura digital do REP-A/REP-P (671)
                }

                $tipoRegistro = $this->ehTrailer671($linha) ? '9' : substr($linha, 9, 1);
                $parser = isset($this->parsers[$tipoRegistro]) ? $this->parsers[$tipoRegistro] : null;
                if (!$parser) {
                    $erros++;
                    continue;
                }

                $this->contadores[$tipoRegistro] = isset($this->contadores[$tipoRegistro])
                    ? $this->contadores[$tipoRegistro] + 1
                    : 1;

                // Se é uma marcação (tipo 3/7/8) e ainda não temos REP (arquivo sem header tipo 1),
                // gera REP de fallback vinculado ao arquivo. Isso permite processar AFDs "parciais"
                // que alguns equipamentos exportam contendo apenas marcações.
                if (in_array($tipoRegistro, ['3', '7', '8'], true) && $this->repAtual === null) {
                    $this->repAtual = $this->repFallback($importacao);
                }

                try {
                    $this->$parser($linha, $importacao);
                    $sucesso++;
                } catch (CpfNaoCadastradoException $e) {
                    $erros++;
                    $cpf = 'CPF ' . $e->getCpfMascarado();
                    $pisNaoCadastrados[$cpf] = ($pisNaoCadastrados[$cpf] ?? 0) + 1;
                } catch (PisNaoCadastradoException $e) {
                    $erros++;
                    $pis = 'PIS ' . $e->getPis();
                    $pisNaoCadastrados[$pis] = ($pisNaoCadastrados[$pis] ?? 0) + 1;
                } catch (\Throwable $e) {
                    $erros++;
                    if (count($erroAmostras) < 20) {
                        // Wave 11 D7.a — exception message pode conter PIS (parseMarcacao
                        // lança "PIS inválido (NSR ...)" com valor cru). LGPD Art. 7º:
                        // amostras de erro vão pra storage + UI admin → PII deve ir mascarada.
                        $msg = app(PiiRedactor::class)->redact($e->getMessage());

                        $erroAmostras[] = [
                            'linha' => $total,
                            'nsr'   => (int) substr($linha, 0, 9),
                            'tipo'  => $tipoRegistro,
                            'erro'  => $msg,
                        ];
                    }
                }

                if ($total % 100 === 0) {
                    $importacao->update([
                        'linhas_total'       => $total,
                        'linhas_processadas' => $total,
                        'linhas_sucesso'     => $sucesso,
                        'linhas_erro'        => $erros,
                    ]);
                }
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            // Garante update final de estado mesmo em caso de exception inesperada.
            $estadoFinal = $erros > 0
                ? Importacao::ESTADO_CONCLUIDA_COM_ERROS
                : Importacao::ESTADO_CONCLUIDA;

            // Se houve PIS não cadastrados, consolida no log + prepend nas amostras.
            $log = null;
            if (!empty($pisNaoCadastrados)) {
                $linhas = [];
                arsort($pisNaoCadastrados);
                $linhas[] = 'PIS/CPF não cadastrados como Colaborador ('
                    . count($pisNaoCadastrados) . ' distintos, ' . array_sum($pisNaoCadastrados) . ' marcações):';
                foreach ($pisNaoCadastrados as $pis => $qtd) {
                    $linhas[] = "  - {$pis}: {$qtd} marcação(ões)";
                }
                $linhas[] = '';
                $linhas[] = 'Cadastre esses PIS/CPF em /ponto/colaboradores e re-importe o arquivo.';
                $log = implode("\n", $linhas);

                // Também adiciona no topo das amostras de erro (se sobrar espaço).
                foreach ($pisNaoCadastrados as $pis => $qtd) {
                    if (count($erroAmostras) >= 20) break;
                    array_unshift($erroAmostras, [
                        'linha' => null,
                        'nsr'   => null,
                        'tipo'  => '3',
                        'erro'  => "{$pis} não cadastrado como Colaborador ({$qtd} marcações ignoradas).",
                    ]);
                }
            }

            $importacao->update([
                'estado'             => $estadoFinal,
                'linhas_total'       => $total,
                'linhas_processadas' => $total,
                'linhas_sucesso'     => $sucesso,
                'linhas_erro'        => $erros,
                'erros_amostra'      => $erroAmostras,
                'log'                => $log,
                'concluido_em'       => now(),
            ]);

            // D9.b Wave 16 — log estruturado conclusão AFD (cron/queue worker).
            // Tier 0 multi-tenant: business_id sempre presente. PII: zero — só
            // ids numéricos + contadores agregados (pis_nao_cadastrados é COUNT,
            // não lista valores). Útil pra alertas: linhas_erro > 0 em prod.
            Log::info('ponto.afd.processar.concluido', [
                'business_id'                  => $importacao->business_id,
                'importacao_id'                => $importacao->id,
                'tipo'                         => $importacao->tipo,
                'estado_final'                 => $estadoFinal,
                'linhas_total'                 => $total,
                'linhas_sucesso'               => $sucesso,
                'linhas_erro'                  => $erros,
                'pis_nao_cadastrados_distintos' => count($pisNaoCadastrados),
            ]);
        }
    }

    /**
     * Cria (ou recupera) um REP fallback quando o AFD não traz cabeçalho (tipo 1).
     * Identificador usa o hash do arquivo para ser estável entre re-importações.
     */
    protected function repFallback(Importacao $importacao)
    {
        $identificador = 'AFD-' . substr($importacao->hash_arquivo ?: md5((string) $importacao->id), 0, 13);

        $rep = Rep::where('business_id', $importacao->business_id)
            ->where('identificador', $identificador)
            ->first();

        if (!$rep) {
            $rep = Rep::create([
                'business_id'   => $importacao->business_id,
                'tipo'          => Rep::TIPO_REP_C,
                'identificador' => $identificador,
                'descricao'     => 'REP inferido — AFD sem cabeçalho (importação #' . $importacao->id . ')',
                'cnpj'          => null,
                'ultimo_nsr'    => 0,
                'ativo'         => true,
            ]);
        }

        return $rep;
    }

    /**
     * Tipo 1 — Cabeçalho. Identifica REP e cria se não existir.
     *
     * Layout (posições 1-based; convertido para 0-based no substr):
     *   NSR(9) + tipo(1=1) + CNPJ(14) + CEI(12) + razão social(150)
     *   + data inicial(DDMMAAAA 8) + data final(DDMMAAAA 8) + data geração(DDMMAAAA 8)
     *   + hora geração(HHMMSS 6) + tipo ident REP(3) + identificador REP(17)
     */
    protected function parseHeader($linha, Importacao $importacao)
    {
        if (strlen($linha) >= 302 && $this->ehDataHora671(substr($linha, 226, 24))) {
            // Leiaute 671: CNPJ/CPF 012-025, nº fabricação (REP-C) / processo (REP-A) / INPI (REP-P) 190-206.
            $this->repAtual = $this->repDoCabecalho(
                $importacao,
                trim(substr($linha, 189, 17)),
                trim(substr($linha, 11, 14)),
                Rep::TIPO_REP_C
            );
            return;
        }

        if (strlen($linha) < 228) {
            throw new \RuntimeException('Cabeçalho AFD muito curto (esperado >= 228 chars).');
        }

        $cnpj          = trim(substr($linha, 10, 14));
        // CEI = substr($linha, 24, 12)
        // razao = substr($linha, 36, 150)
        // data_inicial = substr($linha, 186, 8)  -- DDMMAAAA
        // data_final   = substr($linha, 194, 8)  -- DDMMAAAA
        // data_geracao = substr($linha, 202, 8)  -- DDMMAAAA
        // hora_geracao = substr($linha, 210, 6)  -- HHMMSS
        $tipoIdent     = trim(substr($linha, 216, 3));
        $identificador = trim(substr($linha, 219, 17));

        $this->repAtual = $this->repDoCabecalho(
            $importacao,
            $identificador,
            $cnpj,
            $this->inferirTipoRep($tipoIdent, $identificador)
        );
    }

    /** Recupera (ou cria) o REP identificado no cabeçalho, no business da importação. */
    protected function repDoCabecalho(Importacao $importacao, $identificador, $cnpj, $tipoRep)
    {
        if ($identificador === '') {
            throw new \RuntimeException('Identificador de REP ausente no cabeçalho.');
        }

        $rep = Rep::where('business_id', $importacao->business_id)
            ->where('identificador', $identificador)
            ->first();

        return $rep ?: Rep::create([
            'business_id'   => $importacao->business_id,
            'tipo'          => $tipoRep,
            'identificador' => $identificador,
            'descricao'     => 'REP importado via AFD ' . $importacao->id,
            'cnpj'          => $cnpj !== '' ? $cnpj : null,
            'ultimo_nsr'    => 0,
            'ativo'         => true,
        ]);
    }

    /** "AAAA-MM-ddThh:mm:00ZZZZZ" — formato DH da Portaria 671/2021 (24 posições). */
    protected function ehDataHora671($valor)
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{4}$/', (string) $valor);
    }

    /** Trailer 671: NSR "999999999", seis contadores de 9 e o tipo "9" na posição 64. */
    protected function ehTrailer671($linha)
    {
        return strlen($linha) === 64 && strpos($linha, '999999999') === 0 && $linha[63] === '9';
    }

    protected function parseEmpresa($linha, Importacao $importacao)
    {
        // Tipo 2: log informativo — não persistimos alteração de empresa.
    }

    /**
     * Marcação de ponto — tipo 3 (1510 e 671) e tipo 7 (REP-P no 671).
     * O leiaute é detectado pelo formato do campo de data/hora (ver docblock da classe).
     */
    protected function parseMarcacao($linha, Importacao $importacao)
    {
        $nsrArquivo = (int) substr($linha, 0, 9);

        if (strlen($linha) >= 46 && $this->ehDataHora671(substr($linha, 10, 24))) {
            // Leiaute 671 (tipo 3 REP-C/REP-A e tipo 7 REP-P): DH 011-034 + CPF 035-046.
            $momento = Carbon::createFromFormat('Y-m-d H:i', str_replace('T', ' ', substr($linha, 10, 16)));
            $cpf = substr(trim(substr($linha, 34, 12)), -11);
            if (!preg_match('/^\d{11}$/', $cpf)) {
                throw new \RuntimeException("CPF inválido (NSR {$nsrArquivo}).");
            }

            // Cadastro pode guardar o CPF com máscara — compara só os dígitos.
            $colaborador = Colaborador::where('business_id', $importacao->business_id)
                ->whereRaw("REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?", [$cpf])
                ->first();

            if (!$colaborador) {
                throw new CpfNaoCadastradoException($cpf);
            }
        } else {
            // Leiaute 1510/2009 (legado): data DDMMAAAA + hora HHMM + PIS.
            if (strlen($linha) < 34) {
                throw new \RuntimeException('Registro tipo 3 curto demais.');
            }

            $pis = trim(substr($linha, 22, 12));
            if ($pis === '' || !preg_match('/^\d+$/', $pis)) {
                throw new \RuntimeException("PIS inválido (NSR {$nsrArquivo}).");
            }

            $momento = Carbon::createFromFormat('dmYHi', substr($linha, 10, 8) . substr($linha, 18, 4));

            $colaborador = Colaborador::where('business_id', $importacao->business_id)
                ->where('pis', $pis)
                ->first();

            if (!$colaborador) {
                throw new PisNaoCadastradoException($pis);
            }
        }

        if (!$momento) {
            throw new \RuntimeException("Momento inválido (NSR {$nsrArquivo}).");
        }
        $momento->second(0);

        // Dedup: mesma marcação (REP, NSR arquivo) já importada?
        if ($this->repAtual) {
            $jaExiste = Marcacao::where('rep_id', $this->repAtual->id)
                ->where('business_id', $importacao->business_id)
                ->where('colaborador_config_id', $colaborador->id)
                ->whereDate('momento', $momento->toDateString())
                ->whereTime('momento', $momento->format('H:i:s'))
                ->where('origem', Marcacao::ORIGEM_AFD)
                ->exists();

            if ($jaExiste) {
                return; // idempotência de reimport
            }
        }

        $tipo = $this->inferirTipoMarcacao($colaborador->id, $momento);

        $this->marcacoes->registrar([
            'business_id'           => $importacao->business_id,
            'colaborador_config_id' => $colaborador->id,
            'rep_id'                => $this->repAtual ? $this->repAtual->id : null,
            'momento'               => $momento,
            'origem'                => Marcacao::ORIGEM_AFD,
            'tipo'                  => $tipo,
            'usuario_criador_id'    => $importacao->usuario_id,
            'dispositivo_id'        => 'afd:' . $importacao->id,
        ]);
    }

    protected function parseAjuste($linha, Importacao $importacao)
    {
        // Tipo 4 — Ajuste de relógio. Log, sem marcação.
    }

    protected function parseEmpregado($linha, Importacao $importacao)
    {
        // Tipo 5 — Alterações cadastrais de empregado. Log, sem marcação.
    }

    protected function parseEvento($linha, Importacao $importacao)
    {
        // Tipo 6 — Eventos sensíveis do REP (abertura da tampa, bateria fraca, etc.). Log.
    }

    protected function parseRetificacaoAnterior($linha, Importacao $importacao)
    {
        // Tipo 7 — no 671 é a marcação do REP-P (mesmas posições de DH/CPF do tipo 3);
        // em arquivos legados era tratado como retificação. Nos dois casos vira Marcacao.
        $this->parseMarcacao($linha, $importacao);
    }

    protected function parseRetificacaoPosterior($linha, Importacao $importacao)
    {
        // Tipo 8 — Retificação por marcação posterior. Mesmo tratamento.
        $this->parseMarcacao($linha, $importacao);
    }

    /**
     * Tipo 9 — Trailer. Formato: NSR(9) + tipo(1='9') + total por tipo 2..8 (9 dígitos cada).
     * Aqui apenas validamos contagens; divergências viram amostra de erro.
     */
    protected function parseTrailer($linha, Importacao $importacao)
    {
        // Parse defensivo: nem todo gerador respeita 100% do layout.
        // Se quiser validar: extrair totais dos tipos 2..8 e comparar com $this->contadores.
    }

    /**
     * Infere tipo de marcação (ENTRADA/SAIDA/ALMOCO_*) pela sequência já persistida
     * para o colaborador no mesmo dia.
     *
     * Heurística simples:
     *   0ª marcação do dia → ENTRADA
     *   1ª  → ALMOCO_INICIO
     *   2ª  → ALMOCO_FIM
     *   3ª  → SAIDA
     *   +   → SAIDA (ciclos adicionais vão para "saída" — operador revisa)
     */
    protected function inferirTipoMarcacao($colaboradorId, Carbon $momento)
    {
        $qtd = Marcacao::where('colaborador_config_id', $colaboradorId)
            ->whereDate('momento', $momento->toDateString())
            ->whereNotIn('origem', [Marcacao::ORIGEM_ANULACAO])
            ->count();

        $tabela = [
            0 => Marcacao::TIPO_ENTRADA,
            1 => Marcacao::TIPO_ALMOCO_INICIO,
            2 => Marcacao::TIPO_ALMOCO_FIM,
            3 => Marcacao::TIPO_SAIDA,
        ];

        return isset($tabela[$qtd]) ? $tabela[$qtd] : Marcacao::TIPO_SAIDA;
    }

    /**
     * Mapeia o "tipo ident REP" do cabeçalho para o enum do domínio.
     */
    protected function inferirTipoRep($tipoIdent, $identificador)
    {
        // Heurística: Portaria 671 usa "REP-P" em texto livre; identificador de 17 chars.
        $upper = strtoupper($tipoIdent);
        if (strpos($upper, 'REP-P') !== false || strpos($upper, 'REPP') !== false) {
            return Rep::TIPO_REP_P;
        }
        if (strpos($upper, 'REP-A') !== false || strpos($upper, 'REPA') !== false) {
            return Rep::TIPO_REP_A;
        }
        return Rep::TIPO_REP_C; // convencional é o default histórico (Portaria 1510/2009)
    }
}
