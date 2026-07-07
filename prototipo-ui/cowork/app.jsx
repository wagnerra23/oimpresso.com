// app.jsx — orquestra rotas Chat / Tarefas / Módulo legado
const { useState: useStateA, useEffect: useEffectA } = React;

// Error boundary por rota — um erro numa tela não derruba o app inteiro.
// key={route} reseta o boundary ao trocar de rota (uma tela consertada se recupera).
class RouteErrorBoundary extends React.Component {
  constructor(props) {super(props);this.state = { err: null };}
  static getDerivedStateFromError(err) {return { err };}
  componentDidCatch(err, info) {console.error("Route crash:", err, info);}
  render() {
    if (this.state.err) {
      return (
        <div style={{ padding: "48px 32px", maxWidth: "60ch" }}>
          <div style={{ fontSize: "var(--fs-6,18px)", fontWeight: 600, color: "var(--text)", marginBottom: 8 }}>Esta tela encontrou um erro.</div>
          <div style={{ fontSize: "var(--fs-3,12.5px)", color: "var(--text-dim)", fontFamily: "var(--mono, monospace)" }}>{String(this.state.err && this.state.err.message || this.state.err)}</div>
        </div>);

    }
    return this.props.children;
  }
}

// ─────────────────────────────────────────────────────────────────
// Nota: módulos antigos (Financeiro, Boletos, Compras, Oficina Auto)
// foram migrados de HTMLs standalone para páginas React nativas dentro
// deste shell (financeiro-app.jsx, boleto-contas-app.jsx, compras-page.jsx,
// oficina-page.jsx). Não usamos mais iframes — ROUTE_HTML/IframeView removidos.
// ─────────────────────────────────────────────────────────────────

function ChatPage({ company, activeConvId, onSelectConv, linkedCollapsed, onToggleLinked }) {
  const conversations = MOCK.CONV[company.id] || [];
  const [convs, setConvs] = useStateA(conversations);
  const [convTab, setConvTab] = useStateA(() => {
    try {return localStorage.getItem("oimpresso.chat.tab") || "todas";} catch (e) {return "todas";}
  });
  const [convQuery, setConvQuery] = useStateA("");

  useEffectA(() => {setConvs(MOCK.CONV[company.id] || []);}, [company.id]);
  useEffectA(() => {try {localStorage.setItem("oimpresso.chat.tab", convTab);} catch (e) {}}, [convTab]);

  const conv = convs.find((c) => c.id === activeConvId) || convs[0];

  useEffectA(() => {
    if (!conv || !conv.unread) return;
    setConvs((cs) => cs.map((c) => c.id === conv.id ? { ...c, unread: 0 } : c));
  }, [conv?.id]);

  const handleSend = (text, raw) => {
    setConvs((cs) => cs.map((c) => {
      if (c.id !== conv.id) return c;
      const newMsg = raw || { d: "Hoje", who: "você", side: "me", t: text, time: "agora", read: false };
      return { ...c, msgs: [...c.msgs, newMsg], time: "agora", preview: (raw?.who ? raw.who + ': ' : 'você: ') + (raw?.t || text || '') };
    }));
  };

  return (
    <div className="chat-page">
      <div className="chat-main">
        <ConvTabsBar tab={convTab} onTab={setConvTab} query={convQuery} onQuery={setConvQuery} />
        <Thread conv={conv} onSend={handleSend} />
      </div>
      <LinkedAppsPanel conv={conv} collapsed={linkedCollapsed} onToggle={onToggleLinked} />
    </div>);

}

function ConvTabsBar({ tab, onTab, query, onQuery }) {
  const tabs = [
  { id: "todas", label: "Todos" },
  { id: "os", label: "OS" },
  { id: "team", label: "Equipe" },
  { id: "client", label: "Clientes" }];

  return (
    <div className="chat-tabsbar">
      <div className="chat-tabs">
        {tabs.map((t) =>
        <button key={t.id}
        className={"chat-tab" + (tab === t.id ? " active" : "")}
        onClick={() => onTab(t.id)}>{t.label}</button>
        )}
      </div>
      <div className="chat-search">
        <I.search size={12} />
        <input placeholder="Buscar nesta conversa..." value={query} onChange={(e) => onQuery(e.target.value)} />
      </div>
    </div>);

}

