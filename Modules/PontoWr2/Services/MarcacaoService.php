<?php

namespace Modules\PontoWr2\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\PontoWr2\Entities\Marcacao;
use Modules\PontoWr2\Entities\Rep;
use RuntimeException;

/**
 * Orquestra criação de marcações com hash encadeado (SHA-256) e NSR sequencial.
 *
 * Regras:
 *   - O hash da marcação N é SHA-256 de (hash_anterior || payload canônico || hash algoritmo).
 *   - hash_anterior é o hash da última marcação aceita no mesmo REP (ou null se primeira).
 *   - Operação é transacional: lock na REP (via NsrService), cálculo de hash, INSERT.
 *   - Não usa UPDATE/DELETE em marcações (append-only, Portaria 671/2021).
 *
 * Refs: Portaria MTP 671/2021 (Art. 85 — imutabilidade; Anexo I — NSR sequencial).
 */
class MarcacaoService
{
    /** @var NsrService */
    protected $nsr;

    public function __construct(NsrService $nsr)
    {
        $this->nsr = $nsr;
    }

    /**
     * Registra nova marcação no REP informado (ou virtual se $repId = null).
     *
     * @param array $dados Campos da marcação (ver Marcacao::$fillable).
     * @return Marcacao
     */
    public function registrar(array $dados)
    {
        if (empty($dados['business_id']) || empty($dados['colaborador_config_id'])) {
            throw new RuntimeException('registrar(): business_id e colaborador_config_id são obrigatórios.');
        }
        if (empty($dados['momento'])) {
            $dados['momento'] = now();
        }
        if (empty($dados['origem'])) {
            $dados['origem'] = Marcacao::ORIGEM_MANUAL;
        }
        if (empty($dados['tipo'])) {
            throw new RuntimeException('registrar(): tipo da marcação é obrigatório.');
        }
        if (empty($dados['usuario_criador_id'])) {
            throw new RuntimeException('registrar(): usuario_criador_id é obrigatório.');
        }

        $repId = isset($dados['rep_id']) ? $dados['rep_id'] : null;
        $service = $this;

        return DB::transaction(function () use ($dados, $repId, $service) {
            // 1) NSR sequencial com lock pessimista no REP
            $nsr = $service->nsr->proximo($repId);

            // 2) Hash anterior = hash da última marcação aceita no mesmo REP
            $hashAnterior = null;
            if ($repId !== null) {
                $ultima = Marcacao::where('rep_id', $repId)
                    ->orderByDesc('nsr')
                    ->first();
                if ($ultima) {
                    $hashAnterior = $ultima->hash;
                }
            }

            // 3) Payload canônico para hash
            $payload = $service->payloadCanonico(array_merge($dados, [
                'nsr'           => $nsr,
                'hash_anterior' => $hashAnterior,
            ]));

            $algoritmo = config('pontowr2.marcacao.hash_algoritmo', 'sha256');
            $hash = hash($algoritmo, $payload);

            // 4) Monta e persiste
            $atributos = array_merge($dados, [
                'nsr'           => $nsr,
                'hash_anterior' => $hashAnterior,
                'hash'          => $hash,
            ]);

            return Marcacao::create($atributos);
        });
    }

    /**
     * Cria uma marcação de ANULACAO apontando para $original, com hash encadeado.
     */
    public function anular(Marcacao $original, $usuarioId, $motivo)
    {
        if ($original->origem === Marcacao::ORIGEM_ANULACAO) {
            throw new RuntimeException('Não é possível anular uma marcação de anulação.');
        }

        $janela = (int) config('pontowr2.marcacao.janela_correcao_minutos', 5);
        if ($janela > 0 && $original->created_at instanceof Carbon) {
            // Anulação permitida a qualquer tempo; janela de correção é separada.
            // Mantido como comentário/placeholder — política definida em operação.
        }

        return $this->registrar([
            'business_id'           => $original->business_id,
            'colaborador_config_id' => $original->colaborador_config_id,
            'rep_id'                => $original->rep_id,
            'momento'               => now(),
            'origem'                => Marcacao::ORIGEM_ANULACAO,
            'tipo'                  => $original->tipo,
            'marcacao_anulada_id'   => $original->id,
            'usuario_criador_id'    => $usuarioId,
            'dispositivo_id'        => 'anulacao:' . substr(md5($motivo), 0, 16),
        ]);
    }

    /**
     * Verifica integridade da cadeia hash para um REP.
     * Percorre todas as marcações do REP ordenadas por NSR e valida
     * que hash_anterior[N] == hash[N-1] e que hash[N] == H(payload[N]).
     *
     * @return array ['ok' => bool, 'quebrados' => [ ['nsr' => ..., 'motivo' => ...] ]]
     */
    public function verificarIntegridade($repId)
    {
        $quebrados = [];
        $ultimoHash = null;

        Marcacao::where('rep_id', $repId)
            ->orderBy('nsr')
            ->chunk(500, function ($chunk) use (&$quebrados, &$ultimoHash) {
                foreach ($chunk as $m) {
                    if ($m->hash_anterior !== $ultimoHash) {
                        $quebrados[] = [
                            'nsr'    => $m->nsr,
                            'motivo' => 'hash_anterior não bate com hash da marcação N-1',
                        ];
                    }
                    $esperado = hash(
                        config('pontowr2.marcacao.hash_algoritmo', 'sha256'),
                        $this->payloadCanonico($m->getAttributes())
                    );
                    if ($esperado !== $m->hash) {
                        $quebrados[] = [
                            'nsr'    => $m->nsr,
                            'motivo' => 'hash recalculado diverge do armazenado',
                        ];
                    }
                    $ultimoHash = $m->hash;
                }
            });

        return ['ok' => empty($quebrados), 'quebrados' => $quebrados];
    }

    /**
     * Monta string canônica determinística para hash.
     * Ordem: business_id|colaborador_config_id|rep_id|nsr|momento|origem|tipo|hash_anterior|usuario_criador_id
     */
    public function payloadCanonico(array $d)
    {
        $momento = isset($d['momento']) ? $d['momento'] : null;
        if ($momento instanceof Carbon) {
            $momento = $momento->format('Y-m-d H:i:s');
        } elseif (is_string($momento) && $momento !== '') {
            // Normaliza para o formato canônico
            try {
                $momento = Carbon::parse($momento)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // Mantém string original se não parseável
            }
        }

        $partes = [
            isset($d['business_id'])           ? $d['business_id']           : '',
            isset($d['colaborador_config_id']) ? $d['colaborador_config_id'] : '',
            isset($d['rep_id'])                ? $d['rep_id']                : '',
            isset($d['nsr'])                   ? $d['nsr']                   : '',
            $momento !== null ? $momento : '',
            isset($d['origem'])                ? $d['origem']                : '',
            isset($d['tipo'])                  ? $d['tipo']                  : '',
            isset($d['hash_anterior'])         ? $d['hash_anterior']         : '',
            isset($d['usuario_criador_id'])    ? $d['usuario_criador_id']    : '',
        ];

        return implode('|', $partes);
    }
}
