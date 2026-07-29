import * as React from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import {
  ChevronLeft,
  ChevronRight,
  X,
  Pin,
  ThumbsUp,
  AlertTriangle,
  Sparkles,
  CheckCircle2,
  Link as LinkIcon,
  Presentation,
  Printer,
  History,
  Edit3,
  ExternalLink,
  Unlink,
  Github,
  Loader2,
} from 'lucide-react';
import type { KbCategory, KbNode } from '../_lib/types';
import { useKbNodeBody } from '../_lib/useKbNodeBody';
import {
  codeDriftRefs,
  extractHeadings,
  findCategory,
  fmtRelative,
  freshnessLevel,
  isNodeOutdated,
  relatedNodes,
  typeLabelPtBR,
} from '../_lib/helpers';
import BlockRenderer from './BlockRenderer';
import KbFavStar from './KbFavStar';
import { Inline } from '@/Components/layout';
import { cn } from '@/Lib/utils';

/**
 * NodeReader — coluna 3 do tri-pane
 *
 * Port consolidado de `kb-page.jsx::ArticleReader` + `kb-extras.jsx::KBRelated` (Cowork [CC]).
 *
 * Estrutura:
 *  - Empty state quando nenhum nó selecionado (sugestões pinned)
 *  - Header: pílula cat hue, nível, equip, pinned, outdated, frescor, fav star, nav arrows, X
 *  - Title h2 + meta (autor, atualizado, readTime, reads)
 *  - TOC se houver h2 no body
 *  - Body via BlockRenderer
 *  - Footer: tags + voto helpful/outdated + ações (Resumir IA / Re-verificar / Histórico /
 *    Anexar a OS / Apresentar / Imprimir SOP / Editar)
 *  - Related articles (top-3) ao final
 */

interface Props {
  node: KbNode | null;
  allNodes: KbNode[];
  categories: KbCategory[];
  prevNode: KbNode | null;
  nextNode: KbNode | null;
  isFavorite: boolean;
  onClose: () => void;
  onPrev: () => void;
  onNext: () => void;
  onToggleFav: () => void;
  onVoteHelpful: () => void;
  onVoteOutdated: () => void;
  onReverify: () => void;
  onAttachToOS: () => void;
  onSummarizeAI: () => void;
  onPresent: () => void;
  onPrint: () => void;
  onHistory: () => void;
  onEdit: () => void;
  onPickRelated: (id: number) => void;
  /** chamado quando clica em "#kb-NNN" inline */
  onPickByRef: (ref: string) => void;
  onPickTag: (tag: string) => void;
  /** nós pinned pra empty state */
  pinned: KbNode[];
  /** capabilities pra esconder botões */
  canWrite?: boolean;
  canAiAsk?: boolean;
}