// Stub para módulos do menu — em produção, abre Inertia page do módulo
// MIGRATION_INFO — auditoria dos 36 módulos do repo wagnerra23/oimpresso.com@main
// Fonte canônica em AUDITORIA_MODULOS.md. fase 1=Inventário ✅, 2=Adapter menu, 3=Reescrita React, 4=Decommission.
const MIGRATION_INFO = {
  // ─────── OFFICEIMPRESSO ───────
  os: { phase: 2, status: "next", blade: "Modules/Officeimpresso/Resources/views/os/", routes: ["/os", "/os/create", "/os/{id}"], priority: "Piloto", desc: "Núcleo do ERP. Listagem + form + detalhe da Ordem de Serviço.", actions: ["Nova OS", "Listar abertas", "Fila de produção"] },
  clientes: { phase: 3, status: "queue", blade: "Modules/Officeimpresso/Resources/views/clientes/", routes: ["/clientes"], priority: "Alta freq.", desc: "CRUD de clientes + histórico de compras + contatos.", actions: ["Novo cliente", "Importar", "Aniversariantes"] },
  produtos: { phase: 3, status: "queue", blade: "Modules/Officeimpresso/Resources/views/produtos/", routes: ["/produtos"], priority: "Alta freq.", desc: "Catálogo de produtos com preço, insumos e especificações técnicas.", actions: ["Novo produto", "Tabela de preço", "Insumos"] },
  orcamentos: { phase: 3, status: "queue", blade: "Modules/Officeimpresso/Resources/views/orcamentos/", routes: ["/orcamentos"], priority: "Alta freq.", desc: "Geração de orçamentos a partir do catálogo + envio por e-mail/WhatsApp.", actions: ["Novo orçamento", "Aprovados", "A vencer"] },
  vendas: { phase: 3, status: "queue", blade: "Modules/Officeimpresso/Resources/views/vendas/", routes: ["/vendas"], priority: "Alta freq.", desc: "Pedidos confirmados, faturamento e nota fiscal. Inclui Sells/Create P0.", actions: ["Hoje", "Mês", "Por cliente"] },
  cv: { phase: 1, status: "queue", blade: "Modules/ComunicacaoVisual/Resources/views/", routes: ["/cv"], priority: "Núcleo", desc: "Módulo Comunicação Visual: banners, lonas, adesivos, fachadas. Especialização do catálogo Officeimpresso.", actions: ["Novo job CV", "Templates", "Histórico"] },
  catalogue: { phase: 1, status: "later", blade: "Modules/ProductCatalogue/Resources/views/", routes: ["/catalogue"], priority: "Suporte", desc: "Catálogo umbrella reusável entre módulos. Especificações técnicas de insumos e produtos.", actions: ["Famílias", "Insumos", "SKUs"] },
  portalos: { phase: 1, status: "later", blade: "Modules/ConsultaOs/Resources/views/", routes: ["/portal/consulta"], priority: "Externo", desc: "Portal cliente-facing para acompanhar status de OS sem login no ERP.", actions: ["Buscar OS", "Histórico cliente", "Configurar"] },

  // ─────── COMERCIAL ───────
  crm: { phase: 1, status: "later", blade: "Modules/Crm/Resources/views/", routes: ["/crm"], priority: "Comercial", desc: "Funil de leads, oportunidades, follow-ups. Integra com Tarefas (TaskProvider crm_ligar).", actions: ["Leads", "Oportunidades", "Funil"] },
  ads: { phase: 1, status: "later", blade: "Modules/ADS/Resources/views/", routes: ["/ads"], priority: "Marketing", desc: "Gestão de campanhas pagas: Google Ads, Meta. UTMs + atribuição a leads.", actions: ["Campanhas", "ROAS", "UTMs"] },
  grow: { phase: 1, status: "later", blade: "Modules/Grow/Resources/views/", routes: ["/grow"], priority: "Marketing", desc: "Growth & marketing orgânico: e-mail, automações, nutrição de leads.", actions: ["Automações", "E-mails", "Segmentos"] },
  inbox: { phase: 1, status: "partial", blade: "Modules/Inbox/Resources/views/", routes: ["/inbox"], priority: "Comercial", desc: "Caixa unificada (omnichannel): WhatsApp Baileys ativo; Meta Cloud, Z-API, Instagram DM, Messenger, Email IMAP e Mercado Livre em homologação. Lista + thread + contexto ERP (OS, saldo, LTV) + broadcast cross-canal.", actions: ["Conversas", "Templates", "Broadcast", "Canais"] },
  equipe: { phase: 1, status: "partial", blade: "Modules/Equipe/Resources/views/", routes: ["/equipe"], priority: "Comercial", desc: "Comunicação interna: canais (#producao, #financeiro, #vendas, #motoboy, #geral) + DMs entre operadores. Distinto da Caixa unificada — não mistura SLA cliente com recado de colega.", actions: ["Canais", "DMs", "Equipe"] },

  // ─────── PRODUÇÃO ───────
  fila: { phase: 4, status: "later", blade: "Modules/Officeimpresso/Resources/views/producao/fila/", routes: ["/fila"], priority: "Operacional", desc: "Fila de impressão por equipamento — sequenciamento e prioridade.", actions: ["Roland 540", "HP Latex", "Plotter recorte"] },
  acabamento: { phase: 4, status: "later", blade: "Modules/Officeimpresso/Resources/views/producao/acabamento/", routes: ["/acabamento"], priority: "Operacional", desc: "Pós-impressão: corte, laminação, aplicação.", actions: ["Pendentes", "Concluídos hoje"] },
  expedicao: { phase: 4, status: "later", blade: "Modules/Officeimpresso/Resources/views/producao/expedicao/", routes: ["/expedicao"], priority: "Operacional", desc: "Embalagem, romaneio e entrega.", actions: ["A entregar", "Roteirizar", "Concluídas"] },
  manufacturing: { phase: 1, status: "later", blade: "Modules/Manufacturing/Resources/views/", routes: ["/manufacturing"], priority: "Industrial", desc: "Produção industrial UltimatePOS: BOM, ordens de fabricação, custo.", actions: ["BOM", "Ordens", "Custo"] },
  iproduction: { phase: 1, status: "later", blade: "Modules/IProduction/Resources/views/", routes: ["/iproduction"], priority: "Industrial", desc: "Variante extendida de produção. Complementa Manufacturing.", actions: ["Receitas", "Lotes"] },
  brief: { phase: 1, status: "later", blade: "Modules/Brief/Resources/views/", routes: ["/brief"], priority: "Pré-produção", desc: "Briefings de design: questionário, aprovação de layout, anexos por OS.", actions: ["Novo brief", "Pendentes", "Templates"] },

  // ─────── VERTICAIS ───────
  repair: { phase: 1, status: "later", blade: "Modules/Repair/Resources/views/", routes: ["/repair"], priority: "Vertical", desc: "Assistência técnica (eletrônicos). Módulo UltimatePOS de referência canônica (ADR 0011).", actions: ["Nova OS Repair", "Em andamento", "Concluídas"] },
  oficinaauto: { phase: 1, status: "later", blade: "Modules/OficinaAuto/Resources/views/", routes: ["/oficina"], priority: "Vertical", desc: "Vertical oficina automotiva: OS de manutenção veicular, peças, mão-de-obra.", actions: ["Nova OS", "Veículos", "Catálogo peças"] },
  vestuario: { phase: 1, status: "later", blade: "Modules/Vestuario/Resources/views/", routes: ["/vestuario"], priority: "Vertical", desc: "Vertical confecção: grades por tamanho/cor, estampas, peças.", actions: ["Pedidos", "Grades", "Estampas"] },

  // ─────── PESSOAS ───────
  ponto: { phase: 4, status: "partial", blade: "Modules/Ponto/Resources/views/", routes: ["/ponto", "/ponto/espelho"], priority: "Núcleo RH", desc: "Ponto WR2 — Portaria MTP 671/2021. Bater ponto, espelho do mês, justificativas. Parcialmente React.", actions: ["Bater ponto", "Meu espelho", "Justificar"] },
  equipes: { phase: 5, status: "later", blade: "Modules/Officeimpresso/Resources/views/equipes/", routes: ["/equipes"], priority: "Baixa freq.", desc: "Times, escalas e responsáveis por etapas de produção.", actions: ["Times", "Escala da semana"] },

  // ─────── FINANCEIRO ───────
  financeiro: { phase: 1, status: "done", blade: "—", routes: ["/financeiro"], priority: "Concluído", desc: "Já migrado para React. Contas a pagar/receber, fluxo de caixa, conciliação Inter.", actions: ["Contas a pagar", "Contas a receber", "Fluxo"] },
  relatorios: { phase: 5, status: "later", blade: "Modules/Officeimpresso/Resources/views/relatorios/", routes: ["/relatorios"], priority: "Baixa freq.", desc: "BI: vendas por período, margem, produtividade.", actions: ["Vendas", "Margem", "Produtividade"] },
  nfse: { phase: 1, status: "partial", blade: "Modules/NFSe/Resources/views/", routes: ["/nfse"], priority: "Fiscal", desc: "Nota Fiscal de Serviço Eletrônica — emissão, RPS, lote, prefeituras.", actions: ["Emitir", "Lote", "Histórico"] },
  nfe: { phase: 1, status: "partial", blade: "Modules/NfeBrasil/Resources/views/", routes: ["/nfe"], priority: "Fiscal", desc: "NF-e Brasil (produto): emissão, transmissão SEFAZ, DANFE.", actions: ["Emitir", "Inutilizar", "DANFE"] },
  accounting: { phase: 1, status: "later", blade: "Modules/Accounting/Resources/views/", routes: ["/accounting"], priority: "Contábil", desc: "Plano de contas, lançamentos, balancete. Integra com Financeiro.", actions: ["Plano", "Balancete", "DRE"] },
  recurring: { phase: 1, status: "later", blade: "Modules/RecurringBilling/Resources/views/", routes: ["/recurring"], priority: "Financeiro", desc: "Cobrança recorrente: planos, assinaturas, geração automática de boletos.", actions: ["Planos", "Assinantes", "Ciclos"] },

  // ─────── PROJETOS & GESTÃO ───────
  projects: { phase: 1, status: "later", blade: "Modules/ProjectMgmt/Resources/views/", routes: ["/projects"], priority: "Gestão", desc: "Gestão de projetos: tarefas, milestones, timesheet, gantt. Cliente-facing opcional.", actions: ["Projetos", "Tarefas", "Timesheet"] },
  assets: { phase: 1, status: "later", blade: "Modules/AssetManagement/Resources/views/", routes: ["/assets"], priority: "Gestão", desc: "Patrimônio: equipamentos (Roland, HP Latex, Plotter), manutenção, depreciação.", actions: ["Inventário", "Manutenção", "Depreciação"] },
  auditoria: { phase: 1, status: "later", blade: "Modules/Auditoria/Resources/views/", routes: ["/auditoria"], priority: "Compliance", desc: "Trilha de auditoria: quem alterou o quê e quando. LGPD + controles internos.", actions: ["Logs", "Filtros", "Exportar"] },
  governance: { phase: 1, status: "later", blade: "Modules/Governance/Resources/views/", routes: ["/governance"], priority: "Compliance", desc: "Políticas, controles, indicadores de governança corporativa.", actions: ["Políticas", "Indicadores", "Aprovações"] },
  kb: { phase: 1, status: "done", blade: "—", routes: ["/kb"], priority: "Conhecimento", desc: "Base de conhecimento interna em React: SOPs, tutoriais, troubleshooting de equipamentos, command palette ⌘K, decision tree.", actions: ["Artigos", "Categorias", "Buscar"] },
  spreadsheet: { phase: 1, status: "later", blade: "Modules/Spreadsheet/Resources/views/", routes: ["/spreadsheet"], priority: "Suporte", desc: "Planilhas internas para cálculos ad-hoc e imports/exports tabulares.", actions: ["Nova planilha", "Templates", "Imports"] },

  // ─────── OUTROS ───────
  memcofre: { phase: 1, status: "done", blade: "—", routes: ["/memcofre"], priority: "Concluído", desc: "Já migrado para React. Cofre de senhas e credenciais (memória cofre).", actions: ["Senhas", "Cartões", "Notas"] },
  copiloto: { phase: 1, status: "done", blade: "—", routes: ["/copiloto"], priority: "Em iteração", desc: "Assistente IA — React + Inertia. Hoje em DRY_RUN; roadmap Vizra ADK + Meilisearch (ADRs 0035/0036).", actions: ["Nova conversa", "Histórico", "LGPD memória"] },
  site: { phase: 1, status: "done", blade: "—", routes: ["/site"], priority: "Concluído", desc: "Já migrado para React. CMS do site institucional (Modules/Cms).", actions: ["Páginas", "Posts", "Mídia"] },
  arquivos: { phase: 5, status: "later", blade: "Modules/Arquivos/Resources/views/", routes: ["/arquivos"], priority: "Baixa freq.", desc: "Drive interno por OS, cliente e projeto.", actions: ["Recentes", "Compartilhados", "Lixeira"] },

  // ─────── INTEGRAÇÕES ───────
  connector: { phase: 1, status: "later", blade: "Modules/Connector/Resources/views/", routes: ["/connector"], priority: "Integração", desc: "Connector UltimatePOS: integrações via API, webhooks, importers.", actions: ["Tokens API", "Webhooks", "Importers"] },
  woocommerce: { phase: 1, status: "later", blade: "Modules/Woocommerce/Resources/views/", routes: ["/woocommerce"], priority: "Integração", desc: "Sincronização WooCommerce: produtos, estoque, pedidos.", actions: ["Sync produtos", "Pedidos", "Logs"] },
  teammcp: { phase: 1, status: "later", blade: "Modules/TeamMcp/Resources/views/", routes: ["/team-mcp"], priority: "Experimental", desc: "Team MCP: expor o ERP via Model Context Protocol para agentes (Claude Desktop, Cursor). Ver ADR 0035.", actions: ["Tokens MCP", "Recursos", "Logs"] },
  srs: { phase: 1, status: "later", blade: "Modules/SRS/Resources/views/", routes: ["/srs"], priority: "Interno", desc: "SRS — Software Requirement Specifications. Documentação interna de requisitos.", actions: ["Specs", "Versões"] },

  // ─────── CONFIGURAÇÕES ───────
  prefs: { phase: 5, status: "later", blade: "Modules/Essentials/Resources/views/prefs/", routes: ["/prefs"], priority: "Config.", desc: "Preferências do usuário e da empresa.", actions: ["Pessoais", "Empresa", "Notificações"] },
  users: { phase: 5, status: "later", blade: "Modules/Essentials/Resources/views/users/", routes: ["/usuarios"], priority: "Config.", desc: "Usuários, papéis e permissões.", actions: ["Usuários", "Papéis", "Permissões"] },
  admin: { phase: 1, status: "later", blade: "Modules/Admin/Resources/views/", routes: ["/admin"], priority: "Config.", desc: "Admin core do UltimatePOS: configurações de business, planos, módulos ativos.", actions: ["Business", "Módulos", "Planos"] },
  superadmin: { phase: 1, status: "later", blade: "Modules/Superadmin/Resources/views/", routes: ["/superadmin"], priority: "Suporte", desc: "Superadmin UltimatePOS: multi-business, suporte cross-tenant.", actions: ["Tenants", "Suporte", "Logs"] },
  jana: { phase: 1, status: "later", blade: "Modules/Jana/Resources/views/", routes: ["/jana"], priority: "Referência", desc: "Módulo de referência canônica do projeto (ADR 0011). Imitar a estrutura ao criar/ajustar módulos.", actions: ["Ver código", "Padrões"] }
};

