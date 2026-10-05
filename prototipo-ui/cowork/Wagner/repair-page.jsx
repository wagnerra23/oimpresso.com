// repair-page.jsx — módulo Repair (assistência técnica) importado dos blades
// Modules/Repair/Resources/views/{dashboard,job_sheet,repair,status,device_model,settings}
// + topnav.php (6 itens) + Routes/web.php. Fica no grupo PRODUÇÃO do shell: o módulo é
// pipeline de serviço (folha → status → pronto), irmão de Ordens de Serviço — o que é
// comercial nele (fatura, pagamento, garantia) já nasce em Vendas/Financeiro.
// Tudo montado nos componentes do DS + modulo-padrao. Expõe window.RepairPage.
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const R = () => window.RepData;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const Ic = (p) => { const F = window.JcIcon; return F ? <F {...p} /> : null; };

function useAltura(ref, min = 300) {
  const [h, setH] = useState(min);
  useEffect(() => {
    const el = ref.current; if (!el) return;
    const medir = () => setH(Math.max(min, el.clientHeight));
    medir(); const ro = new ResizeObserver(medir); ro.observe(el);
    return () => ro.disconnect();
  }, []);
  return h;
}

function Campo({ l, v, mono }) {
  return <div className="f"><label>{l}</label><span className={mono ? "mono" : ""}>{v == null || v === "" ? "—" : v}</span></div>;
}

// Pill do status do legado: a cor vem de repair_statuses.color, não de um tom do DS.
function SeloStatus({ id }) {
  const s = R().statusDe(id);
  return <span className="rep-st" style={{ "--st": s.cor }}><i />{s.nome}</span>;
}

function SeloPrazo({ f }) {
  const D = R(); const { StatusBadge } = DS();
  if (!StatusBadge) return null;
  if (D.statusDe(f.status).concluido) return <StatusBadge kind="sla" value="fresh" label="entregue no prazo" />;
  const d = D.dias(D.iso(D.HOJE), f.entrega);
  if (d < 0) return <StatusBadge kind="sla" value="late" label={"atrasada " + Math.abs(d) + "d"} />;
  if (d === 0) return <StatusBadge kind="sla" value="aging" label="vence hoje" />;
  return <StatusBadge kind="sla" value="fresh" label={"em " + d + "d"} />;
}

// ══════════ PAINEL (dashboard/index.blade.php) ══════════
function Painel({ folhas, onIr }) {
  const D = R();
  const { KpiCard, Chart, Alert } = DS();
  const pend = D.pendentes(folhas), conc = D.concluidas(folhas);
  const atrasadas = folhas.filter(D.atrasada);
  const semTecnico = pend.filter((f) => f.tecnico === "Não atribuído");
  const porStatus = D.contagemStatus(folhas).filter((x) => x.n > 0);
  return (
    <div className="rep-painel">
      {atrasadas.length > 0 && Alert &&
        <Alert tone="danger" title={atrasadas.length + " folha(s) com entrega vencida"}
          action={<button className="rep-a" onClick={() => onIr("folhas", "atrasadas")}>Ver as atrasadas</button>}>
          Prazo de entrega é o que o cliente ouviu no balcão — vencido sem aviso, ele liga antes de você.
        </Alert>}
      <div className="rep-kpis">
        {KpiCard && <>
          <KpiCard hero label="Folhas pendentes" value={pend.length} unit="abertas"
            spark={[6, 8, 7, 9, 11, 10, pend.length]} description={semTecnico.length + " sem técnico atribuído"} />
          <KpiCard label="Concluídas" value={conc.length} tone="success" description="prontas, entregues e devolvidas" />
          <KpiCard label="Entrega vencida" value={atrasadas.length} tone={atrasadas.length ? "danger" : "default"} description="folha pendente com data no passado" />
          <KpiCard label="Ticket médio" value={D.fmt(D.ticketMedio(folhas))} tone="info" description="custo estimado das folhas com orçamento" />
        </>}
      </div>
      <div className="rep-grid2">
        <section className="rep-card">
          <h3>Folhas por status</h3>
          <div className="rep-bars">
            {porStatus.map(({ status, n }) =>
              <button key={status.id} className="rep-bar" onClick={() => onIr("folhas", status.concluido ? "concluidas" : "pendentes")}>
                <span className="l"><i style={{ background: status.cor }} />{status.nome}</span>
                <span className="t" style={{ "--st": status.cor, "--w": (n / Math.max(...porStatus.map((x) => x.n))) * 100 + "%" }} />
                <b>{n}</b>
              </button>)}
          </div>
        </section>
        <section className="rep-card">
          <h3>Folhas por técnico</h3>
          <div className="rep-bars">
            {D.porTecnico(folhas).map((t) =>
              <div key={t.tecnico} className="rep-bar plain">
                <span className="l">{t.tecnico}</span>
                <span className="t" style={{ "--st": "var(--accent)", "--w": (t.n / Math.max(...D.porTecnico(folhas).map((x) => x.n))) * 100 + "%" }} />
                <b>{t.n}</b>
              </div>)}
          </div>
        </section>
      </div>
      <div className="rep-grid3">
        {[["Marcas em alta", "marca"], ["Equipamentos em alta", "dispositivo"], ["Modelos em alta", "modelo"]].map(([t, campo]) =>
          <section key={campo} className="rep-card">
            <h3>{t}</h3>
            {Chart && <Chart type="bar" height={130} data={D.tendencia(folhas, campo).slice(0, 6)} highlightLast={false} formatValue={(v) => v + " folhas"} />}
          </section>)}
      </div>
    </div>
  );
}

// ══════════ PRODUÇÃO · OFICINA (producao-oficina, KanbanProductionService) ══════════
function Producao({ folhas, onAbrir, onMover, papel, avisar }) {
  const D = R();
  const { BoardColumn, TaskCard, Alert } = DS();
  const [arrastando, setArrastando] = useState(null);
  if (!BoardColumn || !TaskCard) return null;
  const pode = D.can(papel, "repair_status.update");
  return (
    <div className="rep-board-wrap">
      {Alert && <Alert tone="info" title="Kanban derivado do status, não um campo novo">
        A coluna vem de <b>repair_statuses.sort_order</b> + <b>is_completed_status</b> (KanbanProductionService). Mover o card grava o status padrão daquela coluna.
      </Alert>}
      {/* Puxado do vivo (ProducaoOficina/Index.tsx): contagem à direita da barra de filtros. */}
      <div className="rep-subtabs">
        <span className="sp" />
        <span className="rep-hint">{folhas.length} OS · {folhas.filter((f) => (f.pecas || []).some((p) => p.situacao === "aprovacao")).length} aguardando aprovação</span>
      </div>
      <div className="rep-board">
        {D.COLUNAS.map((col) => {
          const lista = D.porColuna(folhas, col.id);
          return (
            <BoardColumn key={col.id} status={col.board} label={col.label} count={lista.length}
              onDrop={pode ? () => { if (arrastando) { onMover(arrastando, col.id); setArrastando(null); } } : undefined}>
              {lista.map((f) => {
                const m = D.modeloDe(f.modelo);
                return <div key={f.id} className="rep-board-card">
                  <TaskCard selected={false}
                    onDragStart={pode ? () => setArrastando(f) : undefined}
                    onClick={() => onAbrir(f.id)}
                    task={{
                      displayId: f.os, title: m.marca + " " + m.nome + " · " + (f.defeitos[0] || "sem defeito descrito"),
                      priority: f.prior, module: f.cliente, owner: f.tecnico === "Não atribuído" ? null : f.tecnico,
                      due: D.d2(f.entrega), isBlocked: D.statusDe(f.status).coluna === "aguardando-pecas",
                      isOverdue: D.atrasada(f),
                    }} />
                </div>;
              })}
            </BoardColumn>
          );
        })}
      </div>
      {!pode && <p className="rep-nota">Seu papel não altera status — arraste desabilitado (permissão <b>repair_status.update</b>).</p>}
    </div>
  );
}

