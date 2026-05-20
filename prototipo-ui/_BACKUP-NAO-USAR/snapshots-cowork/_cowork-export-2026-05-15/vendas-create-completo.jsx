// vendas-create-completo.jsx — Drawer Nova Venda completo
// 3 modos: Comunicação Visual / Vestuário & Mercadorias / Ordem de Serviço
// Documentos fiscais: NFC-e, NF-e, NFS-e, MDF-e (referenciam Modules/NfeBrasil + Modules/NFSe)
const { useState: useS, useMemo: useM, useEffect: useE } = React;

// ───────────── catálogos por vertical (mock realista) ─────────────
const CAT_COMVIS = [
  { id:"banner-lona",   name:"Banner lona 440g",        unit:"m²",  price:38.00, type:"produto+servico", calcM2:true,  ncm:"3919.10", cfop:"5101" },
  { id:"adesivo-vinil", name:"Adesivo vinil recortado", unit:"m²",  price:62.00, type:"produto+servico", calcM2:true,  ncm:"3919.10", cfop:"5101" },
  { id:"acm-letreiro",  name:"Letreiro ACM 3mm",        unit:"m²",  price:280.00,type:"produto+servico", calcM2:true,  ncm:"7610.90", cfop:"5101" },
  { id:"placa-pvc",     name:"Placa PVC 3mm",           unit:"m²",  price:95.00, type:"produto+servico", calcM2:true,  ncm:"3920.49", cfop:"5101" },
  { id:"diagramacao",   name:"Diagramação / arte final",unit:"hora",price:80.00, type:"servico",          calcM2:false, codServ:"17.06" },
  { id:"instalacao",    name:"Instalação local",        unit:"diária",price:240.00,type:"servico",        calcM2:false, codServ:"7.05"  },
];

const CAT_VEST = [
  { id:"cam-bas-pre",  name:"Camiseta básica preta",  variants:["P","M","G","GG"], colors:["preto","branco","cinza"], price:49.90,  ncm:"6109.10" },
  { id:"cal-jeans",    name:"Calça jeans skinny",     variants:["36","38","40","42","44"], colors:["azul","preto"],     price:139.00, ncm:"6203.42" },
  { id:"jaq-corta",    name:"Jaqueta corta-vento",    variants:["P","M","G"],       colors:["verde","azul"],          price:189.00, ncm:"6201.93" },
  { id:"meia-par",     name:"Par de meias algodão",   variants:["U"],               colors:["preto","branco"],        price:14.90,  ncm:"6115.95" },
  { id:"vest-floral",  name:"Vestido floral midi",    variants:["P","M","G"],       colors:["estampado"],             price:159.00, ncm:"6204.43" },
  { id:"acessorio-cap",name:"Boné aba reta",          variants:["U"],               colors:["preto","cinza"],         price:69.00,  ncm:"6505.00" },
];

const CAT_OS_SERVICOS = [
  { id:"manut-equip",   name:"Manutenção equipamento",   codServ:"14.01", price:180.00 },
  { id:"reparo-eletro", name:"Reparo eletrônico",        codServ:"14.01", price:120.00 },
  { id:"limpeza-geral", name:"Limpeza/higienização",     codServ:"7.10",  price:90.00  },
  { id:"diagnostico",   name:"Diagnóstico técnico",      codServ:"14.01", price:60.00  },
];
const CAT_OS_PECAS = [
  { id:"pec-fonte",  name:"Fonte 12V 5A",   ncm:"8504.40", price:89.00 },
  { id:"pec-placa",  name:"Placa-mãe genérica", ncm:"8534.00", price:240.00 },
  { id:"pec-cabo",   name:"Cabo HDMI 2m",   ncm:"8544.42", price:24.00 },
  { id:"pec-rolam",  name:"Rolamento SKF",  ncm:"8482.10", price:48.00 },
];

const MODES = [
  { id:"comvis", title:"Comunicação Visual",  hint:"Banner, lona, ACM, placas — calcula m²", emoji:"🪧",
    sub:"Modules/ComunicacaoVisual + NfeBrasil + NFSe (LC 214/2025)" },
  { id:"vest",   title:"Vestuário & Mercadoria", hint:"Roupas, acessórios, pronta-entrega — variação tamanho/cor", emoji:"👕",
    sub:"Modules/Vestuario + NfeBrasil (NFC-e default)" },
  { id:"os",     title:"Ordem de Serviço",    hint:"Manutenção, reparo, instalação — serviço + peças", emoji:"🔧",
    sub:"Modules/Repair + NFSe + NfeBrasil (peças)" },
];

