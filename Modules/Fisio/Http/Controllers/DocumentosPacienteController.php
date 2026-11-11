<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Arquivos\Services\VaultEncryptionService;
use Modules\Fisio\Entities\DocumentoPaciente;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\DocumentosDoPaciente;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exames e receitas na ficha do paciente (US-FISIO-063, parte 1).
 *
 * Anexar e tirar da lista exigem `fisio.editar`; abrir o arquivo exige `fisio.access` (a ficha
 * inteira já exige). Paciente e documento de outra empresa: 404 pelo escopo e por
 * `where business_id`, antes de validar qualquer coisa (ADR 0093).
 *
 * O arquivo sai só por esta rota, com a empresa conferida, `nosniff` e `private`. Abrir é leitura
 * de prontuário: cada abertura vai para o `activity_log` só com ids.
 */
class DocumentosPacienteController extends Controller
{
    public function __construct(private DocumentosDoPaciente $documentos) {}

    public function store(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $paciente = Patient::query()->where('business_id', $this->empresa($request))->findOrFail($id);
        $this->exigirEdicao($request);
        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['arquivo' => 'Paciente bloqueado: não recebe documento novo.']);
        }

        $dados = $request->validate(DocumentosDoPaciente::regras(), DocumentosDoPaciente::mensagens());
        $doc = $this->documentos->anexar($paciente, $dados, $request->file('arquivo'), (int) $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json(['id' => $doc->id], 201);
        }

        return back()->with('success', 'Documento anexado ao prontuário.');
    }

    /** Tira da lista. O documento e o arquivo ficam no prontuário; o motivo é obrigatório. */
    public function tirar(Request $request, int $documento): JsonResponse|RedirectResponse
    {
        $doc = $this->documento($request, $documento);
        $this->exigirEdicao($request);
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:255']], [
            'motivo.required' => 'Diga por que o documento sai da lista.',
            'motivo.min' => 'Diga por que o documento sai da lista.',
        ]);

        $tirou = $this->documentos->tirar($doc, $request->user(), trim($dados['motivo']));

        if ($request->expectsJson()) {
            return response()->json(['tirado' => $tirou]);
        }

        return back()->with('success', $tirou ? 'Documento tirado da lista. Ele continua no prontuário.' : 'O documento já estava fora da lista.');
    }

    public function arquivo(Request $request, int $documento, VaultEncryptionService $vault): Response
    {
        $doc = $this->documento($request, $documento);
        $arquivo = $this->documentos->arquivo($doc);
        abort_if($arquivo === null, 404);

        $discoNome = (string) ($arquivo->disk ?: config('arquivos.disk_default', 'local'));
        $disco = Storage::disk($discoNome);
        abort_unless($disco->exists((string) $arquivo->storage_path), 404);

        activity(DocumentosDoPaciente::LOG)
            ->performedOn($doc)
            ->causedBy($request->user())
            ->withProperties(['patient_id' => (int) $doc->patient_id, 'document_id' => (int) $doc->id])
            ->log('fisio.documento.aberto');

        $ext = DocumentosDoPaciente::MIMES[(string) $arquivo->mime_type] === 'pdf' ? 'pdf' : (pathinfo((string) $arquivo->storage_path, PATHINFO_EXTENSION) ?: 'bin');
        $cabecalhos = [
            'Content-Type' => (string) $arquivo->mime_type,
            'Content-Disposition' => 'inline; filename="documento-'.$doc->id.'.'.$ext.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        $conteudo = $arquivo->encrypted
            ? $vault->getDecrypted($discoNome, (string) $arquivo->storage_path)
            : $disco->get((string) $arquivo->storage_path);
        abort_if($conteudo === null, 404);

        return response($conteudo, 200, $cabecalhos);
    }

    /** Documento da empresa da sessão; de outra empresa → 404. */
    private function documento(Request $request, int $id): DocumentoPaciente
    {
        /** @var DocumentoPaciente $doc */
        $doc = DocumentoPaciente::query()->where('business_id', $this->empresa($request))->findOrFail($id);

        return $doc;
    }

    private function empresa(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
    }
}
