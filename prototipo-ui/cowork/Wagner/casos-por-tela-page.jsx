// casos-por-tela-page.jsx — Documentação › Casos de uso por tela (rota `documentacao-casos`).
// Tela do sistema: resources/js/Pages/Documentacao/CasosPorTela.tsx (rota /documentacao/casos-por-tela).
// Layout aprovado: scripts/casos-backlog/template.html (Artefato "Casos de uso por tela").
//
// DERIVADO, não escrito: nenhum pedido, número, cliente ou caso de uso mora neste arquivo. Os dados
// chegam em window.__CASOS_POR_TELA__, gravado por
//   node scripts/casos-backlog/dados-prototipo.mjs --out <dir>/casos-por-tela-dados.js
// (fora do git: vai junto do Artefato, ao lado do index.html). Sem o arquivo, a tela diz como gerar.
// Conferir e responder decisão aqui ficam só nesta aba; no sistema gravam em doc_casos_marcas.
(() => {
const { useState, useEffect, useMemo } = React;

const VISOES = [["casos","Casos"],["contratos","O que as telas garantem"],["regras","Regras"],["decisoes","Decisões para o Wagner"],["clientes","Clientes"]];
const TOM = { ok: "ok", part: "warn", miss: "danger" };
const VEREDITO = { pass: ["ok","teste verde"], fail: ["danger","teste vermelho"], "sem-prova": ["neutral","sem prova"] };
const STATUS_REGRA = { producao: ["ok","Em produção"], parcial: ["warn","Em parte"], planejado: ["danger","Planejado"] };
const FILTROS = [
 ["valor","Mexe em valor ou estoque",c=>c.tags.includes("valor")],
 ["cv","Comunicação visual",c=>c.tags.includes("cv")],
 ["part","Existe em parte",c=>c.hoje.tipo==="part"],
 ["miss","Não existe",c=>c.hoje.tipo==="miss"],
 ["decisao","Depende do Wagner",c=>c.tags.includes("decisao")],
 ["semregra","Sem regra citada",c=>!c.regras.length],
 ["naoconf","Não conferido",(c,conf)=>!conf[c.id]],
 ["uc","Já viraram caso de uso",c=>c.estagio!=="pedido"],
];
const ARQ_DADOS = "casos-por-tela-dados.js";

function Rico({ texto }){
 return <>{String(texto||"").split("`").map((t,i)=>i%2?<code key={i}>{t}</code>:<span key={i}>{t}</span>)}</>;
}
function Selo({ tom="neutral", children, title, mono }){
 return <span className={"cpt-selo "+tom+(mono?" mono":"")} title={title}>{children}</span>;
}
function Pilula({ ativo, onClick, children }){
 return <button type="button" className={"cpt-pill"+(ativo?" on":"")} aria-pressed={ativo} onClick={onClick}>{children}</button>;
}
function LinkCaso({ n, titulo, onClick }){
 return <button type="button" className="cpt-link-caso" title={titulo} onClick={onClick}>#{n}</button>;
}

function useDados(){
 const [d,setD]=useState(()=>window.__CASOS_POR_TELA__||null);
 const [falhou,setFalhou]=useState(false);
 useEffect(()=>{
  if(d)return;
  const s=document.createElement("script");s.src=ARQ_DADOS;
  s.onload=()=>window.__CASOS_POR_TELA__?setD(window.__CASOS_POR_TELA__):setFalhou(true);
  s.onerror=()=>setFalhou(true);document.body.appendChild(s);
 },[]);
 return [d,falhou];
}

function CasosPorTelaPage(){
 const [dados,falhou]=useDados();
 return (
 <div className="os-page cpt-page" data-screen-label="Casos de uso por tela">
  <header className="os-page-h">
   <div className="os-page-h-l">
    <div className="cpt-crumb"><button type="button" onClick={()=>window.__go&&window.__go("documentacao")}>Documentação</button><span>›</span><b>Casos de uso por tela</b></div>
    <h1>Casos de uso por tela</h1>
    <p>Os pedidos dos clientes e o que cada tela já garante, lidos dos casos.md a cada acesso.</p>
   </div>
   <div className="os-page-h-r">
    <button className="os-btn ghost" onClick={()=>window.__go&&window.__go("documentacao")}>Documentação</button>
   </div>
  </header>
  <div className="cpt-body">
   {dados ? <Pagina dados={dados} />
    : falhou ? <div className="cpt-vazio" role="status">Os dados não foram gerados. Rode <code>node scripts/casos-backlog/dados-prototipo.mjs --out {ARQ_DADOS}</code> ao lado do <code>oimpresso.com.html</code>.</div>
    : <div className="cpt-carregando" aria-hidden="true"><i/><i/><i/><i/></div>}
  </div>
 </div>);
}

function Pagina({ dados }){
 const [visao,setVisao]=useState(()=>{const h=(location.hash||"").slice(1);return (VISOES.find(([v])=>v===h)||["casos"])[0]});
 const [abrirCaso,setAbrirCaso]=useState(null);
 const [abrirTela,setAbrirTela]=useState(null);
 const [cliente,setCliente]=useState("");
 const [recorte,setRecorte]=useState("tudo");
 const [conf,setConf]=useState({});
 const [resp,setResp]=useState({});
 const C=dados.casos;
 const clientes=useMemo(()=>[...new Set(C.flatMap(c=>c.clientes))].sort((a,b)=>a.localeCompare(b,"pt-BR")),[C]);
 const irParaCaso=n=>{const c=C.find(x=>x.n===n);if(!c)return;setRecorte("tudo");setCliente("");setAbrirCaso(c.id);setVisao("casos")};
 const irParaContrato=t=>{setAbrirTela(t);setVisao("contratos")};
 const irParaTela=t=>{setRecorte(t);setAbrirCaso(null);setVisao("casos")};
 const irParaCliente=n=>{setCliente(n);setRecorte("tudo");setAbrirCaso(null);setVisao("casos")};
 const nConf=C.filter(c=>conf[c.id]).length;
 const etapas=[["Pedido",C.length,"ligados a este documento"],["Conferido",nConf,"contra as regras"],
  ["Virou caso de uso",C.filter(c=>c.estagio!=="pedido").length,"tem id UC no casos.md"],["Testado",C.filter(c=>c.estagio==="testado").length,"com o teste verde"]];
 const totalUcs=dados.contratos.reduce((s,t)=>s+t.ucs.length,0);
 const contagem={casos:C.length,contratos:totalUcs,regras:dados.regras.length,decisoes:dados.decisoes.filter(d=>!resp[d.id]).length,clientes:clientes.length};
 const nums=[[C.length,`casos em ${dados.telas.length} telas`],[clientes.length,"clientes ligados"],
  [C.filter(c=>c.tags.includes("valor")).length,"mexem em valor ou estoque"],[C.filter(c=>c.tags.includes("decisao")).length,"dependem do Wagner",true],
  [C.filter(c=>!c.regras.length).length,"não citam regra de Fluxos"]];
 return (<>
  <p className="cpt-intro"><Rico texto="Cada caso é um item do casos.md da sua tela, ligado a este documento pelo subitem `Pedido: P-NNN`; as regras vêm do catálogo de Fluxos (`UC-FLX`). Para mudar um caso, edite o casos.md da tela: a página muda no próximo acesso." /></p>
  <div className="cpt-nums">{nums.map(([n,r,al])=><div key={r} className={"cpt-num"+(al?" alerta":"")}><b>{n}</b><span>{r}</span></div>)}</div>
  <div className="cpt-etapas" aria-label="Ciclo de vida dos casos">{etapas.map(([nome,n,sub])=>
   <div key={nome}><span>{nome}</span><b>{n}</b><small>{sub}</small><div className="cpt-barra"><i style={{width:(C.length?Math.round(100*n/C.length):0)+"%"}}/></div></div>)}</div>
  {dados.geradoEm&&<p className="cpt-gerado">Dados gerados em {dados.geradoEm.split("-").reverse().join("/")} a partir do git. No protótipo, conferir e responder ficam só nesta aba.</p>}
  {dados.problemas&&dados.problemas.length>0&&<div className="cpt-aviso" role="status"><b>Os casos.md têm {dados.problemas.length} problema(s) de ligação:</b><ul>{dados.problemas.map(p=><li key={p}>{p}</li>)}</ul></div>}
  <nav className="cpt-tabs" role="tablist" aria-label="Visões">{VISOES.map(([v,r])=>
   <button key={v} type="button" role="tab" aria-selected={visao===v} className={visao===v?"on":""} onClick={()=>setVisao(v)}>{r}<i>{contagem[v]}</i></button>)}</nav>
  <div role="tabpanel">
   {visao==="casos"?<VisaoCasos dados={dados} conf={conf} setConf={setConf} clientes={clientes} recorte={recorte} setRecorte={setRecorte} cliente={cliente} setCliente={setCliente} aberto={abrirCaso} setAberto={setAbrirCaso} irParaContrato={irParaContrato}/>
   :visao==="contratos"?<VisaoContratos contratos={dados.contratos} aberta={abrirTela} setAberta={setAbrirTela} irParaTela={irParaTela}/>
   :visao==="regras"?<VisaoRegras dados={dados} irParaCaso={irParaCaso}/>
   :visao==="decisoes"?<VisaoDecisoes dados={dados} resp={resp} setResp={setResp} irParaCaso={irParaCaso}/>
   :<VisaoClientes dados={dados} clientes={clientes} irParaCaso={irParaCaso} irParaCliente={irParaCliente}/>}
  </div>
 </>);
}

function VisaoCasos(p){
 const { dados, conf } = p; const C=dados.casos;
 const [busca,setBusca]=useState(""); const [filtros,setFiltros]=useState(new Set());
 const areaDaTela=useMemo(()=>Object.fromEntries(dados.areas.flatMap(a=>a.telas.map(t=>[t,a.id]))),[dados]);
 const telaDe=useMemo(()=>Object.fromEntries(dados.telas.map(t=>[t.id,t])),[dados]);
 const contratoDe=useMemo(()=>Object.fromEntries(dados.contratos.map(t=>[t.id,t])),[dados]);
 const regraPorId=useMemo(()=>Object.fromEntries(dados.regras.map(r=>[r.id,r])),[dados]);
 const naBusca=c=>{const q=busca.trim().toLowerCase();if(!q)return true;const m=q.match(/^#?([0-9]+)$/);if(m)return c.n===Number(m[1]);
  return [c.titulo,c.aceite,c.texto,c.ancora,c.fazer,c.uc,c.hoje.nota,c.clientes.join(" ")].join(" ").toLowerCase().includes(q)};
 const base=(c,ign)=>naBusca(c)&&(!p.cliente||c.clientes.includes(p.cliente))&&FILTROS.every(([id,,f])=>id===ign||!filtros.has(id)||f(c,conf));
 const noRecorte=c=>p.recorte==="tudo"||c.tela===p.recorte||areaDaTela[c.tela]===p.recorte;
 const vis=C.filter(c=>noRecorte(c)&&base(c));
 const conta=pred=>C.filter(c=>pred(c)&&base(c)).length;
 const alternar=id=>setFiltros(s=>{const n=new Set(s);n.has(id)?n.delete(id):n.add(id);return n});
 const sujo=filtros.size>0||busca!==""||p.cliente!==""||p.recorte!=="tudo";
 const limpar=()=>{setFiltros(new Set());setBusca("");p.setCliente("");p.setRecorte("tudo")};
 const Rec=({id,rotulo,n,forte})=><button type="button" aria-pressed={p.recorte===id} className={"cpt-rec"+(p.recorte===id?" on":"")+(forte?" forte":"")} onClick={()=>p.setRecorte(id)}><span>{rotulo}</span><i>{n}</i></button>;
 return (
 <div className="cpt-casos">
  <aside className="cpt-rail" aria-label="Áreas e telas">
   <Rec id="tudo" rotulo="Todas as telas" n={conta(()=>true)}/>
   {dados.areas.map(a=><div key={a.id}><Rec id={a.id} rotulo={a.nome} n={conta(c=>areaDaTela[c.tela]===a.id)} forte/>
    <ul>{a.telas.map(t=><li key={t}><Rec id={t} rotulo={(telaDe[t]||{}).nome||t} n={conta(c=>c.tela===t)}/></li>)}</ul></div>)}
  </aside>
  <div className="cpt-col">
   <div className="cpt-linha">
    <input type="search" className="cpt-input" value={busca} onChange={e=>{setBusca(e.target.value);p.setAberto(null)}} placeholder="Buscar no título, aceite, âncora ou número (#45)" aria-label="Buscar casos"/>
    <select className="cpt-input cpt-sel" aria-label="Cliente" value={p.cliente} onChange={e=>p.setCliente(e.target.value)}>
     <option value="">Todos os clientes</option>{p.clientes.map(c=><option key={c} value={c}>{c}</option>)}</select>
   </div>
   <div className="cpt-linha" role="group" aria-label="Filtros (somam entre si)">{FILTROS.map(([id,rot,f])=>
    <Pilula key={id} ativo={filtros.has(id)} onClick={()=>alternar(id)}>{rot}<i>{C.filter(c=>noRecorte(c)&&base(c,id)&&f(c,conf)).length}</i></Pilula>)}</div>
   <div className="cpt-res">{vis.length===C.length?`${C.length} casos`:`${vis.length} de ${C.length} casos`} {sujo&&<button type="button" onClick={limpar}>Limpar filtros</button>}</div>
   <div className="cpt-lista">
    {!C.length?<div className="cpt-vazio">Nenhum caso ligado ainda. Um caso entra aqui quando o item dele no casos.md da tela ganha o subitem "Pedido: P-NNN".</div>
    :!vis.length?<div className="cpt-vazio">Nenhum caso com esses filtros. Tire um filtro ou limpe a busca.</div>
    :vis.map(c=><CasoItem key={c.id} c={c} aberto={p.aberto===c.id} alternar={()=>p.setAberto(p.aberto===c.id?null:c.id)}
      marca={conf[c.id]} setMarca={v=>p.setConf(s=>{const n={...s};v?n[c.id]={por:"você",em:new Date().toISOString()}:delete n[c.id];return n})}
      tela={telaDe[c.tela]} contrato={contratoDe[c.tela]} regraTitulo={r=>(regraPorId[r]||{}).titulo||""} irParaContrato={p.irParaContrato}/>)}
   </div>
   <details className="cpt-card cpt-como"><summary>Como entra um pedido novo</summary>
    <p><Rico texto="Um pedido novo não se escreve aqui: ele entra no `casos.md` da tela, como um item de backlog com o subitem `Pedido`. No próximo acesso ele aparece nesta lista."/></p>
    <pre>{["- **[BACKLOG] Título curto do pedido** `[V0]`",`  - Pedido: ${dados.proximoPedido}`,"  - Aceite: Dado … · Quando … · Então …","  - Âncora: UC-FLX-NN · US-XXX-NNN","  - Fazer: o que precisa ser construído","  - Hoje: miss | Não tem | o que existe hoje","  - Comunicação visual: ok | Sim | por quê","  - Clientes: Cliente A, Cliente B","  - Etiquetas: valor, miss, cv"].join("\n")}</pre>
   </details>
  </div>
 </div>);
}

function CasoItem({ c, aberto, alternar, marca, setMarca, tela, contrato, regraTitulo, irParaContrato }){
 const sel=s=>s.texto?<span><Selo tom={TOM[s.tipo]||"neutral"}>{s.texto}</Selo> <Rico texto={s.nota}/></span>:null;
 const L=[];
 if(c.aceite)L.push(["Aceite",<Rico texto={c.aceite}/>]);else if(c.texto)L.push(["Descrição",<Rico texto={c.texto}/>]);
 if(c.fazer)L.push(["O que fazer",<Rico texto={c.fazer}/>]);
 if(c.hoje.texto)L.push(["Hoje no sistema",sel(c.hoje)]);
 if(c.cv.texto)L.push(["Comunicação visual",sel(c.cv)]);
 if(c.ancora)L.push(["Regra e âncora",<Rico texto={c.ancora}/>]);
 L.push(["Clientes",c.clientes.length?c.clientes.join(", "):<span className="cpt-mute">nenhum ligado</span>]);
 L.push(["Onde está",<span>{(tela||{}).nome||c.tela} · <a href={`${(tela||{}).url||""}?plain=1#L${c.linha}`} target="_blank" rel="noopener"><code>{c.arquivo}:{c.linha}</code></a></span>]);
 if(contrato)L.push(["A tela já garante",<button type="button" className="cpt-txtlink" onClick={()=>irParaContrato(c.tela)}>{contrato.ucs.length} casos de uso</button>]);
 return (
 <article className={"cpt-card cpt-caso"+(marca?" conf":"")} data-caso={c.id}>
  <button type="button" className="cpt-caso-h" aria-expanded={aberto} onClick={alternar}>
   <span className="cpt-n">#{c.n}</span>
   <span className="cpt-t"><span>{c.titulo}</span><span className="cpt-selos">
    {c.uc&&<Selo tom="ok" title={c.estagio==="testado"?"Virou caso de uso, com o teste verde":"Virou caso de uso"}>{c.uc}</Selo>}
    {c.marcas.map(m=><Selo key={m} tom="warn" title={m==="V0"?"Mexe em valor":m==="E0"?"Mexe em estoque":"Mexe em tributo"}>{m}</Selo>)}
    {c.hoje.texto&&<Selo tom={TOM[c.hoje.tipo]||"neutral"}>{c.hoje.texto}</Selo>}
    {c.tags.includes("decisao")&&<Selo tom="info">Depende do Wagner</Selo>}
    {c.regras.length?c.regras.map(r=><Selo key={r} title={regraTitulo(r)} mono>{r}</Selo>):<Selo tom="outline">sem regra citada</Selo>}
    {marca&&<Selo tom="ok">Conferido</Selo>}
   </span></span>
   <span className={"cpt-seta"+(aberto?" aberta":"")} aria-hidden="true">›</span>
  </button>
  {aberto&&<div className="cpt-caso-b">
   <dl>{L.map(([k,v])=>[<dt key={k+"t"}>{k}</dt>,<dd key={k+"d"}>{v}</dd>])}</dl>
   <div className="cpt-caso-p">
    <label><input type="checkbox" checked={!!marca} onChange={e=>setMarca(e.target.checked)} aria-label={`Conferido contra as regras: caso ${c.n}`}/> Conferido contra as regras</label>
    <span className="cpt-mute">{marca?`${marca.por} · agora`:""}</span>
   </div>
  </div>}
 </article>);
}

function VisaoContratos({ contratos, aberta, setAberta, irParaTela }){
 const [busca,setBusca]=useState(""); const [modulo,setModulo]=useState(""); const [semProva,setSemProva]=useState(false);
 const modulos=useMemo(()=>[...new Set(contratos.map(t=>t.modulo))].sort((a,b)=>a.localeCompare(b,"pt-BR")),[contratos]);
 const total=contratos.reduce((s,t)=>s+t.ucs.length,0);
 const verdes=contratos.reduce((s,t)=>s+t.ucs.filter(u=>u.veredito==="pass").length,0);
 const q=busca.trim().toLowerCase();
 const vis=contratos.filter(t=>!modulo||t.modulo===modulo).map(t=>{const casa=!q||[t.nome,t.id,t.modulo].join(" ").toLowerCase().includes(q);
  return {t,ucs:t.ucs.filter(u=>(!semProva||u.veredito!=="pass")&&(casa||[u.id,u.titulo].join(" ").toLowerCase().includes(q)))}}).filter(x=>x.ucs.length);
 const nUcs=vis.reduce((s,x)=>s+x.ucs.length,0);
 return (
 <div className="cpt-col">
  <div><h2>O que as telas já garantem</h2><p className="cpt-lead">{total} casos de uso em {contratos.length} telas, {verdes} com o teste verde. É o contrato do que o sistema já faz: cada caso tem id e um teste que o prova. O veredito vem do resultado dos testes, não do que está escrito no arquivo.</p></div>
  <div className="cpt-linha">
   <input type="search" className="cpt-input" value={busca} onChange={e=>{setBusca(e.target.value);setAberta(null)}} placeholder="Buscar tela, módulo, id ou título do caso de uso" aria-label="Buscar casos de uso"/>
   <select className="cpt-input cpt-sel" aria-label="Módulo" value={modulo} onChange={e=>{setModulo(e.target.value);setAberta(null)}}><option value="">Todos os módulos</option>{modulos.map(m=><option key={m} value={m}>{m}</option>)}</select>
   <Pilula ativo={semProva} onClick={()=>setSemProva(!semProva)}>Só os casos sem teste verde</Pilula>
  </div>
  <div className="cpt-res">{vis.length} {vis.length===1?"tela":"telas"} · {nUcs} {nUcs===1?"caso de uso":"casos de uso"}</div>
  <div className="cpt-lista">
   {!vis.length?<div className="cpt-vazio">Nenhum caso de uso com essa busca.</div>:vis.slice(0,aberta?vis.length:120).map(({t,ucs})=>{
    const ok=t.ucs.filter(u=>u.veredito==="pass").length; const ab=aberta===t.id;
    return (<article key={t.id} className="cpt-card" data-tela={t.id}>
     <button type="button" className="cpt-caso-h cpt-tela-h" aria-expanded={ab} onClick={()=>setAberta(ab?null:t.id)}>
      <span className="cpt-mod">{t.modulo}</span>
      <span className="cpt-t"><span>{t.nome}</span><span className="cpt-selos"><Selo tom={ok===t.ucs.length?"ok":"warn"}>{ok} de {t.ucs.length} com teste verde</Selo>{t.pedidos>0&&<Selo tom="info">{t.pedidos} pedido{t.pedidos>1?"s":""} em aberto</Selo>}</span></span>
      <span className={"cpt-seta"+(ab?" aberta":"")} aria-hidden="true">›</span>
     </button>
     {ab&&<div className="cpt-caso-b"><div className="cpt-tab-w"><table className="cpt-tab"><thead><tr><th>Caso de uso</th><th>O que garante</th><th>Teste</th></tr></thead><tbody>
      {ucs.map(u=>{const [tom,rot]=VEREDITO[u.veredito]||["neutral",u.veredito];return <tr key={u.id+u.linha}><td><a href={`${t.url}?plain=1#L${u.linha}`} target="_blank" rel="noopener"><code>{u.id}</code></a></td><td>{u.titulo}</td><td><Selo tom={tom}>{rot}</Selo></td></tr>})}
     </tbody></table></div>{t.pedidos>0&&<button type="button" className="cpt-txtlink" onClick={()=>irParaTela(t.id)}>Ver os {t.pedidos} pedidos desta tela</button>}</div>}
    </article>)})}
   {!aberta&&vis.length>120&&<div className="cpt-res">Mostrando 120 de {vis.length} telas. Busque ou filtre por módulo para ver as outras.</div>}
  </div>
 </div>);
}

function VisaoRegras({ dados, irParaCaso }){
 const [so,setSo]=useState(false);
 const casosDa=id=>dados.casos.filter(c=>c.regras.includes(id));
 const citadas=dados.regras.filter(r=>casosDa(r.id).length).length;
 return (
 <div className="cpt-col">
  <div className="cpt-linha cpt-entre"><div><h2>Regras do catálogo de Fluxos</h2><p className="cpt-lead">{citadas} das {dados.regras.length} regras são citadas por algum caso. As outras {dados.regras.length-citadas} não aparecem em nenhum: ou não se aplicam a estes pedidos, ou ninguém conferiu. {dados.casos.filter(c=>!c.regras.length).length} casos não citam regra nenhuma.</p></div>
   <Pilula ativo={so} onClick={()=>setSo(!so)}>Só as regras sem caso</Pilula></div>
  <div className="cpt-tab-w cpt-card"><table className="cpt-tab"><thead><tr><th>Regra</th><th>O que diz</th><th>Etapa</th><th>No sistema</th><th>Casos que dependem dela</th></tr></thead><tbody>
   {dados.regras.filter(r=>!so||!casosDa(r.id).length).map(r=>{const cs=casosDa(r.id);const [tom,rot]=STATUS_REGRA[r.status]||["neutral",r.status];
    return <tr key={r.id} className={cs.length?"":"sem"}><td><code>{r.id}</code></td><td><b>{r.titulo}</b><div className="cpt-mute">{r.efeito}</div></td><td>{r.etapa}</td><td><Selo tom={tom}>{rot}</Selo></td>
     <td>{cs.length?<span className="cpt-links">{cs.map(c=><LinkCaso key={c.id} n={c.n} titulo={c.titulo} onClick={()=>irParaCaso(c.n)}/>)}</span>:<span className="cpt-mute">nenhum</span>}</td></tr>})}
  </tbody></table></div>
 </div>);
}

function VisaoDecisoes({ dados, resp, setResp, irParaCaso }){
 const dep=dados.casos.filter(c=>c.tags.includes("decisao")).map(c=>c.n);
 const cob=new Set(dados.decisoes.flatMap(d=>d.casos)); const soltos=dep.filter(n=>!cob.has(n));
 const abertas=dados.decisoes.filter(d=>!resp[d.id]).length;
 return (
 <div className="cpt-col">
  <div><h2>Decisões para o Wagner</h2><p className="cpt-lead">{abertas} de {dados.decisoes.length} perguntas em aberto. {dep.length} casos dependem de você; {dep.length-soltos.length} deles estão ligados a uma pergunta abaixo. {soltos.length>0&&<span>Os outros {soltos.length} ainda não têm pergunta escrita: <span className="cpt-links">{soltos.map(n=><LinkCaso key={n} n={n} onClick={()=>irParaCaso(n)}/>)}</span></span>}</p></div>
  {!dados.decisoes.length?<div className="cpt-vazio">Nenhuma decisão escrita nos casos.md ainda.</div>:dados.decisoes.map(d=>
   <DecisaoItem key={d.id} d={d} r={resp[d.id]} registrar={v=>setResp(s=>({...s,[d.id]:v}))} irParaCaso={irParaCaso}/>)}
 </div>);
}

function DecisaoItem({ d, r, registrar, irParaCaso }){
 const [opcao,setOpcao]=useState(r?r.opcao||"":""); const [nota,setNota]=useState(r?r.nota||"":""); const [erro,setErro]=useState("");
 const responder=()=>{if(!opcao&&!nota.trim()){setErro("Escolha uma opção ou escreva a resposta antes de registrar.");return}setErro("");registrar({opcao,nota,por:"você",em:new Date().toISOString()})};
 return (
 <article className={"cpt-card cpt-dec"+(r?" resp":"")} data-decisao={d.id}>
  <div className="cpt-dec-e">{r?"Respondida":d.estado}</div>
  <h3>{d.pergunta}</h3>
  {d.texto&&<p>{d.texto}</p>}
  {d.opcoes.length>0&&<div className="cpt-ops" role="radiogroup" aria-label={d.pergunta}>{d.opcoes.map(o=>
   <label key={o.nome} className={opcao===o.nome?"on":""}><b><input type="radio" name={"op-"+d.id} value={o.nome} checked={opcao===o.nome} onChange={()=>setOpcao(o.nome)}/> {o.nome}</b><span>{o.texto}</span></label>)}</div>}
  {d.casos.length>0&&<div className="cpt-links"><span className="cpt-mute">Destrava: </span>{d.casos.map(n=><LinkCaso key={n} n={n} onClick={()=>irParaCaso(n)}/>)}</div>}
  <div className="cpt-linha">
   <input className="cpt-input" value={nota} onChange={e=>setNota(e.target.value)} placeholder={d.opcoes.length?"Observação (opcional)":"Sua resposta"} aria-label={"Resposta da decisão "+d.id}/>
   <button type="button" className="os-btn" onClick={responder}>{r?"Atualizar resposta":"Registrar resposta"}</button>
  </div>
  {erro&&<p className="cpt-erro" role="alert">{erro}</p>}
  {r&&<div className="cpt-mute">Respondida por {r.por}{r.opcao?" · "+r.opcao:""} (só nesta aba)</div>}
 </article>);
}

function VisaoClientes({ dados, clientes, irParaCaso, irParaCliente }){
 const areaDaTela=Object.fromEntries(dados.areas.flatMap(a=>a.telas.map(t=>[t,a])));
 const lista=clientes.map(nome=>({nome,casos:dados.casos.filter(c=>c.clientes.includes(nome))})).sort((a,b)=>b.casos.length-a.casos.length||a.nome.localeCompare(b.nome,"pt-BR"));
 return (
 <div className="cpt-col">
  <div><h2>Clientes</h2><p className="cpt-lead">{lista.length} clientes ligados a algum caso, os que mais pediram primeiro. Clique num número para abrir o caso ou no nome para ver só os casos dele.</p></div>
  <div className="cpt-clientes">{lista.map(cl=>
   <article key={cl.nome} className="cpt-card cpt-cli">
    <div className="cpt-cli-h"><button type="button" className="cpt-txtlink" onClick={()=>irParaCliente(cl.nome)}>{cl.nome}</button><span>{cl.casos.length} {cl.casos.length===1?"caso":"casos"}</span></div>
    <div className="cpt-selos">{[...new Set(cl.casos.map(c=>(areaDaTela[c.tela]||{}).nome||"Outras telas"))].map(a=><Selo key={a}>{a}</Selo>)}</div>
    <div className="cpt-links">{cl.casos.map(c=><LinkCaso key={c.id} n={c.n} titulo={c.titulo} onClick={()=>irParaCaso(c.n)}/>)}</div>
   </article>)}</div>
 </div>);
}

window.CasosPorTelaPage=CasosPorTelaPage;
})();
