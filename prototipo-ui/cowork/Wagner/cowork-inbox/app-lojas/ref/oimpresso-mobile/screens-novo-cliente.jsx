// Novo Cliente — cadastro rápido em wizard de etapas
// Adaptado da proposta FSM (pouca digitação, busca CNPJ/CEP, atalhos scan/voz)
// para os tokens e padrões do Oimpresso.
const { Ic } = window;
const { DetailHeader } = window.Screens;
const BRL = window.BRL;

function Field({ label, req, children, hint }) {
  return (
    <div className="oi-field">
      {label && <label>{label}{req && <span className="req">*</span>}</label>}
      {children}
      {hint && <div className="oi-hint"><Ic.zap size={13} />{hint}</div>}
    </div>
  );
}

function CheckRow({ on, onClick, children }) {
  return (
    <div className={"oi-checkrow" + (on ? " on" : "")} onClick={onClick}>
      <div className="box">{on && <Ic.check size={14} color="#fff" strokeWidth={3} />}</div>
      <div className="lbl">{children}</div>
    </div>
  );
}

const STEPS = [
  { id: "dados",     label: "Dados" },
  { id: "contato",   label: "Contato" },
  { id: "endereco",  label: "Endereço" },
  { id: "comercial", label: "Comercial" },
  { id: "lgpd",      label: "LGPD" },
];

