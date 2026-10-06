// fluxos-page.jsx — Sistema · Fluxos e regras. Catálogo de modelos de fluxo (FSM) por porte × atividade,
// assistente de IA que recomenda o modelo, fluxos em uso na empresa e casos de uso levantados nos
// concorrentes (Mubisys, Holdprint e os melhores do ramo). Dados em fluxos-data.jsx (window.FLX);
// regra de custo da compra vem de fsm-regras.jsx (window.OiFsmRegras). Expõe window.FluxosPage.
// Âncora no main: ADR 0129 (FSM tabular — "UI admin /admin/fsm: clonar template ou em branco").
(() => {
const { useState, useMemo, useEffect } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const F = () => window.FLX;
const K = { aba: "oimpresso.fluxos.aba", perfil: "oimpresso.fluxos.perfil.v1", modelo: "oimpresso.fluxos.modelo.v1" };
const ler = (k, d) => { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } };
const gravar = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };

const ABAS = [
  { key: "modelos", label: "Modelos" },
  { key: "processos", label: "Processos" },
  { key: "empresa", label: "Fluxos da empresa" },
  { key: "casos", label: "Casos de uso" },
];
const NEC = ["PageHeader", "TabBar", "Button", "Widget", "Segmented", "Select", "Drawer", "Modal", "DataGrid", "EmptyState", "Alert", "Switch"];

function Pill({ tone, children }) { return <span className={"flx-pill " + (tone || "")}>{children}</span>; }
function Cadeia({ etapas, marcadas }) {
  return (
    <ol className="flx-chain">
      {etapas.map((e, i) => <li key={e} className={marcadas && marcadas.has(e) ? "r" : ""}><span className="n">{i + 1}</span>{e}</li>)}
    </ol>
  );
}
const nFontes = (ids) => ids.map((f) => (F().FONTES[f] || { n: f }).n).join(", ");
const nPorte = (v) => (F().PORTES.find((p) => p.v === v) || { l: v }).l;
const nAtiv = (v) => (F().ATIVIDADES.find((a) => a.v === v) || { l: v }).l;
const nRegras = (m) => m.processos.reduce((s, p) => s + p.regras.length, 0);
const DOM_PROC = { venda: "venda", producao: "producao", compra: "compra", entrega: "entrega", financeiro: "cobranca" };
function motivo(m, perfil) {
  const P = m.portes.includes(perfil.porte), A = m.ativs.includes(perfil.ativ);
  const viz = !P && m.portes.some((x) => Math.abs(["micro", "pequena", "media", "grande"].indexOf(x) - ["micro", "pequena", "media", "grande"].indexOf(perfil.porte)) === 1);
  if (P && A) return "Porte e atividade batem";
  if (A) return viz ? "Atividade bate · feito para porte vizinho" : "Atividade bate · porte diferente";
  if (P) return "Porte bate · outra atividade";
  return "Outro porte e outra atividade";
}
function diferenca(de, para) {
  if (!de || !para || de.id === para.id) return null;
  const pd = de.processos.map((x) => x.dom), pp = para.processos.map((x) => x.dom);
  return { entram: pp.filter((x) => !pd.includes(x)), saem: pd.filter((x) => !pp.includes(x)),
    casosMais: para.casos.filter((x) => !de.casos.includes(x)), casosMenos: de.casos.filter((x) => !para.casos.includes(x)) };
}
const EXEMPLOS = [
  { l: "Gráfica rápida, 3 pessoas", t: "Somos 3 pessoas, gráfica rápida no centro. Cartão, panfleto e adesivo pequeno, quase tudo entregue no mesmo dia, pagamento no balcão." },
  { l: "Comunicação visual com instalação", t: "12 pessoas, banner, adesivo e fachada. Duas equipes de instalação. O cliente aprova a arte pelo celular e pagamos 50% de sinal antes de produzir." },
  { l: "Indústria gráfica com PCP", t: "60 pessoas em dois turnos, offset e digital. O gargalo é decidir o que entra na máquina; papel muda de preço todo mês." },
  { l: "Sublimação e camisetas", t: "Brindes, canecas e camisetas DTF. Compramos por grade de cor e tamanho e vendemos muito pela internet." },
];
const encaixeRot = (s) => s >= 4 ? ["Recomendado", "ok"] : s === 3 ? ["Serve", "accent"] : s >= 1 ? ["Parcial", "mute"] : ["Outro perfil", "mute"];

