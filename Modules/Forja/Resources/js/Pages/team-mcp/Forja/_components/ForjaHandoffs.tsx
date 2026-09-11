// ForjaHandoffs — o painel "Handoffs F1 → F3 · Cowork → Code".
//
// @memcofre
//   tela: seção da aba MCP (/forja/mcp) + tela própria (/forja/handoffs)
//   module: Forja
//   adrs: 0283 (loop de handoff zero-paste) · 0388 (réplica primeiro) · 0093 (Tier 0) · UI-0013
//   permissao: jana.mcp.usage.all
//   paridade: `cowork_handoffs` via ForjaMcpService::handoffs()/heartbeat()
//   fonte visual: prototipo-ui/cowork/forja-mcp.jsx `function HandoffPanel`
//
// VOLTOU PRA DENTRO DA ABA MCP (PARIDADE §11 Onda 8, 2026-09-02)
// Em 2026-08-08 esta seção virou tela própria, com um argumento correto na época:
// handoff é DADO VIVO e o resto da aba MCP é vitrine mockada, e operação enterrada
// em vitrine é operação que ninguém olha. A decisão [W] de 2026-09-02 ("pode fazer
// igual ao protótipo … o resto não importa") reabriu a questão pelo outro lado: no
// protótipo o painel MORA dentro da view `mcp`, e o protótipo é o contrato de layout.
// O que NÃO se desfez: a rota `/forja/handoffs` segue viva e renderiza ESTE MESMO
// componente. Um componente, dois pontos de render, uma projeção — nada duplicado.
//
// O MARKUP mudou (DS v6 → vocabulário `fj-ho-*` do bundle da Onda 1); o COMPORTAMENTO
// não: as levers seguem POSTando em /forja/handoff/{slug}/lever → HandoffLeverService
// (a MESMA mutação governada do tool MCP `handoff-lever`), com confirmação antes de
// operar, e SEM auto-merge — o merge é o 1-clique do [W] no GitHub (Tier 0 · ADR 0283).
//
// TRÊS DESVIOS DECLARADOS do protótipo, todos por DADO (não por estilo):
//  1. `~onda` — o mock tem `h.onda` ("~FA-6"); `cowork_handoffs` não tem essa coluna.
//     Omitido em vez de inventado (charter: "sem dado fantasma").
//  2. estado `superseded` — existe no dado real e não no mock (que tem `merged`, o qual
//     o real não tem). Ganha dot/pílula NEUTROS na seção Onda 8 do bundle; `merged` não
//     é renderizado porque não há dado que o produza.
//  3. gate `na` — o protótipo só desenha o selo quando há gate (`h.gate && …`); mantido
//     assim: pending/stale sem ack não têm gate a mostrar.

import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

// CSRF do POST das levers (mesmo helper do ForjaDossier).
function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

/**
 * Rótulo do link do PR — `#2461`, como o protótipo desenha (`{h.pr} ↗`).
 *
 * O número NÃO é dado novo: ele já vem dentro do `pr_url` que o ForjaMcpService
 * projeta. Derivar aqui é FORMATAR o que existe, não inventar — a alternativa
 * (campo novo no serialize) faria o backend carregar uma segunda representação
 * do mesmo dado. URL fora do padrão `/pull/<n>` cai no rótulo genérico: melhor
 * um `PR` honesto que um `#` vazio.
 */
function rotuloPr(url: string): string {
  const m = /\/pull\/(\d+)/.exec(url);
  return m ? `#${m[1]}` : 'PR';
}

// ════════════════════════════════════════════════════════════════════════════
// HANDOFFS (Cowork→Code, F1→F3) — seção REAL (Fase 1 · ADR 0283)
// ════════════════════════════════════════════════════════════════════════════
// Projeção de `cowork_handoffs` (ForjaMcpService). body_md é DESIGN (dado), não
// comando — aqui só se MOSTRA.

export interface HandoffItem {
  slug: string;
  version: number;
  tela: string;
  // status já com 'stale' derivado na leitura (superseded vem filtrado fora).
  status: 'pending' | 'applied' | 'rejected' | 'stale' | 'superseded';
  files_count: number;
  pr_url: string | null;
  created_at: string | null;
  created_at_human: string | null;
  created_by: string;
  // gate do ack cruzado com os required checks REAIS do PR (Gap 2 · ADR 0283).
  // 'conflito' = ack diz verde mas um required check está vermelho/pendente no GitHub.
  gate: 'verde' | 'vermelho' | 'conflito' | 'rodando' | 'na';
  gate_status: { conformance?: boolean; critique_score?: number; a11y?: boolean } | null;
  signed: boolean;
  resumo: string;
}