// ══════════ FOLHAS DE OS (job_sheet/index.blade.php) ══════════
function Folhas({ folhas, papel, dense, filtro, setFiltro, busca, onAbrir, acoes }) {
  const D = R();
  const { DataTablePro, TabBar, Button, Select, StatusBadge, EmptyState, Pagination, FilterChip, BulkBar } = DS();
  const areaRef = useRef(null); const altura = useAltura(areaRef, 260);
  const [pag, setPag] = useState(1);
  const [tecnico, setTecnico] = useState("todos");
  const [local, setLocal] = useState("todos");
  const [statusF, setStatusF] = useState("todos");
  const [clienteF, setClienteF] = useState("todos");
  const [selecao, setSelecao] = useState([]);
  const [gridKey, setGridKey] = useState(0);
  if (!DataTablePro) return null;

  const base = folhas.filter((f) => {
    if (filtro === "pendentes" && D.statusDe(f.status).concluido) return false;
    if (filtro === "concluidas" && !D.statusDe(f.status).concluido) return false;
    if (filtro === "atrasadas" && !D.atrasada(f)) return false;
    if (tecnico !== "todos" && f.tecnico !== tecnico) return false;
    if (local !== "todos" && D.LOCAIS[f.local] !== local) return false;
    if (statusF !== "todos" && String(f.status) !== statusF) return false;
    if (clienteF !== "todos" && f.cliente !== clienteF) return false;
    const q = (busca || "").trim().toLowerCase();
    if (!q) return true;
    return [f.os, f.cliente, f.serie, D.modeloDe(f.modelo).nome].join(" ").toLowerCase().includes(q);
  });
  const porPagina = dense ? 12 : 9;
  const pagina = Math.min(pag, Math.max(1, Math.ceil(base.length / porPagina)));
  const rows = base.slice((pagina - 1) * porPagina, pagina * porPagina);

  const colunas = [
    { key: "os", label: "Folha nº", width: 132, sortable: true, mono: true, resizable: true },
    { key: "servico", label: "Tipo de serviço", width: 108, sortable: true },
    { key: "entrega", label: "Entrega prevista", width: 150, sortable: true },
    { key: "status", label: "Status", width: 168, sortable: true },
    { key: "fase", label: "Pipeline", width: 150 },
    { key: "tecnico", label: "Técnico", width: 138, sortable: true },
    { key: "cliente", label: "Cliente", width: 190, sortable: true },
    { key: "equip", label: "Equipamento", width: 210 },
    { key: "serie", label: "Nº de série", width: 140, mono: true },
    { key: "custo", label: "Custo estimado", width: 130, align: "right", mono: true, sortable: true },
    { key: "acoes", label: "Ações", width: 128 },
  ];
  const linhas = rows.map((f) => {
    const m = D.modeloDe(f.modelo);
    return {
      id: f.id, state: D.atrasada(f) ? "urgent" : D.statusDe(f.status).concluido ? "archived" : undefined,
      cells: {
        os: f.os, servico: D.SERVICO[f.servico],
        entrega: <span className="rep-cell-2"><b className="mono">{D.d2(f.entrega)}</b><SeloPrazo f={f} /></span>,
        status: <SeloStatus id={f.status} />,
        fase: window.OiFsmStepper ? <window.OiFsmStepper domain="repair" current={D.faseDe(f.status)} variant="dots-inline" /> : null,
        tecnico: f.tecnico === "Não atribuído" ? <span className="rep-dim">não atribuído</span> : f.tecnico,
        cliente: { primary: f.cliente, sub: D.LOCAIS[f.local] },
        equip: { primary: m.marca + " " + m.nome, sub: m.dispositivo },
        serie: f.serie, custo: f.custo ? D.fmt(f.custo) : "—",
        acoes: <span className="rep-acoes">
          {D.can(papel, "repair_status.update") &&
            <button onClick={(e) => { e.stopPropagation(); acoes.status(f); }}>status<span className="rep-sr"> da folha {f.os}</span></button>}
          {D.can(papel, "job_sheet.edit") &&
            <button onClick={(e) => { e.stopPropagation(); acoes.editar(f); }}>editar<span className="rep-sr"> a folha {f.os}</span></button>}
          <button onClick={(e) => { e.stopPropagation(); acoes.imprimir(f); }}>imprimir<span className="rep-sr"> a folha {f.os}</span></button>
          {D.can(papel, "job_sheet.delete") &&
            <button className="neg" onClick={(e) => { e.stopPropagation(); acoes.excluir(f); }}>excluir<span className="rep-sr"> a folha {f.os}</span></button>}
        </span>,
      },
    };
  });

  return (
    <div className="rep-list">
      <div className="rep-subtabs">
        <window.CliTabs ariaLabel="Recorte das folhas" pad={0} active={filtro}
          onChange={(k) => { setFiltro(k); setPag(1); }}
          tabs={[
            { key: "pendentes", label: "Pendentes", n: D.pendentes(folhas).length },
            { key: "concluidas", label: "Concluídas", n: D.concluidas(folhas).length },
            { key: "atrasadas", label: "Entrega vencida", n: folhas.filter(D.atrasada).length },
            { key: "todas", label: "Todas", n: folhas.length },
          ]} />
        <span className="sp" />
        <span className="rep-hint">Pendente = status sem <b className="mono">is_completed_status</b>. A folha só sai daqui quando o status conclui.</span>
      </div>
      {!D.can(papel, "job_sheet.view_all") &&
        <div className="rep-aviso-perm">Você tem <b className="mono">job_sheet.view_assigned</b> sem <b className="mono">view_all</b> — só as folhas atribuídas a você entram nesta lista e nas contagens.</div>}
      <div className="rep-toolbar">
        {Select && <div className="rep-filtro"><Select label="Técnico" value={tecnico} onChange={(e) => setTecnico(e.target ? e.target.value : e)}
          options={["todos", ...D.TECNICOS].map((t) => ({ value: t, label: t === "todos" ? "Todos" : t }))} /></div>}
        {Select && <div className="rep-filtro"><Select label="Local" value={local} onChange={(e) => setLocal(e.target ? e.target.value : e)}
          options={["todos", ...D.LOCAIS].map((t) => ({ value: t, label: t === "todos" ? "Todos" : t }))} /></div>}
        {/* Puxado do vivo (JobSheet/Index.tsx): filtros Status e Cliente + Limpar. */}
        {Select && <div className="rep-filtro"><Select label="Status" value={statusF} onChange={(e) => setStatusF(e.target ? e.target.value : e)}
          options={[{ value: "todos", label: "Todos os status" }, ...D.STATUS.map((s) => ({ value: String(s.id), label: s.nome }))]} /></div>}
        {Select && <div className="rep-filtro"><Select label="Cliente" value={clienteF} onChange={(e) => setClienteF(e.target ? e.target.value : e)}
          options={[{ value: "todos", label: "Todos os clientes" }, ...D.CLIENTES.map((c) => ({ value: c, label: c }))]} /></div>}
        {Button && (tecnico !== "todos" || local !== "todos" || statusF !== "todos" || clienteF !== "todos") &&
          <Button size="sm" variant="ghost" onClick={() => { setTecnico("todos"); setLocal("todos"); setStatusF("todos"); setClienteF("todos"); }}>Limpar</Button>}
        <span className="sp" />
        {FilterChip && filtro === "atrasadas" && <FilterChip label="prazo" value="vencido" onRemove={() => setFiltro("pendentes")} />}
      </div>
      <div className="rep-grid" ref={areaRef}>
        {linhas.length
          ? <DataTablePro key={gridKey} columns={colunas} rows={linhas} height={altura} density={dense ? "compact" : "comfortable"}
              selectable onSelectionChange={setSelecao}
              onRowClick={(r) => onAbrir(r.id)} defaultSort={{ key: "entrega", dir: "asc" }} />
          : EmptyState && <EmptyState variant="no-results" title="Nenhuma folha neste filtro"
              description="Troque a aba ou limpe técnico e local — a busca do topo também filtra por nº, cliente, série e modelo." />}
      </div>
      <div className="rep-foot">
        <span>{base.length} folha(s) · {D.pendentes(base).length} pendente(s)</span>
        <span className="sp" />
        {Pagination && base.length > porPagina &&
          <Pagination page={pagina} pageCount={Math.ceil(base.length / porPagina)} total={base.length} pageSize={porPagina} onChange={setPag} />}
      </div>
      {BulkBar && selecao.length > 0 &&
        <BulkBar count={selecao.length} onClose={() => { setSelecao([]); setGridKey((k) => k + 1); }}
          actions={[
            ...(D.can(papel, "repair_status.update") ? [{ label: "Alterar status", onClick: () => { acoes.statusLote(selecao); setSelecao([]); setGridKey((k) => k + 1); } }] : []),
            ...(D.can(papel, "job_sheet.edit") ? [{ label: "Atribuir técnico", onClick: () => { acoes.tecnicoLote(selecao); setSelecao([]); setGridKey((k) => k + 1); } }] : []),
            { label: "Imprimir etiquetas", onClick: () => acoes.etiquetaLote(selecao) },
            ...(D.can(papel, "job_sheet.delete") ? [{ label: "Excluir", tone: "danger", onClick: () => { acoes.excluirLote(selecao); setSelecao([]); setGridKey((k) => k + 1); } }] : []),
          ]} />}
    </div>
  );
}

// ══════════ REPAROS (Repair/Index.tsx — a venda de reparo, sub_type=repair) ══════════
// Puxado do vivo (thread 00, 2026-10-02): a Page lista a venda de reparo com 3 KPIs
// clicáveis, busca + local + responsável + chips de status, 10 colunas e rodapé
// "Mostrando X–Y de N". O protótipo antes só tinha fatura/saldo; o que o vivo tem entrou.
// Cada folha tem a sua venda derivada; as já faturadas usam o REPAROS do legado.
function vendasDeReparo(folhas) {
  const D = R();
  return folhas.map((f) => {
    const r = D.REPAROS.find((x) => x.folha === f.id);
    const total = r ? r.total : (f.custo || 0) + (f.pecas || []).reduce((s, p) => s + p.valor * p.qtd, 0);
    return {
      id: f.id, folha: f, repair: r ? r.repair : "REP-" + f.os.slice(3),
      garantia: r ? r.garantia : "Serviço 90 dias", pagamento: r ? r.pagamento : (total ? "due" : "pending"),
      total, saldo: r ? r.saldo : total, emitido: r ? r.emitido : f.criado,
    };
  });
}

