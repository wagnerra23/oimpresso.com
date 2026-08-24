// fiscal-actions.jsx — Onda 1 (mutações) + Onda 2 (⌘K + teclado) do módulo Fiscal.
// Espelha os contratos do vivo: Modules/Fiscal/Http/Controllers/AcoesController (cancelar NF-e,
// CC-e 110110, inutilização faixa cstat 102, retransmitir, manifestar DF-e) + PaletteSearchController.
// Aqui é F1: o estado vive em memória (store local), o vivo delega pros services do NfeBrasil.
const { useState: useStateFa, useEffect: useEffectFa, useMemo: useMemoFa, useRef: useRefFa } = React;
const faD = () => window.FISCAL_DATA;

const FxStore = { notas: null, eventos: null, dfe: null, hist: null, toasts: [], proc: false, seq: 0, subs: new Set() };
function faEnsure() {
  if (FxStore.notas || !window.FISCAL_DATA) return;
  FxStore.notas = faD().NOTAS.map(n => Object.assign({}, n));
  FxStore.eventos = faD().EVENTOS.slice();
  FxStore.dfe = faD().DFE.map(d => Object.assign({}, d));
  FxStore.hist = faD().DFE_HISTORICO.slice();
  FxStore.config = JSON.parse(JSON.stringify(faD().CONFIG));
  FxStore.gate = false;
  try { FxStore.proc = localStorage.getItem("oimpresso.fiscal.procedencia") === "1"; } catch (e) {}
}
function faNotify() { FxStore.subs.forEach(f => f(++FxStore.seq)); }
function faNota(id) { return FxStore.notas.find(n => n.id === id); }
function faEvento(ev) { FxStore.eventos = [ev].concat(FxStore.eventos); }
function faAgora() {
  const d = new Date();
  const p = (v) => String(v).padStart(2, "0");
  return p(d.getDate()) + "/" + p(d.getMonth() + 1) + " " + p(d.getHours()) + ":" + p(d.getMinutes());
}

window.useFiscalStore = function useFiscalStore() {
  const [, set] = useStateFa(0);
  faEnsure();
  useEffectFa(() => { FxStore.subs.add(set); return () => { FxStore.subs.delete(set); }; }, []);
  return FxStore;
};

window.fxToast = function (msg, tone) {
  const id = "t" + Date.now() + Math.random();
  FxStore.toasts = FxStore.toasts.concat([{ id: id, msg: msg, tone: tone || "ok" }]);
  faNotify();
  setTimeout(() => { FxStore.toasts = FxStore.toasts.filter(t => t.id !== id); faNotify(); }, 4200);
};

