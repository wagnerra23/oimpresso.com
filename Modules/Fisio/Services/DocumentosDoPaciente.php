<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Fisio\Entities\DocumentoPaciente;
use Modules\Fisio\Entities\Patient;

/**
 * Exames e receitas do paciente (US-FISIO-063, parte 1).
 *
 * O arquivo vai para o `Modules/Arquivos` (ADR 0123), como a mídia do exercício
 * (`MidiaExercicio`): grava sob `biz-{id}/`, deduplica dentro da empresa e passa pelo Curador,
 * que manda dado sensível para o cofre cifrado. O vínculo é com a linha de
 * `fisio_patient_documents`, nunca com o paciente direto, e a empresa vem do PACIENTE (dono
 * conferido antes), não do corpo do request.
 *
 * Documento do prontuário não se apaga: `tirar()` grava quando, quem e o motivo. O arquivo NÃO
 * passa pelo `softDelete` do Arquivos — a retenção do backbone purga o que foi excluído, e
 * prontuário se guarda por 20 anos (Lei 13.787/2018).
 *
 * A tela nunca recebe caminho de disco nem nome original do arquivo: só a rota que serve
 * conferindo a empresa (`fisio.documentos.arquivo`).
 */
final class DocumentosDoPaciente
{
    /** Tipos de arquivo aceitos → o que a tela mostra. Lista fechada; quem decide é o servidor. */
    public const MIMES = [
        'image/jpeg' => 'foto',
        'image/png' => 'foto',
        'image/webp' => 'foto',
        'application/pdf' => 'pdf',
    ];

    public const LOG = 'fisio';

    public function __construct(private ArquivosService $arquivos) {}

    public static function maxKb(): int
    {
        return (int) config('fisio.documentos.max_kb', 10240);
    }

