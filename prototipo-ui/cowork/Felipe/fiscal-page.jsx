// fiscal-page.jsx — Módulo Fiscal (cockpit unificado) · window.FiscalPage
// Import do vivo: Modules/Fiscal + resources/js/Pages/Fiscal/{Cockpit,Nfe,Nfse,...}.tsx
// Ondas: 1 mutações (fiscal-actions.jsx) · 2 ⌘K + teclado · 3 densidade/visões/paginação/write-off · 4 SPED.
const { useState: useStateFx, useMemo: useMemoFx, useEffect: useEffectFx, useRef: useRefFx } = React;

const FxI = ({ name, size = 13 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const fxD = () => window.FISCAL_DATA;
const fxBrl = (v) => "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fxKey = (k) => (k && k.length > 20 ? k.slice(0, 10) + "…" + k.slice(-8) : k);
const fxGo = (rota) => { if (rota && window.__selectRoute) window.__selectRoute(rota); };
const fxLS = (k, v) => { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) {} return null; };

const fxRejected = (n) => !n.cancelada && (n.statusKind === "sefaz" ? fxD().REJ_CODES.includes(n.status) : n.status === "rejeitada");
const fxProcessing = (n) => !n.cancelada && (n.statusKind === "sefaz" ? n.status === 999 : n.status === "processando");
const fxAuthorized = (n) => !n.cancelada && (n.statusKind === "sefaz" ? n.status === 100 : n.status === "autorizada");
const fxTone = (n) => (n.cancelada ? "bad" : fxAuthorized(n) ? "ok" : fxRejected(n) ? "bad" : "warn");
const fxStatusLabel = (n) => (n.cancelada ? "Cancelada" : n.statusKind === "sefaz" ? (fxD().STATUS_LABEL[n.status] || ("Status " + n.status)) : String(n.status).charAt(0).toUpperCase() + String(n.status).slice(1));

const FX_TABS = [
  { id: "fiscal", label: "Notas fiscais" },
  { id: "fiscal-nfe", label: "NF-e · NFC-e" },
  { id: "fiscal-nfse", label: "NFS-e" },
  { id: "fiscal-eventos", label: "Eventos" },
  { id: "fiscal-dfe", label: "Manifesto DF-e" },
  { id: "fiscal-config", label: "Certificado" },
  { id: "fiscal-sped", label: "SPED e livros" },
];

function FxSubnav({ current }) {
  const st = window.useFiscalStore();
  const counts = {
    "fiscal-nfe": st.notas.filter(n => n.kind === "nfe").length,
    "fiscal-nfse": st.notas.filter(n => n.kind === "nfse").length,
    "fiscal-eventos": st.eventos.length,
    "fiscal-dfe": st.dfe.filter(d => d.status === "pendente").length,
  };
  return (
    <nav className="cli-moduletopnav" aria-label="Sub-páginas do módulo Fiscal">
      {FX_TABS.map(t => (
        <button key={t.id} className={"cli-moduletopnav-tab " + (current === t.id ? "active" : "")} onClick={() => fxGo(t.id)} aria-current={current === t.id ? "page" : undefined}>
          <span>{t.label}</span>
          {counts[t.id] != null && <span className="cli-moduletopnav-n">{counts[t.id]}</span>}
        </button>
      ))}
    </nav>
  );
}

function FxHeader({ title, crumb, children }) {
  const s = fxD().SEFAZ;
  const st = window.useFiscalStore();
  return (
    <header className="fx-h">
      <div>
        <h1>{title}</h1>
        <p>{crumb}</p>
      </div>
      <div className="fx-h-r">
        <span className="fx-env" data-ok={s.operacional ? "true" : "false"}><i></i>{s.label}<window.FxProc k="sefaz" /></span>
        <button className="fx-btn" data-contract="procedencia" aria-pressed={st.proc} onClick={window.fxToggleProc} title="Mostra, por superfície, o que é leitura real e o que é demonstração"><FxI name="audit" /> Procedência</button>
        <span className="fx-kbd" title="Busca fiscal"><b>⌘</b><b>K</b></span>
        {children}
      </div>
    </header>
  );
}

// Onda 5 · débitos declarados no vivo, por tela
function FxDebitosPage({ tela }) {
  const itens = (fxD().DEBITOS || []).filter(d => d.tela === tela);
  if (!itens.length) return null;
  return (
    <div className="fx-debitos">
      <h3>Débitos conhecidos desta tela</h3>
      {itens.map((d, i) => <div className="fx-debito" data-tom={d.tom} key={i}><b>{d.titulo}</b><small>{d.texto}</small></div>)}
    </div>
  );
}

function FxSpark({ data }) {
  const max = Math.max.apply(null, data.concat([1]));
  const pts = data.map((v, i) => (i / (data.length - 1)) * 56 + "," + (14 - (v / max) * 12)).join(" ");
  return <svg width="56" height="15" viewBox="0 0 56 15" fill="none" aria-hidden="true"><polyline points={pts} stroke="currentColor" strokeWidth="1.2" /></svg>;
}

function FxEmitir({ onInutilizar }) {
  const [open, setOpen] = useStateFx(false);
  const ref = useRefFx(null);
  useEffectFx(() => {
    if (!open) return;
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="fx-pop" ref={ref}>
      <button className="fx-btn primary" onClick={() => setOpen(v => !v)} aria-haspopup="menu" aria-expanded={open}><FxI name="plus" /> Emitir</button>
      {open && (
        <div className="fx-pop-menu" role="menu">
          <button role="menuitem" onClick={() => { setOpen(false); fxGo("fiscal-nfe"); }}><FxI name="receipt" /> NF-e</button>
          <button role="menuitem" onClick={() => { setOpen(false); fxGo("fiscal-nfe"); }}><FxI name="receipt" /> NFC-e</button>
          <button role="menuitem" onClick={() => { setOpen(false); fxGo("fiscal-nfse"); }}><FxI name="doc" /> NFS-e</button>
          <button role="menuitem" onClick={() => { setOpen(false); onInutilizar(); }}><FxI name="audit" /> Inutilizar faixa</button>
        </div>
      )}
    </div>
  );
}

function FxRibbon() {
  const k = fxD().KPIS, sp = fxD().SPARK;
  return (
    <div className="fx-ribbon" data-contract="kpi-ribbon" role="region" aria-label="KPIs fiscais">
      <span className="fx-ri"><small>Emitidas <window.FxProc k="kpis" /></small><b>{k.emitidas}</b><em className="up">↑ 12 vs abr</em><FxSpark data={sp.emitidas} /></span>
      <span className="fx-ri"><small>Autorizadas</small><b className="ok">{k.autorizadas}</b><em>{k.autorizadasPct}%</em><FxSpark data={sp.autorizadas} /></span>
      <span className="fx-ri"><small>Rejeitadas</small><b className={k.rejeitadas > 0 ? "bad" : ""}>{k.rejeitadas}</b>{k.rejeitadas > 0 && <em className="down">requer ação</em>}<FxSpark data={sp.rejeitadas} /></span>
      <span className="fx-ri"><small>DF-e p/ manifestar</small><b>{k.dfeAguardando}</b><em>prazo 90d</em></span>
      <span className="fx-ri"><small>Certif. A1</small><b>{k.certificadoValidadeDias}d</b><em>{k.certificadoValidadeDias <= 30 ? "renovar" : "vigente"}</em></span>
      <span className="fx-ri"><small>Faturado fiscal</small><b>{fxBrl(k.faturamentoFiscal)}</b><em>maio/2026</em></span>
      <button className="fx-ribbon-cta" onClick={() => fxGo("fiscal-sped")}>Fechar mês →</button>
    </div>
  );
}

function FxAlerts() {
  return (
    <div className="fx-alerts" data-contract="alertas-fiscais">
      <window.FxProc k="alerts" />
      {fxD().ALERTS.map((a, i) => (
        <div className="fx-alert" data-level={a.level} key={i}>
          <span className="fx-alert-ic"><FxI name={a.icon} size={14} /></span>
          <span className="fx-alert-t"><b>{a.title}</b><small>{a.sub}</small></span>
          <button className="fx-btn" onClick={() => fxGo(a.goto)}>{a.action}</button>
        </div>
      ))}
    </div>
  );
}

// Onda 3 · write-off de auditoria mensal (determinístico, sem IA)
function FxWriteOff() {
  const [aberto, setAberto] = useStateFx(true);
  const w = fxD().WRITEOFF;
  if (!w || !aberto) return null;
  return (
    <div className="fx-writeoff" data-contract="write-off">
      <span className="fx-alert-ic"><FxI name="audit" size={14} /></span>
      <div className="fx-writeoff-t">
        <b>{w.totalCandidates.toLocaleString("pt-BR")} títulos candidatos a baixa por incobrabilidade <window.FxProc k="writeoff" /></b>
        <small>{w.scopeLabel} · {fxBrl(w.totalValor)} · mais antigo há {w.oldestAge} dias · critério determinístico, revisão do contador</small>
      </div>
      <button className="fx-btn" onClick={() => fxGo("fin-receber")}>Revisar</button>
      <button className="fx-btn" onClick={() => setAberto(false)}>Depois</button>
    </div>
  );
}

function FxNotasTable({ rows, selected, onToggle, onToggleAll, onOpen, openedId, cursor, density, onCliente, onAcao }) {
  if (!rows.length) return <div className="fx-empty"><b>Nenhuma nota pra esses filtros</b><small>Tente outro preset ou limpe a busca.</small></div>;
  return (
    <div className={"fx-table fx-d-" + density} data-contract="tabela-notas">
      <table>
        <thead>
          <tr>
            <th style={{ width: 34 }}><input type="checkbox" checked={selected.size === rows.length && rows.length > 0} onChange={onToggleAll} aria-label="Selecionar todas" /></th>
            <th style={{ width: 88 }}>Tipo</th>
            <th style={{ width: 88 }}>Número</th>
            <th>Cliente / chave</th>
            <th style={{ width: 168 }}>Status</th>
            <th style={{ width: 132 }}>Prazo</th>
            <th style={{ width: 190, textAlign: "right" }}>Valor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((n, i) => {
            const rej = fxRejected(n);
            return (
              <tr key={n.id} className={(openedId === n.id ? "sel " : "") + (cursor === i ? "cursor" : "")} onClick={() => onOpen(n.id)}>
                <td onClick={e => e.stopPropagation()}><input type="checkbox" checked={selected.has(n.id)} onChange={() => onToggle(n.id)} aria-label={"Selecionar nota " + n.num} /></td>
                <td><span className={"fx-tipo " + (n.tipo === "NFC-e" ? "t-nfce" : n.tipo === "NFS-e" ? "t-nfse" : "t-nfe")}>{n.tipo}</span></td>
                <td className="num"><b>{n.num}</b><small>{n.serie ? "série " + n.serie : n.when}</small></td>
                <td className="cli">
                  <a className="fx-link" onClick={e => { e.preventDefault(); e.stopPropagation(); onCliente(n.cliente); }} title="Filtrar por este cliente"><b>{n.cliente}</b></a>
                  <div>{n.doc} · {n.uf}{n.venda ? " · " + n.venda : n.ref ? " · " + n.ref : ""}</div>
                  <code className="fx-key" title={n.keyOrCode}>{n.kind === "nfe" ? fxKey(n.keyOrCode) : "cód. " + n.keyOrCode + (n.iss ? " · " + n.iss + "% ISS" : "")}</code>
                </td>
                <td>
                  <span className={"fx-sefaz " + fxTone(n)}>{fxStatusLabel(n)}</span>
                  {n.rejMsg && <div className="fx-rej">↳ {n.rejMsg}</div>}
                </td>
                <td>
                  {n.prazoCancel ? <span className={"fx-timepill u-" + n.prazoCancel.urgency}>cancelar em <b>{n.prazoCancel.label}</b></span>
                    : n.prazoCce ? <span className={"fx-timepill u-" + n.prazoCce.urgency}>CC-e <b>{n.prazoCce.label}</b></span>
                    : rej ? <span className="fx-timepill u-crit"><b>ação</b></span>
                    : <span style={{ color: "var(--text-mute)" }}>—</span>}
                </td>
                <td className="val">
                  <span className="fx-row-acts" onClick={e => e.stopPropagation()}>
                    <button className="fx-row-act" title="Baixar XML">XML</button>
                    <button className="fx-row-act" title="Baixar DANFE">PDF</button>
                    {rej && <button className="fx-row-act danger" title="Retransmitir" onClick={() => onAcao("retransmitir", n)}>↻</button>}
                  </span>
                  {fxBrl(n.value)}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function FxNotaDrawer({ nota, onClose, onAcao }) {
  useEffectFx(() => {
    if (!nota) return;
    const h = (e) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, [nota, onClose]);
  if (!nota) return null;
  const n = nota, rej = fxRejected(n);
  const receita = n.statusKind === "sefaz" ? fxD().SEFAZ_ACTIONS[n.status] : null;
  return (
    <>
      <div className="fx-scrim" onClick={onClose}></div>
      <aside className="fx-drawer" data-contract="drawer-nota" role="dialog" aria-label={"Nota " + n.num}>
        <div className="fx-dr-h">
          <div>
            <h2>{n.tipo} {n.num}{n.serie ? " · série " + n.serie : ""}</h2>
            <p>{n.cliente} · {n.when}</p>
          </div>
          <button className="fx-dr-x" onClick={onClose} aria-label="Fechar">×</button>
        </div>
        <div className="fx-dr-b">
          <div className="fx-sec">
            <span className={"fx-sefaz " + fxTone(n)} style={{ alignSelf: "flex-start" }}>{fxStatusLabel(n)}{n.statusKind === "sefaz" && !n.cancelada ? " · cstat " + n.status : n.cancelada ? " · evento 110111" : ""}</span>
            {n.rejMsg && <div className="fx-rej">↳ {n.rejMsg}</div>}
          </div>
          {rej && receita && (
            <div className="fx-recipe">
              <b>O que fazer</b>
              <small style={{ fontSize: "var(--fs-3)", color: "var(--text-dim)" }}>{receita.causa}</small>
              <ol>{receita.passos.map((p, i) => <li key={i}>{p}</li>)}</ol>
            </div>
          )}
          <div className="fx-sec">
            <h3>{n.kind === "nfe" ? "Destinatário e operação" : "Tomador e serviço"}</h3>
            <dl className="fx-kv">
              <dt>{n.kind === "nfe" ? "Destinatário" : "Tomador"}</dt><dd>{n.cliente}</dd>
              <dt>Documento</dt><dd>{n.doc}</dd>
              <dt>{n.kind === "nfe" ? "UF" : "Município"}</dt><dd>{n.uf}</dd>
              {n.venda && <><dt>Venda</dt><dd>{n.venda}</dd></>}
              {n.ref && <><dt>Origem</dt><dd>{n.ref}</dd></>}
              {n.kind === "nfe" ? <><dt>Modelo</dt><dd>{n.modelo}</dd><dt>Chave</dt><dd>{n.keyOrCode}</dd></>
                : <><dt>Cód. serviço</dt><dd>{n.codServ} · LC 116</dd><dt>ISS</dt><dd>{n.iss}%</dd><dt>Competência</dt><dd>{n.competencia}</dd></>}
              <dt>Valor total</dt><dd>{fxBrl(n.value)}</dd>
            </dl>
          </div>
          {n.itens && n.itens.length > 0 && (
            <div className="fx-sec"><h3>Itens</h3>
              <div className="fx-list">{n.itens.map((it, i) => (
                <div className="fx-list-i" key={i}><span><b>{it.nome}</b><br /><code>{it.codigo} · {it.qtd}×</code></span><span className="mono">{fxBrl(it.vl * it.qtd)}</span></div>
              ))}</div>
            </div>
          )}
          {n.boleto && (
            <div className="fx-sec"><h3>Cobrança</h3>
              <div className="fx-list"><div className="fx-list-i"><span><b>{n.boleto.id}</b> · vence {n.boleto.venc} · {n.boleto.status}</span><span className="mono">{fxBrl(n.boleto.valor)}</span></div></div>
            </div>
          )}
          {n.arquivos && n.arquivos.length > 0 && (
            <div className="fx-sec"><h3>Arquivos</h3>
              <div className="fx-list">{n.arquivos.map((a, i) => <div className="fx-list-i" key={i}><span><b>{a.tipo}</b> {a.nome}</span><span>{a.tamanho}</span></div>)}</div>
            </div>
          )}
          {n.emails && n.emails.length > 0 && (
            <div className="fx-sec"><h3>E-mails</h3>
              <div className="fx-list">{n.emails.map((e, i) => <div className="fx-list-i" key={i}><span><b>{e.tipo}</b><br />{e.para}</span><span>{e.quando} · {e.status}</span></div>)}</div>
            </div>
          )}
          {n.eventos && n.eventos.length > 0 && (
            <div className="fx-sec"><h3>Eventos SEFAZ</h3>
              <div className="fx-list">{n.eventos.map(e => <div className="fx-list-i" key={e.id}><span><b>{e.tipo}{e.sequencia ? " #" + e.sequencia : ""}</b><br />{e.descricao}</span><span>{e.emit} · {e.autor}</span></div>)}</div>
            </div>
          )}
          {n.auditoria && n.auditoria.length > 0 && (
            <div className="fx-sec"><h3>Auditoria</h3>
              <div className="fx-list">{n.auditoria.map((a, i) => <div className="fx-list-i" key={i}><span><b>{a.autor}</b> {a.acao}</span><span>{a.quando}</span></div>)}</div>
            </div>
          )}
        </div>
        <div className="fx-dr-f">
          <button className="fx-btn">Baixar XML</button>
          <button className="fx-btn">{n.kind === "nfe" ? "Baixar DANFE" : "Baixar PDF"}</button>
          {n.kind === "nfe" && !rej && !n.cancelada && <button className="fx-btn" onClick={() => onAcao("cce", n)}>Carta de correção</button>}
          {n.kind === "nfe" && n.prazoCancel && !n.cancelada && <button className="fx-btn danger" onClick={() => onAcao("cancelar", n)}>Cancelar nota</button>}
          {rej && <button className="fx-btn primary" onClick={() => onAcao("retransmitir", n)}>Retransmitir</button>}
        </div>
      </aside>
    </>
  );
}

function FxContabilDrawer({ open, onClose }) {
  if (!open) return null;
  const c = fxD().CONTABIL;
  return (
    <>
      <div className="fx-scrim" onClick={onClose}></div>
      <aside className="fx-drawer" role="dialog" aria-label="Enviar para contabilidade">
        <div className="fx-dr-h">
          <div><h2>Enviar p/ contabilidade <window.FxProc k="contabil" /></h2><p>Competência {c.periodo} · {c.destinatario}</p></div>
          <button className="fx-dr-x" onClick={onClose} aria-label="Fechar">×</button>
        </div>
        <div className="fx-dr-b">
          <div className="fx-sec"><h3>Pré-checagem do pacote</h3>
            <div className="fx-list">{c.validacoes.map((v, i) => (
              <div className="fx-list-i" key={i}>
                <span style={{ color: v.ok === "warn" ? "var(--color-warning, var(--text))" : "var(--text-dim)" }}>{v.ok === "warn" ? "⚠ " : "✓ "}{v.label}</span>
                {v.action && <button className="fx-btn" onClick={() => fxGo(v.goto)}>{v.action}</button>}
              </div>
            ))}</div>
          </div>
          <div className="fx-sec"><h3>Conteúdo</h3>
            <dl className="fx-kv">
              <dt>NF-e autorizadas</dt><dd>{c.totais.autorizadas}</dd>
              <dt>NFS-e</dt><dd>{c.totais.nfse}</dd>
              <dt>Eventos</dt><dd>{c.totais.eventos}</dd>
            </dl>
          </div>
          <div className="fx-sec"><h3>Histórico de envios</h3>
            <div className="fx-list">{c.historico.map(h => <div className="fx-list-i" key={h.id}><span><b>{h.periodo}</b><br />{h.metodo} · {h.destino}</span><span>{h.enviadoEm} · {h.pacote}</span></div>)}</div>
          </div>
        </div>
        <div className="fx-dr-f">
          <button className="fx-btn primary" onClick={() => { onClose(); window.fxToast("Pacote de " + c.periodo + " enviado pra " + c.destinatario); }}>Enviar por e-mail</button>
          <button className="fx-btn" onClick={() => window.fxToast("Pacote " + c.periodo + " gerado pra download")}>Baixar pacote (ZIP)</button>
        </div>
      </aside>
    </>
  );
}

// ─────────── Sub-páginas 1/2/3 · Notas (cockpit · NF-e/NFC-e · NFS-e) ───────────
function FxNotasPage({ preset, title, crumbExtra, route }) {
  const st = window.useFiscalStore();
  const base = useMemoFx(() => (preset ? st.notas.filter(n => preset.includes(n.tipo)) : st.notas), [st.notas, st.seq, preset]);
  const [view, setView] = useStateFx("todas");
  const [tipo, setTipo] = useStateFx("todos");
  const [status, setStatus] = useStateFx("todos");
  const [busca, setBusca] = useStateFx("");
  const [cliente, setCliente] = useStateFx(null);
  const [selected, setSelected] = useStateFx(new Set());
  const [openedId, setOpenedId] = useStateFx(null);
  const [contabil, setContabil] = useStateFx(false);
  const [modal, setModal] = useStateFx(null);
  const [density, setDensity] = useStateFx(() => fxLS("oimpresso.fiscal.densidade") || "comfort");
  const [pagina, setPagina] = useStateFx(1);
  const [porPagina, setPorPagina] = useStateFx(8);
  const [cursor, setCursor] = useStateFx(-1);

  useEffectFx(() => { fxLS("oimpresso.fiscal.densidade", density); }, [density]);

  const filtrados = useMemoFx(() => {
    let r = base;
    if (tipo !== "todos") r = r.filter(n => n.tipo === tipo);
    if (status === "autorizadas") r = r.filter(fxAuthorized);
    if (status === "rejeitadas") r = r.filter(fxRejected);
    if (status === "processando") r = r.filter(fxProcessing);
    if (status === "cancelaveis") r = r.filter(n => n.kind === "nfe" && n.prazoCancel);
    if (cliente) r = r.filter(n => n.cliente === cliente);
    if (busca.trim()) {
      const s = busca.toLowerCase(), sn = s.replace(/\D/g, "");
      r = r.filter(n => n.num.includes(s) || n.cliente.toLowerCase().includes(s) || (sn.length >= 3 && n.keyOrCode.indexOf(sn) >= 0));
    }
    return r;
  }, [base, tipo, status, cliente, busca, st.seq]);

  const paginas = Math.max(1, Math.ceil(filtrados.length / porPagina));
  const rows = useMemoFx(() => filtrados.slice((pagina - 1) * porPagina, pagina * porPagina), [filtrados, pagina, porPagina]);

  useEffectFx(() => { setSelected(new Set()); setPagina(1); setCursor(-1); }, [tipo, status, busca, cliente, porPagina]);

  // Onda 2 · J/K + Enter (charter Nfe DoD 5)
  useEffectFx(() => {
    const h = (e) => {
      const tag = (e.target && e.target.tagName || "").toLowerCase();
      if (tag === "input" || tag === "textarea" || e.metaKey || e.ctrlKey || modal || openedId) return;
      const k = e.key.toLowerCase();
      if (k === "j") { e.preventDefault(); setCursor(c => Math.min((c < 0 ? -1 : c) + 1, rows.length - 1)); }
      if (k === "k") { e.preventDefault(); setCursor(c => Math.max(c - 1, 0)); }
      if (e.key === "Enter" && cursor >= 0 && rows[cursor]) { e.preventDefault(); setOpenedId(rows[cursor].id); }
      if (k === "n") { e.preventDefault(); fxGo("fiscal-nfe"); }
    };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, [rows, cursor, modal, openedId]);

  const contaView = (v) => {
    let r = v.tipo === "todos" ? base : base.filter(n => n.tipo === v.tipo);
    if (v.status === "rejeitadas") r = r.filter(fxRejected);
    if (v.status === "processando") r = r.filter(fxProcessing);
    if (v.status === "cancelaveis") r = r.filter(n => n.kind === "nfe" && n.prazoCancel);
    return r.length;
  };
  const aplicaView = (v) => { setView(v.id); setTipo(v.tipo); setStatus(v.status); setCliente(null); setBusca(""); };
  const toggle = (id) => setSelected(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const toggleAll = () => setSelected(s => (s.size === rows.length ? new Set() : new Set(rows.map(r => r.id))));
  const opened = st.notas.find(n => n.id === openedId) || null;
  const rejTotal = base.filter(fxRejected).length;

  // Onda 1 · specs dos modais de mutação
  const acao = (tipoAcao, n) => {
    if (tipoAcao === "retransmitir") { window.FxActions.retransmitir(n.id); setOpenedId(null); return; }
    if (tipoAcao === "cancelar") {
      setModal({
        title: "Cancelar " + n.tipo + " " + n.num, tone: "danger", confirmLabel: "Cancelar nota na SEFAZ",
        sub: "Evento 110111 · janela legal aberta por " + (n.prazoCancel ? n.prazoCancel.label : "—") + ". A venda vinculada entra em cascata de cancelamento (FSM).",
        fields: [{ id: "just", label: "Justificativa", type: "textarea", min: 15, placeholder: "Ex.: cliente desistiu da compra antes da produção" }],
        aviso: "Cancelamento é irreversível e vai pro Fisco. Fora da janela, o caminho é nota de devolução.",
        onConfirm: (v) => { window.FxActions.cancelar(n.id, v.just.trim()); setOpenedId(null); },
      });
    }
    if (tipoAcao === "cce") {
      setModal({
        title: "Carta de correção · " + n.tipo + " " + n.num, confirmLabel: "Transmitir CC-e",
        sub: "Evento 110110 · até 30 dias da autorização. Não corrige valor, destinatário nem data de emissão.",
        fields: [{ id: "texto", label: "Texto da correção", type: "textarea", min: 15, placeholder: "Ex.: corrigir informação adicional da natureza da operação" }],
        onConfirm: (v) => window.FxActions.cce(n.id, v.texto.trim()),
      });
    }
    if (tipoAcao === "inutilizar") {
      setModal({
        title: "Inutilizar faixa numérica", confirmLabel: "Inutilizar na SEFAZ", tone: "danger",
        sub: "cstat 102 · para números saltados que nunca viraram nota. Não serve pra cancelar nota autorizada.",
        fields: [
          { id: "serie", label: "Série", value: "1" },
          { id: "de", label: "Número inicial", type: "number", min: 1, value: "" },
          { id: "ate", label: "Número final", type: "number", min: 1, value: "" },
          { id: "just", label: "Justificativa", type: "textarea", min: 15, placeholder: "Ex.: faixa saltada por erro de digitação no emissor" },
        ],
        onConfirm: (v) => window.FxActions.inutilizar(v.serie, v.de, v.ate, v.just.trim()),
      });
    }
  };

  return (
    <div className="fx-page">
      <FxHeader title={title} crumb={`Maio 2026 · ${base.length} notas${crumbExtra ? " · " + crumbExtra : ""}${rejTotal ? " · " + rejTotal + " requerem ação" : ""}`}>
        <button className="fx-btn" onClick={() => fxGo("fiscal-eventos")}><FxI name="refresh" /> Eventos <b style={{ fontFamily: "var(--font-mono)", fontSize: "var(--fs-1)" }}>{st.eventos.length}</b></button>
        <button className="fx-btn" onClick={() => setContabil(true)}><FxI name="archive" /> Enviar p/ contabilidade</button>
        <FxEmitir onInutilizar={() => acao("inutilizar", {})} />
      </FxHeader>
      <FxSubnav current={route} />
      {route === "fiscal" && <FxRibbon />}
      {route === "fiscal" && <FxAlerts />}
      {route === "fiscal" && <FxWriteOff />}
      <div className="fx-toolbar" data-contract="toolbar-notas">
        <div className="fx-search">
          <FxI name="search" />
          <input type="search" placeholder="Buscar nº, cliente, CNPJ, chave…" value={busca} onChange={e => setBusca(e.target.value)} />
        </div>
        <select className="fx-select" value={tipo} onChange={e => { setTipo(e.target.value); setView("custom"); }} aria-label="Filtrar por tipo">
          <option value="todos">Todos os tipos · {base.length}</option>
          {["NF-e", "NFC-e", "NFS-e"].filter(t => base.some(n => n.tipo === t)).map(t => <option key={t} value={t}>{t} · {base.filter(n => n.tipo === t).length}</option>)}
        </select>
        <select className="fx-select" value={status} onChange={e => { setStatus(e.target.value); setView("custom"); }} aria-label="Filtrar por status">
          <option value="todos">Todos status · {base.length}</option>
          <option value="autorizadas">Autorizadas · {base.filter(fxAuthorized).length}</option>
          <option value="rejeitadas">Rejeitadas · {base.filter(fxRejected).length}</option>
          <option value="cancelaveis">Janela 24h · {base.filter(n => n.kind === "nfe" && n.prazoCancel).length}</option>
          <option value="processando">Processando · {base.filter(fxProcessing).length}</option>
        </select>
        <div className="fx-density" role="radiogroup" aria-label="Densidade da tabela">
          {[["compact", "Compacto"], ["comfort", "Confortável"], ["relax", "Relaxado"]].map(d => (
            <button key={d[0]} className={density === d[0] ? "active" : ""} title={d[1]} aria-pressed={density === d[0]} onClick={() => setDensity(d[0])}>{d[1][0]}</button>
          ))}
        </div>
        <window.FxProc k="notas" />
      </div>
      <div className="fx-chips" data-contract="visoes-salvas" role="group" aria-label="Visões salvas">
        {fxD().SAVED_VIEWS.filter(v => !preset || v.tipo === "todos" || preset.includes(v.tipo)).map(v => (
          <button key={v.id} className="fx-chip" data-tone={v.tone || null} aria-pressed={view === v.id} onClick={() => aplicaView(v)}>{v.label} <b>{contaView(v)}</b></button>
        ))}
        {view === "custom" && <button className="fx-chip" aria-pressed="true">Filtro manual <b>{filtrados.length}</b></button>}
        <window.FxProc k="viewCounts" />
      </div>
      {cliente && <div className="fx-active-filter"><span>Filtrando por cliente:</span><b>{cliente}</b><button onClick={() => setCliente(null)} aria-label="Limpar filtro">×</button></div>}
      {selected.size > 0 && (
        <div className="fx-bulk" role="region" aria-label="Ações em lote">
          <span><b>{selected.size}</b> nota{selected.size > 1 ? "s" : ""} selecionada{selected.size > 1 ? "s" : ""}</span>
          <button className="fx-btn" onClick={() => window.fxToast(selected.size + " XMLs empacotados em ZIP")}>Baixar XMLs (ZIP)</button>
          <button className="fx-btn" onClick={() => window.fxToast(selected.size + " DANFEs geradas em PDF")}>Baixar DANFEs (PDF)</button>
          <button className="fx-btn" onClick={() => window.fxToast("Reenvio disparado pra " + selected.size + " notas")}>Reenviar por e-mail</button>
          <button className="fx-btn" onClick={() => setSelected(new Set())}>Limpar seleção</button>
        </div>
      )}
      <FxNotasTable rows={rows} selected={selected} onToggle={toggle} onToggleAll={toggleAll} onOpen={setOpenedId} openedId={openedId} cursor={cursor} density={density} onCliente={setCliente} onAcao={acao} />
      {filtrados.length > 0 && (
        <div className="fx-pager">
          <span>{(pagina - 1) * porPagina + 1}–{Math.min(pagina * porPagina, filtrados.length)} de {filtrados.length}</span>
          <select className="fx-select" value={porPagina} onChange={e => setPorPagina(Number(e.target.value))} aria-label="Notas por página">
            <option value="8">8 por página</option><option value="25">25 por página</option><option value="50">50 por página</option>
          </select>
          <button className="fx-btn" disabled={pagina <= 1} onClick={() => setPagina(p => p - 1)}>Anterior</button>
          <span className="fx-pager-n">{pagina} / {paginas}</span>
          <button className="fx-btn" disabled={pagina >= paginas} onClick={() => setPagina(p => p + 1)}>Próxima</button>
          <span className="fx-pager-hint">J/K navega · ↵ abre · N emite</span>
        </div>
      )}
      {route === "fiscal-nfe" && (
        <div className="fx-card">
          <div className="fx-card-h"><span>Entrada de terceiros</span></div>
          <div className="fx-row">
            <span className="fx-row-l"><b style={{ color: "var(--text)" }}>Importar XML de entrada</b><br />Backlog F2 no vivo — depende do NfeBrasil expor o endpoint de importação. Enquanto isso, nota emitida contra o CNPJ se resolve no Manifesto DF-e.</span>
            <span style={{ display: "flex", gap: 6 }}>
              <button className="fx-btn" disabled title="Endpoint de importação não existe no vivo">Importar XML</button>
              <button className="fx-btn" onClick={() => fxGo("fiscal-dfe")}>Abrir DF-e</button>
            </span>
          </div>
        </div>
      )}
      <FxDebitosPage tela={route} />
      <FxNotaDrawer nota={opened} onClose={() => setOpenedId(null)} onAcao={acao} />
      <FxContabilDrawer open={contabil} onClose={() => setContabil(false)} />
      <window.FxActionModal spec={modal} onClose={() => setModal(null)} />
      <window.FxPalette />
      <window.FxToasts />
    </div>
  );
}

Object.assign(window, {
  FxUI: { I: FxI, D: fxD, brl: fxBrl, key: fxKey, go: fxGo, ls: fxLS, tone: fxTone, statusLabel: fxStatusLabel, rejected: fxRejected, Header: FxHeader, Subnav: FxSubnav },
  FiscalPage: function FiscalPage({ view }) {
    if (view === "eventos") return <window.FxEventosPage />;
    if (view === "dfe") return <window.FxDfePage />;
    if (view === "config") return <window.FxConfigPage />;
    if (view === "sped") return <window.FxSpedPage />;
    if (view === "nfe") return <FxNotasPage route="fiscal-nfe" title="NF-e · NFC-e" preset={["NF-e", "NFC-e"]} crumbExtra="modelos 55 e 65" />;
    if (view === "nfse") return <FxNotasPage route="fiscal-nfse" title="NFS-e" preset={["NFS-e"]} crumbExtra="Sistema Nacional NT 2024-001" />;
    return <FxNotasPage route="fiscal" title="Notas fiscais" />;
  },
});
