<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Modules\Arquivos\Services\VaultEncryptionService;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Services\MidiaExercicio;
use Symfony\Component\HttpFoundation\Response;

/**
 * Foto ou vídeo curto do exercício PRÓPRIO da clínica (UC-FBI-05..07).
 *
 * Controller à parte do `BibliotecaController` de propósito: a mídia tem ciclo próprio (anexar,
 * servir, remover) e não mistura com o cadastro do exercício.
 *
 * Mesmo desenho de escopo do `BibliotecaController` (UC-FBI-03): o exercício é procurado entre
 * "global OU da sessão" — o próprio de outra empresa responde 404 sem código extra, e o global
 * responde 403 na escrita (é visível, mas não é da clínica). `business_id` vem sempre da sessão
 * (ADR 0093); o arquivo é gravado e lido pelo backbone `Modules/Arquivos` sob `biz-{id}/`.
 *
 * A mídia nunca sai por URL pública de disco: `show` confere a empresa da sessão e só então
 * entrega o arquivo. Vídeo local é servido com suporte a faixa de bytes (o Safari exige).
 */
class BibliotecaMidiaController extends Controller
{
    public function __construct(private MidiaExercicio $midia) {}

    public function store(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->exigirEdicao($request);
        $exercicio = $this->exercicioVisivel($request, $id);
        abort_if($exercicio->ehGlobal(), 403, 'Exercício da biblioteca global não aceita mídia da clínica.');

        $request->validate(MidiaExercicio::regras(), MidiaExercicio::mensagens());

        $arquivo = $this->midia->anexar($exercicio, $request->file('midia'));
        $resumo = MidiaExercicio::resumo($exercicio->fresh() ?? $exercicio);

        if ($request->expectsJson()) {
            return response()->json(['id' => $arquivo->id, 'midia' => $resumo], 201);
        }

        return back()->with('status', 'Mídia do exercício salva.');
    }

    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $this->exigirEdicao($request);
        $exercicio = $this->exercicioVisivel($request, $id);
        abort_if($exercicio->ehGlobal(), 403, 'Exercício da biblioteca global não tem mídia da clínica.');

        $removidas = $this->midia->remover($exercicio);

        if ($request->expectsJson()) {
            return response()->json(['removidas' => $removidas]);
        }

        return back()->with('status', $removidas > 0 ? 'Mídia removida.' : 'O exercício não tinha mídia.');
    }

    public function show(Request $request, int $id, VaultEncryptionService $vault): Response
    {
        $exercicio = $this->exercicioVisivel($request, $id);
        abort_if($exercicio->ehGlobal(), 404);

        $arquivo = $this->midia->vigente($exercicio);
        abort_if($arquivo === null, 404);

        $cabecalhos = [
            'Content-Type' => MidiaExercicio::contentType((string) $arquivo->mime_type),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];
        $nome = 'exercicio-'.$exercicio->id.'.'.(pathinfo((string) $arquivo->storage_path, PATHINFO_EXTENSION) ?: 'bin');
        $discoNome = (string) ($arquivo->disk ?: config('arquivos.disk_default', 'local'));
        $disco = Storage::disk($discoNome);
        abort_unless($disco->exists((string) $arquivo->storage_path), 404);

        if ($arquivo->encrypted) {
            $conteudo = $vault->getDecrypted($discoNome, (string) $arquivo->storage_path);
            abort_if($conteudo === null, 404);

            return response($conteudo, 200, $cabecalhos + ['Content-Disposition' => 'inline; filename="'.$nome.'"']);
        }

        if ($disco instanceof FilesystemAdapter && $disco->getAdapter() instanceof LocalFilesystemAdapter) {
            // BinaryFileResponse responde a `Range` sozinho — o player de vídeo do Safari exige.
            return response()->file($disco->path((string) $arquivo->storage_path), $cabecalhos);
        }

        return $disco->response((string) $arquivo->storage_path, $nome, $cabecalhos, 'inline');
    }

    /** Global ou da sessão; o próprio de outra empresa → 404 (UC-FBI-03). */
    private function exercicioVisivel(Request $request, int $id): Exercise
    {
        $biz = (int) $request->session()->get('user.business_id');

        /** @var Exercise $exercicio */
        $exercicio = Exercise::query()
            ->where(fn (Builder $w) => $w->whereNull('business_id')->orWhere('business_id', $biz))
            ->findOrFail($id);

        return $exercicio;
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
    }
}