function resumoCompra(p) {
  const R = window.OiFsmRegras; if (!R || !p) return "";
  const rot = (k, v) => ((R.OPC[k] && R.OPC[k].o.find((x) => x[0] === v)) || [v, v])[1];
  return "Ao entrar em " + rot("etapa", p.etapa).split(" —")[0] + ": " + rot("metodo", p.metodo).toLowerCase() + " · " + rot("preco", p.preco).toLowerCase() + (p.preco === "propor" ? " (" + rot("aprovacao", p.aprovacao).toLowerCase() + ")" : "") + ".";
}

// ─── IA: recomenda modelo a partir do perfil + descrição livre ───
async function recomendar(texto, perfil) {
  const { MODELOS, CASOS, ATIVIDADES, encaixe } = F();
  const local = () => {
    const m = MODELOS.slice().sort((a, b) => encaixe(b, perfil) - encaixe(a, perfil))[0];
    return { modelo: m.id, porque: "Escolhido só pelo porte e pela atividade — a IA não respondeu.", ligar: [], desligar: [], porte: perfil.porte, ativ: perfil.ativ, origem: "regra" };
  };
  if (!window.claude || !window.claude.complete) return local();
  const prompt = [
    "Você configura um ERP brasileiro para gráficas, comunicação visual e oficinas. Escolha o modelo de fluxo (máquina de estados) mais adequado.",
    "Perfil informado: porte " + perfil.porte + ", atividade " + perfil.ativ + ".",
    "Descrição do dono: \"" + (texto || "(sem descrição)") + "\"",
    "Atividades possíveis: " + ATIVIDADES.map((a) => a.v + "=" + a.l).join("; "),
    "Modelos:\n" + MODELOS.map((m) => m.id + ": " + m.n + " | portes " + m.portes.join("/") + " | atividades " + m.ativs.join("/") + " | " + m.para + " | casos " + m.casos.join(",")).join("\n"),
    "Casos de uso:\n" + CASOS.map((c) => c.id + ": " + c.t).join("\n"),
    "Responda SOMENTE um JSON, sem texto fora dele: {\"modelo\":\"<id>\",\"porque\":\"<até 2 frases em português, tratando o dono por você>\",\"ligar\":[\"<casos fora do modelo que a descrição pede>\"],\"desligar\":[\"<casos do modelo que a descrição dispensa>\"],\"porte\":\"micro|pequena|media|grande\",\"ativ\":\"<código da atividade>\"}",
  ].join("\n\n");
  try {
    const txt = await window.claude.complete(prompt);
    const j = JSON.parse(txt.slice(txt.indexOf("{"), txt.lastIndexOf("}") + 1));
    if (!MODELOS.find((m) => m.id === j.modelo)) return local();
    const ok = (a) => (Array.isArray(a) ? a : []).filter((id) => F().casoPorId(id));
    return { modelo: j.modelo, porque: String(j.porque || ""), ligar: ok(j.ligar), desligar: ok(j.desligar), porte: j.porte || perfil.porte, ativ: j.ativ || perfil.ativ, origem: "ia" };
  } catch (e) { return local(); }
}

