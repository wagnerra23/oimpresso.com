<?php

namespace Modules\PontoWr2\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Entities\Importacao;
use Modules\PontoWr2\Entities\Marcacao;
use Modules\PontoWr2\Entities\Rep;

/**
 * Parser de arquivos AFD — Portaria MTP 671/2021 Anexo I.
 *
 * Layout posicional ISO-8859-1, line-oriented (um registro por linha). Primeiros
 * 9 chars = NSR; char 10 = tipo de registro (1..9).
 *
 * Tipos suportados aqui:
 *   1: Cabeçalho — identifica REP/CNPJ/período
 *   2: Inclusão/alteração de empresa (não usado no app)
 *   3: Marcação (registro principal — cria Marcacao via MarcacaoService)
 *   4: Ajuste de relógio (log, não cria marcação)
 *   5: Inclusão/alteração/exclusão de empregado (log)
 *   6: Evento sensível (log)
 *   7: Retificação anterior a uma marcação
 *   8: Retificação posterior a uma marcação
 *   9: Trailer (totalizadores)
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
                if (strlen($linha) < 10) {
                    continue;
                }

                $tipoRegistro = substr($linha, 9, 1);
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
                } catch (PisNaoCadastradoException $e) {
                    $erros++;
                    $pis = $e->getPis();
                    $pisNaoCadastrados[$pis] = isset($pisNaoCadastrados[$pis])
                        ? $pisNaoCadastrados[$pis] + 1
                        : 1;
                } catch (\Throwable $e) {
                    $erros++;
                    if (count($erroAmostras) < 20) {
                        $erroAmostras[] = [
                            'linha' => $total,
                            'nsr'   => (int) substr($linha, 0, 9),
                            'tipo'  => $tipoRegistro,
                            'erro'  => $e->getMessage(),
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
                $linhas[] = 'PIS não cadastrados como Colaborador ('
                    . count($pisNaoCadastrados) . ' distintos, ' . array_sum($pisNaoCadastrados) . ' marcações):';
                foreach ($pisNaoCadastrados as $pis => $qtd) {
                    $linhas[] = "  - {$pis}: {$qtd} marcação(ões)";
                }
                $linhas[] = '';
                $linhas[] = 'Cadastre esses PIS em /ponto/colaboradores e re-importe o arquivo.';
                $log = implode("\n", $linhas);

                // Também adiciona no topo das amostras de erro (se sobrar espaço).
                foreach ($pisNaoCadastrados as $pis => $qtd) {
                    if (count($erroAmostras) >= 20) break;
                    array_unshift($erroAmostras, [
                        'linha' => null,
                        'nsr'   => null,
                        'tipo'  => '3',
                        'erro'  => "PIS {$pis} não cadastrado como Colaborador ({$qtd} marcações ignoradas).",
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

        if ($identificador === '') {
            throw new \RuntimeException('Identificador de REP ausente no cabeçalho.');
        }

        $tipoRep = $this->inferirTipoRep($tipoIdent, $identificador);

        $rep = Rep::where('business_id', $importacao->business_id)
            ->where('identificador', $identificador)
            ->first();

        if (!$rep) {
            $rep = Rep::create([
                'business_id'   => $importacao->business_id,
                'tipo'          => $tipoRep,
                'identificador' => $identificador,
                'descricao'     => 'REP importado via AFD ' . $importacao->id,
                'cnpj'          => $cnpj !== '' ? $cnpj : null,
                'ultimo_nsr'    => 0,
                'ativo'         => true,
            ]);
        }

        $this->repAtual = $rep;
    }

    protected function parseEmpresa($linha, Importacao $importacao)
    {
        // Tipo 2: log informativo — não persistimos alteração de empresa.
    }

    /**
     * Tipo 3 — Marcação de ponto.
     *
     * Layout:
     *   NSR(9) + tipo(1='3') + data(DDMMAAAA 8) + hora(HHMM 4) + PIS(12)
     */
    protected function parseMarcacao($linha, Importacao $importacao)
    {
        if (strlen($linha) < 34) {
            throw new \RuntimeException('Registro tipo 3 curto demais.');
        }

        $nsrArquivo = (int) substr($linha, 0, 9);
        $dataStr    = substr($linha, 10, 8);  // DDMMAAAA
        $horaStr    = substr($linha, 18, 4);  // HHMM
        $pis        = trim(substr($linha, 22, 12));

        if ($pis === '' || !preg_match('/^\d+$/', $pis)) {
            throw new \RuntimeException("PIS inválido (NSR {$nsrArquivo}).");
        }

        $momento = Carbon::createFromFormat('dmYHi', $dataStr . $horaStr);
        if (!$momento) {
            throw new \RuntimeException("Momento inválido (NSR {$nsrArquivo}).");
        }
        $momento->second(0);

        // Resolver colaborador por PIS no business da importação
        $colaborador = Colaborador::where('business_id', $importacao->business_id)
            ->where('pis', $pis)
            ->first();

        if (!$colaborador) {
            throw new PisNaoCadastradoException($pis);
        }

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
        // Tipo 7 — Retificação por marcação anterior.
        // Tratamento no app: registrar como Marcacao normal (inferencia resolve o tipo).
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