window.FxActions = {
  // POST /fiscal/acoes/nfe/{emissao}/cancelar — FSM cascade ADR 0143
  cancelar: function (id, justificativa) {
    const n = faNota(id); if (!n) return;
    n.cancelada = true; n.prazoCancel = null; n.prazoCce = null;
    n.auditoria = [{ quando: faAgora(), autor: "Eliana", acao: "cancelou a nota — " + justificativa }].concat(n.auditoria || []);
    faEvento({ id: "evt-c" + id, tipo: "Cancelamento", kind: "cancel", nota: n.tipo + " " + n.num, descricao: justificativa, emit: faAgora(), autor: "Eliana", sefaz: 101 });
    faNotify(); window.fxToast(n.tipo + " " + n.num + " cancelada · evento 110111 aceito (cstat 101)");
  },
  // POST /fiscal/acoes/nfe/{emissao}/cce — tpEvento 110110
  cce: function (id, texto) {
    const n = faNota(id); if (!n) return;
    const seq = ((n.eventos || []).filter(e => e.tipo === "Carta de Correção").length) + 1;
    const ev = { id: "evt-cce" + id + seq, tipo: "Carta de Correção", kind: "cce", nota: n.tipo + " " + n.num, sequencia: seq, descricao: texto, emit: faAgora(), autor: "Eliana", sefaz: 100 };
    n.eventos = (n.eventos || []).concat([ev]);
    faEvento(ev); faNotify();
    window.fxToast("CC-e #" + seq + " registrada na " + n.tipo + " " + n.num + " (cstat 100)");
  },
  // POST /fiscal/acoes/nfe/inutilizar — faixa numérica, SEFAZ cstat 102
  inutilizar: function (serie, de, ate, justificativa) {
    faEvento({ id: "evt-inut" + Date.now(), tipo: "Inutilização", kind: "inutilizacao", nota: "Faixa " + de + "-" + ate + " · série " + serie, descricao: justificativa, emit: faAgora(), autor: "Wagner", sefaz: 102 });
    faNotify(); window.fxToast("Faixa " + de + "–" + ate + " inutilizada (cstat 102)");
  },
  // POST /fiscal/acoes/nfe/{emissao}/retransmitir — preserva numeração (CONFAZ Art. 14)
  retransmitir: function (id) {
    const n = faNota(id); if (!n) return;
    if (n.statusKind === "sefaz") { n.status = 100; } else { n.status = "autorizada"; }
    n.rejMsg = null;
    n.prazoCancel = n.kind === "nfe" ? { label: "23h50", urgency: "ok" } : null;
    n.auditoria = [{ quando: faAgora(), autor: "Eliana", acao: "retransmitiu com a mesma numeração → autorizada" }].concat(n.auditoria || []);
    faNotify(); window.fxToast(n.tipo + " " + n.num + " autorizada na retransmissão");
  },
  // POST /fiscal/acoes/dfe/{recebido}/{acao}
  manifestar: function (id, acao) {
    const d = FxStore.dfe.find(x => x.id === id); if (!d) return;
    const mapa = { cienciar: "ciencia", confirmar: "confirmada", desconhecer: "desconhecida", nao_realizada: "nao_realizada" };
    const rot = { cienciar: "Ciência da operação", confirmar: "Confirmação da operação", desconhecer: "Desconhecimento", nao_realizada: "Operação não realizada" };
    d.status = mapa[acao];
    if (acao !== "cienciar") d.prazo = null;
    faEvento({ id: "evt-dfe" + id + acao, tipo: "Manifestação destinatário", kind: "manifest", nota: "NF-e entrada " + d.num, descricao: rot[acao] + " — " + d.emitente, emit: faAgora(), autor: "Wagner", sefaz: acao === "confirmar" ? 135 : 136 });
    faNotify(); window.fxToast(rot[acao] + " registrada na NF-e " + d.num);
  },
};

window.fxToggleProc = function () {
  FxStore.proc = !FxStore.proc;
  try { localStorage.setItem("oimpresso.fiscal.procedencia", FxStore.proc ? "1" : "0"); } catch (e) {}
  faNotify();
};

// Onda 1 · selo de procedência por superfície (CU-FISC-16 @main: 6 superfícies de demonstração)
window.FxProc = function FxProc({ k }) {
  const st = window.useFiscalStore();
  if (!st.proc) return null;
  const p = faD().PROC[k];
  if (!p) return null;
  return <span className="fx-proc" data-kind={p.kind} title={p.explica}>{p.label}</span>;
};

// Manifestação em lote (Onda 3) — backlog declarado em Dfe.charter.md
window.FxActions.manifestarLote = function (ids, acao) {
  ids.forEach(id => window.FxActions.manifestar(id, acao));
  window.fxToast(ids.length + " DF-e manifestadas em lote");
};

