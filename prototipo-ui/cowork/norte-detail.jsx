// norte-detail.jsx — Método KB-9.75 · Refino #3 "Curadoria + guia"
// Drawer de registro: Histórico (C2) · Comentários inline (C5) · Cross-link #ref (G4) · Frescor/Conferir (C3/C4).
// Exporta window.NorteDetail = { RecordDrawer, clientRecord, saleRecord, genericRecord }.
(() => {
const { useState, useEffect, useRef } = React;

const Svg = ({s=14,w=2,children}) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">{children}</svg>;
const Ic = {
  x:(p)=><Svg {...p}><path d="M18 6L6 18M6 6l12 12"/></Svg>,
  star:(p)=><Svg {...p}><path d="M12 2l3 6.5 7 .9-5 4.8 1.3 7L12 18l-6.3 3.2L7 14.2 2 9.4l7-.9L12 2z"/></Svg>,
  print:(p)=><Svg {...p}><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></Svg>,
  check:(p)=><Svg {...p}><path d="M5 13l4 4 10-10"/></Svg>,
  link:(p)=><Svg {...p}><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></Svg>,
  edit:(p)=><Svg {...p}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></Svg>,
  plus:(p)=><Svg {...p}><path d="M12 5v14M5 12h14"/></Svg>,
  send:(p)=><Svg {...p}><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></Svg>,
  dot:(p)=><Svg {...p}><circle cx="12" cy="12" r="4"/></Svg>,
};
const initials=(n)=>n.split(" ").slice(0,2).map(s=>s[0]).join("").toUpperCase();

// rotas-alvo de cross-link conforme o prefixo do #ref
const linkRoute=(label)=>{
  const l=label.toLowerCase();
  if(l.includes("os ")||l.startsWith("#48")) return "oficina";
  if(l.includes("venda")||l.startsWith("#44")) return "vendas";
  if(l.includes("pedido")||l.startsWith("#pc")) return "compras";
  if(l.includes("nf")||l.includes("nota")) return "fiscal";
  if(l.includes("título")||l.includes("ft-")||l.includes("fatura")) return "financeiro";
  return null;
};

// ─── builders (dados grounded por tipo) ───
function clientRecord(r){
  return {
    kind:"Cliente", title:r.nm, sub:`${r.tp||"PJ"} · ${r.doc} · ${r.city}`, color:r.color||"var(--m-vendas)",
    fresc:r.fr, conferido:r.saldo===0,
    fields:[["Documento",r.doc],["Cidade",r.city],["OS no total",String(r.os)],["Saldo aberto",r.saldo?("R$ "+r.saldo.toLocaleString("pt-BR")):"—"]],
    links:[r.os?("OS #88"+(10+(r.os%9))):null, "Venda #44"+(60+(r.os%9))].filter(Boolean),
    timeline:[
      {who:"Larissa",act:"criou o cadastro",when:"há 3 meses",dot:"mute"},
      {who:"Sistema",act:`vinculou OS #88${10+(r.os%9)} ao cliente`,when:"há 2 sem",dot:"accent"},
      r.saldo?{who:"Financeiro",act:`gerou título de R$ ${r.saldo.toLocaleString("pt-BR")}`,when:"há 5 dias",dot:"neg"}:{who:"Eliana",act:"conferiu — sem pendência",when:"há 5 dias",dot:"pos"},
      {who:"Larissa",act:"abriu nova venda no balcão",when:"ontem",dot:"accent"},
    ],
    comments:[
      {who:"Eliana",txt:r.saldo?`Boleto vence essa semana — cobrar via Caixa. Ref #FT-${3300+(r.os%9)}.`:"Cliente bom pagador, libera crédito.",when:"há 2 dias"},
    ],
  };
}
function saleRecord(r){
  return {
    kind:"Venda", title:r.id+" · "+r.cli, sub:`${r.origem||"balcão"} · ${r.pay||"PIX"} · ${r.val}`, color:"var(--m-vendas)",
    fresc:["recente","hoje"], conferido:r.status!=="vencida",
    fields:[["Cliente",r.cli],["Origem",r.origem||"balcão"],["Pagamento",r.pay||"PIX"],["Total",r.val]],
    links:[r.origem==="oficina"?"OS #88"+(20+(r.i||0)):null,"NF-e 55","Cliente "+r.cli].filter(Boolean),
    timeline:[
      {who:r.origem==="oficina"?"Oficina":"Larissa",act:r.origem==="oficina"?"OS pronta gerou esta venda":"abriu a venda no balcão",when:"há 2h",dot:"accent"},
      {who:"Sistema",act:"reservou itens no estoque",when:"há 2h",dot:"mute"},
      {who:"Larissa",act:"confirmou o pagamento",when:"há 1h",dot:"pos"},
    ],
    comments:[],
  };
}
function genericRecord(mod, cols, row){
  const title=String(row[0]); const sub=cols.slice(1,3).map((c,i)=>row[i+1]).filter(x=>typeof x==="string").join(" · ");
  return {
    kind:mod, title, sub, color:"var(--m-vendas)",
    fresc:["recente","hoje"], conferido:true,
    fields:cols.map((c,i)=>[c, typeof row[i]==="object"?(row[i]&&row[i].t)||"—":String(row[i])]).slice(0,6),
    links:[title.startsWith("#")?title:null].filter(Boolean),
    timeline:[
      {who:"Larissa",act:"criou o registro",when:"há 1 dia",dot:"mute"},
      {who:"Sistema",act:"atualizou status",when:"há 3h",dot:"accent"},
    ],
    comments:[],
  };
}

const DOT={mute:"var(--text-4)",accent:"var(--accent)",pos:"var(--pos)",neg:"var(--neg)"};
const FC={recente:["var(--pos)","var(--pos-soft)"],recent:["var(--pos)","var(--pos-soft)"],fresc:["var(--accent)","var(--accent-soft)"],distante:["oklch(0.62 0.13 70)","var(--warn-soft)"],frio:["var(--text-4)","var(--sunken)"]};

// transforma #refs do texto em chips clicáveis
function withRefs(txt, onGo){
  const parts=txt.split(/(#[A-Za-zÀ-ú]*-?\d+|OS #\d+|NF-e \d+)/g);
  return parts.map((p,i)=>{
    if(/^(#|OS #|NF-e )/.test(p)){
      const route=linkRoute(p);
      return <button key={i} className="nd-ref" onClick={()=>route&&onGo&&onGo(route)}>{p}</button>;
    }
    return <React.Fragment key={i}>{p}</React.Fragment>;
  });
}

function RecordDrawer({record,onClose,onGo}){
  const [conf,setConf]=useState(false);
  const [comments,setComments]=useState([]);
  const [draft,setDraft]=useState("");
  const [fav,setFav]=useState(false);
  const inRef=useRef(null);

  const favKey=record?("norte.fav."+record.title):null;
  useEffect(()=>{ if(record){ setConf(!!record.conferido); setComments(record.comments||[]); setDraft(""); setFav(localStorage.getItem("norte.fav."+record.title)==="1"); } },[record]);
  useEffect(()=>{
    if(!record) return;
    const k=(e)=>{ if(e.key==="Escape") onClose(); };
    window.addEventListener("keydown",k); return ()=>window.removeEventListener("keydown",k);
  },[record]);

  if(!record) return null;
  const r=record;
  const go=(route)=>{ if(route){ onClose(); onGo&&onGo(route); } };
  const addComment=(e)=>{ e.preventDefault(); if(!draft.trim()) return; setComments(c=>[...c,{who:"Larissa",txt:draft,when:"agora"}]); setDraft(""); };
  const toggleFav=()=>{ setFav(f=>{ const nv=!f; if(nv) localStorage.setItem(favKey,"1"); else localStorage.removeItem(favKey); return nv; }); };
  const doPrint=()=>{ document.body.setAttribute("data-printing","record"); setTimeout(()=>{ window.print(); document.body.removeAttribute("data-printing"); },60); };
  const fc=FC[r.fresc&&r.fresc[0]]||FC.fresc;

  return (
    <div className="nd-back" onMouseDown={onClose}>
      <aside className="nd" style={{"--c":r.color}} onMouseDown={e=>e.stopPropagation()}>
        <div className="nd-h">
          <div className="nd-av">{initials(r.title)}</div>
          <div className="nd-ht">
            <div className="nd-kind">{r.kind}</div>
            <h3>{r.title}</h3>
            <div className="nd-sub">{r.sub}</div>
          </div>
          <div className="nd-actions">
            <button className={"nd-star"+(fav?" on":"")} onClick={toggleFav} title={fav?"Remover dos favoritos":"Favoritar"} aria-pressed={fav}><Ic.star s={16}/></button>
            <button className="nd-print" onClick={doPrint} title="Imprimir ficha"><Ic.print s={15}/></button>
            <button className="nd-x" onClick={onClose}><Ic.x s={16}/></button>
          </div>
        </div>

        <div className="nd-body">
          {/* frescor + conferir (C3/C4) + cross-links (G4) */}
          <div className="nd-meta">
            {r.fresc && <span className="nd-fresc" style={{color:fc[0],background:fc[1]}}><span className="d" style={{background:fc[0]}}/>{r.fresc[0]} · {r.fresc[1]}</span>}
            <button className={"nd-conf"+(conf?" on":"")} onClick={()=>setConf(c=>!c)}>
              <Ic.check s={12}/>{conf?"Conferido":"Marcar conferido"}
            </button>
          </div>
          {r.links&&r.links.length>0 && (
            <div className="nd-links">
              <span className="nd-links-l"><Ic.link s={11}/> Vínculos</span>
              {r.links.map((l,i)=>{ const route=linkRoute(l); return <button key={i} className="nd-ref" onClick={()=>go(route)}>{l}</button>; })}
            </div>
          )}

          {/* campos */}
          <div className="nd-fields">
            {r.fields.map((f,i)=><div key={i} className="nd-field"><div className="k">{f[0]}</div><div className="v">{f[1]}</div></div>)}
          </div>

          {/* histórico (C2) */}
          <div className="nd-sec-t">Histórico</div>
          <div className="nd-tl">
            {r.timeline.map((t,i)=>(
              <div key={i} className="nd-tl-row">
                <span className="nd-tl-dot" style={{background:DOT[t.dot]||DOT.mute}}/>
                <div className="nd-tl-body"><span className="nd-tl-txt"><b>{t.who}</b> {withRefs(t.act,go)}</span><span className="nd-tl-when">{t.when}</span></div>
              </div>
            ))}
          </div>

          {/* comentários inline (C5) */}
          <div className="nd-sec-t">Comentários <span className="nd-count">{comments.length}</span></div>
          <div className="nd-comments">
            {comments.length===0 && <div className="nd-noc">Sem comentários ainda. Anote algo pro time.</div>}
            {comments.map((c,i)=>(
              <div key={i} className="nd-com">
                <div className="nd-com-av">{initials(c.who)}</div>
                <div className="nd-com-b"><div className="nd-com-h"><b>{c.who}</b><span>{c.when}</span></div><div className="nd-com-txt">{withRefs(c.txt,go)}</div></div>
              </div>
            ))}
          </div>
        </div>

        <form className="nd-add" onSubmit={addComment}>
          <input ref={inRef} value={draft} onChange={e=>setDraft(e.target.value)} placeholder="Comentar… use #4821 pra vincular" aria-label="Novo comentário"/>
          <button type="submit" disabled={!draft.trim()} aria-label="Enviar"><Ic.send s={14}/></button>
        </form>

        {/* ficha branded só pra impressão (S3) */}
        <div className="np-sheet" aria-hidden="true">
          <div className="np-head">
            <div className="np-brand"><span className="np-logo">Oi</span><div><b>Oimpresso</b><small>ROTA LIVRE · Comunicação Visual</small></div></div>
            <div className="np-doc">{r.kind} · ficha<br/>{new Date().toLocaleDateString("pt-BR")}</div>
          </div>
          <h1 className="np-title">{r.title}</h1>
          <div className="np-subt">{r.sub}</div>
          <table className="np-fields"><tbody>
            {r.fields.map((f,i)=><tr key={i}><th>{f[0]}</th><td>{f[1]}</td></tr>)}
          </tbody></table>
          {r.timeline&&<>
            <div className="np-sec">Histórico</div>
            <ul className="np-tl">{r.timeline.map((t,i)=><li key={i}><b>{t.who}</b> {t.act} <span>· {t.when}</span></li>)}</ul>
          </>}
          {comments.length>0&&<>
            <div className="np-sec">Comentários</div>
            <ul className="np-com">{comments.map((c,i)=><li key={i}><b>{c.who}</b> <span>({c.when})</span>: {c.txt}</li>)}</ul>
          </>}
          <div className="np-foot">Gerado pelo Oimpresso ERP · {r.kind} · {r.title}</div>
        </div>
      </aside>
    </div>
  );
}

window.NorteDetail={ RecordDrawer, clientRecord, saleRecord, genericRecord };
})();
