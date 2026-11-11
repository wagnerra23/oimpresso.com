<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Modules\Arquivos\Services\VaultEncryptionService;
use Modules\Fisio\Ai\Agents\TranscricaoDocumentoAgent;
use Modules\Fisio\Entities\DocumentoPaciente;
use Modules\Fisio\Entities\MedicamentoPaciente;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\TranscricaoDocumento;

/**
 * Transcrição da foto do exame ou da receita, conferida pelo profissional (US-FISIO-063, parte 3).
 *
 * - Desligada por padrão: a empresa liga em `fisio_settings` (`documentos.transcricao_ia`), como o
 *   assistente da pesquisa.
 * - Só roda com provedor da lista `fisio.documentos.transcricao.provedores_sem_treino`: provedor
 *   cuja API, por contrato, não usa o conteúdo enviado para treinar modelo. Fora da lista, recusa
 *   antes de mandar a foto.
 * - A IA só copia (ver o agente). O que ela devolve vira `pendente`; nada vai para o prontuário,
 *   para a data do documento nem para a lista de medicamentos até o profissional confirmar ou
 *   corrigir. Quem confirmou, quando e quais campos foram corrigidos ficam gravados.
 * - Nada chega ao paciente: a transcrição só aparece na ficha.
 */
final class TranscricaoDocumentos
{
    public const CHAVE_CONFIG = 'documentos.transcricao_ia';

    public const LOG = 'fisio';

    /** Tipo do documento → modo do agente. "Outro" não tem campos a transcrever. */
    private const MODOS = [
        'receita' => TranscricaoDocumentoAgent::RECEITA,
        'exame_imagem' => TranscricaoDocumentoAgent::EXAME,
        'exame_laboratorial' => TranscricaoDocumentoAgent::EXAME,
        'laudo_medico' => TranscricaoDocumentoAgent::EXAME,
    ];

    public function __construct(
        private readonly ConfiguracaoClinica $config,
        private readonly DocumentosDoPaciente $documentos,
        private readonly MedicamentosDoPaciente $medicamentos,
        private readonly VaultEncryptionService $vault,
    ) {}

    public function ligada(int $businessId): bool
    {
        return (bool) $this->config->ler($businessId, self::CHAVE_CONFIG, false);
    }

    public function ligar(int $businessId, bool $ligar, User $autor): void
    {
        $this->config->gravar($businessId, self::CHAVE_CONFIG, $ligar, (int) $autor->id);
        activity(self::LOG)->causedBy($autor)
            ->withProperties(['business_id' => $businessId, 'ligado' => $ligar])
            ->log('fisio.transcricao_ia.configurada');
    }

    public static function modo(DocumentoPaciente $doc): ?string
    {
        return self::MODOS[$doc->kind] ?? null;
    }

    /** O provedor que vai responder está na lista dos que não treinam com o conteúdo? */
    public function provedorPermitido(TranscricaoDocumentoAgent $agente): bool
    {
        $provedor = $agente->provider() ?? (string) config('ai.default');
        $lista = (array) config('fisio.documentos.transcricao.provedores_sem_treino', []);

        return in_array($provedor, $lista, true);
    }

    /**
     * Pede a sugestão à IA e grava como pendente. Recusa (InvalidArgumentException, vira 422) se
     * a empresa não ligou, se o provedor não está na lista, se o documento saiu da lista ou se o
     * tipo não tem campos a transcrever.
     */
    public function sugerir(DocumentoPaciente $doc, User $autor): TranscricaoDocumento
    {
        if (! $this->ligada((int) $doc->business_id)) {
            throw new InvalidArgumentException('A transcrição pela IA está desligada nesta clínica.');
        }
        $modo = self::modo($doc);
        if ($modo === null) {
            throw new InvalidArgumentException('Este tipo de documento não tem campos para transcrever.');
        }
        if ($doc->foiTirado() || $doc->status === DocumentoPaciente::RECUSADO) {
            throw new InvalidArgumentException('Documento fora da lista não é transcrito.');
        }
        $agente = new TranscricaoDocumentoAgent($modo);
        if (! $this->provedorPermitido($agente)) {
            throw new InvalidArgumentException('O provedor de IA configurado não está na lista dos que não usam o conteúdo para treino.');
        }
        $anexo = $this->anexo($doc);
        if ($anexo === null) {
            throw new InvalidArgumentException('O arquivo do documento não está disponível.');
        }

        $resposta = $agente->prompt($agente->montarPrompt(), [$anexo]);
        $bruto = [];
        foreach (TranscricaoDocumentoAgent::CAMPOS as $campo) {
            $bruto[$campo] = $resposta[$campo] ?? null;
        }

        return TranscricaoDocumento::withoutGlobalScopes()->create([ // SUPERADMIN: empresa do documento, explícita.
            'business_id' => (int) $doc->business_id,
            'document_id' => (int) $doc->id,
            'status' => TranscricaoDocumento::PENDENTE,
            'suggested' => self::limpar($modo, $bruto),
            'model' => $agente->model(),
            'requested_by' => (int) $autor->id,
        ]);
    }