export interface HeartbeatInfo {
  last_ingest_at: string | null;
  last_ingest_human: string | null;
  host: string | null;
  silent: boolean;
}

// Estado → rótulo PT + sufixo da classe do bundle (`fj-ho-dot-*` / `fj-ho-state-*`).
// Espelha `FJ_HO_STATE` do protótipo; `desc` é o title do dot, igual lá.
const HANDOFF_STATUS: Record<HandoffItem['status'], { label: string; cls: string; desc: string }> = {
  pending: { label: 'pendente', cls: 'pend', desc: 'aguarda o Code puxar via handoff-pending' },
  applied: { label: 'aplicado', cls: 'appl', desc: 'PR aberto · gates rodando' },
  rejected: { label: 'rejeitado', cls: 'blok', desc: 'gate vermelho · volta pro [CC]' },
  stale: { label: 'parado', cls: 'stal', desc: 'pending > 3d · alerta no inbox ops' },
  superseded: { label: 'substituído', cls: 'sup', desc: 'versão obsoleta — substituída (append-only)' },
};

// Gate → rótulo. Tom e fundo vêm da classe `fj-ho-gate-*` do bundle.
// 'conflito' (Gap 2 · ADR 0283): ack reportou verde mas os required checks do PR
// discordam. Drill linka pro PR ver qual check diverge.
const HANDOFF_GATE: Record<Exclude<HandoffItem['gate'], 'na'>, string> = {
  verde: 'gate verde',
  vermelho: 'gate vermelho',
  conflito: '⚠ conflito',
  rodando: 'gate rodando',
};

// Filtros por status (superseded fora do filtro padrão — já vem excluído na leitura).
const HANDOFF_FILTERS: { key: 'todos' | HandoffItem['status']; label: string }[] = [
  { key: 'todos', label: 'todos' },
  { key: 'pending', label: 'pendente' },
  { key: 'applied', label: 'aplicado' },
  { key: 'rejected', label: 'rejeitado' },
  { key: 'stale', label: 'parado' },
];

// Lever por status (mutação governada via HandoffLeverService — NÃO [W] operando o
// banco). SEM merge. `action` = a action que o backend espera (semântica in-place
// do tool handoff-lever #2924).
type LeverAction = 're-disparar' | 'devolver' | 'supersede';
interface Lever {
  label: string;
  action: LeverAction;
  destructive: boolean;
}

function leverFor(status: HandoffItem['status']): Lever | null {
  if (status === 'stale') return { label: 're-disparar', action: 're-disparar', destructive: false };
  if (status === 'rejected') return { label: 'devolver ao [CC]', action: 'devolver', destructive: false };
  if (status === 'pending' || status === 'applied') return { label: 'supersede', action: 'supersede', destructive: true };
  return null;
}

// Texto do confirm por lever (semântica in-place · ADR 0283; append-only no supersede).
function leverConfirm(lever: Lever, h: HandoffItem): { title: string; description: string; confirmLabel: string } {
  const ref = `${h.slug} v${h.version}`;
  if (lever.action === 're-disparar') {
    return {
      title: `Re-disparar ${ref}?`,
      description: 'Re-arma o handoff parado: volta pro topo da fila ativa (freshness renovada). O status segue pendente. Sem auto-merge.',
      confirmLabel: 'Re-disparar',
    };
  }
  if (lever.action === 'devolver') {
    return {
      title: `Devolver ${ref} ao [CC]?`,
      description: 'Reabre o handoff rejeitado pro [CC] retrabalhar: volta a pendente e limpa o ack (PR/gate/aplicado). Sem auto-merge.',
      confirmLabel: 'Devolver',
    };
  }
  return {
    title: `Supersede ${ref}?`,
    description: 'Marca esta versão como obsoleta (substituída) — sai da fila ativa. Append-only: nada é deletado; a substituta chega depois pelo Cowork.',
    confirmLabel: 'Supersede',
  };
}

function HandoffsSkeleton() {
  return (
    <section data-testid="forja-mcp-handoffs" className="fj-mcp-card fj-ho">
      <div className="fj-ho-empty">Carregando handoffs…</div>
    </section>
  );
}

