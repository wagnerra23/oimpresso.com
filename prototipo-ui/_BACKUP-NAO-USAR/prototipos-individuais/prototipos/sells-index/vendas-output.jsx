// vendas-output.jsx — Refino #4 KB-9.75 · Distribuição (2026-05-15)
// 4 features: Transcript PDF (jurídico) · Modo apresentação · Variáveis live · Art-slot
// Render acionado pelos botões do drawer em vendas-page.jsx

const { useState: useStateOut, useEffect: useEffectOut, useMemo: useMemoOut } = React;

const _outFmt = (n) => (n || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
const _outDate = (d) => d ? d.split("T")[0].split("-").reverse().join("/") : "—";
const _outTime = (d) => d ? d.split("T")[1]?.slice(0,5) || "" : "";

// ──────────────────────────────────────────────────────────────
// 1) TRANSCRIPT PDF — overlay full-page A4 imprimível
//    Tudo: cabeçalho, cliente, itens, fiscal completo, timeline,
//    comentários, audit, assinaturas. window.print() imprime só isso.
// ──────────────────────────────────────────────────────────────
function VdTranscriptPDF({ venda, onClose }) {
  const v = venda;
  const totalNum = v.totalNum || (v.itemsList || []).reduce((s, i) => s + i.qty * i.unit, 0);
  const audit = window.vdAuditTrail ? window.vdAuditTrail(v) : [];
  const comments = window.useVdItemComments ? window.useVdItemComments() : null;
  const allComments = comments
    ? (v.itemsList || []).map((it, idx) => ({ item: it, idx, list: comments.get(v.id, idx) || [] })).filter(x => x.list.length > 0)
    : [];

  useEffectOut(() => {
    const onKey = (e) => { if (e.key === "Escape") { e.preventDefault(); onClose(); } };
    window.addEventListener("keydown", onKey);
    // marca body pra @media print esconder tudo menos o transcript
    document.body.classList.add("vd-print-mode");
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.classList.remove("vd-print-mode");
    };
  }, [onClose]);

  return (
    <div className="vd-trans-bd" onClick={onClose}>
      <div className="vd-trans-toolbar">
        <button className="os-btn ghost" onClick={onClose}>← Fechar</button>
        <span className="vd-trans-tag">Transcript completo · venda #{v.id}</span>
        <button className="os-btn primary" onClick={() => window.print()}>⎙ Imprimir / Salvar PDF</button>
      </div>

      <div className="vd-trans-page" onClick={e => e.stopPropagation()}>

        {/* ────── HEADER ────── */}
        <header className="vd-trans-h">
          <div>
            <div className="vd-trans-brand">OIMPRESSO</div>
            <div className="vd-trans-brand-sub">Comunicação Visual</div>
          </div>
          <div className="vd-trans-meta-co">
            <div>CNPJ 12.345.678/0001-90</div>
            <div>Rua das Gráficas, 123 — Centro</div>
            <div>(11) 4000-1234 · oimpresso.com</div>
          </div>
        </header>

        <div className="vd-trans-title">
          <h1>TRANSCRIPT DE VENDA <small>#{v.id}</small></h1>
          <span>Documento jurídico-comercial · gerado em {new Date().toLocaleDateString("pt-BR")} às {new Date().toLocaleTimeString("pt-BR",{hour:"2-digit",minute:"2-digit"})}</span>
        </div>

        {/* ────── CLIENTE + DADOS ────── */}
        <section className="vd-trans-grid">
          <div className="vd-trans-block">
            <small>Cliente</small>
            <h3>{v.client}</h3>
            {v.clientNote && <p>{v.clientNote}</p>}
          </div>
          <div className="vd-trans-block">
            <small>Atendido por</small>
            <h3>{v.seller || v.sellerId || "—"}</h3>
            <p>{v.date.split("-").reverse().join("/")} às {v.time}</p>
          </div>
          <div className="vd-trans-block">
            <small>Pagamento</small>
            <h3>{v.payment}{v.installments > 1 ? ` · ${v.installments}×` : ""}</h3>
            <p>Prazo: {v.payTerm || 0} dias</p>
          </div>
          <div className="vd-trans-block vd-trans-total">
            <small>Total da venda</small>
            <h3>{_outFmt(totalNum)}</h3>
          </div>
        </section>

        {/* ────── ITENS ────── */}
        <section className="vd-trans-section">
          <h2>Itens</h2>
          <table className="vd-trans-items">
            <thead>
              <tr><th>SKU</th><th>Descrição</th><th>Tipo</th><th className="num">Qtd</th><th className="num">Unitário</th><th className="num">Subtotal</th></tr>
            </thead>
            <tbody>
              {(v.itemsList || []).map((it, i) => (
                <tr key={i}>
                  <td className="mono">{it.sku}</td>
                  <td>{it.name}</td>
                  <td>{it.type === "produto" ? "Produto (NF-e)" : "Serviço (NFS-e)"}</td>
                  <td className="num">{it.qty}</td>
                  <td className="num">{_outFmt(it.unit)}</td>
                  <td className="num strong">{_outFmt(it.qty * it.unit)}</td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr><td colSpan={5} className="strong">Total</td><td className="num strong">{_outFmt(totalNum)}</td></tr>
            </tfoot>
          </table>
        </section>

        {/* ────── DOCUMENTOS FISCAIS ────── */}
        {(v.fiscal?.nfe || v.fiscal?.nfse) && (
          <section className="vd-trans-section">
            <h2>Documentos fiscais</h2>
            <table className="vd-trans-fiscal">
              <tbody>
                {v.fiscal?.nfe && (
                  <tr>
                    <th>NF-e modelo 55</th>
                    <td>
                      <div>Nº {v.fiscal.nfe.numero} · Série {v.fiscal.nfe.serie} · Status: <b>{v.fiscal.nfe.status === "ok" ? "Autorizada SEFAZ" : v.fiscal.nfe.status === "bad" ? "Rejeitada" : v.fiscal.nfe.status === "canc" ? "Cancelada" : "Processando"}</b></div>
                      <div className="mono small">Chave: {v.fiscal.nfe.chave}</div>
                      <div className="small">Emitida em {_outDate(v.fiscal.nfe.date)} às {_outTime(v.fiscal.nfe.date)}</div>
                      {v.fiscal.nfe.failReason && <div className="small fail">⚠ {v.fiscal.nfe.failReason}</div>}
                    </td>
                  </tr>
                )}
                {v.fiscal?.nfse && (
                  <tr>
                    <th>NFS-e LC 214/25</th>
                    <td>
                      <div>Nº {v.fiscal.nfse.numero} · Status: <b>{v.fiscal.nfse.status === "ok" ? "Autorizada" : "Processando"}</b></div>
                      <div className="mono small">Chave: {v.fiscal.nfse.chave}</div>
                      <div className="small">Emitida em {_outDate(v.fiscal.nfse.date)} às {_outTime(v.fiscal.nfse.date)}</div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </section>
        )}

        {/* ────── HISTÓRICO DE EDIÇÕES (Auditoria) ────── */}
        {audit.length > 0 && (
          <section className="vd-trans-section">
            <h2>Histórico de edições</h2>
            <table className="vd-trans-audit">
              <thead><tr><th>Quando</th><th>Quem</th><th>Ação</th></tr></thead>
              <tbody>
                {audit.map((e, i) => (
                  <tr key={i}>
                    <td className="mono small">{e.when}</td>
                    <td>{e.who}</td>
                    <td>{e.desc}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
        )}

        {/* ────── COMENTÁRIOS INLINE (curadoria) ────── */}
        {allComments.length > 0 && (
          <section className="vd-trans-section">
            <h2>Comentários da equipe</h2>
            {allComments.map(({ item, idx, list }) => (
              <div key={idx} className="vd-trans-comment-block">
                <b className="vd-trans-comment-item">Item: {item.name}</b>
                {list.map((c, ci) => (
                  <div key={ci} className="vd-trans-comment">
                    <span className="mono small">{c.author} · {c.when}</span>
                    <p>{c.text}</p>
                  </div>
                ))}
              </div>
            ))}
          </section>
        )}

        {/* ────── ENTREGA/OS ────── */}
        {v.osIds && v.osIds.length > 0 && (
          <section className="vd-trans-section">
            <h2>Vínculos com produção</h2>
            <p>Ordens de serviço vinculadas: {v.osIds.map(id => `#OS-${id}`).join(" · ")}</p>
          </section>
        )}

        {/* ────── ASSINATURAS ────── */}
        <section className="vd-trans-sign">
          <div>
            <div className="vd-trans-sign-line"></div>
            <small>{v.client}<br/>Cliente / responsável</small>
          </div>
          <div>
            <div className="vd-trans-sign-line"></div>
            <small>{v.seller || v.sellerId || "—"}<br/>Atendente Oimpresso</small>
          </div>
        </section>

        <footer className="vd-trans-foot">
          Documento emitido por Oimpresso ERP em {new Date().toLocaleString("pt-BR")} · pág 1 de 1
        </footer>
      </div>
    </div>
  );
}
window.VdTranscriptPDF = VdTranscriptPDF;

// ──────────────────────────────────────────────────────────────
// 2) MODO APRESENTAÇÃO — fullscreen, fonte gigante, read-only,
//    sem IDs internos. Pra reunião com cliente / sócio.
// ──────────────────────────────────────────────────────────────
function VdPresentationMode({ venda, onClose }) {
  const v = venda;
  const totalNum = v.totalNum || (v.itemsList || []).reduce((s, i) => s + i.qty * i.unit, 0);
  const [slide, setSlide] = useStateOut(0);
  const slides = [
    { id: "intro", label: "Visão geral" },
    { id: "items", label: `Itens (${(v.itemsList||[]).length})` },
    { id: "money", label: "Valor e prazo" },
    { id: "next",  label: "Próximos passos" },
  ];

  useEffectOut(() => {
    const onKey = (e) => {
      if (e.key === "Escape") { e.preventDefault(); onClose(); }
      else if (e.key === "ArrowRight" || e.key === " ") { e.preventDefault(); setSlide(s => Math.min(slides.length - 1, s + 1)); }
      else if (e.key === "ArrowLeft") { e.preventDefault(); setSlide(s => Math.max(0, s - 1)); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose, slides.length]);

  const status = v.fsm >= 4 ? "Concluída" : v.fsm >= 2 ? "Em andamento" : "Aguardando confirmação";
  const fiscalStatus = v.fiscal?.nfe?.status === "ok" || v.fiscal?.nfse?.status === "ok"
    ? "Nota fiscal autorizada"
    : v.fiscal?.nfe?.status === "wait" ? "Nota processando"
    : "Sem nota emitida";

  return (
    <div className="vd-pres-overlay">
      <header className="vd-pres-top">
        <span className="vd-pres-brand">OIMPRESSO · Comunicação Visual</span>
        <span className="vd-pres-counter">{slide + 1} / {slides.length}</span>
        <button className="vd-pres-close" onClick={onClose} title="Esc">×</button>
      </header>

      <main className="vd-pres-stage">
        {slide === 0 && (
          <div className="vd-pres-slide vd-pres-intro">
            <small className="vd-pres-eyebrow">Pedido para</small>
            <h1>{v.client}</h1>
            <p>{v.clientNote}</p>
            <div className="vd-pres-chips">
              <span className="vd-pres-chip">Atendido por {v.seller || v.sellerId}</span>
              <span className="vd-pres-chip">{v.date.split("-").reverse().join("/")}</span>
              <span className="vd-pres-chip primary">{status}</span>
            </div>
          </div>
        )}

        {slide === 1 && (
          <div className="vd-pres-slide vd-pres-items">
            <small className="vd-pres-eyebrow">{(v.itemsList||[]).length} ite{(v.itemsList||[]).length>1?"ns":"m"}</small>
            <h1>O que está incluso</h1>
            <ul>
              {(v.itemsList || []).map((it, i) => (
                <li key={i}>
                  <div className="vd-pres-item-l">
                    <b>{it.name}</b>
                    <small>{it.type === "produto" ? "Produto" : "Serviço"} · {it.qty}× {_outFmt(it.unit)}</small>
                  </div>
                  <div className="vd-pres-item-r">{_outFmt(it.qty * it.unit)}</div>
                </li>
              ))}
            </ul>
          </div>
        )}

        {slide === 2 && (
          <div className="vd-pres-slide vd-pres-money">
            <small className="vd-pres-eyebrow">Valor total</small>
            <div className="vd-pres-amount">{_outFmt(totalNum)}</div>
            <div className="vd-pres-payment">
              <span>Pagamento</span>
              <b>{v.payment}{v.installments > 1 ? ` · ${v.installments}× de ${_outFmt(totalNum / v.installments)}` : ""}</b>
            </div>
            <div className="vd-pres-payment">
              <span>Prazo</span>
              <b>{v.payTerm || 0} dias</b>
            </div>
          </div>
        )}

        {slide === 3 && (
          <div className="vd-pres-slide vd-pres-next">
            <small className="vd-pres-eyebrow">A partir daqui</small>
            <h1>Próximos passos</h1>
            <ol>
              {v.fsm < 4 && <li>Confirmar pagamento</li>}
              {(v.fsm < 2 || (!v.fiscal?.nfe && !v.fiscal?.nfse)) && <li>Emitir documento fiscal</li>}
              {v.fsm < 3 && (v.osIds?.length > 0) && <li>Iniciar produção (OS {v.osIds.join(", ")})</li>}
              {v.fsm < 3 && <li>Retirada / entrega ao cliente</li>}
              {v.fsm >= 3 && v.fsm < 4 && <li>Confirmar recebimento e finalizar</li>}
              {v.fsm >= 4 && <li>Pedido concluído ✓</li>}
            </ol>
            <p className="vd-pres-foot-note">{fiscalStatus}</p>
          </div>
        )}
      </main>

      <footer className="vd-pres-bot">
        <button onClick={() => setSlide(s => Math.max(0, s - 1))} disabled={slide === 0}>←</button>
        <div className="vd-pres-dots">
          {slides.map((s, i) => (
            <span key={i} className={`vd-pres-dot ${i === slide ? "on" : ""}`} onClick={() => setSlide(i)}/>
          ))}
        </div>
        <button onClick={() => setSlide(s => Math.min(slides.length - 1, s + 1))} disabled={slide === slides.length - 1}>→</button>
      </footer>
    </div>
  );
}
window.VdPresentationMode = VdPresentationMode;

// ──────────────────────────────────────────────────────────────
// 3) VARIÁVEIS LIVE — preview da mensagem de orçamento
//    Template com {{cliente}}, {{id}}, {{total}}, {{forma}}, {{prazo}}
//    Renderiza substituído + botão copiar + abrir WhatsApp
// ──────────────────────────────────────────────────────────────
const VD_MESSAGE_TEMPLATES = [
  { id: "confirmacao", label: "Confirmação pedido",
    text: "Olá {{cliente}}, sua venda #{{id}} de {{data}} no valor de {{total}} ficou {{status}}. Forma de pagamento: {{forma}}. Atendido por {{seller}}. Qualquer dúvida me chama!" },
  { id: "retirada", label: "Pronto pra retirada",
    text: "Oi {{cliente}}! Seu pedido #{{id}} ({{itens}} ite{{itens_plural}}) está pronto pra retirada na Oimpresso. Endereço: Rua das Gráficas, 123. Horário: 8h-18h. Total: {{total}} ({{forma}})." },
  { id: "cobranca", label: "Lembrete pagamento",
    text: "Olá {{cliente}}, passando pra lembrar do boleto da venda #{{id}} no valor de {{total}}, vencendo em {{vencimento}}. Qualquer coisa, manda mensagem aqui." },
];

function VdMessagePreview({ venda }) {
  const v = venda;
  const [tplId, setTplId] = useStateOut("confirmacao");
  const [copied, setCopied] = useStateOut(false);

  const totalNum = v.totalNum || (v.itemsList || []).reduce((s, i) => s + i.qty * i.unit, 0);
  const itemsCount = (v.itemsList || []).length;

  const vars = {
    cliente:   v.client,
    id:        v.id,
    data:      v.date.split("-").reverse().join("/"),
    total:     _outFmt(totalNum),
    forma:     v.payment + (v.installments > 1 ? ` ${v.installments}×` : ""),
    seller:    v.seller || v.sellerId || "—",
    status:    v.fsm >= 4 ? "PAGA" : v.fsm >= 2 ? "FATURADA" : "PENDENTE",
    prazo:     `${v.payTerm || 0} dias`,
    itens:     itemsCount,
    itens_plural: itemsCount > 1 ? "ns" : "m",
    vencimento: (() => {
      const [Y,M,D] = v.date.split("-").map(Number);
      const d = new Date(Y, M-1, D); d.setDate(d.getDate() + (v.payTerm || 0));
      return ("0"+d.getDate()).slice(-2) + "/" + ("0"+(d.getMonth()+1)).slice(-2) + "/" + d.getFullYear();
    })(),
  };

  const tpl = VD_MESSAGE_TEMPLATES.find(t => t.id === tplId);
  const rendered = tpl.text.replace(/\{\{(\w+)\}\}/g, (m, key) => {
    if (vars[key] === undefined) return m;
    return String(vars[key]);
  });

  // detecta variáveis usadas
  const usedVars = useMemoOut(() => {
    const set = new Set();
    let m;
    const re = /\{\{(\w+)\}\}/g;
    while ((m = re.exec(tpl.text)) !== null) set.add(m[1]);
    return [...set];
  }, [tpl.text]);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(rendered);
      setCopied(true); setTimeout(() => setCopied(false), 1500);
    } catch (e) {}
  };

  const wa = () => {
    const url = `https://wa.me/?text=${encodeURIComponent(rendered)}`;
    window.open(url, "_blank");
  };

  return (
    <div className="vd-msg">
      <header className="vd-msg-h">
        <h4>Mensagem pra cliente</h4>
        <div className="vd-msg-tpls">
          {VD_MESSAGE_TEMPLATES.map(t => (
            <button key={t.id}
                    className={`vd-msg-tpl ${tplId === t.id ? "on" : ""}`}
                    onClick={() => setTplId(t.id)}>
              {t.label}
            </button>
          ))}
        </div>
      </header>

      <div className="vd-msg-vars">
        <small>Variáveis substituídas:</small>
        {usedVars.map(k => (
          <span key={k} className="vd-msg-var" title={vars[k]}>
            <span className="k">{`{{${k}}}`}</span>
            <span className="arr">→</span>
            <span className="v">{String(vars[k] || "—").slice(0, 24)}</span>
          </span>
        ))}
      </div>

      <div className="vd-msg-preview">
        <div className="vd-msg-bubble">{rendered}</div>
      </div>

      <div className="vd-msg-actions">
        <button className="os-btn ghost" onClick={copy}>{copied ? "Copiado ✓" : "Copiar"}</button>
        <button className="os-btn primary" onClick={wa}>↗ Abrir WhatsApp</button>
      </div>
    </div>
  );
}
window.VdMessagePreview = VdMessagePreview;

// ──────────────────────────────────────────────────────────────
// 4) ART-SLOT POR ITEM — preview da arte/mockup anexa
//    Drag-drop ou click → arquivo → dataURL → localStorage
// ──────────────────────────────────────────────────────────────
function _artLoad() {
  try { return JSON.parse(localStorage.getItem("oimpresso.vendas.itemArt") || "{}"); }
  catch (e) { return {}; }
}
function _artSave(m) {
  try { localStorage.setItem("oimpresso.vendas.itemArt", JSON.stringify(m)); } catch (e) {}
}

function VdItemArt({ vendaId, itemIdx, itemName }) {
  const key = `${vendaId}:${itemIdx}`;
  const [art, setArt] = useStateOut(() => _artLoad()[key] || null);
  const [drag, setDrag] = useStateOut(false);

  const persist = (dataUrl) => {
    setArt(dataUrl);
    const m = _artLoad();
    if (dataUrl) m[key] = dataUrl; else delete m[key];
    _artSave(m);
  };

  const handleFile = (file) => {
    if (!file || !file.type.startsWith("image/")) return;
    const reader = new FileReader();
    reader.onload = () => persist(reader.result);
    reader.readAsDataURL(file);
  };

  return (
    <div className={`vd-art ${art ? "has" : "empty"} ${drag ? "drag" : ""}`}
         onDragOver={e => { e.preventDefault(); setDrag(true); }}
         onDragLeave={() => setDrag(false)}
         onDrop={e => { e.preventDefault(); setDrag(false); handleFile(e.dataTransfer.files?.[0]); }}>
      {art ? (
        <React.Fragment>
          <img src={art} alt={itemName}/>
          <button className="vd-art-rm" onClick={() => persist(null)} title="Remover arte">×</button>
        </React.Fragment>
      ) : (
        <label className="vd-art-empty">
          <span className="vd-art-ic">🖼</span>
          <small>{drag ? "solte aqui" : "arraste arte/mockup"}</small>
          <input type="file" accept="image/*" onChange={e => handleFile(e.target.files?.[0])}/>
        </label>
      )}
    </div>
  );
}
window.VdItemArt = VdItemArt;
