<?php

declare(strict_types=1);

namespace Modules\Jana\Support;

/**
 * Vocabulário único de DEGRADAÇÃO de retrieval nas tools MCP de busca.
 *
 * Por que existe (2026-07-28): três superfícies caem no FULLTEXT quando o caminho
 * semântico não responde — `kb-answer` (via KbAnswerService), `decisions-search` e
 * `memoria-search`. O fallback é deliberado (disponibilidade acima de tudo), mas as
 * três respondiam a MESMA frase de "não achei" tanto pra índice fora do ar quanto
 * pra ausência real de conteúdo. Quem lê a resposta não tinha como distinguir.
 *
 * O `kb-answer` foi corrigido primeiro (PR #4979) e a frase nasceu como constante
 * dentro do `KbAnswerService`. Ao fechar as outras duas portas, manter a redação lá
 * faria o service de Q&A virar dono do texto que o `decisions-search` e o
 * `memoria-search` também emitem — acoplamento errado. A frase mora aqui; cada
 * superfície mantém seus próprios nomes de estado (os caminhos são distintos:
 * Meilisearch/`mcp_memory_documents` num, MeilisearchDriver/`jana_memoria_facts` no
 * outro), mas o texto que chega ao humano é UM só.
 *
 * NÃO é um enum de status: cada tool nomeia seus estados no seu próprio vocabulário.
 * O que se compartilha é o predicado "isto foi degradação?" e a frase que o declara.
 *
 * @see Modules/Jana/Services/Kb/KbAnswerService.php
 * @see Modules/Jana/Mcp/Tools/DecisionsSearchTool.php
 * @see Modules/Jana/Mcp/Tools/MemoriaSearchTool.php
 */
final class RetrievalStatus
{
    /**
     * Frase única de degradação. Redação deliberada em dois tempos: diz o que houve
     * (infra) e o que NÃO se pode concluir (ausência) — porque o erro que isto
     * corrige era exatamente o leitor concluir ausência a partir de silêncio.
     */
    public const AVISO = '⚠️ Busca semântica indisponível no momento — esta resposta usou apenas busca textual, que tem recall menor. Ausência de fonte aqui NÃO prova ausência na KB: repita em alguns minutos.';

    /**
     * Sufixo a anexar na resposta — string vazia quando o retrieval foi pleno.
     *
     * Puro de propósito: a regra "degradação tem que ser VISÍVEL" fica testável sem
     * DB, sem Meilisearch e sem LLM. Verificar por grep do texto no fonte seria
     * presence-gate — mede a FORMA, não o COMPORTAMENTO (classe LC-11, banida em
     * proibicoes.md §5 2026-07-27).
     */
    public static function aviso(bool $degradado): string
    {
        return $degradado ? "\n\n".self::AVISO : '';
    }
}
