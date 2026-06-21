// DviInlineEditor — Vistoria Digital (DVI) editável inline com semáforo de 1 toque.
//
// F3 OS-V2-2 (fila TELAS_REVIEW_QUEUE · 2026-06-09). Porta pro drawer real
// (ServiceOrderRichSheet) o protótipo Cowork APROVADO [W] 2026-06-09
// (`DviTraffic` + `DviEditor` em oficina-forms.jsx · `.ofc-traffic` em oficina-page.css).
//
// Substitui o `<select>` de status por um RADIOGROUP de 3 botões redondos
// (ok/atenção/crítico · 24px · aria-checked · focus-visible · hover scale) — padrão
// canon Shop-Ware/Tekmetric: persona Técnico Repair de tablet, mãos sujas, 1 toque.
// NUNCA `<select>` nativo pra severidade. Tokens DS (success/warning/destructive),
// nunca hex cru.
//
// Backend já existe (Wave 3 US-OFICINA-035):
//   POST   /oficina-auto/ordens-servico/{order}/dvi          → cria item
//   PUT    /oficina-auto/ordens-servico/{order}/dvi/{item}   → patch parcial
//   DELETE /oficina-auto/ordens-servico/{order}/dvi/{item}   → soft-delete
//   POST   /oficina-auto/ordens-servico/{order}/enviar-aprovacao → gate WhatsApp+PIN
//
// Delta do protótipo (relatado no PR): o backend separa `categoria` (enum 10) de
// `descricao` (texto livre). O protótipo tem um campo "sistema" só. Resolvemos:
// `descricao` é o texto do sistema editável inline; `categoria` é derivada por
// keyword (deriveCategoria) — silenciosa, validada no enum pelo backend. O form de
// adicionar oferece presets (SISTEMAS) que já trazem a categoria certa.
//
// CRÍTICO React 19 — useCallback nos handlers (lição PR #717).
// CRÍTICO F3 LICOES_F3_FINANCEIRO_REJEITADO.md — sem emoji (lucide-react only),
// sem auto-request on-mount, sem window.print, sem nova aba.
// CRÍTICO multi-tenant Tier 0 [ADR 0093] — backend escopa business_id (frontend só consome).

import { useCallback, useMemo, useState } from 'react';
import { Check, Loader2, MessageCircle, Plus, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Grid, Inline, Stack } from '@/Components/layout';

export type DviSeverity = 'ok' | 'atencao' | 'critico';

export type DviCategoria =
  | 'motor'
  | 'freios'
  | 'correia'
  | 'bateria'
  | 'pneus'
  | 'suspensao'
  | 'direcao'
  | 'eletrica'
  | 'fluidos'
  | 'outro';

export interface DviInlineItem {
  id: number;
  categoria: string;
  descricao: string;
  severity: string; // ok | atencao | critico
  recomendacao: string | null;
  valor_recomendado: number | null;
  sort_order?: number;
  budget_item_id?: number | null;
}

// F3 OS-V2-3 — estado do gate de aprovação, derivado do backend (ServiceOrder::approval_state).
export type ApprovalState = 'none' | 'pending' | 'approved' | 'declined';

export interface ApprovalInfo {
  state: ApprovalState;
  total: number;
  requested_at: string | null;
  decided_at: string | null;
  decision: string | null;
}

interface Props {
  serviceOrderId: number;
  initialItems: DviInlineItem[];
  /**
   * Dispara o gate de aprovação (status → orcamento → WhatsApp+PIN). O MESMO handler
   * serve "Pedir aprovação" (none), "Cobrar" (pending) e "Revisar e reenviar" (declined) —
   * o backend re-carimba `approval_requested_at` e re-dispara o WhatsApp.
   */
  onPedirAprovacao: () => void;
  /** true enquanto o POST enviar-aprovacao está em voo (vem do drawer). */
  approvalSending?: boolean;
  /** F3 OS-V2-3 — estado do gate vindo do backend (null = none). */
  approval?: ApprovalInfo | null;
}

