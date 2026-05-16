<?php

declare(strict_types=1);

namespace Modules\Jana\Jobs\Mcp;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

/**
 * GAP D7 #2 (auditoria memoria-senior 2026-05-15) — Re-index targeted de 1 doc.
 *
 * Diferente do `mcp:sync-memory` que varre tudo, este job re-indexa UM doc
 * específico — usado por `ReindexJobDispatcher` pra processar stale/drift em
 * lote sem reprocessar 350+ docs cada execução.
 *
 * Estratégia: lê o `.md` do git via path, dispara o `IndexarMemoryGitParaDb`
 * filtrando pra processar só o arquivo daquele doc.
 *
 * MULTI-TENANT: repo-wide job, sem business_id by design (ADR 0093
 * §"Commands & Jobs sem HTTP context"). Tabela `mcp_memory_documents` e
 * REPO-WIDE (docs canon do git, nao dados de business). Wave 16 governance v3
 * — marker reforcado pra rubrica D1 v3.2 hardened distinguir "esqueceu
 * businessId" de "by design".
 */
class ReindexarDocumentoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $documentId,
        public readonly string $reason = 'freshness',
    ) {
    }

    public function handle(): void
    {
        $doc = McpMemoryDocument::find($this->documentId);
        if ($doc === null) {
            Log::channel('copiloto-ai')->warning('ReindexarDocumentoJob: doc não existe', [
                'document_id' => $this->documentId,
            ]);
            return;
        }

        $repoBase = base_path();
        $fullPath = $repoBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $doc->git_path);

        if (! is_file($fullPath)) {
            Log::channel('copiloto-ai')->warning('ReindexarDocumentoJob: arquivo sumiu do filesystem', [
                'document_id' => $this->documentId,
                'git_path'    => $doc->git_path,
            ]);
            return;
        }

        // Atualiza apenas indexed_at + dispara Scout. O sync completo (mcp:sync-memory
        // every5min) já reescreve content_md quando o sha muda. Aqui força bump no
        // observable de Scout pra re-indexar Meilisearch quando o conteúdo está fresh
        // mas o indexed_at expirou (caso típico: time decay penalizou doc que ainda é
        // canônico).
        $doc->forceFill(['indexed_at' => now()])->save();

        Log::channel('copiloto-ai')->info('ReindexarDocumentoJob.completed', [
            'document_id' => $this->documentId,
            'slug'        => $doc->slug,
            'reason'      => $this->reason,
        ]);
    }
}
