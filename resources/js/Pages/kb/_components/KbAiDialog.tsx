// KbAiDialog — "Perguntar ao KB" (RAG) — ONDA 5 IA (ADR 0150).
//   Dialog com campo de pergunta → POST /kb/ai/ask {question} → resposta markdown
//   + lista de fontes (sources[]). Citações renderizadas via ReactMarkdown + remarkGfm
//   (mesmo stack do NodeReader/Index). IA pode estar indisponível no ambiente:
//   falha vira toast amigável (sonner), nunca quebra a tela.
//
//   Backend esperado (Agent A — ONDA 5): POST /kb/ai/ask
//     req:  { question: string }
//     resp: { answer: string (markdown), sources?: Array<{ id?, title?, slug?, excerpt? }> }
//   Tolerante: se `sources` vier ausente/null, só não renderiza a seção.

import * as React from 'react';
import { Loader2, Sparkles, Send, FileText } from 'lucide-react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog';
import { Textarea } from '@/Components/ui/textarea';
import { Button } from '@/Components/ui/button';
import { toast } from 'sonner';
import { cn } from '@/Lib/utils';

interface Props {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  /** Pré-preenche a pergunta ao abrir (ex.: vindo do CommandPalette). */
  initialQuery?: string;
}

/** Fonte citada pela resposta — campos best-effort (Agent A pode mandar parcial). */
interface KbAiSource {
  id?: number;
  title?: string;
  slug?: string;
  excerpt?: string;
}

interface KbAiAnswer {
  answer: string;
  sources?: KbAiSource[] | null;
}

function csrf(): string {
  return (
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ??
    ''
  );
}

