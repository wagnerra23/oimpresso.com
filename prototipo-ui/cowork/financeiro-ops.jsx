/* ════════════════════════════════════════════════════════════════════════
 * Financeiro — Operações (paridade com produção /financeiro/unificado v16)
 * Onda 1 Baixa · Onda 2 Anexos · Onda 3 Aprovação · Onda 4 OCR boleto · Onda 5 Combobox
 * Espelha: FinBaixaSheet · FinAnexosPanel · workflow aprovação · FinOcrBoletoSheet · ClienteCombobox
 * Carrega DEPOIS de curation (usa FIN_EDIT_OPTIONS) e ANTES de financeiro-page.
 * ════════════════════════════════════════════════════════════════════════ */
(() => {
const { useState, useEffect, useRef, useMemo } = React;
const I = window.FIN_I;

const fmtBRL = (n) => "R$ " + (n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const parseBRL = (s) => parseFloat(String(s).replace(/\./g, "").replace(",", ".")) || 0;
const fmtNum = (n) => (n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const todayISO = () => { const t = window.FIN_TODAY || new Date(); return t.toISOString().slice(0, 10); };

// Listas canônicas das operações (mock). Contas espelham o filtro "Contas" do git.
const CONTAS = [
  { id: "itau", label: "Itaú PJ", detail: "ag 0438 · cc 4521-7" },
  { id: "bradesco", label: "Bradesco PJ", detail: "ag 1234 · cc 5678-9" },
  { id: "caixa", label: "Caixa interno", detail: "dinheiro em espécie" },
  { id: "mp", label: "Mercado Pago", detail: "conta digital · maquininha" },
];
const FORMAS = ["PIX", "Boleto", "Dinheiro", "Transferência", "Cartão", "Débito autom."];
window.FIN_CONTAS = CONTAS;
window.FIN_FORMAS = FORMAS;
const planosList = () => (window.FIN_EDIT_OPTIONS && window.FIN_EDIT_OPTIONS.categories) || ["Banner", "Adesivo", "Insumo", "Serviço", "Imposto", "Outros"];

/* ─────────────────────────────────────────────────────────────────────────
 * Hooks de estado (localStorage) — mesmo padrão de useFinComments
 * ─────────────────────────────────────────────────────────────────────── */
function makeMapHook(key) {
  return function useMapStore() {
    const [map, setMap] = useState(() => {
      try { return JSON.parse(localStorage.getItem(key) || "{}"); } catch (e) { return {}; }
    });
    const persist = (next) => { setMap(next); try { localStorage.setItem(key, JSON.stringify(next)); } catch (e) {} };
    return {
      forOf: (id) => map[id] || [],
      get: (id) => map[id],
      set: (id, val) => persist({ ...map, [id]: val }),
      push: (id, item) => persist({ ...map, [id]: [...(map[id] || []), item] }),
      removeAt: (id, idx) => persist({ ...map, [id]: (map[id] || []).filter((_, i) => i !== idx) }),
      _map: map,
    };
  };
}
window.useFinBaixas = makeMapHook("oimpresso.financeiro.baixas");
window.useFinAnexos = makeMapHook("oimpresso.financeiro.anexos");
window.useFinAprovacao = makeMapHook("oimpresso.financeiro.aprovacao");

// Valor em aberto de um título = total − somatório das baixas registradas (parciais).
window.finValorAberto = function finValorAberto(row, baixasStore) {
  if (!row) return 0;
  if (row.paid_at) return 0;
  const total = row.amount || 0;
  const pago = ((baixasStore && baixasStore.forOf(row.id)) || []).reduce((s, b) => s + (b.valor || 0), 0);
  return Math.max(0, total - pago);
};

/* ─────────────────────────────────────────────────────────────────────────
 * Onda 1 — Diálogo de baixa (FinBaixaSheet)
 * Substitui a baixa instantânea: escolhe valor (parcial), conta, forma, plano, data.
 * ─────────────────────────────────────────────────────────────────────── */
function FinBaixaSheet({ row, open, onClose, onConfirm, aberto }) {
  const isIn = row && row.kind === "receivable";
  const max = aberto != null ? aberto : (row ? row.amount : 0);
  const [valor, setValor] = useState("");
  const [contaId, setContaId] = useState(CONTAS[0].id);
  const [forma, setForma] = useState(isIn ? "PIX" : "Boleto");
  const [plano, setPlano] = useState("");
  const [data, setData] = useState(todayISO());
  useEffect(() => {
    if (open && row) {
      setValor(fmtNum(max));
      setForma(row.channel && FORMAS.includes(row.channel) ? row.channel : (isIn ? "PIX" : "Boleto"));
      setPlano(row.category || "");
      setData(todayISO());
      setContaId(CONTAS[0].id);
    }
  }, [open, row && row.id]);
  if (!open || !row) return null;
  const v = parseBRL(valor);
  const parcial = v > 0 && v < max - 0.001;
  const can = v > 0 && v <= max + 0.001;
  const submit = () => {
    if (!can) return;
    onConfirm(row.id, { valor: v, contaId, conta: (CONTAS.find((c) => c.id === contaId) || {}).label, forma, plano, data, parcial });
    onClose();
  };
  return (
    <div className="fin-ops-backdrop" onClick={onClose} onKeyDown={(e) => { if (e.key === "Escape") onClose(); }}>
      <div className="fin-baixa-card" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Registrar baixa"
        onKeyDown={(e) => { if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) submit(); }}>
        <div className="fin-baixa-head">
          <span className={"fin-baixa-ic " + (isIn ? "in" : "out")}><I.Wallet size={15} /></span>
          <div className="min-w-0">
            <b>{isIn ? "Registrar recebimento" : "Registrar pagamento"}</b>
            <small>{row.id} · {row.party}</small>
          </div>
          <button className="fin-ops-x" onClick={onClose} aria-label="Fechar"><I.X size={15} /></button>
        </div>
        <div className="fin-baixa-body">
          <label className="fin-ops-field fin-ops-col2">
            <span>Valor da baixa</span>
            <div className="fin-baixa-valor">
              <i>R$</i>
              <input inputMode="decimal" value={valor} onChange={(e) => setValor(e.target.value)} className="num tabular-nums" autoFocus />
            </div>
            <em className="fin-baixa-hint">
              Em aberto: <b className="num">{fmtBRL(max)}</b>
              <button type="button" className="fin-baixa-link" onClick={() => setValor(fmtNum(max))}>valor cheio</button>
              {parcial && <span className="fin-baixa-parcial">baixa parcial · resta {fmtBRL(max - v)}</span>}
            </em>
          </label>
          <label className="fin-ops-field">
            <span>Conta bancária</span>
            <div className="fin-ops-select">
              <select value={contaId} onChange={(e) => setContaId(e.target.value)}>
                {CONTAS.map((c) => <option key={c.id} value={c.id}>{c.label} · {c.detail}</option>)}
              </select>
              <I.ChevronDown size={14} />
            </div>
          </label>
          <label className="fin-ops-field">
            <span>Forma de pagamento</span>
            <div className="fin-ops-select">
              <select value={forma} onChange={(e) => setForma(e.target.value)}>
                {FORMAS.map((f) => <option key={f} value={f}>{f}</option>)}
              </select>
              <I.ChevronDown size={14} />
            </div>
          </label>
          <label className="fin-ops-field">
            <span>Plano de contas</span>
            <div className="fin-ops-select">
              <select value={plano} onChange={(e) => setPlano(e.target.value)}>
                <option value="">— sem classificar —</option>
                {planosList().map((p) => <option key={p} value={p}>{p}</option>)}
              </select>
              <I.ChevronDown size={14} />
            </div>
          </label>
          <label className="fin-ops-field">
            <span>Data da baixa</span>
            <input type="date" value={data} onChange={(e) => setData(e.target.value)} className="fin-ops-date" />
          </label>
        </div>
        <div className="fin-baixa-foot">
          <span className="fin-baixa-foot-sum">{parcial ? "Baixa parcial" : "Quitação total"} · <b className="num">{fmtBRL(v)}</b></span>
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" disabled={!can} onClick={submit}>
            <I.Check size={13} /> {isIn ? "Confirmar recebimento" : "Confirmar pagamento"}
          </button>
        </div>
      </div>
    </div>);
}
window.FinBaixaSheet = FinBaixaSheet;

/* ─────────────────────────────────────────────────────────────────────────
 * Onda 2 — Anexos NF / comprovante (FinAnexosPanel)
 * Lista + drop/upload (pdf, png, jpg, xml). Dedup por nome (proxy do SHA-256 do git).
 * ─────────────────────────────────────────────────────────────────────── */
// Seed determinístico: títulos com NF ganham o XML/PDF da nota; pagos ganham comprovante.
function seedAnexos(row) {
  const out = [];
  if (row.invoice) {
    const n = row.invoice.replace(/\D/g, "") || "0000";
    out.push({ name: "NFe-" + n + ".xml", type: "xml", size: 18 * 1024 + (n.length * 311), at: "emissão", seed: true });
    out.push({ name: "DANFE-" + n + ".pdf", type: "pdf", size: 96 * 1024, at: "emissão", seed: true });
  }
  if (row.paid_at) out.push({ name: "comprovante-" + row.id + ".pdf", type: "pdf", size: 41 * 1024, at: "baixa", seed: true });
  return out;
}
const ANEXO_IC = { pdf: "PDF", xml: "XML", png: "IMG", jpg: "IMG", jpeg: "IMG" };
const fmtSize = (b) => b >= 1024 * 1024 ? (b / 1048576).toFixed(1) + " MB" : Math.max(1, Math.round(b / 1024)) + " KB";

function FinAnexosPanel({ row, anexos }) {
  const fileRef = useRef(null);
  const [drag, setDrag] = useState(false);
  const user = anexos ? anexos.forOf(row.id) : [];
  const list = useMemo(() => [...seedAnexos(row), ...user], [row.id, user.length]);
  const accept = ".pdf,.png,.jpg,.jpeg,.xml";
  const addFiles = (files) => {
    if (!anexos || !files) return;
    const existing = new Set(list.map((a) => a.name.toLowerCase()));
    [...files].forEach((f) => {
      if (existing.has(f.name.toLowerCase())) { window.vdToast?.("Anexo “" + f.name + "” já existe (ignorado)", "warn", 3000); return; }
      const ext = (f.name.split(".").pop() || "").toLowerCase();
      anexos.push(row.id, { name: f.name, type: ext, size: f.size || 12 * 1024, at: "agora" });
      existing.add(f.name.toLowerCase());
    });
  };
  return (
    <section className="fin-lens py-4">
      <header className="flex items-center gap-2 mb-2.5">
        <span className="fin-lens-ic fin-lens-ic-accent"><I.FileText size={13} /></span>
        <h4 className="text-[length:var(--fs-3)] font-semibold text-[var(--text)] m-0">Anexos</h4>
        <span className="fin-anexo-ct">{list.length}</span>
        <button type="button" className="fin-anexo-add ml-auto" onClick={() => fileRef.current && fileRef.current.click()}>
          <I.Plus size={12} /> Anexar
        </button>
        <input ref={fileRef} type="file" accept={accept} multiple hidden onChange={(e) => { addFiles(e.target.files); e.target.value = ""; }} />
      </header>
      <div className={"fin-anexo-drop" + (drag ? " over" : "")}
        onDragOver={(e) => { e.preventDefault(); setDrag(true); }}
        onDragLeave={() => setDrag(false)}
        onDrop={(e) => { e.preventDefault(); setDrag(false); addFiles(e.dataTransfer.files); }}>
        {list.length === 0 ? (
          <div className="fin-anexo-empty">
            Arraste a NF, o comprovante ou a foto do boleto aqui — ou clique em <b>Anexar</b>.<br />
            <span>Aceita PDF, PNG, JPG e XML (NF-e).</span>
          </div>
        ) : (
          <ul className="fin-anexo-list">
            {list.map((a, i) => (
              <li key={a.name + i} className="fin-anexo-item">
                <span className={"fin-anexo-badge t-" + (ANEXO_IC[a.type] || "PDF").toLowerCase()}>{ANEXO_IC[a.type] || a.type.toUpperCase()}</span>
                <div className="fin-anexo-meta min-w-0">
                  <b className="truncate">{a.name}</b>
                  <small>{fmtSize(a.size)} · {a.at}{a.seed ? " · do sistema" : ""}</small>
                </div>
                {a.seed ? (
                  <span className="fin-anexo-lock" title="Anexado automaticamente pelo sistema"><I.Check size={12} /></span>
                ) : (
                  <button className="fin-anexo-del" title="Remover anexo" onClick={() => anexos.removeAt(row.id, user.indexOf(a))}><I.X size={12} /></button>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>);
}
window.FinAnexosPanel = FinAnexosPanel;

/* ─────────────────────────────────────────────────────────────────────────
 * Onda 3 — Workflow de aprovação de pagamento (a pagar)
 * none → pendente → aprovado | rejeitado. Espelha Onda 21 #55 do git.
 * ─────────────────────────────────────────────────────────────────────── */
function FinAprovacaoPanel({ row, aprovacao }) {
  const [motivoOpen, setMotivoOpen] = useState(false);
  const [motivo, setMotivo] = useState("");
  // Só faz sentido pra A PAGAR em aberto/atrasado/vencendo.
  if (!row || row.kind !== "payable" || row.paid_at) return null;
  const st = (aprovacao && aprovacao.get(row.id)) || { status: "none" };
  const setSt = (next) => aprovacao && aprovacao.set(row.id, next);
  const TONE = { pendente: "warn", aprovado: "pos", rejeitado: "neg" };
  return (
    <section className="fin-lens py-4">
      <header className="flex items-center gap-2 mb-2.5">
        <span className="fin-lens-ic fin-lens-ic-accent"><I.Check size={13} /></span>
        <h4 className="text-[length:var(--fs-3)] font-semibold text-[var(--text)] m-0">Aprovação de pagamento</h4>
        {st.status !== "none" && <span className={"fin-aprov-pill t-" + TONE[st.status]}>{st.status === "pendente" ? "aguardando" : st.status}</span>}
      </header>

      {st.status === "none" && (
        <div className="fin-aprov-box">
          <p>Pagamento ainda não passou por aprovação. Solicite a liberação antes de quitar.</p>
          <button className="fin-cob-btn fin-cob-btn--primary" onClick={() => setSt({ status: "pendente", by: "Eliana", at: todayISO() })}>
            <I.Send size={13} /> Solicitar aprovação
          </button>
        </div>
      )}

      {st.status === "pendente" && (
        <div className="fin-aprov-box">
          <p>Solicitado por <b>{st.by || "Eliana"}</b> · aguardando o gestor liberar.</p>
          {!motivoOpen ? (
            <div className="flex flex-wrap gap-1.5">
              <button className="fin-cob-btn fin-cob-btn--primary" onClick={() => setSt({ ...st, status: "aprovado", decididoBy: "Wagner", decididoAt: todayISO() })}>
                <I.Check size={13} /> Aprovar
              </button>
              <button className="fin-cob-btn fin-cob-btn--ghost" onClick={() => setMotivoOpen(true)}>
                <I.X size={13} /> Rejeitar
              </button>
            </div>
          ) : (
            <div className="fin-aprov-motivo">
              <input placeholder="Motivo da rejeição…" value={motivo} onChange={(e) => setMotivo(e.target.value)} autoFocus />
              <button className="fin-cob-btn fin-cob-btn--ghost" onClick={() => { setMotivoOpen(false); setMotivo(""); }}>Voltar</button>
              <button className="fin-cob-btn fin-cob-btn--danger" disabled={!motivo.trim()}
                onClick={() => { setSt({ ...st, status: "rejeitado", motivo: motivo.trim(), decididoBy: "Wagner", decididoAt: todayISO() }); setMotivoOpen(false); }}>
                Confirmar rejeição
              </button>
            </div>
          )}
        </div>
      )}

      {st.status === "aprovado" && (
        <div className="fin-aprov-box t-pos">
          <p><b>Liberado pra pagamento</b> por {st.decididoBy || "Wagner"}{st.decididoAt ? " · " + st.decididoAt.split("-").reverse().join("/") : ""}.</p>
          <button className="fin-aprov-undo" onClick={() => setSt({ status: "none" })}>desfazer</button>
        </div>
      )}

      {st.status === "rejeitado" && (
        <div className="fin-aprov-box t-neg">
          <p><b>Bloqueado pra pagamento.</b> {st.motivo ? "Motivo: " + st.motivo : ""}</p>
          <button className="fin-cob-btn fin-cob-btn--ghost" onClick={() => setSt({ status: "pendente", by: "Eliana", at: todayISO() })}>
            <I.Send size={13} /> Reenviar pra aprovação
          </button>
        </div>
      )}
    </section>);
}
window.FinAprovacaoPanel = FinAprovacaoPanel;

/* ─────────────────────────────────────────────────────────────────────────
 * Onda 4 — Leitura de boleto (OCR) → pré-preenche título a pagar
 * Espelha FinOcrBoletoSheet do git. Mock: cola a linha digitável → extrai campos.
 * ─────────────────────────────────────────────────────────────────────── */
function parseLinhaDigitavel(raw) {
  const digits = (raw || "").replace(/\D/g, "");
  if (digits.length < 30) return null;
  // Valor: últimos 10 dígitos do código de barras tradicional (centavos). Determinístico.
  const cents = parseInt(digits.slice(-10), 10) || 0;
  const valor = cents / 100;
  // Vencimento: fator de 4 dígitos antes do valor → offset de dias da base 07/10/1997 (regra Febraban).
  const fator = parseInt(digits.slice(-14, -10), 10) || 0;
  const base = new Date(1997, 9, 7);
  const venc = fator ? new Date(base.getTime() + fator * 86400000) : new Date((window.FIN_TODAY || new Date()).getTime() + 5 * 86400000);
  const banco = digits.slice(0, 3);
  const BANCOS = { "001": "Banco do Brasil", "104": "Caixa Econômica", "237": "Bradesco", "341": "Itaú", "033": "Santander", "748": "Sicredi", "260": "Nubank" };
  return { valor: valor > 0 ? valor : 247.9, venc, banco: BANCOS[banco] || "Cedente (banco " + banco + ")", linha: digits };
}

function FinOcrBoletoSheet({ open, onClose, onCreate }) {
  const [raw, setRaw] = useState("");
  const [parsed, setParsed] = useState(null);
  const [scanning, setScanning] = useState(false);
  const [party, setParty] = useState("");
  const [desc, setDesc] = useState("");
  useEffect(() => { if (open) { setRaw(""); setParsed(null); setScanning(false); setParty(""); setDesc(""); } }, [open]);
  if (!open) return null;
  const ler = () => { const p = parseLinhaDigitavel(raw); if (p) { setParsed(p); setDesc(desc || "Boleto " + p.banco); setParty(party || p.banco); } else window.vdToast?.("Linha digitável incompleta — cole os ~47 números", "warn", 3200); };
  // Simular leitura de uma foto/PDF (não há OCR real no protótipo): gera uma linha plausível.
  const simular = () => {
    setScanning(true);
    setTimeout(() => {
      const demo = "23793381286008301947700000027319" + String(Date.now()).slice(-15);
      setRaw(demo);
      const p = parseLinhaDigitavel(demo);
      setParsed(p); setDesc("Boleto " + p.banco); setParty(p.banco); setScanning(false);
    }, 900);
  };
  const criar = () => {
    if (!parsed) return;
    onCreate({ kind: "payable", desc: (desc.trim() || "Boleto"), party: (party.trim() || parsed.banco), amount: parsed.valor, due: parsed.venc });
    onClose();
  };
  return (
    <div className="fin-ops-backdrop" onClick={onClose}>
      <div className="fin-ocr-card" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Ler boleto">
        <div className="fin-baixa-head">
          <span className="fin-baixa-ic out"><I.Receipt size={15} /></span>
          <div className="min-w-0"><b>Ler boleto</b><small>linha digitável ou foto → título a pagar</small></div>
          <button className="fin-ops-x" onClick={onClose} aria-label="Fechar"><I.X size={15} /></button>
        </div>
        <div className="fin-ocr-body">
          <div className={"fin-ocr-scan" + (scanning ? " on" : "")} onClick={simular} title="Simular leitura de uma foto/PDF do boleto">
            {scanning ? <span className="fin-ocr-scanning"><I.Sparkles size={16} /> Lendo boleto…</span>
              : <span><I.FileText size={18} /> Tirar foto / soltar PDF do boleto<em>leitura simulada</em></span>}
          </div>
          <label className="fin-ops-field fin-ops-col2">
            <span>Linha digitável</span>
            <input value={raw} onChange={(e) => setRaw(e.target.value)} placeholder="00000.00000 00000.000000 00000.000000 0 00000000000000"
              className="num tabular-nums fin-ocr-input" onKeyDown={(e) => { if (e.key === "Enter") ler(); }} />
            <button type="button" className="fin-baixa-link self-start" onClick={ler}>extrair dados →</button>
          </label>
          {parsed && (
            <div className="fin-ocr-result">
              <div className="fin-ocr-grid">
                <div><small>Cedente</small><b>{parsed.banco}</b></div>
                <div><small>Vencimento</small><b className="num">{parsed.venc.toLocaleDateString("pt-BR")}</b></div>
                <div><small>Valor</small><b className="num">{fmtBRL(parsed.valor)}</b></div>
              </div>
              <div className="fin-ocr-edit">
                <label className="fin-ops-field"><span>Fornecedor</span><input value={party} onChange={(e) => setParty(e.target.value)} /></label>
                <label className="fin-ops-field"><span>Descrição</span><input value={desc} onChange={(e) => setDesc(e.target.value)} /></label>
              </div>
            </div>
          )}
        </div>
        <div className="fin-baixa-foot">
          <span className="fin-baixa-foot-sum">{parsed ? <>A pagar · <b className="num">{fmtBRL(parsed.valor)}</b></> : "Cole a linha ou simule a leitura"}</span>
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" disabled={!parsed} onClick={criar}><I.Plus size={13} /> Criar título a pagar</button>
        </div>
      </div>
    </div>);
}
window.FinOcrBoletoSheet = FinOcrBoletoSheet;

/* ─────────────────────────────────────────────────────────────────────────
 * Onda 5 — Combobox de contraparte com autocomplete (ClienteCombobox)
 * Sugere contrapartes já existentes no ledger (do lado certo), permite digitar livre.
 * ─────────────────────────────────────────────────────────────────────── */
function FinClienteCombobox({ kind, value, onChange, autoFocus }) {
  const [open, setOpen] = useState(false);
  const [hi, setHi] = useState(0);
  const ref = useRef(null);
  // Universo de contrapartes do mesmo lado (receivable=clientes · payable=fornecedores).
  const universe = useMemo(() => {
    const seen = new Map();
    (window.FIN_ROWS || []).filter((r) => r.kind === kind).forEach((r) => {
      if (!seen.has(r.party)) seen.set(r.party, 0);
      seen.set(r.party, seen.get(r.party) + 1);
    });
    return [...seen.entries()].map(([party, n]) => ({ party, n })).sort((a, b) => b.n - a.n);
  }, [kind]);
  const q = (value || "").toLowerCase();
  const matches = useMemo(() => (!q ? universe : universe.filter((u) => u.party.toLowerCase().includes(q))).slice(0, 6), [q, universe]);
  useEffect(() => {
    const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, []);
  const pick = (p) => { onChange(p); setOpen(false); };
  return (
    <div className="fin-combo" ref={ref}>
      <input
        autoFocus={autoFocus}
        value={value}
        onChange={(e) => { onChange(e.target.value); setOpen(true); setHi(0); }}
        onFocus={() => setOpen(true)}
        placeholder={kind === "receivable" ? "Buscar ou digitar cliente…" : "Buscar ou digitar fornecedor…"}
        onKeyDown={(e) => {
          if (!open && (e.key === "ArrowDown")) { setOpen(true); return; }
          if (e.key === "ArrowDown") { e.preventDefault(); setHi((h) => Math.min(h + 1, matches.length - 1)); }
          else if (e.key === "ArrowUp") { e.preventDefault(); setHi((h) => Math.max(h - 1, 0)); }
          else if (e.key === "Enter" && open && matches[hi]) { e.preventDefault(); pick(matches[hi].party); }
          else if (e.key === "Escape") setOpen(false);
        }}
        className="h-9 px-3 rounded-md border border-[var(--border)] bg-[var(--surface)] text-[length:var(--fs-4)] outline-none focus:border-[var(--accent)] w-full" />
      {open && matches.length > 0 && (
        <ul className="fin-combo-pop">
          {matches.map((u, i) => (
            <li key={u.party} className={"fin-combo-opt" + (i === hi ? " hi" : "")}
              onMouseEnter={() => setHi(i)} onMouseDown={(e) => { e.preventDefault(); pick(u.party); }}>
              <span className="fin-combo-av"><I.User size={11} /></span>
              <span className="fin-combo-name truncate">{u.party}</span>
              <span className="fin-combo-n">{u.n}×</span>
            </li>
          ))}
        </ul>
      )}
    </div>);
}
window.FinClienteCombobox = FinClienteCombobox;

})();
