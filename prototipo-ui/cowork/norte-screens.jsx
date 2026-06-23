// norte-screens.jsx — corpos das telas do shell Norte (body-only). Exporta p/ window.
(() => {
const { useState, useMemo } = React;

const Svg = ({s=14,w=2,children}) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">{children}</svg>;
const I = {
  plus:(p)=><Svg {...p}><path d="M12 5v14M5 12h14"/></Svg>,
  search:(p)=><Svg {...p}><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></Svg>,
  chev:(p)=><Svg {...p}><path d="M6 9l6 6 6-6"/></Svg>,
  arrow:(p)=><Svg {...p}><path d="M5 12h14M13 6l6 6-6 6"/></Svg>,
  users:(p)=><Svg {...p}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></Svg>,
  wrench:(p)=><Svg {...p}><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.1-.5-.5-2.1 2.6-2.6z"/></Svg>,
  cart:(p)=><Svg {...p}><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12.5a1.7 1.7 0 0 0 1.7 1.3h8.6a1.7 1.7 0 0 0 1.7-1.3L23 7H6"/></Svg>,
  chat:(p)=><Svg {...p}><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.9 9.9 0 0 1-4-.8L3 21l1.9-4.5A8.4 8.4 0 1 1 21 11.5Z"/></Svg>,
  coins:(p)=><Svg {...p}><circle cx="9" cy="9" r="6"/><path d="M14.7 5.3A6 6 0 1 1 15 18.6"/></Svg>,
  home:(p)=><Svg {...p}><path d="M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10"/></Svg>,
  phone:(p)=><Svg {...p}><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/></Svg>,
  dot:(p)=><Svg {...p}><circle cx="12" cy="12" r="4"/></Svg>,
};
const BRL=(n)=>"R$ "+n.toLocaleString("pt-BR",{minimumFractionDigits:2,maximumFractionDigits:2});
const BRLk=(n)=>n>=1000?"R$ "+(n/1000).toFixed(1).replace(".",",")+"k":BRL(n);
const PURPLE="var(--m-vendas)";
const M={crm:PURPLE,oficina:PURPLE,vendas:PURPLE,inbox:PURPLE,fiscal:PURPLE,financeiro:PURPLE,compras:PURPLE,estoque:PURPLE};
const AVP=["oklch(0.55 0.13 268)","oklch(0.56 0.12 195)","oklch(0.58 0.12 65)","oklch(0.57 0.14 18)","oklch(0.55 0.12 150)","oklch(0.55 0.13 295)","oklch(0.55 0.12 235)","oklch(0.56 0.13 330)"];
const hash=(s)=>{let h=0;for(let i=0;i<s.length;i++)h=(h*31+s.charCodeAt(i))|0;return Math.abs(h);};
const ini=(n)=>n.split(" ").slice(0,2).map(s=>s[0]).join("").toUpperCase();
function Av({name,size}){ return <div className="nav-av" style={{background:AVP[hash(name)%AVP.length],width:size,height:size}}>{ini(name)}</div>; }

// ════════ a costura — band reusável ════════
function Costura({here,cap}){
  const FLOW=[
    {label:"Cliente",c:M.crm,v:"CRM"},
    {label:"OS",c:M.oficina,v:"Oficina"},
    {label:"Venda",c:M.vendas,v:"Vendas"},
    {label:"Nota",c:M.fiscal,v:"NF-e+NFS-e"},
    {label:"Financeiro",c:M.financeiro,v:"a receber"},
  ];
  const hi=FLOW.findIndex(f=>f.label===here);
  return (
    <div className="ncostura" style={{marginBottom:16}}>
      <p className="ncostura-cap">{cap}</p>
      <div className="nspine">
        {FLOW.map((f,i)=>(
          <div key={i} className={"nspine-node"+(i<=hi?" on":"")+(f.label===here?" here":"")} style={{"--c":f.c}}>
            <span className="nspine-line"/><span className="nspine-dot"/>
            <span className="nspine-lbl">{f.label}</span><span className="nspine-v">{f.v}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

// ════════ DASHBOARD / INÍCIO ════════
function Dashboard(){
  const ATIV=[
    {who:"Frota Boa Esperança",mod:"oficina",t:"OS #4821 entrou em execução",ago:"agora",c:M.oficina},
    {who:"Gráfica Rota Livre",mod:"vendas",t:"Venda #4471 faturada · R$ 5.440",ago:"4 min",c:M.vendas},
    {who:"Niterói Print",mod:"inbox",t:"aprovou orçamento no WhatsApp",ago:"12 min",c:M.inbox},
    {who:"Transportes Anderson",mod:"financeiro",t:"PIX recebido · R$ 3.910",ago:"28 min",c:M.financeiro},
    {who:"Auto Center BH",mod:"fiscal",t:"NF-e 1182 autorizada",ago:"1 h",c:M.fiscal},
  ];
  return (
    <div className="ns-screen" style={{"--c":M.vendas}}>
      <div className="npage-h">
        <div className="npage-ic"><I.home s={18}/></div>
        <div><h1>Bom dia, Larissa</h1><p>ROTA LIVRE · quinta, 5 de junho · <b>3 OS</b> aguardando aprovação</p></div>
        <div className="sp"><button className="nb"><I.search s={13}/> Buscar</button><button className="nb primary"><I.plus s={13}/> Nova OS</button></div>
      </div>

      <Costura here="OS" cap="Este é o caminhão de ponta a ponta. Cada cor é um módulo; cada passo costura no próximo — do CRM ao Financeiro, sem redigitar nada. O Oimpresso é esse círculo."/>

      <div className="nkpis" style={{gridTemplateColumns:"repeat(6,1fr)"}}>
        <div className="nkpi dark"><div className="l">Faturamento hoje</div><div className="v">R$ 24,8k</div><div className="s"><span className="up">+12%</span> vs ontem</div></div>
        <div className="nkpi accent"><div className="l">OS abertas</div><div className="v">24</div><div className="s">3 aguardam aprovação</div></div>
        <div className="nkpi"><div className="l">Vendas hoje</div><div className="v">18</div><div className="s">ticket R$ 1,3k</div></div>
        <div className="nkpi pos"><div className="l">A receber hoje</div><div className="v">R$ 9,2k</div><div className="s">6 títulos</div></div>
        <div className="nkpi neg"><div className="l">Vencidos</div><div className="v">R$ 1,2k</div><div className="s">4 clientes</div></div>
        <div className="nkpi"><div className="l">Fila produção</div><div className="v">7</div><div className="s">2 atrasadas</div></div>
      </div>

      <div style={{display:"grid",gridTemplateColumns:"1.4fr 1fr",gap:14,alignItems:"start"}}>
        <div className="ncard">
          <div style={{display:"flex",alignItems:"center",gap:10,padding:"13px 16px 11px",borderBottom:"1px solid var(--border-2)"}}>
            <strong style={{fontSize:13.5,letterSpacing:"-.01em",whiteSpace:"nowrap"}}>Atividade do fluxo</strong>
            <span style={{marginLeft:"auto",fontSize:11,color:"var(--text-4)",fontFamily:"var(--mono)"}}>tempo real</span>
          </div>
          <div>
            {ATIV.map((a,i)=>(
              <div key={i} style={{display:"flex",alignItems:"center",gap:11,padding:"10px 16px",borderBottom:i<ATIV.length-1?"1px solid var(--border-2)":"0"}}>
                <Av name={a.who} size={28}/>
                <div style={{minWidth:0,flex:1}}>
                  <div style={{fontSize:12.5,color:"var(--text)"}}><b style={{fontWeight:600}}>{a.who}</b> · {a.t}</div>
                </div>
                <span className="nbadge mod" style={{"--mc":a.c}}>{a.mod}</span>
                <span style={{fontSize:11,color:"var(--text-4)",fontFamily:"var(--mono)",minWidth:42,textAlign:"right"}}>{a.ago}</span>
              </div>
            ))}
          </div>
        </div>
        <div className="ncard">
          <div style={{padding:"13px 16px 11px",borderBottom:"1px solid var(--border-2)"}}><strong style={{fontSize:13.5}}>Precisa de você</strong></div>
          <div style={{padding:"6px 10px 10px"}}>
            {[
              {t:"3 orçamentos aguardando envio",mod:"oficina",c:M.oficina},
              {t:"6 títulos a receber hoje",mod:"financeiro",c:M.financeiro},
              {t:"2 OS atrasadas na produção",mod:"estoque",c:M.estoque},
              {t:"5 conversas sem resposta",mod:"inbox",c:M.inbox},
            ].map((x,i)=>(
              <button key={i} className="ns-item" style={{"--c":x.c,padding:"9px 9px",width:"100%"}}>
                <span className="dot"/><span className="lbl" style={{whiteSpace:"normal",fontSize:12.5}}>{x.t}</span><I.arrow s={13}/>
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

// ════════ VENDAS (roxo) ════════
function Vendas({query="",onQuery,onOpen}){
  const q=query;
  const ROWS=[
    {id:"4471",cli:"Frota Boa Esperança",origem:"oficina",val:5440,pag:"PIX",st:["pos","faturar"]},
    {id:"4470",cli:"Larissa Couros ME",origem:"vendas",val:1280,pag:"Boleto",st:["warn","aguardando"]},
    {id:"4469",cli:"Transportes Anderson",origem:"oficina",val:3910,pag:"PIX",st:["pos","faturar"]},
    {id:"4468",cli:"Gráfica Rota Livre",origem:"compras",val:740,pag:"Boleto",st:["neg","vencida"]},
    {id:"4467",cli:"Niterói Print",origem:"oficina",val:2610,pag:"Cartão",st:["pos","faturar"]},
    {id:"4466",cli:"Móveis Diadema",origem:"vendas",val:1720,pag:"PIX",st:["pos","paga"]},
  ];
  const list=ROWS.filter(r=>!q||(`${r.cli} ${r.id}`.toLowerCase().includes(q.toLowerCase())));
  const tot=ROWS.reduce((a,r)=>a+r.val,0);
  return (
    <div className="ns-screen" style={{"--c":M.vendas}}>
      <div className="npage-h">
        <div className="npage-ic"><I.cart s={18}/></div>
        <div><h1>Vendas</h1><p><b>{ROWS.length}</b> em aberto · <b>{BRLk(tot)}</b> hoje</p></div>
        <div className="sp"><button className="nb ghost">Exportar</button><button className="nb primary"><I.plus s={13}/> Nova venda</button></div>
      </div>
      <Costura here="Venda" cap="A venda nasce da OS: quando a oficina entrega, o pedido já vem montado — itens, valores, cliente e a separação fiscal. Daqui sai a Nota e cai no Financeiro."/>
      <div className="nkpis" style={{gridTemplateColumns:"repeat(4,1fr)"}}>
        <div className="nkpi accent"><div className="l">Em aberto</div><div className="v">{BRLk(tot)}</div><div className="s">{ROWS.length} pedidos</div></div>
        <div className="nkpi"><div className="l">Vendas hoje</div><div className="v">18</div><div className="s"><span className="up">+5</span> vs ontem</div></div>
        <div className="nkpi pos"><div className="l">Ticket médio</div><div className="v">R$ 1,3k</div><div className="s">margem 38%</div></div>
        <div className="nkpi neg"><div className="l">Vencidas</div><div className="v">1</div><div className="s">R$ 740</div></div>
      </div>
      <div style={{display:"flex",gap:9,marginBottom:12,alignItems:"center"}}>
        <div style={{position:"relative",flex:"1 1 280px",maxWidth:360}}>
          <span style={{position:"absolute",left:11,top:"50%",transform:"translateY(-50%)",color:"var(--text-4)",pointerEvents:"none",display:"flex"}}><I.search s={14}/></span>
          <input value={q} onChange={e=>onQuery&&onQuery(e.target.value)} placeholder="Buscar venda ou cliente…" aria-label="Buscar venda ou cliente"
            style={{width:"100%",height:34,padding:"0 12px 0 34px",borderRadius:8,border:"1px solid var(--border)",background:"var(--surface)",font:"inherit",fontSize:12.5,color:"var(--text)"}}/>
        </div>
        <button className="nb sm">Origem <I.chev s={11}/></button>
        <button className="nb sm">Pagamento <I.chev s={11}/></button>
        <span style={{marginLeft:"auto",fontSize:11.5,color:"var(--text-4)",fontFamily:"var(--mono)"}}>{list.length} vendas</span>
      </div>
      <div className="ntbl-wrap">
        <table className="ntbl">
          <thead><tr><th>Venda</th><th>Cliente</th><th>Origem</th><th>Pagamento</th><th className="num">Valor</th><th>Status</th></tr></thead>
          <tbody>
            {list.map(r=>(
              <tr key={r.id} onClick={()=>onOpen&&window.NorteDetail&&onOpen(window.NorteDetail.saleRecord(r))}>
                <td className="num"><b>#{r.id}</b></td>
                <td><div style={{display:"flex",alignItems:"center",gap:9}}><Av name={r.cli} size={26}/><b style={{fontWeight:600}}>{r.cli}</b></div></td>
                <td><span className="nbadge mod" style={{"--mc":M[r.origem]}}>{r.origem}</span></td>
                <td>{r.pag}</td>
                <td className="num"><b>{BRL(r.val)}</b></td>
                <td><span className={"nbadge "+r.st[0]}>{r.st[1]}</span></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ════════ CAIXA / INBOX (rosa) ════════
function Caixa({query=""}){
  const CONV=[
    {who:"Marcos Aleixo",canal:"WhatsApp",prev:"Pode fazer, aprovo o orçamento 👍",ago:"agora",un:0,os:"OS #4821",hot:true},
    {who:"Niterói Print",canal:"WhatsApp",prev:"Perfeito, obrigado!",ago:"12 min",un:0,os:"OS #8801"},
    {who:"Larissa Couros",canal:"Instagram",prev:"Vocês fazem banner 3x1?",ago:"33 min",un:2,os:null},
    {who:"Auto Center BH",canal:"WhatsApp",prev:"Quando fica pronto?",ago:"1 h",un:1,os:"OS #8390"},
    {who:"Frota Boa Esperança",canal:"E-mail",prev:"Segue NF em anexo",ago:"2 h",un:0,os:"OS #4821"},
  ];
  const [sel,setSel]=useState(0);
  const CV=query?CONV.filter(x=>`${x.who} ${x.prev} ${x.os||""}`.toLowerCase().includes(query.toLowerCase())):CONV;
  const c=CV[sel]||CV[0]||CONV[0];
  return (
    <div className="ns-screen" style={{"--c":M.inbox,padding:0,height:"100%"}}>
      <div style={{display:"grid",gridTemplateColumns:"320px 1fr",height:"calc(100vh - 52px)"}}>
        <div style={{borderRight:"1px solid var(--border-2)",display:"flex",flexDirection:"column",minHeight:0}}>
          <div style={{padding:"13px 16px",borderBottom:"1px solid var(--border-2)",display:"flex",alignItems:"center",gap:9}}>
            <div className="npage-ic" style={{width:30,height:30}}><I.chat s={15}/></div>
            <div><strong style={{fontSize:14,letterSpacing:"-.01em"}}>Caixa unificada</strong><div style={{fontSize:11,color:"var(--text-4)"}}>WhatsApp · Instagram · E-mail</div></div>
          </div>
          <div style={{flex:1,overflowY:"auto"}}>
            {CV.length===0 && <div style={{padding:"24px 16px",fontSize:12,color:"var(--text-4)",textAlign:"center"}}>Nenhuma conversa encontrada.</div>}
            {CV.map((x,i)=>(
              <button key={i} onClick={()=>setSel(i)} style={{display:"flex",gap:10,width:"100%",textAlign:"left",padding:"11px 16px",border:0,borderBottom:"1px solid var(--border-2)",background:i===sel?"color-mix(in oklch,var(--m-vendas) 11%,var(--surface))":"none",cursor:"pointer",boxShadow:i===sel?"inset 2.5px 0 0 var(--m-vendas)":"none"}}>
                <Av name={x.who} size={34}/>
                <div style={{minWidth:0,flex:1}}>
                  <div style={{display:"flex",alignItems:"center",gap:6}}><b style={{fontSize:12.5,fontWeight:600,color:"var(--text)"}}>{x.who}</b>{x.hot&&<span className="nbadge" style={{background:"var(--m-vendas)",color:"#fff",fontSize:9}}>aprovou</span>}<span style={{marginLeft:"auto",fontSize:10,color:"var(--text-4)",fontFamily:"var(--mono)"}}>{x.ago}</span></div>
                  <div style={{fontSize:11.5,color:"var(--text-3)",whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis",marginTop:2}}>{x.prev}</div>
                  <div style={{display:"flex",alignItems:"center",gap:7,marginTop:4}}><span style={{fontSize:9.5,color:"var(--m-vendas)",fontFamily:"var(--mono)",fontWeight:600}}>{x.canal}</span>{x.os&&<span style={{fontSize:9.5,color:"var(--text-4)",fontFamily:"var(--mono)"}}>· {x.os}</span>}{x.un>0&&<span style={{marginLeft:"auto",background:"var(--m-vendas)",color:"#fff",fontSize:9.5,fontWeight:700,borderRadius:999,padding:"1px 6px",fontFamily:"var(--mono)"}}>{x.un}</span>}</div>
                </div>
              </button>
            ))}
          </div>
        </div>
        <div style={{display:"flex",flexDirection:"column",minHeight:0,background:"var(--bg)"}}>
          <div style={{padding:"11px 18px",borderBottom:"1px solid var(--border-2)",display:"flex",alignItems:"center",gap:11,background:"var(--surface)"}}>
            <Av name={c.who} size={32}/>
            <div><b style={{fontSize:13.5,fontWeight:600}}>{c.who}</b><div style={{fontSize:11,color:"var(--text-4)"}}>{c.canal}{c.os&&` · vinculado a ${c.os}`}</div></div>
            {c.os&&<button className="nb sm" style={{marginLeft:"auto"}}><span className="nbadge mod" style={{"--mc":M.vendas,padding:0,background:"none"}}>●</span> Abrir {c.os} <I.arrow s={12}/></button>}
          </div>
          <div style={{flex:1,overflowY:"auto",padding:"18px",display:"flex",flexDirection:"column",gap:9}}>
            <div style={{alignSelf:"flex-start",maxWidth:"70%",background:"var(--surface)",border:"1px solid var(--border-2)",borderRadius:"14px",borderTopLeftRadius:4,padding:"9px 13px",fontSize:12.5}}>Oi! Segue o orçamento da revisão de freios — pastilhas + disco + óleo. Total R$ {c.os?"1.165,00":"a definir"}. Tem as fotos da inspeção junto.</div>
            <div style={{alignSelf:"flex-end",maxWidth:"70%",background:"var(--m-vendas)",color:"#fff",borderRadius:"14px",borderTopRightRadius:4,padding:"9px 13px",fontSize:12.5,fontWeight:500}}>{c.prev}<div style={{fontSize:9.5,opacity:.75,marginTop:3,textAlign:"right"}}>{c.ago} ✓✓</div></div>
            {c.hot&&<div style={{alignSelf:"center",fontSize:11,color:"var(--pos)",fontWeight:600,background:"var(--pos-soft)",border:"1px solid color-mix(in oklch,var(--pos) 35%,transparent)",borderRadius:999,padding:"5px 13px",marginTop:4}}>✓ Cliente aprovou — OS #4821 destravada para execução</div>}
          </div>
          <div style={{padding:"12px 18px",borderTop:"1px solid var(--border-2)",background:"var(--surface)",display:"flex",gap:9}}>
            <input placeholder="Escreva uma mensagem…" style={{flex:1,height:36,padding:"0 13px",borderRadius:9,border:"1px solid var(--border)",background:"var(--bg)",font:"inherit",fontSize:12.5,color:"var(--text)"}}/>
            <button className="nb primary">Enviar</button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ════════ FINANCEIRO (verde) — fecha o ciclo ════════
function Financeiro(){
  const [aba,setAba]=useState("receber");
  const REC=[
    {cli:"Frota Boa Esperança",origem:"vendas",ref:"Venda #4471",venc:"hoje",val:5440,st:["warn","vence hoje"],pag:"PIX"},
    {cli:"Transportes Anderson",origem:"oficina",ref:"OS #8790",venc:"hoje",val:3910,st:["warn","vence hoje"],pag:"Boleto"},
    {cli:"Gráfica Rota Livre",origem:"compras",ref:"Pedido #221",venc:"há 3d",val:740,st:["neg","vencida"],pag:"Boleto"},
    {cli:"Niterói Print",origem:"vendas",ref:"Venda #4467",venc:"em 2d",val:2610,st:["pos","em dia"],pag:"Cartão"},
    {cli:"Móveis Diadema",origem:"vendas",ref:"Venda #4466",venc:"em 5d",val:1720,st:["pos","em dia"],pag:"PIX"},
    {cli:"Auto Center BH",origem:"oficina",ref:"OS #8390",venc:"há 12d",val:980,st:["neg","vencida"],pag:"Boleto"},
  ];
  const PAG=[
    {forn:"Bobinas & Cia",cat:"Insumo",ref:"NF 8821",venc:"amanhã",val:3200,st:["warn","vence amanhã"]},
    {forn:"Tintas Sul",cat:"Insumo",ref:"NF 1190",venc:"em 4d",val:1850,st:["pos","em dia"]},
    {forn:"Energia ENEL",cat:"Fixo",ref:"Conta maio",venc:"em 6d",val:2240,st:["pos","em dia"]},
    {forn:"Aluguel Galpão",cat:"Fixo",ref:"Maio/26",venc:"há 1d",val:4800,st:["neg","vencida"]},
  ];
  const rows=aba==="receber"?REC:PAG;
  const totRec=REC.reduce((a,r)=>a+r.val,0), totPag=PAG.reduce((a,r)=>a+r.val,0);
  return (
    <div className="ns-screen" style={{"--c":M.financeiro}}>
      <div className="npage-h">
        <div className="npage-ic"><I.coins s={18}/></div>
        <div><h1>Financeiro</h1><p>Saldo do dia · <b>{BRLk(totRec-totPag)}</b> projetado · <b>6</b> a receber, <b>4</b> a pagar</p></div>
        <div className="sp"><button className="nb ghost">Conciliar Inter</button><button className="nb primary"><I.plus s={13}/> Lançamento</button></div>
      </div>
      <Costura here="Financeiro" cap="O Financeiro fecha o círculo: cada título aqui nasceu de uma Venda, que nasceu de uma OS, que nasceu de um Cliente. Recebido, o histórico volta pro CRM e alimenta a próxima venda."/>
      <div className="nkpis" style={{gridTemplateColumns:"repeat(5,1fr)"}}>
        <div className="nkpi pos"><div className="l">A receber hoje</div><div className="v">R$ 9,4k</div><div className="s">2 títulos</div></div>
        <div className="nkpi neg"><div className="l">Vencidos</div><div className="v">R$ 2,7k</div><div className="s">3 clientes</div></div>
        <div className="nkpi"><div className="l">A pagar 7d</div><div className="v">R$ 12,1k</div><div className="s">4 contas</div></div>
        <div className="nkpi accent"><div className="l">Saldo projetado</div><div className="v">R$ 28k</div><div className="s">fim do mês</div></div>
        <div className="nkpi dark"><div className="l">Em caixa</div><div className="v">R$ 41,3k</div><div className="s">Inter · conciliado</div></div>
      </div>
      <div style={{display:"flex",gap:9,marginBottom:12,alignItems:"center"}}>
        <div className="ns-seg" style={{borderRadius:9}}>
          <button className={aba==="receber"?"on":""} onClick={()=>setAba("receber")} style={{padding:"6px 13px",border:0,background:aba==="receber"?"var(--surface)":"none",borderRadius:7,font:"inherit",fontSize:12,fontWeight:600,color:aba==="receber"?"var(--pos)":"var(--text-3)",cursor:"pointer",boxShadow:aba==="receber"?"var(--sh-1)":"none"}}>A receber · {BRLk(totRec)}</button>
          <button className={aba==="pagar"?"on":""} onClick={()=>setAba("pagar")} style={{padding:"6px 13px",border:0,background:aba==="pagar"?"var(--surface)":"none",borderRadius:7,font:"inherit",fontSize:12,fontWeight:600,color:aba==="pagar"?"var(--neg)":"var(--text-3)",cursor:"pointer",boxShadow:aba==="pagar"?"var(--sh-1)":"none"}}>A pagar · {BRLk(totPag)}</button>
        </div>
        <span style={{marginLeft:"auto",fontSize:11.5,color:"var(--text-4)",fontFamily:"var(--mono)"}}>{rows.length} títulos</span>
      </div>
      <div className="ntbl-wrap">
        {aba==="receber"?(
          <table className="ntbl">
            <thead><tr><th>Cliente</th><th>Origem</th><th>Referência</th><th>Pagamento</th><th>Vencimento</th><th className="num">Valor</th><th>Status</th></tr></thead>
            <tbody>{REC.map((r,i)=>(
              <tr key={i}>
                <td><div style={{display:"flex",alignItems:"center",gap:9}}><Av name={r.cli} size={26}/><b style={{fontWeight:600}}>{r.cli}</b></div></td>
                <td><span className="nbadge mod" style={{"--mc":M[r.origem]}}>{r.origem}</span></td>
                <td style={{fontFamily:"var(--mono)",fontSize:11.5,color:"var(--text-3)"}}>{r.ref}</td>
                <td>{r.pag}</td>
                <td style={{color:r.st[0]==="neg"?"var(--neg)":r.st[0]==="warn"?"var(--warn)":"var(--text-2)",fontWeight:r.st[0]!=="pos"?600:400}}>{r.venc}</td>
                <td className="num"><b>{BRL(r.val)}</b></td>
                <td><span className={"nbadge "+r.st[0]}>{r.st[1]}</span></td>
              </tr>
            ))}</tbody>
          </table>
        ):(
          <table className="ntbl">
            <thead><tr><th>Fornecedor</th><th>Categoria</th><th>Referência</th><th>Vencimento</th><th className="num">Valor</th><th>Status</th></tr></thead>
            <tbody>{PAG.map((r,i)=>(
              <tr key={i}>
                <td><div style={{display:"flex",alignItems:"center",gap:9}}><Av name={r.forn} size={26}/><b style={{fontWeight:600}}>{r.forn}</b></div></td>
                <td><span className="nbadge mod" style={{"--mc":r.cat==="Insumo"?M.compras:M.estoque}}>{r.cat}</span></td>
                <td style={{fontFamily:"var(--mono)",fontSize:11.5,color:"var(--text-3)"}}>{r.ref}</td>
                <td style={{color:r.st[0]==="neg"?"var(--neg)":r.st[0]==="warn"?"var(--warn)":"var(--text-2)",fontWeight:r.st[0]!=="pos"?600:400}}>{r.venc}</td>
                <td className="num"><b>{BRL(r.val)}</b></td>
                <td><span className={"nbadge "+r.st[0]}>{r.st[1]}</span></td>
              </tr>
            ))}</tbody>
          </table>
        )}
      </div>
    </div>
  );
}

Object.assign(window, { NorteScreens: { Dashboard, Vendas, Caixa, Financeiro }, NorteCostura: Costura, NorteAv: Av });
})();