function Assistente({ perfil, onVer, onPerfil }) {
  const { Widget, Button, Textarea } = ds();
  const [txt, setTxt] = useState("");
  const [st, setSt] = useState({ fase: "livre" });
  const pedir = async () => { if (st.fase === "pensando") return; setSt({ fase: "pensando" }); const r = await recomendar(txt, perfil); setSt({ fase: "pronto", r }); };
  const atalho = (e) => { if ((e.ctrlKey || e.metaKey) && e.key === "Enter") { e.preventDefault(); pedir(); } };
  const r = st.r, m = r && F().MODELOS.find((x) => x.id === r.modelo);
  return (
    <Widget title="Assistente de configuração" note="Conte como a empresa trabalha. A IA sugere um modelo; quem decide é você.">
      <div className="flx-ia" onKeyDown={atalho}>
        <div className="flx-ex"><span className="flx-lbl">Começar de um exemplo</span>{EXEMPLOS.map((e) => <button key={e.l} type="button" className={"flp-chip" + (txt === e.t ? " on" : "")} aria-pressed={txt === e.t} onClick={() => setTxt(e.t)}>{e.l}</button>)}</div>
        {Textarea
          ? <Textarea label="Como a empresa trabalha" value={txt} onChange={(e) => setTxt(e.target.value)} placeholder="Ex.: 12 pessoas, banner e adesivo, duas equipes de instalação, o cliente aprova a arte pelo WhatsApp e a lona sobe de preço todo mês." />
          : <textarea className="flx-ta" aria-label="Como a empresa trabalha" value={txt} onChange={(e) => setTxt(e.target.value)} />}
        <div className="flx-ia-acoes">
          <Button variant="primary" size="sm" onClick={pedir} disabled={st.fase === "pensando"}>{st.fase === "pensando" ? "Analisando…" : "Recomendar modelo"}</Button>
          <span className="flx-dim">Usa o perfil acima: {nPorte(perfil.porte)} · {nAtiv(perfil.ativ)}{txt ? " · Ctrl+Enter envia" : " · sem descrição, decide só pelo perfil"}</span>
        </div>
        {st.fase === "pronto" && m && (
          <div className="flx-rec" role="status">
            <div className="flx-rec-h"><span className="flx-dim">{r.origem === "ia" ? "Sugestão da IA" : "Sugestão pelo perfil"}</span><b>{m.n}</b></div>
            {r.porque && <p>{r.porque}</p>}
            {r.ligar.length > 0 && <div className="flx-rec-l"><span>Ligar também</span>{r.ligar.map((id) => <Pill key={id} tone="ok">{F().casoPorId(id).t}</Pill>)}</div>}
            {r.desligar.length > 0 && <div className="flx-rec-l"><span>Pode desligar</span>{r.desligar.map((id) => <Pill key={id}>{F().casoPorId(id).t}</Pill>)}</div>}
            <div className="flx-ia-acoes">
              <Button size="sm" onClick={() => onVer(m.id)}>Ver o modelo</Button>
              {(r.porte !== perfil.porte || r.ativ !== perfil.ativ) && <Button size="sm" variant="ghost" onClick={() => onPerfil({ porte: r.porte, ativ: r.ativ })}>Ajustar perfil para {nPorte(r.porte)} · {nAtiv(r.ativ)}</Button>}
            </div>
          </div>
        )}
      </div>
    </Widget>
  );
}

function ModeloCard({ m, perfil, emUso, onOpen }) {
  const s = F().encaixe(m, perfil), [rot, tone] = encaixeRot(s);
  const princ = m.processos.find((p) => p.dom === "producao") || m.processos.find((p) => p.dom === "entrega") || m.processos[0];
  return (
    <button type="button" className={"flx-card" + (s < 2 ? " fraco" : "")} onClick={onOpen}>
      <div className="flx-card-h"><b>{m.n}</b>{emUso ? <Pill tone="accent">Em uso</Pill> : <Pill tone={tone}>{rot}</Pill>}</div>
      <span className={"flx-mot" + (s >= 4 ? " ok" : "")}>{motivo(m, perfil)}</span>
      <p>{m.para}</p>
      <div className="flx-card-p"><span>{F().PROCESSOS[princ.dom]}</span><Cadeia etapas={princ.etapas} marcadas={new Set(princ.regras.map((r) => r[0]))} /></div>
      <div className="flx-card-f">
        <span>{m.processos.length} processos · {nRegras(m)} regras · {m.casos.length} casos de uso</span>
        <span>Inspirado em {nFontes(m.insp)}</span>
      </div>
    </button>
  );
}

