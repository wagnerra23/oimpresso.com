// KbHistoryDialog — histórico de versões de um SOP — ONDA 3 (ADR 0150).
//   Dialog que, ao abrir com node != null, busca GET /kb/nodes/{slug}/versions
//   e lista os snapshots (mais recente no topo). Estados: loading, empty, erro.
//
//   Backend esperado (Agent A — ONDA 3): GET /kb/nodes/{slug}/versions
//     resp: { versions: KbNodeVersion[] }  (ou array puro — ambos tolerados)
//     KbNodeVersion: { id?, version_at, author_user_id?, author_name?, change_reason? }
//   Tolerante: aceita resposta como array direto OU { versions: [...] }; campos
//   ausentes caem em fallback ("—"). Falha de rede vira toast amigável (sonner).

import * as React from 'react';
import { Loader2, History, User } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog';
import { toast } from 'sonner';
import type { KbNode } from '../_lib/types';
import { fmtRelative } from '../_lib/helpers';

interface Props {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  /** Node cujo histórico será carregado; null = nada a buscar. */
  node: KbNode | null;
}

/** Snapshot de versão — campos best-effort (Agent A pode mandar parcial). */
interface KbNodeVersion {
  id?: number;
  version_at: string | null;
  author_user_id?: number | null;
  author_name?: string | null;
  change_reason?: string | null;
}

export default function KbHistoryDialog({ open, onOpenChange, node }: Props) {
  const [loading, setLoading] = React.useState(false);
  const [versions, setVersions] = React.useState<KbNodeVersion[] | null>(null);

  React.useEffect(() => {
    if (!open || !node) {
      setVersions(null);
      return;
    }
    let cancelled = false;
    const ctrl = new AbortController();
    setLoading(true);
    setVersions(null);

    (async () => {
      try {
        const res = await fetch(
          `/kb/nodes/${encodeURIComponent(node.slug)}/versions`,
          {
            method: 'GET',
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            signal: ctrl.signal,
          },
        );
        if (!res.ok) throw res;
        const data: unknown = await res.json();
        // Aceita { versions: [...] } OU array puro.
        const list: KbNodeVersion[] = Array.isArray(data)
          ? (data as KbNodeVersion[])
          : Array.isArray((data as { versions?: unknown })?.versions)
            ? ((data as { versions: KbNodeVersion[] }).versions)
            : [];
        if (!cancelled) setVersions(list);
      } catch (err) {
        if (cancelled || (err instanceof DOMException && err.name === 'AbortError')) {
          return;
        }
        toast.error('Não foi possível carregar o histórico de versões.');
        if (!cancelled) setVersions([]);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
      ctrl.abort();
    };
  }, [open, node]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg p-0 gap-0">
        <DialogHeader className="px-5 py-3 border-b border-border space-y-0.5">
          <small className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground inline-flex items-center gap-1">
            <History size={11} /> Histórico de versões
          </small>
          <DialogTitle className="text-[16px] line-clamp-1">
            {node?.title ?? 'Versões'}
          </DialogTitle>
          <DialogDescription className="text-[11.5px]">
            Snapshots salvos a cada edição. O mais recente aparece no topo.
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-[60vh] overflow-y-auto px-4 py-3">
          {loading ? (
            <div className="flex items-center justify-center gap-2 py-10 text-muted-foreground">
              <Loader2 size={16} className="animate-spin" />
              <span className="text-[12.5px]">Carregando histórico…</span>
            </div>
          ) : !versions || versions.length === 0 ? (
            <div className="py-10 text-center">
              <History
                size={28}
                className="mx-auto mb-2 text-muted-foreground/50"
              />
              <p className="m-0 text-[12.5px] text-muted-foreground">
                Nenhuma versão anterior registrada ainda.
              </p>
              <p className="m-0 mt-0.5 text-[11px] text-muted-foreground/80">
                As próximas edições deste SOP ficarão listadas aqui.
              </p>
            </div>
          ) : (
            <ol className="relative m-0 list-none space-y-0 pl-4">
              {/* linha do tempo */}
              <span
                className="absolute left-[5px] top-1 bottom-1 w-px bg-border"
                aria-hidden
              />
              {versions.map((v, i) => {
                const author = v.author_name ?? (v.author_user_id ? `Usuário #${v.author_user_id}` : null);
                return (
                  <li
                    key={v.id ?? `${v.version_at ?? 'v'}-${i}`}
                    className="relative py-2.5"
                  >
                    <span
                      className="absolute -left-4 top-3.5 h-2 w-2 rounded-full border-2 border-background bg-primary"
                      aria-hidden
                    />
                    <div className="flex items-baseline justify-between gap-2">
                      <span className="text-[12.5px] font-medium text-foreground">
                        {fmtRelative(v.version_at)}
                      </span>
                      {i === 0 && (
                        <span className="text-[9.5px] font-semibold uppercase tracking-wider text-primary bg-primary/10 px-1.5 py-px rounded-sm">
                          mais recente
                        </span>
                      )}
                    </div>
                    {author && (
                      <span className="mt-0.5 inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                        <User size={11} /> {author}
                      </span>
                    )}
                    {v.change_reason && (
                      <p className="m-0 mt-1 text-[11.5px] text-muted-foreground">
                        {v.change_reason}
                      </p>
                    )}
                  </li>
                );
              })}
            </ol>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
