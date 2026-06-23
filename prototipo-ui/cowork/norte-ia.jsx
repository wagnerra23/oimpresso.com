// norte-ia.jsx — Método KB-9.75 · Refino #2 "IA dentro do fluxo"
// Copiloto: resume a tela, responde com FONTE citada, sugestão REVISÁVEL (P4).
// Exporta window.NorteIA = { IAPanel }.
(() => {
const { useState, useEffect, useRef } = React;

const Svg = ({s=14,w=2,children}) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">{children}</svg>;
const Ic = {
  spark:(p)=><Svg {...p}><path d="M12 3l1.6 4.6L18 9l-4.4 1.4L12 15l-1.6-4.6L6 9l4.4-1.4L12 3Z"/><path d="M19 14l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7.7-2Z"/></Svg>,
  send:(p)=><Svg {...p}><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></Svg>,
  copy:(p)=><Svg {...p}><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></Svg>,
  check:(p)=><Svg {...p}><path d="M5 13l4 4 10-10"/></Svg>,
  edit:(p)=><Svg {...p}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></Svg>,
  x:(p)=><Svg {...p}><path d="M18 6L6 18M6 6l12 12"/></Svg>,
};
const norm=(s)=>s.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"");

// ── base de conhecimento aterrada nos dados das telas (a IA "raciocina" sobre isto) ──
const MODNAME={inicio:"Início",caixa:"Caixa unificada",oficina:"Oficina · OS",vendas:"Vendas",clientes:"Clientes",financeiro:"Financeiro",orcamentos:"Orçamentos",produtos:"Produtos",fila:"Fila de impressão",compras:"Compras",crm:"CRM",fiscal:"Fiscal",cobranca:"Cobrança"};

// respostas curadas (grounded) por intenção + módulo. Cada uma cita fonte e pode trazer sugestão.
function answer(route, qRaw){
  const q=norm(qRaw);
  const has=(...w)=>w.some(x=>q.includes(x));

  if(has("atencao","atenção","risco","problema","alerta","urgente","vencid")){
    return {
      text:"Três pontos pedem ação hoje:\n• **Auto Center BH** — R$ 980 vencido há 12 dias (OS #8390) e frescor distante. Maior risco.\n• **Gráfica Rota Livre** — R$ 740 vencido há 3 dias (Pedido #221), apesar de ser VIP.\n• **Curitiba Sinalização** — R$ 1.280 em aberto + sem compra há 2 meses: risco de churn.",
      sources:["Financeiro · vencidos","Clientes · frescor"],
      suggestion:"Disparar cobrança via Caixa pra Auto Center BH e Gráfica Rota Livre (R$ 1.720 no total)."
    };
  }
  if(has("saldo","devend","devem","aberto","receber")){
    return {
      text:"9 clientes com saldo em aberto, somando **R$ 18,4k**. Os maiores:\n• Transportes Anderson — R$ 3.910 (boleto, vence em 3 dias)\n• Curitiba Sinalização — R$ 1.280\n• Auto Center BH — R$ 980 (vencido)\n• Gráfica Rota Livre — R$ 740 (vencido)",
      sources:["Financeiro · a receber","Clientes · saldo"],
      suggestion:"Priorizar os 2 vencidos (R$ 1.720) — o resto está dentro do prazo."
    };
  }
  if(has("vip","melhor","top","maior client","fiel")){
    return {
      text:"São **12 VIPs**. Os 3 de maior volume:\n• Frota Boa Esperança — 22 OS, R$ 48,2k acumulado, sem saldo\n• Gráfica Rota Livre — 14 OS, R$ 33,6k (atenção: 1 título vencido)\n• Niterói Print — 9 OS, R$ 26,1k, em dia",
      sources:["Clientes · histórico"],
    };
  }
  if(has("resum","panorama","visao","visão","como est","status")){
    return resumo(route);
  }
  if(has("vend","faturar","faturad")){
    return {
      text:"6 vendas em aberto (R$ 15,7k). 3 prontas pra **faturar** agora: #4471 Frota (R$ 5.440), #4469 Transportes (R$ 3.910) e #4467 Niterói (R$ 2.610). A #4468 Gráfica Rota Livre está vencida.",
      sources:["Vendas · em aberto"],
      suggestion:"Faturar as 3 prontas de uma vez — R$ 11.960 que viram nota e caem no Financeiro."
    };
  }
  if(has("estoque","peça","peca","material","insumo","minimo","mínimo")){
    return {
      text:"7 itens abaixo do mínimo. Crítico: **Ilhós metálico** (180 un / mín 500) e **Tinta latex ciano** (2,1 L / mín 2,0). PS 2mm também baixo (9 chapas).",
      sources:["Estoque · saldo","Compras · pedidos"],
      suggestion:"Já há pedido a caminho de vinil; falta abrir pedido de ilhós e tinta ciano."
    };
  }
  // fallback contextual
  return {
    text:`Posso ajudar com o módulo **${MODNAME[route]||"atual"}**. Tente: "o que precisa de atenção?", "resumir a tela", "quem tem saldo aberto?", "quais vendas faturar?".`,
    sources:[MODNAME[route]||"Oimpresso"],
  };
}

function resumo(route){
  const R={
    clientes:{text:"**142 clientes**, 38 com OS aberta, 12 VIPs. 9 têm saldo em aberto (R$ 18,4k), sendo 2 vencidos. 21 sem compra há 90 dias — vale uma campanha de reativação. 6 novos este mês.",sources:["Clientes"],suggestion:"Criar lista de reativação pros 21 inativos."},
    vendas:{text:"**6 vendas em aberto** somando R$ 15,7k; ticket médio R$ 1.620, margem 38%. 3 prontas pra faturar (R$ 11,9k), 1 vencida (Gráfica Rota Livre, R$ 740).",sources:["Vendas"],suggestion:"Faturar as 3 prontas."},
    financeiro:{text:"**A receber hoje: R$ 9,4k** (2 títulos). Vencidos: R$ 2,7k em 3 clientes. A pagar nos próximos 7 dias: R$ 12,1k. Saldo projetado fim do mês: R$ 28k.",sources:["Financeiro"],suggestion:"Cobrar os 3 vencidos antes de liberar os pagamentos."},
    oficina:{text:"**24 OS** no fluxo. A OS #4821 (Honda Civic) está em orçamento, aguardando aprovação do cliente — trava a execução. 2 reprovados no DVI viraram orçamento.",sources:["Oficina · OS"],suggestion:"Enviar o orçamento da #4821 pelo Caixa pra destravar."},
  };
  return R[route]||{text:`Resumo de **${MODNAME[route]||"Oimpresso"}**: a tela está vestida na identidade Norte. Pergunte sobre saldos, vendas, atenção ou clientes pra um panorama com dados.`,sources:[MODNAME[route]||"Oimpresso"]};
}

function quickPrompts(route){
  const base=["O que precisa de atenção hoje?","Resumir esta tela"];
  const byRoute={
    clientes:["Quem tem saldo em aberto?","Quais são meus VIPs?"],
    vendas:["Quais vendas posso faturar?","Resumir esta tela"],
    financeiro:["O que está vencido?","Quanto entra hoje?"],
    oficina:["O que está travado?","Resumir esta tela"],
    estoque:["O que está abaixo do mínimo?"],
  };
  return [...base.slice(0,1),...(byRoute[route]||["Quem tem saldo em aberto?"]),"Resumir esta tela"].slice(0,4);
}

// markdown leve (negrito + bullets)
function MD({text}){
  return text.split("\n").map((ln,i)=>{
    const parts=ln.split(/(\*\*[^*]+\*\*)/g).map((p,j)=> p.startsWith("**")?<b key={j}>{p.slice(2,-2)}</b>:<React.Fragment key={j}>{p}</React.Fragment>);
    return <div key={i} className={ln.startsWith("•")?"nia-bul":"nia-ln"}>{parts}</div>;
  });
}

function IAPanel({open,onClose,route}){
  const [thread,setThread]=useState([]);
  const [q,setQ]=useState("");
  const [busy,setBusy]=useState(false);
  const [applied,setApplied]=useState({});
  const bodyRef=useRef(null);
  const inRef=useRef(null);

  useEffect(()=>{ if(open){ setTimeout(()=>inRef.current&&inRef.current.focus(),30);} },[open]);
  useEffect(()=>{ const b=bodyRef.current; if(b) b.scrollTop=b.scrollHeight; },[thread,busy]);
  useEffect(()=>{
    if(!open) return;
    const k=(e)=>{ if(e.key==="Escape") onClose(); };
    window.addEventListener("keydown",k); return ()=>window.removeEventListener("keydown",k);
  },[open]);

  const ask=(text)=>{
    if(!text.trim()||busy) return;
    setThread(t=>[...t,{role:"user",text}]); setQ(""); setBusy(true);
    setTimeout(()=>{ // "pensando" curto, resposta grounded
      const a=answer(route,text);
      setThread(t=>[...t,{role:"ia",...a}]);
      setBusy(false);
    },520);
  };

  if(!open) return null;
  return (
    <div className="nia-back" onMouseDown={onClose}>
      <aside className="nia" onMouseDown={e=>e.stopPropagation()}>
        <div className="nia-h">
          <span className="nia-spark"><Ic.spark s={16}/></span>
          <div><b>Norte IA</b><small>copiloto · propõe, você decide</small></div>
          <button className="nia-x" onClick={onClose}><Ic.x s={16}/></button>
        </div>

        <div className="nia-body" ref={bodyRef}>
          {thread.length===0 && (
            <div className="nia-empty">
              <span className="nia-empty-ic"><Ic.spark s={22}/></span>
              <h4>Pergunte sobre {MODNAME[route]||"o seu ERP"}</h4>
              <p>Eu leio os dados da tela e respondo citando a fonte. Nunca aplico nada sozinho — você revisa antes.</p>
            </div>
          )}
          {thread.map((m,i)=>(
            m.role==="user"
              ? <div key={i} className="nia-q"><span>{m.text}</span></div>
              : <div key={i} className="nia-a">
                  <div className="nia-a-ic"><Ic.spark s={13}/></div>
                  <div className="nia-a-body">
                    <div className="nia-a-text"><MD text={m.text}/></div>
                    {m.sources&&<div className="nia-src"><span className="nia-src-l">Fontes</span>{m.sources.map((s,j)=><span key={j} className="nia-src-pill">{s}</span>)}</div>}
                    {m.suggestion&&(
                      <div className="nia-sug">
                        <div className="nia-sug-h"><Ic.spark s={12}/> Sugestão — revise antes de aplicar</div>
                        <p>{m.suggestion}</p>
                        <div className="nia-sug-act">
                          {applied[i]
                            ? <span className="nia-applied"><Ic.check s={13}/> aplicado</span>
                            : <button className="nia-apply" onClick={()=>setApplied(a=>({...a,[i]:true}))}><Ic.check s={13}/> Aplicar</button>}
                          <button className="nia-ghost"><Ic.edit s={12}/> Editar</button>
                          <button className="nia-ghost"><Ic.copy s={12}/> Copiar</button>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
          ))}
          {busy && <div className="nia-a"><div className="nia-a-ic"><Ic.spark s={13}/></div><div className="nia-think"><span/><span/><span/></div></div>}
        </div>

        {thread.length===0 && (
          <div className="nia-chips">
            {quickPrompts(route).map((p,i)=><button key={i} className="nia-chip" onClick={()=>ask(p)}>{p}</button>)}
          </div>
        )}

        <form className="nia-in" onSubmit={e=>{e.preventDefault();ask(q);}}>
          <input ref={inRef} value={q} onChange={e=>setQ(e.target.value)} placeholder={"Perguntar sobre "+(MODNAME[route]||"o ERP")+"…"} aria-label="Perguntar à Norte IA"/>
          <button type="submit" disabled={!q.trim()||busy} aria-label="Enviar"><Ic.send s={15}/></button>
        </form>
        <div className="nia-foot">A Norte IA cita a fonte e nunca executa sem sua confirmação. <b>P4 · copiloto, não autor.</b></div>
      </aside>
    </div>
  );
}

window.NorteIA={ IAPanel };
})();
