// Tarefas — master/detail (lista esquerda + viewer direito)
const { useState: useStateT, useEffect: useEffectT, useMemo: useMemoT } = React;

function TaskCard({ task, active, onClick }) {
  return (
    <div className={`tk-card ${active?"active":""} ${task.urgent?"urgent":""}`} onClick={onClick}>
      <div className="tk-card-h">
        <span className="tk-origin" style={{background:`var(--origin-${task.origin}-bg)`, color:`var(--origin-${task.origin}-fg)`}}>{task.origin}</span>
        {task.unread && <span className="tk-dot"/>}
        <span className="tk-when">{task.when}</span>
      </div>
      <b className="tk-title">{task.title}</b>
      <small className="tk-sub">{task.subtitle}</small>
      <div className="tk-foot">
        <span className="tk-from">de {task.from}</span>
      </div>
    </div>
  );
}

function TasksList({ tasks, activeId, onSelect, filter, onFilter, query, onQuery }) {
  const groups = [
    { key:"hoje",      label:"Hoje" },
    { key:"atrasadas", label:"Atrasadas" },
    { key:"semana",    label:"Esta semana" },
  ];
  const filters = [
    { key:"all",       label:"Todas" },
    { key:"hoje",      label:"Hoje" },
    { key:"atrasadas", label:"Atrasadas" },
    { key:"OS",        label:"OS" },
    { key:"CRM",       label:"CRM" },
    { key:"FIN",       label:"Financeiro" },
    { key:"PNT",       label:"Ponto" },
  ];

  const filtered = useMemoT(() => {
    let out = tasks;
    if (filter !== "all") {
      if (["OS","CRM","FIN","PNT","MFG"].includes(filter)) out = out.filter(t=>t.origin===filter);
      else out = out.filter(t => t.group === filter);
    }
    if (query.trim()) {
      const q = query.toLowerCase();
      out = out.filter(t =>
        t.title.toLowerCase().includes(q) ||
        t.subtitle.toLowerCase().includes(q) ||
        (t.from || "").toLowerCase().includes(q)
      );
    }
    return out;
  }, [tasks, filter, query]);

  // KPIs
  const kpis = useMemoT(() => ({
    hoje: tasks.filter(t => t.group === "hoje").length,
    atrasadas: tasks.filter(t => t.group === "atrasadas").length,
    semana: tasks.filter(t => t.group === "semana").length,
  }), [tasks]);

  return (
    <div className="tk-list">
      <div className="tk-list-h">
        <div className="tk-list-h-row">
          <h2>Tarefas</h2>
          <span className="tk-count">{tasks.length}</span>
          <button className="tk-mini-btn" title="Configurar"><I.cog size={12}/></button>
        </div>
        <p className="tk-list-h-sub">Inbox unificada de todos os módulos</p>
        <div className="tk-kpis">
          <div className={`tk-kpi ${kpis.atrasadas>0 ? "warn":""}`}>
            <b>{kpis.atrasadas}</b><small>Atrasadas</small>
          </div>
          <div className="tk-kpi">
            <b>{kpis.hoje}</b><small>Hoje</small>
          </div>
          <div className="tk-kpi muted">
            <b>{kpis.semana}</b><small>Semana</small>
          </div>
        </div>
        <div className="tk-search">
          <I.search size={12}/>
          <input placeholder="Buscar tarefa, cliente, OS..." value={query} onChange={e => onQuery(e.target.value)}/>
        </div>
      </div>
      <div className="tk-filters">
        {filters.map(f => (
          <button key={f.key} className={`tk-filter ${filter===f.key?"active":""}`} onClick={() => onFilter(f.key)}>{f.label}</button>
        ))}
      </div>
      <div className="tk-list-body">
        {groups.map(g => {
          const items = filtered.filter(t => t.group === g.key);
          if (items.length === 0) return null;
          return (
            <div key={g.key} className="tk-group">
              <div className="tk-group-h">
                <span>{g.label}</span>
                <span className="tk-group-c">{items.length}</span>
              </div>
              {items.map(t => (
                <TaskCard key={t.id} task={t} active={t.id===activeId} onClick={() => onSelect(t.id)}/>
              ))}
            </div>
          );
        })}
        {filtered.length === 0 && (
          <div className="tk-empty">
            <div className="tk-empty-ico"><I.check size={20}/></div>
            <b>Tudo em dia</b>
            <small>{query ? "Nenhuma tarefa bate com a busca." : "Nada pendente nesse filtro."}</small>
          </div>
        )}
      </div>
    </div>
  );
}

