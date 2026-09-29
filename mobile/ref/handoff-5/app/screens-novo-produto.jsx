// Novo Produto — cadastro em wizard, mesmo padrão de Pessoas
const { Ic } = window;
const { DetailHeader } = window.OIUi;
const BRL = window.BRL;

function PField({ label, req, children, hint }) {
  return (
    <div className="oi-field">
      {label && <label>{label}{req && <span className="req">*</span>}</label>}
      {children}
      {hint && <div className="oi-hint"><Ic.zap size={13} />{hint}</div>}
    </div>
  );
}

function PCheckRow({ on, onClick, children }) {
  return (
    <div className={"oi-checkrow" + (on ? " on" : "")} onClick={onClick}>
      <div className="box">{on && <Ic.check size={14} color="#fff" strokeWidth={3} />}</div>
      <div className="lbl">{children}</div>
    </div>
  );
}

const PSTEPS = [
  { id: "dados",   label: "Dados" },
  { id: "precos",  label: "Preços" },
  { id: "estoque", label: "Estoque" },
  { id: "fiscal",  label: "Fiscal" },
  { id: "ficha",   label: "Ficha técnica" },
];

function NovoProdutoScreen({ nav, ctx, params }) {
  const editing = !!(params && params.id);
  const base = editing ? window.MOCK.fullProduto(window.MOCK.PRODUTOS.find(x => x.id === params.id)) : null;
  const [step, setStep] = React.useState((params && params.startStep) || 0);
  const [done, setDone] = React.useState(false);
  const [maxReached, setMaxReached] = React.useState(editing ? PSTEPS.length - 1 : 0);
  const [f, setF] = React.useState(base || {
    nome: "", sku: "", barras: "", cat: "", tipo: "produto", un: "unid",
    precoStr: "", custoStr: "", promo: "",
    controlaEstoque: true, estoque: "", min: "", local: "", fornecedor: "",
    ncm: "", cfop: "5101", origem: "0 - Nacional", cest: "",
    descricao: "", gramatura: "", acabamento: "",
    img: "box",
  });
  const set = (k, v) => setF(s => ({ ...s, [k]: v }));
  const [scanning, setScanning] = React.useState(false);

  const go = (i) => { setStep(i); setMaxReached(m => Math.max(m, i)); };
  const next = () => { if (step < PSTEPS.length - 1) go(step + 1); else setDone(true); };
  const prev = () => { if (step > 0) setStep(step - 1); };

  // Gera SKU a partir do nome
  const gerarSku = () => {
    const base = (f.nome || "PROD").toUpperCase().replace(/[^A-Z0-9 ]/g, "").split(" ").filter(Boolean).slice(0, 2).map(w => w.slice(0, 3)).join("-");
    set("sku", base + "-" + Math.floor(Math.random() * 90 + 10));
  };
  const bipar = () => {
    setScanning(true);
    setTimeout(() => { set("barras", "789" + Math.floor(Math.random() * 9e9 + 1e9)); setScanning(false); }, 900);
  };

  // Margem calculada
  const parseMoney = (s) => parseFloat(String(s).replace(/[^\d,.-]/g, "").replace(".", "").replace(",", ".")) || 0;
  const preco = parseMoney(f.precoStr), custo = parseMoney(f.custoStr);
  const margem = preco > 0 && custo > 0 ? ((preco - custo) / preco * 100) : null;

  if (done) {
    return (
      <>
        <DetailHeader nav={nav} title={editing ? "Produto atualizado" : "Produto cadastrado"} />
        <div className="oi-scroll">
          <div className="oi-section" style={{ paddingTop: 28, textAlign: "center" }}>
            <div style={{ width: 76, height: 76, borderRadius: 20, background: "color-mix(in oklch, var(--ok) 22%, transparent)",
                          color: "var(--ok)", display: "grid", placeItems: "center", margin: "0 auto 14px" }}>
              <Ic.checkCircle size={38} />
            </div>
            <div style={{ fontSize: 22, fontWeight: 600, letterSpacing: "-0.02em" }}>
              {editing ? "Alterações salvas!" : "Produto salvo!"}
            </div>
            <div style={{ fontSize: 13.5, color: "var(--text-dim)", marginTop: 4 }}>{f.nome || "Sem nome"}</div>
            <div style={{ marginTop: 8, display: "flex", justifyContent: "center", gap: 6 }}>
              <span className="oi-status accent"><span className="dot" />{f.precoStr || BRL(preco)}</span>
              {f.sku && <span className="oi-status"><span className="oi-mono">{f.sku}</span></span>}
            </div>
          </div>
          <div className="oi-section">
            {editing ? (
              <button className="oi-btn block primary" onClick={() => nav.pop()}>Voltar ao produto</button>
            ) : (
              <>
                <button className="oi-btn block primary" onClick={() => { setF(b => ({ ...b })); setDone(false); setStep(0); }}>
                  <Ic.plus size={18} /> Cadastrar outro
                </button>
                <button className="oi-btn block" style={{ marginTop: 8 }} onClick={() => nav.pop()}>
                  Ver no catálogo
                </button>
              </>
            )}
            <button className="oi-btn block ghost" style={{ marginTop: 8 }} onClick={() => nav.pop()}>Fechar</button>
          </div>
        </div>
      </>
    );
  }

  return (
    <>
      <DetailHeader nav={nav} title={editing ? "Editar produto" : "Novo produto"}
                    eyebrow={editing ? f.nome : "Cadastro de produto"} />

      <div className="oi-steps">
        {PSTEPS.map((s, i) => (
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
              <PField label="Tipo">
                <div className="oi-seg">
                  <button className={f.tipo === "produto" ? "on" : ""} onClick={() => set("tipo", "produto")}>
                    <Ic.box size={16} /> Produto
                  </button>
                  <button className={f.tipo === "servico" ? "on" : ""} onClick={() => set("tipo", "servico")}>
                    <Ic.zap size={16} /> Serviço
                  </button>
                  <button className={f.tipo === "insumo" ? "on" : ""} onClick={() => set("tipo", "insumo")}>
                    <Ic.layers size={16} /> Insumo
                  </button>
                </div>
              </PField>
            </div>

            <div className="oi-section">
              <h3 className="oi-section-h">Identificação</h3>
              <div className="oi-card pad">
                <PField label="Nome do produto" req>
                  <input className="oi-input" value={f.nome} onChange={e => set("nome", e.target.value)}
                         placeholder="Ex: Cartão de visita 9x5 4/4" />
                </PField>
                <PField label="SKU / Código interno" req>
                  <div style={{ display: "flex", gap: 8 }}>
                    <input className="oi-input oi-mono" style={{ flex: 1 }} value={f.sku}
                           onChange={e => set("sku", e.target.value)} placeholder="CV-9X5-44" />
                    <button className="oi-btn" style={{ flex: "0 0 auto", padding: "0 14px", height: 48 }} onClick={gerarSku}>
                      <Ic.zap size={16} /> Gerar
                    </button>
                  </div>
                </PField>
                <PField label="Código de barras" hint="Use a câmera para ler o código (EAN/UPC)">
                  <div style={{ display: "flex", gap: 8 }}>
                    <input className="oi-input oi-mono" style={{ flex: 1 }} value={f.barras}
                           onChange={e => set("barras", e.target.value)} placeholder="789…" inputMode="numeric" />
                    <button className="oi-btn action" style={{ flex: "0 0 auto", padding: "0 16px", height: 48 }}
                            onClick={bipar} disabled={scanning}>
                      {scanning ? <Ic.refresh size={16} /> : <><Ic.scan size={16} /> Bipar</>}
                    </button>
                  </div>
                </PField>
                <div style={{ display: "flex", gap: 10 }}>
                  <div style={{ flex: 1 }}>
                    <PField label="Categoria" req>
                      <select className="oi-select" value={f.cat} onChange={e => set("cat", e.target.value)}>
                        <option value="">Selecionar</option>
                        {window.MOCK.CATEGORIAS.map(c => <option key={c}>{c}</option>)}
                      </select>
                    </PField>
                  </div>
                  <div style={{ flex: "0 0 130px" }}>
                    <PField label="Unidade" req>
                      <select className="oi-select" value={f.un} onChange={e => set("un", e.target.value)}>
                        {window.MOCK.UNIDADES.map(u => <option key={u}>{u}</option>)}
                      </select>
                    </PField>
                  </div>
                </div>
              </div>
            </div>
          </>
        )}

        {/* STEP 1 — PREÇOS */}
        {step === 1 && (
          <div className="oi-section">
            <h3 className="oi-section-h">Preços</h3>
            <div className="oi-card pad">
              <PField label="Preço de venda" req>
                <input className="oi-input oi-money" value={f.precoStr} onChange={e => set("precoStr", e.target.value)}
                       placeholder="R$ 0,00" inputMode="numeric" />
              </PField>
              <PField label="Custo">
                <input className="oi-input oi-money" value={f.custoStr} onChange={e => set("custoStr", e.target.value)}
                       placeholder="R$ 0,00" inputMode="numeric" />
              </PField>
              {margem !== null && (
                <div className="oi-hint" style={{ background: margem < 20 ? "color-mix(in oklch, var(--danger) 14%, transparent)" : "color-mix(in oklch, var(--ok) 14%, transparent)",
                                                   color: margem < 20 ? "var(--danger)" : "var(--ok)" }}>
                  <Ic.chart size={13} /> Margem de {margem.toFixed(1)}% · lucro {BRL(preco - custo)} por {f.un}
                </div>
              )}
              <PField label="Preço promocional" hint="Opcional — usado em campanhas e venda rápida">
                <input className="oi-input oi-money" value={f.promo} onChange={e => set("promo", e.target.value)}
                       placeholder="R$ 0,00" inputMode="numeric" />
              </PField>
            </div>
          </div>
        )}

        {/* STEP 2 — ESTOQUE */}
        {step === 2 && (
          <div className="oi-section">
            <h3 className="oi-section-h">Estoque</h3>
            <div className="oi-card pad">
              <PField>
                <PCheckRow on={f.controlaEstoque} onClick={() => set("controlaEstoque", !f.controlaEstoque)}>
                  Controlar estoque deste item
                </PCheckRow>
              </PField>
              {f.controlaEstoque ? (
                <>
                  <div style={{ display: "flex", gap: 10 }}>
                    <div style={{ flex: 1 }}>
                      <PField label="Estoque atual">
                        <input className="oi-input oi-mono" value={f.estoque} onChange={e => set("estoque", e.target.value)}
                               placeholder="0" inputMode="numeric" />
                      </PField>
                    </div>
                    <div style={{ flex: 1 }}>
                      <PField label="Estoque mínimo" hint="">
                        <input className="oi-input oi-mono" value={f.min} onChange={e => set("min", e.target.value)}
                               placeholder="0" inputMode="numeric" />
                      </PField>
                    </div>
                  </div>
                  <PField label="Localização">
                    <input className="oi-input" value={f.local} onChange={e => set("local", e.target.value)}
                           placeholder="Prateleira, gaveta…" />
                  </PField>
                  <PField label="Fornecedor padrão">
                    <select className="oi-select" value={f.fornecedor} onChange={e => set("fornecedor", e.target.value)}>
                      <option value="">Selecionar</option>
                      {window.MOCK.CLIENTES.filter(c => (c.papeis || []).includes("fornecedor")).map(c =>
                        <option key={c.id}>{c.nome}</option>)}
                    </select>
                  </PField>
                </>
              ) : (
                <div className="oi-hint" style={{ background: "var(--bg-2)", color: "var(--text-dim)" }}>
                  <Ic.zap size={13} /> Item sem controle de estoque (serviço ou sob demanda).
                </div>
              )}
            </div>
          </div>
        )}

        {/* STEP 3 — FISCAL */}
        {step === 3 && (
          <div className="oi-section">
            <h3 className="oi-section-h">Dados fiscais</h3>
            <div className="oi-card pad">
              <div style={{ display: "flex", gap: 10 }}>
                <div style={{ flex: 1 }}>
                  <PField label="NCM" req hint="">
                    <input className="oi-input oi-mono" value={f.ncm} onChange={e => set("ncm", e.target.value)}
                           placeholder="0000.00.00" inputMode="numeric" />
                  </PField>
                </div>
                <div style={{ flex: "0 0 110px" }}>
                  <PField label="CFOP">
                    <input className="oi-input oi-mono" value={f.cfop} onChange={e => set("cfop", e.target.value)}
                           placeholder="5101" inputMode="numeric" />
                  </PField>
                </div>
              </div>
              <PField label="Origem">
                <select className="oi-select" value={f.origem} onChange={e => set("origem", e.target.value)}>
                  <option>0 - Nacional</option>
                  <option>1 - Estrangeira (importação direta)</option>
                  <option>2 - Estrangeira (mercado interno)</option>
                </select>
              </PField>
              <PField label="CEST" hint="Preenchido automaticamente conforme o NCM, quando aplicável">
                <input className="oi-input oi-mono" value={f.cest} onChange={e => set("cest", e.target.value)}
                       placeholder="00.000.00" inputMode="numeric" />
              </PField>
            </div>
          </div>
        )}

        {/* STEP 4 — FICHA TÉCNICA */}
        {step === 4 && (
          <div className="oi-section">
            <h3 className="oi-section-h">Ficha técnica</h3>
            <div className="oi-card pad">
              <PField label="Foto do produto">
                <button className="oi-btn block" onClick={() => window.oiToast("Abrindo câmera / galeria…")}
                        style={{ height: 96, flexDirection: "column", gap: 6, borderStyle: "dashed", color: "var(--text-mute)" }}>
                  <Ic.image size={26} />
                  <span style={{ fontSize: 12, fontWeight: 500 }}>Adicionar foto</span>
                </button>
              </PField>
              {f.tipo !== "servico" && (
                <div style={{ display: "flex", gap: 10 }}>
                  <div style={{ flex: 1 }}>
                    <PField label="Gramatura / material">
                      <input className="oi-input" value={f.gramatura} onChange={e => set("gramatura", e.target.value)}
                             placeholder="250g couchê" />
                    </PField>
                  </div>
                  <div style={{ flex: 1 }}>
                    <PField label="Acabamento">
                      <input className="oi-input" value={f.acabamento} onChange={e => set("acabamento", e.target.value)}
                             placeholder="Laminação fosca" />
                    </PField>
                  </div>
                </div>
              )}
              <PField label="Descrição / observações">
                <textarea className="oi-textarea" value={f.descricao} onChange={e => set("descricao", e.target.value)}
                          placeholder="Detalhes que ajudam vendas e produção…" />
              </PField>
            </div>
          </div>
        )}

        <div style={{ height: 100 }} />
      </div>

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
          {step < PSTEPS.length - 1
            ? (<>Avançar <Ic.chevR size={16} /></>)
            : (<><Ic.check size={18} /> {editing ? "Salvar alterações" : "Salvar produto"}</>)}
        </button>
      </div>
    </>
  );
}

window.Screens = window.Screens || {};
window.Screens.NovoProdutoScreen = NovoProdutoScreen;