export default function NodeReader({
  node,
  allNodes,
  categories,
  prevNode,
  nextNode,
  isFavorite,
  onClose,
  onPrev,
  onNext,
  onToggleFav,
  onVoteHelpful,
  onVoteOutdated,
  onReverify,
  onAttachToOS,
  onSummarizeAI,
  onPresent,
  onPrint,
  onHistory,
  onEdit,
  onPickRelated,
  onPickByRef,
  onPickTag,
  pinned,
  canWrite,
  canAiAsk,
}: Props) {
  const scrollRef = React.useRef<HTMLDivElement | null>(null);

  // Scroll-to-top quando muda nó
  React.useEffect(() => {
    if (scrollRef.current) scrollRef.current.scrollTop = 0;
  }, [node?.id]);

  // related precisa ser computado ANTES do early return — senão React quebra
  // com "Rendered more hooks than during the previous render" (#310) quando
  // user clica artigo (null → node) e o número de hooks chamados muda.
  const related = React.useMemo(
    () => (node ? relatedNodes(node, allNodes, 3) : []),
    [node, allNodes],
  );

  // ─── Empty state ─────────────────────────────────────
  if (!node) {
    return (
      <section
        className="kb-reader flex flex-col min-h-0 bg-card items-center justify-center px-8 py-12 gap-3 text-center"
        aria-label="Nenhum artigo selecionado"
      >
        <div
          className="text-3xl text-border leading-none"
          aria-hidden
        >
          ≡
        </div>
        <h3 className="m-0 text-[15px] font-semibold text-muted-foreground">
          Selecione um artigo
        </h3>
        <p className="m-0 text-[12.5px] text-muted-foreground max-w-xs">
          Use a lista ao lado ou tecle <kbd className="kb-kbd">⌘K</kbd> para buscar
          em todo o KB.
        </p>
        {pinned.length > 0 && (
          <div className="mt-6 w-full max-w-sm flex flex-col gap-1.5">
            <h4 className="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground m-0 self-start">
              Sugestões
            </h4>
            {pinned.slice(0, 3).map((p) => (
              <button
                key={p.id}
                type="button"
                onClick={() => onPickRelated(p.id)}
                className="rounded-md border border-border bg-background px-3 py-2 text-left hover:border-primary/40 hover:shadow-sm transition-all"
              >
                <b className="block text-[12.5px] font-semibold text-foreground">
                  {p.title}
                </b>
                <span className="block text-[11px] text-muted-foreground mt-0.5">
                  {p.read_time_min ? `${p.read_time_min} min` : '—'}
                  {p.author_name ? ` · ${p.author_name}` : ''}
                </span>
              </button>
            ))}
          </div>
        )}
      </section>
    );
  }

  const cat = findCategory(categories, node.category_id);
  const hue = cat?.hue ?? 240;
  const outdated = isNodeOutdated(node);
  const driftRefs = codeDriftRefs(node);
  const driftCheckedAt = node.code_drift_state?.checked_at ?? null;
  const fresh = freshnessLevel(node.updated_at);
  const headings = extractHeadings(node.body_blocks);
  // `related` movido pra antes do early return (linhas 113-117) — fix React #310

  // Conteúdo do body: bridge canônico (ADR 0061) NÃO tem body_blocks — invariante
  // Tier 0 `is_editable=false ⇒ body_blocks IS NULL`, enforçada pelo KbNodeObserver.
  // O corpo vem por JOIN com mcp_memory_documents, buscado sob demanda em
  // GET /kb/nodes/{slug} (ver `useKbNodeBody`). Leitura pura — o app nunca edita.
  const isBridge = node.body_blocks === null && node.source_doc_id !== null;

  return (
    <section
      className="kb-reader flex flex-col min-h-0 bg-card overflow-hidden"
      aria-label={`Artigo: ${node.title}`}
    >
      {/* Header */}
      <header className="px-5 pt-4 pb-3 border-b border-border">
        <div className="flex flex-wrap items-center gap-1.5 mb-2">
          {cat && (
            <span
              className="kb-hue-chip inline-flex items-center rounded-sm px-1.5 py-px text-[9.5px] font-semibold lowercase"
              style={{ '--kb-hue': hue } as React.CSSProperties}
            >
              {cat.label}
            </span>
          )}
          <span
            className="text-[10px] font-mono text-muted-foreground bg-muted px-1.5 py-px rounded-sm"
            title="Tipo do nó"
          >
            {typeLabelPtBR(node.type)}
          </span>
          {node.nivel && (
            <span className="text-[10px] font-semibold lowercase text-muted-foreground">
              {node.nivel === 'iniciante'
                ? 'iniciante'
                : node.nivel === 'intermediario'
                  ? 'intermediário'
                  : 'avançado'}
            </span>
          )}
          {node.equip && node.equip !== '—' && (
            <span className="text-[10px] font-mono bg-muted text-muted-foreground px-1.5 py-px rounded-sm">
              {node.equip}
            </span>
          )}
          {node.pinned && (
            <span className="inline-flex items-center gap-0.5 text-[9.5px] font-semibold lowercase text-primary bg-primary/10 px-1.5 py-px rounded-sm">
              <Pin size={9} aria-hidden /> fixo
            </span>
          )}
          {outdated && (
            <span className="text-[9.5px] font-semibold lowercase text-warning-fg bg-warning-soft px-1.5 py-px rounded-sm">
              precisa revisão
            </span>
          )}
          {driftRefs.length > 0 && (
            <span
              className="inline-flex items-center gap-0.5 text-[9.5px] font-semibold lowercase text-destructive bg-destructive/10 px-1.5 py-px rounded-sm"
              title={`Cita código removido do git: ${driftRefs
                .map((r) => r.path)
                .join(', ')}${driftCheckedAt ? ` (detectado ${fmtRelative(driftCheckedAt)})` : ''}`}
            >
              <Unlink size={9} aria-hidden /> cita código removido
            </span>
          )}
          <span
            className={cn(
              'inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-px rounded-sm',
              fresh.level === 'fresh' && 'text-success-fg bg-success-soft',
              fresh.level === 'aging' && 'text-info-fg bg-info-soft',
              fresh.level === 'stale' && 'text-warning-fg bg-warning-soft',
              fresh.level === 'expired' && 'text-destructive bg-destructive/10',
            )}
            title={`Última atualização ${fmtRelative(node.updated_at)}`}
          >
            <span className="inline-block h-1 w-1 rounded-full bg-current" aria-hidden />
            {fresh.label}
          </span>

          <KbFavStar active={isFavorite} onClick={onToggleFav} />

          {/* Nav arrows */}
          <div className="ml-auto flex items-center gap-0.5">
            <button
              type="button"
              onClick={onPrev}
              disabled={!prevNode}
              title={prevNode ? `Anterior: ${prevNode.title}` : 'Sem anterior'}
              className="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-40 disabled:hover:bg-transparent"
              aria-label="Artigo anterior"
            >
              <ChevronLeft size={16} />
            </button>
            <button
              type="button"
              onClick={onNext}
              disabled={!nextNode}
              title={nextNode ? `Próximo: ${nextNode.title}` : 'Sem próximo'}
              className="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-40 disabled:hover:bg-transparent"
              aria-label="Próximo artigo"
            >
              <ChevronRight size={16} />
            </button>
            <button
              type="button"
              onClick={onClose}
              title="Fechar (Esc)"
              className="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
              aria-label="Fechar"
            >
              <X size={14} />
            </button>
          </div>
        </div>

        <h2 className="m-0 text-[20px] font-semibold tracking-tight text-foreground leading-tight">
          {node.title}
        </h2>

        <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11.5px] text-muted-foreground">
          {node.author_name && <span>{node.author_name}</span>}
          {node.author_name && <span className="text-border">·</span>}
          <span>atualizado {fmtRelative(node.updated_at)}</span>
          {node.read_time_min && (
            <>
              <span className="text-border">·</span>
              <span className="font-mono">{node.read_time_min} min de leitura</span>
            </>
          )}
          <span className="text-border">·</span>
          <span>{node.reads_count} leituras</span>
          {node.last_verified_at && (
            <>
              <span className="text-border">·</span>
              <span title="Última re-verificação pelo dono">
                re-verificado {fmtRelative(node.last_verified_at)}
              </span>
            </>
          )}
        </div>
      </header>

      {/* TOC */}
      {headings.length > 0 && (
        <aside className="mx-5 my-3 rounded-md bg-muted/40 px-3 py-2">
          <small className="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground">
            Nesta página
          </small>
          <ol className="m-0 mt-1 ml-4 list-decimal text-[12px] text-foreground/80 space-y-0.5">
            {headings.map((h, i) => (
              <li key={i}>{h}</li>
            ))}
          </ol>
        </aside>
      )}

      {/* Body */}
      <div
        ref={scrollRef}
        className="flex-1 overflow-y-auto px-5 py-3"
      >
        {isBridge ? (
          <BridgeBody node={node} />
        ) : node.excerpt ? (
          <p className="mb-4 text-[14.5px] leading-relaxed text-muted-foreground font-medium border-l-2 border-primary/30 pl-3">
            {node.excerpt}
          </p>
        ) : null}

        <BlockRenderer blocks={node.body_blocks} onPickRef={onPickByRef} />

        {/* Tags */}
        {node.tags && node.tags.length > 0 && (
          <div className="mt-6 flex flex-wrap items-center gap-1">
            <small className="text-[10px] text-muted-foreground mr-1">Etiquetas:</small>
            {node.tags.map((t) => (
              <button
                key={t}
                type="button"
                onClick={() => onPickTag(t)}
                className="rounded-full border border-border bg-card px-2 py-0.5 text-[10.5px] text-muted-foreground hover:border-primary/40 hover:text-foreground"
              >
                {t}
              </button>
            ))}
          </div>
        )}

        {/* Related */}
        {related.length > 0 && (
          <div className="mt-8 pt-6 border-t border-border">
            <small className="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground">
              Relacionados
            </small>
            <ul className="mt-2 grid sm:grid-cols-2 gap-2 list-none m-0 p-0">
              {related.map((r) => (
                <li key={r.id}>
                  <button
                    type="button"
                    onClick={() => onPickRelated(r.id)}
                    className="w-full text-left rounded-md border border-border bg-background px-3 py-2 hover:border-primary/40 hover:shadow-sm transition-all"
                  >
                    <b className="block text-[12.5px] font-semibold text-foreground line-clamp-2">
                      {r.title}
                    </b>
                    <span className="block text-[10.5px] text-muted-foreground mt-1">
                      {r.read_time_min ? `${r.read_time_min} min` : '—'}
                      {r.author_name ? ` · ${r.author_name}` : ''}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {/* Footer actions */}
      <footer className="border-t border-border px-5 py-3 flex flex-wrap items-center gap-2 bg-muted/20">
        <button
          type="button"
          onClick={onVoteHelpful}
          className="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1 text-[11.5px] font-medium text-foreground hover:border-success/40 hover:bg-success/5"
          title="Marcar como útil"
        >
          <ThumbsUp size={12} /> Útil{' '}
          <span className="font-mono text-muted-foreground">{node.helpful_count}</span>
        </button>
        <button
          type="button"
          onClick={onVoteOutdated}
          className="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1 text-[11.5px] font-medium text-warning-fg hover:border-warning/40 hover:bg-warning/5"
          title="Marcar como possivelmente desatualizado"
        >
          <AlertTriangle size={12} /> Desatualizado{' '}
          <span className="font-mono text-muted-foreground">{node.outdated_votes}</span>
        </button>

        <div className="ml-auto flex flex-wrap items-center gap-1.5">
          {canAiAsk && (
            <button
              type="button"
              onClick={onSummarizeAI}
              className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11.5px] font-medium text-primary hover:bg-primary/10"
              title="Resumir com IA (3 bullets)"
            >
              <Sparkles size={12} /> Resumir IA
            </button>
          )}
          <button
            type="button"
            onClick={onReverify}
            className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted"
            title="Confirmar que continua válido"
          >
            <CheckCircle2 size={12} /> Re-verificar
          </button>
          <button
            type="button"
            onClick={onHistory}
            className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted"
            title="Histórico de versões"
          >
            <History size={12} /> Histórico
          </button>
          <button
            type="button"
            onClick={onAttachToOS}
            className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted"
            title="Anexar a OS ativa"
          >
            <LinkIcon size={12} /> Anexar a OS
          </button>
          <button
            type="button"
            onClick={onPresent}
            className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted"
            title="Modo apresentação (slides)"
          >
            <Presentation size={12} /> Apresentar
          </button>
          <button
            type="button"
            onClick={onPrint}
            className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted"
            title="Imprimir SOP com header Oimpresso"
          >
            <Printer size={12} /> Imprimir SOP
          </button>
          {canWrite && (
            <button
              type="button"
              onClick={onEdit}
              className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1 text-[11.5px] font-medium text-primary-foreground hover:bg-primary/90"
              title="Editar artigo"
            >
              <Edit3 size={12} /> Editar
            </button>
          )}
        </div>
      </footer>
    </section>
  );
}

/**
 * BridgeBody — o LEITOR do documento canônico (ONDA 1, 2026-07-29).
 *
 * Quando `body_blocks IS NULL` + `source_doc_id != null`, o nó é bridge canônico:
 * o corpo NÃO está (nem pode estar) em `kb_nodes` — invariante Tier 0 do
 * `KbBridgeFromMcpJob` (ADR 0061). O texto vem por JOIN com
 * `mcp_memory_documents.content_md`, buscado em `GET /kb/nodes/{slug}`.
 *
 * Read-only por construção: renderiza markdown, não expõe edição. Quem quiser mudar
 * o documento muda o arquivo em `memory/*` no git — o bridge re-sincroniza em 15 min.
 * (O `PUT /kb/nodes/{slug}` rejeita bridge com 422 `NODE_NOT_EDITABLE`.)
 *
 * Estados honestos (charter §6: "NUNCA afirmar sucesso de uma ação que não aconteceu"):
 * carregando · erro explícito · corpo vazio explícito · corpo renderizado.
 */
function BridgeBody({ node }: { node: KbNode }) {
  const { status, body, error } = useKbNodeBody(node.slug, true);

  return (
    <div className="mb-4">
      {node.excerpt && (
        <p className="mb-4 text-[14.5px] leading-relaxed text-muted-foreground font-medium border-l-2 border-primary/30 pl-3">
          {node.excerpt}
        </p>
      )}

      {(status === 'idle' || status === 'loading') && (
        <Inline
          gap={2}
          className="text-[12px] text-muted-foreground py-4"
          role="status"
          aria-live="polite"
        >
          <Loader2 size={13} className="animate-spin" aria-hidden />
          Carregando o conteúdo do documento…
        </Inline>
      )}

      {status === 'error' && (
        <div
          className="rounded-md border border-destructive/40 bg-destructive/5 px-4 py-3 text-[12.5px] text-destructive"
          role="alert"
        >
          <Inline gap={2} className="font-medium">
            <AlertTriangle size={13} aria-hidden />
            {error}
          </Inline>
          <p className="m-0 mt-1 text-[11.5px] text-muted-foreground">
            O texto canônico continua no git — abra pela fonte, abaixo.
          </p>
        </div>
      )}

      {status === 'ok' && (
        <>
          {body?.content_md ? (
            <article
              className="prose prose-sm dark:prose-invert max-w-none
                prose-headings:scroll-mt-4 prose-headings:font-semibold
                prose-h1:text-xl prose-h1:border-b prose-h1:border-border prose-h1:pb-2 prose-h1:mb-4
                prose-h2:text-base prose-h2:mt-6 prose-h2:mb-2
                prose-h3:text-sm prose-h3:mt-4 prose-h3:mb-1.5
                prose-p:text-[13.5px]
                prose-pre:bg-muted prose-pre:text-foreground prose-pre:border prose-pre:border-border prose-pre:rounded-md prose-pre:p-3 prose-pre:text-[11.5px]
                prose-code:before:content-none prose-code:after:content-none
                prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:text-[11.5px] prose-code:font-mono prose-code:font-normal
                prose-a:text-primary prose-a:no-underline hover:prose-a:underline
                prose-table:text-[11.5px] prose-th:text-[11.5px] prose-td:py-1 prose-td:px-2
                prose-blockquote:border-l-2 prose-blockquote:border-primary/40 prose-blockquote:pl-3 prose-blockquote:not-italic prose-blockquote:text-muted-foreground
                prose-hr:my-5 prose-hr:border-border
                prose-strong:text-foreground
                prose-li:my-0.5 prose-li:text-[13.5px]
                prose-img:rounded-md"
              data-testid="kb-bridge-body"
            >
              <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                  a: ({ href, children, ...rest }) => {
                    const isExternal = href && /^(https?:|mailto:)/.test(href);
                    return isExternal ? (
                      <a href={href} target="_blank" rel="noopener noreferrer" {...rest}>
                        {children}
                      </a>
                    ) : (
                      <a href={href} {...rest}>
                        {children}
                      </a>
                    );
                  },
                }}
              >
                {body.content_md}
              </ReactMarkdown>
            </article>
          ) : (
            <p className="m-0 py-4 text-[12.5px] text-muted-foreground italic">
              Este documento não tem corpo indexado.
            </p>
          )}

          <Inline gap={2} wrap className="mt-6 border-t border-border pt-3 text-[11px] text-muted-foreground">
            <ExternalLink size={11} aria-hidden />
            <span>
              Documento canônico — a fonte é o git; esta tela é leitura.
            </span>
            {body?.github_url && (
              <a
                href={body.github_url}
                target="_blank"
                rel="noopener noreferrer"
                className="ml-auto inline-flex items-center gap-1 rounded-md border border-border bg-card px-2 py-0.5 text-[11px] font-medium text-foreground hover:border-primary/40"
              >
                <Github size={11} aria-hidden /> Ver no GitHub
              </a>
            )}
          </Inline>
        </>
      )}
    </div>
  );
}