function Reparos({ folhas, dense, onAbrir }) {
  const D = R();
  const { DataTablePro, StatusBadge, KpiCard, Select, Input, Button, EmptyState, Pagination } = DS();
  const areaRef = useRef(null); const altura = useAltura(areaRef, 240);
  const [q, setQ] = useState("");
  const [local, setLocal] = useState("todos");
  const [resp, setResp] = useState("todos");
  const [concluido, setConcluido] = useState(null); // null · "0" em andamento · "1" concluídas
  const [status, setStatus] = useState([]);
  const [pag, setPag] = useState(1);
  if (!DataTablePro) return null;
  const vendas = vendasDeReparo(folhas);
  const base = vendas.filter((v) => {
    const f = v.folha; const s = D.statusDe(f.status);
    if (concluido === "0" && s.concluido) return false;
    if (concluido === "1" && !s.concluido) return false;
    if (local !== "todos" && D.LOCAIS[f.local] !== local) return false;
    if (resp !== "todos" && f.tecnico !== resp) return false;
    if (status.length && !status.includes(f.status)) return false;
    const t = q.trim().toLowerCase();
    return !t || [v.repair, f.os, f.cliente, f.serie].join(" ").toLowerCase().includes(t);
  });
  const filtrando = q || local !== "todos" || resp !== "todos" || concluido || status.length;
  const porPagina = dense ? 12 : 9;
  const pagina = Math.min(pag, Math.max(1, Math.ceil(base.length / porPagina)));
  const ini = (pagina - 1) * porPagina;
  const rows = base.slice(ini, ini + porPagina);
  const colunas = [
    { key: "os", label: "OS", width: 140, mono: true, sortable: true },
    { key: "status", label: "Status", width: 168, sortable: true },
    { key: "cliente", label: "Cliente", width: 190, sortable: true },
    { key: "aparelho", label: "Aparelho", width: 180 },
    { key: "serie", label: "Série", width: 130, mono: true },
    { key: "resp", label: "Resp.", width: 130 },
    { key: "aberta", label: "Aberta", width: 96, mono: true, sortable: true },
    { key: "prazo", label: "Prazo", width: 150, sortable: true },
    { key: "total", label: "Total", width: 116, align: "right", mono: true, sortable: true },
    { key: "pgto", label: "Pgto", width: 110 },
  ];
  const linhas = rows.map((v) => {
    const f = v.folha; const m = D.modeloDe(f.modelo);
    return { id: v.id, state: D.atrasada(f) ? "urgent" : undefined, cells: {
      os: v.repair, status: <SeloStatus id={f.status} />, cliente: f.cliente,
      aparelho: m.marca + " " + m.nome, serie: f.serie,
      resp: f.tecnico === "Não atribuído" ? <span className="rep-dim">—</span> : f.tecnico,
      aberta: D.d2(v.emitido), prazo: <span className="rep-cell-2"><b className="mono">{D.d2(f.entrega)}</b><SeloPrazo f={f} /></span>,
      total: D.fmt(v.total), pgto: StatusBadge ? <StatusBadge kind="payment" value={v.pagamento} /> : v.pagamento,
    } };
  });
  const emAndamento = vendas.filter((v) => !D.statusDe(v.folha.status).concluido).length;
  const concluidas = vendas.length - emAndamento;
  return (
    <div className="rep-list">
      <div className="rep-kpis">
        {KpiCard && <>
          <KpiCard label="Em andamento" value={emAndamento} tone={concluido === "0" ? "info" : "default"}
            onClick={() => { setConcluido(concluido === "0" ? null : "0"); setPag(1); }} description="clique filtra a lista" />
          <KpiCard label="Concluídas" value={concluidas} tone={concluido === "1" ? "success" : "default"}
            onClick={() => { setConcluido(concluido === "1" ? null : "1"); setPag(1); }} />
          <KpiCard label="Total exibido" value={base.length} />
        </>}
      </div>
      <div className="rep-toolbar">
        {Input && <div className="rep-filtro"><Input label="Buscar" value={q} placeholder="Nº OS, cliente ou nº de série"
          onChange={(e) => { setQ(e.target ? e.target.value : e); setPag(1); }} /></div>}
        {Select && <div className="rep-filtro"><Select label="Local" value={local} onChange={(e) => { setLocal(e.target ? e.target.value : e); setPag(1); }}
          options={["todos", ...D.LOCAIS].map((t) => ({ value: t, label: t === "todos" ? "Todos os locais" : t }))} /></div>}
        {Select && <div className="rep-filtro"><Select label="Responsável" value={resp} onChange={(e) => { setResp(e.target ? e.target.value : e); setPag(1); }}
          options={["todos", ...D.TECNICOS].map((t) => ({ value: t, label: t === "todos" ? "Todos os responsáveis" : t }))} /></div>}
        <span className="sp" />
        {Button && filtrando && <Button size="sm" variant="ghost" onClick={() => { setQ(""); setLocal("todos"); setResp("todos"); setConcluido(null); setStatus([]); setPag(1); }}>Limpar</Button>}
      </div>
      <div className="rep-chips" role="group" aria-label="Filtrar por status">
        {D.STATUS.map((s) => {
          const on = status.includes(s.id);
          return <button key={s.id} type="button" aria-pressed={on} className={"rep-st" + (on ? " on" : "")} style={{ "--st": s.cor }}
            onClick={() => { setStatus(on ? status.filter((x) => x !== s.id) : [...status, s.id]); setPag(1); }}><i />{s.nome}</button>;
        })}
      </div>
      <div className="rep-grid" ref={areaRef}>
        {linhas.length
          ? <DataTablePro columns={colunas} rows={linhas} height={altura} density={dense ? "compact" : "comfortable"}
              onRowClick={(r) => onAbrir(r.id)} />
          : EmptyState && <EmptyState variant={filtrando ? "no-results" : "empty"}
              title={filtrando ? "Nenhuma OS no filtro" : "Sem ordens de serviço"}
              description={filtrando ? "Ajuste ou limpe os filtros pra ver mais resultados." : "Crie a primeira OS pelo botão “Nova OS”."} />}
      </div>
      <div className="rep-foot">
        <span>{base.length ? "Mostrando " + (ini + 1) + "–" + Math.min(ini + porPagina, base.length) + " de " + base.length : "Nada a mostrar"}</span>
        <span className="sp" />
        {Pagination && base.length > porPagina &&
          <Pagination page={pagina} pageCount={Math.ceil(base.length / porPagina)} total={base.length} pageSize={porPagina} onChange={setPag} />}
      </div>
      <p className="rep-nota">O reparo é a venda derivada da folha — pagamento, garantia e cobrança são de Vendas/Financeiro. Aqui só se lê.</p>
    </div>
  );
}

