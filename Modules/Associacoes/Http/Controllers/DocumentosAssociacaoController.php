<?php

declare(strict_types=1);

namespace Modules\Associacoes\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Services\VaultEncryptionService;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\DocumentoAssociacao;
use Modules\Associacoes\Services\AssociacoesAcesso;
use Modules\Associacoes\Services\DocumentosDaAssociacao;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documentos da associação (US-ASSOC-002 · ADR 0451 D3): anexar, tirar da lista com motivo e
 * abrir o arquivo. Nada se apaga.
 *
 * Associação e documento fora do alcance do usuário (outra empresa ou, para o representante,
 * outra associação) → 404 antes de validar. Anexar e tirar exigem `associacoes.editar`.
 * O arquivo sai só por esta rota, com `nosniff` e `private`.
 */
class DocumentosAssociacaoController extends Controller
{
    public function __construct(private AssociacoesAcesso $acesso, private DocumentosDaAssociacao $documentos) {}

    public function store(Request $request, int $id): RedirectResponse
    {
        /** @var Associacao $associacao */
        $associacao = $this->acesso->restringir(Associacao::query(), $request->user(), 'id')
            ->where('business_id', $this->empresa($request))->findOrFail($id);
        $this->exigirEdicao($request);
        $dados = $request->validate(DocumentosDaAssociacao::regras(), DocumentosDaAssociacao::mensagens());

        $this->documentos->anexar($associacao, $dados, $request->file('arquivo'), (int) $request->user()->id);

        return redirect()->to('/associacoes/lista?ver='.$associacao->id)->with('success', $dados['tipo'] === 'ata_diretoria'
            ? 'Ata da diretoria recebida. A pendência de diretoria foi fechada.'
            : 'Documento anexado.');
    }

    /** Tira da lista com motivo. O documento e o arquivo ficam guardados. */
    public function tirar(Request $request, int $documento): RedirectResponse
    {
        $doc = $this->documento($request, $documento);
        $this->exigirEdicao($request);
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:255']], [
            'motivo.required' => 'Diga por que o documento sai da lista.',
            'motivo.min' => 'Diga por que o documento sai da lista.',
        ]);
        $tirou = $this->documentos->tirar($doc, $request->user(), trim($dados['motivo']));

        return redirect()->to('/associacoes/lista?ver='.$doc->associacao_id)->with('success', $tirou ? 'Documento tirado da lista. Ele continua guardado.' : 'O documento já estava fora da lista.');
    }

    public function arquivo(Request $request, int $documento, VaultEncryptionService $vault): Response
    {
        $doc = $this->documento($request, $documento);
        $arquivo = $this->documentos->arquivo($doc);
        abort_if($arquivo === null, 404);

        $discoNome = (string) ($arquivo->disk ?: config('arquivos.disk_default', 'local'));
        $disco = Storage::disk($discoNome);
        abort_unless($disco->exists((string) $arquivo->storage_path), 404);

        $conteudo = $arquivo->encrypted
            ? $vault->getDecrypted($discoNome, (string) $arquivo->storage_path)
            : $disco->get((string) $arquivo->storage_path);
        abort_if($conteudo === null, 404);
        $ext = DocumentosDaAssociacao::MIMES[(string) $arquivo->mime_type] === 'pdf' ? 'pdf' : (pathinfo((string) $arquivo->storage_path, PATHINFO_EXTENSION) ?: 'bin');

        return response($conteudo, 200, [
            'Content-Type' => (string) $arquivo->mime_type,
            'Content-Disposition' => 'inline; filename="documento-'.$doc->id.'.'.$ext.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Documento no alcance do usuário (empresa + associação, D4); fora → 404. */
    private function documento(Request $request, int $id): DocumentoAssociacao
    {
        /** @var DocumentoAssociacao $doc */
        $doc = $this->acesso->restringir(DocumentoAssociacao::query(), $request->user(), 'associacao_id')
            ->where('business_id', $this->empresa($request))->findOrFail($id);

        return $doc;
    }

    private function empresa(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function exigirEdicao(Request $request): void
    {
        $u = $request->user();
        abort_unless((bool) ($u?->can(AssociacoesAcesso::PERMISSAO_EDITAR) || $u?->can('superadmin')), 403);
    }
}