// Tempo relativo curto pro gate ("há 12 min" / "há 3h" / "há 2d").
function gateRel(iso: string | null | undefined): string {
  if (!iso) return '';
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return '';
  const min = Math.max(1, Math.round((Date.now() - then) / 60000));
  if (min < 60) return `há ${min} min`;
  const h = Math.round(min / 60);
  if (h < 24) return `há ${h}h`;
  return `há ${Math.round(h / 24)}d`;
}

// Presets de sistema → mapeiam o "sistema" do protótipo (DVI_SISTEMAS) na dupla
// canônica do backend (descricao + categoria). Selecionar preenche ambos.
const SISTEMAS: { label: string; categoria: DviCategoria }[] = [
  { label: 'Motor · óleo + filtro', categoria: 'fluidos' },
  { label: 'Motor · arrefecimento', categoria: 'motor' },
  { label: 'Freios dianteiros · pastilhas', categoria: 'freios' },
  { label: 'Freios traseiros · lonas/discos', categoria: 'freios' },
  { label: 'Correia dentada', categoria: 'correia' },
  { label: 'Bateria + sistema elétrico', categoria: 'bateria' },
  { label: 'Pneus · dianteiros', categoria: 'pneus' },
  { label: 'Pneus · traseiros', categoria: 'pneus' },
  { label: 'Suspensão dianteira', categoria: 'suspensao' },
  { label: 'Suspensão traseira', categoria: 'suspensao' },
  { label: 'Direção · alinhamento', categoria: 'direcao' },
  { label: 'Embreagem', categoria: 'motor' },
  { label: 'Câmbio', categoria: 'motor' },
  { label: 'Injeção', categoria: 'eletrica' },
  { label: 'Escapamento', categoria: 'motor' },
  { label: 'Iluminação', categoria: 'eletrica' },
  { label: 'Ar-condicionado', categoria: 'eletrica' },
  { label: 'Limpadores', categoria: 'eletrica' },
];

// Deriva a categoria enum a partir do texto livre do sistema (descricao). O backend
// valida o enum; aqui só damos o melhor palpite pra filtros/relatórios futuros.
function deriveCategoria(desc: string): DviCategoria {
  const d = (desc || '').toLowerCase();
  if (/bateria/.test(d)) return 'bateria';
  if (/freio|pastilha|lona|disco|sangria/.test(d)) return 'freios';
  if (/correia/.test(d)) return 'correia';
  if (/pneu/.test(d)) return 'pneus';
  if (/suspens|amortec|mola|bandeja|piv[oô]/.test(d)) return 'suspensao';
  if (/dire[çc]|alinha|geometr|terminal/.test(d)) return 'direcao';
  if (/el[ée]tr|inje[çc]|farol|ilumina|l[âa]mpada|fus[íi]|alternador|chicote|ar-?cond/.test(d))
    return 'eletrica';
  if (/[óo]leo|fluido|arrefec|[áa]gua|l[íi]quido|radiador|dot/.test(d)) return 'fluidos';
  if (/motor|cabe[çc]ote|junta|vela|embreagem|c[âa]mbio|escapa|turbo/.test(d)) return 'motor';
  return 'outro';
}

function csrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

