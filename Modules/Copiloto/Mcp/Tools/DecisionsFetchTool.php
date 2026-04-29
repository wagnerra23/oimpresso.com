<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.c (ADR 0053) — Tool decisions-fetch.
 *
 * Carrega 1 ADR completo por slug. Respeita permissões: ADRs com
 * scope_required (ex: ADR 0030 credenciais) só são retornados pra users
 * com a Spatie permission correspondente.
 */
class DecisionsFetchTool extends Tool
{
    protected string $name = 'decisions-fetch';

    protected string $title = 'Carregar 1 ADR completo';

    protected string $description = 'Retorna o conteúdo Markdown completo de uma ADR específica. Use slug do decisions-search (ex: "0046-chat-agent-gap-contexto-rico").';

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->required()
                ->description('Slug da ADR (ex: "0046-chat-agent-gap-contexto-rico" — sem extensão .md)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $slug = (string) $request->get('slug', '');

        if (trim($slug) === '') {
            return Response::error('Parâmetro "slug" obrigatório.');
        }

        $user = $request->user();

        $doc = McpMemoryDocument::where('slug', $slug)
            ->acessiveisPara($user)
            ->first();

        if ($doc === null) {
            // Diferenciação: existe mas sem permissão vs não existe
            $existeSemPermissao = McpMemoryDocument::where('slug', $slug)->exists();

            if ($existeSemPermissao) {
                return Response::error(
                    "ADR \"$slug\" existe mas você não tem permissão pra ler. Contate o admin se for necessário."
                );
            }

            return Response::error(
                "ADR \"$slug\" não encontrada. Use `decisions-search` pra buscar pelo nome certo."
            );
        }

        $body = sprintf(
            "# %s\n\n_Slug: `%s` · Tipo: %s · Módulo: %s · Indexed: %s_\n\n---\n\n%s",
            $doc->title,
            $doc->slug,
            $doc->type,
            $doc->module ?? 'core',
            $doc->indexed_at?->toIso8601String() ?? 'desconhecido',
            $doc->content_md
        );

        if ($doc->pii_redactions_count > 0) {
            $body .= "\n\n_⚠️ {$doc->pii_redactions_count} PII redaction(s) aplicada(s) automaticamente._";
        }

        return Response::text($body);
    }
}
