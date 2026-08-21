// cms-page.jsx — Módulo Site (CMS). Espelho F1 do Modules/Cms do git
// (CmsPageController + SettingsController + CmsPage/CmsPageMeta/CmsSiteDetail).
// Rotas reais: /cms/cms-page?type=page|blog|testimonial e /cms/site-details.
// Fiel ao domínio: title · content (HTML) · meta_description · tags · priority ·
// feature_image · is_enabled · layout (home/contact = página de sistema, sem excluir)
// e o page_meta (blocos feature/industry só na home). Detalhes do site = as 8 abas
// do settings/index.blade (aplicação, contato, redes, estatísticas, FAQ, chat,
// integrações, botões) com os mesmos campos.
// Copy em PT-BR — o seed do repo vem em inglês e não serve como UI cliente-facing.
// Expõe window.CmsPage.
(() => {
const { useState, useMemo } = React;
const { Kpis, Kpi, Sw, Nota, Vazio, Confirm, Bulk, Chk } = window.AcessosDS;

// HTML colado de fora pode trazer script/evento/iframe — o site sanitiza antes de publicar.
// Aqui a gente mostra o que seria removido, em vez de remover em silêncio.
function riscos(html) {
  const r = [];
  if (/<script/i.test(html)) r.push("tag <script>");
  if (/\son\w+\s*=/i.test(html)) r.push("atributo de evento (onclick e afins)");
  if (/<iframe/i.test(html)) r.push("tag <iframe>");
  if (/style\s*=\s*"[^"]*expression/i.test(html)) r.push("expressão em style");
  return r;
}

const slugify = (t) => t.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9\s-]/g, "").trim().replace(/\s+/g, "-");
const initials = (n) => n.split(/\s+/).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
const avColor = (n) => { const h = [...n].reduce((a, c) => a + c.charCodeAt(0), 0) % 360; return { bg: `oklch(0.92 0.04 ${h})`, fg: `oklch(0.42 0.13 ${h})` }; };

const LAYOUT_LABEL = { home: "Home", contact: "Contato", pricing: "Planos" };

// ── Mock: conteúdo do site oimpresso.com (voz de marketing do DS) ──
const PAGES = [
  { id: 3, type: "page", layout: "home", prio: 1, on: true, upd: "12/08/2026 14:20", by: "Wagner Rocha",
    title: "O ERP pra quem orça, imprime, monta e entrega",
    meta: "Cálculo automático por m², ordem de produção em tempo real e fechamento fiscal sem retrabalho. PDV, NF-e, estoque, ponto, financeiro e BI em uma plataforma só.",
    tags: "erp, comunicação visual, gráfica, pdv, nf-e",
    content: "<p>Melhor plataforma de gestão pra gráfica, comunicação visual e oficina — do orçamento à entrega.</p><ul><li>Cálculo por m² automático</li><li>Ordem de produção em tempo real</li><li>Fechamento fiscal sem retrabalho</li></ul>",
    blocos: { feature: { title: "Oito módulos. Uma plataforma.", itens: [
      { i: "cloud", t: "Acesse de onde estiver", d: "Abre no navegador da loja, do escritório ou do celular — sempre o mesmo dado." },
      { i: "ruler", t: "Cálculo por m²", d: "Insumo, perda de material, acabamento e hora-máquina na mesma fórmula." },
      { i: "factory", t: "Produção em tempo real", d: "A OS anda de etapa e o balcão vê na hora em que etapa está." },
      { i: "receipt", t: "Fiscal sem retrabalho", d: "A nota sai da venda com o imposto já apurado." },
      { i: "cubes", t: "Estoque por local", d: "Cada loja com o próprio saldo, o mesmo cadastro de produto." },
      { i: "chart", t: "Relatórios que decidem", d: "Margem por serviço, cliente que dá lucro, máquina que está parada." },
    ] }, industry: { title: "Feito pro seu segmento", itens: [
      { i: "print", t: "Comunicação visual", d: "Banner, lona, adesivo, fachada e ACM — com cota e área na OS." },
      { i: "store", t: "Gráfica rápida", d: "Balcão de alto giro: orçamento, PDV e entrega no mesmo caminho." },
      { i: "wrench", t: "Oficina", d: "Vistoria com foto, peças e mão de obra na mesma ordem de serviço." },
    ] } } },
  { id: 4, type: "page", layout: "contact", prio: 2, on: true, upd: "04/08/2026 09:12", by: "Wagner Rocha",
    title: "Fale com a gente",
    meta: "Suporte humano em português. Responda este formulário e a gente retorna em até um dia útil.",
    tags: "contato, suporte",
    content: "<p>A gente responde rápido. Conte o que você precisa resolver na sua operação e em qual etapa está hoje.</p>" },
  { id: 8, type: "page", layout: "pricing", prio: 3, on: true, upd: "11/08/2026 17:45", by: "Wagner Rocha",
    title: "Planos e preços",
    meta: "Preços em reais. Não cobramos setup nem fidelidade. Cancele quando quiser.",
    tags: "preços, planos, assinatura",
    content: "<p>Preços em reais (R$). Não cobramos setup nem fidelidade. Cancele quando quiser.</p>" },
  { id: 11, type: "page", layout: null, prio: 4, on: true, upd: "22/07/2026 11:03", by: "Eliana Martins",
    title: "Sobre a Office Impresso", meta: "", tags: "institucional",
    content: "<p>Nascemos dentro de uma gráfica. Cada tela do sistema saiu de um problema de balcão, de produção ou de fechamento.</p>" },
  { id: 12, type: "page", layout: null, prio: 5, on: true, upd: "30/06/2026 16:40", by: "Eliana Martins",
    title: "Política de privacidade",
    meta: "Como tratamos dados pessoais no oimpresso — bases legais, retenção e seus direitos (LGPD Art. 7º e Art. 18).",
    tags: "lgpd, privacidade",
    content: "<p>Tratamos dados pessoais com base no legítimo interesse e na execução de contrato (LGPD Art. 7º). Você pode pedir acesso, correção ou eliminação a qualquer momento.</p>" },
  { id: 13, type: "page", layout: null, prio: 6, on: false, upd: "18/08/2026 10:07", by: "Wagner Rocha",
    title: "Termos de uso", meta: "", tags: "",
    content: "<p>Rascunho — revisar com o jurídico antes de publicar.</p>" },
];

