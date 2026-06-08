// app.jsx — orquestra rotas Chat / Tarefas / Módulo legado
const { useState: useStateA, useEffect: useEffectA } = React;

function ChatPage({ company, activeConvId, onSelectConv, linkedCollapsed, onToggleLinked }) {
  const conversations = (MOCK.CONV[company.id] || []);
  const [convs, setConvs] = useStateA(conversations);
  const [convTab, setConvTab] = useStateA(() => {
    try { return localStorage.getItem("oimpresso.chat.tab") || "todas"; } catch (e) { return "todas"; }
  });
  const [convQuery, setConvQuery] = useStateA("");

  useEffectA(() => { setConvs(MOCK.CONV[company.id] || []); }, [company.id]);
  useEffectA(() => { try { localStorage.setItem("oimpresso.chat.tab", convTab); } catch (e) {} }, [convTab]);

  const conv = convs.find(c => c.id === activeConvId) || convs[0];

  useEffectA(() => {
    if (!conv || !conv.unread) return;
    setConvs(cs => cs.map(c => c.id === conv.id ? { ...c, unread: 0 } : c));
  }, [conv?.id]);

  const handleSend = (text, raw) => {
    setConvs(cs => cs.map(c => {
      if (c.id !== conv.id) return c;
      const newMsg = raw || { d:"Hoje", who:"você", side:"me", t:text, time:"agora", read:false };
      return { ...c, msgs: [...c.msgs, newMsg], time: "agora", preview: (raw?.who?raw.who+': ':'você: ') + (raw?.t||text||'') };
    }));
  };

  return (
    <div className="chat-page">
      <div className="chat-main">
        <ConvTabsBar tab={convTab} onTab={setConvTab} query={convQuery} onQuery={setConvQuery}/>
        <Thread conv={conv} onSend={handleSend}/>
      </div>
      <LinkedAppsPanel conv={conv} collapsed={linkedCollapsed} onToggle={onToggleLinked}/>
    </div>
  );
}

function ConvTabsBar({ tab, onTab, query, onQuery }) {
  const tabs = [
    { id: "todas",  label: "Todos" },
    { id: "os",     label: "OS" },
    { id: "team",   label: "Equipe" },
    { id: "client", label: "Clientes" },
  ];
  return (
    <div className="chat-tabsbar">
      <div className="chat-tabs">
        {tabs.map(t => (
          <button key={t.id}
                  className={"chat-tab" + (tab === t.id ? " active" : "")}
                  onClick={() => onTab(t.id)}>{t.label}</button>
        ))}
      </div>
      <div className="chat-search">
        <I.search size={12}/>
        <input placeholder="Buscar nesta conversa..." value={query} onChange={e => onQuery(e.target.value)}/>
      </div>
    </div>
  );
}

