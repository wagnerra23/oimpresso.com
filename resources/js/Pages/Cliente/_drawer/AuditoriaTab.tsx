// Wave F-FE -- AuditoriaTab.tsx
//
// Tab 8 do drawer 760px Cliente. Timeline LGPD Art. 18 (Spatie ActivityLog
// v4.8 via Modules/Crm/Http/Controllers/ClienteAuditoriaController).
//
// Refs:
//   - ADR 0179 §Wave F (memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
//   - Charter resources/js/Pages/Cliente/Index.charter.md v3 (Goals Tab Auditoria)
//   - prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md §6
//   - Cowork blueprint: prototipo-ui/prototipos/clientes/clientes-tabs.jsx::AuditTab
//
// Contrato (combinado com ClienteAuditoriaController Wave F-BE):
//   GET /cliente/{id}/auditoria?page=N&per_page=20
//     -> { data: AuditEvent[], meta: { current_page, last_page, per_page, total } }
//   GET /cliente/{id}/auditoria/export?format=csv
//     -> download stream CSV UTF-8 BOM
//
// Pegadinhas Tier 0 / LICOES_F3:
//  - Nao cachear timeline (eventos mudam em tempo real)
//  - cancellation flag `alive` em cleanup useEffect (anti-leak F3 T-AP-14)
//  - PT-BR em TODO texto visivel
//  - A11y: <ol> ordered list semantica timeline, time element pro timestamp
//  - PII LGPD: server ja mascarou CPF/CNPJ (defesa backend) -- frontend nao
//    precisa segundo passe

import { useEffect, useState } from 'react';
import {
  Edit,
  Eye,
  Plus,
  Trash2,
  Shield,
  Tag,
  Loader2,
  AlertCircle,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';

export type AuditEventType = 'created' | 'updated' | 'deleted' | 'restored' | 'custom';

export type AuditIconHint = 'edit' | 'tag' | 'eye' | 'plus' | 'trash' | 'shield';

export interface AuditCauser {
  id: number;
  name: string;
  avatar_initials: string;
}

export interface AuditEvent {
  id: number;
  type: AuditEventType;
  description: string;
  field?: string | null;
  old_value?: string | null;
  new_value?: string | null;
  causer: AuditCauser | null;
  created_at: string;
  created_at_human: string;
  icon_hint: AuditIconHint;
}

export interface AuditMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AuditoriaTabProps {
  contact: { id: number };
}

const ICON_MAP: Record<AuditIconHint, typeof Edit> = {
  edit: Edit,
  eye: Eye,
  plus: Plus,
  trash: Trash2,
  shield: Shield,
  tag: Tag,
};

const PER_PAGE = 20;

function formatAbsoluteDate(iso: string): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
}

export default function AuditoriaTab({ contact }: AuditoriaTabProps) {
  const [events, setEvents] = useState<AuditEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<AuditMeta | null>(null);

  useEffect(() => {
    let alive = true;
    async function load() {
      setLoading(true);
      setError(null);
      try {
        const r = await fetch(
          `/cliente/${contact.id}/auditoria?page=${page}&per_page=${PER_PAGE}`,
          {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
          },
        );
        if (!r.ok) {
          throw new Error(`Erro ${r.status} ao carregar timeline`);
        }
        const j = (await r.json()) as { data: AuditEvent[]; meta: AuditMeta };
        if (!alive) return;
        setEvents(Array.isArray(j.data) ? j.data : []);
        setMeta(j.meta ?? null);
      } catch (e: unknown) {
        if (!alive) return;
        const msg = e instanceof Error ? e.message : 'Erro inesperado';
        setError(msg);
      } finally {
        if (alive) setLoading(false);
      }
    }
    void load();
    return () => {
      alive = false;
    };
  }, [contact.id, page]);

  return (
    <div className="space-y-4" data-testid="auditoria-tab-root">
      {/* Wagner 2026-06-13: cabeçalho (título + nota de privacidade + export CSV)
          removido a pedido — a aba renderiza só a timeline. Acesso LGPD segue
          garantido pelos dados + rota de export no backend. */}

      {/* Loading state */}
      {loading && events.length === 0 && (
        <div
          className="p-8 text-center text-xs text-muted-foreground"
          data-testid="auditoria-tab-loading"
        >
          <Loader2 className="inline-block h-4 w-4 animate-spin mr-2" />
          Carregando timeline...
        </div>
      )}

      {/* Error state */}
      {error && (
        <div
          className="p-4 text-xs text-destructive-fg flex items-start gap-2 rounded-lg border border-destructive/20 bg-destructive-soft"
          data-testid="auditoria-tab-error"
        >
          <AlertCircle size={14} className="flex-shrink-0 mt-0.5" />
          <span>{error}</span>
        </div>
      )}

      {/* Empty state */}
      {!loading && !error && events.length === 0 && (
        <div
          className="p-8 text-center text-xs text-muted-foreground"
          data-testid="auditoria-tab-empty"
        >
          Nenhum evento registrado ainda.
        </div>
      )}

      {/* Timeline */}
      {events.length > 0 && (
        <ol className="space-y-3" data-testid="auditoria-tab-timeline">
          {events.map((ev) => {
            const Icon = ICON_MAP[ev.icon_hint] ?? Eye;
            return (
              <li
                key={ev.id}
                className="flex items-start gap-3 rounded-lg border border-border bg-background p-3"
                data-testid={`auditoria-event-${ev.id}`}
              >
                <div className="flex-shrink-0 h-8 w-8 rounded-md bg-muted flex items-center justify-center">
                  <Icon size={14} className="text-muted-foreground" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm text-foreground font-medium truncate">
                      {ev.description}
                    </p>
                    <time
                      dateTime={ev.created_at}
                      className="text-[10px] text-muted-foreground tabular-nums flex-shrink-0"
                      title={formatAbsoluteDate(ev.created_at)}
                    >
                      {ev.created_at_human}
                    </time>
                  </div>
                  {ev.causer && (
                    <div className="flex items-center gap-2 mt-1">
                      <span
                        className="inline-flex h-5 w-5 items-center justify-center rounded-md bg-muted text-[9px] font-semibold text-foreground"
                        aria-hidden="true"
                      >
                        {ev.causer.avatar_initials}
                      </span>
                      <p className="text-xs text-muted-foreground">
                        Por{' '}
                        <span className="font-medium text-foreground">
                          {ev.causer.name}
                        </span>
                        <span className="ml-2 tabular-nums">
                          {formatAbsoluteDate(ev.created_at)}
                        </span>
                      </p>
                    </div>
                  )}
                  {!ev.causer && (
                    <p className="text-xs text-muted-foreground mt-1">
                      <span className="font-medium text-foreground">Sistema</span>
                      <span className="ml-2 tabular-nums">
                        {formatAbsoluteDate(ev.created_at)}
                      </span>
                    </p>
                  )}
                </div>
              </li>
            );
          })}
        </ol>
      )}

      {/* Paginacao */}
      {meta && meta.last_page > 1 && (
        <div
          className="flex items-center justify-between text-xs text-muted-foreground pt-3 border-t border-border"
          data-testid="auditoria-tab-pagination"
        >
          <span>
            Pagina {meta.current_page} de {meta.last_page} ({meta.total} eventos)
          </span>
          <div className="flex gap-1">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1 || loading}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              data-testid="auditoria-prev-btn"
            >
              Anterior
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= meta.last_page || loading}
              onClick={() => setPage((p) => p + 1)}
              data-testid="auditoria-next-btn"
            >
              Proxima
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
