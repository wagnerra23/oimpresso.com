// ────────────────────────────────────────────────
// EQUIPAMENTOS — frota e máquinas vinculadas a uma pessoa cadastrada
// ────────────────────────────────────────────────
const { Ic: IcE } = window;
const ME = window.MOCK;
const { DetailHeader: DHe, Placa: PlacaE, mDue: mDueE, statusTone: stToneE, MANUT_TONE: MTe } = window.Screens;
const BRLe = window.BRL;

function EqField({ label, req, children, hint }) {
  return (
    <div className="oi-field">
      <label>{label}{req && <span className="req">*</span>}</label>
      {children}
      {hint && <div style={{ fontSize: 11, color: "var(--text-mute)", marginTop: 5 }}>{hint}</div>}
    </div>
  );
}

function eqIcon(eq) { return eq.cat === "veiculo" ? "truck" : (ME.isImpressora(eq.tipo) ? "printer" : "settings"); }
function eqRef(eq) { return eq.cat === "veiculo" ? eq.placa : eq.serie; }
function eqUso(eq) { return eq.cat === "veiculo" ? (eq.km ? eq.km.toLocaleString("pt-BR") + " km" : "0 km") : (eq.horas ? eq.horas.toLocaleString("pt-BR") + " h" : "—"); }
// Estações de produção (impressoras ativas) ganham a cor de produção (accent);
// demais itens da frota usam o tom OFI institucional.
function eqTile(eq) {
  return eq.producao
    ? { bg: "var(--accent-soft)", fg: "var(--accent)" }
    : { bg: "var(--origin-OFI-bg)", fg: "var(--origin-OFI-fg)" };
}

// ─── Lista ───
function EquipamentosScreen({ nav, ctx }) {
  const [q, setQ] = React.useState("");
  const [cat, setCat] = React.useState("todos");
  const equips = ME.EQUIPAMENTOS;

  const list = equips.filter(eq => {
    if (cat === "veiculo" && eq.cat !== "veiculo") return false;
    if (cat === "equipamento" && eq.cat !== "equipamento") return false;
    if (q) {
      const dono = ME.equipDono(eq);
      const hay = (eq.marca + " " + eq.modelo + " " + (eq.placa || "") + " " + (eq.serie || "") + " " + eq.apelido + " " + (dono ? dono.nome : "")).toLowerCase();
      if (!hay.includes(q.toLowerCase())) return false;
    }
    return true;
  });

  return (
    <>
      <DHe nav={nav} title="Equipamentos" eyebrow={equips.length + " cadastrados · frota e máquinas"} />
      <div className="oi-head" style={{ paddingTop: 0, borderBottom: "1px solid var(--border)" }}>
        <div className="oi-search">
          <IcE.search size={16} />
          <input placeholder="Placa, modelo, proprietário…" value={q} onChange={e => setQ(e.target.value)} />
          {q && <button className="oi-iconbtn" style={{ width: 26, height: 26, background: "transparent" }} onClick={() => setQ("")}><IcE.x size={15} /></button>}
        </div>
        <div className="oi-chips" style={{ marginTop: 10 }}>
          {[["todos", "Todos", equips.length], ["veiculo", "Veículos", equips.filter(e => e.cat === "veiculo").length], ["equipamento", "Equipamentos", equips.filter(e => e.cat === "equipamento").length]].map(([id, l, c]) => (
            <button key={id} className={"oi-chip" + (cat === id ? " on" : "")} onClick={() => setCat(id)}>{l} <span className="c">{c}</span></button>
          ))}
        </div>
      </div>
      <div className="oi-scroll">
        <div className="oi-section">
          <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
            {list.map(eq => {
              const dono = ME.equipDono(eq);
              const hist = ME.equipHist(eq);
              const ativa = hist.find(o => o.status !== "Pronto");
              return (
                <div key={eq.id} className="oi-card tight" onClick={() => nav.push("equipamento", { id: eq.id })}
                     style={{ cursor: "pointer", padding: 12, gap: 8 }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <div style={{ width: 40, height: 40, borderRadius: 10, flex: "0 0 auto", background: eqTile(eq).bg,
                                  color: eqTile(eq).fg, display: "grid", placeItems: "center" }}>
                      {React.createElement(IcE[eqIcon(eq)], { size: 21 })}
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: 14, fontWeight: 600, letterSpacing: "-0.005em", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                        {eq.marca} {eq.modelo}
                      </div>
                      <div style={{ fontSize: 11.5, color: "var(--text-mute)" }}>{eq.tipo}{eq.apelido ? " · " + eq.apelido : ""}</div>
                    </div>
                    {eq.cat === "veiculo"
                      ? <PlacaE placa={eq.placa} />
                      : <span className="oi-mono" style={{ fontSize: 11, color: "var(--text-dim)" }}>{eq.serie}</span>}
                  </div>
                  <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 11.5, color: "var(--text-dim)" }}>
                    <span style={{ display: "inline-flex", alignItems: "center", gap: 4 }}>
                      <IcE.user size={12} color="var(--text-mute)" />{dono ? dono.nome : "Sem vínculo"}
                    </span>
                    <span style={{ color: "var(--text-mute)" }}>·</span>
                    <span className="oi-mono">{eqUso(eq)}</span>
                    {eq.producao && !ativa && <span className="oi-status accent" style={{ marginLeft: "auto", fontSize: 10 }}><IcE.printer size={11} /> Estação</span>}
                    {ativa && <span className={"oi-status " + stToneE(ativa.status)} style={{ marginLeft: "auto", fontSize: 10 }}><span className="dot" />Em manutenção</span>}
                  </div>
                </div>
              );
            })}
            {list.length === 0 && (
              <div className="oi-empty" style={{ marginTop: 24 }}>
                <div className="oi-empty-ico"><IcE.truck size={24} /></div>
                <b>Nenhum equipamento</b>
                <small>Nenhum item com esse filtro. Toque no + para cadastrar.</small>
              </div>
            )}
          </div>
        </div>
        <div style={{ height: 90 }} />
      </div>
      <div className="oi-fab" onClick={() => nav.push("novo-equipamento")}><IcE.plus size={26} /></div>
    </>
  );
}