const BLOG = [
  { id: 21, type: "blog", layout: null, prio: 1, on: true, upd: "15/08/2026 08:30", by: "Wagner Rocha",
    title: "Como calcular preço por m² sem perder margem",
    meta: "Lona, adesivo e ACM têm perda de material diferente. Veja como montar a fórmula do m² com insumo, acabamento e hora-máquina.",
    tags: "precificação, m², comunicação visual",
    content: "<p>Quem cobra o m² no chute perde nos dois lados: caro demais espanta o cliente, barato demais come a margem no acabamento.</p>" },
  { id: 22, type: "blog", layout: null, prio: 2, on: true, upd: "05/08/2026 13:55", by: "Larissa Souza",
    title: "Balcão sem fila: o atendimento em três telas",
    meta: "O caminho do orçamento à OS em menos de dois minutos, direto do balcão.",
    tags: "balcão, atendimento, orçamento",
    content: "<p>No balcão, cada clique conta. O caminho é orçamento, aprovação e OS — sem sair da mesma tela.</p>" },
  { id: 23, type: "blog", layout: null, prio: 3, on: true, upd: "28/07/2026 09:10", by: "Eliana Martins",
    title: "Fechamento fiscal do mês em uma tarde", meta: "", tags: "fiscal, nf-e",
    content: "<p>Se a nota sai da venda e a venda sai da OS, o fechamento deixa de ser arqueologia.</p>" },
  { id: 24, type: "blog", layout: null, prio: 4, on: false, upd: "17/08/2026 15:22", by: "Wagner Rocha",
    title: "Oficina auto: da recepção ao pronto sem papel", meta: "", tags: "oficina, os",
    content: "<p>Rascunho — falta a foto da vistoria digital.</p>" },
];

const DEPOIMENTOS = [
  { id: 31, type: "testimonial", layout: null, prio: 1, on: true, upd: "10/08/2026 10:00", by: "Wagner Rocha",
    title: "Larissa Souza", papel: "ROTA LIVRE · Balcão", meta: "", tags: "",
    content: "<p>Antes eu tinha caderno, planilha e WhatsApp. Agora o orçamento vira OS num clique e o cliente acompanha sozinho.</p>" },
  { id: 32, type: "testimonial", layout: null, prio: 2, on: true, upd: "10/08/2026 10:02", by: "Wagner Rocha",
    title: "Martinho Ferreira", papel: "Oficina Martinho · Proprietário", meta: "", tags: "",
    content: "<p>A vistoria com foto acabou com discussão na entrega. O cliente vê o que entrou e o que saiu.</p>" },
  { id: 33, type: "testimonial", layout: null, prio: 3, on: true, upd: "12/08/2026 08:45", by: "Eliana Martins",
    title: "Eliana Martins", papel: "WR2 · Financeiro", meta: "", tags: "",
    content: "<p>Concilio o dia em vinte minutos. O que sobrava de retrabalho no fim do mês simplesmente não aparece mais.</p>" },
  { id: 34, type: "testimonial", layout: null, prio: 4, on: false, upd: "18/08/2026 09:30", by: "Wagner Rocha",
    title: "Rafael Lima", papel: "ROTA LIVRE · Produção", meta: "", tags: "",
    content: "<p>Rascunho — pedir autorização de uso do nome.</p>" },
];

const TIPOS = {
  paginas: { key: "page", label: "Páginas", rows: PAGES },
  blog: { key: "blog", label: "Blog", rows: BLOG },
  depoimentos: { key: "testimonial", label: "Depoimentos", rows: DEPOIMENTOS },
};

// ── Endereço público ──
function endereco(p) {
  if (p.type === "blog") return `/c/blog/${slugify(p.title)}-${p.id}`;
  if (p.layout === "home") return "/";
  if (p.layout === "contact") return "/c/contact-us";
  return `/c/page/${slugify(p.title)}`;
}

function Situacao({ on }) {
  return <span className={`cms-pill ${on ? "on" : "off"}`}>{on ? "Publicada" : "Rascunho"}</span>;
}

