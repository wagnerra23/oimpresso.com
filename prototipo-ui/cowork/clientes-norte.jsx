// clientes-norte.jsx — Clientes (módulo Pessoas) vestido com a identidade Norte.
// Dados representativos fiéis ao schema real (Frescor · Saldo · OS · VIP · Tags · Endereços).
(() => {
const { useState, useMemo } = React;

// ── ícones (componentes que recebem {s}) ──
const Svg = ({s=14,w=2,children}) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">{children}</svg>;
const I = {
  search:(p)=><Svg {...p}><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></Svg>,
  plus:(p)=><Svg {...p}><path d="M12 5v14M5 12h14"/></Svg>,
  upload:(p)=><Svg {...p}><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 20h16"/></Svg>,
  chev:(p)=><Svg {...p}><path d="M6 9l6 6 6-6"/></Svg>,
  phone:(p)=><Svg {...p}><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/></Svg>,
  pin:(p)=><Svg {...p}><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></Svg>,
  star:(p)=><Svg {...p}><path d="M12 2l3 6.5 7 .9-5 4.8 1.3 7L12 18l-6.3 3.2L7 14.2 2 9.4l7-.9L12 2z"/></Svg>,
  more:(p)=><Svg {...p}><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></Svg>,
  x:(p)=><Svg {...p}><path d="M18 6L6 18M6 6l12 12"/></Svg>,
  copy:(p)=><Svg {...p}><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></Svg>,
  pencil:(p)=><Svg {...p}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></Svg>,
  users:(p)=><Svg {...p}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></Svg>,
  sliders:(p)=><Svg {...p}><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/></Svg>,
  sun:(p)=><Svg {...p}><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></Svg>,
  moon:(p)=><Svg {...p}><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></Svg>,
  rows:(p)=><Svg {...p}><rect x="3" y="4" width="18" height="5" rx="1"/><rect x="3" y="13" width="18" height="5" rx="1"/></Svg>,
};
const fmtBRL=(n)=> (n||0).toLocaleString("pt-BR",{style:"currency",currency:"BRL"});
const fmtK=(n)=> !n?"R$ 0":(n>=1000?"R$ "+(n/1000).toFixed(1).replace(".",",")+"k":fmtBRL(n));

// ── avatar (paleta harmonizada com a paleta de módulos) ──
const AV=["oklch(0.55 0.13 268)","oklch(0.56 0.12 195)","oklch(0.58 0.12 65)","oklch(0.57 0.14 18)","oklch(0.55 0.12 150)","oklch(0.55 0.13 295)","oklch(0.55 0.12 235)","oklch(0.56 0.13 330)"];
const hash=(s)=>{let h=0;for(let i=0;i<s.length;i++)h=(h*31+s.charCodeAt(i))|0;return Math.abs(h);};
const initials=(n)=>n.split(" ").slice(0,2).map(s=>s[0]).join("").toUpperCase();

// ── dados ──
const CLIENTS=[
  { id:"1", name:"Frota Boa Esperança", doc:"34.567.890/0001-12", tipo:"PJ", city:"Guarulhos", uf:"SP", phone:"(11) 9 9988-7766", contact:"Anderson Lima",
    fresc:{s:"recente",l:"há 4d"}, saldo:0, os:{t:22,o:3,late:0}, val:48200, tags:["VIP","Premium"], vip:true, novo:false, lastOs:"OS #8821",
    addr:[{lbl:"Sede",cad:true,ent:true,log:"Av. Monteiro Lobato",num:"2400",comp:"Galpão 3",bairro:"Cumbica",cidade:"Guarulhos",uf:"SP",cep:"07190-000"}] },
  { id:"2", name:"Transportes Anderson ME", doc:"23.456.789/0001-01", tipo:"PJ", city:"Campinas", uf:"SP", phone:"(19) 9 9123-4455", contact:"Marcos Andrade",
    fresc:{s:"fresc",l:"há 3sem"}, saldo:3910, os:{t:8,o:2,late:1}, val:21400, tags:["Boleto"], vip:false, novo:false, lastOs:"OS #8790",
    addr:[{lbl:"Matriz",cad:true,ent:true,log:"Rua Barão de Jaguara",num:"1180",comp:"",bairro:"Centro",cidade:"Campinas",uf:"SP",cep:"13015-002"}] },
  { id:"3", name:"Gráfica Rota Livre", doc:"12.345.678/0001-90", tipo:"PJ", city:"São Paulo", uf:"SP", phone:"(11) 9 8877-1020", contact:"Larissa Couto",
    fresc:{s:"recente",l:"há 1sem"}, saldo:0, os:{t:14,o:1,late:0}, val:33600, tags:["VIP","PIX"], vip:true, novo:false, lastOs:"OS #8744",
    addr:[{lbl:"Loja",cad:true,ent:false,log:"Rua Vergueiro",num:"3200",comp:"Loja 4",bairro:"Vila Mariana",cidade:"São Paulo",uf:"SP",cep:"04101-300"},
          {lbl:"Depósito",cad:false,ent:true,log:"Rua do Manifesto",num:"890",comp:"",bairro:"Ipiranga",cidade:"São Paulo",uf:"SP",cep:"04209-000"}] },
  { id:"4", name:"Auto Center BH", doc:"67.890.123/0001-45", tipo:"PJ", city:"Belo Horizonte", uf:"MG", phone:"(31) 9 9456-7788", contact:"Roberto Dias",
    fresc:{s:"distante",l:"há 1m"}, saldo:740, os:{t:7,o:0,late:1}, val:9800, tags:["Boleto","Atraso recorrente"], vip:false, novo:false, lastOs:"OS #8390",
    addr:[{lbl:"Sede",cad:true,ent:true,log:"Av. Cristiano Machado",num:"4100",comp:"",bairro:"Cidade Nova",cidade:"Belo Horizonte",uf:"MG",cep:"31170-000"}] },
  { id:"5", name:"Móveis Diadema LTDA", doc:"56.789.012/0001-34", tipo:"PJ", city:"Diadema", uf:"SP", phone:"(11) 9 9321-6677", contact:"Sônia Ribeiro",
    fresc:{s:"fresc",l:"há 2sem"}, saldo:0, os:{t:11,o:1,late:0}, val:17200, tags:["Indicador"], vip:false, novo:false, lastOs:"OS #8712",
    addr:[{lbl:"Fábrica",cad:true,ent:true,log:"Av. Fábio Eduardo Ramos",num:"560",comp:"",bairro:"Eldorado",cidade:"Diadema",uf:"SP",cep:"09971-160"}] },
  { id:"6", name:"Niterói Print", doc:"78.901.234/0001-56", tipo:"PJ", city:"Niterói", uf:"RJ", phone:"(21) 9 9777-2233", contact:"Felipe Sá",
    fresc:{s:"recente",l:"há 6d"}, saldo:0, os:{t:9,o:2,late:0}, val:26100, tags:["VIP"], vip:true, novo:false, lastOs:"OS #8801",
    addr:[{lbl:"Sede",cad:true,ent:true,log:"Rua da Conceição",num:"45",comp:"Sala 8",bairro:"Centro",cidade:"Niterói",uf:"RJ",cep:"24020-080"}] },
  { id:"7", name:"Eduardo Martins", doc:"123.456.789-00", tipo:"PF", city:"Osasco", uf:"SP", phone:"(11) 9 9654-3322", contact:"Eduardo Martins",
    fresc:{s:"frio",l:"sem histórico"}, saldo:0, os:{t:0,o:0,late:0}, val:0, tags:[], vip:false, novo:true, lastOs:null, addr:[] },
  { id:"8", name:"Curitiba Sinalização", doc:"89.012.345/0001-67", tipo:"PJ", city:"Curitiba", uf:"PR", phone:"(41) 9 9888-5544", contact:"Paula Stein",
    fresc:{s:"distante",l:"há 2m"}, saldo:1280, os:{t:5,o:0,late:0}, val:8400, tags:["Boleto"], vip:false, novo:false, lastOs:"OS #8512",
    addr:[{lbl:"Sede",cad:true,ent:true,log:"Rua XV de Novembro",num:"1300",comp:"Conj. 22",bairro:"Centro",cidade:"Curitiba",uf:"PR",cep:"80020-310"}] },
];

const NAV=[
  {label:"Oficina",c:"var(--m-oficina)",n:"24"},
  {label:"Vendas",c:"var(--m-vendas)",n:"38"},
  {label:"Compras",c:"var(--m-compras)",n:"12"},
  {label:"Financeiro",c:"var(--m-financeiro)",n:"7"},
  {label:"Fiscal",c:"var(--m-fiscal)",n:"3"},
  {label:"Clientes",c:"var(--m-crm)",n:"142",on:true},
  {label:"Caixa",c:"var(--m-inbox)",n:"5"},
  {label:"Estoque",c:"var(--m-estoque)",n:"—"},
];
const TABS=[["Todos","210"],["Clientes","142",true],["Fornecedores","38"],["Funcionários","19"],["Representantes","11"]];

function Avatar({name,size}){ const c=AV[hash(name)%AV.length]; return <div className="cn-av" style={{background:c,width:size,height:size,fontSize:size>=44?15:12}}>{initials(name)}</div>; }

function Fresc({f}){ return <span className={"cn-fresc "+f.s}><span className="fd"/><span className="fs">{f.s}</span><span className="fl">· {f.l}</span></span>; }

function Drawer({cli,onClose}){
  const flow=[
    {label:"CRM", c:"var(--m-crm)", v:"cadastro", on:true},
    {label:"Oficina", c:"var(--m-oficina)", v: cli.os.t?`${cli.os.t} OS`:"—", on:cli.os.t>0},
    {label:"Vendas", c:"var(--m-vendas)", v: cli.os.t?`${Math.max(1,Math.round(cli.os.t*0.6))} vendas`:"—", on:cli.os.t>0},
    {label:"Fiscal", c:"var(--m-fiscal)", v: cli.os.t?"NF-e/NFS-e":"—", on:cli.os.t>0},
    {label:"Financeiro", c:"var(--m-financeiro)", v: cli.saldo>0?"saldo aberto":"em dia", on:true},
  ];
  return (
    <div className="cn-drawer-back" onClick={onClose}>
      <div className="cn-drawer" onClick={(e)=>e.stopPropagation()}>
        <div className="cn-dr-head">
          <Avatar name={cli.name} size={46}/>
          <div style={{minWidth:0}}>
            <div className="cn-dr-name">{cli.name}{cli.vip&&<span className="cn-vip">VIP</span>}</div>
            <div className="cn-dr-doc">
              <span className={"cn-tipo "+cli.tipo.toLowerCase()}>{cli.tipo}</span>
              <span className="cn-doc">{cli.doc}</span><span className="sep">·</span>
              <span>{cli.city}/{cli.uf}</span>
            </div>
          </div>
          <button className="cn-dr-x" onClick={onClose}><I.x s={16}/></button>
        </div>
        <div className="cn-dr-body">
          <div className="cn-dr-kpis">
            <div className="cn-dr-kpi"><div className="v">{cli.os.t}</div><div className="l">OS no total</div></div>
            <div className="cn-dr-kpi"><div className="v">{cli.os.o}</div><div className="l">Em aberto</div></div>
            <div className={"cn-dr-kpi"+(cli.os.late?" danger":"")}><div className="v">{cli.os.late}</div><div className="l">Atrasadas</div></div>
            <div className="cn-dr-kpi"><div className="v">{fmtK(cli.val)}</div><div className="l">Valor total</div></div>
          </div>

          <div className="cn-sec">
            <div className="cn-sec-t">Costura · onde este cliente vive no fluxo</div>
            <div className="cn-flow">
              <div className="cn-flow-cap">O cliente nasce no <b>CRM</b> e costura pra frente — nunca é recadastrado. Aceso onde tem atividade.</div>
              <div className="cn-spine">
                {flow.map((m,i)=>(
                  <div key={i} className={"cn-spine-node"+(m.on?" on":"")} style={{"--c":m.c}}>
                    <span className="cn-spine-line"/>
                    <span className="cn-spine-dot"/>
                    <span className="cn-spine-lbl">{m.label}</span>
                    <span className="cn-spine-v">{m.v}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="cn-sec">
            <div className="cn-sec-t">Frescor &amp; Saldo</div>
            <div className="cn-info">
              <div><div className="l">Frescor</div><div className="v"><Fresc f={cli.fresc}/></div></div>
              <div><div className="l">Saldo em aberto</div><div className="v">{cli.saldo?<span className="cn-saldo">{fmtBRL(cli.saldo)}</span>:<span className="cn-muted">—</span>}</div></div>
              <div style={{gridColumn:"1 / -1"}}><div className="l">Tags</div><div className="v cn-tags" style={{marginTop:5}}>{cli.tags.length?cli.tags.map((t,i)=><span key={i} className={"cn-tag"+(t==="VIP"?" vip":t==="Premium"?" premium":"")}>{t}</span>):<span className="cn-muted">—</span>}</div></div>
            </div>
          </div>

          <div className="cn-sec">
            <div className="cn-sec-t">Contato</div>
            <div className="cn-info">
              <div><div className="l">Responsável</div><div className="v">{cli.contact}</div></div>
              <div><div className="l">Telefone</div><div className="v" style={{fontFamily:"var(--mono)"}}>{cli.phone}</div></div>
              <div><div className="l">CNPJ/CPF</div><div className="v cn-doc">{cli.doc}</div></div>
              <div><div className="l">Última OS</div><div className="v">{cli.lastOs||"—"}</div></div>
            </div>
          </div>

          <div className="cn-sec">
            <div className="cn-sec-t">Endereços <button className="cn-sec-add"><I.plus s={12}/> Adicionar</button></div>
            {cli.addr.length===0 && <div className="cn-muted" style={{fontSize:12.5,display:"flex",gap:7,alignItems:"center"}}><I.pin s={14}/> Nenhum endereço cadastrado.</div>}
            {cli.addr.map((a,i)=>(
              <div key={i} className={"cn-addr"+(a.ent?" entrega":"")}>
                <div className="cn-addr-top">
                  <span className="cn-addr-lbl"><I.pin s={11}/> {a.lbl}</span>
                  {a.cad&&<span className="cn-addr-flag cad">Cadastro</span>}
                  {a.ent&&<span className="cn-addr-flag ent">Entrega padrão</span>}
                  <button className="cn-addr-copy"><I.copy s={13}/></button>
                </div>
                <div className="cn-addr-line">{a.log}, <strong>{a.num}</strong>{a.comp?` · ${a.comp}`:""}</div>
                <div className="cn-addr-sub"><span>{a.bairro}</span><span className="d">·</span><span>{a.cidade}/{a.uf}</span><span className="d">·</span><span className="cep">CEP {a.cep}</span></div>
              </div>
            ))}
          </div>

          <div className="cn-sec">
            <div className="cn-sec-t">Histórico de OS ({cli.os.t})</div>
            <div className="cn-hist">
              {cli.os.t===0 && <div className="cn-muted" style={{fontSize:12.5}}>Nenhuma OS registrada.</div>}
              {cli.os.t>0 && [
                {id:cli.lastOs?cli.lastOs.replace("OS #",""):"—",prod:"Plotagem + recorte adesivo",stage:"Pronto",val:"R$ 5.440"},
                {id:"8612",prod:"Banner lona 440g · 3×1m",stage:"Entregue",val:"R$ 890"},
                {id:"8501",prod:"Cartão de visita · 1000un",stage:"Entregue",val:"R$ 320"},
              ].slice(0,cli.os.t>=3?3:cli.os.t).map((o,i)=>(
                <div key={i} className="cn-os"><span className="cn-os-id">#{o.id}</span><span className="cn-os-prod">{o.prod}</span><span className="cn-os-stage">{o.stage}</span><span className="cn-os-val">{o.val}</span></div>
              ))}
            </div>
          </div>
        </div>
        <div className="cn-dr-foot">
          <button className="btn primary"><I.plus s={14}/> Nova OS</button>
          <button className="btn"><I.pencil s={14}/> Editar cliente</button>
          <button className="btn ghost">Ver financeiro</button>
        </div>
      </div>
    </div>
  );
}

function App(){
  const [q,setQ]=useState("");
  const [openId,setOpenId]=useState(null);
  const [theme,setTheme]=useState(()=>localStorage.getItem("cli.theme")||"light");
  const [density,setDensity]=useState(()=>localStorage.getItem("cli.density")||"compact");
  const setT=(v)=>{setTheme(v);localStorage.setItem("cli.theme",v);};
  const setD=(v)=>{setDensity(v);localStorage.setItem("cli.density",v);};
  const list=useMemo(()=>CLIENTS.filter(c=> !q || `${c.name} ${c.doc} ${c.contact} ${c.city}`.toLowerCase().includes(q.toLowerCase())),[q]);
  const open=openId?CLIENTS.find(c=>c.id===openId):null;

  return (
    <div className="cli-screen" data-theme={theme} data-density={density}>
      <aside className="cn-side">
        <div className="cn-brand"><div className="cn-logo">Oi</div><div><b>Oimpresso</b><small>ROTA LIVRE</small></div></div>
        <div className="cn-navsec">Operação</div>
        {NAV.map((it,i)=>(
          <button key={i} className={"cn-nav"+(it.on?" on":"")} style={{"--c":it.c}}>
            <span className="dot"/><span>{it.label}</span><span className="n">{it.n}</span>
          </button>
        ))}
        <div className="cn-side-foot"><div className="av">LA</div><div><b>Larissa</b><small>Balcão · ROTA LIVRE</small></div></div>
      </aside>

      <main className="cn-main">
        <header className="cn-head">
          <div className="cn-head-icon"><I.users s={20}/></div>
          <div><h1>Clientes</h1><p><b>142</b> cadastrados</p></div>
          <div className="cn-head-actions">
            <div className="cn-seg" role="group" aria-label="Tema">
              <button className={theme==="light"?"on":""} onClick={()=>setT("light")}><I.sun s={13}/> Claro</button>
              <button className={theme==="dark"?"on":""} onClick={()=>setT("dark")}><I.moon s={13}/> Escuro</button>
            </div>
            <div className="cn-seg" role="group" aria-label="Densidade">
              <button className={density==="compact"?"on":""} onClick={()=>setD("compact")}><I.rows s={13}/> Denso</button>
              <button className={density==="cozy"?"on":""} onClick={()=>setD("cozy")}>Confortável</button>
            </div>
            <button className="btn ghost"><I.upload s={13}/> Importar</button>
            <button className="btn primary"><I.plus s={13}/> Novo cliente</button>
          </div>
        </header>

        <nav className="cn-tabs">
          {TABS.map(([label,n,on],i)=>(
            <button key={i} className={"cn-tab"+(on?" on":"")}>{label}<span className="n">{n}</span></button>
          ))}
        </nav>

        <div className="cn-body">
          <div className="cn-kpis">
            <div className="cn-kpi accent"><div className="cn-kpi-l">Clientes ativos</div><div className="cn-kpi-v">38</div><div className="cn-kpi-s">com OS aberta</div></div>
            <div className="cn-kpi"><div className="cn-kpi-l">VIPs</div><div className="cn-kpi-v">12</div><div className="cn-kpi-s">prioridade total</div></div>
            <div className="cn-kpi danger"><div className="cn-kpi-l">Com saldo</div><div className="cn-kpi-v">9</div><div className="cn-kpi-s"><span className="cn-kpi-aside">R$ 18,4k</span> em aberto</div></div>
            <div className="cn-kpi"><div className="cn-kpi-l">Sem compra 90d</div><div className="cn-kpi-v">21</div><div className="cn-kpi-s">risco churn</div></div>
            <div className="cn-kpi"><div className="cn-kpi-l">Novos este mês</div><div className="cn-kpi-v">6</div><div className="cn-kpi-s">desde dia 1</div></div>
            <div className="cn-kpi dark"><div className="cn-kpi-l">Faturamento</div><div className="cn-kpi-v">R$ 248k</div><div className="cn-kpi-s">hoje · <span className="up">+12%</span> vs ontem</div></div>
          </div>

          <div className="cn-tools">
            <div className="cn-search"><I.search s={15}/><input value={q} onChange={(e)=>setQ(e.target.value)} placeholder="Buscar nome, CNPJ/CPF, contato, cidade…"/><kbd>/</kbd></div>
            <button className="cn-filter active"><I.sliders s={13}/> Status<I.chev s={11}/></button>
            <button className="cn-filter">Tipo<I.chev s={11}/></button>
            <button className="cn-filter">UF<I.chev s={11}/></button>
            <button className="cn-filter">Tags<I.chev s={11}/></button>
            <button className="cn-filter">Saldo<I.chev s={11}/></button>
            <span className="cn-tools-count">{list.length} clientes</span>
          </div>

          <div className="cn-table-wrap">
            <table className="cn-table">
              <thead><tr>
                <th>Cliente</th><th>Tipo</th><th>Documento</th><th>Cidade/UF</th><th>Frescor</th>
                <th className="num">Saldo</th><th className="num">OS</th><th>Tags</th><th>Última OS</th><th></th><th></th>
              </tr></thead>
              <tbody>
                {list.map((c)=>(
                  <tr key={c.id} className="cn-row" onClick={()=>setOpenId(c.id)}>
                    <td>
                      <div className="cn-cli"><Avatar name={c.name} size={34}/>
                        <div><div className="cn-cli-name">{c.name}{c.vip&&<span className="cn-vip">VIP</span>}{c.novo&&<span className="cn-new">Novo</span>}</div>
                        <div className="cn-cli-sub"><I.phone s={10}/><span>{c.phone}</span></div></div>
                      </div>
                    </td>
                    <td><span className={"cn-tipo "+c.tipo.toLowerCase()}>{c.tipo}</span></td>
                    <td><span className="cn-doc">{c.doc}</span></td>
                    <td><span className="cn-city">{c.city}<span className="uf">{c.uf}</span></span></td>
                    <td><Fresc f={c.fresc}/></td>
                    <td className="num">{c.saldo?<span className="cn-saldo">{fmtBRL(c.saldo)}</span>:<span className="cn-muted">—</span>}</td>
                    <td className="num">{c.os.t?<span className="cn-os-n">{c.os.t}</span>:<span className="cn-muted">0</span>}</td>
                    <td><div className="cn-tags">{c.tags.length===0&&<span className="cn-muted">—</span>}{c.tags.slice(0,2).map((t,i)=><span key={i} className={"cn-tag"+(t==="VIP"?" vip":t==="Premium"?" premium":"")}>{t}</span>)}{c.tags.length>2&&<span className="cn-tag-more">+{c.tags.length-2}</span>}</div></td>
                    <td>{c.lastOs?<span className="cn-lastos" onClick={(e)=>e.stopPropagation()}>{c.lastOs}</span>:<span className="cn-muted">—</span>}</td>
                    <td><button className="cn-fav" onClick={(e)=>{e.stopPropagation();e.currentTarget.classList.toggle("on");}}><I.star s={14}/></button></td>
                    <td><button className="cn-keb" onClick={(e)=>e.stopPropagation()}><I.more s={14}/></button></td>
                  </tr>
                ))}
                {list.length===0 && <tr><td colSpan={11} style={{textAlign:"center",padding:"28px",color:"var(--text-4)"}}>Nenhum cliente encontrado.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </main>

      {open && <Drawer cli={open} onClose={()=>setOpenId(null)}/>}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<App/>);
})();
