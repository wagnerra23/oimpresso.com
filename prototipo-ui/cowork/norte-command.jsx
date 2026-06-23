// norte-command.jsx — Método KB-9.75 · Refino #1 "Fundação extra"
// ⌘K command palette (N2) + cheat-sheet de atalhos (P2 atalhos visíveis).
// Exporta window.NorteCommand = { CommandPalette, CheatSheet, useCmdKeys }.
(() => {
const { useState, useEffect, useRef, useMemo } = React;

const Svg = ({s=14,w=2,children}) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">{children}</svg>;
const Ic = {
  search:(p)=><Svg {...p}><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></Svg>,
  arrow:(p)=><Svg {...p}><path d="M5 12h14M13 6l6 6-6 6"/></Svg>,
  corner:(p)=><Svg {...p}><path d="M9 10l-5 5 5 5"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/></Svg>,
  bolt:(p)=><Svg {...p}><path d="M13 2L4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z"/></Svg>,
};
const norm=(s)=>s.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"");

// ─── ⌘K Command Palette ───
function CommandPalette({open,onClose,nav,onGo,actions}){
  const [q,setQ]=useState("");
  const [sel,setSel]=useState(0);
  const inputRef=useRef(null);
  const listRef=useRef(null);

  // achata: ações primeiro, depois módulos
  const all=useMemo(()=>{
    const mods=[];
    nav.forEach(g=>g.items.forEach(it=>mods.push({type:"mod",id:it.id,label:it.label,sec:g.sec,n:it.n})));
    const acts=(actions||[]).map(a=>({type:"act",...a}));
    return [...acts,...mods];
  },[nav,actions]);

  const results=useMemo(()=>{
    const nq=norm(q.trim());
    if(!nq) return all;
    return all.filter(x=>norm(x.label+" "+(x.sec||"")+" "+(x.hint||"")).includes(nq));
  },[q,all]);

  useEffect(()=>{ if(open){ setQ(""); setSel(0); setTimeout(()=>inputRef.current&&inputRef.current.focus(),20); } },[open]);
  useEffect(()=>{ setSel(0); },[q]);
  useEffect(()=>{
    if(!open) return;
    const row=listRef.current&&listRef.current.children[sel];
    if(row&&row.scrollIntoView) row.scrollIntoView({block:"nearest"});
  },[sel,open]);

  if(!open) return null;
  const run=(x)=>{ onClose(); if(x.type==="mod") onGo(x.id); else if(x.run) x.run(); };
  const onKey=(e)=>{
    if(e.key==="ArrowDown"){ e.preventDefault(); setSel(s=>Math.min(s+1,results.length-1)); }
    else if(e.key==="ArrowUp"){ e.preventDefault(); setSel(s=>Math.max(s-1,0)); }
    else if(e.key==="Enter"){ e.preventDefault(); results[sel]&&run(results[sel]); }
    else if(e.key==="Escape"){ e.preventDefault(); onClose(); }
  };

  return (
    <div className="nck-back" onMouseDown={onClose}>
      <div className="nck" onMouseDown={e=>e.stopPropagation()}>
        <div className="nck-in">
          <Ic.search s={16}/>
          <input ref={inputRef} value={q} onChange={e=>setQ(e.target.value)} onKeyDown={onKey} placeholder="Ir para módulo ou rodar ação…" aria-label="Comando"/>
          <kbd>esc</kbd>
        </div>
        <div className="nck-list" ref={listRef}>
          {results.length===0 && <div className="nck-empty">Nada encontrado para “{q}”.</div>}
          {results.map((x,i)=>(
            <button key={x.type+(x.id||x.label)} className={"nck-row"+(i===sel?" on":"")} onMouseEnter={()=>setSel(i)} onClick={()=>run(x)}>
              <span className={"nck-ic "+(x.type==="act"?"act":"mod")}>{x.type==="act"?<Ic.bolt s={13}/>:<span className="nck-dot"/>}</span>
              <span className="nck-lbl">{x.label}</span>
              {x.type==="mod" && <span className="nck-sec">{x.sec}</span>}
              {x.type==="act" && x.hint && <span className="nck-hint">{x.hint}</span>}
              <span className="nck-go"><Ic.corner s={13}/></span>
            </button>
          ))}
        </div>
        <div className="nck-foot">
          <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span>
          <span><kbd>↵</kbd> abrir</span>
          <span><kbd>esc</kbd> fechar</span>
          <span className="nck-foot-r">{results.length} resultado{results.length===1?"":"s"}</span>
        </div>
      </div>
    </div>
  );
}

// ─── Cheat-sheet de atalhos (?) ───
const SHORTCUTS=[
  {g:"Navegação",items:[["⌘ K","Paleta de comandos — ir a qualquer módulo"],["/","Focar a busca da tela"],["J / K","Próxima / anterior linha da lista"],["↵","Abrir a linha em foco"],["G então I","Ir para o Início"]]},
  {g:"Ação",items:[["A","Abrir a Norte IA (copiloto)"],["N","Novo registro na tela atual"],["B","Buscar global"],["↵","Abrir a linha em foco"],["Esc","Fechar painel / limpar foco"]]},
  {g:"Exibição",items:[["D","Alternar Denso / Amplo"],["T","Alternar Claro / Escuro"],["[","Recolher / expandir a sidebar"],["?","Mostrar este guia de atalhos"]]},
];
function CheatSheet({open,onClose}){
  useEffect(()=>{
    if(!open) return;
    const k=(e)=>{ if(e.key==="Escape") onClose(); };
    window.addEventListener("keydown",k); return ()=>window.removeEventListener("keydown",k);
  },[open]);
  if(!open) return null;
  return (
    <div className="ncheat-back" onMouseDown={onClose}>
      <div className="ncheat" onMouseDown={e=>e.stopPropagation()}>
        <div className="ncheat-h">
          <div><b>Atalhos de teclado</b><small>Método KB-9.75 · operação sem mouse</small></div>
          <button className="ncheat-x" onClick={onClose}>esc</button>
        </div>
        <div className="ncheat-grid">
          {SHORTCUTS.map((s,i)=>(
            <div key={i} className="ncheat-col">
              <div className="ncheat-sec">{s.g}</div>
              {s.items.map((r,j)=>(
                <div key={j} className="ncheat-row">
                  <span className="ncheat-keys">{r[0].split(" ").map((k,n)=><kbd key={n}>{k}</kbd>)}</span>
                  <span className="ncheat-desc">{r[1]}</span>
                </div>
              ))}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

window.NorteCommand={ CommandPalette, CheatSheet };
})();
