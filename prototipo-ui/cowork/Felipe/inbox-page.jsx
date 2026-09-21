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
//    • Hue por canal: WA 295 (roxo canon · [W] 2026-06-19) · IG 0 · FB 250 · Email 280 · ML 95
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
  { id: "wa_baileys", label: "WhatsApp", short: "WhatsApp", glyph: "W", hue: 295, status: "ativo", group: "WhatsApp" },
  { id: "wa_meta", label: "WhatsApp Meta Cloud", short: "WA · Meta Cloud", glyph: "W", hue: 295, status: "em breve", group: "WhatsApp" },
  { id: "wa_zapi", label: "WhatsApp Z-API", short: "WA · Z-API", glyph: "W", hue: 295, status: "em breve", group: "WhatsApp" },
  { id: "ig", label: "Instagram DM", short: "Instagram", glyph: "◎", hue: 0, status: "em breve", group: "Social" },
  { id: "fb", label: "Facebook Messenger", short: "Messenger", glyph: "f", hue: 250, status: "em breve", group: "Social" },
  { id: "email", label: "Email (IMAP)", short: "Email", glyph: "@", hue: 280, status: "em breve", group: "Email" },
  { id: "ml", label: "Mercado Livre", short: "Mercado Livre", glyph: "M", hue: 95, status: "em breve", group: "Marketplace" }];

  const CHAN_BY_ID = Object.fromEntries(CHANNELS.map((c) => [c.id, c]));

  // ─── Contas (múltiplas por canal) ──────────────────────────────────────────
  const ACCOUNTS = [
  { id: "wa_bal_balcao", channel: "wa_baileys", label: "Balcão", handle: "+55 21 9 8001-0011", owner: "larissa", status: "ativo", health: "healthy" },
  { id: "wa_bal_vendas", channel: "wa_baileys", label: "Vendas", handle: "+55 21 9 8001-0022", owner: "wagner", status: "ativo", health: "degraded", sinceMin: 3 },
  { id: "wa_bal_fin", channel: "wa_baileys", label: "Financeiro", handle: "+55 21 9 8001-0033", owner: "eliana", status: "ativo", health: "healthy" },
  { id: "wa_meta_pri", channel: "wa_meta", label: "Comercial", handle: "+55 21 9 8001-0044", owner: "wagner", status: "em breve" },
  { id: "wa_zapi_pri", channel: "wa_zapi", label: "Marketing", handle: "+55 21 9 8001-0055", owner: "wagner", status: "em breve" },
  { id: "ig_main", channel: "ig", label: "@oimpresso", handle: "@oimpresso", owner: "wagner", status: "em breve" },
  { id: "fb_main", channel: "fb", label: "Oimpresso", handle: "fb.com/oimpresso", owner: "wagner", status: "em breve" },
  { id: "email_main", channel: "email", label: "Contato", handle: "contato@oimpresso.com.br", owner: "wagner", status: "em breve" },
  { id: "email_fin", channel: "email", label: "Financeiro", handle: "financeiro@oimpresso.com.br", owner: "eliana", status: "em breve" },
  { id: "ml_main", channel: "ml", label: "ML · loja", handle: "rotalivre · ML", owner: "larissa", status: "em breve" }];

  const ACC_BY_ID = Object.fromEntries(ACCOUNTS.map((a) => [a.id, a]));

  // ─── Saúde de canal (channel_health: healthy | degraded | down | never_checked) ──
  const HEALTH_VERB = {
    degraded: { label: "degradado", verb: "está degradado" },
    down: { label: "fora do ar", verb: "está fora do ar" }
  };
  // ícones lucide (mesma linguagem das demais svgs da tela)
  function WifiOffIco() {
    return <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.1" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M2 2l20 20" /><path d="M8.5 16.5a5 5 0 0 1 7 0" /><path d="M5 12.86A10 10 0 0 1 12 10" /><path d="M2 8.82a16 16 0 0 1 4.9-2.91" /><path d="M22 8.82a16 16 0 0 0-4.5-2.78" /><path d="M12 20h.01" /></svg>;
  }
  function PlugZapIco() {
    return <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.1" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M13 2 7 8h4l-1 6 6-6h-4l1-6Z" /><path d="M6.5 11 4 13.5a2.83 2.83 0 0 0 0 4 2.83 2.83 0 0 0 4 0L10.5 15" /><path d="M17.5 13 20 10.5a2.83 2.83 0 0 0 0-4 2.83 2.83 0 0 0-4 0L13.5 9" /></svg>;
  }
  function RefreshIco() {
    return <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 0 1 9-9 9 9 0 0 1 6.36 2.64L21 8" /><path d="M21 3v5h-5" /><path d="M21 12a9 9 0 0 1-9 9 9 9 0 0 1-6.36-2.64L3 16" /><path d="M3 21v-5h5" /></svg>;
  }

  // ─── Operadores (assignees) ────────────────────────────────────────────────
  const OPERATORS = {
    wagner: { id: "wagner", name: "Wagner", init: "WR", avc: 220 },
    larissa: { id: "larissa", name: "Larissa", init: "LM", avc: 30 },
    eliana: { id: "eliana", name: "Eliana", init: "EC", avc: 280 },
    felipe: { id: "felipe", name: "Felipe", init: "FO", avc: 60 }
  };
  const ME = "wagner";

  // ─── Filas de atendimento ──────────────────────────────────────────────────
  // Cada conversa entra numa fila ao chegar. Filas têm SLA + regras de
  // distribuição entre operadores subscritos.
  const QUEUES = [
  { id: "vendas", label: "Vendas", hue: 220, sla: "1h", members: ["wagner", "larissa"], dist: "round-robin", desc: "Leads novos, orçamentos" },
  { id: "posvenda", label: "Pós-venda", hue: 145, sla: "2h", members: ["larissa"], dist: "sticky", desc: "Acompanhamento de OS aberta" },
  { id: "fin", label: "Financeiro", hue: 280, sla: "4h", members: ["eliana"], dist: "manual", desc: "Cobranças, boletos, NF" },
  { id: "prod", label: "Produção", hue: 30, sla: "2h", members: ["felipe"], dist: "round-robin", desc: "Dúvidas técnicas do cliente" },
  { id: "geral", label: "Geral", hue: 60, sla: "30min", members: ["wagner", "larissa", "eliana"], dist: "round-robin", desc: "Fallback — quando não rotear" }];

  const Q_BY_ID = Object.fromEntries(QUEUES.map((q) => [q.id, q]));

  // ─── Macros (atalhos do operador) ──────────────────────────────────────────
  const MACROS = [
  { slash: "/orc", title: "Enviar link de orçamento", body: "Segue o link do seu orçamento: https://oimpresso.com.br/orc/{numero}\nQualquer ajuste me avise!" },
  { slash: "/abrir", title: "Abrir conversa", body: "Olá! Em que posso te ajudar hoje?" },
  { slash: "/preco", title: "Solicitar dados pra preço", body: "Pra fechar o valor preciso de: quantidade, medidas e prazo de entrega. Tem CNPJ pra emitir nota?" },
  { slash: "/horario", title: "Horário de atendimento", body: "Funcionamos seg–sex 9h–18h e sábado 9h–13h. Av. Brasil, 1240 — Centro." },
  { slash: "/agradecer", title: "Agradecer", body: "Obrigado pela preferência! Qualquer coisa, estamos por aqui." },
  { slash: "/cobrar", title: "Lembrete de cobrança", body: "Olá! Lembramos que o boleto vence amanhã. Pode pagar via PIX ou boleto.", actions: ["tag:cobrança", "assign:eliana", "queue:fin"] },
  { slash: "/pronto", title: "Pedido pronto", body: "Seu pedido está pronto pra retirada. Funcionamos 9h–18h. 📍 Av. Brasil, 1240." }];


  // ─── Status ────────────────────────────────────────────────────────────────
  const STATUSES = [
  { id: "abertas", label: "Abertas", hint: "tudo em atendimento" },
  { id: "pendentes", label: "Pendentes", hint: "tem msg não lida do cliente" },
  { id: "aguardando", label: "Aguardando", hint: "esperando resposta do cliente" },
  { id: "resolvidas", label: "Resolvidas", hint: "fechadas" }];


  // ─── Templates (Cloud API) ─────────────────────────────────────────────────
  const TEMPLATES = [
  { id: "ok", label: "✓ Pronto pra retirada", body: "Olá! Seu pedido está pronto. Funcionamos de 9h às 18h.", channels: ["wa_baileys", "wa_meta", "wa_zapi"] },
  { id: "art", label: "Aprovação de arte", body: "Segue a arte para aprovação. Responda OK ou indique ajustes.", channels: ["wa_baileys", "wa_meta", "wa_zapi", "email"] },
  { id: "pay", label: "Lembrete cobrança", body: "Olá! Lembramos que o boleto vence em breve. Pode pagar via PIX ou boleto.", channels: ["wa_baileys", "wa_meta", "wa_zapi", "email"] },
  { id: "ride", label: "Saiu pra entrega", body: "Seu pedido saiu para entrega agora e chega ainda hoje.", channels: ["wa_baileys", "wa_meta", "wa_zapi"] }];

  // ─── Templates Jana (prompts internos de IA — geram resposta a partir do contexto) ──────
  const JANA_TEMPLATES = [
  { id: "orc", label: "Pedir dados pro orçamento", body: "Olá! Pra montar seu orçamento, me passa: o que vai imprimir, quantidade, medidas e prazo de entrega?" },
  { id: "status", label: "Status do pedido em produção", body: "Seu pedido está em produção e segue dentro do prazo. Te aviso assim que estiver pronto pra retirada." },
  { id: "followup", label: "Retomar conversa parada", body: "Oi! Passando pra saber se ainda tem interesse no orçamento que enviei. Posso ajustar alguma coisa?" },
  { id: "obrigado", label: "Agradecer e fidelizar", body: "Obrigado pela preferência! Qualquer material novo que precisar, é só chamar por aqui." }];


  // ─── Conversas mock ────────────────────────────────────────────────────────
  const CONVS_INIT = [
  { id: "c1", account: "wa_bal_vendas", queue: "vendas", av: "RL", avc: 145, photo: "inbox-photo-c1.png", name: "Renato Lopes", company: "Padaria Estrela",
    handle: "+55 21 9 8112-4400", assignee: "wagner", tags: ["cliente-fiel"],
    lastFrom: "them", preview: "Posso retirar amanhã 9h?", unread: 2, online: true, status: "pendentes",
    ctx: { os: "#4819 · Cardápios A4", saldo: "R$ 380 a receber", history: "4 pedidos · R$ 1.420 LTV", lastTouch: "11:48 hoje" },
    msgs: [
    { d: "ontem", who: "them", t: "Boa tarde! Os cardápios ficaram prontos?", time: "16:20" },
    { d: "ontem", who: "me", t: "Sim Renato! Estão prontos. Pode passar quando quiser.", time: "16:25" },
    { d: "hoje", who: "me", t: "Cliente atrasou 3x no último pedido. Exigir PIX antes de soltar peça.", time: "08:30", internal: true },
    { d: "hoje", who: "them", t: "Posso passar pra retirar amanhã 9h?", time: "11:48" }]
  },
  { id: "c2", account: "wa_bal_vendas", queue: "posvenda", av: "CD", avc: 30, photo: "inbox-photo-c2.png", name: "Camila Diniz", company: "Acme Comércio Ltda",
    handle: "+55 21 9 9712-0090", assignee: "wagner", tags: ["arte-aprovada"],
    lastFrom: "me", preview: "Você: arte aprovada ✓", unread: 0, status: "aguardando",
    ctx: { os: "#4821 · Banner 3×2m", saldo: "R$ 0 (faturado)", history: "12 pedidos · R$ 8.420 LTV", lastTouch: "10:32 hoje" },
    msgs: [
    { d: "hoje", who: "them", t: "Recebi a prova, mas o azul tá meio escuro. Pode ajustar?", time: "09:14" },
    { d: "hoje", who: "me", t: "Pode deixar Camila, já mando a v2.", time: "09:20" },
    { d: "hoje", who: "me", t: "Arte aprovada ✓", time: "10:32" }]
  },
  { id: "c3", account: "wa_bal_balcao", queue: "vendas", av: "DV", avc: 220, photo: "inbox-photo-c3.png", name: "Diego Vasconcellos", company: "TechPro",
    handle: "+55 21 9 9301-7714", assignee: "larissa", tags: ["novo"],
    lastFrom: "them", preview: "Quanto fica em 200un?", unread: 1, status: "pendentes",
    ctx: { os: null, saldo: "R$ 1.840 a receber", history: "3 pedidos · R$ 4.200 LTV", lastTouch: "13:05 hoje" },
    msgs: [
    { d: "hoje", who: "them", t: "Olá! Adesivos novos, 8×8cm recortado.", time: "12:50" },
    { d: "hoje", who: "them", t: "Quanto fica em 200un?", time: "13:05" }]
  },
  { id: "c4", account: "wa_bal_fin", queue: "fin", av: "MV", avc: 295, photo: "inbox-photo-c4.png", name: "Marcos Vital", company: "Posto BR Centro",
    handle: "+55 21 9 8800-1240", assignee: "eliana", tags: ["cobrança"],
    lastFrom: "them", preview: "Obrigado! Recebi.", unread: 0, status: "abertas",
    ctx: { os: "#4790 · Lona Front-Light", saldo: "R$ 5.620", history: "1 pedido · R$ 5.620 LTV", lastTouch: "ontem 17:48" },
    msgs: [
    { d: "ontem", who: "me", t: "Marcos, peça pronta e nota emitida.", time: "17:42" },
    { d: "ontem", who: "them", t: "Obrigado! Recebi.", time: "17:48" }]
  },

  // Previews dos canais em breve
  { id: "c5", account: "wa_meta_pri", queue: "vendas", av: "FE", avc: 60, name: "Fátima Estrela", company: "Padaria Estrela",
    handle: "+55 21 9 8112-4400 · Cloud API", assignee: null, tags: [],
    lastFrom: "them", preview: "Templates aprovados pela Meta?", unread: 0, preview_only: true, status: "abertas",
    ctx: { os: null, saldo: "—", history: "—", lastTouch: "preview" },
    msgs: [{ d: "hoje", who: "them", t: "Quando rolar o Meta Cloud aqui? Quero usar templates oficiais.", time: "—" }] },
  { id: "c6", account: "ig_main", queue: "vendas", av: "ST", avc: 12, name: "@studio.foco", company: "Studio Foco",
    handle: "@studio.foco · DM", assignee: null, tags: [],
    lastFrom: "them", preview: "Curti! Faz orçamento?", unread: 0, preview_only: true, status: "abertas",
    ctx: { os: null, saldo: "—", history: "—", lastTouch: "preview" },
    msgs: [{ d: "hoje", who: "them", t: "Curti o trabalho da fachada. Faz orçamento por aqui?", time: "—" }] },
  { id: "c7", account: "email_main", queue: "vendas", av: "@", avc: 280, name: "compras@imobhorizonte.com.br", company: "Imobiliária Horizonte",
    handle: "Re: Cotação 200 placas PS · 3mm", assignee: null, tags: [],
    lastFrom: "them", preview: "Segue PO #2418 em anexo", unread: 0, preview_only: true, status: "abertas",
    ctx: { os: null, saldo: "—", history: "—", lastTouch: "preview" },
    msgs: [{ d: "hoje", who: "them", t: "Prezados, segue PO #2418 anexo. Aguardo previsão de entrega.\n\n— Luís, compras", time: "—" }] },
  { id: "c8", account: "ml_main", queue: "posvenda", av: "ML", avc: 95, name: "ML · pedido #4019887214", company: "Mercado Livre",
    handle: "Adesivo personalizado A4 · 10un", assignee: null, tags: [],
    lastFrom: "them", preview: "Posso retirar no balcão?", unread: 0, preview_only: true, status: "abertas",
    ctx: { os: null, saldo: "—", history: "—", lastTouch: "preview" },
    msgs: [{ d: "hoje", who: "them", t: "Comprador pergunta: posso retirar no balcão em vez do envio?", time: "—" }] }];


  // ─── Componentes auxiliares ────────────────────────────────────────────────
  function ChannelGlyph({ ch, size = 14 }) {
    if (!ch) return null;
    return (
      <span className="om-cg" style={{ width: size, height: size, fontSize: size * 0.62, background: `oklch(0.62 0.14 ${ch.hue})` }} title={ch.label}>
      {ch.glyph}
    </span>);

  }

  function OpAvatar({ id, size = 18 }) {
    if (!id) return null;
    const op = OPERATORS[id];
    if (!op) return null;
    return (
      <span className="om-op" style={{ width: size, height: size, fontSize: size * 0.45, background: `oklch(0.55 0.12 ${op.avc})` }} title={`Atribuído a ${op.name}`}>
      {op.init}
    </span>);

  }

  function QueueChip({ q, size = "sm" }) {
    if (!q) return null;
    return (
      <span className={"om-q-chip " + size}
      style={{ background: `oklch(0.95 0.04 ${q.hue})`, color: `oklch(0.30 0.13 ${q.hue})`, borderColor: `oklch(0.85 0.08 ${q.hue})` }}>
      {q.label}
    </span>);

  }

  // QR fake determinístico pro re-pareamento WhatsApp — SÓ protótipo; o backend
  // (whatsmeow/Baileys) serve o QR real via socket. 21×21 com os 3 finder squares.
  function qrMatrix(seed) {
    const N = 21,key = seed || "x";
    let s = 0;for (let i = 0; i < key.length; i++) s = s * 31 + key.charCodeAt(i) >>> 0;
    const rnd = () => {s = s * 1664525 + 1013904223 >>> 0;return s / 4294967296;};
    const m = Array.from({ length: N }, () => Array(N).fill(false));
    for (let r = 0; r < N; r++) for (let c = 0; c < N; c++) m[r][c] = rnd() > 0.52;
    const stamp = (or, oc) => {
      for (let r = -1; r <= 7; r++) for (let c = -1; c <= 7; c++) {
        const rr = or + r,cc = oc + c;
        if (rr < 0 || cc < 0 || rr >= N || cc >= N) continue;
        const inb = r >= 0 && r <= 6 && c >= 0 && c <= 6;
        const edge = inb && (r === 0 || r === 6 || c === 0 || c === 6);
        const core = r >= 2 && r <= 4 && c >= 2 && c <= 4;
        m[rr][cc] = inb ? edge || core : false;
      }
    };
    stamp(0, 0);stamp(0, N - 7);stamp(N - 7, 0);
    return m;
  }

  function InboxPage() {
    const [healthDismissed, setHealthDismissed] = useState(false);
    const [healthFixed, setHealthFixed] = useState({}); // contas reconectadas nesta sessão (mock; backend faz de verdade)
    const [reconnectAcc, setReconnectAcc] = useState(null); // conta sendo re-pareada (abre modal QR)
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
    const [tplDrawerOpen, setTplDrawerOpen] = useState(false); // drawer Templates unificado (Jana+HSM numa só tela)
    const [tplLib, setTplLib] = useState("all"); // filtro da biblioteca: all | jana | hsm
    const [ctxOpen, setCtxOpen] = useState(() => typeof window !== "undefined" ? window.innerWidth >= 1440 : true); // Contexto recolhível (auto-recolhe em tela pequena)
    const [ctxDrawerOpen, setCtxDrawerOpen] = useState(false); // Contexto agora abre como drawer lateral (some a coluna fixa · [W] 2026-06-19)
    // No mobile (≤1100px) a coluna Contexto vira aba cheia — nunca a tira de 44px ([W] 2026-06-17)
    const [isMobileShell, setIsMobileShell] = useState(() => typeof window !== "undefined" && window.matchMedia("(max-width: 1100px)").matches);
    useEffect(() => {
      const mq = window.matchMedia("(max-width: 1100px)");
      const h = () => setIsMobileShell(mq.matches);
      mq.addEventListener("change", h);
      return () => mq.removeEventListener("change", h);
    }, []);
    const ctxExpanded = ctxOpen || isMobileShell;
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [statusMenuOpen, setStatusMenuOpen] = useState(false);
    const [filter, setFilter] = useState("all"); // canal
    const [accFilter, setAccFilter] = useState("all"); // conta
    const [queueFilter, setQueueFilter] = useState("all"); // fila
    const [assigneeFilter, setAssigneeFilter] = useState("all"); // atribuição: all | assigned | unassigned
    const [tagFilter, setTagFilter] = useState("all"); // tag
    const [orderBy, setOrderBy] = useState("recent"); // recent | oldest
    const [statusFilter, setStatusFilter] = useState("abertas");
    const [search, setSearch] = useState("");
    const [toast, setToast] = useState(null);
    // V2 — Refino #1 do Método KB-9.75
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [cheatOpen, setCheatOpen] = useState(false);
    const [mobileView, setMobileView] = useState("list"); // list | thread | ctx
    const { favs, isFav, toggleFav } = window.useInboxFavs ? window.useInboxFavs() : { favs: [], isFav: () => false, toggleFav: () => {} };
    // V2 Refino #2 — IA dentro do thread
    const [summarizeOpen, setSummarizeOpen] = useState(false);
    const [askOpen, setAskOpen] = useState(false);
    const [askInitialQuery, setAskInitialQuery] = useState("");
    // V2 Refino #3 — Curadoria + Guia
    const [tbOpen, setTbOpen] = useState(false);
    const [pathsOpen, setPathsOpen] = useState(false);
    const msgC = window.useMsgComments ? window.useMsgComments() : { add: () => {}, remove: () => {}, forMsg: () => [] };
    // V2 Refino #4 — Distribuição/Saída
    const [transcriptOpen, setTranscriptOpen] = useState(false);
    const [presenterOpen, setPresenterOpen] = useState(false);
    const [varMenuOpen, setVarMenuOpen] = useState(false);
    const [lightboxMedia, setLightboxMedia] = useState(null);
    const paletteRef = useRef(null);
    const threadRef = useRef(null);
    const inputRef = useRef(null);

    useEffect(() => {if (!toast) return;const t = setTimeout(() => setToast(null), 2400);return () => clearTimeout(t);}, [toast]);

    // Atalhos de teclado (V2)
    if (window.useInboxKeyboard) {
      window.useInboxKeyboard({
        onPalette: () => setPaletteOpen(true),
        onCheat: () => setCheatOpen(true),
        onEsc: () => {setPaletteOpen(false);setCheatOpen(false);setShowTpl(false);setShowMacros(false);setSlashOpen(false);},
        onNext: () => {
          if (!filteredConvs.length) return;
          const i = filteredConvs.findIndex((c) => c.id === selId);
          const nx = filteredConvs[Math.min(filteredConvs.length - 1, i + 1)];
          if (nx) {setSelId(nx.id);if (mobileView === "list") setMobileView("thread");}
        },
        onPrev: () => {
          if (!filteredConvs.length) return;
          const i = filteredConvs.findIndex((c) => c.id === selId);
          const pv = filteredConvs[Math.max(0, i - 1)];
          if (pv) {setSelId(pv.id);if (mobileView === "list") setMobileView("thread");}
        },
        onToggleFav: () => {
          if (!selId) return;
          toggleFav(selId);
          setToast(isFav(selId) ? "Removido dos favoritos" : "Favoritado");
        },
        onFocusInput: () => {inputRef.current && inputRef.current.focus();if (mobileView !== "thread") setMobileView("thread");},
        onResolve: () => {if (conv && !isPreview) resolve();},
        deps: [filteredConvs, selId, mobileView, isFav]
      });
    }

    // ── Filtragem ─────────────────────────────────────────────────────
    const filteredConvs = useMemo(() => {
      const out = convs.filter((c) => {
        const acc = ACC_BY_ID[c.account];
        if (filter !== "all" && acc?.channel !== filter) return false;
        if (accFilter !== "all" && c.account !== accFilter) return false;
        if (queueFilter !== "all" && c.queue !== queueFilter) return false;
        if (assigneeFilter === "assigned" && !c.assignee) return false;
        if (assigneeFilter === "unassigned" && c.assignee) return false;
        if (tagFilter !== "all" && !(c.tags || []).includes(tagFilter)) return false;
        if (statusFilter === "pendentes" && !(c.unread > 0)) return false;
        if (statusFilter === "aguardando" && c.lastFrom !== "me") return false;
        if (statusFilter === "resolvidas" && c.status !== "resolvidas") return false;
        if (statusFilter === "abertas" && c.status === "resolvidas") return false;
        if (search.trim()) {
          const q = search.trim().toLowerCase();
          const blob = `${c.name} ${c.company} ${c.preview}`.toLowerCase();
          if (!blob.includes(q)) return false;
        }
        return true;
      });
      return orderBy === "oldest" ? out.slice().reverse() : out;
    }, [convs, filter, accFilter, queueFilter, assigneeFilter, tagFilter, orderBy, statusFilter, search]);

    const conv = convs.find((c) => c.id === selId);
    const convAcc = conv ? ACC_BY_ID[conv.account] : null;
    const convChannel = convAcc ? CHAN_BY_ID[convAcc.channel] : null;
    const convQueue = conv ? Q_BY_ID[conv.queue] : null;
    const isPreview = conv?.preview_only;

    const accountsForFilter = useMemo(() =>
    filter === "all" ? [] : ACCOUNTS.filter((a) => a.channel === filter),
    [filter]);

    const allTags = useMemo(() => [...new Set(convs.flatMap((c) => c.tags || []))], [convs]);

    // Saúde efetiva da conta (reconectar nesta sessão zera o alerta).
    const effHealth = (a) => !a ? "never_checked" : healthFixed[a.id] ? "healthy" : a.health || "never_checked";
    const reconnect = (accId, label) => {setHealthFixed((m) => ({ ...m, [accId]: true }));setToast(`Reconectado · WhatsApp · ${label}`);};
    const openReconnect = (acc) => setReconnectAcc(acc); // mostra o QR de re-pareamento (resposta ao Wagner: sim, mostra o QR)

    // Contas ativas com problema de conexão + nº de conversas (abertas) afetadas.
    // No repo real isto vem de `availableAccounts[].channel_health` do CaixaUnificadaController.
    const unhealthyAccounts = useMemo(() =>
    ACCOUNTS.
    filter((a) => a.status === "ativo" && (healthFixed[a.id] ? "healthy" : a.health || "never_checked") !== "healthy").
    map((a) => ({
      ...a,
      ch: CHAN_BY_ID[a.channel],
      affected: convs.filter((c) => c.account === a.id && c.status !== "resolvidas").length,
      sinceMin: a.sinceMin || 6
    })),
    [convs, healthFixed]);

    useEffect(() => {if (threadRef.current) threadRef.current.scrollTop = threadRef.current.scrollHeight;}, [selId, conv?.msgs.length]);
    useEffect(() => {if (conv && conv.unread > 0) setConvs((cs) => cs.map((c) => c.id === selId ? { ...c, unread: 0 } : c));}, [selId]);
    useEffect(() => {
      if (filteredConvs.length && !filteredConvs.find((c) => c.id === selId)) setSelId(filteredConvs[0].id);
    }, [filter, accFilter, queueFilter, assigneeFilter, statusFilter, search]);
    useEffect(() => {
      if (accFilter !== "all" && ACC_BY_ID[accFilter]?.channel !== filter && filter !== "all") setAccFilter("all");
    }, [filter]);

    const counts = useMemo(() => {
      const m = { all: convs.length };
      for (const ch of CHANNELS) m[ch.id] = convs.filter((c) => ACC_BY_ID[c.account]?.channel === ch.id).length;
      for (const acc of ACCOUNTS) m["acc_" + acc.id] = convs.filter((c) => c.account === acc.id).length;
      for (const q of QUEUES) m["q_" + q.id] = convs.filter((c) => c.queue === q.id).length;
      for (const s of STATUSES) m["st_" + s.id] = convs.filter((c) => {
        if (s.id === "pendentes") return c.unread > 0;
        if (s.id === "aguardando") return c.lastFrom === "me";
        if (s.id === "resolvidas") return c.status === "resolvidas";
        return c.status !== "resolvidas"; // abertas
      }).length;
      return m;
    }, [convs]);

    const totalUnread = convs.reduce((s, c) => s + c.unread, 0);
    const activeAccCount = ACCOUNTS.filter((a) => a.status === "ativo").length;
    const headerSub = `${activeAccCount} contas ativas · ${QUEUES.length} filas · ${convs.length} abertas${totalUnread > 0 ? ` · ${totalUnread} não lidas` : ""}`;

    // Filtros avançados ativos (dentro do popover Filtros) — pra badge no botão
    const advFilterCount = [
    filter !== "all",
    accFilter !== "all",
    queueFilter !== "all",
    assigneeFilter !== "all",
    tagFilter !== "all",
    statusFilter !== "abertas",
    orderBy !== "recent"].
    filter(Boolean).length;
    const anyFilterActive = filter !== "all" || accFilter !== "all" || queueFilter !== "all" || assigneeFilter !== "all" || tagFilter !== "all" || orderBy !== "recent" || statusFilter !== "abertas";

    const clearFilters = () => {
      setFilter("all");setAccFilter("all");setQueueFilter("all");setAssigneeFilter("all");setTagFilter("all");setOrderBy("recent");setStatusFilter("abertas");
    };

    const validTemplates = TEMPLATES.filter((t) => !convChannel || t.channels.includes(convChannel.id));
    const slashQuery = draft.startsWith("/") ? draft.toLowerCase() : null;
    const slashMatches = slashQuery ? MACROS.filter((m) => m.slash.startsWith(slashQuery) || m.title.toLowerCase().includes(slashQuery.slice(1))) : [];

    // ── Ações ─────────────────────────────────────────────────────────
    const sendMsg = (text, opts = {}) => {
      const asInternal = opts.internal ?? internalMode;
      if (isPreview && !asInternal) {setToast(`Canal ${convChannel.label} em homologação — envio bloqueado`);return;}
      const t = (text || draft).trim();
      if (!t) return;
      setConvs((cs) => cs.map((c) => c.id !== selId ? c : {
        ...c,
        msgs: [...c.msgs, { d: "hoje", who: "me", t, time: "agora", internal: asInternal }],
        lastFrom: asInternal ? c.lastFrom : "me",
        preview: asInternal ? c.preview : "Você: " + t
      }));
      setDraft("");
      setShowTpl(false);setShowMacros(false);setSlashOpen(false);
    };

    const applyMacro = (macro) => {
      setDraft(macro.body);
      setShowMacros(false);setSlashOpen(false);
      inputRef.current?.focus();
      if (macro.actions?.length) {
        const tags = macro.actions.filter((a) => a.startsWith("tag:")).map((a) => a.slice(4));
        const assign = macro.actions.find((a) => a.startsWith("assign:"))?.slice(7);
        const queue = macro.actions.find((a) => a.startsWith("queue:"))?.slice(6);
        setConvs((cs) => cs.map((c) => c.id !== selId ? c : {
          ...c,
          tags: Array.from(new Set([...(c.tags || []), ...tags])),
          ...(assign ? { assignee: assign } : {}),
          ...(queue ? { queue } : {})
        }));
        setToast(`Macro: ${macro.title}${assign ? ` · → ${OPERATORS[assign]?.name}` : ""}${queue ? ` · fila ${Q_BY_ID[queue]?.label}` : ""}`);
      }
    };

    const resolve = () => {
      setConvs((cs) => cs.map((c) => c.id === selId ? { ...c, status: "resolvidas" } : c));
      setToast(`Conversa com ${conv.name} resolvida`);
      const next = filteredConvs.find((c) => c.id !== selId);
      if (next) setSelId(next.id);
    };

    const reassign = (opId) => {
      setConvs((cs) => cs.map((c) => c.id === selId ? { ...c, assignee: opId || null } : c));
      if (opId) setToast(`→ ${OPERATORS[opId].name}`);
    };

    const moveToQueue = (qId) => {
      setConvs((cs) => cs.map((c) => c.id === selId ? { ...c, queue: qId } : c));
      setToast(`Movido pra fila ${Q_BY_ID[qId].label}`);
    };

    const applyTemplate = (t) => {
      setDraft(t.body);
      setTplDrawerOpen(false);
      setShowTpl(false);setShowMacros(false);setSlashOpen(false);
      setToast(`Template aplicado · ${t.label}`);
      setTimeout(() => inputRef.current && inputRef.current.focus(), 30);
    };

    // ── Render ────────────────────────────────────────────────────────
    return (
      <div className="os-page om-page" data-screen-label="01 Caixa unificada" data-testid="caixa-unif-page">
      <div className="os-page-h">
        <div className="os-page-h-l" data-comment-anchor="09922f4ed2-div-431-9">
          <h1 data-comment-anchor="0940f70354-h1-532-11">Atendimento
</h1>
          <p>{headerSub}</p>
        </div>
        <div className="os-page-h-r">
          {/* Templates — biblioteca unificada (Jana IA + HSM Meta) numa só tela.
                                                     [W]: "os dois em uma única tela, não quero dois menus diferentes". */}
          <button className="os-btn ghost"
            onClick={() => setTplDrawerOpen(true)}
            data-testid="caixa-unif-topnav-templates">
            Templates
          </button>
          {/* TODO US-WA-301: Filas DB + drawer config (hoje hardcoded em QUEUES array) */}
          <button className="os-btn ghost" onClick={() => setQueuesOpen((v) => !v)}
            data-testid="caixa-unif-topnav-filas">Filas</button>
          {/* TODO US-WA-304: Drawer in-place vs link pra /atendimento/canais (decisão do roadmap §5) */}
          <button className="os-btn ghost" onClick={() => setChSwitcherOpen((v) => !v)}
            data-testid="caixa-unif-topnav-canais">Canais</button>
          {window.InboxTroubleDialog &&
            <button className="os-btn ghost" onClick={() => setTbOpen(true)}>Troubleshooters</button>
            }
          {window.InboxPathsDialog &&
            <button className="os-btn ghost" onClick={() => setPathsOpen(true)}>Trilhas</button>
            }
          {/* TODO US-WA-307: + Nova conversa (ContactPickerModal + template inicial) */}
          <button className="os-btn primary"
            data-testid="caixa-unif-topnav-nova">+ Nova conversa</button>
        </div>
      </div>

      {/* Canais + contas migraram pro popover Filtros (coluna Conversas) — [W] 2026-06-16:
                      "troque a linha pro filtro, do lado da conversa". Sem faixa horizontal no topo. */}

      {window.InboxMobileTabs &&
        <window.InboxMobileTabs
          view={mobileView}
          setView={setMobileView}
          counts={{ list: filteredConvs.length }}
          hasSelected={!!conv} />
        }

      <div className="om-shell no-ctx" data-mobile-view={mobileView}>
        {/* ───── Coluna: Conversas (lista + busca + status) ───── */}
        <aside className="om-list-c">
          <div className="om-list-h">
            <b>Conversas</b>
            <span className="mono">{filteredConvs.length}</span>
            <div className="om-list-h-tools">
              {/* Filtros — inclui Status (movido pra cá · [W]: "põe no filtro, fica mais limpo o layout") */}
              <div className="om-pop-anchor">
                <button
                    className={"om-list-h-btn icon" + (filtersOpen ? " on" : advFilterCount ? " has-filters" : "")}
                    onClick={() => {setFiltersOpen((v) => !v);setStatusMenuOpen(false);}}
                    aria-expanded={filtersOpen}
                    title="Filtros"
                    data-testid="caixa-unif-filtros-btn">
                  <svg className="om-funnel" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" /></svg>
                  <span className="om-list-h-btn-lbl">Filtros</span>
                  {advFilterCount > 0 && <span className="om-flt-badge">{advFilterCount}</span>}
                </button>
                {filtersOpen &&
                  <div className="om-filter-pop om-pop-float wide" data-testid="caixa-unif-filtros-pop">
                    <div className="om-filter-pop-h">
                      <b>Filtros</b>
                      <button className="om-x sm" onClick={() => setFiltersOpen(false)} aria-label="Fechar">✕</button>
                    </div>
                    <div className="om-flt-group" data-comment-anchor="309344561e-select-555-13">
                      <small>Status</small>
                      <div className="om-flt-pills">
                        {STATUSES.map((s) =>
                        <button key={s.id}
                        className={"om-flt-pill" + (statusFilter === s.id ? " sel" : "")}
                        onClick={() => setStatusFilter(s.id)}
                        title={s.hint}
                        data-testid={`caixa-unif-status-${s.id}`}>
                          {s.label} <em>{counts["st_" + s.id] || 0}</em>
                        </button>
                        )}
                      </div>
                    </div>
                    <div className="om-flt-group">
                      <small>Canal</small>
                      <div className="om-flt-pills">
                        <button className={"om-flt-pill" + (filter === "all" ? " sel" : "")}
                        onClick={() => {setFilter("all");setAccFilter("all");}}
                        data-testid="caixa-unif-channel-chip-all">
                          Todos <em data-comment-anchor="2ac6cd0e54-em-491-29">{counts.all}</em>
                        </button>
                        {CHANNELS.map((ch) => {
                          const isComing = ch.status === "em breve";
                          return (
                            <button key={ch.id}
                            className={"om-flt-pill" + (filter === ch.id ? " sel" : "") + (isComing ? " muted" : "")}
                            onClick={() => setFilter(ch.id)}
                            title={ch.label}
                            data-testid={`caixa-unif-channel-chip-${ch.id}`}>
                              <ChannelGlyph ch={ch} size={12} />
                              {ch.short}
                              {isComing ? <em className="soon">em breve</em> : <em>{counts[ch.id] || 0}</em>}
                            </button>);
                        })}
                      </div>
                    </div>
                    {filter !== "all" && accountsForFilter.length > 1 &&
                    <div className="om-flt-group">
                        <small>Conta</small>
                        <div className="om-flt-pills">
                          <button className={"om-flt-pill" + (accFilter === "all" ? " sel" : "")}
                        onClick={() => setAccFilter("all")}
                        data-testid="caixa-unif-account-chip-all">Todas <em>{accountsForFilter.length}</em></button>
                          {accountsForFilter.map((acc) => {
                          const isComing = acc.status === "em breve";
                          return (
                            <button key={acc.id}
                            className={"om-flt-pill" + (accFilter === acc.id ? " sel" : "") + (isComing ? " muted" : "")}
                            onClick={() => setAccFilter(acc.id)}
                            title={`${acc.label} · ${acc.handle}`}
                            data-testid={`caixa-unif-account-chip-${acc.id}`}>
                                {acc.label}
                                {isComing ? <em className="soon">em breve</em> : <em>{counts["acc_" + acc.id] || 0}</em>}
                              </button>);
                        })}
                        </div>
                      </div>
                    }
                    <div className="om-flt-group">
                      <small>Fila</small>
                      <div className="om-flt-pills">
                        <button className={"om-flt-pill" + (queueFilter === "all" ? " sel" : "")}
                        onClick={() => setQueueFilter("all")}>Todas <em>{convs.length}</em></button>
                        {QUEUES.map((q) =>
                        <button key={q.id}
                        className={"om-flt-pill" + (queueFilter === q.id ? " sel" : "")}
                        onClick={() => setQueueFilter(q.id)}
                        data-testid={`caixa-unif-fila-${q.id}`}>
                            <span className="om-q-dot" style={{ background: `oklch(0.62 0.13 ${q.hue})` }} />
                            {q.label} <em>{counts["q_" + q.id] || 0}</em>
                          </button>
                        )}
                      </div>
                    </div>
                    <div className="om-flt-group">
                      <small>Atribuição</small>
                      <div className="om-flt-pills">
                        {[["all", "Todas"], ["assigned", "Atribuídas"], ["unassigned", "Sem dono"]].map(([id, label]) =>
                        <button key={id}
                        className={"om-flt-pill" + (assigneeFilter === id ? " sel" : "")}
                        onClick={() => setAssigneeFilter(id)}
                        data-testid={`caixa-unif-atrib-${id}`}>{label}</button>
                        )}
                      </div>
                    </div>
                    {allTags.length > 0 &&
                    <div className="om-flt-group">
                        <small>Tags</small>
                        <div className="om-flt-pills">
                          <button className={"om-flt-pill" + (tagFilter === "all" ? " sel" : "")}
                        onClick={() => setTagFilter("all")}>Todas</button>
                          {allTags.map((t) =>
                        <button key={t}
                        className={"om-flt-pill" + (tagFilter === t ? " sel" : "")}
                        onClick={() => setTagFilter(t)}
                        data-testid={`caixa-unif-tag-${t}`}>{t}</button>
                        )}
                        </div>
                      </div>
                    }
                    <div className="om-flt-group">
                      <small>Ordenar por</small>
                      <div className="om-flt-pills">
                        {[["recent", "Última msg"], ["oldest", "Mais antigas"]].map(([id, label]) =>
                        <button key={id}
                        className={"om-flt-pill" + (orderBy === id ? " sel" : "")}
                        onClick={() => setOrderBy(id)}
                        data-testid={`caixa-unif-order-${id}`}>{label}</button>
                        )}
                      </div>
                    </div>
                    <div className="om-filter-pop-f">
                      <button className="om-active-clear" onClick={clearFilters} disabled={!anyFilterActive}>Limpar filtros</button>
                    </div>
                  </div>
                  }
              </div>
            </div>
          </div>

          {filtersOpen &&
            <div className="om-pop-backdrop" onClick={() => setFiltersOpen(false)} />
            }

          {/* Busca inline */}
          <div className="om-list-search">
            <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Buscar nome, empresa, texto…"
                aria-label="Buscar nas conversas"
                data-testid="caixa-unif-search" />
            {search && <button className="om-list-search-x" onClick={() => setSearch("")}
              aria-label="Limpar busca" title="Limpar busca">✕</button>}
          </div>

          {/* Banner de saúde de canal — contas ativas degradadas/fora do ar.
                    Reconectar abre o drawer "Canais e contas". Dispensável até a próxima verificação. */}
          {!healthDismissed && unhealthyAccounts.length > 0 && (() => {
              const worst = unhealthyAccounts.some((a) => a.health === "down") ? "err" : "warn";
              const multi = unhealthyAccounts.length > 1;
              const total = unhealthyAccounts.reduce((s, a) => s + a.affected, 0);
              const a0 = unhealthyAccounts[0];
              return (
                <div className={"om-health-banner compact " + worst} role="status" aria-live="polite" data-testid="caixa-unif-health-banner">
                <div className="om-hb-head">
                  <span className="om-hb-ico">{worst === "err" ? <PlugZapIco /> : <WifiOffIco />}</span>
                  <div className="om-hb-txt">
                    {multi ?
                      <>
                        <b>{unhealthyAccounts.length} canais com problema de conexão</b>
                        <span>{total} conversas podem não receber mensagens novas.</span>
                      </> :

                      <>
                        <b>{a0.ch.short} · {a0.label} {HEALTH_VERB[a0.health].verb}.</b>
                        <span>
                          {a0.health === "down" ? "Mensagens novas não estão chegando." : "Sincronização lenta — pode haver atraso."}{" "}
                          {a0.affected} {a0.affected === 1 ? "conversa afetada" : "conversas afetadas"}.
                          <span className="mono"> · há {a0.sinceMin} min</span>
                        </span>
                      </>
                      }
                  </div>
                  <button className="om-hb-x" onClick={() => setHealthDismissed(true)} title="Dispensar até a próxima verificação" aria-label="Dispensar">✕</button>
                </div>
                {!multi &&
                  <div className="om-hb-act">
                    <button className="om-hb-btn" onClick={() => openReconnect(a0)} data-testid="caixa-unif-health-reconnect">
                      <RefreshIco /> Reconectar canal
                    </button>
                  </div>
                  }
                {multi &&
                  <div className="om-hb-rows">
                    {unhealthyAccounts.map((a) =>
                    <div className="om-hb-row" key={a.id}>
                        <span className="om-hb-dot pulse" style={{ background: a.health === "down" ? "oklch(0.63 0.21 27)" : "oklch(0.80 0.13 80)" }} />
                        <span><b>{a.ch.short} · {a.label}</b><span style={{ opacity: .85 }}> — {HEALTH_VERB[a.health].label}, {a.affected} conversas · há {a.sinceMin} min</span></span>
                        <a href="#" onClick={(e) => {e.preventDefault();openReconnect(a);}}>Reconectar</a>
                      </div>
                    )}
                  </div>
                  }
              </div>);

            })()}

          {filteredConvs.length === 0 ?
            <div className="om-empty-list">
              <b>Nenhuma conversa</b>
              <small>Tente outro filtro ou limpe a busca.</small>
            </div> :

            <ul className="om-list" data-comment-anchor="79cf23bb54-ul-694-13">
              {filteredConvs.map((c) => {
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
                      <span className="om-av" style={c.photo ? null : { background: `oklch(0.60 0.12 ${c.avc})` }}>
                        {c.photo ? <img className="om-av-img" src={c.photo} alt="" /> : c.av}
                        {c.online && <span className="om-online" />}
                      </span>
                      <span className="om-av-ch" style={{ background: `oklch(0.62 0.14 ${ch.hue})` }} title={`${ch.label} · ${acc.label}`}>
                        {ch.glyph}
                      </span>
                    </span>
                    <div className="om-list-text">
                      <b>
                        {c.name}
                        {c.preview_only && <span className="om-chip-soon">em breve</span>}
                        {isFav(c.id) && <span className="om-fav-mark" title="Favorito">★</span>}
                      </b>
                      <small>{c.preview}</small>
                    </div>
                    <div className="om-list-r">
                      {!c.preview_only && q && window.SLAPill && <window.SLAPill conv={c} queue={q} size="sm" />}
                      {c.assignee && <OpAvatar id={c.assignee} size={16} />}
                      {c.unread > 0 && <span className="om-un">{c.unread}</span>}
                    </div>
                  </li>);

              })}
            </ul>
            }
        </aside>

        {/* ───── Thread ───── */}
        <main className="om-thread-c">
          {!conv ?
            <div className="om-empty">Selecione uma conversa.</div> :

            <>
              <header className="om-thread-h">
                <span className="om-av-wrap sm">
                  <span className="om-av sm" style={conv.photo ? null : { background: `oklch(0.60 0.12 ${conv.avc})` }}>{conv.photo ? <img className="om-av-img" src={conv.photo} alt="" /> : conv.av}</span>
                  <span className="om-av-ch sm" style={{ background: `oklch(0.62 0.14 ${convChannel.hue})` }}>{convChannel.glyph}</span>
                </span>
                <div className="om-thread-h-text">
                  <b>{conv.name}</b>
                  <small>
                    <span className="om-chip" style={{ "--chip-hue": convChannel.hue, borderColor: `oklch(0.85 0.06 ${convChannel.hue})`, color: `oklch(0.35 0.10 ${convChannel.hue})` }} data-comment-anchor="c31fbbe4f0-span-639-21">
                      {convChannel.short} · {convAcc.label}
                    </span>
                    <span className="om-sep">·</span>
                    <span className="mono">{conv.handle}</span>
                    <span className="om-sep">·</span>
                    {conv.online ? "online" : conv.ctx.lastTouch}
                    {!isPreview && effHealth(convAcc) !== "healthy" &&
                    <>
                        <span className="om-sep">·</span>
                        <span style={{ fontWeight: 600, color: effHealth(convAcc) === "down" ? "oklch(0.48 0.15 27)" : "oklch(0.45 0.10 70)" }}
                      title={effHealth(convAcc) === "down" ? "Canal fora do ar — envio pausado" : "Canal degradado — sincronização lenta"}>
                          ● {effHealth(convAcc) === "down" ? "fora do ar" : "degradado"}
                        </span>
                      </>
                    }
                  </small>
                </div>
                <div className="om-thread-h-r">
                    {!isPreview && convQueue && window.SLAPill && <window.SLAPill conv={conv} queue={convQueue} size="lg" />}
                    {!isPreview &&
                    <button
                    className={"om-fav-btn-h " + (isFav(conv.id) ? "on" : "")}
                    onClick={() => {toggleFav(conv.id);setToast(isFav(conv.id) ? "Removido dos favoritos" : "Favoritado");}}
                    title={isFav(conv.id) ? "Remover favorito (B)" : "Favoritar (B)"}
                    aria-pressed={isFav(conv.id)}>
                      <svg width="14" height="14" viewBox="0 0 24 24" fill={isFav(conv.id) ? "currentColor" : "none"} stroke="currentColor" strokeWidth="2" strokeLinejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
                    </button>
                    }
                    <button className="om-ctx-open-btn" onClick={() => setCtxDrawerOpen(true)} title="Ver contexto da conversa" aria-label="Ver contexto" data-testid="caixa-unif-ctx-open">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9" /><path d="M12 16v-4M12 8h.01" /></svg>
                      Contexto
                    </button>
                    {!isPreview && <button className="os-btn ghost" onClick={resolve}>✓ Resolver</button>}
                  </div>
              </header>

              {isPreview &&
              <div className="om-preview-banner">
                  <b>{convChannel.label} · em homologação.</b>
                  <span>Conexão deste canal ainda não foi ativada. Esta conversa é uma prévia. <a href="#">Ativar canal</a></span>
                </div>
              }

              <div className="om-msgs" ref={threadRef}>
                {conv.msgs.map((m, i) => {
                  const showDay = i === 0 || conv.msgs[i - 1].d !== m.d;
                  // Cross-link nas mensagens: #a3 (KB), #os4821 (OS), #c1 (conv), #q-vendas (fila)
                  const linkHandlers = {
                    kb: (ref) => {try {localStorage.setItem("oimpresso.route", "kb");window.location.reload();} catch (e) {}},
                    os: (ref) => setToast("Abriria OS " + ref),
                    conv: (ref) => setSelId(ref),
                    queue: (ref) => {const qid = ref.replace(/^q-/, "");moveToQueue(qid);}
                  };
                  const renderText = (t) => window.linkifyMessage ? window.linkifyMessage(t, linkHandlers) : t;
                  const blockComments = msgC.forMsg(selId, i);

                  const bubble = m.internal ?
                  <div className="om-internal">
                      <div className="om-internal-h">
                        <span className="om-internal-tag">Nota interna</span>
                        <small>{m.time} · só a equipe vê</small>
                      </div>
                      <div className="om-internal-t" data-comment-anchor="4245f49d9f-div-720-23">{renderText(m.t)}</div>
                    </div> :

                  <div className={"om-bub " + (m.who === "me" ? "me" : "them") + " ch-" + convChannel.id}>
                      <span>{renderText(m.t)}</span>
                      <small>{m.time}{m.who === "me" ? " ✓✓" : ""}</small>
                    </div>;


                  return (
                    <React.Fragment key={i}>
                      {showDay && <div className="om-day-sep"><span>{m.d === "hoje" ? "Hoje" : m.d === "ontem" ? "Ontem" : m.d}</span></div>}
                      {window.MsgCommentWrap ?
                      <window.MsgCommentWrap
                        comments={blockComments}
                        onAdd={(text) => msgC.add(selId, i, text)}
                        onRemove={(idx) => msgC.remove(selId, i, idx)}>
                          {bubble}
                        </window.MsgCommentWrap> :
                      bubble}
                    </React.Fragment>);

                })}
              </div>

              {showTpl &&
              <div className="om-tpl">
                  <small>Templates · disponíveis em {convChannel.short}</small>
                  {validTemplates.length === 0 ?
                <em className="om-tpl-none">Nenhum template configurado para este canal.</em> :
                validTemplates.map((t) =>
                <button key={t.id} onClick={() => sendMsg(t.body)}>
                          <b>{t.label}</b>
                          <em>{t.body}</em>
                        </button>
                )}
                </div>
              }

              {showMacros &&
              <div className="om-tpl macros">
                  <small>Macros · digite <span className="mono">/</span> no input pra autocomplete</small>
                  {MACROS.map((m) =>
                <button key={m.slash} onClick={() => applyMacro(m)}>
                      <b><span className="mono om-slash">{m.slash}</span> {m.title}</b>
                      <em>{m.body}</em>
                      {m.actions?.length > 0 &&
                  <div className="om-macro-actions">
                          {m.actions.map((a) => <span key={a} className="om-tag">{a}</span>)}
                        </div>
                  }
                    </button>
                )}
                </div>
              }

              {slashOpen && slashMatches.length > 0 &&
              <div className="om-slash-pop">
                  {slashMatches.slice(0, 6).map((m) =>
                <button key={m.slash} onClick={() => applyMacro(m)}>
                      <span className="mono om-slash">{m.slash}</span>
                      <b>{m.title}</b>
                      <em>{m.body.slice(0, 70)}…</em>
                    </button>
                )}
                </div>
              }

              {!isPreview && !internalMode && effHealth(convAcc) === "down" &&
              <div className="om-preview-banner" style={{ background: "oklch(0.97 0.02 27)", borderColor: "oklch(0.84 0.09 27)", color: "oklch(0.45 0.13 27)" }}>
                  <b style={{ color: "oklch(0.42 0.16 27)" }}>Envio pausado — canal fora do ar.</b>
                  <span>Reconecte o canal pra enviar; sua mensagem fica salva como rascunho. <a href="#" onClick={(e) => {e.preventDefault();openReconnect(convAcc);}}>Reconectar</a></span>
                </div>
              }
              <div className={"om-composer " + (internalMode ? "internal" : "")}>
                <div className="om-input-main">
                  <input
                    ref={inputRef}
                    value={draft}
                    data-testid="caixa-unif-composer-input"
                    aria-label={internalMode ? "Nota interna" : "Mensagem para o cliente"}
                    onChange={(e) => {
                      const v = e.target.value;
                      setDraft(v);
                      setSlashOpen(v.startsWith("/") && v.length > 0);
                    }}
                    onKeyDown={(e) => {
                      if (e.key === "Enter" && !e.shiftKey) {
                        e.preventDefault();
                        if (slashOpen && slashMatches.length > 0) {applyMacro(slashMatches[0]);return;}
                        sendMsg();
                      }
                      if (e.key === "Escape") {setSlashOpen(false);setShowTpl(false);setShowMacros(false);}
                      if (e.key === "N" && (e.metaKey || e.ctrlKey) && e.shiftKey) {e.preventDefault();setInternalMode((v) => !v);}
                    }}
                    placeholder={
                    internalMode ?
                    "Nota interna · só pra equipe" :
                    isPreview ? `${convChannel.short} em homologação — envio bloqueado` : `Responder via ${convChannel.short} · ${convAcc.label}`
                    }
                    disabled={isPreview && !internalMode} />
                  <button
                    className={"os-btn " + (internalMode ? "" : "primary")}
                    onClick={() => sendMsg()}
                    disabled={!draft.trim() || (isPreview || effHealth(convAcc) === "down") && !internalMode}
                    data-testid="caixa-unif-composer-send"
                    style={internalMode ? { background: "oklch(0.70 0.14 80)", color: "oklch(0.20 0.10 80)" } : null}>
                    {internalMode ? "Anotar" : "Enviar"}
                  </button>
                </div>
                <div className="om-input-tools">
                  {!internalMode && !isPreview && window.SuggestReplyButton &&
                  <window.SuggestReplyButton
                    conv={conv}
                    onApply={(out) => {
                      setDraft(out.reply || "");
                      if (out.queue_suggestion && out.queue_suggestion !== conv.queue) {
                        setToast(`IA sugere mover pra fila ${Q_BY_ID[out.queue_suggestion]?.label || out.queue_suggestion}`);
                      } else {
                        setToast(`Sugestão aplicada · revise antes de enviar`);
                      }
                      inputRef.current && inputRef.current.focus();
                    }} />
                  }
                  {!internalMode && !isPreview && window.SuggestReplyButton && <span className="om-tool-div" />}
                  <button className={"om-tool-btn " + (internalMode ? "on" : "")}
                  onClick={() => setInternalMode((v) => !v)}
                  aria-label={internalMode ? "Modo nota interna ativo (⌘⇧N pra voltar)" : "Alternar pra nota interna (⌘⇧N)"}
                  aria-pressed={internalMode}
                  title="Resposta cliente / Nota interna (⌘⇧N)"
                  data-testid="caixa-unif-mode-toggle" data-comment-anchor="81e3904704-button-801-19">
                    {internalMode ?
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg> :
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><polyline points="9 17 4 12 9 7" /><path d="M20 18v-2a4 4 0 0 0-4-4H4" /></svg>}
                    <span className="om-tool-hint" data-comment-anchor="3300cab192-span-824-21">{internalMode ? "Nota interna" : "Resposta"} <kbd>⌘⇧N</kbd></span>
                  </button>
                  <button className="om-tool-btn" onClick={() => {setShowTpl((v) => !v);setShowMacros(false);}}
                  aria-label="Templates do canal (⌘T)" title="Templates" disabled={internalMode}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><line x1="3" y1="9" x2="21" y2="9" /><line x1="9" y1="21" x2="9" y2="9" /></svg>
                    <span className="om-tool-hint">Templates <kbd>⌘T</kbd></span>
                  </button>
                  {/* TODO US-WA-303: slash macros inline + autocomplete (já prototipado em om-slash-pop) */}
                  <button className="om-tool-btn" onClick={() => {setShowMacros((v) => !v);setShowTpl(false);}}
                  aria-label="Macros (atalhos / slash)" title="Macros" disabled={internalMode}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" /></svg>
                    <span className="om-tool-hint">Macros <kbd>/</kbd></span>
                  </button>
                  {!internalMode && window.VarMenu &&
                  <button className="om-tool-btn" onClick={() => setVarMenuOpen(true)} title="Inserir variável" aria-label="Inserir variável">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M8 3H7a2 2 0 0 0-2 2v4a2 2 0 0 1-2 2 2 2 0 0 1 2 2v4a2 2 0 0 0 2 2h1" /><path d="M16 3h1a2 2 0 0 1 2 2v4a2 2 0 0 1 2 2 2 2 0 0 1-2 2v4a2 2 0 0 1-2 2h-1" /></svg>
                    <span className="om-tool-hint">Variáveis</span>
                  </button>
                  }
                </div>
              </div>
            </>
            }
        </main>

        {/* ───── Contexto (drawer lateral · [W] 2026-06-19: "some com a coluna, abre por botão na thread") ───── */}
        {conv && ctxDrawerOpen &&
          <>
          <div className="om-backdrop" onClick={() => setCtxDrawerOpen(false)} />
          <aside className="om-drawer om-ctx-drawer" data-comment-anchor="b9c6f94e89-div-862-13">
            <header className="om-drawer-h">
              <div><h2>Contexto</h2><p>{conv.name}{conv.company ? " · " + conv.company : ""}</p></div>
              <button className="om-x" onClick={() => setCtxDrawerOpen(false)} aria-label="Fechar">✕</button>
            </header>
            <div className="om-ctx-body">
              {!isPreview && (window.SummarizeThreadDialog || window.AskInboxDialog) &&
                <div className="om-ctx-ai">
                  <small>Inteligência</small>
                  {window.SummarizeThreadDialog &&
                  <button className="om-ctx-ai-btn" onClick={() => setSummarizeOpen(true)} title="Resumir conversa (IA)">
                      <span className="om-suggest-spark">✦</span>Resumir conversa
                    </button>
                  }
                  {window.AskInboxDialog &&
                  <button className="om-ctx-ai-btn" onClick={() => {setAskInitialQuery("");setAskOpen(true);}} title="Perguntar ao histórico deste cliente">
                      <span className="om-suggest-spark">✦</span>Perguntar ao histórico
                    </button>
                  }
                </div>
                }
              <div className="om-kv">
                <small>Fila</small>
                {isPreview ?
                  <b><QueueChip q={convQueue} /></b> : (

                  /* TODO US-WA-305: Mover conversa entre filas (hoje fila vem da heurística
                     tag→fila em deriveQueueFromTags() do CaixaUnificadaController; override
                     manual precisa nova coluna conversations.queue_slug nullable). */
                  <select className="om-ctx-select" value={conv.queue || ""} onChange={(e) => moveToQueue(e.target.value)}
                  aria-label="Mover conversa para outra fila"
                  data-testid="caixa-unif-ctx-queue-select">
                    {QUEUES.map((q) => <option key={q.id} value={q.id}>{q.label}</option>)}
                  </select>)
                  }
                <small style={{ marginTop: 4, color: "var(--text-mute)", textTransform: "none", letterSpacing: 0 }}>
                  SLA {convQueue?.sla} · {convQueue?.dist === "round-robin" ? "alternada" : convQueue?.dist === "sticky" ? "fixa" : "manual"}
                </small>
              </div>
              <div className="om-kv">
                <small>Atribuído</small>
                {isPreview ?
                  <b>— sem atribuição</b> : (

                  /* TODO US-WA-302: Assignee picker real — hoje OPERATORS é mock; deve buscar
                     users do business com permission `whatsapp.access` ATIVA + presence
                     online/offline (Centrifugo). Backend reusa conversations.assigned_user_id. */
                  <select className="om-ctx-select" value={conv.assignee || ""} onChange={(e) => reassign(e.target.value)}
                  aria-label="Atribuir conversa para operador"
                  data-testid="caixa-unif-ctx-assignee-select">
                    <option value="">— sem atribuição</option>
                    {Object.values(OPERATORS).map((op) => <option key={op.id} value={op.id}>{op.name}</option>)}
                  </select>)
                  }
              </div>
              <div className="om-kv">
                <small>Canal · Conta</small>
                <b>
                  <ChannelGlyph ch={convChannel} size={12} />
                  <span style={{ marginLeft: 6 }}>{convChannel.short} · {convAcc.label}</span>
                </b>
                <small className="mono" style={{ marginTop: 2, color: "var(--text-mute)", textTransform: "none", letterSpacing: 0 }}>{convAcc.handle}</small>
              </div>
              {conv.tags?.length > 0 &&
                <div className="om-kv">
                  <small>Tags</small>
                  <div className="om-tags">{conv.tags.map((t) => <span key={t} className="om-tag">{t}</span>)}</div>
                </div>
                }
              {conv.ctx.os &&
                <div className="om-kv">
                  <small>OS vinculada</small>
                  <b>{conv.ctx.os}</b>
                  <button className="os-btn sm" style={{ marginTop: 6 }}>Abrir OS</button>
                </div>
                }
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
          </>
          }
      </div>

      {toast && <div className="om-toast">✓ {toast}</div>}

      {transcriptOpen && conv && window.InboxTranscriptDialog &&
        <window.InboxTranscriptDialog conv={conv} queue={convQueue} channel={convChannel} account={convAcc} onClose={() => setTranscriptOpen(false)} />
        }
      {presenterOpen && conv && window.InboxPresenterMode &&
        <window.InboxPresenterMode conv={conv} channel={convChannel} account={convAcc} onClose={() => setPresenterOpen(false)} />
        }
      {varMenuOpen && window.VarMenu &&
        <window.VarMenu
          onInsert={(token) => {
            const el = inputRef.current;
            if (el) {
              const start = el.selectionStart || draft.length;
              const end = el.selectionEnd || draft.length;
              setDraft(draft.slice(0, start) + token + draft.slice(end));
              setTimeout(() => {el.focus();el.selectionStart = el.selectionEnd = start + token.length;}, 30);
            } else setDraft(draft + token);
          }}
          onClose={() => setVarMenuOpen(false)} />
        }
      {lightboxMedia && window.InboxMediaLightbox &&
        <window.InboxMediaLightbox media={lightboxMedia} onClose={() => setLightboxMedia(null)} />
        }

      {/* V2 Refino #3 — Troubleshooter de atendimento */}
      {tbOpen && window.InboxTroubleDialog &&
        <window.InboxTroubleDialog
          onPickArticle={() => {}}
          onClose={() => setTbOpen(false)} />
        }
      {/* V2 Refino #3 — Trilhas onboarding */}
      {pathsOpen && window.InboxPathsDialog &&
        <window.InboxPathsDialog
          onPickKB={(ref) => {
            try {localStorage.setItem("oimpresso.route", "kb");window.location.reload();} catch (e) {}
          }}
          onPickTrouble={() => {setPathsOpen(false);setTbOpen(true);}}
          onClose={() => setPathsOpen(false)} />
        }

      {/* V2 Refino #2 — IA dentro do thread */}
      {summarizeOpen && conv && window.SummarizeThreadDialog &&
        <window.SummarizeThreadDialog
          conv={conv}
          onClose={() => setSummarizeOpen(false)} />
        }
      {askOpen && window.AskInboxDialog &&
        <window.AskInboxDialog
          conv={conv}
          allConvs={convs}
          kbArticles={window.INITIAL_KB_ARTICLES || []}
          initialQuery={askInitialQuery}
          onClose={() => setAskOpen(false)} />
        }

      {/* V2 — Command palette ⌘K */}
      {paletteOpen && window.InboxPalette &&
        <window.InboxPalette
          convs={convs}
          accounts={ACC_BY_ID}
          channels={CHAN_BY_ID}
          queues={Q_BY_ID}
          operators={OPERATORS}
          kbArticles={window.INITIAL_KB_ARTICLES || []}
          onPickConv={(id) => {setSelId(id);if (mobileView === "list") setMobileView("thread");}}
          onAskAI={(q) => {setAskInitialQuery(q);setAskOpen(true);}}
          inputRef={paletteRef}
          onClose={() => setPaletteOpen(false)} />
        }

      {/* V2 — Cheat-sheet (?) */}
      {cheatOpen && window.InboxCheatSheet &&
        <window.InboxCheatSheet onClose={() => setCheatOpen(false)} />
        }

      {/* ───── Drawer: Templates (biblioteca unificada Jana + HSM numa só tela) ───── */}
      {tplDrawerOpen &&
        <>
          <div className="om-backdrop" onClick={() => setTplDrawerOpen(false)} />
          <aside className="om-drawer wide">
            <header className="om-drawer-h">
              <div><h2>Templates</h2><p>Biblioteca unificada · Jana (IA) + HSM (Meta-aprovados)</p></div>
              <button className="om-x" onClick={() => setTplDrawerOpen(false)}>✕</button>
            </header>
            <div className="om-tpl-seg" role="tablist" aria-label="Filtrar biblioteca de templates">
              {[{ id: "all", label: "Todas" }, { id: "jana", label: "✨ Jana · IA" }, { id: "hsm", label: "▣ HSM · Meta" }].map((s) =>
              <button key={s.id} role="tab" aria-selected={tplLib === s.id}
              className={"om-tpl-seg-btn " + (tplLib === s.id ? "sel" : "")}
              onClick={() => setTplLib(s.id)}
              data-testid={`caixa-unif-tpl-seg-${s.id}`}>{s.label}</button>
              )}
            </div>
            <div className="om-drawer-body">
              {[
              ...JANA_TEMPLATES.map((t) => ({ ...t, lib: "jana" })),
              ...TEMPLATES.map((t) => ({ ...t, lib: "hsm" }))].
              filter((t) => tplLib === "all" || t.lib === tplLib).
              map((t) => {
                const okHere = t.lib === "jana" || !convChannel || t.channels && t.channels.includes(convChannel.id);
                return (
                  <button key={t.lib + "_" + t.id} className="om-tpl-lib-item" onClick={() => applyTemplate(t)}
                  data-testid={`caixa-unif-tpl-${t.lib}-${t.id}`}>
                    <span className={"om-tpl-lib-tag " + t.lib}>{t.lib === "jana" ? "IA" : "Meta"}</span>
                    <span className="om-tpl-lib-text">
                      <b>{t.label}</b>
                      <em>{t.body}</em>
                      {t.lib === "hsm" && !okHere &&
                      <small className="om-tpl-lib-warn">fora do {convChannel ? convChannel.short : "canal atual"} · revisar antes de enviar</small>}
                    </span>
                  </button>);

              })}
              <p className="om-chan-note">
                <b>Jana</b> são prompts internos de IA que geram a resposta a partir do contexto da conversa. <b>HSM</b> são modelos aprovados pela Meta, usados fora da janela de 24h. Tocar num template joga o texto no campo de resposta pra você revisar antes de enviar.
              </p>
            </div>
          </aside>
        </>
        }

      {/* ───── Drawer: Canais e contas ───── */}
      {chSwitcherOpen &&
        <>
          <div className="om-backdrop" onClick={() => setChSwitcherOpen(false)} />
          <aside className="om-drawer">
            <header className="om-drawer-h">
              <div><h2>Canais e contas</h2><p>{activeAccCount} ativas · {ACCOUNTS.length - activeAccCount} em homologação</p></div>
              <button className="om-x" onClick={() => setChSwitcherOpen(false)}>✕</button>
            </header>
            <div className="om-drawer-body">
              {CHANNELS.map((ch) => {
                const accs = ACCOUNTS.filter((a) => a.channel === ch.id);
                if (!accs.length) return null;
                return (
                  <section key={ch.id} className="om-chan-group">
                    <small className="om-chan-group-h">
                      <ChannelGlyph ch={ch} size={11} />
                      <span style={{ marginLeft: 6 }}>{ch.label}</span>
                    </small>
                    {accs.map((acc) =>
                    <div key={acc.id} className={"om-chan-row " + (acc.status === "ativo" ? "on" : "off")}>
                        <div className="om-chan-row-text">
                          <b>{acc.label}</b>
                          <small className="mono">{acc.handle}</small>
                          {OPERATORS[acc.owner] && <small>Responsável: {OPERATORS[acc.owner].name}</small>}
                          {acc.status === "ativo" && effHealth(acc) !== "healthy" &&
                        <small className={"om-chan-health " + (effHealth(acc) === "down" ? "err" : "warn")}>
                              {effHealth(acc) === "down" ? "● Fora do ar" : "● Degradado — sincronização lenta"}
                            </small>
                        }
                        </div>
                        {acc.status === "ativo" ?
                      effHealth(acc) !== "healthy" ?
                      <button className="os-btn sm" style={{ flexShrink: 0 }} onClick={() => openReconnect(acc)}>Reconectar</button> :
                      <span className="om-pill on">ativo</span> :
                      <span className="om-pill off">em breve</span>}
                      </div>
                    )}
                  </section>);

              })}
              <button className="os-btn ghost" style={{ marginTop: 12, justifyContent: "center" }}>+ Adicionar conta</button>
            </div>
          </aside>
        </>
        }

      {/* ───── Modal: Reconectar canal (re-parear via QR) ─────
                Resposta ao comentário [W]: clicar em Reconectar mostra o QR pra re-parear
                (WhatsApp Baileys/whatsmeow/Z-API). Canal Meta Cloud = token, não tem QR. */}
      {reconnectAcc && (() => {
          const rcCh = CHANNELS.find((c) => c.id === reconnectAcc.channel);
          const isQR = reconnectAcc.channel !== "wa_meta";
          const m = qrMatrix(reconnectAcc.id);
          return (
            <>
            <div className="om-rc-backdrop" onClick={() => setReconnectAcc(null)} />
            <div className="om-rc" role="dialog" aria-modal="true" aria-label="Reconectar canal" data-testid="caixa-unif-reconnect-modal">
              <header className="om-rc-h">
                <div>
                  <h2>Reconectar canal</h2>
                  <p>{rcCh ? rcCh.short : "WhatsApp"} · {reconnectAcc.label} <span className="mono">{reconnectAcc.handle}</span></p>
                </div>
                <button className="om-x" onClick={() => setReconnectAcc(null)} aria-label="Fechar">✕</button>
              </header>
              <div className="om-rc-body">
                {isQR ?
                  <>
                    <div className="om-qr" aria-label="QR code pra parear o WhatsApp">
                      {m.map((row, r) => row.map((on, c) => <i key={r + "_" + c} className={on ? "on" : ""} />))}
                    </div>
                    <div className="om-rc-steps">
                      <b>Reabra a sessão escaneando o código</b>
                      <ol>
                        <li>No celular, abra o <b>WhatsApp</b></li>
                        <li>Toque em <b>⋮ → Aparelhos conectados</b></li>
                        <li><b>Conectar um aparelho</b> e aponte a câmera aqui</li>
                      </ol>
                      <span className="om-rc-wait"><span className="om-hb-dot pulse" style={{ background: "oklch(0.62 0.16 145)" }} /> Aguardando leitura…</span>
                    </div>
                  </> :

                  <div className="om-rc-steps">
                    <b>Canal via API oficial da Meta — sem QR</b>
                    <p>Este canal usa token da Cloud API. A queda costuma ser token expirado ou webhook fora do ar — verifique a credencial e o webhook na página do canal.</p>
                  </div>
                  }
              </div>
              <footer className="om-rc-foot">
                <button className="os-btn ghost" onClick={() => {setReconnectAcc(null);setChSwitcherOpen(true);}}>Ver todos os canais</button>
                <div style={{ display: "flex", gap: 8 }}>
                  <button className="os-btn" onClick={() => setReconnectAcc(null)}>Cancelar</button>
                  <button className="os-btn primary" onClick={() => {reconnect(reconnectAcc.id, reconnectAcc.label);setReconnectAcc(null);}} data-testid="caixa-unif-reconnect-confirm">
                    {isQR ? "Já escaneei" : "Reautenticar"}
                  </button>
                </div>
              </footer>
            </div>
          </>);

        })()}

      {/* ───── Drawer: Filas de atendimento ───── */}
      {queuesOpen &&
        <>
          <div className="om-backdrop" onClick={() => setQueuesOpen(false)} />
          <aside className="om-drawer wide">
            <header className="om-drawer-h">
              <div><h2>Filas de atendimento</h2><p>{QUEUES.length} filas · distribuição automática</p></div>
              <button className="om-x" onClick={() => setQueuesOpen(false)}>✕</button>
            </header>
            <div className="om-drawer-body">
              {QUEUES.map((q) => {
                const n = counts["q_" + q.id] || 0;
                return (
                  <div key={q.id} className="om-q-row">
                    <div className="om-q-row-head">
                      <span className="om-q-dot lg" style={{ background: `oklch(0.55 0.13 ${q.hue})` }} />
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
                          {q.members.map((id) => <OpAvatar key={id} id={id} size={20} />)}
                        </div>
                      </div>
                    </div>
                  </div>);

              })}
              <button className="os-btn ghost" style={{ marginTop: 12, justifyContent: "center" }}>+ Nova fila</button>
              <p className="om-chan-note">
                Quando uma mensagem chega num canal, ela vai automaticamente pra fila <b>Geral</b> e dali é roteada (ou fica disponível pros operadores pegarem). Você pode mover qualquer conversa entre filas pelo seletor no header da thread.
              </p>
            </div>
          </aside>
        </>
        }

      {/* ───── Drawer: Broadcast ───── */}
      {bcastOpen &&
        <>
          <div className="om-backdrop" onClick={() => setBcastOpen(false)} />
          <aside className="om-drawer">
            <header className="om-drawer-h">
              <div>
                <h2>Broadcast cross-canal</h2>
                <p>Envia para {convs.filter((c) => !c.preview_only).length} contatos abertos</p>
              </div>
              <button className="om-x" onClick={() => setBcastOpen(false)}>✕</button>
            </header>
            <div className="om-drawer-body">
              <label><small>Conta de envio</small></label>
              <select className="om-select" defaultValue="wa_bal_vendas">
                {ACCOUNTS.map((acc) => {
                  const ch = CHAN_BY_ID[acc.channel];
                  return (
                    <option key={acc.id} value={acc.id} disabled={acc.status !== "ativo"}>
                      {ch.short} · {acc.label} · {acc.handle}{acc.status !== "ativo" ? " · em breve" : ""}
                    </option>);

                })}
              </select>
              <label style={{ marginTop: 14 }}><small>Template</small></label>
              <select className="om-select">{TEMPLATES.map((t) => <option key={t.id}>{t.label}</option>)}</select>
              <label style={{ marginTop: 14 }}><small>Mensagem</small></label>
              <textarea rows={5} defaultValue={TEMPLATES[0].body} />
              <p style={{ fontSize: 11, color: "var(--text-mute)", margin: "8px 0 14px" }}>
                Respeita janela 24h WhatsApp e templates aprovados.
              </p>
              <button className="os-btn primary" onClick={() => {setBcastOpen(false);setToast(`Broadcast disparado para ${convs.filter((c) => !c.preview_only).length} contatos`);}}>
                Disparar broadcast
              </button>
            </div>
          </aside>
        </>
        }
    </div>);

  }

  window.InboxPage = InboxPage;
})();