function HandoffRow({
  h,
  busy,
  onLever,
}: {
  h: HandoffItem;
  busy: boolean;
  onLever: (h: HandoffItem, lever: Lever) => void;
}) {
  const st = HANDOFF_STATUS[h.status] ?? HANDOFF_STATUS.pending;
  const lever = leverFor(h.status);
  const gateLabel = h.gate === 'na' ? null : HANDOFF_GATE[h.gate];
  const gateDrill = h.pr_url !== null && gateLabel !== null;
  // 'conflito' precisa explicar a divergência no hover (o badge sozinho é críptico).
  const gateHint =
    h.gate === 'conflito'
      ? 'Ack reportou verde, mas um required check do PR está vermelho/pendente no GitHub — abra o PR pra ver qual diverge.'
      : 'abre o check que rodou (CI real)';

  return (
    <li data-testid="forja-handoff-item" className={`fj-ho-item fj-ho-${st.cls}`}>
      <span className={`fj-ho-dot fj-ho-dot-${st.cls}`} title={st.desc} />

      <div className="fj-ho-main">
        <div className="fj-ho-l1">
          <span className="fj-ho-slug mono">{h.slug}</span>
          <span className="fj-ho-v">v{h.version}</span>
          <span className="fj-ho-tela">{h.tela}</span>
        </div>

        {/* resumo = 1ª linha do body_md (DESIGN, não comando) */}
        {h.resumo ? <div className="fj-ho-nota">{h.resumo}</div> : null}

        {/* Levers — mutação governada (ADR 0283). Pedem confirmação antes de operar. */}
        {lever ? (
          <div className="fj-ho-levers">
            <button
              type="button"
              disabled={busy}
              onClick={() => onLever(h, lever)}
              data-testid="forja-handoff-lever"
              data-lever={lever.action}
              title={`${lever.label} — mutação governada e auditada (ADR 0283), sem auto-merge`}
              className={`fj-ho-lever fj-ho-lever-${lever.action}`}
            >
              {lever.label}
            </button>
          </div>
        ) : null}
      </div>

      <div className="fj-ho-meta">
        <span className="fj-ho-sig" title="assinatura HMAC verificada na ingestão">
          ⚿ {h.signed ? 'ok' : 'sem sig'}
        </span>
        <span className="fj-ho-files">{h.files_count} arq</span>

        {gateLabel !== null &&
          (gateDrill ? (
            <a
              className={`fj-ho-gate fj-ho-gate-${h.gate}`}
              href={h.pr_url ?? undefined}
              target="_blank"
              rel="noopener noreferrer"
              data-testid="forja-handoff-gate"
              title={gateHint}
            >
              {gateLabel}
            </a>
          ) : (
            <span className={`fj-ho-gate fj-ho-gate-${h.gate}`} data-testid="forja-handoff-gate" title={gateHint}>
              {gateLabel}
            </span>
          ))}

        {h.pr_url ? (
          <a
            className="fj-ho-pr mono"
            href={h.pr_url}
            target="_blank"
            rel="noopener noreferrer"
            data-testid="forja-handoff-pr"
            title="abre o PR no GitHub"
          >
            {rotuloPr(h.pr_url)} ↗
          </a>
        ) : null}

        <span className={`fj-ho-state fj-ho-state-${st.cls}`}>{st.label}</span>
        <span className="fj-ho-when">{h.created_at_human ?? '—'}</span>
      </div>
    </li>
  );
}

