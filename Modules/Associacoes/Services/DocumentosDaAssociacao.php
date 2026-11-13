<?php

declare(strict_types=1);

namespace Modules\Associacoes\Services;

use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\DocumentoAssociacao;

/**
 * Documentos da associação (US-ASSOC-002 · ADR 0451 D3/D7): estatuto, ata da diretoria,
 * certidão e outros.
 *
 * O arquivo vai para o `Modules/Arquivos` (ADR 0123) ligado à linha do documento, no idioma do
 * Fisio (`DocumentosDoPaciente`). A empresa vem da ASSOCIAÇÃO (dona conferida antes), nunca do
 * corpo do request. Documento não se apaga: `tirar()` grava quando, quem e o motivo.
 *
 * Ata da diretoria enviada fecha a pendência de diretoria (D7) via {@see PendenciaDiretoria}.
 */
final class DocumentosDaAssociacao
{
    public const MIMES = [
        'image/jpeg' => 'foto',
        'image/png' => 'foto',
        'image/webp' => 'foto',
        'application/pdf' => 'pdf',
    ];

    public const MAX_KB = 10240;

    public function __construct(private ArquivosService $arquivos, private PendenciaDiretoria $pendencia) {}

    /** @return array<string, mixed> */
    public static function regras(): array
    {
        return [
            'arquivo' => ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:'.implode(',', array_keys(self::MIMES)), 'max:'.self::MAX_KB],
            'tipo' => ['required', 'string', Rule::in(DocumentoAssociacao::TIPOS)],
            'descricao' => ['nullable', 'string', 'max:191'],
            'validade' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        return [
            'arquivo.required' => 'Escolha um PDF ou uma foto do documento.',
            'arquivo.mimes' => 'Use PDF, ou foto JPG, PNG ou WebP.',
            'arquivo.mimetypes' => 'Use PDF, ou foto JPG, PNG ou WebP.',
            'arquivo.max' => 'O arquivo passa de 10 MB.',
            'tipo.required' => 'Escolha o tipo do documento.',
            'tipo.in' => 'Escolha o tipo do documento.',
        ];
    }

    /** @param array{tipo: string, descricao?: ?string, validade?: ?string} $dados */
    public function anexar(Associacao $associacao, array $dados, UploadedFile $arquivo, int $autor): DocumentoAssociacao
    {
        return DB::transaction(function () use ($associacao, $dados, $arquivo, $autor) {
            $doc = DocumentoAssociacao::query()->create([
                'business_id' => (int) $associacao->business_id,
                'associacao_id' => (int) $associacao->id,
                'tipo' => $dados['tipo'],
                'descricao' => isset($dados['descricao']) && trim((string) $dados['descricao']) !== '' ? trim((string) $dados['descricao']) : null,
                'validade' => $dados['validade'] ?? null,
                'enviado_em' => now(),
                'enviado_por' => $autor,
            ]);
            $salvo = $this->arquivos->attachNoNegocio($doc, $arquivo, (int) $associacao->business_id);
            $doc->forceFill(['arquivo_id' => (int) $salvo->id])->save();

            if ($doc->tipo === 'ata_diretoria') {
                $this->pendencia->ataRecebida($associacao, Carbon::today());
            }

            return $doc;
        });
    }

    /** Tira da lista sem apagar. Já tirado: não muda quem nem quando (devolve false). */
    public function tirar(DocumentoAssociacao $doc, User $autor, string $motivo): bool
    {
        if ($doc->removido_em !== null) {
            return false;
        }
        $doc->forceFill(['removido_em' => now(), 'removido_por' => (int) $autor->id, 'removido_motivo' => $motivo])->save();

        return true;
    }

    /** O arquivo do documento, filtrado pela empresa do documento além do escopo do Arquivo. */
    public function arquivo(DocumentoAssociacao $doc): ?Arquivo
    {
        if ($doc->arquivo_id === null) {
            return null;
        }
        /** @var ?Arquivo $a */
        $a = Arquivo::withoutGlobalScope('business_id') // SUPERADMIN: filtro de empresa explícito abaixo.
            ->whereKey((int) $doc->arquivo_id)
            ->where('business_id', (int) $doc->business_id)
            ->where('arquivable_type', DocumentoAssociacao::class)
            ->where('arquivable_id', (int) $doc->id)
            ->first();

        return ($a !== null && isset(self::MIMES[(string) $a->mime_type]) && $a->temConteudo()) ? $a : null;
    }

    /**
     * O que a ficha recebe: documentos na lista, sem caminho de disco nem nome de arquivo.
     *
     * @return list<array<string, mixed>>
     */
    public function daFicha(Associacao $associacao): array
    {
        return DocumentoAssociacao::query()
            ->where('associacao_id', (int) $associacao->id)
            ->whereNull('removido_em')
            ->orderByDesc('enviado_em')->orderByDesc('id')
            ->get()
            ->map(fn (DocumentoAssociacao $d) => [
                'id' => (int) $d->id,
                'tipo' => $d->tipo,
                'descricao' => $d->descricao,
                'validade' => $d->validade?->toDateString(),
                'vencido' => $d->validade !== null && $d->validade->lt(Carbon::today()),
                'enviado_em' => $d->enviado_em->toDateString(),
                'url' => "/associacoes/documentos/{$d->id}/arquivo",
            ])->values()->all();
    }
}
