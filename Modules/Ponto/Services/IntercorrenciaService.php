<?php

namespace Modules\Ponto\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Ponto\Entities\Intercorrencia;

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

        // D9.a OTel — aprovação intercorrência dispara reapuração (cascade impact).
        // PII: employee_id numérico apenas, sem CPF/PIS.
        OtelHelper::span('ponto.intercorrencia.aprovar', [
            'module'           => 'Ponto',
            'business_id'      => (int) $intercorrencia->business_id,
            'employee_id'      => (int) $intercorrencia->colaborador_config_id,
            'intercorrencia_id' => (string) $intercorrencia->id,
        ], function () use ($intercorrencia, $aprovadorId) {
            DB::transaction(function () use ($intercorrencia, $aprovadorId) {
                $intercorrencia->update([
                    'estado'      => Intercorrencia::ESTADO_APROVADA,
                    'aprovador_id' => $aprovadorId,
                    'aprovado_em' => now(),
                ]);

                // Dispara reapuração do dia afetado.
                // Multi-tenant Tier 0 (ADR 0093): job exige $businessId no constructor
                // pra resolver tenant sem session no queue worker.
                \Modules\Ponto\Jobs\ReapurarDiaJob::dispatch(
                    $intercorrencia->business_id,
                    $intercorrencia->colaborador_config_id,
                    $intercorrencia->data
                );
            });
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

        // Wave 11 D7.a — log auditoria com motivo mascarado. Motivo é texto livre do
        // aprovador (RH) e pode conter PII do colaborador (CPF/CNPJ/PIS/email/tel).
        // O VALOR no DB permanece cru (acesso restrito + finalidade legítima Art. 7º V
        // LGPD — gestão de contrato trabalho); apenas o LOG (que vai pra storage/laravel.log
        // + possível external sink OTel) é redactado.
        Log::info('ponto.intercorrencia.rejeitada', [
            'business_id'      => $intercorrencia->business_id,
            'intercorrencia_id' => $intercorrencia->id,
            'aprovador_id'     => $aprovadorId,
            'motivo_redacted'  => app(PiiRedactor::class)->redact($motivo),
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
