// forja-novo-issue.jsx — composer de issue com auto-sugest revisável (o agente propõe
// tipo/fase/papel/onda; [W] revisa). Extraído de forja-mcp.jsx (Onda 3).
const { useState: useStateNi, useMemo: useMemoNi, useRef: useRefNi, useEffect: useEffectNi } = React;
// ─── Novo issue: composer com auto-sugest revisável ───
function ForjaNewIssue({ onCreate, onClose }) {
  const [title, setTitle] = useStateNi("");
  const sug = useMemoNi(() => forjaSuggest(title), [title]);
  const [over, setOver] = useStateNi({});
  const inRef = useRefNi(null);
  useEffectNi(() => { inRef.current?.focus(); }, []);
  const val = (k) => over[k] !== undefined ? over[k] : sug[k];
  const TYPES = window.FORJA.TYPES, PHASES = window.FORJA.PHASES, ACTORS = window.FORJA.ACTORS, ONDAS = window.FORJA.ONDAS;

  const create = () => {
    if (!title.trim()) return;
    onCreate({
      id: "FORJA-" + (143 + Math.floor(Math.random() * 50)),
      frescor: "inferido", titulo: title.trim(), tipo: val("tipo"), prio: "P2", fase: val("fase"), estado: "triagem",
      assignee: val("assignee"), onda: val("onda"), modulo: "Sistema", origem: "agente_mcp",
      vinculos: [], bloqueado_por: [], desc: "Proposto via auto-sugest — vai pra Triagem; analista enriquece, [W] aprova.",
      subtarefas: [], criados: "agente · agora", atualizado: "agente · agora",
      atividade: [{ ator: "CC", t: "issue proposto via auto-sugest (revisado)", quando: "agora" }],
    });
    onClose();
  };

  const Chip = ({ field, options, render }) => (
    <div className="fj-sug-row">
      <span className="fj-sug-lbl">{field}</span>
      <div className="fj-sug-opts">
        {options.map(o => (
          <button key={o.k} className={"fj-sug-chip" + (val(field) === o.k ? " on" : "")} onClick={() => setOver(s => ({ ...s, [field]: o.k }))}>{render(o)}</button>
        ))}
      </div>
    </div>
  );

  return (
    <div className="fj-pal-back" onClick={onClose}>
      <div className="fj-new" onClick={e => e.stopPropagation()}>
        <div className="fj-new-head"><span className="fj-ia-spark">✦</span><b>Novo issue</b><span className="fj-new-note">auto-sugest revisável · IA propõe, você decide</span><button className="icon-btn" onClick={onClose}><I.x size={13}/></button></div>
        <div className="fj-new-body">
          <input ref={inRef} className="fj-new-title" value={title} onChange={e => setTitle(e.target.value)}
                 placeholder="Título do issue…  (ex.: 'gate de cor crua no e2e')" onKeyDown={e => e.key === "Enter" && create()}/>
          <Chip field="tipo" options={Object.entries(TYPES).map(([k, v]) => ({ k, label: v.label }))} render={o => o.label}/>
          <Chip field="fase" options={PHASES.map(p => ({ k: p.id, label: p.id }))} render={o => o.label}/>
          <Chip field="assignee" options={Object.keys(ACTORS).map(k => ({ k }))} render={o => "[" + o.k + "]"}/>
          <Chip field="onda" options={[{ k: null }].concat(ONDAS.map(o => ({ k: o.id })))} render={o => o.k ? "~" + o.k : "sem onda"}/>
        </div>
        <div className="fj-new-foot">
          <span className="fj-foot-note">vai pra Triagem · analista enriquece · [W] aprova</span>
          <button className="os-btn primary" onClick={create} disabled={!title.trim()}>Mandar pra triagem</button>
        </div>
      </div>
    </div>
  );
}
window.ForjaNewIssue = ForjaNewIssue;
