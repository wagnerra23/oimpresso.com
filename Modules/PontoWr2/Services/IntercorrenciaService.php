<?php

namespace Modules\PontoWr2\Services;

use Illuminate\Support\Facades\DB;
use Modules\PontoWr2\Entities\Intercorrencia;

class IntercorrenciaService
{
    public function criar(array $dados, int $solicitanteId): Intercorrencia
    {
        $dados['codigo'] = $this->gerarCodigo($dados['data']);
        $dados['solicitante_id'] = $solicitanteId;
        $dados['estado'] = Intercorrencia::ESTADO_RASCUNHO;
        return Intercorrencia::create($dados);
    }

    public function submeter(Intercorrencia $i): void
    {
        abort_unless(
            $i->estado === Intercorrencia::ESTADO_RASCUNHO,
            422,
            'Apenas rascunhos podem ser submetidos.'
        );

        $i->update(['estado' => Intercorrencia::ESTADO_PENDENTE]);
    }

    public function aprovar(Intercorrencia $intercorrencia, int $aprovadorId, ?string $observacao = null): void
    {
        abort_unless(
            $intercorrencia->estado === Intercorrencia::ESTADO_PENDENTE,
            422,
            'Só é possível aprovar intercorrências pendentes.'
        );

        DB::transaction(function () use ($intercorrencia, $aprovadorId) {
            $intercorrencia->update([
                'estado'      => Intercorrencia::ESTADO_APROVADA,
                'aprovador_id' => $aprovadorId,
                'aprovado_em' => now(),
            ]);

            // Dispara reapuração do dia afetado
            \Modules\PontoWr2\Jobs\ReapurarDiaJob::dispatch(
                $intercorrencia->colaborador_config_id,
                $intercorrencia->data
            );
        });
    }

    public function rejeitar(Intercorrencia $intercorrencia, int $aprovadorId, string $motivo): void
    {
        $intercorrencia->update([
            'estado'          => Intercorrencia::ESTADO_REJEITADA,
            'aprovador_id'    => $aprovadorId,
            'aprovado_em'     => now(),
            'motivo_rejeicao' => $motivo,
        ]);
    }

    public function aprovarEmLote(array $ids, int $aprovadorId): int
    {
        $count = 0;
        foreach (Intercorrencia::whereIn('id', $ids)->pendentes()->get() as $inc) {
            $this->aprovar($inc, $aprovadorId);
            $count++;
        }
        return $count;
    }

    public function cancelar(Intercorrencia $intercorrencia, int $usuarioId): void
    {
        abort_unless(
            in_array($intercorrencia->estado, [Intercorrencia::ESTADO_RASCUNHO, Intercorrencia::ESTADO_PENDENTE]),
            422,
            'Não é possível cancelar nesse estado.'
        );

        $intercorrencia->update(['estado' => Intercorrencia::ESTADO_CANCELADA]);
    }

    private function gerarCodigo(string $data): string
    {
        $prefixo = 'INC-' . str_replace('-', '', substr($data, 0, 10));
        $count = Intercorrencia::where('codigo', 'like', "{$prefixo}%")->count() + 1;
        return sprintf('%s-%03d', $prefixo, $count);
    }
}
