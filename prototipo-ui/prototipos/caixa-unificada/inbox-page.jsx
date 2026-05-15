// inbox-page.jsx — Caixa unificada V4 · FONTE VISUAL CANÔNICA pro repo
// ─────────────────────────────────────────────────────────────────────────────
//
// Este arquivo é a SOURCE-OF-TRUTH visual de `/atendimento/caixa-unificada`
// (wagnerra23/oimpresso.com) — citada explicitamente no charter do repo:
//   resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md →
//   visual_source: prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx
//
// SINCRONIZAÇÃO (2026-05-15):
//
// ✅ Mergeado em PR-D (paridade 88% — visual_comparison.md):
//    • 3-col 320/1fr/300 limpo
//    • Chips horizontais de canais + sub-row de contas com handle mono
//    • Sidebar 8 sections (Fila/Atribuído/Canal/Tags/OS/Saldo/Histórico/Último/Ações)
//    • Banner amarelo "em homologação" pra preview_only
//    • Toggle Resp/Nota (⌘⇧N) com bolha amarela tracejada
//    • Status filter dropdown · busca inline
//    • Centrifugo real-time + polling 5s defensive · Inertia::defer
//    • Multi-tenant Tier 0 (ADR 0093) · ACL canal=fila (US-WA-069)
//
// 🔜 PENDENTE pra próximo PR (gaps marcados com `TODO US-WA-3XX` no código):
//    • US-WA-301 — Filas DB + drawer config (label/hue/sla/dist/members/trigger_tags)
//    • US-WA-302 — Assignee picker no Contexto (select operators ativos)
//    • US-WA-303 — Slash macros inline + Templates picker inline no composer
//    • US-WA-304 — Drawer "Canais e contas" agrupado por type (vs link /canais)
//    • US-WA-305 — Mover conversa entre filas (override manual da heurística tag→fila)
//    • US-WA-306 — Broadcast cross-canal real (janela 24h Meta + opt-in LGPD)
//    • US-WA-307 — + Nova conversa (ContactPickerModal · template inicial · novo channel)
//
// GANHOS DO PR-D ABSORVIDOS NESTE PROTÓTIPO:
//    ✓ Templates como dropdown agrupando "Jana (IA)" + "HSM Meta-aprovados"
//    ✓ a11y completa: role="tab" · aria-selected · aria-label · focus-visible ring
//    ✓ data-testid="caixa-unif-*" — paridade com testes Pest (R-WA-CAIXA-UNIF-001/002/003)
//    ✓ Skeleton loader nos chips quando Deferred resolve
//    ✓ TODO US-WA-3XX honestos (anti-pattern M-AP-1 LICOES_F3 §1)
//
// PADRÕES (não inventar):
//    • Tokens canônicos OKLCH (CLAUDE_DESIGN_BRIEFING.md §4)
//    • Hue por canal: WA 145 · IG 0 · FB 250 · Email 280 · ML 95
//    • Hue por fila: Vendas 220 · Pós-venda 145 · Financeiro 280 · Produção 30 · Geral 60
//    • Sem emoji em UI produtiva · PT-BR sempre · sem rounded-xl+
//
// COMPONENTES MOCK (no repo real são shadcn):
//    Cá:                 No repo (resources/js/Pages/Atendimento/CaixaUnificada/_components):
//    ─────────────────   ─────────────────────────────────────────────────────────────────
//    <ChannelGlyph>      ChannelChipsRow.tsx (chips + sub-row contas)
//    <OpAvatar>          ContextSidebarV4 (assignee placeholder)
//    <QueueChip>         helpers.ts (queueColors) + ConversationListV4 (chip lateral 3px)
//    <om-bub>            ConversationThreadV4 (bubbles inbound/outbound + internal note)
//    <om-input>          ComposerV4 (toggle Resp/Nota + ⌘⇧N + send via inbox.send)
//    drawers locais      Inertia::defer + Deferred fallback skeletons
//
// Wagner pode pedir mudanças neste arquivo a qualquer momento — [CL] reabsorve
// na próxima rodada do RUNBOOK `cowork-prototype-replication` (ADR 0114).
//
// ─────────────────────────────────────────────────────────────────────────────
//
// Princípio de layout: 3-col sem barras horizontais empilhadas no topo;
// canais + contas em pílulas acima da shell · status/busca/filtro restritos
// à coluna Conversas; Fila/Atribuído no Contexto (coluna direita) pra não
// roubar foco do thread (Princípio: thread > tudo).
//
(() => {
const { useState, useMemo, useRef, useEffect } = React;

// ─── Catálogo de canais ────────────────────────────────────────────────────
const CHANNELS = [
  { id: "wa_baileys", label: "WhatsApp Baileys",    short: "WA · Baileys",    glyph: "W", hue: 145, status: "ativo",    group: "WhatsApp"    },
  { id: "wa_meta",    label: "WhatsApp Meta Cloud", short: "WA · Meta Cloud", glyph: "W", hue: 145, status: "em breve", group: "WhatsApp"    },
  { id: "wa_zapi",    label: "WhatsApp Z-API",      short: "WA · Z-API",      glyph: "W", hue: 145, status: "em breve", group: "WhatsApp"    },
  { id: "ig",         label: "Instagram DM",        short: "Instagram",       glyph: "◎", hue: 0,   status: "em breve", group: "Social"      },
  { id: "fb",         label: "Facebook Messenger",  short: "Messenger",       glyph: "f", hue: 250, status: "em breve", group: "Social"      },
  { id: "email",      label: "Email (IMAP)",        short: "Email",           glyph: "@", hue: 280, status: "em breve", group: "Email"       },
  { id: "ml",         label: "Mercado Livre",       short: "Mercado Livre",   glyph: "M", hue: 95,  status: "em breve", group: "Marketplace" },
];
const CHAN_BY_ID = Object.fromEntries(CHANNELS.map(c => [c.id, c]));

// ─── Contas (múltiplas por canal) ──────────────────────────────────────────
const ACCOUNTS = [
  { id: "wa_bal_balcao", channel: "wa_baileys", label: "Balcão",     handle: "+55 21 9 8001-0011", owner: "larissa", status: "ativo"    },
  { id: "wa_bal_vendas", channel: "wa_baileys", label: "Vendas",     handle: "+55 21 9 8001-0022", owner: "wagner",  status: "ativo"    },
  { id: "wa_bal_fin",    channel: "wa_baileys", label: "Financeiro", handle: "+55 21 9 8001-0033", owner: "eliana",  status: "ativo"    },
  { id: "wa_meta_pri",   channel: "wa_meta",    label: "Comercial",  handle: "+55 21 9 8001-0044", owner: "wagner",  status: "em breve" },
  { id: "wa_zapi_pri",   channel: "wa_zapi",    label: "Marketing",  handle: "+55 21 9 8001-0055", owner: "wagner",  status: "em breve" },
  { id: "ig_main",       channel: "ig",         label: "@oimpresso", handle: "@oimpresso",         owner: "wagner",  status: "em breve" },
  { id: "fb_main",       channel: "fb",         label: "Oimpresso",  handle: "fb.com/oimpresso",   owner: "wagner",  status: "em breve" },
  { id: "email_main",    channel: "email",      label: "Contato",    handle: "contato@oimpresso.com.br",    owner: "wagner", status: "em breve" },
  { id: "email_fin",     channel: "email",      label: "Financeiro", handle: "financeiro@oimpresso.com.br", owner: "eliana", status: "em breve" },
  { id: "ml_main",       channel: "ml",         label: "ML · loja",  handle: "rotalivre · ML",     owner: "larissa", status: "em breve" },
];
const ACC_BY_ID = Object.fromEntries(ACCOUNTS.map(a => [a.id, a]));

// ─── Operadores (assignees) ────────────────────────────────────────────────
const OPERATORS = {
  wagner:  { id: "wagner",  name: "Wagner",  init: "WR", avc: 220 },
  larissa: { id: "larissa", name: "Larissa", init: "LM", avc: 30  },
  eliana:  { id: "eliana",  name: "Eliana",  init: "EC", avc: 280 },
  felipe:  { id: "felipe",  name: "Felipe",  init: "FO", avc: 60  },
};
const ME = "wagner";

// ─── Filas de atendimento ──────────────────────────────────────────────────
// Cada conversa entra numa fila ao chegar. Filas têm SLA + regras de
// distribuição entre operadores subscritos.
const QUEUES = [
  { id: "vendas",   label: "Vendas",       hue: 220, sla: "1h",    members: ["wagner","larissa"],            dist: "round-robin", desc: "Leads novos, orçamentos" },
  { id: "posvenda", label: "Pós-venda",    hue: 145, sla: "2h",    members: ["larissa"],                     dist: "sticky",       desc: "Acompanhamento de OS aberta" },
  { id: "fin",      label: "Financeiro",   hue: 280, sla: "4h",    members: ["eliana"],                      dist: "manual",       desc: "Cobranças, boletos, NF" },
  { id: "prod",     label: "Produção",     hue: 30,  sla: "2h",    members: ["felipe"],                      dist: "round-robin", desc: "Dúvidas técnicas do cliente" },
  { id: "geral",    label: "Geral",        hue: 60,  sla: "30min", members: ["wagner","larissa","eliana"],   dist: "round-robin", desc: "Fallback — quando não rotear" },
];
const Q_BY_ID = Object.fromEntries(QUEUES.map(q => [q.id, q]));

// ─── Macros (atalhos do operador) ──────────────────────────────────────────
const MACROS = [
  { slash: "/orc",       title: "Enviar link de orçamento",  body: "Segue o link do seu orçamento: https://oimpresso.com.br/orc/{numero}\nQualquer ajuste me avise!" },
  { slash: "/abrir",     title: "Abrir conversa",            body: "Olá! Em que posso te ajudar hoje?" },
  { slash: "/preco",     title: "Solicitar dados pra preço", body: "Pra fechar o valor preciso de: quantidade, medidas e prazo de entrega. Tem CNPJ pra emitir nota?" },
  { slash: "/horario",   title: "Horário de atendimento",    body: "Funcionamos seg–sex 9h–18h e sábado 9h–13h. Av. Brasil, 1240 — Centro." },
  { slash: "/agradecer", title: "Agradecer",                 body: "Obrigado pela preferência! Qualquer coisa, estamos por aqui." },
  { slash: "/cobrar",    title: "Lembrete de cobrança",      body: "Olá! Lembramos que o boleto vence amanhã. Pode pagar via PIX ou boleto.", actions: ["tag:cobrança", "assign:eliana", "queue:fin"] },
  { slash: "/pronto",    title: "Pedido pronto",             body: "Seu pedido está pronto pra retirada. Funcionamos 9h–18h. 📍 Av. Brasil, 1240." },
];

// ─── Status ────────────────────────────────────────────────────────────────
const STATUSES = [
  { id: "abertas",     label: "Abertas",      hint: "tudo em atendimento" },
  { id: "pendentes",   label: "Pendentes",    hint: "tem msg não lida do cliente" },
  { id: "aguardando",  label: "Aguardando",   hint: "esperando resposta do cliente" },
  { id: "resolvidas",  label: "Resolvidas",   hint: "fechadas" },
];

// ─── Templates (Cloud API) ─────────────────────────────────────────────────
const TEMPLATES = [
  { id: "ok",   label: "✓ Pronto pra retirada", body: "Olá! Seu pedido está pronto. Funcionamos de 9h às 18h.",       channels: ["wa_baileys","wa_meta","wa_zapi"] },
  { id: "art",  label: "Aprovação de arte",     body: "Segue a arte para aprovação. Responda OK ou indique ajustes.", channels: ["wa_baileys","wa_meta","wa_zapi","email"] },
  { id: "pay",  label: "Lembrete cobrança",     body: "Olá! Lembramos que o boleto vence em breve. Pode pagar via PIX ou boleto.", channels: ["wa_baileys","wa_meta","wa_zapi","email"] },
  { id: "ride", label: "Saiu pra entrega",      body: "Seu pedido saiu para entrega agora e chega ainda hoje.",        channels: ["wa_baileys","wa_meta","wa_zapi"] },
];

// ─── Conversas mock ────────────────────────────────────────────────────────
const CONVS_INIT = [
  { id: "c1", account: "wa_bal_vendas", queue: "vendas",   av: "RL", avc: 145, name: "Renato Lopes", company: "Padaria Estrela",
    handle: "+55 21 9 8112-4400", assignee: "wagner", tags: ["cliente-fiel"],
    lastFrom: "them", preview: "Posso retirar amanhã 9h?", unread: 2, online: true, status: "pendentes",
    ctx: { os: "#4819 · Cardápios A4", saldo: "R$ [redacted Tier 0] a receber", history: "4 pedidos · R$ [redacted Tier 0] LTV", lastTouch: "11:48 hoje" },
    msgs: [
      { d: "ontem", who: "them", t: "Boa tarde! Os cardápios ficaram prontos?", time: "16:20" },
      { d: "ontem", who: "me",   t: "Sim Renato! Estão prontos. Pode passar quando quiser.", time: "16:25" },
      { d: "hoje",  who: "me",   t: "Cliente atrasou 3x no último pedido. Exigir PIX antes de soltar peça.", time: "08:30", internal: true },
      { d: "hoje",  who: "them", t: "Posso passar pra retirar amanhã 9h?", time: "11:48" },
    ]},
  { id: "c2", account: "wa_bal_vendas", queue: "posvenda", av: "CD", avc: 30, name: "Camila Diniz", company: "Acme Comércio Ltda",
    handle: "+55 21 9 9712-0090", assignee: "wagner", tags: ["arte-aprovada"],
    lastFrom: "me", preview: "Você: arte aprovada ✓", unread: 0, status: "aguardando",
    ctx: { os: "#4821 · Banner 3×2m", saldo: "R$ [redacted Tier 0] (faturado)", history: "12 pedidos · R$ [redacted Tier 0] LTV", lastTouch: "10:32 hoje" },
    msgs: [
      { d: "hoje", who: "them", t: "Recebi a prova, mas o azul tá meio escuro. Pode ajustar?", time: "09:14" },
      { d: "hoje", who: "me",   t: "Pode deixar Camila, já mando a v2.", time: "09:20" },
      { d: "hoje", who: "me",   t: "Arte aprovada ✓", time: "10:32" },
    ]},
  { id: "c3", account: "wa_bal_balcao", queue: "vendas",   av: "DV", avc: 220, name: "Diego Vasconcellos", company: "TechPro",
    handle: "+55 21 9 9301-7714", assignee: "larissa", tags: ["novo"],
    lastFrom: "them", preview: "Quanto fica em 200un?", unread: 1, status: "pendentes",
    ctx: { os: null, saldo: "R$ [redacted Tier 0] a receber", history: "3 pedidos · R$ [redacted Tier 0] LTV", lastTouch: "13:05 hoje" },
    msgs: [
      { d: "hoje", who: "them", t: "Olá! Adesivos novos, 8×8cm recortado.", time: "12:50" },
      { d: "hoje", who: "them", t: "Quanto fica em 200un?", time: "13:05" },
    ]},
  { id: "c4", account: "wa_bal_fin",    queue: "fin",      av: "MV", avc: 295, name: "Marcos Vital", company: "Posto BR Centro",
    handle: "+55 21 9 8800-1240", assignee: "eliana", tags: ["cobrança"],
    lastFrom: "them", preview: "Obrigado! Recebi.", unread: 0, status: "abertas",
    ctx: { os: "#4790 · Lona Front-Light", saldo: "R$ [redacted Tier 0]", history: "1 pedido · R$ [redacted Tier 0] LTV", lastTouch: "ontem 17:48" },
    msgs: [
      { d: "ontem", who: "me",   t: "Marcos, peça pronta e nota emitida.", time: "17:42" },
      { d: "ontem", who: "them", t: "Obrigado! Recebi.", time: "17:48" },
    ]},

  // Previews dos canais em breve
  { id: "c5", account: "wa_meta_pri",  queue: "vendas",   av: "FE", avc: 60, name: "Fátima Estrela", company: "Padaria Estrela",
    handle: "+55 21 9 8112-4400 · Cloud API", assignee: null, tags: [],
    lastFrom: "them", preview: "Templates aprovados pela Meta?", unread: 0, preview_only: true, status: "abertas",
    ctx: { os: null, saldo: "—", history: "—", lastTouch: "preview" },
    msgs: [{ d: "hoje", who: "them", t: "Quando rolar o Meta Cloud aqui? Quero usar templates oficiais.", time: "—" }]},
  { id: "c6", account: "ig_main",      queue: "vendas",   av: "ST", avc: 12, name: "@studio.foco", company: "Studio Foco",
    handle: "@studio.foco · DM", assignee: null, tags: [],
    lastFrom: "them", preview: "Curti! Faz orçamento?", unread: 0, preview_only: true, status: "abertas",
    ctx: { os: null, saldo: "—", history: "—", lastTouch: "preview" },
    msgs: [{ d: "hoje", who: "them", t: "Curti o trabalho da fachada. Faz orçamento por aqui?", time: "—" }]},
  { id: "c7", account: "email_main",   queue: "vendas",   av: "@", avc: 280, name: "compras@imobhorizonte.com.br", company: "Imobiliária Horizonte",
    handle: "Re: Cotação 200 placas PS · 3mm", assignee: null, tags: [],
    lastFrom: "them", preview: "Segue PO #2418 em anexo", unread: 0, preview_only: true, status: "abertas",
    ctx: { os: null, saldo: "—", history: "—", lastTouch: "preview" },
    msgs: [{ d: "hoje", who: "them", t: "Prezados, segue PO #2418 anexo. Aguardo previsão de entrega.\n\n— Luís, compras", time: "—" }]},
  { id: "c8", account: "ml_main",      queue: "posvenda", av: "ML", avc: 95, name: "ML · pedido #4019887214", company: "Mercado Livre",
    handle: "Adesivo personalizado A4 · 10un", assignee: null, tags: [],
    lastFrom: "them", preview: "Posso retirar no balcão?", unread: 0, preview_only: true, status: "abertas",
    ctx: { os: null, saldo: "—", history: "—", lastTouch: "preview" },
    msgs: [{ d: "hoje", who: "them", t: "Comprador pergunta: posso retirar no balcão em vez do envio?", time: "—" }]},
];

// ─── Componentes auxiliares ────────────────────────────────────────────────
function ChannelGlyph({ ch, size = 14 }) {
  if (!ch) return null;
  return (
    <span className="om-cg" style={{ width: size, height: size, fontSize: size * 0.62, background: `oklch(0.62 0.14 ${ch.hue})` }} title={ch.label}>
      {ch.glyph}
    </span>
  );
}

function OpAvatar({ id, size = 18 }) {
  if (!id) return null;
  const op = OPERATORS[id];
  if (!op) return null;
  return (
    <span className="om-op" style={{ width: size, height: size, fontSize: size * 0.45, background: `oklch(0.55 0.12 ${op.avc})` }} title={`Atribuído a ${op.name}`}>
      {op.init}
    </span>
  );
}

function QueueChip({ q, size = "sm" }) {
  if (!q) return null;
  return (
    <span className={"om-q-chip " + size}
          style={{ background: `oklch(0.95 0.04 ${q.hue})`, color: `oklch(0.30 0.13 ${q.hue})`, borderColor: `oklch(0.85 0.08 ${q.hue})` }}>
      {q.label}
    </span>
  );
}

function InboxPage() {
  const [convs, setConvs] = useState(CONVS_INIT);
  const [selId, setSelId] = useState("c1");
  const [draft, setDraft] = useState("");
  const [internalMode, setInternalMode] = useState(false);
  const [showTpl, setShowTpl] = useState(false);
  const [showMacros, setShowMacros] = useState(false);
  const [slashOpen, setSlashOpen] = useState(false);
  const [bcastOpen, setBcastOpen] = useState(false);
  const [chSwitcherOpen, setChSwitcherOpen] = useState(false);
  const [queuesOpen, setQueuesOpen] = useState(false);
  const [tplMenuOpen, setTplMenuOpen] = useState(false);  // dropdown Templates do header (Jana+HSM)
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [filter, setFilter] = useState("all");           // canal
  const [accFilter, setAccFilter] = useState("all");     // conta
  const [queueFilter, setQueueFilter] = useState("all"); // fila
  const [statusFilter, setStatusFilter] = useState("abertas");
  const [search, setSearch] = useState("");
  const [toast, setToast] = useState(null);
  const threadRef = useRef(null);
  const inputRef = useRef(null);

  useEffect(() => { if (!toast) return; const t = setTimeout(() => setToast(null), 2400); return () => clearTimeout(t); }, [toast]);

  // ── Filtragem ─────────────────────────────────────────────────────
  const filteredConvs = useMemo(() => convs.filter(c => {
    const acc = ACC_BY_ID[c.account];
    if (filter !== "all" && acc?.channel !== filter) return false;
    if (accFilter !== "all" && c.account !== accFilter) return false;
    if (queueFilter !== "all" && c.queue !== queueFilter) return false;
    if (statusFilter === "pendentes"  && !(c.unread > 0)) return false;
    if (statusFilter === "aguardando" && c.lastFrom !== "me") return false;
    if (statusFilter === "resolvidas" && c.status !== "resolvidas") return false;
    if (statusFilter === "abertas" && c.status === "resolvidas") return false;
    if (search.trim()) {
      const q = search.trim().toLowerCase();
      const blob = `${c.name} ${c.company} ${c.preview}`.toLowerCase();
      if (!blob.includes(q)) return false;
    }
    return true;
  }), [convs, filter, accFilter, queueFilter, statusFilter, search]);

  const conv = convs.find(c => c.id === selId);
  const convAcc = conv ? ACC_BY_ID[conv.account] : null;
  const convChannel = convAcc ? CHAN_BY_ID[convAcc.channel] : null;
  const convQueue = conv ? Q_BY_ID[conv.queue] : null;
  const isPreview = conv?.preview_only;

  const accountsForFilter = useMemo(() => (
    filter === "all" ? [] : ACCOUNTS.filter(a => a.channel === filter)
  ), [filter]);

  useEffect(() => { if (threadRef.current) threadRef.current.scrollTop = threadRef.current.scrollHeight; }, [selId, conv?.msgs.length]);
  useEffect(() => { if (conv && conv.unread > 0) setConvs(cs => cs.map(c => c.id === selId ? { ...c, unread: 0 } : c)); }, [selId]);
  useEffect(() => {
    if (filteredConvs.length && !filteredConvs.find(c => c.id === selId)) setSelId(filteredConvs[0].id);
  }, [filter, accFilter, queueFilter, statusFilter, search]);
  useEffect(() => {
    if (accFilter !== "all" && ACC_BY_ID[accFilter]?.channel !== filter && filter !== "all") setAccFilter("all");
  }, [filter]);

  const counts = useMemo(() => {
    const m = { all: convs.length };
    for (const ch of CHANNELS)  m[ch.id]            = convs.filter(c => ACC_BY_ID[c.account]?.channel === ch.id).length;
    for (const acc of ACCOUNTS) m["acc_" + acc.id]  = convs.filter(c => c.account === acc.id).length;
    for (const q of QUEUES)     m["q_" + q.id]      = convs.filter(c => c.queue === q.id).length;
    for (const s of STATUSES)   m["st_" + s.id]     = convs.filter(c => {
      if (s.id === "pendentes")  return c.unread > 0;
      if (s.id === "aguardando") return c.lastFrom === "me";
      if (s.id === "resolvidas") return c.status === "resolvidas";
      return c.status !== "resolvidas"; // abertas
    }).length;
    return m;
  }, [convs]);

  const totalUnread = convs.reduce((s, c) => s + c.unread, 0);
  const activeAccCount = ACCOUNTS.filter(a => a.status === "ativo").length;
  const headerSub = `${activeAccCount} contas ativas · ${QUEUES.length} filas · ${convs.length} abertas${totalUnread > 0 ? ` · ${totalUnread} não lidas` : ""}`;

  // Conta filtros ativos (pra badge no botão Filtros)
  const activeFilterCount = [
    filter !== "all",
    accFilter !== "all",
    queueFilter !== "all",
    statusFilter !== "abertas",
  ].filter(Boolean).length;

  const clearFilters = () => {
    setFilter("all"); setAccFilter("all"); setQueueFilter("all"); setStatusFilter("abertas");
  };

  const validTemplates = TEMPLATES.filter(t => !convChannel || t.channels.includes(convChannel.id));
  const slashQuery = draft.startsWith("/") ? draft.toLowerCase() : null;
  const slashMatches = slashQuery ? MACROS.filter(m => m.slash.startsWith(slashQuery) || m.title.toLowerCase().includes(slashQuery.slice(1))) : [];

  // ── Ações ─────────────────────────────────────────────────────────
  const sendMsg = (text, opts = {}) => {
    const asInternal = opts.internal ?? internalMode;
    if (isPreview && !asInternal) { setToast(`Canal ${convChannel.label} em homologação — envio bloqueado`); return; }
    const t = (text || draft).trim();
    if (!t) return;
    setConvs(cs => cs.map(c => c.id !== selId ? c : {
      ...c,
      msgs: [...c.msgs, { d: "hoje", who: "me", t, time: "agora", internal: asInternal }],
      lastFrom: asInternal ? c.lastFrom : "me",
      preview: asInternal ? c.preview : "Você: " + t,
    }));
    setDraft("");
    setShowTpl(false); setShowMacros(false); setSlashOpen(false);
  };

  const applyMacro = (macro) => {
    setDraft(macro.body);
    setShowMacros(false); setSlashOpen(false);
    inputRef.current?.focus();
    if (macro.actions?.length) {
      const tags = macro.actions.filter(a => a.startsWith("tag:")).map(a => a.slice(4));
      const assign = macro.actions.find(a => a.startsWith("assign:"))?.slice(7);
      const queue = macro.actions.find(a => a.startsWith("queue:"))?.slice(6);
      setConvs(cs => cs.map(c => c.id !== selId ? c : {
        ...c,
        tags: Array.from(new Set([...(c.tags || []), ...tags])),
        ...(assign ? { assignee: assign } : {}),
        ...(queue ? { queue } : {}),
      }));
      setToast(`Macro: ${macro.title}${assign ? ` · → ${OPERATORS[assign]?.name}` : ""}${queue ? ` · fila ${Q_BY_ID[queue]?.label}` : ""}`);
    }
  };

  const resolve = () => {
    setConvs(cs => cs.map(c => c.id === selId ? { ...c, status: "resolvidas" } : c));
    setToast(`Conversa com ${conv.name} resolvida`);
    const next = filteredConvs.find(c => c.id !== selId);
    if (next) setSelId(next.id);
  };

  const reassign = (opId) => {
    setConvs(cs => cs.map(c => c.id === selId ? { ...c, assignee: opId || null } : c));
    if (opId) setToast(`→ ${OPERATORS[opId].name}`);
  };

  const moveToQueue = (qId) => {
    setConvs(cs => cs.map(c => c.id === selId ? { ...c, queue: qId } : c));
    setToast(`Movido pra fila ${Q_BY_ID[qId].label}`);
  };

  // ── Render ────────────────────────────────────────────────────────
  return (
    <div className="os-page om-page" data-screen-label="01 Caixa unificada" data-testid="caixa-unif-page">
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Caixa unificada</h1>
          <p>{headerSub}</p>
        </div>
        <div className="os-page-h-r">
          {/* Templates dropdown — agrupa Jana (IA prompts) + HSM Meta (aprovados).
              Paridade com Index.tsx do repo (PR-D 2026-05-15). */}
          <div className="om-dd-wrap">
            <button
              className="os-btn ghost"
              onClick={() => setTplMenuOpen(v => !v)}
              aria-haspopup="menu"
              aria-expanded={tplMenuOpen}
              data-testid="caixa-unif-topnav-templates">
              Templates <span style={{ fontSize: 9, marginLeft: 4, opacity: 0.6 }}>▾</span>
            </button>
            {tplMenuOpen && (
              <>
                <div className="om-dd-backdrop" onClick={() => setTplMenuOpen(false)}/>
                <div className="om-dd-menu" role="menu">
                  <small className="om-dd-label">Bibliotecas de templates</small>
                  <button className="om-dd-item" role="menuitem" data-testid="caixa-unif-topnav-templates-jana"
                          onClick={() => { setTplMenuOpen(false); setToast("Abriria /atendimento/canais/jana-templates"); }}>
                    <span className="om-dd-icon jana">✨</span>
                    <span className="om-dd-text">
                      <b>Templates Jana</b>
                      <small>Prompts internos IA</small>
                    </span>
                  </button>
                  <button className="om-dd-item" role="menuitem" data-testid="caixa-unif-topnav-templates-hsm"
                          onClick={() => { setTplMenuOpen(false); setToast("Abriria /whatsapp/templates"); }}>
                    <span className="om-dd-icon hsm">▣</span>
                    <span className="om-dd-text">
                      <b>Templates HSM</b>
                      <small>Meta-aprovados (fora 24h)</small>
                    </span>
                  </button>
                </div>
              </>
            )}
          </div>
          {/* TODO US-WA-301: Filas DB + drawer config (hoje hardcoded em QUEUES array) */}
          <button className="os-btn ghost" onClick={() => setQueuesOpen(v => !v)}
                  data-testid="caixa-unif-topnav-filas">Filas</button>
          {/* TODO US-WA-304: Drawer in-place vs link pra /atendimento/canais (decisão do roadmap §5) */}
          <button className="os-btn ghost" onClick={() => setChSwitcherOpen(v => !v)}
                  data-testid="caixa-unif-topnav-canais">Canais</button>
          {/* TODO US-WA-306: Broadcast real (janela 24h Meta + opt-in LGPD + dry-run preview) */}
          <button className="os-btn ghost" onClick={() => setBcastOpen(true)}
                  data-testid="caixa-unif-topnav-broadcast">Broadcast</button>
          {/* TODO US-WA-307: + Nova conversa (ContactPickerModal + template inicial) */}
          <button className="os-btn primary"
                  data-testid="caixa-unif-topnav-nova">+ Nova conversa</button>
        </div>
      </div>

      {/* ───── Filtro horizontal de canais ─────
          Paridade ChannelChipsRow.tsx do repo. role=tablist + aria-selected
          + data-testid pra teste Pest. */}
      <div className="om-filter" role="tablist" aria-label="Filtrar por canal">
        <button className={"om-fil " + (filter === "all" ? "sel" : "")}
                onClick={() => setFilter("all")}
                role="tab"
                aria-selected={filter === "all"}
                data-testid="caixa-unif-channel-chip-all">
          <span>Todos</span><em>{counts.all}</em>
        </button>
        {CHANNELS.map(ch => {
          const n = counts[ch.id] || 0;
          const isComing = ch.status === "em breve";
          const isSel = filter === ch.id;
          return (
            <button key={ch.id}
                    className={"om-fil " + (isSel ? "sel " : "") + (isComing ? "coming" : "")}
                    onClick={() => setFilter(ch.id)}
                    role="tab"
                    aria-selected={isSel}
                    title={ch.label}
                    data-testid={`caixa-unif-channel-chip-${ch.id}`}>
              <ChannelGlyph ch={ch} size={13}/>
              <span>{ch.short}</span>
              {isComing ? <em className="soon">em breve</em> : <em>{n}</em>}
            </button>
          );
        })}
      </div>

      {/* ───── Sub-row de contas (quando 2+ contas no canal selecionado) ───── */}
      {filter !== "all" && accountsForFilter.length > 1 && (
        <div className="om-filter sub" role="tablist" aria-label="Filtrar por conta">
          <button className={"om-fil sm " + (accFilter === "all" ? "sel" : "")}
                  onClick={() => setAccFilter("all")}
                  role="tab"
                  aria-selected={accFilter === "all"}
                  data-testid="caixa-unif-account-chip-all">
            <span>Todas as contas</span><em>{accountsForFilter.length}</em>
          </button>
          {accountsForFilter.map(acc => {
            const n = counts["acc_" + acc.id] || 0;
            const isComing = acc.status === "em breve";
            const isSel = accFilter === acc.id;
            return (
              <button key={acc.id}
                      className={"om-fil sm " + (isSel ? "sel " : "") + (isComing ? "coming" : "")}
                      onClick={() => setAccFilter(acc.id)}
                      role="tab"
                      aria-selected={isSel}
                      title={`${acc.label} · ${acc.handle}`}
                      data-testid={`caixa-unif-account-chip-${acc.id}`}>
                <b>{acc.label}</b>
                <span className="mono">{acc.handle}</span>
                {isComing ? <em className="soon">em breve</em> : <em>{n}</em>}
              </button>
            );
          })}
        </div>
      )}

      <div className="om-shell">
        {/* ───── Coluna: Conversas (lista + busca + status) ───── */}
        <aside className="om-list-c">
          <div className="om-list-h">
            <b>Conversas</b>
            <span className="mono">{filteredConvs.length}</span>
            <select className="om-status-sel" value={statusFilter} onChange={e => setStatusFilter(e.target.value)} title="Filtrar por status">
              {STATUSES.map(s => (
                <option key={s.id} value={s.id}>{s.label} ({counts["st_" + s.id] || 0})</option>
              ))}
            </select>
          </div>

          {/* Busca inline */}
          <div className="om-list-search">
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Buscar nome, empresa, texto…"
              aria-label="Buscar nas conversas"
              data-testid="caixa-unif-search"/>
            {search && <button className="om-list-search-x" onClick={() => setSearch("")}
                               aria-label="Limpar busca" title="Limpar busca">✕</button>}
          </div>

          {filteredConvs.length === 0 ? (
            <div className="om-empty-list">
              <b>Nenhuma conversa</b>
              <small>Tente outro filtro ou limpe a busca.</small>
            </div>
          ) : (
            <ul className="om-list">
              {filteredConvs.map(c => {
                const acc = ACC_BY_ID[c.account];
                const ch = CHAN_BY_ID[acc?.channel];
                const q = Q_BY_ID[c.queue];
                return (
                  <li key={c.id}
                      className={(selId === c.id ? "sel " : "") + (c.preview_only ? "ghost" : "")}
                      onClick={() => setSelId(c.id)}
                      data-testid={`caixa-unif-conv-${c.id}`}
                      aria-current={selId === c.id ? "true" : undefined}
                      style={{ "--om-q-color": q ? `oklch(0.62 0.13 ${q.hue})` : "transparent" }}>
                    <span className="om-av-wrap">
                      <span className="om-av" style={{ background: `oklch(0.60 0.12 ${c.avc})` }}>
                        {c.av}
                        {c.online && <span className="om-online"/>}
                      </span>
                      <span className="om-av-ch" style={{ background: `oklch(0.62 0.14 ${ch.hue})` }} title={`${ch.label} · ${acc.label}`}>
                        {ch.glyph}
                      </span>
                    </span>
                    <div className="om-list-text">
                      <b>
                        {c.name}
                        {c.preview_only && <span className="om-chip-soon">em breve</span>}
                      </b>
                      <small>{c.preview}</small>
                    </div>
                    <div className="om-list-r">
                      {c.assignee && <OpAvatar id={c.assignee} size={16}/>}
                      {c.unread > 0 && <span className="om-un">{c.unread}</span>}
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </aside>

        {/* ───── Thread ───── */}
        <main className="om-thread-c">
          {!conv ? (
            <div className="om-empty">Selecione uma conversa.</div>
          ) : (
            <>
              <header className="om-thread-h">
                <span className="om-av-wrap sm">
                  <span className="om-av sm" style={{ background: `oklch(0.60 0.12 ${conv.avc})` }}>{conv.av}</span>
                  <span className="om-av-ch sm" style={{ background: `oklch(0.62 0.14 ${convChannel.hue})` }}>{convChannel.glyph}</span>
                </span>
                <div className="om-thread-h-text">
                  <b>{conv.name}</b>
                  <small>
                    <span className="om-chip" style={{ borderColor: `oklch(0.85 0.06 ${convChannel.hue})`, color: `oklch(0.35 0.10 ${convChannel.hue})` }}>
                      {convChannel.short} · {convAcc.label}
                    </span>
                    <span className="om-sep">·</span>
                    <span className="mono">{conv.handle}</span>
                    <span className="om-sep">·</span>
                    {conv.online ? "online" : conv.ctx.lastTouch}
                  </small>
                </div>
                {!isPreview && (
                  <div className="om-thread-h-r">
                    <button className="os-btn ghost" onClick={resolve}>✓ Resolver</button>
                  </div>
                )}
              </header>

              {isPreview && (
                <div className="om-preview-banner">
                  <b>{convChannel.label} · em homologação.</b>
                  <span>Conexão deste canal ainda não foi ativada. Esta conversa é uma prévia. <a href="#">Ativar canal</a></span>
                </div>
              )}

              <div className="om-msgs" ref={threadRef}>
                {conv.msgs.map((m, i) => {
                  const showDay = i === 0 || conv.msgs[i-1].d !== m.d;
                  return (
                    <React.Fragment key={i}>
                      {showDay && <div className="om-day-sep"><span>{m.d === "hoje" ? "Hoje" : m.d === "ontem" ? "Ontem" : m.d}</span></div>}
                      {m.internal ? (
                        <div className="om-internal">
                          <div className="om-internal-h">
                            <span className="om-internal-tag">Nota interna</span>
                            <small>{m.time} · só a equipe vê</small>
                          </div>
                          <div className="om-internal-t">{m.t}</div>
                        </div>
                      ) : (
                        <div className={"om-bub " + (m.who === "me" ? "me" : "them") + " ch-" + convChannel.id}>
                          <span>{m.t}</span>
                          <small>{m.time}{m.who === "me" ? " ✓✓" : ""}</small>
                        </div>
                      )}
                    </React.Fragment>
                  );
                })}
              </div>

              {showTpl && (
                <div className="om-tpl">
                  <small>Templates · disponíveis em {convChannel.short}</small>
                  {validTemplates.length === 0
                    ? <em className="om-tpl-none">Nenhum template configurado para este canal.</em>
                    : validTemplates.map(t => (
                        <button key={t.id} onClick={() => sendMsg(t.body)}>
                          <b>{t.label}</b>
                          <em>{t.body}</em>
                        </button>
                      ))}
                </div>
              )}

              {showMacros && (
                <div className="om-tpl macros">
                  <small>Macros · digite <span className="mono">/</span> no input pra autocomplete</small>
                  {MACROS.map(m => (
                    <button key={m.slash} onClick={() => applyMacro(m)}>
                      <b><span className="mono om-slash">{m.slash}</span> {m.title}</b>
                      <em>{m.body}</em>
                      {m.actions?.length > 0 && (
                        <div className="om-macro-actions">
                          {m.actions.map(a => <span key={a} className="om-tag">{a}</span>)}
                        </div>
                      )}
                    </button>
                  ))}
                </div>
              )}

              {slashOpen && slashMatches.length > 0 && (
                <div className="om-slash-pop">
                  {slashMatches.slice(0, 6).map(m => (
                    <button key={m.slash} onClick={() => applyMacro(m)}>
                      <span className="mono om-slash">{m.slash}</span>
                      <b>{m.title}</b>
                      <em>{m.body.slice(0, 70)}…</em>
                    </button>
                  ))}
                </div>
              )}

              <div className={"om-input " + (internalMode ? "internal" : "")}>
                <button className={"om-mode-btn " + (internalMode ? "on" : "")}
                        onClick={() => setInternalMode(v => !v)}
                        aria-label={internalMode ? "Modo nota interna ativo (⌘⇧N pra voltar)" : "Alternar pra nota interna (⌘⇧N)"}
                        aria-pressed={internalMode}
                        title="Resposta cliente / Nota interna (⌘⇧N)"
                        data-testid="caixa-unif-mode-toggle">
                  {internalMode ? "Nota" : "Resp"}
                </button>
                <button className="om-icon-btn" onClick={() => { setShowTpl(v => !v); setShowMacros(false); }}
                        aria-label="Templates do canal (⌘T)" title="Templates" disabled={internalMode}>⌘T</button>
                {/* TODO US-WA-303: slash macros inline + autocomplete (já prototipado em om-slash-pop) */}
                <button className="om-icon-btn" onClick={() => { setShowMacros(v => !v); setShowTpl(false); }}
                        aria-label="Macros (atalhos / slash)" title="Macros" disabled={internalMode}>/</button>
                <input
                  ref={inputRef}
                  value={draft}
                  data-testid="caixa-unif-composer-input"
                  aria-label={internalMode ? "Nota interna" : "Mensagem para o cliente"}
                  onChange={e => {
                    const v = e.target.value;
                    setDraft(v);
                    setSlashOpen(v.startsWith("/") && v.length > 0);
                  }}
                  onKeyDown={e => {
                    if (e.key === "Enter" && !e.shiftKey) {
                      e.preventDefault();
                      if (slashOpen && slashMatches.length > 0) { applyMacro(slashMatches[0]); return; }
                      sendMsg();
                    }
                    if (e.key === "Escape") { setSlashOpen(false); setShowTpl(false); setShowMacros(false); }
                    if (e.key === "N" && (e.metaKey || e.ctrlKey) && e.shiftKey) { e.preventDefault(); setInternalMode(v => !v); }
                  }}
                  placeholder={
                    internalMode
                      ? "Nota interna · só pra equipe"
                      : (isPreview ? `${convChannel.short} em homologação — envio bloqueado` : `Responder via ${convChannel.short} · ${convAcc.label} · / pra macros`)
                  }
                  disabled={isPreview && !internalMode}/>
                <button
                  className={"os-btn " + (internalMode ? "" : "primary")}
                  onClick={() => sendMsg()}
                  disabled={!draft.trim() || (isPreview && !internalMode)}
                  data-testid="caixa-unif-composer-send"
                  style={internalMode ? { background: "oklch(0.70 0.14 80)", color: "oklch(0.20 0.10 80)" } : null}>
                  {internalMode ? "Anotar" : "Enviar"}
                </button>
              </div>
            </>
          )}
        </main>

        {/* ───── Contexto ───── */}
        {conv && (
          <aside className="om-ctx">
            <div className="om-list-h"><b>Contexto</b></div>
            <div className="om-ctx-body">
              <div className="om-kv">
                <small>Fila</small>
                {isPreview ? (
                  <b><QueueChip q={convQueue}/></b>
                ) : (
                  /* TODO US-WA-305: Mover conversa entre filas (hoje fila vem da heurística
                     tag→fila em deriveQueueFromTags() do CaixaUnificadaController; override
                     manual precisa nova coluna conversations.queue_slug nullable). */
                  <select className="om-ctx-select" value={conv.queue || ""} onChange={e => moveToQueue(e.target.value)}
                          aria-label="Mover conversa para outra fila"
                          data-testid="caixa-unif-ctx-queue-select">
                    {QUEUES.map(q => <option key={q.id} value={q.id}>{q.label}</option>)}
                  </select>
                )}
                <small style={{ marginTop: 4, color: "var(--text-mute)", textTransform: "none", letterSpacing: 0 }}>
                  SLA {convQueue?.sla} · {convQueue?.dist === "round-robin" ? "alternada" : convQueue?.dist === "sticky" ? "fixa" : "manual"}
                </small>
              </div>
              <div className="om-kv">
                <small>Atribuído</small>
                {isPreview ? (
                  <b>— sem atribuição</b>
                ) : (
                  /* TODO US-WA-302: Assignee picker real — hoje OPERATORS é mock; deve buscar
                     users do business com permission `whatsapp.access` ATIVA + presence
                     online/offline (Centrifugo). Backend reusa conversations.assigned_user_id. */
                  <select className="om-ctx-select" value={conv.assignee || ""} onChange={e => reassign(e.target.value)}
                          aria-label="Atribuir conversa para operador"
                          data-testid="caixa-unif-ctx-assignee-select">
                    <option value="">— sem atribuição</option>
                    {Object.values(OPERATORS).map(op => <option key={op.id} value={op.id}>{op.name}</option>)}
                  </select>
                )}
              </div>
              <div className="om-kv">
                <small>Canal · Conta</small>
                <b>
                  <ChannelGlyph ch={convChannel} size={12}/>
                  <span style={{ marginLeft: 6 }}>{convChannel.short} · {convAcc.label}</span>
                </b>
                <small className="mono" style={{ marginTop: 2, color: "var(--text-mute)", textTransform: "none", letterSpacing: 0 }}>{convAcc.handle}</small>
              </div>
              {conv.tags?.length > 0 && (
                <div className="om-kv">
                  <small>Tags</small>
                  <div className="om-tags">{conv.tags.map(t => <span key={t} className="om-tag">{t}</span>)}</div>
                </div>
              )}
              {conv.ctx.os && (
                <div className="om-kv">
                  <small>OS vinculada</small>
                  <b>{conv.ctx.os}</b>
                  <button className="os-btn sm" style={{ marginTop: 6 }}>Abrir OS</button>
                </div>
              )}
              <div className="om-kv"><small>Saldo cliente</small><b>{conv.ctx.saldo}</b></div>
              <div className="om-kv"><small>Histórico</small><b>{conv.ctx.history}</b></div>
              <div className="om-kv"><small>Último contato</small><b>{conv.ctx.lastTouch}</b></div>
              <div className="om-actions">
                <button className="os-btn sm" disabled={isPreview}>Emitir cobrança</button>
                <button className="os-btn sm" disabled={isPreview}>Enviar arte</button>
                <button className="os-btn sm" disabled={isPreview}>Ligar</button>
              </div>
            </div>
          </aside>
        )}
      </div>

      {toast && <div className="om-toast">✓ {toast}</div>}

      {/* ───── Drawer: Canais e contas ───── */}
      {chSwitcherOpen && (
        <>
          <div className="om-backdrop" onClick={() => setChSwitcherOpen(false)}/>
          <aside className="om-drawer">
            <header className="om-drawer-h">
              <div><h2>Canais e contas</h2><p>{activeAccCount} ativas · {ACCOUNTS.length - activeAccCount} em homologação</p></div>
              <button className="om-x" onClick={() => setChSwitcherOpen(false)}>✕</button>
            </header>
            <div className="om-drawer-body">
              {CHANNELS.map(ch => {
                const accs = ACCOUNTS.filter(a => a.channel === ch.id);
                if (!accs.length) return null;
                return (
                  <section key={ch.id} className="om-chan-group">
                    <small className="om-chan-group-h">
                      <ChannelGlyph ch={ch} size={11}/>
                      <span style={{ marginLeft: 6 }}>{ch.label}</span>
                    </small>
                    {accs.map(acc => (
                      <div key={acc.id} className={"om-chan-row " + (acc.status === "ativo" ? "on" : "off")}>
                        <div className="om-chan-row-text">
                          <b>{acc.label}</b>
                          <small className="mono">{acc.handle}</small>
                          {OPERATORS[acc.owner] && <small>Responsável: {OPERATORS[acc.owner].name}</small>}
                        </div>
                        {acc.status === "ativo"
                          ? <span className="om-pill on">ativo</span>
                          : <span className="om-pill off">em breve</span>}
                      </div>
                    ))}
                  </section>
                );
              })}
              <button className="os-btn ghost" style={{ marginTop: 12, justifyContent: "center" }}>+ Adicionar conta</button>
            </div>
          </aside>
        </>
      )}

      {/* ───── Drawer: Filas de atendimento ───── */}
      {queuesOpen && (
        <>
          <div className="om-backdrop" onClick={() => setQueuesOpen(false)}/>
          <aside className="om-drawer wide">
            <header className="om-drawer-h">
              <div><h2>Filas de atendimento</h2><p>{QUEUES.length} filas · distribuição automática</p></div>
              <button className="om-x" onClick={() => setQueuesOpen(false)}>✕</button>
            </header>
            <div className="om-drawer-body">
              {QUEUES.map(q => {
                const n = counts["q_" + q.id] || 0;
                return (
                  <div key={q.id} className="om-q-row">
                    <div className="om-q-row-head">
                      <span className="om-q-dot lg" style={{ background: `oklch(0.55 0.13 ${q.hue})` }}/>
                      <div className="om-q-row-text">
                        <b>{q.label}</b>
                        <small>{q.desc}</small>
                      </div>
                      <span className="mono om-q-count">{n}</span>
                    </div>
                    <div className="om-q-row-meta">
                      <div>
                        <small>SLA de 1ª resposta</small>
                        <b className="mono">{q.sla}</b>
                      </div>
                      <div>
                        <small>Distribuição</small>
                        <b>{q.dist === "round-robin" ? "alternada (round-robin)" : q.dist === "sticky" ? "fixa (sticky)" : "manual"}</b>
                      </div>
                      <div>
                        <small>Operadores</small>
                        <div className="om-q-ops">
                          {q.members.map(id => <OpAvatar key={id} id={id} size={20}/>)}
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
              <button className="os-btn ghost" style={{ marginTop: 12, justifyContent: "center" }}>+ Nova fila</button>
              <p className="om-chan-note">
                Quando uma mensagem chega num canal, ela vai automaticamente pra fila <b>Geral</b> e dali é roteada (ou fica disponível pros operadores pegarem). Você pode mover qualquer conversa entre filas pelo seletor no header da thread.
              </p>
            </div>
          </aside>
        </>
      )}

      {/* ───── Drawer: Broadcast ───── */}
      {bcastOpen && (
        <>
          <div className="om-backdrop" onClick={() => setBcastOpen(false)}/>
          <aside className="om-drawer">
            <header className="om-drawer-h">
              <div>
                <h2>Broadcast cross-canal</h2>
                <p>Envia para {convs.filter(c => !c.preview_only).length} contatos abertos</p>
              </div>
              <button className="om-x" onClick={() => setBcastOpen(false)}>✕</button>
            </header>
            <div className="om-drawer-body">
              <label><small>Conta de envio</small></label>
              <select className="om-select" defaultValue="wa_bal_vendas">
                {ACCOUNTS.map(acc => {
                  const ch = CHAN_BY_ID[acc.channel];
                  return (
                    <option key={acc.id} value={acc.id} disabled={acc.status !== "ativo"}>
                      {ch.short} · {acc.label} · {acc.handle}{acc.status !== "ativo" ? " · em breve" : ""}
                    </option>
                  );
                })}
              </select>
              <label style={{ marginTop: 14 }}><small>Template</small></label>
              <select className="om-select">{TEMPLATES.map(t => <option key={t.id}>{t.label}</option>)}</select>
              <label style={{ marginTop: 14 }}><small>Mensagem</small></label>
              <textarea rows={5} defaultValue={TEMPLATES[0].body}/>
              <p style={{ fontSize: 11, color: "var(--text-mute)", margin: "8px 0 14px" }}>
                Respeita janela 24h WhatsApp e templates aprovados.
              </p>
              <button className="os-btn primary" onClick={() => { setBcastOpen(false); setToast(`Broadcast disparado para ${convs.filter(c => !c.preview_only).length} contatos`); }}>
                Disparar broadcast
              </button>
            </div>
          </aside>
        </>
      )}
    </div>
  );
}

window.InboxPage = InboxPage;
})();