// ══════════ DRAWER da venda de reparo (Repair/Show.tsx) ══════════
// Puxado do vivo: Detalhes da venda · Linhas · Checklist · Pagamentos · Timeline.
function ReparoDrawer({ folha, close, avisar }) {
  const D = R();
  const { Drawer, DrawerSection, Button, Alert, StatusBadge } = DS();
  if (!Drawer || !folha) return null;
  const v = vendasDeReparo([folha])[0];
  const m = D.modeloDe(folha.modelo);
  const checklist = m.checklist.split("|");
  const linhas = [{ nome: D.CONFIG.produtoPadrao, qtd: 1, valor: folha.custo || 0 }, ...(folha.pecas || [])].filter((p) => p.valor > 0);
  const pago = v.total - v.saldo;
  const atividades = D.ATIVIDADES[folha.id] || [];
  return (
    <Drawer open onClose={close} width={680} title={"Venda de reparo " + v.repair} subtitle={folha.cliente || "Sem cliente"}
      footer={Button && <>
        <Button variant="ghost" onClick={() => avisar("Via do cliente abre em nova aba.")}>Via do cliente</Button>
        <Button variant="primary" onClick={() => avisar("Editar a venda de reparo abre em Vendas.")}>Editar</Button>
      </>}>
      <DrawerSection title="Detalhes da venda">
        <div className="rep-fields">
          <div className="f"><label>Status</label><span><SeloStatus id={folha.status} /></span></div>
          <div className="f"><label>Pagamento</label><span>{StatusBadge ? <StatusBadge kind="payment" value={v.pagamento} /> : v.pagamento}</span></div>
          <Campo l="Data da venda" v={D.d2(v.emitido)} mono />
          <Campo l="Prazo de entrega" v={D.d2(folha.entrega)} mono />
          <Campo l="Aparelho" v={m.marca + " " + m.nome} />
          <Campo l="Nº de série" v={folha.serie} mono />
          <Campo l="Defeitos" v={folha.defeitos.join(", ")} />
          <Campo l="Valor total" v={D.fmt(v.total)} mono />
          <Campo l="Garantia" v={v.garantia} />
        </div>
      </DrawerSection>
      <DrawerSection title="Linhas (peças/serviços)">
        {linhas.length ? <>
          {linhas.map((p) =>
            <div key={p.nome} className="rep-peca">
              <div><b>{p.nome}</b><small className="mono">{p.qtd}× {D.fmt(p.valor)}</small></div>
              <span className="mono">{D.fmt(p.qtd * p.valor)}</span>
            </div>)}
          <div className="rep-peca total"><b>Total</b><span className="mono">{D.fmt(v.total)}</span></div>
        </> : Alert && <Alert tone="info" title="Sem itens">Nenhuma peça/serviço lançado.</Alert>}
      </DrawerSection>
      <DrawerSection title="Checklist do aparelho">
        <ul className="rep-check">
          {checklist.map((c) => <li key={c} className={folha.checklist.includes(c) ? "on" : ""}><span className="bx">{folha.checklist.includes(c) ? "✓" : ""}</span>{c}</li>)}
        </ul>
      </DrawerSection>
      <DrawerSection title="Pagamentos">
        {pago > 0
          ? <div className="rep-peca"><div><b>Recebido</b><small>{v.pagamento === "paid" ? "quitado" : "parcial"}</small></div><span className="mono">{D.fmt(pago)}</span></div>
          : Alert && <Alert tone="info" title="Sem pagamentos">Cobrança ainda pendente.</Alert>}
      </DrawerSection>
      <DrawerSection title="Timeline">
        {atividades.length ? <div className="rep-log">
          {atividades.slice().reverse().map((a, i) =>
            <div key={i} className="rep-log-item"><div className="h"><b>{a.ev}</b><span className="mono">{D.d2(a.dia)} {a.hora}</span></div><small>{a.quem}</small></div>)}
        </div> : Alert && <Alert tone="info" title="Sem atividades">Histórico aparece após mudanças na venda.</Alert>}
      </DrawerSection>
    </Drawer>
  );
}

// ══════════ STATUS (status/index.blade.php) ══════════
function Status({ folhas, papel, avisar }) {
  const D = R();
  const { Button, Switch, Alert } = DS();
  const pode = D.can(papel, "repair_status.access");
  return (
    <div className="rep-cfg">
      <div>
        <h3>Status do reparo</h3>
        <p>Cada status carrega cor, ordem, marcação de conclusão e os modelos de SMS/e-mail disparados ao cliente. A ordem alimenta o kanban.</p>
      </div>
      <div className="rep-status-list">
        {D.STATUS.map((s) => {
          const n = folhas.filter((f) => f.status === s.id).length;
          return (
            <div key={s.id} className="rep-status-row">
              <span className="rep-st" style={{ "--st": s.cor }}><i />{s.nome}</span>
              <span className="rep-status-meta mono">ordem {s.ordem} · coluna {s.coluna} · {n} folha(s)</span>
              <span className="rep-status-flag">{s.concluido ? "marcado como concluído" : "pendente"}</span>
              <span className="rep-status-tpl">SMS: “{s.sms}”</span>
              {Button && <Button size="sm" variant="ghost" onClick={() => pode ? avisar("Editar status abre o formulário de " + s.nome + ".") : avisar("Seu papel não edita status.", "warn")}>Editar</Button>}
            </div>
          );
        })}
      </div>
      {Alert && <Alert tone="warn" title="Excluir status não é reversível para as folhas">
        No legado o status é FK das folhas — apagar um status usado deixa folha órfã. Antes de excluir, migre as folhas.
        {" "}Hoje {D.STATUS.filter((s) => folhas.some((f) => f.status === s.id)).length} de {D.STATUS.length} status estão em uso.
      </Alert>}
      {Button && <div className="rep-cfg-acoes"><Button variant="primary" onClick={() => pode ? avisar("Novo status — nome, cor, ordem, templates.") : avisar("Sem permissão.", "warn")}>Adicionar status</Button>
        <span>Permissão: <b className="mono">access_job_sheet_status</b></span></div>}
    </div>
  );
}

// ══════════ MODELOS DE DISPOSITIVO (device_model/index.blade.php) ══════════
// Puxado do vivo (DeviceModels/Index.tsx): 3 KPIs, filtros Marca/Categoria + Limpar, coluna
// "Categoria" e a ação "Editar" por linha — o protótipo só tinha a tabela.
function Modelos({ modelos, folhas, dense, papel, onNovo, onEditar }) {
  const D = R();
  const { DataTablePro, Button, TagChip, KpiCard, Select, EmptyState } = DS();
  const areaRef = useRef(null); const altura = useAltura(areaRef, 240);
  const [marca, setMarca] = useState("todas");
  const [cat, setCat] = useState("todas");
  if (!DataTablePro) return null;
  const base = modelos.filter((m) => (marca === "todas" || m.marca === marca) && (cat === "todas" || m.dispositivo === cat));
  const filtrando = marca !== "todas" || cat !== "todas";
  const pode = D.can(papel, "repair.create");
  const colunas = [
    { key: "nome", label: "Modelo", width: 190, sortable: true },
    { key: "marca", label: "Marca", width: 130, sortable: true },
    { key: "disp", label: "Categoria", width: 190, sortable: true },
    { key: "check", label: "Checklist de pré-reparo", width: 380 },
    { key: "n", label: "Folhas", width: 90, align: "right", mono: true, sortable: true },
    { key: "acoes", label: "", width: 80 },
  ];
  const linhas = base.map((m) => ({ id: m.id, cells: {
    nome: m.nome, marca: m.marca, disp: m.dispositivo,
    check: <span className="rep-chips">{m.checklist.split("|").map((c) => TagChip ? <TagChip key={c} label={c.toLowerCase()} /> : <span key={c}>{c}</span>)}</span>,
    n: folhas.filter((f) => f.modelo === m.id).length,
    acoes: pode ? <span className="rep-acoes"><button onClick={(e) => { e.stopPropagation(); onEditar(m); }}>editar<span className="rep-sr"> o modelo {m.nome}</span></button></span> : null,
  } }));
  return (
    <div className="rep-list">
      <div className="rep-kpis">
        {KpiCard && <>
          <KpiCard label="Total de modelos" value={modelos.length} />
          <KpiCard label="Marcas ativas" value={new Set(modelos.map((m) => m.marca)).size} />
          <KpiCard label="Categorias" value={new Set(modelos.map((m) => m.dispositivo)).size} />
        </>}
      </div>
      <div className="rep-toolbar">
        {Select && <div className="rep-filtro"><Select label="Marca" value={marca} onChange={(e) => setMarca(e.target ? e.target.value : e)}
          options={["todas", ...D.MARCAS].map((t) => ({ value: t, label: t === "todas" ? "Todas as marcas" : t }))} /></div>}
        {Select && <div className="rep-filtro"><Select label="Categoria" value={cat} onChange={(e) => setCat(e.target ? e.target.value : e)}
          options={["todas", ...D.DISPOSITIVOS].map((t) => ({ value: t, label: t === "todas" ? "Todas as categorias" : t }))} /></div>}
        {Button && filtrando && <Button size="sm" variant="ghost" onClick={() => { setMarca("todas"); setCat("todas"); }}>Limpar</Button>}
        <span className="sp" />
        {Button && pode && <Button variant="primary" onClick={onNovo}>Novo modelo</Button>}
      </div>
      <div className="rep-subtabs">
        <span className="rep-hint">O checklist do modelo é o que aparece na folha ao escolher o equipamento — no legado é uma string separada por <b className="mono">|</b>.</span>
      </div>
      <div className="rep-grid" ref={areaRef}>
        {linhas.length
          ? <DataTablePro columns={colunas} rows={linhas} height={altura} density={dense ? "compact" : "comfortable"} />
          : EmptyState && <EmptyState variant={filtrando ? "no-results" : "empty"} title="Nenhum modelo cadastrado"
              description={filtrando ? "Nenhum modelo bate com os filtros aplicados." : "Cadastre os modelos de dispositivos que sua oficina atende."} />}
      </div>
    </div>
  );
}

