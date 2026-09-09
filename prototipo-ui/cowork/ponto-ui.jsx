// ponto-ui.jsx — peças compartilhadas das telas do Ponto. ONDA 1 da migração pro DS vivo:
// os primitivos abaixo deixaram de ser paralelos e passaram a DELEGAR pro bundle
// (StatusBadge · Widget · KpiCard · Alert · EmptyState · Pagination · Button). A API
// exportada é a mesma — nenhuma tela do módulo mudou de chamada. Fallback bespoke `pt-*`
// só pra defense-in-depth (o bundle é `defer`; se não subir, a tela não cai).
// Expõe window.PontoUI. Sem estado próprio: só forma.
(() => {
const P = () => window.PONTO;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const Ic = (props) => window.JcIcon ? <window.JcIcon {...props} /> : null;

// Tons locais → tons do DS (o DS fala success/warn/danger/info/neutral/outline).
const TOM_DS = { neutral: "neutral", ok: "success", pos: "success", warn: "warning", danger: "danger", neg: "danger", info: "info", acc: "info", "": "default" };
const TOM_ALERTA = { info: "info", ok: "success", pos: "success", warn: "warn", danger: "danger", neg: "danger", acc: "info" };
// KpiCard variant="filter" fala outro dicionário (KPI_FILTER_TONE: primary/amber/rose/emerald/violet)
// e cai em `primary` no default — sem este mapa os 6 tiles do painel ficavam todos roxos.
const TOM_KPI_FILTRO = { ok: "emerald", pos: "emerald", warn: "amber", neg: "rose", danger: "rose", acc: "violet", info: "violet", neutral: "primary", "": "primary" };

const TOM_ESTADO_INTERC = { RASCUNHO: "neutral", PENDENTE: "warn", APROVADA: "ok", REJEITADA: "danger", APLICADA: "info", CANCELADA: "neutral" };
const TOM_ESTADO_APURACAO = { PENDENTE: "neutral", CALCULADO: "info", DIVERGENCIA: "warn", AJUSTADO: "info", CONSOLIDADO: "ok", FECHADO: "ok" };
const TOM_ESTADO_IMPORT = { PENDENTE: "neutral", PROCESSANDO: "info", CONCLUIDA: "ok", CONCLUIDA_COM_ERROS: "warn", FALHOU: "danger" };

// Rótulos em sentence case (o CAIXA-ALTA mono era vício do AdminLTE; o DS fala sentence case).
const ROTULO_APURACAO = { PENDENTE: "Pendente", CALCULADO: "Calculado", DIVERGENCIA: "Diverg\u00eancia", AJUSTADO: "Ajustado", CONSOLIDADO: "Consolidado", FECHADO: "Fechado" };
const ROTULO_IMPORT = { PENDENTE: "Pendente", PROCESSANDO: "Processando", CONCLUIDA: "Conclu\u00edda", CONCLUIDA_COM_ERROS: "Conclu\u00edda com erros", FALHOU: "Falhou" };

// Pílula de estado — DS StatusBadge. `kind` roteia pelo domínio (intercorrencia/prioridade/rep)
// quando existe; senão vai por `tone` + label literal (apuração e importação não têm domínio no DS).
function Pill({ tom = "neutral", mono, kind, value, children }) {
  const { StatusBadge } = DS();
  if (StatusBadge) {
    return kind
      ? <StatusBadge kind={kind} value={value} tone={tom !== "neutral" && !kind ? TOM_DS[tom] : undefined} />
      : <StatusBadge tone={TOM_DS[tom] || "outline"} label={children} />;
  }
  return <span className={"pt-pill " + tom + (mono ? " mono" : "")}>{children}</span>;
}
const PillIntercorrencia = ({ estado }) => <Pill kind="intercorrencia" value={String(estado || "").toLowerCase()} tom={TOM_ESTADO_INTERC[estado] || "neutral"}>{P().ESTADOS_INTERC[estado] || estado}</Pill>;
const PillApuracao = ({ estado }) => <Pill tom={TOM_ESTADO_APURACAO[estado] || "neutral"}>{ROTULO_APURACAO[estado] || estado}</Pill>;
const PillImportacao = ({ estado }) => <Pill tom={TOM_ESTADO_IMPORT[estado] || "neutral"}>{ROTULO_IMPORT[estado] || estado}</Pill>;
const PillPrioridade = ({ p }) => <Pill kind="prioridade" value={p === "URGENTE" ? "urgente" : "normal"} tom={p === "URGENTE" ? "danger" : "neutral"}>{p === "URGENTE" ? "Urgente" : "Normal"}</Pill>;
const PillSimNao = ({ v, sim = "Sim", nao = "Não" }) => <Pill tom={v ? "ok" : "neutral"}>{v ? sim : nao}</Pill>;

// Card do módulo — DS Widget. `contrato` continua saindo como data-contract (o CI lê isso).
// O `sub` do Blade vinha em dois sabores: contagem entre parênteses (vai pro badge, mono, ao lado
// do título) e frase de apoio (vai pro `note`, abaixo do título) — assim o h3 não trunca.
function Card({ icon, titulo, sub, acao, children, className, contrato, flush }) {
  const { Widget } = DS();
  if (Widget) {
    const s = typeof sub === "string" ? sub.trim() : null;
    const contagem = s && /^[(\u2014-]/.test(s) ? s.replace(/^[\u2014-]\s*/, "") : null;
    const nota = s && !contagem ? s : (typeof sub !== "string" ? sub : null);
    const tit = (titulo || icon) ? (
      <span style={{ display: "inline-flex", alignItems: "center", gap: 8, minWidth: 0 }}>
        {icon && <Ic name={icon} />}
        <span>{titulo}</span>
      </span>
    ) : null;
    const badge = contagem
      ? <span style={{ font: "500 11.5px/1 var(--font-mono)", color: "var(--text-dim)", whiteSpace: "nowrap" }}>{contagem}</span>
      : null;
    // Card cuja única cria é a tabela: sangra até a borda (o zebrado do DataGrid faz o mesmo).
    const kids = React.Children.toArray(children);
    const soTabela = kids.length === 1 && kids[0] && kids[0].type === Tabela;
    return (
      <div data-contract={contrato} className={className} style={{ minWidth: 0 }}>
        <Widget title={tit} badge={badge} note={nota} actions={acao} pad={14} flush={flush != null ? !!flush : soTabela}>{children}</Widget>
      </div>
    );
  }
  return (
    <section className={"pt-card" + (className ? " " + className : "")} data-contract={contrato}>
      {(titulo || acao) &&
        <header className="pt-card-h">
          {icon && <Ic name={icon} />}
          <b>{titulo}</b>
          {sub && <small>{sub}</small>}
          <span className="pt-sp" />
          {acao}
        </header>}
      <div className="pt-card-b">{children}</div>
    </section>
  );
}

// KPI — DS KpiCard. Com onClick é KPI-filtro (variant="filter": o anel de accent quando ativo).
function Kpi({ label, valor, ln, tom, onClick, ativo, icon }) {
  const { KpiCard } = DS();
  if (KpiCard) {
    return onClick
      ? <KpiCard variant="filter" label={label} value={valor} sub={ln} icon={<Ic name={icon || "chart"} />} tone={TOM_KPI_FILTRO[tom] || "primary"} selected={!!ativo} onClick={onClick} />
      : <KpiCard label={label} value={valor} description={ln} tone={TOM_DS[tom] || "default"} />;
  }
  const cls = "pt-kpi" + (tom ? " " + tom : "") + (onClick ? " hit" : "");
  const corpo = <><small>{label}</small><b>{valor}</b>{ln && <span className="ln">{ln}</span>}</>;
  return onClick
    ? <button type="button" className={cls} onClick={onClick}>{corpo}</button>
    : <div className={cls}>{corpo}</div>;
}

// Nota/callout do Blade → DS Alert (fundo tintado 6% + borda 22%, AP7).
function Nota({ tom = "info", icon, titulo, children, contrato, acao, onFechar }) {
  const { Alert } = DS();
  if (Alert) {
    return (
      <div data-contract={contrato}>
        <Alert tone={TOM_ALERTA[tom] || "info"} title={titulo} action={acao} onClose={onFechar}>{children}</Alert>
      </div>
    );
  }
  return (
    <div className={"pt-nota " + tom} data-contract={contrato}>
      <Ic name={icon || (tom === "warn" || tom === "danger" ? "alert" : tom === "ok" ? "check" : "help")} />
      <div>{titulo && <><b>{titulo}</b><br /></>}{children}</div>
    </div>
  );
}

const Legal = ({ children }) => (
  <div className="pt-legal"><Ic name="shield" />{children || "Registros protegidos pela Portaria MTP 671/2021 — marcações são imutáveis (append-only)."}</div>
);

// Vazio — DS EmptyState: sempre POR QUE está vazio (título) + O QUE fazer (descrição/ação).
// Dentro de tabela continua entrando como <tr> pro layout da grade não quebrar.
const TITULO_VAZIO = { "no-results": "Nenhum resultado", filtered: "Nada com esse filtro", first: "Nada cadastrado ainda", done: "Tudo em ordem", error: "Não deu pra carregar", "no-perm": "Sem permissão", offline: "Sem conexão", default: "Nada para mostrar" };
function Vazio({ icon = "list", children, colSpan, titulo, acao, variante = "no-results" }) {
  const { EmptyState } = DS();
  const corpo = EmptyState
    ? <EmptyState variant={variante} icon={icon ? <Ic name={icon} /> : undefined}
        title={titulo || TITULO_VAZIO[variante] || TITULO_VAZIO.default}
        description={children} action={acao} />
    : <div className="pt-tbl-empty"><Ic name={icon} /><div>{children}</div></div>;
  return colSpan ? <tr><td colSpan={colSpan} style={{ padding: 0 }}>{corpo}</td></tr> : corpo;
}

function Tabela({ cols, children }) {
  return (
    <div className="pt-tblwrap">
      <table className="pt-tbl">
        <thead><tr>{cols.map((c, i) => <th key={i} scope="col" className={c.num ? "num" : ""} style={c.w ? { width: c.w } : null}>{c.l}</th>)}</tr></thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  );
}

const Voltar = ({ onClick, children = "Voltar" }) => {
  const { Button } = DS();
  return Button
    ? <Button variant="ghost" size="sm" onClick={onClick} icon={false}>{children}</Button>
    : <button className="pt-btn" onClick={onClick}><Ic name="x" />{children}</button>;
};

// Botão do módulo — DS Button (ghost/primary/danger). `title` vira DS Tooltip (acessível por
// teclado, ao contrário do attr nativo). Publicado em window.PtBtn: as telas do Ponto usam
// <window.PtBtn> direto, no mesmo estilo de <window.JcIcon>/<window.CliSeg>.
function Btn({ primary, danger, size = "default", disabled, type = "button", onClick, title, style, icon, children }) {
  const { Button, Tooltip } = DS();
  if (!Button) {
    return <button className={"pt-btn" + (primary ? " primary" : danger ? " danger" : "")} type={type}
      disabled={disabled} onClick={onClick} title={title} style={style}>{children}</button>;
  }
  const b = (
    <Button variant={primary ? "primary" : danger ? "danger" : "ghost"} size={size} icon={!!icon}
      type={type} disabled={disabled} onClick={onClick} style={{ whiteSpace: "nowrap", flex: "none", ...style }}>{children}</Button>
  );
  return title && Tooltip ? <Tooltip content={title}>{b}</Tooltip> : b;
}
window.PtBtn = Btn;

// Minutos assinados: verde no crédito, vermelho no débito, travessão no zero — como no Blade.
function Min({ v, sinal }) {
  const P_ = P();
  if (!v) return <span className="pt-dim">—</span>;
  const cls = v > 0 ? "pt-pos" : "pt-neg";
  return <span className={cls}>{sinal && v > 0 ? "+" : ""}{P_.fmtMin(v)}</span>;
}

// Paginação — o Blade pagina toda lista (LengthAwarePaginator); aqui o mesmo contrato:
// "N–M de T", passo por página e navegação, sem sumir com o rodapé quando cabe numa página.
function usePagina(total, porPaginaInicial = 15) {
  const [pagina, setPagina] = React.useState(1);
  const [porPagina, setPorPagina] = React.useState(porPaginaInicial);
  const ultima = Math.max(1, Math.ceil(total / porPagina));
  React.useEffect(() => { if (pagina > ultima) setPagina(1); }, [total, porPagina, ultima, pagina]);
  const fatia = (arr) => arr.slice((pagina - 1) * porPagina, pagina * porPagina);
  return { pagina, setPagina, porPagina, setPorPagina, ultima, fatia, total };
}

// Rodapé de lista — DS Pagination (números + elipse + "N–M de T" + passo por página).
function Pager({ p, rotulo = "registros" }) {
  const { Pagination } = DS();
  if (Pagination) {
    return (
      <div className="pt-pager">
        <Pagination page={p.pagina} pageCount={p.ultima} onChange={p.setPagina}
          total={p.total} pageSize={p.porPagina}
          onPageSize={(n) => { p.setPorPagina(Number(n)); p.setPagina(1); }}
          pageSizeOptions={[10, 15, 25, 50, 100]}
          totalLabel={rotulo} />
      </div>
    );
  }
  const ini = p.total === 0 ? 0 : (p.pagina - 1) * p.porPagina + 1;
  const fim = Math.min(p.pagina * p.porPagina, p.total);
  return (
    <div className="pt-pager">
      <label className="pt-pager-pp">Mostrar
        <select value={p.porPagina} onChange={(e) => { p.setPorPagina(Number(e.target.value)); p.setPagina(1); }}>
          {[10, 15, 25, 50, 100].map((n) => <option key={n} value={n}>{n}</option>)}
        </select>
        {rotulo}
      </label>
      <span className="pt-sp" />
      <span className="pt-pager-range">Mostrando <b>{ini}</b>–<b>{fim}</b> de <b>{p.total}</b></span>
      <div className="pt-group">
        <button className="pt-btn" disabled={p.pagina <= 1} onClick={() => p.setPagina(p.pagina - 1)}>Anterior</button>
        <button className="pt-btn" disabled={p.pagina >= p.ultima} onClick={() => p.setPagina(p.pagina + 1)}>Próximo</button>
      </div>
      <span className="pt-pager-cur">{p.pagina} / {p.ultima}</span>
    </div>
  );
}

// Campos de formulário — DS Input/Select/Textarea (o shell do DS já é <label>, então a
// associação rótulo↔controle é implícita: dispensa id/htmlFor). `req` desenha o asterisco,
// `wide` mantém o span largo que o `.pt-fld.wide` dava, `maxLength` fica valendo aqui
// (o Input do DS não repassa atributo nativo — ver "lacunas" no resumo da onda).
function envolve(node, wide) {
  return <div style={wide ? { flex: "1 1 260px", minWidth: 0 } : { minWidth: 150, flex: "0 1 auto" }}>{node}</div>;
}
function rotulo(label, req) {
  return req ? <>{label} <span style={{ color: "var(--neg)" }}>*</span></> : label;
}
function Campo({ label, req, help, error, value, onChange, placeholder, type = "text", disabled, readOnly, maxLength, wide, name }) {
  const { Input } = DS();
  const onC = maxLength != null && onChange
    ? (e) => { if (String(e.target.value).length > maxLength) return; onChange(e); }
    : onChange;
  if (!Input) {
    return (
      <div className={"pt-fld" + (wide ? " wide" : "")}>
        <label>{rotulo(label, req)}</label>
        <input type={type} value={value} onChange={onC} placeholder={placeholder} disabled={disabled} readOnly={readOnly} maxLength={maxLength} name={name} />
        {help && <small>{help}</small>}
      </div>
    );
  }
  return envolve(<Input label={rotulo(label, req)} help={help} error={error} value={value} onChange={onC}
    placeholder={placeholder} type={type} disabled={disabled} readOnly={readOnly} name={name} />, wide);
}
function Escolha({ label, req, help, error, value, onChange, disabled, wide, name, children }) {
  const { Select } = DS();
  if (!Select) {
    return (
      <div className={"pt-fld" + (wide ? " wide" : "")}>
        <label>{rotulo(label, req)}</label>
        <select value={value} onChange={onChange} disabled={disabled} name={name}>{children}</select>
        {help && <small>{help}</small>}
      </div>
    );
  }
  return envolve(<Select label={rotulo(label, req)} help={help} error={error} value={value}
    onChange={onChange} disabled={disabled} name={name}>{children}</Select>, wide);
}
function Texto({ label, req, help, error, value, onChange, placeholder, rows = 3, disabled, maxLength, wide, name }) {
  const { Textarea } = DS();
  const onC = maxLength != null && onChange
    ? (e) => { if (String(e.target.value).length > maxLength) return; onChange(e); }
    : onChange;
  if (!Textarea) {
    return (
      <div className={"pt-fld" + (wide ? " wide" : "")}>
        <label>{rotulo(label, req)}</label>
        <textarea value={value} onChange={onC} placeholder={placeholder} rows={rows} disabled={disabled} maxLength={maxLength} name={name} />
        {help && <small>{help}</small>}
      </div>
    );
  }
  return envolve(<Textarea label={rotulo(label, req)} help={help} error={error} value={value} onChange={onC}
    placeholder={placeholder} rows={rows} disabled={disabled} name={name} />, wide);
}
window.PtCampo = Campo; window.PtEscolha = Escolha; window.PtTexto = Texto;

// Caixa de seleção — DS Checkbox. O DS entrega booleano no onChange; as telas do Ponto usam
// handlers de evento (`set("flag")`), então adaptamos aqui em vez de mexer em cada tela.
function Check({ checked, onChange, label, disabled, name }) {
  const { Checkbox } = DS();
  if (!Checkbox) {
    return <label className="pt-check"><input type="checkbox" checked={!!checked} disabled={disabled} name={name}
      onChange={onChange} />{label}</label>;
  }
  return <Checkbox checked={!!checked} disabled={disabled} name={name} label={label}
    onChange={onChange ? (v) => onChange({ target: { type: "checkbox", checked: v, value: v } }) : undefined} />;
}

// Barra de filtros da tela — DS Toolbar (moldura em tokens; o Toolbar do DS só desenha a
// borda de baixo, e aqui a barra é um bloco solto acima dos cards).
function Barra({ children, contrato }) {
  const { Toolbar } = DS();
  if (!Toolbar) return <div className="pt-toolbar" data-contract={contrato}>{children}</div>;
  return (
    <div data-contract={contrato} style={{ border: "1px solid var(--border)", borderRadius: 12, overflow: "hidden", background: "var(--surface)" }}>
      <Toolbar bordered={false}>{children}</Toolbar>
    </div>
  );
}
window.PtCheck = Check; window.PtBarra = Barra;

window.PontoUI = { Pill, PillIntercorrencia, PillApuracao, PillImportacao, PillPrioridade, PillSimNao, Card, Kpi, Nota, Legal, Vazio, Tabela, Voltar, Min, Ic, usePagina, Pager, Btn, Campo, Escolha, Texto, Check, Barra };
})();