function NovoClienteScreen({ nav, ctx, params }) {
  const editing = !!(params && params.id);
  const base = editing ? window.MOCK.fullCliente(window.MOCK.CLIENTES.find(x => x.id === params.id)) : null;
  const [step, setStep] = React.useState((params && params.startStep) || 0);
  const [done, setDone] = React.useState(false);
  const [maxReached, setMaxReached] = React.useState(editing ? STEPS.length - 1 : 0);
  const [f, setF] = React.useState(base || {
    papeis: ["cliente"],
    tipo: "pf",
    nome: "", fantasia: "", doc: "", contribuinte: "Não contribuinte",
    produtorRural: false, ie: "",
    whats: "", tel2: "", email: "", emailNfe: "",
    cep: "", logradouro: "", numero: "", compl: "", bairro: "", cidade: "", uf: "",
    vendedor: "", pagamento: "", limite: "", origem: "",
    lgpdWhats: true, lgpdNfe: true, lgpdMkt: false,
  });
  const set = (k, v) => setF(s => ({ ...s, [k]: v }));
  const [searching, setSearching] = React.useState(null); // 'doc' | 'cep'

  const go = (i) => { setStep(i); setMaxReached(m => Math.max(m, i)); };
  const next = () => { if (step < STEPS.length - 1) go(step + 1); else setDone(true); };
  const prev = () => { if (step > 0) setStep(step - 1); };

  // Simulated lookups
  const buscarDoc = () => {
    setSearching("doc");
    setTimeout(() => {
      if (f.tipo === "pj") {
        setF(s => ({ ...s, nome: "Gráfica Alfa Comunicação LTDA", fantasia: "Alfa Visual",
                     contribuinte: "Contribuinte ICMS", ie: "251.998.220.114" }));
      } else {
        setF(s => ({ ...s, nome: "João Pedro Silva" }));
      }
      setSearching(null);
    }, 900);
  };
  const buscarCep = () => {
    setSearching("cep");
    setTimeout(() => {
      setF(s => ({ ...s, logradouro: "Av. Brigadeiro Faria Lima", bairro: "Itaim Bibi",
                   cidade: "São Paulo", uf: "SP" }));
      setSearching(null);
    }, 900);
  };

  if (done) {
    const ini = (f.nome || "Novo Cliente").split(" ").slice(0, 2).map(w => w[0]).join("").toUpperCase();
    return (
      <>
        <DetailHeader nav={nav} title={editing ? "Cliente atualizado" : "Cliente cadastrado"} />
        <div className="oi-scroll">
          <div className="oi-section" style={{ paddingTop: 28, textAlign: "center" }}>
            <div style={{ width: 76, height: 76, borderRadius: 20, background: "color-mix(in oklch, var(--ok) 22%, transparent)",
                          color: "var(--ok)", display: "grid", placeItems: "center", margin: "0 auto 14px" }}>
              <Ic.checkCircle size={38} />
            </div>
            <div style={{ fontSize: 22, fontWeight: 600, letterSpacing: "-0.02em" }}>
              {editing ? "Alterações salvas!" : "Cliente salvo!"}
            </div>
            <div style={{ fontSize: 13.5, color: "var(--text-dim)", marginTop: 4 }}>{f.nome || "Sem nome"}</div>
            <div style={{ marginTop: 8, display: "flex", flexDirection: "column", alignItems: "center", gap: 6 }}>
              <span className="oi-status accent"><span className="dot" />{f.tipo === "pj" ? "Pessoa Jurídica" : "Pessoa Física"}</span>
              {window.Screens.PapelBadges && <window.Screens.PapelBadges papeis={f.papeis} size="lg" />}
            </div>
          </div>
          <div className="oi-section">
            {editing ? (
              <>
                <button className="oi-btn block primary" onClick={() => nav.pop()}>
                  Voltar para a ficha
                </button>
                <button className="oi-btn block ghost" style={{ marginTop: 8 }} onClick={() => nav.pop()}>
                  Fechar
                </button>
              </>
            ) : (
              <>
                <button className="oi-btn block primary" onClick={() => nav.push("novo-pedido")}>
                  <Ic.plus size={18} /> Criar primeiro pedido
                </button>
                <button className="oi-btn block" style={{ marginTop: 8 }} onClick={() => nav.pop()}>
                  Ver ficha do cliente
                </button>
                <button className="oi-btn block ghost" style={{ marginTop: 8 }} onClick={() => nav.pop()}>
                  Fechar
                </button>
              </>
            )}
          </div>
        </div>
      </>
    );
  }

  return (
    <>
      <DetailHeader nav={nav} title={editing ? "Editar cliente" : "Novo cliente"}
                    eyebrow={editing ? f.nome : "Cadastro rápido"} />

      {/* Step chips */}
      <div className="oi-steps">
        {STEPS.map((s, i) => (
          <button key={s.id}
                  className={"oi-step" + (i === step ? " active" : (i < maxReached || i < step ? " done" : ""))}
                  onClick={() => go(i)}>
            <span className="num">{(i < step || i < maxReached) && i !== step ? <Ic.check size={11} color="#fff" strokeWidth={3} /> : i + 1}</span>
            {s.label}
          </button>
        ))}
      </div>

      <div className="oi-scroll">
        {/* STEP 0 — DADOS */}
        {step === 0 && (
          <>
            <div className="oi-section">
              <Field label="Classificação" hint="Uma pessoa pode ter mais de um papel (ex: cliente que também é fornecedor)">
                <div style={{ display: "flex", flexWrap: "wrap", gap: 6 }}>
                  {window.MOCK.PAPEIS.map(p => {
                    const on = (f.papeis || []).includes(p.id);
                    return (
                      <button key={p.id}
                              onClick={() => set("papeis", on ? f.papeis.filter(x => x !== p.id) : [...(f.papeis || []), p.id])}
                              style={{
                                appearance: "none", cursor: "pointer",
                                display: "inline-flex", alignItems: "center", gap: 6,
                                padding: "8px 12px", borderRadius: "var(--radius-pill)",
                                border: "1px solid " + (on ? p.color : "var(--border)"),
                                background: on ? "color-mix(in oklch, " + p.color + " 18%, transparent)" : "var(--bg-2)",
                                color: on ? p.color : "var(--text-dim)",
                                font: "inherit", fontSize: 13, fontWeight: 600,
                              }}>
                        {on ? <Ic.check size={14} /> : <Ic.plus size={14} />}
                        {p.label}
                      </button>
                    );
                  })}
                </div>
              </Field>
            </div>

            <div className="oi-section">
              <Field label="Tipo de pessoa">
                <div className="oi-seg">
                  <button className={f.tipo === "pf" ? "on" : ""} onClick={() => set("tipo", "pf")}>
                    <Ic.user size={16} /> Pessoa Física
                  </button>
                  <button className={f.tipo === "pj" ? "on" : ""} onClick={() => set("tipo", "pj")}>
                    <Ic.shield size={16} /> Pessoa Jurídica
                  </button>
                </div>
              </Field>
            </div>

            <div className="oi-section">
              <h3 className="oi-section-h">Dados principais</h3>
              <div className="oi-card pad">
                <Field label={f.tipo === "pj" ? "Razão social" : "Nome completo"} req>
                  <input className="oi-input" value={f.nome} onChange={e => set("nome", e.target.value)}
                         placeholder={f.tipo === "pj" ? "Gráfica Alfa LTDA" : "João Silva"} />
                </Field>
                {f.tipo === "pj" && (
                  <Field label="Nome fantasia">
                    <input className="oi-input" value={f.fantasia} onChange={e => set("fantasia", e.target.value)}
                           placeholder="Alfa Comunicação Visual" />
                  </Field>
                )}
                <Field label={f.tipo === "pj" ? "CNPJ" : "CPF"} req
                       hint="Consulta automática via Receita Federal">
                  <div style={{ display: "flex", gap: 8 }}>
                    <input className="oi-input" style={{ flex: 1 }} value={f.doc}
                           onChange={e => set("doc", e.target.value)}
                           placeholder={f.tipo === "pj" ? "00.000.000/0001-00" : "000.000.000-00"} />
                    <button className="oi-btn action" style={{ flex: "0 0 auto", padding: "0 16px", height: 48 }}
                            onClick={buscarDoc} disabled={searching === "doc"}>
                      {searching === "doc" ? <Ic.refresh size={16} /> : <><Ic.search size={16} /> Buscar</>}
                    </button>
                  </div>
                </Field>
                <Field label="Tipo de contribuinte" req>
                  <select className="oi-select" value={f.contribuinte} onChange={e => set("contribuinte", e.target.value)}>
                    <option>Contribuinte ICMS</option>
                    <option>Não contribuinte</option>
                    <option>Isento</option>
                  </select>
                </Field>
                {f.tipo === "pf" && (
                  <Field>
                    <CheckRow on={f.produtorRural} onClick={() => set("produtorRural", !f.produtorRural)}>
                      Produtor rural com inscrição estadual
                    </CheckRow>
                  </Field>
                )}
                {(f.tipo === "pj" || f.produtorRural) && (
                  <Field label="Inscrição estadual">
                    <input className="oi-input" value={f.ie} onChange={e => set("ie", e.target.value)}
                           placeholder="Digite a inscrição estadual" />
                  </Field>
                )}
              </div>
            </div>

            <div className="oi-section">
              <div style={{ display: "flex", gap: 8 }}>
                <button className="oi-btn" style={{ flex: 1 }} onClick={() => alert("Abrir câmera (OCR do documento)")}>
                  <Ic.scan size={16} /> Escanear doc.
                </button>
                <button className="oi-btn" style={{ flex: 1 }} onClick={() => alert("Ditado por voz")}>
                  <Ic.zap size={16} /> Voz → texto
                </button>
              </div>
            </div>
          </>
        )}

        {/* STEP 1 — CONTATO */}
        {step === 1 && (
          <div className="oi-section">
            <h3 className="oi-section-h">Contato</h3>
            <div className="oi-card pad">
              <Field label="WhatsApp" req>
                <input className="oi-input" value={f.whats} onChange={e => set("whats", e.target.value)}
                       placeholder="(11) 99999-9999" inputMode="tel" />
              </Field>
              <Field label="Telefone secundário">
                <input className="oi-input" value={f.tel2} onChange={e => set("tel2", e.target.value)}
                       placeholder="(11) 3000-0000" inputMode="tel" />
              </Field>
              <Field label="E-mail" req>
                <input className="oi-input" value={f.email} onChange={e => set("email", e.target.value)}
                       placeholder="cliente@email.com" inputMode="email" />
              </Field>
              <Field label="E-mail para NF-e" hint="Recebe as notas fiscais automaticamente">
                <input className="oi-input" value={f.emailNfe} onChange={e => set("emailNfe", e.target.value)}
                       placeholder="financeiro@empresa.com" inputMode="email" />
              </Field>
            </div>
          </div>
        )}

        {/* STEP 2 — ENDEREÇO */}
        {step === 2 && (
          <div className="oi-section">
            <h3 className="oi-section-h">Endereço fiscal</h3>
            <div className="oi-card pad">
              <Field label="CEP" req hint="Preenche logradouro, bairro, cidade e UF">
                <div style={{ display: "flex", gap: 8 }}>
                  <input className="oi-input" style={{ flex: 1 }} value={f.cep} onChange={e => set("cep", e.target.value)}
                         placeholder="00000-000" inputMode="numeric" />
                  <button className="oi-btn action" style={{ flex: "0 0 auto", padding: "0 16px", height: 48 }}
                          onClick={buscarCep} disabled={searching === "cep"}>
                    {searching === "cep" ? <Ic.refresh size={16} /> : <><Ic.search size={16} /> Buscar</>}
                  </button>
                </div>
              </Field>
              <Field label="Logradouro" req>
                <input className="oi-input" value={f.logradouro} onChange={e => set("logradouro", e.target.value)} placeholder="Rua, avenida…" />
              </Field>
              <div style={{ display: "flex", gap: 10 }}>
                <div style={{ flex: "0 0 110px" }}>
                  <Field label="Número" req>
                    <input className="oi-input" value={f.numero} onChange={e => set("numero", e.target.value)} placeholder="123" inputMode="numeric" />
                  </Field>
                </div>
                <div style={{ flex: 1 }}>
                  <Field label="Complemento">
                    <input className="oi-input" value={f.compl} onChange={e => set("compl", e.target.value)} placeholder="Sala, bloco…" />
                  </Field>
                </div>
              </div>
              <Field label="Bairro" req>
                <input className="oi-input" value={f.bairro} onChange={e => set("bairro", e.target.value)} placeholder="Centro" />
              </Field>
              <div style={{ display: "flex", gap: 10 }}>
                <div style={{ flex: 1 }}>
                  <Field label="Cidade" req>
                    <input className="oi-input" value={f.cidade} onChange={e => set("cidade", e.target.value)} placeholder="São Paulo" />
                  </Field>
                </div>
                <div style={{ flex: "0 0 90px" }}>
                  <Field label="UF" req>
                    <input className="oi-input" value={f.uf} onChange={e => set("uf", e.target.value)} placeholder="SP" maxLength={2} />
                  </Field>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* STEP 3 — COMERCIAL */}
        {step === 3 && (
          <div className="oi-section">
            <h3 className="oi-section-h">Configurações comerciais</h3>
            <div className="oi-card pad">
              <Field label="Vendedor responsável">
                <select className="oi-select" value={f.vendedor} onChange={e => set("vendedor", e.target.value)}>
                  <option value="">Selecionar vendedor</option>
                  <option>Wagner Rocha</option>
                  <option>Lucas Andrade</option>
                  <option>Daniela Souza</option>
                </select>
              </Field>
              <Field label="Forma de pagamento padrão">
                <select className="oi-select" value={f.pagamento} onChange={e => set("pagamento", e.target.value)}>
                  <option value="">Selecionar</option>
                  <option>PIX</option>
                  <option>Boleto 7 dias</option>
                  <option>Cartão de crédito</option>
                  <option>Dinheiro</option>
                </select>
              </Field>
              <Field label="Limite de crédito">
                <input className="oi-input oi-money" value={f.limite} onChange={e => set("limite", e.target.value)}
                       placeholder="R$ 0,00" inputMode="numeric" />
              </Field>
              <Field label="Origem do cliente">
                <select className="oi-select" value={f.origem} onChange={e => set("origem", e.target.value)}>
                  <option value="">Como chegou até nós?</option>
                  <option>Indicação</option>
                  <option>Instagram</option>
                  <option>Google</option>
                  <option>Cliente antigo</option>
                  <option>Loja física</option>
                </select>
              </Field>
            </div>
          </div>
        )}

        {/* STEP 4 — LGPD */}
        {step === 4 && (
          <div className="oi-section">
            <h3 className="oi-section-h">Consentimento (LGPD)</h3>
            <div className="oi-card pad" style={{ gap: 10 }}>
              <CheckRow on={f.lgpdWhats} onClick={() => set("lgpdWhats", !f.lgpdWhats)}>
                Autoriza contato e envio de orçamentos via WhatsApp
              </CheckRow>
              <CheckRow on={f.lgpdNfe} onClick={() => set("lgpdNfe", !f.lgpdNfe)}>
                Autoriza envio de NF-e por e-mail
              </CheckRow>
              <CheckRow on={f.lgpdMkt} onClick={() => set("lgpdMkt", !f.lgpdMkt)}>
                Autoriza comunicações de marketing e promoções
              </CheckRow>
              <div className="oi-hint" style={{ background: "var(--bg-2)", color: "var(--text-dim)" }}>
                <Ic.shield size={13} />
                Data, IP e termo de consentimento são registrados automaticamente no salvamento.
              </div>
            </div>
          </div>
        )}

        <div style={{ height: 100 }} />
      </div>

      {/* Bottom bar — guided */}
      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)",
                    display: "flex", gap: 8, alignItems: "center" }}>
        {step > 0 ? (
          <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 16px" }} onClick={prev}>
            <Ic.chevL size={16} /> Voltar
          </button>
        ) : (
          <button className="oi-btn ghost" style={{ flex: "0 0 auto", padding: "0 16px" }} onClick={() => nav.pop()}>
            Cancelar
          </button>
        )}
        <button className="oi-btn primary" style={{ flex: 1 }} onClick={next}>
          {step < STEPS.length - 1
            ? (<>Avançar <Ic.chevR size={16} /></>)
            : (<><Ic.check size={18} /> {editing ? "Salvar alterações" : "Salvar cliente"}</>)}
        </button>
      </div>
    </>
  );
}