// ══════════ Modelo · criar / editar (DeviceModels/Create.tsx · Edit.tsx) ══════════
function ModeloForm({ modo, modelo, onClose, onSalvar }) {
  const D = R();
  const { Drawer, DrawerSection, Input, Select, Button } = DS();
  const [m, setM] = useState(modelo || { nome: "", marca: "", dispositivo: "", checklist: "" });
  const [erro, setErro] = useState("");
  if (!Drawer) return null;
  const set = (k, v) => setM((x) => ({ ...x, [k]: v && v.target ? v.target.value : v }));
  const salvar = () => { if (!m.nome.trim()) return setErro("Nome do modelo é obrigatório."); onSalvar(m, modo === "novo"); };
  return (
    <Drawer open onClose={onClose} width={560}
      title={modo === "novo" ? "Novo modelo de dispositivo" : "Editar modelo: " + modelo.nome}
      subtitle={modo === "novo" ? "Cadastre marca, categoria e checklist padrão de reparo" : "Atualize marca, categoria ou checklist padrão"}
      footer={Button && <>
        <Button variant="ghost" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" onClick={salvar}>{modo === "novo" ? "Salvar" : "Atualizar"}</Button>
      </>}>
      <DrawerSection title="Modelo">
        <div className="rep-fields-col">
          {Input && <Input label="Nome do modelo *" value={m.nome} error={erro} placeholder="Ex.: GR2-540" onChange={(e) => set("nome", e)} />}
          {Select && <Select label="Marca" value={m.marca} onChange={(e) => set("marca", e)}
            options={[{ value: "", label: "Selecione…" }, ...D.MARCAS.map((x) => ({ value: x, label: x }))]} />}
          {Select && <Select label="Categoria / Dispositivo" value={m.dispositivo} onChange={(e) => set("dispositivo", e)}
            options={[{ value: "", label: "Selecione…" }, ...D.DISPOSITIVOS.map((x) => ({ value: x, label: x }))]} />}
          {Input && <Input label="Checklist de reparo (separe itens com |)" value={m.checklist} placeholder="Ex.: cabeçote|lâmina|sensor|firmware"
            onChange={(e) => set("checklist", e)} />}
        </div>
      </DrawerSection>
    </Drawer>
  );
}

// ══════════ CONFIGURAÇÕES (settings/index.blade.php) ══════════
function Config({ papel, avisar, onIr }) {
  const D = R(); const C = D.CONFIG;
  const { Input, Select, Switch, Button, Alert } = DS();
  const pode = D.can(papel, "repair.create");
  const [mostrar, setMostrar] = useState(C.mostrar);
  const troca = (k) => { if (!pode) return avisar("Seu papel não altera configurações.", "warn"); setMostrar((m) => ({ ...m, [k]: !m[k] })); };
  return (
    <div className="rep-cfg">
      <div>
        <h3>Folha de OS</h3>
        <p>Prefixo, status padrão e produto lançado automaticamente ao abrir um reparo.</p>
        <div className="rep-cfg-grid">
          {Input && <Input label="Prefixo do número da folha" value={C.prefixo} readOnly help="Gera JS-2026-0000" />}
          {Select && <Select label="Status padrão da folha" value={String(C.statusPadrao)} options={D.STATUS.map((s) => ({ value: String(s.id), label: s.nome }))} onChange={() => {}} />}
          {Input && <Input label="Produto padrão do reparo" value={C.produtoPadrao} readOnly help="Entra na venda derivada" />}
          {/* Puxado do vivo (Settings/Index.tsx): etiqueta + listas do balcão + termos + checklist padrão. */}
          {Select && <Select label="Etiqueta de código de barras" value="20" onChange={() => {}}
            options={[{ value: "20", label: "20 etiquetas por folha · 4×1,33 pol" }, { value: "1", label: "Etiqueta contínua · 100×50 mm" }]} />}
          {Select && <Select label="Tipo de código de barras" value="C128" onChange={() => {}}
            options={[{ value: "C128", label: "Code 128" }, { value: "C39", label: "Code 39" }, { value: "EAN13", label: "EAN-13" }]} />}
        </div>
        <div className="rep-cfg-grid">
          {Input && <Input label="Problema relatado pelo cliente" value={D.SUGESTOES.defeitos.join(", ")} readOnly help="Sugestões do balcão, separadas por vírgula" />}
          {Input && <Input label="Condição do produto" value={D.SUGESTOES.condicoes.join(", ")} readOnly />}
          {Input && <Input label="Configuração do produto" value={D.SUGESTOES.configuracoes.join(", ")} readOnly />}
          {Input && <Input label="Termos e condições" value="Equipamento não retirado em 90 dias é considerado abandonado." readOnly />}
          {Input && <Input label="Checklist padrão do reparo" value="Liga|Carcaça|Cabos|Acessórios" readOnly help="Vale quando o modelo não tem checklist próprio" />}
        </div>
      </div>
      <div>
        <h3>O que aparece na impressão</h3>
        <p>Cada chave liga um bloco da etiqueta e da folha impressa — e o rótulo é editável porque cada gráfica chama a coisa pelo seu nome.</p>
        <div className="rep-cfg-grid">
          {Input && <Input label="Rótulo do cliente" value="Cliente" readOnly />}
          {Input && <Input label="Rótulo do código do cliente" value="Código" readOnly />}
          {Input && <Input label="Rótulo do documento fiscal" value="CPF/CNPJ" readOnly />}
          {Input && <Input label="Largura da etiqueta (mm)" value="100" readOnly />}
          {Input && <Input label="Altura da etiqueta (mm)" value="50" readOnly />}
        </div>
        <div className="rep-cfg-switches">
          {Object.keys(mostrar).map((k) =>
            <div key={k} className="rep-sw">
              {Switch && <Switch checked={mostrar[k]} onChange={() => troca(k)} label={C.rotulos[k]} sublabel={"chave " + k} />}
            </div>)}
        </div>
      </div>
      <div>
        <h3>Campos personalizados da folha</h3>
        <p>Cinco rótulos livres (job_sheet_custom_field_1..5). Preenchido = coluna na listagem; vazio = coluna nem existe.</p>
        <div className="rep-cfg-grid">
          {C.camposCustom.map((v, i) => Input && <Input key={i} label={"Campo personalizado " + (i + 1)} value={v} placeholder="sem rótulo — coluna oculta" readOnly />)}
        </div>
      </div>
      <div>
        <h3>Configurações em tela própria</h3>
        <p>Estes cadastros já têm tela dedicada — esta página não os duplica.</p>
        {Button && <div className="rep-cfg-acoes">
          <Button variant="ghost" onClick={() => onIr && onIr("status")}>Status de OS</Button>
          <Button variant="ghost" onClick={() => onIr && onIr("modelos")}>Modelos de dispositivo</Button>
        </div>}
      </div>
      {Alert && <Alert tone="info" title="Notificação ao cliente é por status, não global">
        O texto de SMS e o corpo do e-mail moram em cada status. Aqui só se escolhe se o disparo vem marcado por padrão.
      </Alert>}
      <div>
        <h3>Permissões deste papel</h3>
        <p>As permissões são as que o controller do módulo checa de verdade — nenhuma inventada. Papel simulado: <b>{D.PAPEIS[papel].label}</b> ({D.PAPEIS[papel].quem}).</p>
        <div className="rep-perms">
          {Object.entries(D.PERMISSOES).map(([k, desc]) => {
            const tem = D.can(papel, k);
            return <div key={k} className={"rep-perm" + (tem ? " on" : "")}>
              <span className="bx" aria-hidden="true">{tem ? "✓" : "—"}</span>
              <b className="mono">{k}</b>
              <small>{desc}</small>
              <span className="rep-sr">{tem ? "concedida" : "negada"}</span>
            </div>;
          })}
        </div>
      </div>
      {Button && <div className="rep-cfg-acoes"><Button variant="primary" onClick={() => pode ? avisar("Configurações salvas.", "ok") : avisar("Sem permissão.", "warn")}>Salvar configurações</Button>
        <span>Permissão: <b className="mono">repair_module</b> (assinatura) + admin do negócio</span></div>}
    </div>
  );
}