    /** @return array<string, mixed> */
    public static function regras(): array
    {
        return [
            'arquivo' => ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:'.implode(',', array_keys(self::MIMES)), 'max:'.self::maxKb()],
            'tipo' => ['required', 'string', Rule::in(array_keys(DocumentoPaciente::TIPOS))],
            'data_documento' => ['nullable', 'date', 'before_or_equal:today'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        $mb = (string) (int) round(self::maxKb() / 1024);

        return [
            'arquivo.required' => 'Escolha uma foto ou um PDF.',
            'arquivo.file' => 'O envio falhou. Tente de novo.',
            'arquivo.uploaded' => 'O envio falhou. O arquivo pode ser grande demais.',
            'arquivo.mimes' => 'Use foto JPG, PNG ou WebP, ou PDF.',
            'arquivo.mimetypes' => 'Use foto JPG, PNG ou WebP, ou PDF.',
            'arquivo.max' => "O arquivo passa de {$mb} MB.",
            'tipo.required' => 'Escolha o tipo do documento.',
            'tipo.in' => 'Escolha o tipo do documento.',
            'data_documento.before_or_equal' => 'A data do documento não pode ser depois de hoje.',
        ];
    }

    /**
     * Grava o documento e o arquivo. A linha nasce antes (o vínculo precisa do id); se o arquivo
     * falhar, a transação desfaz a linha e nada fica pela metade.
     *
     * @param  array{tipo: string, data_documento?: ?string, observacao?: ?string}  $dados
     */
    public function anexar(Patient $paciente, array $dados, UploadedFile $arquivo, ?int $autor, string $origem = DocumentoPaciente::ORIGEM_CLINICA): DocumentoPaciente
    {
        return DB::transaction(function () use ($paciente, $dados, $arquivo, $autor, $origem) {
            $doc = DocumentoPaciente::withoutGlobalScopes() // SUPERADMIN: grava na empresa do PRÓPRIO paciente (o portal não tem sessão).
                ->create([
                    'business_id' => (int) $paciente->business_id,
                    'patient_id' => (int) $paciente->id,
                    'kind' => $dados['tipo'],
                    'document_date' => $dados['data_documento'] ?? null,
                    'note' => isset($dados['observacao']) && trim((string) $dados['observacao']) !== '' ? trim((string) $dados['observacao']) : null,
                    'origin' => $origem,
                    'status' => $origem === DocumentoPaciente::ORIGEM_PORTAL ? DocumentoPaciente::PENDENTE : DocumentoPaciente::NO_PRONTUARIO,
                    'created_by' => $autor,
                ]);
            $this->arquivos->attachNoNegocio($doc, $arquivo, (int) $paciente->business_id);

            return $doc;
        });
    }

    /** Tira da lista sem apagar. Já tirado: não muda quem nem quando (devolve false). */
    public function tirar(DocumentoPaciente $doc, User $autor, string $motivo): bool
    {
        if ($doc->foiTirado()) {
            return false;
        }
        $doc->forceFill(['removed_at' => now(), 'removed_by' => (int) $autor->id, 'removed_reason' => $motivo])->save();

        activity(self::LOG)
            ->performedOn($doc)
            ->causedBy($autor)
            ->withProperties(['patient_id' => (int) $doc->patient_id, 'document_id' => (int) $doc->id])
            ->log('fisio.documento.tirado_da_lista');

        return true;
    }

    /**
     * O arquivo do documento, para servir. Filtra pela empresa do documento além do escopo do
     * `Arquivo` (que lê a sessão) e pelo tipo da lista fechada.
     */
    public function arquivo(DocumentoPaciente $doc): ?Arquivo
    {
        /** @var ?Arquivo $a */
        $a = Arquivo::withoutGlobalScope('business_id') // SUPERADMIN: o filtro de empresa é explícito abaixo (o portal não tem sessão).
            ->where('arquivable_type', DocumentoPaciente::class)
            ->where('arquivable_id', $doc->id)
            ->where('business_id', (int) $doc->business_id)
            ->orderByDesc('id')
            ->first();
        if ($a === null || ! isset(self::MIMES[(string) $a->mime_type]) || ! $a->temConteudo()) {
            return null;
        }

        return $a;
    }

    /**
     * O que a ficha recebe: documentos do prontuário que não saíram da lista, e os pendentes do
     * portal. Sem nome de arquivo nem caminho de disco.
     *
     * @return list<array<string, mixed>>
     */
    public function daFicha(Patient $paciente): array
    {
        $docs = DocumentoPaciente::query()
            ->where('business_id', (int) $paciente->business_id)
            ->where('patient_id', (int) $paciente->id)
            ->whereNull('removed_at')
            ->whereIn('status', [DocumentoPaciente::NO_PRONTUARIO, DocumentoPaciente::PENDENTE])
            ->with('arquivos:id,arquivable_type,arquivable_id,mime_type')
            ->orderByRaw('document_date IS NULL, document_date DESC')
            ->orderByDesc('id')
            ->get();
        $nomes = $this->nomesDeUsuario($docs->pluck('created_by')->merge($docs->pluck('reviewed_by'))->filter()->unique()->values()->all());
        // Transcrição sugerida/conferida (parte 3). Resolvida aqui, não no construtor: o serviço de
        // transcrição depende deste.
        $transcricoes = app(TranscricaoDocumentos::class)->daFicha($docs->pluck('id')->map(fn ($i) => (int) $i)->values()->all());

        return $docs->map(function (DocumentoPaciente $d) use ($nomes, $transcricoes) {
            $a = $d->arquivos->sortByDesc('id')->first();

            return [
                'id' => $d->id,
                'tipo' => $d->kind,
                'data_documento' => $d->document_date?->toDateString(),
                'observacao' => $d->note,
                'origem' => $d->origin,
                'status' => $d->status,
                'formato' => $a instanceof Arquivo ? (self::MIMES[(string) $a->mime_type] ?? null) : null,
                'url' => "/fisio/documentos/{$d->id}/arquivo",
                'anexado_por' => $d->created_by ? ($nomes[$d->created_by] ?? null) : null,
                'anexado_em' => $d->created_at?->toIso8601String(),
                'transcrevivel' => TranscricaoDocumentos::modo($d) !== null,
                'transcricao' => $transcricoes[(int) $d->id] ?? null,
            ];
        })->values()->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nomesDeUsuario(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('users')->whereIn('id', $ids)->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name.' '.$u->last_name)])->all();
    }
}
