// ⌘K — paleta de comandos sobre TODOS os destinos do menu (ADR 0180: "Cmd+K
// cobre o power-user que quer pular direto"). Usa o Command do DS vivo; sem ele,
// cai num fallback próprio com a mesma mecânica (filtro + ↑/↓ + ↵ + esc).
(() => {
  const { useState, useEffect, useMemo, useRef } = React;
  const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

  const podeVer = (papel, id) => {
    const lista = (window.MOCK.SIDEBAR_PAPEIS || {})[papel];
    return !lista || lista.indexOf(id) >= 0;
  };
  // O Command do DS aceita `icon` como NODE — sem ele o tile fica vazio.
  const ico = (name) => {
    const C = (window.I || {})[name];
    return C ? React.createElement(C, { className: "ic" }) : undefined;
  };
  const hintOf = (id) => {
    const st = (window.MOCK.ROUTE_STATE || {})[id];
    return st ? (window.MOCK.ROUTE_STATE_LABEL || {})[st] : undefined;
  };

  // Grupos da paleta espelham os grupos do sidebar; ghosts entram como "Hub · Ghost".
  function buildGroups(papel, onSelect) {
    const meta = window.MOCK.GROUP_META || {};
    const groups = [];
    const atalhos = [];
    (window.MOCK.MENU || []).forEach((entry) => {
      if (!entry.group) {
        if (podeVer(papel, entry.id)) atalhos.push({ id: entry.id, label: entry.label, icon: ico(entry.icon), hint: hintOf(entry.id), onSelect: () => onSelect(entry.id) });
        return;
      }
      const items = [];
      entry.items.filter((it) => podeVer(papel, it.id)).forEach((it) => {
        items.push({ id: it.id, label: it.label, icon: ico(it.icon), hint: hintOf(it.id), onSelect: () => onSelect(it.id) });
        (it.ghosts || []).forEach((g) => items.push({
          id: g.id, label: `${it.label} · ${g.label}`, icon: ico(g.icon), hint: hintOf(g.id), onSelect: () => onSelect(g.id) }));
      });
      if (items.length) groups.push({ label: (meta[entry.group] || {}).label || entry.group, items });
    });
    const rodape = [];
    [...(window.MOCK.USER_MENU || []), ...(window.MOCK.FOOTER_LINKS || [])].forEach((it) =>
    rodape.push({ id: it.id, label: it.label, icon: ico(it.icon), hint: hintOf(it.id), onSelect: () => onSelect(it.id) }));
    (window.MOCK.SUPERADMIN_MENU || []).forEach((it) => {
      rodape.push({ id: it.id, label: it.label, icon: ico(it.icon), hint: hintOf(it.id), onSelect: () => onSelect(it.id) });
      (it.ghosts || []).forEach((g) => rodape.push({ id: g.id, label: `${it.label} · ${g.label}`, icon: ico(g.icon), hint: hintOf(g.id), onSelect: () => onSelect(g.id) }));
    });
    return [
    { label: "Atalhos", items: atalhos },
    ...groups,
    { label: "Sistema e plataforma", items: rodape }].
    filter((g) => g.items.length);
  }

  function Fallback({ groups, onClose }) {
    const [q, setQ] = useState("");
    const [sel, setSel] = useState(0);
    const inputRef = useRef(null);
    useEffect(() => {inputRef.current?.focus();}, []);
    const flat = useMemo(() => {
      const norm = (s) => s.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
      const t = norm(q.trim());
      const out = [];
      groups.forEach((g) => {
        const hits = t ? g.items.filter((i) => norm(i.label).includes(t) || norm(i.id).includes(t)) : g.items;
        if (hits.length) out.push({ group: g.label, items: hits });
      });
      return out;
    }, [q, groups]);
    const linear = flat.flatMap((g) => g.items);
    useEffect(() => {setSel(0);}, [q]);
    const onKey = (e) => {
      if (e.key === "ArrowDown") {e.preventDefault();setSel((s) => Math.min(s + 1, linear.length - 1));}
      if (e.key === "ArrowUp") {e.preventDefault();setSel((s) => Math.max(s - 1, 0));}
      if (e.key === "Enter") {e.preventDefault();linear[sel]?.onSelect?.();onClose();}
      if (e.key === "Escape") {e.preventDefault();onClose();}
    };
    let idx = -1;
    return (
      <div className="cmdk-scrim" onMouseDown={(e) => {if (e.target === e.currentTarget) onClose();}}>
        <div className="cmdk" onKeyDown={onKey}>
          <input ref={inputRef} className="cmdk-input" placeholder="Ir para uma tela…" value={q} onChange={(e) => setQ(e.target.value)} />
          <div className="cmdk-list">
            {linear.length === 0 && <div className="cmdk-empty">Nenhuma tela com esse nome.</div>}
            {flat.map((g) =>
            <div key={g.group}>
                <div className="cmdk-group">{g.group}</div>
                {g.items.map((it) => {
                idx += 1;
                const active = idx === sel;
                return (
                  <div key={it.id} className={"cmdk-item" + (active ? " active" : "")}
                  onMouseMove={() => setSel(linear.indexOf(it))}
                  onClick={() => {it.onSelect?.();onClose();}}>
                      <span className="cmdk-label">{it.label}</span>
                      {it.hint && <span className="cmdk-hint">{it.hint}</span>}
                      <span className="cmdk-id">{it.id}</span>
                    </div>);

              })}
              </div>
            )}
          </div>
          <div className="cmdk-foot"><span>↑↓ navegar</span><span>↵ abrir</span><span>esc fechar</span></div>
        </div>
      </div>);

  }

  function CommandPalette({ open, onClose, onSelect, papel }) {
    const groups = useMemo(() => buildGroups(papel, (id) => {onSelect(id);onClose();}), [papel, onSelect, onClose]);
    const Cmd = ds().Command;
    if (!open) return null;
    if (Cmd) return <Cmd open={open} onClose={onClose} groups={groups} />;
    return <Fallback groups={groups} onClose={onClose} />;
  }

  window.CommandPalette = CommandPalette;
})();
