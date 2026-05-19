// Financeiro — Telas extras: Fluxo, Conciliação, DRE, Plano de contas.
// All in compact density to match the unified screen aesthetic.
// Tokens: BRIEFING §4 (emerald/amber/rose, rounded-md, shadow-sm, num tabular).

const { useState, useMemo } = React;

const fmtBRL2 = (n) =>
  n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const fmtBRL2k = (n) => {
  const abs = Math.abs(n);
  if (abs >= 1000) return (n < 0 ? "− " : "") + "R$ " + (abs / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "k";
  return fmtBRL2(n);
};
const fmtDate2 = (d) => d.toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit" });
const fmtDateLong2 = (d) => d.toLocaleDateString("pt-BR", { day: "2-digit", month: "short", year: "numeric" });

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
        <div className="rounded-md bg-white border border-stone-200 shadow-sm flex divide-x divide-stone-200 overflow-hidden">
          <div className="flex-1 px-5 py-4 bg-stone-900 text-stone-50">
            <div className="text-[10px] uppercase tracking-widest font-medium text-stone-400">Saldo hoje · 09 mai</div>
            <div className="mt-1 text-[28px] leading-none font-semibold tracking-tight num">{fmtBRL2(saldoHoje)}</div>
            <div className="mt-2 text-[11.5px] text-stone-400">Itaú PJ · ag 0438 cc 4521-7</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Projeção 30 dias</div>
            <div className={`mt-1 text-[28px] leading-none font-semibold tracking-tight num ${proj30 >= saldoHoje ? "text-emerald-700" : "text-rose-700"}`}>{fmtBRL2(proj30)}</div>
            <div className="mt-2 text-[11.5px] text-stone-500">{proj30 >= saldoHoje ? "alta" : "queda"} de {fmtBRL2k(Math.abs(proj30 - saldoHoje))} vs hoje</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Pior dia previsto</div>
            <div className="mt-1 text-[28px] leading-none font-semibold tracking-tight num text-amber-700">{fmtBRL2(minDay.saldo)}</div>
            <div className="mt-2 text-[11.5px] text-stone-500">{fmtDateLong2(minDay.date)}</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Margem mínima</div>
            <div className="mt-1 text-[28px] leading-none font-semibold tracking-tight num text-stone-900">R$ [redacted Tier 0]</div>
            <div className="mt-2 text-[11.5px] text-stone-500">limite definido · acima ✓</div>
          </div>
        </div>
      </div>

      {/* Chart */}
      <div className="px-6 mt-4">
        <div className="bg-white border border-stone-200 rounded-md shadow-sm p-5">
          <div className="flex items-center justify-between mb-3">
            <div>
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Saldo projetado · próximos 35 dias</div>
              <div className="text-[14px] font-semibold mt-0.5">linha laranja = limite mínimo · barras = movimento líquido do dia</div>
            </div>
            <div className="flex items-center gap-3 text-[11.5px] text-stone-500">
              <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-stone-900" /> saldo</span>
              <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-emerald-500" /> entrada</span>
              <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-rose-500" /> saída</span>
            </div>
          </div>

          {/* Chart area */}
          <div className="relative h-[220px] border-b border-stone-200 mt-2">
            {/* Limit line */}
            <div className="absolute left-0 right-0 border-t border-dashed border-amber-400" style={{ top: `${(1 - (5000 - minSaldo) / (maxSaldo - minSaldo)) * 100}%` }}>
              <span className="absolute -top-4 right-0 text-[10px] text-amber-700 font-medium bg-white px-1">R$ [redacted Tier 0]k mínimo</span>
            </div>
            {/* Bars */}
            <div className="absolute inset-0 flex items-end gap-px">
              {days.map((d, i) => {
                const h = ((d.saldo - minSaldo) / (maxSaldo - minSaldo)) * 100;
                const moveBar = Math.max(2, Math.abs(d.net) / Math.max(...days.map((x) => Math.abs(x.net) || 1), 1) * 50);
                return (
                  <div key={i} className="flex-1 h-full flex flex-col justify-end relative group">
                    {/* saldo line column */}
                    <div className={`w-full ${d.isPast ? "bg-stone-300" : d.isToday ? "bg-stone-900" : "bg-stone-700"} ${d.saldo < 5000 ? "!bg-amber-500" : ""}`} style={{ height: `${h}%` }}>
                    </div>
                    {/* hover info */}
                    {d.dayRows.length > 0 && (
                      <div className="hidden group-hover:block absolute -top-14 left-1/2 -translate-x-1/2 z-10 bg-stone-900 text-white text-[10.5px] rounded px-2 py-1 whitespace-nowrap num">
                        {fmtDate2(d.date)} · {fmtBRL2k(d.saldo)}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Date axis */}
          <div className="flex gap-px mt-1.5 text-[9.5px] text-stone-500 num">
            {days.map((d, i) => (
              <div key={i} className={`flex-1 text-center ${d.isToday ? "font-bold text-stone-900" : ""}`}>
                {i % 5 === 0 || d.isToday ? fmtDate2(d.date) : ""}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Upcoming events */}
      <div className="px-6 mt-4">
        <div className="bg-white border border-stone-200 rounded-md shadow-sm overflow-hidden">
          <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">Próximos eventos</div>
              <div className="text-[14px] font-semibold mt-0.5 whitespace-nowrap">7 dias adiante</div>
            </div>
            <div className="ml-auto text-[11.5px] text-stone-500 whitespace-nowrap shrink-0">{days.filter((d) => !d.isPast && d.dayRows.length > 0).slice(0, 7).reduce((s, d) => s + d.dayRows.length, 0)} lançamentos</div>
          </div>
          <table className="w-full text-[12.5px] num">
            <tbody>
              {days.filter((d) => !d.isPast && d.dayRows.length > 0).slice(0, 7).flatMap((d) =>
                d.dayRows.map((r, j) => (
                  <tr key={r.id} className={`border-b border-stone-100 row-hover ${j === 0 ? "border-t-2 border-t-stone-100" : ""}`}>
                    <td className="pl-6 pr-3 py-2 w-[110px] text-stone-700">{j === 0 ? fmtDateLong2(d.date) : ""}</td>
                    <td className="px-2 py-2">
                      <span className={`inline-grid place-items-center rounded ${r.kind === "receivable" ? "bg-emerald-50 text-emerald-700" : "bg-rose-50 text-rose-700"}`} style={{ width: 22, height: 22 }}>
                        {r.kind === "receivable" ? "↓" : "↑"}
                      </span>
                    </td>
                    <td className="px-2 py-2 font-medium text-stone-900 truncate">{r.desc}</td>
                    <td className="px-2 py-2 text-stone-600">{r.party}</td>
                    <td className="px-2 py-2 text-stone-500">{r.category}</td>
                    <td className="pr-6 py-2 text-right font-medium">
                      <span className={r.kind === "receivable" ? "text-emerald-700" : "text-stone-900"}>
                        {r.kind === "receivable" ? "+" : "−"} {fmtBRL2(r.amount)}
                      </span>
                    </td>
                    <td className="pr-6 py-2 text-right text-stone-700 font-medium w-[120px]">
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

const TelaConciliacao = () => {
  const [selected, setSelected] = useState(null);
  const matched = EXTRATO.filter((e) => e.match).length;
  const pending = EXTRATO.filter((e) => !e.match).length;
  const totalIn = EXTRATO.filter((e) => e.amount > 0).reduce((s, e) => s + e.amount, 0);
  const totalOut = EXTRATO.filter((e) => e.amount < 0).reduce((s, e) => s + e.amount, 0);

  return (
    <>
      <div className="px-6 pt-4">
        <div className="rounded-md bg-white border border-stone-200 shadow-sm flex divide-x divide-stone-200 overflow-hidden">
          <div className="flex-1 px-5 py-4">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Período</div>
            <div className="mt-1 text-[16px] font-semibold tracking-tight">02 → 09 mai 2026</div>
            <div className="mt-2 text-[11.5px] text-stone-500">Itaú PJ · OFX importado 09/05 14:32</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Conciliados</div>
            <div className="mt-1 text-[28px] leading-none font-semibold tracking-tight num text-emerald-700">{matched}<span className="text-stone-400 text-[18px]">/{EXTRATO.length}</span></div>
            <div className="mt-2">
              <div className="h-1 bg-stone-100 rounded-full overflow-hidden">
                <div className="h-full bg-emerald-500" style={{ width: `${(matched / EXTRATO.length) * 100}%` }} />
              </div>
            </div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Pendente revisão</div>
            <div className="mt-1 text-[28px] leading-none font-semibold tracking-tight num text-amber-700">{pending}</div>
            <div className="mt-2 text-[11.5px] text-stone-500">com sugestão automática: {EXTRATO.filter((e) => !e.match && e.suggest).length}</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Total no extrato</div>
            <div className="mt-1 text-[18px] leading-tight font-semibold tracking-tight num text-emerald-700">+ {fmtBRL2(totalIn)}</div>
            <div className="text-[18px] leading-tight font-semibold tracking-tight num text-rose-700">{fmtBRL2(totalOut)}</div>
          </div>
        </div>
      </div>

      <div className="px-6 mt-4 mb-4">
        <div className="bg-white border border-stone-200 rounded-md shadow-sm overflow-hidden">
          <div className="grid grid-cols-2 border-b border-stone-200 text-[10px] uppercase tracking-widest text-stone-500 font-medium">
            <div className="px-5 py-2.5 border-r border-stone-200 flex items-center gap-2">
              Extrato Itaú PJ
              <span className="text-stone-400 normal-case tracking-normal text-[11px]">· {EXTRATO.length} lançamentos</span>
            </div>
            <div className="px-5 py-2.5 flex items-center gap-2">
              Sistema oimpresso
              <span className="text-stone-400 normal-case tracking-normal text-[11px]">· match sugerido</span>
            </div>
          </div>

          {EXTRATO.map((e) => {
            const sysRow = window.FIN_ROWS.find((r) => r.id === (e.match || e.suggest));
            const status = e.match ? "matched" : e.suggest ? "suggest" : "none";
            return (
              <div key={e.id} className={`grid grid-cols-2 border-b border-stone-100 text-[12.5px] num ${selected === e.id ? "bg-stone-50" : "row-hover"}`} onClick={() => setSelected(e.id)}>
                {/* Left: extrato */}
                <div className="px-5 py-3 border-r border-stone-200 flex items-center gap-3 cursor-pointer">
                  <div className="text-stone-700 w-[60px]">{fmtDate2(new Date(e.date + "T12:00:00"))}</div>
                  <div className="flex-1 min-w-0">
                    <div className="font-medium text-stone-900 truncate">{e.desc}</div>
                    <div className="text-[10.5px] text-stone-400 font-mono">{e.id}</div>
                  </div>
                  <div className={`font-semibold ${e.amount > 0 ? "text-emerald-700" : "text-stone-900"}`}>
                    {e.amount > 0 ? "+" : "−"} {fmtBRL2(Math.abs(e.amount)).replace("R$", "").trim()}
                  </div>
                </div>

                {/* Right: sistema */}
                <div className="px-5 py-3 flex items-center gap-3">
                  {status === "matched" && sysRow && (
                    <>
                      <span className="inline-flex items-center gap-1 text-emerald-700 text-[11px] font-medium px-2 py-0.5 rounded-full bg-emerald-50 whitespace-nowrap">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                        Conciliado
                      </span>
                      <div className="flex-1 min-w-0">
                        <div className="font-medium text-stone-900 truncate">{sysRow.desc}</div>
                        <div className="text-[10.5px] text-stone-400 font-mono">{sysRow.id} · {sysRow.invoice}</div>
                      </div>
                      <button className="w-7 h-7 grid place-items-center rounded text-stone-400 hover:text-stone-700 hover:bg-stone-100">×</button>
                    </>
                  )}
                  {status === "suggest" && sysRow && (
                    <>
                      <span className="inline-flex items-center gap-1 text-amber-700 text-[11px] font-medium px-2 py-0.5 rounded-full bg-amber-50 whitespace-nowrap">
                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                        Sugerido
                      </span>
                      <div className="flex-1 min-w-0">
                        <div className="font-medium text-stone-900 truncate">{sysRow.desc}</div>
                        <div className="text-[10.5px] text-stone-400 font-mono">{sysRow.id} · {sysRow.invoice} · 95% match</div>
                      </div>
                      <button className="h-7 px-2.5 text-[11.5px] rounded text-emerald-700 bg-emerald-50 hover:bg-emerald-100 font-medium whitespace-nowrap">✓ Aceitar</button>
                    </>
                  )}
                  {status === "none" && (
                    <>
                      <span className="inline-flex items-center gap-1 text-stone-500 text-[11px] font-medium px-2 py-0.5 rounded-full bg-stone-100 whitespace-nowrap">
                        <span className="w-1.5 h-1.5 rounded-full bg-stone-400" />
                        Sem match
                      </span>
                      <div className="flex-1 text-stone-500 italic">Provável tarifa bancária — criar lançamento?</div>
                      <button className="h-7 px-2.5 text-[11.5px] rounded border border-stone-200 hover:bg-stone-50 text-stone-700 whitespace-nowrap">+ Criar</button>
                      <button className="h-7 px-2.5 text-[11.5px] rounded text-stone-500 hover:bg-stone-50 whitespace-nowrap">Buscar</button>
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
        <div className="bg-white border border-stone-200 rounded-md shadow-sm overflow-hidden">
          <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">Demonstração de Resultado</div>
              <div className="text-[16px] font-semibold mt-0.5 whitespace-nowrap">{period}</div>
            </div>
            <div className="ml-auto flex items-center gap-2">
              <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
                {["Mês", "Trimestre", "Ano", "12m"].map((p) => (
                  <button key={p} className={`h-7 px-3 rounded text-[12.5px] ${p === "Mês" ? "bg-white shadow-sm font-medium" : "text-stone-600"}`}>{p}</button>
                ))}
              </div>
              <button className="h-8 px-3 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50">Exportar PDF</button>
              <button className="h-8 px-3 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50">Excel</button>
            </div>
          </div>

          <table className="w-full text-[12.5px] num">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
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
                    <tr key={i} className="border-b border-stone-100">
                      <td className="pl-6 pr-2 py-2 font-medium text-stone-900">{l.label}</td>
                      <td className="px-2 py-2 text-right font-semibold">{fmtBRL2(l.v).replace("R$", "").trim()}</td>
                      <td className="px-2 py-2 text-right text-stone-500">{pct.toFixed(1)}%</td>
                      <td className="px-2 py-2 text-right text-stone-500">{fmtBRL2(l.prev).replace("R$", "").trim()}</td>
                      <td className={`px-2 py-2 text-right ${delta > 0 ? "text-emerald-700" : delta < 0 ? "text-rose-700" : "text-stone-400"}`}>{delta > 0 ? "+" : ""}{delta.toFixed(0)}%</td>
                      <td className="pl-2 pr-6"></td>
                    </tr>
                  );
                }
                if (l.type === "i") {
                  return (
                    <tr key={i} className="border-b border-stone-100 row-hover">
                      <td className="pl-6 pr-2 py-1.5 text-stone-600" style={{ paddingLeft: 24 + (l.indent || 0) * 16 }}>{l.label}</td>
                      <td className="px-2 py-1.5 text-right text-stone-700">{fmtBRL2(l.v).replace("R$", "").trim()}</td>
                      <td className="px-2 py-1.5 text-right text-stone-400">{pct.toFixed(1)}%</td>
                      <td className="px-2 py-1.5 text-right text-stone-400">{fmtBRL2(l.prev).replace("R$", "").trim()}</td>
                      <td className={`px-2 py-1.5 text-right text-[11.5px] ${delta > 0 ? "text-emerald-600" : delta < 0 ? "text-rose-600" : "text-stone-400"}`}>{delta > 0 ? "+" : ""}{delta.toFixed(0)}%</td>
                      <td className="pl-2 pr-6 py-1.5">
                        <div className="h-1 bg-stone-100 rounded-full overflow-hidden">
                          <div className={`h-full ${positive ? "bg-emerald-400" : "bg-rose-400"}`} style={{ width: `${Math.min(100, Math.abs(pct) * 3)}%` }} />
                        </div>
                      </td>
                    </tr>
                  );
                }
                if (l.type === "subtotal") {
                  return (
                    <tr key={i} className={`border-y-2 border-stone-200 ${l.highlight ? "bg-stone-900 text-white" : "bg-stone-50"}`}>
                      <td className={`pl-6 pr-2 py-2.5 font-semibold ${l.highlight ? "text-white" : ""}`}>{l.label}</td>
                      <td className={`px-2 py-2.5 text-right font-bold text-[14px] ${l.highlight ? "text-white" : positive ? "text-emerald-700" : "text-rose-700"}`}>{fmtBRL2(l.v).replace("R$", "").trim()}</td>
                      <td className={`px-2 py-2.5 text-right font-medium ${l.highlight ? "text-stone-300" : "text-stone-600"}`}>{pct.toFixed(1)}%</td>
                      <td className={`px-2 py-2.5 text-right ${l.highlight ? "text-stone-400" : "text-stone-600"}`}>{fmtBRL2(l.prev).replace("R$", "").trim()}</td>
                      <td className={`px-2 py-2.5 text-right font-semibold ${delta > 0 ? "text-emerald-400" : delta < 0 ? "text-rose-400" : ""}`}>{delta > 0 ? "+" : ""}{delta.toFixed(0)}%</td>
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
        <div className="bg-white border border-stone-200 rounded-md shadow-sm p-5">
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Margem operacional</div>
          <div className="mt-1 text-[28px] font-semibold tracking-tight num">10.1%</div>
          <div className="mt-2 text-[11.5px] text-stone-500">vs <span className="num">4.0%</span> em abr · <span className="text-emerald-700 font-medium">+6.1pp</span></div>
          <div className="mt-4 h-2 bg-stone-100 rounded-full overflow-hidden">
            <div className="h-full bg-stone-900" style={{ width: "10%" }} />
          </div>
          <div className="mt-1.5 flex justify-between text-[10.5px] text-stone-400">
            <span>0%</span><span>meta 12%</span><span>100%</span>
          </div>
        </div>

        <div className="bg-white border border-stone-200 rounded-md shadow-sm p-5">
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Top categorias receita · maio</div>
          <div className="mt-3 space-y-2.5">
            {[
              { label: "Banner / Lona / Adesivo", v: 9580, pct: 64 },
              { label: "Gráfica rápida", v: 2940, pct: 20 },
              { label: "Fachada / Placa", v: 2340, pct: 16 },
            ].map((c) => (
              <div key={c.label}>
                <div className="flex items-baseline justify-between text-[12.5px]">
                  <span className="text-stone-700">{c.label}</span>
                  <span className="num font-medium">{fmtBRL2(c.v)} <span className="text-stone-400">· {c.pct}%</span></span>
                </div>
                <div className="mt-1 h-1 bg-stone-100 rounded-full overflow-hidden">
                  <div className="h-full bg-emerald-500" style={{ width: `${c.pct}%` }} />
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

const TelaPContas = () => {
  return (
    <>
      <div className="px-6 pt-4 mb-4">
        <div className="bg-white border border-stone-200 rounded-md shadow-sm overflow-hidden">
          <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">Plano de contas</div>
              <div className="text-[16px] font-semibold mt-0.5 whitespace-nowrap">Comunicação Visual · 2 níveis</div>
            </div>
            <div className="ml-auto flex items-center gap-2 shrink-0">
              <input placeholder="Buscar conta…" className="h-8 px-3 w-[160px] rounded-md border border-stone-200 text-[12.5px] placeholder:text-stone-400 focus:border-stone-400" />
              <button className="h-8 px-3 rounded-md border border-stone-200 text-[12.5px] text-stone-700 hover:bg-stone-50 whitespace-nowrap">Importar</button>
              <button className="h-8 px-3 rounded-md bg-stone-900 text-white text-[12.5px] hover:bg-stone-800 whitespace-nowrap">+ Nova</button>
            </div>
          </div>

          <table className="w-full text-[12.5px] num">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
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
                <tr key={c.code} className={`border-b border-stone-100 row-hover ${c.level === 0 ? "bg-stone-50/40" : ""}`}>
                  <td className="pl-6 pr-2 py-2 text-stone-500 font-mono text-[11.5px]">{c.code}</td>
                  <td className="px-2 py-2" style={{ paddingLeft: 12 + c.level * 18 }}>
                    <span className={`${c.level === 0 ? "font-semibold text-stone-900" : c.level === 1 ? "font-medium text-stone-800" : "text-stone-600"}`}>
                      {c.level > 0 && <span className="text-stone-300 mr-1.5">└</span>}
                      {c.name}
                    </span>
                  </td>
                  <td className="px-2 py-2">
                    <span className={`inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full ${
                      c.type === "rec" ? "bg-emerald-50 text-emerald-700" : "bg-rose-50 text-rose-700"
                    }`}>
                      <span className={`w-1.5 h-1.5 rounded-full ${c.type === "rec" ? "bg-emerald-500" : "bg-rose-500"}`} />
                      {c.type === "rec" ? "Receita" : "Despesa"}
                    </span>
                  </td>
                  <td className="px-2 py-2 text-right text-stone-600">{c.count > 0 ? c.count : <span className="text-stone-300">—</span>}</td>
                  <td className={`px-2 py-2 text-right font-medium ${
                    c.saldo === 0 ? "text-stone-300" :
                    c.saldo > 0 ? "text-emerald-700" : "text-stone-900"
                  }`}>
                    {c.saldo === 0 ? "—" : <>
                      <span className="text-stone-400 mr-0.5">{c.saldo > 0 ? "+" : "−"}</span>
                      {fmtBRL2(Math.abs(c.saldo)).replace("R$", "").trim()}
                    </>}
                  </td>
                  <td className="pl-2 pr-6 py-2 text-right">
                    <button className="text-[11px] text-stone-500 hover:text-stone-900 underline-offset-2 hover:underline">editar</button>
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

window.TelaFluxo = TelaFluxo;
window.TelaConciliacao = TelaConciliacao;
window.TelaDRE = TelaDRE;
window.TelaPContas = TelaPContas;
