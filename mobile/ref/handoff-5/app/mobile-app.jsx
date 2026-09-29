// Main mobile app shell: bottom-tab nav + per-tab stack
const { Ic } = window;

// Início e Mais são FIXOS; o miolo da barra vem do perfil de menu ativo.
const FIXED_START = { id: "inicio", label: "Início", ic: "home" };
const FIXED_END   = { id: "mais",   label: "Mais",   ic: "layers" };

function MobileApp({ platform = "ios", tweaks, setTweak }) {
  const [stacks, setStacks] = React.useState(() => ({
    inicio: [{ name: "inicio", params: {} }],
    mais:   [{ name: "mais", params: {} }],
  }));
  const [tab, setTab] = React.useState("inicio");
  const [toasts, setToasts] = React.useState([]);

  // Toast global: window.oiToast(msg, tone?) — feedback leve para ações inline
  React.useEffect(() => {
    window.oiToast = (msg, tone = "") => {
      const id = Date.now() + Math.random();
      setToasts(ts => [...ts, { id, msg, tone }]);
      setTimeout(() => setToasts(ts => ts.filter(t => t.id !== id)), 2400);
    };
    return () => { delete window.oiToast; };
  }, []);

  // Perfil de menu ativo → monta os slots do meio (até 3)
  const perfis = window.MOCK.MENU_PERFIS;
  const activePerfil = perfis.find(p => p.id === (tweaks.perfil || perfis[0].id)) || perfis[0];
  const midSlots = activePerfil.mods.slice(0, 3)
    .map(id => window.MOCK.menuModule(id))
    .filter(Boolean);
  const barSlots = [FIXED_START, ...midSlots, FIXED_END];
  const barIds = barSlots.map(s => s.id);

  // Se a aba atual saiu da barra (troca de perfil), volta pro Início
  React.useEffect(() => {
    if (!barIds.includes(tab)) setTab("inicio");
  }, [activePerfil.id]);

  const safeTab = barIds.includes(tab) ? tab : "inicio";
  const cur = stacks[safeTab] || [{ name: safeTab, params: {} }];
  const top = cur[cur.length - 1];

  const nav = React.useMemo(() => ({
    push: (name, params = {}) => setStacks(s => ({ ...s, [safeTab]: [...(s[safeTab] || [{ name: safeTab, params: {} }]), { name, params }] })),
    pop: () => setStacks(s => {
      const c = s[safeTab] || [];
      if (c.length <= 1) return s;
      return { ...s, [safeTab]: c.slice(0, -1) };
    }),
    replace: (name, params = {}) => setStacks(s => ({ ...s, [safeTab]: [...(s[safeTab] || [{ name: safeTab, params: {} }]).slice(0, -1), { name, params }] })),
    canPop: () => (stacks[safeTab] || []).length > 1,
    gotoTab: (id) => {
      if (barIds.includes(id)) { setTab(id); return; }
      // módulo fora da barra → empilha como tela na aba atual
      setStacks(s => ({ ...s, [safeTab]: [...(s[safeTab] || [{ name: safeTab, params: {} }]), { name: id, params: {} }] }));
    },
  }), [safeTab, stacks, barIds.join(",")]);

  const ctx = {
    tenant: tweaks.tenant || "oi",
    density: tweaks.density || "normal",
    theme: tweaks.theme || "dark",
    taskState: tweaks.taskState || "cheio",
    prodView: tweaks.prodView || "prazo",
    papel: tweaks.papel || "gerente",
    perfil: activePerfil.id,
  };

  const Screen = (() => {
    const { Screens } = window;
    const map = {
      inicio:   Screens.HomeScreen,
      tarefas:  Screens.TarefasScreen,
      tarefa:   Screens.TarefaDetalheScreen,
      pedidos:  Screens.PedidosScreen,
      pedido:   Screens.PedidoDetalheScreen,
      "novo-pedido": Screens.NovoPedidoScreen,
      produtos: Screens.ProdutosScreen,
      produto:  Screens.ProdutoDetalheScreen,
      "novo-produto": Screens.NovoProdutoScreen,
      "editar-produto": Screens.NovoProdutoScreen,
      mais:     Screens.MaisScreen,
      "venda-rapida": Screens.VendaRapidaScreen,
      vendas:   Screens.PedidosScreen,
      financas: Screens.FinancasScreen,
      transacao:Screens.TransacaoDetalheScreen,
      relatorios: Screens.RelatoriosScreen,
      relatorio: Screens.RelatorioDetalheScreen,
      equipe:   Screens.EquipeScreen,
      perfis:   Screens.PerfisScreen,
      "perfil-edit": Screens.PerfilEditScreen,
      clientes: Screens.ClientesScreen,
      cliente:  Screens.ClienteDetalheScreen,
      "novo-cliente": Screens.NovoClienteScreen,
      "editar-cliente": Screens.NovoClienteScreen,
      "cliente-dados": Screens.ClienteDadosScreen,
      producao: Screens.ProducaoScreen,
      "producao-job": Screens.ProducaoJobDetalheScreen,
      manutencao: Screens.ManutencaoScreen,
      "manut-os": Screens.ManutOsDetalheScreen,
      "nova-manut": Screens.NovaManutencaoScreen,
      locais: Screens.LocaisScreen,
      "novo-local": Screens.NovoLocalScreen,
      equipamentos: Screens.EquipamentosScreen,
      equipamento: Screens.EquipamentoDetalheScreen,
      "novo-equipamento": Screens.NovoEquipamentoScreen,
      "editar-equipamento": Screens.NovoEquipamentoScreen,
      notificacoes: Screens.NotifsScreen,
      perfil:   Screens.PerfilScreen,
      empresa:  Screens.EmpresaScreen,
    };
    return map[top.name] || Screens.HomeScreen;
  })();

  // Task badge (reativo às mutações do store)
  const [taskMut] = window.useStore("tasks.mut", window.OITasks.EMPTY_MUT);
  const taskBadge = (() => {
    const base = ctx.taskState === "vazio" ? [] :
                 ctx.taskState === "urgente" ? window.MOCK.TASKS_URGENT :
                 window.MOCK.TASKS_FULL;
    const all = window.OITasks.apply(base, taskMut);
    const urg = all.filter(t => t.urgent).length;
    return urg > 0 ? urg : null;
  })();

  return (
    <div className="oi oi-app" data-platform={platform} data-theme={ctx.theme} data-density={ctx.density}>
      <div className="oi-screen">
        <Screen nav={nav} ctx={ctx} params={top.params} setTweak={setTweak} />
      </div>
      <nav className="oi-tabbar" style={{ gridTemplateColumns: "repeat(" + barSlots.length + ", 1fr)" }}>
        {barSlots.map(t => {
          const active = t.id === safeTab;
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
      {toasts.length > 0 && (
        <div className="oi-toast-stack">
          {toasts.map(t => (
            <div key={t.id} className={"oi-toast" + (t.tone ? " " + t.tone : "")}>
              {React.createElement(Ic[t.tone === "danger" ? "alert" : "check-circle"], { size: 16 })}
              <span>{t.msg}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

window.MobileApp = MobileApp;
