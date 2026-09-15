// forja-cmdk.jsx — chrome de teclado: paleta de comandos (⌘K) e folha de atalhos (?).
// Extraído de forja-page.jsx (Onda 1).
const { useState: useStateK, useMemo: useMemoK, useEffect: useEffectK, useRef: useRefK } = React;
function FjCommandPalette({ commands, onClose }) {
  const [q, setQ] = useStateK("");
  const [i, setI] = useStateK(0);
  const [stack, setStack] = useStateK([]);
  const inputRef = useRefK(null);
  useEffectK(() => { inputRef.current?.focus(); }, []);
  const list = stack.length ? stack[stack.length - 1].children : commands;
  const filtered = useMemoK(() => { const t = q.trim().toLowerCase(); return !t ? list : list.filter(c => (c.label + " " + (c.sub || "")).toLowerCase().includes(t)); }, [q, list]);
  useEffectK(() => { setI(0); }, [q, stack]);
  const pick = (c) => { if (!c) return; if (c.children) { setStack(s => [...s, c]); setQ(""); } else { c.run(); onClose(); } };
  const onKey = (e) => {
    if (e.key === "ArrowDown") { e.preventDefault(); setI(x => Math.min(x + 1, filtered.length - 1)); }
    else if (e.key === "ArrowUp") { e.preventDefault(); setI(x => Math.max(x - 1, 0)); }
    else if (e.key === "Enter") { e.preventDefault(); pick(filtered[i]); }
    else if (e.key === "Backspace" && !q && stack.length) { e.preventDefault(); setStack(s => s.slice(0, -1)); }
    else if (e.key === "Escape") { e.preventDefault(); if (stack.length) setStack(s => s.slice(0, -1)); else onClose(); }
  };
  return (
    <div className="fj-pal-back" onClick={onClose}>
      <div className="fj-pal" onClick={e => e.stopPropagation()}>
        <div className="fj-pal-in">
          <I.search size={14}/>
          {stack.length > 0 && <span className="fj-pal-crumb">{stack[stack.length - 1].label} ›</span>}
          <input ref={inputRef} value={q} onChange={e => setQ(e.target.value)} onKeyDown={onKey} placeholder={stack.length ? "escolha…" : "Buscar issue, ADR, PR, onda — ou uma ação…"}/>
          <kbd>esc</kbd>
        </div>
        <ul className="fj-pal-list">
          {filtered.length === 0 && <li className="fj-pal-empty">Nada encontrado.</li>}
          {filtered.map((c, idx) => (
            <li key={c.id} className={"fj-pal-it" + (idx === i ? " sel" : "")} onMouseEnter={() => setI(idx)} onClick={() => pick(c)}>
              <span className={"fj-pal-kind fj-pal-kind-" + c.kind}>{c.kindLabel}</span>
              <span className="fj-pal-tx"><b>{c.label}</b>{c.sub && <small>{c.sub}</small>}</span>
              {c.children ? <span className="fj-pal-tag">›</span> : (c.tag && <span className="fj-pal-tag">{c.tag}</span>)}
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

function FjCheatSheet({ onClose }) {
  const rows = [
    ["J / K", "navegar issues"], ["↵ / e", "abrir issue"], ["/ ou c", "buscar"],
    ["p", "fixar no topo"], ["x", "selecionar"], ["→ / ←", "expandir épico"], ["v", "Lista / Quadro / Gantt"],
    ["⌘K", "paleta de comandos"], ["?", "esta ajuda"], ["Esc", "fechar"],
  ];
  return (
    <div className="fj-pal-back" onClick={onClose}>
      <div className="fj-cheat" onClick={e => e.stopPropagation()}>
        <div className="fj-cheat-head"><b>Atalhos</b><button className="icon-btn" onClick={onClose}><I.x size={13}/></button></div>
        <ul className="fj-cheat-list">
          {rows.map(([k, l], idx) => (<li key={idx}><span className="fj-cheat-keys">{k.split(" ").map((p, j) => p === "/" || p === "ou" ? <span key={j} className="fj-cheat-or">{p}</span> : <kbd key={j}>{p}</kbd>)}</span><span>{l}</span></li>))}
        </ul>
      </div>
    </div>
  );
}
window.FjCommandPalette = FjCommandPalette;
window.FjCheatSheet = FjCheatSheet;