// ─── Detalhe ───
function EquipamentoDetalheScreen({ nav, ctx, params }) {
  const eq = ME.EQUIPAMENTOS.find(x => x.id === params.id) || ME.EQUIPAMENTOS[0];
  const dono = ME.equipDono(eq);
  const hist = ME.equipHist(eq);
  return (
    <>
      <DHe nav={nav} title={eq.apelido || (eq.marca + " " + eq.modelo)} eyebrow={"Equipamento · " + eq.id}
           actions={<button className="oi-iconbtn" onClick={() => nav.push("editar-equipamento", { id: eq.id })}><IcE.edit size={18} /></button>} />
      <div className="oi-scroll">
        {/* Hero */}
        <div className="oi-section">
          <div className="oi-card pad" style={{ gap: 12 }}>
            <div style={{ display: "flex", alignItems: "flex-start", gap: 10 }}>
              <div style={{ width: 46, height: 46, borderRadius: 12, flex: "0 0 auto", background: eqTile(eq).bg,
                            color: eqTile(eq).fg, display: "grid", placeItems: "center" }}>
                {React.createElement(IcE[eqIcon(eq)], { size: 26 })}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 17, fontWeight: 600, letterSpacing: "-0.01em", lineHeight: 1.2 }}>{eq.marca} {eq.modelo}</div>
                <div style={{ fontSize: 12, color: "var(--text-dim)" }}>{eq.tipo} · {eq.ano}{eq.cor ? " · " + eq.cor : ""}</div>
                {eq.producao && (
                  <span style={{ display: "inline-flex", alignItems: "center", gap: 4, marginTop: 5, fontSize: 9.5, fontWeight: 700, letterSpacing: ".04em", textTransform: "uppercase", color: "var(--accent)", background: "var(--accent-soft)", padding: "2px 7px", borderRadius: 5 }}>
                    <IcE.printer size={11} /> Estação de produção
                  </span>
                )}
              </div>
              {eq.cat === "veiculo"
                ? (
                  <div style={{ display: "flex", flexDirection: "column", alignItems: "flex-end", gap: 4, flex: "0 0 auto" }}>
                    <PlacaE placa={eq.placa} size="lg" />
                    {eq.placa2 && (
                      <span style={{ display: "inline-flex", alignItems: "center", gap: 4, fontSize: 9, color: "var(--text-mute)", fontWeight: 600, letterSpacing: ".04em", textTransform: "uppercase" }}>
                        Carroceria <PlacaE placa={eq.placa2} />
                      </span>
                    )}
                  </div>
                )
                : <span className="oi-mono" style={{ fontSize: 12, color: "var(--text-dim)" }}>{eq.serie}</span>}
            </div>
            <dl className="oi-dl" style={{ borderTop: "1px solid var(--border-2)", paddingTop: 10 }}>
              {eq.cat === "veiculo" ? (
                <>
                  <dt>Chassi</dt>    <dd className="oi-mono" style={{ fontSize: 12 }}>{eq.chassi}</dd>
                  <dt>Renavam</dt>   <dd className="oi-mono" style={{ fontSize: 12 }}>{eq.renavam}</dd>
                  <dt>Hodômetro</dt> <dd className="oi-mono">{eq.km.toLocaleString("pt-BR")} km</dd>
                  {eq.placa2 && <><dt>Placa carroceria</dt><dd><PlacaE placa={eq.placa2} /></dd></>}
                  {eq.chassi2 && <><dt>Chassi carroceria</dt><dd className="oi-mono" style={{ fontSize: 12 }}>{eq.chassi2}</dd></>}
                </>
              ) : (
                <>
                  <dt>Nº de série</dt><dd className="oi-mono" style={{ fontSize: 12 }}>{eq.serie}</dd>
                  <dt>Horímetro</dt> <dd className="oi-mono">{(eq.horas || 0).toLocaleString("pt-BR")} h</dd>
                  {ME.isImpressora(eq.tipo) && (
                    <>
                      <dt>Impressão</dt><dd>{eq.impTipo || "—"}</dd>
                      <dt>Formato máx.</dt><dd>{eq.impFormato || "—"}</dd>
                      <dt>Tintas</dt><dd>{eq.impTintas || "—"}</dd>
                      {eq.impDpi && <><dt>Resolução</dt><dd className="oi-mono">{eq.impDpi}</dd></>}
                      {eq.impCap && <><dt>Capacidade</dt><dd>{eq.impCap}</dd></>}
                    </>
                  )}
                </>
              )}
              {eq.apelido && <><dt>Apelido</dt><dd>{eq.apelido}</dd></>}
            </dl>
          </div>
        </div>

        {/* Proprietário (vínculo) */}
        <div className="oi-section">
          <h3 className="oi-section-h">Proprietário</h3>
          {dono ? (
            <div className="oi-list card">
              <div className="oi-list-row" onClick={() => nav.push("cliente", { id: dono.id })} style={{ borderBottom: 0 }}>
                <span className={"oi-av " + dono.av} style={{ width: 40, height: 40, borderRadius: "50%", fontSize: 13 }}>
                  {dono.nome.split(" ").slice(0, 2).map(w => w[0]).join("")}
                </span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13.5, fontWeight: 600 }}>{dono.nome}</div>
                  <div className="oi-mono" style={{ fontSize: 11.5, color: "var(--text-mute)" }}>{dono.tipo} · {dono.doc}</div>
                </div>
                <IcE.chevR size={16} color="var(--text-mute)" />
              </div>
            </div>
          ) : (
            <div className="oi-card pad" style={{ alignItems: "center", color: "var(--text-mute)", padding: "16px", fontSize: 12.5 }}>Sem proprietário vinculado.</div>
          )}
        </div>

        {/* Histórico de manutenção */}
        <div className="oi-section">
          <h3 className="oi-section-h">Histórico de manutenção <span style={{ marginLeft: "auto", color: "var(--text-mute)", fontWeight: 500, letterSpacing: 0, textTransform: "none" }}>{hist.length}</span></h3>
          {hist.length === 0 ? (
            <div className="oi-card pad" style={{ alignItems: "center", color: "var(--text-mute)", padding: "20px 16px", gap: 6 }}>
              <IcE.wrench size={24} />
              <small style={{ fontSize: 12 }}>Nenhuma OS registrada para este equipamento</small>
            </div>
          ) : (
            <div className="oi-list card">
              {hist.map(o => {
                const due = mDueE(o);
                return (
                  <div key={o.id} className="oi-list-row" onClick={() => nav.push("manut-os", { id: o.id })}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                        <span className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)", fontWeight: 600 }}>{o.id}</span>
                        <span className={"oi-status " + stToneE(o.status)} style={{ fontSize: 10 }}><span className="dot" />{o.status}</span>
                      </div>
                      <div style={{ fontSize: 12.5, color: "var(--text-dim)", marginTop: 2 }}>{o.tipoManut} · {o.abertura}</div>
                    </div>
                    <div style={{ textAlign: "right" }}>
                      <div className="oi-money" style={{ fontSize: 13, fontWeight: 600 }}>{BRLe(ME.osTotal(o))}</div>
                      <div style={{ fontSize: 10.5, color: MTe[due.tone] }}>{due.label}</div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        <div style={{ height: 100 }} />
      </div>

      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8 }}>
        <button className="oi-btn action block" onClick={() => nav.push("nova-manut", { equip: eq })}>
          <IcE.wrench size={18} /> Abrir manutenção
        </button>
      </div>
    </>
  );
}

// ─── Cadastro / edição ───
function NovoEquipamentoScreen({ nav, ctx, params }) {
  const editing = params && params.id ? ME.EQUIPAMENTOS.find(e => e.id === params.id) : null;
  const [cat, setCat] = React.useState(editing ? editing.cat : "veiculo");
  const [f, setF] = React.useState(editing ? {
    tipo: editing.tipo, marca: editing.marca || "", modelo: editing.modelo || "",
    placa: editing.placa || "", chassi: editing.chassi || "", renavam: editing.renavam || "",
    placa2: editing.placa2 || "", chassi2: editing.chassi2 || "",
    ano: String(editing.ano || ""), cor: editing.cor || "", km: String(editing.km || ""),
    serie: editing.serie || "", horas: String(editing.horas || ""), apelido: editing.apelido || "", donoId: editing.donoId || null,
    producao: !!editing.producao, impTipo: editing.impTipo || ME.IMP_TIPOS[0], impFormato: editing.impFormato || "SRA3", impTintas: editing.impTintas || "CMYK", impDpi: editing.impDpi || "", impCap: editing.impCap || "",
  } : { tipo: ME.EQUIP_TIPOS_VEIC[0], marca: "", modelo: "", placa: "", chassi: "", renavam: "", placa2: "", chassi2: "", ano: "", cor: "", km: "", serie: "", horas: "", apelido: "", donoId: null, producao: true, impTipo: ME.IMP_TIPOS[0], impFormato: "SRA3", impTintas: "CMYK", impDpi: "", impCap: "" });
  const [pick, setPick] = React.useState(false);
  const [addTipo, setAddTipo] = React.useState(null);
  const set = (k, v) => setF(s => ({ ...s, [k]: v }));
  const dono = ME.equipDono({ donoId: f.donoId });
  const isImp = cat === "equipamento" && ME.isImpressora(f.tipo);
  const tipoList = cat === "veiculo" ? ME.EQUIP_TIPOS_VEIC : ME.EQUIP_TIPOS_EQUIP;
  const confirmAddTipo = () => {
    const t = (addTipo || "").trim();
    if (!t) return;
    const ref = cat === "veiculo" ? ME.EQUIP_TIPOS_VEIC : ME.EQUIP_TIPOS_EQUIP;
    if (!ref.includes(t)) ref.unshift(t);
    set("tipo", t); setAddTipo(null);
    if (window.oiToast) window.oiToast("Tipo \u201c" + t + "\u201d cadastrado");
  };

  const salvar = () => {
    if (editing) {
      const base = { cat, tipo: f.tipo, marca: f.marca || "—", modelo: f.modelo || "Modelo", ano: +f.ano || editing.ano, apelido: f.apelido, donoId: f.donoId };
      const dados = cat === "veiculo"
        ? { ...base, placa: (f.placa || "").toUpperCase(), chassi: f.chassi || "—", renavam: f.renavam || "—", placa2: (f.placa2 || "").toUpperCase(), chassi2: f.chassi2 || "", cor: f.cor || "—", km: +String(f.km).replace(/\D/g, "") || 0 }
        : { ...base, serie: f.serie || "—", horas: +String(f.horas).replace(/\D/g, "") || 0, ...(isImp ? { producao: f.producao, impTipo: f.impTipo, impFormato: f.impFormato, impTintas: f.impTintas, impDpi: f.impDpi, impCap: f.impCap, estacaoId: editing.estacaoId || ("imp-" + editing.id) } : { producao: false }) };
      Object.assign(editing, dados);
      nav.pop();
      return;
    }
    const novo = cat === "veiculo"
      ? { id: "EQ-" + Date.now().toString().slice(-4), cat, tipo: f.tipo, marca: f.marca || "—", modelo: f.modelo || "Modelo", placa: (f.placa || "").toUpperCase(), chassi: f.chassi || "—", renavam: f.renavam || "—", placa2: (f.placa2 || "").toUpperCase(), chassi2: f.chassi2 || "", ano: +f.ano || new Date().getFullYear(), cor: f.cor || "—", km: +String(f.km).replace(/\D/g, "") || 0, apelido: f.apelido, donoId: f.donoId }
      : { id: "EQ-" + Date.now().toString().slice(-4), cat, tipo: f.tipo, marca: f.marca || "—", modelo: f.modelo || "Modelo", serie: f.serie || "—", ano: +f.ano || new Date().getFullYear(), horas: +String(f.horas).replace(/\D/g, "") || 0, apelido: f.apelido, donoId: f.donoId, ...(isImp ? { producao: f.producao, impTipo: f.impTipo, impFormato: f.impFormato, impTintas: f.impTintas, impDpi: f.impDpi, impCap: f.impCap, estacaoId: "imp-" + Date.now().toString().slice(-5) } : {}) };
    ME.EQUIPAMENTOS.unshift(novo);
    nav.replace("equipamento", { id: novo.id });
  };
  const pode = f.modelo.trim() && (f.donoId || isImp);

  return (
    <>
      <DHe nav={nav} title={editing ? "Editar equipamento" : "Novo equipamento"} eyebrow={editing ? editing.id : "Cadastro de frota / máquina"} />
      <div className="oi-scroll">
        <div className="oi-section">
          <EqField label="Categoria">
            <div className="oi-seg">
              <button className={cat === "veiculo" ? "on" : ""} onClick={() => { setCat("veiculo"); set("tipo", ME.EQUIP_TIPOS_VEIC[0]); }}><IcE.truck size={15} /> Veículo</button>
              <button className={cat === "equipamento" ? "on" : ""} onClick={() => { setCat("equipamento"); set("tipo", "Impressora"); set("producao", true); }}><IcE.settings size={15} /> Equipamento</button>
            </div>
          </EqField>

          {/* Vínculo com pessoa — destaque */}
          <EqField label="Proprietário" req={!isImp} hint={isImp ? "Opcional para impressora própria (deixe vazio = próprio da empresa)" : "Vincule a uma pessoa já cadastrada (cliente, fornecedor…)"}>
            <button onClick={() => setPick(true)} className="oi-input" style={{ display: "flex", alignItems: "center", gap: 10, cursor: "pointer", textAlign: "left" }}>
              {dono ? (
                <>
                  <span className={"oi-av " + dono.av} style={{ width: 26, height: 26, borderRadius: "50%", fontSize: 10 }}>
                    {dono.nome.split(" ").slice(0, 2).map(w => w[0]).join("")}
                  </span>
                  <span style={{ flex: 1, fontWeight: 600 }}>{dono.nome}</span>
                </>
              ) : (
                <>
                  <IcE.user size={18} color="var(--text-mute)" />
                  <span style={{ flex: 1, color: "var(--text-mute)" }}>Selecionar pessoa…</span>
                </>
              )}
              <IcE.chevR size={16} color="var(--text-mute)" />
            </button>
          </EqField>

          <EqField label="Tipo" hint={isImp ? "Impressora — entra na Produção como estação" : null}>
            {addTipo === null ? (
              <select className="oi-select" value={f.tipo} onChange={e => { e.target.value === "__add" ? setAddTipo("") : set("tipo", e.target.value); }}>
                {tipoList.map(t => <option key={t}>{t}</option>)}
                {!tipoList.includes(f.tipo) && <option key={f.tipo}>{f.tipo}</option>}
                <option value="__add">+ Cadastrar novo tipo…</option>
              </select>
            ) : (
              <div style={{ display: "flex", gap: 8 }}>
                <input className="oi-input" autoFocus placeholder="Nome do novo tipo" value={addTipo}
                       onChange={e => setAddTipo(e.target.value)} onKeyDown={e => { if (e.key === "Enter") confirmAddTipo(); }} />
                <button className="oi-btn sm primary" onClick={confirmAddTipo} disabled={!addTipo.trim()} style={{ flex: "0 0 auto" }}>Add</button>
                <button className="oi-btn sm" onClick={() => setAddTipo(null)} style={{ flex: "0 0 auto" }}>Cancelar</button>
              </div>
            )}
          </EqField>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
            <EqField label="Marca"><input className="oi-input" placeholder="Volvo" value={f.marca} onChange={e => set("marca", e.target.value)} /></EqField>
            <EqField label="Modelo" req><input className="oi-input" placeholder="FH 540" value={f.modelo} onChange={e => set("modelo", e.target.value)} /></EqField>
          </div>

          {cat === "veiculo" ? (
            <>
              <EqField label="Placa">
                <input className="oi-input oi-mono" placeholder="ABC-1D23" value={f.placa} onChange={e => set("placa", e.target.value.toUpperCase())} style={{ textTransform: "uppercase", letterSpacing: ".08em" }} />
              </EqField>
              <EqField label="Chassi"><input className="oi-input oi-mono" placeholder="9BW..." value={f.chassi} onChange={e => set("chassi", e.target.value.toUpperCase())} /></EqField>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <EqField label="Renavam"><input className="oi-input oi-mono" placeholder="0000000000" value={f.renavam} onChange={e => set("renavam", e.target.value)} /></EqField>
                <EqField label="Hodômetro (km)"><input className="oi-input oi-mono" placeholder="0" value={f.km} onChange={e => set("km", e.target.value)} /></EqField>
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <EqField label="Ano"><input className="oi-input oi-mono" placeholder="2022" value={f.ano} onChange={e => set("ano", e.target.value)} /></EqField>
                <EqField label="Cor"><input className="oi-input" placeholder="Branco" value={f.cor} onChange={e => set("cor", e.target.value)} /></EqField>
              </div>
              <div style={{ borderTop: "1px solid var(--border-2)", margin: "2px 0 14px", paddingTop: 12 }}>
                <div style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--text-mute)", marginBottom: 4 }}>Carroceria / reboque</div>
                <div style={{ fontSize: 11, color: "var(--text-mute)", marginBottom: 10 }}>Para caminhão com carroceria ou implemento emplacado à parte (opcional).</div>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                  <EqField label="Placa 2"><input className="oi-input oi-mono" placeholder="ABC-1D24" value={f.placa2} onChange={e => set("placa2", e.target.value.toUpperCase())} style={{ textTransform: "uppercase", letterSpacing: ".08em" }} /></EqField>
                  <EqField label="Chassi 2"><input className="oi-input oi-mono" placeholder="9BW..." value={f.chassi2} onChange={e => set("chassi2", e.target.value.toUpperCase())} /></EqField>
                </div>
              </div>
            </>
          ) : (
            <>
              <EqField label="Nº de série"><input className="oi-input oi-mono" placeholder="STC-..." value={f.serie} onChange={e => set("serie", e.target.value.toUpperCase())} /></EqField>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <EqField label="Ano"><input className="oi-input oi-mono" placeholder="2020" value={f.ano} onChange={e => set("ano", e.target.value)} /></EqField>
                <EqField label="Horímetro (h)"><input className="oi-input oi-mono" placeholder="0" value={f.horas} onChange={e => set("horas", e.target.value)} /></EqField>
              </div>
              {isImp && (
                <div style={{ borderTop: "1px solid var(--border-2)", margin: "2px 0 14px", paddingTop: 12 }}>
                  <div style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", color: "var(--origin-OFI-fg)", marginBottom: 4, display: "flex", alignItems: "center", gap: 6 }}>
                    <IcE.printer size={13} /> Impressora · produção
                  </div>
                  <div style={{ fontSize: 11, color: "var(--text-mute)", marginBottom: 10 }}>Campos exclusivos da máquina de impressão. Quando ativa, aparece como estação na Produção.</div>
                  <EqField label="Tipo de impressão">
                    <select className="oi-select" value={f.impTipo} onChange={e => set("impTipo", e.target.value)}>
                      {ME.IMP_TIPOS.map(t => <option key={t}>{t}</option>)}
                    </select>
                  </EqField>
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                    <EqField label="Formato máximo">
                      <select className="oi-select" value={f.impFormato} onChange={e => set("impFormato", e.target.value)}>
                        {ME.IMP_FORMATOS.map(t => <option key={t}>{t}</option>)}
                      </select>
                    </EqField>
                    <EqField label="Tintas / cores">
                      <select className="oi-select" value={f.impTintas} onChange={e => set("impTintas", e.target.value)}>
                        {ME.IMP_TINTAS.map(t => <option key={t}>{t}</option>)}
                      </select>
                    </EqField>
                  </div>
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                    <EqField label="Resolução"><input className="oi-input oi-mono" placeholder="1440 dpi" value={f.impDpi} onChange={e => set("impDpi", e.target.value)} /></EqField>
                    <EqField label="Capacidade"><input className="oi-input" placeholder="12 m²/h" value={f.impCap} onChange={e => set("impCap", e.target.value)} /></EqField>
                  </div>
                  <button onClick={() => set("producao", !f.producao)} className="oi-input" style={{ display: "flex", alignItems: "center", gap: 10, cursor: "pointer", width: "100%" }}>
                    <span style={{ width: 38, height: 22, borderRadius: 99, background: f.producao ? "var(--accent)" : "var(--bg-2)", border: "1px solid var(--border)", position: "relative", flex: "0 0 auto", transition: ".15s" }}>
                      <span style={{ position: "absolute", top: 1, left: f.producao ? 17 : 1, width: 18, height: 18, borderRadius: 99, background: "#fff", transition: ".15s" }} />
                    </span>
                    <span style={{ flex: 1, fontSize: 13, textAlign: "left" }}>Disponibilizar na Produção como estação</span>
                  </button>
                </div>
              )}
            </>
          )}
          <EqField label="Apelido / identificação interna" hint="Opcional — ex: Frota 12, Gerador da loja">
            <input className="oi-input" placeholder="Frota 12" value={f.apelido} onChange={e => set("apelido", e.target.value)} />
          </EqField>
        </div>
        <div style={{ height: 100 }} />
      </div>

      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8 }}>
        <button className="oi-btn action block" onClick={salvar} disabled={!pode} style={{ opacity: pode ? 1 : 0.5 }}>
          <IcE.check size={18} /> {editing ? "Salvar alterações" : "Salvar equipamento"}
        </button>
      </div>

      {pick && <PessoaPickerSheet onClose={() => setPick(false)} onPick={(id) => { set("donoId", id); setPick(false); }} />}
    </>
  );
}