// ══════════ DRAWER da folha (job_sheet/show.blade.php) ══════════
function FolhaDrawer({ f, close, acoes, papel, avisar }) {
  const D = R();
  const { Drawer, DrawerSection, Button, Alert, Progress, StatusBadge, TagChip, Avatar } = DS();
  const [aba, setAba] = useState("folha");
  if (!Drawer || !f) return null;
  const m = D.modeloDe(f.modelo);
  const checklist = m.checklist.split("|");
  const atividades = D.ATIVIDADES[f.id] || [];
  const totalPecas = (f.pecas || []).reduce((s, p) => s + p.valor * p.qtd, 0);
  // "Anexos" puxado do vivo (JobSheet/Show.tsx tem a seção Anexos ao lado de Peças e Timeline).
  const abas = [["folha", "Folha"], ["checklist", "Checklist"], ["pecas", "Peças"], ["anexos", "Anexos"], ["atividades", "Atividades"]];
  const anexos = (D.DOCS || {})[f.id] || [];
  return (
    <Drawer open onClose={close} width={640} title={f.os}
      subtitle={f.cliente + " · " + m.marca + " " + m.nome}
      footer={Button && <>
        <Button variant="ghost" onClick={() => acoes.docs(f)}>Documentos</Button>
        <Button variant="ghost" onClick={() => acoes.imprimir(f)}>Imprimir</Button>
        <Button variant="ghost" onClick={() => acoes.pecas(f)}>Peças</Button>
        {D.can(papel, "repair_status.update") && <Button variant="primary" onClick={() => acoes.status(f)}>Alterar status</Button>}
      </>}>
      <div className="rep-drawer-top">
        <SeloStatus id={f.status} />
        <SeloPrazo f={f} />
        {StatusBadge && <StatusBadge kind="tipo" value={f.tipoPf} />}
      </div>
      {window.OiFsmStepper && <div className="rep-drawer-fsm"><window.OiFsmStepper domain="repair" current={D.faseDe(f.status)} variant="full-stepper" /></div>}
      <window.CliTabs className="rep-drawer-nav" ariaLabel="Abas da OS" pad={18} size="sm"
        active={aba} onChange={setAba} tabs={abas.map(([k, l]) => ({ key: k, label: l }))} />

      {aba === "folha" && <>
        <DrawerSection title="Recebimento">
          <div className="rep-fields">
            <Campo l="Tipo de serviço" v={D.SERVICO[f.servico]} />
            <Campo l="Local" v={D.LOCAIS[f.local]} />
            <Campo l="Aberta em" v={D.d2(f.criado)} mono />
            <Campo l="Entrega prevista" v={D.d2(f.entrega)} mono />
            <Campo l="Técnico" v={f.tecnico} />
            <Campo l="Custo estimado" v={f.custo ? D.fmt(f.custo) : "sem orçamento"} mono />
          </div>
          {f.endereco && <p className="rep-nota">Retirada / no local: {f.endereco}</p>}
        </DrawerSection>
        <DrawerSection title="Equipamento">
          <div className="rep-fields">
            <Campo l="Marca" v={m.marca} />
            <Campo l="Equipamento" v={m.dispositivo} />
            <Campo l="Modelo" v={m.nome} />
            <Campo l="Número de série" v={f.serie} mono />
            <Campo l="Configuração" v={f.configuracao} />
            <Campo l="Senha / padrão" v={f.senha} mono />
          </div>
        </DrawerSection>
        <DrawerSection title="Defeito relatado pelo cliente">
          <ul className="rep-def">{f.defeitos.map((d) => <li key={d}>{d}</li>)}</ul>
          <div className="rep-fields"><Campo l="Condição do produto" v={f.condicao} /></div>
        </DrawerSection>
        <DrawerSection title="Notificação ao cliente">
          {Alert && <Alert tone={f.notificar ? "success" : "warn"} title={f.notificar ? "Avisa a cada mudança de status" : "Cliente não recebe aviso automático"}>
            {f.notificar ? "SMS e e-mail saem com o texto do status: “" + D.statusDe(f.status).sms + "”" : "Sem notificação, cada mudança de status vira ligação no balcão."}
          </Alert>}
        </DrawerSection>
      </>}

      {aba === "checklist" && <DrawerSection title={"Checklist de pré-reparo · " + m.nome}>
        {Progress && <Progress value={Math.round((f.checklist.length / checklist.length) * 100)} label="Itens conferidos" showValue />}
        <ul className="rep-check">
          {checklist.map((c) => <li key={c} className={f.checklist.includes(c) ? "on" : ""}>
            <span className="bx">{f.checklist.includes(c) ? "✓" : ""}</span>{c}
          </li>)}
        </ul>
        <p className="rep-nota">O checklist vem do modelo do dispositivo — é a prova do que entrou funcionando e o que já chegou com defeito.</p>
      </DrawerSection>}

      {aba === "pecas" && <DrawerSection title="Peças usadas no reparo">
        {(f.pecas || []).length ? <>
          {f.pecas.map((p) =>
            <div key={p.nome} className="rep-peca">
              <div><b>{p.nome}</b><small className="mono">{p.qtd}× {D.fmt(p.valor)} = {D.fmt(p.qtd * p.valor)}</small></div>
              {StatusBadge && <StatusBadge kind="documento"
                value={p.situacao === "ok" ? "aprovado" : p.situacao === "encomendado" ? "pendente" : "rascunho"}
                label={p.situacao === "ok" ? "no balcão" : p.situacao === "encomendado" ? "encomendada" : "aguardando OK do cliente"} />}
            </div>)}
          <div className="rep-peca total"><b>Total em peças</b><span className="mono">{D.fmt(totalPecas)}</span></div>
        </> : Alert && <Alert tone="info" title="Nenhuma peça lançada">
          Peça lançada aqui baixa estoque e entra na venda derivada — sem lançamento, o custo do reparo fica invisível.
        </Alert>}
      </DrawerSection>}

      {aba === "anexos" && <DrawerSection title="Anexos">
        {anexos.length ? anexos.map((d) =>
          <div key={d.nome} className="rep-peca"><div><b>{d.nome}</b><small className="mono">{d.tam} · {D.d2(d.em)}</small></div></div>)
          : Alert && <Alert tone="info" title="Sem anexos">Anexe fotos ou documentos da OS.</Alert>}
      </DrawerSection>}

      {aba === "atividades" && <DrawerSection title="Atividades">
        {atividades.length ? <div className="rep-log">
          {atividades.slice().reverse().map((a, i) =>
            <div key={i} className="rep-log-item">
              <div className="h"><b>{a.ev}</b><span className="mono">{D.d2(a.dia)} {a.hora}</span></div>
              <small>{a.quem}</small>
              {a.nota && <p>{a.nota}</p>}
            </div>)}
        </div> : Alert && <Alert tone="info" title="Sem atividade registrada">Esta folha não mudou de status desde a abertura.</Alert>}
      </DrawerSection>}
    </Drawer>
  );
}

// ══════════ MODAL de status (job_sheet/{id}/status) ══════════
function StatusModal({ f, onClose, onSalvar }) {
  const D = R();
  const { Modal, Select, Switch, Button, Input } = DS();
  const [novo, setNovo] = useState(String(f.status));
  const [sms, setSms] = useState(false);
  const [email, setEmail] = useState(true);
  if (!Modal) return null;
  const s = D.statusDe(Number(novo));
  return (
    <Modal open onClose={onClose} title={"Alterar status · " + f.os} width={520}
      footer={Button && <>
        <Button variant="ghost" onClick={onClose}>Cancelar</Button>
        <Button variant="primary" onClick={() => onSalvar(f, Number(novo), { sms, email })}>Atualizar status</Button>
      </>}>
      <div className="rep-fields-col">
        {Select && <Select label="Status" value={novo} onChange={(e) => setNovo(e.target ? e.target.value : e)}
          options={D.STATUS.map((x) => ({ value: String(x.id), label: x.nome + (x.concluido ? " (conclui a folha)" : "") }))} />}
        {Switch && <Switch checked={email} onChange={setEmail} label="Enviar e-mail" sublabel={"Assunto: " + s.assunto.replace(":os", f.os)} />}
        {Switch && <Switch checked={sms} onChange={setSms} label="Enviar SMS" sublabel={s.sms.replace(":os", f.os).replace(":cliente", f.cliente).replace(":tecnico", f.tecnico).replace(":valor", D.fmt(f.custo))} />}
        {Input && <Input label="Nota de atualização" placeholder="O que o técnico fez nesta etapa" />}
      </div>
    </Modal>
  );
}

// ══════════ MODAL de impressão (print_pdf formato 1/2 · print_label · customerCopy) ══════════
function ImprimirModal({ folha, onClose, onEscolher }) {
  const { Modal, Button } = DS();
  if (!Modal) return null;
  const opcoes = [
    ["f1", "Folha de OS · formato 1", "Completa: checklist, peças, termos e as duas assinaturas."],
    ["f2", "Folha de OS · formato 2", "Enxuta, meia folha: só recebimento, equipamento e defeito."],
    ["etiqueta", "Etiqueta do equipamento", "100×50 mm — cola na máquina, com nº da OS e série."],
    ["cliente", "Via do cliente", "Recibo do que ele deixou + link do portal de consulta."],
  ];
  return (
    <Modal open onClose={onClose} width={520} title={"Imprimir · " + folha.os}
      footer={Button && <Button variant="ghost" onClick={onClose}>Fechar</Button>}>
      <div className="rep-print-opts">
        {opcoes.map(([k, t, s]) =>
          <button key={k} type="button" onClick={() => onEscolher(k)}>
            <b>{t}</b><small>{s}</small><span aria-hidden="true">→</span>
          </button>)}
      </div>
    </Modal>
  );
}

// ══════════ MODAL de ação em lote (BulkBar) ══════════
function LoteModal({ tipo, folhas, onClose, onSalvar }) {
  const D = R();
  const { Modal, Select, Button, Alert } = DS();
  const [valor, setValor] = useState(tipo === "status" ? String(D.CONFIG.statusPadrao) : D.TECNICOS[0]);
  if (!Modal) return null;
  const ehStatus = tipo === "status";
  return (
    <Modal open onClose={onClose} width={480}
      title={(ehStatus ? "Alterar status de " : tipo === "excluir" ? "Excluir " : "Atribuir técnico a ") + folhas.length + " folha(s)"}
      footer={Button && <>
        <Button variant="ghost" onClick={onClose}>Cancelar</Button>
        <Button variant={tipo === "excluir" ? "danger" : "primary"} onClick={() => onSalvar(valor)}>
          {tipo === "excluir" ? "Excluir todas" : "Aplicar nas " + folhas.length}
        </Button>
      </>}>
      <p className="rep-nota">{folhas.map((f) => f.os).join(" · ")}</p>
      {tipo === "excluir"
        ? Alert && <Alert tone="danger" title="Ação em lote não tem desfazer">Reparos já faturados permanecem no Financeiro.</Alert>
        : Select && <Select label={ehStatus ? "Novo status" : "Técnico"} value={valor} onChange={(e) => setValor(e.target ? e.target.value : e)}
            options={(ehStatus ? D.STATUS.map((s) => ({ value: String(s.id), label: s.nome })) : D.TECNICOS.map((t) => ({ value: t, label: t })))} />}
      {!ehStatus && tipo !== "excluir" && <p className="rep-nota">Atribuir não dispara aviso ao cliente — só mudança de status notifica.</p>}
    </Modal>
  );
}

