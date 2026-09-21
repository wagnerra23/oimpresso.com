// oficina-fila.jsx — View "Fila" (master-detail) da Oficina Auto.
// 4ª view do toggle (Kanban / Lista / Grade / Fila). Montagem inspirada no
// cockpit do Luiz: lista persistente à esquerda + detalhe INLINE no centro +
// rail "APPS VINCULADOS" (OS + CRM + WhatsApp) à direita.
//
// NÃO toca o drawer travado: o detalhe inline REUSA os mesmos componentes
// funcionais (window.OficinaForms.{DviEditor, ItemsEditor, StageGate, Plate}).
// "Abrir OS completa" no rail abre o drawer canônico pra edição completa.
// Expõe window.OficinaFila.FilaView. CSS em oficina-fila.css.
(() => {
const { useState, useMemo, useEffect } = React;

const fmtBRL = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const valorNumOf = (s) => parseFloat((s || "").replace(/[^\d,]/g, "").replace(",", ".")) || 0;

const I = {
  print:  (p) => <svg width={p.size||13} height={p.size||13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="9" rx="2"/><path d="M6 14h12v7H6z"/></svg>,
  arrow:  (p) => <svg width={p.size||13} height={p.size||13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>,
  camera: (p) => <svg width={p.size||13} height={p.size||13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 8h4l2-3h6l2 3h4v11H3z"/><circle cx="12" cy="13" r="3.5"/></svg>,
  wa:     (p) => <svg width={p.size||13} height={p.size||13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/></svg>,
  phone:  (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.6 2Z"/></svg>,
  ext:    (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>,
  user:   (p) => <svg width={p.size||13} height={p.size||13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>,
  car:    (p) => <svg width={p.size||13} height={p.size||13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 17h14M3 13l2-5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2l2 5"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>,
};

// Contatos CRM derivados (mock plausível p/ o rail; nome real vem da OS).
const CRM = {
  "Marcos Aleixo":       { tel: "(34) 9 9988-7766", ult: "WhatsApp · hoje" },
  "Frota Boa Esperança": { tel: "(34) 3221-4500",   ult: "E-mail · ontem" },
  "Construtora Lince":   { tel: "(34) 9 9712-3380", ult: "WhatsApp · hoje" },
  "Larissa Nunes":       { tel: "(34) 9 9840-1122", ult: "Ligação · 2 dias" },
  "Agropecuária Vale":   { tel: "(34) 3232-7788",   ult: "WhatsApp · ontem" },
  "Eduardo Pessoa":      { tel: "(34) 9 9655-9090", ult: "WhatsApp · hoje" },
  "Marina Tavares":      { tel: "(34) 9 9533-2010", ult: "WhatsApp · hoje" },
  "Roberto Coelho":      { tel: "(34) 9 9401-7755", ult: "Ligação · hoje" },
  "Patrícia Lemos":      { tel: "(34) 9 9388-6612", ult: "WhatsApp · hoje" },
  "Auto Escola Norte":   { tel: "(34) 3299-1144",   ult: "E-mail · 3 dias" },
  "Helena Bastos":       { tel: "(34) 9 9277-4521", ult: "WhatsApp · ontem" },
  "Tiago Ramires":       { tel: "(34) 9 9166-3309", ult: "Pago PIX · 07/05" },
};
const crmOf = (name) => CRM[name] || { tel: "—", ult: "sem registro" };

const ACTIVITY = {
  "8821": "Cliente entregou chave às 08:14", "8822": "Aguarda primeira triagem",
  "8819": "OBD-II conectado · scan rodando", "8815": "Test drive: rampa do Quartel",
  "8810": "Disco BR-2188 chega 09/05 manhã", "8807": "WhatsApp enviado 11:30",
  "8804": "Peças no balcão · pronto",        "8801": "Polia da bomba removida",
  "8799": "3º pneu sendo balanceado",        "8795": "Cabeçote no banco · medindo",
  "8788": "Cliente avisado 17:42 ontem",     "8786": "Pago no PIX 07/05 16:30",
};

const REF = () => window.OFICINA_REF || { RECURSOS: [], MECANICOS: [], STAGES: [] };
const recursoOf = (id) => REF().RECURSOS.find(r => r.id === id);
const mechOf    = (id) => REF().MECANICOS.find(m => m.id === id);
const stageOf   = (id) => REF().STAGES.find(s => s.id === id);

// ───────────────────────── lista (coluna esquerda) ─────────────────────────
function StageBadge({ stage }) {
  const s = stageOf(stage);
  if (!s) return null;
  return (
    <span className="ofc-fl-badge">
      <span className={"prod-col-dot " + s.dot}/>{s.label}
    </span>
  );
}

function FilaItem({ os, active, onClick }) {
  const r = recursoOf(os.recurso);
  const m = mechOf(os.mech);
  const meta = [os.plate, r && r.label, m && m.nome].filter(Boolean).join(" · ");
  return (
    <button className={"ofc-fl-item" + (active ? " active" : "") + (os.urgent ? " urgent" : "")} onClick={onClick}>
      <div className="ofc-fl-item-top">
        <StageBadge stage={os.stage}/>
        <span className={"ofc-fl-when" + (os.urgent ? " urgent" : "")}>{os.deadline}</span>
      </div>
      <div className="ofc-fl-item-veh">{os.veh}</div>
      <div className="ofc-fl-item-sub">OS #{os.id} · {os.client}</div>
      <div className="ofc-fl-item-meta">{meta || "—"}</div>
    </button>
  );
}

function FilaList({ list, selectedId, onSelect }) {
  const urgentes = list.filter(o => o.urgent);
  const demais   = list.filter(o => !o.urgent);
  const Group = ({ title, items }) => items.length === 0 ? null : (
    <div className="ofc-fl-group">
      <div className="ofc-fl-group-h">{title}<span className="ofc-fl-group-c">{items.length}</span></div>
      {items.map(os => <FilaItem key={os.id} os={os} active={os.id === selectedId} onClick={() => onSelect(os.id)}/>)}
    </div>
  );
  return (
    <div className="ofc-fila-list">
      <div className="ofc-fl-head">
        <b>Ordens de serviço</b>
        <span className="ofc-fl-count">{list.length} abertas</span>
      </div>
      <div className="ofc-fl-scroll">
        {list.length === 0 && <div className="ofc-fl-empty">Nenhuma OS no filtro atual.</div>}
        <Group title="Urgentes" items={urgentes}/>
        <Group title="Demais"   items={demais}/>
      </div>
    </div>
  );
}

// ───────────────────────── detalhe inline (centro) ─────────────────────────
function Pipe({ stage }) {
  const STAGES = REF().STAGES;
  const idx = STAGES.findIndex(s => s.id === stage);
  return (
    <div className="ofc-pipe">
      {STAGES.map((s, i) => (
        <div key={s.id} className={"ofc-pipe-seg" + (i < idx ? " done" : i === idx ? " cur" : "")} title={s.label}>
          <span className={"prod-col-dot " + s.dot}/>
          <span className="ofc-pipe-lbl">{s.label}</span>
        </div>
      ))}
    </div>
  );
}

function OsDetailInline({ os, osItems, setOsItems, osDvi, setOsDvi, onAdvance }) {
  const F = window.OficinaForms || {};
  const r = recursoOf(os.recurso);
  const m = mechOf(os.mech);
  const STAGES = REF().STAGES;
  const items = osItems[os.id] || [];
  const dviItems = osDvi[os.id] || [];
  const stageIdx = STAGES.findIndex(s => s.id === os.stage);
  const nextStage = stageIdx >= 0 && stageIdx < STAGES.length - 1 ? STAGES[stageIdx + 1] : null;

  const gateCtx = {
    dviCount: dviItems.length,
    dviBad: dviItems.filter(d => d.status === "bad").length,
    itemsCount: items.length,
    itemsDone: items.filter(i => i.done).length,
  };

  const tl = [
    { when: "Hoje 08:14", what: "Veículo recepcionado",    by: "Larissa (recep.)", status: "done" },
    { when: "Hoje 09:10", what: "Triagem inicial",          by: m?.nome || "—",     status: "done" },
    { when: "Hoje 10:45", what: "Diagnóstico em andamento", by: m?.nome || "—",     status: os.stage === "diagnostico" ? "now" : "done" },
    ...(["pecas", "execucao", "pronto"].includes(os.stage) ? [{ when: "Hoje 11:30", what: "Orçamento enviado ao cliente", by: m?.nome || "—", status: "done" }] : []),
    ...(os.stage === "pecas" ? [{ when: "agora", what: "Aguardando peças/aprovação", by: "—", status: "now" }] : []),
    ...(os.stage === "execucao" ? [
      { when: "Hoje 13:20", what: "Aprovação do cliente recebida", by: "WhatsApp", status: "done" },
      { when: "agora", what: "Execução em andamento", by: m?.nome || "—", status: "now" },
    ] : []),
    ...(os.stage === "pronto" ? [
      { when: os.finishedAt, what: "Serviço concluído", by: m?.nome || "—", status: "done" },
      { when: "—", what: "Aguardando retirada", by: os.client, status: "now" },
    ] : []),
  ];

  return (
    <div className="ofc-fila-detail" data-screen-label="01 Oficina Auto · Fila">
      <header className="ofc-det-head">
        <div className="ofc-det-head-l">
          <span className="ofc-det-eyebrow"><StageBadge stage={os.stage}/> OS #{os.id} · {os.client}</span>
          <h2>{os.veh}</h2>
        </div>
        <div className="ofc-det-head-r">
          <button className="os-btn ghost" onClick={() => window.OficinaPrint && window.OficinaPrint.printOS(os, { items: osItems[os.id] || [], dvi: osDvi[os.id] || [] })}><I.print size={12}/>Imprimir</button>
          {nextStage
            ? <button className="os-btn primary" onClick={() => onAdvance(os)}>Avançar p/ {nextStage.label} <I.arrow size={12}/></button>
            : <button className="os-btn primary" onClick={() => onAdvance(os)}>Entregar veículo <I.arrow size={12}/></button>}
        </div>
      </header>

      <div className="ofc-det-body">
        <Pipe stage={os.stage}/>

        <div className="ofc-veh-card">
          {F.Plate && <F.Plate value={os.plate}/>}
          <dl>
            <dt>KM</dt><dd>{os.km}</dd>
            <dt>Box</dt><dd>{r ? r.label : "— (sem alocação)"}</dd>
            <dt>Mecânico</dt><dd>{m ? m.nome : "—"}</dd>
            <dt>Valor</dt><dd>{os.value}</dd>
          </dl>
        </div>

        <div className="ofc-drawer-section">
          <h4>Sintoma reportado</h4>
          <p style={{ margin: 0, fontSize: "13px", lineHeight: 1.45, color: "var(--text)" }}>{os.symptom}</p>
        </div>

        <div className="ofc-drawer-section">
          <h4>Vistoria Digital · DVI</h4>
          {F.DviEditor
            ? <F.DviEditor items={dviItems} onChange={(next) => setOsDvi({ ...osDvi, [os.id]: next })} onAprovarWhats={() => alert("Link de aprovação enviado por WhatsApp para o cliente.")}/>
            : <p className="dim sm">DVI editor não carregado.</p>}
        </div>

        <div className="ofc-drawer-section">
          <h4>Fotos &amp; Laudo</h4>
          <div className="ofc-photos">
            <div className="ofc-photo">FOTO·1<br/>frente</div>
            <div className="ofc-photo">FOTO·2<br/>painel</div>
            <div className="ofc-photo">FOTO·3<br/>peça</div>
          </div>
        </div>

        <div className="ofc-drawer-section">
          <h4>Peças &amp; Mão de obra</h4>
          {F.ItemsEditor
            ? <F.ItemsEditor items={items} onChange={(next) => setOsItems({ ...osItems, [os.id]: next })}/>
            : <p className="dim sm">Editor de items não carregado.</p>}
        </div>

        <div className="ofc-drawer-section">
          <h4>Checklist de etapa</h4>
          {F.StageGate
            ? <F.StageGate os={os} ctx={gateCtx} onAdvance={() => onAdvance(os)}/>
            : <p className="dim sm">Gate de etapa não carregado.</p>}
        </div>

        <div className="ofc-drawer-section">
          <h4>Linha do tempo</h4>
          <div className="ofc-timeline">
            {tl.map((t, i) => (
              <div key={i} className={"ofc-tl-item " + t.status}>
                <div className="ofc-tl-when">{t.when}</div>
                <div className="ofc-tl-what">{t.what}</div>
                <div className="ofc-tl-by">{t.by}</div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

// ───────────────────────── rail "APPS VINCULADOS" (direita) ─────────────────────────
function AppsRail({ os, onOpenFull }) {
  const stage = stageOf(os.stage);
  const crm = crmOf(os.client);
  const initials = os.client.split(" ").slice(0, 2).map(w => w[0]).join("").toUpperCase();
  return (
    <aside className="ofc-fila-rail">
      <div className="ofc-rail-h">Apps vinculados</div>

      <div className="ofc-rail-card">
        <div className="ofc-rail-card-h">
          <span className="ofc-rail-tag os"><I.car size={12}/>OS</span>
          <b>Ordem #{os.id}</b>
        </div>
        <dl className="ofc-rail-dl">
          <dt>Veículo</dt><dd>{os.veh}</dd>
          <dt>Etapa</dt><dd>{stage ? stage.label : os.stage}</dd>
          <dt>Prazo</dt><dd>{os.deadline}</dd>
          <dt>Valor</dt><dd className="mono">{os.value}</dd>
        </dl>
        <button className="ofc-rail-btn" onClick={() => onOpenFull(os)}><I.ext size={12}/>Abrir OS completa</button>
      </div>

      <div className="ofc-rail-card">
        <div className="ofc-rail-card-h">
          <span className="ofc-rail-tag crm"><I.user size={12}/>CRM</span>
          <b>{os.client}</b>
        </div>
        <dl className="ofc-rail-dl">
          <dt>Telefone</dt><dd className="mono">{crm.tel}</dd>
          <dt>Últ. contato</dt><dd>{crm.ult}</dd>
          <dt>Atividade</dt><dd>{ACTIVITY[os.id] || "sem registro"}</dd>
        </dl>
        <button className="ofc-rail-btn"><I.wa size={12}/>Abrir conversa</button>
      </div>
    </aside>
  );
}

// ───────────────────────── view ─────────────────────────
function FilaView({ list, osList, selectedId, setSelectedId, osItems, setOsItems, osDvi, setOsDvi, onAdvance, onOpenFull }) {
  // seleção: respeita selectedId se ainda estiver na lista; senão 1º da lista.
  const sel = useMemo(() => {
    const inList = list.find(o => o.id === selectedId);
    return inList || list[0] || null;
  }, [list, selectedId]);

  useEffect(() => {
    if (sel && sel.id !== selectedId) setSelectedId(sel.id);
  }, [sel]); // eslint-disable-line

  return (
    <div className="ofc-fila">
      <FilaList list={list} selectedId={sel ? sel.id : null} onSelect={setSelectedId}/>
      {sel
        ? <OsDetailInline os={sel} osItems={osItems} setOsItems={setOsItems} osDvi={osDvi} setOsDvi={setOsDvi} onAdvance={onAdvance}/>
        : <div className="ofc-fila-detail ofc-fila-empty">Selecione uma OS na fila.</div>}
      {sel && <AppsRail os={sel} onOpenFull={onOpenFull}/>}
    </div>
  );
}

window.OficinaFila = { FilaView };
})();
