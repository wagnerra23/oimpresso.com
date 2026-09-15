// financeiro-ai.jsx — Refino #2 KB-9.75 · IA dentro do fluxo (2026-05-18)
// 4 ferramentas:
// - finAiPartyHistory(name) → stats da contraparte (média, sazonalidade, atrasos)
// - <FinAiAnomalia row> → detecta valor outlier vs histórico
// - <FinAiPartyContext row> → "perguntar à IA" sobre fornecedor/cliente
// - <FinAiMonthDigest> → overlay com resumo executivo do mês (Eliana 5min sexta)
//
// Reusa CSS .vd-ai-* já existente em styles.css

(() => {
  const { useState, useMemo, useEffect } = React;

  const _fmt = (n) => (n || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
  const _fmtShort = (n) => n >= 1000 ? "R$ " + (n/1000).toFixed(1).replace(".",",") + "k" : _fmt(n);

  // ─────────────────────────────────────────────────────────────
  // ANALYTICS: histórico de uma contraparte (party)
  // ─────────────────────────────────────────────────────────────
  window.finAiPartyHistory = function finAiPartyHistory(partyName, currentRow) {
    const list = (window.FIN_ROWS || []).filter(r => r.party === partyName);
    const mine = currentRow ? list.filter(r => r.id !== currentRow.id) : list;
    if (mine.length === 0) {
      return { count: 0, total: 0, avg: 0, isNew: true };
    }
    const total = mine.reduce((s, r) => s + r.amount, 0);
    const avg = total / mine.length;
    const paid = mine.filter(r => r.paid_at);
    const overdue = mine.filter(r => r.status === "atrasado");
    const onTime = paid.filter(r => r.paid_at && r.due && r.paid_at <= r.due).length;
    const onTimePct = paid.length ? Math.round(onTime / paid.length * 100) : null;
    const lastDate = mine.reduce((max, r) => {
      const d = r.paid_at || r.due;
      return !max || d > max ? d : max;
    }, null);
    // categoria mais comum
    const catCount = {};
    mine.forEach(r => { catCount[r.category] = (catCount[r.category] || 0) + 1; });
    const topCat = Object.entries(catCount).sort((a,b) => b[1]-a[1])[0]?.[0];

    return {
      count: mine.length, total, avg,
      paidCount: paid.length, overdueCount: overdue.length,
      onTimePct, lastDate, topCat,
      isNew: mine.length === 1,
      isRecurrent: mine.length >= 3,
    };
  };

  // ─────────────────────────────────────────────────────────────
  // ANOMALIA: detecta valor fora do padrão histórico
  // ─────────────────────────────────────────────────────────────
  window.finAiAnomalia = function finAiAnomalia(row) {
    const h = window.finAiPartyHistory(row.party, row);
    if (!h.isRecurrent) return null;
    const diff = row.amount - h.avg;
    const pct = (diff / h.avg) * 100;
    if (Math.abs(pct) < 25) return null;
    return {
      kind: diff > 0 ? "high" : "low",
      pct,
      avg: h.avg,
      desc: diff > 0
        ? `${pct.toFixed(0)}% acima da média histórica`
        : `${Math.abs(pct).toFixed(0)}% abaixo da média histórica`,
    };
  };

  // ─────────────────────────────────────────────────────────────
  // GENERIC AI BLOCK (reusa CSS .vd-ai-*)
  // ─────────────────────────────────────────────────────────────
  function FinAiBlock({ icon = "✦", title, hint, prompt, parseResult, idleCta = "Gerar com IA" }) {
    const [state, setState] = useState("idle");
    const [result, setResult] = useState(null);

    const run = async () => {
      if (!window.claude?.complete) {
        setState("error");
        setResult("Helper window.claude.complete não disponível neste ambiente.");
        return;
      }
      setState("loading");
      try {
        const text = await window.claude.complete(prompt);
        setResult(parseResult ? parseResult(text) : text);
        setState("done");
      } catch (e) {
        setResult(String(e?.message || e));
        setState("error");
      }
    };

    return (
      <div className={`vd-ai-block vd-ai-${state}`}>
        <header className="vd-ai-block-h">
          <span className="vd-ai-ic">{icon}</span>
          <div className="vd-ai-block-tx">
            <b>{title}</b>
            {hint && <small>{hint}</small>}
          </div>
          {state === "idle" && (
            <button className="vd-ai-cta" onClick={run}>{idleCta}</button>
          )}
          {state === "loading" && (
            <span className="vd-ai-loader"><span/><span/><span/></span>
          )}
          {(state === "done" || state === "error") && (
            <button className="vd-ai-retry" onClick={run} title="Refazer">↻</button>
          )}
        </header>
        {state === "loading" && (
          <div className="vd-ai-skel">
            <div className="vd-ai-skel-line" style={{width:"92%"}}/>
            <div className="vd-ai-skel-line" style={{width:"78%"}}/>
            <div className="vd-ai-skel-line" style={{width:"45%"}}/>
          </div>
        )}
        {(state === "done" || state === "error") && (
          <div className={`vd-ai-out ${state === "error" ? "err" : ""}`}>{result}</div>
        )}
      </div>
    );
  }

  // ─────────────────────────────────────────────────────────────
  // 1) ANOMALIA BANNER (mostrado direto no topo do drawer)
  // ─────────────────────────────────────────────────────────────
  function FinAiAnomaliaBanner({ row }) {
    const a = useMemo(() => window.finAiAnomalia(row), [row.id, row.amount]);
    if (!a) return null;
    return (
      <div className={`fin-ai-anomalia fin-ai-anomalia-${a.kind}`}>
        <span className="fin-ai-anomalia-ic">{a.kind === "high" ? "⚠" : "ℹ"}</span>
        <div className="fin-ai-anomalia-body">
          <b>{a.desc}</b>
          <small>Média histórica de <i>{row.party}</i>: {_fmt(a.avg)} · este lançamento: {_fmt(row.amount)}</small>
        </div>
        <button className="fin-ai-anomalia-cta">Investigar</button>
      </div>
    );
  }
  window.FinAiAnomaliaBanner = FinAiAnomaliaBanner;

  // ─────────────────────────────────────────────────────────────
  // 2) PARTY CONTEXT — relacionamento com fornecedor/cliente
  // ─────────────────────────────────────────────────────────────
  function FinAiPartyContext({ row }) {
    const h = useMemo(() => window.finAiPartyHistory(row.party, row), [row.party, row.id]);
    const isIn = row.kind === "receivable";

    if (h.count === 0) {
      return (
        <div className="vd-ai-block vd-ai-disabled">
          <header className="vd-ai-block-h">
            <span className="vd-ai-ic">✦</span>
            <div className="vd-ai-block-tx">
              <b>{isIn ? "Cliente" : "Fornecedor"} novo</b>
              <small>Sem histórico ainda. IA precisa de 2+ lançamentos pra inferir padrão.</small>
            </div>
          </header>
        </div>
      );
    }

    const lastDateStr = h.lastDate ? h.lastDate.toLocaleDateString("pt-BR") : "—";
    const prompt = `Você é assistente do Financeiro da Oimpresso (gráfica). Em 2-3 frases curtas, descreva o relacionamento com ${isIn ? "este cliente" : "este fornecedor"}: padrão de pagamento + nível de relacionamento + atenção pro próximo lançamento.

${isIn ? "Cliente" : "Fornecedor"}: ${row.party}
Lançamentos: ${h.count} (${h.paidCount} já liquidados)
Total acumulado: ${_fmt(h.total)} · ticket médio ${_fmt(h.avg)}
Categoria mais frequente: ${h.topCat}
Última operação: ${lastDateStr}
${h.onTimePct != null ? `Pontualidade: ${h.onTimePct}% pagam/recebem no prazo` : ""}
${h.overdueCount > 0 ? `Atrasados: ${h.overdueCount} lançamento(s) em atraso` : ""}

Lançamento atual: ${_fmt(row.amount)} · vence ${row.due.toLocaleDateString("pt-BR")}.`;

    return (
      <div className="fin-ai-party">
        <div className="vd-ai-stats">
          <div className="vd-ai-stat">
            <small>Lançamentos</small>
            <b>{h.count}</b>
            {h.isRecurrent && <span className="vd-ai-tag new">recorrente</span>}
          </div>
          <div className="vd-ai-stat">
            <small>Total acumulado</small>
            <b>{_fmtShort(h.total)}</b>
          </div>
          <div className="vd-ai-stat">
            <small>Ticket médio</small>
            <b>{_fmtShort(h.avg)}</b>
          </div>
          <div className="vd-ai-stat">
            <small>Pontualidade</small>
            <b>{h.onTimePct != null ? `${h.onTimePct}%` : "—"}</b>
            {h.overdueCount > 0 && <span className="vd-ai-tag warn">{h.overdueCount}× atrasou</span>}
          </div>
        </div>
        <FinAiBlock
          title={`Perguntar à IA sobre ${isIn ? "este cliente" : "este fornecedor"}`}
          hint={`Padrão de ${isIn ? "recebimento" : "pagamento"} · relacionamento · próximos passos`}
          prompt={prompt}
          idleCta="✦ Perguntar"/>
      </div>
    );
  }
  window.FinAiPartyContext = FinAiPartyContext;

  // ─────────────────────────────────────────────────────────────
  // 3) PANEL — usado no Drawer
  // ─────────────────────────────────────────────────────────────
  function FinAiPanel({ row }) {
    return (
      <section className="fin-ai-panel">
        <div className="vd-ai-banner">
          <span className="vd-ai-banner-ic">✦</span>
          <div>
            <b>IA copiloto</b>
            <small>Anomalia · relacionamento com contraparte · resumo executivo. IA propõe, Eliana decide.</small>
          </div>
        </div>
        <h3>Contraparte · {row.party}</h3>
        <FinAiPartyContext row={row}/>
      </section>
    );
  }
  window.FinAiPanel = FinAiPanel;

  // ─────────────────────────────────────────────────────────────
  // 4) MONTH DIGEST — overlay com resumo executivo do mês
  // ─────────────────────────────────────────────────────────────
  function FinAiMonthDigest({ open, onClose }) {
    useEffect(() => {
      if (!open) return;
      const onKey = (e) => { if (e.key === "Escape") onClose(); };
      window.addEventListener("keydown", onKey);
      return () => window.removeEventListener("keydown", onKey);
    }, [open, onClose]);

    const rows = window.FIN_ROWS || [];
    const today = window.FIN_TODAY;

    // Snapshot do mês corrente
    const monthStart = today ? new Date(today.getFullYear(), today.getMonth(), 1) : null;
    const monthRows = monthStart ? rows.filter(r => (r.due >= monthStart) || (r.paid_at && r.paid_at >= monthStart)) : rows;
    const totalIn = monthRows.filter(r => r.kind === "receivable").reduce((s, r) => s + r.amount, 0);
    const totalOut = monthRows.filter(r => r.kind === "payable").reduce((s, r) => s + r.amount, 0);
    const recebido = monthRows.filter(r => r.kind === "receivable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const pago = monthRows.filter(r => r.kind === "payable" && r.paid_at).reduce((s, r) => s + r.amount, 0);
    const aReceber = totalIn - recebido;
    const aPagar = totalOut - pago;
    const saldo = recebido - pago;
    const previsto = saldo + aReceber - aPagar;
    const overdue = rows.filter(r => r.status === "atrasado");

    // Top categorias
    const byCat = {};
    monthRows.filter(r => r.kind === "payable").forEach(r => {
      byCat[r.category] = (byCat[r.category] || 0) + r.amount;
    });
    const topCats = Object.entries(byCat).sort((a,b) => b[1]-a[1]).slice(0, 5);
    const totalGasto = Object.values(byCat).reduce((s, v) => s + v, 0);

    // Anomalias
    const anomalias = monthRows.map(r => ({ r, a: window.finAiAnomalia(r) })).filter(x => x.a);

    const monthName = monthStart ? monthStart.toLocaleDateString("pt-BR", { month: "long", year: "numeric" }) : "Este mês";

    const aiPrompt = `Você é assistente financeiro da Oimpresso (gráfica). Em 3 frases curtas, gere um RESUMO EXECUTIVO do mês pra Eliana ler em 30s antes da reunião com Wagner. Seja direto, sem floreio.

Mês: ${monthName}
Saldo atual: ${_fmt(saldo)} (recebido ${_fmt(recebido)} - pago ${_fmt(pago)})
Saldo previsto fim mês: ${_fmt(previsto)} (+ ${_fmt(aReceber)} a receber, - ${_fmt(aPagar)} a pagar)
Maior categoria de gasto: ${topCats[0]?.[0]} · ${_fmt(topCats[0]?.[1] || 0)}
Lançamentos em atraso: ${overdue.length}
Anomalias detectadas: ${anomalias.length}

Comente em 3 frases:
1. Saúde financeira do mês
2. Onde está saindo mais dinheiro
3. Atenção pra próxima semana`;

    if (!open) return null;

    return (
      <div className="fin-digest-overlay" onClick={onClose}>
        <div className="fin-digest" onClick={e => e.stopPropagation()}>
          <header className="fin-digest-h">
            <span className="fin-digest-eyebrow">Resumo executivo · IA</span>
            <h2>{monthName.charAt(0).toUpperCase() + monthName.slice(1)}</h2>
            <button className="fin-digest-x" onClick={onClose} aria-label="Fechar (Esc)">×</button>
          </header>

          <div className="fin-digest-body">
            <section className="fin-digest-snapshot">
              <div className="fin-digest-card primary">
                <small>Saldo previsto fim do mês</small>
                <b className={previsto < 0 ? "neg" : "pos"}>{_fmt(previsto)}</b>
                <span>realizado {_fmt(saldo)} · pendente {_fmt(aReceber - aPagar)}</span>
              </div>
              <div className="fin-digest-card">
                <small>A receber</small>
                <b className="pos">{_fmt(aReceber)}</b>
                <span>{monthRows.filter(r => r.kind === "receivable" && !r.paid_at).length} lançamentos</span>
              </div>
              <div className="fin-digest-card">
                <small>A pagar</small>
                <b className="neg">{_fmt(aPagar)}</b>
                <span>{monthRows.filter(r => r.kind === "payable" && !r.paid_at).length} lançamentos</span>
              </div>
              <div className={`fin-digest-card ${overdue.length > 0 ? "warn" : ""}`}>
                <small>Em atraso</small>
                <b>{overdue.length}</b>
                <span>{_fmt(overdue.reduce((s,r) => s + r.amount, 0))} · atenção</span>
              </div>
            </section>

            <section className="fin-digest-section">
              <h3>Onde está saindo dinheiro</h3>
              <ul className="fin-digest-cats">
                {topCats.map(([cat, val]) => {
                  const pct = (val / totalGasto) * 100;
                  return (
                    <li key={cat}>
                      <span className="fin-digest-cat-l">{cat}</span>
                      <div className="fin-digest-cat-bar"><div style={{width: pct + "%"}}/></div>
                      <span className="fin-digest-cat-r">{_fmt(val)} <small>{pct.toFixed(0)}%</small></span>
                    </li>
                  );
                })}
              </ul>
            </section>

            {anomalias.length > 0 && (
              <section className="fin-digest-section">
                <h3>Anomalias detectadas <span className="fin-digest-tag">{anomalias.length}</span></h3>
                <ul className="fin-digest-anomalias">
                  {anomalias.slice(0, 5).map(({ r, a }, i) => (
                    <li key={i}>
                      <span className={`fin-digest-anomalia-tag ${a.kind === "high" ? "high" : "low"}`}>
                        {a.kind === "high" ? "↑" : "↓"} {Math.abs(a.pct).toFixed(0)}%
                      </span>
                      <div>
                        <b>{r.party}</b>
                        <small>{r.desc.slice(0, 60)}{r.desc.length > 60 ? "…" : ""} · {_fmt(r.amount)}</small>
                      </div>
                    </li>
                  ))}
                </ul>
              </section>
            )}

            <section className="fin-digest-section">
              <h3>Análise da IA</h3>
              <FinAiBlock
                title="Gerar resumo executivo"
                hint="3 frases · saúde financeira · maior gasto · atenção pra próxima semana"
                prompt={aiPrompt}
                idleCta="✦ Gerar"/>
            </section>
          </div>
        </div>
      </div>
    );
  }
  window.FinAiMonthDigest = FinAiMonthDigest;

})();
