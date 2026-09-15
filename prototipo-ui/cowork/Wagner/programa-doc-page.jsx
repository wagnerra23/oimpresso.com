// programa-doc-page.jsx — Programa de documentação · Trilha D (rota `programa-doc`).
// Dono do texto: memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md § Trilha D.
// Esta tela RENDERIZA o plano; status de execução vive nas tasks MCP (parent_plan=programa-ondas).
(() => {
const { useState } = React;
const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const GIT = "memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md";
const BLOB = "https://github.com/wagnerra23/oimpresso.com/blob/main/" + GIT;

const STATIONS = [
 {n:"01",ph:"medir",t:"Descobrir",s:"máquina, hook, MCP, módulo ou fluxo",
  ent:"árvore do git · inventários derivados · achado de incidente",
  mq:"<code>system-map</code> · <code>PAINEL-SISTEMA.md</code> · <code>MAQUINAS-INVENTARIO.md</code>",
  reg:"Inventário é sempre derivado — descobrir não é escrever lista nova."},
 {n:"02",ph:"medir",t:"Medir o estado real",s:"inventário, código e probes",
  ent:"fonte e configuração reais, não a lembrança do doc",
  mq:"<code>documentation-loop --snapshot</code> · probe da máquina",
  reg:"Guardar o <b>ID estável</b> do achado — é ele que precisa sumir no recibo."},
 {n:"03",ph:"medir",t:"Classificar e localizar o dono",s:"camada e arquivo que já responde",
  ent:"camada (infra · plataforma · aplicação · fluxo · operação)",
  mq:"donos documentais no git · <code>GUIA-DO-SISTEMA.md</code> aponta",
  reg:"<b>Ponteiro &gt; cópia.</b> Nunca criar resumo paralelo ao dono."},
 {n:"04",ph:"medir",t:"Priorizar o gap",s:"criticidade e impacto",
  ent:"exposição da máquina × débito documental",
  mq:"tasks MCP <code>parent_plan=programa-ondas</code>",
  reg:"Achado adjacente vira task nova — não desvio desta unidade."},
 {n:"05",ph:"traduzir",t:"Documentar no dono existente",s:"traduzir para humano",
  ent:"o achado medido + o dono localizado",
  mq:"workflow <code>.claude/workflows/documentacao-tecnica.js</code>",
  reg:"Sem copiar tabela gerada; sem congelar contagem em prosa."},
 {n:"06",ph:"traduzir",t:"Validar tecnicamente",s:"fonte, links e arquitetura",
  ent:"diff do dono",
  mq:"checagem de link · diagrama · dependência · tenant · PII",
  reg:"Segredo só como <b>ponteiro pro Vaultwarden</b> — nunca valor."},
 {n:"07",ph:"traduzir",t:"Validar operacionalmente",s:"executar o runbook",
  ent:"ambiente correto (Hostinger · CT 100 · runner)",
  mq:"o próprio runbook, rodado de verdade",
  reg:"<code>last_validated</code> só muda quando o resultado real bateu."},
 {n:"08",ph:"publicar",t:"Publicar",s:"PR, merge e /documentacao",
  ent:"um PR de <b>uma intenção</b>",
  mq:"<code>documentation-loop</code> antes→depois · deploy ou <code>quick-sync</code>",
  reg:"[W] ratifica pelo merge. Alteração de data não fecha achado."},
 {n:"09",ph:"operar",t:"Operar e observar",s:"o doc em uso",
  ent:"operador usando o runbook · rota humana autenticada",
  mq:"<code>jana:health-check</code> · vital-signs · Langfuse/OTel",
  reg:"Doc que ninguém abre em incidente não está pronto — está escrito."},
 {n:"10",ph:"operar",t:"Incidente ou drift",s:"a realidade discorda do doc",
  ent:"PR que muda a máquina · incidente · plano velho",
  mq:"staleness documental · <code>plan-health</code> · <code>jana:plan-drift</code>",
  reg:"Batimento é <b>advisory</b>: detecta e oferece, não decide nem merga."},
 {n:"11",ph:"operar",t:"Aprender e corrigir",s:"runbook, lição ou decisão",
  ent:"o que a operação real desmentiu",
  mq:"<code>LICOES_CODE.md</code> · <code>proibicoes.md</code> · ADR quando vira lei",
  reg:"Fecha a task e <b>volta pra estação 02</b> — o ciclo não termina em publicar."}
];

const ONDAS = [
 ["D0","fundação","inventários, donos, criticidade, gaps e navegação","esta seção + navegação do Guia + tasks MCP","plano ligado ao MCP; inventários <code>--check</code>; baseline registrada","doing"],
 ["D1","infra crítica","Hostinger, CT 100, Proxmox e GitHub Actions","referências de infra + runbooks de acesso/deploy/rollback/saúde","operador identifica onde roda, valida saúde e recupera sem editar servidor","todo"],
 ["D2","plataforma","hooks, CI, skills, agents, scripts, baselines e observabilidade","índice derivado + explicação humana por família","cada família declara gatilho, bloqueio/advisory, risco, bite/release e diagnóstico","todo"],
 ["D3","MCP ponta a ponta","Git→sync→cache→servidor→tool→audit","arquitetura Jana/MCP + runbooks de acesso, deploy, drift e recovery","auth, <code>business_id</code>, 401/403/404, reindexação e auditoria reproduzíveis","todo"],
 ["D4","módulos críticos","Sells, Estoque, Financeiro, Fiscal, Repair e Jana","portas documentais aplicáveis por módulo","responsabilidade, requisito, arquitetura, superfície e operação alcançáveis","todo"],
 ["D5","verticais e integrações","Vestuario, ComunicacaoVisual, OficinaAuto, WhatsApp, NFe/NFSe, gateways","mesmos donos modulares","integração e recuperação documentadas sem misturar produto com sistema","todo"],
 ["D6","legado e rede local","Windows/Firebird, PBX, SVN, router e dispositivos","referências de infra + runbooks de legado","acesso, dependência e recuperação do legado explícitos","todo"],
 ["D7","fluxos transversais","venda, cancelamento, fiscal, WhatsApp, IA, migração e deploy","diagramas e ponteiros no Guia/donos","ator, máquina, módulo, dado, auth, tenant, retry, falha parcial e rollback explícitos","todo"],
 ["D8","continuidade","backup, restore, perda de máquina, segredos e disaster recovery","auditoria Ops/DR + runbooks","RPO/RTO medidos, drill seguro, responsável e evidência datada","todo"],
 ["D9","publicação e onboarding","<code>/documentacao</code> e trilha de entrada do time","Guia + donos corrigidos","navegação humana alcança infra, plataforma, módulos, fluxos e operação","todo"],
 ["D10","manutenção contínua","detectores, revalidação de runbooks e aprendizado","recibos do <code>documentation-loop</code> + lições","detectores reexecutados; runbook revalidado; incidente vira lição","todo"]
];

const PATHS = [
 {t:"Máquinas e ambientes",s:"Hostinger, Proxmox, CT 100, Actions, Windows/Firebird, router, Tailscale, PBX, SVN",
  flow:["inventário","arquitetura","acesso","operação","monitoramento","backup","restore","incidente"],
  items:["função e responsável","serviços e dados","dependências","configuração versionada","acesso, sem copiar segredo","probe de saúde","deploy, restart e rollback","backup, restore, RPO e RTO","falhas conhecidas","última validação com evidência"]},
 {t:"Hooks",s:"a documentação explica a família; o índice gerado continua sendo o inventário",
  flow:["arquivo do hook","índice gerado","família humana","cenário de bloqueio","troubleshooting"],
  items:["quando dispara","que risco protege","bloqueia ou alerta","que entrada examina","que mensagem produz","como provar que morde e solta","como diagnosticar falso positivo ou ausência de disparo"]},
 {t:"MCP",s:"do canon no git até a auditoria da ação",
  flow:["Git canon","sincronização","banco/cache","servidor CT 100","autenticação","tool","auditoria"],
  items:["arquitetura e fronteiras","catálogo derivado das tools","tokens, papéis e permissões","isolamento por business_id","sincronização e drift","deploy e reload","health check","401, 403, 404 e indisponibilidade","reindexação e recuperação","auditoria das ações","onboarding e offboarding"]},
 {t:"Módulos",s:"as portas documentais aplicáveis — sem lista manual concorrente",
  flow:["árvore do código","superfície derivada","responsabilidade","requisitos","arquitetura","operação"],
  items:["SCOPE.md — responsabilidade e limites","BRIEFING.md — estado e capacidade","SPEC.md — requisitos","SUPERFICIE.md — retrato derivado do código","ARCHITECTURE.md — construção e integrações, quando necessário","RUNBOOK-*.md — operação e recuperação"]},
 {t:"Fluxos ponta a ponta",s:"venda, cancelamento, fiscal, WhatsApp, IA, migração, deploy e recuperação",
  flow:["ator","máquinas","módulos","dado","falha","recuperação"],
  items:["ator e ponto de entrada","máquinas e módulos atravessados","dado transportado","autenticação e autorização","business_id","transação e idempotência","filas, retry e timeout","logs, métricas e alertas","falha parcial","compensação ou rollback","procedimento de recuperação"]}
];

const DOD = [
 ["parcial","toda máquina crítica tem dono técnico, probe e runbook validado","D1 · D7"],
 ["aberto","hooks e tools MCP inventariados por máquina e explicados por família","D2 · D3"],
 ["aberto","cada módulo ativo alcança suas portas documentais aplicáveis","D4 · D5"],
 ["aberto","cada fluxo crítico declara auth, business_id, dado, observabilidade, falha e recuperação","D7"],
 ["aberto","runbooks críticos carregam owner e last_validated sustentados por execução","D1 · D8"],
 ["parcial","segredos aparecem apenas como ponteiro para o cofre","transversal"],
 ["aberto","/documentacao navega por infra, plataforma, módulos, fluxos e operação","D9"],
 ["aberto","detectores reexecutados; resíduo fechado ou justificado","D10"],
 ["aberto","tasks MCP do parent_plan não deixam trabalho concluído marcado como aberto","transversal"],
 ["aberto","um incidente já gerou aprendizado e voltou ao início do ciclo","D10"]
];

const BATIMENTO = [
 ["Mudança em PR","staleness/impacto documental","mostra módulos e donos afetados; não edita automaticamente"],
 ["Batimento agendado","<code>system-map</code> + <code>memory-health</code>","atualiza retratos derivados e acusa integridade/fato quebrado"],
 ["Revisão semanal","<code>briefing-code-staleness</code> + <code>documentation-loop --snapshot</code>","oferece a fila de drift; o ZELADOR escolhe um item"],
 ["Execução","workflow <code>documentacao-tecnica</code>","snapshot → correção no dono → recibo → PR, exatamente um item"],
 ["Revisão do plano","<code>plan-health</code> + <code>jana:plan-drift</code>","acusa plano velho, ligação fantasma ou status divergente das tasks"],
 ["Incidente","runbook + <code>LICOES_CODE.md</code>/<code>proibicoes.md</code>","devolve o aprendizado ao próximo ciclo de medição"]
];

const CAMADAS = [
 ["Infraestrutura","Hostinger, Proxmox, CT 100, GitHub Actions, Windows/Firebird, router, Tailscale, PBX, SVN e dispositivos","<code>memory/reference/infra-*.md</code> + <code>Infra/RUNBOOK-*.md</code>"],
 ["Plataforma","hooks, MCP, CI, skills, agents, scripts, baselines e observabilidade","índices gerados + arquitetura/runbooks Jana, Forja e Infra"],
 ["Aplicação","kernel, módulos transversais, verticais e integrações","<code>SCOPE</code> · <code>BRIEFING</code> · <code>SPEC</code> · <code>SUPERFICIE</code> · <code>ARCHITECTURE</code> · <code>RUNBOOK-*</code>"],
 ["Fluxos","venda, estoque, financeiro, fiscal, WhatsApp, Jana, migração, deploy e recuperação","<code>GUIA-DO-SISTEMA.md</code> aponta; detalhe fica no dono do fluxo"],
 ["Operação","acesso, monitoramento, manutenção, backup, restore, rollback e incidentes","runbooks de operação + auditoria Ops/DR"],
 ["Visão humana","a rota <code>/documentacao</code>, autenticada","renderiza <code>memory/GUIA-DO-SISTEMA.md</code>"]
];

const H = s => ({dangerouslySetInnerHTML:{__html:s}});

function Kpi({k,v,u,s}){return (
 <div className="pd-kpi"><div className="k">{k}</div><div className="v">{v}{u&&<em>{u}</em>}</div><div className="s">{s}</div></div>);}

function Ciclo(){
 const [sel,setSel]=useState(1);
 const st=STATIONS[sel];
 return (<>
  <div className="pd-sec-h"><h2>O ciclo completo</h2><p>onze estações — descobrir, medir, traduzir, publicar, operar, aprender e medir de novo</p></div>
  <div className="pd-loop">
   {STATIONS.map((s,i)=>
    <button key={s.n} className={"pd-st"+(i===sel?" on":"")} data-phase={s.ph} onClick={()=>setSel(i)}>
     <span className="dot" /><i>{s.n}</i><b>{s.t}</b><span>{s.s}</span></button>)}
  </div>
  <div className="pd-back">estação 11 → estação 02 · o aprendizado reentra na medição; publicar não encerra</div>
  <div className="pd-det">
   <div className="pd-det-h"><span className="n">{st.n}</span><b>{st.t}</b>
    <span className="pd-phase" data-phase={st.ph}>{st.ph}</span></div>
   <div className="pd-det-g">
    <div><div className="k">entrada</div><div className="v" {...H(st.ent)} /></div>
    <div><div className="k">máquina que já existe</div><div className="v" {...H(st.mq)} /></div>
    <div><div className="k">regra</div><div className="v" {...H(st.reg)} /></div>
   </div>
  </div>
  <div className="pd-note">{DS.Alert
   ? <DS.Alert tone="info" title="Uma unidade de trabalho = uma task + um achado">Selecionar, medir antes, localizar o dono, priorizar, traduzir, validar técnica e operacionalmente, provar pelo recibo, entregar PR de uma intenção, publicar, fechar e aprender.</DS.Alert>
   : <p>Uma unidade de trabalho = uma task MCP + exatamente um achado acionável.</p>}</div>
 </>);
}

function Ondas(){
 const [sel,setSel]=useState("D0");
 return (<>
  <div className="pd-sec-h"><h2>Ondas executáveis</h2><p>kernel e transversais críticos → plataforma → verticais → integrações → legado</p></div>
  <table className="pd-t"><thead><tr>
   <th>onda</th><th>escopo</th><th>saída no dono existente</th><th>gate de saída</th><th>estado</th></tr></thead>
   <tbody>{ONDAS.map(([id,nome,esc,saida,gate,st])=>
    <tr key={id} role="button" tabIndex={0} className={sel===id?"on":""} onClick={()=>setSel(id)}>
     <td><b>{id}</b><div className="mono">{nome}</div></td>
     <td {...H(esc)} /><td {...H(saida)} /><td {...H(gate)} />
     <td>{st==="doing"
      ? (DS.StatusBadge?<DS.StatusBadge kind="documento" value="em-execucao" label="em execução" />:<b>em execução</b>)
      : <span className="mono">na fila</span>}</td>
    </tr>)}</tbody></table>
  <div className="pd-note">{DS.Alert
   ? <DS.Alert tone="warn" title="Estado vivo não mora aqui">Ondas e DoD são intenção — este plano é o dono. Execução (<code>todo/doing/done</code>) vive nas tasks MCP com <code>parent_plan=programa-ondas</code>; duplicar status em markdown é o erro que a trilha existe pra não repetir.</DS.Alert>
   : null}</div>
 </>);
}

function Caminhos(){
 return (<>
  <div className="pd-sec-h"><h2>Caminho canônico por tipo</h2><p>o que cada artefato precisa declarar antes de ser considerado documentado</p></div>
  <div className="pd-paths">{PATHS.map(p=>
   <div className="pd-path" key={p.t}>
    <h3>{p.t}</h3><p className="sub">{p.s}</p>
    <div className="pd-flow">{p.flow.map((f,i)=>[
     i>0&&<span className="sep" key={"s"+i}>→</span>,<span key={f}>{f}</span>])}</div>
    <ul>{p.items.map(i=><li key={i}>{i}</li>)}</ul>
   </div>)}</div>
  <div className="pd-sec-h"><h2>As seis camadas do escopo</h2><p>cada uma com dono documental que já existe no git</p></div>
  <table className="pd-t"><thead><tr><th>camada</th><th>componentes</th><th>dono principal</th></tr></thead>
   <tbody>{CAMADAS.map(([c,comp,dono])=>
    <tr key={c}><td><b>{c}</b></td><td>{comp}</td><td {...H(dono)} /></tr>)}</tbody></table>
 </>);
}

function Pronto(){
 const feitos=DOD.filter(d=>d[0]==="feito").length, parc=DOD.filter(d=>d[0]==="parcial").length;
 return (<>
  <div className="pd-sec-h"><h2>Definição de pronto</h2>
   <p>{feitos} fechados · {parc} parciais · {DOD.length-feitos-parc} abertos — a trilha só termina quando o ciclo já girou uma vez inteiro</p></div>
  <div className="pd-dod">{DOD.map(([st,txt,onda])=>
   <div className="pd-dod-i" data-st={st} key={txt}><div className="box" /><div><p>{txt}</p><em>{onda} · {st}</em></div></div>)}</div>
  <div className="pd-sec-h"><h2>Batimento que mantém a trilha ativa</h2><p>advisory por decisão: detecta e oferece trabalho, não decide conteúdo nem merge</p></div>
  <table className="pd-t"><thead><tr><th>momento</th><th>máquina existente</th><th>efeito</th></tr></thead>
   <tbody>{BATIMENTO.map(([m,mq,ef])=>
    <tr key={m}><td><b>{m}</b></td><td {...H(mq)} /><td {...H(ef)} /></tr>)}</tbody></table>
  <div className="pd-note">{DS.Alert
   ? <DS.Alert tone="danger" title="Fora de escopo">Documentação de produto por tela · cópia manual de inventário · reescrita de ADR aceita · criação de máquina de governança · correção de achado adjacente durante outra etapa.</DS.Alert>
   : null}</div>
 </>);
}

const VIEWS=[["ciclo","Ciclo",Ciclo],["ondas","Ondas",Ondas],["caminhos","Caminhos",Caminhos],["pronto","Pronto & batimento",Pronto]];

function ProgramaDocPage(){
 const [v,setV]=useState(()=>{try{return localStorage.getItem("oimpresso.progdoc.v")||"ciclo"}catch(e){return "ciclo"}});
 const set=k=>{setV(k);try{localStorage.setItem("oimpresso.progdoc.v",k)}catch(e){}};
 const View=(VIEWS.find(x=>x[0]===v)||VIEWS[0])[2];
 return (
 <div className="os-page" data-screen-label="01 Programa de documentação">
  <header className="os-page-h">
   <div className="os-page-h-l">
    <h1>Programa de documentação <span className="suffix">· Trilha D</span></h1>
    <p>Não é escrever documentação — é manter um sistema que mede, traduz, publica, opera, detecta drift e aprende. Autorizada por [W] em 05/08/2026 · ciclo completo em 06/08/2026.</p>
   </div>
   <div className="os-page-h-r">
    <button className="os-btn ghost" onClick={()=>window.__go&&window.__go("documentacao")}>← Documentação</button>
    <a className="os-btn ghost" href={BLOB} target="_blank" rel="noopener">Ver plano no git</a>
   </div>
  </header>
  {DS.TabBar
   ? <div className="pd-tabbar"><DS.TabBar tabs={VIEWS.map(([k,l])=>({key:k,label:l}))} active={v} onChange={set} /></div>
   : <div className="pd-tabbar fallback" role="group" aria-label="Visão do programa">
      {VIEWS.map(([k,l])=><button key={k} className={v===k?"on":""} onClick={()=>set(k)}>{l}</button>)}</div>}
  <div className="pd-body">
   <div className="pd-kpis">
    <Kpi k="onda atual" v="D0" s="fundação — inventários, donos e navegação" />
    <Kpi k="ondas" v="1" u="/ 11" s="D0 em execução · D1–D10 na fila" />
    <Kpi k="estações do ciclo" v="11" s="fecha em aprender → medir de novo" />
    <Kpi k="task MCP" v="US-INFRA-048" s="parent_plan=programa-ondas · merge ratifica" />
   </div>
   <View />
   <div className="pd-foot">
    dono deste texto: <a href={BLOB} target="_blank" rel="noopener">{GIT}</a>
    <span>·</span> a tela renderiza o plano; não é cópia commitada
   </div>
  </div>
 </div>);
}

window.ProgramaDocPage=ProgramaDocPage;
})();
