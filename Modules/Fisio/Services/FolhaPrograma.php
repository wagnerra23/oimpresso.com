<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Services\VaultEncryptionService;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Entities\PortalToken;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Throwable;

/**
 * Folha para imprimir do programa do paciente (B-12; UC-FPS-37, UC-FPS-38).
 *
 * Separado do controller pelo mesmo motivo do ProntuarioExportacao: o teste lê o que vai para o
 * papel sem decodificar o PDF.
 *
 * LGPD (art. 11): entra SÓ o programa — nome do paciente, instrução de parada, versão de crise e
 * os exercícios (nome, dose, passos, cuidados, observação da dose e a foto do exercício). Fica de
 * fora tudo que é clínico além disso: queixa, objetivos, marcadores de condição, prontuário, dor,
 * e a confirmação de contraindicação com a justificativa (é registro do profissional, não do
 * paciente).
 *
 * QR do portal: NÃO entra. O banco guarda só o hash do token (PortalTokenService), então o link
 * vivo não pode ser reimpresso; gerar um novo para pôr no papel revogaria o que o paciente já
 * usa. A folha diz isso em texto — com link vivo, "use o link que a clínica enviou"; sem link,
 * "peça o acesso à clínica". Imprimir nunca emite nem revoga link.
 *
 * Foto: só a mídia própria da clínica (exercício global não tem), JPG ou PNG — o dompdf não lê
 * WebP sem extensão extra, e vídeo não cabe no papel; nos dois casos a folha aponta o portal.
 * A imagem vai embutida (data URI): o dompdf não busca nada pela rede.
 */
final class FolhaPrograma
{
    private const FOTOS_NO_PAPEL = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly MidiaExercicio $midia,
        private readonly VaultEncryptionService $vault,
    ) {}

    /** @return array<string, mixed> */
    public function montar(Program $programa, int $businessId): array
    {
        $programa->loadMissing('patient.contact:id,name');

        $itens = ProgramItem::query()
            ->where('program_id', $programa->id)
            ->with('exercise')
            ->orderBy('position')
            ->get()
            ->map(fn (ProgramItem $i) => [
                'nome' => (string) $i->exercise?->name,
                'dose' => $this->dose($i),
                'passos' => array_values(array_map('strval', (array) ($i->exercise?->steps ?? []))),
                'cuidados' => array_values(array_map('strval', (array) ($i->exercise?->safety_notes ?? []))),
                'observacao' => $i->notes,
                'midia' => $i->exercise ? $this->midiaNoPapel($i->exercise) : null,
            ])
            ->values()
            ->all();

        $temLink = PortalToken::query()
            ->where('business_id', $businessId)
            ->where('patient_id', $programa->patient_id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        return [
            'paciente' => ['nome' => $programa->patient?->contact?->name],
            'parada' => $programa->stop_instruction,
            'crise' => array_values(array_map('strval', (array) ($programa->crisis_items ?? []))),
            'itens' => $itens,
            'portal_ativo' => $temLink,
            'empresa' => (string) DB::table('business')->where('id', $businessId)->value('name'),
            'emitido_em' => now()->format('d/m/Y'),
        ];
    }

    /** "3 séries × 12 repetições · segure 5 s · 2 vezes ao dia" — por extenso, para o papel. */
    private function dose(ProgramItem $i): string
    {
        $partes = [];
        if ($i->sets) {
            $partes[] = $i->sets.' '.($i->sets === 1 ? 'série' : 'séries');
        }
        if ($i->reps) {
            $partes[] = $i->reps.' '.($i->reps === 1 ? 'repetição' : 'repetições');
        }
        $texto = implode(' × ', $partes);
        if ($i->hold_s) {
            $texto .= ($texto === '' ? '' : ' · ').'segure '.$i->hold_s.' s';
        }
        $vezes = max(1, (int) $i->times_per_day);
        $texto .= ($texto === '' ? '' : ' · ').$vezes.' '.($vezes === 1 ? 'vez' : 'vezes').' ao dia';

        return $texto;
    }

    /** @return array{tipo: string, src?: string}|null */
    private function midiaNoPapel(Exercise $exercicio): ?array
    {
        if ($exercicio->ehGlobal()) {
            return null;
        }
        $arquivo = $this->midia->vigente($exercicio);
        if ($arquivo === null) {
            return null;
        }
        $mime = (string) $arquivo->mime_type;
        if (! in_array($mime, self::FOTOS_NO_PAPEL, true)) {
            return ['tipo' => MidiaExercicio::tipo($mime) ?? 'outro'];
        }

        try {
            $disco = (string) ($arquivo->disk ?: config('arquivos.disk_default', 'local'));
            $bytes = $arquivo->encrypted
                ? $this->vault->getDecrypted($disco, (string) $arquivo->storage_path)
                : Storage::disk($disco)->get((string) $arquivo->storage_path);
        } catch (Throwable) {
            $bytes = null;
        }
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return ['tipo' => 'foto', 'src' => 'data:'.$mime.';base64,'.base64_encode($bytes)];
    }
}