    /**
     * Confere: grava o que o profissional confirmou ou corrigiu. Na receita, cada medicamento
     * confirmado entra na lista com a receita como origem e quem conferiu; a data confirmada vira
     * a data do documento quando ele não tinha uma.
     *
     * @param  array<string, mixed>  $campos  já validados pelo controller
     */
    public function confirmar(TranscricaoDocumento $t, array $campos, User $autor): TranscricaoDocumento
    {
        if ($t->status !== TranscricaoDocumento::PENDENTE) {
            throw new InvalidArgumentException('Esta transcrição já foi decidida.');
        }
        /** @var DocumentoPaciente $doc */
        $doc = DocumentoPaciente::withoutGlobalScopes()->where('business_id', $t->business_id)->findOrFail($t->document_id); // SUPERADMIN: empresa da transcrição, explícita.
        $modo = self::modo($doc) ?? TranscricaoDocumentoAgent::EXAME;
        $confirmado = self::limpar($modo, $campos);
        $corrigidos = self::corrigidos($t->suggested, $confirmado);

        DB::transaction(function () use ($t, $doc, $modo, $confirmado, $corrigidos, $autor) {
            $t->forceFill([
                'status' => TranscricaoDocumento::CONFERIDA,
                'confirmed' => $confirmado,
                'corrected' => $corrigidos,
                'decided_by' => (int) $autor->id,
                'decided_at' => now(),
            ])->save();

            if ($doc->document_date === null && $confirmado['data'] !== null) {
                $doc->forceFill(['document_date' => $confirmado['data']])->save();
            }

            if ($modo === TranscricaoDocumentoAgent::RECEITA) {
                /** @var Patient $paciente */
                $paciente = Patient::withoutGlobalScopes()->where('business_id', $doc->business_id)->findOrFail($doc->patient_id); // SUPERADMIN: empresa do documento.
                foreach ($confirmado['medicamentos'] as $m) {
                    $this->medicamentos->incluir($paciente, [
                        'nome' => $m['nome'], 'dose' => $m['dose'], 'frequencia' => $m['frequencia'], 'desde' => $confirmado['data'],
                    ], $autor, ['source' => MedicamentoPaciente::TRANSCRICAO, 'document_id' => (int) $doc->id, 'confirmed_by' => (int) $autor->id]);
                }
            }
        });

        activity(self::LOG)->performedOn($doc)->causedBy($autor)
            ->withProperties(['patient_id' => (int) $doc->patient_id, 'transcription_id' => (int) $t->id, 'corrigidos' => $corrigidos])
            ->log('fisio.transcricao.conferida');

        return $t;
    }

    public function descartar(TranscricaoDocumento $t, User $autor): TranscricaoDocumento
    {
        if ($t->status !== TranscricaoDocumento::PENDENTE) {
            throw new InvalidArgumentException('Esta transcrição já foi decidida.');
        }
        $t->forceFill(['status' => TranscricaoDocumento::DESCARTADA, 'decided_by' => (int) $autor->id, 'decided_at' => now()])->save();

        return $t;
    }