const PHASE_LABEL = {
  done: { label: "Migrado", cls: "ok" },
  partial: { label: "Parcial", cls: "partial" },
  next: { label: "Próximo", cls: "next" },
  queue: { label: "Fila", cls: "queue" },
  later: { label: "Backlog", cls: "muted" }
};

function ModuleStub({ routeId }) {
  const flat = MOCK.MENU_FLAT;
  const item = flat.find((i) => i.id === routeId);
  const Icon = item ? I[item.icon] : I.folder;
  const info = MIGRATION_INFO[routeId] || { phase: 5, status: "later", blade: "—", routes: ["/" + routeId], priority: "—", desc: "Tela legada do ERP. Será migrada conforme o roadmap MWART.", actions: [] };
  const phase = PHASE_LABEL[info.status];

  return (
    <div className="mod-stub">
      <div className="mod-stub-hero">
        <div className="mod-stub-hero-l">
          <div className="mod-stub-ico"><Icon size={28} /></div>
          <div>
            <div className="mod-stub-eyebrow">
              <span>Módulo</span>
              <span className="bc-sep">·</span>
              <span>Fase {info.phase}</span>
              <span className="bc-sep">·</span>
              <span>{info.priority}</span>
            </div>
            <h1>{item?.label || routeId}</h1>
            <p>{info.desc}</p>
          </div>
        </div>
        <div className="mod-stub-hero-r">
          <span className={`mod-stub-status ${phase.cls}`}>
            <span className="dot" />{phase.label}
          </span>
        </div>
      </div>

      <div className="mod-stub-grid">
        <section className="mod-stub-card">
          <h3>Ações típicas</h3>
          {info.actions.length > 0 ?
          <div className="mod-stub-actions">
              {info.actions.map((a, i) =>
            <button key={i} className={"mod-stub-action" + (i === 0 ? " primary" : "")}>
                  {i === 0 && <I.plus size={12} />}
                  {a}
                </button>
            )}
            </div> :
          <p className="mod-stub-empty">Sem ações definidas ainda.</p>}
        </section>

        <section className="mod-stub-card">
          <h3>Stack atual</h3>
          <dl className="mod-stub-dl">
            <dt>Front</dt>
            <dd>{info.status === "done" ? "React 19 + Inertia" : info.status === "partial" ? "Misto (Blade + React)" : "Blade legado"}</dd>
            <dt>Backend</dt>
            <dd>Laravel 13.6 + nWidart</dd>
            <dt>Rotas</dt>
            <dd className="mono">{info.routes.join(" · ")}</dd>
            <dt>Origem Blade</dt>
            <dd className="mono small">{info.blade}</dd>
          </dl>
        </section>

        <section className="mod-stub-card span">
          <h3>Roadmap MWART</h3>
          <ol className="mod-stub-roadmap">
            <li className={info.phase >= 1 ? "done" : ""}>
              <span className="step">1</span>
              <div><b>Inventário</b><small>Mapear views Blade, rotas e modelos</small></div>
            </li>
            <li className={info.phase >= 2 ? info.status === "next" ? "current" : "done" : ""}>
              <span className="step">2</span>
              <div><b>Adapter de menu</b><small>LegacyMenuAdapter expõe item via shell.menu</small></div>
            </li>
            <li className={info.status === "done" ? "done" : info.status === "partial" ? "current" : ""}>
              <span className="step">3</span>
              <div><b>Reescrita React</b><small>Inertia page + componentes shared</small></div>
            </li>
            <li className={info.status === "done" ? "done" : ""}>
              <span className="step">4</span>
              <div><b>Decommission</b><small>Flip do flag inertia: true e remoção do Blade</small></div>
            </li>
          </ol>
        </section>
      </div>

      <div className="mod-stub-foot">
        <span className="mod-stub-tag"><span className="t-mute">rota:</span> <span className="mono">/{routeId}</span></span>
        <span className="mod-stub-foot-spacer" />
        <button className="mod-stub-link">Ver no Blade atual ↗</button>
        <button className="mod-stub-link">Ver issue de migração ↗</button>
      </div>
    </div>);

}