// Export CSV (Onda 3) — backlog declarado em Eventos.charter.md
window.fxExportCsv = function (nome, colunas, linhas) {
  const esc = (v) => '"' + String(v == null ? "" : v).replace(/"/g, '""') + '"';
  const csv = [colunas.map(c => esc(c.label)).join(";")].concat(linhas.map(l => colunas.map(c => esc(c.get(l))).join(";"))).join("\r\n");
  try {
    const url = URL.createObjectURL(new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8" }));
    const a = document.createElement("a");
    a.href = url; a.download = nome; a.click();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
    window.fxToast(linhas.length + " linhas exportadas · " + nome);
  } catch (e) { window.fxToast("Export indisponível neste ambiente", "warn"); }
};

// Onda 6 · Config editável atrás de gate próprio (proposta [CC], ratificação [W] pendente)
window.FxActions.toggleGate = function () { FxStore.gate = !FxStore.gate; faNotify(); };

window.FxActions.trocarAmbiente = function (destino, justificativa) {
  const c = FxStore.config;
  const antes = c.cert.ambiente;
  c.cert.ambiente = destino;
  faEvento({ id: "evt-amb" + Date.now(), tipo: "Troca de ambiente SEFAZ", kind: "config", nota: "Configuração fiscal", descricao: antes + " → " + destino + " — " + justificativa, emit: faAgora(), autor: "Wagner (superadmin)", sefaz: "—" });
  faNotify();
  window.fxToast("Ambiente de emissão agora é " + destino + " · registrado na auditoria");
};

window.FxActions.enviarCertificado = function (arquivo) {
  const c = FxStore.config;
  c.cert.pendente = arquivo || "certificado.pfx";
  faEvento({ id: "evt-cert" + Date.now(), tipo: "Certificado A1 substituído", kind: "config", nota: "Configuração fiscal", descricao: "Arquivo " + c.cert.pendente + " · senha não registrada em log", emit: faAgora(), autor: "Wagner (superadmin)", sefaz: "—" });
  faNotify();
  window.fxToast("Certificado recebido · validação de titular e CNPJ acontece no emissor");
};

// ─── Toasts ───
window.FxToasts = function FxToasts() {
  const st = window.useFiscalStore();
  if (!st.toasts.length) return null;
  return (
    <div className="fx-toasts" role="status" aria-live="polite">
      {st.toasts.map(t => <div className="fx-toast" data-tone={t.tone} key={t.id}>{t.msg}</div>)}
    </div>
  );
};

// ─── Modal de ação (PT-04 · confirmação, nunca detalhe) ───
window.FxActionModal = function FxActionModal({ spec, onClose }) {
  const [vals, setVals] = useStateFa(() => {
    const o = {}; (spec ? spec.fields : []).forEach(f => { o[f.id] = f.value || ""; }); return o;
  });
  useEffectFa(() => {
    const o = {}; (spec ? spec.fields : []).forEach(f => { o[f.id] = f.value || ""; }); setVals(o);
  }, [spec]);
  useEffectFa(() => {
    if (!spec) return;
    const h = (e) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, [spec, onClose]);
  if (!spec) return null;
  const invalido = spec.fields.filter(f => f.min && String(vals[f.id] || "").trim().length < f.min);
  return (
    <>
      <div className="fx-scrim" onClick={onClose}></div>
      <div className="fx-modal" data-contract="acoes-mutacao" role="dialog" aria-modal="true" aria-label={spec.title}>
        <div className="fx-modal-h"><h2>{spec.title}</h2><button className="fx-dr-x" onClick={onClose} aria-label="Fechar">×</button></div>
        <div className="fx-modal-b">
          {spec.sub && <p className="fx-modal-sub">{spec.sub}</p>}
          {spec.fields.map(f => (
            <label className="fx-field" key={f.id}>
              <span>{f.label}{f.min ? " · mín. " + f.min + " caracteres" : ""}</span>
              {f.type === "textarea"
                ? <textarea rows="3" value={vals[f.id] ?? ""} onChange={e => setVals(Object.assign({}, vals, { [f.id]: e.target.value }))} placeholder={f.placeholder} />
                : <input type={f.type || "text"} value={vals[f.id] ?? ""} onChange={e => setVals(Object.assign({}, vals, { [f.id]: e.target.value }))} placeholder={f.placeholder} />}
              {f.min && String(vals[f.id] || "").trim().length < f.min && <em>{String(vals[f.id] || "").trim().length}/{f.min}</em>}
            </label>
          ))}
          {spec.aviso && <div className="fx-modal-aviso">{spec.aviso}</div>}
        </div>
        <div className="fx-modal-f">
          <button className="fx-btn" onClick={onClose}>Cancelar</button>
          <button className={"fx-btn " + (spec.tone === "danger" ? "danger" : "primary")} disabled={invalido.length > 0}
            onClick={() => { spec.onConfirm(vals); onClose(); }}>{spec.confirmLabel}</button>
        </div>
      </div>
    </>
  );
};

// ─── ⌘K palette cross-fiscal (GET /fiscal/palette/search) ───
window.FxPalette = function FxPalette() {
  const st = window.useFiscalStore();
  const [open, setOpen] = useStateFa(false);
  const [q, setQ] = useStateFa("");
  const [cur, setCur] = useStateFa(0);
  const inputRef = useRefFa(null);

  useEffectFa(() => {
    const h = (e) => {
      const tag = (e.target && e.target.tagName || "").toLowerCase();
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") { e.preventDefault(); setOpen(v => !v); return; }
      if (e.key === "Escape") setOpen(false);
      if (e.key === "?" && tag !== "input" && tag !== "textarea") { /* atalhos: silencioso no F1 */ }
    };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, []);
  useEffectFa(() => { if (open && inputRef.current) inputRef.current.focus(); setCur(0); }, [open, q]);

  const itens = useMemoFa(() => {
    if (!st.notas) return [];
    const s = q.trim().toLowerCase(), sn = s.replace(/\D/g, "");
    const telas = [
      { id: "t1", grupo: "Ir para", label: "Notas fiscais", rota: "fiscal" },
      { id: "t2", grupo: "Ir para", label: "NF-e · NFC-e", rota: "fiscal-nfe" },
      { id: "t3", grupo: "Ir para", label: "NFS-e", rota: "fiscal-nfse" },
      { id: "t4", grupo: "Ir para", label: "Eventos fiscais", rota: "fiscal-eventos" },
      { id: "t5", grupo: "Ir para", label: "Manifesto DF-e", rota: "fiscal-dfe" },
      { id: "t6", grupo: "Ir para", label: "Certificado e configuração", rota: "fiscal-config" },
      { id: "t7", grupo: "Ir para", label: "SPED e livros", rota: "fiscal-sped" },
    ];
    const casa = (txt) => !s || String(txt).toLowerCase().indexOf(s) >= 0;
    const notas = st.notas.filter(n => casa(n.num) || casa(n.cliente) || (sn.length >= 3 && n.keyOrCode.indexOf(sn) >= 0))
      .slice(0, 6).map(n => ({ id: n.id, grupo: "Notas", label: n.tipo + " " + n.num + " · " + n.cliente, hint: n.when, rota: n.kind === "nfse" ? "fiscal-nfse" : "fiscal-nfe" }));
    const dfe = st.dfe.filter(d => casa(d.num) || casa(d.emitente) || (sn.length >= 3 && d.chave.indexOf(sn) >= 0))
      .slice(0, 4).map(d => ({ id: d.id, grupo: "DF-e recebidos", label: "NF-e entrada " + d.num + " · " + d.emitente, hint: d.status, rota: "fiscal-dfe" }));
    return notas.concat(dfe).concat(telas.filter(t => casa(t.label)));
  }, [q, st.seq, st.notas]);

  if (!open) return null;
  const escolhe = (it) => { setOpen(false); setQ(""); if (window.__selectRoute) window.__selectRoute(it.rota); };
  let grupoAtual = null;
  return (
    <>
      <div className="fx-scrim" onClick={() => setOpen(false)}></div>
      <div className="fx-cmdk" role="dialog" aria-modal="true" aria-label="Busca fiscal">
        <input ref={inputRef} value={q} placeholder="Buscar nota, chave, DF-e ou tela fiscal…" onChange={e => setQ(e.target.value)}
          onKeyDown={e => {
            if (e.key === "ArrowDown") { e.preventDefault(); setCur(c => Math.min(c + 1, itens.length - 1)); }
            if (e.key === "ArrowUp") { e.preventDefault(); setCur(c => Math.max(c - 1, 0)); }
            if (e.key === "Enter" && itens[cur]) escolhe(itens[cur]);
          }} />
        <div className="fx-cmdk-l">
          {itens.length === 0 && <div className="fx-cmdk-vazio">Nada encontrado em notas, DF-e ou telas.</div>}
          {itens.map((it, i) => {
            const cab = it.grupo !== grupoAtual ? (grupoAtual = it.grupo) : null;
            return (
              <React.Fragment key={it.id}>
                {cab && <div className="fx-cmdk-g">{cab}</div>}
                <button className={"fx-cmdk-i " + (i === cur ? "cur" : "")} onMouseEnter={() => setCur(i)} onClick={() => escolhe(it)}>
                  <span>{it.label}</span>{it.hint && <em>{it.hint}</em>}
                </button>
              </React.Fragment>
            );
          })}
        </div>
        <div className="fx-cmdk-f"><span>↑↓ navegar</span><span>↵ abrir</span><span>esc fechar</span></div>
      </div>
    </>
  );
};
