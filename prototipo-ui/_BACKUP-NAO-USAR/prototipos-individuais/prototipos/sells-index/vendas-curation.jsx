// vendas-curation.jsx — Refino #3 KB-9.75 · Curadoria + Guia (2026-05-15)
// 4 features: comentários inline · histórico edições · troubleshooter · cross-link
// Reusa kb-trouble-lib (KBTroubleshooterDialog) com customTroubles de venda.

const { useState: useStateCu, useEffect: useEffectCu, useMemo: useMemoCu } = React;

// ──────────────────────────────────────────────────────────────
// 1) COMENTÁRIOS INLINE POR LINHA DE ITEM
//    Storage: oimpresso.vendas.itemComments = { vendaId: { itemIdx: [{author, text, when}] } }
// ──────────────────────────────────────────────────────────────
function _vdLoadComments() {
  try { return JSON.parse(localStorage.getItem("oimpresso.vendas.itemComments") || "{}"); }
  catch (e) { return {}; }
}
function _vdSaveComments(m) {
  try { localStorage.setItem("oimpresso.vendas.itemComments", JSON.stringify(m)); } catch (e) {}
}

function useVdItemComments() {
  const [m, setM] = useStateCu(_vdLoadComments);
  useEffectCu(() => { _vdSaveComments(m); }, [m]);

  const add = (vendaId, itemIdx, text, author) => {
    setM(prev => {
      const v = prev[vendaId] || {};
      const it = v[itemIdx] || [];
      const next = { ...v, [itemIdx]: [...it, { author: author || "você", text, when: "agora" }] };
      return { ...prev, [vendaId]: next };
    });
  };
  const remove = (vendaId, itemIdx, ci) => {
    setM(prev => {
      const v = prev[vendaId] || {};
      const it = v[itemIdx] || [];
      const nextIt = it.filter((_, i) => i !== ci);
      const nextV = nextIt.length ? { ...v, [itemIdx]: nextIt } : { ...v };
      if (!nextIt.length) delete nextV[itemIdx];
      return { ...prev, [vendaId]: nextV };
    });
  };
  const get = (vendaId, itemIdx) => (m[vendaId]?.[itemIdx]) || [];
  const countFor = (vendaId) => {
    const v = m[vendaId] || {};
    return Object.values(v).reduce((s, l) => s + l.length, 0);
  };
  return { add, remove, get, countFor };
}
window.useVdItemComments = useVdItemComments;