const fmtBRL = (n: number | null | undefined) =>
  (n ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

// Semáforo de severidade — 3 botões redondos, 1 toque. Tokens semânticos
// (success/warning/destructive), NUNCA <select> nativo (regra OS-V2-2).
const SEV_BTN: Record<DviSeverity, { label: string; on: string; ring: string; hover: string }> = {
  ok: {
    label: 'OK',
    on: 'bg-success border-success',
    ring: 'ring-success/30',
    hover: 'hover:border-success',
  },
  atencao: {
    label: 'Atenção',
    on: 'bg-warning border-warning',
    ring: 'ring-warning/30',
    hover: 'hover:border-warning',
  },
  critico: {
    label: 'Crítico',
    on: 'bg-destructive border-destructive',
    ring: 'ring-destructive/30',
    hover: 'hover:border-destructive',
  },
};
const SEV_ORDER: DviSeverity[] = ['ok', 'atencao', 'critico'];

function DviTraffic({
  value,
  onChange,
  name,
}: {
  value: string;
  onChange: (s: DviSeverity) => void;
  name: string;
}) {
  return (
    <div
      role="radiogroup"
      aria-label={`Severidade de ${name}`}
      className="inline-flex items-center gap-1.5 self-center"
    >
      {SEV_ORDER.map((sev) => {
        const cfg = SEV_BTN[sev];
        const on = value === sev;
        return (
          <button
            key={sev}
            type="button"
            role="radio"
            aria-checked={on}
            aria-label={cfg.label}
            title={cfg.label}
            onClick={() => onChange(sev)}
            className={
              'h-6 w-6 shrink-0 rounded-full border-2 transition-transform hover:scale-110 ' +
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
              (on ? `${cfg.on} ring-2 ${cfg.ring}` : `border-border bg-transparent ${cfg.hover}`)
            }
          />
        );
      })}
    </div>
  );
}

interface Draft {
  sistema: string;
  severity: DviSeverity;
  recomendacao: string;
  valor: string;
}

const EMPTY_DRAFT: Draft = {
  sistema: SISTEMAS[0]?.label ?? 'Outro',
  severity: 'ok',
  recomendacao: '',
  valor: '',
};

export default function DviInlineEditor({
  serviceOrderId,
  initialItems,
  onPedirAprovacao,
  approvalSending = false,
  approval = null,
}: Props) {
  // Estado local seeded uma vez por OS (drawer renderiza com key={data.id}).
  const [items, setItems] = useState<DviInlineItem[]>(initialItems);
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState<Draft>(EMPTY_DRAFT);
  const [busy, setBusy] = useState(false);

  const base = `/oficina-auto/ordens-servico/${serviceOrderId}/dvi`;

  const sums = useMemo(() => {
    const ok = items.filter((i) => i.severity === 'ok').length;
    const atencao = items.filter((i) => i.severity === 'atencao').length;
    const critico = items.filter((i) => i.severity === 'critico').length;
    const total = items
      .filter((i) => i.severity === 'atencao' || i.severity === 'critico')
      .reduce((s, i) => s + (i.valor_recomendado ?? 0), 0);
    return { ok, atencao, critico, total };
  }, [items]);

  const request = useCallback(
    async (method: string, url: string, body?: Record<string, unknown>) => {
      const resp = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken(),
          ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
      });
      if (!resp.ok) {
        let msg = `HTTP ${resp.status}`;
        try {
          const j = await resp.json();
          msg =
            j?.message ??
            j?.errors?.descricao?.[0] ??
            j?.errors?.severity?.[0] ??
            j?.errors?.categoria?.[0] ??
            msg;
        } catch {
          /* sem JSON body */
        }
        throw new Error(msg);
      }
      return resp.status === 204 ? null : resp.json();
    },
    [],
  );

  const patchLocal = useCallback((id: number, patch: Partial<DviInlineItem>) => {
    setItems((prev) => prev.map((it) => (it.id === id ? { ...it, ...patch } : it)));
  }, []);

  // Severidade — otimista + PUT {severity}, reverte no erro.
  const handleSeverity = useCallback(
    async (item: DviInlineItem, sev: DviSeverity) => {
      if (item.severity === sev) return;
      const prevSev = item.severity;
      patchLocal(item.id, { severity: sev });
      try {
        await request('PUT', `${base}/${item.id}`, { severity: sev });
      } catch (e) {
        patchLocal(item.id, { severity: prevSev });
        toast.error(e instanceof Error ? e.message : 'Falha ao salvar severidade.');
      }
    },
    [base, request, patchLocal],
  );

  // Salva campo de texto/número no blur (só se mudou).
  const saveField = useCallback(
    async (item: DviInlineItem, patch: Partial<DviInlineItem>) => {
      try {
        await request('PUT', `${base}/${item.id}`, patch as Record<string, unknown>);
      } catch (e) {
        toast.error(e instanceof Error ? e.message : 'Falha ao salvar item.');
      }
    },
    [base, request],
  );

  const handleRemove = useCallback(
    async (item: DviInlineItem) => {
      const snapshot = items;
      setItems((prev) => prev.filter((it) => it.id !== item.id));
      try {
        await request('DELETE', `${base}/${item.id}`);
      } catch (e) {
        setItems(snapshot);
        toast.error(e instanceof Error ? e.message : 'Falha ao remover item.');
      }
    },
    [base, request, items],
  );

  const handleAdd = useCallback(async () => {
    const descricao = draft.sistema.trim();
    if (!descricao) return;
    setBusy(true);
    try {
      const valor = parseFloat(draft.valor.replace(',', '.'));
      const json = await request('POST', base, {
        categoria: deriveCategoria(descricao),
        descricao: descricao.slice(0, 150),
        severity: draft.severity,
        recomendacao: draft.recomendacao.trim() || null,
        valor_recomendado: Number.isFinite(valor) && valor > 0 ? valor : null,
        sort_order: items.length,
      });
      if (json?.item) {
        const it = json.item as DviInlineItem;
        setItems((prev) => [...prev, it]);
      }
      setDraft(EMPTY_DRAFT);
      setAdding(false);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Falha ao adicionar item.');
    } finally {
      setBusy(false);
    }
  }, [base, draft, items.length, request]);

  return (
    <div className="space-y-2.5">
      {/* Pills de contagem + botão adicionar */}
      <Inline className="gap-1.5">
        <Pill tone="success" count={sums.ok} label="ok" />
        <Pill tone="warning" count={sums.atencao} label="atenção" />
        <Pill tone="destructive" count={sums.critico} label="crítico" />
        {!adding && (
          <Button
            type="button"
            size="sm"
            variant="ghost"
            className="ml-auto h-7 gap-1 text-xs"
            onClick={() => setAdding(true)}
          >
            <Plus size={13} aria-hidden />
            Item
          </Button>
        )}
      </Inline>

      {/* Estado vazio */}
      {items.length === 0 && !adding && (
        <p className="text-sm italic text-muted-foreground">
          — vistoria ainda não iniciada.{' '}
          <button
            type="button"
            className="font-medium text-primary not-italic hover:underline"
            onClick={() => setAdding(true)}
          >
            Adicionar primeiro item
          </button>
        </p>
      )}

      {/* Lista editável */}
      {items.length > 0 && (
        <ul className="divide-y divide-border/60 overflow-hidden rounded-md border border-border bg-white">
          {items.map((item) => (
            <Grid
              asChild
              gap={2}
              key={item.id}
              className={
                'grid-cols-[auto_1fr_72px_auto] items-center px-2.5 py-2 ' +
                (item.severity === 'critico' ? 'bg-destructive/5' : '')
              }
            >
            <li>
              <DviTraffic
                value={item.severity}
                name={item.descricao}
                onChange={(s) => handleSeverity(item, s)}
              />
              <div className="min-w-0">
                <input
                  defaultValue={item.descricao}
                  aria-label="Sistema vistoriado"
                  className="w-full rounded border border-transparent bg-transparent px-1 py-0.5 text-[12px] font-medium text-foreground outline-none hover:border-border focus:border-ring"
                  onBlur={(e) => {
                    const v = e.target.value.trim().slice(0, 150);
                    if (v && v !== item.descricao) {
                      patchLocal(item.id, { descricao: v });
                      void saveField(item, { descricao: v, categoria: deriveCategoria(v) });
                    }
                  }}
                />
                <input
                  defaultValue={item.recomendacao ?? ''}
                  placeholder="observação · recomendação"
                  aria-label="Observação / recomendação"
                  className="w-full rounded border border-transparent bg-transparent px-1 py-0.5 text-[10.5px] text-muted-foreground outline-none hover:border-border focus:border-ring"
                  onBlur={(e) => {
                    const v = e.target.value.trim().slice(0, 255);
                    if (v !== (item.recomendacao ?? '')) {
                      patchLocal(item.id, { recomendacao: v || null });
                      void saveField(item, { recomendacao: v || null });
                    }
                  }}
                />
              </div>
              <input
                type="number"
                min="0"
                step="0.01"
                defaultValue={item.valor_recomendado ?? ''}
                aria-label="Valor recomendado em reais"
                placeholder="R$"
                className="w-full rounded border border-border/70 bg-background px-1.5 py-1 text-right text-[11px] tabular-nums outline-none focus:border-ring"
                onBlur={(e) => {
                  const raw = e.target.value.replace(',', '.');
                  const v = raw === '' ? null : parseFloat(raw);
                  const next = v != null && Number.isFinite(v) && v >= 0 ? v : null;
                  if (next !== item.valor_recomendado) {
                    patchLocal(item.id, { valor_recomendado: next });
                    void saveField(item, { valor_recomendado: next });
                  }
                }}
              />
              <button
                type="button"
                onClick={() => handleRemove(item)}
                title="Remover item da vistoria"
                aria-label={`Remover ${item.descricao}`}
                className="grid place-items-center h-7 w-7 rounded text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
              >
                <Trash2 size={13} aria-hidden />
              </button>
            </li>
            </Grid>
          ))}
        </ul>
      )}

      {/* Form de adicionar */}
      {adding && (
        <Inline align="start" gap={2} className="rounded-md border border-dashed border-primary/40 bg-primary/5 p-2.5">
          <Stack gap={2} className="flex-1 min-w-0">
            <Select
              value={draft.sistema}
              onValueChange={(v) => setDraft((d) => ({ ...d, sistema: v }))}
            >
              <SelectTrigger className="h-8 text-[12px]" aria-label="Sistema a vistoriar">
                <SelectValue placeholder="Sistema a vistoriar" />
              </SelectTrigger>
              <SelectContent>
                {SISTEMAS.map((s) => (
                  <SelectItem key={s.label} value={s.label} className="text-[12px]">
                    {s.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Inline gap={2}>
              <DviTraffic
                value={draft.severity}
                name="novo item"
                onChange={(s) => setDraft((d) => ({ ...d, severity: s }))}
              />
              <input
                value={draft.valor}
                inputMode="decimal"
                placeholder="R$"
                aria-label="Valor recomendado"
                className="w-20 rounded border border-border bg-background px-2 py-1 text-right text-[11px] tabular-nums outline-none focus:border-ring"
                onChange={(e) => setDraft((d) => ({ ...d, valor: e.target.value }))}
              />
            </Inline>
            <input
              value={draft.recomendacao}
              placeholder="observação · recomendação"
              aria-label="Observação"
              className="w-full rounded border border-border bg-background px-2 py-1 text-[11px] outline-none focus:border-ring"
              onChange={(e) => setDraft((d) => ({ ...d, recomendacao: e.target.value }))}
            />
          </Stack>
          <Stack gap={1}>
            <Button
              type="button"
              size="sm"
              className="h-7 w-7 p-0"
              disabled={busy || !draft.sistema.trim()}
              onClick={handleAdd}
              aria-label="Confirmar item"
            >
              {busy ? <Loader2 size={13} className="animate-spin" /> : <Check size={13} />}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="ghost"
              className="h-7 w-7 p-0"
              onClick={() => {
                setAdding(false);
                setDraft(EMPTY_DRAFT);
              }}
              aria-label="Cancelar"
            >
              <X size={13} />
            </Button>
          </Stack>
        </Inline>
      )}

      {/* Rodapé: gate de aprovação com ciclo de estados (F3 OS-V2-3).
          none → pending → approved | declined. Derivado do backend (approval). */}
      <DviGateFoot
        total={sums.total}
        itemsCount={items.length}
        approval={approval}
        sending={approvalSending}
        onPedirAprovacao={onPedirAprovacao}
      />
    </div>
  );
}

// F3 OS-V2-3 — barra hero do gate de aprovação com 4 estados (espelha DviGateFoot do
// protótipo Cowork aprovado [W] 2026-06-09). Sem botões de simulação demo: o estado
// vem do backend (status da OS + approval_requested_at / approval_decided_at).
function DviGateFoot({
  total,
  itemsCount,
  approval,
  sending,
  onPedirAprovacao,
}: {
  total: number;
  itemsCount: number;
  approval: ApprovalInfo | null;
  sending: boolean;
  onPedirAprovacao: () => void;
}) {
  const state: ApprovalState = approval?.state ?? 'none';
  // Em pending/approved/declined o valor exibido é o que o cliente recebeu (backend);
  // em none usa o total recomendado vivo da DVI.
  const shownTotal = state === 'none' ? total : (approval?.total ?? total);

  const spinnerOr = (icon: React.ReactNode) =>
    sending ? <Loader2 size={14} className="animate-spin" aria-hidden /> : icon;

  if (state === 'pending') {
    return (
      <Inline
        justify="between"
        gap={3}
        className="rounded-md border border-warning/40 bg-warning/10 px-3 py-2 pt-2"
      >
        <div className="min-w-0">
          <div className="truncate text-[10px] font-semibold uppercase tracking-wider text-warning-foreground">
            Aguardando aprovação · WhatsApp {gateRel(approval?.requested_at)}
          </div>
          <div className="text-base font-semibold tabular-nums text-foreground">
            {fmtBRL(shownTotal)}
          </div>
        </div>
        <Button
          type="button"
          size="sm"
          variant="outline"
          className="h-8 gap-1.5"
          disabled={sending}
          onClick={onPedirAprovacao}
          title="Reenvia o link de aprovação por WhatsApp"
        >
          {spinnerOr(<MessageCircle size={14} aria-hidden />)}
          Cobrar
        </Button>
      </Inline>
    );
  }

  if (state === 'approved') {
    return (
      <Inline
        justify="between"
        gap={3}
        className="rounded-md border border-success/40 bg-success/10 px-3 py-2"
      >
        <div className="min-w-0">
          <div className="truncate text-[10px] font-semibold uppercase tracking-wider text-success-foreground">
            Aprovado pelo cliente {gateRel(approval?.decided_at)}
          </div>
          <div className="text-base font-semibold tabular-nums text-foreground">
            {fmtBRL(shownTotal)}
          </div>
        </div>
        <span className="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-success">
          <Check size={15} aria-hidden />
          Autorizado
        </span>
      </Inline>
    );
  }

  if (state === 'declined') {
    return (
      <Inline
        justify="between"
        gap={3}
        className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2"
      >
        <div className="min-w-0">
          <div className="truncate text-[10px] font-semibold uppercase tracking-wider text-destructive">
            Cliente recusou {gateRel(approval?.decided_at)}
          </div>
          <div className="text-base font-semibold tabular-nums text-foreground">
            {fmtBRL(shownTotal)}
          </div>
        </div>
        <Button
          type="button"
          size="sm"
          variant="outline"
          className="h-8 gap-1.5"
          disabled={sending}
          onClick={onPedirAprovacao}
          title="Revise o orçamento e reenvie o link de aprovação"
        >
          {spinnerOr(<MessageCircle size={14} aria-hidden />)}
          Revisar e reenviar
        </Button>
      </Inline>
    );
  }

  // none — barra padrão "Total recomendado · cliente" + CTA Pedir aprovação.
  return (
    <Inline justify="between" gap={3} className="pt-1">
      <div>
        <div className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
          Total recomendado · cliente
        </div>
        <div className="text-base font-semibold tabular-nums text-foreground">
          {fmtBRL(shownTotal)}
        </div>
      </div>
      <Button
        type="button"
        size="sm"
        className="h-8 gap-1.5"
        disabled={sending || itemsCount === 0}
        onClick={onPedirAprovacao}
        title="Envia o orçamento da vistoria por WhatsApp (link + PIN)"
      >
        {spinnerOr(<MessageCircle size={14} aria-hidden />)}
        Pedir aprovação
      </Button>
    </Inline>
  );
}

function Pill({
  tone,
  count,
  label,
}: {
  tone: 'success' | 'warning' | 'destructive';
  count: number;
  label: string;
}) {
  const cls: Record<typeof tone, string> = {
    success: 'bg-success/10 text-success-foreground',
    warning: 'bg-warning/10 text-warning-foreground',
    destructive: 'bg-destructive/10 text-destructive',
  };
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] ${cls[tone]}`}
    >
      <b className="tabular-nums font-semibold">{count}</b>
      {label}
    </span>
  );
}
