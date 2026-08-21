// essenciais-page.jsx — módulo Essentials, aba "Essenciais" (nav_essentials.blade):
// todo · document · memos · reminder · messages · knowledge-base · settings.
// Importado do blade do main: Modules/Essentials/Resources/views/*.
// Ondas: E1 mecânica (período, ordenação, lote, modal de situação) · E2 formulários
// (editar, comentar, compartilhar, upload) · E3 telas que faltavam (docs da tarefa, KB, config)
// E4 origem do lembrete + mural com lida · E5 atraso, densidade, teclado.
// Primitivos do DS via hrm-ui.jsx; telas de apoio em essenciais-extras.jsx.
// Expõe window.EssenciaisPage.
(() => {
const { useState, useMemo, useRef } = React;
const E = window.ESSENCIAIS;
const H = window.HRM;
const U = window.HrmUI;
const X = window.EssenciaisExtras;
const { Badge, Card, Row, Nota, Busca, Drawer, Sec, KV, Campo, Escolha, Texto, Data, Tabela, Paginacao, Bulk, Skel, Vazio, Aviso, Ambiente, useAmbiente, SemPermissao, usePagina, useAviso, useAtalhos, useCarga, usePersist, limparDados } = U;

const TABS = [
  { id:"essenciais",     label:"Tarefas",              n:(d) => d.tar.filter((t) => t.status !== "completed").length },
  { id:"ess-documentos", label:"Documentos",           n:(d) => d.docs.length },
  { id:"ess-memorandos", label:"Memorandos",           n:(d) => d.memos.length },
  { id:"ess-lembretes",  label:"Lembretes",            n:(d) => d.lemb.length },
  { id:"ess-mensagens",  label:"Mensagens",            n:(d) => d.msg.filter((m) => !m.lida).length },
  { id:"ess-kb",         label:"Base de conhecimento" },
  { id:"ess-config",     label:"Configurações" },
];
const TAB_DE = { "ess-tarefa":"essenciais" };
const MESES = ["janeiro","fevereiro","março","abril","maio","junho","julho","agosto","setembro","outubro","novembro","dezembro"];
const optEmp = (H.EMP || []).map((e) => [e.id, e.nome]);
const iniciais = (n) => n.split(" ").map((p) => p[0]).slice(0, 2).join("");
const ORDEM_PRIO = { urgent:0, high:1, medium:2, low:3 };

// ═══════════════════════ TAREFAS (todo/index.blade + todo/view.blade) ═══════════════════════
function Tarefas({ view }) {
  const A = useAmbiente();
  const carregando = useCarga();
  const [tar, setTar] = usePersist("tar", A.dados.tar);
  const [q, setQ] = useState("");
  const [fUser, setFUser] = useState("");
  const [fPrio, setFPrio] = useState("");
  const [fSt, setFSt] = useState("");
  const [de, setDe] = useState("");
  const [ate, setAte] = useState("");
  const [ordem, setOrdem] = useState("recentes");
  const [densa, setDensa] = usePersist("dens", "comfortable");
  const [selLinhas, setSelLinhas] = useState([]); // ids como string: o DataTablePro do DS emite String(r.id)
  const [sel, setSel] = useState(null);
  const [form, setForm] = useState(null);   // {} = nova · tarefa = edição
  const [modal, setModal] = useState(null); // tarefas cuja situação vai mudar
  const [docsDe, setDocsDe] = useState(null);
  const [aviso, setAviso] = useAviso();
  const busca = useRef(null);

  const podeAtribuir = A.pode("atribuir");
  const minhas = podeAtribuir ? tar : tar.filter((t) => t.a.includes(A.eu) || t.por === A.eu);
  const dentro = (t) => {
    if (!de && !ate) return true;
    const d = X.brDate(t.ini);
    if (de && d < new Date(de)) return false;
    if (ate && d > new Date(ate)) return false;
    return true;
  };
  const lista = useMemo(() => {
    const base = minhas.filter((t) =>
      (!q || (t.tarefa + t.ref).toLowerCase().includes(q.toLowerCase())) &&
      (!fUser || t.a.includes(fUser)) && (!fPrio || t.prio === fPrio) && (!fSt || t.status === fSt) && dentro(t));
    const cmp = {
      recentes:(a, b) => X.brDate(b.criado.split(" ")[0]) - X.brDate(a.criado.split(" ")[0]),
      prazo:(a, b) => (X.brDate(a.fim) || 8e12) - (X.brDate(b.fim) || 8e12),
      prioridade:(a, b) => ORDEM_PRIO[a.prio] - ORDEM_PRIO[b.prio],
      horas:(a, b) => b.horas - a.horas,
    }[ordem];
    return [...base].sort(cmp);
  }, [minhas, q, fUser, fPrio, fSt, de, ate, ordem]);
  const pg = usePagina(lista, densa === "compact" ? 12 : 8);
  useAtalhos({ busca, onNovo:A.demo ? null : () => setForm({}), onEsc:() => { setSel(null); setForm(null); setModal(null); setDocsDe(null); setSelLinhas([]); } });

  const aplicarStatus = (status) => {
    const ids = modal.map((t) => t.id);
    const marca = (t) => ({ ...t, status, hist:[...(t.hist || []), ["21/08/2026 10:12", status, A.eu]] });
    setTar(tar.map((t) => ids.includes(t.id) ? marca(t) : t));
    setSel((s) => s && ids.includes(s.id) ? marca(s) : s);
    setModal(null); setSelLinhas([]);
    setAviso(`${ids.length === 1 ? modal[0].ref : ids.length + " tarefas"} agora em "${E.ST_TAR[status].l.toLowerCase()}".`);
  };
  const concluir = (itens) => {
    const ids = itens.map((t) => t.id);
    const marca = (t) => ({ ...t, status:"completed", hist:[...(t.hist || []), ["21/08/2026 10:12", "completed", A.eu]] });
    setTar(tar.map((t) => ids.includes(t.id) ? marca(t) : t));
    setSel((s) => s && ids.includes(s.id) ? marca(s) : s);
    setSelLinhas([]);
    setAviso(`${ids.length === 1 ? itens[0].ref + " concluída" : ids.length + " tarefas concluídas"}.`);
  };
  const salvar = (t) => {
    if (t.id) {
      setTar(tar.map((x) => x.id === t.id ? { ...x, ...t } : x));
      setSel((s) => s && s.id === t.id ? { ...s, ...t } : s);
      setAviso(`Tarefa ${t.ref} atualizada.`);
    } else {
      const ref = "TAR2026/" + String(13 + tar.length - E.TAREFAS.length).padStart(4, "0");
      setTar([{ ...t, id:Date.now(), ref, criado:"21/08/2026 09:40", por:A.eu, docs:[], coments:[], hist:[["21/08/2026 09:40", t.status, A.eu]] }, ...tar]);
      setAviso(`Tarefa ${ref} criada e atribuída.`);
    }
    setForm(null);
  };
  const comentar = (t, texto) => {
    const c = { por:A.eu, q:"21/08/2026 09:55", t:texto };
    setTar(tar.map((x) => x.id === t.id ? { ...x, coments:[...x.coments, c] } : x));
    setSel((s) => s && s.id === t.id ? { ...s, coments:[...s.coments, c] } : s);
    setAviso("Comentário publicado na tarefa.");
  };
  const excluir = (ids) => {
    const chaves = ids.map(String);
    setTar(tar.filter((t) => !chaves.includes(String(t.id))));
    setSelLinhas([]); setSel(null);
    setAviso(`${ids.length === 1 ? "Tarefa excluída" : ids.length + " tarefas excluídas"}.`);
  };
  const selecionadas = tar.filter((t) => selLinhas.includes(String(t.id)));

  const cols = [
    { key:"criado", label:"Criado em", width:120 },
    { key:"ref",    label:"ID da tarefa", mono:true, width:118 },
    { key:"tarefa", label:"Tarefa", width:330 },
    { key:"status", label:"Situação", width:150 },
    { key:"ini",    label:"Início", mono:true, width:96 },
    { key:"fim",    label:"Fim", mono:true, width:110 },
    { key:"horas",  label:"Horas est.", align:"right", width:88 },
    { key:"por",    label:"Atribuído por", width:140 },
    { key:"a",      label:"Atribuído a", width:170 },
  ];
  const rows = pg.fatia.map((t) => ({
    id:t.id, _t:t, state:X.atrasada(t) ? "urgent" : (t.status === "completed" ? "archived" : undefined),
    cells:{
      criado:{ primary:t.criado.split(" ")[0], sub:t.criado.split(" ")[1] },
      ref:<span className="hrm-mono">{t.ref}</span>,
      tarefa:{ primary:t.tarefa, sub:`Prioridade ${E.PRIO[t.prio].l.toLowerCase()}${t.docs.length ? " · " + t.docs.length + " anexo(s)" : ""}${t.coments.length ? " · " + t.coments.length + " comentário(s)" : ""}` },
      status:<span className="ess-pills">
        <Badge tone={E.ST_TAR[t.status].tone}>{E.ST_TAR[t.status].l}</Badge>
        {X.atrasada(t) && <Badge tone="danger">Atrasada</Badge>}
      </span>,
      ini:<span className="hrm-mono">{t.ini}</span>,
      fim:t.fim ? <span className={`hrm-mono ${X.atrasada(t) ? "hrm-neg" : ""}`}>{t.fim}</span> : <span className="hrm-meta">—</span>,
      horas:<span className="hrm-mono">{t.horas ? t.horas + " h" : "—"}</span>,
      por:<span className="hrm-name">{E.nome(t.por)}</span>,
      a:<span className="ess-quem">{t.a.map((id) => <span key={id} className="ess-av" title={E.nome(id)}>{iniciais(E.nome(id))}</span>)}<span className="hrm-meta">{E.nome(t.a[0])}{t.a.length > 1 ? ` +${t.a.length - 1}` : ""}</span></span>,
    },
  }));
  const linha = (r) => setSel(r._t || (rows.find((x) => x.id === r.id) || {})._t);
  const abrirCheia = (t) => { window.__ESS_TAREFA_ID = t.id; setSel(null); window.__go && window.__go("ess-tarefa"); };

  if (view === "ess-tarefa") {
    const t = tar.find((x) => x.id === window.__ESS_TAREFA_ID) || tar[0];
    return (
      <>
        <X.TarefaPage t={t} onVoltar={() => window.__go && window.__go("essenciais")}
          onStatus={(x) => setModal([x])} onEditar={(x) => setForm(x)} onComentar={comentar}/>
        {form && <FormTarefa item={form.id ? form : null} onClose={() => setForm(null)} onSalvar={salvar} podeAtribuir={podeAtribuir}/>}
        {modal && <X.ModalStatus tarefas={modal} onClose={() => setModal(null)} onAplicar={aplicarStatus}/>}
        <Aviso msg={aviso} tone="ok"/>
      </>
    );
  }

  return (
    <>
      <div className="hrm-toolbar">
        <Busca value={q} onChange={setQ} placeholder="Buscar tarefa ou ID  ·  /" inputRef={busca}/>
        {podeAtribuir && (
          <select className="hrm-sel" value={fUser} onChange={(e) => setFUser(e.target.value)} aria-label="Atribuído a">
            <option value="">Atribuído a: todos</option>
            {optEmp.map(([id, n]) => <option key={id} value={id}>{n}</option>)}
          </select>)}
        <select className="hrm-sel" value={fPrio} onChange={(e) => setFPrio(e.target.value)} aria-label="Prioridade">
          <option value="">Prioridade: todas</option>
          {Object.entries(E.PRIO).map(([k, v]) => <option key={k} value={k}>{v.l}</option>)}
        </select>
        <select className="hrm-sel" value={fSt} onChange={(e) => setFSt(e.target.value)} aria-label="Situação">
          <option value="">Situação: todas</option>
          {Object.entries(E.ST_TAR).map(([k, v]) => <option key={k} value={k}>{v.l}</option>)}
        </select>
        <button className="os-btn" disabled={A.demo} title={A.demo ? "Indisponível na demonstração" : "Nova tarefa (n)"} onClick={() => setForm({})}>Adicionar</button>
      </div>
      <div className="hrm-toolbar ess-toolbar2">
        <label className="ess-lab">Período de início
          <input className="hrm-sel" type="date" value={de} onChange={(e) => setDe(e.target.value)} aria-label="De"/>
          <span className="hrm-meta">até</span>
          <input className="hrm-sel" type="date" value={ate} onChange={(e) => setAte(e.target.value)} aria-label="Até"/>
        </label>
        {(de || ate) && <button className="os-btn ghost sm" onClick={() => { setDe(""); setAte(""); }}>Limpar período</button>}
        <span className="hrm-spacer"></span>
        <select className="hrm-sel" value={ordem} onChange={(e) => setOrdem(e.target.value)} aria-label="Ordenar por">
          <option value="recentes">Mais recentes</option>
          <option value="prazo">Prazo mais próximo</option>
          <option value="prioridade">Prioridade</option>
          <option value="horas">Maior esforço</option>
        </select>
        <div className="hrm-seg">
          <button className={densa === "comfortable" ? "on" : ""} onClick={() => setDensa("comfortable")}>Confortável</button>
          <button className={densa === "compact" ? "on" : ""} onClick={() => setDensa("compact")}>Compacto</button>
        </div>
      </div>

      {carregando ? <Skel n={8}/> : lista.length === 0
        ? <Vazio variante={A.primeira ? "first" : "no-results"}
            titulo={A.primeira ? "Nenhuma tarefa por aqui" : "Nada com esses filtros"}
            desc={A.primeira
              ? "A lista de afazeres é do escritório inteiro: quem atribui usa a permissão essentials.assign_todos; quem só executa vê as suas. Crie a primeira em Adicionar."
              : "Combinação de responsável, prioridade, situação e período sem resultado. Limpe um filtro pra ver mais."}/>
        : <>
            <Tabela cols={cols} rows={rows} altura={430} densidade={densa} selecionavel={A.pode("editar_tarefa")}
              onSelecao={(ids) => setSelLinhas(Array.isArray(ids) ? ids.map((i) => String((i && i.id) || i)) : [])} onLinha={linha}/>
            <Paginacao pagina={pg.pagina} paginas={pg.paginas} onMudar={pg.setPagina} total={pg.total} porPagina={pg.porPagina}/>
          </>}

      <Bulk n={selecionadas.length} rotulo="tarefas selecionadas" onFechar={() => setSelLinhas([])}
        acoes={[
          { label:"Alterar situação", onClick:() => setModal(selecionadas) },
          { label:"Concluir", onClick:() => concluir(selecionadas) },
          { label:"Excluir", tone:"danger", onClick:() => excluir(selLinhas) },
        ]}/>

      {sel && <DetalheTarefa t={sel} onClose={() => setSel(null)} onStatus={() => setModal([sel])}
        onEditar={() => setForm(sel)} onComentar={comentar} onDocs={() => setDocsDe(sel)}
        onExcluir={() => excluir([sel.id])} onCheia={() => abrirCheia(sel)}/>}
      {form && <FormTarefa item={form.id ? form : null} onClose={() => setForm(null)} onSalvar={salvar} podeAtribuir={podeAtribuir}/>}
      {modal && <X.ModalStatus tarefas={modal} onClose={() => setModal(null)} onAplicar={aplicarStatus}/>}
      {docsDe && <X.DocsDaTarefa t={docsDe} onClose={() => setDocsDe(null)}/>}
      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

function DetalheTarefa({ t, onClose, onStatus, onEditar, onComentar, onDocs, onExcluir, onCheia }) {
  const A = useAmbiente();
  const [texto, setTexto] = useState("");
  return (
    <Drawer title={t.tarefa} sub={`${t.ref} · criada em ${t.criado} por ${E.nome(t.por)}`} onClose={onClose} largo
      footer={<>
        <button className="os-btn ghost" onClick={onClose}>Fechar</button>
        <button className="os-btn ghost" onClick={onCheia}>Abrir em tela cheia</button>
        {A.pode("editar_tarefa") && <button className="os-btn ghost" disabled={A.demo} onClick={onEditar}>Editar</button>}
        {A.pode("excluir") && <button className="os-btn ghost danger" disabled={A.demo} onClick={onExcluir}>Excluir</button>}
        <button className="os-btn" onClick={onStatus}>Alterar situação</button>
      </>}>
      <Sec title="Situação">
        <div className="ess-pills">
          <Badge tone={E.ST_TAR[t.status].tone}>{E.ST_TAR[t.status].l}</Badge>
          <Badge tone={E.PRIO[t.prio].tone}>Prioridade {E.PRIO[t.prio].l.toLowerCase()}</Badge>
          {X.atrasada(t) && <Badge tone="danger">Fim venceu em {t.fim}</Badge>}
        </div>
      </Sec>
      <Sec title="Prazo e esforço">
        <KV pairs={[["Início", t.ini], ["Fim", t.fim || "sem data de fim"], ["Horas estimadas", t.horas ? `${t.horas} h` : "—"], ["Atribuído a", t.a.map((id) => E.nome(id)).join(", ")]]}/>
      </Sec>
      <Sec title="Descrição">
        <p className="ess-texto">{t.desc}</p>
      </Sec>
      <Sec title="Histórico de situação"><X.Historico t={t}/></Sec>
      <Sec title={`Documentos (${t.docs.length})`}>
        {t.docs.length === 0
          ? <p className="hrm-empty">Nenhum anexo nesta tarefa.</p>
          : <div className="hrm-list">{t.docs.map((d) => <Row key={d.n} t={d.n} s={d.t} v={<button className="os-btn ghost sm">Baixar</button>}/>)}</div>}
        <button className="os-btn ghost sm" onClick={onDocs}>Ver documentos compartilhados</button>
      </Sec>
      <Sec title={`Comentários (${t.coments.length})`}>
        {t.coments.length === 0 ? <p className="hrm-empty">Sem comentários.</p>
          : <div className="ess-coments">{t.coments.map((c, i) => (
              <div className="ess-coment" key={i}>
                <span className="ess-av">{iniciais(E.nome(c.por))}</span>
                <div><div className="hrm-meta">{E.nome(c.por)} · {c.q}</div><p className="ess-texto">{c.t}</p></div>
              </div>))}</div>}
        <div className="ess-coment-novo">
          <textarea className="ess-ta" rows="2" placeholder="Escreva um comentário…" value={texto} disabled={A.demo}
            onChange={(e) => setTexto(e.target.value)}
            onKeyDown={(e) => { if (e.key === "Enter" && (e.metaKey || e.ctrlKey) && texto.trim()) { onComentar(t, texto.trim()); setTexto(""); } }}></textarea>
          <button className="os-btn" disabled={A.demo || !texto.trim()} onClick={() => { onComentar(t, texto.trim()); setTexto(""); }}>Comentar</button>
        </div>
      </Sec>
    </Drawer>
  );
}

function FormTarefa({ item, onClose, onSalvar, podeAtribuir }) {
  const [tarefa, setTarefa] = useState(item?.tarefa || "");
  const [a, setA] = useState(item?.a || []);
  const [prio, setPrio] = useState(item?.prio || "medium");
  const [status, setStatus] = useState(item?.status || "new");
  const [ini, setIni] = useState(item?.ini || "21/08/2026");
  const [fim, setFim] = useState(item?.fim || "");
  const [horas, setHoras] = useState(item ? String(item.horas || "") : "");
  const [desc, setDesc] = useState(item?.desc || "");
  const [erros, setErros] = useState({});
  const enviar = () => {
    const e = {};
    if (!tarefa.trim()) e.tarefa = "A tarefa precisa de um título — é o que aparece na lista.";
    if (podeAtribuir && a.length === 0) e.a = "Escolha pelo menos um responsável: tarefa sem dono não é feita.";
    if (fim && ini && X.brDate(fim) < X.brDate(ini)) e.fim = "O fim não pode ser antes do início.";
    setErros(e);
    if (Object.keys(e).length) return;
    onSalvar({ ...(item || {}), tarefa, a:a.length ? a : ["e-1"], prio, status, ini, fim, horas:Number(horas) || 0, desc });
  };
  const alterna = (id) => setA((l) => l.includes(id) ? l.filter((x) => x !== id) : [...l, id]);
  return (
    <Drawer title={item ? `Editar ${item.ref}` : "Adicionar tarefa"} sub={`essentials/todo · campos do todo/${item ? "edit" : "create"}.blade`} onClose={onClose}
      footer={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button><button className="os-btn" onClick={enviar}>Salvar</button></>}>
      <Sec title="Tarefa">
        <Campo label="Tarefa *" valor={tarefa} onChange={setTarefa} erro={erros.tarefa} wide foco placeholder="O que precisa ser feito"/>
      </Sec>
      {podeAtribuir && (
        <Sec title="Atribuído a *">
          <div className="ess-chips">
            {optEmp.map(([id, n]) => (
              <button key={id} type="button" className={`ess-chip ${a.includes(id) ? "on" : ""}`} onClick={() => alterna(id)}>{n}</button>))}
          </div>
          {erros.a && <span className="hrm-erro">{erros.a}</span>}
        </Sec>)}
      <Sec title="Prioridade e situação">
        <div className="hrm-campos">
          <Escolha label="Prioridade" valor={prio} onChange={setPrio} opcoes={Object.entries(E.PRIO).map(([k, v]) => ({ v:k, l:v.l }))}/>
          <Escolha label="Situação" valor={status} onChange={setStatus} opcoes={Object.entries(E.ST_TAR).map(([k, v]) => ({ v:k, l:v.l }))}/>
        </div>
      </Sec>
      <Sec title="Prazo">
        <div className="hrm-campos">
          <Data label="Início *" valor={ini} onChange={setIni}/>
          <Data label="Fim" valor={fim} onChange={setFim} erro={erros.fim} help="Em branco = tarefa sem prazo de entrega."/>
          <Campo label="Horas estimadas" valor={horas} onChange={setHoras} placeholder="6"/>
        </div>
      </Sec>
      <Sec title="Descrição">
        <Texto label="Descrição" valor={desc} onChange={setDesc} linhas={4}/>
        <p className="hrm-card-sub">No main o anexo sobe por dropzone (attach.medias.to.model) depois de salvar a tarefa.</p>
      </Sec>
    </Drawer>
  );
}

// ═══════════════ DOCUMENTOS e MEMORANDOS (document/index.blade · memos/index.blade) ═══════════════
function Arquivos({ modo }) {
  const A = useAmbiente();
  const carregando = useCarga();
  const memo = modo === "memos";
  const [itens, setItens] = usePersist(memo ? "memos" : "docs", memo ? A.dados.memos : A.dados.docs);
  const [q, setQ] = useState("");
  const [form, setForm] = useState(false);
  const [sel, setSel] = useState(null);
  const [comp, setComp] = useState(null);
  const [aviso, setAviso] = useAviso();
  const busca = useRef(null);
  useAtalhos({ busca, onNovo:A.demo ? null : () => setForm(true), onEsc:() => { setForm(false); setSel(null); setComp(null); } });

  const lista = itens.filter((i) => !q || ((memo ? i.tit : i.arq) + i.desc).toLowerCase().includes(q.toLowerCase()));
  const cols = memo
    ? [{ key:"tit", label:"Título", width:280 }, { key:"desc", label:"Descrição", width:440 }, { key:"data", label:"Data de criação", width:150 }, { key:"acao", label:"Ações", width:200 }]
    : [{ key:"arq", label:"Nome", width:280 }, { key:"desc", label:"Descrição", width:400 }, { key:"data", label:"Data do upload", width:150 }, { key:"acao", label:"Ações", width:230 }];
  const acoes = (i) => (
    <span className="ess-acoes">
      <button className="os-btn ghost sm" onClick={(ev) => { ev.stopPropagation(); setSel(i); }}>{memo ? "Ver" : "Baixar"}</button>
      {A.pode("compartilhar") && <button className="os-btn ghost sm" onClick={(ev) => { ev.stopPropagation(); setComp(i); }}>Compartilhar</button>}
      {A.pode("excluir") && <button className="os-btn ghost sm danger" disabled={A.demo}
        onClick={(ev) => { ev.stopPropagation(); setItens(itens.filter((x) => x.id !== i.id)); setAviso("Excluído."); }}>Excluir</button>}
    </span>
  );
  const rows = lista.map((i) => ({
    id:i.id, _i:i,
    cells:memo
      ? { tit:{ primary:i.tit, sub:`por ${E.nome(i.por)}${i.comp.length ? " · compartilhado com " + i.comp.length : ""}` }, desc:<span className="ess-linha2">{i.desc}</span>, data:<span className="hrm-mono">{i.data}</span>, acao:acoes(i) }
      : { arq:{ primary:i.arq, sub:`${i.tipo} · ${i.tam} · por ${E.nome(i.por)}` }, desc:<span className="ess-linha2">{i.desc}</span>, data:<span className="hrm-mono">{i.data}</span>, acao:acoes(i) },
  }));

  return (
    <>
      <Card title={memo ? "Todas as notas" : "Todos os documentos"} sub={memo ? "Gerencie todas as suas notas — o memorando é um documento com tipo memos no mesmo controller." : "Gerencie todos os seus documentos — arquivo, descrição e com quem está compartilhado."}
        acao={<button className="os-btn" disabled={A.demo || !A.pode(memo ? "add_memo" : "subir_doc")} onClick={() => setForm(true)}>Adicionar</button>}>
        {form && <FormArquivo memo={memo} onClose={() => setForm(false)}
          onSalvar={(novo) => { setItens([novo, ...itens]); setForm(false); setAviso(memo ? "Memorando publicado." : "Documento enviado."); }}/>}
        {carregando ? <Skel n={5}/> : lista.length === 0
          ? <Vazio variante={A.primeira ? "first" : "no-results"} titulo={memo ? "Nenhum memorando" : "Nenhum documento"}
              desc={memo ? "Memorando é o recado que fica: regra de balcão, plantão de feriado, prazo do mês. Publique o primeiro em Adicionar."
                         : "Aqui ficam contrato, apólice, tabela de preços e perfil de cor — o que a equipe precisa achar sem pedir no WhatsApp."}/>
          : <Tabela cols={cols} rows={rows} altura={400} onLinha={(r) => setSel(r._i || (rows.find((x) => x.id === r.id) || {})._i)}/>}
      </Card>
      {sel && (
        <Drawer title={memo ? sel.tit : sel.arq} sub={memo ? `Criado em ${sel.data} por ${E.nome(sel.por)}` : `${sel.tipo} · ${sel.tam} · enviado em ${sel.data} por ${E.nome(sel.por)}`} onClose={() => setSel(null)}
          footer={<><button className="os-btn ghost" onClick={() => setSel(null)}>Fechar</button>
            {A.pode("compartilhar") && <button className="os-btn ghost" onClick={() => { setComp(sel); setSel(null); }}>Compartilhar</button>}
            {!memo && <button className="os-btn">Baixar</button>}</>}>
          <Sec title="Descrição"><p className="ess-texto">{sel.desc}</p></Sec>
          <Sec title={`Compartilhado com (${sel.comp.length})`}>
            {sel.comp.length === 0
              ? <p className="hrm-empty">Só quem enviou vê este item. Compartilhar libera por usuário ou por função (document_share).</p>
              : <div className="hrm-list">{sel.comp.map((c, i) => <Row key={i} t={c.q} s={c.f}/>)}</div>}
          </Sec>
        </Drawer>)}
      {comp && <X.Compartilhar item={comp} memo={memo} onClose={() => setComp(null)}
        onSalvar={(lista2) => { setItens(itens.map((i) => i.id === comp.id ? { ...i, comp:lista2 } : i)); setComp(null); setAviso("Compartilhamento salvo."); }}/>}
      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

function FormArquivo({ memo, onClose, onSalvar }) {
  const A = useAmbiente();
  const [tit, setTit] = useState("");
  const [desc, setDesc] = useState("");
  const [arq, setArq] = useState(null);
  const [erro, setErro] = useState("");
  const file = useRef(null);
  const escolher = (e) => {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    const ext = f.name.split(".").pop().toLowerCase();
    if (!E.UPLOAD.tipos.includes(ext)) { setErro(`Tipo .${ext} não aceito. Use ${E.UPLOAD.tipos.join(" · ")}.`); return; }
    setErro(""); setArq({ nome:f.name, tipo:ext.toUpperCase(), tam:(f.size / 1048576).toFixed(1).replace(".", ",") + " MB" });
  };
  const enviar = () => {
    if (memo && !tit.trim()) { setErro("O memorando precisa de um título."); return; }
    if (!memo && !arq) { setErro("Escolha o arquivo antes de enviar."); return; }
    onSalvar(memo
      ? { id:Date.now(), tit, desc, data:"21/08/2026 10:05", por:A.eu, comp:[] }
      : { id:Date.now(), arq:arq.nome, tipo:arq.tipo, tam:arq.tam, desc, data:"21/08/2026 10:05", por:A.eu, comp:[] });
  };
  return (
    <div className="ess-upload">
      {!memo && (
        <>
          <div className={`ess-drop ${arq ? "ok" : ""}`} onClick={() => file.current && file.current.click()}>
            <b>{arq ? arq.nome : "Escolher arquivo"}</b>
            <span className="hrm-meta">{arq ? `${arq.tipo} · ${arq.tam}` : `${E.UPLOAD.tipos.join(" · ")} · até ${E.UPLOAD.limite} (constants.document_upload_mimes_types)`}</span>
          </div>
          <input ref={file} className="ess-file" type="file" onChange={escolher} accept={E.UPLOAD.tipos.map((t) => "." + t).join(",")}/>
        </>)}
      {memo && <Campo label="Título *" valor={tit} onChange={setTit} wide placeholder="Assunto do memorando"/>}
      <Texto label="Descrição" valor={desc} onChange={setDesc} linhas={3}/>
      {erro && <span className="hrm-erro">{erro}</span>}
      <div className="ess-upload-acoes">
        <button className="os-btn" onClick={enviar} disabled={A.demo}>Enviar</button>
        <button className="os-btn ghost danger" onClick={onClose}>Cancelar</button>
      </div>
    </div>
  );
}

// ═══════════════════════ LEMBRETES (reminder/index.blade — fullcalendar) ═══════════════════════
function Lembretes() {
  const A = useAmbiente();
  const [lemb, setLemb] = usePersist("lemb", A.dados.lemb);
  const [mes, setMes] = useState(7); // agosto/2026
  const ano = 2026;
  const [fOrig, setFOrig] = useState("");
  const [form, setForm] = useState(false);
  const [sel, setSel] = useState(null);
  const [foco, setFoco] = useState(21);
  const [aviso, setAviso] = useAviso();
  const grade = useRef(null);

  const primeiro = new Date(ano, mes, 1).getDay();
  const dias = new Date(ano, mes + 1, 0).getDate();
  const iso = (d) => `${ano}-${String(mes + 1).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
  const visiveis = lemb.filter((l) => !fOrig || l.origem === fOrig);
  const local = (s) => { const [a, m, dd] = s.split("-"); return new Date(+a, +m - 1, +dd); };
  const doDia = (d) => visiveis.filter((l) => l.data === iso(d) || l.rep === "every_day"
    || (l.rep === "every_week" && local(l.data).getDay() === new Date(ano, mes, d).getDay())
    || (l.rep === "every_month" && +l.data.split("-")[2] === d));
  const celulas = [...Array(primeiro).fill(null), ...Array.from({ length:dias }, (_, i) => i + 1)];

  const teclas = (e, d) => {
    const passo = { ArrowLeft:-1, ArrowRight:1, ArrowUp:-7, ArrowDown:7 }[e.key];
    if (passo) {
      e.preventDefault();
      const alvo = Math.min(dias, Math.max(1, d + passo));
      setFoco(alvo);
      const el = grade.current && grade.current.querySelector(`[data-dia="${alvo}"]`);
      if (el) el.focus();
      return;
    }
    if (e.key === "Enter" || e.key === " ") {
      const primeiroEv = doDia(d)[0];
      if (primeiroEv) { e.preventDefault(); setSel(primeiroEv); }
    }
  };

  return (
    <>
      <div className="hrm-toolbar">
        <div className="hrm-seg" role="tablist">
          <button onClick={() => setMes((m) => Math.max(0, m - 1))}>Anterior</button>
          <button className="on">{MESES[mes]} de {ano}</button>
          <button onClick={() => setMes((m) => Math.min(11, m + 1))}>Próximo</button>
        </div>
        <select className="hrm-sel" value={fOrig} onChange={(e) => setFOrig(e.target.value)} aria-label="Origem">
          <option value="">Origem: todas</option>
          {Object.entries(E.ORIGEM).map(([k, v]) => <option key={k} value={k}>{v.l}</option>)}
        </select>
        <span className="hrm-spacer"></span>
        <button className="os-btn" disabled={A.demo} onClick={() => setForm(true)}>Adicionar lembrete</button>
      </div>
      <div className="ess-cal">
        <div className="ess-cal-h">{["dom","seg","ter","qua","qui","sex","sáb"].map((d) => <span key={d}>{d}</span>)}</div>
        <div className="ess-cal-g" ref={grade} role="grid" aria-label={`Lembretes de ${MESES[mes]} de ${ano}`}>
          {celulas.map((d, i) => (
            <div key={i} role={d ? "gridcell" : "presentation"} data-dia={d || undefined}
              tabIndex={d ? (d === foco ? 0 : -1) : undefined}
              aria-label={d ? `${d} de ${MESES[mes]}, ${doDia(d).length} lembrete(s)` : undefined}
              className={`ess-cal-d ${d ? "" : "vazio"} ${d === 21 ? "hoje" : ""}`}
              onFocus={() => d && setFoco(d)} onKeyDown={(e) => d && teclas(e, d)}>
              {d && <><span className="ess-cal-n">{d}</span>
                {doDia(d).slice(0, 3).map((l) => (
                  <button key={l.id + "-" + d} className={`ess-ev o-${l.origem}`} onClick={() => setSel(l)} title={`${l.nome} · ${E.ORIGEM[l.origem].l}`} tabIndex={-1}>
                    <span className="hrm-mono">{l.h}</span> {l.nome}
                  </button>))}
                {doDia(d).length > 3 && <span className="hrm-meta">+{doDia(d).length - 3}</span>}</>}
            </div>))}
        </div>
      </div>
      <div className="ess-legenda">
        {Object.entries(E.ORIGEM).map(([k, v]) => <span key={k} className="ess-leg"><i className={`ess-leg-c o-${k}`}></i>{v.l}</span>)}
        <span className="hrm-meta">Setas percorrem os dias · Enter abre o primeiro lembrete do dia</span>
      </div>

      {sel && (
        <Drawer title={sel.nome} sub={`${E.REPETE[sel.rep]} · ${E.ORIGEM[sel.origem].l}`} onClose={() => setSel(null)}
          footer={<><button className="os-btn ghost" onClick={() => setSel(null)}>Fechar</button>
            {sel.origem === "manual"
              ? <button className="os-btn danger" disabled={A.demo} onClick={() => { setLemb(lemb.filter((l) => l.id !== sel.id)); setSel(null); setAviso("Lembrete excluído."); }}>Excluir lembrete</button>
              : <button className="os-btn" onClick={() => window.__go && window.__go(sel.origem === "financeiro" ? "financeiro" : "ponto")}>Abrir no módulo</button>}</>}>
          <Sec title="Detalhes do lembrete">
            <KV pairs={[["Evento", sel.nome], ["Data", sel.data.split("-").reverse().join("/")], ["Hora de início", sel.h], ["Hora de término", sel.fim || "—"], ["Repetição", E.REPETE[sel.rep]],
              ["Origem", E.ORIGEM[sel.origem].l]].concat(sel.valor ? [["Valor", sel.valor]] : [])}/>
          </Sec>
          {sel.origem !== "manual" && <Nota tone="info" title="Vem de outro módulo">{E.ORIGEM[sel.origem].d}</Nota>}
        </Drawer>)}
      {form && <FormLembrete onClose={() => setForm(false)} onSalvar={(l) => { setLemb([...lemb, { ...l, id:Date.now(), por:A.eu, origem:"manual" }]); setForm(false); setAviso("Lembrete criado."); }}/>}
      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

function FormLembrete({ onClose, onSalvar }) {
  const [nome, setNome] = useState("");
  const [rep, setRep] = useState(E.CFG.reminder_default_repeat);
  const [data, setData] = useState("2026-08-21");
  const [h, setH] = useState("09:00");
  const [fim, setFim] = useState("");
  const [erro, setErro] = useState("");
  return (
    <Drawer title="Adicionar lembrete" sub="essentials/reminder · campos do reminder/create.blade" onClose={onClose}
      footer={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className="os-btn" onClick={() => nome.trim() ? onSalvar({ nome, rep, data, h, fim }) : setErro("O evento precisa de nome.")}>Enviar</button></>}>
      <Sec title="Evento">
        <Campo label="Nome do evento *" valor={nome} onChange={setNome} erro={erro} wide foco placeholder="Reunião de produção"/>
      </Sec>
      <Sec title="Quando">
        <div className="hrm-campos">
          <Escolha label="Repetição *" valor={rep} onChange={setRep} opcoes={Object.entries(E.REPETE).map(([k, l]) => ({ v:k, l }))}/>
          <Campo label="Data *" valor={data} onChange={setData} tipo="date"/>
          <Campo label="Hora de início *" valor={h} onChange={setH} tipo="time"/>
          <Campo label="Hora de término" valor={fim} onChange={setFim} tipo="time"/>
        </div>
      </Sec>
    </Drawer>
  );
}

// ═══════════════════════ MENSAGENS (messages/index.blade — mural por localidade) ═══════════════════════
function Mensagens() {
  const A = useAmbiente();
  const [msg, setMsg] = usePersist("msg", A.dados.msg);
  const [texto, setTexto] = useState("");
  const [local, setLocal] = useState(E.LOCAIS[0]);
  const [fLocal, setFLocal] = useState("");
  const [aviso, setAviso] = useAviso();
  if (!A.pode("ver_msg")) return <SemPermissao frase="As mensagens internas exigem essentials.view_message."/>;

  const lista = msg.filter((m) => !fLocal || m.local === fLocal);
  const naoLidas = msg.filter((m) => !m.lida).length;
  const enviar = () => {
    if (!texto.trim()) return;
    setMsg([...msg, { id:Date.now(), lida:true, por:A.eu, local, q:"21/08/2026 09:52", t:texto.trim() }]);
    setTexto(""); setAviso("Mensagem publicada no mural.");
  };
  return (
    <>
      <Card title="Mensagens" sub="Mural interno do negócio: uma mensagem por vez, com a localidade a que se refere. Sem thread, sem privado — é o que o blade entrega."
        acao={<span className="ess-acoes">
          <select className="hrm-sel" value={fLocal} onChange={(e) => setFLocal(e.target.value)} aria-label="Localidade">
            <option value="">Todas as localidades</option>
            {E.LOCAIS.map((l) => <option key={l} value={l}>{l}</option>)}
          </select>
          <button className="os-btn ghost sm" disabled={!naoLidas} onClick={() => { setMsg(msg.map((m) => ({ ...m, lida:true }))); setAviso("Mural marcado como lido."); }}>
            Marcar tudo como lido{naoLidas ? ` (${naoLidas})` : ""}
          </button>
        </span>}>
        <div className="ess-chat">
          {lista.map((m) => {
            const eu = m.por === A.eu;
            return (
              <div className={`ess-msg ${eu ? "eu" : ""} ${m.lida ? "" : "nova"}`} key={m.id}>
                <span className="ess-av">{iniciais(E.nome(m.por))}</span>
                <div className="ess-msg-b">
                  <div className="ess-msg-h"><b>{E.nome(m.por)}</b><span className="hrm-meta">{m.local} · {m.q}</span>
                    {!m.lida && <button className="ess-nova" onClick={() => setMsg(msg.map((x) => x.id === m.id ? { ...x, lida:true } : x))}>nova · marcar lida</button>}</div>
                  <p className="ess-texto">{m.t}</p>
                </div>
              </div>);
          })}
        </div>
        {A.pode("criar_msg") && (
          <div className="ess-compositor">
            <textarea className="ess-ta" rows="2" value={texto} onChange={(e) => setTexto(e.target.value)} disabled={A.demo}
              placeholder="Escreva uma mensagem" onKeyDown={(e) => { if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) enviar(); }}></textarea>
            <select className="hrm-sel" value={local} onChange={(e) => setLocal(e.target.value)} aria-label="Localidade da mensagem">
              {E.LOCAIS.map((l) => <option key={l} value={l}>{l}</option>)}
            </select>
            <button className="os-btn" onClick={enviar} disabled={A.demo || !texto.trim()}>Enviar</button>
          </div>)}
      </Card>
      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

// ═══════════════════════ SHELL ═══════════════════════
function EssenciaisPage({ view = "essenciais" }) {
  const [papel, setPapel] = usePersist("papel", "admin");
  const [estado, setEstado] = usePersist("estado", "normal");
  // limparDados() só zera as chaves do HRM — as do Essenciais precisam ir também,
  // senão "Primeira vez"/"Demonstração" continuam mostrando a lista anterior.
  const limparEss = () => { const s = window.__hrmSess; if (s) ["tar", "docs", "memos", "lemb", "msg"].forEach((k) => { delete s[k]; }); };
  const trocar = (setter) => (e) => { limparDados(); limparEss(); setter(e.target.value); };
  const go = (id) => window.__go && window.__go(id);
  const cur = TABS.find((t) => t.id === (TAB_DE[view] || view)) || TABS[0];
  const primeira = estado === "primeira";
  const demo = estado === "demo";
  const vazio = (v) => primeira ? [] : v;
  const dados = { tar:vazio(E.TAREFAS), docs:vazio(E.DOCS), memos:vazio(E.MEMOS), lemb:vazio(E.LEMB), msg:vazio(E.MSG) };
  const ambiente = {
    papel, estado, eu:"e-1", demo, primeira, dados,
    pode:(acao) => (E.PERMS[papel] || []).includes(acao),
    din:(v) => demo ? "R$ ••••" : H.brl(v),
  };
  const abertas = dados.tar.filter((t) => t.status !== "completed").length;
  const atrasadas = dados.tar.filter((t) => X.atrasada(t)).length;

  const body =
    view === "ess-documentos" ? <Arquivos modo="docs"/> :
    view === "ess-memorandos" ? <Arquivos modo="memos"/> :
    view === "ess-lembretes" ? <Lembretes/> :
    view === "ess-mensagens" ? <Mensagens/> :
    view === "ess-kb" ? <X.BaseConhecimento/> :
    view === "ess-config" ? <X.Config/> :
    <Tarefas view={view}/>;

  return (
    <Ambiente valor={ambiente}>
    <div className="os-page hrm-page ess-page" data-screen-label={`Essenciais · ${cur.label}`}>
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Essenciais</h1>
          <p className="tabular">{abertas} tarefa{abertas === 1 ? "" : "s"} em aberto{atrasadas ? ` · ${atrasadas} atrasada${atrasadas === 1 ? "" : "s"}` : ""} · {dados.docs.length} documentos · {dados.lemb.length} lembretes no calendário</p>
        </div>
        <div className="os-page-h-r">
          <span className="hrm-sim" title="Afordância de protótipo — não vai pro F3">
            <select className="hrm-sel" value={papel} onChange={trocar(setPapel)} aria-label="Papel simulado">
              {Object.entries(H.PAPEIS).map(([k, v]) => <option key={k} value={k}>{v.l}</option>)}
            </select>
            <select className="hrm-sel" value={estado} onChange={trocar(setEstado)} aria-label="Estado da tela">
              {Object.entries(H.ESTADOS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </span>
          <span className="hrm-scope">Essentials · /essentials</span>
          <button className="os-btn ghost" onClick={() => go("hrm")}>HRM</button>
        </div>
      </header>

      <nav className="hrm-tabs" role="tablist">
        {TABS.map((t) => {
          const n = t.n ? t.n(dados) : null;
          return (
            <button key={t.id} role="tab" aria-selected={t.id === (TAB_DE[view] || view)} className={t.id === (TAB_DE[view] || view) ? "on" : ""} onClick={() => go(t.id)}>
              {t.label}{n ? <span className="hrm-tab-n">{n}</span> : null}
            </button>);
        })}
      </nav>

      {(demo || papel !== "admin") && (
        <div className="hrm-note-ds">
          <Nota tone={demo ? "warn" : "info"} title={demo ? "Modo demonstração" : `Visão de ${H.PAPEIS[papel].l.toLowerCase()}`}>
            {demo
              ? <>Ambiente de demonstração (<code>APP_ENV=demo</code>): criar, enviar arquivo e excluir ficam indisponíveis, e o atalho <kbd>n</kbd> desliga.</>
              : <>Sem <code>essentials.assign_todos</code> este papel vê só as tarefas em que está — atribuir a outra pessoa fica com o administrador ou o gestor.</>}
          </Nota>
        </div>)}

      <div className="hrm-body" key={papel + estado}>{body}</div>
    </div>
    </Ambiente>
  );
}

window.EssenciaisPage = EssenciaisPage;
})();
