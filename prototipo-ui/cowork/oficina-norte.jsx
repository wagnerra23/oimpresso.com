// oficina-norte.jsx — OS (Oficina) vestida com a identidade Norte (âmbar).
// Reusa o shell de clientes-norte.css. A costura liga a OS pra frente no fluxo.
(() => {
const { useState, useEffect } = React;

const Svg = ({s=14,w=2,children}) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={w} strokeLinecap="round" strokeLinejoin="round">{children}</svg>;
const I = {
  plus:(p)=><Svg {...p}><path d="M12 5v14M5 12h14"/></Svg>,
  chev:(p)=><Svg {...p}><path d="M6 9l6 6 6-6"/></Svg>,
  arrow:(p)=><Svg {...p}><path d="M5 12h14M13 6l6 6-6 6"/></Svg>,
  phone:(p)=><Svg {...p}><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/></Svg>,
  clock:(p)=><Svg {...p}><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></Svg>,
  search:(p)=><Svg {...p}><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></Svg>,
  send:(p)=><Svg {...p}><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></Svg>,
  check:(p)=><Svg {...p}><path d="M5 13l4 4 10-10"/></Svg>,
  x:(p)=><Svg {...p}><path d="M18 6L6 18M6 6l12 12"/></Svg>,
  receipt:(p)=><Svg {...p}><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1V2l-2 1-2-1-2 1-2-1-2 1-2-1z"/><path d="M8 7h8M8 11h8M8 15h5"/></Svg>,
  shield:(p)=><Svg {...p}><path d="M12 2l8 3v6c0 5-3.5 8.5-8 11-4.5-2.5-8-6-8-11V5l8-3z"/></Svg>,
  cam:(p)=><Svg {...p}><path d="M3 8a2 2 0 0 1 2-2h2l1.5-2h7L17 6h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><circle cx="12" cy="12.5" r="3.2"/></Svg>,
  info:(p)=><Svg {...p}><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></Svg>,
  wrench:(p)=><Svg {...p}><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.1-.5-.5-2.1 2.6-2.6z"/></Svg>,
  cart:(p)=><Svg {...p}><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12.5a1.7 1.7 0 0 0 1.7 1.3h8.6a1.7 1.7 0 0 0 1.7-1.3L23 7H6"/></Svg>,
  sun:(p)=><Svg {...p}><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></Svg>,
  moon:(p)=><Svg {...p}><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></Svg>,
  rows:(p)=><Svg {...p}><rect x="3" y="4" width="18" height="5" rx="1"/><rect x="3" y="13" width="18" height="5" rx="1"/></Svg>,
};
const BRL = (n) => "R$ " + n.toLocaleString("pt-BR",{minimumFractionDigits:2,maximumFractionDigits:2});

const NAV=[
  {label:"Oficina",c:"var(--m-oficina)",n:"24",on:true},
  {label:"Vendas",c:"var(--m-vendas)",n:"38"},
  {label:"Compras",c:"var(--m-compras)",n:"12"},
  {label:"Financeiro",c:"var(--m-financeiro)",n:"7"},
  {label:"Fiscal",c:"var(--m-fiscal)",n:"3"},
  {label:"Clientes",c:"var(--m-crm)",n:"142"},
  {label:"Caixa",c:"var(--m-inbox)",n:"5"},
  {label:"Estoque",c:"var(--m-estoque)",n:"—"},
];
const STEPS=["Recepção","Diagnóstico","Orçamento","Aprovação","Execução","Pronto"];
const FLOW=[
  {label:"Cliente", c:"var(--m-crm)", v:"Marcos Aleixo", on:true},
  {label:"OS", c:"var(--m-oficina)", v:"#4821 · orçamento", on:true, here:true},
  {label:"Venda", c:"var(--m-vendas)", v:"aguarda OS pronta", on:false},
  {label:"Nota", c:"var(--m-fiscal)", v:"NF-e + NFS-e", on:false},
  {label:"Financeiro", c:"var(--m-financeiro)", v:"a receber", on:false},
];
const DVI0=[
  {nm:"Pastilhas dianteiras", sub:"espessura 2mm — abaixo do mínimo", s:"r", photo:true, sug:120},
  {nm:"Disco dianteiro", sub:"sulco perceptível", s:"y", photo:true, sug:90},
  {nm:"Óleo do motor", sub:"vencido / nível baixo", s:"r", photo:false, sug:60},
  {nm:"Pneus", sub:"sulco ok, 4mm", s:"g", photo:false, sug:0},
  {nm:"Bateria", sub:"12.3V — observar", s:"y", photo:false, sug:0},
];
const SERV0=[
  {nm:"Troca de pastilhas + disco dianteiro", ab:"JL", mech:"João Lima", h:"1,5h", v:180},
  {nm:"Troca de óleo + filtro", ab:"PS", mech:"Pedro Souza", h:"0,5h", v:60},
];
const PECAS=[
  {nm:"Jogo pastilhas dianteiras Bosch", sku:"BRP-001", qty:"1 un", stock:"ok", v:280},
  {nm:"Disco de freio dianteiro (par)", sku:"DSC-118", qty:"1 par", stock:"res", v:420},
  {nm:"Óleo 5W30 sintético", sku:"OLE-5W30", qty:"4 L", stock:"ok", v:180},
  {nm:"Filtro de óleo", sku:"FLT-220", qty:"1 un", stock:"ok", v:45},
];

function Stepper(){
  const active=2;
  return (
    <div className="ofx-fsm">
      {STEPS.map((s,i)=>(
        <React.Fragment key={s}>
          {i>0 && <span className={"ofx-step-line"+(i<=active?" done":"")}/>}
          <div className={"ofx-step "+(i<active?"done":i===active?"active":"")}>
            <span className="dot">{i<active?<I.check s={12}/>:i+1}</span>
            <span className="lbl">{s}</span>
          </div>
        </React.Fragment>
      ))}
    </div>
  );
}

function App(){
  const [theme,setTheme]=useState(()=>localStorage.getItem("ofx.theme")||"light");
  const [density,setDensity]=useState(()=>localStorage.getItem("ofx.density")||"compact");
  const setT=(v)=>{setTheme(v);localStorage.setItem("ofx.theme",v);};
  const setD=(v)=>{setDensity(v);localStorage.setItem("ofx.density",v);};
  const [dvi,setDvi]=useState(DVI0.map(x=>({...x})));
  const [serv,setServ]=useState(SERV0.map(x=>({...x})));
  const [added,setAdded]=useState({});
  const [tab,setTab]=useState("servicos");
  const [appr,setAppr]=useState("aguardando");
  const [toast,setToast]=useState(null);

  const ping=(m)=>{setToast(m);clearTimeout(ping._t);ping._t=setTimeout(()=>setToast(null),2400);};
  const setS=(i,s)=>setDvi(p=>p.map((x,j)=>j===i?{...x,s}:x));
  const addReprov=(it)=>{ setServ(p=>[...p,{nm:"Verificar/trocar — "+it.nm, ab:"?", mech:"a definir", h:"a orçar", v:it.sug||0, fromDvi:true}]); setAdded(p=>({...p,[it.nm]:true})); setTab("servicos"); ping(it.nm+" → orçamento"); };

  const totServ=serv.reduce((a,s)=>a+s.v,0);
  const totPecas=PECAS.reduce((a,p)=>a+p.v,0);
  const total=totServ+totPecas;
  const reprov=dvi.filter(x=>x.s==="r").length, aten=dvi.filter(x=>x.s==="y").length;

  return (
    <div className="cli-screen" data-mod="oficina" data-theme={theme} data-density={density}>
      <aside className="cn-side">
        <div className="cn-brand"><div className="cn-logo">Oi</div><div><b>Oimpresso</b><small>ROTA LIVRE</small></div></div>
        <div className="cn-navsec">Operação</div>
        {NAV.map((it,i)=>(<button key={i} className={"cn-nav"+(it.on?" on":"")} style={{"--c":it.c}}><span className="dot"/><span>{it.label}</span><span className="n">{it.n}</span></button>))}
        <div className="cn-side-foot"><div className="av">JL</div><div><b>João Lima</b><small>Mecânico · Box 2</small></div></div>
      </aside>

      <main className="cn-main">
        <header className="cn-head">
          <div className="cn-head-icon"><I.wrench s={19}/></div>
          <div><h1>Ordem de Serviço</h1><p><span className="ofx-osno">OS #4821</span> · aberta hoje 09:14</p></div>
          <div className="cn-head-actions">
            <div className="cn-seg" role="group" aria-label="Tema">
              <button className={theme==="light"?"on":""} onClick={()=>setT("light")}><I.sun s={13}/> Claro</button>
              <button className={theme==="dark"?"on":""} onClick={()=>setT("dark")}><I.moon s={13}/> Escuro</button>
            </div>
            <div className="cn-seg" role="group" aria-label="Densidade">
              <button className={density==="compact"?"on":""} onClick={()=>setD("compact")}><I.rows s={13}/> Denso</button>
              <button className={density==="cozy"?"on":""} onClick={()=>setD("cozy")}>Confortável</button>
            </div>
          </div>
        </header>

        <div style={{flex:"0 0 auto",padding:"13px 24px",borderBottom:"1px solid var(--border-2)",display:"flex",justifyContent:"center"}}>
          <Stepper/>
        </div>

        <div className="cn-body">
          {/* ── COSTURA: a OS no fluxo (a alma Norte) ── */}
          <div className="cn-sec" style={{marginBottom:16}}>
            <div className="cn-sec-t" style={{textTransform:"uppercase"}}>Costura · onde esta OS vive no fluxo</div>
            <div className="cn-flow">
              <div className="cn-flow-cap">A OS é o coração do fluxo: vem do <b>Cliente</b>, e quando fica pronta vira <b>Venda</b> sozinha — que emite as <b>Notas</b> e cai no <b>Financeiro</b>. O gate de aprovação é o que destrava a execução.</div>
              <div className="cn-spine">
                {FLOW.map((m,i)=>(
                  <div key={i} className={"cn-spine-node"+(m.on?" on":"")+(m.here?" here":"")} style={{"--c":m.c}}>
                    <span className="cn-spine-line"/><span className="cn-spine-dot"/>
                    <span className="cn-spine-lbl">{m.label}</span><span className="cn-spine-v">{m.v}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="ofx-grid">
            <div className="ofx-col">
              {/* hero veículo */}
              <div className="ofx-card"><div className="ofx-card-b">
                <div className="ofx-hero">
                  <div className="ofx-plate"><div className="br">BRASIL</div><div className="num">RBA-2H78</div></div>
                  <div className="ofx-veh">
                    <h2>Honda Civic EXL <span className="yr">2019</span></h2>
                    <div className="sub">Prata · 84.220 km · chassi 9BWZZZ377VT004251</div>
                    <div className="ofx-specs">
                      <div className="ofx-spec"><div className="k">Última revisão</div><div className="v">72.000 km</div></div>
                      <div className="ofx-spec"><div className="k">Mecânico</div><div className="v">João Lima</div></div>
                      <div className="ofx-spec"><div className="k">Combustível</div><div className="v">35% · ~1/3</div></div>
                    </div>
                  </div>
                  <div className="ofx-cust">
                    <span className="nm">Marcos Aleixo</span>
                    <span className="row"><I.phone s={12}/>(11) 9 8821-4490</span>
                    <span className="row"><I.clock s={12}/>Cliente desde 2021 · 6 OS</span>
                    <span className="crmlink"><span className="d"/>vem do CRM · ficha completa</span>
                  </div>
                </div>
              </div></div>

              {/* check-in */}
              <div className="ofx-card">
                <div className="ofx-card-h"><span className="ic"><I.info s={16}/></span><div><h3>Check-in do veículo</h3><div className="desc">Estado de entrada — protege a oficina e o cliente</div></div></div>
                <div className="ofx-card-b">
                  <div className="ofx-relato">Cliente relata barulho metálico na frente ao frear e pedal de freio baixo. Pediu também revisão de óleo.</div>
                  <div className="ofx-dmg-row">
                    <button className="ofx-dmg on"><span className="d"/>Risco porta dir.</button>
                    <button className="ofx-dmg on"><span className="d"/>Amassado para-choque</button>
                    <button className="ofx-dmg"><span className="d"/>Para-brisa</button>
                    <button className="ofx-dmg"><I.cam s={12}/>2 fotos de entrada</button>
                  </div>
                </div>
              </div>

              {/* inspeção / DVI */}
              <div className="ofx-card">
                <div className="ofx-card-h"><span className="ic"><I.search s={16}/></span><div><h3>Inspeção · DVI</h3><div className="desc">Reprovado vira orçamento — já separado peça/serviço</div></div><span className="count">{reprov} reprov · {aten} atenção</span></div>
                <div className="ofx-card-b">
                  <div className="ofx-dvi">
                    {dvi.map((it,i)=>(
                      <div key={i} className={"ofx-dvi-item"+(it.s==="r"?" is-r":"")}>
                        <div className="ofx-traffic">
                          {["g","y","r"].map(c=><button key={c} className={"ofx-tl "+c+(it.s===c?" on":"")} onClick={()=>setS(i,c)} aria-label={c}/>)}
                        </div>
                        <div className="nm">{it.nm}<small>{it.sub}</small></div>
                        <span className={"ofx-photo"+(it.photo?" has":"")}><I.cam s={12}/>{it.photo?"2 fotos":"foto"}</span>
                        {it.s==="r"
                          ? (added[it.nm]?<span className="ofx-added"><I.check s={12}/>no orçamento</span>:<button className="ofx-addbtn" onClick={()=>addReprov(it)}>+ orçamento</button>)
                          : <span/>}
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {/* itens */}
              <div className="ofx-card">
                <div className="ofx-card-h"><span className="ic"><I.cart s={16}/></span><div><h3>Itens da OS</h3><div className="desc">Serviço (NFS-e) e peças (NF-e) — naturezas fiscais distintas</div></div></div>
                <div className="ofx-card-b">
                  <div className="ofx-tabs">
                    <button className={"ofx-tab"+(tab==="servicos"?" on":"")} onClick={()=>setTab("servicos")}>Serviços · {serv.length}</button>
                    <button className={"ofx-tab"+(tab==="pecas"?" on":"")} onClick={()=>setTab("pecas")}>Peças · {PECAS.length}</button>
                  </div>
                  {tab==="servicos" ? serv.map((s,i)=>(
                    <div key={i} className="ofx-line">
                      <div className="nm">{s.nm}<span className="ofx-mech"><span className="ofx-av">{s.ab}</span>{s.mech}{s.fromDvi&&<span className="ofx-frominsp">da inspeção</span>}</span></div>
                      <span className="ofx-qty">{s.h}</span><span/>
                      <span className="tot">{s.v?BRL(s.v):"a orçar"}</span>
                    </div>
                  )) : PECAS.map((p,i)=>(
                    <div key={i} className="ofx-line">
                      <div className="nm">{p.nm}<small>{p.sku}</small></div>
                      <span className="ofx-qty">{p.qty}</span>
                      <span className={"ofx-stock "+p.stock}>{p.stock==="ok"?"em estoque":"reservar"}</span>
                      <span className="tot">{BRL(p.v)}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* rail */}
            <div className="ofx-col">
              <div className="ofx-card"><div className="ofx-card-b ofx-sum">
                <div className="row"><span>Mão de obra</span><span className="v">{BRL(totServ)}</span></div>
                <div className="row"><span>Peças</span><span className="v">{BRL(totPecas)}</span></div>
                <div className="total"><span className="k">Total da OS</span><span className="v">{BRL(total)}</span></div>
              </div></div>

              <div className="ofx-card ofx-gate">
                <div className="ofx-card-h"><span className="ic">{appr==="aprovado"?<I.check s={16}/>:<I.clock s={16}/>}</span><div><h3>Aprovação do cliente</h3><div className="desc">A costura crítica · OS ↔ cliente</div></div></div>
                <div className="ofx-card-b">
                  {appr==="aguardando" && <>
                    <div className="ofx-gate-status"><span className="pulse"/>Aguardando aprovação</div>
                    <p>A execução não inicia sem o cliente autorizar. Sai pelo mesmo Inbox do WhatsApp, com as fotos da inspeção.</p>
                    <button className="ofx-wpp" onClick={()=>{setAppr("aprovado");ping("Cliente aprovou — execução liberada");}}><I.send s={15}/>Enviar orçamento por WhatsApp</button>
                  </>}
                  {appr==="aprovado" && <>
                    <div className="ofx-gate-status aprovado"><I.check s={13}/>Aprovado pelo cliente</div>
                    <p>OS destravada. O mecânico já pode iniciar — e quando ficar pronta, a venda nasce sozinha.</p>
                    <button className="ofx-wpp done"><I.check s={15}/>Gate liberado</button>
                  </>}
                </div>
              </div>

              <div className="ofx-card">
                <div className="ofx-card-h"><span className="ic"><I.receipt s={16}/></span><div><h3>Fiscal</h3><div className="desc">Separado desde o DVI</div></div></div>
                <div className="ofx-card-b">
                  <div className="ofx-doc"><span className="badge nfe">NF-e 55</span><span className="lbl">Peças<small>mercadoria</small></span><span className="amt">{BRL(totPecas)}</span></div>
                  <div className="ofx-doc"><span className="badge nfse">NFS-e</span><span className="lbl">Mão de obra<small>serviço</small></span><span className="amt">{BRL(totServ)}</span></div>
                  <div className="ofx-warranty"><I.shield s={14}/>Garantia de 90 dias em serviço e peça</div>
                </div>
              </div>

              <div className="ofx-card ofx-next">
                <div className="ofx-card-h"><span className="ic"><I.arrow s={16}/></span><div><h3>Próxima costura → Venda</h3></div></div>
                <div className="ofx-card-b"><p>Quando esta OS chegar em <b>Pronto</b>, uma <b>Venda</b> é criada automaticamente com tudo daqui — peças, mão de obra, cliente e a separação fiscal. <b>Ninguém digita a venda de novo.</b></p></div>
              </div>
            </div>
          </div>
        </div>

        <div className="ofx-foot">
          <div className="ofx-recap">
            <div><div className="k">Peças</div><div className="v">{BRL(totPecas)}</div></div>
            <div><div className="k">Mão de obra</div><div className="v">{BRL(totServ)}</div></div>
            <div><div className="k">Total</div><div className="v big">{BRL(total)}</div></div>
          </div>
          <div className="sp"/>
          <button className="btn ghost">Salvar rascunho</button>
          {appr==="aprovado"
            ? <button className="btn primary"><I.check s={14}/> Iniciar execução</button>
            : <button className="btn" disabled style={{opacity:.5,cursor:"not-allowed"}}><I.clock s={14}/> Requer aprovação</button>}
        </div>
      </main>

      {toast && <div className="ofx-toast"><I.check s={14}/>{toast}</div>}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<App/>);
})();