// Histórico — o Model já grava com o activitylog (LGPD Art. 37/38)
const HIST = {
  3: [["12/08/2026 14:20", "Wagner Rocha", "trocou a chamada principal"], ["11/08/2026 09:04", "Wagner Rocha", "editou o bloco de recursos"], ["20/07/2026 16:12", "Eliana Martins", "publicou a página"]],
  13: [["18/08/2026 10:07", "Wagner Rocha", "tirou do ar para revisão"], ["18/08/2026 09:58", "Wagner Rocha", "criou a página"]],
  24: [["17/08/2026 15:22", "Wagner Rocha", "salvou como rascunho"]],
};

// ── Drawer de edição (PT-02) ──
function EditorDrawer({ p, tipo, onClose, onExcluir }) {
  const [f, setF] = useState({ title: p.title, content: p.content, meta: p.meta || "", tags: p.tags || "", prio: p.prio || "", on: p.on });
  const [modo, setModo] = useState("visual");
  const [largura, setLargura] = useState("desktop");
  const [blocoAberto, setBlocoAberto] = useState(null);
  const [salvo, setSalvo] = useState(false);
  const set = (k) => (e) => { setF((s) => ({ ...s, [k]: e.target.value })); setSalvo(false); };
  const sistema = !!p.layout;
  const metaLen = f.meta.length;
  const metaTone = metaLen === 0 ? "bad" : metaLen > 160 ? "bad" : metaLen < 80 ? "warn" : "";
  const tituloLabel = tipo === "depoimentos" ? "Nome de quem depõe" : "Título";
  const corpoLabel = tipo === "depoimentos" ? "Depoimento" : sistema ? "Descrição" : "Conteúdo";

  return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <div className="os-drawer wide cms-drawer" data-screen-label="Site (CMS) · Editar">
        <div className="os-drawer-head">
          <div className="os-drawer-head-l">
            <div className="os-drawer-id">{TIPOS[tipo].label} · #{p.id}</div>
            <h2>{f.title || "Sem título"}</h2>
            <p><span className="cms-slug">{endereco({ ...p, title: f.title })}</span> · atualizada {p.upd} por {p.by}</p>
          </div>
          <div className="os-drawer-head-r">
            <Situacao on={f.on} />
            <button className="os-btn ghost" onClick={onClose}>Fechar</button>
          </div>
        </div>

        <div className="os-drawer-body">
          {sistema &&
          <div className="os-drawer-section">
            <Nota tone="info" title={`Página de sistema (${LAYOUT_LABEL[p.layout]})`}>
              O layout vem do tema do site — aqui você troca o texto, não a estrutura. Páginas de sistema não podem ser excluídas.
            </Nota>
          </div>}

          <div className="os-drawer-section">
            <h3>Conteúdo</h3>
            <div className="cms-f">
              <label>{tituloLabel}</label>
              <input type="text" value={f.title} onChange={set("title")} />
              {tipo !== "depoimentos" && (f.title !== p.title && p.id !== "novo"
                ? <small className="help cms-help-warn">O endereço vai mudar para <span className="cms-slug">{endereco({ ...p, title: f.title })}</span> — quem tem o link antigo cai em página não encontrada.</small>
                : <small className="help">O endereço público sai do título — mudar o título muda o link.</small>)}
            </div>
            <div className="cms-f">
              <label>{corpoLabel}</label>
              <div className="cms-ed-bar">
                <div className="cms-ed-seg">
                  <button className={modo === "visual" ? "active" : ""} onClick={() => setModo("visual")}>Prévia</button>
                  <button className={modo === "codigo" ? "active" : ""} onClick={() => setModo("codigo")}>HTML</button>
                </div>
                {modo === "visual" &&
                <div className="cms-ed-seg">
                  <button className={largura === "desktop" ? "active" : ""} onClick={() => setLargura("desktop")}>Computador</button>
                  <button className={largura === "celular" ? "active" : ""} onClick={() => setLargura("celular")}>Celular</button>
                </div>}
                <span className="cms-ed-note">{f.content.replace(/<[^>]+>/g, "").length} caracteres</span>
              </div>
              {modo === "codigo"
                ? <textarea className="cms-ed-code" value={f.content} onChange={set("content")} />
                : <div className={`cms-ed-prev ${largura === "celular" ? "mob" : ""}`} dangerouslySetInnerHTML={{ __html: f.content }} />}
              {riscos(f.content).length > 0
                ? <div style={{ marginTop: 8 }}>
                    <Nota tone="warn" title="Isto não vai pro site">
                      A publicação remove: {riscos(f.content).join(", ")}. O resto do conteúdo sai igual — se você precisa de código no site, use Detalhes do site → Integrações.
                    </Nota>
                  </div>
                : <small className="help">Texto, listas, links e imagens passam direto. Script, iframe e atributo de evento são removidos na publicação.</small>}
            </div>
          </div>

          {tipo !== "depoimentos" &&
          <div className="os-drawer-section">
            <h3>Busca e compartilhamento</h3>
            <div className="cms-f">
              <label>Descrição para busca (meta description)</label>
              <textarea rows="3" value={f.meta} onChange={set("meta")} />
              <div className={`cms-meter ${metaTone}`}><i style={{ width: Math.min(100, metaLen / 160 * 100) + "%" }} /></div>
              <small className="help">{metaLen} de 160 caracteres{metaLen === 0 ? " — sem descrição, o Google recorta o começo do conteúdo." : metaLen > 160 ? " — passou do limite, o final será cortado." : ""}</small>
            </div>
            <div className="cms-f">
              <label>Palavras-chave (tags)</label>
              <input type="text" value={f.tags} onChange={set("tags")} placeholder="separe por vírgula" />
              {f.tags.trim() !== "" &&
              <div className="cms-tags">{f.tags.split(",").map((t) => t.trim()).filter(Boolean).map((t, i) => <span key={i} className="cms-tag">{t}</span>)}</div>}
            </div>
          </div>}

          {p.blocos &&
          <div className="os-drawer-section">
            <h3>Blocos da home</h3>
            <div className="cms-blocks">
              {[["feature", "Bloco de recursos"], ["industry", "Bloco de segmentos"]].map(([k, l]) => {
                const b = p.blocos[k]; const aberto = blocoAberto === k;
                return (
                  <div key={k}>
                    <div className="cms-block">
                      <div><b>{b.title}</b><small>{l}</small></div>
                      <span className="cms-prio">{b.itens.length} itens</span>
                      <button className="os-btn sm" onClick={() => setBlocoAberto(aberto ? null : k)}>{aberto ? "Recolher" : "Editar itens"}</button>
                    </div>
                    {aberto &&
                    <div className="cms-bloco-itens">
                      {b.itens.map((it, i) => (
                        <div key={i} className="cms-bloco-item">
                          <span className="cms-bloco-n">{String(i + 1).padStart(2, "0")}</span>
                          <div className="cms-f" style={{ margin: 0 }}>
                            <input type="text" defaultValue={it.t} onChange={() => setSalvo(false)} />
                            <textarea rows="2" defaultValue={it.d} onChange={() => setSalvo(false)} />
                          </div>
                          <span className="cms-tag">{it.i}</span>
                        </div>
                      ))}
                      <button className="os-btn sm">Acrescentar item</button>
                    </div>}
                  </div>
                );
              })}
            </div>
            <small className="help cms-help-b">Cada item tem ícone, título e descrição — só a home usa esses blocos.</small>
          </div>}

          <div className="os-drawer-section">
            <h3>Publicação</h3>
            <div className="cms-f-row">
              <div className="cms-f">
                <label>Ordem de exibição</label>
                <input type="number" value={f.prio} onChange={set("prio")} />
                <small className="help">Menor primeiro. Vazio joga pro fim da lista.</small>
              </div>
              <div className="cms-f">
                <label>{tipo === "depoimentos" ? "Foto de quem depõe" : "Imagem de destaque"}</label>
                <div className="cms-img">
                  <div className="cms-img-ph">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="9" cy="10" r="2" /><path d="m4 18 5-4 4 3 3-2 4 3" /></svg>
                  </div>
                  <div>
                    <button className="os-btn sm">Escolher arquivo</button>
                    <small>JPG ou PNG, até 5 MB.</small>
                  </div>
                </div>
              </div>
            </div>
            <Sw on={f.on} onToggle={() => { setF((s) => ({ ...s, on: !s.on })); setSalvo(false); }}
              label={f.on ? "Visível no site" : "Fora do ar"}
              sub={f.on ? "Qualquer pessoa com o link consegue abrir." : "Fica salva aqui, mas não aparece no site."} />
          </div>

          {HIST[p.id] &&
          <div className="os-drawer-section">
            <h3>Histórico</h3>
            <ul className="cms-hist">
              {HIST[p.id].map(([q, quem, oq], i) => (
                <li key={i}><span className="cms-prio">{q}</span><b>{quem}</b> {oq}</li>
              ))}
            </ul>
          </div>}
        </div>

        <div className="os-drawer-actions">
          <button className="os-btn ghost danger" disabled={sistema} title={sistema ? "Página de sistema — não pode ser excluída" : ""} onClick={() => onExcluir(p)}>Excluir</button>
          <span style={{ marginLeft: "auto", fontSize: 11.5, color: "var(--text-mute)" }}>{salvo ? "Alterações salvas." : ""}</span>
          <button className="os-btn" onClick={() => window.open(endereco({ ...p, title: f.title }), "_blank")}>Ver no site</button>
          <button className="os-btn primary" onClick={() => setSalvo(true)}>Salvar</button>
        </div>
      </div>
    </>
  );
}