// Stub para módulos do menu — em produção, abre Inertia page do módulo
const MIGRATION_INFO = {
  os:         { phase: 2, status: "next",     blade: "Modules/Officeimpresso/Resources/views/os/", routes: ["/os","/os/create","/os/{id}"], priority: "Piloto", desc: "Núcleo do ERP. Listagem + form + detalhe da Ordem de Serviço.", actions:["Nova OS","Listar abertas","Fila de produção"] },
  clientes:   { phase: 3, status: "queue",    blade: "Modules/Officeimpresso/Resources/views/clientes/", routes: ["/clientes"], priority: "Alta freq.", desc: "CRUD de clientes + histórico de compras + contatos.", actions:["Novo cliente","Importar","Aniversariantes"] },
  produtos:   { phase: 3, status: "queue",    blade: "Modules/Officeimpresso/Resources/views/produtos/", routes: ["/produtos"], priority: "Alta freq.", desc: "Catálogo de produtos com preço, insumos e especificações técnicas.", actions:["Novo produto","Tabela de preço","Insumos"] },
  orcamentos: { phase: 3, status: "queue",    blade: "Modules/Officeimpresso/Resources/views/orcamentos/", routes: ["/orcamentos"], priority: "Alta freq.", desc: "Geração de orçamentos a partir do catálogo + envio por e-mail/WhatsApp.", actions:["Novo orçamento","Aprovados","A vencer"] },
  vendas:     { phase: 3, status: "queue",    blade: "Modules/Officeimpresso/Resources/views/vendas/", routes: ["/vendas"], priority: "Alta freq.", desc: "Pedidos confirmados, faturamento e nota fiscal.", actions:["Hoje","Mês","Por cliente"] },
  fila:       { phase: 4, status: "later",    blade: "Modules/Officeimpresso/Resources/views/producao/fila/", routes: ["/fila"], priority: "Operacional", desc: "Fila de impressão por equipamento — sequenciamento e prioridade.", actions:["Roland 540","HP Latex","Plotter recorte"] },
  acabamento: { phase: 4, status: "later",    blade: "Modules/Officeimpresso/Resources/views/producao/acabamento/", routes: ["/acabamento"], priority: "Operacional", desc: "Pós-impressão: corte, laminação, aplicação.", actions:["Pendentes","Concluídos hoje"] },
  expedicao:  { phase: 4, status: "later",    blade: "Modules/Officeimpresso/Resources/views/producao/expedicao/", routes: ["/expedicao"], priority: "Operacional", desc: "Embalagem, romaneio e entrega.", actions:["A entregar","Roteirizar","Concluídas"] },
  ponto:      { phase: 4, status: "partial",  blade: "Modules/PontoWr2/Resources/views/", routes: ["/ponto","/ponto/espelho"], priority: "Núcleo RH", desc: "Bater ponto, espelho do mês e justificativas. Parcialmente em React.", actions:["Bater ponto","Meu espelho","Justificar"] },
  equipes:    { phase: 5, status: "later",    blade: "Modules/Officeimpresso/Resources/views/equipes/", routes: ["/equipes"], priority: "Baixa freq.", desc: "Times, escalas e responsáveis por etapas de produção.", actions:["Times","Escala da semana"] },
  financeiro: { phase: 1, status: "done",     blade: "—", routes: ["/financeiro"], priority: "Concluído", desc: "Já migrado para React. Contas a pagar/receber, fluxo de caixa, conciliação.", actions:["Contas a pagar","Contas a receber","Fluxo"] },
  relatorios: { phase: 5, status: "later",    blade: "Modules/Officeimpresso/Resources/views/relatorios/", routes: ["/relatorios"], priority: "Baixa freq.", desc: "BI: vendas por período, margem, produtividade.", actions:["Vendas","Margem","Produtividade"] },
  memcofre:   { phase: 1, status: "done",     blade: "—", routes: ["/memcofre"], priority: "Concluído", desc: "Já migrado para React. Cofre de senhas e credenciais.", actions:["Senhas","Cartões","Notas"] },
  copiloto:   { phase: 1, status: "done",     blade: "—", routes: ["/copiloto"], priority: "Concluído", desc: "Já migrado para React. Assistente IA — em iteração ativa.", actions:["Nova conversa","Histórico"] },
  site:       { phase: 1, status: "done",     blade: "—", routes: ["/site"], priority: "Concluído", desc: "Já migrado para React. CMS do site institucional.", actions:["Páginas","Posts","Mídia"] },
  arquivos:   { phase: 5, status: "later",    blade: "Modules/Essentials/Resources/views/arquivos/", routes: ["/arquivos"], priority: "Baixa freq.", desc: "Drive interno por OS, cliente e projeto.", actions:["Recentes","Compartilhados","Lixeira"] },
  prefs:      { phase: 5, status: "later",    blade: "Modules/Essentials/Resources/views/prefs/", routes: ["/prefs"], priority: "Config.", desc: "Preferências do usuário e da empresa.", actions:["Pessoais","Empresa","Notificações"] },
  users:      { phase: 5, status: "later",    blade: "Modules/Essentials/Resources/views/users/", routes: ["/usuarios"], priority: "Config.", desc: "Usuários, papéis e permissões.", actions:["Usuários","Papéis","Permissões"] },
};

const PHASE_LABEL = {
  done:    { label: "Migrado",       cls: "ok"   },
  partial: { label: "Parcial",        cls: "partial" },
  next:    { label: "Próximo",       cls: "next" },
  queue:   { label: "Fila",           cls: "queue" },
  later:   { label: "Backlog",        cls: "muted" },
};