// Componente — item row com comentário inline
function VdItemRow({ venda, item, idx, comments, onAdd, onRemove }) {
  const [open, setOpen] = useStateCu(false);
  const [text, setText] = useStateCu("");
  const list = comments || [];

  const submit = () => {
    const t = text.trim();
    if (!t) return;
    onAdd(t);
    setText("");
    setOpen(false);
  };

  const _fmt = (n) => (n || 0).toLocaleString("pt-BR", { style:"currency", currency:"BRL" });
  const hasComments = list.length > 0;

  return (
    <div className={`vd-item-card vd-item-cur ${hasComments ? "has-comments" : ""}`}>
      <div className="vd-item-c-main">
        <div className="vd-item-c-l">
          <b>{item.name}</b>
          <small>{item.sku} · {item.type === "produto" ? "Produto (NF-e)" : "Serviço (NFS-e)"}</small>
        </div>
        <span className="vd-item-c-qty">{item.qty}×</span>
        <span className="vd-item-c-unit">{_fmt(item.unit)}</span>
        <span className="vd-item-c-sub">{_fmt(item.qty * item.unit)}</span>
        <button
          className={`vd-item-c-comm ${open ? "on" : ""} ${hasComments ? "has" : ""}`}
          onClick={() => setOpen(o => !o)}
          title={hasComments ? `${list.length} comentário${list.length>1?"s":""}` : "Comentar este item"}>
          {open ? "×" : "💬"}
          {hasComments && !open && <span className="ct">{list.length}</span>}
        </button>
      </div>

      {(list.length > 0 || open) && (
        <div className="vd-item-thread">
          {list.map((c, ci) => (
            <div key={ci} className="vd-item-comment">
              <div className="vd-item-comment-h">
                <span className="vd-item-comment-av">{(c.author || "?").charAt(0).toUpperCase()}</span>
                <b>{c.author}</b>
                <span className="vd-item-comment-when">{c.when}</span>
                <button className="vd-item-comment-x" onClick={() => onRemove(ci)} title="Remover">×</button>
              </div>
              <p>{c.text}</p>
            </div>
          ))}
          {open && (
            <div className="vd-item-comment-new">
              <textarea
                autoFocus value={text} rows={2}
                onChange={e => setText(e.target.value)}
                onKeyDown={e => { if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) { e.preventDefault(); submit(); } }}
                placeholder='Ex: "Cliente pediu material premium" · "Confirmar arte antes de imprimir" · ⌘↵ envia'/>
              <div className="vd-item-comment-row">
                <small>📌 visível pra Produção · Financeiro · Vendedor</small>
                <button className="os-btn ghost" onClick={() => { setOpen(false); setText(""); }}>Cancelar</button>
                <button className="os-btn primary" disabled={!text.trim()} onClick={submit}>Comentar</button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
window.VdItemRow = VdItemRow;

// ──────────────────────────────────────────────────────────────
// 2) HISTÓRICO DE EDIÇÕES — auditoria de qty/preço/desconto
//    Mock determinístico por venda.id pra demo.
// ──────────────────────────────────────────────────────────────
function vdAuditTrail(venda) {
  // Gera entradas fake baseadas no v.id, payment e fsm — determinístico.
  const seed = (venda.id || "").charCodeAt(venda.id.length - 1) % 4;
  const entries = [];
  // sempre: criação
  entries.push({
    when: `${venda.date.slice(8,10)}/${venda.date.slice(5,7)} ${venda.time}`,
    who: venda.seller || venda.sellerId || "—",
    kind: "create",
    desc: "Venda registrada · " + (venda.itemsList?.length || 0) + " ite" + ((venda.itemsList?.length||0)>1?"ns":"m"),
  });
  // se seed >= 1, edita qty
  if (seed >= 1 && venda.itemsList?.[0]) {
    const it = venda.itemsList[0];
    entries.push({
      when: `${venda.date.slice(8,10)}/${venda.date.slice(5,7)} ${("0" + (parseInt(venda.time.slice(0,2))+1)).slice(-2)}:${venda.time.slice(3)}`,
      who: venda.seller || venda.sellerId || "—",
      kind: "edit",
      desc: `Quantidade de "${it.name.slice(0,32)}" · ${Math.max(1,it.qty-1)} → ${it.qty}`,
      field: "qty",
    });
  }
  // se seed >= 2, ajuste de preço
  if (seed >= 2 && venda.itemsList?.[0]) {
    const it = venda.itemsList[0];
    const oldUnit = Math.round(it.unit * 1.08);
    entries.push({
      when: `${venda.date.slice(8,10)}/${venda.date.slice(5,7)} ${("0" + (parseInt(venda.time.slice(0,2))+1)).slice(-2)}:${venda.time.slice(3)}`,
      who: venda.seller || venda.sellerId || "—",
      kind: "edit",
      desc: `Preço unitário de "${it.name.slice(0,28)}" · R$ ${oldUnit.toFixed(2)} → R$ ${it.unit.toFixed(2)}`,
      field: "unit",
      diff: { from: oldUnit, to: it.unit, pct: ((it.unit - oldUnit)/oldUnit*100) },
    });
  }
  // se faturada (fsm>=2), emissão fiscal
  if (venda.fsm >= 2 && venda.fiscal?.nfe?.status === "ok") {
    entries.push({
      when: venda.fiscal.nfe.date.split("T")[0].split("-").reverse().join("/") + " " + (venda.fiscal.nfe.date.split("T")[1]||"").slice(0,5),
      who: "sistema",
      kind: "fiscal",
      desc: `NF-e ${venda.fiscal.nfe.numero}/${venda.fiscal.nfe.serie} autorizada SEFAZ`,
    });
  }
  if (venda.fiscal?.nfe?.status === "bad") {
    entries.push({
      when: venda.fiscal.nfe.date.split("T")[0].split("-").reverse().join("/") + " " + (venda.fiscal.nfe.date.split("T")[1]||"").slice(0,5),
      who: "sistema",
      kind: "reject",
      desc: `NF-e rejeitada: ${venda.fiscal.nfe.failReason}`,
    });
  }
  return entries;
}
window.vdAuditTrail = vdAuditTrail;

function VdAuditTrail({ venda }) {
  const entries = useMemoCu(() => vdAuditTrail(venda), [venda.id]);
  const KIND_LABEL = { create:"criou", edit:"editou", fiscal:"fiscal", reject:"erro" };
  const KIND_IC = { create:"+", edit:"✎", fiscal:"📄", reject:"⚠" };
  return (
    <div className="vd-audit">
      <div className="vd-audit-h">
        <h4>Histórico de edições</h4>
        <small>{entries.length} entradas · auditoria fiscal</small>
      </div>
      <ul className="vd-audit-list">
        {entries.map((e, i) => (
          <li key={i} className={`vd-audit-row vd-audit-${e.kind}`}>
            <span className="vd-audit-ic">{KIND_IC[e.kind] || "·"}</span>
            <div className="vd-audit-body">
              <header>
                <b>{e.who}</b>
                <span className="vd-audit-action">{KIND_LABEL[e.kind] || ""}</span>
                <time>{e.when}</time>
              </header>
              <p>{e.desc}</p>
              {e.diff && (
                <div className="vd-audit-diff">
                  <span className="diff-from">R$ {e.diff.from.toFixed(2)}</span>
                  <span className="diff-arr">→</span>
                  <span className="diff-to">R$ {e.diff.to.toFixed(2)}</span>
                  <span className={`diff-pct ${e.diff.pct < 0 ? "neg" : "pos"}`}>
                    {e.diff.pct > 0 ? "+" : ""}{e.diff.pct.toFixed(1)}%
                  </span>
                </div>
              )}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
window.VdAuditTrail = VdAuditTrail;

// ──────────────────────────────────────────────────────────────
// 3) TROUBLESHOOTER DE VENDA — reuso KBTroubleshooterDialog com customTroubles
// ──────────────────────────────────────────────────────────────
const VENDAS_TROUBLES = [
  {
    id: "tr-nfe-rejeitada",
    title: "NF-e foi rejeitada pela SEFAZ",
    equip: "fiscal",
    hue: 25,
    when: "código de rejeição apareceu no drawer Fiscal",
    steps: [
      { q: "É código 539 (duplicidade de número)?",
        yes: { fix: "Você já emitiu esse número antes. Em Fiscal → consultar último número, use o PRÓXIMO da sequência. Antes de re-emitir, SEMPRE inutilize o número rejeitado pra não furar a sequência fiscal." },
        no:  1 },
      { q: "É código 692 (Inscrição Estadual inválida)?",
        yes: { fix: "IE do destinatário está errada ou desativada. Consulte o cliente no SINTEGRA da UF dele. Atualize o cadastro em Clientes → CNPJ/IE → Re-validar. Reabra a venda e re-emita." },
        no:  2 },
      { q: "É código 778 (CFOP inválido)?",
        yes: { fix: "Operação dentro do estado usa 5102 (venda mercadoria). Fora do estado usa 6102. Ajuste no item da venda ou no cadastro do produto, e re-emita." },
        no:  3 },
      { q: "É código 402 (origem da mercadoria)?",
        yes: { fix: "Falta cadastrar origem do produto. Vai em Produtos → este item → Origem: 0 (Nacional) ou 1 (Estrangeira direto). Salva e re-emite a NF-e." },
        no:  4 },
      { q: "É código 539 ou 539 ou outro de NCM?",
        yes: { fix: "NCM do produto diverge do cadastro SEFAZ. Confira a tabela NCM oficial (gov.br/receita) e corrija no produto. Mais em #a9." },
        no:  { fix: "Código fora da lista comum. Abra o XML da rejeição (drawer Fiscal → Logs → última transmissão), copie o código e poste no canal #financeiro pra Eliana analisar. Não re-emita sem inutilizar." } },
    ],
  },
  {
    id: "tr-cliente-sem-cnpj",
    title: "Cliente quer faturar mas não tem CNPJ",
    equip: "balcão",
    hue: 60,
    when: "B2B sem documento — bloqueia NF-e",
    steps: [
      { q: "Cliente é pessoa física e quer só recibo?",
        yes: { fix: "Emite NFC-e (cupom consumidor) com CPF na nota — opcional, mas vai pro programa Nota Premiada. Não emite NF-e modelo 55. O recibo térmica + a NFC-e atendem." },
        no:  1 },
      { q: "É empresa em formalização (MEI/ME novo)?",
        yes: { fix: "Cliente tem 30 dias após registro pra emitir IE. Enquanto isso, emite com 'ISENTO' no campo IE. NF-e modelo 55 com CFOP 5102. Anota no clientNote o prazo final." },
        no:  2 },
      { q: "É órgão público / sem CNPJ próprio?",
        yes: { fix: "Pode emitir nota com IE de Substituto Tributário OU pelo CNPJ da matriz. Pergunta no atendimento qual modelo a contabilidade do órgão exige." },
        no:  { fix: "Sem CNPJ válido, só dá pra emitir NFC-e (cupom). Se o cliente exige NF-e, peça o CNPJ ou negue a venda nesse formato — não há saída fiscal." } },
    ],
  },
  {
    id: "tr-cliente-desistiu",
    title: "Cliente desistiu depois de pagar",
    equip: "atendimento",
    hue: 320,
    when: "venda paga + cliente pediu cancelamento",
    steps: [
      { q: "A NF-e já foi autorizada SEFAZ?",
        yes: 1,
        no:  { fix: "Ainda dá pra cancelar a venda sem complicação fiscal. Marque como 'Cancelada' no drawer e reembolse via PIX direto. Anote o motivo." } },
      { q: "Faz menos de 24h da autorização?",
        yes: { fix: "Use 'Cancelar NF-e' no drawer Fiscal. Justificativa obrigatória (mín 15 caracteres). SEFAZ autoriza em ~30s. Reembolse via PIX. Em Devoluções, registra com motivo 'desistencia'." },
        no:  2 },
      { q: "Produto foi entregue ou retirado?",
        yes: { fix: "Cliente devolve o produto. Você emite NF-e de DEVOLUÇÃO (CFOP 1202/2202 dentro/fora estado). Reembolse após receber. Em Devoluções, motivo 'desistencia' + tipo 'estorno'." },
        no:  { fix: "Após 24h, NF-e original não cancela mais. Emite NF-e de DEVOLUÇÃO (CFOP 1202/2202). Cliente assina termo de não-retirada. Reembolso via PIX em até 7 dias." } },
    ],
  },
];
window.VENDAS_TROUBLES = VENDAS_TROUBLES;

// ──────────────────────────────────────────────────────────────
// 4) CROSS-LINK — #V-7825 #OS-4831 #CLI-Cliente #orc-123
// ──────────────────────────────────────────────────────────────
function VdLinkify({ text, onPick }) {
  if (!text || typeof text !== "string") return text;
  // Padrões: #V-1234, #OS-1234, #CLI-Nome, #orc-1234 (case insensitive)
  const re = /#(V-\d+|OS-\d+|CLI-[\wÀ-ÿ]+|orc-\d+|os\d+|cli\d+)/gi;
  const parts = [];
  let last = 0, m, i = 0;
  while ((m = re.exec(text)) !== null) {
    if (m.index > last) parts.push(text.slice(last, m.index));
    const tok = m[0]; // ex "#V-7825"
    const id = m[1];  // sem "#"
    const kind = id.split(/[-]/)[0].toLowerCase().replace(/\d/g,"");
    const cls = kind === "v" ? "venda"
              : kind === "os" ? "os"
              : kind === "cli" ? "cli"
              : kind === "orc" ? "orc" : "ref";
    parts.push(
      <a key={"k" + i++}
         className={`vd-link vd-link-${cls}`}
         href="#"
         onClick={e => { e.preventDefault(); onPick?.(id, cls); }}>
        {tok}
      </a>
    );
    last = m.index + tok.length;
  }
  if (last < text.length) parts.push(text.slice(last));
  return <React.Fragment>{parts}</React.Fragment>;
}
window.VdLinkify = VdLinkify;

// ──────────────────────────────────────────────────────────────
// BOTÃO LANÇADOR DO TROUBLESHOOTER (usado no drawer)
// ──────────────────────────────────────────────────────────────
function VdTroubleButton({ venda }) {
  const [open, setOpen] = useStateCu(false);
  // Sugere o trouble certo baseado no estado
  const suggested = (() => {
    if (venda.fiscal?.nfe?.status === "bad" || venda.fiscal?.nfse?.status === "bad") return "tr-nfe-rejeitada";
    if (venda.client === "Consumidor Final" && venda.fsm < 4) return "tr-cliente-sem-cnpj";
    return null;
  })();
  const sg = suggested ? VENDAS_TROUBLES.find(t => t.id === suggested) : null;

  return (
    <React.Fragment>
      <button className="vd-trouble-btn" onClick={() => setOpen(true)}>
        <span className="vd-trouble-ic">?</span>
        <span className="vd-trouble-lbl">
          {sg ? <>Resolver: <b>{sg.title}</b></> : "Guia de objeções"}
        </span>
        <span className="vd-trouble-ct">{VENDAS_TROUBLES.length} fluxos</span>
      </button>
      {open && window.KBTroubleshooterDialog && (
        <window.KBTroubleshooterDialog
          customTroubles={VENDAS_TROUBLES}
          onClose={() => setOpen(false)}
          onPickArticle={() => {}}
          onCreateNew={() => {}}
        />
      )}
    </React.Fragment>
  );
}
window.VdTroubleButton = VdTroubleButton;
