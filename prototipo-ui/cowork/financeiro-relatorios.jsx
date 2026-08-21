// Financeiro — peças puxadas do vivo na leva 1 do espelho (sync git 2026-08-17):
//   · FinFluxoRealizado — tab "Realizado · últ 12 meses" (US-FIN-014c, charter Fluxo v2)
//   · FinBalanco        — tab "Balanço" patrimonial gerencial (US-FIN-014d, DRE Fase 4)
//   · FinBalancete      — tab "Balancete" de verificação gerencial (US-FIN-014e)
// Idioma do protótipo: tokens (--pos/--neg/--warn/--surface), num tabular, densidade compacta.
// No vivo os números vêm de fin_titulo_baixas × fin_titulos agregadas; aqui derivam do mock
// FIN_ROWS e, nos meses sem baixa, de série determinística (protótipo, nunca random).
(() => {
const { useState, useMemo } = React;
const I = window.FIN_I;

const brl = (n) => (n || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const brlNude = (n) => brl(n).replace("R$", "").trim();
const brlK = (n) => {
  const abs = Math.abs(n);
  if (abs >= 1000) return (n < 0 ? "− " : "") + "R$ " + (abs / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "k";
  return brl(n);
};
const MESES = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];

/* ═════════════════════════════════════════════════════════════════════════
 * FLUXO · tab Realizado — 12 meses de movimentação confirmada
 * Charter Fluxo v2: KPIs Saldo 12M / Entradas / Saídas / Baixas registradas ·
 * barras gêmeas por mês (mês atual mais escuro) · tabela com linha total ·
 * ignora estorno · read-only.
 * ═════════════════════════════════════════════════════════════════════════ */
const FinFluxoRealizado = ({ meses = 12 }) => {
  const rows = window.FIN_ROWS;
  const today = window.FIN_TODAY;

  const serie = useMemo(() => {
    const out = [];
    for (let i = meses - 1; i >= 0; i--) {
      const d = new Date(today.getFullYear(), today.getMonth() - i, 1);
      const key = d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0");
      const baixas = rows.filter((r) => r.paid_at && r.paid_at.getFullYear() === d.getFullYear() && r.paid_at.getMonth() === d.getMonth());
      let entradas = baixas.filter((r) => r.kind === "receivable").reduce((s, r) => s + r.amount, 0);
      let saidas = baixas.filter((r) => r.kind === "payable").reduce((s, r) => s + r.amount, 0);
      let qtd = baixas.length;
      if (qtd === 0) {
        // Histórico mock determinístico (no vivo: agregação SQL por YYYY-MM).
        const f = 1 + (i * 7 % 5) / 10;
        entradas = Math.round(9800 * f);
        saidas = Math.round(8100 * (1 + (i * 3 % 4) / 10));
        qtd = 6 + i % 5;
      }
      out.push({ key, label: MESES[d.getMonth()] + "/" + String(d.getFullYear()).slice(2), entradas, saidas, saldo: entradas - saidas, qtd, atual: i === 0 });
    }
    return out;
  }, [rows, today, meses]);

  const totais = useMemo(() => ({
    entradas: serie.reduce((s, m) => s + m.entradas, 0),
    saidas: serie.reduce((s, m) => s + m.saidas, 0),
    qtd: serie.reduce((s, m) => s + m.qtd, 0)
  }), [serie]);
  const saldo12 = totais.entradas - totais.saidas;
  const maxBar = Math.max(...serie.map((m) => Math.max(m.entradas, m.saidas)), 1);

  return (
    <>
      <div className="px-6 pt-4">
        <div className="fin-card flex divide-x divide-[var(--border)] overflow-hidden">
          <div className="flex-1 px-5 py-4 fin-ink">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest font-medium text-[var(--text-3)]">Saldo {meses}M</div>
            <div className={`mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num ${saldo12 >= 0 ? "text-[var(--pos)]" : "text-[var(--neg)]"}`}>{brl(saldo12)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-3)]">entradas − saídas do período</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Entradas</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num text-[var(--pos)]">{brl(totais.entradas)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">recebimentos confirmados</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Saídas</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num text-[var(--neg)]">{brl(totais.saidas)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">pagamentos confirmados</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Baixas registradas</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num">{totais.qtd}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">estornos fora da conta</div>
          </div>
        </div>
      </div>

      <div className="px-6 mt-4">
        <div className="fin-card p-5">
          <div className="flex items-center justify-between mb-3">
            <div>
              <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Movimento confirmado · últimos {meses} meses</div>
              <div className="text-[length:var(--fs-4)] font-semibold mt-0.5">barras gêmeas: entrada × saída por mês</div>
            </div>
            <div className="flex items-center gap-3 text-[length:var(--fs-2)] text-[var(--text-2)]">
              <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-[var(--pos)]" /> entrada</span>
              <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-[var(--neg)]" /> saída</span>
            </div>
          </div>
          <div className="h-[200px] flex items-end gap-3 border-b border-[var(--border)]">
            {serie.map((m) =>
            <div key={m.key} className="flex-1 h-full flex flex-col justify-end group relative">
              <div className="flex items-end justify-center gap-1 h-full">
                <div className={`w-1/2 rounded-t-sm ${m.atual ? "bg-[var(--pos)]" : "bg-[var(--pos-soft)] border border-[var(--pos)]/30"}`} style={{ height: `${m.entradas / maxBar * 100}%` }} />
                <div className={`w-1/2 rounded-t-sm ${m.atual ? "bg-[var(--neg)]" : "bg-[var(--neg-soft)] border border-[var(--neg)]/30"}`} style={{ height: `${m.saidas / maxBar * 100}%` }} />
              </div>
              <div className="hidden group-hover:block absolute -top-9 left-1/2 -translate-x-1/2 z-10 fin-ink text-[length:var(--fs-1)] rounded px-2 py-1 whitespace-nowrap num">
                {m.label} · saldo {brlK(m.saldo)}
              </div>
            </div>
            )}
          </div>
          <div className="flex gap-3 mt-1.5 text-[length:var(--fs-1)] text-[var(--text-2)] num">
            {serie.map((m) => <div key={m.key} className={`flex-1 text-center ${m.atual ? "font-bold text-[var(--text)]" : ""}`}>{m.label}</div>)}
          </div>
        </div>
      </div>

      <div className="px-6 mt-4 mb-4">
        <div className="fin-card overflow-hidden">
          <div className="px-5 py-3 border-b border-[var(--border)] flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium whitespace-nowrap">Detalhe por mês</div>
              <div className="text-[length:var(--fs-4)] font-semibold mt-0.5 whitespace-nowrap">{meses} meses · sem estornos</div>
            </div>
            <div className="ml-auto text-[length:var(--fs-2)] text-[var(--text-2)] num">{totais.qtd} baixas</div>
          </div>
          {serie.length === 0 ?
          <div className="px-6 py-12 text-center text-[length:var(--fs-3)] text-[var(--text-2)]">Nenhuma baixa registrada nos últimos {meses} meses.</div> :

          <table className="w-full text-[length:var(--fs-3)] num">
            <thead>
              <tr className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] border-b border-[var(--border)] bg-[var(--sunken)]">
                <th className="pl-6 pr-2 py-2 text-left font-medium">Mês</th>
                <th className="px-2 py-2 text-right font-medium w-[150px]">Entradas</th>
                <th className="px-2 py-2 text-right font-medium w-[150px]">Saídas</th>
                <th className="px-2 py-2 text-right font-medium w-[150px]">Saldo</th>
                <th className="pl-2 pr-6 py-2 text-right font-medium w-[100px]">Baixas</th>
              </tr>
            </thead>
            <tbody>
              {serie.map((m) =>
              <tr key={m.key} className={`border-b border-[var(--hairline)] row-hover ${m.atual ? "bg-[var(--sunken)] font-semibold" : ""}`}>
                <td className="pl-6 pr-2 py-2 text-[var(--text)]">
                  {m.label}{m.atual && <span className="ml-2 text-[length:var(--fs-1)] font-medium text-[var(--text-3)] uppercase tracking-wider">atual</span>}
                </td>
                <td className="px-2 py-2 text-right text-[var(--pos)]">{brlNude(m.entradas)}</td>
                <td className="px-2 py-2 text-right text-[var(--neg)]">{brlNude(m.saidas)}</td>
                <td className={`px-2 py-2 text-right ${m.saldo >= 0 ? "text-[var(--text)]" : "text-[var(--neg)]"}`}>{brlNude(m.saldo)}</td>
                <td className="pl-2 pr-6 py-2 text-right text-[var(--text-2)]">{m.qtd}</td>
              </tr>
              )}
              <tr className="border-t-2 border-[var(--border)] bg-[var(--sunken)] font-bold">
                <td className="pl-6 pr-2 py-2.5">Total {meses} meses</td>
                <td className="px-2 py-2.5 text-right text-[var(--pos)]">{brlNude(totais.entradas)}</td>
                <td className="px-2 py-2.5 text-right text-[var(--neg)]">{brlNude(totais.saidas)}</td>
                <td className={`px-2 py-2.5 text-right ${saldo12 >= 0 ? "text-[var(--text)]" : "text-[var(--neg)]"}`}>{brlNude(saldo12)}</td>
                <td className="pl-2 pr-6 py-2.5 text-right">{totais.qtd}</td>
              </tr>
            </tbody>
          </table>
          }
        </div>
      </div>
    </>);

};

/* Banner obrigatório das visões gerenciais ([W] 2026-05-21) */
const BannerGerencial = ({ children }) =>
<div className="mx-6 mt-4 rounded-lg border px-4 py-3 text-[length:var(--fs-3)] leading-relaxed"
  style={{ background: "color-mix(in oklch, var(--warn) 10%, var(--surface))", borderColor: "color-mix(in oklch, var(--warn) 28%, transparent)", color: "var(--text)" }}>
  <b>Versão gerencial</b> · {children} Para contabilidade fiscal (CFC-compliant) consulte o balancete do contador externo.
</div>;

/* ═════════════════════════════════════════════════════════════════════════
 * DRE · tab Balanço — patrimonial gerencial (Ativo | Passivo + PL)
 * ═════════════════════════════════════════════════════════════════════════ */
const FinBalanco = () => {
  const rows = window.FIN_ROWS;
  const b = useMemo(() => {
    const pagoRec = rows.filter((r) => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const pagoPag = rows.filter((r) => r.kind === "payable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const saldoBancos = pagoRec - pagoPag;
    const aReceber = rows.filter((r) => r.kind === "receivable" && !r.paid_at && r.status !== "cancelado").reduce((s, r) => s + r.amount, 0);
    const aPagar = rows.filter((r) => r.kind === "payable" && !r.paid_at && r.status !== "cancelado").reduce((s, r) => s + r.amount, 0);
    const ativo = saldoBancos + aReceber;
    return { saldoBancos, aReceber, aPagar, ativo, passivo: aPagar, pl: ativo - aPagar };
  }, [rows]);
  const linha = (label, valor, opts = {}) =>
  <tr className={`border-b border-[var(--hairline)] ${opts.child ? "row-hover" : ""}`}>
    <td className={`pr-2 ${opts.child ? "py-1.5 pl-12 text-[var(--text-2)]" : "py-2 pl-6 font-medium text-[var(--text)]"}`}>{label}</td>
    <td className={`pr-6 text-right num ${opts.child ? "py-1.5 text-[var(--text-2)]" : "py-2 font-semibold"} ${opts.tone === "neg" ? "text-[var(--neg)]" : ""}`}>{brl(valor)}</td>
  </tr>;

  return (
    <>
      <BannerGerencial>usa contas a pagar/receber + saldos sincronizados.</BannerGerencial>
      <div className="px-6 mt-4">
        <div className="fin-card flex divide-x divide-[var(--border)] overflow-hidden">
          <div className="flex-1 px-5 py-4 fin-ink">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest font-medium text-[var(--text-3)]">Ativo total</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num text-[var(--pos)]">{brl(b.ativo)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-3)]">circulante</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Passivo total</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num text-[var(--neg)]">{brl(b.passivo)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">circulante</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Patrimônio líquido</div>
            <div className={`mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num ${b.pl >= 0 ? "text-[var(--pos)]" : "text-[var(--neg)]"}`}>{brl(b.pl)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">ativo − passivo</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Equação patrimonial</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight text-[var(--pos)]">OK</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">A = P + PL</div>
          </div>
        </div>
      </div>

      <div className="px-6 mt-4 mb-4">
        <div className="fin-card overflow-hidden">
          <div className="px-5 py-3 border-b border-[var(--border)] flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Balanço patrimonial gerencial</div>
              <div className="text-[length:var(--fs-5)] font-semibold mt-0.5">Posição em {window.FIN_TODAY.toLocaleDateString("pt-BR")}</div>
            </div>
            <div className="ml-auto text-[length:var(--fs-2)] text-[var(--text-2)] shrink-0">ROTA LIVRE</div>
          </div>
          <div className="grid grid-cols-2">
            <div className="border-r border-[var(--border)]">
              <div className="px-6 py-3 bg-[var(--sunken)] border-b border-[var(--border)] text-[length:var(--fs-2)] uppercase tracking-widest font-semibold text-[var(--text-2)]">Ativo</div>
              <table className="w-full text-[length:var(--fs-3)] num">
                <tbody>
                  {linha("Ativo circulante", b.ativo)}
                  {linha("Saldo em contas bancárias", b.saldoBancos, { child: true })}
                  {linha("Contas a receber", b.aReceber, { child: true })}
                  <tr className="border-y-2 border-[var(--border)] bg-[var(--sunken)]">
                    <td className="pl-6 pr-2 py-2.5 font-semibold">Total do ativo</td>
                    <td className="pr-6 py-2.5 text-right font-bold text-[length:var(--fs-4)] text-[var(--pos)] num">{brl(b.ativo)}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div>
              <div className="px-6 py-3 bg-[var(--sunken)] border-b border-[var(--border)] text-[length:var(--fs-2)] uppercase tracking-widest font-semibold text-[var(--text-2)]">Passivo + patrimônio líquido</div>
              <table className="w-full text-[length:var(--fs-3)] num">
                <tbody>
                  {linha("Passivo circulante", b.passivo)}
                  {linha("Contas a pagar", b.aPagar, { child: true })}
                  {linha("Patrimônio líquido", b.pl, { tone: b.pl < 0 ? "neg" : null })}
                  <tr className="border-b border-[var(--hairline)] row-hover">
                    <td className="pl-12 pr-2 py-1.5 text-[var(--text-2)]">Derivado (ativo − passivo)</td>
                    <td className="pr-6 py-1.5 text-right text-[var(--text-3)] text-[length:var(--fs-2)]">F1 simplificado</td>
                  </tr>
                  <tr className="border-y-2 border-[var(--border)] bg-[var(--sunken)]">
                    <td className="pl-6 pr-2 py-2.5 font-semibold">Total passivo + PL</td>
                    <td className="pr-6 py-2.5 text-right font-bold text-[length:var(--fs-4)] text-[var(--pos)] num">{brl(b.passivo + b.pl)}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div className="px-6 py-3 border-t border-[var(--border)] bg-[var(--sunken)] flex items-center gap-3 text-[length:var(--fs-2)]">
            <span className="inline-flex items-center gap-1.5 font-medium text-[var(--pos)]">
              <span className="w-2 h-2 rounded-full bg-current" /> Equação patrimonial OK
            </span>
            <span className="text-[var(--text-2)] num">Ativo ({brl(b.ativo)}) = Passivo ({brl(b.passivo)}) + PL ({brl(b.pl)})</span>
          </div>
        </div>
      </div>
    </>);

};

/* ═════════════════════════════════════════════════════════════════════════
 * DRE · tab Balancete — verificação gerencial por plano de contas (D/C)
 * ═════════════════════════════════════════════════════════════════════════ */
const FinBalancete = () => {
  const rows = window.FIN_ROWS;
  const { linhas, debito, credito } = useMemo(() => {
    const porCat = (kind) => {
      const m = new Map();
      rows.filter((r) => r.kind === kind && r.status !== "cancelado").forEach((r) => m.set(r.category, (m.get(r.category) || 0) + r.amount));
      return [...m.entries()].sort((a, b) => b[1] - a[1]);
    };
    const rec = porCat("receivable");
    const desp = porCat("payable");
    const pagoRec = rows.filter((r) => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const pagoPag = rows.filter((r) => r.kind === "payable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aReceber = rows.filter((r) => r.kind === "receivable" && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aPagar = rows.filter((r) => r.kind === "payable" && !r.paid_at).reduce((s, r) => s + r.amount, 0);
    const L = [];
    const push = (codigo, nome, nivel, tipo, dc, saldo) => L.push({ codigo, nome, nivel, tipo, dc, saldo });
    push("1", "Ativo", 1, "ativo", "D", pagoRec - pagoPag + aReceber);
    push("1.1", "Caixa e equivalentes", 2, "ativo", "D", pagoRec - pagoPag);
    push("1.2", "Contas a receber", 2, "ativo", "D", aReceber);
    push("2", "Passivo", 1, "passivo", "C", aPagar);
    push("2.1", "Contas a pagar", 2, "passivo", "C", aPagar);
    push("3", "Receitas", 1, "receita", "C", rec.reduce((s, [, v]) => s + v, 0));
    rec.forEach(([cat, v], i) => push("3.1." + String(i + 1).padStart(2, "0"), cat, 3, "receita", "C", v));
    push("4", "Custos e despesas", 1, "despesa", "D", desp.reduce((s, [, v]) => s + v, 0));
    desp.forEach(([cat, v], i) => push("4.1." + String(i + 1).padStart(2, "0"), cat, 3, "despesa", "D", v));
    const linhas = L.filter((l) => Math.abs(l.saldo) > 0.005);
    return {
      linhas,
      debito: linhas.filter((l) => l.nivel === 1 && l.dc === "D").reduce((s, l) => s + l.saldo, 0),
      credito: linhas.filter((l) => l.nivel === 1 && l.dc === "C").reduce((s, l) => s + l.saldo, 0)
    };
  }, [rows]);
  const totalGeral = debito + credito;

  return (
    <>
      <BannerGerencial>saldos por plano de contas no regime de competência.</BannerGerencial>
      <div className="px-6 mt-4">
        <div className="fin-card flex divide-x divide-[var(--border)] overflow-hidden">
          <div className="flex-1 px-5 py-4 fin-ink">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest font-medium text-[var(--text-3)]">Período</div>
            <div className="mt-1 text-[length:var(--fs-6)] leading-none font-semibold tracking-tight">Maio 2026</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-3)]">{linhas.length} contas com movimento</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Total débito (D)</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num">{brl(debito)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">ativo + custo + despesa</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Total crédito (C)</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num">{brl(credito)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">passivo + receita + PL</div>
          </div>
          <div className="flex-1 px-5 py-4">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Total geral</div>
            <div className="mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight num">{brl(totalGeral)}</div>
            <div className="mt-2 text-[length:var(--fs-2)] text-[var(--text-2)]">D + C consolidado</div>
          </div>
        </div>
      </div>

      <div className="px-6 mt-4 mb-4">
        <div className="fin-card overflow-hidden">
          <div className="px-5 py-3 border-b border-[var(--border)] flex items-center gap-3">
            <div className="min-w-0">
              <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] font-medium">Balancete de verificação gerencial</div>
              <div className="text-[length:var(--fs-5)] font-semibold mt-0.5">Maio 2026</div>
            </div>
            <div className="ml-auto text-[length:var(--fs-2)] text-[var(--text-2)] shrink-0">ROTA LIVRE</div>
          </div>
          {linhas.length === 0 ?
          <div className="px-6 py-12 text-center text-[length:var(--fs-3)] text-[var(--text-2)]">Nenhuma conta com movimento no período.</div> :

          <table className="w-full text-[length:var(--fs-3)] num">
            <thead>
              <tr className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-2)] border-b border-[var(--border)] bg-[var(--sunken)]">
                <th className="pl-6 pr-2 py-2 text-left font-medium w-[110px]">Código</th>
                <th className="px-2 py-2 text-left font-medium">Conta</th>
                <th className="px-2 py-2 text-center font-medium w-[60px]">D/C</th>
                <th className="px-2 py-2 text-right font-medium w-[150px]">Saldo</th>
                <th className="pl-2 pr-6 py-2 text-left font-medium w-[110px]">Tipo</th>
              </tr>
            </thead>
            <tbody>
              {linhas.map((l) =>
              <tr key={l.codigo} className={`border-b border-[var(--hairline)] ${l.nivel <= 2 ? "bg-[var(--sunken)] font-semibold" : "row-hover"}`}>
                <td className="pl-6 pr-2 py-1.5 text-[var(--text-3)] text-[length:var(--fs-2)] font-mono">{l.codigo}</td>
                <td className={`px-2 py-1.5 ${l.nivel <= 2 ? "text-[var(--text)]" : "text-[var(--text-2)]"}`} style={{ paddingLeft: 8 + (l.nivel - 1) * 16 }}>{l.nome}</td>
                <td className={`px-2 py-1.5 text-center text-[length:var(--fs-1)] font-bold ${l.dc === "D" ? "text-[var(--accent)]" : "text-[var(--warn)]"}`}>{l.dc}</td>
                <td className={`px-2 py-1.5 text-right ${l.nivel <= 2 ? "font-bold" : ""}`}>{brlNude(l.saldo)}</td>
                <td className="pl-2 pr-6 py-1.5 text-[var(--text-3)] text-[length:var(--fs-1)] uppercase tracking-wider">{l.tipo}</td>
              </tr>
              )}
              <tr className="border-t-2 border-[var(--border)] bg-[var(--sunken)] font-bold">
                <td className="pl-6 pr-2 py-2.5" />
                <td className="px-2 py-2.5">Total geral</td>
                <td className="px-2 py-2.5 text-center text-[var(--text-3)] text-[length:var(--fs-1)]">D + C</td>
                <td className="px-2 py-2.5 text-right">{brlNude(totalGeral)}</td>
                <td className="pl-2 pr-6 py-2.5 text-[var(--text-3)] text-[length:var(--fs-1)]">D: {brlNude(debito)} · C: {brlNude(credito)}</td>
              </tr>
            </tbody>
          </table>
          }
        </div>
      </div>
    </>);

};

/* Pill segmented reusado pelas duas telas (pattern do vivo: Fluxo e DRE) */
const FinTabPill = ({ tabs, value, onChange }) =>
<div className="px-6 pt-4">
  <div className="fin-seg" role="tablist">
    {tabs.map((t) =>
    <button key={t.id} role="tab" aria-selected={value === t.id} onClick={() => onChange(t.id)}
      className={"fin-seg-btn" + (value === t.id ? " on" : "")}>
      {t.label}{t.hint && <span className="text-[var(--text-3)] ml-1.5">{t.hint}</span>}
    </button>
    )}
  </div>
</div>;

Object.assign(window, { FinFluxoRealizado, FinBalanco, FinBalancete, FinTabPill });
})();
