// norte-shell.jsx — o chrome unificado + roteador. Denso por padrão.
(() => {
const { useState, useEffect } = React;
const { Dashboard, Vendas, Caixa, Financeiro } = window.NorteScreens;
const Costura = window.NorteCostura, Av = window.NorteAv;

const Svg = ({s=14,w=2,children}) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">{children}</svg>;
const I = {
  home:(p)=><Svg {...p}><path d="M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10"/></Svg>,
  chat:(p)=><Svg {...p}><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.9 9.9 0 0 1-4-.8L3 21l1.9-4.5A8.4 8.4 0 1 1 21 11.5Z"/></Svg>,
  wrench:(p)=><Svg {...p}><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.1-.5-.5-2.1 2.6-2.6z"/></Svg>,
  cart:(p)=><Svg {...p}><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12.5a1.7 1.7 0 0 0 1.7 1.3h8.6a1.7 1.7 0 0 0 1.7-1.3L23 7H6"/></Svg>,
  users:(p)=><Svg {...p}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></Svg>,
  tag:(p)=><Svg {...p}><path d="M20.6 13.4L13 21l-9-9V4h8l8.6 8.6a2 2 0 0 1 0 2.8Z"/><circle cx="8" cy="8" r="1.4"/></Svg>,
  box:(p)=><Svg {...p}><path d="M3 8l9-5 9 5v8l-9 5-9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></Svg>,
  receipt:(p)=><Svg {...p}><path d="M5 2v20l2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8M8 11h8M8 15h5"/></Svg>,
  coins:(p)=><Svg {...p}><circle cx="9" cy="9" r="6"/><path d="M14.7 5.3A6 6 0 1 1 15 18.6"/></Svg>,
  doc:(p)=><Svg {...p}><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></Svg>,
  truck:(p)=><Svg {...p}><path d="M1 4h13v10H1zM14 8h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></Svg>,
  print:(p)=><Svg {...p}><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></Svg>,
  shield:(p)=><Svg {...p}><path d="M12 2l8 3v6c0 5-3.5 8.5-8 11-4.5-2.5-8-6-8-11V5z"/></Svg>,
  book:(p)=><Svg {...p}><path d="M4 4a2 2 0 0 1 2-2h13v18H6a2 2 0 0 0-2 2z"/><path d="M4 19h15"/></Svg>,
  chart:(p)=><Svg {...p}><path d="M3 3v18h18M7 14v3M12 9v8M17 6v11"/></Svg>,
  cog:(p)=><Svg {...p}><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.4l2-1.5-2-3.4-2.3.9a7 7 0 0 0-2.4-1.4L13.8 2h-3.6l-.4 2.4a7 7 0 0 0-2.4 1.4l-2.3-.9-2 3.4 2 1.5A7 7 0 0 0 5 12a7 7 0 0 0 .1 1.4l-2 1.5 2 3.4 2.3-.9a7 7 0 0 0 2.4 1.4l.4 2.4h3.6l.4-2.4a7 7 0 0 0 2.4-1.4l2.3.9 2-3.4-2-1.5A7 7 0 0 0 19 12Z"/></Svg>,
  search:(p)=><Svg {...p}><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></Svg>,
  bell:(p)=><Svg {...p}><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></Svg>,
  spark:(p)=><Svg {...p}><path d="M12 3l1.6 4.6L18 9l-4.4 1.4L12 15l-1.6-4.6L6 9l4.4-1.4L12 3Z"/><path d="M19 14l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7.7-2Z"/></Svg>,
  sun:(p)=><Svg {...p}><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></Svg>,
  moon:(p)=><Svg {...p}><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></Svg>,
  rows:(p)=><Svg {...p}><rect x="3" y="4" width="18" height="5" rx="1"/><rect x="3" y="13" width="18" height="5" rx="1"/></Svg>,
  panel:(p)=><Svg {...p}><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></Svg>,
  plus:(p)=><Svg {...p}><path d="M12 5v14M5 12h14"/></Svg>,
  chev:(p)=><Svg {...p}><path d="M6 9l6 6 6-6"/></Svg>,
  arrow:(p)=><Svg {...p}><path d="M5 12h14M13 6l6 6-6 6"/></Svg>,
};
const PURPLE="var(--m-vendas)";
const M={crm:PURPLE,oficina:PURPLE,vendas:PURPLE,inbox:PURPLE,fiscal:PURPLE,financeiro:PURPLE,compras:PURPLE,estoque:PURPLE};
const BRL=(n)=>"R$ "+n.toLocaleString("pt-BR",{minimumFractionDigits:2,maximumFractionDigits:2});

// ─── mapa de navegação (cor-por-módulo) ───
const NAV=[
  {sec:"Hoje",items:[
    {id:"inicio",label:"Início",ic:"home",c:M.vendas},
    {id:"caixa",label:"Caixa unificada",ic:"chat",c:M.inbox,n:"5"},
  ]},
  {sec:"Operação",items:[
    {id:"oficina",label:"Oficina · OS",ic:"wrench",c:M.oficina,n:"24"},
    {id:"vendas",label:"Vendas",ic:"cart",c:M.vendas,n:"18"},
    {id:"orcamentos",label:"Orçamentos",ic:"doc",c:M.vendas,n:"6"},
    {id:"produtos",label:"Produtos",ic:"tag",c:M.estoque},
    {id:"clientes",label:"Clientes",ic:"users",c:M.crm,n:"142"},
  ]},
  {sec:"Produção",items:[
    {id:"fila",label:"Fila de impressão",ic:"print",c:M.estoque,n:"7"},
    {id:"expedicao",label:"Expedição",ic:"truck",c:M.estoque},
    {id:"estoque",label:"Estoque",ic:"box",c:M.estoque},
  ]},
  {sec:"Financeiro",items:[
    {id:"financeiro",label:"Financeiro",ic:"coins",c:M.financeiro,n:"7"},
    {id:"fiscal",label:"Fiscal · NF-e/NFS-e",ic:"receipt",c:M.fiscal,n:"3"},
    {id:"cobranca",label:"Cobrança",ic:"coins",c:M.financeiro},
  ]},
  {sec:"Gestão",items:[
    {id:"crm",label:"CRM · Funil",ic:"chart",c:M.crm},
    {id:"compras",label:"Compras",ic:"box",c:M.compras,n:"12"},
    {id:"relatorios",label:"Relatórios",ic:"chart",c:M.vendas},
    {id:"kb",label:"Base de conhecimento",ic:"book",c:M.crm},
    {id:"config",label:"Configurações",ic:"cog",c:M.estoque},
  ]},
];
const FLAT={}; NAV.forEach(g=>g.items.forEach(it=>FLAT[it.id]=it));
const HEAD_TABS={
  oficina:["OS aberta","Em produção","Fila","Histórico"],
  vendas:["Em aberto","Faturadas","Por cliente","Mês"],
  clientes:["Todos","Clientes","Fornecedores","Funcionários"],
  financeiro:["Visão geral","A pagar","A receber","Fluxo","DRE"],
  crm:["Funil","Leads","Oportunidades"],
};

// ─── dicionário de módulos: cada tela do backlog vira uma tela densa real ───
const badge=(t,tone)=>({t,tone});
const MODDATA={
  orcamentos:{ sub:"Gere a partir do catálogo, envie pelo Caixa. Aprovado, vira OS sem redigitar.",
    here:"Venda", cap:"O orçamento é o ensaio da venda: aprovado pelo cliente no Caixa, ele vira OS e Venda sem ninguém redigitar.",
    cta:"Novo orçamento", new:"Novo orçamento",
    kpis:[["Em aberto","14","accent"],["Aprovados (mês)","38","pos"],["Valor em aberto","R$ 62,4k"],["Conversão","61%","pos"],["Vencendo","3","neg"]],
    cols:["Orçamento","Cliente","Itens","Validade","Valor","Status"],
    rows:[
      ["#OR-2208","Frota Boa Esperança","Plotagem + recorte","2d",  "R$ 5.440",badge("aprovado","pos")],
      ["#OR-2207","Auto Center BH","Banner 3×1m · lona",       "5d",  "R$ 890", badge("enviado","accent")],
      ["#OR-2206","Móveis Diadema","Adesivo vitrine",           "hoje","R$ 1.260",badge("vencendo","warn")],
      ["#OR-2205","Niterói Print","Cartão · 1000un",            "7d",  "R$ 320", badge("rascunho","mute")],
      ["#OR-2204","Curitiba Sinalização","Placa PS 2mm",        "4d",  "R$ 2.180",badge("enviado","accent")],
    ]},
  produtos:{ sub:"Catálogo de produtos e serviços — preço, insumos e natureza fiscal. A base de toda venda.",
    cta:"Novo produto", new:"Novo produto",
    kpis:[["Itens ativos","318","accent"],["Serviços","42"],["Abaixo do mínimo","7","neg"],["Margem média","44%","pos"],["Sem preço","2","warn"]],
    cols:["Produto","SKU","Tipo","Custo","Preço","Estoque"],
    rows:[
      ["Lona 440g brilho","LON-440","Insumo","R$ 18,00/m²","R$ 42,00/m²",badge("128 m²","pos")],
      ["Adesivo vinil branco","ADV-BR","Insumo","R$ 12,50/m²","R$ 34,00/m²",badge("64 m²","pos")],
      ["Plotagem + aplicação","SRV-PLT","Serviço","—","R$ 60,00/h",badge("serviço","mute")],
      ["Placa PS 2mm","PS-2MM","Produto","R$ 28,00","R$ 78,00",badge("9 un","warn")],
      ["Ilhós metálico","ILH-01","Insumo","R$ 0,40","R$ 1,20",badge("baixo","neg")],
    ]},
  fila:{ sub:"Fila de impressão por equipamento — Roland, HP Latex, recorte. Sequência e prioridade.",
    cta:"Enviar à fila", new:"Enviar p/ fila",
    kpis:[["Na fila","7","accent"],["Imprimindo","2","pos"],["Atrasados","1","neg"],["Concluídos hoje","18","pos"],["Equipamentos","3"]],
    cols:["Job","OS","Equipamento","Material","Prioridade","Status"],
    rows:[
      ["Banner Frota BE","#4821","Roland 540","Lona 440g",  badge("alta","neg"),  badge("imprimindo","pos")],
      ["Adesivo vitrine","#4818","HP Latex 315","Vinil",     badge("normal","mute"),badge("na fila","accent")],
      ["Recorte logo RL","#4815","Plotter recorte","Vinil",  badge("normal","mute"),badge("na fila","accent")],
      ["Placa Auto BH","#4810","Roland 540","PS 2mm",        badge("baixa","mute"), badge("aguard. material","warn")],
    ]},
  expedicao:{ sub:"Embalagem, romaneio e entrega. Fecha o ciclo de produção antes da retirada.",
    cta:"Novo romaneio", new:"Novo romaneio",
    kpis:[["A embalar","5","accent"],["A entregar","8"],["Em rota","3","pos"],["Entregues hoje","12","pos"],["Atrasadas","1","neg"]],
    cols:["Pedido","Cliente","Itens","Destino","Tipo","Status"],
    rows:[
      ["#4821","Frota Boa Esperança","2 volumes","Guarulhos/SP","Entrega",badge("em rota","pos")],
      ["#4818","Móveis Diadema","1 volume","Diadema/SP","Retirada",badge("pronto","accent")],
      ["#4815","Gráfica Rota Livre","3 volumes","São Paulo/SP","Entrega",badge("a embalar","warn")],
      ["#4810","Auto Center BH","1 volume","BH/MG","Transportadora",badge("agendado","mute")],
    ]},
  estoque:{ sub:"Saldo de matéria-prima e peças. Cada execução de OS baixa daqui automaticamente.",
    cta:"Entrada manual", new:"Entrada",
    kpis:[["SKUs","318"],["Abaixo do mínimo","7","neg"],["Reservado p/ OS","R$ 8,9k","accent"],["Valor total","R$ 142k"],["Sem giro 60d","11","warn"]],
    cols:["Insumo","SKU","Saldo","Mínimo","Reservado","Status"],
    rows:[
      ["Lona 440g brilho","LON-440","128 m²","40 m²","12 m²",badge("ok","pos")],
      ["Vinil branco","ADV-BR","64 m²","30 m²","8 m²",badge("ok","pos")],
      ["Tinta latex ciano","TNT-C","2,1 L","2,0 L","—",badge("repor","warn")],
      ["Ilhós metálico","ILH-01","180 un","500 un","60 un",badge("crítico","neg")],
      ["PS 2mm branco","PS-2MM","9 ch","12 ch","3 ch",badge("baixo","warn")],
    ]},
  fiscal:{ sub:"NF-e (mercadoria) e NFS-e (serviço) — separadas desde a inspeção da OS.",
    here:"Nota", cap:"A nota nasce pronta da venda: peça vira NF-e, mão de obra vira NFS-e — a separação fiscal já veio lá da inspeção da OS.",
    cta:"Emitir nota", new:"Emitir",
    kpis:[["Autorizadas (mês)","214","pos"],["Pendentes","3","warn"],["Rejeitadas","1","neg"],["Valor emitido","R$ 318k","accent"],["Canceladas","2"]],
    cols:["Documento","Nº","Cliente","Natureza","Valor","Status"],
    rows:[
      ["NF-e 55","8821","Frota Boa Esperança","Mercadoria","R$ 925,00",badge("autorizada","pos")],
      ["NFS-e","1142","Frota Boa Esperança","Serviço","R$ 240,00",badge("autorizada","pos")],
      ["NF-e 55","8820","Auto Center BH","Mercadoria","R$ 1.180,00",badge("processando","warn")],
      ["NFS-e","1141","Móveis Diadema","Serviço","R$ 360,00",badge("rejeitada","neg")],
    ]},
  cobranca:{ sub:"Boletos, PIX e cobrança recorrente. Puxa os títulos direto do Financeiro.",
    cta:"Nova cobrança", new:"Nova cobrança",
    kpis:[["A receber","R$ 48,2k","accent"],["Vencidos","R$ 8,4k","neg"],["Recebido (mês)","R$ 196k","pos"],["Boletos ativos","62"],["PIX hoje","14","pos"]],
    cols:["Título","Cliente","Meio","Vencimento","Valor","Status"],
    rows:[
      ["#FT-3301","Transportes Anderson","Boleto","em 3d","R$ 3.910",badge("aberto","accent")],
      ["#FT-3298","Auto Center BH","Boleto","-2d","R$ 740",badge("vencido","neg")],
      ["#FT-3295","Gráfica Rota Livre","PIX","hoje","R$ 1.280",badge("pago","pos")],
      ["#FT-3290","Curitiba Sinalização","Boleto","em 8d","R$ 2.180",badge("aberto","accent")],
    ]},
  crm:{ sub:"Funil de leads e oportunidades. O cliente daqui vira a próxima OS — o começo do círculo.",
    here:"Cliente", cap:"O CRM é onde o círculo recomeça: o lead vira cliente, o cliente vira OS, e o histórico da OS realimenta o funil.",
    cta:"Nova oportunidade", new:"Nova oportunidade",
    kpis:[["Leads","48","accent"],["Oportunidades","17"],["Em negociação","R$ 84k","pos"],["Ganhos (mês)","12","pos"],["Sem follow-up","5","warn"]],
    cols:["Oportunidade","Contato","Etapa","Valor","Próximo passo","Status"],
    rows:[
      ["Frota — adesivagem 8 veículos","Anderson Lima","Proposta","R$ 12.400","ligar 2ª",badge("quente","pos")],
      ["Auto BH — fachada nova","Roberto Dias","Diagnóstico","R$ 6.800","visita",badge("morno","warn")],
      ["Móveis — sinalização interna","Sônia Ribeiro","Negociação","R$ 4.200","enviar OR",badge("quente","pos")],
      ["Niterói — recorrente mensal","Felipe Sá","Lead","—","qualificar",badge("frio","mute")],
    ]},
  compras:{ sub:"Pedidos a fornecedor, entrada de mercadoria e custo. Abastece o estoque.",
    cta:"Novo pedido", new:"Novo pedido",
    kpis:[["Pedidos abertos","12","accent"],["A receber","8"],["Atrasados","2","neg"],["Gasto (mês)","R$ 74k"],["Fornecedores","28"]],
    cols:["Pedido","Fornecedor","Itens","Previsão","Valor","Status"],
    rows:[
      ["#PC-882","Vinil Brasil Dist.","Vinil branco · 200m²","em 2d","R$ 2.500",badge("a caminho","accent")],
      ["#PC-880","Tintas Roland SP","Latex ciano+magenta","-1d","R$ 1.840",badge("atrasado","neg")],
      ["#PC-878","Acrílicos Paulista","PS 2mm · 20 chapas","em 5d","R$ 560",badge("confirmado","pos")],
      ["#PC-875","Ferragens União","Ilhós · 5000un","recebido","R$ 200",badge("recebido","pos")],
    ]},
  relatorios:{ sub:"BI do balcão: vendas por período, margem e produtividade da oficina.",
    cta:"Exportar", new:"Novo relatório",
    kpis:[["Faturamento (mês)","R$ 318k","accent"],["Ticket médio","R$ 1.620","pos"],["Margem","44%","pos"],["OS concluídas","186"],["Retrabalho","2,1%","warn"]],
    cols:["Relatório","Período","Métrica","Valor","Variação","Status"],
    rows:[
      ["Vendas por período","Junho","Faturamento","R$ 318k",badge("+12%","pos"),badge("pronto","pos")],
      ["Margem por produto","Junho","Margem média","44%",badge("+3pp","pos"),badge("pronto","pos")],
      ["Produtividade oficina","Junho","OS/dia","9,1",badge("-4%","neg"),badge("pronto","pos")],
      ["Inadimplência","Junho","% vencido","4,2%",badge("+0,8pp","warn"),badge("pronto","pos")],
    ]},
};
const TONE={accent:["var(--m-vendas)","color-mix(in oklch,var(--m-vendas) 14%,var(--bg))"],pos:["var(--pos)","var(--pos-soft)"],neg:["var(--neg)","var(--neg-soft)"],warn:["oklch(0.52 0.11 70)","var(--warn-soft)"],mute:["var(--text-3)","var(--sunken)"]};
function Pill({b}){ const[c,bg]=TONE[b.tone]||TONE.mute; return <span style={{display:"inline-flex",alignItems:"center",fontSize:10.5,fontWeight:600,fontFamily:b.tone==="mute"?"inherit":"inherit",padding:"2px 9px",borderRadius:999,color:c,background:bg,whiteSpace:"nowrap"}}>{b.t}</span>; }

// ─── módulo genérico vestido Norte (cobre todo o backlog) ───
function GenericMod({id,query="",onQuery,onOpen}){
  const it=FLAT[id]||{label:id,c:M.vendas,ic:"box"};
  const Icon=I[it.ic]||I.box;
  const d=MODDATA[id];

  // fallback elegante pros módulos ainda sem dicionário
  if(!d){
    return (
      <div className="ns-screen" style={{"--c":it.c}}>
        <div className="npage-h">
          <div className="npage-ic"><Icon s={18}/></div>
          <div><h1>{it.label}</h1><p>Tela vestida na identidade Norte — pronta pra receber conteúdo.</p></div>
          <div className="sp"><button className="nb primary"><I.plus s={13}/> Novo</button></div>
        </div>
        <div className="nstub" style={{marginTop:8}}>
          <div className="nstub-ic"><Icon s={26}/></div>
          <h1>{it.label}</h1>
          <p>Este módulo já herda o shell, os tokens e o tema do Norte. O conteúdo denso entra na fase de migração da tela.</p>
          <div className="nstub-meta"><span className="nstub-chip"><span className="d"/>shell Norte</span><span className="nstub-chip"><span className="d"/>tema claro/escuro</span><span className="nstub-chip"><span className="d"/>busca global</span></div>
        </div>
      </div>
    );
  }

  const rows=d.rows.filter(r=>!query||r.join(" ").toLowerCase().includes(query.toLowerCase()));
  return (
    <div className="ns-screen" style={{"--c":it.c}}>
      <div className="npage-h">
        <div className="npage-ic"><Icon s={18}/></div>
        <div><h1>{it.label}</h1><p>{d.sub}</p></div>
        <div className="sp"><button className="nb primary"><I.plus s={13}/> {d.new||d.cta}</button></div>
      </div>
      {d.here && <Costura here={d.here} cap={d.cap}/>}
      <div className="nkpis" style={{gridTemplateColumns:`repeat(${d.kpis.length},1fr)`}}>
        {d.kpis.map((k,i)=>(
          <div key={i} className={"nkpi"+(k[2]?" "+(k[2]==="accent"?"accent":k[2]==="pos"?"pos":"neg"):"")}>
            <div className="l">{k[0]}</div><div className="v">{k[1]}</div>{k[3]&&<div className="s">{k[3]}</div>}
          </div>
        ))}
      </div>
      <div style={{display:"flex",gap:9,marginBottom:12,alignItems:"center"}}>
        <LocalSearch query={query} onQuery={onQuery} placeholder={"Buscar em "+it.label.toLowerCase()+"…"}/>
        <button className="nb sm">Filtrar <I.chev s={11}/></button>
        <button className="nb sm">Período <I.chev s={11}/></button>
        <span style={{marginLeft:"auto",fontSize:11.5,color:"var(--text-4)",fontFamily:"var(--mono)"}}>{rows.length} registros</span>
      </div>
      <div className="ntbl-wrap">
        <table className="ntbl">
          <thead><tr>{d.cols.map((c,i)=><th key={i} className={i>=d.cols.length-2&&typeof d.rows[0][i]==="string"&&d.rows[0][i].startsWith("R$")?"num":""}>{c}</th>)}</tr></thead>
          <tbody>
            {rows.map((r,ri)=>(
              <tr key={ri} onClick={()=>onOpen&&window.NorteDetail&&onOpen(window.NorteDetail.genericRecord(it.label,d.cols,r))}>
                {r.map((cell,ci)=>(
                  <td key={ci} className={typeof cell==="string"&&(cell.startsWith("R$")||cell.startsWith("#"))?"":undefined} style={typeof cell==="string"&&cell.startsWith("#")?{fontFamily:"var(--mono)",fontWeight:600}:typeof cell==="string"&&cell.startsWith("R$")?{fontFamily:"var(--mono)",fontWeight:600}:ci===0?{fontWeight:600}:undefined}>
                    {typeof cell==="object"&&cell&&cell.t?<Pill b={cell}/>:cell}
                  </td>
                ))}
              </tr>
            ))}
            {rows.length===0 && <tr><td colSpan={d.cols.length} style={{textAlign:"center",padding:"24px",color:"var(--text-4)"}}>Nenhum registro encontrado.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ─── busca inline reutilizável (sincroniza com a query global) ───
function LocalSearch({query,onQuery,placeholder,count}){
  return (
    <div style={{position:"relative",flex:"1 1 280px",maxWidth:360}}>
      <span style={{position:"absolute",left:11,top:"50%",transform:"translateY(-50%)",color:"var(--text-4)",pointerEvents:"none",display:"flex"}}><I.search s={14}/></span>
      <input value={query} onChange={e=>onQuery&&onQuery(e.target.value)} placeholder={placeholder} aria-label={placeholder}
        style={{width:"100%",height:34,padding:"0 12px 0 34px",borderRadius:8,border:"1px solid var(--border)",background:"var(--surface)",font:"inherit",fontSize:12.5,color:"var(--text)"}}/>
    </div>
  );
}

// ─── Clientes (denso) embutido ───
function Clientes({query="",onQuery,onOpen}){
  const q=query;
  const ROWS=[
    {nm:"Frota Boa Esperança",doc:"34.567.890/0001-12",tp:"PJ",city:"Guarulhos/SP",fr:["recente","há 4d"],saldo:0,os:22,tags:["VIP","Premium"],vip:true},
    {nm:"Transportes Anderson",doc:"23.456.789/0001-01",tp:"PJ",city:"Campinas/SP",fr:["fresc","há 3sem"],saldo:3910,os:8,tags:["Boleto"]},
    {nm:"Gráfica Rota Livre",doc:"12.345.678/0001-90",tp:"PJ",city:"São Paulo/SP",fr:["recente","há 1sem"],saldo:0,os:14,tags:["VIP","PIX"],vip:true},
    {nm:"Auto Center BH",doc:"67.890.123/0001-45",tp:"PJ",city:"Belo Horizonte/MG",fr:["distante","há 1m"],saldo:740,os:7,tags:["Boleto"]},
    {nm:"Móveis Diadema",doc:"56.789.012/0001-34",tp:"PJ",city:"Diadema/SP",fr:["fresc","há 2sem"],saldo:0,os:11,tags:["Indicador"]},
    {nm:"Niterói Print",doc:"78.901.234/0001-56",tp:"PJ",city:"Niterói/RJ",fr:["recente","há 6d"],saldo:0,os:9,tags:["VIP"],vip:true},
    {nm:"Curitiba Sinalização",doc:"89.012.345/0001-67",tp:"PJ",city:"Curitiba/PR",fr:["distante","há 2m"],saldo:1280,os:5,tags:["Boleto"]},
  ];
  const FC={recente:["var(--pos)","var(--pos-soft)"],fresc:["var(--m-vendas)","color-mix(in oklch,var(--m-vendas) 14%,var(--bg))"],distante:["var(--warn)","var(--warn-soft)"],frio:["var(--text-4)","var(--sunken)"]};
  const list=ROWS.filter(r=>!q||`${r.nm} ${r.doc} ${r.city}`.toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="ns-screen" style={{"--c":M.crm}}>
      <div className="npage-h">
        <div className="npage-ic"><I.users s={18}/></div>
        <div><h1>Clientes</h1><p><b>142</b> cadastrados · <b>38</b> com OS aberta</p></div>
        <div className="sp"><button className="nb ghost">Importar</button><button className="nb primary"><I.plus s={13}/> Novo cliente</button></div>
      </div>
      <Costura here="Cliente" cap="O cliente é onde o círculo começa e fecha: do CRM nasce a OS; quando o Financeiro recebe, o histórico volta pra cá e alimenta a próxima venda."/>
      <div className="nkpis" style={{gridTemplateColumns:"repeat(5,1fr)"}}>
        <div className="nkpi accent"><div className="l">Ativos</div><div className="v">38</div><div className="s">com OS aberta</div></div>
        <div className="nkpi"><div className="l">VIPs</div><div className="v">12</div><div className="s">prioridade</div></div>
        <div className="nkpi neg"><div className="l">Com saldo</div><div className="v">9</div><div className="s">R$ 18,4k</div></div>
        <div className="nkpi"><div className="l">Sem compra 90d</div><div className="v">21</div><div className="s">risco churn</div></div>
        <div className="nkpi"><div className="l">Novos no mês</div><div className="v">6</div><div className="s">desde dia 1</div></div>
      </div>
      <div style={{display:"flex",gap:9,marginBottom:12,alignItems:"center"}}>
        <LocalSearch query={q} onQuery={onQuery} placeholder="Buscar nome, CNPJ, cidade…"/>
        <button className="nb sm">Tipo <I.chev s={11}/></button>
        <button className="nb sm">UF <I.chev s={11}/></button>
        <button className="nb sm">Tags <I.chev s={11}/></button>
        <span style={{marginLeft:"auto",fontSize:11.5,color:"var(--text-4)",fontFamily:"var(--mono)"}}>{list.length} clientes</span>
      </div>
      <div className="ntbl-wrap">
        <table className="ntbl">
          <thead><tr><th>Cliente</th><th>Documento</th><th>Cidade</th><th>Frescor</th><th className="num">Saldo</th><th className="num">OS</th><th>Tags</th></tr></thead>
          <tbody>
            {list.map((r,i)=>(
              <tr key={i} onClick={()=>onOpen&&window.NorteDetail&&onOpen(window.NorteDetail.clientRecord(r))}>
                <td><div style={{display:"flex",alignItems:"center",gap:9}}><Av name={r.nm} size={28}/><span><b style={{fontWeight:600}}>{r.nm}</b>{r.vip&&<span style={{marginLeft:7,fontSize:9,fontWeight:700,padding:"1px 6px",borderRadius:5,background:"oklch(0.95 0.06 85)",color:"oklch(0.52 0.12 75)"}}>VIP</span>}</span></div></td>
                <td style={{fontFamily:"var(--mono)",fontSize:11.5}}>{r.doc}</td>
                <td>{r.city}</td>
                <td><span style={{display:"inline-flex",alignItems:"center",gap:6,fontSize:11,fontWeight:500,padding:"3px 9px",borderRadius:999,background:FC[r.fr[0]][1],border:"1px solid "+FC[r.fr[0]][0]}}><span style={{width:7,height:7,borderRadius:"50%",background:FC[r.fr[0]][0]}}/><span style={{color:FC[r.fr[0]][0],fontWeight:600,textTransform:"capitalize"}}>{r.fr[0]}</span><span style={{color:"var(--text-4)"}}>· {r.fr[1]}</span></span></td>
                <td className="num">{r.saldo?<b style={{color:"var(--neg)"}}>{BRL(r.saldo)}</b>:<span style={{color:"var(--text-4)"}}>—</span>}</td>
                <td className="num"><b>{r.os}</b></td>
                <td><div style={{display:"flex",gap:5}}>{r.tags.slice(0,2).map((t,j)=><span key={j} style={{fontSize:10,fontWeight:600,padding:"2px 8px",borderRadius:6,background:t==="VIP"?"oklch(0.95 0.06 85)":t==="Premium"?"color-mix(in oklch,var(--m-crm) 14%,var(--bg))":"var(--sunken)",color:t==="VIP"?"oklch(0.52 0.12 75)":t==="Premium"?"var(--m-crm)":"var(--text-3)",border:"1px solid var(--border-2)"}}>{t}</span>)}</div></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ─── OS-lite (documento, denso) ───
function OficinaOS(){
  const STEPS=["Recepção","Diagnóstico","Orçamento","Aprovação","Execução","Pronto"];
  const active=2;
  return (
    <div className="ns-screen" style={{"--c":M.oficina}}>
      <div className="npage-h">
        <div className="npage-ic"><I.wrench s={18}/></div>
        <div><h1>Ordem de Serviço <span style={{fontFamily:"var(--mono)",color:"var(--accent)"}}>#4821</span></h1><p>Honda Civic EXL · Marcos Aleixo · aberta hoje 09:14</p></div>
        <div className="sp"><button className="nb ghost">Rascunho</button><button className="nb primary">Enviar orçamento</button></div>
      </div>
      <div className="ncard" style={{padding:"12px 16px",marginBottom:14,display:"flex",justifyContent:"center"}}>
        <div style={{display:"flex",alignItems:"center"}}>
          {STEPS.map((s,i)=>(
            <React.Fragment key={s}>
              {i>0&&<span style={{width:26,height:2,margin:"0 8px",background:i<=active?"color-mix(in oklch,var(--accent) 55%,var(--bg))":"var(--border)"}}/>}
              <div style={{display:"flex",alignItems:"center",gap:7}}>
                <span style={{width:22,height:22,borderRadius:"50%",display:"grid",placeItems:"center",fontFamily:"var(--mono)",fontSize:11,fontWeight:600,background:i<active?"color-mix(in oklch,var(--accent) 55%,var(--bg))":i===active?"var(--accent)":"var(--sunken)",border:i<=active?"0":"1.5px solid var(--border)",color:i<=active?"var(--on-accent)":"var(--text-4)",boxShadow:i===active?"0 0 0 4px var(--accent-soft)":"none"}}>{i<active?"✓":i+1}</span>
                <span style={{fontSize:11.5,color:i===active?"var(--text)":"var(--text-4)",fontWeight:i===active?600:400}}>{s}</span>
              </div>
            </React.Fragment>
          ))}
        </div>
      </div>
      <Costura here="OS" cap="A OS é o coração do fluxo: vem do Cliente e, quando fica pronta, vira Venda sozinha — que emite as Notas e cai no Financeiro. O gate de aprovação é o que destrava a execução."/>
      <div style={{display:"grid",gridTemplateColumns:"1fr 300px",gap:14,alignItems:"start"}}>
        <div style={{display:"flex",flexDirection:"column",gap:14}}>
          <div className="ncard"><div style={{padding:"13px 16px"}}>
            <div style={{display:"flex",alignItems:"center",gap:16}}>
              <div style={{border:"1.5px solid var(--text-3)",borderRadius:7,overflow:"hidden",width:108}}><div style={{background:"oklch(0.42 0.13 250)",color:"#fff",fontSize:7,textAlign:"center",padding:"2px",letterSpacing:".1em"}}>BRASIL</div><div style={{fontFamily:"var(--mono)",fontWeight:700,fontSize:18,textAlign:"center",padding:"4px 0"}}>RBA-2H78</div></div>
              <div style={{flex:1}}><div style={{fontSize:16,fontWeight:600,letterSpacing:"-.02em"}}>Honda Civic EXL <span style={{color:"var(--text-4)",fontWeight:500}}>2019</span></div><div style={{fontSize:11.5,color:"var(--text-4)",fontFamily:"var(--mono)",marginTop:2}}>Prata · 84.220 km</div></div>
              <div style={{textAlign:"right"}}><div style={{fontSize:13,fontWeight:600}}>Marcos Aleixo</div><div style={{fontSize:11,color:"var(--m-vendas)"}}>● vem do CRM · 6 OS</div></div>
            </div>
          </div></div>
          <div className="ncard">
            <div style={{padding:"12px 16px 10px",borderBottom:"1px solid var(--border-2)",display:"flex",alignItems:"center",gap:9}}><b style={{fontSize:13}}>Inspeção · DVI</b><span style={{marginLeft:"auto",fontSize:11,fontFamily:"var(--mono)",color:"var(--text-3)",background:"var(--sunken)",padding:"3px 9px",borderRadius:999}}>2 reprov · 2 atenção</span></div>
            <div style={{padding:"10px 16px",display:"flex",flexDirection:"column",gap:7}}>
              {[["Pastilhas dianteiras","2mm — abaixo do mínimo","r"],["Disco dianteiro","sulco perceptível","y"],["Óleo do motor","vencido / nível baixo","r"],["Pneus","4mm, ok","g"]].map((d,i)=>(
                <div key={i} style={{display:"grid",gridTemplateColumns:"auto 1fr auto",gap:12,alignItems:"center",padding:"8px 11px",borderRadius:8,background:d[2]==="r"?"var(--neg-soft)":"var(--sunken)",border:"1px solid "+(d[2]==="r"?"color-mix(in oklch,var(--neg) 35%,var(--border))":"var(--border-2)")}}>
                  <span style={{display:"flex",gap:4}}>{["g","y","r"].map(c=><span key={c} style={{width:13,height:13,borderRadius:"50%",border:"1.5px solid "+(c==="g"?"var(--pos)":c==="y"?"var(--warn)":"var(--neg)"),background:d[2]===c?(c==="g"?"var(--pos)":c==="y"?"var(--warn)":"var(--neg)"):"transparent",opacity:d[2]===c?1:.4}}/>)}</span>
                  <span style={{fontSize:12.5,fontWeight:600}}>{d[0]}<small style={{display:"block",fontSize:10.5,color:"var(--text-4)",fontWeight:400}}>{d[1]}</small></span>
                  {d[2]==="r"&&<span style={{fontSize:10.5,color:"var(--accent)",fontWeight:600}}>→ orçamento</span>}
                </div>
              ))}
            </div>
          </div>
        </div>
        <div style={{display:"flex",flexDirection:"column",gap:14}}>
          <div className="ncard"><div style={{padding:"14px 16px"}}>
            <div style={{display:"flex",justifyContent:"space-between",fontSize:12.5,padding:"5px 0"}}><span style={{color:"var(--text-2)",whiteSpace:"nowrap"}}>Mão de obra</span><b style={{fontFamily:"var(--mono)"}}>{BRL(240)}</b></div>
            <div style={{display:"flex",justifyContent:"space-between",fontSize:12.5,padding:"5px 0"}}><span style={{color:"var(--text-2)"}}>Peças</span><b style={{fontFamily:"var(--mono)"}}>{BRL(925)}</b></div>
            <div style={{display:"flex",justifyContent:"space-between",alignItems:"baseline",borderTop:"1px solid var(--border-2)",marginTop:8,paddingTop:11}}><span style={{fontSize:12,color:"var(--text-3)"}}>Total</span><b style={{fontFamily:"var(--mono)",fontSize:21,letterSpacing:"-.02em"}}>{BRL(1165)}</b></div>
          </div></div>
          <div className="ncard" style={{borderColor:"color-mix(in oklch,var(--warn) 30%,var(--border))"}}><div style={{padding:"14px 16px"}}>
            <div style={{display:"flex",alignItems:"center",gap:7,fontSize:12,fontWeight:600,color:"var(--warn)"}}><span style={{width:8,height:8,borderRadius:"50%",background:"var(--warn)"}}/>Aguardando aprovação</div>
            <p style={{fontSize:11.5,color:"var(--text-3)",lineHeight:1.5,margin:"9px 0 12px"}}>Sem o sim do cliente, a execução não inicia. Sai pelo Caixa, com as fotos da inspeção.</p>
            <button className="nb primary" style={{width:"100%",justifyContent:"center"}}>Enviar por WhatsApp</button>
          </div></div>
          <div className="ncard" style={{background:"linear-gradient(160deg,color-mix(in oklch,var(--m-vendas) 12%,var(--surface)),var(--surface))",borderColor:"color-mix(in oklch,var(--m-vendas) 28%,var(--border))"}}><div style={{padding:"13px 16px"}}>
            <div style={{display:"flex",alignItems:"center",gap:8,marginBottom:8}}><span style={{color:"var(--m-vendas)"}}><I.arrow s={15}/></span><b style={{fontSize:12.5}}>Próxima costura → Venda</b></div>
            <p style={{fontSize:11.5,color:"var(--text-2)",lineHeight:1.5,margin:0}}>OS em <b>Pronto</b> = <b>Venda</b> criada sozinha, com tudo daqui. Ninguém digita de novo.</p>
          </div></div>
        </div>
      </div>
    </div>
  );
}

function App(){
  const [route,setRoute]=useState(()=>localStorage.getItem("norte.route")||"inicio");
  const [theme,setTheme]=useState(()=>localStorage.getItem("norte.theme")||"light");
  const [density,setDensity]=useState(()=>localStorage.getItem("norte.density")||"compact");
  const [sb,setSb]=useState(()=>localStorage.getItem("norte.sb")||"expanded");
  const [htab,setHtab]=useState(0);
  const [query,setQuery]=useState("");
  const [cmdOpen,setCmdOpen]=useState(false);
  const [cheatOpen,setCheatOpen]=useState(false);
  const [iaOpen,setIaOpen]=useState(false);
  const [rec,setRec]=useState(null);
  useEffect(()=>{localStorage.setItem("norte.route",route);setHtab(0);setQuery("");},[route]);
  useEffect(()=>{localStorage.setItem("norte.theme",theme);},[theme]);
  useEffect(()=>{localStorage.setItem("norte.density",density);},[density]);
  useEffect(()=>{localStorage.setItem("norte.sb",sb);},[sb]);
  useEffect(()=>{
    const typing=()=>/^(INPUT|TEXTAREA)$/.test(document.activeElement.tagName);
    let gWait=false, gTimer=null;
    const rows=()=>[...document.querySelectorAll(".ns-body .ntbl tbody tr")].filter(r=>!r.querySelector('td[colspan]'));
    const moveK=(dir)=>{
      const rs=rows(); if(!rs.length) return;
      let cur=rs.findIndex(r=>r.classList.contains("kfocus"));
      cur = cur<0 ? (dir>0?0:rs.length-1) : Math.min(rs.length-1,Math.max(0,cur+dir));
      rs.forEach(r=>r.classList.remove("kfocus"));
      rs[cur].classList.add("kfocus");
      rs[cur].scrollIntoView({block:"nearest"});
    };
    const onKey=(e)=>{
      if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==="k"){ e.preventDefault(); setCmdOpen(o=>!o); return; }
      if(typing()) return;
      const k=e.key.toLowerCase();
      if(gWait){ gWait=false; clearTimeout(gTimer); if(k==="i"){ e.preventDefault(); setRoute("inicio"); return; } }
      if(e.key==="/"||k==="b"){ e.preventDefault(); const el=document.querySelector(".ns-search input"); el&&el.focus(); }
      else if(e.key==="?"){ e.preventDefault(); setCheatOpen(o=>!o); }
      else if(e.key==="j"){ e.preventDefault(); moveK(1); }
      else if(e.key==="k"){ e.preventDefault(); moveK(-1); }
      else if(e.key==="Enter"){ const f=document.querySelector(".ns-body .ntbl tbody tr.kfocus"); if(f){ e.preventDefault(); f.click(); } }
      else if(k==="n"){ const btn=document.querySelector(".ns-body .nb.primary, .ns-body .npage-h .nb.primary"); if(btn){ e.preventDefault(); btn.click(); } }
      else if(e.key==="Escape"){ rows().forEach(r=>r.classList.remove("kfocus")); }
      else if(k==="d"){ setDensity(d=>d==="compact"?"cozy":"compact"); }
      else if(k==="t"){ setTheme(t=>t==="light"?"dark":"light"); }
      else if(e.key==="["){ setSb(s=>s==="rail"?"expanded":"rail"); }
      else if(k==="g"){ gWait=true; clearTimeout(gTimer); gTimer=setTimeout(()=>{gWait=false;},800); }
      else if(k==="a"){ e.preventDefault(); setIaOpen(o=>!o); }
    };
    window.addEventListener("keydown",onKey);
    return ()=>window.removeEventListener("keydown",onKey);
  },[]);

  const CMD=window.NorteCommand||{};
  const CMD_ACTIONS=[
    {label:"Perguntar à Norte IA",hint:"✦ A",run:()=>setIaOpen(true)},
    {label:"Resumir esta tela com IA",hint:"✦",run:()=>setIaOpen(true)},
    {label:"Novo cliente",hint:"CRM",run:()=>setRoute("clientes")},
    {label:"Nova OS",hint:"Oficina",run:()=>setRoute("oficina")},
    {label:"Nova venda",hint:"Vendas",run:()=>setRoute("vendas")},
    {label:"Novo orçamento",hint:"Comercial",run:()=>setRoute("orcamentos")},
    {label:"Alternar tema claro/escuro",hint:"T",run:()=>setTheme(t=>t==="light"?"dark":"light")},
    {label:"Alternar densidade",hint:"D",run:()=>setDensity(d=>d==="compact"?"cozy":"compact")},
    {label:"Ver atalhos de teclado",hint:"?",run:()=>setCheatOpen(true)},
  ];

  const it=FLAT[route]||{label:route,c:"var(--m-vendas)"};
  const tabs=HEAD_TABS[route];
  let body;
  if(route==="inicio") body=<Dashboard/>;
  else if(route==="caixa") body=<Caixa query={query} onQuery={setQuery}/>;
  else if(route==="vendas") body=<Vendas query={query} onQuery={setQuery} onOpen={setRec}/>;
  else if(route==="clientes") body=<Clientes query={query} onQuery={setQuery} onOpen={setRec}/>;
  else if(route==="oficina") body=<OficinaOS/>;
  else if(route==="financeiro") body=<Financeiro/>;
  else body=<GenericMod id={route} query={query} onQuery={setQuery} onOpen={setRec}/>;

  const SEARCH_PH={inicio:"Buscar em tudo…",caixa:"Buscar conversa ou contato…",oficina:"Buscar OS, placa, cliente…",vendas:"Buscar venda ou cliente…",clientes:"Buscar nome, CNPJ, cidade…",financeiro:"Buscar título ou cliente…"};

  const SEC_OF={inicio:"Hoje",caixa:"Caixa",oficina:"Operação",vendas:"Operação",orcamentos:"Operação",produtos:"Operação",clientes:"Operação",fila:"Produção",expedicao:"Produção",estoque:"Produção",financeiro:"Financeiro",fiscal:"Financeiro",cobranca:"Financeiro",crm:"Gestão",compras:"Gestão",relatorios:"Gestão",kb:"Gestão",config:"Gestão"};

  return (
    <div className="ns" data-theme={theme} data-density={density} data-sb={sb}>
      <aside className="ns-side">
        <div className="ns-side-top">
          <div className="ns-logo">Oi</div>
          <div className="ns-logo-tx"><b>Oimpresso</b><small>ROTA LIVRE</small></div>
        </div>
        <nav className="ns-nav">
          {NAV.map(g=>(
            <div key={g.sec}>
              <div className="ns-navsec">{g.sec}</div>
              {g.items.map(x=>{
                const Icon=I[x.ic];
                return (
                  <button key={x.id} className={"ns-item"+(route===x.id?" on":"")} style={{"--c":x.c}} onClick={()=>setRoute(x.id)} title={x.label}>
                    <span className="dot"/><span className="lbl">{x.label}</span>{x.n&&<span className="n">{x.n}</span>}
                  </button>
                );
              })}
            </div>
          ))}
        </nav>
        <div className="ns-side-foot">
          <div className="ns-user-av">LA</div>
          <div className="ns-user-tx"><b>Larissa</b><small>Balcão · ROTA LIVRE</small></div>
        </div>
      </aside>

      <div className="ns-main">
        <header className="ns-head" style={{"--c":it.c}}>
          <button className="ns-railtog" onClick={()=>setSb(s=>s==="rail"?"expanded":"rail")} title="Alternar sidebar"><I.panel s={15}/></button>
          <div className="ns-crumb">
            <span className="mdot"/>
            <span className="area">{SEC_OF[route]||"Oimpresso"}</span>
            <span className="sep">/</span>
            <h2>{it.label}</h2>
          </div>
          {tabs&&<div className="ns-tabs" style={{"--c":it.c}}>{tabs.map((t,i)=><button key={i} className={htab===i?"on":""} onClick={()=>setHtab(i)}>{t}</button>)}</div>}
          <div className="ns-head-r">
            <div className="ns-search">
              <I.search s={14}/>
              <input value={query} onChange={e=>setQuery(e.target.value)} placeholder={SEARCH_PH[route]||"Buscar…"} aria-label="Buscar"/>
              <kbd>/</kbd>
            </div>
            <button className="ns-ia" onClick={()=>setIaOpen(true)} title="Norte IA (A)"><I.spark s={14}/><span className="lab">Norte IA</span></button>
            <button className="ns-cmdk" onClick={()=>setCmdOpen(true)} title="Paleta de comandos (⌘K)"><kbd>⌘</kbd><kbd>K</kbd><span className="lab">comandos</span></button>
            <div className="ns-seg">
              <button className={theme==="light"?"on":""} onClick={()=>setTheme("light")}><I.sun s={13}/></button>
              <button className={theme==="dark"?"on":""} onClick={()=>setTheme("dark")}><I.moon s={13}/></button>
            </div>
            <div className="ns-seg">
              <button className={density==="compact"?"on":""} onClick={()=>setDensity("compact")}><I.rows s={12}/> Denso</button>
              <button className={density==="cozy"?"on":""} onClick={()=>setDensity("cozy")}>Amplo</button>
            </div>
            <button className="ns-ibtn"><I.bell s={14}/></button>
            <span className="ns-tenant">RL</span>
          </div>
        </header>
        <div className="ns-body">{body}</div>
      </div>
      {CMD.CommandPalette && <CMD.CommandPalette open={cmdOpen} onClose={()=>setCmdOpen(false)} nav={NAV} onGo={setRoute} actions={CMD_ACTIONS}/>}
      {CMD.CheatSheet && <CMD.CheatSheet open={cheatOpen} onClose={()=>setCheatOpen(false)}/>}
      {window.NorteIA && window.NorteIA.IAPanel && <window.NorteIA.IAPanel open={iaOpen} onClose={()=>setIaOpen(false)} route={route}/>}
      {window.NorteDetail && window.NorteDetail.RecordDrawer && <window.NorteDetail.RecordDrawer record={rec} onClose={()=>setRec(null)} onGo={setRoute}/>}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<App/>);
})();
