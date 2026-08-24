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
  const [selecao, setSelecao] = useState([]);
  const [gridKey, setGridKey] = useState(0);
  if (!DataTablePro) return null;

  const base = folhas.filter((f) => {
    if (filtro === "pendentes" && D.statusDe(f.status).concluido) return false;
    if (filtro === "concluidas" && !D.statusDe(f.status).concluido) return false;
    if (filtro === "atrasadas" && !D.atrasada(f)) return false;
    if (tecnico !== "todos" && f.tecnico !== tecnico) return false;
    if (local !== "todos" && D.LOCAIS[f.local] !== local) return false;
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
        fase: window.FsmStepper ? <window.FsmStepper domain="repair" current={D.faseDe(f.status)} variant="dots-inline" /> : null,
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
        {TabBar && <TabBar active={filtro} onChange={(k) => { setFiltro(k); setPag(1); }}
          tabs={[
            { key: "pendentes", label: "Pendentes", count: D.pendentes(folhas).length },
            { key: "concluidas", label: "Concluídas", count: D.concluidas(folhas).length },
            { key: "atrasadas", label: "Entrega vencida", count: folhas.filter(D.atrasada).length },
            { key: "todas", label: "Todas", count: folhas.length },
          ]} />}
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

// ══════════ REPAROS (repair/index.blade.php — a transaction) ══════════
function Reparos({ folhas, dense, onAbrir }) {
  const D = R();
  const { DataTablePro, StatusBadge, KpiCard } = DS();
  const areaRef = useRef(null); const altura = useAltura(areaRef, 240);
  if (!DataTablePro) return null;
  const total = D.REPAROS.reduce((s, r) => s + r.total, 0);
  const aberto = D.REPAROS.reduce((s, r) => s + r.saldo, 0);
  const colunas = [
    { key: "repair", label: "Nº do reparo", width: 150, mono: true, sortable: true },
    { key: "fatura", label: "Fatura", width: 118, mono: true },
    { key: "folha", label: "Folha de OS", width: 132, mono: true },
    { key: "cliente", label: "Cliente", width: 200, sortable: true },
    { key: "garantia", label: "Garantia", width: 150 },
    { key: "pgto", label: "Pagamento", width: 120 },
    { key: "total", label: "Total", width: 120, align: "right", mono: true, sortable: true },
    { key: "saldo", label: "Saldo devedor", width: 130, align: "right", mono: true },
  ];
  const linhas = D.REPAROS.map((r) => {
    const f = folhas.find((x) => x.id === r.folha) || {};
    return { id: r.id, state: r.saldo > 0 ? "urgent" : undefined, cells: {
      repair: r.repair, fatura: r.fatura, folha: f.os || "—", cliente: f.cliente || "—", garantia: r.garantia,
      pgto: StatusBadge ? <StatusBadge kind="payment" value={r.pagamento} /> : r.pagamento,
      total: D.fmt(r.total), saldo: r.saldo ? D.fmt(r.saldo) : "—",
    } };
  });
  return (
    <div className="rep-list">
      <div className="rep-kpis">
        {KpiCard && <>
          <KpiCard label="Reparos faturados" value={D.REPAROS.length} description="transactions do módulo" />
          <KpiCard label="Valor faturado" value={D.fmt(total)} tone="info" />
          <KpiCard label="Em aberto" value={D.fmt(aberto)} tone={aberto ? "warning" : "success"} description="cobrança vive no Financeiro" />
        </>}
      </div>
      <div className="rep-grid" ref={areaRef}>
        <DataTablePro columns={colunas} rows={linhas} height={altura} density={dense ? "compact" : "comfortable"}
          onRowClick={(r) => { const rr = D.REPAROS.find((x) => x.id === r.id); if (rr) onAbrir(rr.folha); }} />
      </div>
      <p className="rep-nota">O reparo é a venda derivada da folha — pagamento, garantia e cobrança são de Vendas/Financeiro. Aqui só se lê.</p>
    </div>
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
      </Alert>}
      {Button && <div className="rep-cfg-acoes"><Button variant="primary" onClick={() => pode ? avisar("Novo status — nome, cor, ordem, templates.") : avisar("Sem permissão.", "warn")}>Adicionar status</Button>
        <span>Permissão: <b className="mono">access_job_sheet_status</b></span></div>}
    </div>
  );
}

// ══════════ MODELOS DE DISPOSITIVO (device_model/index.blade.php) ══════════
function Modelos({ folhas, dense, papel, avisar }) {
  const D = R();
  const { DataTablePro, Button, TagChip } = DS();
  const areaRef = useRef(null); const altura = useAltura(areaRef, 240);
  if (!DataTablePro) return null;
  const colunas = [
    { key: "nome", label: "Modelo", width: 190, sortable: true },
    { key: "marca", label: "Marca", width: 130, sortable: true },
    { key: "disp", label: "Equipamento", width: 190, sortable: true },
    { key: "check", label: "Checklist de pré-reparo", width: 420 },
    { key: "n", label: "Folhas", width: 90, align: "right", mono: true, sortable: true },
  ];
  const linhas = D.MODELOS.map((m) => ({ id: m.id, cells: {
    nome: m.nome, marca: m.marca, disp: m.dispositivo,
    check: <span className="rep-chips">{m.checklist.split("|").map((c) => TagChip ? <TagChip key={c} label={c.toLowerCase()} /> : <span key={c}>{c}</span>)}</span>,
    n: folhas.filter((f) => f.modelo === m.id).length,
  } }));
  return (
    <div className="rep-list">
      <div className="rep-subtabs">
        <span className="rep-hint">O checklist do modelo é o que aparece na folha ao escolher o equipamento — no legado é uma string separada por <b className="mono">|</b>.</span>
        <span className="sp" />
        {Button && D.can(papel, "repair.create") && <Button variant="primary" onClick={() => avisar("Novo modelo — marca, equipamento e checklist.")}>Adicionar modelo</Button>}
      </div>
      <div className="rep-grid" ref={areaRef}>
        <DataTablePro columns={colunas} rows={linhas} height={altura} density={dense ? "compact" : "comfortable"} />
      </div>
    </div>
  );
}

// ══════════ CONFIGURAÇÕES (settings/index.blade.php) ══════════
function Config({ papel, avisar }) {
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
        </div>
      </div>
      <div>
        <h3>O que aparece na impressão</h3>
        <p>Cada chave liga um bloco da etiqueta e da folha impressa — e o rótulo é editável porque cada gráfica chama a coisa pelo seu nome.</p>
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
  const abas = [["folha", "Folha"], ["checklist", "Checklist"], ["pecas", "Peças"], ["atividades", "Atividades"]];
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
      {window.FsmStepper && <div className="rep-drawer-fsm"><window.FsmStepper domain="repair" current={D.faseDe(f.status)} variant="full-stepper" /></div>}
      <nav className="rep-drawer-nav">
        {abas.map(([k, l]) => <button key={k} className={aba === k ? "on" : ""} onClick={() => setAba(k)}>{l}</button>)}
      </nav>

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

// ══════════ SHELL ══════════
function RepairPage({ view, dense, estado = "dados", papel: papelProp }) {
  const D = R();
  const MP = window.ModuloPadrao || {};
  const { Button } = DS();
  const papel = D.PAPEIS[papelProp] ? papelProp : "administrador";
  const inicial = { "rep-producao": "producao", "rep-folhas": "folhas", "rep-reparos": "reparos", "rep-status": "status", "rep-modelos": "modelos", "rep-config": "config", "rep-portal": "portal" }[view];
  const [aba, setAba] = (MP.useAba || ((k, i) => useState(i)))("oimpresso.repair.aba", inicial || "painel");
  const [avisoNode, avisar] = (MP.useAviso || (() => [null, () => {}]))();
  const [folhas, setFolhas] = useState(D.FOLHAS);
  const [docsFolha, setDocsFolha] = useState(null);
  const [filtro, setFiltro] = useState("pendentes");
  const [busca, setBusca] = useState("");
  const [sel, setSel] = useState(null);
  const [modal, setModal] = useState(null);
  const [hora, setHora] = useState("09:42");
  useEffect(() => { if (inicial) setAba(inicial); }, [view]);

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
              <span>⌕</span>
              <input placeholder="Buscar folha, cliente, série, modelo..." value={busca}
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
            { key: "reparos", label: "Reparos", icon: "coins", n: D.REPAROS.length },
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
        {aba === "reparos" && <Reparos folhas={lista} dense={dense} onAbrir={setSel} />}
        {aba === "status" && <div className="mp-body"><Status folhas={lista} papel={papel} avisar={avisar} /></div>}
        {aba === "modelos" && <Modelos folhas={lista} dense={dense} papel={papel} avisar={avisar} />}
        {aba === "portal" && <div className="mp-body">{Portal && <Portal folhas={folhas} avisar={avisar} />}</div>}
        {aba === "config" && <div className="mp-body"><Config papel={papel} avisar={avisar} /></div>}
      </>}

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
})();
