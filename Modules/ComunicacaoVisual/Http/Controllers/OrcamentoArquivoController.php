<?php

namespace Modules\ComunicacaoVisual\Http\Controllers;

use App\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\Os;
use Modules\ComunicacaoVisual\Services\OrcamentoViraOs;

/**
 * Arquivos do orçamento (arte, foto do local, layout) — UC-FLX-01 · US-COMVIS-020.
 *
 * Grava em `media` do core (o mesmo arquivo em `public/uploads/media`), com `model_type` =
 * Orcamento. Ao aprovar, `OrcamentoViraOs` copia esses registros para a OS: por isso só se anexa
 * ou remove ANTES da OS existir — depois disso o arquivo da OS já é outra linha.
 *
 *   POST   /comunicacao-visual/api/orcamentos/{id}/arquivos          → anexa (multipart `arquivos[]`)
 *   DELETE /comunicacao-visual/api/orcamentos/{id}/arquivos/{media}  → remove
 *
 * Tier 0 (ADR 0093): `media` não tem global scope; toda leitura filtra `business_id` explícito.
 * Orçamento de outra empresa → 404.
 */
class OrcamentoArquivoController extends Controller
{
    /** Formatos de arte e de foto que a gráfica recebe. Extensão, não MIME: `.cdr`/`.ai` não têm MIME confiável. */
    public const EXTENSOES = ['pdf', 'jpg', 'jpeg', 'png', 'svg', 'ai', 'eps', 'cdr', 'tif', 'tiff', 'psd'];

    public function store(Request $request, int $id): JsonResponse
    {
        $this->exigirCriar();
        $businessId = $this->businessId();
        $orcamento = $this->orcamentoAberto($businessId, $id);
        if ($orcamento instanceof JsonResponse) {
            return $orcamento;
        }

        // `media` aceita até constants.document_size_limit (bytes); a regra `max` é em KB.
        $maxKb = (int) floor(((int) config('constants.document_size_limit', 5000000)) / 1024);
        $request->validate([
            'arquivos'   => ['required', 'array', 'min:1', 'max:10'],
            'arquivos.*' => ['file', 'max:' . $maxKb, 'extensions:' . implode(',', self::EXTENSOES)],
        ], [
            'arquivos.required'    => 'Escolha ao menos um arquivo.',
            'arquivos.max'         => 'Envie no máximo 10 arquivos de cada vez.',
            'arquivos.*.max'       => 'Cada arquivo pode ter no máximo ' . round($maxKb / 1024, 1) . ' MB.',
            'arquivos.*.extensions' => 'Formato não aceito. Use: ' . implode(', ', self::EXTENSOES) . '.',
        ]);

        foreach ($request->file('arquivos') as $arquivo) {
            $nome = Media::uploadFile($arquivo);
            if ($nome === null) {
                return response()->json(['message' => 'Não consegui gravar ' . $arquivo->getClientOriginalName() . '.'], 422);
            }
            Media::create([
                'business_id'  => $businessId,
                'file_name'    => $nome,
                'uploaded_by'  => auth()->id(),
                'model_type'   => Orcamento::class,
                'model_id'     => $orcamento->id,
            ]);
        }

        return response()->json(self::doOrcamento($businessId, $orcamento->id), 201);
    }

    public function destroy(int $id, int $media): JsonResponse
    {
        $this->exigirCriar();
        $businessId = $this->businessId();
        $orcamento = $this->orcamentoAberto($businessId, $id);
        if ($orcamento instanceof JsonResponse) {
            return $orcamento;
        }

        $registro = Media::query()->where('business_id', $businessId)
            ->where('model_type', Orcamento::class)->where('model_id', $orcamento->id)
            ->findOrFail($media);
        Media::deleteMedia($businessId, $registro->id);

        return response()->json(self::doOrcamento($businessId, $orcamento->id), 200);
    }

    /** Arquivos do orçamento no formato da tela (OrcamentoController::paraTela usa o mesmo). */
    public static function doOrcamento(int $businessId, int $orcamentoId): array
    {
        return Media::query()->where('business_id', $businessId)
            ->where('model_type', Orcamento::class)->where('model_id', $orcamentoId)
            ->orderBy('id')->get()
            ->map(fn (Media $m) => ['id' => $m->id, 'nome' => $m->display_name, 'url' => $m->display_url])
            ->values()->all();
    }

    /** O orçamento da empresa, se ainda aceita arquivo (sem OS); senão a resposta 422. */
    private function orcamentoAberto(int $businessId, int $id): Orcamento|JsonResponse
    {
        $orcamento = Orcamento::query()->where('comvis_orcamentos.business_id', $businessId)->findOrFail($id);
        $temOs = Os::query()->where('business_id', $businessId)->where('orcamento_id', $orcamento->id)->exists();
        if ($temOs || ! in_array($orcamento->status, OrcamentoViraOs::APROVAVEIS, true)) {
            return response()->json(['message' => $temOs
                ? "O {$orcamento->numero} já virou OS: os arquivos dela ficam na OS."
                : "O {$orcamento->numero} está {$orcamento->status} e não recebe arquivo."], 422);
        }

        return $orcamento;
    }

    private function businessId(): int
    {
        return (int) (session('user.business_id') ?? session('business.id'));
    }

    private function exigirCriar(): void
    {
        $user = auth()->user();
        if (! $user->can('superadmin') && ! $user->can('comvis.orcamento.create')) {
            abort(403, 'Sem permissão para anexar arquivo ao orçamento (comvis.orcamento.create).');
        }
    }
}