function ModuleStub({ routeId }) {
  const flat = MOCK.MENU.flatMap(e => e.group ? e.items : [e]);
  const item = flat.find(i => i.id === routeId);
  const Icon = item ? I[item.icon] : I.folder;
  const info = MIGRATION_INFO[routeId] || { phase: 5, status: "later", blade: "—", routes: ["/" + routeId], priority: "—", desc: "Tela legada do ERP. Será migrada conforme o roadmap MWART.", actions:[] };
  const phase = PHASE_LABEL[info.status];

  return (
    <div className="mod-stub">
      <div className="mod-stub-hero">
        <div className="mod-stub-hero-l">
          <div className="mod-stub-ico"><Icon size={28}/></div>
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
            <span className="dot"/>{phase.label}
          </span>
        </div>
      </div>

      <div className="mod-stub-grid">
        <section className="mod-stub-card">
          <h3>Ações típicas</h3>
          {info.actions.length > 0 ? (
            <div className="mod-stub-actions">
              {info.actions.map((a, i) => (
                <button key={i} className={"mod-stub-action" + (i===0 ? " primary" : "")}>
                  {i === 0 && <I.plus size={12}/>}
                  {a}
                </button>
              ))}
            </div>
          ) : <p className="mod-stub-empty">Sem ações definidas ainda.</p>}
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
            <li className={info.phase >= 2 ? (info.status==="next"?"current":"done") : ""}>
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
        <span className="mod-stub-foot-spacer"/>
        <button className="mod-stub-link">Ver no Blade atual ↗</button>
        <button className="mod-stub-link">Ver issue de migração ↗</button>
      </div>
    </div>
  );
}

function Header({ company, route }) {
  // breadcrumb de navegação
  const flat = MOCK.MENU.flatMap(e => e.group ? e.items.map(i=>({...i, group:e.group})) : [{...e, group:null}]);
  const item = flat.find(i => i.id === route);
  const crumb = item
    ? (item.group ? [item.group, item.label] : [item.label])
    : ["Chat"];

  return (
    <header className="topbar">
      <div className="bc">
        <span className="bc-tenant">{company.name}</span>
        <span className="bc-sep">/</span>
        {crumb.map((c, i) => (
          <React.Fragment key={i}>
            <span className={i === crumb.length-1 ? "bc-cur" : "bc-up"}>{c}</span>
            {i < crumb.length-1 && <span className="bc-sep">›</span>}
          </React.Fragment>
        ))}
      </div>
      <div className="topbar-r">
        <button className="icon-btn"><I.search size={14}/></button>
        <button className="icon-btn"><I.bell size={14}/></button>
      </div>
    </header>
  );
}

