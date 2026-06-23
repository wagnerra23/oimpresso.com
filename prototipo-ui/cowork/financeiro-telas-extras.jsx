// Financeiro — Telas extras: Fluxo, Conciliação, DRE, Plano de contas.
// All in compact density to match the unified screen aesthetic.
// Tokens: BRIEFING §4 (emerald/amber/rose, rounded-md, shadow-sm, num tabular).
// IIFE para não vazar `const I`/helpers no escopo global.
(() => {
const { useState, useMemo } = React;
const I = window.FIN_I; // ícones do módulo Financeiro

const fmtBRL2 = (n) =>
  n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const fmtBRL2k = (n) => {
  const abs = Math.abs(n);
  if (abs >= 1000) return (n < 0 ? "− " : "") + "R$ " + (abs / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "k";
  return fmtBRL2(n);
};
const fmtDate2 = (d) => d.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit" });
const fmtDateLong2 = (d) => d.toLocaleDateString("pt-BR", { day: "2-digit", month: "short", year: "numeric" });

// ── Breadcrumb compartilhado pelas sub-rotas (Refino 2026-05-18)
const FinBcrumb = ({ here, onBack }) => (
  <nav className="fin-bcrumb" aria-label="Voltar para Financeiro">
    <button className="fin-bcrumb-back" onClick={onBack} title="Voltar pra visão unificada (esc)">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><polyline points="10,3 5,8 10,13"/></svg>
      Financeiro
    </button>
    <span className="fin-bcrumb-sep">›</span>
    <span className="fin-bcrumb-here">{here}</span>
  </nav>
);

// Esc volta — só ativo quando há onBack
const useFinBackEsc = (onBack) => {
  useEffect(() => {
    if (!onBack) return;
    const onKey = (e) => {
      if (e.key !== "Escape") return;
      if (document.querySelector(".os-drawer-back, .fin-cmdk-back, .fin-cmdk-overlay")) return;
      e.preventDefault();
      onBack();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onBack]);
};

/* ═════════════════════════════════════════════════════════════════════════
 * 1. FLUXO DE CAIXA — projeção dia-a-dia
 * ═════════════════════════════════════════════════════════════════════════ */
const TelaFluxo = () => {
  const rows = window.FIN_ROWS;
  const today = window.FIN_TODAY;

  // Build day-by-day projection for next 30 days
  const days = useMemo(() => {
    const out = [];
    let saldo = rows.filter((r) => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0)
              - rows.filter((r) => r.kind === "payable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    for (let i = -2; i < 35; i++) {
      const date = new Date(today);
      date.setDate(date.getDate() + i);
      const key = date.toISOString().slice(0, 10);
      const dayRows = rows.filter((r) => r.due.toISOString().slice(0, 10) === key && !r.paid_at);
      const inSum = dayRows.filter((r) => r.kind === "receivable").reduce((s, r) => s + r.amount, 0);
      const outSum = dayRows.filter((r) => r.kind === "payable").reduce((s, r) => s + r.amount, 0);
      const net = inSum - outSum;
      if (i >= 0) saldo += net; // historical doesn't change saldo (already reflected in paid_at)
      out.push({ date, dayRows, inSum, outSum, net, saldo, isToday: i === 0, isPast: i < 0 });
    }
    return out;
  }, [rows, today]);

  const saldoHoje = days.find((d) => d.isToday)?.saldo ?? 0;
  const proj30 = days[days.length - 1].saldo;
  const minDay = days.filter((d) => !d.isPast).reduce((m, d) => d.saldo < m.saldo ? d : m, days[0]);
  const maxSaldo = Math.max(...days.map((d) => d.saldo));
  const minSaldo = Math.min(...days.map((d) => d.saldo), 0);

  return (
    <>
      <div className="px-6 pt-4">
        <div className="fin-card flex divide-x divide-[var(--border)] overflow-hidden">
          <div className="flex-1 px-5 py-4 fin-ink">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest font-medium text-[var(--text-3)]">Saldo hoje · 09 mai</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num">{fmtBRL2(saldoHoje)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-3)]">Itaú PJ · ag 0438 cc 4521-7</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Projeção 30 dias</div>
            <div className={`mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num ${proj30 >= saldoHoje ? "text-[var(--pos)]" : "text-[var(--neg)]"}`}>{fmtBRL2(proj30)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">{proj30 >= saldoHoje ? "alta" : "queda"} de {fmtBRL2k(Math.abs(proj30 - saldoHoje))} vs hoje</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Pior dia previsto</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num text-[var(--warn)]">{fmtBRL2(minDay.saldo)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">{fmtDateLong2(minDay.date)}</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Margem mínima</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num text-[var(--text)]">R$ 5.000</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">limite definido · acima ✓</div>
          </div>
        </div>
      </div>

      {/* Chart */}
      <div className="px-6 mt-4">
        <div className="fin-card p-5">
          <div className="flex items-center justify-between mb-3">
            <div>
              <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Saldo projetado · próximos 35 dias</div>
              <div className="text-[length:var(--fs-4)] font-semibold mt-0.5">linha laranja = limite mínimo · barras = movimento líquido do dia</div>
            </div>
            <div className="flex items-center gap-3 text-[length:var(--fs-2)] text-[var(--text-2)]">
              <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-[var(--accent)]" /> saldo</span>
              <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-[var(--pos)]" /> entrada</span>
              <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-[var(--neg)]" /> saída</span>
            </div>
          </div>

          {/* Chart area */}
          <div className="relative h-[220px] border-b border-[var(--border)] mt-2">
            {/* Limit line */}
            <div className="absolute left-0 right-0 border-t border-dashed border-[var(--warn)]" style={{ top: `${(1 - (5000 - minSaldo) / (maxSaldo - minSaldo)) * 100}%` }}>
              <span className="absolute -top-4 right-0 text-[length:var(--fs-1)] text-[var(--warn)] font-medium bg-[var(--surface)] px-1">R$ 5k mínimo</span>
            </div>
            {/* Bars */}
            <div className="absolute inset-0 flex items-end gap-px">
              {days.map((d, i) => {
                const h = ((d.saldo - minSaldo) / (maxSaldo - minSaldo)) * 100;
                const moveBar = Math.max(2, Math.abs(d.net) / Math.max(...days.map((x) => Math.abs(x.net) || 1), 1) * 50);
                return (
                  <div key={i} className="flex-1 h-full flex flex-col justify-end relative group">
                    {/* saldo line column */}
                    <div className={`w-full ${d.isPast ? "bg-[var(--border)]" : d.isToday ? "bg-[var(--accent)]" : "bg-[var(--text-3)]"} ${d.saldo < 5000 ? "!bg-[var(--warn)]" : ""}`} style={{ height: `${h}%` }}>
                    </div>
                    {/* hover info */}
                    {d.dayRows.length > 0 && (
                      <div className="hidden group-hover:block absolute -top-14 left-1/2 -translate-x-1/2 z-10 fin-ink text-[length:var(--fs-1)] rounded px-2 py-1 whitespace-nowrap num">
                        {fmtDate2(d.date)} · {fmtBRL2k(d.saldo)}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Date axis */}
          <div className="flex gap-px mt-1.5 text-[length:var(--fs-1)] text-[var(--text-2)] num">
            {days.map((d, i) => (
              <div key={i} className={`flex-1 text-center ${d.isToday ? "font-bold text-[var(--text)]" : ""}`}>
                {i % 5 === 0 || d.isToday ? fmtDate2(d.date) : ""}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Upcoming events */}
      <div className="px-6 mt-4">
        <div className="fin-card overflow-hidden">
          <div className="px-5 py-3 border-b border-[var(--border)] flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium whitespace-nowrap">Próximos eventos</div>
              <div className="text-[length:var(--fs-4)] font-semibold mt-0.5 whitespace-nowrap">7 dias adiante</div>
            </div>
            <div className="ml-auto text-[length:var(--fs-2)] text-[var(--text-2)] whitespace-nowrap shrink-0">{days.filter((d) => !d.isPast && d.dayRows.length > 0).slice(0, 7).reduce((s, d) => s + d.dayRows.length, 0)} lançamentos</div>
          </div>
          <table className="w-full text-[length:var(--fs-3)] num">
            <tbody>
              {days.filter((d) => !d.isPast && d.dayRows.length > 0).slice(0, 7).flatMap((d) =>
                d.dayRows.map((r, j) => (
                  <tr key={r.id} className={`border-b border-[var(--hairline)] row-hover ${j === 0 ? "border-t-2 border-t-[var(--hairline)]" : ""}`}>
                    <td className="pl-6 pr-3 py-2 w-[110px] text-[var(--text)]">{j === 0 ? fmtDateLong2(d.date) : ""}</td>
                    <td className="px-2 py-2">
                      <span className={`inline-grid place-items-center rounded ${r.kind === "receivable" ? "bg-[var(--pos-soft)] text-[var(--pos)]" : "bg-[var(--neg-soft)] text-[var(--neg)]"}`} style={{ width: 22, height: 22 }}>
                        {r.kind === "receivable" ? "↓" : "↑"}
                      </span>
                    </td>
                    <td className="px-2 py-2 font-medium text-[var(--text)] truncate">{r.desc}</td>
                    <td className="px-2 py-2 text-[var(--text-2)]">{r.party}</td>
                    <td className="px-2 py-2 text-[var(--text-2)]">{r.category}</td>
                    <td className="pr-6 py-2 text-right font-medium">
                      <span className={r.kind === "receivable" ? "text-[var(--pos)]" : "text-[var(--text)]"}>
                        {r.kind === "receivable" ? "+" : "−"} {fmtBRL2(r.amount)}
                      </span>
                    </td>
                    <td className="pr-6 py-2 text-right text-[var(--text)] font-medium w-[120px]">
                      {j === d.dayRows.length - 1 ? fmtBRL2(d.saldo) : ""}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
};

/* ═════════════════════════════════════════════════════════════════════════
 * 2. CONCILIAÇÃO — extrato OFX × lançamentos sistema
 * ═════════════════════════════════════════════════════════════════════════ */
const EXTRATO = [
  { id: "OFX-04388", date: "2026-05-02", desc: "TED IMOBILIARIA CENTRO LTDA", amount: -4500.00, match: "P-1884" },
  { id: "OFX-04389", date: "2026-05-03", desc: "DEB AUT VIVO EMPRESAS", amount: -320.00, match: "P-1885" },
  { id: "OFX-04390", date: "2026-05-04", desc: "PIX RECEBIDO STUDIO FOCO", amount: 380.00, match: "R-2647" },
  { id: "OFX-04391", date: "2026-05-05", desc: "TED FOLHA LARISSA", amount: -2800.00, match: "P-1890" },
  { id: "OFX-04392", date: "2026-05-05", desc: "PIX ALPHAGRAF", amount: -1120.00, match: "P-1888" },
  { id: "OFX-04393", date: "2026-05-06", desc: "PIX FARMACIA SAUDE TOTAL", amount: 720.00, match: "R-2645" },
  { id: "OFX-04394", date: "2026-05-07", desc: "PIX CLINICA VIDA PLENA LTDA", amount: 880.00, match: "R-2651" },
  { id: "OFX-04395", date: "2026-05-08", desc: "PIX PADARIA PAO QUENTE", amount: 480.00, match: null, suggest: "R-2641" },
  { id: "OFX-04396", date: "2026-05-08", desc: "TARIFA CESTA CONTA PJ", amount: -89.90, match: null, suggest: null },
  { id: "OFX-04397", date: "2026-05-09", desc: "PIX MARIA APARECIDA", amount: 120.00, match: null, suggest: "R-2652" },
];

const TelaConciliacao = ({ onBack }) => {
  useFinBackEsc(onBack);
  const [selected, setSelected] = useState(null);
  const matched = EXTRATO.filter((e) => e.match).length;
  const pending = EXTRATO.filter((e) => !e.match).length;
  const totalIn = EXTRATO.filter((e) => e.amount > 0).reduce((s, e) => s + e.amount, 0);
  const totalOut = EXTRATO.filter((e) => e.amount < 0).reduce((s, e) => s + e.amount, 0);

  return (
    <>
      <div className="px-6 pt-4">
        <div className="fin-card flex divide-x divide-[var(--border)] overflow-hidden">
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Período</div>
            <div className="mt-1 text-[length:var(--fs-5)] font-semibold tracking-tight">02 → 09 mai 2026</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">Itaú PJ · OFX importado 09/05 14:32</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Conciliados</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num text-[var(--pos)]">{matched}<span className="text-[var(--text-3)] text-[length:var(--fs-6)]">/{EXTRATO.length}</span></div>
            <div className="mt-2">
              <div className="h-1 bg-[var(--sunken)] rounded-full overflow-hidden">
                <div className="h-full bg-[var(--pos-soft)]0" style={{ width: `${(matched / EXTRATO.length) * 100}%` }} />
              </div>
            </div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Pendente revisão</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num text-[var(--warn)]">{pending}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">com sugestão automática: {EXTRATO.filter((e) => !e.match && e.suggest).length}</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Total no extrato</div>
            <div className="mt-1 text-[length:var(--fs-6)] leading-tight font-semibold tracking-tight num text-[var(--pos)]">+ {fmtBRL2(totalIn)}</div>
            <div className="text-[length:var(--fs-6)] leading-tight font-semibold tracking-tight num text-[var(--neg)]">{fmtBRL2(totalOut)}</div>
          </div>
        </div>
      </div>

      <div className="px-6 mt-4 mb-4">
        <div className="fin-card overflow-hidden">
          <div className="grid grid-cols-2 border-b border-[var(--border)] text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">
            <div className="px-5 py-2.5 border-r border-[var(--border)] flex items-center gap-2">
              Extrato Itaú PJ
              <span className="text-[var(--text-3)] normal-case tracking-normal text-[length:var(--fs-2)]">· {EXTRATO.length} lançamentos</span>
            </div>
            <div className="px-5 py-2.5 flex items-center gap-2">
              Sistema oimpresso
              <span className="text-[var(--text-3)] normal-case tracking-normal text-[length:var(--fs-2)]">· match sugerido</span>
            </div>
          </div>

          {EXTRATO.map((e) => {
            const sysRow = window.FIN_ROWS.find((r) => r.id === (e.match || e.suggest));
            const status = e.match ? "matched" : e.suggest ? "suggest" : "none";
            return (
              <div key={e.id} className={`grid grid-cols-2 border-b border-[var(--hairline)] text-[length:var(--fs-3)] num ${selected === e.id ? "bg-[var(--sunken)]" : "row-hover"}`} onClick={() => setSelected(e.id)}>
                {/* Left: extrato */}
                <div className="px-5 py-3 border-r border-[var(--border)] flex items-center gap-3 cursor-pointer">
                  <div className="text-[var(--text)] w-[60px]">{fmtDate2(new Date(e.date + "T12:00:00"))}</div>
                  <div className="flex-1 min-w-0">
                    <div className="font-medium text-[var(--text)] truncate">{e.desc}</div>
                    <div className="text-[length:var(--fs-1)] text-[var(--text-3)] font-mono">{e.id}</div>
                  </div>
                  <div className={`font-semibold ${e.amount > 0 ? "text-[var(--pos)]" : "text-[var(--text)]"}`}>
                    {e.amount > 0 ? "+" : "−"} {fmtBRL2(Math.abs(e.amount)).replace("R$", "").trim()}
                  </div>
                </div>

                {/* Right: sistema */}
                <div className="px-5 py-3 flex items-center gap-3">
                  {status === "matched" && sysRow && (
                    <>
                      <span className="inline-flex items-center gap-1 text-[var(--pos)] text-[length:var(--fs-2)] font-medium px-2 py-0.5 rounded-full bg-[var(--pos-soft)] whitespace-nowrap">
                        <span className="w-1.5 h-1.5 rounded-full bg-[var(--pos)]" />
                        Conciliado
                      </span>
                      <div className="flex-1 min-w-0">
                        <div className="font-medium text-[var(--text)] truncate">{sysRow.desc}</div>
                        <div className="text-[length:var(--fs-1)] text-[var(--text-3)] font-mono">{sysRow.id} · {sysRow.invoice}</div>
                      </div>
                      <button className="fin-iconbtn" title="Desfazer conciliação"><I.X size={14}/></button>
                    </>
                  )}
                  {status === "suggest" && sysRow && (
                    <>
                      <span className="inline-flex items-center gap-1 text-[var(--warn)] text-[length:var(--fs-2)] font-medium px-2 py-0.5 rounded-full bg-[var(--warn-soft)] whitespace-nowrap">
                        <span className="w-1.5 h-1.5 rounded-full bg-[var(--warn)]" />
                        Sugerido
                      </span>
                      <div className="flex-1 min-w-0">
                        <div className="font-medium text-[var(--text)] truncate">{sysRow.desc}</div>
                        <div className="text-[length:var(--fs-1)] text-[var(--text-3)] font-mono">{sysRow.id} · {sysRow.invoice} · 95% match</div>
                      </div>
                      <button className="fin-sysbtn fin-sysbtn--accent"><I.Check size={13}/> Aceitar</button>
                    </>
                  )}
                  {status === "none" && (
                    <>
                      <span className="inline-flex items-center gap-1 text-[var(--text-2)] text-[length:var(--fs-2)] font-medium px-2 py-0.5 rounded-full bg-[var(--sunken)] whitespace-nowrap">
                        <span className="w-1.5 h-1.5 rounded-full bg-[var(--text-3)]" />
                        Sem match
                      </span>
                      <div className="flex-1 text-[var(--text-2)] italic">Provável tarifa bancária — criar lançamento?</div>
                      <button className="fin-sysbtn"><I.Plus size={13}/> Criar</button>
                      <button className="fin-sysbtn fin-sysbtn--ghost"><I.Search size={13}/> Buscar</button>
                    </>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </>
  );
};

/* ═════════════════════════════════════════════════════════════════════════
 * 3. DRE / RELATÓRIOS
 * ═════════════════════════════════════════════════════════════════════════ */
const DRE_LINES = [
  { type: "h", label: "Receita operacional bruta", v: 14860, prev: 12340, kind: "rec" },
  { type: "i", label: "Banner / Lona / Adesivo",   v: 9580, prev: 7920, indent: 1 },
  { type: "i", label: "Gráfica rápida",            v: 2940, prev: 2580, indent: 1 },
  { type: "i", label: "Fachada / Placa",           v: 2340, prev: 1840, indent: 1 },
  { type: "h", label: "(−) Deduções",              v: -1260, prev: -980, kind: "ded" },
  { type: "i", label: "Impostos sobre vendas (Simples)", v: -1260, prev: -980, indent: 1 },
  { type: "subtotal", label: "Receita líquida",    v: 13600, prev: 11360 },
  { type: "h", label: "(−) Custos diretos",        v: -5180, prev: -4220, kind: "ded" },
  { type: "i", label: "Insumos (papel, lona, tinta)", v: -3420, prev: -2680, indent: 1 },
  { type: "i", label: "Acabamento terceirizado",  v: -1120, prev: -940, indent: 1 },
  { type: "i", label: "Frete / Instalação",        v: -640, prev: -600, indent: 1 },
  { type: "subtotal", label: "Lucro bruto",        v: 8420, prev: 7140 },
  { type: "h", label: "(−) Despesas operacionais", v: -7042, prev: -6680, kind: "ded" },
  { type: "i", label: "Folha + encargos",          v: -2800, prev: -2800, indent: 1 },
  { type: "i", label: "Aluguel + IPTU",            v: -5390, prev: -5390, indent: 1 },
  { type: "i", label: "Energia / água / internet", v: -1500, prev: -1480, indent: 1 },
  { type: "i", label: "Manutenção equipamentos",  v: -780, prev: -260, indent: 1 },
  { type: "subtotal", label: "Resultado operacional", v: 1378, prev: 460, highlight: true },
];

const TelaDRE = () => {
  const [period, setPeriod] = useState("Maio 2026");
  return (
    <>
      <div className="px-6 pt-4">
        <div className="fin-card overflow-hidden">
          <div className="px-5 py-3 border-b border-[var(--border)] flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium whitespace-nowrap">Demonstração de Resultado</div>
              <div className="text-[length:var(--fs-5)] font-semibold mt-0.5 whitespace-nowrap">{period}</div>
            </div>
            <div className="ml-auto flex items-center gap-2">
              <div className="fin-seg">
                {["Mês", "Trimestre", "Ano", "12m"].map((p) => (
                  <button key={p} className={"fin-seg-btn" + (p === "Mês" ? " on" : "")}
                          disabled={p !== "Mês"} title={p !== "Mês" ? "Em breve" : undefined}>{p}</button>
                ))}
              </div>
              <button className="fin-sysbtn"><I.Download size={13}/> PDF</button>
              <button className="fin-sysbtn">Excel</button>
            </div>
          </div>

          <table className="w-full text-[length:var(--fs-3)] num">
            <thead>
              <tr className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] border-b border-[var(--border)] bg-[var(--sunken)]">
                <th className="pl-6 pr-2 py-2 text-left font-medium">Conta</th>
                <th className="px-2 py-2 text-right font-medium w-[140px]">Mai/2026</th>
                <th className="px-2 py-2 text-right font-medium w-[100px]">% RL</th>
                <th className="px-2 py-2 text-right font-medium w-[140px]">Abr/2026</th>
                <th className="px-2 py-2 text-right font-medium w-[80px]">Δ</th>
                <th className="pl-2 pr-6 py-2 w-[160px]"></th>
              </tr>
            </thead>
            <tbody>
              {DRE_LINES.map((l, i) => {
                const rl = 13600;
                const pct = (l.v / rl) * 100;
                const delta = l.prev !== 0 ? ((l.v - l.prev) / Math.abs(l.prev)) * 100 : 0;
                const positive = l.v >= 0;
                if (l.type === "h") {
                  return (
                    <tr key={i} className="border-b border-[var(--hairline)]">
                      <td className="pl-6 pr-2 py-2 font-medium text-[var(--text)]">{l.label}</td>
                      <td className="px-2 py-2 text-right font-semibold">{fmtBRL2(l.v).replace("R$", "").trim()}</td>
                      <td className="px-2 py-2 text-right text-[var(--text-2)]">{pct.toFixed(1)}%</td>
                      <td className="px-2 py-2 text-right text-[var(--text-2)]">{fmtBRL2(l.prev).replace("R$", "").trim()}</td>
                      <td className={`px-2 py-2 text-right ${delta > 0 ? "text-[var(--pos)]" : delta < 0 ? "text-[var(--neg)]" : "text-[var(--text-3)]"}`}>{delta > 0 ? "+" : ""}{delta.toFixed(0)}%</td>
                      <td className="pl-2 pr-6"></td>
                    </tr>
                  );
                }
                if (l.type === "i") {
                  return (
                    <tr key={i} className="border-b border-[var(--hairline)] row-hover">
                      <td className="pl-6 pr-2 py-1.5 text-[var(--text-2)]" style={{ paddingLeft: 24 + (l.indent || 0) * 16 }}>{l.label}</td>
                      <td className="px-2 py-1.5 text-right text-[var(--text)]">{fmtBRL2(l.v).replace("R$", "").trim()}</td>
                      <td className="px-2 py-1.5 text-right text-[var(--text-3)]">{pct.toFixed(1)}%</td>
                      <td className="px-2 py-1.5 text-right text-[var(--text-3)]">{fmtBRL2(l.prev).replace("R$", "").trim()}</td>
                      <td className={`px-2 py-1.5 text-right text-[length:var(--fs-2)] ${delta > 0 ? "text-[var(--pos)]" : delta < 0 ? "text-[var(--neg)]" : "text-[var(--text-3)]"}`}>{delta > 0 ? "+" : ""}{delta.toFixed(0)}%</td>
                      <td className="pl-2 pr-6 py-1.5">
                        <div className="h-1 bg-[var(--sunken)] rounded-full overflow-hidden">
                          <div className={`h-full ${positive ? "bg-[var(--pos)]" : "bg-[var(--neg)]"}`} style={{ width: `${Math.min(100, Math.abs(pct) * 3)}%` }} />
                        </div>
                      </td>
                    </tr>
                  );
                }
                if (l.type === "subtotal") {
                  return (
                    <tr key={i} className={`border-y-2 border-[var(--border)] ${l.highlight ? "fin-ink" : "bg-[var(--sunken)]"}`}>
                      <td className={`pl-6 pr-2 py-2.5 font-semibold ${l.highlight ? "text-white" : ""}`}>{l.label}</td>
                      <td className={`px-2 py-2.5 text-right font-bold text-[length:var(--fs-4)] ${l.highlight ? "text-white" : positive ? "text-[var(--pos)]" : "text-[var(--neg)]"}`}>{fmtBRL2(l.v).replace("R$", "").trim()}</td>
                      <td className={`px-2 py-2.5 text-right font-medium ${l.highlight ? "text-[var(--text-4)]" : "text-[var(--text-2)]"}`}>{pct.toFixed(1)}%</td>
                      <td className={`px-2 py-2.5 text-right ${l.highlight ? "text-[var(--text-3)]" : "text-[var(--text-2)]"}`}>{fmtBRL2(l.prev).replace("R$", "").trim()}</td>
                      <td className={`px-2 py-2.5 text-right font-semibold ${delta > 0 ? "text-[var(--pos)]" : delta < 0 ? "text-[var(--neg)]" : ""}`}>{delta > 0 ? "+" : ""}{delta.toFixed(0)}%</td>
                      <td className="pl-2 pr-6"></td>
                    </tr>
                  );
                }
                return null;
              })}
            </tbody>
          </table>
        </div>
      </div>

      <div className="px-6 mt-4 mb-4 grid grid-cols-2 gap-4">
        <div className="fin-card p-5">
          <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Margem operacional</div>
          <div className="mt-1 text-[length:var(--fs-8)] font-semibold tracking-tight num">10.1%</div>
          <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">vs <span className="num">4.0%</span> em abr · <span className="text-[var(--pos)] font-medium">+6.1pp</span></div>
          <div className="mt-4 h-2 bg-[var(--sunken)] rounded-full overflow-hidden">
            <div className="h-full bg-[var(--accent)]" style={{ width: "10%" }} />
          </div>
          <div className="mt-1.5 flex justify-between text-[length:var(--fs-1)] text-[var(--text-3)]">
            <span>0%</span><span>meta 12%</span><span>100%</span>
          </div>
        </div>

        <div className="fin-card p-5">
          <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Top categorias receita · maio</div>
          <div className="mt-3 space-y-2.5">
            {[
              { label: "Banner / Lona / Adesivo", v: 9580, pct: 64 },
              { label: "Gráfica rápida", v: 2940, pct: 20 },
              { label: "Fachada / Placa", v: 2340, pct: 16 },
            ].map((c) => (
              <div key={c.label}>
                <div className="flex items-baseline justify-between text-[length:var(--fs-3)]">
                  <span className="text-[var(--text)]">{c.label}</span>
                  <span className="num font-medium">{fmtBRL2(c.v)} <span className="text-[var(--text-3)]">· {c.pct}%</span></span>
                </div>
                <div className="mt-1 h-1 bg-[var(--sunken)] rounded-full overflow-hidden">
                  <div className="h-full bg-[var(--pos-soft)]0" style={{ width: `${c.pct}%` }} />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </>
  );
};

/* ═════════════════════════════════════════════════════════════════════════
 * 4. PLANO DE CONTAS
 * ═════════════════════════════════════════════════════════════════════════ */
const PCONTAS = [
  { code: "1", name: "Receitas", level: 0, type: "rec", saldo: 14860, count: 14, expand: true },
  { code: "1.1", name: "Vendas de produtos", level: 1, type: "rec", saldo: 14260, count: 12 },
  { code: "1.1.01", name: "Banner / Lona", level: 2, type: "rec", saldo: 6720, count: 5 },
  { code: "1.1.02", name: "Adesivo / Envelopamento", level: 2, type: "rec", saldo: 8180, count: 4 },
  { code: "1.1.03", name: "Fachada / ACM", level: 2, type: "rec", saldo: 2770, count: 3 },
  { code: "1.1.04", name: "Gráfica rápida", level: 2, type: "rec", saldo: 1600, count: 4 },
  { code: "1.2", name: "Receitas financeiras", level: 1, type: "rec", saldo: 0, count: 0 },
  { code: "2", name: "Despesas", level: 0, type: "exp", saldo: -13482, count: 13, expand: true },
  { code: "2.1", name: "Custos diretos", level: 1, type: "exp", saldo: -5180, count: 6 },
  { code: "2.1.01", name: "Insumos gráficos", level: 2, type: "exp", saldo: -3420, count: 3 },
  { code: "2.1.02", name: "Acabamento terceirizado", level: 2, type: "exp", saldo: -1120, count: 1 },
  { code: "2.1.03", name: "Frete e instalação", level: 2, type: "exp", saldo: -640, count: 2 },
  { code: "2.2", name: "Despesas administrativas", level: 1, type: "exp", saldo: -6190, count: 5 },
  { code: "2.2.01", name: "Aluguel", level: 2, type: "exp", saldo: -4500, count: 1 },
  { code: "2.2.02", name: "Utilidades (energia/internet)", level: 2, type: "exp", saldo: -1500, count: 2 },
  { code: "2.2.03", name: "Impostos e taxas", level: 2, type: "exp", saldo: -190, count: 2 },
  { code: "2.3", name: "Folha de pagamento", level: 1, type: "exp", saldo: -2800, count: 1 },
  { code: "2.4", name: "Manutenção", level: 1, type: "exp", saldo: -780, count: 1 },
];

const TelaPContas = ({ onBack }) => {
  useFinBackEsc(onBack);
  return (
    <>
      <div className="px-6 pt-4 mb-4">
        <div className="fin-card overflow-hidden">
          <div className="px-5 py-3 border-b border-[var(--border)] flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium whitespace-nowrap">Plano de contas</div>
              <div className="text-[length:var(--fs-5)] font-semibold mt-0.5 whitespace-nowrap">Comunicação Visual · 2 níveis</div>
            </div>
            <div className="ml-auto flex items-center gap-2 shrink-0">
              <input placeholder="Buscar conta…" className="h-8 px-3 w-[160px] rounded-md border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-3)] text-[var(--text)] placeholder:text-[var(--text-3)] focus:border-[var(--text-3)]" />
              <button className="h-8 px-3 rounded-md border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-3)] text-[var(--text-2)] hover:bg-[var(--sunken)] hover:text-[var(--text)] whitespace-nowrap">Importar</button>
              <button className="h-8 px-3 rounded-md bg-[var(--accent)] text-[var(--accent-fg,#fff)] text-[length:var(--fs-3)] hover:bg-[var(--accent-hi)] whitespace-nowrap">+ Nova</button>
            </div>
          </div>

          <table className="w-full text-[length:var(--fs-3)] num">
            <thead>
              <tr className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] border-b border-[var(--border)] bg-[var(--sunken)]">
                <th className="pl-6 pr-2 py-2 text-left font-medium w-[100px]">Código</th>
                <th className="px-2 py-2 text-left font-medium">Conta</th>
                <th className="px-2 py-2 text-left font-medium w-[120px]">Tipo</th>
                <th className="px-2 py-2 text-right font-medium w-[80px]">Lanç. mês</th>
                <th className="px-2 py-2 text-right font-medium w-[140px]">Saldo mês</th>
                <th className="pl-2 pr-6 py-2 w-[80px]"></th>
              </tr>
            </thead>
            <tbody>
              {PCONTAS.map((c) => (
                <tr key={c.code} className={`border-b border-[var(--hairline)] row-hover ${c.level === 0 ? "bg-[var(--sunken)]" : ""}`}>
                  <td className="pl-6 pr-2 py-2 text-[var(--text-2)] font-mono text-[length:var(--fs-2)]">{c.code}</td>
                  <td className="px-2 py-2" style={{ paddingLeft: 12 + c.level * 18 }}>
                    <span className={`${c.level === 0 ? "font-semibold text-[var(--text)]" : c.level === 1 ? "font-medium text-[var(--text)]" : "text-[var(--text-2)]"}`}>
                      {c.level > 0 && <span className="text-[var(--text-4)] mr-1.5">└</span>}
                      {c.name}
                    </span>
                  </td>
                  <td className="px-2 py-2">
                    <span className={`inline-flex items-center gap-1 text-[length:var(--fs-2)] font-medium px-2 py-0.5 rounded-full ${
                      c.type === "rec" ? "bg-[var(--pos-soft)] text-[var(--pos)]" : "bg-[var(--neg-soft)] text-[var(--neg)]"
                    }`}>
                      <span className={`w-1.5 h-1.5 rounded-full ${c.type === "rec" ? "bg-[var(--pos-soft)]0" : "bg-[var(--neg-soft)]0"}`} />
                      {c.type === "rec" ? "Receita" : "Despesa"}
                    </span>
                  </td>
                  <td className="px-2 py-2 text-right text-[var(--text-2)]">{c.count > 0 ? c.count : <span className="text-[var(--text-4)]">—</span>}</td>
                  <td className={`px-2 py-2 text-right font-medium ${
                    c.saldo === 0 ? "text-[var(--text-4)]" :
                    c.saldo > 0 ? "text-[var(--pos)]" : "text-[var(--text)]"
                  }`}>
                    {c.saldo === 0 ? "—" : <>
                      <span className="text-[var(--text-3)] mr-0.5">{c.saldo > 0 ? "+" : "−"}</span>
                      {fmtBRL2(Math.abs(c.saldo)).replace("R$", "").trim()}
                    </>}
                  </td>
                  <td className="pl-2 pr-6 py-2 text-right">
                    <button className="text-[length:var(--fs-2)] text-[var(--text-2)] hover:text-[var(--text)] underline-offset-2 hover:underline">editar</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
};

/* ═════════════════════════════════════════════════════════════
 * 5. IMPOSTOS & OBRIGAÇÕES — fiscal na visão financeira (onda W2 · 2026-06-10)
 * Gap apontado na reavaliação 06-09: "impostos-a-recolher + calendário de
 * obrigações não existe em lugar nenhum". Estimativa VISUAL (Simples Nacional);
 * apuração oficial mora no módulo Fiscal — disclaimer fixo no rodapé.
 * ═════════════════════════════════════════════════════════════ */
const IMP_STATUS = {
  a_vencer: { label: "a vencer",  cls: "text-[var(--warn)] bg-[var(--warn-soft)]" },
  paga:     { label: "paga",      cls: "text-[var(--pos)] bg-[var(--pos-soft)]" },
  atrasada: { label: "atrasada",  cls: "text-[var(--neg)] bg-[var(--neg-soft)]" },
};

const TelaImpostos = ({ onBack }) => {
  useFinBackEsc(onBack);
  const rows = window.FIN_ROWS;
  // Receita RECEBIDA no mês — base da estimativa do DAS (regime caixa)
  const receitaRecebida = useMemo(
    () => rows.filter((r) => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0),
    [rows]);
  const DAS_RATE = 0.06; // alíquota efetiva típica — estimativa, não apuração
  const dasEstimado = Math.round(receitaRecebida * DAS_RATE * 100) / 100;

  const [guias, setGuias] = useState(() => [
    { id: "fgts-mai", nome: "FGTS · folha",            comp: "maio 2026",  venc: "05/06", valor: 412.8,  status: "a_vencer", det: "GRF · depósito 8%" },
    { id: "inss-mai", nome: "DCTFWeb · INSS retido",   comp: "maio 2026",  venc: "15/06", valor: 358.2,  status: "a_vencer", det: "retenções da folha" },
    { id: "das-mai",  nome: "DAS · Simples Nacional",  comp: "maio 2026",  venc: "20/06", valor: dasEstimado, status: "a_vencer", det: "≈ 6% s/ recebido no mês", estimado: true },
    { id: "das-abr",  nome: "DAS · Simples Nacional",  comp: "abril 2026", venc: "20/05", valor: 786.3,  status: "paga", lanc: "P-1872" },
  ]);
  const lancar = (id) =>
    setGuias((gs) => gs.map((g) => g.id === id && !g.lanc ? { ...g, lanc: "P-" + (1930 + gs.indexOf(g)) } : g));

  const abertas = guias.filter((g) => g.status !== "paga");
  const aRecolher = abertas.reduce((s, g) => s + g.valor, 0);
  const proxima = abertas[0];

  // Costura NF ↔ título: recebíveis sem NF vinculada distorcem a base do DAS
  const semNf = useMemo(() => rows.filter((r) => r.kind === "receivable" && !r.invoice), [rows]);
  const recComNf = useMemo(() => {
    const rec = rows.filter((r) => r.kind === "receivable");
    if (!rec.length) return 100;
    return Math.round((rec.filter((r) => !!r.invoice).length / rec.length) * 100);
  }, [rows]);

  return (
    <div className="fin-imp">
      <FinBcrumb here="Impostos & obrigações" onBack={onBack} />

      <div className="os-stats fin-stats">
        <div className="os-stat">
          <small>A recolher · junho</small>
          <b className="mono">{fmtBRL2(aRecolher)}</b>
          <span className="fin-stat-hint">{abertas.length} guia(s) em aberto</span>
        </div>
        <div className="os-stat">
          <small>Próxima obrigação</small>
          <b>{proxima ? proxima.venc : "—"}</b>
          <span className="fin-stat-hint">{proxima ? proxima.nome : "nada em aberto"}</span>
        </div>
        <div className="os-stat">
          <small>Receita com NF · maio</small>
          <b className="mono">{recComNf}%</b>
          <span className="fin-stat-hint">{semNf.length === 0 ? "todos os títulos com NF ✓" : semNf.length + " título(s) sem NF vinculada"}</span>
        </div>
      </div>

      <div className="grid grid-cols-[1fr_300px] max-[1100px]:grid-cols-1 gap-4 items-start mt-4">
        {/* Guias */}
        <section className="border border-[var(--border)] rounded-lg bg-[var(--surface)] overflow-hidden">
          <header className="px-4 h-10 flex items-center gap-2 border-b border-[var(--border)]">
            <I.Receipt size={13} className="text-[var(--text-3)]" />
            <b className="text-[length:var(--fs-3)] font-semibold">Guias do período</b>
            <span className="ml-auto text-[length:var(--fs-2)] text-[var(--text-3)]">competência abril–maio</span>
          </header>
          <table className="w-full text-[length:var(--fs-3)]">
            <thead>
              <tr className="text-left text-[length:var(--fs-1)] uppercase tracking-wider text-[var(--text-3)]">
                <th className="px-4 py-2 font-medium">Guia</th>
                <th className="px-2 py-2 font-medium">Competência</th>
                <th className="px-2 py-2 font-medium">Venc.</th>
                <th className="px-2 py-2 font-medium text-right">Valor</th>
                <th className="px-2 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium text-right">No caixa</th>
              </tr>
            </thead>
            <tbody>
              {guias.map((g) => {
                const st = IMP_STATUS[g.status];
                return (
                  <tr key={g.id} className="border-t border-[var(--border)]">
                    <td className="px-4 py-2.5">
                      <div className="font-medium">{g.nome}</div>
                      <div className="text-[length:var(--fs-2)] text-[var(--text-3)]">{g.det}{g.estimado && " · estimado"}</div>
                    </td>
                    <td className="px-2 py-2.5 text-[var(--text-2)]">{g.comp}</td>
                    <td className="px-2 py-2.5 mono">{g.venc}</td>
                    <td className="px-2 py-2.5 mono text-right">{fmtBRL2(g.valor)}</td>
                    <td className="px-2 py-2.5">
                      <span className={"inline-flex items-center px-1.5 py-0.5 rounded text-[length:var(--fs-1)] font-medium " + st.cls}>{st.label}</span>
                    </td>
                    <td className="px-4 py-2.5 text-right">
                      {g.lanc
                        ? <span className="text-[length:var(--fs-2)] text-[var(--text-2)] inline-flex items-center gap-1"><I.Check size={11} className="text-[var(--pos)]" /> {g.status === "paga" ? "paga · " : "a pagar · "}<span className="mono">{g.lanc}</span></span>
                        : <button className="os-btn ghost" onClick={() => lancar(g.id)} title="Cria o título a pagar no caixa unificado">Lançar a pagar</button>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </section>

        {/* Coluna lateral: calendário + costura NF */}
        <div className="grid gap-4">
          <section className="border border-[var(--border)] rounded-lg bg-[var(--surface)]">
            <header className="px-4 h-10 flex items-center gap-2 border-b border-[var(--border)]">
              <I.Calendar size={13} className="text-[var(--text-3)]" />
              <b className="text-[length:var(--fs-3)] font-semibold">Calendário de obrigações</b>
            </header>
            <ul className="py-1">
              {abertas.map((g) => (
                <li key={g.id} className="px-4 py-2 flex items-baseline gap-2.5 text-[length:var(--fs-3)]">
                  <span className="mono text-[length:var(--fs-2)] text-[var(--text-2)] shrink-0 w-10">{g.venc}</span>
                  <span className="flex-1">{g.nome}</span>
                  <span className="mono text-[length:var(--fs-2)] text-[var(--text-2)]">{fmtBRL2k(g.valor)}</span>
                </li>
              ))}
              <li className="px-4 py-2 flex items-baseline gap-2.5 text-[length:var(--fs-3)] border-t border-[var(--border)]">
                <span className="mono text-[length:var(--fs-2)] text-[var(--text-2)] shrink-0 w-10">30/06</span>
                <span className="flex-1 text-[var(--text-2)]">Fechamento mensal (trilha guiada)</span>
              </li>
            </ul>
          </section>

          <section className="border border-[var(--border)] rounded-lg bg-[var(--surface)]">
            <header className="px-4 h-10 flex items-center gap-2 border-b border-[var(--border)]">
              <I.FileText size={13} className="text-[var(--text-3)]" />
              <b className="text-[length:var(--fs-3)] font-semibold">NF ↔ título</b>
            </header>
            {semNf.length === 0 ? (
              <p className="px-4 py-3 text-[length:var(--fs-3)] text-[var(--text-2)] leading-relaxed">
                <I.Check size={12} className="inline text-[var(--pos)] mr-1" />
                Todos os recebíveis do período têm NF vinculada — base do DAS consistente.
              </p>
            ) : (
              <ul className="py-1">
                {semNf.slice(0, 3).map((r) => (
                  <li key={r.id} className="px-4 py-2 text-[length:var(--fs-3)] flex items-baseline gap-2">
                    <span className="mono text-[var(--text-3)]">{r.id}</span>
                    <span className="flex-1 truncate">{r.party}</span>
                    <span className="mono">{fmtBRL2k(r.amount)}</span>
                  </li>
                ))}
                <li className="px-4 py-2 text-[length:var(--fs-2)] text-[var(--text-2)] border-t border-[var(--border)]">Sem NF a base do DAS sai distorcida — vincule antes do fechamento.</li>
              </ul>
            )}
          </section>
        </div>
      </div>

      <p className="mt-4 text-[length:var(--fs-2)] text-[var(--text-3)] leading-relaxed max-w-[60ch]">
        Estimativa visual (Simples Nacional · alíquota efetiva ≈ 6% sobre {fmtBRL2(receitaRecebida)} recebidos) — a apuração oficial, cálculo por anexo e emissão de guia moram no módulo <b>Fiscal</b>.
      </p>
    </div>
  );
};

window.TelaFluxo = TelaFluxo;
window.TelaConciliacao = TelaConciliacao;
window.TelaDRE = TelaDRE;
window.TelaPContas = TelaPContas;
window.TelaImpostos = TelaImpostos;
})();