function ModeloDetalhe({ m, emUso, onProc, onCaso }) {
  const { FONTES, PROCESSOS, casoPorId } = F();
  const dif = diferenca(emUso, m);
  return (
    <div className="flx-det">
      <p className="flx-det-para">{m.para}</p>
      <div className="flx-det-meta"><span>Porte: {m.portes.map(nPorte).join(", ")}</span><span>Atividades: {m.ativs.map(nAtiv).join(", ")}</span></div>
      {dif && (
        <section className="flx-dif" aria-label={"Diferença para " + emUso.n}>
          <h4>Comparado ao modelo em uso ({emUso.n})</h4>
          <ul>
            {dif.entram.length > 0 && <li><b>Passa a ter:</b> {dif.entram.map((d) => PROCESSOS[d]).join(", ")}</li>}
            {dif.saem.length > 0 && <li><b>Deixa de ter:</b> {dif.saem.map((d) => PROCESSOS[d]).join(", ")}</li>}
            <li><b>Casos de uso:</b> +{dif.casosMais.length} · −{dif.casosMenos.length}</li>
            {m.compra && emUso.compra && resumoCompra(m.compra) !== resumoCompra(emUso.compra) && <li><b>Compra:</b> {resumoCompra(m.compra)}</li>}
          </ul>
        </section>
      )}
      {m.processos.map((p) => (
        <section key={p.dom} className="flx-proc">
          <h4>{PROCESSOS[p.dom]}<button type="button" className="flx-link" onClick={() => onProc(DOM_PROC[p.dom])}>Ver estados e condições</button></h4>
          <Cadeia etapas={p.etapas} marcadas={new Set(p.regras.map((r) => r[0]))} />
          <ul className="flx-regras">
            {p.regras.map(([em, t, uc], i) => (
              <li key={i}><span className="em">Ao entrar em <b>{em}</b></span><span>{t}{p.dom === "compra" && m.compra ? " — " + resumoCompra(m.compra) : ""}</span>{uc && <button type="button" className="flx-uc" onClick={() => onCaso(uc)}>{uc}</button>}</li>
            ))}
          </ul>
        </section>
      ))}
      <section className="flx-proc">
        <h4>Casos de uso cobertos <span className="flx-dim">{m.casos.length}</span></h4>
        <ul className="flx-casos">
          {m.casos.map((id) => { const c = casoPorId(id); return (
            <li key={id}><button type="button" className="flx-uc" onClick={() => onCaso(id)}>{id}</button><span>{c.t}</span><small>{c.fontes.map((f) => FONTES[f].n).join(" · ")}</small></li>); })}
        </ul>
      </section>
    </div>
  );
}

function RegrasLiga({ m, p, onCaso }) {
  const { Switch } = ds();
  const E = window.OiEtapa;
  const [, tick] = useState(0);
  useEffect(() => { const h = () => tick((n) => n + 1); window.addEventListener("oi-etapa", h); return () => window.removeEventListener("oi-etapa", h); }, []);
  return (
    <div className="flx-cmp">
      {p.regras.map(([em, t, uc], i) => {
        const on = E ? E.regraLigada(m.id, p.dom, i) : true;
        return (
          <div key={i} className="flx-liga-r">
            {Switch && E ? <Switch checked={on} onChange={(v) => E.setRegra(m.id, p.dom, i, v)} label={t} sublabel={"Ao entrar em " + em} /> : <span>{t} — ao entrar em {em}</span>}
            {uc && <button type="button" className="flx-uc" onClick={() => onCaso(uc)}>{uc}</button>}
          </div>
        );
      })}
      <p className="flx-nota">Ligar ou desligar aqui aparece no painel de etapa das telas deste processo.</p>
    </div>
  );
}
function TelasDoProc({ dom }) {
  const P = window.FLX_PROC; if (!P) return null;
  const ids = dom === "financeiro" ? ["cobranca", "nfe", "nfse", "contrato"] : [DOM_PROC[dom]];
  const telas = ids.flatMap((id) => (P.TELAS && P.TELAS[id]) || []);
  if (!telas.length) return null;
  return <div className="flp-liga flx-telas"><span className="flx-lbl">Aparece em</span>{telas.map(([rota, l]) => <button key={rota + l} type="button" className="flp-chip" onClick={() => window.__selectRoute && window.__selectRoute(rota)}>{l}</button>)}</div>;
}