export default function KbAiDialog({ open, onOpenChange, initialQuery }: Props) {
  const [question, setQuestion] = React.useState('');
  const [loading, setLoading] = React.useState(false);
  const [answer, setAnswer] = React.useState<KbAiAnswer | null>(null);
  const inputRef = React.useRef<HTMLTextAreaElement>(null);

  // (re)inicializa ao abrir: pré-preenche query, limpa resposta anterior, foca.
  React.useEffect(() => {
    if (!open) return;
    setQuestion(initialQuery ?? '');
    setAnswer(null);
    setLoading(false);
    // foco no próximo tick (depois do mount do conteúdo do Dialog)
    const t = window.setTimeout(() => inputRef.current?.focus(), 60);
    return () => window.clearTimeout(t);
  }, [open, initialQuery]);

  async function ask() {
    const q = question.trim();
    if (q.length < 3) {
      toast.error('Escreva uma pergunta com pelo menos 3 caracteres.');
      return;
    }
    setLoading(true);
    setAnswer(null);
    try {
      const res = await fetch('/kb/ai/ask', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ query: q }),
      });
      if (!res.ok) throw res;
      const data = (await res.json()) as Partial<KbAiAnswer>;
      setAnswer({
        answer:
          typeof data.answer === 'string' && data.answer.trim()
            ? data.answer
            : '*A IA não retornou uma resposta para esta pergunta.*',
        sources: Array.isArray(data.sources) ? data.sources : null,
      });
    } catch {
      toast.error(
        'Não foi possível consultar a IA agora. Tente de novo em instantes.',
      );
    } finally {
      setLoading(false);
    }
  }

  // Cmd/Ctrl+Enter envia (textarea multi-linha; Enter puro quebra linha).
  function onKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      if (!loading) ask();
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl p-0 gap-0">
        <DialogHeader className="px-5 py-3 border-b border-border space-y-0.5">
          <small className="text-[10px] font-bold uppercase tracking-wider text-primary inline-flex items-center gap-1">
            <Sparkles size={11} /> Assistente do KB
          </small>
          <DialogTitle className="text-[16px]">Perguntar ao KB</DialogTitle>
          <DialogDescription className="text-[11.5px]">
            Pergunte em linguagem natural. A IA responde com base nos
            procedimentos publicados e cita as fontes.
          </DialogDescription>
        </DialogHeader>

        <div className="px-5 py-4 space-y-3 max-h-[60vh] overflow-y-auto">
          <div className="space-y-2">
            <Textarea
              ref={inputRef}
              value={question}
              onChange={(e) => setQuestion(e.target.value)}
              onKeyDown={onKeyDown}
              rows={3}
              disabled={loading}
              placeholder="Ex.: Como faço a troca da bobina da HP Latex 365 sem danificar o eixo?"
              className="text-[13.5px]"
            />
            <div className="flex items-center justify-between gap-2">
              <small className="text-[10.5px] text-muted-foreground">
                <kbd className="font-mono">Ctrl/⌘ + Enter</kbd> para enviar
              </small>
              <Button
                onClick={ask}
                disabled={loading || question.trim().length < 3}
                size="sm"
                className="h-8 text-xs"
              >
                {loading ? (
                  <Loader2 size={14} className="mr-1.5 animate-spin" />
                ) : (
                  <Send size={14} className="mr-1.5" />
                )}
                Perguntar
              </Button>
            </div>
          </div>

          {loading && (
            <div className="flex items-center justify-center gap-2 py-8 text-muted-foreground">
              <Loader2 size={16} className="animate-spin" />
              <span className="text-[12.5px]">Consultando o KB…</span>
            </div>
          )}

          {!loading && answer && (
            <div className="space-y-3 pt-1">
              <article
                className={cn(
                  'prose prose-sm dark:prose-invert max-w-none',
                  'prose-headings:font-semibold prose-headings:text-foreground',
                  'prose-h2:text-base prose-h2:mt-4 prose-h2:mb-2',
                  'prose-h3:text-sm prose-h3:mt-3 prose-h3:mb-1.5',
                  'prose-p:text-[13.5px] prose-p:leading-relaxed',
                  'prose-li:text-[13.5px] prose-li:my-0.5',
                  'prose-code:before:content-none prose-code:after:content-none',
                  'prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:text-xs prose-code:font-mono prose-code:font-normal',
                  'prose-a:text-primary prose-a:no-underline hover:prose-a:underline',
                  'prose-strong:text-foreground',
                  'prose-blockquote:border-l-4 prose-blockquote:border-primary prose-blockquote:pl-3 prose-blockquote:italic prose-blockquote:text-muted-foreground',
                )}
              >
                <ReactMarkdown
                  remarkPlugins={[remarkGfm]}
                  components={{
                    a: ({ href, children, ...rest }) => {
                      const isExternal = href && /^(https?:|mailto:)/.test(href);
                      return isExternal ? (
                        <a
                          href={href}
                          target="_blank"
                          rel="noopener noreferrer"
                          {...rest}
                        >
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
                  {answer.answer}
                </ReactMarkdown>
              </article>

              {answer.sources && answer.sources.length > 0 && (
                <div className="rounded-md border border-border bg-muted/40 px-3 py-2.5">
                  <small className="block text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">
                    Fontes ({answer.sources.length})
                  </small>
                  <ul className="m-0 list-none space-y-1">
                    {answer.sources.map((s, i) => {
                      const label = s.title ?? s.slug ?? `Fonte ${i + 1}`;
                      const inner = (
                        <span className="inline-flex items-start gap-1.5">
                          <FileText
                            size={12}
                            className="mt-0.5 shrink-0 text-primary"
                          />
                          <span className="min-w-0">
                            <span className="text-[12.5px] font-medium text-foreground">
                              {label}
                            </span>
                            {s.excerpt && (
                              <span className="block text-[11px] text-muted-foreground line-clamp-2">
                                {s.excerpt}
                              </span>
                            )}
                          </span>
                        </span>
                      );
                      return (
                        <li key={s.slug ?? s.id ?? i}>
                          {s.slug ? (
                            <a
                              href={`/kb/${s.slug}`}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="block rounded-sm p-1 -m-1 hover:bg-muted"
                            >
                              {inner}
                            </a>
                          ) : (
                            <div className="p-1 -m-1">{inner}</div>
                          )}
                        </li>
                      );
                    })}
                  </ul>
                </div>
              )}

              <p className="text-[10.5px] text-muted-foreground italic">
                Respostas geradas por IA podem conter imprecisões — confira nas
                fontes antes de aplicar.
              </p>
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
