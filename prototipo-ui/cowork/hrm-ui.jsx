// hrm-ui.jsx — HRM: primitivos da tela + pontes pro DS vivo (onda HRM-O4).
// Leitura do bundle em tempo de render + fallback pras classes do shell (mesmo
// idioma do acessos-ds.jsx). Carrega ANTES de hrm-forms/hrm-extras/hrm-page.
// Expõe window.HrmUI.
(() => {
const { useState, useEffect, useMemo, useRef } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

// ── Base ──
function Badge({ tone = "muted", children }) { return <span className={`hrm-badge ${tone}`}>{children}</span>; }
function Card({ title, sub, aside, acao, children }) {
  return (
    <section className="hrm-card">
      {title && <h3>{title}{aside && <span> · {aside}</span>}{acao && <span className="hrm-card-acao">{acao}</span>}</h3>}
      {sub && <p className="hrm-card-sub">{sub}</p>}
      {children}
    </section>
  );
}
function Row({ t, s, v }) {
  return (
    <div className="hrm-row">
      <span className="hrm-row-l"><span className="hrm-row-t">{t}</span>{s && <span className="hrm-row-s">{s}</span>}</span>
      {v != null && <span className="hrm-row-v">{v}</span>}
    </div>
  );
}
function Seg({ value, onChange, options }) {
  return (
    <div className="hrm-seg" role="tablist">
      {options.map((o) => <button key={o.id} role="tab" aria-selected={value === o.id} className={value === o.id ? "on" : ""} onClick={() => onChange(o.id)}>{o.label}</button>)}
    </div>
  );
}
function Nota({ tone = "info", title, children }) {
  const { Alert } = ds();
  if (Alert) return <div className="hrm-note-ds"><Alert tone={tone} title={title}>{children}</Alert></div>;
  return <div className="hrm-note"><span><b>{title}</b> — {children}</span></div>;
}
function Kpis({ items }) {
  const { KpiCard } = ds();
  return (
    <div className="usr-kpis">
      {items.map((k) => KpiCard
        ? <KpiCard key={k.l} label={k.l} value={k.v} unit={k.unit} description={k.sub} tone={k.tone || "default"} spark={k.spark} />
        : <div key={k.l} className="usr-kpi"><span className="usr-kpi-v">{k.v}</span><span className="usr-kpi-l">{k.l}</span></div>)}
    </div>
  );
}
function Busca({ value, onChange, placeholder, inputRef }) {
  return (
    <div className="usr-search">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input ref={inputRef} value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} aria-label={placeholder} />
      {value && <button className="mod-search-x" onClick={() => onChange("")} aria-label="Limpar busca">×</button>}
    </div>
  );
}
function Drawer({ title, sub, onClose, children, footer, largo }) {
  useEffect(() => {
    const k = (e) => { if (e.key === "Escape") { e.stopPropagation(); onClose(); } };
    document.addEventListener("keydown", k);
    return () => document.removeEventListener("keydown", k);
  }, [onClose]);
  return (
    <>
      <div className="os-drawer-back" onClick={onClose}></div>
      <aside className={`os-drawer hrm-drawer ${largo ? "largo" : ""}`} role="dialog" aria-label={title}>
        <header className="os-drawer-h hrm-drawer-h">
          <div><h2>{title}</h2>{sub && <p>{sub}</p>}</div>
          <button className="hrm-drawer-x" onClick={onClose} aria-label="Fechar">×</button>
        </header>
        <div className="os-drawer-body hrm-drawer-body">{children}</div>
        {footer && <footer className="hrm-drawer-f">{footer}</footer>}
      </aside>
    </>
  );
}
function Sec({ title, children }) { return <div className="hrm-drawer-sec"><h4>{title}</h4>{children}</div>; }
function KV({ pairs }) {
  return <dl className="hrm-kv">{pairs.map(([k, v], i) => <React.Fragment key={i}><dt>{k}</dt><dd>{v}</dd></React.Fragment>)}</dl>;
}

// ── Campos (DS Input/Select/Textarea/DatePicker com fallback do módulo) ──
function Campo({ label, help, erro, valor, onChange, tipo = "text", placeholder, wide, foco }) {
  const { Input } = ds();
  const el = Input
    ? <Input label={label} help={help} error={erro} value={valor} type={tipo} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />
    : <label className="hrm-field"><span>{label}</span><input autoFocus={foco} type={tipo} value={valor} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />{erro ? <span className="hrm-erro">{erro}</span> : help && <span className="hrm-help">{help}</span>}</label>;
  return <div className={`hrm-campo ${wide ? "wide" : ""} ${erro ? "invalido" : ""}`}>{el}</div>;
}
function Escolha({ label, help, erro, valor, onChange, opcoes, wide }) {
  const { Select } = ds();
  const el = Select
    ? <Select label={label} help={help} error={erro} value={valor} onChange={(e) => onChange(e.target.value)}>{opcoes.map((o) => <option key={o.v} value={o.v}>{o.l}</option>)}</Select>
    : <label className="hrm-field"><span>{label}</span><select value={valor} onChange={(e) => onChange(e.target.value)}>{opcoes.map((o) => <option key={o.v} value={o.v}>{o.l}</option>)}</select>{erro ? <span className="hrm-erro">{erro}</span> : help && <span className="hrm-help">{help}</span>}</label>;
  return <div className={`hrm-campo ${wide ? "wide" : ""} ${erro ? "invalido" : ""}`}>{el}</div>;
}
function Texto({ label, help, valor, onChange, linhas = 3, wide = true }) {
  const { Textarea } = ds();
  const el = Textarea
    ? <Textarea label={label} help={help} value={valor} rows={linhas} onChange={(e) => onChange(e.target.value)} />
    : <label className="hrm-field"><span>{label}</span><textarea rows={linhas} value={valor} onChange={(e) => onChange(e.target.value)} />{help && <span className="hrm-help">{help}</span>}</label>;
  return <div className={`hrm-campo ${wide ? "wide" : ""}`}>{el}</div>;
}
function Data({ label, help, erro, valor, onChange, wide }) {
  const { DatePicker } = ds();
  const el = DatePicker
    ? <DatePicker label={label} value={valor || null} onChange={(d) => onChange(d ? new Date(d).toISOString().slice(0, 10) : "")} placeholder="dd/mm/aaaa" />
    : <label className="hrm-field"><span>{label}</span><input type="date" value={valor || ""} onChange={(e) => onChange(e.target.value)} /></label>;
  return <div className={`hrm-campo ${wide ? "wide" : ""} ${erro ? "invalido" : ""}`}>{el}{erro ? <span className="hrm-erro">{erro}</span> : help && <span className="hrm-help">{help}</span>}</div>;
}
function Periodo({ valor, onChange, label }) {
  const { PeriodBar } = ds();
  if (!PeriodBar) return null;
  return <div className="hrm-periodo"><PeriodBar value={valor} onChange={onChange} label={label || "Período"} /></div>;
}
function Grafico({ tipo = "bar", dados, cor, altura = 120, formata, destacaUltimo }) {
  const { Chart } = ds();
  if (!Chart) return null;
  return <Chart type={tipo} data={dados} color={cor || "var(--accent)"} height={altura} formatValue={formata} highlightLast={destacaUltimo} />;
}

// ── Tabela (DataTablePro do DS; fallback = os-table do shell) ──
function Tabela({ cols, rows, altura = 460, densidade = "comfortable", selecionavel, onLinha, onSelecao, ordem }) {
  const { DataTablePro } = ds();
  if (DataTablePro) return (
    <div className="hrm-tabela">
      <DataTablePro columns={cols} rows={rows} height={altura} density={densidade} selectable={selecionavel}
        onRowClick={onLinha} onSelectionChange={onSelecao} defaultSort={ordem} />
    </div>
  );
  const cell = (v) => v && typeof v === "object" && "primary" in v
    ? <><div className="hrm-name">{v.primary}</div>{v.sub && <div className="hrm-meta">{v.sub}</div>}</>
    : v;
  return (
    <div className="os-table-wrap"><table className="os-table">
      <thead><tr>{cols.map((c) => <th key={c.key} className={c.align === "right" ? "hrm-num" : ""}>{c.label}</th>)}</tr></thead>
      <tbody>{rows.map((r) => (
        <tr key={r.id} onClick={onLinha ? () => onLinha(r) : undefined} style={onLinha ? { cursor:"pointer" } : null}>
          {cols.map((c) => <td key={c.key} className={c.align === "right" ? "hrm-num" : ""}>{cell(r.cells ? r.cells[c.key] : r[c.key])}</td>)}
        </tr>))}</tbody>
    </table></div>
  );
}

function Paginacao({ pagina, paginas, onMudar, total, porPagina }) {
  const { Pagination } = ds();
  if (paginas <= 1) return null;
  if (Pagination) return <div className="hrm-pag"><Pagination page={pagina} pageCount={paginas} onChange={onMudar} total={total} pageSize={porPagina} /></div>;
  return (
    <div className="hrm-pag hrm-pag-fb">
      <button className="os-btn ghost" disabled={pagina <= 1} onClick={() => onMudar(pagina - 1)}>Anterior</button>
      <span className="hrm-mono">{pagina} de {paginas}</span>
      <button className="os-btn ghost" disabled={pagina >= paginas} onClick={() => onMudar(pagina + 1)}>Próxima</button>
    </div>
  );
}
function Bulk({ n, acoes, onFechar, rotulo }) {
  const { BulkBar } = ds();
  if (!n) return null;
  if (BulkBar) return <BulkBar count={n} label={rotulo || "selecionadas"} actions={acoes} onClose={onFechar} />;
  return (
    <div className="hrm-bulk">
      <b>{n}</b> {rotulo || "selecionadas"}
      {acoes.map((a) => <button key={a.label} className={`os-btn ${a.tone === "danger" ? "danger" : "ghost"}`} onClick={a.onClick}>{a.label}</button>)}
      <button className="os-btn ghost" onClick={onFechar}>Limpar</button>
    </div>
  );
}
function Skel({ n = 6 }) {
  const { Skeleton } = ds();
  if (Skeleton) return <div className="hrm-skel"><Skeleton variant="row" count={n} /></div>;
  return <div className="hrm-skel">{Array.from({ length:n }).map((_, i) => <div className="hrm-skel-row" key={i}></div>)}</div>;
}
function Vazio({ variante = "no-results", titulo, desc, acao }) {
  const { EmptyState } = ds();
  if (EmptyState) return <div className="hrm-vazio"><EmptyState variant={variante} title={titulo} description={desc} action={acao} /></div>;
  return <div className="hrm-vazio hrm-empty"><b>{titulo}</b><p>{desc}</p>{acao}</div>;
}
function Aviso({ msg, tone }) {
  const { Toast } = ds();
  if (!msg) return null;
  if (Toast) return <div className="hrm-toast-host"><Toast tone={tone || "default"}>{msg}</Toast></div>;
  return <div className="hrm-toast">{msg}</div>;
}

// ── Hooks de mecânica (onda HRM-O1) ──
function usePagina(lista, porPagina = 12) {
  const [pagina, setPagina] = useState(1);
  const paginas = Math.max(1, Math.ceil(lista.length / porPagina));
  useEffect(() => { if (pagina > paginas) setPagina(1); }, [paginas, pagina]);
  const fatia = useMemo(() => lista.slice((pagina - 1) * porPagina, pagina * porPagina), [lista, pagina, porPagina]);
  return { pagina, setPagina, paginas, fatia, porPagina, total:lista.length };
}
function useAviso() {
  const [aviso, setAviso] = useState(null);
  useEffect(() => { if (!aviso) return; const t = setTimeout(() => setAviso(null), 2800); return () => clearTimeout(t); }, [aviso]);
  return [aviso, setAviso];
}
function useAtalhos({ busca, onNovo, onEsc }) {
  useEffect(() => {
    const k = (e) => {
      const digitando = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);
      if (e.key === "/" && !digitando && busca?.current) { e.preventDefault(); busca.current.focus(); }
      if (e.key === "n" && !digitando && onNovo) { e.preventDefault(); onNovo(); }
      if (e.key === "Escape" && onEsc) onEsc();
    };
    document.addEventListener("keydown", k);
    return () => document.removeEventListener("keydown", k);
  }, [busca, onNovo, onEsc]);
}
// Carregamento simulado — a tela nunca aparece "pronta" antes de dizer que carregou
function useCarga(ms = 380) {
  const [carregando, setCarregando] = useState(true);
  useEffect(() => { const t = setTimeout(() => setCarregando(false), ms); return () => clearTimeout(t); }, [ms]);
  return carregando;
}