function TaskDetail({ task, onAction, listIndex, listTotal }) {
  if (!task) {
    return (
      <div className="tk-detail empty">
        <div className="tk-empty-state">
          <div className="tk-empty-ico lg"><I.inbox size={28}/></div>
          <b>Selecione uma tarefa</b>
          <small>Escolha uma tarefa na lista para resolvê-la inline.</small>
          <div className="tk-shortcuts">
            <span><kbd>J</kbd><kbd>K</kbd> navegar</span>
            <span><kbd>E</kbd> concluir</span>
            <span><kbd>A</kbd> adiar</span>
          </div>
        </div>
      </div>
    );
  }
  const Viewer = VIEWERS[task.viewer];

  return (
    <div className="tk-detail">
      <div className="tk-detail-h">
        <div className="tk-detail-bc">
          <span className="tk-origin" style={{background:`var(--origin-${task.origin}-bg)`, color:`var(--origin-${task.origin}-fg)`}}>{task.origin}</span>
          <span className="tk-bc-sep">·</span>
          <span className="tk-bc-step">{task.subtitle.split(" · ")[0]}</span>
          {listIndex != null && listTotal > 0 && (
            <>
              <span className="tk-bc-spacer"/>
              <span className="tk-bc-pos">{listIndex + 1} / {listTotal}</span>
            </>
          )}
        </div>
        <div className="tk-detail-title-row">
          <h1>{task.title}</h1>
          <div className="tk-detail-actions">
            <button className="icon-btn" title="Marcar como não lida"><I.bell size={14}/></button>
            <button className="icon-btn" title="Copiar link"><I.search size={14}/></button>
            <button className="icon-btn" title="Mais opções"><I.more size={14}/></button>
          </div>
        </div>
        <div className="tk-detail-meta">
          <span><span className="t-mute">de</span> <b>{task.from}</b></span>
          <span className="tk-bc-sep">·</span>
          <span><span className="t-mute">para</span> <b>você</b></span>
          <span className="tk-bc-sep">·</span>
          <span className={task.urgent ? "urgent" : ""}><span className="t-mute">prazo</span> <b>{task.when}</b></span>
        </div>
      </div>
      <div className="tk-detail-body">
        {Viewer
          ? <Viewer task={task} onAction={onAction}/>
          : <div className="empty">Viewer não implementado: {task.viewer}</div>}
      </div>
    </div>
  );
}

function TasksPage() {
  const [activeId, setActiveId] = useStateT(() => {
    try { return localStorage.getItem("oimpresso.tasks.lastId") || MOCK.TASKS[0].id; }
    catch (e) { return MOCK.TASKS[0].id; }
  });
  const [filter, setFilter] = useStateT(() => {
    try { return localStorage.getItem("oimpresso.tasks.filter") || "all"; }
    catch (e) { return "all"; }
  });
  const [query, setQuery] = useStateT("");
  const [tasks, setTasks] = useStateT(MOCK.TASKS);

  useEffectT(() => { localStorage.setItem("oimpresso.tasks.lastId", activeId); }, [activeId]);
  useEffectT(() => { localStorage.setItem("oimpresso.tasks.filter", filter); }, [filter]);

  const active = tasks.find(t => t.id === activeId);
  const activeIdx = tasks.findIndex(t => t.id === activeId);

  // Atalhos J/K/E/A
  useEffectT(() => {
    const h = (e) => {
      if (e.target.matches("input,textarea")) return;
      const visible = tasks;
      const idx = visible.findIndex(t => t.id === activeId);
      if (e.key === "j" || e.key === "ArrowDown") {
        e.preventDefault();
        const n = visible[Math.min(visible.length-1, idx+1)];
        if (n) setActiveId(n.id);
      } else if (e.key === "k" || e.key === "ArrowUp") {
        e.preventDefault();
        const n = visible[Math.max(0, idx-1)];
        if (n) setActiveId(n.id);
      } else if (e.key === "e") {
        if (active) handleAction("done");
      } else if (e.key === "a") {
        if (active) handleAction("snooze");
      }
    };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, [activeId, tasks, active]);

  const handleAction = (action) => {
    if (!active) return;
    if (["done","approve","reject","submit","snooze"].includes(action)) {
      const idx = tasks.findIndex(t => t.id === active.id);
      const newTasks = tasks.filter(t => t.id !== active.id);
      setTasks(newTasks);
      if (newTasks.length > 0) {
        setActiveId(newTasks[Math.min(idx, newTasks.length-1)].id);
      } else {
        setActiveId(null);
      }
    }
  };

  return (
    <div className="tk-page">
      <TasksList tasks={tasks} activeId={activeId}
        onSelect={setActiveId} filter={filter} onFilter={setFilter}
        query={query} onQuery={setQuery}/>
      <TaskDetail task={active} onAction={handleAction}
        listIndex={activeIdx} listTotal={tasks.length}/>
    </div>
  );
}

window.TasksPage = TasksPage;
