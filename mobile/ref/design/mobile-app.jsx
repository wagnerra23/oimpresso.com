// Main mobile app shell: bottom-tab nav + per-tab stack
const { Ic } = window;

const TABS = [
  { id: "inicio",   label: "Início",    ic: "home"   },
  { id: "tarefas",  label: "Tarefas",   ic: "inbox"  },
  { id: "pedidos",  label: "Pedidos",   ic: "file"   },
  { id: "producao", label: "Produção",  ic: "printer"},
  { id: "mais",     label: "Mais",      ic: "layers" },
];

// Home → atalho "Finanças" agora aponta pra detalhes; Início → "Ver detalhes" deve abrir financas
// via push (não gotoTab pq finanças deixou de ser tab).

function MobileApp({ platform = "ios", tweaks, setTweak }) {
  // One stack per tab, persisted in component state.
  const initialStacks = () => Object.fromEntries(TABS.map(t => [t.id, [{ name: t.id, params: {} }]]));
  const [stacks, setStacks] = React.useState(initialStacks);
  const [tab, setTab] = React.useState("inicio");

  // Reset stack count badge animation
  React.useEffect(() => {
    // ensure stacks contain at least the root for each tab
    setStacks(s => {
      const next = { ...s };
      TABS.forEach(t => { if (!next[t.id] || next[t.id].length === 0) next[t.id] = [{ name: t.id, params: {} }]; });
      return next;
    });
  }, []);

  const cur = stacks[tab] || [{ name: tab, params: {} }];
  const top = cur[cur.length - 1];

  const nav = React.useMemo(() => ({
    push: (name, params = {}) => setStacks(s => ({ ...s, [tab]: [...(s[tab] || []), { name, params }] })),
    pop: () => setStacks(s => {
      const c = s[tab] || [];
      if (c.length <= 1) return s;
      return { ...s, [tab]: c.slice(0, -1) };
    }),
    replace: (name, params = {}) => setStacks(s => ({ ...s, [tab]: [...(s[tab] || []).slice(0, -1), { name, params }] })),
    canPop: () => (stacks[tab] || []).length > 1,
    gotoTab: (id) => {
      // reset selected tab's stack to root unless already there
      setTab(id);
    },
  }), [tab, stacks]);

  const ctx = {
    tenant: tweaks.tenant || "oi",
    density: tweaks.density || "normal",
    theme: tweaks.theme || "dark",
    taskState: tweaks.taskState || "cheio",
  };

  const Screen = (() => {
    const { Screens } = window;
    const map = {
      inicio:   Screens.HomeScreen,
      tarefas:  Screens.TarefasScreen,
      tarefa:   Screens.TarefaDetalheScreen,
      pedidos:  Screens.PedidosScreen,
      pedido:   Screens.PedidoDetalheScreen,
      "novo-pedido": NovoPedidoStub,
      produtos: Screens.ProdutosScreen,
      produto:  Screens.ProdutoDetalheScreen,
      mais:     Screens.MaisScreen,
      "venda-rapida": Screens.VendaRapidaScreen,
      financas: Screens.FinancasScreen,
      transacao:Screens.TransacaoDetalheScreen,
      clientes: Screens.ClientesScreen,
      cliente:  Screens.ClienteDetalheScreen,
      producao: Screens.ProducaoScreen,
      "producao-job": Screens.ProducaoJobDetalheScreen,
      notificacoes: Screens.NotifsScreen,
      perfil:   Screens.PerfilScreen,
      empresa:  Screens.EmpresaScreen,
    };
    return map[top.name] || Screens.HomeScreen;
  })();

  // Task badge
  const taskBadge = (() => {
    const all = ctx.taskState === "vazio" ? [] :
                ctx.taskState === "urgente" ? window.MOCK.TASKS_URGENT :
                window.MOCK.TASKS_FULL;
    const urg = all.filter(t => t.urgent).length;
    return urg > 0 ? urg : null;
  })();

  return (
    <div className="oi oi-app" data-platform={platform} data-theme={ctx.theme} data-density={ctx.density}>
      <div className="oi-screen">
        <Screen nav={nav} ctx={ctx} params={top.params} setTweak={setTweak} />
      </div>
      <nav className="oi-tabbar">
        {TABS.map(t => {
          const active = t.id === tab;
          const badge = t.id === "tarefas" ? taskBadge : null;
          const Icon = Ic[t.ic];
          return (
            <button key={t.id} className={"oi-tab" + (active ? " active" : "")}
                    onClick={() => setTab(t.id)}>
              <div className="ico-wrap">
                <Icon size={22} />
              </div>
              <span>{t.label}</span>
              {badge && <span className="pin">{badge}</span>}
            </button>
          );
        })}
      </nav>
    </div>
  );
}

function NovoPedidoStub({ nav, ctx }) {
  const { ScreenHeader, DetailHeader } = window.Screens;
  return (
    <>
      <DetailHeader nav={nav} title="Novo pedido" />
      <div className="oi-scroll">
        <div className="oi-section">
          <p style={{ margin: "0 0 12px", fontSize: 13, color: "var(--text-dim)" }}>
            Selecione cliente, produto e prazo. Você pode salvar como rascunho.
          </p>
          <div className="oi-list card">
            <Step ic="user" label="Cliente" v="Marília Costa" />
            <Step ic="box" label="Produto" v="1.000 cartões 9x5 4/4" />
            <Step ic="calendar" label="Prazo" v="Hoje 18:00" />
            <Step ic="dollar" label="Valor" v="R$ 248,00" last />
          </div>
        </div>
        <div className="oi-section">
          <button className="oi-btn block primary" onClick={() => nav.pop()}>Criar OS</button>
        </div>
      </div>
    </>
  );
}
function Step({ ic, label, v, last }) {
  return (
    <div className="oi-list-row" style={{ minHeight: 56, ...(last ? { borderBottom: 0 } : {}) }}>
      <div style={{ color: "var(--text-mute)" }}>{React.createElement(Ic[ic], { size: 18 })}</div>
      <div style={{ flex: 1 }}>
        <div style={{ fontSize: 11.5, color: "var(--text-mute)", fontWeight: 600, textTransform: "uppercase", letterSpacing: ".06em" }}>{label}</div>
        <div style={{ fontSize: 14, fontWeight: 500 }}>{v}</div>
      </div>
      <Ic.chevR color="var(--text-mute)" />
    </div>
  );
}

window.MobileApp = MobileApp;
