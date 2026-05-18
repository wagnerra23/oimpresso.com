// financeiro-curation.jsx — Refino #1 KB-9.75 · Curadoria (2026-05-18)
// 4 features espelhando vendas-curation.jsx:
// - useFinComments: thread de comentários por lançamento (Eliana ↔ produção)
// - useFinConferido: marca paritária "já conferi" (toggle Eliana)
// - finAuditTrail: histórico determinístico criação/edição/conciliação
// - FinPillFrescor: pill de SLA (novo / a vencer / vencendo / atrasado / pago)

(() => {
  const { useState, useEffect, useMemo } = React;

  // ─────────────────────────────────────────────────────────────
  // 1) Comentários inline por lançamento
  //   Storage: oimpresso.financeiro.comments = { rowId: [{author,text,when}] }
  // ─────────────────────────────────────────────────────────────
  const _finLoadComments = () => {
    try { return JSON.parse(localStorage.getItem("oimpresso.financeiro.comments") || "{}"); }
    catch (e) { return {}; }
  };
  const _finSaveComments = (m) => {
    try { localStorage.setItem("oimpresso.financeiro.comments", JSON.stringify(m)); } catch (e) {}
  };

  function useFinComments() {
    const [m, setM] = useState(_finLoadComments);
    useEffect(() => { _finSaveComments(m); }, [m]);
    return {
      get: (rowId) => m[rowId] || [],
      add: (rowId, text, author) => setM(prev => ({
        ...prev,
        [rowId]: [...(prev[rowId] || []), { author: author || "Eliana", text, when: new Date().toLocaleString("pt-BR", { day:"2-digit", month:"2-digit", hour:"2-digit", minute:"2-digit" }) }],
      })),
      remove: (rowId, idx) => setM(prev => {
        const next = { ...prev };
        next[rowId] = (next[rowId] || []).filter((_, i) => i !== idx);
        if (next[rowId].length === 0) delete next[rowId];
        return next;
      }),
      countFor: (rowId) => (m[rowId] || []).length,
      hasAny: () => Object.keys(m).length > 0,
    };
  }
  window.useFinComments = useFinComments;

  // ─────────────────────────────────────────────────────────────
  // 2) "Conferido" — toggle paritário de Eliana
  //   Storage: oimpresso.financeiro.conferido = ["R-2641", ...]
  // ─────────────────────────────────────────────────────────────
  const _finLoadConferido = () => {
    try { return new Set(JSON.parse(localStorage.getItem("oimpresso.financeiro.conferido") || "[]")); }
    catch (e) { return new Set(); }
  };
  const _finSaveConferido = (s) => {
    try { localStorage.setItem("oimpresso.financeiro.conferido", JSON.stringify([...s])); } catch (e) {}
  };

  function useFinConferido() {
    const [set, setSet] = useState(_finLoadConferido);
    useEffect(() => { _finSaveConferido(set); }, [set]);
    return {
      has: (id) => set.has(id),
      toggle: (id, who) => {
        setSet(prev => {
          const next = new Set(prev);
          if (next.has(id)) next.delete(id); else next.add(id);
          return next;
        });
      },
      count: set.size,
    };
  }
  window.useFinConferido = useFinConferido;

  // ─────────────────────────────────────────────────────────────
  // 3) Audit trail determinístico (mock — em produção viria do backend)
  // ─────────────────────────────────────────────────────────────
  function finAuditTrail(row) {
    const seed = (row.id || "").charCodeAt(row.id.length - 1) % 4;
    const entries = [];
    // Sempre: criação
    const dueDate = row.due instanceof Date ? row.due.toLocaleDateString("pt-BR") : row.due;
    entries.push({
      when: `${row.due.toLocaleDateString("pt-BR", { day:"2-digit", month:"2-digit" })} ${row.kind === "receivable" ? "emitido" : "recebido"}`,
      who: row.kind === "receivable" ? "Bruna Vendas" : "Suprigraf · NF",
      kind: "create",
      desc: `Lançamento ${row.id} · ${row.desc.slice(0, 48)}${row.desc.length > 48 ? "…" : ""}`,
    });
    // Categorização (sempre)
    entries.push({
      when: `${row.due.toLocaleDateString("pt-BR", { day:"2-digit", month:"2-digit" })} ${("0" + (row.due.getHours() + 1)).slice(-2)}:${("0" + row.due.getMinutes()).slice(-2)}`,
      who: "Eliana Financeiro",
      kind: "categorize",
      desc: `Classificado em "${row.category}"`,
    });
    // Edição de valor — só se seed >= 2
    if (seed >= 2) {
      const oldAmount = Math.round(row.amount * 0.94 * 100) / 100;
      const diff = row.amount - oldAmount;
      entries.push({
        when: `${row.due.toLocaleDateString("pt-BR", { day:"2-digit", month:"2-digit" })} ajuste`,
        who: "Eliana Financeiro",
        kind: "edit",
        desc: `Valor revisado · R$ ${oldAmount.toFixed(2)} → R$ ${row.amount.toFixed(2)}`,
        diff: { from: oldAmount, to: row.amount, pct: (diff / oldAmount * 100) },
      });
    }
    // Conciliação — só se pago
    if (row.paid_at) {
      entries.push({
        when: row.paid_at.toLocaleDateString("pt-BR"),
        who: "Banco (Inter)",
        kind: "concil",
        desc: `Conciliado com extrato · ${row.channel || "—"}`,
      });
    }
    // Atrasado
    if (row.status === "atrasado") {
      entries.push({
        when: "agora",
        who: "sistema",
        kind: "alert",
        desc: `⚠ Vencimento ultrapassou — em atraso`,
      });
    }
    return entries;
  }
  window.finAuditTrail = finAuditTrail;

  function FinAuditTrail({ row }) {
    const entries = useMemo(() => finAuditTrail(row), [row.id, row.paid_at]);
    const KIND_LABEL = { create: "criou", categorize: "categorizou", edit: "editou", concil: "conciliou", alert: "alerta" };
    const KIND_IC = { create: "+", categorize: "▦", edit: "✎", concil: "≣", alert: "⚠" };
    return (
      <div className="fin-audit">
        <div className="fin-audit-h">
          <h4>Histórico</h4>
          <small>{entries.length} eventos · auditoria contábil</small>
        </div>
        <ul className="fin-audit-list">
          {entries.map((e, i) => (
            <li key={i} className={`fin-audit-row fin-audit-${e.kind}`}>
              <span className="fin-audit-ic">{KIND_IC[e.kind] || "·"}</span>
              <div className="fin-audit-body">
                <header>
                  <b>{e.who}</b>
                  <span className="fin-audit-action">{KIND_LABEL[e.kind] || ""}</span>
                  <time>{e.when}</time>
                </header>
                <p>{e.desc}</p>
                {e.diff && (
                  <div className="fin-audit-diff">
                    <span className="diff-from">R$ {e.diff.from.toFixed(2)}</span>
                    <span className="diff-arr">→</span>
                    <span className="diff-to">R$ {e.diff.to.toFixed(2)}</span>
                    <span className={`diff-pct ${e.diff.pct < 0 ? "neg" : "pos"}`}>
                      {e.diff.pct > 0 ? "+" : ""}{e.diff.pct.toFixed(1)}%
                    </span>
                  </div>
                )}
              </div>
            </li>
          ))}
        </ul>
      </div>
    );
  }
  window.FinAuditTrail = FinAuditTrail;

  // ─────────────────────────────────────────────────────────────
  // 4) Pill de frescor (novo · a vencer · vencendo · atrasado · pago)
  // ─────────────────────────────────────────────────────────────
  function finFrescorInfo(row) {
    if (row.paid_at) {
      const days = Math.round((window.FIN_TODAY - row.paid_at) / 86400000);
      return { kind: "paid", label: days === 0 ? "pago hoje" : `pago ${days}d atrás`, ic: "✓" };
    }
    const daysUntil = Math.round((row.due - window.FIN_TODAY) / 86400000);
    if (daysUntil < 0) return { kind: "overdue", label: `${-daysUntil}d em atraso`, ic: "✕" };
    if (daysUntil === 0) return { kind: "today", label: "vence hoje", ic: "●" };
    if (daysUntil <= 3) return { kind: "warning", label: `${daysUntil}d`, ic: "▲" };
    if (daysUntil <= 7) return { kind: "soon", label: `${daysUntil}d`, ic: "○" };
    return { kind: "fresh", label: `${daysUntil}d`, ic: "○" };
  }
  window.finFrescorInfo = finFrescorInfo;

  function FinPillFrescor({ row, compact }) {
    const s = finFrescorInfo(row);
    return (
      <span className={`fin-frescor fin-frescor-${s.kind}`} title={s.label}>
        <span className="fin-frescor-ic">{s.ic}</span>
        {!compact && <span className="fin-frescor-lbl">{s.label}</span>}
      </span>
    );
  }
  window.FinPillFrescor = FinPillFrescor;

  // ─────────────────────────────────────────────────────────────
  // 5) FinConferidoToggle — botão grande no drawer
  // ─────────────────────────────────────────────────────────────
  function FinConferidoToggle({ row, conferido }) {
    const isOn = conferido.has(row.id);
    return (
      <button
        className={`fin-conferido-toggle ${isOn ? "on" : ""}`}
        onClick={() => conferido.toggle(row.id, "Eliana")}
        title={isOn ? "Desmarcar conferido (Eliana já bateu olho)" : "Marcar como conferido"}>
        <span className="fin-conf-check">{isOn ? "✓" : ""}</span>
        <span className="fin-conf-lbl">{isOn ? "Conferido" : "Conferir"}</span>
        {!isOn && <small>Eliana valida</small>}
      </button>
    );
  }
  window.FinConferidoToggle = FinConferidoToggle;

  // ─────────────────────────────────────────────────────────────
  // 6) Comments thread (compact)
  // ─────────────────────────────────────────────────────────────
  function FinCommentsThread({ rowId, comments }) {
    const [text, setText] = useState("");
    const list = comments.get(rowId);
    const submit = () => {
      const t = text.trim();
      if (!t) return;
      comments.add(rowId, t, "Eliana");
      setText("");
    };
    return (
      <div className="fin-comments">
        <div className="fin-comments-h">
          <h4>Comentários</h4>
          <small>{list.length} · visíveis pra Eliana · Wagner · Bruna</small>
        </div>
        {list.length > 0 && (
          <ul className="fin-comments-list">
            {list.map((c, i) => (
              <li key={i} className="fin-comment">
                <span className="fin-comment-av">{(c.author || "?").charAt(0).toUpperCase()}</span>
                <div className="fin-comment-body">
                  <header>
                    <b>{c.author}</b>
                    <time>{c.when}</time>
                    <button className="fin-comment-x" onClick={() => comments.remove(rowId, i)} title="Remover">×</button>
                  </header>
                  <p>{c.text}</p>
                </div>
              </li>
            ))}
          </ul>
        )}
        <div className="fin-comment-new">
          <textarea
            value={text} rows={2}
            onChange={e => setText(e.target.value)}
            onKeyDown={e => { if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) { e.preventDefault(); submit(); } }}
            placeholder='Ex: "Conferi com Bruna, valor correto" · "Anexar comprovante" · ⌘↵ envia'/>
          <button onClick={submit} disabled={!text.trim()}>Comentar</button>
        </div>
      </div>
    );
  }
  window.FinCommentsThread = FinCommentsThread;

})();