function App() {
  const [company, setCompany] = useStateA(() => {
    try {
      const id = localStorage.getItem("oimpresso.company");
      return MOCK.COMPANIES.find(c => c.id === id) || MOCK.COMPANIES[0];
    } catch (e) { return MOCK.COMPANIES[0]; }
  });
  const [tab, setTab] = useStateA(() => {
    try { return localStorage.getItem("oimpresso.sidebar.tab") || "menu"; }
    catch (e) { return "menu"; }
  });
  const [route, setRoute] = useStateA(() => {
    try { return localStorage.getItem("oimpresso.route") || "tarefas"; }
    catch (e) { return "tarefas"; }
  });
  const [activeConvId, setActiveConvId] = useStateA(() => {
    try { return localStorage.getItem("oimpresso.conv") || "c1"; }
    catch (e) { return "c1"; }
  });
  const [showLaravel, setShowLaravel] = useStateA(false);
  const [linkedCollapsed, setLinkedCollapsed] = useStateA(() => {
    try { return localStorage.getItem("oimpresso.linked.collapsed") === "1"; } catch (e) { return false; }
  });
  useEffectA(() => {
    try { localStorage.setItem("oimpresso.linked.collapsed", linkedCollapsed ? "1" : "0"); } catch (e) {}
  }, [linkedCollapsed]);

  useEffectA(() => { localStorage.setItem("oimpresso.company", company.id); }, [company]);
  useEffectA(() => { localStorage.setItem("oimpresso.sidebar.tab", tab); }, [tab]);
  useEffectA(() => { localStorage.setItem("oimpresso.route", route); }, [route]);
  useEffectA(() => { if (activeConvId) localStorage.setItem("oimpresso.conv", activeConvId); }, [activeConvId]);

  // exposto p/ sidebar
  window.__company = company;

  // ─── Tweaks expressivos ───
  const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
    "vibe": "workspace",
    "density": 50,
    "accentHue": 220,
    "showLaravel": false
  }/*EDITMODE-END*/;
  const [tweaks, setTweak] = useTweaks(TWEAK_DEFAULTS);

  // Aplica vibe no <html> + variáveis CSS
  useEffectA(() => {
    const root = document.documentElement;
    root.dataset.vibe = tweaks.vibe;

    // Density: 0 = skim (28px row), 50 = normal (32), 100 = briefing (40)
    const rowH = 26 + (tweaks.density / 100) * 16;
    root.style.setProperty("--row-h", `${rowH}px`);
    root.style.setProperty("--card-pad",  `${8 + (tweaks.density/100)*8}px`);
    root.style.setProperty("--card-gap",  `${(tweaks.density/100)*4}px`);
    root.dataset.density = tweaks.density < 30 ? "skim" : tweaks.density > 70 ? "briefing" : "normal";

    // Accent hue — repinta accent e origens harmonicamente
    const h = tweaks.accentHue;
    root.style.setProperty("--accent",      `oklch(0.58 0.12 ${h})`);
    root.style.setProperty("--accent-2",    `oklch(0.66 0.12 ${h})`);
    root.style.setProperty("--accent-soft", `oklch(0.94 0.04 ${h})`);
    root.style.setProperty("--bubble-me",   `oklch(0.58 0.12 ${h})`);
    // Origens: rotacionam proporcionalmente em torno do accent
    root.style.setProperty("--origin-OS-bg",  `oklch(0.93 0.07 ${(h+210)%360})`);
    root.style.setProperty("--origin-OS-fg",  `oklch(0.40 0.10 ${(h+210)%360})`);
    root.style.setProperty("--origin-CRM-bg", `oklch(0.92 0.06 ${h})`);
    root.style.setProperty("--origin-CRM-fg", `oklch(0.40 0.10 ${h})`);
    root.style.setProperty("--origin-FIN-bg", `oklch(0.93 0.07 ${(h+285)%360})`);
    root.style.setProperty("--origin-FIN-fg", `oklch(0.36 0.10 ${(h+285)%360})`);
    root.style.setProperty("--origin-PNT-bg", `oklch(0.93 0.06 ${(h+75)%360})`);
    root.style.setProperty("--origin-PNT-fg", `oklch(0.40 0.10 ${(h+75)%360})`);
  }, [tweaks.vibe, tweaks.density, tweaks.accentHue]);

  useEffectA(() => { setShowLaravel(tweaks.showLaravel); }, [tweaks.showLaravel]);

  const handleSelectRoute = (r) => {
    setRoute(r);
    if (r === "chat") setTab("chat");
  };

  const handleSelectConv = (id) => {
    setActiveConvId(id);
    setRoute("chat");
  };

  let content;
  if (route === "chat")          content = <ChatPage company={company} activeConvId={activeConvId} onSelectConv={setActiveConvId} linkedCollapsed={linkedCollapsed} onToggleLinked={() => setLinkedCollapsed(v => !v)}/>;
  else if (route === "tarefas")  content = <TasksPage/>;
  else if (route === "os")       content = <OsListPage/>;
  else                            content = <ModuleStub routeId={route}/>;

  return (
    <div className="app">
      <Sidebar
        company={company} onCompany={setCompany}
        tab={tab} onTab={setTab}
        activeConvId={activeConvId} onSelectConv={handleSelectConv}
        activeRoute={route} onSelectRoute={handleSelectRoute}/>
      <div className="main">
        <Header company={company} route={route}/>
        <div className="main-body">{content}</div>
      </div>
      {showLaravel && <LaravelPanel onClose={() => setShowLaravel(false)}/>}
      <TweaksPanel title="Tweaks">
        <TweakSection label="Vibe"/>
        <TweakRadio
          label="Atmosfera"
          value={tweaks.vibe}
          options={["workspace","daylight","focus"]}
          onChange={(v) => setTweak("vibe", v)}/>

        <TweakSection label="Densidade"/>
        <TweakSlider
          label="Skim ↔ Briefing"
          value={tweaks.density}
          min={0} max={100} step={5}
          unit="%"
          onChange={(v) => setTweak("density", v)}/>

        <TweakSection label="Cor"/>
        <TweakSlider
          label="Tom do accent"
          value={tweaks.accentHue}
          min={0} max={360} step={10}
          unit="°"
          onChange={(v) => setTweak("accentHue", v)}/>

        <TweakSection label="Sistema"/>
        <TweakToggle
          label="Painel Laravel"
          value={tweaks.showLaravel}
          onChange={(v) => setTweak("showLaravel", v)}/>
      </TweaksPanel>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<App/>);