function Header({ company, route, onSelectRoute, prodType, onProdType, chatTab, onChatTab }) {
  const flat = MOCK.MENU_FLAT;
  const item = flat.find((i) => i.id === route);
  const groupKey = item?.group && item.group !== "__user__" ? item.group : null;
  const group = groupKey ? MOCK.MENU.find((g) => g.group === groupKey) : null;
  const meta = groupKey ? (MOCK.GROUP_META || {})[groupKey] : null;
  const hue = meta?.hue ?? 220;
  // Ghosts: telas subordinadas ao hub (Vendas/Financeiro/Cobrança) — no PageHeader, não no sidebar
  const hubItem = group ? group.items.find((it) => it.id === route || (it.ghosts || []).some((g) => g.id === route)) : null;
  const ghosts = hubItem?.ghosts || [];

  // Quando dentro de área: mostra label da área à esquerda + pílulas das sub-telas
  // Quando solta (chat/tarefas/roadmap): mostra só o nome da rota
  const areaLabel = meta ? meta.label : item?.label || "Chat";

  // Topnav contextual: na página Produtos vira filtro de tipo (Todos/Produto/Serviço/Composição)
  const isProdutos = route === "produtos";
  const isChat = route === "chat";
  const PROD_TYPES = [
  { key: "all", label: "Todos", color: "oklch(0.55 0.02 250)" },
  { key: "produto", label: "Produto", color: "oklch(0.42 0.10 250)" },
  { key: "servico", label: "Serviço", color: "oklch(0.48 0.13 220)" },
  { key: "composicao", label: "Composição", color: "oklch(0.48 0.13 145)" }];

  const CHAT_TABS = [
  { key: "dashboard", label: "Dashboard", icon: "📊", color: "oklch(0.42 0.10 250)" },
  { key: "ia", label: "Analista IA", icon: "🤖", color: "oklch(0.45 0.15 290)" }];


  // Rotas de módulo (hubs/ghosts) usam o PageHeaderNav abaixo do título — não o topbar global.
  // O topbar contextual agora só serve à Jana (chat). Em Produtos as abas de tipo
  // foram movidas pra DENTRO da página, abaixo do page header ([W] 2026-06-22).
  if (!isChat) return null;

  return (
    <header className="topbar topbar--ctx">
      {isProdutos ?
      <nav className="topbar-tabs" aria-label="Filtro de tipo">
          {PROD_TYPES.map((t) => {
          const active = (prodType || "all") === t.key;
          return (
            <button key={t.key}
            className={"topbar-tab topbar-tab--type" + (active ? " active" : "")}
            onClick={() => onProdType?.(t.key)}
            style={active ? { borderBottomColor: t.color, color: t.color } : null}>
                {t.key !== "all" && <span className="topbar-tab-dot" style={{ background: t.color }} />}
                <span>{t.label}</span>
              </button>);

        })}
        </nav> :
      isChat ?
      <nav className="topbar-tabs" aria-label="Modo do Jana">
          {CHAT_TABS.map((t) => {
          const active = (chatTab || "dashboard") === t.key;
          return (
            <button key={t.key}
            className={"topbar-tab topbar-tab--chat" + (active ? " active" : "")}
            onClick={() => onChatTab?.(t.key)}
            style={active ? { borderBottomColor: t.color, color: t.color } : null}>
                <span className="topbar-tab-emoji">{t.icon}</span>
                <span>{t.label}</span>
              </button>);

        })}
        </nav> :
      group ?
      <nav className="topbar-tabs" aria-label={areaLabel}>
          {group.items.map((it) => {
          const Icon = I[it.icon];
          const isActive = route === it.id || (it.ghosts || []).some((g) => g.id === route);
          return (
            <button key={it.id}
            className={"topbar-tab" + (isActive ? " active" : "")}
            onClick={() => onSelectRoute(it.id)}
            style={isActive ? { borderBottomColor: `oklch(0.55 0.14 ${hue})`, color: `oklch(0.42 0.10 ${hue})` } : null}>
                {Icon && <Icon size={12} />}
                <span>{it.label}</span>
              </button>);

        })}
          {ghosts.length > 0 && <span aria-hidden="true" style={{ alignSelf: "center", color: "var(--text-mute)", opacity: 0.5, padding: "0 2px" }}>·</span>}
          {ghosts.map((g) => {
          const Icon = I[g.icon];
          const isActive = route === g.id;
          return (
            <button key={g.id}
            className={"topbar-tab topbar-tab--ghost" + (isActive ? " active" : "")}
            onClick={() => onSelectRoute(g.id)}
            title={`${hubItem?.label || ""} · ${g.label}`}
            style={{ opacity: isActive ? 1 : 0.66, ...(isActive ? { borderBottomColor: `oklch(0.55 0.14 ${hue})`, color: `oklch(0.42 0.10 ${hue})` } : {}) }}>
                {Icon && <Icon size={12} />}
                <span>{g.label}</span>
              </button>);

        })}
        </nav> :

      <div className="topbar-tabs topbar-tabs-empty" />
      }
    </header>);

}