// ⌘K — paleta do módulo: pula pra área ou abre uma folha por número/cliente.
function usePaleta({ folhas, onAba, onAbrir, onNova }) {
  const [aberta, setAberta] = useState(false);
  useEffect(() => {
    const h = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") { e.preventDefault(); setAberta(true); }
    };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, []);
  const { Command } = DS();
  const grupos = [
    { label: "Áreas", items: [
      { id: "a-painel", label: "Painel", onSelect: () => onAba("painel") },
      { id: "a-prod", label: "Produção · kanban", onSelect: () => onAba("producao") },
      { id: "a-folhas", label: "Folhas de OS", onSelect: () => onAba("folhas") },
      { id: "a-portal", label: "Portal do cliente", onSelect: () => onAba("portal") },
      { id: "a-nova", label: "Abrir nova folha", kbd: "N", onSelect: onNova },
    ] },
    { label: "Folhas", items: folhas.slice(0, 12).map((f) => ({
      id: "f-" + f.id, label: f.os + " · " + f.cliente, hint: R().statusDe(f.status).nome,
      onSelect: () => onAbrir(f.id),
    })) },
  ];
  const node = Command && aberta ? <Command open onClose={() => setAberta(false)} groups={grupos} /> : null;
  return [node, () => setAberta(true)];
}

// ══════════ ROTAS — uma vista `rep-*` por Page viva (thread 00 do playbook Repair, 2026-10-02) ══════════
// Antes, só 7 abas tinham rota e a rota `repair` abria a ÚLTIMA aba guardada no localStorage — por
// isso as 6 medidas de 2026-09-18 saíram com o mesmo design.json. Cada rota agora fixa a aba e, nas
// Pages de detalhe/formulário, abre o drawer correspondente com uma folha/modelo fixos (medida
// reprodutível). `page` é a Page Inertia que a vista representa (resources/js/Pages/Repair/…).
const ROTAS = {
  "repair":            { page: "Repair/Dashboard/Index",       aba: "painel" },
  "rep-painel":        { page: "Repair/Dashboard/Index",       aba: "painel" },
  "rep-reparos":       { page: "Repair/Index",                 aba: "reparos" },
  "rep-reparo":        { page: "Repair/Show",                  aba: "reparos", reparo: 11 },
  "rep-producao":      { page: "Repair/ProducaoOficina/Index", aba: "producao" },
  "rep-folhas":        { page: "Repair/JobSheet/Index",        aba: "folhas" },
  "rep-folha":         { page: "Repair/JobSheet/Show",         aba: "folhas", sel: 5 },
  "rep-folha-nova":    { page: "Repair/JobSheet/Create",       aba: "folhas", modal: { t: "folha", modo: "novo" } },
  "rep-folha-editar":  { page: "Repair/JobSheet/Edit",         aba: "folhas", modal: { t: "folha", modo: "editar", folhaId: 5 } },
  "rep-folha-pecas":   { page: "Repair/JobSheet/AddParts",     aba: "folhas", modal: { t: "pecas", folhaId: 7 } },
  "rep-status":        { page: "Repair/Status/Index",          aba: "status" },
  "rep-modelos":       { page: "Repair/DeviceModels/Index",    aba: "modelos" },
  "rep-modelo-novo":   { page: "Repair/DeviceModels/Create",   aba: "modelos", modelo: { modo: "novo" } },
  "rep-modelo-editar": { page: "Repair/DeviceModels/Edit",     aba: "modelos", modelo: { modo: "editar", id: 2 } },
  "rep-config":        { page: "Repair/Settings/Index",        aba: "config" },
  "rep-portal":        { page: null /* portal do cliente: Blade, thread 04 */, aba: "portal" },
};