// ─── Sheet: escolher pessoa cadastrada ───
function PessoaPickerSheet({ onClose, onPick }) {
  const [q, setQ] = React.useState("");
  const list = (ME.CLIENTES || []).filter(c => !q || (c.nome + " " + c.doc).toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="oi-sheet-backdrop" onClick={onClose}>
      <div className="oi-sheet" onClick={e => e.stopPropagation()}>
        <div className="oi-sheet-grip" />
        <div className="oi-sheet-h"><b>Vincular pessoa</b><button className="oi-iconbtn close" onClick={onClose}><IcE.x /></button></div>
        <div style={{ padding: "12px 16px 8px" }}>
          <div className="oi-search"><IcE.search size={16} /><input placeholder="Nome ou documento" value={q} onChange={e => setQ(e.target.value)} /></div>
        </div>
        <div className="oi-list" style={{ borderTop: 0, borderBottom: 0 }}>
          {list.map(c => (
            <div key={c.id} className="oi-list-row" onClick={() => onPick(c.id)}>
              <span className={"oi-av " + c.av} style={{ width: 36, height: 36, borderRadius: "50%", fontSize: 12 }}>
                {c.nome.split(" ").slice(0, 2).map(w => w[0]).join("")}
              </span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600 }}>{c.nome}</div>
                <div className="oi-mono" style={{ fontSize: 11, color: "var(--text-mute)" }}>{c.tipo} · {c.doc}</div>
              </div>
              {window.Screens.PapelBadges && <window.Screens.PapelBadges papeis={c.papeis} />}
            </div>
          ))}
        </div>
        <div style={{ height: 12 }} />
      </div>
    </div>
  );
}

window.Screens = window.Screens || {};
Object.assign(window.Screens, { EquipamentosScreen, EquipamentoDetalheScreen, NovoEquipamentoScreen });
