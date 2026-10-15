// officeimpresso-page.jsx — Módulo Office Impresso (licenciamento do desktop Delphi) no Cockpit V2.
// F1 do módulo interno WR2 / superadmin-exclusivo. Traduz o Blade legado (restaurado da 3.7,
// ADR 0017) para o DS vivo, seguindo o topnav declarativo Resources/menus/topnav.php:
//   licenca_computador/businessall → view "empresas"  (grupo econômico, ADR 0020)
//   licenca_computador/index + computadores → view "licencas"
//   licenca_log/index              → view "log"       (lista de MÁQUINAS — D7 [W] 2026-10-14)
//   licenca_log/timeline/{id}      → view "timeline"  (rota oi-log-timeline · thread A4)
// Gate real dos controllers: officeimpresso.access (Clientes OAuth: officeimpresso.clientes.liberar).
// Expõe window.OfficeimpressoPage. Reusa sa-* / os-* / cli-* do shell + oi-* de officeimpresso-page.css.
(() => {
const { useState, useRef } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

// ── Mock: empresas licenciadas (business + campos officeimpresso_*) ──
// Campos consolidados na matriz (ADR 0020): versao_obrigatoria, versao_disponivel,
// caminho_banco_servidor, officeimpresso_limitemaquinas, officeimpresso_bloqueado.
const EMPRESAS = [
  { biz:1,   nome:"WR2 Comunicação Visual",   cidade:"São Paulo · SP",   matriz:true,  grupo:"WR2",      versaoObrig:"6.7.14", versaoDisp:"6.7.14", banco:"\\\\SRV-WR2\\OI\\DADOS.FDB",        limite:6,  usadas:4,  bloqueado:false, ultimo:"há 12min", frescor:"recente", desde:"12/03/2019" },
  { biz:4,   nome:"ROTA LIVRE Comunicação Visual", cidade:"São Paulo · SP", matriz:true, grupo:"ROTA LIVRE", versaoObrig:"6.7.12", versaoDisp:"6.7.14", banco:"\\\\SRV-ROTA\\OFFICE\\DADOS.FDB",   limite:8,  usadas:8,  bloqueado:false, ultimo:"há 3min",  frescor:"recente", desde:"04/09/2021" },
  { biz:58,  nome:"ROTA LIVRE — Zona Leste",  cidade:"São Paulo · SP",   matriz:false, grupo:"ROTA LIVRE", matrizDe:4, versaoObrig:"6.7.12", versaoDisp:"6.7.14", banco:"herda da matriz",       limite:3,  usadas:2,  bloqueado:false, ultimo:"há 1h",    frescor:"recente", desde:"18/02/2024" },
  { biz:164, nome:"Martinho Oficina",         cidade:"Guarulhos · SP",   matriz:true,  grupo:"Martinho", versaoObrig:"6.7.09", versaoDisp:"6.7.14", banco:"C:\\OFFICE\\DADOS.FDB",             limite:2,  usadas:2,  bloqueado:false, ultimo:"há 4h",    frescor:"fresc",   desde:"04/02/2026" },
  { biz:149, nome:"Grupo Sinaliza",           cidade:"Porto Alegre · RS",matriz:true,  grupo:"Sinaliza", versaoObrig:"6.7.14", versaoDisp:"6.7.14", banco:"\\\\SINALIZA-SRV\\OI\\DADOS.FDB",  limite:12, usadas:9,  bloqueado:false, ultimo:"ontem",    frescor:"fresc",   desde:"09/07/2020" },
  { biz:151, nome:"Sinaliza — Canoas",        cidade:"Canoas · RS",      matriz:false, grupo:"Sinaliza", matrizDe:149, versaoObrig:"6.7.14", versaoDisp:"6.7.14", banco:"herda da matriz",      limite:4,  usadas:3,  bloqueado:false, ultimo:"há 2 dias",frescor:"fresc",   desde:"21/05/2022" },
  { biz:142, nome:"Copiadora Central",        cidade:"Goiânia · GO",     matriz:true,  grupo:"Copiadora",versaoObrig:"6.5.02", versaoDisp:"6.7.14", banco:"\\\\CC-FILE\\OI\\DADOS.FDB",       limite:3,  usadas:1,  bloqueado:false, ultimo:"há 27 dias",frescor:"frio",   desde:"02/04/2018" },
  { biz:158, nome:"Print Ideal",              cidade:"Curitiba · PR",    matriz:true,  grupo:"Print Ideal",versaoObrig:"6.7.09",versaoDisp:"6.7.14", banco:"\\\\PI-SRV\\OFFICE\\DADOS.FDB",   limite:2,  usadas:3,  bloqueado:true,  ultimo:"há 4 dias",frescor:"fresc",   desde:"22/11/2019" },
  { biz:190, nome:"Fachadas Norte",           cidade:"Manaus · AM",      matriz:true,  grupo:"Fachadas", versaoObrig:"6.7.14", versaoDisp:"6.7.14", banco:"C:\\OI\\DADOS.FDB",                 limite:2,  usadas:0,  bloqueado:false, ultimo:"nunca",    frescor:"distante",desde:"13/08/2026" },
];

// ── Mock: licenças de computador (licenca_computador) ──
const LICENCAS = [
  { id:"LIC-4412", host:"BALCAO-01",    serial:"9F31-A7C2-1B04-77DE", biz:4,   versao:"6.7.14", so:"Windows 11 Pro 23H2", ip:"189.45.12.88",  ultimo:"há 3min",   frescor:"recente", status:"ativa",     usuario:"larissa",  desde:"04/09/2021" },
  { id:"LIC-4413", host:"BALCAO-02",    serial:"77C0-2DE4-91AA-3F18", biz:4,   versao:"6.7.14", so:"Windows 11 Pro 23H2", ip:"189.45.12.88",  ultimo:"há 22min",  frescor:"recente", status:"ativa",     usuario:"jonas",    desde:"04/09/2021" },
  { id:"LIC-4471", host:"PRODUCAO-PLOT",serial:"2A88-CF10-5501-B2C9", biz:4,   versao:"6.7.12", so:"Windows 10 Pro 22H2", ip:"189.45.12.90",  ultimo:"há 1h",     frescor:"recente", status:"ativa",     usuario:"felipe",   desde:"11/01/2023" },
  { id:"LIC-4502", host:"ZL-CAIXA",     serial:"D1E0-4471-88AC-0092", biz:58,  versao:"6.7.12", so:"Windows 11 Pro 23H2", ip:"177.92.4.31",   ultimo:"há 1h",     frescor:"recente", status:"ativa",     usuario:"marli",    desde:"18/02/2024" },
  { id:"LIC-4390", host:"MARTINHO-PC",  serial:"7C02-DE41-2210-4F8B", biz:164, versao:"6.7.09", so:"Windows 10 Pro 21H2", ip:"201.17.88.140", ultimo:"há 4h",     frescor:"fresc",   status:"ativa",     usuario:"martinho", desde:"04/02/2026" },
  { id:"LIC-4391", host:"OFICINA-BOX",  serial:"3PkQ-8bL2-7741-CC02", biz:164, versao:"6.5.02", so:"Windows 8.1",         ip:"201.17.88.141", ultimo:"há 9 dias", frescor:"fresc",   status:"ativa",     usuario:"ailton",   desde:"19/04/2026" },
  { id:"LIC-4288", host:"SINALIZA-CX1", serial:"B4A2-9910-71DE-0043", biz:149, versao:"6.7.14", so:"Windows 11 Pro 24H2", ip:"200.140.7.19",  ultimo:"ontem",     frescor:"fresc",   status:"ativa",     usuario:"eder",     desde:"09/07/2020" },
  { id:"LIC-4291", host:"CANOAS-CX1",   serial:"6610-BB84-2F19-A700", biz:151, versao:"6.7.14", so:"Windows 11 Pro 23H2", ip:"200.140.7.55",  ultimo:"há 2 dias", frescor:"fresc",   status:"ativa",     usuario:"rita",     desde:"21/05/2022" },
  { id:"LIC-3902", host:"CC-BALCAO",    serial:"0F19-3320-1187-AB2C", biz:142, versao:"6.5.02", so:"Windows 7",           ip:"186.220.31.7",  ultimo:"há 27 dias",frescor:"frio",    status:"ativa",     usuario:"marli",    desde:"02/04/2018" },
  { id:"LIC-4110", host:"PI-CAIXA",     serial:"A730-1180-4402-91FF", biz:158, versao:"6.7.09", so:"Windows 10 Pro 22H2", ip:"179.108.9.44",  ultimo:"há 4 dias", frescor:"fresc",   status:"bloqueada", usuario:"claudia",  desde:"22/11/2019" },
  { id:"LIC-4111", host:"PI-PRODUCAO",  serial:"C002-7781-3322-1D40", biz:158, versao:"6.7.09", so:"Windows 10 Pro 22H2", ip:"179.108.9.45",  ultimo:"há 4 dias", frescor:"fresc",   status:"bloqueada", usuario:"claudia",  desde:"22/11/2019" },
  { id:"LIC-4112", host:"PI-NOTEBOOK",  serial:"E551-0098-7741-3B20", biz:158, versao:"6.7.09", so:"Windows 11 Home",     ip:"179.108.9.61",  ultimo:"há 6 dias", frescor:"frio",    status:"excedente", usuario:"claudia",  desde:"07/08/2026" },
  { id:"LIC-3771", host:"WR2-DEV01",    serial:"1122-4002-9F31-0AA8", biz:1,   versao:"6.7.14", so:"Windows 11 Pro 24H2", ip:"192.168.0.21",  ultimo:"há 12min",  frescor:"recente", status:"ativa",     usuario:"wagner",   desde:"12/03/2019" },
  { id:"LIC-3660", host:"ROTA-ANTIGO",  serial:"8890-4114-2210-77C1", biz:4,   versao:"6.3.11", so:"Windows 7",           ip:"—",             ultimo:"há 8 meses",frescor:"distante",status:"revogada",  usuario:"—",        desde:"04/09/2021" },
];

// ── Mock: log de acesso (licenca_log, append-only) ──
// Tipos conforme retention_days do module.json: api_call · login_success · login_error · admin_action · error_log
const LOGS = [
  { ts:"19/08/2026 10:34:12", tipo:"api_call",      biz:4,   host:"BALCAO-01",    ip:"189.45.12.88",  rota:"GET /api/officeimpresso/produtos",  ms:180, detalhe:"142 produtos · variações incluídas", autor:"—" },
  { ts:"19/08/2026 10:33:58", tipo:"login_success", biz:4,   host:"BALCAO-01",    ip:"189.45.12.88",  rota:"POST /oauth/token",                 ms:310, detalhe:"password grant · access_token emitido (1 ano)", autor:"larissa" },
  { ts:"19/08/2026 10:19:04", tipo:"api_call",      biz:58,  host:"ZL-CAIXA",     ip:"177.92.4.31",   rota:"GET /api/officeimpresso/clientes",  ms:224, detalhe:"delta desde 18/08 · 31 registros", autor:"—" },
  { ts:"19/08/2026 09:58:41", tipo:"error_log",     biz:164, host:"OFICINA-BOX",  ip:"201.17.88.141", rota:"GET /api/officeimpresso/vendas",    ms:8120,detalhe:"timeout no Firebird · versão 6.5.02 abaixo da obrigatória", autor:"—" },
  { ts:"19/08/2026 09:41:20", tipo:"admin_action",  biz:158, host:"—",            ip:"192.168.0.21",  rota:"businessupdate/158",                ms:96,  detalhe:"bloqueio da empresa · motivo: assinatura vencida há 15 dias", autor:"wagner" },
  { ts:"19/08/2026 09:40:52", tipo:"admin_action",  biz:158, host:"PI-NOTEBOOK",  ip:"192.168.0.21",  rota:"licenca_computador/4112/toggle-block", ms:74, detalhe:"licença marcada excedente — limite de 2 máquinas estourado", autor:"wagner" },
  { ts:"19/08/2026 09:12:07", tipo:"login_error",   biz:158, host:"PI-CAIXA",     ip:"179.108.9.44",  rota:"POST /oauth/token",                 ms:142, detalhe:"empresa bloqueada (officeimpresso_bloqueado=1)", autor:"claudia" },
  { ts:"19/08/2026 08:55:33", tipo:"login_error",   biz:158, host:"PI-PRODUCAO",  ip:"179.108.9.45",  rota:"POST /oauth/token",                 ms:138, detalhe:"empresa bloqueada (officeimpresso_bloqueado=1)", autor:"claudia" },
  { ts:"19/08/2026 08:31:19", tipo:"api_call",      biz:1,   host:"WR2-DEV01",    ip:"192.168.0.21",  rota:"GET /api/officeimpresso/versao",    ms:64,  detalhe:"handshake de versão · 6.7.14 em dia", autor:"—" },
  { ts:"18/08/2026 19:02:44", tipo:"api_call",      biz:149, host:"SINALIZA-CX1", ip:"200.140.7.19",  rota:"POST /api/officeimpresso/audit",    ms:210, detalhe:"contexto rico do Delphi (hostname + serial)", autor:"—" },
  { ts:"18/08/2026 18:44:10", tipo:"login_success", biz:151, host:"CANOAS-CX1",   ip:"200.140.7.55",  rota:"POST /oauth/token",                 ms:288, detalhe:"password grant · filial herda config da matriz 149", autor:"rita" },
  { ts:"18/08/2026 11:20:02", tipo:"error_log",     biz:142, host:"CC-BALCAO",    ip:"186.220.31.7",  rota:"GET /api/officeimpresso/produtos",  ms:5440,detalhe:"Windows 7 · TLS 1.0 recusado pelo servidor", autor:"—" },
  { ts:"12/08/2026 15:08:31", tipo:"admin_action",  biz:4,   host:"ROTA-ANTIGO",  ip:"192.168.0.21",  rota:"licenca_computador/3660",           ms:88,  detalhe:"licença revogada · máquina desativada na troca de servidor", autor:"wagner" },
];

const EV = {
  api_call:      { l:"api_call",      cls:"api" },
  login_success: { l:"login_success", cls:"login_success" },
  login_error:   { l:"login_error",   cls:"login_error" },
  admin_action:  { l:"admin_action",  cls:"admin_action" },
  error_log:     { l:"error_log",     cls:"error_log" },
};
const LIC_TONE = { ativa:"success", bloqueada:"danger", excedente:"warning", revogada:"neutral" };
const LIC_LABEL = { ativa:"Ativa", bloqueada:"Bloqueada", excedente:"Excedente", revogada:"Revogada" };
const empresaDe = (biz) => EMPRESAS.find((e) => e.biz === biz) || { nome:"biz #" + biz, cidade:"—" };

// Origem do evento — o log recebe por três caminhos diferentes e a tela precisa distinguir:
// middleware LogDelphiAccess (passivo) · listener do Passport · POST /api/officeimpresso/audit
// (contexto rico mandado pelo Delphi) · ação feita aqui na UI.
const origemDe = (g) => g.tipo === "admin_action" ? { l:"UI superadmin", t:"ui" }
  : /audit/.test(g.rota) ? { l:"Delphi · audit", t:"audit" }
  : /oauth/i.test(g.rota) ? { l:"Passport listener", t:"passport" }
  : { l:"middleware", t:"mw" };

// Sinais do officeimpresso:health (ADR 0155 D9.c) — 4 checks, read-only, cross-tenant.
const HEALTH = [
  { nome:"licencas_table_present", status:"OK",   detalhe:"Tabela de licenças presente", rec:"Schema canônico aplicado." },
  { nome:"licenca_logs_table_present", status:"OK", detalhe:"Audit do Delphi ativo", rec:"Log recebendo evento." },
  { nome:"desktop_pings_24h", status:"OK",       detalhe:"9 pings em 24h", rec:"Desktop legado ativo." },
  { nome:"bloqueadas_count", status:"WARN",      detalhe:"3/14 licenças travadas", rec:"Visibilidade — bloqueios manuais pela UI." },
];
const cmpVer = (a, b) => {
  const pa = String(a).split("."), pb = String(b).split(".");
  for (let i = 0; i < 3; i++) { const d = (+pa[i] || 0) - (+pb[i] || 0); if (d) return d; }
  return 0;
};

// ── Peças reusadas (mesmo vocabulário do superadmin-page) ──
// Kebab: delega pro CliKebab (adaptador do Kebab do DS). Eram SEIS cópias
// idênticas deste componente no build; o DS publica um que ainda navega por teclado,
// coisa que nenhuma das seis fazia.
function Kebab({ items }) {
  return <window.CliKebab items={items} label="Mais ações" />;
}

function FilterDropdown({ label, value, options, onChange }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  React.useEffect(() => {
    if (!open) return;
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  const cur = options.find((o) => o.id === value);
  const active = value && value !== "all";
  return (
    <div className="cli-fdrop-wrap" ref={ref}>
      <button className={"cli-fdrop-btn " + (active ? "active" : "")} onClick={() => setOpen(!open)} aria-expanded={open}>
        <span className="cli-fdrop-l">{label}</span>
        {active && cur && <span className="cli-fdrop-v">{cur.label}</span>}
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      {open &&
        <div className="cli-fdrop-menu">
          {options.map((o) =>
            <button key={o.id} className={value === o.id ? "active" : ""} onClick={() => { onChange(o.id); setOpen(false); }}>
              {o.label}{o.count != null && <span className="cli-fdrop-n">{o.count}</span>}
            </button>)}
        </div>}
    </div>
  );
}

const OI_ABAS = [
  { key: "officeimpresso", label: "Empresas" },
  { key: "oi-licencas", label: "Licenças" },
  { key: "oi-clientes", label: "Clientes OAuth" },
  { key: "oi-importar", label: "Importar do Firebird" },
  { key: "oi-log", label: "Logs" },
];
const OiCtx = React.createContext("officeimpresso");
function PageHead({ titulo, sub, acoes }) {
  const { PageHeader } = ds();
  const ativo = React.useContext(OiCtx);
  const abas = window.OiRotaTabs ? <window.OiRotaTabs ariaLabel="Seções do Office Impresso" ativo={ativo} tabs={OI_ABAS} /> : null;
  if (!PageHeader) return (<>
    <header className="os-page-h">
      <div className="os-page-h-l"><h1>{titulo}</h1><p>{sub}</p></div>
      <div className="os-page-h-r">{acoes}</div>
    </header>{abas}
  </>);
  return <><div className="sa-ph"><PageHeader title={titulo} subtitle={sub} actions={acoes}/></div>{abas}</>;
}

function Kpi({ v, l, sub, tone }) {
  const { KpiCard } = ds();
  if (!KpiCard) return <div className="sa-kpi"><span className="sa-kpi-v">{v}</span><span className="sa-kpi-l">{l}</span></div>;
  return <KpiCard label={l} value={v} description={sub} tone={tone || "default"}/>;
}

function Frescor({ v, rel }) {
  const { StatusBadge } = ds();
  if (!StatusBadge) return <span className="sa-mono">{rel}</span>;
  return <StatusBadge kind="frescor" value={v} rel={rel}/>;
}

function LicBadge({ s }) {
  const { StatusBadge } = ds();
  if (!StatusBadge) return <span className="sa-badge">{LIC_LABEL[s]}</span>;
  return <StatusBadge label={LIC_LABEL[s]} tone={LIC_TONE[s] || "neutral"}/>;
}

function Nota({ tone = "info", titulo, children }) {
  const { Alert } = ds();
  if (!Alert) return <p className="sa-nota">{children}</p>;
  return <div className="oi-note"><Alert tone={tone} title={titulo}>{children}</Alert></div>;
}

function useToast() {
  const [toast, setToast] = useState(null);
  React.useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 3200);
    return () => clearTimeout(t);
  }, [toast]);
  const { Toast } = ds();
  const node = !toast ? null : (
    <div className="sa-toast-wrap" role="status">
      {Toast ? <Toast tone={toast.tone || "default"}>{toast.msg}</Toast> : <span className="sa-toast">{toast.msg}</span>}
    </div>
  );
  return [node, (msg, tone) => setToast({ msg, tone })];
}

function Confirm({ open, titulo, texto, cta, onConfirm, onClose }) {
  const { Modal } = ds();
  if (!open || !Modal) return null;
  return (
    <Modal open={open} onClose={onClose} title={titulo}
      footer={<>
        <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className="os-btn danger" onClick={() => { onConfirm(); onClose(); }}>{cta}</button>
      </>}>
      <p className="sa-modal-p">{texto}</p>
    </Modal>
  );
}

// Confirmação com MOTIVO obrigatório — RevokeLicencaRequest / BulkRevokeLicencaRequest
// exigem `motivo` (min 5, max 500) porque o evento vira audit trail LGPD no LicencaLog.
function ConfirmMotivo({ open, titulo, texto, cta, aviso, onConfirm, onClose }) {
  const { Modal, Textarea } = ds();
  const [motivo, setMotivo] = useState("");
  React.useEffect(() => { if (open) setMotivo(""); }, [open]);
  if (!open || !Modal) return null;
  const t = motivo.trim();
  const erro = t.length > 0 && t.length < 5 ? "Motivo precisa ter pelo menos 5 caracteres."
    : motivo.length > 500 ? "Motivo não pode passar de 500 caracteres." : undefined;
  const ok = t.length >= 5 && motivo.length <= 500;
  return (
    <Modal open={open} onClose={onClose} title={titulo}
      footer={<>
        <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className="os-btn danger" disabled={!ok} onClick={() => { onConfirm(t); onClose(); }}>{cta}</button>
      </>}>
      <p className="sa-modal-p">{texto}</p>
      {aviso && <p className="sa-modal-p oi-aviso">{aviso}</p>}
      <div className="sa-campo oi-motivo">
        {Textarea
          ? <Textarea label="Motivo (obrigatório)" value={motivo} rows={3} error={erro}
              help={erro ? undefined : `Fica no log para sempre — evidência contratual, Lei do Software 9.609/98. ${motivo.length}/500`}
              placeholder="Ex: contrato cancelado em 18/08 — cliente migrou de servidor"
              onChange={(e) => setMotivo(e && e.target ? e.target.value : e)}/>
          : <textarea value={motivo} rows={3} onChange={(e) => setMotivo(e.target.value)}/>}
      </div>
    </Modal>
  );
}

function Vazio({ titulo, texto, acao }) {
  const { EmptyState, RegistrationMark } = ds();
  if (!EmptyState) return <div className="sa-vazio"><b>{titulo}</b><p>{texto}</p>{acao}</div>;
  return (
    <div className="sa-vazio-wrap">
      <EmptyState variant="no-results" title={titulo} description={texto} action={acao}
        icon={RegistrationMark ? <RegistrationMark size={22}/> : undefined}/>
    </div>
  );
}

function Drawer({ titulo, sub, badge, children, rodape, onClose }) {
  React.useEffect(() => {
    const h = (e) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, [onClose]);
  return (
    <>
      <div className="sa-scrim" onClick={onClose}></div>
      <aside className="sa-drawer" role="dialog" aria-label={titulo}>
        <header className="sa-dr-h">
          <div><h2>{titulo}</h2><p>{sub}</p>{badge}</div>
          <button className="sa-dr-x" onClick={onClose} title="Fechar (esc)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
          </button>
        </header>
        <div className="sa-dr-body">{children}</div>
        {rodape && <footer className="sa-dr-f">{rodape}</footer>}
      </aside>
    </>
  );
}

function Health() {
  const { Tooltip } = ds();
  const resumo = { ok:HEALTH.filter(h=>h.status==="OK").length, warn:HEALTH.filter(h=>h.status==="WARN").length, fail:HEALTH.filter(h=>h.status==="FAIL").length };
  return (
    <div className="oi-health">
      <span className="oi-health-t">Saúde da ponte <small>officeimpresso:health</small></span>
      <span className="oi-health-sig">
        {HEALTH.map((h) => {
          const chip = (
            <span key={h.nome} className={"oi-sig oi-sig--" + h.status.toLowerCase()}>
              <i></i><b>{h.nome}</b><span>{h.detalhe}</span>
            </span>
          );
          return Tooltip ? <Tooltip key={h.nome} content={h.rec} side="bottom">{chip}</Tooltip> : chip;
        })}
      </span>
      <span className="oi-health-r">{resumo.ok} OK · {resumo.warn} WARN · {resumo.fail} FAIL</span>
    </div>
  );
}

function Ver({ instalada, obrigatoria, curta }) {
  const atras = cmpVer(instalada, obrigatoria) < 0;
  if (curta) return (
    <span className={"oi-ver " + (atras ? "atras" : "ok")} title={atras ? "Abaixo da obrigatória (" + obrigatoria + ")" : "Em dia com a obrigatória"}>
      {instalada}{atras && <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M12 9v4M12 17h.01M10.3 4.3 2.6 18a1.6 1.6 0 0 0 1.4 2.4h16a1.6 1.6 0 0 0 1.4-2.4L13.7 4.3a1.6 1.6 0 0 0-2.8 0Z"/></svg>}
    </span>
  );
  return (
    <span className={"oi-ver " + (atras ? "atras" : "ok")} title={atras ? "Abaixo da versão obrigatória" : "Em dia com a obrigatória"}>
      {instalada}
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      {obrigatoria}
    </span>
  );
}

function Maquinas({ usadas, limite }) {
  const pct = limite ? Math.min(100, usadas / limite * 100) : 0;
  const cls = usadas > limite ? "estourado" : usadas === limite ? "cheio" : "";
  return (
    <span className="oi-maq">
      <span className={"oi-bar " + cls}><i style={{ width: pct + "%" }}></i></span>
      <span className="sa-mono">{usadas}/{limite}</span>
    </span>
  );
}

// ── View: Empresas Licenciadas (businessall) ──
function ViewEmpresas() {
  const [q, setQ] = useState("");
  const [fBloq, setFBloq] = useState("all");
  const [fVer, setFVer] = useState("all");
  const [aberta, setAberta] = useState(null);
  const [confirma, setConfirma] = useState(null);
  const [toastNode, toast] = useToast();
  const buscaRef = useRef(null);

  React.useEffect(() => {
    const h = (e) => {
      if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA" || e.metaKey || e.ctrlKey) return;
      if (e.key === "/") { e.preventDefault(); buscaRef.current?.focus(); }
    };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, []);

  const desatualizadas = EMPRESAS.filter((e) => cmpVer(e.versaoObrig, e.versaoDisp) < 0);
  const filtradas = EMPRESAS.filter((e) => {
    if (fBloq === "bloq" && !e.bloqueado) return false;
    if (fBloq === "livre" && e.bloqueado) return false;
    if (fBloq === "estourado" && e.usadas <= e.limite) return false;
    if (fVer === "atras" && cmpVer(e.versaoObrig, e.versaoDisp) >= 0) return false;
    if (fVer === "dia" && cmpVer(e.versaoObrig, e.versaoDisp) < 0) return false;
    if (q) {
      const s = q.toLowerCase();
      if (![e.nome, e.cidade, e.grupo, e.banco, String(e.biz)].some((v) => String(v).toLowerCase().includes(s))) return false;
    }
    return true;
  });
  const limpar = () => { setFBloq("all"); setFVer("all"); setQ(""); };
  const ativos = [fBloq, fVer].filter((v) => v !== "all").length;

  return (
    <div className="os-page sa-page" data-screen-label="Office Impresso · Empresas Licenciadas">
      <PageHead titulo="Empresas licenciadas" sub={`${EMPRESAS.length} empresas · ${EMPRESAS.filter(e=>e.matriz).length} matrizes · ${LICENCAS.filter(l=>l.status!=="revogada").length} máquinas vivas`}
        acoes={<>
          <button className="os-btn ghost" onClick={() => toast(`${filtradas.length} empresas exportadas em CSV`, "ok")}>Exportar</button>
          <button className="os-btn primary" onClick={() => window.__selectRoute?.("oi-licencas")}>Ver licenças</button>
        </>}/>

      <div className="sa-kpis sa-kpis--4">
        <Kpi v={EMPRESAS.length} l="Empresas licenciadas" sub={`${EMPRESAS.filter(e=>!e.matriz).length} filiais herdando config`}/>
        <Kpi v={LICENCAS.filter(l=>l.status==="ativa").length} l="Máquinas ativas" sub="handshake nos últimos 30 dias" tone="success"/>
        <Kpi v={desatualizadas.length} l="Versão obrigatória atrás" sub={`disponível hoje: 6.7.14`} tone="warning"/>
        <Kpi v={EMPRESAS.filter(e=>e.bloqueado).length} l="Empresas bloqueadas" sub="Delphi recusa o login" tone="danger"/>
      </div>

      <Health />

      <Nota titulo="Grupo econômico consolida na matriz" tone="info">
        Versão obrigatória, versão disponível, caminho do banco e limite de máquinas ficam na matriz — a filial herda (ADR 0020). Editar uma filial só muda o limite dela.
      </Nota>

      <div className="sa-toolbar">
        <div className="sa-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input ref={buscaRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar empresa, grupo, caminho do banco ou biz #…"/>
          <kbd className="sa-kbd">/</kbd>
        </div>
        <div className="sa-filters">
          <FilterDropdown label="Situação" value={fBloq} onChange={setFBloq} options={[
            { id:"all", label:"Todas" }, { id:"livre", label:"Liberadas" }, { id:"bloq", label:"Bloqueadas" }, { id:"estourado", label:"Limite estourado" }]}/>
          <FilterDropdown label="Versão" value={fVer} onChange={setFVer} options={[
            { id:"all", label:"Qualquer" }, { id:"atras", label:"Obrigatória atrás da disponível" }, { id:"dia", label:"Em dia" }]}/>
          {(ativos > 0 || q) && <button className="sa-clear" onClick={limpar}>Limpar</button>}
          <span className="sa-count">{filtradas.length} de {EMPRESAS.length}</span>
        </div>
      </div>

      {filtradas.length === 0 ? (
        <Vazio titulo="Nenhuma empresa com esses filtros"
          texto={q ? `A busca "${q}" não bate com nome, grupo, caminho do banco ou biz #.` : "Os filtros combinados não deixaram nenhuma empresa."}
          acao={<button className="os-btn ghost" onClick={limpar}>Limpar filtros</button>}/>
      ) : (
        <div className="os-table-wrap">
          <table className="os-table sa-table">
            <thead><tr>
              <th>Empresa</th><th>Versão obrigatória → disponível</th><th>Máquinas</th>
              <th>Banco no servidor</th><th>Último handshake</th><th className="sa-th-act"></th>
            </tr></thead>
            <tbody>
              {filtradas.map((e) => (
                <tr key={e.biz} className="sa-row" onClick={() => setAberta(e)}>
                  <td>
                    <div className={"sa-biz" + (e.matriz ? "" : " oi-filial")}>
                      <b>{e.nome}{e.bloqueado && <span className="sa-inativo">bloqueada</span>}</b>
                      <small className="sa-mono">biz #{e.biz}</small>
                      <small>{e.matriz ? <span className="oi-grupo">matriz · {e.grupo}</span> : `filial de biz #${e.matrizDe}`} · {e.cidade}</small>
                    </div>
                  </td>
                  <td><Ver instalada={e.versaoObrig} obrigatoria={e.versaoDisp}/></td>
                  <td><Maquinas usadas={e.usadas} limite={e.limite}/></td>
                  <td><span className="sa-mono">{e.banco}</span></td>
                  <td><Frescor v={e.frescor} rel={e.ultimo}/></td>
                  <td className="sa-td-act" onClick={(ev) => ev.stopPropagation()}>
                    <Kebab items={[
                      { label:"Ver configuração", action: () => setAberta(e) },
                      { label:"Licenças da empresa", action: () => { toast(`Filtrando licenças de ${e.nome}`); window.__selectRoute?.("oi-licencas"); } },
                      { label:"Log de acesso", action: () => window.__selectRoute?.("oi-log") },
                      { sep:true },
                      { label:"Forçar versão disponível", action: () => toast(`${e.nome}: obrigatória atualizada para ${e.versaoDisp}`, "ok") },
                      { label: e.bloqueado ? "Desbloquear empresa" : "Bloquear empresa", danger: !e.bloqueado,
                        action: () => setConfirma(e) },
                    ]}/>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {aberta && <EmpresaDrawer e={aberta} onClose={() => setAberta(null)} onSalvar={(nome) => { setAberta(null); toast(`Configuração de ${nome} salva`, "ok"); }}/>}

      <ConfirmMotivo open={!!confirma} onClose={() => setConfirma(null)}
        titulo={confirma?.bloqueado ? "Desbloquear a empresa?" : "Bloquear a empresa?"}
        texto={confirma?.bloqueado
          ? `As ${confirma?.usadas || 0} máquinas de ${confirma?.nome} voltam a autenticar no próximo handshake.`
          : `Todas as ${confirma?.usadas || 0} máquinas de ${confirma?.nome} param de autenticar no próximo handshake. O dado fica intacto e o desbloqueio é imediato.`}
        aviso={confirma?.matriz && confirma?.grupo ? "É matriz: o bloqueio desce para as filiais do grupo." : undefined}
        cta={confirma?.bloqueado ? "Desbloquear" : "Bloquear"}
        onConfirm={() => toast(`${confirma.nome} ${confirma.bloqueado ? "desbloqueada" : "bloqueada"} · admin_action registrado com motivo`, confirma.bloqueado ? "ok" : "danger")}/>

      {toastNode}
    </div>
  );
}

function EmpresaDrawer({ e, onClose, onSalvar }) {
  const { Input, Switch, StatusBadge } = ds();
  const [f, setF] = useState({ obrig:e.versaoObrig, disp:e.versaoDisp, banco:e.banco, limite:String(e.limite), bloqueado:e.bloqueado });
  const set = (k) => (v) => setF((s) => ({ ...s, [k]: v && v.target ? v.target.value : v }));
  const licencas = LICENCAS.filter((l) => l.biz === e.biz);
  const herda = !e.matriz;
  // Regras reais do UpdateEmpresaConfigRequest
  const erroVer = (v) => !/^\d+(\.\d+){0,3}$/.test(v) ? "Use X, X.Y, X.Y.Z ou X.Y.Z.W — só dígitos e pontos."
    : v.length > 20 ? "Máximo de 20 caracteres." : undefined;
  const erroBanco = /(\.\.|~)/.test(f.banco) ? 'O caminho não pode conter ".." nem "~" (path traversal).'
    : f.banco.length > 500 ? "Máximo de 500 caracteres." : undefined;
  const erroLimite = !/^\d+$/.test(f.limite) || +f.limite < 1 ? "O número de máquinas deve ser ao menos 1."
    : +f.limite > 9999 ? "Não pode exceder 9999 (limite anti-fraude)."
    : +f.limite < e.usadas ? `${e.usadas} máquinas já registradas — abaixar para ${f.limite} marca ${e.usadas - +f.limite} como excedente.` : undefined;
  const bloqueiaSalvar = !!(erroVer(f.obrig) || erroVer(f.disp) || erroBanco) || !/^\d+$/.test(f.limite) || +f.limite < 1 || +f.limite > 9999;
  return (
    <Drawer titulo={e.nome} sub={`biz #${e.biz} · ${e.cidade} · licenciada desde ${e.desde}`}
      badge={StatusBadge ? <StatusBadge label={e.bloqueado ? "Bloqueada" : "Liberada"} tone={e.bloqueado ? "danger" : "success"}/> : null}
      onClose={onClose}
      rodape={<>
        <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className="os-btn primary" disabled={bloqueiaSalvar} onClick={() => onSalvar(e.nome)}>Salvar configuração</button>
      </>}>
      {herda && <Nota tone="warn" titulo="Filial — herda da matriz">Versão, banco e bloqueio vêm de biz #{e.matrizDe}. Aqui só o limite de máquinas é próprio.</Nota>}

      <section className="sa-dr-sec">
        <h3>Versão do desktop</h3>
        <div className="sa-campo">
          {Input
            ? <Input label="Versão obrigatória" value={f.obrig} onChange={set("obrig")} disabled={herda} error={erroVer(f.obrig)}
                help="Abaixo dela o Delphi recusa abrir e pede atualização."/>
            : <input value={f.obrig} onChange={set("obrig")}/>}
        </div>
        <div className="sa-campo">
          {Input
            ? <Input label="Versão disponível" value={f.disp} onChange={set("disp")} disabled={herda} error={erroVer(f.disp)}
                help="A que o instalador publica hoje."/>
            : <input value={f.disp} onChange={set("disp")}/>}
        </div>
      </section>

      <section className="sa-dr-sec">
        <h3>Banco e limite</h3>
        <div className="sa-campo">
          {Input
            ? <Input label="Caminho do banco no servidor" value={f.banco} onChange={set("banco")} disabled={herda} error={erroBanco}
                help="Firebird (.FDB) — usado pelo importador e pelo suporte. Sem &quot;..&quot; nem &quot;~&quot;."/>
            : <input value={f.banco} onChange={set("banco")}/>}
        </div>
        <div className="sa-campo">
          {Input
            ? <Input label="Limite de máquinas" value={f.limite} onChange={set("limite")} type="number" error={erroLimite}
                help={erroLimite ? undefined : "officeimpresso_numerodemaquinas · 1 a 9999 (limite de contrato)."}/>
            : <input value={f.limite} onChange={set("limite")}/>}
        </div>
        {Switch && <div className="sa-swrow-ds">
          <Switch checked={f.bloqueado} onChange={set("bloqueado")} label="Bloquear a empresa"
            sublabel="O Delphi recusa o login em todas as máquinas no próximo handshake."/>
        </div>}
      </section>

      <section className="sa-dr-sec">
        <h3>Máquinas ({licencas.length})</h3>
        <ul className="oi-tl">
          {licencas.map((l) => (
            <li key={l.id}>
              <span className="oi-tl-dot"></span>
              <span className="oi-tl-b">
                <b>{l.host} · <span className="sa-mono">{l.versao}</span></b>
                <small>{l.id} · {l.so} · {l.ultimo}</small>
              </span>
            </li>
          ))}
          {licencas.length === 0 && <li><span className="oi-tl-dot"></span><span className="oi-tl-b"><b>Nenhuma máquina registrada</b><small>a empresa nunca fez handshake</small></span></li>}
        </ul>
      </section>
    </Drawer>
  );
}

// ── View: Licenças / Computadores (licenca_computador + computadores) ──
const LIC_PAGE = 8;
function ViewLicencas() {
  const [modo, setModo] = useState("licencas");
  const [q, setQ] = useState("");
  const [fStatus, setFStatus] = useState("all");
  const [fBiz, setFBiz] = useState("all");
  const [sel, setSel] = useState([]);
  const [aberta, setAberta] = useState(null);
  const [confirma, setConfirma] = useState(null);
  const [page, setPage] = useState(1);
  const [toastNode, toast] = useToast();
  const { Checkbox, Pagination, BulkBar } = ds();

  const filtradas = LICENCAS.filter((l) => {
    if (fStatus !== "all" && l.status !== fStatus) return false;
    if (fBiz !== "all" && String(l.biz) !== fBiz) return false;
    if (q) {
      const s = q.toLowerCase();
      if (![l.host, l.serial, l.id, l.ip, l.usuario, empresaDe(l.biz).nome].some((v) => String(v).toLowerCase().includes(s))) return false;
    }
    return true;
  });
  const agrupadas = modo === "computadores";
  const pageCount = Math.max(1, Math.ceil(filtradas.length / LIC_PAGE));
  const pagina = filtradas.slice((Math.min(page, pageCount) - 1) * LIC_PAGE, Math.min(page, pageCount) * LIC_PAGE);
  const limpar = () => { setFStatus("all"); setFBiz("all"); setQ(""); setPage(1); };
  const marcado = (id) => sel.includes(id);

  return (
    <div className="os-page sa-page" data-screen-label="Office Impresso · Licenças">
      <PageHead titulo="Licenças de computador" sub={`${LICENCAS.length} registradas · ${LICENCAS.filter(l=>l.status==="ativa").length} ativas · ${LICENCAS.filter(l=>l.status==="bloqueada"||l.status==="excedente").length} travadas`}
        acoes={<>
          <window.CliSeg role="tablist" ariaLabel="Modo de visualização" value={modo} onChange={setModo} options={[{ key: "licencas", label: "Licenças" }, { key: "computadores", label: "Por empresa" }]} />
          <button className="os-btn ghost" onClick={() => window.__selectRoute?.("oi-log")}>Log de acesso</button>
        </>}/>

      <div className="sa-toolbar">
        <div className="sa-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Buscar host, serial, LIC-…, IP ou usuário…"/>
        </div>
        <div className="sa-filters">
          <FilterDropdown label="Status" value={fStatus} onChange={(v) => { setFStatus(v); setPage(1); }} options={[
            { id:"all", label:"Todos" },
            ...["ativa","bloqueada","excedente","revogada"].map((s) => ({ id:s, label:LIC_LABEL[s], count:LICENCAS.filter(l=>l.status===s).length }))]}/>
          <FilterDropdown label="Empresa" value={fBiz} onChange={(v) => { setFBiz(v); setPage(1); }} options={[
            { id:"all", label:"Todas" },
            ...EMPRESAS.map((e) => ({ id:String(e.biz), label:`${e.nome} (#${e.biz})`, count:LICENCAS.filter(l=>l.biz===e.biz).length }))]}/>
          {(fStatus !== "all" || fBiz !== "all" || q) && <button className="sa-clear" onClick={limpar}>Limpar</button>}
          <span className="sa-count">{filtradas.length} de {LICENCAS.length}</span>
        </div>
      </div>

      {filtradas.length === 0 ? (
        <Vazio titulo="Nenhuma licença com esses filtros"
          texto="Host, serial e IP entram na busca. O serial é o que o Delphi manda no handshake."
          acao={<button className="os-btn ghost" onClick={limpar}>Limpar filtros</button>}/>
      ) : agrupadas ? (
        EMPRESAS.filter((e) => filtradas.some((l) => l.biz === e.biz)).map((e) => {
          const ls = filtradas.filter((l) => l.biz === e.biz);
          return (
            <section key={e.biz} className="sa-card oi-card">
              <header className="sa-card-h">
                <div><h3>{e.nome}</h3><p>biz #{e.biz} · limite {e.limite} máquinas · obrigatória {e.versaoObrig}</p></div>
                <Maquinas usadas={e.usadas} limite={e.limite}/>
              </header>
              <div className="os-table-wrap">
                <table className="os-table sa-table">
                  <thead><tr><th>Computador</th><th>Versão</th><th>Usuário</th><th>Último acesso</th><th>Status</th></tr></thead>
                  <tbody>
                    {ls.map((l) => (
                      <tr key={l.id} className="sa-row" onClick={() => setAberta(l)}>
                        <td><div className="oi-host"><b>{l.host}</b><small>{l.serial}</small></div></td>
                        <td><Ver curta instalada={l.versao} obrigatoria={e.versaoObrig}/></td>
                        <td>{l.usuario}</td>
                        <td><Frescor v={l.frescor} rel={l.ultimo}/></td>
                        <td><LicBadge s={l.status}/></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          );
        })
      ) : (
        <>
          <div className="os-table-wrap">
            <table className="os-table sa-table">
              <thead><tr>
                <th className="sa-th-check">
                  {Checkbox && <span className="sa-check-wrap" onClick={(e) => e.stopPropagation()}>
                    <Checkbox checked={pagina.length > 0 && pagina.every((l) => marcado(l.id))} name="Selecionar a página"
                      onChange={(on) => setSel(on ? [...new Set([...sel, ...pagina.map(l => l.id)])] : sel.filter((id) => !pagina.some((l) => l.id === id)))}/>
                  </span>}
                </th>
                <th>Computador</th><th>Empresa</th><th>Versão instalada</th><th>IP · usuário</th><th>Último acesso</th><th>Status</th><th className="sa-th-act"></th>
              </tr></thead>
              <tbody>
                {pagina.map((l) => {
                  const e = empresaDe(l.biz);
                  return (
                    <tr key={l.id} className={"sa-row" + (marcado(l.id) ? " sel" : "")} onClick={() => setAberta(l)}>
                      <td className="sa-td-check" onClick={(ev) => ev.stopPropagation()}>
                        {Checkbox && <span className="sa-check-wrap"><Checkbox checked={marcado(l.id)} name={"Selecionar " + l.host}
                          onChange={(on) => setSel((s) => on ? [...s, l.id] : s.filter((x) => x !== l.id))}/></span>}
                      </td>
                      <td><div className="oi-host"><b>{l.host}</b><small>{l.id} · {l.serial}</small></div></td>
                      <td><div className="sa-biz"><b className="sa-b-reg">{e.nome}</b><small className="sa-mono">biz #{l.biz}</small></div></td>
                      <td><Ver curta instalada={l.versao} obrigatoria={e.versaoObrig || l.versao}/></td>
                      <td><div className="sa-biz"><b className="sa-mono sa-b-reg">{l.ip}</b><small>{l.usuario}</small></div></td>
                      <td><Frescor v={l.frescor} rel={l.ultimo}/></td>
                      <td><LicBadge s={l.status}/></td>
                      <td className="sa-td-act" onClick={(ev) => ev.stopPropagation()}>
                        <Kebab items={[
                          { label:"Ver licença", action: () => setAberta(l) },
                          { label:"Timeline no log", action: () => window.__selectRoute?.("oi-log-timeline") },
                          { sep:true },
                          { label: l.status === "bloqueada" ? "Desbloquear" : "Bloquear máquina",
                            action: () => setConfirma({ tipo:"bloqueio", l }) },
                          { label:"Revogar licença", danger:true, action: () => setConfirma({ tipo:"uma", l }) },
                        ]}/>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          {pageCount > 1 && Pagination &&
            <div className="sa-pag"><Pagination page={Math.min(page, pageCount)} pageCount={pageCount} total={filtradas.length} pageSize={LIC_PAGE} onChange={setPage} nextLabel="Próxima"/></div>}
        </>
      )}

      {BulkBar && sel.length > 0 &&
        <div className="sa-bulk-wrap">
          <BulkBar count={sel.length} label={sel.length === 1 ? "licença selecionada" : "licenças selecionadas"} onClose={() => setSel([])}
            actions={[
              { label:"Bloquear", onClick: () => setConfirma({ tipo:"lote", bloquear:true }) },
              { label:"Exportar seleção", onClick: () => toast(`${sel.length} licenças exportadas em CSV`, "ok") },
              { label:"Revogar", tone:"danger", onClick: () => setConfirma({ tipo:"lote" }) },
            ]}/>
        </div>}

      {aberta && <LicencaDrawer l={aberta} onClose={() => setAberta(null)}
        onAcao={(t) => { const l = aberta; setAberta(null); setConfirma(t === "revogar" ? { tipo:"uma", l } : { tipo:"bloqueio", l }); }}/>}

      <ConfirmMotivo open={!!confirma} onClose={() => setConfirma(null)}
        titulo={confirma?.tipo === "lote" ? (confirma?.bloquear ? "Bloquear em lote?" : "Revogar em lote?")
          : confirma?.tipo === "bloqueio" ? (confirma?.l?.status === "bloqueada" ? "Desbloquear a máquina?" : "Bloquear a máquina?")
          : "Revogar a licença?"}
        texto={confirma?.tipo === "lote"
          ? `${sel.length} máquinas ${confirma?.bloquear ? "param de autenticar" : "perdem a licença"}. O registro fica no log (append-only) e a vaga volta pro limite da empresa.`
          : confirma?.tipo === "bloqueio"
          ? `${confirma?.l?.host} ${confirma?.l?.status === "bloqueada" ? "volta a autenticar" : "para de autenticar"} no próximo handshake. A vaga no limite continua ocupada.`
          : `${confirma?.l?.host} perde a licença e libera 1 vaga no limite de ${empresaDe(confirma?.l?.biz).nome}. O histórico no log continua.`}
        aviso={confirma?.tipo === "lote"
          ? (sel.length > 100 ? "Operação em lote é limitada a 100 licenças por chamada — reduza a seleção." : "O mesmo motivo é gravado em cada uma das licenças.")
          : undefined}
        cta={confirma?.tipo === "lote" ? (confirma?.bloquear ? "Bloquear" : "Revogar")
          : confirma?.tipo === "bloqueio" ? (confirma?.l?.status === "bloqueada" ? "Desbloquear" : "Bloquear") : "Revogar"}
        onConfirm={() => {
          if (confirma.tipo === "lote") { toast(`${sel.length} licenças ${confirma.bloquear ? "bloqueadas" : "revogadas"} · motivo gravado em cada uma`, "danger"); setSel([]); }
          else if (confirma.tipo === "bloqueio") toast(`${confirma.l.host} ${confirma.l.status === "bloqueada" ? "desbloqueada" : "bloqueada"} · admin_action registrado`, confirma.l.status === "bloqueada" ? "ok" : "warn");
          else toast(`${confirma.l.host} revogada · admin_action registrado com motivo`, "danger");
        }}/>

      {toastNode}
    </div>
  );
}

function LicencaDrawer({ l, onClose, onAcao }) {
  const e = empresaDe(l.biz);
  const eventos = LOGS.filter((g) => g.host === l.host);
  return (
    <Drawer titulo={l.host} sub={`${l.id} · ${e.nome} (biz #${l.biz})`} badge={<LicBadge s={l.status}/>} onClose={onClose}
      rodape={<>
        <button className="os-btn ghost" onClick={() => onAcao("bloqueio")}>{l.status === "bloqueada" ? "Desbloquear" : "Bloquear"}</button>
        <button className="os-btn danger" onClick={() => onAcao("revogar")}>Revogar licença</button>
      </>}>
      <section className="sa-dr-sec">
        <h3>Identificação</h3>
        <dl className="oi-kv">
          <dt>Serial</dt><dd className="mono">{l.serial}</dd>
          <dt>Versão</dt><dd><Ver instalada={l.versao} obrigatoria={e.versaoObrig || l.versao}/></dd>
          <dt>Sistema</dt><dd>{l.so}</dd>
          <dt>IP</dt><dd className="mono">{l.ip}</dd>
          <dt>Usuário</dt><dd>{l.usuario}</dd>
          <dt>Registrada</dt><dd className="mono">{l.desde}</dd>
          <dt>Último acesso</dt><dd><Frescor v={l.frescor} rel={l.ultimo}/></dd>
        </dl>
      </section>
      <section className="sa-dr-sec">
        <h3>Timeline no log ({eventos.length})</h3>
        {eventos.length === 0
          ? <p className="sa-modal-p">Nenhum evento nesta janela de retenção.</p>
          : <ul className="oi-tl">
              {eventos.map((g, i) => (
                <li key={i}>
                  <span className="oi-tl-dot"></span>
                  <span className="oi-tl-b">
                    <b><span className={"oi-ev oi-ev--" + EV[g.tipo].cls}><i></i>{EV[g.tipo].l}</span></b>
                    <small>{g.ts} · {g.rota} · {g.ms}ms</small>
                    <small>{g.detalhe}</small>
                  </span>
                </li>
              ))}
            </ul>}
      </section>
    </Drawer>
  );
}

// ── View: Clientes OAuth (resource client) ──
// Credencial Passport password-grant que cada Delphi usa pra autenticar.
// Gate: listar/criar aceita officeimpresso.clientes.liberar; excluir e regenerar são superadmin-only.
const OAUTH = [
  { id:12, nome:"Delphi ROTA LIVRE — balcão", clientId:"9",  secret:"o1Kd8sQz2Vb7Yn4Lp6Rt3Xw9Ac5Ef1Gh0Jm7Nq2S", criado:"04/09/2021", revogado:false, biz:4,   usos:"3 máquinas", ultimo:"há 3min" },
  { id:14, nome:"Delphi Martinho Oficina",    clientId:"11", secret:"7Pq2Wm9Zx4Rt6Yb1Kd8Nc5Vf3Hj0Ls7Ap4Eg2Q", criado:"04/02/2026", revogado:false, biz:164, usos:"2 máquinas", ultimo:"há 4h" },
  { id:15, nome:"Delphi Grupo Sinaliza",      clientId:"12", secret:"2Bn6Yt8Kw1Qs4Zx7Rc3Vd9Hf5Lg0Jm8Ap6Ne1T", criado:"09/07/2020", revogado:false, biz:149, usos:"4 máquinas", ultimo:"ontem" },
  { id:9,  nome:"Delphi Print Ideal",         clientId:"7",  secret:"5Kd0Nq3Xw8Vb2Yt6Rc1Zs9Hf4Lg7Jm5Ap3Ee8W", criado:"22/11/2019", revogado:true,  biz:158, usos:"3 máquinas", ultimo:"há 4 dias" },
  { id:3,  nome:"WR2 — homologação",          clientId:"3",  secret:"1Ap4Eg2Qq7Kd8Nc5Vf3Hj0Ls9Zx4Rt6Yb2Wm7P", criado:"12/03/2019", revogado:false, biz:1,   usos:"1 máquina",  ultimo:"há 12min" },
];

function ViewClientes() {
  const [revelado, setRevelado] = useState([]);
  const [novo, setNovo] = useState(false);
  const [confirma, setConfirma] = useState(null);
  const [nome, setNome] = useState("");
  const [toastNode, toast] = useToast();
  const { Input, StatusBadge } = ds();
  const mostra = (id) => revelado.includes(id);

  return (
    <div className="os-page sa-page" data-screen-label="Office Impresso · Clientes OAuth">
      <PageHead titulo="Clientes OAuth" sub={`${OAUTH.length} credenciais password-grant · ${OAUTH.filter(c=>c.revogado).length} revogada · uma por instalação Delphi`}
        acoes={<>
          <button className="os-btn ghost" onClick={() => setConfirma({ tipo:"regen" })}>Regenerar chaves</button>
          <button className="os-btn primary" onClick={() => { setNome(""); setNovo(true); }}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Nova credencial
          </button>
        </>}/>

      <Nota titulo="Permissão delegável para listar e criar" tone="info">
        Ver a lista e criar credencial aceita <span className="sa-mono">officeimpresso.clientes.liberar</span> — dá para um funcionário liberar cliente sem abrir o Financeiro. Excluir e regenerar continuam <span className="sa-mono">superadmin</span>-only, porque derrubam acesso.
      </Nota>

      <div className="os-table-wrap">
        <table className="os-table sa-table">
          <thead><tr>
            <th>Credencial</th><th>client_id</th><th>client_secret</th><th>Em uso</th><th>Criada</th><th className="sa-th-act"></th>
          </tr></thead>
          <tbody>
            {OAUTH.map((c) => (
              <tr key={c.id} className="sa-row">
                <td>
                  <div className="sa-biz">
                    <b>{c.nome}{c.revogado && <span className="sa-inativo">revogada</span>}</b>
                    <small className="sa-mono">{empresaDe(c.biz).nome} · biz #{c.biz}</small>
                  </div>
                </td>
                <td><span className="sa-mono">{c.clientId}</span></td>
                <td>
                  <span className="oi-secret">
                    <code>{mostra(c.id) ? c.secret : "•".repeat(28)}</code>
                    <button className="oi-secret-b" onClick={() => setRevelado((s) => mostra(c.id) ? s.filter((x) => x !== c.id) : [...s, c.id])}>
                      {mostra(c.id) ? "ocultar" : "revelar"}
                    </button>
                    <button className="oi-secret-b" onClick={() => toast("client_secret copiado — cole no Delphi e não guarde em e-mail", "ok")}>copiar</button>
                  </span>
                </td>
                <td><div className="sa-biz"><b className="sa-b-reg">{c.usos}</b><small>último handshake {c.ultimo}</small></div></td>
                <td><span className="sa-mono">{c.criado}</span></td>
                <td className="sa-td-act">
                  <Kebab items={[
                    { label:"Copiar client_id", action: () => toast("client_id copiado", "ok") },
                    { label:"Licenças desta empresa", action: () => window.__selectRoute?.("oi-licencas") },
                    { sep:true },
                    { label:"Excluir credencial", danger:true, action: () => setConfirma({ tipo:"del", c }) },
                  ]}/>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {novo && <Drawer titulo="Nova credencial OAuth" sub="password grant · secret de 40 caracteres gerado agora"
        onClose={() => setNovo(false)}
        rodape={<>
          <button className="os-btn ghost" onClick={() => setNovo(false)}>Cancelar</button>
          <button className="os-btn primary" disabled={nome.trim().length < 3}
            onClick={() => { setNovo(false); toast(`Credencial "${nome}" criada — revele o secret e cole no Delphi`, "ok"); }}>Criar credencial</button>
        </>}>
        <section className="sa-dr-sec">
          <h3>Identificação</h3>
          <div className="sa-campo">
            {Input
              ? <Input label="Nome da credencial" value={nome} onChange={(e) => setNome(e && e.target ? e.target.value : e)}
                  placeholder="Ex: Delphi Fachadas Norte — caixa"
                  help="Aparece só aqui. Use empresa + posto para saber o que derrubar depois."/>
              : <input value={nome} onChange={(e) => setNome(e.target.value)}/>}
          </div>
          <dl className="oi-kv">
            <dt>Tipo</dt><dd>password_client = 1</dd>
            <dt>Redirect</dt><dd className="mono">http://localhost</dd>
            <dt>Secret</dt><dd>gerado no salvamento · 40 caracteres</dd>
          </dl>
        </section>
        <Nota tone="warn" titulo="O secret aparece uma vez">Copie e cole no Delphi na hora. Perdido o secret, o caminho é criar outra credencial — não há como recuperar.</Nota>
      </Drawer>}

      <Confirm open={confirma?.tipo === "del"} onClose={() => setConfirma(null)}
        titulo="Excluir a credencial?"
        texto={`${confirma?.c?.nome} para de autenticar imediatamente — as ${confirma?.c?.usos || ""} da empresa ficam sem acesso até receberem uma nova. Ação superadmin-only.`}
        cta="Excluir" onConfirm={() => toast(`${confirma.c.nome} excluída`, "danger")}/>

      <Confirm open={confirma?.tipo === "regen"} onClose={() => setConfirma(null)}
        titulo="Regenerar as chaves do Passport?"
        texto="Roda passport:install --force: TODAS as credenciais são recriadas e TODO Delphi em campo perde o acesso no mesmo instante. Só faça em janela combinada, com o instalador novo pronto."
        cta="Regenerar mesmo" onConfirm={() => toast("Chaves regeneradas — redistribua os secrets", "danger")}/>

      {toastNode}
    </div>
  );
}

// ── View: Importar do Firebird (officeimpresso:import) ──
// One-way Firebird (Delphi WR Comercial) → oimpresso, por business_id (Tier 0 ADR 0093).
// Sem pdo_firebird o connector cai em modo mock — a tela diz isso em vez de fingir leitura.
const FAMILIAS = [
  { de:"CLIENTES",           para:"contacts",           read:412, mig:398, skip:14, nota:"14 sem documento — caem em tipo Outros" },
  { de:"PRODUTOS",           para:"products",           read:1180, mig:1094, skip:86, nota:"86 duplicados por código legado" },
  { de:"VENDAS",             para:"transactions",       read:8740, mig:8740, skip:0,  nota:"histórico completo, sem quebra" },
  { de:"LICENCA_COMPUTADOR", para:"licenca_computador", read:3,   mig:3,   skip:0,  nota:"máquinas do cliente já vinculadas" },
  { de:"FINANCEIRO",         para:"fin_titulos",        read:2210, mig:2118, skip:92, nota:"92 títulos sem vencimento válido" },
  { de:"NOTA_FISCAL",        para:"nfe_emissoes",       read:1904, mig:1904, skip:0,  nota:"XML preservado como anexo" },
];

function ViewImportar() {
  const [biz, setBiz] = useState("164");
  const [fdb, setFdb] = useState("");
  const [user, setUser] = useState("SYSDBA");
  const [pass, setPass] = useState("masterkey");
  const [driver, setDriver] = useState(true);
  const [estado, setEstado] = useState("pronto"); // pronto | rodando | feito | erro
  const [confirma, setConfirma] = useState(false);
  const [toastNode, toast] = useToast();
  const { Input, Select, Switch, Progress } = ds();

  const erroPath = /(\.\.|~)/.test(fdb) ? 'O caminho não pode conter ".." nem "~" (path traversal).' : undefined;
  const modo = !driver ? "mock" : fdb.trim() === "" ? "mock" : "live";
  const podeRodar = !erroPath && estado !== "rodando";
  const total = FAMILIAS.reduce((s, f) => s + f.mig, 0);
  const e = empresaDe(+biz);

  const rodar = (persistir) => {
    setEstado("rodando");
    setTimeout(() => {
      if (modo === "live" && !/\.fdb$/i.test(fdb.trim())) { setEstado("erro"); return; }
      setEstado("feito");
      toast(persistir ? `${total.toLocaleString("pt-BR")} registros gravados em biz #${biz}` : `Dry-run: ${total.toLocaleString("pt-BR")} registros migrariam — nada persistido`, persistir ? "danger" : "ok");
    }, 900);
  };

  return (
    <div className="os-page sa-page" data-screen-label="Office Impresso · Importar do Firebird">
      <PageHead titulo="Importar do Firebird" sub="Delphi WR Comercial → oimpresso · one-way, por empresa · dry-run antes de qualquer gravação"
        acoes={<>
          <button className="os-btn ghost" disabled={!podeRodar} onClick={() => rodar(false)}>Rodar dry-run</button>
          <button className="os-btn primary" disabled={!podeRodar || estado !== "feito"} onClick={() => setConfirma(true)}>Importar de verdade</button>
        </>}/>

      {!driver &&
        <Nota tone="warn" titulo="pdo_firebird ausente no servidor — modo mock">
          Sem o driver o connector devolve dados de fixture: serve pra ensaiar a tela e o de-para, nunca pra migrar cliente. Instale o driver ou rode a importação na máquina que tem o Firebird.
        </Nota>}

      <div className="sa-grid2">
        <section className="sa-card oi-card">
          <header className="sa-card-h"><div><h3>Origem e destino</h3><p>Tudo que o comando pede na linha de comando</p></div></header>
          <div className="oi-form">
            <div className="sa-campo">
              {Select
                ? <Select label="Empresa destino" value={biz} onChange={(ev) => setBiz(ev && ev.target ? ev.target.value : ev)}
                    help="business_id é obrigatório — o import nunca roda cross-tenant."
                    options={EMPRESAS.map((x) => ({ value:String(x.biz), label:`${x.nome} — biz #${x.biz}` }))}/>
                : <select value={biz} onChange={(ev) => setBiz(ev.target.value)}>{EMPRESAS.map((x) => <option key={x.biz} value={x.biz}>{x.nome}</option>)}</select>}
            </div>
            <div className="sa-campo">
              {Input
                ? <Input label="Caminho do .fdb legado" value={fdb} onChange={(ev) => setFdb(ev && ev.target ? ev.target.value : ev)} error={erroPath}
                    placeholder="C:\legacy\wr_comercial.fdb"
                    help={erroPath ? undefined : "Vazio = modo mock (fixture). Caminho absoluto na máquina do servidor."}/>
                : <input value={fdb} onChange={(ev) => setFdb(ev.target.value)}/>}
            </div>
            <div className="oi-form-2">
              <div className="sa-campo">
                {Input ? <Input label="Usuário Firebird" value={user} onChange={(ev) => setUser(ev && ev.target ? ev.target.value : ev)}/> : <input value={user} onChange={(ev) => setUser(ev.target.value)}/>}
              </div>
              <div className="sa-campo">
                {Input ? <Input label="Senha" value={pass} type="password" onChange={(ev) => setPass(ev && ev.target ? ev.target.value : ev)}/> : <input value={pass} onChange={(ev) => setPass(ev.target.value)}/>}
              </div>
            </div>
            {Switch && <div className="sa-swrow-ds">
              <Switch checked={driver} onChange={setDriver} label="Driver pdo_firebird disponível"
                sublabel="Simula o ambiente do servidor — desligue para ver a tela em modo mock."/>
            </div>}
          </div>
        </section>

        <section className="sa-card oi-card">
          <header className="sa-card-h">
            <div><h3>Health check do connector</h3><p>Roda antes de ler qualquer tabela</p></div>
            <span className={"oi-sig oi-sig--" + (modo === "live" ? "ok" : "warn")}><i></i><b>mode={modo}</b><span>{modo === "live" ? "ok=true" : "fixture"}</span></span>
          </header>
          <div className="oi-form">
            <dl className="oi-kv">
              <dt>Empresa</dt><dd>{e.nome} <span className="sa-mono">biz #{biz}</span></dd>
              <dt>Banco no cadastro</dt><dd className="mono">{e.banco}</dd>
              <dt>Fonte desta rodada</dt><dd className="mono">{fdb.trim() || ":mock:"}</dd>
              <dt>Direção</dt><dd>Firebird → oimpresso (one-way, sem volta)</dd>
            </dl>
            <Nota tone="info" titulo="Convivência de 30 dias">
              Depois do import o cliente segue usando o Delphi em paralelo por ~30 dias. Cutover só com revisão do Wagner — a retenção da Lei do Software 9.609/98 fica preservada nos dois lados.
            </Nota>
          </div>
        </section>
      </div>

      {estado === "rodando" &&
        <section className="sa-card oi-card">
          <header className="sa-card-h"><div><h3>Lendo o Firebird…</h3><p>Nada é gravado nesta fase</p></div></header>
          <div className="oi-form">{Progress ? <Progress variant="bar" value={62} label="Lendo VENDAS" showValue/> : <p className="sa-modal-p">Lendo…</p>}</div>
        </section>}

      {estado === "erro" &&
        <Vazio titulo="O connector não abriu o arquivo"
          texto={`"${fdb}" não termina em .fdb ou não existe na máquina do servidor. Confira o caminho no cadastro da empresa (${e.banco}) e se o serviço Firebird está de pé.`}
          acao={<button className="os-btn ghost" onClick={() => setEstado("pronto")}>Corrigir caminho</button>}/>}

      {estado === "feito" &&
        <section className="sa-card oi-card">
          <header className="sa-card-h">
            <div><h3>Dry-run · o que migraria</h3><p>{total.toLocaleString("pt-BR")} registros em 6 famílias · nada persistido</p></div>
            <span className="sa-mono">biz #{biz}</span>
          </header>
          <div className="os-table-wrap">
            <table className="os-table sa-table">
              <thead><tr><th>Firebird → oimpresso</th><th className="ta-r">Lidos</th><th className="ta-r">Migrariam</th><th className="ta-r">Pulados</th><th>Por quê pula</th></tr></thead>
              <tbody>
                {FAMILIAS.map((f) => (
                  <tr key={f.de}>
                    <td><div className="oi-host"><b>{f.de}</b><small>→ {f.para}</small></div></td>
                    <td className="ta-r"><span className="sa-mono">{f.read.toLocaleString("pt-BR")}</span></td>
                    <td className="ta-r"><span className="sa-mono">{f.mig.toLocaleString("pt-BR")}</span></td>
                    <td className="ta-r"><span className={"sa-mono" + (f.skip ? " oi-skip" : "")}>{f.skip}</span></td>
                    <td>{f.skip ? f.nota : <span className="oi-ok-txt">{f.nota}</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <footer className="sa-card-f">Total: <b>{total.toLocaleString("pt-BR")}</b> registros migrariam · {FAMILIAS.reduce((s,f)=>s+f.skip,0)} pulados. Revise os pulados antes de gravar — o que pula aqui não volta sozinho depois.</footer>
        </section>}

      <ConfirmMotivo open={confirma} onClose={() => setConfirma(false)}
        titulo="Importar de verdade?"
        texto={`${total.toLocaleString("pt-BR")} registros vão ser gravados em ${e.nome} (biz #${biz}). É one-way: desfazer significa restaurar backup do tenant.`}
        aviso="Reviewer obrigatório antes do cutover — o motivo abaixo fica no audit trail junto do seu login."
        cta="Gravar no tenant" onConfirm={() => rodar(true)}/>

      {toastNode}
    </div>
  );
}

// ── View: Máquinas Cadastradas (rota licenca_log) ── D7 [W] 2026-10-14: a tela lista MÁQUINAS,
// não eventos. Espelha Logs/Index.tsx + _components/MaquinasTable.tsx do main (93ff8a960b74):
// KPIs do buildKpisPayload, colunas e ações da MaquinasTable, filtros q/estado_atual + chips business_id/licenca_id/hd.
const CNPJ = { 1:"12.345.678/0001-90", 4:"23.456.789/0001-01", 58:"23.456.789/0002-92", 164:"34.567.890/0001-12", 149:"45.678.901/0001-23", 151:"45.678.901/0002-04", 142:"56.789.012/0001-34", 158:"67.890.123/0001-45", 190:"78.901.234/0001-56" };
const maquinaDe = (l) => {
  const e = empresaDe(l.biz);
  const ult = LOGS.find((g) => g.host === l.host && (g.tipo === "login_success" || g.tipo === "login_error"));
  return { ...l, empresa:e.nome, empresaBloq:!!e.bloqueado, maqBloq:l.status === "bloqueada" || l.status === "excedente",
    hd:l.serial, local:{ nome:e.nome, cnpj:CNPJ[l.biz] || null },
    ultimoLogin: ult ? ult.ts : null, cadastro: l.desde + " 00:00:00",
    bloqNoUltimo: ult ? ult.tipo === "login_error" : null, ip: ult ? ult.ip : l.ip };
};
function EstadoAtual({ m }) {
  const { StatusBadge } = ds();
  const [l, tone] = m.empresaBloq ? ["Empresa bloqueada", "danger"] : m.maqBloq ? ["Máquina bloqueada", "warning"] : ["Ativa", "success"];
  return StatusBadge ? <StatusBadge label={l} tone={tone}/> : <span className="sa-badge">{l}</span>;
}
function EstadoNoAcesso({ v }) {
  const { StatusBadge } = ds();
  if (v === null) return <span className="sa-mute">—</span>;
  const [l, tone] = v ? ["Bloqueada", "danger"] : ["Liberada", "success"];
  return StatusBadge ? <StatusBadge label={l} tone={tone}/> : <span className="sa-badge">{l}</span>;
}
function ViewLog() {
  const [q, setQ] = useState("");
  const [fEstado, setFEstado] = useState("all");
  const [chips, setChips] = useState({});
  const [conf, setConf] = useState(null);
  const [toastNode, toast] = useToast();
  const MAQ = LICENCAS.filter((l) => l.status !== "revogada").map(maquinaDe);
  const kpis = { total:MAQ.length, maqBloq:MAQ.filter((m) => m.maqBloq).length, empBloq:EMPRESAS.filter((e) => e.bloqueado).length,
    acessos24h:LOGS.filter((g) => g.ts.startsWith("19/08/2026")).length };
  const filtrados = MAQ.filter((m) => {
    if (fEstado === "ativa" && (m.maqBloq || m.empresaBloq)) return false;
    if (fEstado === "bloqueada" && !(m.maqBloq || m.empresaBloq)) return false;
    if (chips.business_id && m.biz !== chips.business_id) return false;
    if (chips.licenca_id && m.id !== chips.licenca_id) return false;
    if (chips.hd && m.hd !== chips.hd) return false;
    if (q) { const s = q.toLowerCase(); if (![m.empresa, m.local.cnpj, m.hd, m.host, m.ip].some((v) => String(v || "").toLowerCase().includes(s))) return false; }
    return true;
  });
  const temFiltro = !!q || fEstado !== "all" || Object.keys(chips).length > 0;
  const limpar = () => { setQ(""); setFEstado("all"); setChips({}); };
  const addChip = (k, v) => setChips((c) => ({ ...c, [k]: v }));
  const tirarChip = (k) => setChips((c) => { const n = { ...c }; delete n[k]; return n; });
  const rotChip = { business_id:(v) => "Empresa #" + v, licenca_id:(v) => "Equipamento #" + v, hd:(v) => "HD " + v };

  return (
    <div className="os-page sa-page" data-screen-label="Office Impresso · Máquinas Cadastradas">
      <PageHead titulo="Máquinas Cadastradas" sub={<>O cadastro é populado por <span className="sa-mono">/connector/api/processa-dados-cliente</span>; cada linha mostra o último acesso registrado.</>}/>

      <div className="sa-kpis sa-kpis--4">
        <Kpi v={kpis.total} l="Máquinas cadastradas" sub="total de equipamentos"/>
        <Kpi v={kpis.maqBloq} l="Máquinas bloqueadas" sub="bloqueio individual" tone="warning"/>
        <Kpi v={kpis.empBloq} l="Empresas bloqueadas" sub="bloqueio em massa" tone="danger"/>
        <Kpi v={kpis.acessos24h} l="Acessos 24h" sub="processa-dados-cliente" tone="success"/>
      </div>

      <div className="sa-toolbar">
        <div className="sa-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input type="search" aria-label="Buscar empresa ou máquina" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nome, CNPJ, HD, hostname, IP…"/>
        </div>
        <div className="sa-filters">
          <FilterDropdown label="Estado atual" value={fEstado} onChange={setFEstado} options={[
            { id:"all", label:"Todos" }, { id:"ativa", label:"Ativa" }, { id:"bloqueada", label:"Bloqueada" }]}/>
          {Object.entries(chips).map(([k, v]) => <button key={k} className="os-btn ghost" onClick={() => tirarChip(k)}>{rotChip[k](v)} ✕</button>)}
          {temFiltro && <button className="sa-clear" onClick={limpar}>Limpar</button>}
          <span className="sa-count">{filtrados.length} de {MAQ.length}</span>
        </div>
      </div>

      {filtrados.length === 0 ? (
        temFiltro
          ? <Vazio titulo="Nenhuma máquina encontrada com os filtros aplicados." texto="" acao={<button className="os-btn ghost" onClick={limpar}>Limpar filtros</button>}/>
          : <Vazio titulo="Nenhuma máquina cadastrada ainda." texto="A tabela é populada pela rotina /connector/api/processa-dados-cliente quando o Delphi envia CNPJ + HD."/>
      ) : (
        <div className="os-table-wrap">
          <table className="os-table sa-table oi-maq-table">
            <colgroup><col/><col/><col style={{ width:140 }}/><col style={{ width:170 }}/><col style={{ width:80 }}/><col style={{ width:120 }}/><col style={{ width:160 }}/><col style={{ width:120 }}/><col style={{ width:150 }}/><col style={{ width:170 }}/></colgroup>
            <thead><tr>
              <th>Empresa</th><th>Location / CNPJ</th><th>Máquina</th><th>HD</th><th>Versão</th><th>IP</th><th>Último login</th><th>Estado no último login</th><th>Estado atual</th><th className="ta-r">Ações</th>
            </tr></thead>
            <tbody>
              {filtrados.map((m) => (
                <tr key={m.id} className="sa-row">
                  <td><button className="oi-link" title="Filtrar por esta empresa" onClick={() => addChip("business_id", m.biz)}>{m.empresa}</button></td>
                  <td><div className="sa-biz"><b className="sa-b-reg">{m.local.nome}</b>{m.local.cnpj && <small className="sa-mono">CNPJ {m.local.cnpj}</small>}</div></td>
                  <td><button className="oi-link sa-mono" title="Filtrar por este equipamento" onClick={() => addChip("licenca_id", m.id)}>{m.host || "(sem hostname)"}</button></td>
                  <td><button className="oi-link sa-mono" title="Ver todas as empresas com este HD" onClick={() => addChip("hd", m.hd)}>{m.hd}</button></td>
                  <td><span className="sa-mono">{m.versao}</span></td>
                  <td><span className="sa-mono">{m.ip}</span></td>
                  <td><span className="sa-mono">{m.ultimoLogin || m.cadastro}</span>{!m.ultimoLogin && <small className="sa-mute" style={{ display:"block" }}>(cadastro)</small>}</td>
                  <td><EstadoNoAcesso v={m.bloqNoUltimo}/></td>
                  <td><EstadoAtual m={m}/></td>
                  <td className="ta-r">
                    {m.empresaBloq
                      ? <button className="os-btn ghost" onClick={() => setConf({ titulo:"Desbloquear a empresa inteira?", texto:`Isto libera o Delphi de TODAS as máquinas de ${m.empresa}, não só desta.`, ok:`${m.empresa} desbloqueada` })}>Desbloquear empresa</button>
                      : <button className={"os-btn " + (m.maqBloq ? "ghost" : "danger")} onClick={() => setConf({ titulo: m.maqBloq ? "Desbloquear esta máquina?" : "Bloquear esta máquina?", texto:`${m.host} — ${m.empresa}.`, ok:`${m.host} ${m.maqBloq ? "desbloqueada" : "bloqueada"}` })}>{m.maqBloq ? "Desbloquear máquina" : "Bloquear máquina"}</button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Confirm open={!!conf} titulo={conf?.titulo} texto={conf?.texto} cta="Confirmar"
        onConfirm={() => { toast(conf.ok, "ok"); setConf(null); }} onClose={() => setConf(null)}/>
      {toastNode}
    </div>
  );
}

// ── View: Timeline de uma licença (licenca_log/timeline/{licenca_id}) ── thread A4: vista própria,
// licença FIXA (a 1ª ativa) para a medida ser reprodutível; ?licenca= na produção é parâmetro.
function ViewTimeline() {
  const { Timeline } = ds();
  const l = LICENCAS.find((x) => x.status === "ativa") || LICENCAS[0];
  const e = empresaDe(l.biz);
  const eventos = LOGS.filter((g) => g.host === l.host);
  return (
    <div className="os-page sa-page" data-screen-label="Office Impresso · Timeline da licença">
      <PageHead titulo={"Timeline · " + l.host} sub={`${l.id} · ${e.nome} (biz #${l.biz}) · ${eventos.length} eventos · append-only`}
        acoes={<>
          <button className="os-btn ghost" onClick={() => window.__selectRoute?.("oi-log")}>Voltar ao log</button>
          <button className="os-btn ghost" onClick={() => window.__selectRoute?.("oi-licencas")}>Abrir a licença</button>
        </>}/>
      <div className="sa-body" style={{ padding: "0 24px 24px" }}>
        {eventos.length === 0
          ? <p className="sa-modal-p">Nenhum evento desta máquina na janela de retenção.</p>
          : Timeline
            ? <Timeline groupByDay entries={eventos.map((g) => ({ time: g.ts, actor: g.autor === "—" ? "desktop" : g.autor, action: EV[g.tipo].l, detail: g.detalhe, meta: g.rota }))}/>
            : <ul className="sa-dr-hist">{eventos.map((g, i) => <li key={i}><b className="sa-mono">{g.ts}</b><span>{EV[g.tipo].l} · {g.detalhe}</span></li>)}</ul>}
      </div>
    </div>
  );
}

function OfficeimpressoPage({ view = "empresas" }) {
  const M = { licencas: [ViewLicencas, "oi-licencas"], clientes: [ViewClientes, "oi-clientes"], importar: [ViewImportar, "oi-importar"], log: [ViewLog, "oi-log"], timeline: [ViewTimeline, "oi-log"] };
  const [V, rota] = M[view] || [ViewEmpresas, "officeimpresso"];
  return <OiCtx.Provider value={rota}><V /></OiCtx.Provider>;
}

window.OfficeimpressoPage = OfficeimpressoPage;
})();