const PG = [
  { id:"pix",      label:"PIX",          icon:"⚡", clearing:"imediato" },
  { id:"dinheiro", label:"Dinheiro",     icon:"💵", clearing:"imediato" },
  { id:"cartao-c", label:"Cartão crédito", icon:"💳", clearing:"D+30" },
  { id:"cartao-d", label:"Cartão débito",  icon:"💳", clearing:"D+1" },
  { id:"boleto30", label:"Boleto 30d",   icon:"📄", clearing:"30d" },
  { id:"faturado", label:"Faturado 30d", icon:"📋", clearing:"prazo" },
];

// ───────────── componente principal ─────────────
function VendaCreateCompleto({ onClose }) {
  const [mode, setMode] = useS(null);
  const [step, setStep] = useS(1);

  const [client, setClient] = useS(null);
  const [clientQuery, setClientQuery] = useS("");
  const { OS_CLIENTS } = window.OS_DATA || { OS_CLIENTS: [] };

  const [items, setItems] = useS([]);
  const [payment, setPayment] = useS("pix");
  const [installments, setInstallments] = useS(1);
  const [discount, setDiscount] = useS(0);

  // Fiscal — auto pre-seleciona, usuário ajusta
  const [docs, setDocs] = useS([]); // ids: nfce / nfe / nfse / mdfe
  const [entregaMunicipio, setEntregaMunicipio] = useS("mesmo"); // mesmo | outro

  useE(() => {
    const onKey = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  // ─── Atalhos consumidor final
  const setWalkin = () => setClient({ id:"walkin", name:"Consumidor Final", cnpj:"", contact:"—", phone:"" });
  const clientMatches = useM(() => {
    if (!clientQuery.trim()) return (OS_CLIENTS || []).slice(0, 6);
    const q = clientQuery.toLowerCase();
    return (OS_CLIENTS || []).filter(c =>
      c.name.toLowerCase().includes(q) || (c.cnpj||"").includes(q)
    ).slice(0, 8);
  }, [clientQuery, OS_CLIENTS]);

  const fmt = (n) => Number(n||0).toLocaleString("pt-BR",{ style:"currency", currency:"BRL" });

  // ─── totais
  const subtotal = useM(() => items.reduce((s, it) => s + (Number(it.qty)||0) * (Number(it.unitPrice)||0), 0), [items]);
  const total = Math.max(0, subtotal - Number(discount||0));

  // ─── inferência fiscal automática
  const hasProduto = items.some(it => it.kind === "produto" || it.kind === "produto+servico");
  const hasServico = items.some(it => it.kind === "servico"  || it.kind === "produto+servico");
  const hasCnpj = !!(client?.cnpj && client.cnpj.replace(/\D/g,"").length === 14);

  // pre-seleção quando entra na etapa fiscal
  useE(() => {
    if (step !== (mode === "os" ? 5 : 4) || docs.length > 0) return;
    const auto = [];
    if (mode === "vest") {
      auto.push(hasCnpj ? "nfe" : "nfce");
    } else if (mode === "comvis") {
      if (hasProduto) auto.push(hasCnpj ? "nfe" : "nfce");
      if (hasServico) auto.push("nfse");
      if (entregaMunicipio === "outro" && hasProduto) auto.push("mdfe");
    } else if (mode === "os") {
      if (hasServico) auto.push("nfse");
      if (hasProduto) auto.push(hasCnpj ? "nfe" : "nfce");
    }
    setDocs(auto);
  }, [step, mode]);

  const toggleDoc = (id) => setDocs(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);

  // ─── steps por modo
  const stepsByMode = {
    comvis: [
      { n:1, label:"Cliente",   ok: !!client },
      { n:2, label:"Itens",     ok: items.length > 0 },
      { n:3, label:"Pagamento", ok: !!payment },
      { n:4, label:"Fiscal",    ok: docs.length > 0 },
      { n:5, label:"Confirmar", ok: false },
    ],
    vest: [
      { n:1, label:"Cliente",   ok: !!client },
      { n:2, label:"Itens",     ok: items.length > 0 },
      { n:3, label:"Pagamento", ok: !!payment },
      { n:4, label:"Fiscal",    ok: docs.length > 0 },
      { n:5, label:"Confirmar", ok: false },
    ],
    os: [
      { n:1, label:"Cliente",   ok: !!client },
      { n:2, label:"Serviço",   ok: items.some(it => it.kind === "servico") },
      { n:3, label:"Peças",     ok: true /* opcional */ },
      { n:4, label:"Pagamento", ok: !!payment },
      { n:5, label:"Fiscal",    ok: docs.length > 0 },
      { n:6, label:"Confirmar", ok: false },
    ],
  };
  const steps = mode ? stepsByMode[mode] : [];
  const lastStep = mode === "os" ? 6 : 5;
  const fiscalStep = mode === "os" ? 5 : 4;
  const canNext = mode && steps[step-1] && steps[step-1].ok;

  // ─── catálogo do modo
  const catalog = mode === "comvis" ? CAT_COMVIS
                : mode === "vest"   ? CAT_VEST
                : []; // OS usa CAT_OS_SERVICOS / CAT_OS_PECAS direto

  // ─── helpers de itens
  const addProductGeneric = (p, override = {}) => {
    setItems(prev => [...prev, {
      key: Date.now() + Math.random(),
      productId: p.id, name: p.name,
      qty: 1, unitPrice: p.price, unit: p.unit || "un",
      kind: p.type || (p.codServ ? "servico" : "produto"),
      ncm: p.ncm, cfop: p.cfop, codServ: p.codServ,
      ...override,
    }]);
  };
  const update = (k, patch) => setItems(prev => prev.map(it => it.key === k ? { ...it, ...patch } : it));
  const remove = (k) => setItems(prev => prev.filter(it => it.key !== k));

  return (
    <div className="os-drawer-back" onClick={onClose}>
      <aside className="os-drawer wide vd-create vd-completo" onClick={e => e.stopPropagation()}>
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <span className="os-drawer-id">Nova venda</span>
            <h2>{mode ? MODES.find(m => m.id === mode).title : "Selecione o tipo"}</h2>
            <p>{mode ? MODES.find(m => m.id === mode).sub : "Cada vertical tem seu fluxo e regras fiscais"}</p>
          </div>
          <div className="os-drawer-head-r">
            {mode && <button className="os-btn ghost" onClick={() => { setMode(null); setStep(1); setItems([]); setDocs([]); }}>↺ Trocar tipo</button>}
            <button className="icon-btn" onClick={onClose}><I.close size={14}/></button>
          </div>
        </header>

        {/* ── Step 0 — pick mode */}
        {!mode && (
          <div className="vd-mode-pick">
            {MODES.map(m => (
              <button key={m.id} className="vd-mode-card" onClick={() => setMode(m.id)}>
                <span className="vd-mode-emoji">{m.emoji}</span>
                <span className="vd-mode-title">{m.title}</span>
                <span className="vd-mode-hint">{m.hint}</span>
                <span className="vd-mode-sub">{m.sub}</span>
              </button>
            ))}
            <div className="vd-mode-foot">
              Diferentes verticais → diferentes documentos fiscais. <b>NFC-e</b> consumidor balcão · <b>NF-e</b> B2B com CNPJ · <b>NFS-e</b> serviço (LC 214/2025) · <b>MDF-e</b> transporte interestadual.
            </div>
          </div>
        )}

        {/* ── Stepper */}
        {mode && (
          <nav className="vd-stepper">
            {steps.map((s, i) => (
              <button key={s.n}
                      className={"vd-step" + (step === s.n ? " active" : "") + (s.ok ? " done" : "")}
                      onClick={() => (s.ok || step > s.n) ? setStep(s.n) : null}>
                <span className="vd-step-num">{s.ok && step !== s.n ? "✓" : s.n}</span>
                <span>{s.label}</span>
                {i < steps.length - 1 && <span className="vd-step-sep">›</span>}
              </button>
            ))}
          </nav>
        )}

        {mode && (
          <div className="os-drawer-body vd-create-body">

            {/* ── Step 1 — Cliente (todos os modos) */}
            {step === 1 && (
              <section className="vd-section">
                <h3>Cliente</h3>
                {!client ? (
                  <>
                    <div className="vd-search-wrap">
                      <I.search size={12}/>
                      <input autoFocus placeholder="Nome, CNPJ ou telefone..." value={clientQuery}
                             onChange={e => setClientQuery(e.target.value)}/>
                      <button className="vd-walkin" onClick={setWalkin}>Consumidor Final</button>
                    </div>
                    <div className="vd-client-list">
                      {clientMatches.map(c => (
                        <button key={c.id} className="vd-client-card" onClick={() => setClient(c)}>
                          <div className="vd-client-card-name">{c.name}</div>
                          <div className="vd-client-card-meta">{c.cnpj || "sem CNPJ"} · {c.contact || "—"}</div>
                        </button>
                      ))}
                    </div>
                  </>
                ) : (
                  <div className="vd-client-selected">
                    <div>
                      <strong>{client.name}</strong>
                      <div className="vd-meta-line">{client.cnpj || "sem CNPJ — só NFC-e disponível"}</div>
                    </div>
                    <button className="os-btn ghost" onClick={() => setClient(null)}>Trocar</button>
                  </div>
                )}
              </section>
            )}

            {/* ── COMVIS Step 2 — Itens com cálculo m² */}
            {mode === "comvis" && step === 2 && (
              <ItensComVisual catalog={catalog} items={items} update={update} remove={remove} addProductGeneric={addProductGeneric} fmt={fmt}/>
            )}

            {/* ── VEST Step 2 — Itens com variação */}
            {mode === "vest" && step === 2 && (
              <ItensVestuario catalog={catalog} items={items} update={update} remove={remove} addProductGeneric={addProductGeneric} fmt={fmt}/>
            )}

            {/* ── OS Step 2 — Serviço */}
            {mode === "os" && step === 2 && (
              <ItensOSServico items={items} update={update} remove={remove} addProductGeneric={addProductGeneric} fmt={fmt}/>
            )}

            {/* ── OS Step 3 — Peças (opcional) */}
            {mode === "os" && step === 3 && (
              <ItensOSPecas items={items} update={update} remove={remove} addProductGeneric={addProductGeneric} fmt={fmt}/>
            )}

            {/* ── Pagamento */}
            {(((mode === "comvis" || mode === "vest") && step === 3) || (mode === "os" && step === 4)) && (
              <PagamentoSection {...{ payment, setPayment, installments, setInstallments, discount, setDiscount, subtotal, total, fmt }}/>
            )}

            {/* ── Fiscal */}
            {step === fiscalStep && (
              <FiscalSection {...{ mode, docs, toggleDoc, hasProduto, hasServico, hasCnpj, items, client, entregaMunicipio, setEntregaMunicipio, fmt, total }}/>
            )}

            {/* ── Confirmar */}
            {step === lastStep && (
              <ConfirmarSection {...{ mode, client, items, payment, installments, docs, total, fmt }}/>
            )}
          </div>
        )}

        {/* ── Footer */}
        {mode && (
          <footer className="os-drawer-actions vd-foot">
            <div className="vd-foot-summary">
              {items.length > 0 && (
                <>
                  <span>{items.length} {items.length === 1 ? "item" : "itens"}</span>
                  <span className="vd-foot-total">{fmt(total)}</span>
                </>
              )}
            </div>
            <div className="vd-foot-actions">
              {step > 1 && <button className="os-btn ghost" onClick={() => setStep(step-1)}>← Voltar</button>}
              {step < lastStep && <button className="os-btn primary" disabled={!canNext} onClick={() => setStep(step+1)}>Avançar →</button>}
              {step === lastStep && (
                <button className="os-btn primary" onClick={() => { alert("Venda registrada — emitiria: " + docs.map(d => d.toUpperCase()).join(" + ")); onClose(); }}>
                  <I.check size={11}/>Confirmar e emitir
                </button>
              )}
            </div>
          </footer>
        )}
      </aside>
    </div>
  );
}

// ───────────── COMVIS — itens com m² ─────────────
function ItensComVisual({ catalog, items, update, remove, addProductGeneric, fmt }) {
  const [q, setQ] = useS("");
  const matches = catalog.filter(p => !q || p.name.toLowerCase().includes(q.toLowerCase())).slice(0, 6);
  return (
    <section className="vd-section">
      <h3>Itens · cálculo automático m²</h3>
      <div className="vd-search-wrap">
        <I.search size={12}/>
        <input placeholder="Buscar produto/serviço (banner, ACM, diagramação...)" value={q} onChange={e => setQ(e.target.value)}/>
      </div>
      {q && matches.length > 0 && (
        <div className="vd-prod-suggest">
          {matches.map(p => (
            <button key={p.id} className="vd-prod-row" onClick={() => { addProductGeneric(p, p.calcM2 ? { largura:1, altura:1, qty:1, unitPrice:p.price } : {}); setQ(""); }}>
              <span>{p.name}</span>
              <span className="vd-prod-price">{fmt(p.price)} / {p.unit}</span>
            </button>
          ))}
        </div>
      )}
      {items.length === 0 ? (
        <div className="vd-empty-state">Nenhum item. Buscar acima — banner/lona/ACM calculam m² automático.</div>
      ) : (
        <table className="vd-items-table vd-comvis-table">
          <thead><tr><th>Item</th><th>Larg.(m)</th><th>Alt.(m)</th><th>m²</th><th>Qtd</th><th>R$/m²</th><th>Subtotal</th><th></th></tr></thead>
          <tbody>
            {items.map(it => {
              const m2 = it.largura && it.altura ? +(Number(it.largura) * Number(it.altura)).toFixed(2) : null;
              const sub = it.largura ? (m2 || 0) * (Number(it.qty)||0) * (Number(it.unitPrice)||0)
                                     : (Number(it.qty)||0) * (Number(it.unitPrice)||0);
              return (
                <tr key={it.key}>
                  <td>
                    <div className="vd-strong">{it.name}</div>
                    <div className="vd-meta-line">NCM {it.ncm || "—"} · CFOP {it.cfop || "—"}</div>
                  </td>
                  <td>{it.largura !== undefined ? <input type="number" step="0.1" value={it.largura} onChange={e => update(it.key,{largura:e.target.value})}/> : "—"}</td>
                  <td>{it.altura !== undefined ? <input type="number" step="0.1" value={it.altura} onChange={e => update(it.key,{altura:e.target.value})}/> : "—"}</td>
                  <td className="vd-strong">{m2 != null ? m2 + " m²" : "—"}</td>
                  <td><input type="number" min="1" value={it.qty} onChange={e => update(it.key,{qty:e.target.value})}/></td>
                  <td><input type="number" step="0.01" value={it.unitPrice} onChange={e => update(it.key,{unitPrice:e.target.value})}/></td>
                  <td className="vd-strong">{fmt(sub)}</td>
                  <td><button className="icon-btn" onClick={() => remove(it.key)}><I.close size={12}/></button></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}
    </section>
  );
}

// ───────────── VEST — itens com variação tamanho/cor ─────────────
function ItensVestuario({ catalog, items, update, remove, addProductGeneric, fmt }) {
  const [q, setQ] = useS("");
  const matches = catalog.filter(p => !q || p.name.toLowerCase().includes(q.toLowerCase())).slice(0, 6);
  return (
    <section className="vd-section">
      <h3>Itens · variação tamanho/cor</h3>
      <div className="vd-search-wrap">
        <I.search size={12}/>
        <input placeholder="Buscar mercadoria ou bipar código de barras" value={q} onChange={e => setQ(e.target.value)}/>
        <button className="vd-walkin" onClick={() => setQ("")}>📷 Câmera</button>
      </div>
      {q && matches.length > 0 && (
        <div className="vd-prod-suggest">
          {matches.map(p => (
            <button key={p.id} className="vd-prod-row" onClick={() => { addProductGeneric(p, { qty:1, unitPrice:p.price, variant:p.variants[0], color:p.colors[0], variants:p.variants, colors:p.colors, kind:"produto" }); setQ(""); }}>
              <span>{p.name}</span>
              <span className="vd-prod-price">{fmt(p.price)}</span>
            </button>
          ))}
        </div>
      )}
      {items.length === 0 ? (
        <div className="vd-empty-state">Bipar código de barras ou buscar pelo nome.</div>
      ) : (
        <table className="vd-items-table vd-vest-table">
          <thead><tr><th>Mercadoria</th><th>Tamanho</th><th>Cor</th><th>Qtd</th><th>Unit.</th><th>Subtotal</th><th></th></tr></thead>
          <tbody>
            {items.map(it => {
              const sub = (Number(it.qty)||0) * (Number(it.unitPrice)||0);
              return (
                <tr key={it.key}>
                  <td>
                    <div className="vd-strong">{it.name}</div>
                    <div className="vd-meta-line">NCM {it.ncm}</div>
                  </td>
                  <td>
                    <select value={it.variant || ""} onChange={e => update(it.key,{variant:e.target.value})}>
                      {(it.variants || ["U"]).map(v => <option key={v}>{v}</option>)}
                    </select>
                  </td>
                  <td>
                    <select value={it.color || ""} onChange={e => update(it.key,{color:e.target.value})}>
                      {(it.colors || ["—"]).map(c => <option key={c}>{c}</option>)}
                    </select>
                  </td>
                  <td><input type="number" min="1" value={it.qty} onChange={e => update(it.key,{qty:e.target.value})}/></td>
                  <td><input type="number" step="0.01" value={it.unitPrice} onChange={e => update(it.key,{unitPrice:e.target.value})}/></td>
                  <td className="vd-strong">{fmt(sub)}</td>
                  <td><button className="icon-btn" onClick={() => remove(it.key)}><I.close size={12}/></button></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}
    </section>
  );
}

// ───────────── OS — serviço (descrição + diagnóstico) ─────────────
function ItensOSServico({ items, update, remove, addProductGeneric, fmt }) {
  const [q, setQ] = useS("");
  const services = items.filter(i => i.kind === "servico");
  const matches = CAT_OS_SERVICOS.filter(s => !q || s.name.toLowerCase().includes(q.toLowerCase()));
  return (
    <section className="vd-section">
      <h3>Serviço da OS</h3>
      <div className="vd-search-wrap">
        <I.search size={12}/>
        <input placeholder="Tipo de serviço (manutenção, reparo, diagnóstico)" value={q} onChange={e => setQ(e.target.value)}/>
      </div>
      {q && matches.length > 0 && (
        <div className="vd-prod-suggest">
          {matches.map(p => (
            <button key={p.id} className="vd-prod-row" onClick={() => { addProductGeneric(p, { qty:1, unitPrice:p.price, kind:"servico", desc:"" }); setQ(""); }}>
              <span>{p.name}</span>
              <span className="vd-prod-price">{fmt(p.price)} · cód.serv {p.codServ}</span>
            </button>
          ))}
        </div>
      )}
      {services.length === 0 ? (
        <div className="vd-empty-state">OS exige ao menos 1 serviço — busque acima.</div>
      ) : (
        services.map(it => (
          <div key={it.key} className="vd-os-service-card">
            <div className="vd-os-service-head">
              <strong>{it.name}</strong>
              <span className="vd-meta-line">cód.serv {it.codServ}</span>
              <button className="icon-btn" onClick={() => remove(it.key)}><I.close size={12}/></button>
            </div>
            <textarea placeholder="Descrição do problema / diagnóstico (vai pra DANFSE e ficha de produção)..."
                      value={it.desc || ""} rows={3}
                      onChange={e => update(it.key,{desc:e.target.value})}/>
            <div className="vd-os-service-row">
              <label>Qtd <input type="number" min="1" value={it.qty} onChange={e => update(it.key,{qty:e.target.value})}/></label>
              <label>Unit. <input type="number" step="0.01" value={it.unitPrice} onChange={e => update(it.key,{unitPrice:e.target.value})}/></label>
              <span className="vd-strong">Subtotal {fmt((Number(it.qty)||0)*(Number(it.unitPrice)||0))}</span>
            </div>
          </div>
        ))
      )}
    </section>
  );
}

// ───────────── OS — peças (opcional) ─────────────
function ItensOSPecas({ items, update, remove, addProductGeneric, fmt }) {
  const [q, setQ] = useS("");
  const pecas = items.filter(i => i.kind === "produto");
  const matches = CAT_OS_PECAS.filter(p => !q || p.name.toLowerCase().includes(q.toLowerCase()));
  return (
    <section className="vd-section">
      <h3>Peças <span style={{fontWeight:400, fontSize:11, color:"#777", textTransform:"none", letterSpacing:0}}>· opcional</span></h3>
      <div className="vd-search-wrap">
        <I.search size={12}/>
        <input placeholder="Buscar peça (fonte, placa, cabo...)" value={q} onChange={e => setQ(e.target.value)}/>
      </div>
      {q && matches.length > 0 && (
        <div className="vd-prod-suggest">
          {matches.map(p => (
            <button key={p.id} className="vd-prod-row" onClick={() => { addProductGeneric(p, { qty:1, unitPrice:p.price, kind:"produto" }); setQ(""); }}>
              <span>{p.name}</span>
              <span className="vd-prod-price">{fmt(p.price)} · NCM {p.ncm}</span>
            </button>
          ))}
        </div>
      )}
      {pecas.length === 0 ? (
        <div className="vd-empty-state vd-info">Sem peças — só serviço, será emitida apenas <b>NFS-e</b>.</div>
      ) : (
        <table className="vd-items-table">
          <thead><tr><th>Peça</th><th>NCM</th><th>Qtd</th><th>Unit.</th><th>Subtotal</th><th></th></tr></thead>
          <tbody>
            {pecas.map(it => (
              <tr key={it.key}>
                <td><div className="vd-strong">{it.name}</div></td>
                <td>{it.ncm}</td>
                <td><input type="number" min="1" value={it.qty} onChange={e => update(it.key,{qty:e.target.value})}/></td>
                <td><input type="number" step="0.01" value={it.unitPrice} onChange={e => update(it.key,{unitPrice:e.target.value})}/></td>
                <td className="vd-strong">{fmt((Number(it.qty)||0)*(Number(it.unitPrice)||0))}</td>
                <td><button className="icon-btn" onClick={() => remove(it.key)}><I.close size={12}/></button></td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  );
}

// ───────────── Pagamento (compartilhado) ─────────────
function PagamentoSection({ payment, setPayment, installments, setInstallments, discount, setDiscount, subtotal, total, fmt }) {
  return (
    <section className="vd-section">
      <h3>Pagamento</h3>
      <div className="vd-pay-grid">
        {PG.map(p => (
          <button key={p.id} className={"vd-pay-card" + (payment === p.id ? " active" : "")} onClick={() => setPayment(p.id)}>
            <span className="vd-pay-icon">{p.icon}</span>
            <span className="vd-pay-label">{p.label}</span>
            <span className="vd-pay-clear">{p.clearing}</span>
          </button>
        ))}
      </div>
      {(payment.startsWith("cartao") || payment.startsWith("boleto") || payment === "faturado") && (
        <div className="vd-fields">
          <label>Parcelas
            <select value={installments} onChange={e => setInstallments(parseInt(e.target.value))}>
              {[1,2,3,4,5,6,10,12].map(n => <option key={n} value={n}>{n}× de {fmt(total/n)}</option>)}
            </select>
          </label>
          <label>Desconto
            <input type="number" min="0" step="0.01" value={discount} onChange={e => setDiscount(parseFloat(e.target.value) || 0)}/>
          </label>
        </div>
      )}
      <dl className="vd-totals">
        <dt>Subtotal</dt><dd>{fmt(subtotal)}</dd>
        <dt>Desconto</dt><dd>-{fmt(discount)}</dd>
        <dt className="vd-total-row">Total</dt><dd className="vd-total-row">{fmt(total)}</dd>
      </dl>
    </section>
  );
}

// ───────────── Fiscal — NFC-e/NF-e/NFS-e/MDF-e ─────────────
function FiscalSection({ mode, docs, toggleDoc, hasProduto, hasServico, hasCnpj, client, entregaMunicipio, setEntregaMunicipio, fmt, total }) {
  const all = [
    { id:"nfce", tag:"NFC-e · 65", name:"Cupom consumidor",
      desc:"Balcão · CPF na nota opcional · DANFE simples impressa",
      module:"NfeBrasil/NfeEmissaoController",
      avail: hasProduto, why: !hasProduto ? "exige produto" : null,
      conflict: docs.includes("nfe") ? "redundante com NF-e" : null },
    { id:"nfe", tag:"NF-e · 55", name:"NF eletrônica",
      desc:"B2B · DANFE A4 + XML por e-mail · entra em nfe_emissoes com status SEFAZ",
      module:"NfeBrasil/NfeEmissaoController",
      avail: hasProduto && hasCnpj, why: !hasProduto ? "exige produto" : !hasCnpj ? "cliente sem CNPJ" : null,
      conflict: docs.includes("nfce") ? "redundante com NFC-e" : null },
    { id:"nfse", tag:"NFS-e · LC 214/25", name:"NF de serviço",
      desc:"Sistema Nacional · ISS municipal · DANFSE com descrição do serviço",
      module:"NFSe/NfseController",
      avail: hasServico, why: !hasServico ? "exige item de serviço" : null },
    { id:"mdfe", tag:"MDF-e · 58", name:"Manifesto de transporte",
      desc:"Transporte interestadual de carga própria · só ativa se entrega for fora do município",
      module:"NfeBrasil (backlog) — ainda não implementado",
      avail: false, why:"módulo backlog · entrega para outro município",
      backlog:true },
  ];

  return (
    <section className="vd-section">
      <h3>Documentos fiscais</h3>

      {/* contexto da venda */}
      <div className="vd-fiscal-ctx">
        <div><span>Cliente</span><b>{client?.name || "—"}</b><i>{client?.cnpj || "sem CNPJ"}</i></div>
        <div><span>Composição</span><b>{hasProduto && hasServico ? "produto + serviço" : hasProduto ? "só produto" : "só serviço"}</b><i>{hasProduto ? "→ NF-e/NFC-e " : ""}{hasServico ? "→ NFS-e" : ""}</i></div>
        <div><span>Total</span><b>{fmt(total)}</b><i>base de cálculo dos impostos</i></div>
        {mode === "comvis" && (
          <div className="vd-fiscal-ctx-action">
            <span>Entrega</span>
            <select value={entregaMunicipio} onChange={e => setEntregaMunicipio(e.target.value)}>
              <option value="mesmo">No mesmo município</option>
              <option value="outro">Outro município/estado</option>
            </select>
            <i>{entregaMunicipio === "outro" ? "habilita MDF-e" : "MDF-e dispensado"}</i>
          </div>
        )}
      </div>

      <div className="vd-fiscal-grid">
        {all.map(d => {
          const active = docs.includes(d.id);
          return (
            <button key={d.id}
                    className={"vd-fiscal-doc"
                      + (active ? " active" : "")
                      + (!d.avail ? " unavail" : "")
                      + (d.backlog ? " backlog" : "")}
                    onClick={() => d.avail && toggleDoc(d.id)}
                    disabled={!d.avail}>
              <div className="vd-fiscal-head">
                <span className="vd-fiscal-tag">{d.tag}</span>
                {active && <span className="vd-fiscal-state on">✓ vai emitir</span>}
                {!d.avail && d.backlog && <span className="vd-fiscal-state bk">backlog</span>}
                {!d.avail && !d.backlog && <span className="vd-fiscal-state off">indisponível</span>}
              </div>
              <div className="vd-fiscal-name">{d.name}</div>
              <div className="vd-fiscal-desc">{d.desc}</div>
              <div className="vd-fiscal-module">{d.module}</div>
              {d.why && <div className="vd-fiscal-why">⚠ {d.why}</div>}
              {d.conflict && active && <div className="vd-fiscal-why warn">⚠ {d.conflict}</div>}
            </button>
          );
        })}
      </div>

      {docs.length > 0 && (
        <div className="vd-fiscal-summary">
          Ao confirmar, serão emitidos: {docs.map(d => <b key={d}>{d.toUpperCase()}</b>).reduce((p,c,i) => i ? [p, " + ", c] : [c], null)}.
          Disparo via fila <code>nfe_emissoes</code>/<code>nfse_jobs</code> · resposta SEFAZ assíncrona.
        </div>
      )}
    </section>
  );
}

// ───────────── Confirmar ─────────────
function ConfirmarSection({ mode, client, items, payment, installments, docs, total, fmt }) {
  return (
    <section className="vd-section vd-confirm">
      <h3>Confirmar venda</h3>
      <div className="vd-confirm-grid">
        <div className="vd-confirm-block">
          <span className="vd-confirm-label">Tipo</span>
          <strong>{MODES.find(m => m.id === mode).title}</strong>
          <span className="vd-meta-line">{MODES.find(m => m.id === mode).emoji} {mode}</span>
        </div>
        <div className="vd-confirm-block">
          <span className="vd-confirm-label">Cliente</span>
          <strong>{client?.name || "—"}</strong>
          <span className="vd-meta-line">{client?.cnpj || "sem CNPJ"}</span>
        </div>
        <div className="vd-confirm-block">
          <span className="vd-confirm-label">Itens</span>
          <strong>{items.length} {items.length === 1 ? "item" : "itens"}</strong>
          <span className="vd-meta-line">{items.filter(i => i.kind?.includes("servico")).length} serviço · {items.filter(i => i.kind === "produto" || i.kind === "produto+servico").length} produto</span>
        </div>
        <div className="vd-confirm-block">
          <span className="vd-confirm-label">Pagamento</span>
          <strong>{(PG.find(p => p.id === payment) || {}).label}</strong>
          <span className="vd-meta-line">{installments > 1 ? `${installments}× de ${fmt(total/installments)}` : "à vista"}</span>
        </div>
        <div className="vd-confirm-block vd-confirm-fiscal">
          <span className="vd-confirm-label">Documentos fiscais</span>
          <strong>{docs.length === 0 ? "nenhum" : docs.map(d => d.toUpperCase()).join(" + ")}</strong>
          <span className="vd-meta-line">emissão na confirmação</span>
        </div>
        <div className="vd-confirm-block vd-confirm-total">
          <span className="vd-confirm-label">Total</span>
          <strong className="vd-total-big">{fmt(total)}</strong>
        </div>
      </div>
      <div className="vd-callout">
        <strong>Pronto.</strong> Ao confirmar, a venda entra em <code>vendas</code>, dispara emissão fiscal e — se for {mode === "comvis" ? "Comunicação Visual com produção" : mode === "os" ? "OS" : "venda com produção"} — gera ficha em <code>repair_jobs</code> para o setor produtivo.
      </div>
    </section>
  );
}

window.VendaCreateCompleto = VendaCreateCompleto;
