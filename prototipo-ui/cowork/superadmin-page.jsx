// superadmin-page.jsx — Módulo Superadmin (SaaS/licenciamento) dentro do Cockpit V2.
// Traduz o Blade legado Modules/Superadmin/Resources/views/* (AdminLTE) para o DS vivo:
//   superadmin/index      → view "visao"        (KPIs por período + tendência mensal)
//   business/index        → view "negocios"     (datatable server-side + filtros)
//   superadmin_subscription/index → "assinaturas"
//   packages/index        → view "pacotes"      (cards de pacote)
//   communicator/index    → view "comunicador"  (compor + histórico)
//   superadmin_settings/edit → view "config"    (partials como seções)
// Expõe window.SuperadminPage. Reusa classes os-* / cli-fdrop-* / cli-kebab-* do shell.
(() => {
const { useState, useRef, useMemo } = React;

const BRL = (n) => "R$ " + n.toLocaleString("pt-BR", { minimumFractionDigits: 0 });

// DS vivo (bundle compilado) — lido em tempo de render porque o script é defer.
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

// ── Mock: pacotes de assinatura (espelha packages table) ──
const PACOTES = [
  { id:1, nome:"Balcão", preco:189, intervalo:"mês", intervalCount:1, ativo:true, privado:false, avulso:false, trial:14,
    locais:1, usuarios:3, produtos:500, faturas:300, desc:"Para gráfica rápida de balcão — orçamento, OS e PDV.", assinantes:38 },
  { id:2, nome:"Gráfica", preco:389, intervalo:"mês", intervalCount:1, ativo:true, privado:false, avulso:false, trial:14,
    locais:2, usuarios:10, produtos:0, faturas:0, desc:"Produção completa: OP, fila de impressão, acabamento e fiscal.", assinantes:57 },
  { id:3, nome:"Rede", preco:890, intervalo:"mês", intervalCount:1, ativo:true, privado:false, avulso:false, trial:30,
    locais:0, usuarios:0, produtos:0, faturas:0, desc:"Multi-loja com BI consolidado e ponto eletrônico.", assinantes:12 },
  { id:4, nome:"Piloto ROTA LIVRE", preco:0, intervalo:"mês", intervalCount:6, ativo:true, privado:true, avulso:false, trial:0,
    locais:1, usuarios:6, produtos:0, faturas:0, desc:"Pacote privado do piloto — liberado só pelo superadmin.", assinantes:1 },
  { id:5, nome:"Implantação", preco:2400, intervalo:"ano", intervalCount:1, ativo:true, privado:false, avulso:true, trial:0,
    locais:1, usuarios:2, produtos:200, faturas:100, desc:"Cobrança avulsa de setup e migração de dados.", assinantes:9 },
  { id:6, nome:"Legado 2024", preco:149, intervalo:"mês", intervalCount:1, ativo:false, privado:false, avulso:false, trial:0,
    locais:1, usuarios:3, produtos:300, faturas:200, desc:"Grade antiga — mantida só para contratos vigentes.", assinantes:4 },
];

const PERM_LABEL = { ponto:"Ponto eletrônico", fiscal:"NF-e / NFS-e", bi:"BI e dashboards", oficina:"Oficina Auto" };
const PACOTE_PERMS = { 1:["fiscal"], 2:["fiscal","bi"], 3:["fiscal","bi","ponto"], 4:["ponto","oficina"], 5:[], 6:["fiscal"] };

// ── Mock: negócios (business + owner + subscription) ──
const NEGOCIOS = [
  { id:164, nome:"Martinho Oficina", dono:"Martinho Alves", email:"martinho@oficinamartinho.com.br", fone:"(11) 98876-2210", foneBiz:"(11) 4002-1122", cidade:"Guarulhos · SP", ativo:true, pacote:"Gráfica", sub:"ativa", venc:"12/09/2026", criado:"04/02/2026", criador:"wagner", ultimaTx:"hoje", mrr:389 },
  { id:171, nome:"ROTA LIVRE Comunicação Visual", dono:"Larissa Souza", email:"larissa@rotalivre.com", fone:"(11) 99120-4408", foneBiz:"(11) 4114-8890", cidade:"São Paulo · SP", ativo:true, pacote:"Piloto ROTA LIVRE", sub:"trial", venc:"30/08/2026", criado:"11/06/2026", criador:"wagner", ultimaTx:"hoje", mrr:0 },
  { id:158, nome:"Print Ideal", dono:"Cláudia Bertoni", email:"claudia@printideal.com.br", fone:"(41) 99604-1180", foneBiz:"(41) 3322-7781", cidade:"Curitiba · PR", ativo:true, pacote:"Balcão", sub:"ativa", venc:"03/09/2026", criado:"22/11/2025", criador:"auto-cadastro", ultimaTx:"há 2 dias", mrr:189 },
  { id:149, nome:"Grupo Sinaliza (3 lojas)", dono:"Éder Nogueira", email:"eder@sinaliza.com.br", fone:"(51) 99881-0075", foneBiz:"(51) 3025-4410", cidade:"Porto Alegre · RS", ativo:true, pacote:"Rede", sub:"ativa", venc:"28/08/2026", criado:"09/07/2025", criador:"wagner", ultimaTx:"ontem", mrr:890 },
  { id:186, nome:"Adesivo Express", dono:"Tiago Prado", email:"tiago@adesivoexpress.com", fone:"(31) 98410-9922", foneBiz:"(31) 3481-0022", cidade:"Belo Horizonte · MG", ativo:true, pacote:"Balcão", sub:"vencida", venc:"04/08/2026", criado:"18/03/2026", criador:"auto-cadastro", ultimaTx:"há 19 dias", mrr:189 },
  { id:190, nome:"Fachadas Norte", dono:"Rita Camargo", email:"rita@fachadasnorte.com.br", fone:"(92) 99117-3308", foneBiz:"(92) 3232-1140", cidade:"Manaus · AM", ativo:true, pacote:"Gráfica", sub:"trial", venc:"27/08/2026", criado:"13/08/2026", criador:"auto-cadastro", ultimaTx:"hoje", mrr:0 },
  { id:193, nome:"Studio Lona", dono:"Vinícius Sá", email:"vinicius@studiolona.com", fone:"(21) 98220-7741", foneBiz:"(21) 2551-0098", cidade:"Rio de Janeiro · RJ", ativo:false, pacote:"—", sub:"sem", venc:"—", criado:"16/08/2026", criador:"auto-cadastro", ultimaTx:"nunca", mrr:0 },
  { id:142, nome:"Copiadora Central", dono:"Marli Duarte", email:"marli@copiadoracentral.com.br", fone:"(62) 99320-1187", foneBiz:"(62) 3212-4471", cidade:"Goiânia · GO", ativo:true, pacote:"Legado 2024", sub:"ativa", venc:"15/09/2026", criado:"02/04/2025", criador:"wagner", ultimaTx:"há 6 dias", mrr:149 },
  { id:181, nome:"Vestuário Malha Viva", dono:"Sérgio Kubo", email:"sergio@malhaviva.com.br", fone:"(47) 99871-2004", foneBiz:"(47) 3033-9912", cidade:"Blumenau · SC", ativo:true, pacote:"Gráfica", sub:"cancelada", venc:"31/07/2026", criado:"27/01/2026", criador:"auto-cadastro", ultimaTx:"há 32 dias", mrr:0 },
  { id:195, nome:"Placa & Cia", dono:"Heloísa Prado", email:"heloisa@placaecia.com.br", fone:"(85) 98004-3312", foneBiz:"(85) 3261-7788", cidade:"Fortaleza · CE", ativo:true, pacote:"—", sub:"sem", venc:"—", criado:"17/08/2026", criador:"auto-cadastro", ultimaTx:"nunca", mrr:0 },
];

// ── Mock: assinaturas (subscriptions) ──
const ASSINATURAS = [
  { id:"SUB-2291", negocio:"Martinho Oficina", pacote:"Gráfica", status:"aprovada", criado:"12/08/2026", inicio:"12/08/2026", trialFim:"—", fim:"12/09/2026", preco:389, via:"Pix automático", tx:"pix_9f31a7c2" },
  { id:"SUB-2288", negocio:"Fachadas Norte", pacote:"Gráfica", status:"trial", criado:"13/08/2026", inicio:"13/08/2026", trialFim:"27/08/2026", fim:"27/08/2026", preco:0, via:"—", tx:"—" },
  { id:"SUB-2284", negocio:"Grupo Sinaliza (3 lojas)", pacote:"Rede", status:"aprovada", criado:"28/07/2026", inicio:"28/07/2026", trialFim:"—", fim:"28/08/2026", preco:890, via:"Cartão (Stripe)", tx:"ch_3PkQ8bL2" },
  { id:"SUB-2280", negocio:"Print Ideal", pacote:"Balcão", status:"aprovada", criado:"03/08/2026", inicio:"03/08/2026", trialFim:"—", fim:"03/09/2026", preco:189, via:"Boleto", tx:"bol_774120" },
  { id:"SUB-2276", negocio:"Adesivo Express", pacote:"Balcão", status:"vencida", criado:"04/07/2026", inicio:"04/07/2026", trialFim:"—", fim:"04/08/2026", preco:189, via:"Boleto", tx:"bol_761884" },
  { id:"SUB-2271", negocio:"Copiadora Central", pacote:"Legado 2024", status:"aprovada", criado:"15/08/2026", inicio:"15/08/2026", trialFim:"—", fim:"15/09/2026", preco:149, via:"Pix automático", tx:"pix_7c02de41" },
  { id:"SUB-2265", negocio:"Vestuário Malha Viva", pacote:"Gráfica", status:"cancelada", criado:"01/07/2026", inicio:"01/07/2026", trialFim:"—", fim:"31/07/2026", preco:389, via:"Cartão (Stripe)", tx:"ch_3PdN1aQ7" },
  { id:"SUB-2260", negocio:"ROTA LIVRE Comunicação Visual", pacote:"Piloto ROTA LIVRE", status:"trial", criado:"11/06/2026", inicio:"11/06/2026", trialFim:"30/08/2026", fim:"11/12/2026", preco:0, via:"Liberado pelo superadmin", tx:"—" },
  { id:"SUB-2254", negocio:"Martinho Oficina", pacote:"Implantação", status:"aprovada", criado:"04/02/2026", inicio:"04/02/2026", trialFim:"—", fim:"04/02/2027", preco:2400, via:"Pix automático", tx:"pix_2a88cf10" },
  { id:"SUB-2249", negocio:"Studio Lona", pacote:"Balcão", status:"pendente", criado:"16/08/2026", inicio:"—", trialFim:"—", fim:"—", preco:189, via:"Boleto", tx:"bol_781902" },
];

const SUB_TONE = {
  aprovada:  { l:"Aprovada",  h:150 }, trial: { l:"Trial", h:230 },
  pendente:  { l:"Pendente",  h:70 },  vencida:{ l:"Vencida", h:25 },
  cancelada: { l:"Cancelada", h:null }, ativa: { l:"Ativa", h:150 }, sem: { l:"Sem assinatura", h:null },
};
const SUB_DS_TONE = { aprovada:"success", ativa:"success", trial:"info", pendente:"warning", vencida:"danger", cancelada:"neutral", sem:"neutral" };
function SubBadge({ s }) {
  const { StatusBadge } = ds();
  const t = SUB_TONE[s] || SUB_TONE.pendente;
  if (!StatusBadge) return <span className="sa-badge">{t.l}</span>;
  return <StatusBadge label={t.l} tone={SUB_DS_TONE[s] || "neutral"}/>;
}

// ── Mock: histórico do comunicador ──
const MENSAGENS = [
  { id:1, assunto:"Manutenção programada — domingo 06:00 às 08:00", res:"Todos os negócios", enviados:112, abriram:87, data:"14/08/2026 18:40", autor:"wagner" },
  { id:2, assunto:"NFS-e: novo layout de prefeitura disponível", res:"Pacote Gráfica e Rede", enviados:69, abriram:41, data:"07/08/2026 09:12", autor:"wagner" },
  { id:3, assunto:"Sua assinatura vence em 5 dias", res:"Assinaturas vencendo", enviados:14, abriram:13, data:"30/07/2026 07:00", autor:"rotina automática" },
  { id:4, assunto:"Ponto eletrônico: relatório do espelho atualizado", res:"Pacote Rede", enviados:12, abriram:5, data:"21/07/2026 16:25", autor:"wagner" },
];

// ── Funil trial→pago e churn (últimos 90 dias) ──
const FUNIL = [
  { etapa:"Cadastraram", n:38 },
  { etapa:"Começaram o trial", n:29 },
  { etapa:"Usaram além do 3º dia", n:21 },
  { etapa:"Assinaram", n:13 },
];
const CHURN = { taxa:3.4, saidas:4, base:118, motivo:[
  { m:"Preço", n:2 }, { m:"Fechou o negócio", n:1 }, { m:"Foi pra concorrente", n:1 },
]};

// Assinaturas vencendo — fila de cobrança do superadmin
const VENCENDO = [
  { negocio:"Grupo Sinaliza (3 lojas)", pacote:"Rede", venc:"28/08/2026", dias:10, valor:890, via:"Cartão (Stripe)", risco:"ok" },
  { negocio:"Fachadas Norte", pacote:"Gráfica", venc:"27/08/2026", dias:9, valor:389, via:"trial termina", risco:"trial" },
  { negocio:"ROTA LIVRE Comunicação Visual", pacote:"Piloto ROTA LIVRE", venc:"30/08/2026", dias:12, valor:0, via:"trial termina", risco:"trial" },
  { negocio:"Adesivo Express", pacote:"Balcão", venc:"04/08/2026", dias:-14, valor:189, via:"Boleto não pago", risco:"vencida" },
];

// ── Séries do gráfico de tendência ──
const TENDENCIA = [
  { m:"set", v:19400 }, { m:"out", v:21900 }, { m:"nov", v:23100 }, { m:"dez", v:20600 },
  { m:"jan", v:24800 }, { m:"fev", v:26300 }, { m:"mar", v:27100 }, { m:"abr", v:25900 },
  { m:"mai", v:29400 }, { m:"jun", v:31200 }, { m:"jul", v:33050 }, { m:"ago", v:30480 },
];

const PERIODOS = [
  { id:"hoje", label:"Hoje", subs:2, subsValor:578, cadastros:2, receita:578 },
  { id:"semana", label:"Semana", subs:6, subsValor:2405, cadastros:5, receita:2405 },
  { id:"mes", label:"Mês", subs:14, subsValor:8930, cadastros:11, receita:30480 },
  { id:"ano", label:"Ano", subs:126, subsValor:214700, cadastros:98, receita:294120 },
];

// ── Peças reusadas ──
function Kebab({ items }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  React.useEffect(() => {
    if (!open) return;
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, [open]);
  return (
    <div className="cli-kebab-wrap" ref={ref}>
      <button className="cli-kebab-btn" onClick={(e) => { e.stopPropagation(); setOpen(!open); }} aria-expanded={open} title="Mais ações">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
      </button>
      {open && (
        <div className="cli-kebab-menu" onClick={(e) => e.stopPropagation()}>
          {items.map((it, i) => it.sep
            ? <div key={i} className="cli-kebab-sep"></div>
            : <button key={i} className={it.danger ? "danger" : ""} onClick={() => { setOpen(false); it.action?.(); }}>{it.label}</button>)}
        </div>
      )}
    </div>
  );
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
      <button className={`cli-fdrop-btn ${active ? "active" : ""}`} onClick={() => setOpen(!open)} aria-expanded={open}>
        <span className="cli-fdrop-l">{label}</span>
        {active && cur && <span className="cli-fdrop-v">{cur.label}</span>}
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      {open && (
        <div className="cli-fdrop-menu">
          {options.map((o) => (
            <button key={o.id} className={value === o.id ? "active" : ""} onClick={() => { onChange(o.id); setOpen(false); }}>
              {o.label}{o.count != null && <span className="cli-fdrop-n">{o.count}</span>}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function PageHead({ titulo, sub, acoes }) {
  const { PageHeader } = ds();
  if (!PageHeader) return (
    <header className="os-page-h">
      <div className="os-page-h-l"><h1>{titulo}</h1><p>{sub}</p></div>
      <div className="os-page-h-r">{acoes}</div>
    </header>
  );
  return <div className="sa-ph"><PageHeader title={titulo} subtitle={sub} actions={acoes}/></div>;
}

function Kpi({ v, l, sub, tone }) {
  const { KpiCard } = ds();
  if (!KpiCard) return <div className="sa-kpi"><span className="sa-kpi-v">{v}</span><span className="sa-kpi-l">{l}</span></div>;
  return <KpiCard label={l} value={v} description={sub} tone={tone === "accent" ? "info" : tone === "ok" ? "success" : tone === "warn" ? "warning" : tone === "danger" ? "danger" : "default"}/>;
}

// ── Feedback: toast, confirmação, skeleton, vazio ──
function useToast() {
  const [toast, setToast] = useState(null);
  const show = (msg, tone) => { setToast({ msg, tone }); };
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
  return [node, show];
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

function SkelTable({ cols, rows = 6 }) {
  const { Skeleton } = ds();
  return (
    <div className="os-table-wrap">
      <table className="os-table sa-table"><tbody>
        {Array.from({ length: rows }).map((_, i) => (
          <tr key={i}>{Array.from({ length: cols }).map((__, j) => (
            <td key={j}>
              {Skeleton
                ? <Skeleton variant="text" width={j === 0 ? "70%" : j === cols - 1 ? "24px" : "52%"}/>
                : <span className="sa-skel" style={{ width: "60%" }}></span>}
            </td>
          ))}</tr>
        ))}
      </tbody></table>
    </div>
  );
}

function Vazio({ titulo, texto, acao, variante = "no-results" }) {
  const { EmptyState, RegistrationMark } = ds();
  if (!EmptyState) return <div className="sa-vazio"><b>{titulo}</b><p>{texto}</p>{acao}</div>;
  return (
    <div className="sa-vazio-wrap">
      <EmptyState variant={variante} title={titulo} description={texto} action={acao}
        icon={RegistrationMark ? <RegistrationMark size={22}/> : undefined}/>
    </div>
  );
}

// Cabeçalho ordenável
function SortTh({ id, label, sort, onSort, className }) {
  const on = sort.col === id;
  return (
    <th className={(className || "") + " sa-th-sort" + (on ? " on" : "")}
      onClick={() => onSort(id)} title={"Ordenar por " + label.toLowerCase()}>
      <span>{label}</span>
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" aria-hidden="true">
        {on && sort.dir === "asc" ? <path d="m6 14 6-6 6 6"/> : <path d="m6 10 6 6 6-6"/>}
      </svg>
    </th>
  );
}

function Paginacao({ page, pageCount, total, pageSize, onChange }) {
  const { Pagination } = ds();
  if (pageCount <= 1) return <div className="sa-pag"><span className="sa-pag-meta">{total} {total === 1 ? "registro" : "registros"}</span></div>;
  if (!Pagination) return <div className="sa-pag"><span className="sa-pag-meta">{total} registros</span></div>;
  return <div className="sa-pag"><Pagination page={page} pageCount={pageCount} total={total} pageSize={pageSize} onChange={onChange} nextLabel="Próxima"/></div>;
}

function BulkBar({ count, rotulo, acoes, onClose }) {
  const DSB = ds().BulkBar;
  if (!count || !DSB) return null;
  return (
    <div className="sa-bulk-wrap">
      <DSB count={count} label={count === 1 ? rotulo.s + " selecionado" : rotulo.p + " selecionados"} onClose={onClose}
        actions={acoes.map((a) => ({ label:a.label, tone:a.tone, onClick:a.action }))}/>
    </div>
  );
}

function Check({ on, onChange, label }) {
  const { Checkbox } = ds();
  if (!Checkbox) return <button className={"sa-check" + (on ? " on" : "")} onClick={() => onChange(!on)} aria-label={label}></button>;
  return <span className="sa-check-wrap" onClick={(e) => e.stopPropagation()}><Checkbox checked={on} onChange={onChange} name={label}/></span>;
}

// Carga simulada — dá casa ao skeleton sem mentir sobre latência
function useCarga(ms = 420) {
  const [carregando, setCarregando] = useState(true);
  React.useEffect(() => { const t = setTimeout(() => setCarregando(false), ms); return () => clearTimeout(t); }, []);
  return carregando;
}

// Ordenação genérica: campos de data dd/mm/aaaa e número entendidos
const ordKey = (v) => {
  if (typeof v === "number") return v;
  const d = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(v));
  if (d) return +(d[3] + d[2] + d[1]);
  return String(v).toLowerCase();
};
function ordenar(arr, col, dir) {
  if (!col) return arr;
  return [...arr].sort((a, b) => {
    const x = ordKey(a[col]), y = ordKey(b[col]);
    if (x < y) return dir === "asc" ? -1 : 1;
    if (x > y) return dir === "asc" ? 1 : -1;
    return 0;
  });
}

// ── Controles de formulário (reusados pelos 3 forms) ──
function Campo({ label, valor, onChange, erro, ajuda, tipo = "text", mono, placeholder, unidade }) {
  const { Input } = ds();
  const rotulo = unidade ? `${label} (${unidade})` : label;
  if (!Input) return (
    <div className="sa-field">
      <label>{rotulo}</label>
      <input type={tipo} value={valor} placeholder={placeholder} onChange={(e) => onChange(e.target.value)}/>
    </div>
  );
  return (
    <div className={mono ? "sa-campo mono" : "sa-campo"}>
      <Input label={rotulo} value={valor} placeholder={placeholder} type={tipo} error={erro} help={erro ? undefined : ajuda}
        onChange={(e) => onChange(e && e.target ? e.target.value : e)}/>
    </div>
  );
}

function Sel({ label, valor, onChange, opcoes, ajuda }) {
  const { Select } = ds();
  const opts = opcoes.map((o) => ({ value: o.id ?? o, label: o.label ?? o }));
  if (!Select) return (
    <div className="sa-field">
      <label>{label}</label>
      <select className="sa-select-el" value={valor} onChange={(e) => onChange(e.target.value)}>
        {opts.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    </div>
  );
  return <div className="sa-campo"><Select label={label} help={ajuda} value={valor} options={opts}
    onChange={(e) => onChange(e && e.target ? e.target.value : e)}/></div>;
}

function Sw({ label, sub, on, onChange }) {
  const { Switch } = ds();
  if (!Switch) return (
    <button className="sa-swrow" onClick={() => onChange(!on)} aria-pressed={on}>
      <span className="sa-swrow-t"><b>{label}</b>{sub && <small>{sub}</small>}</span>
      <span className={"sa-switch" + (on ? " on" : "")}><i></i></span>
    </button>
  );
  return <div className="sa-swrow-ds"><Switch checked={on} onChange={onChange} label={label} sublabel={sub}/></div>;
}

function Seg({ label, valor, onChange, opcoes }) {
  return (
    <div className="sa-field">
      <label>{label}</label>
      <div className="sa-seg sa-seg--form">
        {opcoes.map((o) => (
          <button key={o.id} className={valor === o.id ? "active" : ""} onClick={() => onChange(o.id)}>{o.label}</button>
        ))}
      </div>
    </div>
  );
}

// Nota contextual — Alert do DS (banner tintado por token, nunca pastel sólido)
function Nota({ tone = "info", children }) {
  const { Alert } = ds();
  if (!Alert) return <p className="sa-nota">{children}</p>;
  return <div className="sa-nota-ds"><Alert tone={tone}>{children}</Alert></div>;
}

// Casca comum: drawer de formulário com rodapé fixo
function FormDrawer({ titulo, sub, largo, children, onClose, onSalvar, ctaSalvar, podeSalvar = true }) {
  React.useEffect(() => {
    const h = (e) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, [onClose]);
  return (
    <>
      <div className="sa-scrim" onClick={onClose}></div>
      <aside className={"sa-drawer" + (largo ? " sa-drawer--largo" : "")} role="dialog" aria-label={titulo}>
        <header className="sa-dr-h">
          <div><h2>{titulo}</h2><p>{sub}</p></div>
          <button className="sa-dr-x" onClick={onClose} title="Fechar (esc)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
          </button>
        </header>
        <div className="sa-dr-body">{children}</div>
        <footer className="sa-dr-f">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" disabled={!podeSalvar} onClick={onSalvar}>{ctaSalvar}</button>
        </footer>
      </aside>
    </>
  );
}

const UFS = ["AC","AL","AM","AP","BA","CE","DF","ES","GO","MA","MG","MS","MT","PA","PB","PE","PI","PR","RJ","RN","RO","RR","RS","SC","SE","SP","TO"];
const soDigitos = (v) => v.replace(/\D/g, "");
const mascaraCnpj = (v) => { const d = soDigitos(v).slice(0, 14); return d.replace(/^(\d{2})(\d)/, "$1.$2").replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3").replace(/\.(\d{3})(\d)/, ".$1/$2").replace(/(\d{4})(\d)/, "$1-$2"); };
const mascaraFone = (v) => { const d = soDigitos(v).slice(0, 11); return d.length > 10 ? d.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, "($1) $2-$3") : d.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, "($1) $2-$3"); };
const emailOk = (v) => /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v);

// ── Form de negócio (business/create + edit) ──
function NegocioForm({ modo, base, onClose, onSalvar }) {
  const [f, setF] = useState(() => ({
    nome: base?.nome || "", cnpj: "", cidade: (base?.cidade || "").split(" · ")[0] || "", uf: (base?.cidade || "").split(" · ")[1] || "SP",
    foneBiz: base?.foneBiz || "", dono: base?.dono || "", email: base?.email || "", fone: base?.fone || "",
    pacote: base?.pacote && base.pacote !== "—" ? base.pacote : PACOTES[0].nome, trial: "14", inicio: "18/08/2026",
    ativo: base ? base.ativo : true, notificar: true,
  }));
  const [tocado, setTocado] = useState({});
  const set = (k) => (v) => { setF((x) => ({ ...x, [k]: v })); setTocado((t) => ({ ...t, [k]: true })); };
  const erro = {
    nome: tocado.nome && !f.nome.trim() ? "Diga o nome que aparece na nota e no app." : null,
    dono: tocado.dono && !f.dono.trim() ? "Toda conta precisa de um responsável." : null,
    email: tocado.email && !emailOk(f.email) ? "E-mail inválido — é por aqui que o dono recebe o acesso." : null,
  };
  const podeSalvar = f.nome.trim() && f.dono.trim() && emailOk(f.email);
  const pkg = PACOTES.find((p) => p.nome === f.pacote);

  return (
    <FormDrawer largo onClose={onClose} podeSalvar={podeSalvar}
      titulo={modo === "editar" ? "Editar negócio" : "Novo negócio"}
      sub={modo === "editar" ? `biz #${base.id} · criado em ${base.criado}` : "O dono recebe o acesso por e-mail assim que você salvar"}
      ctaSalvar={modo === "editar" ? "Salvar alterações" : "Criar negócio"}
      onSalvar={() => onSalvar(f, modo)}>

      <section className="sa-dr-sec">
        <h3>Identificação</h3>
        <div className="sa-form sa-form--drawer">
          <Campo label="Nome do negócio" valor={f.nome} onChange={set("nome")} erro={erro.nome} placeholder="ROTA LIVRE Comunicação Visual"/>
          <div className="sa-form-2">
            <Campo label="CNPJ" valor={f.cnpj} onChange={(v) => set("cnpj")(mascaraCnpj(v))} mono placeholder="00.000.000/0000-00" ajuda="Opcional no cadastro; obrigatório para emitir NF-e."/>
            <Campo label="Telefone do negócio" valor={f.foneBiz} onChange={(v) => set("foneBiz")(mascaraFone(v))} mono placeholder="(11) 4002-1122"/>
          </div>
          <div className="sa-form-2">
            <Campo label="Cidade" valor={f.cidade} onChange={set("cidade")} placeholder="São Paulo"/>
            <Sel label="UF" valor={f.uf} onChange={set("uf")} opcoes={UFS}/>
          </div>
        </div>
      </section>

      <section className="sa-dr-sec">
        <h3>Dono da conta</h3>
        <div className="sa-form sa-form--drawer">
          <Campo label="Nome do dono" valor={f.dono} onChange={set("dono")} erro={erro.dono} placeholder="Larissa Souza"/>
          <div className="sa-form-2">
            <Campo label="E-mail" valor={f.email} onChange={set("email")} erro={erro.email} tipo="email" placeholder="larissa@rotalivre.com"/>
            <Campo label="Celular" valor={f.fone} onChange={(v) => set("fone")(mascaraFone(v))} mono placeholder="(11) 99120-4408"/>
          </div>
          <Sw label="Enviar e-mail de primeiro acesso" sub="Link de senha válido por 48 h — sem senha provisória no meio." on={f.notificar} onChange={set("notificar")}/>
        </div>
      </section>

      <section className="sa-dr-sec">
        <h3>Assinatura inicial</h3>
        <div className="sa-form sa-form--drawer">
          <Sel label="Pacote" valor={f.pacote} onChange={set("pacote")}
            opcoes={PACOTES.filter((p) => p.ativo).map((p) => ({ id: p.nome, label: p.nome + (p.privado ? " (privado)" : "") }))}/>
          {pkg && <Nota>{pkg.usuarios === 0 ? "Usuários ilimitados" : pkg.usuarios + " usuários"} · {pkg.locais === 0 ? "locais ilimitados" : pkg.locais + (pkg.locais === 1 ? " local" : " locais")} · {pkg.preco ? BRL(pkg.preco) + " / mês" : "grátis"}</Nota>}
          <div className="sa-form-2">
            <Campo label="Teste" valor={f.trial} onChange={(v) => set("trial")(soDigitos(v).slice(0, 3))} mono unidade="dias" ajuda="0 cobra desde o primeiro dia."/>
            <Campo label="Começa em" valor={f.inicio} onChange={set("inicio")} mono placeholder="dd/mm/aaaa"/>
          </div>
          <Sw label="Negócio ativo" sub="Desativado, ninguém entra — o dado fica guardado." on={f.ativo} onChange={set("ativo")}/>
        </div>
      </section>
    </FormDrawer>
  );
}

// ── Form de pacote (packages/create + edit) ──
function PacoteForm({ modo, base, onClose, onSalvar }) {
  const [f, setF] = useState(() => ({
    nome: base?.nome || "", desc: base?.desc || "", preco: String(base?.preco ?? ""), intervalo: base?.intervalo || "mês",
    intervalCount: String(base?.intervalCount ?? 1), trial: String(base?.trial ?? 14),
    locais: String(base?.locais ?? 1), usuarios: String(base?.usuarios ?? 3), produtos: String(base?.produtos ?? 0), faturas: String(base?.faturas ?? 0),
    perms: base ? (PACOTE_PERMS[base.id] || []) : ["fiscal"], ativo: base ? base.ativo : true, privado: base?.privado || false, avulso: base?.avulso || false,
  }));
  const [tocado, setTocado] = useState({});
  const set = (k) => (v) => { setF((x) => ({ ...x, [k]: v })); setTocado((t) => ({ ...t, [k]: true })); };
  const erroNome = tocado.nome && !f.nome.trim() ? "O nome aparece na tela de assinatura do cliente." : null;
  const num = (v) => soDigitos(v).slice(0, 6);
  const togglePerm = (k) => setF((x) => ({ ...x, perms: x.perms.includes(k) ? x.perms.filter((p) => p !== k) : [...x.perms, k] }));

  return (
    <FormDrawer largo onClose={onClose} podeSalvar={!!f.nome.trim()}
      titulo={modo === "editar" ? "Editar pacote" : "Novo pacote"}
      sub={modo === "editar" ? `${base.assinantes} ${base.assinantes === 1 ? "assinante usa" : "assinantes usam"} este pacote hoje` : "Limites com 0 valem como ilimitado"}
      ctaSalvar={modo === "editar" ? "Salvar pacote" : "Criar pacote"}
      onSalvar={() => onSalvar(f, modo)}>

      <section className="sa-dr-sec">
        <h3>Identidade e preço</h3>
        <div className="sa-form sa-form--drawer">
          <Campo label="Nome do pacote" valor={f.nome} onChange={set("nome")} erro={erroNome} placeholder="Gráfica"/>
          <Campo label="Descrição" valor={f.desc} onChange={set("desc")} placeholder="Produção completa: OP, fila de impressão, acabamento e fiscal."/>
          <div className="sa-form-2">
            <Campo label="Preço" valor={f.preco} onChange={(v) => set("preco")(num(v))} mono unidade="R$" placeholder="389" ajuda="0 = pacote gratuito."/>
            <Campo label="Teste" valor={f.trial} onChange={(v) => set("trial")(num(v))} mono unidade="dias"/>
          </div>
          <div className="sa-form-2">
            <Seg label="Cobrança por" valor={f.intervalo} onChange={set("intervalo")} opcoes={[{ id:"mês", label:"Mês" }, { id:"ano", label:"Ano" }]}/>
            <Campo label="Períodos por ciclo" valor={f.intervalCount} onChange={(v) => set("intervalCount")(num(v))} mono ajuda="2 com cobrança mensal = cobra a cada 2 meses."/>
          </div>
        </div>
      </section>

      <section className="sa-dr-sec">
        <h3>Limites</h3>
        <div className="sa-form sa-form--drawer">
          <div className="sa-form-2">
            <Campo label="Locais" valor={f.locais} onChange={(v) => set("locais")(num(v))} mono/>
            <Campo label="Usuários" valor={f.usuarios} onChange={(v) => set("usuarios")(num(v))} mono/>
          </div>
          <div className="sa-form-2">
            <Campo label="Produtos" valor={f.produtos} onChange={(v) => set("produtos")(num(v))} mono/>
            <Campo label="Faturas por mês" valor={f.faturas} onChange={(v) => set("faturas")(num(v))} mono/>
          </div>
          <Nota>Deixe 0 no campo que não deve ter teto. Reduzir um limite não corta quem já passou dele — só bloqueia novos.</Nota>
        </div>
      </section>

      <section className="sa-dr-sec">
        <h3>Módulos liberados</h3>
        <div className="sa-chips">
          {Object.entries(PERM_LABEL).map(([k, label]) => (
            <button key={k} className={"sa-chip" + (f.perms.includes(k) ? " on" : "")} onClick={() => togglePerm(k)}>{label}</button>
          ))}
        </div>
      </section>

      <section className="sa-dr-sec">
        <h3>Visibilidade</h3>
        <div className="sa-form sa-form--drawer">
          <Sw label="Pacote ativo" sub="Inativo sai da tela de assinatura; quem já assinou continua." on={f.ativo} onChange={set("ativo")}/>
          <Sw label="Privado" sub="Só o superadmin consegue atribuir — não aparece pro cliente." on={f.privado} onChange={set("privado")}/>
          <Sw label="Cobrança avulsa" sub="Cobra uma vez (setup, implantação) e não renova." on={f.avulso} onChange={set("avulso")}/>
        </div>
      </section>
    </FormDrawer>
  );
}

// ── Editar assinatura: status e vigência ──
function AssinaturaForm({ campo, base, onClose, onSalvar }) {
  const [status, setStatus] = useState(base.status);
  const [inicio, setInicio] = useState(base.inicio);
  const [fim, setFim] = useState(base.fim);
  const [trialFim, setTrialFim] = useState(base.trialFim === "—" ? "" : base.trialFim);
  const [motivo, setMotivo] = useState("");
  const mudouStatus = status !== base.status;
  return (
    <FormDrawer onClose={onClose}
      titulo={campo === "status" ? "Mudar status da assinatura" : "Editar vigência"}
      sub={`${base.id} · ${base.negocio} · ${base.pacote}`}
      ctaSalvar={campo === "status" ? "Aplicar status" : "Salvar datas"}
      podeSalvar={campo === "status" ? mudouStatus : true}
      onSalvar={() => onSalvar(campo === "status" ? { status, motivo } : { inicio, fim, trialFim })}>
      <section className="sa-dr-sec">
        <div className="sa-form sa-form--drawer">
          {campo === "status" ? (
            <>
              <Sel label="Status" valor={status} onChange={setStatus} opcoes={[
                { id:"aprovada", label:"Aprovada" }, { id:"trial", label:"Trial" }, { id:"pendente", label:"Pendente" },
                { id:"vencida", label:"Vencida" }, { id:"cancelada", label:"Cancelada" }]}/>
              <Campo label="Motivo (fica no log)" valor={motivo} onChange={setMotivo} placeholder="Pix confirmado fora do gateway"/>
              {status === "aprovada" && base.status !== "aprovada" && <Nota>Aprovar libera o acesso na hora e conta na receita do mês.</Nota>}
              {status === "cancelada" && <Nota>Cancelar não apaga o registro: ele sai da lista ativa e para de renovar.</Nota>}
            </>
          ) : (
            <>
              <div className="sa-form-2">
                <Campo label="Início" valor={inicio} onChange={setInicio} mono placeholder="dd/mm/aaaa"/>
                <Campo label="Fim" valor={fim} onChange={setFim} mono placeholder="dd/mm/aaaa"/>
              </div>
              <Campo label="Fim do trial" valor={trialFim} onChange={setTrialFim} mono placeholder="dd/mm/aaaa" ajuda="Em branco = assinatura sem período de teste."/>
              <Nota>Esticar o fim prorroga o acesso sem gerar cobrança nova.</Nota>
            </>
          )}
        </div>
      </section>
    </FormDrawer>
  );
}

// ── View: visão geral ──
function ViewVisao() {
  const [per, setPer] = useState("mes");
  const p = PERIODOS.find((x) => x.id === per);
  const DSChart = ds().Chart;
  const semAssinatura = NEGOCIOS.filter((n) => n.sub === "sem").length;
  const mrr = NEGOCIOS.reduce((a, n) => a + n.mrr, 0);
  const max = Math.max(...TENDENCIA.map((t) => t.v));

  return (
    <div className="os-page sa-page" data-screen-label="Superadmin · Visão geral">
      <PageHead titulo="Superadmin" sub={`${NEGOCIOS.length} negócios · ${PACOTES.filter(x=>x.ativo).length} pacotes ativos · MRR ${BRL(mrr)}`}
        acoes={<>
          <button className="os-btn ghost" onClick={() => window.__selectRoute?.("sa-comunicador")}>Comunicador</button>
          <button className="os-btn primary" onClick={() => window.__selectRoute?.("sa-negocios")}>Ver negócios</button>
        </>}/>

      <div className="sa-periodo">
        <div className="sa-seg">
          {PERIODOS.map((x) => (
            <button key={x.id} className={per === x.id ? "active" : ""} onClick={() => setPer(x.id)}>{x.label}</button>
          ))}
        </div>
        <span className="sa-periodo-nota">Janela rolante — encerra em 18/08/2026</span>
      </div>

      <div className="sa-kpis">
        <Kpi v={BRL(p.subsValor)} l="Novas assinaturas" sub={`${p.subs} contratos no período`} tone="accent"/>
        <Kpi v={p.cadastros} l="Novos cadastros" sub="cadastro próprio + criados pelo superadmin"/>
        <Kpi v={semAssinatura} l="Sem assinatura" sub="cadastrou e não assinou" tone="danger"/>
        <Kpi v={BRL(mrr)} l="Receita recorrente (MRR)" sub="+8,4% contra o mês anterior" tone="ok"/>
      </div>

      <div className="sa-grid3">
        <section className="sa-card">
          <header className="sa-card-h"><h2>Trial vira assinatura</h2><span className="sa-card-meta">últimos 90 dias</span></header>
          <div className="sa-funil">
            {FUNIL.map((e, i) => {
              const pct = e.n / FUNIL[0].n * 100;
              const perda = i > 0 ? FUNIL[i-1].n - e.n : 0;
              return (
                <div key={e.etapa} className="sa-funil-l">
                  <div className="sa-funil-h"><span>{e.etapa}</span><b className="sa-mono">{e.n}</b></div>
                  <i style={{ width: pct + "%" }}></i>
                  {i > 0 && <small>−{perda} aqui · {Math.round(e.n / FUNIL[i-1].n * 100)}% seguiram</small>}
                </div>
              );
            })}
          </div>
          <footer className="sa-card-f">Conversão ponta a ponta: <b>{Math.round(FUNIL[3].n / FUNIL[0].n * 100)}%</b> — o maior vazamento é entre o cadastro e o 3º dia de uso.</footer>
        </section>

        <section className="sa-card">
          <header className="sa-card-h"><h2>Churn</h2><span className="sa-card-meta">30 dias</span></header>
          <div className="sa-churn">
            <div className="sa-churn-v"><b>{CHURN.taxa.toString().replace(".", ",")}%</b><span>{CHURN.saidas} saídas em {CHURN.base} assinantes</span></div>
            <ul>
              {CHURN.motivo.map((m) => (
                <li key={m.m}><span>{m.m}</span><b className="sa-mono">{m.n}</b><i style={{ width: (m.n / CHURN.saidas * 100) + "%" }}></i></li>
              ))}
            </ul>
          </div>
          <footer className="sa-card-f">Motivo vem da resposta do cancelamento — 1 saída sem motivo declarado não entra na conta.</footer>
        </section>

        <section className="sa-card">
          <header className="sa-card-h"><h2>Receita por pacote</h2><span className="sa-card-meta">MRR {BRL(mrr)}</span></header>
          <ul className="sa-rpp">
            {PACOTES.filter((p) => p.preco > 0 && p.intervalo === "mês").map((p) => {
              const receita = NEGOCIOS.filter((n) => n.pacote === p.nome).reduce((a, n) => a + n.mrr, 0);
              const pagantes = NEGOCIOS.filter((n) => n.pacote === p.nome && n.mrr > 0).length;
              return (
                <li key={p.id}>
                  <div className="sa-rpp-h"><span>{p.nome}</span><b className="sa-mono">{receita ? BRL(receita) : "—"}</b></div>
                  <i style={{ width: (mrr ? receita / mrr * 100 : 0) + "%" }}></i>
                  <small>{pagantes} {pagantes === 1 ? "pagante" : "pagantes"} · {BRL(p.preco)} / mês</small>
                </li>
              );
            })}
          </ul>
          <footer className="sa-card-f">Pacotes gratuitos e avulsos ficam fora do MRR — aparecem no caixa do mês, não na recorrência.</footer>
        </section>
      </div>

      <section className="sa-card sa-card--wide">
        <header className="sa-card-h">
          <h2>Vencendo ou vencido</h2>
          <span className="sa-card-meta">{VENCENDO.filter(v=>v.dias<0).length} em atraso · {VENCENDO.filter(v=>v.dias>=0).length} nos próximos 12 dias</span>
        </header>
        <ul className="sa-fila">
          {[...VENCENDO].sort((a,b)=>a.dias-b.dias).map((v) => (
            <li key={v.negocio} className={v.dias < 0 ? "atraso" : ""}>
              <span className={"sa-prazo " + (v.dias < 0 ? "atraso" : v.dias <= 10 ? "perto" : "")}>
                {v.dias < 0 ? `${Math.abs(v.dias)} dias em atraso` : `em ${v.dias} dias`}
              </span>
              <div className="sa-fila-t"><b>{v.negocio}</b><small>{v.pacote} · {v.via} · vence {v.venc}</small></div>
              <span className="sa-mono sa-fila-v">{v.valor ? BRL(v.valor) : "grátis"}</span>
              <button className="sa-fila-a" onClick={() => window.__selectRoute?.("sa-assinaturas")}>
                {v.dias < 0 ? "Cobrar" : v.risco === "trial" ? "Converter" : "Ver assinatura"}
              </button>
            </li>
          ))}
        </ul>
      </section>

      <div className="sa-grid2">
        <section className="sa-card">
          <header className="sa-card-h">
            <h2>Tendência mensal de vendas</h2>
            <span className="sa-card-meta">últimos 12 meses · {BRL(TENDENCIA[TENDENCIA.length-1].v)} em ago</span>
          </header>
          <div className="sa-chart-ds">
            {DSChart
              ? <DSChart type="bar" height={180} highlightLast formatValue={(v) => BRL(v)}
                  data={TENDENCIA.map((t) => ({ label: t.m, value: t.v }))}/>
              : <div className="sa-chart">{TENDENCIA.map((t) => (
                  <div key={t.m} className="sa-bar-wrap"><div className="sa-bar" style={{ height: (t.v / max * 100) + "%" }}></div><span className="sa-bar-l">{t.m}</span></div>
                ))}</div>}
          </div>
        </section>

        <section className="sa-card">
          <header className="sa-card-h">
            <h2>O que fazer primeiro</h2>
            <span className="sa-card-meta">3 itens</span>
          </header>
          <ul className="sa-pend">
            <li onClick={() => window.__selectRoute?.("sa-negocios")}><b>2 negócios cadastraram e não assinaram</b><span>Studio Lona e Placa &amp; Cia, nos últimos 3 dias — o vazamento do funil está aqui.</span></li>
            <li onClick={() => window.__selectRoute?.("sa-comunicador")}><b>2 trials terminam nesta semana</b><span>Fachadas Norte (27/08) e ROTA LIVRE (30/08) — aviso de fim de teste sai pelo comunicador.</span></li>
            <li onClick={() => window.__selectRoute?.("sa-pacotes")}><b>Legado 2024 está inativo com 4 assinantes</b><span>Migrar para Balcão antes de arquivar; hoje eles pagam R$ 149 por uma grade que não existe mais.</span></li>
          </ul>
        </section>
      </div>

      <section className="sa-card sa-card--wide">
        <header className="sa-card-h">
          <h2>Cadastros recentes</h2>
          <button className="sa-link" onClick={() => window.__selectRoute?.("sa-negocios")}>Ver todos</button>
        </header>
        <div className="os-table-wrap">
          <table className="os-table sa-table">
            <thead><tr><th>Negócio</th><th>Dono</th><th>Pacote</th><th>Assinatura</th><th>Cadastro</th><th className="ta-r">MRR</th></tr></thead>
            <tbody>
              {NEGOCIOS.slice().sort((a,b)=>b.id-a.id).slice(0,5).map((n) => (
                <tr key={n.id}>
                  <td><div className="sa-biz"><b>{n.nome}</b><small>biz #{n.id} · {n.cidade}</small></div></td>
                  <td><span className="sa-dim">{n.dono}</span></td>
                  <td><span className="sa-mono">{n.pacote}</span></td>
                  <td><SubBadge s={n.sub}/></td>
                  <td><span className="sa-dim">{n.criado}</span></td>
                  <td className="ta-r"><span className="sa-mono">{n.mrr ? BRL(n.mrr) : "—"}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

// ── Drawer de detalhe do negócio (PT-02) ──
function NegocioDrawer({ n, onClose, onAcao }) {
  React.useEffect(() => {
    const h = (e) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, [onClose]);
  if (!n) return null;
  const subs = ASSINATURAS.filter((s) => s.negocio === n.nome);
  const pkg = PACOTES.find((p) => p.nome === n.pacote);
  const uso = { usuarios: Math.min(n.id % 9 + 1, 12), produtos: 120 + (n.id % 7) * 43 };
  return (
    <>
      <div className="sa-scrim" onClick={onClose}></div>
      <aside className="sa-drawer" role="dialog" aria-label={`Negócio ${n.nome}`}>
        <header className="sa-dr-h">
          <div>
            <span className="sa-mono sa-dr-id">biz #{n.id}</span>
            <h2>{n.nome}</h2>
            <p>{n.cidade} · cadastro {n.criado} por {n.criador}</p>
          </div>
          <button className="sa-dr-x" onClick={onClose} title="Fechar (esc)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
          </button>
        </header>

        <div className="sa-dr-body">
          <section className="sa-dr-sec">
            <h3>Assinatura</h3>
            <div className="sa-dr-rows">
              <div><span>Situação</span><SubBadge s={n.sub}/></div>
              <div><span>Pacote</span><b>{n.pacote}</b></div>
              <div><span>Vence em</span><b className="sa-mono">{n.venc}</b></div>
              <div><span>Recorrência</span><b className="sa-mono">{n.mrr ? BRL(n.mrr) + " / mês" : "—"}</b></div>
            </div>
          </section>

          {pkg && (
            <section className="sa-dr-sec">
              <h3>Uso contra o limite do pacote</h3>
              <div className="sa-dr-uso">
                <Uso rotulo="Usuários" usado={uso.usuarios} teto={pkg.usuarios}/>
                <Uso rotulo="Produtos" usado={uso.produtos} teto={pkg.produtos}/>
              </div>
            </section>
          )}

          <section className="sa-dr-sec">
            <h3>Dono e contato</h3>
            <div className="sa-dr-rows">
              <div><span>Dono</span><b>{n.dono}</b></div>
              <div><span>E-mail</span><b>{n.email}</b></div>
              <div><span>Celular</span><b className="sa-mono">{n.fone}</b></div>
              <div><span>Telefone do negócio</span><b className="sa-mono">{n.foneBiz}</b></div>
              <div><span>Situação do acesso</span><span className={"sa-dot " + (n.ativo ? "on" : "off")}><i></i>{n.ativo ? "Ativo" : "Inativo"}</span></div>
              <div><span>Última venda</span><b>{n.ultimaTx}</b></div>
            </div>
          </section>

          <section className="sa-dr-sec">
            <h3>Histórico de assinaturas</h3>
            {subs.length === 0
              ? <p className="sa-dr-empty">Nunca assinou — só cadastro.</p>
              : <ul className="sa-dr-hist">
                  {subs.map((s) => (
                    <li key={s.id}>
                      <b className="sa-mono">{s.id}</b>
                      <span>{s.pacote} · {s.inicio} a {s.fim} · {s.preco ? BRL(s.preco) : "grátis"}</span>
                      <SubBadge s={s.status}/>
                    </li>
                  ))}
                </ul>}
          </section>
        </div>

        <footer className="sa-dr-f">
          <button className="os-btn ghost" onClick={() => onAcao("entrar", n)}>Entrar como este negócio</button>
          <button className="os-btn primary" onClick={() => onAcao("assinar", n)}>Adicionar assinatura</button>
        </footer>
      </aside>
    </>
  );
}

// Uso contra o teto do pacote — Progress do DS
function Uso({ rotulo, usado, teto }) {
  const { Progress } = ds();
  const ilim = teto === 0;
  const pct = ilim ? 0 : Math.min(100, usado / teto * 100);
  const tone = pct >= 90 ? "danger" : pct >= 70 ? "warning" : "accent";
  if (!Progress) return <div className="sa-uso-l"><span>{rotulo}</span><b className="sa-mono">{usado} de {ilim ? "∞" : teto}</b></div>;
  return (
    <div className="sa-uso-l">
      <Progress value={ilim ? 0 : usado} max={ilim ? 100 : teto} tone={ilim ? "accent" : tone}
        label={rotulo} showValue formatValue={() => (ilim ? `${usado} · sem teto` : `${usado} de ${teto}`)}/>
    </div>
  );
}

// ── View: negócios ──
const NEG_PAGE = 6;
function ViewNegocios() {
  const [q, setQ] = useState("");
  const [fPacote, setFPacote] = useState("all");
  const [fSub, setFSub] = useState("all");
  const [fAtivo, setFAtivo] = useState("all");
  const [fTx, setFTx] = useState("all");
  const [aberto, setAberto] = useState(null);
  const [sort, setSort] = useState({ col: "criado", dir: "desc" });
  const [page, setPage] = useState(1);
  const [sel, setSel] = useState([]);
  const [confirma, setConfirma] = useState(null);
  const [form, setForm] = useState(null);
  const [toastNode, toast] = useToast();
  const carregando = useCarga();
  const buscaRef = useRef(null);

  React.useEffect(() => {
    const h = (e) => {
      const alvo = e.target.tagName;
      if (alvo === "INPUT" || alvo === "TEXTAREA" || e.metaKey || e.ctrlKey) return;
      if (e.key === "n") { e.preventDefault(); setForm({ modo: "novo" }); }
      if (e.key === "/") { e.preventDefault(); buscaRef.current?.focus(); }
    };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, []);

  const filtrados = NEGOCIOS.filter((n) => {
    if (fPacote !== "all" && n.pacote !== fPacote) return false;
    if (fSub !== "all" && n.sub !== fSub) return false;
    if (fAtivo !== "all" && (fAtivo === "ativo") !== n.ativo) return false;
    if (fTx === "nunca" && n.ultimaTx !== "nunca") return false;
    if (fTx === "30d" && !/dias|nunca/.test(n.ultimaTx)) return false;
    if (q) {
      const s = q.toLowerCase();
      if (![n.nome, n.dono, n.email, n.cidade, String(n.id)].some((v) => String(v).toLowerCase().includes(s))) return false;
    }
    return true;
  });
  const ordenados = ordenar(filtrados, sort.col, sort.dir);
  const pageCount = Math.max(1, Math.ceil(ordenados.length / NEG_PAGE));
  const pagina = ordenados.slice((Math.min(page, pageCount) - 1) * NEG_PAGE, Math.min(page, pageCount) * NEG_PAGE);
  const ativos = [fPacote, fSub, fAtivo, fTx].filter((v) => v !== "all").length;
  const limpar = () => { setFPacote("all"); setFSub("all"); setFAtivo("all"); setFTx("all"); setQ(""); setPage(1); };
  const onSort = (col) => { setSort((s) => ({ col, dir: s.col === col && s.dir === "asc" ? "desc" : "asc" })); setPage(1); };
  const marcado = (id) => sel.includes(id);
  const marcar = (id, on) => setSel((s) => (on ? [...s, id] : s.filter((x) => x !== id)));
  const todosDaPagina = pagina.length > 0 && pagina.every((n) => marcado(n.id));

  const acao = (tipo, n) => {
    if (tipo === "entrar") toast(`Sessão aberta como ${n.nome} — sair pelo menu do topo`, "ok");
    if (tipo === "assinar") toast(`Assinatura lançada para ${n.nome}`, "ok");
    if (tipo === "senha") toast(`Link de redefinição enviado para ${n.email}`, "ok");
    if (tipo === "editar") setForm({ modo: "editar", base: n });
    if (tipo === "toggle") toast(n.ativo ? `${n.nome} desativado` : `${n.nome} ativado`, n.ativo ? "warn" : "ok");
  };

  return (
    <div className="os-page sa-page" data-screen-label="Superadmin · Negócios">
      <PageHead titulo="Negócios" sub={`${NEGOCIOS.length} cadastrados · ${NEGOCIOS.filter(n=>n.sub==="ativa").length} com assinatura ativa · ${NEGOCIOS.filter(n=>!n.ativo).length} inativo`}
        acoes={<>
          <button className="os-btn ghost" onClick={() => toast(`${ordenados.length} negócios exportados em CSV`, "ok")}>Exportar</button>
          <button className="os-btn primary" onClick={() => setForm({ modo: "novo" })}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Novo negócio <kbd className="sa-kbd sa-kbd--btn">n</kbd>
          </button>
        </>}/>

      <div className="sa-toolbar">
        <div className="sa-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input ref={buscaRef} value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} placeholder="Buscar negócio, dono, e-mail ou biz #…"/>
          <kbd className="sa-kbd">/</kbd>
        </div>
        <div className="sa-filters">
          <FilterDropdown label="Pacote" value={fPacote} onChange={(v) => { setFPacote(v); setPage(1); }} options={[{ id:"all", label:"Todos" }, ...PACOTES.map(p=>({ id:p.nome, label:p.nome, count:p.assinantes }))]}/>
          <FilterDropdown label="Assinatura" value={fSub} onChange={(v) => { setFSub(v); setPage(1); }} options={[
            { id:"all", label:"Todas" }, { id:"ativa", label:"Ativa" }, { id:"trial", label:"Trial" },
            { id:"vencida", label:"Vencida" }, { id:"cancelada", label:"Cancelada" }, { id:"sem", label:"Sem assinatura" }]}/>
          <FilterDropdown label="Status" value={fAtivo} onChange={(v) => { setFAtivo(v); setPage(1); }} options={[{ id:"all", label:"Todos" }, { id:"ativo", label:"Ativo" }, { id:"inativo", label:"Inativo" }]}/>
          <FilterDropdown label="Última venda" value={fTx} onChange={(v) => { setFTx(v); setPage(1); }} options={[
            { id:"all", label:"Qualquer" }, { id:"30d", label:"Há mais de 7 dias" }, { id:"nunca", label:"Nunca vendeu" }]}/>
          {(ativos > 0 || q) && <button className="sa-clear" onClick={limpar}>Limpar</button>}
          <span className="sa-count">{ordenados.length} de {NEGOCIOS.length}</span>
        </div>
      </div>

      {carregando ? <SkelTable cols={6}/> : ordenados.length === 0 ? (
        <Vazio titulo="Nenhum negócio com esses filtros"
          texto={q ? `A busca "${q}" não bate com nome, dono, e-mail ou biz #. Tente só o número do biz.` : "Os filtros combinados não deixaram nenhum cadastro. Limpe e refine um de cada vez."}
          acao={<button className="os-btn ghost" onClick={limpar}>Limpar filtros</button>}/>
      ) : (
        <>
          <div className="os-table-wrap">
            <table className="os-table sa-table sa-table--neg">
              <thead><tr>
                <th className="sa-th-check">
                  <Check on={todosDaPagina} label="Selecionar a página"
                    onChange={(on) => setSel(on ? [...new Set([...sel, ...pagina.map(n => n.id)])] : sel.filter(id => !pagina.some(n => n.id === id)))}/>
                </th>
                <SortTh id="nome" label="Negócio" sort={sort} onSort={onSort}/>
                <SortTh id="dono" label="Dono" sort={sort} onSort={onSort}/>
                <SortTh id="sub" label="Assinatura atual" sort={sort} onSort={onSort}/>
                <SortTh id="mrr" label="Recorrência" sort={sort} onSort={onSort} className="ta-r"/>
                <SortTh id="criado" label="Cadastro" sort={sort} onSort={onSort}/>
                <th className="sa-th-act"></th>
              </tr></thead>
              <tbody>
                {pagina.map((n) => (
                  <tr key={n.id} className={"sa-row" + (marcado(n.id) ? " sel" : "")} onClick={() => setAberto(n)}>
                    <td className="sa-td-check" onClick={(e) => e.stopPropagation()}>
                      <Check on={marcado(n.id)} onChange={(on) => marcar(n.id, on)} label={`Selecionar ${n.nome}`}/>
                    </td>
                    <td>
                      <div className="sa-biz">
                        <b>{n.nome}{!n.ativo && <span className="sa-inativo">inativo</span>}</b>
                        <small className="sa-mono">biz #{n.id}</small><small>{n.cidade}</small>
                      </div>
                    </td>
                    <td><div className="sa-biz"><b className="sa-b-reg">{n.dono}</b><small>{n.email}</small></div></td>
                    <td>
                      <div className="sa-sub-cell">
                        <SubBadge s={n.sub}/>
                        <small>{n.pacote !== "—" ? `${n.pacote} · vence ${n.venc}` : "nenhum pacote"}</small>
                      </div>
                    </td>
                    <td className="ta-r"><span className="sa-mono">{n.mrr ? BRL(n.mrr) : "—"}</span></td>
                    <td><div className="sa-biz"><b className="sa-mono sa-b-reg">{n.criado}</b><small>última venda {n.ultimaTx}</small></div></td>
                    <td className="sa-td-act" onClick={(e) => e.stopPropagation()}>
                      <Kebab items={[
                        { label:"Ver detalhes", action: () => setAberto(n) },
                        { label:"Entrar como este negócio", action: () => acao("entrar", n) },
                        { label:"Editar cadastro", action: () => acao("editar", n) },
                        { sep:true },
                        { label:"Adicionar assinatura", action: () => acao("assinar", n) },
                        { label:"Redefinir senha do dono", action: () => setConfirma({ tipo:"senha", n }) },
                        { sep:true },
                        { label: n.ativo ? "Desativar negócio" : "Ativar negócio", action: () => (n.ativo ? setConfirma({ tipo:"toggle", n }) : acao("toggle", n)) },
                        { label:"Excluir negócio", danger:true, action: () => setConfirma({ tipo:"excluir", n }) },
                      ]}/>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Paginacao page={Math.min(page, pageCount)} pageCount={pageCount} total={ordenados.length} pageSize={NEG_PAGE} onChange={setPage}/>
        </>
      )}

      <BulkBar count={sel.length} rotulo={{ s:"negócio", p:"negócios" }} onClose={() => setSel([])}
        acoes={[
          { label:"Comunicar", action: () => { toast(`Comunicador aberto para ${sel.length} negócios`); window.__selectRoute?.("sa-comunicador"); } },
          { label:"Exportar seleção", action: () => toast(`${sel.length} negócios exportados em CSV`, "ok") },
          { label:"Desativar", tone:"danger", action: () => setConfirma({ tipo:"lote" }) },
        ]}/>

      <NegocioDrawer n={aberto} onClose={() => setAberto(null)} onAcao={(t, n) => { if (t === "editar") setAberto(null); acao(t, n); }}/>

      {form && <NegocioForm modo={form.modo} base={form.base} onClose={() => setForm(null)}
        onSalvar={(f, modo) => { setForm(null); toast(modo === "editar" ? `${f.nome} atualizado` : `${f.nome} criado — acesso enviado para ${f.email}`, "ok"); }}/>}

      <Confirm open={!!confirma} onClose={() => setConfirma(null)}
        titulo={confirma?.tipo === "excluir" ? "Excluir negócio?"
          : confirma?.tipo === "toggle" ? "Desativar negócio?"
          : confirma?.tipo === "lote" ? "Desativar em lote?" : "Redefinir a senha do dono?"}
        texto={confirma?.tipo === "excluir" ? `${confirma?.n?.nome} sai da plataforma com vendas, OS e fiscal. Não tem como desfazer.`
          : confirma?.tipo === "toggle" ? `${confirma?.n?.nome} perde o acesso agora; o dado fica guardado e você pode reativar depois.`
          : confirma?.tipo === "lote" ? `${sel.length} negócios perdem o acesso agora. O dado fica guardado.`
          : `Enviamos um link de redefinição para ${confirma?.n?.email}. A senha atual continua valendo até ele usar o link.`}
        cta={confirma?.tipo === "excluir" ? "Excluir mesmo" : confirma?.tipo === "senha" ? "Enviar link" : "Desativar"}
        onConfirm={() => {
          if (confirma.tipo === "excluir") toast(`${confirma.n.nome} excluído`, "danger");
          else if (confirma.tipo === "lote") { toast(`${sel.length} negócios desativados`, "warn"); setSel([]); }
          else acao(confirma.tipo, confirma.n);
        }}/>

      {toastNode}
    </div>
  );
}

// ── View: assinaturas ──
const ASS_PAGE = 6;
function ViewAssinaturas() {
  const [fPacote, setFPacote] = useState("all");
  const [fStatus, setFStatus] = useState("all");
  const [sort, setSort] = useState({ col: "criado", dir: "desc" });
  const [page, setPage] = useState(1);
  const [sel, setSel] = useState([]);
  const [confirma, setConfirma] = useState(null);
  const [form, setForm] = useState(null);
  const [toastNode, toast] = useToast();
  const carregando = useCarga();

  const filtrados = ASSINATURAS.filter((s) => {
    if (fPacote !== "all" && s.pacote !== fPacote) return false;
    if (fStatus !== "all" && s.status !== fStatus) return false;
    return true;
  });
  const ordenados = ordenar(filtrados, sort.col, sort.dir);
  const pageCount = Math.max(1, Math.ceil(ordenados.length / ASS_PAGE));
  const pagina = ordenados.slice((Math.min(page, pageCount) - 1) * ASS_PAGE, Math.min(page, pageCount) * ASS_PAGE);
  const receita = ASSINATURAS.filter(s => s.status === "aprovada").reduce((a, s) => a + s.preco, 0);
  const ativos = [fPacote, fStatus].filter(v => v !== "all").length;
  const limpar = () => { setFPacote("all"); setFStatus("all"); setPage(1); };
  const onSort = (col) => { setSort((s) => ({ col, dir: s.col === col && s.dir === "asc" ? "desc" : "asc" })); setPage(1); };
  const marcado = (id) => sel.includes(id);
  const todosDaPagina = pagina.length > 0 && pagina.every((s) => marcado(s.id));

  return (
    <div className="os-page sa-page" data-screen-label="Superadmin · Assinaturas">
      <PageHead titulo="Assinaturas" sub={`${ASSINATURAS.length} registros · ${BRL(receita)} aprovados · 1 pendente de baixa`}
        acoes={<>
          <button className="os-btn ghost" onClick={() => toast(`${ordenados.length} assinaturas exportadas em CSV`, "ok")}>Exportar</button>
          <button className="os-btn primary" onClick={() => setForm({ campo:"status", base: ASSINATURAS[0] })}>Lançar assinatura</button>
        </>}/>

      <div className="sa-kpis sa-kpis--4">
        <Kpi v={ASSINATURAS.filter(s=>s.status==="aprovada").length} l="Aprovadas" tone="ok"/>
        <Kpi v={ASSINATURAS.filter(s=>s.status==="trial").length} l="Em trial"/>
        <Kpi v={ASSINATURAS.filter(s=>s.status==="pendente").length} l="Pendentes" tone="warn"/>
        <Kpi v={ASSINATURAS.filter(s=>s.status==="vencida"||s.status==="cancelada").length} l="Vencidas ou canceladas" tone="danger"/>
      </div>

      <div className="sa-toolbar">
        <div className="sa-filters">
          <FilterDropdown label="Pacote" value={fPacote} onChange={(v) => { setFPacote(v); setPage(1); }} options={[{ id:"all", label:"Todos" }, ...PACOTES.map(p=>({ id:p.nome, label:p.nome }))]}/>
          <FilterDropdown label="Status" value={fStatus} onChange={(v) => { setFStatus(v); setPage(1); }} options={[
            { id:"all", label:"Todos" }, { id:"aprovada", label:"Aprovada" }, { id:"trial", label:"Trial" },
            { id:"pendente", label:"Pendente" }, { id:"vencida", label:"Vencida" }, { id:"cancelada", label:"Cancelada" }]}/>
          <FilterDropdown label="Criada em" value="all" onChange={() => {}} options={[
            { id:"all", label:"Qualquer período" }, { id:"7d", label:"Últimos 7 dias" }, { id:"30d", label:"Últimos 30 dias" }, { id:"mes", label:"Este mês" }]}/>
          {ativos > 0 && <button className="sa-clear" onClick={limpar}>Limpar</button>}
          <span className="sa-count">{ordenados.length} de {ASSINATURAS.length}</span>
        </div>
      </div>

      {carregando ? <SkelTable cols={7}/> : ordenados.length === 0 ? (
        <Vazio titulo="Nenhuma assinatura com esses filtros"
          texto="Esse cruzamento de pacote e status não tem registro. Volte um filtro e tente de novo."
          acao={<button className="os-btn ghost" onClick={limpar}>Limpar filtros</button>}/>
      ) : (
        <>
          <div className="os-table-wrap">
            <table className="os-table sa-table sa-table--ass">
              <thead><tr>
                <th className="sa-th-check">
                  <Check on={todosDaPagina} label="Selecionar a página"
                    onChange={(on) => setSel(on ? [...new Set([...sel, ...pagina.map(s => s.id)])] : sel.filter(id => !pagina.some(s => s.id === id)))}/>
                </th>
                <SortTh id="id" label="Assinatura" sort={sort} onSort={onSort}/>
                <SortTh id="negocio" label="Negócio" sort={sort} onSort={onSort}/>
                <SortTh id="status" label="Status" sort={sort} onSort={onSort}/>
                <SortTh id="inicio" label="Vigência" sort={sort} onSort={onSort}/>
                <SortTh id="preco" label="Valor" sort={sort} onSort={onSort} className="ta-r"/>
                <th>Pagamento</th>
                <th className="sa-th-act"></th>
              </tr></thead>
              <tbody>
                {pagina.map((s) => (
                  <tr key={s.id} className={marcado(s.id) ? "sel" : ""}>
                    <td className="sa-td-check">
                      <Check on={marcado(s.id)} onChange={(on) => setSel((x) => on ? [...x, s.id] : x.filter(i => i !== s.id))} label={`Selecionar ${s.id}`}/>
                    </td>
                    <td><div className="sa-biz"><b className="sa-mono sa-strong">{s.id}</b><small>criada {s.criado}</small></div></td>
                    <td><div className="sa-biz"><b className="sa-b-reg">{s.negocio}</b><small>{s.pacote}</small></div></td>
                    <td><SubBadge s={s.status}/></td>
                    <td>
                      <div className="sa-biz">
                        <b className="sa-mono sa-b-reg">{s.inicio} → {s.fim}</b>
                        <small>{s.trialFim !== "—" ? `trial até ${s.trialFim}` : "sem trial"}</small>
                      </div>
                    </td>
                    <td className="ta-r"><span className="sa-mono">{s.preco ? BRL(s.preco) : "—"}</span></td>
                    <td><div className="sa-biz"><b className="sa-b-reg">{s.via}</b><small className="sa-mono">{s.tx}</small></div></td>
                    <td className="sa-td-act">
                      <Kebab items={[
                        { label:"Ver negócio", action: () => window.__selectRoute?.("sa-negocios") },
                        { label:"Mudar status", action: () => setForm({ campo:"status", base:s }) },
                        { label:"Editar datas", action: () => setForm({ campo:"datas", base:s }) },
                        { sep:true },
                        { label:"Baixar comprovante", action: () => toast(`Comprovante de ${s.id} baixado`, "ok") },
                        { label:"Cancelar assinatura", danger:true, action: () => setConfirma({ s }) },
                      ]}/>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Paginacao page={Math.min(page, pageCount)} pageCount={pageCount} total={ordenados.length} pageSize={ASS_PAGE} onChange={setPage}/>
        </>
      )}

      <BulkBar count={sel.length} rotulo={{ s:"assinatura", p:"assinaturas" }} onClose={() => setSel([])}
        acoes={[
          { label:"Baixar comprovantes", action: () => toast(`${sel.length} comprovantes baixados`, "ok") },
          { label:"Exportar seleção", action: () => toast(`${sel.length} assinaturas exportadas em CSV`, "ok") },
        ]}/>

      <Confirm open={!!confirma} onClose={() => setConfirma(null)}
        titulo="Cancelar assinatura?"
        texto={`${confirma?.s?.id} de ${confirma?.s?.negocio} para de renovar no fim da vigência (${confirma?.s?.fim}). O acesso continua até lá.`}
        cta="Cancelar assinatura"
        onConfirm={() => toast(`${confirma.s.id} cancelada`, "warn")}/>

      {form && <AssinaturaForm campo={form.campo} base={form.base} onClose={() => setForm(null)}
        onSalvar={(v) => { setForm(null); toast(v.status ? `${form.base.id} agora está ${SUB_TONE[v.status].l.toLowerCase()}` : `Vigência de ${form.base.id} salva`, "ok"); }}/>}

      {toastNode}
    </div>
  );
}

// ── View: pacotes ──
function ViewPacotes() {
  const [form, setForm] = useState(null);
  const [toastNode, toast] = useToast();
  // sing/plur explícito — "1 local", "2 locais", "ilimitados" quando 0
  const lim = (n, sing, plur, ilim) => (n === 0 ? ilim : `${n} ${n === 1 ? sing : plur}`);
  // plural do intervalo — mapa explícito (mês→meses, ano→anos), nunca concatena "es"
  const PLURAL = { "mês":"meses", ano:"anos", semana:"semanas", dia:"dias" };
  const per = (n, u) => (n === 1 ? u : `${n} ${PLURAL[u] || u + "s"}`);
  return (
    <div className="os-page sa-page" data-screen-label="Superadmin · Pacotes">
      <PageHead titulo="Pacotes de assinatura" sub={`${PACOTES.length} pacotes · ${PACOTES.filter(p=>p.ativo).length} ativos · 1 privado`}
        acoes={<button className="os-btn primary" onClick={() => setForm({ modo:"novo" })}>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
          Novo pacote
        </button>}/>

      <div className="sa-pkgs">
        {PACOTES.map((p) => (
          <article key={p.id} className={"sa-pkg" + (p.ativo ? "" : " off")}>
            <header className="sa-pkg-h">
              <div className="sa-pkg-t">
                <h3>{p.nome}</h3>
                <div className="sa-pkg-tags">
                  {p.privado && <span className="sa-tag priv">privado</span>}
                  {p.avulso && <span className="sa-tag one">avulso</span>}
                  <span className={"sa-tag " + (p.ativo ? "on" : "off")}>{p.ativo ? "ativo" : "inativo"}</span>
                </div>
              </div>
              <Kebab items={[
                { label:"Editar pacote", action: () => setForm({ modo:"editar", base:p }) },
                { label:"Duplicar", action: () => setForm({ modo:"novo", base:{ ...p, nome: p.nome + " (cópia)", assinantes:0 } }) },
                { sep:true },
                { label: p.ativo ? "Desativar" : "Ativar", action: () => toast(p.ativo ? `${p.nome} desativado — quem já assinou continua` : `${p.nome} ativado`, p.ativo ? "warn" : "ok") },
                { label:"Excluir", danger:true, action: () => toast(p.assinantes > 0 ? `${p.nome} tem ${p.assinantes} assinantes — migre antes de excluir` : `${p.nome} excluído`, p.assinantes > 0 ? "danger" : "ok") },
              ]}/>
            </header>

            <div className="sa-pkg-preco">
              {p.preco === 0
                ? <><span className="sa-pkg-v">Grátis</span><small>por {per(p.intervalCount, p.intervalo)}</small></>
                : <><span className="sa-pkg-v">{BRL(p.preco)}</span><small>/ {per(p.intervalCount, p.intervalo)}</small></>}
            </div>

            <ul className="sa-pkg-lim">
              <li>{lim(p.locais, "local", "locais", "locais ilimitados")}</li>
              <li>{lim(p.usuarios, "usuário", "usuários", "usuários ilimitados")}</li>
              <li>{lim(p.produtos, "produto", "produtos", "produtos ilimitados")}</li>
              <li>{lim(p.faturas, "fatura", "faturas", "faturas ilimitadas")}</li>
              {p.trial > 0 && <li>{p.trial} dias de teste</li>}
            </ul>

            {(PACOTE_PERMS[p.id] || []).length > 0 && (
              <div className="sa-pkg-perms">
                {(PACOTE_PERMS[p.id] || []).map((k) => <span key={k} className="sa-perm">{PERM_LABEL[k]}</span>)}
              </div>
            )}

            <footer className="sa-pkg-f">
              <p>{p.desc}</p>
              <span className="sa-pkg-ass">{p.assinantes} {p.assinantes === 1 ? "assinante" : "assinantes"}</span>
            </footer>
          </article>
        ))}
      </div>

      {form && <PacoteForm modo={form.modo} base={form.base} onClose={() => setForm(null)}
        onSalvar={(f, modo) => { setForm(null); toast(modo === "editar" ? `${f.nome} salvo` : `${f.nome} criado`, "ok"); }}/>}

      {toastNode}
    </div>
  );
}

// ── View: comunicador ──
function ViewComunicador() {
  const [dest, setDest] = useState(["todos"]);
  const [assunto, setAssunto] = useState("");
  const [msg, setMsg] = useState("");
  const [agendar, setAgendar] = useState(false);
  const [quando, setQuando] = useState({ data:"19/08/2026", hora:"08:00" });
  const [previa, setPrevia] = useState(false);
  const [toastNode, toast] = useToast();
  const DSTextarea = ds().Textarea;
  const GRUPOS = [
    { id:"todos", label:"Todos os negócios", n:NEGOCIOS.length },
    { id:"ativas", label:"Com assinatura ativa", n:NEGOCIOS.filter(n=>n.sub==="ativa").length },
    { id:"trial", label:"Em trial", n:NEGOCIOS.filter(n=>n.sub==="trial").length },
    { id:"vencidas", label:"Assinatura vencida", n:NEGOCIOS.filter(n=>n.sub==="vencida").length },
    { id:"sem", label:"Sem assinatura", n:NEGOCIOS.filter(n=>n.sub==="sem").length },
    { id:"grafica", label:"Pacote Gráfica", n:PACOTES[1].assinantes },
    { id:"rede", label:"Pacote Rede", n:PACOTES[2].assinantes },
  ];
  const toggle = (id) => setDest((d) => d.includes(id) ? d.filter(x => x !== id) : [...d, id]);
  const alcance = GRUPOS.filter(g => dest.includes(g.id)).reduce((a, g) => Math.max(a, g.n), 0);

  return (
    <div className="os-page sa-page" data-screen-label="Superadmin · Comunicador">
      <PageHead titulo="Comunicador" sub="Aviso em massa para os negócios da plataforma — chega por e-mail e como notificação no app"
        acoes={<button className="os-btn ghost" onClick={() => window.__selectRoute?.("sa-negocios")}>Ver negócios</button>}/>

      <div className="sa-grid2 sa-grid2--form">
        <section className="sa-card">
          <header className="sa-card-h"><h2>Compor mensagem</h2><span className="sa-card-meta">alcance estimado: {alcance} negócios</span></header>
          <div className="sa-form">
            <div className="sa-field">
              <label>Destinatários</label>
              <div className="sa-chips">
                {GRUPOS.map((g) => (
                  <button key={g.id} className={"sa-chip" + (dest.includes(g.id) ? " on" : "")} onClick={() => toggle(g.id)}>
                    {g.label}<span className="sa-chip-n">{g.n}</span>
                  </button>
                ))}
              </div>
              <p className="sa-help">Escolha um ou mais grupos. Um negócio em dois grupos recebe uma vez só.</p>
            </div>
            <Campo label="Assunto" valor={assunto} onChange={setAssunto} placeholder="Manutenção programada — domingo 06:00 às 08:00"/>
            <div className="sa-campo">
              {DSTextarea
                ? <DSTextarea label="Mensagem" rows={7} value={msg} onChange={(e) => setMsg(e.target.value)}
                    help={`${msg.length} caracteres · o rodapé com o nome da plataforma entra automático.`}
                    placeholder="Escreva em português claro. Diga o que muda, quando, e o que o negócio precisa fazer."/>
                : <textarea rows="7" value={msg} onChange={(e) => setMsg(e.target.value)}></textarea>}
            </div>
            <Sw label="Agendar envio" sub="Sem agendamento, sai no momento em que você confirmar." on={agendar} onChange={setAgendar}/>
            {agendar && (
              <div className="sa-form-2">
                <Campo label="Data" valor={quando.data} onChange={(v) => setQuando({ ...quando, data:v })} mono placeholder="dd/mm/aaaa"/>
                <Campo label="Hora" valor={quando.hora} onChange={(v) => setQuando({ ...quando, hora:v })} mono placeholder="08:00"/>
              </div>
            )}

            <div className="sa-form-f">
              <button className="os-btn ghost" onClick={() => setPrevia(!previa)}>{previa ? "Fechar prévia" : "Ver prévia"}</button>
              <button className="os-btn ghost" onClick={() => toast("Teste enviado para wagner@wr2.com.br", "ok")}>Enviar teste para mim</button>
              <button className="os-btn primary" disabled={!assunto || !msg || dest.length === 0}
                onClick={() => toast(agendar ? `Agendado para ${quando.data} ${quando.hora} · ${alcance} negócios` : `Enviando para ${alcance} negócios`, "ok")}>
                {agendar ? "Agendar envio" : `Enviar para ${alcance} negócios`}
              </button>
            </div>

            {previa && (
              <div className="sa-previa">
                <span className="sa-previa-tag">prévia do e-mail</span>
                <div className="sa-previa-mail">
                  <header>
                    <b>{assunto || "Sem assunto"}</b>
                    <small>Office Impresso · nao-responda@oimpresso.com</small>
                  </header>
                  <p>{msg || "O corpo da mensagem aparece aqui do jeito que o dono do negócio recebe."}</p>
                  <footer>Você recebe este aviso porque usa o Office Impresso. Dúvidas? Responda esta conversa no app.</footer>
                </div>
              </div>
            )}
          </div>
        </section>

        <section className="sa-card">
          <header className="sa-card-h"><h2>Histórico de envios</h2><span className="sa-card-meta">{MENSAGENS.length} mensagens</span></header>
          <ul className="sa-hist">
            {MENSAGENS.map((m) => {
              const pct = Math.round(m.abriram / m.enviados * 100);
              return (
                <li key={m.id}>
                  <b>{m.assunto}</b>
                  <span>{m.res} · {m.enviados} destinatários</span>
                  <div className="sa-hist-ab">
                    <span className="sa-trilho"><i style={{ width: pct + "%" }}></i></span>
                    <em>{pct}% abriram <span className="sa-mono">({m.abriram})</span></em>
                  </div>
                  <small>{m.data} · por {m.autor}</small>
                </li>
              );
            })}
          </ul>
          <footer className="sa-card-f">Abertura conta um por negócio, não por pessoa — e só nos 14 dias seguintes ao envio.</footer>
        </section>
      </div>

      {toastNode}
    </div>
  );
}

// ── View: configurações ──
const CONFIG_SECOES = [
  { id:"app", titulo:"Aplicação", desc:"Nome exibido, moeda padrão e idioma da plataforma.", campos:[
    { l:"Nome da aplicação", v:"Office Impresso", tipo:"text" },
    { l:"Moeda padrão", v:"Real brasileiro (R$)", tipo:"select" },
    { l:"Idioma padrão", v:"Português (Brasil)", tipo:"select" },
    { l:"Cadastro próprio de negócios", v:true, tipo:"switch" },
  ]},
  { id:"smtp", titulo:"E-mail (SMTP)", desc:"Servidor de saída usado por avisos, boletos e comunicador.", campos:[
    { l:"Host", v:"smtp.oimpresso.com", tipo:"text" },
    { l:"Porta", v:"587", tipo:"text" },
    { l:"Remetente", v:"nao-responda@oimpresso.com", tipo:"text" },
    { l:"Criptografia TLS", v:true, tipo:"switch" },
  ]},
  { id:"gateways", titulo:"Gateways de pagamento", desc:"Meios de cobrança oferecidos na tela de assinatura.", campos:[
    { l:"Pix automático", v:true, tipo:"switch" },
    { l:"Boleto registrado", v:true, tipo:"switch" },
    { l:"Cartão (Stripe)", v:true, tipo:"switch" },
    { l:"Pagamento offline", v:false, tipo:"switch" },
  ]},
  { id:"pusher", titulo:"Notificações em tempo real", desc:"Credenciais Pusher para notificação no app.", campos:[
    { l:"App ID", v:"1842119", tipo:"mono" },
    { l:"Cluster", v:"sa1", tipo:"mono" },
    { l:"Notificações ativas", v:true, tipo:"switch" },
  ]},
  { id:"cron", titulo:"Rotinas e backup", desc:"Agenda de tarefas e cópia do banco.", campos:[
    { l:"Última execução do cron", v:"18/08/2026 20:00", tipo:"mono" },
    { l:"Backup diário", v:true, tipo:"switch" },
    { l:"Retenção de backup", v:"30 dias", tipo:"select" },
  ]},
  { id:"extra", titulo:"JS e CSS adicionais", desc:"Injeção de código nas telas do cliente — use com cuidado.", campos:[
    { l:"CSS extra", v:"", tipo:"mono", placeholder:"nenhum CSS extra injetado" },
    { l:"JS extra", v:"", tipo:"mono", placeholder:"nenhum JS extra injetado" },
  ]},
];

function ViewConfig() {
  const [sec, setSec] = useState("app");
  const atual = CONFIG_SECOES.find((s) => s.id === sec);
  const [vals, setVals] = useState(() => {
    const v = {};
    CONFIG_SECOES.forEach((s) => s.campos.forEach((c) => { v[c.l] = c.v; }));
    return v;
  });
  return (
    <div className="os-page sa-page" data-screen-label="Superadmin · Configurações">
      <PageHead titulo="Configurações do superadmin" sub="Vale para toda a plataforma — mexe em todos os negócios"
        acoes={<><button className="os-btn ghost">Descartar</button><button className="os-btn primary">Salvar alterações</button></>}/>

      <div className="sa-cfg">
        <nav className="sa-cfg-nav">
          {CONFIG_SECOES.map((s) => (
            <button key={s.id} className={sec === s.id ? "active" : ""} onClick={() => setSec(s.id)}>
              <b>{s.titulo}</b><small>{s.campos.length} ajustes</small>
            </button>
          ))}
        </nav>

        <section className="sa-card sa-cfg-body">
          <header className="sa-card-h"><h2>{atual.titulo}</h2><span className="sa-card-meta">{atual.desc}</span></header>
          <div className="sa-form">
            {atual.campos.map((c) => (
              c.tipo === "switch"
                ? <Sw key={c.l} label={c.l} on={!!vals[c.l]} onChange={(on) => setVals({ ...vals, [c.l]: on })}/>
                : c.tipo === "select"
                  ? <Sel key={c.l} label={c.l} valor={vals[c.l]} onChange={(v) => setVals({ ...vals, [c.l]: v })} opcoes={[c.v]}/>
                  : <Campo key={c.l} label={c.l} valor={vals[c.l]} mono={c.tipo === "mono"} placeholder={c.placeholder}
                      onChange={(v) => setVals({ ...vals, [c.l]: v })}/>
            ))}
          </div>
          {sec === "extra" && <div className="sa-warn-ds"><Nota tone="danger">Código injetado aqui roda na sessão de todos os clientes. Toda alteração fica no log de auditoria.</Nota></div>}
        </section>
      </div>
    </div>
  );
}

function SuperadminPage({ view = "visao" }) {
  if (view === "negocios") return <ViewNegocios />;
  if (view === "assinaturas") return <ViewAssinaturas />;
  if (view === "pacotes") return <ViewPacotes />;
  if (view === "comunicador") return <ViewComunicador />;
  if (view === "config") return <ViewConfig />;
  return <ViewVisao />;
}

window.SuperadminPage = SuperadminPage;
})();
