// ────────────────────────────────────────────────
// OFICINA — Abertura de OS de manutenção + Cadastro de Locais
// ────────────────────────────────────────────────
const { Ic: IcC } = window;
const MC = window.MOCK;
const { DetailHeader: DHc } = window.OIUi;

function nextManId() {
  const nums = MC.OFICINA_OS.map(o => parseInt((o.id || "").replace(/\D/g, ""), 10)).filter(n => !isNaN(n));
  return "MAN-" + ((nums.length ? Math.max(...nums) : 2040) + 1);
}

function Field({ label, req, children, hint }) {
  return (
    <div className="oi-field">
      <label>{label}{req && <span className="req">*</span>}</label>
      {children}
      {hint && <div style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 5 }}>{hint}</div>}
    </div>
  );
}

// ─── Abertura de OS ───
function NovaManutencaoScreen({ nav, ctx, params }) {
  const STEPS = [
    { id: "veic", n: 1, label: "Veículo" },
    { id: "cliente", n: 2, label: "Cliente" },
    { id: "servico", n: 3, label: "Abertura" },
  ];
  const [step, setStep] = React.useState("veic");
  const si = STEPS.findIndex(s => s.id === step);
  const eq = params && params.equip;
  const eqDono = eq ? MC.equipDono(eq) : null;
  const [f, setF] = React.useState(eq ? {
    placa: eq.placa || "", tipoVeic: eq.tipo || "Cavalo mecânico 6x4", veiculo: ((eq.marca || "") + " " + (eq.modelo || "")).trim(),
    ano: String(eq.ano || ""), cor: eq.cor || "", chassi: eq.chassi || "", renavam: eq.renavam || "", km: String(eq.km || ""),
    cliente: eqDono ? eqDono.nome : "", motorista: "", frota: eq.apelido || "",
    tipoManut: "Corretiva", prio: "media", local: "patio", mecanico: null, prazo: "Hoje 17:00", sintomas: "",
  } : {
    placa: "", tipoVeic: "Cavalo mecânico 6x4", veiculo: "", ano: "", cor: "", chassi: "", renavam: "", km: "",
    cliente: "", motorista: "", frota: "",
    tipoManut: "Corretiva", prio: "media", local: "patio", mecanico: null, prazo: "Hoje 17:00", sintomas: "",
  });
  const set = (k, v) => setF(s => ({ ...s, [k]: v }));

  // autofill demo a partir de OS existente com a mesma placa
  const buscarPlaca = () => {
    const hit = MC.OFICINA_OS.find(o => o.placa.replace(/\W/g, "").toUpperCase() === f.placa.replace(/\W/g, "").toUpperCase());
    if (hit) setF(s => ({ ...s, tipoVeic: hit.tipoVeic, veiculo: hit.veiculo, ano: String(hit.ano), cor: hit.cor, chassi: hit.chassi, renavam: hit.renavam, km: String(hit.km), cliente: hit.cliente, motorista: hit.motorista, frota: hit.frota }));
  };

  const abrir = () => {
    const novo = {
      id: nextManId(), abertura: "Agora",
      placa: f.placa.toUpperCase() || "SEM-PLACA", chassi: f.chassi || "—", renavam: f.renavam || "—",
      veiculo: f.veiculo || "Veículo não identificado", tipoVeic: f.tipoVeic, ano: +f.ano || new Date().getFullYear(), cor: f.cor || "—",
      km: +String(f.km).replace(/\D/g, "") || 0, frota: f.frota || "—", cliente: f.cliente || "Cliente avulso", motorista: f.motorista || "—",
      tipoManut: f.tipoManut, status: "Triagem", local: f.local, mecanico: f.mecanico,
      prio: f.prio, prazo: f.prazo, dueMin: 480, sintomas: f.sintomas || "—", diagnostico: "", itens: [],
    };
    MC.OFICINA_OS.unshift(novo);
    nav.replace("manut-os", { id: novo.id });
  };

  const podeAvancar = step === "veic" ? f.placa.trim().length > 0 : true;

  return (
    <>
      <DHc nav={nav} title="Nova manutenção" eyebrow="Abertura de OS" />
      <div className="oi-steps">
        {STEPS.map((s, i) => (
          <button key={s.id} className={"oi-step" + (step === s.id ? " active" : i < si ? " done" : "")} onClick={() => setStep(s.id)}>
            <span className="num">{i < si ? "✓" : s.n}</span>{s.label}
          </button>
        ))}
      </div>

      <div className="oi-scroll">
        {step === "veic" && (
          <div className="oi-section">
            <Field label="Placa" req>
              <div style={{ display: "flex", gap: 8 }}>
                <input className="oi-input oi-mono" placeholder="ABC-1D23" value={f.placa}
                       onChange={e => set("placa", e.target.value.toUpperCase())} style={{ textTransform: "uppercase", letterSpacing: ".08em" }} />
                <button className="oi-btn" style={{ flex: "0 0 auto" }} onClick={buscarPlaca}><IcC.search size={16} /> Buscar</button>
              </div>
            </Field>
            <Field label="Tipo de veículo">
              <select className="oi-select" value={f.tipoVeic} onChange={e => set("tipoVeic", e.target.value)}>
                {["Cavalo mecânico 6x4", "Cavalo mecânico 6x2", "Caminhão 4x2 toco", "Caminhão 6x2 truck", "Semirreboque 3 eixos", "Utilitário / van", "Máquina / implemento"].map(t => <option key={t}>{t}</option>)}
              </select>
            </Field>
            <Field label="Marca e modelo">
              <input className="oi-input" placeholder="Ex: Volvo FH 540" value={f.veiculo} onChange={e => set("veiculo", e.target.value)} />
            </Field>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              <Field label="Ano"><input className="oi-input oi-mono" placeholder="2022" value={f.ano} onChange={e => set("ano", e.target.value)} /></Field>
              <Field label="Cor"><input className="oi-input" placeholder="Branco" value={f.cor} onChange={e => set("cor", e.target.value)} /></Field>
            </div>
            <Field label="Chassi" hint="17 caracteres — usado para histórico e garantia">
              <input className="oi-input oi-mono" placeholder="9BW..." value={f.chassi} onChange={e => set("chassi", e.target.value.toUpperCase())} />
            </Field>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              <Field label="Renavam"><input className="oi-input oi-mono" placeholder="0000000000" value={f.renavam} onChange={e => set("renavam", e.target.value)} /></Field>
              <Field label="Hodômetro (km)"><input className="oi-input oi-mono" placeholder="418320" value={f.km} onChange={e => set("km", e.target.value)} /></Field>
            </div>
          </div>
        )}

        {step === "cliente" && (
          <div className="oi-section">
            <Field label="Cliente / transportadora" hint="Proprietário responsável pela OS">
              <input className="oi-input" placeholder="Ex: Transportes Andorinha" value={f.cliente} onChange={e => set("cliente", e.target.value)} />
            </Field>
            <Field label="Motorista">
              <input className="oi-input" placeholder="Nome do condutor" value={f.motorista} onChange={e => set("motorista", e.target.value)} />
            </Field>
            <Field label="Nº de frota" hint="Identificação interna do cliente (opcional)">
              <input className="oi-input oi-mono" placeholder="FR-12" value={f.frota} onChange={e => set("frota", e.target.value)} />
            </Field>
          </div>
        )}

        {step === "servico" && (
          <div className="oi-section">
            <Field label="Tipo de manutenção">
              <div className="oi-seg">
                {["Corretiva", "Preventiva", "Revisão", "Sinistro"].map(t => (
                  <button key={t} className={f.tipoManut === t ? "on" : ""} onClick={() => set("tipoManut", t)} style={{ fontSize: 12 }}>{t}</button>
                ))}
              </div>
            </Field>
            <Field label="Prioridade">
              <div className="oi-seg">
                {[["baixa", "Baixa"], ["media", "Média"], ["alta", "Alta"]].map(([v, l]) => (
                  <button key={v} className={f.prio === v ? "on" : ""} onClick={() => set("prio", v)}
                          style={{ background: f.prio === v ? (v === "alta" ? "var(--danger)" : v === "media" ? "var(--warn)" : "var(--accent)") : undefined,
                                   borderColor: f.prio === v ? "transparent" : undefined }}>{l}</button>
                ))}
              </div>
            </Field>
            <Field label="Local inicial">
              <div className="oi-chips" style={{ flexWrap: "wrap" }}>
                {MC.OFICINA_LOCAIS.map(l => (
                  <button key={l.id} className={"oi-chip" + (f.local === l.id ? " on" : "")} onClick={() => set("local", l.id)}>
                    {React.createElement(IcC[l.ic] || IcC.location, { size: 13 })} {l.nome}
                  </button>
                ))}
              </div>
            </Field>
            <Field label="Mecânico responsável">
              <div className="oi-chips" style={{ flexWrap: "wrap" }}>
                <button className={"oi-chip" + (!f.mecanico ? " on" : "")} onClick={() => set("mecanico", null)}>A definir</button>
                {MC.OFICINA_MECANICOS.map(m => (
                  <button key={m.id} className={"oi-chip" + (f.mecanico === m.id ? " on" : "")} onClick={() => set("mecanico", m.id)}>{m.nome}</button>
                ))}
              </div>
            </Field>
            <Field label="Relato do motorista / sintomas">
              <textarea className="oi-textarea" placeholder="Ex: perda de força e fumaça preta sob carga…" value={f.sintomas} onChange={e => set("sintomas", e.target.value)} />
            </Field>
          </div>
        )}

        <div style={{ height: 100 }} />
      </div>

      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8 }}>
        {si > 0 && <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 16px" }} onClick={() => setStep(STEPS[si - 1].id)}><IcC.chevL size={16} /></button>}
        {si < STEPS.length - 1 ? (
          <button className="oi-btn primary" disabled={!podeAvancar}
                  onClick={() => podeAvancar && setStep(STEPS[si + 1].id)}
                  style={{ flex: 1, opacity: podeAvancar ? 1 : 0.5 }}>Continuar</button>
        ) : (
          <button className="oi-btn action" style={{ flex: 1 }} onClick={abrir}><IcC.check size={18} /> Abrir OS</button>
        )}
      </div>
    </>
  );
}

