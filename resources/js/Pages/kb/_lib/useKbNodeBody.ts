import * as React from 'react';

/**
 * useKbNodeBody — carrega o CORPO do documento aberto no leitor da V2.
 *
 * ⚠️ Por que fetch e não prop: o corpo NÃO vive em `kb_nodes`. O bridge canônico
 * (`KbBridgeFromMcpJob`) copia metadata e mantém `body_blocks = NULL` — invariante
 * Tier 0 declarada 3× no job e validada pelo `KbNodeObserver` (ADR 0061: doc do git
 * não vira editável no app, senão vira segunda fonte da verdade). O corpo vem por
 * **JOIN** com `mcp_memory_documents.content_md`, servido pelo endpoint que JÁ existe:
 *
 *   GET /kb/nodes/{slug}  →  KbNodeController@show  →  { node, content_md, github_url }
 *
 * Contrato: `NodeReader.charter.md` Goal 2 ("detalhe + body com JOIN mcp_memory_documents
 * se bridge; incrementa reads_count atomic"). NÃO criamos rota nova — endpoint duplicado
 * seria 2º oráculo do mesmo dado (proibicoes.md §5, "duplica régua consolidada").
 *
 * Tier 0 (ADR 0093): o scope de `KbNode` resolve o business da sessão → nó de outro
 * tenant devolve 404, nunca conteúdo.
 *
 * Efeito colateral consciente: o endpoint incrementa `reads_count` (charter Goal 2).
 * Por isso o debounce de 250ms — navegar com j/k não deve inflar a métrica de leitura
 * com nós que só passaram de raspão. O AbortController cancela o voo anterior.
 */

export type KbNodeBodyStatus = 'idle' | 'loading' | 'ok' | 'error';

export interface KbNodeBody {
  /** markdown canônico do doc (JOIN mcp_memory_documents). null = doc sem corpo. */
  content_md: string | null;
  /** permalink do arquivo no git (fonte da verdade). */
  github_url: string | null;
}

export interface KbNodeBodyState {
  status: KbNodeBodyStatus;
  body: KbNodeBody | null;
  /** mensagem pt-BR pra UI — nunca `null` quando status === 'error'. */
  error: string | null;
}

const IDLE: KbNodeBodyState = { status: 'idle', body: null, error: null };

/** Exportado só pro teste client-side conseguir montar a URL esperada. */
export function kbNodeBodyUrl(slug: string): string {
  return `/kb/nodes/${encodeURIComponent(slug)}`;
}

/**
 * @param slug     slug do nó aberto (`null` = leitor fechado → não busca nada)
 * @param enabled  só busca quando o nó precisa de JOIN (bridge). Artigo editável
 *                 já traz `body_blocks` no payload da lista.
 */
export function useKbNodeBody(slug: string | null, enabled: boolean): KbNodeBodyState {
  const [state, setState] = React.useState<KbNodeBodyState>(IDLE);

  React.useEffect(() => {
    if (!slug || !enabled) {
      setState(IDLE);
      return;
    }

    const controller = new AbortController();
    let alive = true;

    setState({ status: 'loading', body: null, error: null });

    const timer = setTimeout(() => {
      fetch(kbNodeBodyUrl(slug), {
        signal: controller.signal,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      })
        .then((r) => {
          if (!r.ok) {
            // 404 = slug fora do tenant OU fora do padrão da rota; 403 = permissão.
            throw new Error(`HTTP ${r.status}`);
          }
          return r.json();
        })
        .then((d: { content_md?: string | null; github_url?: string | null }) => {
          if (!alive) return;
          setState({
            status: 'ok',
            body: {
              content_md: d.content_md ?? null,
              github_url: d.github_url ?? null,
            },
            error: null,
          });
        })
        .catch((e: unknown) => {
          if (!alive || (e instanceof DOMException && e.name === 'AbortError')) return;
          setState({
            status: 'error',
            body: null,
            error: 'Não foi possível carregar o conteúdo deste documento.',
          });
        });
    }, 250);

    return () => {
      alive = false;
      clearTimeout(timer);
      controller.abort();
    };
  }, [slug, enabled]);

  return state;
}