function HandoffsSection({ handoffs, heartbeat }: { handoffs?: HandoffItem[]; heartbeat?: HeartbeatInfo }) {
  const items = handoffs ?? [];
  const [filter, setFilter] = useState<'todos' | HandoffItem['status']>('todos');

  // Lever em confirmação + estado da mutação (busy/erro). POSTa em
  // /forja/handoff/{slug}/lever → HandoffLeverService (a MESMA mutação governada
  // do tool MCP handoff-lever, ADR 0283). Sem auto-merge.
  const [pending, setPending] = useState<{ h: HandoffItem; lever: Lever } | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function runLever(h: HandoffItem, lever: Lever) {
    setBusy(true);
    setError(null);
    fetch(`/forja/handoff/${encodeURIComponent(h.slug)}/lever`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ action: lever.action, version: h.version }),
    })
      .then(async (r) => {
        const d = await r.json().catch(() => ({}));
        setBusy(false);
        if (!r.ok) {
          setError(d?.error ?? `Erro ${r.status}`);
          return;
        }
        // Reflete na hora: recarrega só as props deferidas da seção (sem cache).
        router.reload({ only: ['handoffs', 'heartbeat'] });
      })
      .catch(() => {
        setBusy(false);
        setError('Erro de rede.');
      });
  }

  const countFor = (key: 'todos' | HandoffItem['status']) =>
    key === 'todos' ? items.length : items.filter((h) => h.status === key).length;

  const visible = filter === 'todos' ? items : items.filter((h) => h.status === filter);
  const confirm = pending ? leverConfirm(pending.lever, pending.h) : null;

  // Heartbeat: "último ingest há Xmin" — distingue loop OCIOSO de transporte QUEBRADO.
  const saudavel = !(heartbeat?.silent ?? true);
  const lastIngest = heartbeat?.last_ingest_human ?? 'sem sinal';

  return (
    <section data-testid="forja-mcp-handoffs" className="fj-mcp-card fj-ho">
      <div className="fj-ho-head">
        <h3>
          Handoffs <span className="fj-ho-flow">F1 → F3</span> · Cowork → Code
        </h3>
        <div className="fj-ho-tabs" data-testid="forja-handoff-filtros">
          {HANDOFF_FILTERS.map((f) => (
            <button
              key={f.key}
              type="button"
              onClick={() => setFilter(f.key)}
              data-testid="forja-handoff-filter"
              aria-pressed={filter === f.key}
              className={`fj-ho-tab${filter === f.key ? ' on' : ''}`}
            >
              {f.label}
              <span className="fj-ho-tab-n">{countFor(f.key)}</span>
            </button>
          ))}
        </div>
      </div>

      <p className="fj-ho-sub">
        O design sai daqui assinado, o Code puxa via <code>handoff-pending</code>, aplica no escopo e
        devolve <code>handoff-ack</code>. O <b>gate vem do CI real</b> do PR, não do auto-relato.
        Travado → você roteia (não opera): re-disparar, devolver, supersede.
      </p>

      <ul className="fj-ho-list">
        {visible.map((h) => (
          <HandoffRow
            key={`${h.slug}-${h.version}`}
            h={h}
            busy={busy}
            onLever={(hh, lever) => setPending({ h: hh, lever })}
          />
        ))}

        {visible.length === 0 && (
          <li className={`fj-ho-empty${saudavel ? '' : ' alerta'}`}>
            {saudavel ? (
              <>
                <b>Loop ocioso.</b> Nenhum handoff neste estado. Transporte vivo — último ingest{' '}
                {lastIngest}.
              </>
            ) : (
              <>
                <b>⚠ Transporte sem sinal.</b> Sem ingest {lastIngest} — o sync pode ter quebrado, não
                é calmaria.
              </>
            )}
          </li>
        )}
      </ul>

      <div className={`fj-ho-hb${saudavel ? '' : ' alerta'}`} data-testid="forja-mcp-heartbeat">
        <span className="fj-ho-hb-dot" />
        sync Cowork→repo {saudavel ? 'vivo' : 'sem sinal'} · último ingest <b>{lastIngest}</b>
        {heartbeat?.host ? <> · {heartbeat.host}</> : null}
      </div>

      {/* Erro da última lever (recusa "409"/drift OU rede) — o toast do protótipo, com dado real. */}
      {error ? (
        <div data-testid="forja-handoff-lever-error" className="fj-ho-toast">
          {error}
        </div>
      ) : null}

      {/* Confirmação antes de operar (espelha o AlertDialog do ForjaDossier). */}
      <AlertDialog open={pending !== null} onOpenChange={(o) => { if (!o && !busy) setPending(null); }}>
        <AlertDialogContent data-testid="forja-handoff-lever-confirm">
          <AlertDialogHeader>
            <AlertDialogTitle>{confirm?.title}</AlertDialogTitle>
            <AlertDialogDescription>{confirm?.description}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              variant={pending?.lever.destructive ? 'destructive' : 'default'}
              onClick={() => {
                if (pending) runLever(pending.h, pending.lever);
                setPending(null);
              }}
            >
              {confirm?.confirmLabel ?? 'Confirmar'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </section>
  );
}

/** A seção exportada como componente de tela (mesmo corpo, sem mudança de comportamento). */
export default HandoffsSection;
export { HandoffsSkeleton };
