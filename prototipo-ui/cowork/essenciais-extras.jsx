// essenciais-extras.jsx — ondas E1–E5 do módulo Essentials:
// modal de situação (todo/update_task_status_modal) · compartilhar por usuário OU função
// (document_share/edit) · documentos compartilhados na tarefa (todo/view_shared_docs) ·
// base de conhecimento hierárquica (knowledge_base/index + sidebar) · configurações do módulo
// (EssentialsSettingsController). Expõe window.EssenciaisExtras. Carrega depois de hrm-ui.jsx.
(() => {
const { useState, useEffect, useRef } = React;
const E = window.ESSENCIAIS;
const U = window.HrmUI;
const { Badge, Card, Row, Nota, Drawer, Sec, KV, Campo, Escolha, Texto, Vazio, useAmbiente, SemPermissao } = U;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const HOJE = new Date(2026, 7, 21);
const brDate = (s) => { if (!s) return null; const [d, m, a] = s.split("/"); return new Date(+a, +m - 1, +d); };
const atrasada = (t) => t.status !== "completed" && t.fim && brDate(t.fim) < HOJE;

// ── Modal de troca de situação (o blade usa modal, não select solto) ──
function ModalStatus({ tarefas, onClose, onAplicar }) {
  const [status, setStatus] = useState(tarefas.length === 1 ? tarefas[0].status : "in_progress");
  const { Modal } = ds();
  const caixa = useRef(null);
  useEffect(() => {
    const antes = document.activeElement;
    const foco = () => caixa.current ? [...caixa.current.querySelectorAll('button,select,input')].filter((el) => !el.disabled) : [];
    const t = setTimeout(() => { const f = foco(); if (f[0]) f[0].focus(); }, 30);
    const k = (e) => {
      if (e.key === "Escape") { e.stopPropagation(); onClose(); return; }
      if (e.key !== "Tab") return;
      const f = foco(); if (!f.length) return;
      const ini = f[0], fim = f[f.length - 1];
      if (e.shiftKey && document.activeElement === ini) { e.preventDefault(); fim.focus(); }
      else if (!e.shiftKey && document.activeElement === fim) { e.preventDefault(); ini.focus(); }
    };
    document.addEventListener("keydown", k);
    return () => { clearTimeout(t); document.removeEventListener("keydown", k); if (antes && antes.focus) antes.focus(); };
  }, [onClose]);
  const corpo = (
    <>
      <p className="ess-texto">{tarefas.length === 1
        ? <>Alterar a situação de <b>{tarefas[0].ref}</b> — {tarefas[0].tarefa}.</>
        : <>Alterar a situação de <b>{tarefas.length} tarefas</b> selecionadas de uma vez.</>}</p>
      <div className="ess-radios" role="radiogroup" aria-label="Situação">
        {Object.entries(E.ST_TAR).map(([k, v]) => (
          <button key={k} type="button" role="radio" aria-checked={status === k} className={`ess-radio ${status === k ? "on" : ""}`} onClick={() => setStatus(k)}>
            <Badge tone={v.tone}>{v.l}</Badge>
          </button>))}
      </div>
    </>
  );
  const acoes = (
    <>
      <button className="os-btn ghost" onClick={onClose}>Fechar</button>
      <button className="os-btn" onClick={() => onAplicar(status)}>Atualizar situação</button>
    </>
  );
  if (Modal) return <div ref={caixa}><Modal open onClose={onClose} title="Alterar situação" footer={acoes}>{corpo}</Modal></div>;
  return (
    <div className="ess-modal-bg" onClick={onClose}>
      <div ref={caixa} className="ess-modal" role="dialog" aria-modal="true" aria-label="Alterar situação" onClick={(e) => e.stopPropagation()}>
        <h3>Alterar situação</h3>{corpo}<div className="ess-modal-f">{acoes}</div>
      </div>
    </div>
  );
}

// ── Compartilhar documento/memorando: por usuário ou por função ──
function Compartilhar({ item, memo, onClose, onSalvar }) {
  const A = useAmbiente();
  const [comp, setComp] = useState(item.comp);
  const [modo, setModo] = useState("usuario");
  const [quem, setQuem] = useState("");
  const add = () => {
    if (!quem) return;
    if (comp.some((c) => c.q === quem)) return;
    setComp([...comp, { q:quem, f:modo === "funcao" ? "Função" : (window.HRM.EMP.find((e) => e.nome === quem) || {}).setor || "Equipe" }]);
    setQuem("");
  };
  const opcoes = modo === "funcao" ? E.FUNCOES : (window.HRM.EMP || []).map((e) => e.nome);
  return (
    <Drawer title="Compartilhar" sub={`${memo ? item.tit : item.arq} · document_share`} onClose={onClose}
      footer={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className="os-btn" onClick={() => onSalvar(comp)}>Salvar compartilhamento</button></>}>
      <Sec title="Quem passa a ver">
        <div className="hrm-seg">
          <button className={modo === "usuario" ? "on" : ""} onClick={() => { setModo("usuario"); setQuem(""); }}>Por usuário</button>
          <button className={modo === "funcao" ? "on" : ""} onClick={() => { setModo("funcao"); setQuem(""); }}>Por função</button>
        </div>
        <div className="ess-add-linha">
          <select className="hrm-sel" value={quem} onChange={(e) => setQuem(e.target.value)} aria-label={modo === "funcao" ? "Função" : "Usuário"}>
            <option value="">{modo === "funcao" ? "Escolha a função" : "Escolha a pessoa"}</option>
            {opcoes.map((o) => <option key={o} value={o}>{o}</option>)}
          </select>
          <button className="os-btn" onClick={add} disabled={A.demo || !quem}>Adicionar</button>
        </div>
        <p className="hrm-card-sub">Compartilhar por função acompanha a equipe: quem entra na função passa a ver sem ninguém refazer a lista.</p>
      </Sec>
      <Sec title={`Compartilhado com (${comp.length})`}>
        {comp.length === 0 ? <p className="hrm-empty">Só quem enviou vê este item.</p>
          : <div className="hrm-list">{comp.map((c, i) => (
              <Row key={i} t={c.q} s={c.f}
                v={<button className="os-btn ghost sm danger" disabled={A.demo} onClick={() => setComp(comp.filter((_, ix) => ix !== i))}>Remover</button>}/>))}</div>}
      </Sec>
    </Drawer>
  );
}

// ── Documentos compartilhados com a tarefa (todo/view_shared_docs) ──
function DocsDaTarefa({ t, onClose }) {
  const nomes = t.a.map((id) => E.nome(id));
  const docs = E.DOCS.filter((d) => d.comp.some((c) => nomes.includes(c.q)));
  return (
    <Drawer title="Documentos compartilhados" sub={`${t.ref} · visíveis para quem está na tarefa`} onClose={onClose}
      footer={<button className="os-btn ghost" onClick={onClose}>Fechar</button>}>
      <Sec title={`Anexos da tarefa (${t.docs.length})`}>
        {t.docs.length === 0 ? <p className="hrm-empty">Nenhum anexo nesta tarefa.</p>
          : <div className="hrm-list">{t.docs.map((d) => <Row key={d.n} t={d.n} s={d.t} v={<button className="os-btn ghost sm">Baixar</button>}/>)}</div>}
      </Sec>
      <Sec title={`Do repositório, já compartilhados com os responsáveis (${docs.length})`}>
        {docs.length === 0
          ? <p className="hrm-empty">Nada do repositório está compartilhado com {nomes.join(" ou ")}. Compartilhe em Documentos para não anexar cópia.</p>
          : <div className="hrm-list">{docs.map((d) => <Row key={d.id} t={d.arq} s={`${d.tipo} · ${d.tam} · ${d.desc.slice(0, 60)}…`} v={<button className="os-btn ghost sm">Baixar</button>}/>)}</div>}
      </Sec>
    </Drawer>
  );
}

// ── Base de conhecimento: categoria → seção → artigo (knowledge_base/index + sidebar) ──
function BaseConhecimento() {
  const A = useAmbiente();
  const [cat, setCat] = useState(E.KB[0].id);
  const [art, setArt] = useState(E.KB[0].filhos[0] ? E.KB[0].filhos[0].id : null);
  const [q, setQ] = useState("");
  const atual = E.KB.find((c) => c.id === cat) || E.KB[0];
  const achar = (id) => {
    for (const c of E.KB) { if (c.id === id) return c;
      for (const s of c.filhos) { if (s.id === id) return s; for (const a of s.filhos || []) if (a.id === id) return a; } }
    return null;
  };
  const aberto = achar(art) || atual;
  const casa = (n) => !q || (n.t + n.c).toLowerCase().includes(q.toLowerCase());

  return (
    <>
      <div className="hrm-toolbar">
        <U.Busca value={q} onChange={setQ} placeholder="Buscar na base  ·  /"/>
        {A.pode("gerir_kb") && <button className="os-btn" disabled={A.demo}>Adicionar categoria</button>}
      </div>
      <div className="ess-kb">
        <aside className="ess-kb-nav" aria-label="Categorias e seções">
          {E.KB.map((c) => (
            <div className="ess-kb-cat" key={c.id}>
              <button className={`ess-kb-b cat ${c.id === cat ? "on" : ""}`} onClick={() => { setCat(c.id); setArt(c.id); }}>{c.t}</button>
              {c.id === cat && c.filhos.filter(casa).map((s) => (
                <div key={s.id}>
                  <button className={`ess-kb-b sec ${s.id === art ? "on" : ""}`} onClick={() => setArt(s.id)}>{s.t}</button>
                  {(s.filhos || []).filter(casa).map((a) => (
                    <button key={a.id} className={`ess-kb-b art ${a.id === art ? "on" : ""}`} onClick={() => setArt(a.id)}>{a.t}</button>))}
                </div>))}
            </div>))}
        </aside>
        <article className="ess-kb-doc">
          <h2>{aberto.t}</h2>
          <p className="ess-texto">{aberto.c}</p>
          {(aberto.blocos || []).length > 0 && (
            <div className="ess-kb-rico">
              {aberto.blocos.map((b, i) => b.t === "h" ? <h3 key={i}>{b.x}</h3>
                : b.t === "li" ? <p key={i} className="ess-kb-li">{b.x}</p>
                : <p key={i} className="ess-texto">{b.x}</p>)}
            </div>)}
          {(aberto.filhos || []).length > 0 && (
            <div className="ess-kb-filhos">
              {aberto.filhos.map((f) => (
                <button className="ess-kb-card" key={f.id} onClick={() => setArt(f.id)}>
                  <b>{f.t}</b><span className="hrm-meta">{f.c.slice(0, 90)}…</span>
                </button>))}
            </div>)}
          {A.pode("gerir_kb") && (
            <div className="ess-kb-acoes">
              <button className="os-btn ghost sm" disabled={A.demo}>Editar</button>
              <button className="os-btn ghost sm" disabled={A.demo}>Adicionar seção</button>
              <button className="os-btn ghost sm danger" disabled={A.demo}>Excluir</button>
            </div>)}
        </article>
      </div>
    </>
  );
}

// ── Configurações do módulo (aba própria; o HRM tem as dele) ──
function Config() {
  const A = useAmbiente();
  const [c, setC] = useState(E.CFG);
  const [salvo, setSalvo] = useState(false);
  if (!A.pode("config")) return <SemPermissao frase="Editar as configurações do Essentials exige edit_essentials_settings."/>;
  const set = (k, v) => { setC({ ...c, [k]:v }); setSalvo(false); };
  return (
    <>
      <div className="hrm-grid">
        <Card title="Tarefas" sub="Prefixo do ID e quem pode atribuir.">
          <div className="hrm-campos">
            <Campo label="Prefixo do ID da tarefa" valor={c.essentials_todos_prefix} onChange={(v) => set("essentials_todos_prefix", v)} help="Gera TAR2026/0001."/>
            <Escolha label="Quem atribui tarefa" valor={c.todo_assign_default} onChange={(v) => set("todo_assign_default", v)}
              opcoes={[{ v:"admin", l:"Só administrador" }, { v:"gestor", l:"Administrador e gestor" }, { v:"todos", l:"Qualquer colaborador" }]}/>
          </div>
        </Card>
        <Card title="Documentos e memorandos" sub="Limite de upload e padrão de compartilhamento.">
          <div className="hrm-campos">
            <Campo label="Tamanho máximo (MB)" valor={c.document_upload_max} onChange={(v) => set("document_upload_max", v)} help={`Tipos aceitos: ${E.UPLOAD.tipos.join(" · ")}.`}/>
            <Escolha label="Compartilhar memorando por" valor={c.memo_share_default} onChange={(v) => set("memo_share_default", v)} opcoes={[{ v:"usuario", l:"Usuário" }, { v:"funcao", l:"Função" }]}/>
          </div>
        </Card>
        <Card title="Lembretes e mensagens" sub="Padrões do calendário e do mural.">
          <div className="hrm-campos">
            <Escolha label="Repetição padrão" valor={c.reminder_default_repeat} onChange={(v) => set("reminder_default_repeat", v)} opcoes={Object.entries(E.REPETE).map(([k, l]) => ({ v:k, l }))}/>
            <Escolha label="Mensagem exige localidade" valor={c.message_locations ? "1" : "0"} onChange={(v) => set("message_locations", v === "1")} opcoes={[{ v:"1", l:"Sim" }, { v:"0", l:"Não" }]}/>
            <Escolha label="Base de conhecimento visível ao cliente" valor={c.kb_public ? "1" : "0"} onChange={(v) => set("kb_public", v === "1")} opcoes={[{ v:"0", l:"Não — só interna" }, { v:"1", l:"Sim" }]}/>
          </div>
        </Card>
      </div>
      <div className="hrm-toolbar">
        <button className="os-btn" disabled={A.demo} onClick={() => setSalvo(true)}>Salvar configurações</button>
        {salvo && <span className="hrm-meta">Salvo. No main isso grava em businesses.essentials_settings.</span>}
      </div>
      <Nota tone="info" title="Uma configuração, dois lugares">
        Esta aba mexe no que é do escritório (tarefas, documentos, lembretes, mural). Tolerância de marcação, prefixo da folha e meta de venda continuam em <code>HRM · Configurações</code> — o controller é o mesmo.
      </Nota>
    </>
  );
}

// ── Histórico de situação (E6: o main registra em activity log; aqui é a leitura) ──
function Historico({ t }) {
  const h = t.hist || [];
  if (!h.length) return <p className="hrm-empty">Sem histórico — a tarefa nasceu e não mudou de situação.</p>;
  return (
    <ol className="ess-hist">
      {h.map(([quando, st, quem], i) => (
        <li key={i}>
          <span className="ess-hist-p"></span>
          <div>
            <div className="ess-hist-l"><Badge tone={E.ST_TAR[st].tone}>{E.ST_TAR[st].l}</Badge><span className="hrm-mono">{quando}</span></div>
            <span className="hrm-meta">por {E.nome(quem)}</span>
          </div>
        </li>))}
    </ol>
  );
}

// ── Tarefa em tela cheia (todo/show.blade) — a mesma tarefa que o drawer, com espaço ──
function TarefaPage({ t, onVoltar, onStatus, onEditar, onComentar }) {
  const A = useAmbiente();
  const [texto, setTexto] = useState("");
  if (!t) return <Vazio variante="no-results" titulo="Tarefa não encontrada" desc="Volte para a lista e abra outra." action={<button className="os-btn" onClick={onVoltar}>Voltar para tarefas</button>}/>;
  return (
    <div className="ess-show">
      <div className="hrm-toolbar">
        <button className="os-btn ghost" onClick={onVoltar}>← Tarefas</button>
        <span className="hrm-mono">{t.ref}</span>
        <span className="hrm-spacer"></span>
        {A.pode("editar_tarefa") && <button className="os-btn ghost" disabled={A.demo} onClick={() => onEditar(t)}>Editar</button>}
        <button className="os-btn" onClick={() => onStatus(t)}>Alterar situação</button>
      </div>
      <div className="ess-show-g">
        <article className="hrm-card">
          <h3>{t.tarefa}</h3>
          <div className="ess-pills">
            <Badge tone={E.ST_TAR[t.status].tone}>{E.ST_TAR[t.status].l}</Badge>
            <Badge tone={E.PRIO[t.prio].tone}>Prioridade {E.PRIO[t.prio].l.toLowerCase()}</Badge>
            {atrasada(t) && <Badge tone="danger">Fim venceu em {t.fim}</Badge>}
          </div>
          <p className="ess-texto" style={{ marginTop:12 }}>{t.desc}</p>
          <h4 className="ess-sub">Comentários ({t.coments.length})</h4>
          {t.coments.length === 0 ? <p className="hrm-empty">Sem comentários.</p>
            : <div className="ess-coments">{t.coments.map((c, i) => (
                <div className="ess-coment" key={i}>
                  <span className="ess-av">{E.nome(c.por).split(" ").map((p) => p[0]).slice(0, 2).join("")}</span>
                  <div><div className="hrm-meta">{E.nome(c.por)} · {c.q}</div><p className="ess-texto">{c.t}</p></div>
                </div>))}</div>}
          <div className="ess-coment-novo">
            <textarea className="ess-ta" rows="2" value={texto} disabled={A.demo} placeholder="Escreva um comentário…" onChange={(e) => setTexto(e.target.value)}></textarea>
            <button className="os-btn" disabled={A.demo || !texto.trim()} onClick={() => { onComentar(t, texto.trim()); setTexto(""); }}>Comentar</button>
          </div>
        </article>
        <aside className="ess-show-lado">
          <Card title="Prazo e esforço">
            <KV pairs={[["Criada em", t.criado], ["Início", t.ini], ["Fim", t.fim || "sem data de fim"], ["Horas estimadas", t.horas ? t.horas + " h" : "—"]]}/>
          </Card>
          <Card title="Pessoas">
            <KV pairs={[["Atribuída por", E.nome(t.por)], ["Responsáveis", t.a.map((id) => E.nome(id)).join(", ")]]}/>
          </Card>
          <Card title="Histórico de situação"><Historico t={t}/></Card>
          <Card title={`Documentos (${t.docs.length})`}>
            {t.docs.length === 0 ? <p className="hrm-empty">Nenhum anexo.</p>
              : <div className="hrm-list">{t.docs.map((d) => <Row key={d.n} t={d.n} s={d.t} v={<button className="os-btn ghost sm">Baixar</button>}/>)}</div>}
          </Card>
        </aside>
      </div>
    </div>
  );
}

window.EssenciaisExtras = { ModalStatus, Compartilhar, DocsDaTarefa, BaseConhecimento, Config, Historico, TarefaPage, atrasada, brDate, HOJE };
})();
