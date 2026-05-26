// vendas-flow.jsx — Fatias 2/3/4 KB-9.75 (2026-05-26)
// 3 features: VdNextActionPanel (FSM transitions) · VdNfeEmitModal · VdNfseEmitModal
// + helpers vdFsmActions(venda) · useVdFsmPatches() · useVdFiscalPatches()
//
// Persistência: localStorage.
//   oimpresso.vendas.fsmPatches  = { [vendaId]: { fsm: N, history: [...] } }
//   oimpresso.vendas.fiscPatches = { [vendaId]: { nfe?: {...}, nfse?: {...} } }
//
// Defensivo: se algo der ruim, falla pra UI atual sem quebrar a página.

const { useState: useStateFl, useEffect: useEffectFl, useMemo: useMemoFl, useRef: useRefFl } = React;

// ──────────────────────────────────────────────────────────────
// HELPERS — formatação e estado FSM/Fiscal
// ──────────────────────────────────────────────────────────────
const _flFmt = (n) => (n || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" });

// Gera chave SEFAZ mock de 44 dígitos
function _genChaveSefaz() {
  let s = "";
  for (let i = 0; i < 44; i++) s += Math.floor(Math.random() * 10);
  return s;
}

// Persistência FSM
function _flLoadFsm() {
  try { return JSON.parse(localStorage.getItem("oimpresso.vendas.fsmPatches") || "{}"); }
  catch (e) { return {}; }
}
function _flSaveFsm(m) {
  try { localStorage.setItem("oimpresso.vendas.fsmPatches", JSON.stringify(m)); } catch (e) {}
}

// Persistência fiscal
function _flLoadFisc() {
  try { return JSON.parse(localStorage.getItem("oimpresso.vendas.fiscPatches") || "{}"); }
  catch (e) { return {}; }
}
function _flSaveFisc(m) {
  try { localStorage.setItem("oimpresso.vendas.fiscPatches", JSON.stringify(m)); } catch (e) {}
}

// ──────────────────────────────────────────────────────────────
// HOOK · useVdFsmPatches — patches do fsm + history
// ──────────────────────────────────────────────────────────────
function useVdFsmPatches() {
  const [m, setM] = useStateFl(_flLoadFsm);
  useEffectFl(() => _flSaveFsm(m), [m]);

  // Re-sincroniza quando outro componente disparar fsm-advance
  useEffectFl(() => {
    const fn = () => setM(_flLoadFsm());
    window.addEventListener("oimpresso:fsm-advance", fn);
    window.addEventListener("oimpresso:fiscal-patched", fn);
    return () => {
      window.removeEventListener("oimpresso:fsm-advance", fn);
      window.removeEventListener("oimpresso:fiscal-patched", fn);
    };
  }, []);

  const effectiveFsm = (venda) => {
    if (!venda) return 0;
    const patch = m[venda.id];
    return patch?.fsm ?? venda.fsm ?? 0;
  };

  const advance = (vendaId, toFsm, note) => {
    setM(prev => {
      const cur = prev[vendaId] || { fsm: null, history: [] };
      const stamp = new Date().toISOString();
      const next = {
        ...prev,
        [vendaId]: {
          fsm: toFsm,
          history: [...(cur.history || []), { at: stamp, fsm: toFsm, note: note || "" }],
        },
      };
      _flSaveFsm(next);
      return next;
    });
    setTimeout(() => {
      window.dispatchEvent(new CustomEvent("oimpresso:fsm-advance", { detail: { vendaId, fsm: toFsm } }));
      // Glossário BR: avanço pra fsm=4 = baixa financeira (pagamento recebido)
      if (toFsm === 4) {
        window.dispatchEvent(new CustomEvent("oimpresso:venda-paid", { detail: { vendaId, at: new Date().toISOString() } }));
      }
      // fsm=2 = faturada (NF emitida + título no contas a receber)
      if (toFsm === 2) {
        window.dispatchEvent(new CustomEvent("oimpresso:venda-invoiced", { detail: { vendaId, at: new Date().toISOString() } }));
      }
    }, 0);
  };

  const reset = (vendaId) => {
    setM(prev => { const { [vendaId]: _, ...rest } = prev; return rest; });
  };

  const history = (vendaId) => (m[vendaId]?.history || []);

  return { effectiveFsm, advance, reset, history, patches: m };
}
window.useVdFsmPatches = useVdFsmPatches;

// ──────────────────────────────────────────────────────────────
// HOOK · useVdFiscalPatches — overlays NF-e / NFS-e
// ──────────────────────────────────────────────────────────────
function useVdFiscalPatches() {
  const [m, setM] = useStateFl(_flLoadFisc);
  useEffectFl(() => _flSaveFisc(m), [m]);

  // Re-sincroniza quando outro componente disparar fiscal-patched
  useEffectFl(() => {
    const fn = () => setM(_flLoadFisc());
    window.addEventListener("oimpresso:fiscal-patched", fn);
    return () => window.removeEventListener("oimpresso:fiscal-patched", fn);
  }, []);

  const effectiveFiscal = (venda) => {
    if (!venda) return {};
    const base = venda.fiscal || {};
    const patch = m[venda.id] || {};
    return { ...base, ...patch };
  };

  const setDoc = (vendaId, kind, doc) => {
    setM(prev => {
      const next = { ...prev, [vendaId]: { ...(prev[vendaId] || {}), [kind]: doc } };
      // salvar sincronamente, sem esperar useEffect
      _flSaveFisc(next);
      return next;
    });
    // dispatch event após o setState assentar
    setTimeout(() => {
      window.dispatchEvent(new CustomEvent("oimpresso:fiscal-patched", { detail: { vendaId, kind } }));
    }, 0);
  };

  const removeDoc = (vendaId, kind) => {
    setM(prev => {
      const cur = { ...(prev[vendaId] || {}) };
      delete cur[kind];
      return { ...prev, [vendaId]: cur };
    });
  };

  const reset = (vendaId) => {
    setM(prev => { const { [vendaId]: _, ...rest } = prev; return rest; });
  };

  return { effectiveFiscal, setDoc, removeDoc, reset, patches: m };
}
window.useVdFiscalPatches = useVdFiscalPatches;

// ──────────────────────────────────────────────────────────────
// vdFsmActions — retorna a próxima ação contextual por fsm/vertical
// ──────────────────────────────────────────────────────────────
function vdFsmActions(venda, currentFsm, effectiveFiscal) {
  const vert = venda.vertical || "cv";
  const FSM_BY_VERTICAL = window.VENDAS_DATA?.FSM_BY_VERTICAL || {};
  const set = FSM_BY_VERTICAL[vert] || FSM_BY_VERTICAL.cv;
  const cur = set.steps[currentFsm];
  const next = set.steps[currentFsm + 1];

  const hasProduto = (venda.itemsList || []).some(i => i.type === "produto");
  const hasServico = (venda.itemsList || []).some(i => i.type === "servico");
  const fisc = effectiveFiscal || venda.fiscal || {};
  const nfeOk  = fisc.nfe?.status  === "ok";
  const nfseOk = fisc.nfse?.status === "ok";
  const fiscalReady = (!hasProduto || nfeOk) && (!hasServico || nfseOk);

  // === Map por vertical CV (gráfica comum) ===
  // Glossário BR (correção semântica 2026-05-26):
  //   Faturar  = emitir documento fiscal (NF-e/NFS-e)  → gera título no contas a receber
  //   Receber  = baixa financeira do título            → entrada no caixa/banco
  //   "Marcar como paga" SÓ depois que documento fiscal existir + entrega ocorreu
  if (vert === "cv") {
    if (currentFsm === 0) return { label: "Aprovar pedido", icon: "✓", target: 1, color: "blue", desc: "Cliente confirmou o orçamento — vira pedido firme. Sem implicação fiscal ainda." };
    if (currentFsm === 1) {
      if (!fiscalReady) {
        const need = [];
        if (hasProduto && !nfeOk)  need.push("NF-e");
        if (hasServico && !nfseOk) need.push("NFS-e");
        return { label: "Faturar", icon: "📄", target: 2, color: "indigo", gate: `Emita ${need.join(" e ")} antes de faturar.`, gateAction: need[0] === "NF-e" ? "emit-nfe" : "emit-nfse" };
      }
      return { label: "Confirmar faturamento", icon: "📄", target: 2, color: "indigo", desc: "Documento fiscal emitido — título entra no contas a receber." };
    }
    if (currentFsm === 2) return { label: "Confirmar entrega", icon: "📦", target: 3, color: "amber", desc: "Cliente recebeu / retirou os itens. Título continua aberto até a baixa financeira." };
    if (currentFsm === 3) return { label: "Receber pagamento", icon: "💰", target: 4, color: "green", desc: "Baixa do título no contas a receber — entrada no caixa/banco. Fecha o ciclo." };
    if (currentFsm === 4) return null; // ciclo completo
  }

  // === Map por vertical MEC (oficina mecânica) ===
  if (vert === "mec") {
    if (currentFsm === 0) return { label: "Iniciar diagnóstico", icon: "🔍", target: 1, color: "blue", desc: "Veículo entrou na oficina pra avaliação." };
    if (currentFsm === 1) return { label: "Aprovar orçamento de peças", icon: "✓", target: 2, color: "indigo", desc: "Cliente autorizou compra de peças." };
    if (currentFsm === 2) return { label: "Iniciar execução", icon: "🔧", target: 3, color: "amber", desc: "Mecânico começa o serviço." };
    if (currentFsm === 3) {
      if (!fiscalReady && hasServico) {
        return { label: "Marcar como pronto", icon: "✓", target: 4, color: "green", gate: "Emita NFS-e antes de entregar o veículo.", gateAction: "emit-nfse" };
      }
      return { label: "Marcar como pronto", icon: "✓", target: 4, color: "green", desc: "Veículo pronto pra retirada." };
    }
    return null;
  }

  // Fallback genérico
  if (next) return { label: `Avançar pra ${next}`, icon: "→", target: currentFsm + 1, color: "blue", desc: `Próxima etapa: ${next}.` };
  return null;
}
window.vdFsmActions = vdFsmActions;

// ──────────────────────────────────────────────────────────────
// COMPONENTE · VdNextActionPanel
// ──────────────────────────────────────────────────────────────
function VdNextActionPanel({ venda, onOpenEmit }) {
  const fsmH = useVdFsmPatches();
  const fiscH = useVdFiscalPatches();
  const currentFsm = fsmH.effectiveFsm(venda);
  const effFiscal = fiscH.effectiveFiscal(venda);
  const action = vdFsmActions(venda, currentFsm, effFiscal);

  const vert = venda.vertical || "cv";
  const FSM_BY_VERTICAL = window.VENDAS_DATA?.FSM_BY_VERTICAL || {};
  const set = FSM_BY_VERTICAL[vert] || FSM_BY_VERTICAL.cv;
  const total = set.steps.length;

  // se ciclo completo
  if (!action) {
    return (
      <div className="vd-next vd-next-done">
        <div className="vd-next-h">
          <span className="vd-next-ic">✓</span>
          <div>
            <b>Ciclo concluído</b>
            <small>Venda finalizada · etapa {currentFsm + 1}/{total} ({set.steps[currentFsm]})</small>
          </div>
        </div>
      </div>
    );
  }

  const onAdvance = () => {
    if (action.gate) return; // bloqueado
    fsmH.advance(venda.id, action.target, action.label);
  };

  return (
    <div className={`vd-next vd-next-${action.color}`}>
      <div className="vd-next-h">
        <span className="vd-next-now">
          <small>Etapa atual</small>
          <b>{set.steps[currentFsm]}</b>
          <span className="vd-next-progress">
            {set.steps.map((_, i) => (
              <span key={i} className={`vd-next-dot ${i < currentFsm ? "done" : i === currentFsm ? "current" : ""}`}/>
            ))}
          </span>
        </span>
        <span className="vd-next-arr">→</span>
        <span className="vd-next-cta">
          <small>Próxima ação</small>
          {action.gate ? (
            <React.Fragment>
              <b className="vd-next-gate-lbl">
                <span className="vd-next-ic">⚠</span>
                {action.label} bloqueado
              </b>
            </React.Fragment>
          ) : (
            <button className={`vd-next-btn ${action.color}`} onClick={onAdvance}>
              <span className="vd-next-ic">{action.icon}</span>
              {action.label}
            </button>
          )}
        </span>
      </div>
      {action.gate ? (
        <div className="vd-next-gate">
          <span className="vd-next-gate-msg">{action.gate}</span>
          {action.gateAction === "emit-nfe"  && <button className="vd-next-gate-cta" onClick={() => onOpenEmit?.("nfe")}>📄 Emitir NF-e agora →</button>}
          {action.gateAction === "emit-nfse" && <button className="vd-next-gate-cta" onClick={() => onOpenEmit?.("nfse")}>📄 Emitir NFS-e agora →</button>}
        </div>
      ) : (
        action.desc && <p className="vd-next-desc">{action.desc}</p>
      )}
    </div>
  );
}
window.VdNextActionPanel = VdNextActionPanel;

// ──────────────────────────────────────────────────────────────
// COMPONENTE · VdNfeEmitModal — wizard 3 steps de emissão NF-e
// ──────────────────────────────────────────────────────────────
function VdNfeEmitModal({ venda, onClose, kind = "nfe" }) {
  const fiscH = useVdFiscalPatches();
  const [step, setStep] = useStateFl(1);
  const [transmitting, setTransmitting] = useStateFl(false);
  const [result, setResult]   = useStateFl(null); // {status: 'ok'|'bad', ...}
  const isNfe = kind === "nfe";
  const lbl = isNfe ? "NF-e" : "NFS-e";
  const items = (venda.itemsList || []).filter(it => isNfe ? it.type === "produto" : it.type === "servico");

  // step 1 — items review (CFOP/NCM/CST pra NFe, código serviço pra NFSe)
  const [fields, setFields] = useStateFl(() => items.map((it, i) => ({
    name: it.name, qty: it.qty, unit: it.unit, total: it.qty * it.unit,
    cfop: isNfe ? "5102" : null,
    ncm:  isNfe ? "4901.99.00" : null,
    cst:  isNfe ? "00" : null,
    codSrv: !isNfe ? "14.05" : null,
    issAliq: !isNfe ? "5.0" : null,
    issRet: !isNfe ? false : null,
  })));
  const totalNum = items.reduce((s, it) => s + it.qty * it.unit, 0);

  // step 2 — destinatario
  const [destCpfCnpj, setDestCpfCnpj] = useStateFl(venda.clientCnpj || "");
  const [destEmail, setDestEmail] = useStateFl(venda.contact || "");
  const [destEndereco, setDestEndereco] = useStateFl(venda.address || "Rua exemplo, 100 · São Paulo/SP · 01310-100");

  // step 3 — confirmação + envio SEFAZ
  const doTransmit = () => {
    setTransmitting(true);
    setStep(3);
    // simula 2.5s de SEFAZ
    setTimeout(() => {
      const fail = Math.random() < 0.18;
      if (fail) {
        const reasons = [
          "Código IBGE do destinatário inconsistente (cod 215)",
          "NCM informado não corresponde ao código de serviço",
          "Inscrição estadual do destinatário inválida",
          "Soma dos itens não confere com o valor total da nota",
        ];
        setResult({ status: "bad", reason: reasons[Math.floor(Math.random()*reasons.length)] });
      } else {
        const date = new Date().toISOString();
        const chave = _genChaveSefaz();
        const doc = {
          status: "ok",
          numero: String(1000 + Math.floor(Math.random()*9000)),
          serie:  "001",
          chave,
          date,
        };
        fiscH.setDoc(venda.id, kind, doc);
        setResult({ status: "ok", doc });
      }
      setTransmitting(false);
    }, 2500);
  };

  const retry = () => { setResult(null); setStep(1); };

  return (
    <div className="vd-emit-bd" onClick={onClose}>
      <div className="vd-emit-modal" onClick={e => e.stopPropagation()}>
        <header className="vd-emit-h">
          <div>
            <span className="vd-emit-tag">Emitir {lbl}</span>
            <h2>Venda #{venda.id} · {venda.client}</h2>
            <small>{items.length} {isNfe ? "produto(s)" : "serviço(s)"} · {_flFmt(totalNum)}</small>
          </div>
          <button className="vd-emit-x" onClick={onClose} title="Esc">✕</button>
        </header>

        {/* Steps indicator */}
        <nav className="vd-emit-steps">
          {["1. Revisar fiscal", "2. Destinatário", "3. Enviar SEFAZ"].map((s, i) => {
            const n = i + 1;
            const stat = step > n ? "done" : step === n ? "current" : "";
            return (
              <span key={i} className={`vd-emit-step ${stat}`}>
                <span className="vd-emit-step-n">{step > n ? "✓" : n}</span>
                <span className="vd-emit-step-l">{s.replace(/^\d+\.\s*/, "")}</span>
              </span>
            );
          })}
        </nav>

        <div className="vd-emit-body">
          {step === 1 && (
            <section>
              <h3>{isNfe ? "Dados fiscais por item" : "Código de serviço · alíquota"}</h3>
              <p className="vd-emit-help">
                {isNfe
                  ? "Confira CFOP, NCM e CST por linha. Valores padrão sugeridos pra material gráfico."
                  : "Confira código de serviço municipal e alíquota ISS. Marque retenção se cliente é tomador qualificado."}
              </p>
              <table className="vd-emit-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    {isNfe && <th>CFOP</th>}
                    {isNfe && <th>NCM</th>}
                    {isNfe && <th>CST</th>}
                    {!isNfe && <th>Cód. serviço</th>}
                    {!isNfe && <th>Alíq. ISS</th>}
                    {!isNfe && <th>ISS retido?</th>}
                    <th>Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  {fields.map((f, i) => (
                    <tr key={i}>
                      <td><b>{f.name}</b><small>{f.qty}× {_flFmt(f.unit)}</small></td>
                      {isNfe && <td><input value={f.cfop}
                        className={window.vdValidateCfop && !window.vdValidateCfop(f.cfop).ok ? "vd-input-error" : ""}
                        onChange={e => setFields(p => p.map((x,j) => j===i ? {...x, cfop:e.target.value.replace(/\D/g,'').slice(0,4)} : x))}/></td>}
                      {isNfe && <td><input value={f.ncm}
                        className={window.vdValidateNcm && !window.vdValidateNcm(f.ncm).ok ? "vd-input-error" : ""}
                        onChange={e => setFields(p => p.map((x,j) => j===i ? {...x, ncm:e.target.value} : x))}/></td>}
                      {isNfe && <td><input value={f.cst}
                        className={window.vdValidateCstCsosn && !window.vdValidateCstCsosn(f.cst, "lucro").ok ? "vd-input-error" : ""}
                        onChange={e => setFields(p => p.map((x,j) => j===i ? {...x, cst:e.target.value} : x))}/></td>}
                      {!isNfe && <td>
                        <select value={f.codSrv} onChange={e => setFields(p => p.map((x,j) => j===i ? {...x, codSrv:e.target.value} : x))}>
                          <option value="14.05">14.05 · Restauração, recond., acond.</option>
                          <option value="13.05">13.05 · Composição gráfica</option>
                          <option value="13.04">13.04 · Reprografia · cópias</option>
                          <option value="17.05">17.05 · Inserção de textos</option>
                          <option value="23.01">23.01 · Serviços de programação visual</option>
                        </select>
                      </td>}
                      {!isNfe && <td><input value={f.issAliq}
                        className={window.vdValidateIssAliq && !window.vdValidateIssAliq(f.issAliq).ok ? "vd-input-error" : ""}
                        onChange={e => setFields(p => p.map((x,j) => j===i ? {...x, issAliq:e.target.value} : x))}/>%</td>}
                      {!isNfe && <td>
                        <input type="checkbox" checked={f.issRet} onChange={e => setFields(p => p.map((x,j) => j===i ? {...x, issRet:e.target.checked} : x))}/>
                      </td>}
                      <td className="vd-emit-num">{_flFmt(f.total)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {/* Sumário de validações do step 1 */}
              {(() => {
                if (!window.vdValidateCfop || !window.vdValidateNcm || !window.vdValidateIssAliq) return null;
                const errs = [];
                fields.forEach((f, i) => {
                  if (isNfe) {
                    const cf = window.vdValidateCfop(f.cfop);
                    const nc = window.vdValidateNcm(f.ncm);
                    const ct = window.vdValidateCstCsosn(f.cst, "lucro");
                    if (!cf.ok) errs.push(`Linha ${i+1} (CFOP): ${cf.msg}`);
                    if (!nc.ok) errs.push(`Linha ${i+1} (NCM): ${nc.msg}`);
                    if (!ct.ok) errs.push(`Linha ${i+1} (CST): ${ct.msg}`);
                  } else {
                    const ia = window.vdValidateIssAliq(f.issAliq);
                    if (!ia.ok) errs.push(`Linha ${i+1} (ISS): ${ia.msg}`);
                  }
                });
                if (errs.length === 0) return null;
                return (
                  <div className="vd-emit-errs">
                    <b>⚠ {errs.length} erro(s) detectado(s):</b>
                    <ul>{errs.map((e, i) => <li key={i}>{e}</li>)}</ul>
                  </div>
                );
              })()}
            </section>
          )}

          {step === 2 && (
            <section>
              <h3>Destinatário</h3>
              <p className="vd-emit-help">Confirme dados do tomador. Esses dados vão pra SEFAZ exatamente como estão aqui.</p>
              <div className="vd-emit-fields">
                <label>Nome / Razão social
                  <input value={venda.client} readOnly/>
                </label>
                <label>CPF / CNPJ
                  <input 
                    value={destCpfCnpj} 
                    onChange={e => setDestCpfCnpj(window.vdMaskCpfCnpj ? window.vdMaskCpfCnpj(e.target.value) : e.target.value)} 
                    placeholder="00.000.000/0000-00"
                    className={window.vdValidateCpfCnpj && !window.vdValidateCpfCnpj(destCpfCnpj).ok ? "vd-input-error" : ""}/>
                  {window.VdFieldError && window.vdValidateCpfCnpj && <window.VdFieldError validation={window.vdValidateCpfCnpj(destCpfCnpj)}/>}
                </label>
                <label>E-mail (recebe DANFE/DANFS-e)
                  <input 
                    value={destEmail} 
                    onChange={e => setDestEmail(e.target.value)} 
                    placeholder="contato@cliente.com.br"
                    className={window.vdValidateEmail && !window.vdValidateEmail(destEmail).ok ? "vd-input-error" : ""}/>
                  {window.VdFieldError && window.vdValidateEmail && <window.VdFieldError validation={window.vdValidateEmail(destEmail)}/>}
                </label>
                <label className="vd-emit-fwide">Endereço completo
                  <input value={destEndereco} onChange={e => setDestEndereco(e.target.value)}/>
                </label>
              </div>
            </section>
          )}

          {step === 3 && (
            <section>
              {transmitting && (
                <div className="vd-emit-trans">
                  <div className="vd-emit-spinner"/>
                  <h3>Transmitindo pra SEFAZ…</h3>
                  <p>Aguarde — não feche esta janela. Tempo médio: 2-3 segundos.</p>
                  <ol className="vd-emit-trans-steps">
                    <li className="active">Validando XML</li>
                    <li className="active">Assinando com certificado A1</li>
                    <li className="active">Enviando ao webservice</li>
                    <li>Aguardando autorização</li>
                  </ol>
                </div>
              )}
              {!transmitting && result?.status === "ok" && (
                <div className="vd-emit-ok">
                  <div className="vd-emit-ok-ic">✓</div>
                  <h3>{lbl} autorizada pela SEFAZ</h3>
                  <dl>
                    <dt>Número</dt><dd>{result.doc.numero}</dd>
                    <dt>Série</dt><dd>{result.doc.serie}</dd>
                    <dt>Chave</dt><dd className="vd-emit-chave">{result.doc.chave.replace(/(\d{4})/g, "$1 ").trim()}</dd>
                  </dl>
                  <div className="vd-emit-ctas">
                    <button className="vd-emit-btn ghost">⎙ Baixar DANFE PDF</button>
                    <button className="vd-emit-btn ghost">{"</>"} Baixar XML</button>
                    <button className="vd-emit-btn primary" onClick={onClose}>Concluir →</button>
                  </div>
                </div>
              )}
              {!transmitting && result?.status === "bad" && (
                <div className="vd-emit-bad">
                  <div className="vd-emit-bad-ic">✕</div>
                  <h3>SEFAZ rejeitou a transmissão</h3>
                  <p className="vd-emit-reason">{result.reason}</p>
                  <p className="vd-emit-help">Corrija os dados acima e tente novamente. O documento não foi gravado.</p>
                  <div className="vd-emit-ctas">
                    <button className="vd-emit-btn ghost"  onClick={onClose}>Cancelar</button>
                    <button className="vd-emit-btn primary" onClick={retry}>← Corrigir e reenviar</button>
                  </div>
                </div>
              )}
            </section>
          )}
        </div>

        {/* Footer só quando NÃO está transmitindo nem mostrando resultado */}
        {!transmitting && !result && (() => {
          // Validar step atual antes de habilitar "Avançar"
          let stepHasErrors = false;
          if (step === 1 && isNfe && window.vdValidateNcm && window.vdValidateCfop) {
            stepHasErrors = fields.some(f =>
              !window.vdValidateNcm(f.ncm).ok ||
              !window.vdValidateCfop(f.cfop).ok
            );
          }
          if (step === 1 && !isNfe && window.vdValidateIssAliq) {
            stepHasErrors = fields.some(f => !window.vdValidateIssAliq(f.issAliq).ok);
          }
          if (step === 2 && window.vdValidateCpfCnpj && window.vdValidateEmail) {
            stepHasErrors = !window.vdValidateCpfCnpj(destCpfCnpj).ok || !window.vdValidateEmail(destEmail).ok;
          }
          return (
            <footer className="vd-emit-foot">
              {step > 1 && <button className="vd-emit-btn ghost" onClick={() => setStep(step - 1)}>← Voltar</button>}
              <span className="vd-emit-foot-sp"/>
              {stepHasErrors && <span className="vd-emit-foot-err">⚠ Corrija os campos em vermelho</span>}
              <button className="vd-emit-btn ghost" onClick={onClose}>Cancelar</button>
              {step < 3 && <button className="vd-emit-btn primary" disabled={stepHasErrors} onClick={() => setStep(step + 1)}>Avançar →</button>}
              {step === 3 && <button className="vd-emit-btn primary" disabled={stepHasErrors} onClick={doTransmit}>📤 Transmitir SEFAZ</button>}
            </footer>
          );
        })()}
      </div>
    </div>
  );
}
window.VdNfeEmitModal = VdNfeEmitModal;
window.VdNfseEmitModal = VdNfeEmitModal; // alias — mesmo componente aceita kind

// ──────────────────────────────────────────────────────────────
// REFINO C · 2026-05-26 — Toast feedback global
// ──────────────────────────────────────────────────────────────
// Sistema independente · monta sozinho em <div id="__vd_toast_root">
// API: window.vdToast(msg, kind, dur) — kind: ok | warn | info | bad
// Hook reativo via custom event "oimpresso:toast"
// Auto-dispara em: oimpresso:fsm-advance · oimpresso:fiscal-patched
// ──────────────────────────────────────────────────────────────

window.vdToast = (msg, kind = "info", dur = 3200) => {
  window.dispatchEvent(new CustomEvent("oimpresso:toast", { detail: { msg, kind, dur, id: Date.now() + Math.random() } }));
};

function VdToastHost() {
  const [items, setItems] = useStateFl([]);

  useEffectFl(() => {
    const onToast = (e) => {
      const t = e.detail;
      setItems(prev => [...prev, t]);
      setTimeout(() => setItems(prev => prev.filter(x => x.id !== t.id)), t.dur);
    };
    const onFsmAdv = (e) => {
      const { vendaId, fsm } = e.detail || {};
      const venda = (window.VENDAS_DATA?.VENDAS_LIST || []).find(v => v.id === vendaId);
      if (!venda) return;
      // Glossário BR: toast contextual por etapa
      if (fsm === 2) {
        window.vdToast(`#${vendaId} faturada · título no contas a receber`, "ok", 3200);
        return;
      }
      if (fsm === 4) {
        const tit = "R-" + (1500 + Math.floor(Math.random() * 9500));
        window.vdToast(`Pagamento recebido · #${vendaId} · ${tit} baixado`, "ok", 3600);
        return;
      }
      const set = window.VENDAS_DATA?.FSM_BY_VERTICAL?.[venda.vertical || "cv"] || window.VENDAS_DATA?.FSM_BY_VERTICAL?.cv;
      const lbl = set?.steps?.[fsm] || `etapa ${fsm + 1}`;
      window.vdToast(`#${vendaId} → ${lbl}`, "ok", 2800);
    };
    const onFiscPatched = (e) => {
      const { vendaId, kind } = e.detail || {};
      window.vdToast(`${kind === "nfe" ? "NF-e" : "NFS-e"} autorizada · #${vendaId}`, "ok", 3600);
    };
    window.addEventListener("oimpresso:toast", onToast);
    window.addEventListener("oimpresso:fsm-advance", onFsmAdv);
    window.addEventListener("oimpresso:fiscal-patched", onFiscPatched);
    return () => {
      window.removeEventListener("oimpresso:toast", onToast);
      window.removeEventListener("oimpresso:fsm-advance", onFsmAdv);
      window.removeEventListener("oimpresso:fiscal-patched", onFiscPatched);
    };
  }, []);

  if (!items.length) return null;
  return (
    <div className="vd-toast-stack">
      {items.map(t => (
        <div key={t.id} className={`vd-toast vd-toast-${t.kind}`}>
          <span className="vd-toast-ic">{t.kind === "ok" ? "✓" : t.kind === "bad" ? "✕" : t.kind === "warn" ? "⚠" : "i"}</span>
          <span className="vd-toast-msg">{t.msg}</span>
        </div>
      ))}
    </div>
  );
}
window.VdToastHost = VdToastHost;

// Auto-mount em portal global (sem precisar de patch em vendas-page.jsx)
(function() {
  const tryMount = () => {
    if (document.getElementById("__vd_toast_root")) return;
    if (!document.body) { document.addEventListener("DOMContentLoaded", tryMount); return; }
    const host = document.createElement("div");
    host.id = "__vd_toast_root";
    document.body.appendChild(host);
    if (window.ReactDOM?.createRoot) {
      window.ReactDOM.createRoot(host).render(<VdToastHost/>);
    } else if (window.ReactDOM?.render) {
      window.ReactDOM.render(<VdToastHost/>, host);
    }
  };
  setTimeout(tryMount, 200);
})();


// ──────────────────────────────────────────────────────────────
// REFINO E · 2026-05-26 — Bulk Emit NF-e/NFS-e em lote
// ──────────────────────────────────────────────────────────────
// Modal sequencial: processa N vendas selecionadas, emitindo
// NF-e/NFS-e conforme tipo de item. Cada uma vira uma transmissão
// SEFAZ simulada de ~1.5s. Mostra progresso em tempo real.
// ──────────────────────────────────────────────────────────────
function VdBulkEmitFlow({ vendaIds, onClose }) {
  const fiscH = useVdFiscalPatches();
  const VENDAS_LIST = window.VENDAS_DATA?.VENDAS_LIST || [];
  const vendas = vendaIds.map(id => VENDAS_LIST.find(v => v.id === id)).filter(Boolean);

  // monta plano de emissões: cada venda pode precisar de 0, 1 ou 2 docs
  const plan = useMemoFl(() => {
    const out = [];
    vendas.forEach(v => {
      const hasProd = (v.itemsList || []).some(i => i.type === "produto");
      const hasSrv  = (v.itemsList || []).some(i => i.type === "servico");
      const fisc    = fiscH.effectiveFiscal(v);
      if (hasProd && !fisc.nfe)  out.push({ vendaId: v.id, client: v.client, kind: "nfe",  total: v.totalNum });
      if (hasSrv  && !fisc.nfse) out.push({ vendaId: v.id, client: v.client, kind: "nfse", total: v.totalNum });
    });
    return out;
  }, [vendaIds.join("|")]);

  const [idx, setIdx]       = useStateFl(0);
  const [phase, setPhase]   = useStateFl("ready"); // ready | running | done
  const [results, setResults] = useStateFl([]);  // [{vendaId, kind, status, ...}]

  const startBatch = async () => {
    setPhase("running");
    for (let i = 0; i < plan.length; i++) {
      setIdx(i);
      await new Promise(r => setTimeout(r, 1400));
      const fail = Math.random() < 0.12;
      const item = plan[i];
      if (fail) {
        setResults(prev => [...prev, { ...item, status: "bad", reason: "Inscrição estadual inconsistente" }]);
      } else {
        const doc = {
          status: "ok",
          numero: String(1000 + Math.floor(Math.random()*9000)),
          serie: "001",
          chave: _genChaveSefaz(),
          date: new Date().toISOString(),
        };
        fiscH.setDoc(item.vendaId, item.kind, doc);
        setResults(prev => [...prev, { ...item, status: "ok", doc }]);
      }
    }
    setIdx(plan.length);
    setPhase("done");
  };

  const okCt  = results.filter(r => r.status === "ok").length;
  const badCt = results.filter(r => r.status === "bad").length;

  return (
    <div className="vd-emit-bd" onClick={phase !== "running" ? onClose : null}>
      <div className="vd-emit-modal vd-bulk-modal" onClick={e => e.stopPropagation()}>
        <header className="vd-emit-h">
          <div>
            <span className="vd-emit-tag">Emissão em lote</span>
            <h2>{plan.length} documento(s) · {vendas.length} venda(s)</h2>
            <small>NF-e + NFS-e mistas serão emitidas em sequência. SEFAZ aceita uma transmissão por vez.</small>
          </div>
          {phase !== "running" && <button className="vd-emit-x" onClick={onClose} title="Esc">✕</button>}
        </header>

        <div className="vd-emit-body vd-bulk-body">
          {/* progress bar */}
          <div className="vd-bulk-progress">
            <div className="vd-bulk-bar">
              <div className="vd-bulk-fill"   style={{width: (idx / Math.max(plan.length, 1) * 100) + "%"}}/>
              <div className="vd-bulk-fill-ok"  style={{width: (okCt  / Math.max(plan.length, 1) * 100) + "%"}}/>
              <div className="vd-bulk-fill-bad" style={{width: (badCt / Math.max(plan.length, 1) * 100) + "%", left: (okCt / Math.max(plan.length, 1) * 100) + "%"}}/>
            </div>
            <span className="vd-bulk-counter">
              {phase === "done" ? `${okCt} autorizadas · ${badCt} rejeitadas` : `${idx} de ${plan.length}`}
            </span>
          </div>

          {/* lista detalhada */}
          <ul className="vd-bulk-list">
            {plan.map((item, i) => {
              const r = results[i];
              const isCur = phase === "running" && i === idx;
              let stat = "pending";
              if (r?.status === "ok")  stat = "ok";
              if (r?.status === "bad") stat = "bad";
              if (isCur)               stat = "running";
              return (
                <li key={i} className={`vd-bulk-item vd-bulk-${stat}`}>
                  <span className="vd-bulk-ic">
                    {stat === "ok"      && "✓"}
                    {stat === "bad"     && "✕"}
                    {stat === "running" && <span className="vd-emit-spinner vd-bulk-spinner"/>}
                    {stat === "pending" && "○"}
                  </span>
                  <span className="vd-bulk-tag-doc">{item.kind === "nfe" ? "NF-e" : "NFS-e"}</span>
                  <span className="vd-bulk-vendaid">#{item.vendaId}</span>
                  <span className="vd-bulk-client">{item.client}</span>
                  <span className="vd-bulk-total">{_flFmt(item.total)}</span>
                  {r?.status === "ok" && <small className="vd-bulk-doc">nº {r.doc.numero}</small>}
                  {r?.status === "bad" && <small className="vd-bulk-fail">{r.reason}</small>}
                </li>
              );
            })}
          </ul>
        </div>

        <footer className="vd-emit-foot">
          {phase === "ready" && (
            <React.Fragment>
              <button className="vd-emit-btn ghost" onClick={onClose}>Cancelar</button>
              <span className="vd-emit-foot-sp"/>
              <button className="vd-emit-btn primary" onClick={startBatch} disabled={plan.length === 0}>
                📤 Iniciar transmissão SEFAZ ({plan.length})
              </button>
            </React.Fragment>
          )}
          {phase === "running" && (
            <span className="vd-bulk-running-msg">
              <span className="vd-emit-spinner vd-bulk-spinner"/>
              Transmitindo {idx + 1} de {plan.length} — não feche…
            </span>
          )}
          {phase === "done" && (
            <React.Fragment>
              <span className="vd-bulk-summary">
                {badCt === 0 ? "Lote concluído sem erros." : `${badCt} rejeição(ões) — retransmitir manualmente.`}
              </span>
              <span className="vd-emit-foot-sp"/>
              <button className="vd-emit-btn primary" onClick={onClose}>Concluir →</button>
            </React.Fragment>
          )}
        </footer>
      </div>
    </div>
  );
}
window.VdBulkEmitFlow = VdBulkEmitFlow;

// ──────────────────────────────────────────────────────────────
// REFINO H · 2026-05-26 — Timeline rica (FSM + emissões fiscais)
// ──────────────────────────────────────────────────────────────
function VdRichTimeline({ venda }) {
  const fsmH  = useVdFsmPatches();
  const fiscH = useVdFiscalPatches();
  const fsmHistory  = fsmH.history(venda.id);
  const effFiscal   = fiscH.effectiveFiscal(venda);

  // monta lista única ordenada
  const set = window.VENDAS_DATA?.FSM_BY_VERTICAL?.[venda.vertical || "cv"] || window.VENDAS_DATA?.FSM_BY_VERTICAL?.cv;
  const evts = [];

  // 1. abertura da venda
  evts.push({
    at: `${venda.date}T${(venda.time || "00:00")}:00`,
    icon: "📝",
    title: "Venda registrada",
    sub: `Por ${venda.seller || "—"} · ${(venda.itemsList || []).length} item(s) · ${(venda.totalNum || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" })}`,
    color: "neutral",
  });

  // 2. fiscal events
  if (effFiscal.nfe) {
    evts.push({
      at: effFiscal.nfe.date,
      icon: effFiscal.nfe.status === "ok" ? "📄" : "⚠",
      title: effFiscal.nfe.status === "ok" ? "NF-e autorizada pela SEFAZ" : "NF-e rejeitada",
      sub: effFiscal.nfe.status === "ok"
        ? `nº ${effFiscal.nfe.numero}/${effFiscal.nfe.serie} · chave ${(effFiscal.nfe.chave || "").slice(-8)}`
        : effFiscal.nfe.failReason || "",
      color: effFiscal.nfe.status === "ok" ? "ok" : "bad",
    });
  }
  if (effFiscal.nfse) {
    evts.push({
      at: effFiscal.nfse.date,
      icon: effFiscal.nfse.status === "ok" ? "📄" : "⚠",
      title: effFiscal.nfse.status === "ok" ? "NFS-e autorizada pela prefeitura" : "NFS-e rejeitada",
      sub: effFiscal.nfse.status === "ok"
        ? `nº ${effFiscal.nfse.numero}/${effFiscal.nfse.serie} · chave ${(effFiscal.nfse.chave || "").slice(-8)}`
        : effFiscal.nfse.failReason || "",
      color: effFiscal.nfse.status === "ok" ? "ok" : "bad",
    });
  }

  // 3. FSM advance history
  fsmHistory.forEach(h => {
    evts.push({
      at: h.at,
      icon: "→",
      title: `Avançou pra "${set?.steps?.[h.fsm] || `etapa ${h.fsm + 1}`}"`,
      sub: h.note || "",
      color: "blue",
    });
  });

  // ordena por data ASC
  evts.sort((a, b) => (a.at > b.at ? 1 : -1));

  return (
    <div className="vd-rich-tl">
      <h3>Linha do tempo</h3>
      {evts.length === 0 && <p className="vd-empty-state">Sem eventos registrados ainda.</p>}
      <ol className="vd-rich-tl-list">
        {evts.map((e, i) => {
          const d = new Date(e.at);
          const dateStr = isNaN(d) ? e.at : d.toLocaleDateString("pt-BR");
          const timeStr = isNaN(d) ? "" : d.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
          return (
            <li key={i} className={`vd-rich-tl-it vd-rich-tl-${e.color}`}>
              <span className="vd-rich-tl-ic">{e.icon}</span>
              <div className="vd-rich-tl-c">
                <div className="vd-rich-tl-h">
                  <b>{e.title}</b>
                  <span className="vd-rich-tl-when">{dateStr} <small>{timeStr}</small></span>
                </div>
                {e.sub && <small className="vd-rich-tl-sub">{e.sub}</small>}
              </div>
            </li>
          );
        })}
      </ol>
    </div>
  );
}
window.VdRichTimeline = VdRichTimeline;


// ──────────────────────────────────────────────────────────────
// REFINO I · 2026-05-26 — Recibo térmico 80mm imprimível
// ──────────────────────────────────────────────────────────────
// Overlay full-page com layout monospace 80mm de largura.
// window.print() imprime SÓ o recibo (resto da página é hidden via @media print).
// ──────────────────────────────────────────────────────────────
function VdReceiptThermal({ venda, onClose }) {
  const v = venda;
  const fiscH = useVdFiscalPatches();
  const effFisc = fiscH.effectiveFiscal(v);

  useEffectFl(() => {
    const onKey = (e) => { if (e.key === "Escape") { e.preventDefault(); onClose(); } };
    window.addEventListener("keydown", onKey);
    document.body.classList.add("vd-print-receipt");
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.classList.remove("vd-print-receipt");
    };
  }, [onClose]);

  const items     = v.itemsList || [];
  const subtotal  = items.reduce((s, it) => s + (it.qty || 0) * (it.unit || 0), 0);
  const discount  = v.discount || 0;
  const totalNum  = v.totalNum || (subtotal - discount);
  const fmt = (n) => (n || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
  const fmtCompact = (n) => (n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  // formatação de data/hora
  const dateBR = v.date ? `${v.date.slice(8,10)}/${v.date.slice(5,7)}/${v.date.slice(0,4)}` : "—";
  const timeBR = v.time || "—";

  // chave SEFAZ compacta (últimos 8 dígitos)
  const nfeChave  = effFisc.nfe?.chave;
  const nfseChave = effFisc.nfse?.chave;

  return (
    <div className="vd-receipt-bd" onClick={onClose}>
      <div className="vd-receipt-toolbar">
        <button className="os-btn ghost" onClick={onClose}>← Fechar</button>
        <span className="vd-receipt-tag">Recibo térmico · #{v.id}</span>
        <button className="os-btn primary" onClick={() => window.print()}>⎙ Imprimir</button>
      </div>

      <div className="vd-receipt-paper" onClick={e => e.stopPropagation()}>
        {/* HEADER */}
        <div className="vd-rcp-h">
          <div className="vd-rcp-brand">OIMPRESSO</div>
          <div className="vd-rcp-sub">Comunicação Visual</div>
          <div className="vd-rcp-meta">
            CNPJ 12.345.678/0001-90<br/>
            Rua Exemplo 100 · São Paulo/SP<br/>
            (11) 4002-8922
          </div>
        </div>

        <div className="vd-rcp-sep"/>

        {/* INFO VENDA */}
        <div className="vd-rcp-info">
          <div className="vd-rcp-row"><span>VENDA</span><b>#{v.id}</b></div>
          <div className="vd-rcp-row"><span>DATA</span><b>{dateBR} {timeBR}</b></div>
          <div className="vd-rcp-row"><span>VENDEDOR</span><b>{v.seller || "—"}</b></div>
          {v.client !== "Consumidor Final" && (
            <div className="vd-rcp-row"><span>CLIENTE</span><b>{v.client}</b></div>
          )}
          {v.clientCnpj && (
            <div className="vd-rcp-row"><span>CPF/CNPJ</span><b>{v.clientCnpj}</b></div>
          )}
        </div>

        <div className="vd-rcp-sep"/>

        {/* ITENS */}
        <div className="vd-rcp-itens">
          <div className="vd-rcp-itens-h">
            <span>ITEM</span>
            <span>VL.UNIT</span>
            <span>TOTAL</span>
          </div>
          {items.map((it, i) => (
            <div key={i} className="vd-rcp-item">
              <div className="vd-rcp-item-name">{it.name}</div>
              <div className="vd-rcp-item-row">
                <span className="vd-rcp-item-qty">{it.qty}× {fmtCompact(it.unit)}</span>
                <span className="vd-rcp-item-tot">{fmtCompact(it.qty * it.unit)}</span>
              </div>
            </div>
          ))}
        </div>

        <div className="vd-rcp-sep"/>

        {/* TOTAIS */}
        <div className="vd-rcp-totais">
          <div className="vd-rcp-row"><span>SUBTOTAL</span><b>{fmt(subtotal)}</b></div>
          {discount > 0 && (
            <div className="vd-rcp-row"><span>DESCONTO</span><b>-{fmt(discount)}</b></div>
          )}
          <div className="vd-rcp-row vd-rcp-row-total">
            <span>TOTAL</span><b>{fmt(totalNum)}</b>
          </div>
        </div>

        <div className="vd-rcp-sep"/>

        {/* PAGAMENTO */}
        <div className="vd-rcp-pgto">
          <div className="vd-rcp-row"><span>PAGAMENTO</span><b>{v.payment || "—"}</b></div>
          {v.installments > 1 && (
            <div className="vd-rcp-row"><span>PARCELAS</span><b>{v.installments}× de {fmt(totalNum / v.installments)}</b></div>
          )}
          {v.payTerm > 0 && (
            <div className="vd-rcp-row"><span>PRAZO</span><b>{v.payTerm} dias</b></div>
          )}
        </div>

        {/* FISCAL */}
        {(nfeChave || nfseChave) && (
          <React.Fragment>
            <div className="vd-rcp-sep"/>
            <div className="vd-rcp-fiscal">
              {nfeChave && (
                <div>
                  <small>NF-e nº {effFisc.nfe?.numero}/{effFisc.nfe?.serie}</small>
                  <div className="vd-rcp-chave">{nfeChave.replace(/(\d{4})/g, "$1 ").trim()}</div>
                </div>
              )}
              {nfseChave && (
                <div>
                  <small>NFS-e nº {effFisc.nfse?.numero}/{effFisc.nfse?.serie}</small>
                  <div className="vd-rcp-chave">{nfseChave.replace(/(\d{4})/g, "$1 ").trim()}</div>
                </div>
              )}
              <small className="vd-rcp-fiscal-help">
                Consulte autenticidade em www.nfe.fazenda.gov.br
              </small>
            </div>
          </React.Fragment>
        )}

        <div className="vd-rcp-sep"/>

        {/* FOOTER */}
        <div className="vd-rcp-foot">
          <div className="vd-rcp-thanks">Obrigado pela preferência!</div>
          <small className="vd-rcp-disclaimer">
            {(nfeChave || nfseChave)
              ? "Recibo auxiliar · comprovante para o consumidor."
              : "Este documento NÃO é um documento fiscal."}
          </small>
          <div className="vd-rcp-stamp">
            Impresso em {new Date().toLocaleDateString("pt-BR")} {new Date().toLocaleTimeString("pt-BR", { hour:"2-digit", minute:"2-digit" })}
          </div>
        </div>
      </div>
    </div>
  );
}
window.VdReceiptThermal = VdReceiptThermal;


// ──────────────────────────────────────────────────────────────
// OPÇÃO B · 2026-05-26 — Validações fiscais brasileiras
// Inspirado em Bling/Tiny/Omie/eGestor
// Helpers puros + hook validador + UI inline
// ──────────────────────────────────────────────────────────────

// CPF — algoritmo oficial Receita Federal (11 dígitos com 2 DVs)
window.vdValidateCpf = function(cpf) {
  const s = (cpf || "").replace(/\D/g, "");
  if (s.length !== 11) return { ok: false, msg: "CPF deve ter 11 dígitos" };
  if (/^(\d)\1{10}$/.test(s)) return { ok: false, msg: "CPF inválido (todos dígitos iguais)" };
  let sum = 0;
  for (let i = 0; i < 9; i++) sum += parseInt(s[i]) * (10 - i);
  let dv1 = (sum * 10) % 11;
  if (dv1 === 10) dv1 = 0;
  if (dv1 !== parseInt(s[9])) return { ok: false, msg: "CPF inválido (DV1 incorreto)" };
  sum = 0;
  for (let i = 0; i < 10; i++) sum += parseInt(s[i]) * (11 - i);
  let dv2 = (sum * 10) % 11;
  if (dv2 === 10) dv2 = 0;
  if (dv2 !== parseInt(s[10])) return { ok: false, msg: "CPF inválido (DV2 incorreto)" };
  return { ok: true };
};

// CNPJ — algoritmo oficial Receita Federal (14 dígitos com 2 DVs)
window.vdValidateCnpj = function(cnpj) {
  const s = (cnpj || "").replace(/\D/g, "");
  if (s.length !== 14) return { ok: false, msg: "CNPJ deve ter 14 dígitos" };
  if (/^(\d)\1{13}$/.test(s)) return { ok: false, msg: "CNPJ inválido (todos dígitos iguais)" };
  const w1 = [5,4,3,2,9,8,7,6,5,4,3,2];
  const w2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
  let sum = 0;
  for (let i = 0; i < 12; i++) sum += parseInt(s[i]) * w1[i];
  let dv1 = sum % 11;
  dv1 = dv1 < 2 ? 0 : 11 - dv1;
  if (dv1 !== parseInt(s[12])) return { ok: false, msg: "CNPJ inválido (DV1 incorreto)" };
  sum = 0;
  for (let i = 0; i < 13; i++) sum += parseInt(s[i]) * w2[i];
  let dv2 = sum % 11;
  dv2 = dv2 < 2 ? 0 : 11 - dv2;
  if (dv2 !== parseInt(s[13])) return { ok: false, msg: "CNPJ inválido (DV2 incorreto)" };
  return { ok: true };
};

// CPF ou CNPJ — auto-detect por contagem de dígitos
window.vdValidateCpfCnpj = function(value) {
  const s = (value || "").replace(/\D/g, "");
  if (s.length === 11) return window.vdValidateCpf(s);
  if (s.length === 14) return window.vdValidateCnpj(s);
  if (s.length === 0)  return { ok: false, msg: "Obrigatório" };
  return { ok: false, msg: "Deve ter 11 (CPF) ou 14 (CNPJ) dígitos" };
};

// Máscara dinâmica CPF/CNPJ
window.vdMaskCpfCnpj = function(value) {
  const s = (value || "").replace(/\D/g, "").slice(0, 14);
  if (s.length <= 11) {
    return s
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
  }
  return s
    .replace(/(\d{2})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1.$2")
    .replace(/(\d{3})(\d)/, "$1/$2")
    .replace(/(\d{4})(\d{1,2})$/, "$1-$2");
};

// CEP — 8 dígitos formato 00000-000
window.vdValidateCep = function(cep) {
  const s = (cep || "").replace(/\D/g, "");
  if (s.length !== 8) return { ok: false, msg: "CEP deve ter 8 dígitos" };
  return { ok: true };
};
window.vdMaskCep = function(value) {
  return (value || "").replace(/\D/g, "").slice(0, 8).replace(/(\d{5})(\d)/, "$1-$2");
};

// NCM — 8 dígitos formato 0000.00.00 (validação formato; tabela TIPI seria ideal)
window.vdValidateNcm = function(ncm) {
  const s = (ncm || "").replace(/\D/g, "");
  if (s.length !== 8) return { ok: false, msg: "NCM deve ter 8 dígitos" };
  return { ok: true };
};

// CFOP — 4 dígitos, primeiro dígito determina natureza
// 5xxx = saída dentro do estado · 6xxx = saída interestadual · 7xxx = saída exterior
window.vdValidateCfop = function(cfop, destUf, emitUf) {
  const s = (cfop || "").replace(/\D/g, "");
  if (s.length !== 4) return { ok: false, msg: "CFOP deve ter 4 dígitos" };
  const first = parseInt(s[0]);
  if (![1,2,3,5,6,7].includes(first)) {
    return { ok: false, msg: "Primeiro dígito do CFOP deve ser 1, 2, 3, 5, 6 ou 7" };
  }
  // se temos UF emit/dest, validar consistência
  if (destUf && emitUf) {
    if (first === 5 && destUf !== emitUf) {
      return { ok: false, msg: `CFOP 5xxx é dentro do estado. Cliente em ${destUf}, emissor em ${emitUf} — use 6xxx.` };
    }
    if (first === 6 && destUf === emitUf) {
      return { ok: false, msg: `CFOP 6xxx é interestadual. Cliente está em ${destUf} (mesmo estado) — use 5xxx.` };
    }
  }
  return { ok: true };
};

// CST — 2 dígitos (Lucro Real) · CSOSN — 3 dígitos (Simples Nacional)
window.vdValidateCstCsosn = function(value, regime) {
  const s = (value || "").replace(/\D/g, "");
  if (regime === "simples") {
    if (s.length !== 3) return { ok: false, msg: "CSOSN deve ter 3 dígitos (Simples Nacional)" };
    if (!["101","102","103","201","202","203","300","400","500","900"].includes(s)) {
      return { ok: false, msg: "CSOSN inválido. Válidos: 101, 102, 103, 201, 202, 203, 300, 400, 500, 900" };
    }
  } else {
    if (s.length !== 2) return { ok: false, msg: "CST deve ter 2 dígitos (Lucro Real/Presumido)" };
    if (!["00","10","20","30","40","41","50","51","60","70","90"].includes(s)) {
      return { ok: false, msg: "CST inválido. Válidos: 00, 10, 20, 30, 40, 41, 50, 51, 60, 70, 90" };
    }
  }
  return { ok: true };
};

// E-mail — RFC simplificado
window.vdValidateEmail = function(email) {
  const s = (email || "").trim();
  if (!s) return { ok: false, msg: "Obrigatório" };
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(s)) return { ok: false, msg: "Formato de e-mail inválido" };
  return { ok: true };
};

// Alíquota ISS — entre 2% e 5% (limites legais municipais)
window.vdValidateIssAliq = function(aliq) {
  const n = parseFloat((aliq || "").toString().replace(",", "."));
  if (isNaN(n)) return { ok: false, msg: "Alíquota deve ser número" };
  if (n < 2)  return { ok: false, msg: "Alíquota mínima ISS é 2% (LC 116/2003)" };
  if (n > 5)  return { ok: false, msg: "Alíquota máxima ISS é 5% (LC 116/2003)" };
  return { ok: true };
};

// Soma itens vs total — tolerância R$ [redacted Tier 0]
window.vdValidateItemsTotal = function(itemsSum, declaredTotal) {
  if (Math.abs(itemsSum - declaredTotal) > 0.01) {
    return {
      ok: false,
      msg: `Soma dos itens (${itemsSum.toFixed(2)}) difere do total (${declaredTotal.toFixed(2)})`,
    };
  }
  return { ok: true };
};

// ──────────────────────────────────────────────────────────────
// VdFieldError — UI inline pra erros de validação
// Usar: <VdFieldError validation={result}/>
// ──────────────────────────────────────────────────────────────
function VdFieldError({ validation }) {
  if (!validation || validation.ok) return null;
  return (
    <span className="vd-field-error">
      <span className="vd-field-error-ic">✕</span>
      {validation.msg}
    </span>
  );
}
window.VdFieldError = VdFieldError;


// ──────────────────────────────────────────────────────────────
// OPÇÃO C · 2026-05-26 — Impressão de Orçamento (A4 formal)
// Diferente do recibo térmico (80mm) e do transcript jurídico —
// é a proposta comercial enviada ao cliente antes da venda.
// ──────────────────────────────────────────────────────────────
function VdOrcamentoPrint({ venda, onClose }) {
  const v = venda;
  const fmt = (n) => (n || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
  const items = v.itemsList || [];
  const subtotal  = items.reduce((s, it) => s + (it.qty || 0) * (it.unit || 0), 0);
  const discount  = v.discount || 0;
  const totalNum  = v.totalNum || (subtotal - discount);

  // validade: 7 dias a partir de hoje (padrão de mercado)
  const today = new Date();
  const validade = new Date(today.getTime() + 7 * 86400000);
  const fmtDt = (d) => d.toLocaleDateString("pt-BR");

  // número do orçamento — deriva do ID da venda (V-XXXX → Q-XXXX)
  const orcNum = (v.id || "V-0000").replace(/^V-/, "Q-");

  useEffectFl(() => {
    const onKey = (e) => { if (e.key === "Escape") { e.preventDefault(); onClose(); } };
    window.addEventListener("keydown", onKey);
    document.body.classList.add("vd-print-orc");
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.classList.remove("vd-print-orc");
    };
  }, [onClose]);

  return (
    <div className="vd-orc-bd" onClick={onClose}>
      <div className="vd-orc-toolbar">
        <button className="os-btn ghost" onClick={onClose}>← Fechar</button>
        <span className="vd-orc-tag">Orçamento {orcNum} · {v.client}</span>
        <button className="os-btn primary" onClick={() => window.print()}>⎙ Imprimir / Salvar PDF</button>
      </div>

      <div className="vd-orc-page" onClick={e => e.stopPropagation()}>
        {/* HEADER */}
        <header className="vd-orc-h">
          <div className="vd-orc-brand-block">
            <div className="vd-orc-logo">OI</div>
            <div>
              <div className="vd-orc-brand">OIMPRESSO</div>
              <div className="vd-orc-brand-sub">Comunicação Visual</div>
              <div className="vd-orc-brand-meta">
                CNPJ 12.345.678/0001-90 · IE 123.456.789.012<br/>
                Rua Exemplo 100, São Paulo/SP · 01310-100<br/>
                (11) 4002-8922 · contato@oimpresso.com.br
              </div>
            </div>
          </div>
          <div className="vd-orc-num-block">
            <div className="vd-orc-num-label">ORÇAMENTO</div>
            <div className="vd-orc-num">{orcNum}</div>
            <dl className="vd-orc-num-meta">
              <dt>Data emissão</dt><dd>{fmtDt(today)}</dd>
              <dt>Válido até</dt><dd className="vd-orc-validade">{fmtDt(validade)}</dd>
              <dt>Vendedor</dt><dd>{v.seller || "—"}</dd>
            </dl>
          </div>
        </header>

        {/* DESTINATÁRIO */}
        <section className="vd-orc-dest">
          <h3>PROPOSTA PARA</h3>
          <div className="vd-orc-dest-grid">
            <div>
              <span className="vd-orc-dest-lbl">Razão social</span>
              <b>{v.client}</b>
            </div>
            <div>
              <span className="vd-orc-dest-lbl">CNPJ</span>
              <b>{v.clientCnpj || "—"}</b>
            </div>
            <div>
              <span className="vd-orc-dest-lbl">Contato</span>
              <b>{v.contact || v.seller || "—"}</b>
            </div>
            <div>
              <span className="vd-orc-dest-lbl">Telefone</span>
              <b>{v.phone || "—"}</b>
            </div>
          </div>
        </section>

        {/* ITENS */}
        <section className="vd-orc-itens">
          <h3>ITENS DA PROPOSTA</h3>
          <table className="vd-orc-tbl">
            <thead>
              <tr>
                <th style={{width: 32}}>#</th>
                <th>Descrição</th>
                <th style={{width: 60, textAlign:"right"}}>Qtd</th>
                <th style={{width: 90, textAlign:"right"}}>Vl. unit.</th>
                <th style={{width: 110, textAlign:"right"}}>Total</th>
              </tr>
            </thead>
            <tbody>
              {items.map((it, i) => (
                <tr key={i}>
                  <td className="vd-orc-tbl-n">{String(i + 1).padStart(2, "0")}</td>
                  <td>
                    <b>{it.name}</b>
                    {it.sku && <small className="vd-orc-tbl-sku">SKU {it.sku} · {it.type === "produto" ? "Material" : "Serviço"}</small>}
                  </td>
                  <td style={{textAlign:"right"}}>{it.qty}</td>
                  <td style={{textAlign:"right"}}>{fmt(it.unit)}</td>
                  <td style={{textAlign:"right"}} className="vd-orc-tbl-tot">{fmt(it.qty * it.unit)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={4} className="vd-orc-tbl-foot-lbl">Subtotal</td>
                <td style={{textAlign:"right"}}>{fmt(subtotal)}</td>
              </tr>
              {discount > 0 && (
                <tr>
                  <td colSpan={4} className="vd-orc-tbl-foot-lbl">Desconto comercial</td>
                  <td style={{textAlign:"right"}}>-{fmt(discount)}</td>
                </tr>
              )}
              <tr className="vd-orc-tbl-total-row">
                <td colSpan={4} className="vd-orc-tbl-foot-lbl">TOTAL</td>
                <td style={{textAlign:"right"}}>{fmt(totalNum)}</td>
              </tr>
            </tfoot>
          </table>
        </section>

        {/* CONDIÇÕES */}
        <section className="vd-orc-cond">
          <h3>CONDIÇÕES COMERCIAIS</h3>
          <ul>
            <li><b>Prazo de entrega:</b> 5 dias úteis após confirmação do pedido e aprovação da arte.</li>
            <li><b>Forma de pagamento:</b> {v.payment === "PIX" ? "PIX à vista" : v.payment || "A combinar"}{v.payTerm ? ` · prazo ${v.payTerm} dias` : ""}.</li>
            <li><b>Validade desta proposta:</b> 7 dias corridos a contar da data de emissão.</li>
            <li><b>Arte e revisão:</b> 1 (uma) revisão inclusa. Revisões adicionais R$ [redacted Tier 0] cada.</li>
            <li><b>Tributação:</b> valores já incluem impostos. Emissão de NF-e/NFS-e conforme natureza do item.</li>
            <li><b>Cancelamento:</b> em caso de cancelamento após início da produção, será cobrado proporcional ao executado.</li>
          </ul>
        </section>

        {/* OBSERVAÇÕES */}
        {v.clientNote && (
          <section className="vd-orc-obs">
            <h3>OBSERVAÇÕES</h3>
            <p>{v.clientNote}</p>
          </section>
        )}

        {/* ASSINATURAS */}
        <section className="vd-orc-sign">
          <div className="vd-orc-sign-col">
            <div className="vd-orc-sign-line"/>
            <div className="vd-orc-sign-lbl">
              <b>Aprovação do cliente</b>
              <small>{v.client}</small>
              <small>Data: ____ / ____ / ________</small>
            </div>
          </div>
          <div className="vd-orc-sign-col">
            <div className="vd-orc-sign-line"/>
            <div className="vd-orc-sign-lbl">
              <b>Oimpresso Comunicação Visual</b>
              <small>{v.seller || "—"}</small>
              <small>Data: {fmtDt(today)}</small>
            </div>
          </div>
        </section>

        {/* FOOTER */}
        <footer className="vd-orc-ft">
          <span>Orçamento {orcNum} · Emitido em {fmtDt(today)} · Página 1 de 1</span>
          <span>oimpresso.com.br</span>
        </footer>
      </div>
    </div>
  );
}
window.VdOrcamentoPrint = VdOrcamentoPrint;