// ── Sessão do módulo: papel, estado e dados sobrevivem à troca de aba (cada aba é rota
// no shell, então HrmPage remonta e o useState morreria) ──
const SESS = window.__hrmSess || (window.__hrmSess = (() => {
  let papel = "admin", estado = "normal";
  try { papel = localStorage.getItem("oimpresso.hrm.papel") || papel; estado = localStorage.getItem("oimpresso.hrm.estado") || estado; } catch (e) {}
  return { papel, estado };
})());
function usePersist(chave, inicial) {
  const [v, setV] = React.useState(() => SESS[chave] !== undefined ? SESS[chave] : inicial);
  React.useEffect(() => {
    SESS[chave] = v;
    if (chave === "papel" || chave === "estado") { try { localStorage.setItem("oimpresso.hrm." + chave, v); } catch (e) {} }
  }, [chave, v]);
  return [v, setV];
}
function limparDados() { ["lic", "pre", "tur", "fer", "lotes", "folha", "metas"].forEach((k) => { delete SESS[k]; }); }

// ── Ambiente simulado: papel + estado da tela (ondas O9 e O10) ──
const CtxAmbiente = React.createContext(null);
function Ambiente({ valor, children }) { return <CtxAmbiente.Provider value={valor}>{children}</CtxAmbiente.Provider>; }
function useAmbiente() {
  const a = React.useContext(CtxAmbiente);
  const H = window.HRM;
  if (a) return a;
  return { papel:"admin", estado:"normal", eu:"e-1", demo:false, primeira:false, pode:() => true, dados:{ lic:H.LIC, pre:H.PRE, tur:H.TUR, fer:H.FER, lotes:H.LOTES, folha:H.FOLHA, tipos:H.TIPOS, metas:H.METAS }, din:(v) => H.brl(v) };
}
// Bloqueio de permissão com motivo (nunca tela em branco)
function SemPermissao({ frase }) {
  const { papel } = useAmbiente();
  return <Vazio variante="no-perm" titulo="Acesso restrito"
    desc={`${frase} O papel ${window.HRM.PAPEIS[papel].l.toLowerCase()} não tem essa permissão — quem precisa dela pede ao administrador. A tela não esconde o item do menu, explica o porquê.`}/>;
}

window.HrmUI = {
  Badge, Card, Row, Seg, Nota, Kpis, Busca, Drawer, Sec, KV,
  Campo, Escolha, Texto, Data, Periodo, Grafico,
  Tabela, Paginacao, Bulk, Skel, Vazio, Aviso, Ambiente, useAmbiente, SemPermissao,
  usePagina, useAviso, useAtalhos, useCarga, usePersist, limparDados,
};
})();
