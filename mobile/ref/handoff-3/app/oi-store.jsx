// ────────────────────────────────────────────────
// OIStore — store compartilhado + persistência (localStorage)
// Resolve: mutações refletindo entre lista/detalhe e sobrevivendo ao refresh.
// Uso:  const [val, setVal] = window.useStore("chave", initialFn)
//       setVal(novo) ou setVal(prev => próximo)  → re-renderiza TODOS os
//       componentes inscritos na mesma chave e persiste em localStorage.
// ────────────────────────────────────────────────
(() => {
  const PREFIX = "oimpresso.";
  const VERSION = "v1"; // mude para invalidar dados persistidos antigos
  try {
    if (localStorage.getItem(PREFIX + "__version") !== VERSION) {
      Object.keys(localStorage)
        .filter(k => k.startsWith(PREFIX))
        .forEach(k => localStorage.removeItem(k));
      localStorage.setItem(PREFIX + "__version", VERSION);
    }
  } catch (e) { /* sem storage — segue em memória */ }

  const state = {};
  const subs = {};

  const init = (key, initial) => {
    if (key in state) return state[key];
    let v;
    try {
      const raw = localStorage.getItem(PREFIX + key);
      v = raw != null ? JSON.parse(raw) : undefined;
    } catch (e) { v = undefined; }
    if (v === undefined) v = typeof initial === "function" ? initial() : initial;
    state[key] = v;
    return v;
  };

  const get = (key) => state[key];

  const set = (key, next) => {
    const v = typeof next === "function" ? next(state[key]) : next;
    state[key] = v;
    try { localStorage.setItem(PREFIX + key, JSON.stringify(v)); } catch (e) {}
    (subs[key] || []).slice().forEach(fn => fn(v));
  };

  const subscribe = (key, fn) => {
    (subs[key] = subs[key] || []).push(fn);
    return () => { subs[key] = (subs[key] || []).filter(f => f !== fn); };
  };

  window.OIStore = { init, get, set, subscribe };

  window.useStore = (key, initial) => {
    const [val, setVal] = React.useState(() => init(key, initial));
    React.useEffect(() => subscribe(key, setVal), [key]);
    const setter = React.useCallback((next) => set(key, next), [key]);
    return [val, setter];
  };
})();

// ────────────────────────────────────────────────
// OISheet — bottom sheet genérico (usa .oi-sheet-* dos tokens)
// <OISheet title="…" onClose={…}> conteúdo </OISheet>
// ────────────────────────────────────────────────
function OISheet({ title, onClose, children, footer }) {
  return (
    <div className="oi-sheet-backdrop" onClick={onClose}>
      <div className="oi-sheet" onClick={e => e.stopPropagation()}>
        <div className="oi-sheet-grip"></div>
        <div className="oi-sheet-h">
          <b>{title}</b>
          <button className="oi-iconbtn close" aria-label="Fechar" onClick={onClose}><window.Ic.x /></button>
        </div>
        <div style={{ padding: "14px 16px 18px" }}>{children}</div>
        {footer && (
          <div style={{ padding: "0 16px 18px" }}>{footer}</div>
        )}
      </div>
    </div>
  );
}
window.OISheet = OISheet;

// ────────────────────────────────────────────────
// Helpers de domínio compartilhados
// ────────────────────────────────────────────────

// Tarefas: mutações por cima do dataset base (concluídas, adiadas, criadas)
window.OITasks = {
  EMPTY_MUT: { done: [], snooze: {}, created: [] },
  // aplica mutações a uma lista base de tarefas
  apply(base, mut) {
    const m = mut || window.OITasks.EMPTY_MUT;
    const snoozeLabel = { amanha: "Amanhã", semana: "Esta semana" };
    const out = base
      .filter(t => !m.done.includes(t.id))
      .map(t => m.snooze[t.id]
        ? { ...t, bucket: m.snooze[t.id], when: snoozeLabel[m.snooze[t.id]] || t.when, urgent: false }
        : t);
    return out.concat((m.created || []).filter(t => !m.done.includes(t.id)));
  },
};

// Pedidos: pipeline canônico + overrides de etapa + criados
window.OIPedidos = {
  ORDER: ["orc", "aprov", "prod", "faturar", "entrega", "done"],
  PROGRESS: { orc: 0.1, aprov: 0.28, prod: 0.5, faturar: 0.72, entrega: 0.9, done: 1 },
  LABEL: { orc: "Orçamento", aprov: "Aguardando aprovação", prod: "Em produção", faturar: "Pronto p/ faturar", entrega: "Saiu para entrega", done: "Concluído" },
  NEXT_CTA: { orc: "Aprovar orçamento", aprov: "Aprovar arte", prod: "Finalizar produção", faturar: "Faturar pedido", entrega: "Confirmar entrega" },
  EMPTY_MUT: { etapas: {}, created: [] },
  apply(base, mut) {
    const m = mut || window.OIPedidos.EMPTY_MUT;
    const upd = base.map(p => {
      const k = m.etapas[p.id];
      if (!k) return p;
      return { ...p, etapaKey: k, etapa: window.OIPedidos.LABEL[k], progresso: window.OIPedidos.PROGRESS[k],
               atualizado: "agora", prazo: k === "done" ? "Entregue" : p.prazo, urgent: k === "done" ? false : p.urgent };
    });
    return [...(m.created || []).map(p => m.etapas[p.id]
      ? { ...p, etapaKey: m.etapas[p.id], etapa: window.OIPedidos.LABEL[m.etapas[p.id]], progresso: window.OIPedidos.PROGRESS[m.etapas[p.id]] }
      : p), ...upd];
  },
  advance(etapaKey) {
    const o = window.OIPedidos.ORDER;
    const i = o.indexOf(etapaKey);
    return i >= 0 && i < o.length - 1 ? o[i + 1] : etapaKey;
  },
};
