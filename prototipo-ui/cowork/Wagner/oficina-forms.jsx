// oficina-forms.jsx — Sprint paridade CRUD Oficina Auto (2026-05-26 [CC]).
// Escopo aprovado (m0193): SÓ destravar o que existe na tela aceita.
//   ▸ OsCreateDrawer  → controller create/store + edit/update reaproveita
//   ▸ ItemsEditor     → CRUD inline de items no drawer (Wave 1.3 US-027)
//   ▸ DviEditor       → CRUD inline DVI no drawer (Wave 3 US-035)
//   ▸ StageGate       → checklist de bloqueio por etapa (cliente curtiu m0193)
// FORA DE ESCOPO: Veículos CRUD, Caçambas, Aprovação pública UI, Show full-page.
//
// IIFE expõe window.OficinaForms.
(() => {
const { useState, useMemo } = React;

const fmtBRL = (n) => "R$ " + (n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const uid = () => Math.random().toString(36).slice(2, 8);

const I = {
  x:     <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M6 18L18 6"/></svg>,
  plus:  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>,
  trash: <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>,
  edit:  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 4l6 6-11 11H3v-6z"/></svg>,
  check: <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round"><path d="M5 13l4 4 10-10"/></svg>,
  arrow: <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>,
  msg:   <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/></svg>,
  search:<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>,
  lock:  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>,
};

// ──────────────────────────────────────────────────────────────
// Lookup mock — 6 veículos suficientes pra fluxo (sem CRUD de frota)
// ──────────────────────────────────────────────────────────────
const VEIC_MOCK = [
  { placa: "RBA-2H78", veh: "Honda Civic 2019",    km: "84.220",  cli: "Marcos Aleixo" },
  { placa: "FZJ-4F12", veh: "VW Saveiro 2021",     km: "62.140",  cli: "Frota Boa Esperança" },
  { placa: "QXM-1B33", veh: "Fiat Strada 2022",    km: "31.580",  cli: "Construtora Lince" },
  { placa: "GHS-8E22", veh: "Ford Ka 2018",        km: "108.900", cli: "Larissa Nunes" },
  { placa: "OWD-5R09", veh: "Toyota Hilux 2020",   km: "97.300",  cli: "Agropecuária Vale" },
  { placa: "ZTH-6L91", veh: "VW Gol 2016",         km: "162.800", cli: "Auto Escola Norte" },
];

function Plate({ value }) {
  return (
    <div className="ofc-plate">
      <div className="top">BR · MERCOSUL</div>
      <div className="num">{value || "—"}</div>
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// CHECKLIST DE BLOQUEIO POR ETAPA — gate canônico do fluxo
// Cliente aprovou esse conceito em m0193. Define o que TEM que estar
// feito antes da OS poder avançar pra próxima coluna do kanban.
// ──────────────────────────────────────────────────────────────
const STAGE_GATES = {
  recepcao: {
    next: "diagnostico",
    nextLabel: "Iniciar diagnóstico",
    items: [
      { id: "sint",  label: "Sintoma reportado pelo cliente",         auto: (os) => !!os.symptom?.trim() },
      { id: "km",    label: "KM atual conferido",                      auto: (os) => !!os.km },
      { id: "mech",  label: "Mecânico responsável alocado",            auto: (os) => !!os.mech },
      { id: "box",   label: "Box / elevador atribuído",                auto: (os) => !!os.recurso },
      { id: "fotoIn",label: "Foto de chegada (estado inicial)",        auto: () => false, manual: true },
    ],
  },
  diagnostico: {
    next: "pecas",
    nextLabel: "Enviar orçamento ao cliente",
    items: [
      { id: "dviMin",  label: "Vistoria DVI ≥ 5 itens",                   auto: (os, ctx) => (ctx.dviCount || 0) >= 5 },
      { id: "dviCrit", label: "Itens críticos da DVI documentados",       auto: (os, ctx) => (ctx.dviBad || 0) === 0 || !!ctx.dviCritDocumented },
      { id: "orca",    label: "Orçamento composto (peças + MO)",          auto: (os, ctx) => (ctx.itemsCount || 0) > 0 },
      { id: "fotoDiag",label: "Foto do problema identificado",             auto: () => false, manual: true },
    ],
  },
  pecas: {
    next: "execucao",
    nextLabel: "Iniciar execução",
    items: [
      { id: "aprov", label: "Cliente aprovou orçamento (PIN/WhatsApp)",  auto: (os) => os.partsStatus === "ok" },
      { id: "pecas", label: "Peças confirmadas (balcão ou caminho)",     auto: (os) => os.partsStatus === "ok" || os.partsStatus === "encomendado" },
      { id: "mech",  label: "Mecânico disponível",                        auto: (os) => !!os.mech },
      { id: "box",   label: "Box / elevador reservado",                   auto: (os) => !!os.recurso, manual: true },
    ],
  },
  execucao: {
    next: "pronto",
    nextLabel: "Concluir serviço",
    items: [
      { id: "itens",  label: "Todos os items da OS executados",          auto: (os, ctx) => (ctx.itemsDone || 0) === (ctx.itemsCount || 0) && (ctx.itemsCount || 0) > 0 },
      { id: "teste",  label: "Test drive / teste de bancada OK",          auto: () => false, manual: true },
      { id: "limpa",  label: "Veículo limpo (interior + exterior)",       auto: () => false, manual: true },
      { id: "fotoOut",label: "Foto pós-serviço (laudo final)",            auto: () => false, manual: true },
    ],
  },
  pronto: {
    next: "entregue",
    nextLabel: "Entregar veículo",
    items: [
      { id: "pago",   label: "Pagamento processado",                      auto: (os) => !!os.paid },
      { id: "avisa",  label: "Cliente avisado da retirada",              auto: () => false, manual: true },
      { id: "termo",  label: "Termo de garantia assinado",                auto: () => false, manual: true },
      { id: "chave",  label: "Chaves devolvidas + nota entregue",         auto: () => false, manual: true },
    ],
  },
};

// Hook: persiste checks manuais em localStorage por OS
function useManualChecks(osId) {
  const key = `ofc:manualChecks:${osId}`;
  const [m, setM] = useState(() => {
    try { return JSON.parse(localStorage.getItem(key) || "{}"); } catch { return {}; }
  });
  const set = (id, v) => {
    const next = { ...m, [id]: v };
    setM(next);
    try { localStorage.setItem(key, JSON.stringify(next)); } catch {}
  };
  return [m, set];
}

function evalGate(os, ctx) {
  const gate = STAGE_GATES[os.stage];
  if (!gate) return { gate: null, done: 0, total: 0, pct: 100, items: [] };
  let manualChecks = {};
  try { manualChecks = JSON.parse(localStorage.getItem(`ofc:manualChecks:${os.id}`) || "{}"); } catch {}
  const items = gate.items.map(it => ({
    ...it,
    ok: it.manual ? !!manualChecks[it.id] : !!it.auto(os, ctx),
  }));
  const done = items.filter(x => x.ok).length;
  return { gate, items, done, total: items.length, pct: items.length ? Math.round((done / items.length) * 100) : 100 };
}

// Mini-indicador pra usar nos cards do kanban (compacto)
function StageGateMini({ os, ctx }) {
  const { gate, done, total } = evalGate(os, ctx);
  if (!gate || total === 0) return null;
  const ok = done === total;
  return (
    <span className={"ofc-gate-mini " + (ok ? "ok" : done > total / 2 ? "warn" : "")} title={`${done}/${total} requisitos para "${gate.nextLabel}"`}>
      {ok ? I.check : I.lock}
      <span>{done}/{total}</span>
    </span>
  );
}

// Componente completo pra usar no drawer
function StageGate({ os, ctx, onAdvance }) {
  const [manual, setManual] = useManualChecks(os.id);
  const gate = STAGE_GATES[os.stage];
  if (!gate) {
    return (
      <div className="ofc-gate-card ok">
        <div className="ofc-gate-head"><span className="ico">{I.check}</span><b>OS encerrada</b><small>Sem mais etapas no fluxo.</small></div>
      </div>
    );
  }
  const items = gate.items.map(it => ({
    ...it,
    ok: it.manual ? !!manual[it.id] : !!it.auto(os, ctx),
  }));
  const done = items.filter(x => x.ok).length;
  const total = items.length;
  const ready = done === total;

  return (
    <div className={"ofc-gate-card " + (ready ? "ok" : "")}>
      <div className="ofc-gate-head">
        <span className="ico">{ready ? I.check : I.lock}</span>
        <div>
          <b>Gate p/ "{gate.nextLabel}"</b>
          <small>{done}/{total} requisitos · {ready ? "tudo pronto, pode avançar" : "OS bloqueada até completar checklist"}</small>
        </div>
        <span className="ofc-gate-pct">{Math.round((done/total)*100)}%</span>
      </div>
      <div className="ofc-gate-bar">
        <div className="ofc-gate-bar-fill" style={{ width: `${(done/total)*100}%` }}/>
      </div>
      <ul className="ofc-gate-list">
        {items.map(it => (
          <li key={it.id} className={it.ok ? "ok" : ""}>
            {it.manual ? (
              <label>
                <input type="checkbox" checked={it.ok} onChange={e => setManual(it.id, e.target.checked)}/>
                <span>{it.label}</span>
              </label>
            ) : (
              <div>
                <span className={"ofc-gate-auto " + (it.ok ? "ok" : "")}>{it.ok ? I.check : "○"}</span>
                <span>{it.label}</span>
                <small className="dim">auto · puxa do sistema</small>
              </div>
            )}
          </li>
        ))}
      </ul>
      <button className={"ofc-gate-cta " + (ready ? "primary" : "blocked")} onClick={() => ready && onAdvance?.(gate.next)} disabled={!ready}>
        {ready ? <>{I.arrow} {gate.nextLabel}</> : <>{I.lock} {total - done} {total - done === 1 ? "requisito pendente" : "requisitos pendentes"}</>}
      </button>
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// OS CREATE / EDIT DRAWER — stepper 3 passos
// ──────────────────────────────────────────────────────────────
function OsCreateDrawer({ initialOs, onClose, onSaved }) {
  const editing = !!initialOs;
  const RECURSOS = window.OFICINA_REF?.RECURSOS || [];
  const MECANICOS = window.OFICINA_REF?.MECANICOS || [];

  const [step, setStep] = useState(1);
  const [search, setSearch] = useState("");
  const [picked, setPicked] = useState(() => {
    if (!editing) return null;
    return { placa: initialOs.plate, veh: initialOs.veh, km: initialOs.km, cli: initialOs.client };
  });
  const [novo, setNovo] = useState({ placa:"", veh:"", km:"", cli:"" });
  const [showNovo, setShowNovo] = useState(false);

  const [sintoma, setSintoma] = useState(initialOs?.symptom || "");
  const [recurso, setRecurso] = useState(initialOs?.recurso || "");
  const [mech, setMech] = useState(initialOs?.mech || "");
  const [prazo, setPrazo] = useState(initialOs?.deadline || "Hoje 17h");
  const [urgent, setUrgent] = useState(initialOs?.urgent || false);
  const [valor, setValor] = useState((initialOs?.value || "0,00").replace(/^R\$\s*/, ""));
  const [aprovWhats, setAprovWhats] = useState(true);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return VEIC_MOCK.slice(0, 5);
    return VEIC_MOCK.filter(v =>
      v.placa.toLowerCase().includes(q) ||
      v.veh.toLowerCase().includes(q) ||
      v.cli.toLowerCase().includes(q)
    ).slice(0, 8);
  }, [search]);

  const canStep2 = !!picked;
  const canStep3 = !!sintoma.trim();

  const submit = () => {
    const id = editing ? initialOs.id : String(8800 + Math.floor(Math.random() * 200));
    const out = {
      ...(editing ? initialOs : {}),
      id,
      stage: editing ? initialOs.stage : "recepcao",
      plate: picked.placa,
      veh: picked.veh,
      km: picked.km || "0",
      client: picked.cli,
      symptom: sintoma,
      recurso: recurso || null,
      mech: mech || null,
      deadline: prazo,
      urgent,
      value: valor ? `R$ ${valor}` : "R$ 0,00",
      arrived: editing ? initialOs.arrived : new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }),
    };
    onSaved?.(out);
    onClose?.();
  };

  return (
    <div className="prod-drawer-backdrop" onClick={onClose}>
      <aside className="prod-drawer ofc-form-drawer" onClick={e => e.stopPropagation()}>
        <header className="prod-drawer-head">
          <div>
            <div className="prod-drawer-eyebrow">
              {editing ? `Editar OS #${initialOs.id}` : "Nova OS"}
              <span className="ofc-form-step-chip">passo {step}/3</span>
            </div>
            <h2 style={{margin:"4px 0 2px"}}>
              {step === 1 && "Identificar veículo"}
              {step === 2 && "Sintoma & alocação"}
              {step === 3 && "Revisar & abrir"}
            </h2>
            <p style={{margin:0,fontSize:"12px",color:"var(--text-dim)"}}>
              {step === 1 && "Busque por placa, modelo ou cliente"}
              {step === 2 && "O que o cliente reclamou e onde a OS começa"}
              {step === 3 && (editing ? "Confirme as alterações" : "Confirme antes de gerar a OS")}
            </p>
          </div>
          <button className="icon-btn" onClick={onClose}>{I.x}</button>
        </header>

        <div className="ofc-form-stepper">
          {[1,2,3].map(n => (
            <div key={n} className={"ofc-form-step " + (step === n ? "on" : step > n ? "done" : "")}>
              <span className="dot">{step > n ? I.check : n}</span>
              <span className="lbl">{n === 1 ? "Veículo" : n === 2 ? "Sintoma" : "Revisão"}</span>
            </div>
          ))}
        </div>

        <div className="prod-drawer-body">

          {step === 1 && (
            <>
              <div className="ofc-form-search">
                <span className="ico">{I.search}</span>
                <input type="text" autoFocus placeholder="placa · modelo · cliente"
                  value={search} onChange={e => setSearch(e.target.value)}/>
                <button className="os-btn ghost sm" onClick={() => setShowNovo(s => !s)}>
                  {I.plus} {showNovo ? "Cancelar" : "Veículo novo"}
                </button>
              </div>

              {editing && (
                <p className="ofc-form-edit-hint">
                  <b>Modo edição</b> · veículo atualmente vinculado abaixo. Escolha outro na lista pra trocar — todo veículo da OS vem do cadastro.
                </p>
              )}

              {showNovo && (
                <div className="ofc-form-card">
                  <h5 className="ofc-form-h5">Cadastrar rápido</h5>
                  <div className="ofc-form-grid">
                    <label><span>Placa</span><input value={novo.placa} onChange={e=>setNovo({...novo, placa:e.target.value.toUpperCase()})} placeholder="ABC-1D23" maxLength={8}/></label>
                    <label><span>Veículo</span><input value={novo.veh} onChange={e=>setNovo({...novo, veh:e.target.value})} placeholder="Fiat Argo 2022"/></label>
                    <label><span>KM atual</span><input value={novo.km} onChange={e=>setNovo({...novo, km:e.target.value})} className="mono"/></label>
                    <label><span>Cliente</span><input value={novo.cli} onChange={e=>setNovo({...novo, cli:e.target.value})} placeholder="Nome ou razão social"/></label>
                  </div>
                  <button className="os-btn primary sm" style={{marginTop:8}} disabled={!novo.placa || !novo.veh} onClick={() => {
                    setPicked({ ...novo });
                    setShowNovo(false);
                  }}>{I.check} Usar este veículo</button>
                </div>
              )}

              {!showNovo && (
                <ul className="ofc-form-veiclist">
                  {filtered.map(v => (
                    <li key={v.placa} className={picked?.placa === v.placa ? "on" : ""} onClick={() => setPicked(v)}>
                      <Plate value={v.placa}/>
                      <div className="meta">
                        <b>{v.veh}</b>
                        <small>{v.km} km · {v.cli}</small>
                      </div>
                      {picked?.placa === v.placa && <span className="picked-check">{I.check}</span>}
                    </li>
                  ))}
                  {filtered.length === 0 && (
                    <li className="empty">
                      Nenhum veículo. <button className="link" onClick={()=>setShowNovo(true)}>Cadastrar agora</button>
                    </li>
                  )}
                </ul>
              )}

              {picked && !showNovo && (
                <div className="ofc-form-veh-summary">
                  <Plate value={picked.placa}/>
                  <div className="m">
                    <b>{picked.veh}</b>
                    <small>{picked.km || "0"} km · {picked.cli}</small>
                  </div>
                </div>
              )}
            </>
          )}

          {step === 2 && (
            <>
              <div className="ofc-form-card">
                <h5 className="ofc-form-h5">Sintoma reportado pelo cliente</h5>
                <textarea className="ofc-form-textarea" rows={4} autoFocus
                  value={sintoma} onChange={e => setSintoma(e.target.value)}
                  placeholder="Ex: barulho nas rodas dianteiras quando faz curva à esquerda"/>
                {!editing && (
                  <div className="ofc-form-quick">
                    <small>atalhos</small>
                    {["Revisão preventiva","Troca de óleo","Luz de injeção","Freio com ruído","Embreagem patinando","Suspensão"].map(t => (
                      <button key={t} type="button" onClick={() => setSintoma(s => s ? s + " · " + t.toLowerCase() : t)}>{t}</button>
                    ))}
                  </div>
                )}
              </div>

              <div className="ofc-form-card">
                <h5 className="ofc-form-h5">Alocação</h5>
                <div className="ofc-form-grid">
                  <label><span>Box / elevador</span>
                    <select value={recurso} onChange={e => setRecurso(e.target.value)}>
                      <option value="">— sem alocação —</option>
                      {RECURSOS.map(r => <option key={r.id} value={r.id}>{r.label}</option>)}
                    </select>
                  </label>
                  <label><span>Mecânico responsável</span>
                    <select value={mech} onChange={e => setMech(e.target.value)}>
                      <option value="">— a definir —</option>
                      {MECANICOS.map(m => <option key={m.id} value={m.id}>{m.nome}</option>)}
                    </select>
                  </label>
                  <label><span>Prazo prometido</span>
                    <input value={prazo} onChange={e => setPrazo(e.target.value)} placeholder="Hoje 17h"/>
                  </label>
                  <label><span>Autoriz. preliminar (R$)</span>
                    <input value={valor} onChange={e => setValor(e.target.value)} className="mono" placeholder="0,00"/>
                  </label>
                  <label className="ofc-form-toggle full">
                    <input type="checkbox" checked={urgent} onChange={e => setUrgent(e.target.checked)}/>
                    <span><b>Marcar como urgente</b> — sobe pro topo + alerta no painel</span>
                  </label>
                  <label className="ofc-form-toggle full">
                    <input type="checkbox" checked={aprovWhats} onChange={e => setAprovWhats(e.target.checked)}/>
                    <span><b>Enviar link de aprovação por WhatsApp</b> — quando orçamento estiver pronto</span>
                  </label>
                </div>
              </div>
            </>
          )}

          {step === 3 && (
            <div className="ofc-form-review">
              <h5 className="ofc-form-h5">Resumo</h5>
              <div className="ofc-form-rev-card">
                <div className="row">
                  <Plate value={picked.placa}/>
                  <div>
                    <b>{picked.veh}</b>
                    <small>{picked.cli} · {picked.km || "0"} km</small>
                  </div>
                  {urgent && <span className="ofc-form-urg-chip">URGENTE</span>}
                </div>
                <dl>
                  <dt>Sintoma</dt><dd>{sintoma || <i style={{color:"var(--text-mute)"}}>—</i>}</dd>
                  <dt>Box</dt><dd>{RECURSOS.find(r => r.id === recurso)?.label || "—"}</dd>
                  <dt>Mecânico</dt><dd>{MECANICOS.find(m => m.id === mech)?.nome || "a definir"}</dd>
                  <dt>Prazo</dt><dd>{prazo}</dd>
                  <dt>Autoriz.</dt><dd className="mono">R$ {valor || "0,00"}</dd>
                  <dt>Aprovação</dt><dd>{aprovWhats ? "📲 WhatsApp + PIN" : "presencial"}</dd>
                </dl>
              </div>
              <p className="ofc-form-warn">
                {editing ? "Alterações salvam direto na OS atual." : "Após gerar, OS entra em Recepção e fica bloqueada pelo gate até a triagem ser completada."}
              </p>
            </div>
          )}

        </div>

        <footer className="ofc-form-foot">
          {step > 1 && <button className="os-btn ghost" onClick={() => setStep(step - 1)}>← Voltar</button>}
          <div style={{marginLeft:"auto", display:"flex", gap:8}}>
            <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
            {step < 3 && (
              <button className="os-btn primary"
                disabled={(step === 1 && !canStep2) || (step === 2 && !canStep3)}
                onClick={() => setStep(step + 1)}>
                Próximo {I.arrow}
              </button>
            )}
            {step === 3 && (
              <button className="os-btn primary" onClick={submit}>
                {I.check} {editing ? "Salvar alterações" : "Abrir OS"}
              </button>
            )}
          </div>
        </footer>
      </aside>
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// ITEMS EDITOR — CRUD inline de peça/MO/terceiro
// ──────────────────────────────────────────────────────────────
function ItemsEditor({ items, onChange }) {
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState({ tipo: "peca", nome: "", qty: 1, unit: 0, stat: "ok", done: false });

  const total = items.reduce((s, it) => s + (it.qty * it.unit), 0);
  const doneCount = items.filter(it => it.done).length;

  const add = () => {
    if (!draft.nome.trim()) return;
    onChange([...items, { ...draft, id: uid() }]);
    setDraft({ tipo: "peca", nome: "", qty: 1, unit: 0, stat: "ok", done: false });
    setAdding(false);
  };
  const patch = (id, p) => onChange(items.map(it => it.id === id ? { ...it, ...p } : it));
  const remove = (id) => onChange(items.filter(it => it.id !== id));

  const tipoLabel = { peca: "PEÇA", mao_obra: "M.O.", terceiro: "3º" };

  return (
    <div className="ofc-items-editor">
      <div className="ofc-items-head">
        <span>{items.length} {items.length === 1 ? "item" : "items"} · {doneCount}/{items.length} executado{doneCount !== 1 ? "s" : ""} · total <b className="mono">{fmtBRL(total)}</b></span>
        {!adding && <button className="os-btn ghost sm" onClick={() => setAdding(true)}>{I.plus} Adicionar</button>}
      </div>

      {items.length === 0 && !adding && (
        <div className="ofc-items-empty">— nenhum item lançado. <button className="link" onClick={()=>setAdding(true)}>Adicionar agora</button></div>
      )}

      <ul className="ofc-items-list">
        {items.map(it => (
          <li key={it.id} className={it.done ? "done" : ""}>
            <input type="checkbox" className="ofc-items-done" checked={it.done} onChange={e => patch(it.id, { done: e.target.checked })} title="marcar como executado"/>
            <span className={"ofc-items-tipo t-" + it.tipo}>{tipoLabel[it.tipo]}</span>
            <input className="ofc-items-nome" value={it.nome} onChange={e => patch(it.id, { nome: e.target.value })}/>
            <input className="ofc-items-qty mono" type="number" min="0" step="0.1" value={it.qty} onChange={e => patch(it.id, { qty: parseFloat(e.target.value) || 0 })}/>
            <span className="ofc-items-x">×</span>
            <input className="ofc-items-unit mono" type="number" min="0" step="0.01" value={it.unit} onChange={e => patch(it.id, { unit: parseFloat(e.target.value) || 0 })}/>
            <span className="ofc-items-sub mono">{fmtBRL(it.qty * it.unit)}</span>
            {it.tipo === "peca"
              ? <select className={"ofc-items-stat ofc-items-stat-" + (it.stat || "ok")} value={it.stat || "ok"} onChange={e => patch(it.id, { stat: e.target.value })}>
                  <option value="ok">estoque</option>
                  <option value="warn">encomend.</option>
                  <option value="wait">ag. aprov.</option>
                </select>
              : <span className="ofc-items-stat ofc-items-stat-na">{it.tipo === "mao_obra" ? "serviço" : "terceiro"}</span>}
            <button className="ofc-items-del" onClick={() => remove(it.id)} title="Remover">{I.trash}</button>
          </li>
        ))}
      </ul>

      {adding && (
        <div className="ofc-items-add">
          <select value={draft.tipo} onChange={e => { const t = e.target.value; setDraft({...draft, tipo: t, stat: t === "peca" ? "ok" : null}); }}>
            <option value="peca">Peça</option>
            <option value="mao_obra">Mão de obra</option>
            <option value="terceiro">Terceiro</option>
          </select>
          <input autoFocus placeholder={draft.tipo === "mao_obra" ? "serviço (ex: troca de pastilha)" : draft.tipo === "terceiro" ? "serviço terceirizado" : "descrição da peça"} value={draft.nome} onChange={e => setDraft({...draft, nome: e.target.value})}/>
          <input type="number" min="0" step="0.1" className="mono" placeholder={draft.tipo === "mao_obra" ? "horas" : "qtd"} value={draft.qty} onChange={e => setDraft({...draft, qty: parseFloat(e.target.value) || 0})}/>
          <span>×</span>
          <input type="number" min="0" step="0.01" className="mono" placeholder={draft.tipo === "mao_obra" ? "R$/h" : "R$ unit"} value={draft.unit} onChange={e => setDraft({...draft, unit: parseFloat(e.target.value) || 0})}/>
          {draft.tipo === "peca"
            ? <select value={draft.stat || "ok"} onChange={e => setDraft({...draft, stat: e.target.value})}>
                <option value="ok">estoque</option>
                <option value="warn">encomend.</option>
                <option value="wait">ag. aprov.</option>
              </select>
            : <span className="ofc-items-stat ofc-items-stat-na">{draft.tipo === "mao_obra" ? "serviço" : "terceiro"}</span>}
          <button className="os-btn primary sm" onClick={add}>{I.check}</button>
          <button className="os-btn ghost sm" onClick={()=>setAdding(false)}>{I.x}</button>
        </div>
      )}
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// DVI EDITOR — CRUD inline de itens da Vistoria Digital
// ──────────────────────────────────────────────────────────────
const DVI_SISTEMAS = [
  "Motor · óleo + filtro", "Motor · arrefecimento", "Freios dianteiros · pastilhas",
  "Freios traseiros · lonas/discos", "Correia dentada", "Bateria + sistema elétrico",
  "Pneus · dianteiros", "Pneus · traseiros", "Suspensão dianteira", "Suspensão traseira",
  "Direção · alinhamento", "Embreagem", "Câmbio", "Injeção", "Escapamento",
  "Iluminação", "Ar-condicionado", "Limpadores",
];

// Semáforo de severidade — 1 toque por estado (padrão canon Shop-Ware/Tekmetric;
// persona Técnico Repair: tablet, mãos sujas — nada de <select> nativo).
// F1 OS-V2-2 (fila 2026-06-09).
function DviTraffic({ value, onChange, name }) {
  const OPTS = [["ok", "OK"], ["warn", "Aten\u00e7\u00e3o"], ["bad", "Cr\u00edtico"]];
  return (
    <div className="ofc-traffic" role="radiogroup" aria-label={"Severidade de " + (name || "item")}>
      {OPTS.map(([v, lab]) => (
        <button key={v} type="button" role="radio" aria-checked={value === v}
          className={"t-" + v + (value === v ? " on" : "")}
          title={lab} aria-label={lab}
          onClick={() => onChange(v)} />
      ))}
    </div>
  );
}

// Gate de aprovação hero (F1 OS-V2-3) — barra de total + CTA com ciclo de estados:
// none → pending (WhatsApp enviado) → approved | declined → reopen.
// Estado vive no pai (osApproval por OS); aqui só render + ações.
function gateRel(ts) {
  if (!ts) return "";
  const m = Math.max(1, Math.round((Date.now() - ts) / 60000));
  return m < 60 ? "há " + m + " min" : "há " + Math.round(m / 60) + "h";
}
function DviGateFoot({ total, approval, onApproval, fallback }) {
  const st = (approval && approval.status) || "none";
  const tot = approval && approval.total != null ? approval.total : total;
  if (!onApproval) {
    return (
      <div className="ofc-dvi-foot">
        <div><small>Total recomendado · cliente</small><b>{fmtBRL(total)}</b></div>
        <button className="os-btn primary sm" onClick={fallback}>{I.msg} Pedir aprovação</button>
      </div>
    );
  }
  if (st === "pending") {
    return (
      <div className="ofc-dvi-foot pending">
        <div><small>Aguardando aprovação · WhatsApp {approval.sentLabel || gateRel(approval.sentAt)}</small><b>{fmtBRL(tot)}</b></div>
        <div className="gate-actions">
          <button className="os-btn sm" onClick={() => onApproval("cobrar")}>Cobrar</button>
          <span className="gate-sim">demo:
            <button onClick={() => onApproval("sim-approve")} title="Simular: cliente aprovou">✓</button>
            <button onClick={() => onApproval("sim-decline")} title="Simular: cliente recusou">✕</button>
          </span>
        </div>
      </div>
    );
  }
  if (st === "approved") {
    return (
      <div className="ofc-dvi-foot approved">
        <div><small>Aprovado pelo cliente {approval.decidedLabel || gateRel(approval.decidedAt)}</small><b>{fmtBRL(tot)}</b></div>
        <span className="gate-ok">{I.check} Autorizado</span>
      </div>
    );
  }
  if (st === "declined") {
    return (
      <div className="ofc-dvi-foot declined">
        <div><small>Cliente recusou {approval.decidedLabel || gateRel(approval.decidedAt)}</small><b>{fmtBRL(tot)}</b></div>
        <button className="os-btn sm" onClick={() => onApproval("reopen")}>Revisar e reenviar</button>
      </div>
    );
  }
  return (
    <div className="ofc-dvi-foot">
      <div><small>Total recomendado · cliente</small><b>{fmtBRL(total)}</b></div>
      <button className="os-btn primary sm" disabled={total <= 0} onClick={() => onApproval("request")}>{I.msg} Pedir aprovação</button>
    </div>
  );
}

function DviEditor({ items, onChange, onAprovarWhats, approval, onApproval }) {
  const [adding, setAdding] = useState(false);
  const [draft, setDraft] = useState({ sistema: DVI_SISTEMAS[0], status: "ok", obs: "", valor: 0 });
  const sums = items.reduce((acc, it) => { acc[it.status] = (acc[it.status] || 0) + 1; return acc; }, {});
  const total = items.reduce((s, i) => s + (parseFloat(i.valor) || 0), 0);

  const add = () => {
    if (!draft.sistema.trim()) return;
    onChange([...items, { ...draft, id: uid() }]);
    setDraft({ sistema: DVI_SISTEMAS[0], status: "ok", obs: "", valor: 0 });
    setAdding(false);
  };

  return (
    <>
      <div className="ofc-dvi-summary">
        <span className="ofc-dvi-pill ok"><b>{sums.ok || 0}</b> ok</span>
        <span className="ofc-dvi-pill warn"><b>{sums.warn || 0}</b> atenção</span>
        <span className="ofc-dvi-pill bad"><b>{sums.bad || 0}</b> crítico</span>
        {!adding && <button className="os-btn ghost sm" style={{marginLeft:"auto"}} onClick={()=>setAdding(true)}>{I.plus} Item</button>}
      </div>

      {items.length === 0 && !adding && (
        <div className="ofc-items-empty">— vistoria ainda não iniciada. <button className="link" onClick={()=>setAdding(true)}>Adicionar primeiro item</button></div>
      )}

      <ul className="ofc-dvi-list ofc-dvi-editable">
        {items.map(it => (
          <li key={it.id} className={it.status}>
            <DviTraffic value={it.status} name={it.sistema}
              onChange={(s) => onChange(items.map(x => x.id === it.id ? {...x, status: s} : x))}/>
            <div className="meta">
              <select value={DVI_SISTEMAS.includes(it.sistema) ? it.sistema : ""} onChange={e => onChange(items.map(x => x.id === it.id ? {...x, sistema: e.target.value} : x))}>
                {!DVI_SISTEMAS.includes(it.sistema) && <option value="">{it.sistema}</option>}
                {DVI_SISTEMAS.map(s => <option key={s}>{s}</option>)}
              </select>
              <input value={it.obs} placeholder="observação · recomendação" onChange={e => onChange(items.map(x => x.id === it.id ? {...x, obs: e.target.value} : x))}/>
            </div>
            <input className="vlr mono" type="number" min="0" step="0.01" value={it.valor} onChange={e => onChange(items.map(x => x.id === it.id ? {...x, valor: parseFloat(e.target.value) || 0} : x))}/>
            <button className="del" onClick={() => onChange(items.filter(x => x.id !== it.id))} title="Remover">{I.trash}</button>
          </li>
        ))}
      </ul>

      {adding && (
        <div className="ofc-dvi-add">
          <select value={draft.sistema} onChange={e => setDraft({...draft, sistema: e.target.value})}>
            {DVI_SISTEMAS.map(s => <option key={s}>{s}</option>)}
          </select>
          <DviTraffic value={draft.status} name="novo item" onChange={(s) => setDraft({...draft, status: s})}/>
          <input placeholder="observação" value={draft.obs} onChange={e => setDraft({...draft, obs: e.target.value})} autoFocus/>
          <input type="number" min="0" step="0.01" placeholder="R$" className="mono" value={draft.valor} onChange={e => setDraft({...draft, valor: parseFloat(e.target.value) || 0})}/>
          <button className="os-btn primary sm" onClick={add}>{I.check}</button>
          <button className="os-btn ghost sm" onClick={()=>setAdding(false)}>{I.x}</button>
        </div>
      )}

      <DviGateFoot total={total} approval={approval} onApproval={onApproval} fallback={onAprovarWhats}/>
    </>
  );
}

// ──────────────────────────────────────────────────────────────
// Export
// ──────────────────────────────────────────────────────────────
window.OficinaForms = {
  OsCreateDrawer, ItemsEditor, DviEditor,
  StageGate, StageGateMini, evalGate,
  Plate,
};
})();