// ─── Cadastro de Locais ───
function LocaisScreen({ nav, ctx }) {
  const [, force] = React.useReducer(x => x + 1, 0);
  const locais = MC.OFICINA_LOCAIS;
  const ocup = (lid) => MC.OFICINA_OS.filter(o => o.local === lid && o.status !== "Pronto").length;
  const tipoLabel = (t) => (MC.OFICINA_LOCAL_TIPOS.find(x => x.id === t) || {}).label || t;
  const excluir = (id) => {
    const i = MC.OFICINA_LOCAIS.findIndex(l => l.id === id);
    if (i >= 0) MC.OFICINA_LOCAIS.splice(i, 1);
    force();
  };

  return (
    <>
      <DHc nav={nav} title="Locais da oficina" eyebrow={locais.length + " posições cadastradas"} />
      <div className="oi-scroll">
        <div className="oi-section">
          <p style={{ margin: "0 0 12px", fontSize: 12.5, color: "var(--text-dim)", lineHeight: 1.5 }}>
            Posições físicas onde os veículos ficam durante o serviço. Cada OS é alocada a um local — controle a ocupação em tempo real.
          </p>
          <div className="oi-list card">
            {locais.map(l => {
              const usados = ocup(l.id);
              const livre = usados === 0;
              return (
                <div key={l.id} className="oi-list-row" style={{ alignItems: "center" }}>
                  <div style={{ width: 36, height: 36, borderRadius: 9, flex: "0 0 auto", display: "grid", placeItems: "center",
                                background: livre ? "var(--bg-2)" : "var(--accent-soft)", color: livre ? "var(--text-mute)" : "var(--accent)",
                                border: "1px solid var(--border)" }}>
                    {React.createElement(IcC[l.ic] || IcC.location, { size: 18 })}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 13.5, fontWeight: 600 }}>{l.nome}</div>
                    <div style={{ fontSize: 11, color: "var(--text-mute)" }}>{tipoLabel(l.tipo)} · cap. {l.cap} · {l.obs}</div>
                  </div>
                  <span className={"oi-status " + (livre ? "ok" : "warn")} style={{ fontSize: 10.5 }}>
                    <span className="dot" />{livre ? "Livre" : (l.cap > 1 ? usados + "/" + l.cap : "Ocupado")}
                  </span>
                  <button className="oi-iconbtn" title={livre ? "Excluir local" : "Local ocupado — não pode excluir"}
                          onClick={() => { if (livre) excluir(l.id); }}
                          style={{ width: 32, height: 32, background: "transparent", flex: "0 0 auto",
                                   color: livre ? "var(--danger)" : "var(--text-mute)", opacity: livre ? 1 : 0.35,
                                   cursor: livre ? "pointer" : "not-allowed" }}>
                    <IcC.trash size={16} />
                  </button>
                </div>
              );
            })}
          </div>
        </div>
        <div style={{ height: 90 }} />
      </div>
      <div className="oi-fab" onClick={() => nav.push("novo-local")}><IcC.plus size={26} /></div>
    </>
  );
}