// ────────────────────────────────────────────────
// FICHA CADASTRAL — visualização completa (read-only) com editar por seção
// ────────────────────────────────────────────────
function ClienteDadosScreen({ nav, ctx, params }) {
  const c = window.MOCK.CLIENTES.find(x => x.id === params.id) || window.MOCK.CLIENTES[0];
  const d = window.MOCK.fullCliente(c);

  const SectionHead = ({ title, stepIdx }) => (
    <h3 className="oi-section-h">
      {title}
      <a className="more" onClick={() => nav.push("editar-cliente", { id: c.id, startStep: stepIdx })}>
        Editar
      </a>
    </h3>
  );

  const Row = ({ k, v, mono }) => (
    <>
      <dt>{k}</dt>
      <dd className={mono ? "oi-mono" : ""} style={{ wordBreak: "break-word" }}>{v || "—"}</dd>
    </>
  );

  return (
    <>
      <DetailHeader nav={nav} title="Dados cadastrais" eyebrow={c.nome}
                    actions={<button className="oi-iconbtn" onClick={() => nav.push("editar-cliente", { id: c.id })}>
                      <Ic.edit size={20} />
                    </button>} />
      <div className="oi-scroll">
        {/* Identificação */}
        <div className="oi-section">
          <SectionHead title="Identificação" stepIdx={0} />
          <div className="oi-card pad">
            <dl className="oi-dl">
              <Row k="Classificação" v={(d.papeis || []).map(id => (window.MOCK.PAPEIS.find(p => p.id === id) || {}).label).filter(Boolean).join(" · ")} />
              <Row k="Tipo" v={d.tipo === "pj" ? "Pessoa Jurídica" : "Pessoa Física"} />
              <Row k={d.tipo === "pj" ? "Razão social" : "Nome"} v={d.nome} />
              {d.tipo === "pj" && <Row k="Fantasia" v={d.fantasia} />}
              <Row k={d.tipo === "pj" ? "CNPJ" : "CPF"} v={d.doc} mono />
              <Row k="Contribuinte" v={d.contribuinte} />
              {(d.tipo === "pj" || d.produtorRural) && <Row k="Insc. estadual" v={d.ie} mono />}
              <Row k="Cliente desde" v={d.desde} />
            </dl>
          </div>
        </div>

        {/* Contato */}
        <div className="oi-section">
          <SectionHead title="Contato" stepIdx={1} />
          <div className="oi-card pad">
            <dl className="oi-dl">
              <Row k="WhatsApp" v={d.whats} mono />
              <Row k="Tel. 2" v={d.tel2} mono />
              <Row k="E-mail" v={d.email} />
              <Row k="E-mail NF-e" v={d.emailNfe} />
            </dl>
          </div>
        </div>

        {/* Endereço fiscal */}
        <div className="oi-section">
          <SectionHead title="Endereço fiscal" stepIdx={2} />
          <div className="oi-card pad">
            <dl className="oi-dl">
              <Row k="CEP" v={d.cep} mono />
              <Row k="Logradouro" v={d.logradouro + (d.numero ? ", " + d.numero : "")} />
              {d.compl && <Row k="Compl." v={d.compl} />}
              <Row k="Bairro" v={d.bairro} />
              <Row k="Cidade/UF" v={d.cidade + " · " + d.uf} />
            </dl>
          </div>
        </div>

        {/* Comercial */}
        <div className="oi-section">
          <SectionHead title="Comercial" stepIdx={3} />
          <div className="oi-card pad">
            <dl className="oi-dl">
              <Row k="Vendedor" v={d.vendedor} />
              <Row k="Pagamento" v={d.pagamento} />
              <Row k="Limite" v={d.limite} mono />
              <Row k="Origem" v={d.origem} />
            </dl>
          </div>
        </div>

        {/* LGPD */}
        <div className="oi-section">
          <SectionHead title="Consentimento (LGPD)" stepIdx={4} />
          <div className="oi-card pad" style={{ gap: 8 }}>
            {[
              { l: "Contato via WhatsApp", on: d.lgpdWhats },
              { l: "Envio de NF-e por e-mail", on: d.lgpdNfe },
              { l: "Comunicações de marketing", on: d.lgpdMkt },
            ].map((x, i) => (
              <div key={i} style={{ display: "flex", alignItems: "center", gap: 10, fontSize: 13 }}>
                <div style={{ color: x.on ? "var(--ok)" : "var(--text-mute)" }}>
                  {x.on ? <Ic.checkCircle size={18} /> : <Ic.x size={18} />}
                </div>
                <span style={{ flex: 1, color: x.on ? "var(--text)" : "var(--text-mute)" }}>{x.l}</span>
                <span style={{ fontSize: 11, color: x.on ? "var(--ok)" : "var(--text-mute)", fontWeight: 600 }}>
                  {x.on ? "Autorizado" : "Não"}
                </span>
              </div>
            ))}
            <div className="oi-hint" style={{ background: "var(--bg-2)", color: "var(--text-dim)" }}>
              <Ic.shield size={13} /> Consentimento registrado em {d.desde} · IP 187.45.x.x
            </div>
          </div>
        </div>

        <div style={{ height: 100 }} />
      </div>

      <div style={{ padding: 12, background: "var(--surface)", borderTop: "1px solid var(--border)", display: "flex", gap: 8 }}>
        <button className="oi-btn primary block" onClick={() => nav.push("editar-cliente", { id: c.id })}>
          <Ic.edit size={18} /> Editar cadastro
        </button>
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
window.Screens.NovoClienteScreen = NovoClienteScreen;
window.Screens.ClienteDadosScreen = ClienteDadosScreen;
