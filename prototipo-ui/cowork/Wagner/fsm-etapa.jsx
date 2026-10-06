// fsm-etapa.jsx — as 4 peças comuns que toda tela com fluxo usa (ADR 0129, fase 2):
// 1 etapa atual (trilha) · 2 ações da etapa com condição, motivo do bloqueio e AÇÃO DE CORREÇÃO ·
// 3 prévia do que o sistema vai fazer antes de confirmar · 4 histórico da transição.
// + regras do modelo em uso (Fluxos e regras) para este processo, com a da etapa atual em destaque.
// modo="acao": o painel executa a transição (estado salvo por documento).
// modo="leitura": a tela é dona dos botões; quando ela muda o estado, o painel registra no histórico.
// Expõe window.OiEtapaPainel e window.OiEtapa.
(() => {
const { useState, useEffect } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const KEY = "oimpresso.etapa.v1";
const KREG = "oimpresso.fluxos.regras.v1";
const lerJ = (k) => { try { return JSON.parse(localStorage.getItem(k) || "{}") || {}; } catch (e) { return {}; } };
const gravarJ = (k, o) => { try { localStorage.setItem(k, JSON.stringify(o)); } catch (e) {} };
const ler = () => lerJ(KEY);
const agora = () => { const d = new Date(); return d.toLocaleDateString("pt-BR") + " " + d.toTimeString().slice(0, 5); };
// processo do painel → processo do modelo (fluxos-data.jsx)
const PROC_DOM = { venda: "venda", producao: "producao", compra: "compra", entrega: "entrega", cobranca: "financeiro", nfe: "financeiro", nfse: "financeiro", contrato: "financeiro" };

function abrirRegras(proc) {
  try { localStorage.setItem("oimpresso.fluxos.proc2", JSON.stringify(proc)); localStorage.setItem("oimpresso.fluxos.aba", JSON.stringify("processos")); } catch (e) {}
  if (window.__selectRoute) window.__selectRoute("fluxos");
}
// "tela" = último estado que a TELA informou (prop). Só muda quando a tela muda — assim uma transição
// feita pelo painel não é desfeita ao reabrir o documento.
function registrar(proc, docId, entrada, estado, tela) {
  const all = ler(), k = proc + ":" + docId, cur = all[k] || { hist: [] };
  all[k] = { estado, tela: tela !== undefined ? tela : cur.tela, hist: [{ em: agora(), ...entrada }].concat(cur.hist || []).slice(0, 30) };
  gravarJ(KEY, all); window.dispatchEvent(new Event("oi-etapa"));
}
const transitar = (proc, docId, t, de) => registrar(proc, docId, { quem: "Você", acao: t.acao, de, para: t.para }, t.para || de);
// regra do modelo ligada? (padrão: ligada)
const regraLigada = (mid, dom, i) => lerJ(KREG)[mid + ":" + dom + ":" + i] !== false;
function setRegra(mid, dom, i, v) { const o = lerJ(KREG); o[mid + ":" + dom + ":" + i] = v; gravarJ(KREG, o); window.dispatchEvent(new Event("oi-etapa")); }
function modeloEmUso() {
  const F = window.FLX; if (!F) return null;
  let v = null; try { v = JSON.parse(localStorage.getItem("oimpresso.fluxos.modelo.v1") || "null"); } catch (e) {}
  return v && F.MODELOS.find((m) => m.id === v.id) || null;
}

function RegrasModelo({ proc, atual }) {
  const m = modeloEmUso();
  const dom = PROC_DOM[proc];
  const pm = m && m.processos.find((x) => x.dom === dom);
  if (!m) return <p className="oie-mod-v">Nenhum modelo escolhido — <button type="button" className="oie-link in" onClick={() => { try { localStorage.setItem("oimpresso.fluxos.aba", JSON.stringify("modelos")); } catch (e) {} window.__selectRoute && window.__selectRoute("fluxos"); }}>escolher em Fluxos e regras</button>.</p>;
  if (!pm) return null;
  const regras = pm.regras.map((r, i) => ({ em: r[0], t: r[1], i, on: regraLigada(m.id, dom, i) }));
  return (
    <details className="oie-mod">
      <summary>Regras do modelo <b>{m.n}</b> · {regras.filter((r) => r.on).length} de {regras.length} ligadas</summary>
      <ul>{regras.map((r) => (
        <li key={r.i} className={(r.em === atual ? "agora " : "") + (r.on ? "" : "off")}>
          <span>Ao entrar em <b>{r.em}</b></span><span>{r.t}</span><em>{r.on ? "ligada" : "desligada"}</em>
        </li>))}
      </ul>
    </details>
  );
}

function OiEtapaPainel({ proc, estado, docId, bloqueios, resolver, interceptar, onAcao, modo, nota }) {
  const [prev, setPrev] = useState(null);
  const [, tick] = useState(0);
  useEffect(() => {
    const h = () => tick((n) => n + 1);
    window.addEventListener("oi-etapa", h); document.addEventListener("oi:lazy-done", h);
    return () => { window.removeEventListener("oi-etapa", h); document.removeEventListener("oi:lazy-done", h); };
  }, []);
  // Sincronia: o estado que a TELA informa mudou desde a última vez → a tela venceu; vira histórico.
  useEffect(() => {
    if (!estado || !proc || docId == null) return;
    const all = ler(), k = proc + ":" + docId, cur = all[k];
    if (!cur) { all[k] = { estado, tela: estado, hist: [] }; gravarJ(KEY, all); return; }
    if (cur.tela && cur.tela !== estado) registrar(proc, docId, { quem: "Tela", acao: "Mudança feita na tela", de: cur.estado || cur.tela, para: estado }, estado, estado);
    else if (!cur.tela) { cur.tela = estado; gravarJ(KEY, all); }
  }, [proc, docId, estado]);

  const P = window.FLX_PROC;
  const p = P && P.PROCS.find((x) => x.id === proc);
  if (!p) return null;
  const { Modal, Button } = ds();
  const leitura = modo === "leitura";
  const salvo = ler()[proc + ":" + docId];
  const atual = leitura ? (estado || p.estados[0].l) : ((salvo && salvo.estado) || estado || p.estados[0].l);
  const hist = (salvo && salvo.hist) || [];
  const linear = p.estados.filter((s) => s.tipo !== "x" && (!s.lado || s.l === atual));
  const idx = linear.findIndex((s) => s.l === atual);
  const est = p.estados.find((s) => s.l === atual);
  const fimX = est && est.tipo === "x", fimOk = est && est.tipo === "ok";
  const bl = (typeof bloqueios === "function" ? bloqueios() : bloqueios) || {};
  const rs = (typeof resolver === "function" ? resolver() : resolver) || {};
  // Transição feita pelo sistema/banco/SEFAZ não é botão: aparece como "acontece sozinho".
  const auto = (t) => /^(Sistema|Banco|SEFAZ|Prefeitura)/.test(t.quem || "");
  const todas = p.trans.filter((t) => t.de === atual);
  const acoes = todas.filter((t) => !auto(t)), sozinhas = todas.filter(auto);
  const presas = acoes.filter((t) => bl[t.acao]);
  const pri = acoes.findIndex((t) => !bl[t.acao]);

  const confirmar = () => {
    const t = prev; setPrev(null);
    if (interceptar && interceptar[t.acao]) { interceptar[t.acao](t); return; }
    transitar(proc, docId, t, atual);
    if (onAcao) onAcao(t);
  };

  return (
    <section className="oie" aria-label={"Etapa do processo " + p.n}>
      <div className="oie-h">
        <span className="oie-l">Etapa</span>
        <b>{atual}</b>
        <span className="oie-p">{p.n}</span>
        <button type="button" className="oie-link" onClick={() => abrirRegras(proc)}>Regras desta etapa</button>
      </div>
      <ol className="oie-trilha">
        {linear.map((s, i) => <li key={s.k} className={fimX ? "" : i < idx ? "feito" : i === idx ? "agora" : ""} aria-current={i === idx ? "step" : undefined}>{s.l}</li>)}
      </ol>
      {fimX && <p className="oie-fim">Encerrado em <b>{atual}</b> — nenhuma ação sai daqui.</p>}
      {nota && <p className="oie-nota">{nota}</p>}
      {acoes.length > 0 && (
        <div className="oie-acoes">
          <span className="oie-l">{leitura ? "O que pode acontecer daqui" : "Ações desta etapa"}</span>
          <div className="oie-bts">
            {acoes.map((t, i) => leitura
              ? <span key={i} className={"oie-chip" + (bl[t.acao] ? " preso" : "")}>{t.acao}{t.para ? " → " + t.para : ""}</span>
              : <button key={i} type="button" className={"oie-bt" + (i === pri ? " pri" : "")} disabled={!!bl[t.acao]} onClick={() => setPrev(t)}
                  title={bl[t.acao] || (t.guarda ? "Só passa se: " + t.guarda : "")}>{t.acao}{t.para ? " → " + t.para : ""}</button>)}
          </div>
          {presas.length > 0 && (
            <ul className="oie-bloq" aria-live="polite">
              {presas.map((t, i) => (
                <li key={i}><span><b>{t.acao}</b> bloqueada — {String(bl[t.acao]).charAt(0).toLowerCase() + String(bl[t.acao]).slice(1)}</span>
                  {rs[t.acao] && <button type="button" className="oie-fix" onClick={rs[t.acao].onClick}>{rs[t.acao].label}</button>}
                </li>))}
            </ul>
          )}
        </div>
      )}
      {sozinhas.length > 0 && (
        <div className="oie-auto">
          <span className="oie-l">Acontece sozinho</span>
          <ul>{sozinhas.map((t, i) => <li key={i}><b>{t.acao}</b>{t.para ? " → " + t.para : ""}<span>{t.quem}</span></li>)}</ul>
        </div>
      )}
      {todas.length === 0 && fimOk && <p className="oie-nota oie-conc">Fluxo concluído em <b>{atual}</b>.</p>}
      {todas.length === 0 && !fimX && !fimOk && <p className="oie-nota">Nenhuma ação nesta etapa.</p>}
      <RegrasModelo proc={proc} atual={atual} />
      {hist.length > 0 && (
        <details className="oie-hist">
          <summary>Histórico da etapa · {hist.length}</summary>
          <ol>{hist.map((h, i) => <li key={i}><span className="mono">{h.em}</span> · {h.quem} · <b>{h.acao}</b>: {h.de} → {h.para || "mesma etapa"}</li>)}</ol>
        </details>
      )}
      {Modal && (
        <Modal open={!!prev} onClose={() => setPrev(null)} title={prev ? prev.acao : ""} width={460}
          footer={prev && <>
            <Button variant="ghost" onClick={() => setPrev(null)}>Cancelar</Button>
            <Button variant="primary" onClick={confirmar}>Confirmar</Button>
          </>}>
          {prev && (
            <div className="oie-prev">
              <p>{prev.para ? <>De <b>{atual}</b> para <b>{prev.para}</b>.</> : <>Fica em <b>{atual}</b>.</>}</p>
              {prev.guarda && <p className="oie-ok"><span className="oie-l">Condição cumprida</span>{prev.guarda}</p>}
              <div><span className="oie-l">O sistema vai</span>
                {prev.efeito ? <ul>{prev.efeito.split(" · ").map((e, i) => <li key={i}>{e}</li>)}</ul> : <p>Só mudar a etapa.</p>}
              </div>
              {prev.quem && <p className="oie-dim">Quem pode: {prev.quem}</p>}
            </div>
          )}
        </Modal>
      )}
    </section>
  );
}

window.OiEtapaPainel = OiEtapaPainel;
window.OiEtapa = { abrirRegras, transitar, registrar, ler, regraLigada, setRegra, modeloEmUso, PROC_DOM };
})();