function CompraRegras() {
  const R = window.OiFsmRegras;
  const { Switch, Select } = ds();
  const regras = R.useRegras("compra");
  return (
    <div className="flx-cmp">
      {regras.map((r) => (
        <div key={r.id} className="flx-cmp-r">
          <Switch checked={r.ativo} disabled={r.trava} onChange={(v) => R.salvar("compra", r.id, { ativo: v })} label={r.titulo} sublabel={"Ao entrar em " + r.etapaEf + (r.trava ? " · obrigatória" : "")} />
          {r.ativo && r.params && r.id === "insumo.custo" && (
            <div className="flx-cmp-p">
              {Object.keys(r.params).filter((k) => R.OPC[k] && (k !== "aprovacao" || r.params.preco === "propor") && (k !== "regraEm" || r.params.preco !== "so_custo")).map((k) => (
                <Select key={k} label={R.OPC[k].l} value={r.params[k]} onChange={(e) => R.salvar("compra", r.id, { params: { [k]: e.target.value } })}>
                  {R.OPC[k].o.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </Select>
              ))}
            </div>
          )}
        </div>
      ))}
    </div>
  );
}

function CasoDrawer({ id, onClose, onModelo, onProc, emUso }) {
  const { Drawer, Button } = ds();
  const { FONTES, PROCESSOS, ESTADOS, MODELOS, casoPorId } = F();
  const c = id && casoPorId(id);
  const usam = c ? MODELOS.filter((m) => m.casos.includes(c.id)) : [];
  return (
    <Drawer open={!!c} onClose={onClose} width={520} title={c ? c.t : ""} subtitle={c ? c.id + " · " + PROCESSOS[c.proc] : ""}
      footer={c && <><Button variant="ghost" onClick={onClose}>Fechar</Button><Button onClick={() => onProc(DOM_PROC[c.proc] || "venda")}>Ver o processo</Button></>}>
      {c && <div className="flx-det">
        <section className="flx-proc"><h4>Regra</h4>
          <ul className="flx-regras"><li><span className="em">Ao entrar em <b>{c.etapa}</b></span><span>{c.regra}</span></li></ul>
        </section>
        <section className="flx-proc"><h4>No oimpresso</h4>
          <div className="flx-est-l"><Pill tone={ESTADOS[c.estado].tone}>{ESTADOS[c.estado].l}</Pill>{c.us && <span className="flx-dim">{c.us}</span>}<span className="flx-dim">· a partir do porte {nPorte(c.porte).toLowerCase()}</span></div>
        </section>
        <section className="flx-proc"><h4>Quem anuncia <span className="flx-dim">{c.fontes.length}</span></h4>
          <ul className="flx-fontes">{c.fontes.map((f) => <li key={f}>{FONTES[f].url ? <a href={FONTES[f].url} target="_blank" rel="noopener noreferrer">{FONTES[f].n}</a> : <span>{FONTES[f].n} (proposta própria)</span>}</li>)}</ul>
        </section>
        <section className="flx-proc"><h4>Modelos que usam <span className="flx-dim">{usam.length}</span></h4>
          {usam.length ? <div className="flp-liga">{usam.map((m) => <button key={m.id} type="button" className="flp-chip" onClick={() => onModelo(m.id)}>{m.n}{emUso && emUso.id === m.id ? " · em uso" : ""}</button>)}</div>
            : <p className="flx-nota">Nenhum modelo usa este caso ainda.</p>}
        </section>
      </div>}
    </Drawer>
  );
}

function FluxosPage() {
  const m = ds();
  const faltam = NEC.filter((n) => !m[n]);
  if (!window.FLX || faltam.length) return <div className="flx-root" role="status" style={{ padding: 20 }}>Carregando Fluxos e regras…</div>;
  return <FluxosConteudo />;
}

function FluxosConteudo() {
  const { PageHeader, TabBar, Button, Widget, Segmented, Select, Drawer, Modal, DataGrid, EmptyState, Alert } = ds();
  const { MODELOS, CASOS, PORTES, ATIVIDADES, PROCESSOS, ESTADOS, FONTES, encaixe } = F();
  const [aba, setAbaS] = useState(() => ler(K.aba, "modelos"));
  const setAba = (v) => { setAbaS(v); gravar(K.aba, v); };
  const [perfil, setPerfilS] = useState(() => ler(K.perfil, { porte: "pequena", ativ: "cv" }));
  const setPerfil = (p) => { setPerfilS(p); gravar(K.perfil, p); };
  const [emUso, setEmUso] = useState(() => ler(K.modelo, null));
  const [aberto, setAberto] = useState(null);
  const [confirma, setConfirma] = useState(null);
  const [aviso, setAviso] = useState(null);
  const [fProc, setFProc] = useState("todos"), [fFonte, setFFonte] = useState("todas"), [fEst, setFEst] = useState("todos"), [q, setQ] = useState("");
  const [caso, setCaso] = useState(null);
  const [procSel, setProcSelS] = useState(() => ler("oimpresso.fluxos.proc2", "producao"));
  const setProcSel = (v) => { setProcSelS(v); gravar("oimpresso.fluxos.proc2", v); };
  const irProc = (id) => { setAberto(null); setCaso(null); setProcSel(id); setAba("processos"); };
  const irCaso = (id) => { setAberto(null); setCaso(id); };
  const irModelo = (id) => { setCaso(null); setAberto(id); };
  const filtrosAtivos = (fProc !== "todos") + (fFonte !== "todas") + (fEst !== "todos") + (q.trim() ? 1 : 0);
  const limpar = () => { setFProc("todos"); setFFonte("todas"); setFEst("todos"); setQ(""); };

  const ordenados = useMemo(() => MODELOS.slice().sort((a, b) => encaixe(b, perfil) - encaixe(a, perfil)), [perfil]);
  const bons = ordenados.filter((x) => encaixe(x, perfil) >= 2), outros = ordenados.filter((x) => encaixe(x, perfil) < 2);
  const mAberto = MODELOS.find((x) => x.id === aberto), mUso = emUso && MODELOS.find((x) => x.id === emUso.id), mConf = MODELOS.find((x) => x.id === confirma);

  const aplicar = (mod) => {
    const v = { id: mod.id, porte: perfil.porte, ativ: perfil.ativ, em: new Date().toLocaleDateString("pt-BR") };
    gravar(K.modelo, v); setEmUso(v);
    if (window.OiFsmRegras && mod.compra) window.OiFsmRegras.salvar("compra", "insumo.custo", { ativo: true, params: mod.compra });
    setConfirma(null); setAberto(null); setAviso(mod.n); setAba("empresa");
  };

  const qn = q.trim().toLowerCase();
  const casosF = CASOS.filter((c) => (!qn || (c.id + " " + c.t + " " + c.regra + " " + c.etapa + " " + nFontes(c.fontes)).toLowerCase().includes(qn)) && (fProc === "todos" || c.proc === fProc) && (fFonte === "todas" || c.fontes.includes(fFonte)) && (fEst === "todos" || c.estado === fEst));
  const usoEm = (id) => MODELOS.filter((x) => x.casos.includes(id)).length;
  const COLS = [
    { key: "id", label: "Caso", mono: true, width: 96 },
    { key: "t", label: "O que faz" },
    { key: "proc", label: "Processo" },
    { key: "fontes", label: "Quem anuncia" },
    { key: "porte", label: "A partir de" },
    { key: "mod", label: "Modelos", align: "right", mono: true },
    { key: "estado", label: "No oimpresso" },
  ];

  return (
    <div className="flx-root" data-screen-label={"Fluxos e regras · " + (ABAS.find((a) => a.key === aba) || {}).label}>
      <PageHeader title="Fluxos e regras"
        stats={[{ value: MODELOS.length, label: "modelos" }, { value: CASOS.length, label: "casos de uso" }, { value: Object.keys(FONTES).length - 1, label: "sistemas pesquisados" }]}
        actions={mUso ? <span className="flx-dim">Em uso: <b>{mUso.n}</b></span> : null} />
      <TabBar tabs={ABAS.map((a) => ({ ...a, count: a.key === "casos" ? CASOS.length : a.key === "modelos" ? MODELOS.length : a.key === "processos" && window.FLX_PROC ? window.FLX_PROC.PROCS.length : undefined }))} active={aba} onChange={setAba} inset={20} ariaLabel="Seções de fluxos" />

      <div className="flx-body">
        {aba === "modelos" && (
          <>
            <div className="flx-perfil">
              <div className="flx-perfil-c"><span className="flx-lbl">Porte da empresa</span>
                <Segmented size="sm" ariaLabel="Porte da empresa" value={perfil.porte} onChange={(v) => setPerfil({ ...perfil, porte: v })} options={PORTES.map((p) => ({ value: p.v, label: p.l }))} />
                <span className="flx-dim">{PORTES.find((p) => p.v === perfil.porte).d}</span>
              </div>
              <div className="flx-perfil-c flx-perfil-sel">
                <Select label="Atividade principal" value={perfil.ativ} onChange={(e) => setPerfil({ ...perfil, ativ: e.target.value })}>
                  {ATIVIDADES.map((a) => <option key={a.v} value={a.v}>{a.l}</option>)}
                </Select>
              </div>
            </div>
            <Assistente perfil={perfil} onVer={setAberto} onPerfil={setPerfil} />
            <div className="flx-sec"><span>Para {nPorte(perfil.porte).toLowerCase()} · {nAtiv(perfil.ativ).toLowerCase()}</span><i></i></div>
            {bons.length ? <div className="flx-grid">{bons.map((x) => <ModeloCard key={x.id} m={x} perfil={perfil} emUso={emUso && emUso.id === x.id} onOpen={() => setAberto(x.id)} />)}</div>
              : <EmptyState variant="no-results" title="Nenhum modelo feito para este perfil" description="Os modelos abaixo servem de ponto de partida; ajuste as etapas depois de escolher." />}
            {outros.length > 0 && <>
              <div className="flx-sec"><span>Outros modelos</span><i></i></div>
              <div className="flx-grid">{outros.map((x) => <ModeloCard key={x.id} m={x} perfil={perfil} emUso={emUso && emUso.id === x.id} onOpen={() => setAberto(x.id)} />)}</div>
            </>}
          </>
        )}

        {aba === "processos" && (window.FluxosProcessos ? <window.FluxosProcessos sel={procSel} onSel={setProcSel} /> : <div role="status" className="flx-dim">Carregando processos…</div>)}

        {aba === "empresa" && (
          !mUso ? (
            <EmptyState variant="first" icon={ds().RegistrationMark ? React.createElement(ds().RegistrationMark, { size: 20 }) : undefined} title="Nenhum modelo escolhido" description="Escolha um modelo pelo porte e pela atividade da empresa. As etapas e regras de cada processo vêm dele."
              action={<Button size="sm" variant="primary" onClick={() => setAba("modelos")}>Ver modelos</Button>} />
          ) : (
            <>
              {aviso && <Alert tone="success" title={"Modelo " + aviso + " aplicado"} onClose={() => setAviso(null)}>Documentos em andamento continuam na etapa em que estavam; as regras novas valem a partir da próxima transição.</Alert>}
              <div className="flx-uso">
                <div><span className="flx-lbl">Modelo em uso</span><b>{mUso.n}</b><span className="flx-dim">Escolhido em {emUso.em} para {nPorte(emUso.porte).toLowerCase()} · {nAtiv(emUso.ativ).toLowerCase()}</span></div>
                <Button size="sm" onClick={() => setAba("modelos")}>Trocar modelo</Button>
              </div>
              <div className="flx-procs">
                {mUso.processos.map((p) => (
                  <Widget key={p.dom} title={PROCESSOS[p.dom]} note={p.etapas.length + " etapas · " + p.regras.length + (p.regras.length === 1 ? " regra" : " regras")}
                    badge={p.dom === "compra" ? <Pill tone="accent">configurável</Pill> : null}>
                    <Cadeia etapas={p.etapas} marcadas={new Set(p.regras.map((r) => r[0]))} />
                    {p.dom === "compra" && window.OiFsmRegras ? <CompraRegras /> : <RegrasLiga m={mUso} p={p} onCaso={irCaso} />}
                    <TelasDoProc dom={p.dom} />
                  </Widget>
                ))}
              </div>
            </>
          )
        )}

        {aba === "casos" && (
          <>
            <p className="flx-intro">Clique num caso para ver as fontes e os modelos que o usam. Cada caso é uma transição com efeito — <i>ao entrar na etapa, o sistema faz</i>. Levantado no que os concorrentes <b>anunciam</b> em páginas públicas; nada foi testado em demonstração. Ausência de fonte quer dizer “não anunciado”, não “não tem”.</p>
            <div className="flx-filtros">
              <label className="flx-busca"><span className="flx-lbl">Buscar</span><input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="caso, etapa, efeito ou concorrente" aria-label="Buscar caso de uso" /></label>
              <Select label="Processo" value={fProc} onChange={(e) => setFProc(e.target.value)}>
                <option value="todos">Todos</option>{Object.keys(PROCESSOS).map((k) => <option key={k} value={k}>{PROCESSOS[k]}</option>)}
              </Select>
              <Select label="Quem anuncia" value={fFonte} onChange={(e) => setFFonte(e.target.value)}>
                <option value="todas">Todos</option>{Object.keys(FONTES).map((k) => <option key={k} value={k}>{FONTES[k].n}</option>)}
              </Select>
              <Select label="No oimpresso" value={fEst} onChange={(e) => setFEst(e.target.value)}>
                <option value="todos">Todos</option>{Object.keys(ESTADOS).map((k) => <option key={k} value={k}>{ESTADOS[k].l}</option>)}
              </Select>
              <div className="flx-filtros-r" aria-live="polite"><span className="flx-dim">{casosF.length} de {CASOS.length} casos</span>{filtrosAtivos > 0 && <Button size="sm" variant="ghost" onClick={limpar}>Limpar filtros ({filtrosAtivos})</Button>}</div>
            </div>
            {casosF.length === 0 ? <EmptyState variant="filtered" title="Nenhum caso com esses filtros" description="Troque o processo, a fonte ou o estado." action={<Button size="sm" onClick={limpar}>Limpar filtros</Button>} /> : (
              <div className="flx-gridwrap">
                <DataGrid caption="Casos de uso" totalLabel="casos" columns={COLS}
                  rows={casosF.map((c) => ({ id: c.id, cells: {
                    id: c.id,
                    t: { primary: c.t, sub: "Ao entrar em " + c.etapa + " — " + c.regra },
                    proc: PROCESSOS[c.proc],
                    fontes: nFontes(c.fontes),
                    porte: nPorte(c.porte),
                    mod: usoEm(c.id),
                    estado: <span className="flx-est"><Pill tone={ESTADOS[c.estado].tone}>{ESTADOS[c.estado].l}</Pill>{c.us && <small>{c.us}</small>}</span>,
                  } }))}
                  onRowClick={(row) => setCaso(row.id)}
                  page={1} pageSize={50} pageSizeOptions={[50]} onPageChange={() => {}} />
              </div>
            )}
            <div className="flx-sec"><span>Fontes</span><i></i></div>
            <ul className="flx-fontes">
              {Object.keys(FONTES).filter((k) => FONTES[k].url).map((k) => (
                <li key={k}><a href={FONTES[k].url} target="_blank" rel="noopener noreferrer">{FONTES[k].n}</a><span>{CASOS.filter((c) => c.fontes.includes(k)).length} casos</span></li>
              ))}
            </ul>
            <p className="flx-nota">Pesquisa base: memory/research/2026-05-prospeccao/02 e 33 (main, lidos em 06/10/2026) + páginas públicas da Holdprint. Documento: fluxos-modelos.casos.md.</p>
          </>
        )}
      </div>

      <Drawer open={!!mAberto} onClose={() => setAberto(null)} width={620} title={mAberto ? mAberto.n : ""}
        subtitle={mAberto ? (() => { const [r] = encaixeRot(encaixe(mAberto, perfil)); return r + " para " + nPorte(perfil.porte).toLowerCase() + " · " + nAtiv(perfil.ativ).toLowerCase(); })() : ""}
        footer={mAberto && <>
          <Button variant="ghost" onClick={() => setAberto(null)}>Fechar</Button>
          <Button variant="primary" disabled={emUso && emUso.id === mAberto.id} onClick={() => setConfirma(mAberto.id)}>{emUso && emUso.id === mAberto.id ? "Modelo em uso" : "Usar este modelo"}</Button>
        </>}>
        {mAberto && <ModeloDetalhe m={mAberto} emUso={mUso} onProc={irProc} onCaso={irCaso} />}
      </Drawer>

      <CasoDrawer id={caso} onClose={() => setCaso(null)} onModelo={irModelo} onProc={irProc} emUso={mUso} />

      <Modal open={!!mConf} onClose={() => setConfirma(null)} title={mConf ? "Usar " + mConf.n + "?" : ""} width={460}
        footer={mConf && <>
          <Button variant="ghost" onClick={() => setConfirma(null)}>Cancelar</Button>
          <Button variant="primary" onClick={() => aplicar(mConf)}>Usar modelo</Button>
        </>}>
        {mConf && <div className="flx-conf">
          <p>As etapas e regras de {mConf.processos.map((p) => PROCESSOS[p.dom]).join(", ")} passam a seguir este modelo.</p>
          <p>Documentos em andamento continuam na etapa em que estão.</p>
          {mConf.compra && <p><b>Compra:</b> {resumoCompra(mConf.compra)}</p>}
          {mUso && (() => { const d = diferenca(mUso, mConf); return <p className="flx-dim">Substitui {mUso.n}{d ? " · casos de uso +" + d.casosMais.length + " / −" + d.casosMenos.length + (d.saem.length ? " · deixa de ter " + d.saem.map((x) => PROCESSOS[x]).join(", ") : "") : ""}.</p>; })()}
        </div>}
      </Modal>
    </div>
  );
}

window.FluxosPage = FluxosPage;
})();