    /**
     * Normaliza para o formato do modo: só as chaves do schema, texto aparado, vazio vira nulo,
     * data inválida vira nula, até 20 medicamentos. Chave fora do schema não passa.
     *
     * @param  array<string, mixed>  $bruto
     * @return array<string, mixed>
     */
    public static function limpar(string $modo, array $bruto): array
    {
        $txt = function (mixed $v, int $max): ?string {
            if (! is_scalar($v)) {
                return null;
            }
            $s = trim((string) $v);

            return $s === '' ? null : mb_substr($s, 0, $max);
        };
        $data = $txt($bruto['data'] ?? null, 10);
        if ($data !== null && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1 || ! checkdate((int) substr($data, 5, 2), (int) substr($data, 8, 2), (int) substr($data, 0, 4)))) {
            $data = null;
        }

        if ($modo === TranscricaoDocumentoAgent::RECEITA) {
            $meds = [];
            foreach (array_slice(is_array($bruto['medicamentos'] ?? null) ? $bruto['medicamentos'] : [], 0, 20) as $m) {
                $nome = is_array($m) ? $txt($m['nome'] ?? null, 120) : null;
                if ($nome === null) {
                    continue;
                }
                $meds[] = ['nome' => $nome, 'dose' => $txt($m['dose'] ?? null, 60), 'frequencia' => $txt($m['frequencia'] ?? null, 120)];
            }

            return ['data' => $data, 'medico' => $txt($bruto['medico'] ?? null, 120), 'crm' => $txt($bruto['crm'] ?? null, 40), 'medicamentos' => $meds];
        }

        return ['data' => $data, 'tipo_exame' => $txt($bruto['tipo_exame'] ?? null, 120), 'conclusao' => $txt($bruto['conclusao'] ?? null, 5000)];
    }

    /**
     * Para a ficha: a transcrição mais recente de cada documento, pendente ou conferida.
     *
     * @param  list<int>  $docIds
     * @return array<int, array<string, mixed>> document_id => resumo
     */
    public function daFicha(array $docIds): array
    {
        if ($docIds === []) {
            return [];
        }
        $lista = TranscricaoDocumento::query()->whereIn('document_id', $docIds)
            ->whereIn('status', [TranscricaoDocumento::PENDENTE, TranscricaoDocumento::CONFERIDA])
            ->orderByDesc('id')->get()->unique('document_id');
        $nomes = DB::table('users')->whereIn('id', $lista->pluck('decided_by')->filter()->unique()->values()->all())
            ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name.' '.$u->last_name)]);

        return $lista->mapWithKeys(fn (TranscricaoDocumento $t) => [(int) $t->document_id => [
            'id' => $t->id,
            'status' => $t->status,
            'sugestao' => $t->suggested,
            'confirmado' => $t->confirmed,
            'corrigidos' => $t->corrected ?? [],
            'conferido_por' => $t->decided_by ? ($nomes[$t->decided_by] ?? null) : null,
            'conferido_em' => $t->decided_at?->toIso8601String(),
        ]])->all();
    }

    /**
     * @param  array<string, mixed>  $sugerido
     * @param  array<string, mixed>  $confirmado
     * @return list<string>
     */
    private static function corrigidos(array $sugerido, array $confirmado): array
    {
        $out = [];
        foreach ($confirmado as $campo => $valor) {
            if (($sugerido[$campo] ?? null) !== $valor) {
                $out[] = $campo;
            }
        }

        return $out;
    }

    private function anexo(DocumentoPaciente $doc): Image|Document|null
    {
        $arquivo = $this->documentos->arquivo($doc);
        if ($arquivo === null) {
            return null;
        }
        $disco = (string) ($arquivo->disk ?: config('arquivos.disk_default', 'local'));
        $conteudo = $arquivo->encrypted
            ? $this->vault->getDecrypted($disco, (string) $arquivo->storage_path)
            : Storage::disk($disco)->get((string) $arquivo->storage_path);
        if ($conteudo === null || $conteudo === '') {
            return null;
        }
        $mime = (string) $arquivo->mime_type;

        return $mime === 'application/pdf'
            ? Document::fromString($conteudo, $mime)
            : Image::fromBase64(base64_encode($conteudo), $mime);
    }
}