// ── Lista (páginas e blog) — ordem por arrasto, seleção múltipla e filtros ──
function Lista({ tipo, rows, onOpen }) {
  const [q, setQ] = useState("");
  const [fSit, setFSit] = useState("all");
  const [fLay, setFLay] = useState("all");
  const [ordem, setOrdem] = useState(() => rows.map((p) => p.id));
  const [sel, setSel] = useState([]);
  const [drag, setDrag] = useState(null);
  const [alvo, setAlvo] = useState(null);
  const [aviso, setAviso] = useState("");

  const ordenadas = ordem.map((id) => rows.find((p) => p.id === id)).filter(Boolean);
  const filtered = useMemo(() => {
    const t = q.trim().toLowerCase();
    return ordenadas.filter((p) =>
      (fSit === "all" || (fSit === "on" ? p.on : !p.on)) &&
      (fLay === "all" || (fLay === "sys" ? !!p.layout : !p.layout)) &&
      (!t || (p.title + " " + (p.tags || "") + " " + endereco(p)).toLowerCase().includes(t))
    );
  }, [q, fSit, fLay, ordem, rows]);

  const filtrando = q.trim() !== "" || fSit !== "all" || fLay !== "all";
  const toggle = (id) => setSel((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);
  const soltar = (destId) => {
    if (drag == null || drag === destId) { setDrag(null); setAlvo(null); return; }
    setOrdem((o) => {
      const sem = o.filter((x) => x !== drag);
      const i = sem.indexOf(destId);
      return [...sem.slice(0, i), drag, ...sem.slice(i)];
    });
    setAviso("Ordem salva. É essa a sequência que aparece no site.");
    setDrag(null); setAlvo(null);
  };

  return (
    <>
      <div className="cms-toolbar">
        <div className="cms-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por título, endereço ou palavra-chave…" />
        </div>
        <div className="cms-chips">
          {[["all", "Todas"], ["on", "No ar"], ["off", "Rascunhos"]].map(([k, l]) => (
            <button key={k} className={`cms-chip ${fSit === k ? "active" : ""}`} onClick={() => setFSit(k)}>{l}</button>
          ))}
          {tipo === "paginas" &&
          <span className="cms-chips-sep"></span>}
          {tipo === "paginas" && [["all", "Todo layout"], ["sys", "De sistema"], ["free", "Livres"]].map(([k, l]) => (
            <button key={k} className={`cms-chip ${fLay === k ? "active" : ""}`} onClick={() => setFLay(k)}>{l}</button>
          ))}
          {filtrando && <button className="cms-chip clear" onClick={() => { setQ(""); setFSit("all"); setFLay("all"); }}>Limpar</button>}
        </div>
        <span className="cms-count">{filtered.length} de {rows.length}</span>
      </div>
      {aviso &&
      <div className="cms-toolbar" style={{ paddingTop: 8 }}>
        <Nota tone="success" title="Ordem atualizada">{aviso}</Nota>
      </div>}
      <div className="os-table-wrap">
        <table className="os-table cms-table">
          <thead><tr>
            <th className="cms-th-chk"></th>
            <th className="cms-th-grip"></th>
            <th>{tipo === "blog" ? "Publicação" : "Página"}</th>
            <th>Endereço</th>
            {tipo === "paginas" && <th>Layout</th>}
            <th>Busca</th>
            <th>Situação</th>
            <th>Atualizada</th>
          </tr></thead>
          <tbody>
            {filtered.map((p) => (
              <tr key={p.id}
                className={`os-row ${sel.includes(p.id) ? "sel" : ""} ${drag === p.id ? "dragging" : ""} ${alvo === p.id ? "dropzone" : ""}`}
                draggable={!filtrando}
                onDragStart={() => setDrag(p.id)}
                onDragOver={(e) => { e.preventDefault(); setAlvo(p.id); }}
                onDragEnd={() => { setDrag(null); setAlvo(null); }}
                onDrop={() => soltar(p.id)}
                onClick={() => onOpen(p)}>
                <td className="cms-td-chk" onClick={(e) => { e.stopPropagation(); toggle(p.id); }}>
                  <Chk on={sel.includes(p.id)} onToggle={() => toggle(p.id)} label={`Selecionar ${p.title}`} />
                </td>
                <td className="cms-td-grip" title={filtrando ? "Limpe os filtros para reordenar" : "Arraste para mudar a ordem"} onClick={(e) => e.stopPropagation()}>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" /></svg>
                </td>
                <td>
                  <div className="cms-title-cell">
                    <b>{p.title}</b>
                    <small>{p.by}</small>
                  </div>
                </td>
                <td><span className="cms-slug">{endereco(p)}</span></td>
                {tipo === "paginas" &&
                <td>{p.layout ? <span className="cms-pill sys">{LAYOUT_LABEL[p.layout]}</span> : <span className="cms-pill">Livre</span>}</td>}
                <td>{p.meta ? <span className="cms-pill on">Descrição pronta</span> : <span className="cms-pill warn">Sem descrição</span>}</td>
                <td><Situacao on={p.on} /></td>
                <td><span className="cms-prio">{p.upd}</span></td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 &&
        <Vazio title="Nada encontrado com esse termo." description="Tente parte do título ou do endereço da página." />}
        {filtrando && filtered.length > 0 &&
        <p className="cms-nota-ordem">A ordem só pode ser mudada com a lista inteira à vista — limpe a busca e os filtros para arrastar.</p>}
      </div>
      {sel.length > 0 &&
      <Bulk count={sel.length} label={sel.length === 1 ? "selecionada" : "selecionadas"}
        onClose={() => setSel([])}
        actions={[
          { label: "Publicar", onClick: () => { setAviso(`${sel.length} conteúdo(s) no ar.`); setSel([]); } },
          { label: "Tirar do ar", onClick: () => { setAviso(`${sel.length} conteúdo(s) fora do ar.`); setSel([]); } },
          { label: "Excluir", tone: "danger", onClick: () => { setAviso("Nada foi excluído — páginas de sistema bloqueiam a ação em lote."); setSel([]); } },
        ]} />}
    </>
  );
}

// ── Depoimentos (cards) ──
function Depoimentos({ rows, onOpen }) {
  return (
    <div className="cms-cards">
      {rows.map((d) => {
        const c = avColor(d.title);
        return (
          <div key={d.id} className="cms-card" onClick={() => onOpen(d)}>
            <div className="cms-card-h">
              <div className="cms-card-av" style={{ background: c.bg, color: c.fg }}>{initials(d.title)}</div>
              <div><b>{d.title}</b><small>{d.papel}</small></div>
            </div>
            <p className="cms-card-txt">{d.content.replace(/<[^>]+>/g, "")}</p>
            <div className="cms-card-f">
              <span className="cms-prio">ordem {d.prio}</span>
              <Situacao on={d.on} />
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ── Detalhes do site (settings/index.blade — 8 abas) ──
const CFG_TABS = [
  ["aplicacao", "Aplicação"], ["contato", "Contato"], ["redes", "Redes sociais"], ["estatisticas", "Estatísticas"],
  ["faq", "Perguntas frequentes"], ["chat", "Atendimento por chat"], ["integracoes", "Integrações"], ["botoes", "Botões do site"],
];

function Campo({ label, value, help, type = "text", rows, mono, onChange, placeholder }) {
  return (
    <div className="cms-f">
      <label>{label}</label>
      {rows
        ? <textarea rows={rows} value={value} placeholder={placeholder} style={mono ? { fontFamily: "var(--font-mono)", fontSize: 12 } : null} onChange={(e) => onChange?.(e.target.value)} />
        : <input type={type} value={value} placeholder={placeholder} onChange={(e) => onChange?.(e.target.value)} />}
      {help && <small className="help">{help}</small>}
    </div>
  );
}

function Detalhes() {
  const [tab, setTab] = useState("aplicacao");
  const [d, setD] = useState({
    email: "contato@oimpresso.com",
    tel: [{ l: "Comercial", n: "(11) 4002-8922" }, { l: "Suporte", n: "(11) 4002-8923" }, { l: "WhatsApp", n: "(11) 99123-4567" }],
    mail: [{ l: "Comercial", e: "vendas@oimpresso.com" }, { l: "Suporte", e: "suporte@oimpresso.com" }],
    redes: { facebook: "https://facebook.com/officeimpresso", instagram: "https://instagram.com/officeimpresso", linkedin: "https://linkedin.com/company/officeimpresso", youtube: "https://youtube.com/@officeimpresso", twitter: "" },
    stats: { tagline: "O oimpresso em números", desc: "Cada vez mais gráficas e oficinas rodam a operação inteira aqui.",
      itens: [{ v: "200", t: "Lojas atendidas" }, { v: "1.400", t: "Usuários por dia" }, { v: "180 mil", t: "Documentos emitidos" }, { v: "12", t: "Módulos integrados" }] },
    faqs: [
      { q: "Preciso instalar algo?", a: "Não. O oimpresso roda no navegador — você abre e usa, de qualquer computador da loja." },
      { q: "O sistema calcula por m²?", a: "Sim. A fórmula considera insumo, perda de material, acabamento e hora-máquina." },
      { q: "Emite NF-e e NFC-e?", a: "Emite. A nota sai direto da venda, com os impostos já apurados no fechamento." },
      { q: "Como fica meu dado se eu cancelar?", a: "Você exporta tudo antes de sair. Guardamos o backup por 30 dias e depois eliminamos (LGPD Art. 18)." },
    ],
    chat: "in_app", messenger: "", telegram: "",
    ga: "G-8QJ4KDZ2M1", pixel: "", js: "", css: "", metaTags: '<meta name="author" content="Office Impresso">',
    btns: { navbar: { t: "Entrar", l: "/login" }, hero: { t: "Começar grátis", l: "/business/register" }, industry: { t: "Ver por segmento", l: "/c/page/segmentos" }, cta: { t: "Falar com a gente", l: "/c/contact-us" } },
  });
  const [salvo, setSalvo] = useState(false);
  const upd = (patch) => { setD((s) => ({ ...s, ...patch })); setSalvo(false); };

  return (
    <div className="cms-cfg">
      <div className="cms-cfg-rail">
        <h4>Detalhes do site</h4>
        {CFG_TABS.map(([k, l]) => (
          <button key={k} className={tab === k ? "active" : ""} onClick={() => setTab(k)}>{l}</button>
        ))}
      </div>
      <div>
        <div className="cms-cfg-pane">
          {tab === "aplicacao" && <>
            <h2>Aplicação</h2>
            <p className="lead">Marca e e-mail que recebe os avisos do site.</p>
            <div className="cms-f">
              <label>Logo do site</label>
              <div className="cms-img">
                <div className="cms-img-ph"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M7 15l3-4 3 3 2-2 2 3" /></svg></div>
                <div><button className="os-btn sm">Trocar logo</button><small>SVG ou PNG com fundo transparente. O lockup claro pede fundo roxo ou escuro.</small></div>
              </div>
            </div>
            <Campo label="E-mail que recebe os contatos do site" value={d.email} onChange={(v) => upd({ email: v })}
              help="Todo formulário enviado em /c/contact-us cai nesta caixa e gera um lead no CRM." />
          </>}

          {tab === "contato" && <>
            <h2>Contato</h2>
            <p className="lead">Telefones e e-mails que aparecem no rodapé e na página de contato.</p>
            <div className="cms-rows">
              {d.tel.map((t, i) => (
                <div key={i} className="cms-row3">
                  <Campo label={i === 0 ? "Rótulo" : "Rótulo"} value={t.l} onChange={(v) => { const tel = [...d.tel]; tel[i] = { ...t, l: v }; upd({ tel }); }} />
                  <Campo label="Telefone" value={t.n} onChange={(v) => { const tel = [...d.tel]; tel[i] = { ...t, n: v }; upd({ tel }); }} />
                </div>
              ))}
            </div>
            <div className="cms-rows" style={{ marginTop: 8 }}>
              {d.mail.map((m, i) => (
                <div key={i} className="cms-row3">
                  <Campo label="Rótulo" value={m.l} onChange={(v) => { const mail = [...d.mail]; mail[i] = { ...m, l: v }; upd({ mail }); }} />
                  <Campo label="E-mail" type="email" value={m.e} onChange={(v) => { const mail = [...d.mail]; mail[i] = { ...m, e: v }; upd({ mail }); }} />
                </div>
              ))}
            </div>
          </>}

          {tab === "redes" && <>
            <h2>Redes sociais</h2>
            <p className="lead">Deixe em branco a rede que você não usa — o ícone some do rodapé.</p>
            {Object.entries({ facebook: "Facebook", instagram: "Instagram", linkedin: "LinkedIn", youtube: "YouTube", twitter: "X (Twitter)" }).map(([k, l]) => (
              <Campo key={k} label={l} value={d.redes[k]} placeholder="https://" onChange={(v) => upd({ redes: { ...d.redes, [k]: v } })} />
            ))}
          </>}

          {tab === "estatisticas" && <>
            <h2>Estatísticas</h2>
            <p className="lead">A faixa de números da home. Escreva o número como ele deve aparecer.</p>
            <Campo label="Chamada" value={d.stats.tagline} onChange={(v) => upd({ stats: { ...d.stats, tagline: v } })} />
            <Campo label="Descrição" rows={2} value={d.stats.desc} onChange={(v) => upd({ stats: { ...d.stats, desc: v } })} />
            <div className="cms-stat-grid">
              {d.stats.itens.map((it, i) => (
                <div key={i}>
                  <Campo label={`Número ${i + 1}`} value={it.v} onChange={(v) => { const itens = [...d.stats.itens]; itens[i] = { ...it, v }; upd({ stats: { ...d.stats, itens } }); }} />
                  <Campo label="Legenda" value={it.t} onChange={(v) => { const itens = [...d.stats.itens]; itens[i] = { ...it, t: v }; upd({ stats: { ...d.stats, itens } }); }} />
                </div>
              ))}
            </div>
          </>}

          {tab === "faq" && <>
            <h2>Perguntas frequentes</h2>
            <p className="lead">Seis pares de pergunta e resposta. Deixar em branco esconde o item.</p>
            {d.faqs.map((f, i) => (
              <div key={i} className="cms-faq">
                <div className="cms-faq-n">Pergunta {i + 1}</div>
                <Campo label="Pergunta" rows={2} value={f.q} onChange={(v) => { const faqs = [...d.faqs]; faqs[i] = { ...f, q: v }; upd({ faqs }); }} />
                <Campo label="Resposta" rows={3} value={f.a} onChange={(v) => { const faqs = [...d.faqs]; faqs[i] = { ...f, a: v }; upd({ faqs }); }} />
              </div>
            ))}
          </>}

          {tab === "chat" && <>
            <h2>Atendimento por chat</h2>
            <p className="lead">Qual janela de conversa aparece pro visitante do site.</p>
            <div className="cms-f">
              <label>Canal de chat</label>
              <select value={d.chat} onChange={(e) => upd({ chat: e.target.value })}>
                <option value="in_app">Chat do próprio sistema (cai na caixa de entrada)</option>
                <option value="other">Outro serviço (código incorporado)</option>
              </select>
              <small className="help">Com o chat do sistema, a conversa do site entra na mesma fila de WhatsApp e Instagram.</small>
            </div>
            {d.chat === "other" && <>
              <Campo label="Link do Messenger" value={d.messenger} placeholder="http://m.me/seuusuario" onChange={(v) => upd({ messenger: v })} />
              <Campo label="Link do Telegram" value={d.telegram} placeholder="https://t.me/seuusuario" onChange={(v) => upd({ telegram: v })} />
              <Campo label="Código do widget" rows={4} mono value="" placeholder="<script>…</script>" onChange={() => {}} />
            </>}
          </>}

          {tab === "integracoes" && <>
            <h2>Integrações</h2>
            <p className="lead">Medição, código extra e metatags do site público. Não afeta o sistema por dentro.</p>
            <Campo label="Google Analytics" value={d.ga} placeholder="G-XXXXXXX" onChange={(v) => upd({ ga: v })} />
            <Campo label="Pixel do Facebook" value={d.pixel} placeholder="000000000000000" onChange={(v) => upd({ pixel: v })} />
            <Campo label="JavaScript extra" rows={4} mono value={d.js} onChange={(v) => upd({ js: v })} help="Entra antes do fechamento do body, só nas páginas do site." />
            <Campo label="CSS extra" rows={4} mono value={d.css} onChange={(v) => upd({ css: v })} />
            <Campo label="Metatags" rows={3} mono value={d.metaTags} onChange={(v) => upd({ metaTags: v })} help="Verificação de domínio, autoria, prévia em rede social." />
          </>}

          {tab === "botoes" && <>
            <h2>Botões do site</h2>
            <p className="lead">Texto e destino dos quatro botões do tema.</p>
            {[["navbar", "Topo do site"], ["hero", "Chamada principal"], ["industry", "Bloco de segmentos"], ["cta", "Chamada final"]].map(([k, l]) => (
              <div key={k} className="cms-f-row">
                <Campo label={`${l} — texto`} value={d.btns[k].t} onChange={(v) => upd({ btns: { ...d.btns, [k]: { ...d.btns[k], t: v } } })} />
                <Campo label={`${l} — destino`} value={d.btns[k].l} onChange={(v) => upd({ btns: { ...d.btns, [k]: { ...d.btns[k], l: v } } })} />
              </div>
            ))}
          </>}
        </div>
        <div className="cms-savebar">
          <small>{salvo ? "Detalhes do site salvos." : "As mudanças valem pro site público, não pro sistema."}</small>
          <button className="os-btn" onClick={() => window.open("/", "_blank")}>Ver o site</button>
          <button className="os-btn primary" onClick={() => setSalvo(true)}>Salvar detalhes</button>
        </div>
      </div>
    </div>
  );
}

function CmsPage({ view = "paginas" }) {
  const [tab, setTab] = useState(view);
  const [editar, setEditar] = useState(null);
  const [excluir, setExcluir] = useState(null);
  React.useEffect(() => setTab(view), [view]);

  const todas = [...PAGES, ...BLOG, ...DEPOIMENTOS];
  const kpis = {
    total: todas.length,
    pub: todas.filter((p) => p.on).length,
    rasc: todas.filter((p) => !p.on).length,
    semSeo: [...PAGES, ...BLOG].filter((p) => !p.meta).length,
  };
  const tipo = TIPOS[tab];
  return (
    <div className="os-page cms-page" data-screen-label="Site (CMS) · Conteúdo">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Site</h1>
          <p>{PAGES.length} páginas · {BLOG.length} publicações · {DEPOIMENTOS.length} depoimentos · <span className="cms-slug">oimpresso.com</span></p>
        </div>
        <div className="os-page-h-r">
          <button className="os-btn ghost" onClick={() => window.open("/", "_blank")}>Ver o site</button>
          {tab !== "site" && tab !== "modulo" && tab !== "leads" &&
          <button className="os-btn primary" onClick={() => setEditar({ id: "novo", type: tipo.key, layout: null, prio: "", on: false, upd: "agora", by: "você", title: "", content: "<p></p>", meta: "", tags: "" })}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14" /></svg>
            {tab === "blog" ? "Nova publicação" : tab === "depoimentos" ? "Novo depoimento" : "Nova página"}
          </button>}
        </div>
      </header>

      {tab !== "leads" &&
      <Kpis>
        <Kpi v={kpis.total} l="Conteúdos" />
        <Kpi v={kpis.pub} l="No ar" tone="success" />
        <Kpi v={kpis.rasc} l="Rascunhos" />
        <Kpi v={kpis.semSeo} l="Sem descrição de busca" tone={kpis.semSeo > 0 ? "warning" : undefined} />
      </Kpis>}

      <div className="cms-sub">
        <button className={`cms-sub-b ${tab === "paginas" ? "active" : ""}`} onClick={() => setTab("paginas")}>Páginas <span className="n">{PAGES.length}</span></button>
        <button className={`cms-sub-b ${tab === "blog" ? "active" : ""}`} onClick={() => setTab("blog")}>Blog <span className="n">{BLOG.length}</span></button>
        <button className={`cms-sub-b ${tab === "depoimentos" ? "active" : ""}`} onClick={() => setTab("depoimentos")}>Depoimentos <span className="n">{DEPOIMENTOS.length}</span></button>
        <button className={`cms-sub-b ${tab === "leads" ? "active" : ""}`} onClick={() => setTab("leads")}>Leads <span className="n">5</span></button>
        <button className={`cms-sub-b ${tab === "site" ? "active" : ""}`} onClick={() => setTab("site")}>Detalhes do site</button>
        <button className={`cms-sub-b ${tab === "modulo" ? "active" : ""}`} onClick={() => setTab("modulo")}>Módulo</button>
      </div>

      {tab === "site" ? <Detalhes />
        : tab === "modulo" ? <window.CmsExtras.Modulo />
        : tab === "leads" ? <window.CmsExtras.Leads />
        : tab === "depoimentos" ? <Depoimentos rows={DEPOIMENTOS} onOpen={setEditar} />
        : <Lista tipo={tab} rows={tipo.rows} onOpen={setEditar} />}

      {editar && <EditorDrawer p={editar} tipo={["site", "modulo", "leads"].includes(tab) ? "paginas" : tab} onClose={() => setEditar(null)} onExcluir={(p) => { setEditar(null); setExcluir(p); }} />}
      {excluir &&
      <Confirm open title={`Excluir “${excluir.title}”?`} cta="Excluir" onClose={() => setExcluir(null)} onConfirm={() => setExcluir(null)}>
        O conteúdo sai do ar na hora e o endereço passa a devolver página não encontrada. Quem já compartilhou o link vai bater em erro.
      </Confirm>}
    </div>
  );
}

window.CmsPage = CmsPage;
})();