// PageHeaderNav — barra de sub-navegação ENXUTA (só abas do hub + ghosts),
// renderizada ABAIXO do título de cada módulo. Sem nome de área, sem busca, sem sino.
function PageHeaderNav({ route }) {
  const group = MOCK.MENU.find((g) => (g.items || []).some((it) => it.id === route || (it.ghosts || []).some((gh) => gh.id === route)));
  if (!group) return null;
  const meta = (MOCK.GROUP_META || {})[group.group];
  const hue = meta?.hue ?? 220;
  const hubItem = (group.items || []).find((it) => it.id === route || (it.ghosts || []).some((g) => g.id === route));
  const ghosts = hubItem && hubItem.ghosts || [];
  const go = (id) => window.__selectRoute && window.__selectRoute(id);
  const onColor = "var(--accent)",onBorder = "var(--accent)";
  return (
    <nav className="ph-nav" aria-label={meta?.label || group.group}>
      {group.items.map((it) => {
        const Icon = I[it.icon];
        const isActive = route === it.id || (it.ghosts || []).some((g) => g.id === route);
        return (
          <button key={it.id} className={"topbar-tab" + (isActive ? " active" : "")}
          onClick={() => go(it.id)}
          style={isActive ? { borderBottomColor: onBorder, color: onColor } : null}>
            {Icon && <Icon size={12} />}<span>{it.label}</span>
          </button>);

      })}
      {ghosts.length > 0 && <span className="ph-nav-sep" aria-hidden="true">·</span>}
      {ghosts.map((g) => {
        const Icon = I[g.icon];
        const isActive = route === g.id;
        return (
          <button key={g.id} className={"topbar-tab topbar-tab--ghost" + (isActive ? " active" : "")}
          onClick={() => go(g.id)}
          style={{ opacity: isActive ? 1 : 0.66, ...(isActive ? { borderBottomColor: onBorder, color: onColor } : {}) }}>
            {Icon && <Icon size={12} />}<span>{g.label}</span>
          </button>);

      })}
    </nav>);

}
window.PageHeaderNav = PageHeaderNav;