// ══════════ SHELL ══════════
function RepairPage({ view, dense, estado = "dados", papel: papelProp }) {
  const D = R();
  const MP = window.ModuloPadrao || {};
  const { Button } = DS();
  const papel = D.PAPEIS[papelProp] ? papelProp : "administrador";
  const rota = ROTAS[view] || null;
  const inicial = rota ? rota.aba : undefined;
  const [aba, setAba] = (MP.useAba || ((k, i) => useState(i)))("oimpresso.repair.aba", inicial || "painel");
  const [avisoNode, avisar] = (MP.useAviso || (() => [null, () => {}]))();
  const [folhas, setFolhas] = useState(D.FOLHAS);
  const [modelos, setModelos] = useState(D.MODELOS);
  const [docsFolha, setDocsFolha] = useState(null);
  const [filtro, setFiltro] = useState("pendentes");
  const [busca, setBusca] = useState("");
  const [sel, setSel] = useState(rota && rota.sel ? rota.sel : null);
  const [reparo, setReparo] = useState(rota && rota.reparo ? rota.reparo : null);
  const [modeloForm, setModeloForm] = useState(() => (rota && rota.modelo
    ? (rota.modelo.modo === "novo" ? { modo: "novo" } : { modo: "editar", modelo: D.MODELOS.find((m) => m.id === rota.modelo.id) })
    : null));
  const montarModal = (r) => {
    if (!r || !r.modal) return null;
    const fo = r.modal.folhaId ? D.FOLHAS.find((x) => x.id === r.modal.folhaId) : undefined;
    return r.modal.t === "pecas" ? { t: "pecas", folha: fo } : { t: "folha", modo: r.modal.modo, folha: fo };
  };
  const montarModelo = (r) => {
    if (!r || !r.modelo) return null;
    return r.modelo.modo === "novo" ? { modo: "novo" } : { modo: "editar", modelo: D.MODELOS.find((m) => m.id === r.modelo.id) };
  };
  const [modal, setModal] = useState(montarModal(rota));
  const [hora, setHora] = useState("09:42");
  useEffect(() => {
    if (!rota) return;
    setAba(rota.aba);
    setSel(rota.sel || null); setReparo(rota.reparo || null);
    setModal(montarModal(rota)); setModeloForm(montarModelo(rota));
  }, [view]);

  const vazio = estado === "vazio";
  // job_sheet.view_assigned sem view_all: o técnico não vê a casa toda (JobSheetController:118).
  const lista = vazio ? [] : D.visiveis(folhas, papel);
  const selecionada = lista.find((f) => f.id === sel);
  const P = window.RepForms || {};
  const PR = window.RepairPrint;
  const Portal = (window.RepPortal || {}).PortalConsulta;

  const mover = (f, coluna) => {
    const alvo = D.STATUS.find((s) => s.coluna === coluna);
    if (!alvo) return;
    setFolhas((l) => l.map((x) => (x.id === f.id ? { ...x, status: alvo.id } : x)));
    avisar(f.os + " → " + alvo.nome, "ok");
  };
  const acoes = {
    nova: () => setModal({ t: "folha", modo: "novo" }),
    editar: (f) => setModal({ t: "folha", modo: "editar", folha: f }),
    excluir: (f) => setModal({ t: "excluir", folha: f }),
    status: (f) => setModal({ t: "status", f }),
    pecas: (f) => setModal({ t: "pecas", folha: f }),
    docs: (f) => setModal({ t: "docs", folha: f }),
    imprimir: (f) => setModal({ t: "imprimir", folha: f }),
    statusLote: (ids) => setModal({ t: "lote", tipo: "status", ids }),
    tecnicoLote: (ids) => setModal({ t: "lote", tipo: "tecnico", ids }),
    excluirLote: (ids) => setModal({ t: "lote", tipo: "excluir", ids }),
    etiquetaLote: (ids) => {
      if (!PR) return avisar("Impressão não carregou.", "warn");
      const alvos = folhas.filter((f) => ids.includes(f.id));
      alvos.forEach((f, i) => setTimeout(() => PR.printEtiqueta(f), i * 400));
      avisar(alvos.length + " etiqueta(s) enviada(s) pra impressão.", "ok");
    },
  };
  const doLote = (ids) => folhas.filter((f) => ids.includes(f.id));
  const [paletaNode, abrirPaleta] = usePaleta({
    folhas: lista, onAba: setAba, onNova: acoes.nova,
    onAbrir: (id) => { setAba("folhas"); setSel(id); },
  });
  const irPara = (a, f) => { if (f) setFiltro(f); setAba(a); };

  const pend = D.pendentes(lista);
  return (
    <div className={"rep-root mp-page" + (dense ? " rep-dense" : "")} data-screen-label="01 Assistência técnica">
      {MP.Header &&
        <MP.Header modulo="Assistência técnica" papel={D.PAPEIS[papel].label}
          contexto={["REPAIR", "Matriz + 1 filial", lista.length + " folhas · " + pend.length + " pendentes"]}
          atualizadoAs={hora} glyph={<Ic name="wrench" />}
          onRefresh={() => { setHora(new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })); avisar("Reapurado — folhas, status e prazos.", "ok"); }}
          acoes={<>
            <div className="mp-busca">
              <span aria-hidden="true">⌕</span>
              <input aria-label="Buscar folha, cliente, série ou modelo" placeholder="Buscar folha, cliente, série, modelo..." value={busca}
                onChange={(e) => { setBusca(e.target.value); setAba("folhas"); setFiltro("todas"); }} />
              <kbd>/</kbd>
            </div>
            {Button && <Button variant="ghost" onClick={abrirPaleta}>Buscar <kbd className="rep-kbd">⌘K</kbd></Button>}
            {Button && D.can(papel, "job_sheet.create") && <Button variant="primary" onClick={acoes.nova}>Adicionar folha</Button>}
          </>} />}
      {MP.Tabs &&
        <MP.Tabs tab={aba} onTab={setAba} aria="Áreas da assistência técnica"
          tabs={[
            { key: "painel", label: "Painel", icon: "chart" },
            { key: "producao", label: "Produção", icon: "grid", n: pend.length },
            { key: "folhas", label: "Folhas de OS", icon: "orders", n: lista.length },
            { key: "reparos", label: "Reparos", icon: "coins", n: lista.length },
            { key: "status", label: "Status", icon: "list", n: D.STATUS.length },
            { key: "modelos", label: "Modelos", icon: "product", n: D.MODELOS.length },
            { key: "portal", label: "Portal do cliente", icon: "target" },
            { key: "config", label: "Configurações", icon: "settings" },
          ]} />}

      {estado === "carregando" && MP.Skeleton && <MP.Skeleton />}
      {estado === "erro" && MP.Estado &&
        <div className="mp-body">
          <MP.Estado erro titulo="Não consegui carregar a assistência técnica"
            descricao="A consulta de folhas e status falhou. Nada foi perdido. Tente reapurar; se insistir, o módulo pode estar sem assinatura ativa (permissão repair_module)."
            acao={Button ? <Button variant="ghost" onClick={() => avisar("Reapurando…")}>Tentar de novo</Button> : null} />
        </div>}
      {vazio && MP.Estado && estado === "dados" &&
        <div className="mp-body">
          <MP.Estado titulo="Nenhuma folha de OS aberta"
            descricao="A assistência começa no balcão: quem trouxe, qual equipamento, qual defeito e quando promete. Abra a primeira folha e o kanban passa a existir."
            acao={Button ? <Button variant="primary" onClick={acoes.nova}>Abrir a primeira folha</Button> : null} />
        </div>}

      {estado === "dados" && !vazio && <>
        {aba === "painel" && <div className="mp-body"><Painel folhas={lista} onIr={irPara} /></div>}
        {aba === "producao" && <div className="mp-body"><Producao folhas={lista} papel={papel} avisar={avisar} onAbrir={setSel} onMover={mover} /></div>}
        {aba === "folhas" && <Folhas folhas={lista} papel={papel} dense={dense} filtro={filtro} setFiltro={setFiltro} busca={busca} onAbrir={setSel} acoes={acoes} />}
        {aba === "reparos" && <Reparos folhas={lista} dense={dense} onAbrir={setReparo} />}
        {aba === "status" && <div className="mp-body"><Status folhas={lista} papel={papel} avisar={avisar} /></div>}
        {aba === "modelos" && <Modelos modelos={modelos} folhas={lista} dense={dense} papel={papel}
          onNovo={() => setModeloForm({ modo: "novo" })} onEditar={(m) => setModeloForm({ modo: "editar", modelo: m })} />}
        {aba === "portal" && <div className="mp-body">{Portal && <Portal folhas={folhas} avisar={avisar} />}</div>}
        {aba === "config" && <div className="mp-body"><Config papel={papel} avisar={avisar} onIr={setAba} /></div>}
      </>}

      {reparo && !modal && <ReparoDrawer folha={folhas.find((x) => x.id === reparo)} close={() => setReparo(null)} avisar={avisar} />}
      {modeloForm && <ModeloForm modo={modeloForm.modo} modelo={modeloForm.modelo} onClose={() => setModeloForm(null)}
        onSalvar={(m, novo) => {
          setModelos((l) => (novo ? [...l, { ...m, id: Math.max(0, ...l.map((x) => x.id)) + 1 }] : l.map((x) => (x.id === m.id ? { ...x, ...m } : x))));
          setModeloForm(null); avisar(novo ? "Modelo " + m.nome + " cadastrado." : "Modelo " + m.nome + " atualizado.", "ok");
        }} />}
      {selecionada && !modal && <FolhaDrawer f={selecionada} papel={papel} close={() => setSel(null)} acoes={acoes} avisar={avisar} />}
      {modal && modal.t === "status" &&
        <StatusModal f={modal.f} onClose={() => setModal(null)}
          onSalvar={(f, id, envio) => {
            setFolhas((l) => l.map((x) => (x.id === f.id ? { ...x, status: id } : x)));
            setModal(null);
            avisar(f.os + " → " + D.statusDe(id).nome + (envio.email || envio.sms ? " · cliente notificado" : ""), "ok");
          }} />}
      {modal && modal.t === "folha" && P.FolhaForm &&
        <P.FolhaForm modo={modal.modo} folha={modal.folha} folhas={folhas} papel={papel} onClose={() => setModal(null)}
          onSalvar={(f, novo) => {
            setFolhas((l) => (novo ? [{ ...f, id: Math.max(0, ...l.map((x) => x.id)) + 1 }, ...l] : l.map((x) => (x.id === f.id ? { ...x, ...f } : x))));
            setModal(null); setAba("folhas"); setFiltro("todas");
            avisar(novo ? "Folha " + f.os + " aberta — etiqueta na impressora." : "Folha " + f.os + " atualizada.", "ok");
          }} />}
      {modal && modal.t === "pecas" && P.PecasDrawer &&
        <P.PecasDrawer folha={modal.folha} avisar={avisar} onClose={() => setModal(null)}
          onSalvar={(f, itens, concluir) => {
            const concluido = D.STATUS.find((s) => s.concluido);
            setFolhas((l) => l.map((x) => (x.id === f.id ? { ...x, pecas: itens, status: concluir && concluido ? concluido.id : x.status } : x)));
            setModal(null);
            avisar(itens.length + " peça(s) em " + f.os + (concluir ? " · folha concluída" : ""), "ok");
          }} />}
      {modal && modal.t === "docs" && P.DocsDrawer &&
        <P.DocsDrawer folha={modal.folha} avisar={avisar} onClose={() => setModal(null)} />}
      {modal && modal.t === "excluir" && P.ExcluirModal &&
        <P.ExcluirModal folha={modal.folha} onClose={() => setModal(null)}
          onConfirmar={(f) => { setFolhas((l) => l.filter((x) => x.id !== f.id)); setModal(null); setSel(null); avisar("Folha " + f.os + " excluída.", "warn"); }} />}
      {modal && modal.t === "imprimir" && ImprimirModal &&
        <ImprimirModal folha={modal.folha} onClose={() => setModal(null)}
          onEscolher={(k) => {
            if (!PR) return avisar("Impressão não carregou.", "warn");
            if (k === "f1") PR.printFolha(modal.folha, { formato: 1 });
            if (k === "f2") PR.printFolha(modal.folha, { formato: 2 });
            if (k === "etiqueta") PR.printEtiqueta(modal.folha);
            if (k === "cliente") PR.printViaCliente(modal.folha);
            setModal(null);
          }} />}
      {modal && modal.t === "lote" &&
        <LoteModal tipo={modal.tipo} folhas={doLote(modal.ids)} onClose={() => setModal(null)}
          onSalvar={(valor) => {
            if (modal.tipo === "excluir") setFolhas((l) => l.filter((x) => !modal.ids.includes(x.id)));
            else setFolhas((l) => l.map((x) => (modal.ids.includes(x.id)
              ? (modal.tipo === "status" ? { ...x, status: Number(valor) } : { ...x, tecnico: valor })
              : x)));
            setModal(null);
            avisar(modal.ids.length + " folha(s) " + (modal.tipo === "excluir" ? "excluída(s)" : modal.tipo === "status" ? "com status " + D.statusDe(Number(valor)).nome : "atribuída(s) a " + valor), modal.tipo === "excluir" ? "warn" : "ok");
          }} />}
      {paletaNode}
      <div className="rep-aviso-live" role="status" aria-live="polite" aria-atomic="true">{avisoNode}</div>
    </div>
  );
}

window.RepairPage = RepairPage;
window.RepRotas = ROTAS;
})();