// ─── Novo local ───
function NovoLocalScreen({ nav, ctx, params }) {
  const [f, setF] = React.useState({ nome: "", tipo: "rampa", cap: 1, obs: "" });
  const set = (k, v) => setF(s => ({ ...s, [k]: v }));
  const salvar = () => {
    const tipo = MC.OFICINA_LOCAL_TIPOS.find(t => t.id === f.tipo) || {};
    MC.OFICINA_LOCAIS.push({
      id: "loc-" + Date.now(), nome: f.nome.trim() || "Novo local", tipo: f.tipo, ic: tipo.ic || "location",
      cap: Math.max(1, +f.cap || 1), obs: f.obs.trim() || tipo.label,
    });
    nav.pop();
  };
  return (
    <>
      <DHc nav={nav} title="Novo local" eyebrow="Cadastro de posição" />
      <div className="oi-scroll">
        <div className="oi-section">
          <Field label="Nome do local" req hint="Ex: Rampa 3, Elevador 2, Box de pintura">
            <input className="oi-input" placeholder="Rampa 3" value={f.nome} onChange={e => set("nome", e.target.value)} />
          </Field>
          <Field label="Tipo">
            <div className="oi-chips" style={{ flexWrap: "wrap" }}>
              {MC.OFICINA_LOCAL_TIPOS.map(t => (
                <button key={t.id} className={"oi-chip" + (f.tipo === t.id ? " on" : "")} onClick={() => set("tipo", t.id)}>
                  {React.createElement(IcC[t.ic] || IcC.location, { size: 13 })} {t.label}
                </button>
              ))}
            </div>
          </Field>
          <Field label="Capacidade" hint="Quantos veículos cabem ao mesmo tempo">
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <button className="oi-btn" style={{ width: 46, flex: "0 0 auto", padding: 0 }} onClick={() => set("cap", Math.max(1, f.cap - 1))}><IcC.x size={14} /></button>
              <span className="oi-mono" style={{ fontSize: 22, fontWeight: 700, minWidth: 36, textAlign: "center" }}>{f.cap}</span>
              <button className="oi-btn" style={{ width: 46, flex: "0 0 auto", padding: 0 }} onClick={() => set("cap", f.cap + 1)}><IcC.plus size={16} /></button>
            </div>
          </Field>
          <Field label="Observação">
            <input className="oi-input" placeholder="Ex: elevadora 4t, geometria a laser…" value={f.obs} onChange={e => set("obs", e.target.value)} />
          </Field>
        </div>
        <div style={{ height: 90 }} />
      </div>
      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8 }}>
        <button className="oi-btn action block" onClick={salvar}><IcC.check size={18} /> Salvar local</button>
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { NovaManutencaoScreen, LocaisScreen, NovoLocalScreen });