function App() {
  const [company, setCompany] = useStateA(() => {
    try {
      const id = localStorage.getItem("oimpresso.company");
      return MOCK.COMPANIES.find((c) => c.id === id) || MOCK.COMPANIES[0];
    } catch (e) {return MOCK.COMPANIES[0];}
  });
  const [tab, setTab] = useStateA(() => {
    try {return localStorage.getItem("oimpresso.sidebar.tab") || "menu";}
    catch (e) {return "menu";}
  });
  const [route, setRoute] = useStateA(() => {
    try {return localStorage.getItem("oimpresso.route") || "chat";}
    catch (e) {return "chat";}
  });
  const [activeConvId, setActiveConvId] = useStateA(() => {
    try {return localStorage.getItem("oimpresso.conv") || "c1";}
    catch (e) {return "c1";}
  });
  const [showLaravel, setShowLaravel] = useStateA(false);
  const [linkedCollapsed, setLinkedCollapsed] = useStateA(() => {
    try {return localStorage.getItem("oimpresso.linked.collapsed") === "1";} catch (e) {return false;}
  });
  // Filtro de tipo de produto — controlado pelo topnav contextual
  const [prodType, setProdType] = useStateA(() => {
    try {return localStorage.getItem("oimpresso.prod.type") || "all";} catch (e) {return "all";}
  });
  useEffectA(() => {
    try {localStorage.setItem("oimpresso.prod.type", prodType);} catch (e) {}
  }, [prodType]);
  // Tab do Chat (Dashboard | Analista IA)
  const [chatTab, setChatTab] = useStateA(() => {
    try {return localStorage.getItem("oimpresso.chat.tab") || "dashboard";} catch (e) {return "dashboard";}
  });
  useEffectA(() => {
    try {localStorage.setItem("oimpresso.chat.tab", chatTab);} catch (e) {}
  }, [chatTab]);
  useEffectA(() => {
    try {localStorage.setItem("oimpresso.linked.collapsed", linkedCollapsed ? "1" : "0");} catch (e) {}
  }, [linkedCollapsed]);

  // ─── Sidebar: modo expanded | rail | hidden ───
  const [sbMode, setSbMode] = useStateA(() => {
    try {
      const v = localStorage.getItem("oimpresso.sidebar.mode");
      if (v === "rail" || v === "hidden" || v === "expanded") return v;
    } catch (e) {}
    // auto-rail em telas estreitas
    return typeof window !== "undefined" && window.innerWidth < 1280 ? "rail" : "expanded";
  });
  useEffectA(() => {
    try {localStorage.setItem("oimpresso.sidebar.mode", sbMode);} catch (e) {}
  }, [sbMode]);

  // ─── Mobile: sidebar vira menu flutuante (off-canvas) ───
  // [W] 2026-06-17: "tem como esse menu no celular ficar flutuante? o layout
  // da página deveria ser para celular tbm". Em ≤768px a sidebar sai do grid e
  // desliza por cima do conteúdo (que ocupa a largura toda).
  const [isMobile, setIsMobile] = useStateA(() => typeof window !== "undefined" && window.matchMedia("(max-width: 768px)").matches);
  const [mobileMenuOpen, setMobileMenuOpen] = useStateA(false);
  useEffectA(() => {
    const mq = window.matchMedia("(max-width: 768px)");
    const h = () => setIsMobile(mq.matches);
    mq.addEventListener("change", h);
    return () => mq.removeEventListener("change", h);
  }, []);
  // Fecha o menu ao trocar de rota (navegou → some o drawer)
  useEffectA(() => {setMobileMenuOpen(false);}, [route]);
  // Trava o scroll do body enquanto o drawer mobile está aberto
  useEffectA(() => {
    if (isMobile && mobileMenuOpen) {
      document.body.style.overflow = "hidden";
      return () => {document.body.style.overflow = "";};
    }
  }, [isMobile, mobileMenuOpen]);

  // Atalhos: ⌘\ alterna expanded↔rail, ⌘⇧\ oculta
  useEffectA(() => {
    const onKey = (e) => {
      const mod = e.metaKey || e.ctrlKey;
      if (!mod) return;
      if (e.key === "\\") {
        e.preventDefault();
        if (e.shiftKey) {
          setSbMode((m) => m === "hidden" ? "expanded" : "hidden");
        } else {
          setSbMode((m) => m === "rail" ? "expanded" : "rail");
        }
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  useEffectA(() => {localStorage.setItem("oimpresso.company", company.id);}, [company]);
  useEffectA(() => {localStorage.setItem("oimpresso.sidebar.tab", tab);}, [tab]);
  useEffectA(() => {localStorage.setItem("oimpresso.route", route);}, [route]);
  useEffectA(() => {if (activeConvId) localStorage.setItem("oimpresso.conv", activeConvId);}, [activeConvId]);

  // exposto p/ sidebar
  window.__company = company;
  window.__route = route;

  // ─── Tweaks expressivos ───
  const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
    "vibe": "workspace",
    "theme": "dark",
    "density": 50,
    "accentHue": 295,
    "showLaravel": false
  } /*EDITMODE-END*/;
  const [tweaks, setTweak] = useTweaks(TWEAK_DEFAULTS);

  // Aplica vibe no <html> + variáveis CSS
  useEffectA(() => {
    const root = document.documentElement;
    root.dataset.vibe = tweaks.vibe;

    // Tema claro/escuro — ativa o bloco [data-theme="dark"] do ds-v5/tokens.css
    // (existia mas nunca era ligado). Dark = padrão do projeto (W 2026-06-03);
    // claro segue disponível pelo toggle → sem regressão. Roxo canon intacto.
    root.dataset.theme = tweaks.theme;

    // Density: 0 = skim (28px row), 50 = normal (32), 100 = briefing (40)
    const rowH = 26 + tweaks.density / 100 * 16;
    root.style.setProperty("--row-h", `${rowH}px`);
    root.style.setProperty("--card-pad", `${8 + tweaks.density / 100 * 8}px`);
    root.style.setProperty("--card-gap", `${tweaks.density / 100 * 4}px`);
    root.dataset.density = tweaks.density < 30 ? "skim" : tweaks.density > 70 ? "briefing" : "normal";

    // HUE-ONLY (fix 2026-06-12 · verifier): só rotaciona o HUE; a luminância do accent/-soft
    // é DONA DO TEMA via tokens.css (oklch(... var(--accent-h))). Antes fixava o valor CLARO
    // inline e o inline vencia o [data-theme=dark] → --accent-soft claro-no-claro (ilegível)
    // em TODA tela dark (ex: pm-row.sel · pt-row.primary). Agora robusto a qualquer troca de tema.
    const h = tweaks.accentHue;
    root.style.setProperty("--accent-h", `${h}`);
    root.style.setProperty("--bubble-me", `oklch(0.55 0.15 ${h})`);
  }, [tweaks.vibe, tweaks.theme, tweaks.density, tweaks.accentHue]);

  useEffectA(() => {setShowLaravel(tweaks.showLaravel);}, [tweaks.showLaravel]);

  const handleSelectRoute = (r) => {
    setRoute(r);
    if (r === "chat") setTab("chat");
    // persiste última rota visitada por área (pra "lean sidebar → goToGroup" funcionar)
    const fi = MOCK.MENU_FLAT.find((i) => i.id === r);
    if (fi?.group && fi.group !== "__user__") {
      try {localStorage.setItem(`oimpresso.group.${fi.group}.route`, r);} catch (e) {}
    }
  };
  window.__selectRoute = handleSelectRoute;

  const handleSelectConv = (id) => {
    setActiveConvId(id);
    setRoute("chat");
  };

  // Permite componentes filhos (ex: telas PG) navegarem cross-tela via
  // window.PgGotoRoute('payment-gateways'). Ver pg-shell-adapters.jsx.
  useEffectA(() => {
    const onGoto = (e) => {if (e.detail) handleSelectRoute(e.detail);};
    window.addEventListener('pg:goto-route', onGoto);
    return () => window.removeEventListener('pg:goto-route', onGoto);
  }, []);

  let content;
  if (route === "chat") content = <window.JanaCockpit company={company} tab={chatTab} />;else
  if (route === "tarefas") content = <TasksPage />;else
  if (route === "perfil") content = <window.PerfilPage />;else
  if (route === "usuarios") content = <window.UsuariosPage />;else
  if (route === "os") content = <OsListPage />;else
  if (route === "clientes") content = <CliListPage />;else
  if (route === "orcamentos") content = <OrcListPage />;else
  if (route === "produtos") content = <ProdListPage typeFilter={prodType} onTypeFilter={setProdType} />;else
  if (route === "vendas") content = <VendasModule />;else
  if (route === "fila" || route === "acabamento" || route === "expedicao") content = <ProducaoPage />;else
  if (route === "financeiro") content = <window.FinanceiroPage initialTela="unified" />;else
  if (route === "fin-fluxo") content = <window.FinanceiroPage initialTela="fluxo" />;else
  if (route === "fin-concil") content = <window.FinanceiroPage initialTela="concil" />;else
  if (route === "fin-dre") content = <window.FinanceiroPage initialTela="dre" />;else
  if (route === "fin-pcontas") content = <window.FinanceiroPage initialTela="pcontas" />;else
  if (route === "fin-impostos") content = <window.FinanceiroPage initialTela="impostos" />;else
  if (route === "boletos") content = <window.BoletosPage />;else
  if (route === "cobranca") content = <window.CobrancaPage />;else
  if (route === "payment-gateways") content = <window.PaymentGatewaysPage />;else
  if (route === "sells-pg-preview") content = <window.SellsCobrancaPreviewPage />;else
  if (route === "compras") content = <window.ComprasPage />;else
  if (route === "oficinaauto") content = <window.OficinaPage />;else
  if (route === "oficina-os") content = <window.OficinaOSPage />;else
  if (route === "crm") content = <window.CrmPage />;else
  if (route === "crm-ficha") content = <window.CrmFicha />;else
  if (route === "inbox") content = <window.InboxPage data-comment-anchor="0aa4565a1c-small-841-23" />;else
  if (route === "equipe") content = <window.EquipePage />;else
  if (route === "kb") content = <window.KBPage />;else
  if (route === "projects" || route === "teammcp") content = <window.ForjaPage />;
  // Cobrança Recorrente (F1) — sub-nav espelha git RecurringBilling (Assinaturas/Planos/Faturas/Configurações)
  else if (route === "recurring") content = <window.CobrancaRecorrentePage view="assinaturas" />;else
  if (route === "rb-assinaturas") content = <window.CobrancaRecorrentePage view="assinaturas" />;else
  if (route === "rb-planos") content = <window.CobrancaRecorrentePage view="planos" />;else
  if (route === "rb-faturas") content = <window.CobrancaRecorrentePage view="faturas" />;else
  if (route === "rb-config") content = <window.CobrancaRecorrentePage view="config" />;else
  if ([
  "crm", "inbox", "equipe", "cv", "relatorios", "fiscal", "nfe", "nfse", "assets",
  "catalogue", "brief", "repair", "manufacturing", "ads", "vestuario",
  "portalos", "auditoria"].
  includes(route)) content = <window.MockupPage route={route === "nfe" || route === "nfse" ? "fiscal" : route} />;else
  content = <ModuleStub routeId={route} />;

  return (
    <div className={"app app--sb-" + (isMobile ? "expanded" : sbMode) + (isMobile ? " app--mobile" : "") + (isMobile && mobileMenuOpen ? " app--mob-open" : "")}>
      {(isMobile || sbMode !== "hidden") &&
      <Sidebar
        company={company} onCompany={setCompany}
        tab={tab} onTab={setTab}
        activeConvId={activeConvId} onSelectConv={handleSelectConv}
        activeRoute={route} onSelectRoute={handleSelectRoute}
        mode={isMobile ? "expanded" : sbMode} onModeChange={setSbMode} />
      }
      {!isMobile && sbMode === "hidden" &&
      <window.SidebarReopenHandle onOpen={() => setSbMode("expanded")} />
      }
      {isMobile &&
      <button
        className="sb-mobile-toggle"
        onClick={() => setMobileMenuOpen((v) => !v)}
        aria-label={mobileMenuOpen ? "Fechar menu" : "Abrir menu"}
        aria-expanded={mobileMenuOpen}>
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            {mobileMenuOpen ?
          <path d="M18 6 6 18M6 6l12 12" /> :
          <path d="M3 12h18M3 6h18M3 18h18" />}
          </svg>
        </button>
      }
      {isMobile && mobileMenuOpen &&
      <div className="sb-mobile-backdrop" onClick={() => setMobileMenuOpen(false)} />
      }
      <div className="main">
        <Header company={company} route={route} onSelectRoute={handleSelectRoute}
        prodType={prodType} onProdType={setProdType}
        chatTab={chatTab} onChatTab={setChatTab} />
        <div className="main-body"><RouteErrorBoundary key={route}>{content}</RouteErrorBoundary></div>
      </div>
      {showLaravel && <LaravelPanel onClose={() => setShowLaravel(false)} />}
      <TweaksPanel title="Tweaks">
        <TweakSection label="Vibe" />
        <TweakRadio
          label="Tema"
          value={tweaks.theme}
          options={["dark", "light"]}
          onChange={(v) => setTweak("theme", v)} />
        <TweakRadio
          label="Atmosfera"
          value={tweaks.vibe}
          options={["workspace", "daylight", "focus"]}
          onChange={(v) => setTweak("vibe", v)} />

        <TweakSection label="Densidade" />
        <TweakSlider
          label="Skim ↔ Briefing"
          value={tweaks.density}
          min={0} max={100} step={5}
          unit="%"
          onChange={(v) => setTweak("density", v)} />

        <TweakSection label="Cor" />
        <TweakSlider
          label="Tom do accent"
          value={tweaks.accentHue}
          min={0} max={360} step={10}
          unit="°"
          onChange={(v) => setTweak("accentHue", v)} />

        <TweakSection label="Sistema" />
        <TweakToggle
          label="Painel Laravel"
          value={tweaks.showLaravel}
          onChange={(v) => setTweak("showLaravel", v)} />
      </TweaksPanel>
    </div>);

}

ReactDOM.createRoot(document.getElementById("app")).render(<App />);