// clientes-page.jsx — Pessoas (Cliente · Fornecedor · Funcionário · Representante)
// 2026-05-25 — refactor: dispatcher por role. Cada entidade tem vocabulário próprio.
// Charter Cockpit V2 cream-and-navy preservado. Wave A-G features só onde fazem sentido.

const { useState: useStateC, useMemo: useMemoC, useEffect: useEffectC, useRef: useRefC } = React;

// ════════════════════════════════════════════════════════════════════
// HELPERS COMPARTILHADOS
// ════════════════════════════════════════════════════════════════════
function cliHash(s) {
  let h = 0; const str = String(s);
  for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) | 0;
  return Math.abs(h);
}

const CLI_AVATAR_PALETTE = [
  { bg: "oklch(0.62 0.13 255)", fg: "#fff" },
  { bg: "oklch(0.65 0.14 145)", fg: "#fff" },
  { bg: "oklch(0.68 0.13 60)",  fg: "#fff" },
  { bg: "oklch(0.62 0.14 25)",  fg: "#fff" },
  { bg: "oklch(0.60 0.10 280)", fg: "#fff" },
  { bg: "oklch(0.55 0.08 200)", fg: "#fff" },
  { bg: "oklch(0.58 0.12 165)", fg: "#fff" },
  { bg: "oklch(0.62 0.13 320)", fg: "#fff" },
];
function avatarColor(name) { return CLI_AVATAR_PALETTE[cliHash(name) % CLI_AVATAR_PALETTE.length]; }
function initialsOf(name) {
  return String(name).split(/\s+/).map((p) => p.replace(/[^\p{L}]/gu, "")).filter(Boolean)
    .slice(0, 2).map((p) => p[0]).join("").toUpperCase() || "?";
}
function fmtBRL(n) { return (n || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" }); }
function fmtBRLshort(n) {
  if (!n) return "R$ 0";
  if (n >= 1000) return "R$ " + (n / 1000).toFixed(1).replace(".", ",") + "k";
  return fmtBRL(n);
}
// "Hoje" do protótipo — o mesmo relógio do Financeiro (FIN_TODAY). Usar o relógio
// real aqui faria a tela chamar de "frio" um contato de três dias atrás.
function cliAgora() {
  const t = window.FIN_TODAY;
  return t instanceof Date ? t.getTime() : Date.now();
}
function daysSince(iso) {
  if (!iso || iso === "—") return null;
  const [y, m, d] = iso.split("-").map(Number);
  if (!y) return null;
  const then = new Date(y, (m || 1) - 1, d || 1);
  return Math.floor((cliAgora() - then.getTime()) / (1000 * 60 * 60 * 24));
}
function fmtDataBR(iso) {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ""));
  return m ? `${m[3]}/${m[2]}/${m[1]}` : (iso || "—");
}
function fmtAgo(iso) {
  const d = daysSince(iso);
  if (d == null) return "—";
  if (d < 7)  return `há ${d}d`;
  if (d < 60) return `há ${Math.floor(d / 7)}sem`;
  if (d < 365)return `há ${Math.floor(d / 30)}m`;
  return `há ${Math.floor(d / 365)}a`;
}

// ════════════════════════════════════════════════════════════════════
// PRIMITIVOS DE UI (usados por todas as views)
// ════════════════════════════════════════════════════════════════════

// FavStar — favorito persistido por role
function FavStar({ id, namespace, favs, toggle }) {
  const isFav = favs.has(id);
  return (
    <button
      className={`cli-fav-btn ${isFav ? "on" : ""}`}
      onClick={(e) => { e.stopPropagation(); toggle(id); }}
      aria-pressed={isFav} title={isFav ? "Remover dos favoritos" : "Marcar como favorito"}>
      {isFav ? <I.starFill size={14}/> : <I.star size={14}/>}
    </button>
  );
}

// RowKebab — menu ⋮ contextual por role
function RowKebab({ items, onOpenDetail }) {
  const [open, setOpen] = useStateC(false);
  const ref = useRefC(null);
  useEffectC(() => {
    if (!open) return;
    const close = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, [open]);
  return (
    <div className="cli-kebab-wrap" ref={ref}>
      <button className="cli-kebab-btn" onClick={(e) => { e.stopPropagation(); setOpen(!open); }}
        aria-expanded={open} title="Mais ações"><I.moreV size={14}/></button>
      {open && (
        <div className="cli-kebab-menu" onClick={(e) => e.stopPropagation()}>
          {items.filter(Boolean).map((it, i) => it.sep
            ? <div key={i} className="cli-kebab-sep"></div>
            : it.group
            ? <div key={i} className="cli-kebab-group">{it.group}</div>
            : <button key={i} className={(it.danger ? "danger" : "") + (it.disabled ? " off" : "")}
                disabled={it.disabled} title={it.disabled ? it.motivo : undefined}
                onClick={() => { setOpen(false); it.action === "open" ? onOpenDetail?.() : it.action?.(); }}>
                {it.icon && <it.icon size={12}/>} {it.label}
              </button>
          )}
        </div>
      )}
    </div>
  );
}

// FilterDropdown
function cliSlug(t) { return String(t).toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]+/g, "-"); }

function FilterDropdown({ label, value, options, onChange, multi }) {
  const [open, setOpen] = useStateC(false);
  const ref = useRefC(null);
  useEffectC(() => {
    if (!open) return;
    const close = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, [open]);
  const sel = multi ? (value || []) : value;
  const cur = multi ? null : options.find((o) => o.id === value);
  const isActive = multi ? sel.length > 0 : (value && value !== "all");
  const resumo = multi ? (sel.length === 1 ? sel[0] : `${sel.length} selecionadas`) : null;
  return (
    <div className="cli-fdrop-wrap" ref={ref}>
      <button className={`cli-fdrop-btn ${isActive ? "active" : ""}`} onClick={() => setOpen(!open)} aria-expanded={open}>
        <span className="cli-fdrop-l">{label}</span>
        {isActive && <span className="cli-fdrop-v">{multi ? resumo : cur && cur.label}</span>}
        <I.chevDown size={11}/>
      </button>
      {open && (
        <div className={"cli-fdrop-menu" + (options.length > 12 ? " cli-fdrop-menu-tall" : "")}>
          {options.map((o) => {
            const on = multi ? sel.includes(o.id) : value === o.id;
            return (
              <button key={o.id} className={on ? "active" : ""} aria-pressed={multi ? on : undefined}
                onClick={() => { if (multi) { onChange(on ? sel.filter((x) => x !== o.id) : [...sel, o.id]); } else { onChange(o.id); setOpen(false); } }}>
                {multi && <span className={"cli-fdrop-box" + (on ? " on" : "")} aria-hidden="true"/>}
                {o.label}
                {o.count != null && <span className="cli-fdrop-n">{o.count}</span>}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

// Avatar
function Avatar({ name, size = 32 }) {
  const c = avatarColor(name);
  return (
    <div className={`cli-avatar ${size >= 44 ? "lg" : ""}`} style={{ background: c.bg, color: c.fg, width: size, height: size, flexBasis: size, fontSize: size >= 44 ? 14 : 11 }}>
      {initialsOf(name)}
    </div>
  );
}

// Origens de crédito (definidas por [W] 2026-08-07).
const CLI_CREDITO_ORIGENS = ["Entrada / sinal pago", "Devolução de mercadoria", "Acordo comercial", "Bonificação"];

// O "hoje" do protótipo é o do Financeiro (FIN_TODAY) — carimbar com o relógio
// do sistema jogaria o lançamento meses fora da janela de período da tela.
function cliHoje() {
  const d = new Date(cliAgora());
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

// ── O outro lado do crédito: o lançamento no Financeiro ────────────
// Sinal pago é dinheiro que ENTROU (entrada liquidada, adiantamento do cliente).
// Devolução, acordo e bonificação NÃO movimentam caixa: viram obrigação da loja
// com o cliente — saída em aberto, que se encerra quando o crédito é consumido.
function cliCreditoRow(cliente, l) {
  const entrouDinheiro = l.origem === CLI_CREDITO_ORIGENS[0];
  const data = new Date(String(l.data) + "T12:00:00");
  const row = {
    id: l.finId,
    kind: entrouDinheiro ? "receivable" : "payable",
    desc: (entrouDinheiro ? "Adiantamento de cliente" : "Crédito de cliente") + " · " + l.origem + (l.obs ? " · " + l.obs : ""),
    descClean: entrouDinheiro ? "Adiantamento de cliente" : "Crédito de cliente",
    party: cliente.name,
    amount: l.valor,
    due: data,
    emissao: data,
    competencia: data,
    category: entrouDinheiro ? "Adiantamento de cliente" : "Crédito de cliente",
    channel: "—",
    invoice: "",
    paid_at: entrouDinheiro ? data : null,
    links: { cliente: cliente.name },
  };
  row.status = window.FIN_STATUS_FOR ? window.FIN_STATUS_FOR(row) : (entrouDinheiro ? "recebido" : "vencendo");
  return row;
}

// Reaplica no Financeiro os créditos guardados (sobrevive ao reload). Idempotente.
function cliSyncCreditosFinanceiro() {
  try {
    const map = JSON.parse(localStorage.getItem("oimpresso.clientes.creditos") || "{}");
    const rows = window.FIN_ROWS;
    if (!Array.isArray(rows)) return;
    const clientes = (window.OS_DATA && window.OS_DATA.OS_CLIENTS) || [];
    Object.entries(map).forEach(([id, lancs]) => {
      const cliente = clientes.find((c) => String(c.id) === String(id)) || { name: "Cliente " + id };
      (lancs || []).forEach((l) => {
        if (!l.finId || rows.some((r) => r.id === l.finId)) return;
        rows.unshift(cliCreditoRow(cliente, l));
      });
    });
  } catch (e) {}
}
setTimeout(cliSyncCreditosFinanceiro, 0);
window.cliSyncCreditosFinanceiro = cliSyncCreditosFinanceiro;

// SaldoCell / AmountCell
function SaldoNeg({ value, credito, title = "Em aberto" }) {
  if (!value && credito > 0) return <span className="cli-saldo-pos" title="Crédito a favor do cliente">{fmtBRL(credito)} a favor</span>;
  if (!value) return <span className="cli-cell-muted">—</span>;
  return (
    <span className="cli-saldo-dupla">
      <span className="cli-saldo-neg" title={title}>{fmtBRL(value)}</span>
      {credito > 0 && <span className="cli-saldo-pos-sub" title="Crédito a favor do cliente">{fmtBRL(credito)} a favor</span>}
    </span>);
}

// ════════════════════════════════════════════════════════════════════
// FAVORITES HOOK (namespace por role)
// ════════════════════════════════════════════════════════════════════
function useFavorites(namespace) {
  const key = `oimpresso.${namespace}.favorites`;
  const [favs, setFavs] = useStateC(() => {
    try { return new Set(JSON.parse(localStorage.getItem(key) || "[]")); }
    catch (_) { return new Set(); }
  });
  const toggle = (id) => setFavs((prev) => {
    const next = new Set(prev);
    if (next.has(id)) next.delete(id); else next.add(id);
    try { localStorage.setItem(key, JSON.stringify([...next])); } catch (_) {}
    return next;
  });
  return [favs, toggle];
}

// Papéis extras de um cadastro de Outros. ADR 0246: flags são ADITIVAS —
// virar cliente não tira de Outros, adiciona o papel. Por isso o total do
// diretório não muda quando alguém converte: é o mesmo cadastro.
const CLI_PAPEIS_KEY = "oimpresso.outros.papeis";
function cliPapeisLer() {
  try { return JSON.parse(localStorage.getItem(CLI_PAPEIS_KEY) || "{}"); } catch (e) { return {}; }
}
function usePapeisOutros() {
  const [map, setMap] = useStateC(cliPapeisLer);
  const alternar = (id, papel) => setMap((m) => {
    const atuais = m[id] || [];
    const tem = atuais.includes(papel);
    const next = { ...m, [id]: tem ? atuais.filter((p) => p !== papel) : [...atuais, papel] };
    if (next[id].length === 0) delete next[id];
    try { localStorage.setItem(CLI_PAPEIS_KEY, JSON.stringify(next)); } catch (e) {}
    return next;
  });
  return [map, alternar];
}

// Ativar/desativar cadastro — persistido por papel, igual aos favoritos.
function useStatusCadastro(namespace) {
  const key = `oimpresso.${namespace}.status`;
  const [map, setMap] = useStateC(() => {
    try { return JSON.parse(localStorage.getItem(key) || "{}"); } catch (e) { return {}; }
  });
  const set = (id, status) => setMap((m) => {
    const next = { ...m };
    if (status) next[id] = status; else delete next[id];
    try { localStorage.setItem(key, JSON.stringify(next)); } catch (e) {}
    return next;
  });
  return [map, set];
}

function useCreditos(namespace) {
  const key = `oimpresso.${namespace}.creditos`;
  const [map, setMap] = useStateC(() => {
    try { return JSON.parse(localStorage.getItem(key) || "{}"); } catch (e) { return {}; }
  });
  const lancar = (id, lanc) => setMap((m) => {
    const next = { ...m, [id]: [...(m[id] || []), lanc] };
    try { localStorage.setItem(key, JSON.stringify(next)); } catch (e) {}
    return next;
  });
  const remover = (id, idx) => setMap((m) => {
    const next = { ...m, [id]: (m[id] || []).filter((_, i) => i !== idx) };
    if (next[id].length === 0) delete next[id];
    try { localStorage.setItem(key, JSON.stringify(next)); } catch (e) {}
    return next;
  });
  return [map, lancar, remover];
}

// Lançar crédito — nasce aqui ou no Financeiro; o registro é o mesmo.
function CliCreditoModal({ client, onClose, onSalvar }) {
  const [valor, setValor] = useStateC("");
  const [origem, setOrigem] = useStateC(CLI_CREDITO_ORIGENS[0]);
  const [obs, setObs] = useStateC("");
  const ref = useRefC(null);
  useEffectC(() => { ref.current?.focus(); }, []);
  const n = Number(String(valor).replace(/\D/g, "")) / 100;
  const valido = n > 0;
  return (
    <div className="cli-modal-scrim" onClick={onClose}>
      <div className="cli-modal" role="dialog" aria-label="Lançar crédito" onClick={(e) => e.stopPropagation()}>
        <header>
          <b>Lançar crédito</b>
          <span>{client.name}</span>
        </header>
        <div className="cli-modal-body">
          <label className="cli-modal-f">
            <span>Valor</span>
            <input ref={ref} className="cli-modal-in" inputMode="numeric" value={valor ? fmtBRL(n) : ""}
              onChange={(e) => setValor(e.target.value.replace(/\D/g, ""))} placeholder="R$ 0,00"/>
          </label>
          <label className="cli-modal-f">
            <span>Origem</span>
            <select className="cli-modal-in" value={origem} onChange={(e) => setOrigem(e.target.value)}>
              {CLI_CREDITO_ORIGENS.map((o) => <option key={o} value={o}>{o}</option>)}
            </select>
          </label>
          <label className="cli-modal-f cli-modal-f-full">
            <span>Observação</span>
            <input className="cli-modal-in" value={obs} onChange={(e) => setObs(e.target.value)}
              placeholder="Ex.: devolução da lona da OS #4712"/>
          </label>
          <p className="cli-modal-nota">
            O crédito fica a favor do cliente e é abatido automaticamente na próxima venda — o vendedor pode recusar antes de fechar.
            {origem === CLI_CREDITO_ORIGENS[0]
              ? " No Financeiro entra como entrada liquidada (adiantamento do cliente) — o dinheiro entrou no caixa."
              : " Não movimenta caixa: no Financeiro entra como obrigação da loja com o cliente, em aberto até o crédito ser usado."}
          </p>
        </div>
        <footer>
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn primary" disabled={!valido}
            onClick={() => onSalvar({ valor: n, origem, obs, data: cliHoje() })}>
            Lançar crédito
          </button>
        </footer>
      </div>
    </div>
  );
}

// Atalho `/` pra focar busca
function useSearchShortcut(ref) {
  useEffectC(() => {
    const onKey = (e) => {
      if (e.key === "/" && document.activeElement?.tagName !== "INPUT") {
        e.preventDefault(); ref.current?.focus();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);
}

// ════════════════════════════════════════════════════════════════════
// PAGE SHELL — header + role tabs + view dispatch
// ════════════════════════════════════════════════════════════════════
const ROLE_DEFS = {
  other:          { icon: I.tag,       title: "Outros",         unit: "cadastrados", cta: "Novo cadastro" },
  customer:       { icon: I.users,     title: "Clientes",       unit: "cadastrados", cta: "Novo cliente" },
  supplier:       { icon: I.truck,     title: "Fornecedores",   unit: "cadastrados", cta: "Novo fornecedor" },
  employee:       { icon: I.briefcase, title: "Funcionários",   unit: "no quadro",   cta: "Novo funcionário" },
  representative: { icon: I.clients,   title: "Representantes", unit: "ativos",      cta: "Novo representante" },
  all:            { icon: I.list,      title: "Contatos",       unit: "cadastros",   cta: null },
};

function CliListPage() {
  const initialRole = window.__PESSOAS_ROLE_INITIAL || "customer";
  const [role, setRole] = useStateC(initialRole);
  const [resumo, setResumo] = useStateC(null);
  const [papeisOutros, alternarPapelOutro] = usePapeisOutros();

  // Counts globais pra tabs (fonte: data-people.jsx + OS_DATA)
  const base = window.PEOPLE_COUNTS || { customer: 0, supplier: 0, employee: 0, representative: 0, other: 0, all: 0 };
  const extras = Object.values(papeisOutros);
  // Papel aditivo: soma no papel novo e NÃO tira de Outros nem do total.
  const counts = { ...base,
    customer: base.customer + extras.filter((p) => p.includes("customer")).length,
    supplier: base.supplier + extras.filter((p) => p.includes("supplier")).length };
  const def = ROLE_DEFS[role] || ROLE_DEFS.customer;

  const ROLE_TABS = [
    { id: "all",            label: "Todos",          icon: I.list,      n: counts.all },
    { id: "customer",       label: "Clientes",       icon: I.users,     n: counts.customer },
    { id: "supplier",       label: "Fornecedores",   icon: I.truck,     n: counts.supplier },
    { id: "employee",       label: "Funcionários",   icon: I.briefcase, n: counts.employee },
    { id: "representative", label: "Representantes", icon: I.clients,   n: counts.representative },
    // ADR 0246 — cadastros sem CPF/CNPJ obrigatório (prospect, lead, legado WR).
    { id: "other",          label: "Outros",         icon: I.tag,       n: counts.other || 0 },
  ];

  return (
    <div className="os-page cli-page">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>{def.title}</h1>
          <p className="tabular">
            <strong>{(role === "all" ? counts.all : (counts[role] || 0)).toLocaleString("pt-BR")}</strong> {def.unit}
            {role === "customer" && resumo && <>
              {" · "}<strong>{resumo.ativos.toLocaleString("pt-BR")}</strong> ativos
              {resumo.comSaldo > 0 && <>{" · "}<strong className="cli-h-danger">{resumo.comSaldo.toLocaleString("pt-BR")} com saldo</strong></>}
            </>}
          </p>
        </div>
        <div className="os-page-h-r">
          <RowKebab items={[
            { group: "Dados" },
            { label: "Importar", icon: I.upload, action: () => window.__go?.("cli-import") },
            { label: "Exportar CSV", icon: I.upload, action: () => window.__cliExport?.() },
            { sep: true },
            { group: "Configuração" },
            { label: "Grupos de cliente", icon: I.tag, action: () => window.__go?.("cli-grupos") },
            { label: "Mapa de clientes", icon: I.mapPin, action: () => window.__go?.("cli-mapa") },
          ]}/>
          {def.cta && <button className="os-btn primary" onClick={() => window.__go?.("cli-novo")}><I.plus size={13}/> {def.cta}</button>}
        </div>
      </header>

      <nav className="cli-moduletopnav" aria-label="Tipo de pessoa">
        {ROLE_TABS.map((t) => (
          <button key={t.id}
            className={`cli-moduletopnav-tab ${role === t.id ? "active" : ""}`}
            onClick={() => setRole(t.id)}
            aria-current={role === t.id ? "page" : undefined}>
            <t.icon size={14}/>
            <span>{t.label}</span>
            <span className="cli-moduletopnav-n">{t.n}</span>
          </button>
        ))}
      </nav>

      {role === "customer"       && <CustomerView onResumo={setResumo} papeisOutros={papeisOutros}/>}
      {role === "supplier"       && <SupplierView papeisOutros={papeisOutros}/>}
      {role === "employee"       && <EmployeeView/>}
      {role === "representative" && <RepresentativeView/>}
      {role === "all"            && <AllView setRole={setRole} counts={counts} papeisOutros={papeisOutros}/>}
      {role === "other"          && <OtherView setRole={setRole} papeis={papeisOutros} alternarPapel={alternarPapelOutro}/>}
    </div>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: CLIENTE
// Vocabulário: Frescor · Saldo · OS · VIP · Tags
// ════════════════════════════════════════════════════════════════════
const CLI_UFS = ["SP","SP","SP","SP","RJ","MG","PR","RS","SC"];
const CLI_CITIES = {
  SP: ["São Paulo","Campinas","Santo André","Guarulhos","Osasco","Diadema","São Bernardo","Barueri"],
  RJ: ["Rio de Janeiro","Niterói","Petrópolis"], MG: ["Belo Horizonte","Contagem","Juiz de Fora"],
  PR: ["Curitiba","Londrina"], RS: ["Porto Alegre","Caxias do Sul"], SC: ["Florianópolis","Tubarão"],
};
// Tags canônicas de produção (_drawer/ClassificacaoTab.tsx::TAG_OPTIONS).
const CLI_TAGS_POOL = ["varejo","atacado","corporativo","evento","parceiro","agência","governo","reincidente"];
// Grupos de cliente = FK customer_group_id. Vínculo pelo ID: renomear não
// desassocia ninguém. Modelo lido de CustomerGroupController + ContactController:
//   name · price_calculation_type ('percentage' | 'selling_price_group')
//   amount (percentual de DESCONTO, só quando percentage)
//   selling_price_group_id (só quando selling_price_group)
// Não existe campo de descrição na tabela.
const CLI_SPG = [
  { id: "1", nome: "Tabela varejo" },
  { id: "2", nome: "Tabela atacado" },
  { id: "3", nome: "Tabela evento" },
];
const CLI_GRUPOS_SEED = [
  { id: "1", nome: "Padrão",   calc: "percentage",          amount: 0,  spg: null },
  { id: "2", nome: "Atacado",  calc: "percentage",          amount: 12, spg: null },
  { id: "3", nome: "Parceiro", calc: "percentage",          amount: 18, spg: null },
  { id: "4", nome: "Evento",   calc: "selling_price_group", amount: 0,  spg: "3" },
];
const CLI_GRUPOS_KEY = "oimpresso.clientes.grupos.v2";
function cliGruposLer() {
  try {
    const j = JSON.parse(localStorage.getItem(CLI_GRUPOS_KEY) || "null");
    return Array.isArray(j) && j.length ? j : CLI_GRUPOS_SEED;
  } catch (e) { return CLI_GRUPOS_SEED; }
}
function cliGruposGravar(lista) {
  try { localStorage.setItem(CLI_GRUPOS_KEY, JSON.stringify(lista)); } catch (e) {}
}
function cliGrupoNome(id) {
  const g = cliGruposLer().find((x) => x.id === String(id));
  return g ? g.nome : "—";
}
const CLI_UF_ALL = ["AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG","PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"];
const CLI_STATUS_LABEL = { ativo: "Ativo", inativo: "Inativo", bloqueado: "Bloqueado" };

function CliFrescorPill({ state, label }) {
  const st = state || "frio";
  return (
    <span className={`cli-frescor cli-frescor-${st}`}>
      <span className="cli-frescor-dot" aria-hidden="true"></span>
      <span className="cli-frescor-state">{st}</span>
      {label && <span className="cli-frescor-sep">·</span>}
      {label && <span className="cli-frescor-label">{label}</span>}
    </span>
  );
}

function deriveCli(c, stats) {
  const h = cliHash(c.id);
  const tipo = (h % 5 === 0) ? "PF" : "PJ";
  const principal = (c.addresses && c.addresses.find((a) => a.principal)) || (c.addresses && c.addresses[0]) || null;
  const uf = principal?.uf || CLI_UFS[h % CLI_UFS.length];
  const city = principal?.cidade || (CLI_CITIES[uf] ? CLI_CITIES[uf][(h >> 3) % CLI_CITIES[uf].length] : "São Paulo");
  let saldo = 0;
  if (stats.lateCount > 0) saldo = stats.totalValue * 0.38;
  else if (stats.openCount > 0 && h % 4 === 0) saldo = stats.totalValue * 0.22;
  let frescor;
  if (stats.count === 0) frescor = { state: "frio", label: "sem histórico" };
  else if (stats.lateCount > 0) { const m = 1 + (h % 11); frescor = { state: "distante", label: `há ${m}m` }; }
  else if (stats.openCount > 0) { const w = 1 + (h % 4); frescor = { state: w <= 2 ? "recente" : "fresc", label: w === 1 ? "há 1sem" : `há ${w}sem` }; }
  else { const m = 2 + (h % 6); frescor = { state: m >= 4 ? "frio" : "distante", label: `há ${m}m` }; }
  // Dias desde a última compra — deriva do estado de frescor (usado pelo filtro 15/30/90/180/365).
  const diasBase = { recente: 5, fresc: 20, distante: 70, frio: 240 }[frescor.state] || 240;
  frescor.dias = diasBase + (h % 40);
  const nTags = h % 3 === 0 ? 2 : (h % 5 === 0 ? 1 : 0);
  const tags = [];
  for (let i = 0; i < nTags; i++) {
    const t = CLI_TAGS_POOL[(h + i * 7) % CLI_TAGS_POOL.length];
    if (!tags.includes(t)) tags.push(t);
  }
  const isVip = stats.totalValue > 2000;
  if (isVip) tags.unshift("vip");
  // Status = enum de 3 valores (produção: contact_status ativo/inativo/bloqueado).
  const status = h % 13 === 0 ? "bloqueado" : (h % 9 === 0 ? "inativo" : "ativo");
  // Crédito a favor: sinal de entrada ou devolução que ainda não foi consumido.
  const creditoBase = h % 6 === 0 ? 120 + (h % 9) * 65 : 0;
  // Contribuinte de ICMS e inscrição estadual (determinístico, como status/tags).
  // Só PJ contribuinte precisa de IE — é o que torna o sinal de risco um sinal.
  const contribuinte = tipo === "PJ" && h % 3 !== 0;
  const ie = contribuinte && h % 4 !== 0 ? String(110000000 + (h % 89999999)).replace(/(\d{3})(\d{3})(\d{3})/, "$1.$2.$3") : "";
  // Walk-in (consumidor/fornecedor padrão do UPOS) — nunca pode ser excluído.
  const isDefault = /consumidor final|walk[- ]?in|cliente padr/i.test(c.name);
  const grupoId = String((h % 4) + 1);
  return { tipo, uf, city, saldo, frescor, tags, status, isVip, isDefault, contribuinte, ie, grupoId, creditoBase, credito: creditoBase, creditos: [], isNew: h % 7 === 0 };
}

function clientStats(client, osList) {
  const own = osList.filter((o) => o.client === client.name);
  const open = own.filter((o) => !["entregue","cancelado"].includes(o.stage));
  const late = own.filter((o) => /atrasada/i.test(o.deadline));
  const totalValue = own.reduce((s, o) => {
    const n = parseFloat((o.value || "0").replace(/[^\d,]/g, "").replace(",", "."));
    return s + (isNaN(n) ? 0 : n);
  }, 0);
  return { count: own.length, openCount: open.length, lateCount: late.length, totalValue, ownList: own };
}

function CustomerView({ onResumo, papeisOutros = {} }) {
  const OS_DATA = window.OS_DATA || {};
  const OS_LIST = OS_DATA.OS_LIST || [];
  // Cadastros de Outros que ganharam o papel de cliente entram aqui — mesmo
  // cadastro, papel a mais (ADR 0246), sem histórico de OS ainda.
  const OS_CLIENTS = useMemoC(() => {
    const base = OS_DATA.OS_CLIENTS || [];
    const vindos = (window.OTHERS || [])
      .filter((o) => (papeisOutros[o.id] || []).includes("customer"))
      .map((o) => ({ id: o.id, name: o.name, doc: o.doc || "—", contact: o.contact, phone: o.phone, lastOs: null, addresses: [] }));
    return [...base, ...vindos];
  }, [OS_DATA.OS_CLIENTS, papeisOutros]);

  const [q, setQ] = useStateC("");
  const [openId, setOpenId] = useStateC(null);
  const [abrirEm, setAbrirEm] = useStateC({ aba: "identificacao", sub: "ledger" });
  const [fStatus, setFStatus] = useStateC("all");
  const [fTipo, setFTipo] = useStateC("all");
  const [fUf, setFUf] = useStateC("all");
  const [fTags, setFTags] = useStateC("all");
  const [fSemCompra, setFSemCompra] = useStateC("all");
  const [fSaldo, setFSaldo] = useStateC("all");
  const [fTagList, setFTagList] = useStateC([]);
  const [fGrupo, setFGrupo] = useStateC(() => { const g = window.__CLI_GRUPO_INICIAL; delete window.__CLI_GRUPO_INICIAL; return g || "all"; });
  const [fNovos, setFNovos] = useStateC(false);
  const [cursor, setCursor] = useStateC(-1);
  const [ajuda, setAjuda] = useStateC(false);
  const [carregando, setCarregando] = useStateC(true);
  const [favs, toggleFav] = useFavorites("clientes");
  const [statusOv, setStatusOv] = useStatusCadastro("clientes");
  const [creditos, lancarCredito] = useCreditos("clientes");
  const [creditoDe, setCreditoDe] = useStateC(null);
  const [excluirAlvo, setExcluirAlvo] = useStateC(null);
  const [excluidos, setExcluidos] = useStateC(() => new Set());
  const [sort, setSort] = useStateC({ key: "name", dir: "asc" });
  const [page, setPage] = useStateC(1);
  const [porPagina, setPorPagina] = useStateC(25);
  const [toast, setToast] = useStateC(null);
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);
  useEffectC(() => { const t = setTimeout(() => setCarregando(false), 520); return () => clearTimeout(t); }, []);

  const enriched = useMemoC(() => OS_CLIENTS.map((c) => {
    const stats = clientStats(c, OS_LIST);
    const derived = deriveCli(c, stats);
    if (statusOv[c.id]) derived.status = statusOv[c.id];
    const lancados = creditos[c.id] || [];
    derived.creditos = lancados;
    derived.credito = derived.creditoBase + lancados.reduce((s2, l) => s2 + (l.valor || 0), 0);
    return { c, stats, derived };
  }), [OS_CLIENTS, OS_LIST, statusOv, creditos]);

  // Ativar/desativar: muda na hora, avisa e deixa desfazer.
  const alternarStatus = (c, atual) => {
    const novo = atual === "ativo" ? "inativo" : "ativo";
    const anterior = statusOv[c.id];
    setStatusOv(c.id, novo);
    setToast({
      msg: novo === "inativo"
        ? `${c.name} foi desativado — some das buscas de venda, mas o histórico fica.`
        : `${c.name} está ativo de novo.`,
      desfazer: () => { setStatusOv(c.id, anterior || null); setToast(null); },
    });
  };
  useEffectC(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 6000);
    return () => clearTimeout(t);
  }, [toast]);

  const kpis = useMemoC(() => ({
    total:       enriched.length,
    ativos:      enriched.filter((e) => e.derived.status === "ativo").length,
    vips:        enriched.filter((e) => e.derived.isVip).length,
    comSaldo:    enriched.filter((e) => e.derived.saldo > 0).length,
    comCredito:  enriched.filter((e) => e.derived.credito > 0).length,
    creditoTotal: enriched.reduce((s2, e) => s2 + e.derived.credito, 0),
    sem90d:      enriched.filter((e) => (e.derived?.frescor?.dias || 0) >= 90).length,
    novos:       enriched.filter((e) => e.derived.isNew).length,
    faturamento: enriched.reduce((s, e) => s + e.stats.totalValue, 0),
    saldoTotal:  enriched.reduce((s, e) => s + e.derived.saldo, 0),
  }), [enriched]);

  const ufList = useMemoC(() => {
    const m = {}; enriched.forEach((e) => { m[e.derived.uf] = (m[e.derived.uf] || 0) + 1; });
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, [enriched]);

  const filtered = enriched.filter(({ c, stats, derived }) => {
    if (excluidos.has(c.id)) return false;
    if (fStatus !== "all" && derived.status !== fStatus) return false;
    if (fTipo !== "all" && derived.tipo !== fTipo) return false;
    if (fUf !== "all" && derived.uf !== fUf) return false;
    if (fTagList.length > 0 && !fTagList.every((t) => derived.tags.includes(t))) return false;
    if (fGrupo !== "all" && derived.grupoId !== fGrupo) return false;
    if (fSaldo === "negativo" && derived.saldo <= 0) return false;
    if (fSaldo === "credito" && derived.credito <= 0) return false;
    if (fSaldo === "zero" && (derived.saldo > 0 || derived.credito > 0)) return false;
    if (fSemCompra !== "all" && (derived?.frescor?.dias || 0) < Number(fSemCompra)) return false;
    if (fNovos && !derived.isNew) return false;
    if (q && !`${c.name} ${c.doc} ${c.contact} ${c.phone} ${derived.city}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const ordenar = (key) => setSort((s2) => ({ key, dir: s2.key === key && s2.dir === "asc" ? "desc" : "asc" }));
  const activeF = [fStatus, fTipo, fUf, fSemCompra, fSaldo, fGrupo].filter((v) => v && v !== "all").length
    + (fTagList.length > 0 ? 1 : 0) + (fNovos ? 1 : 0);
  const limpar = () => { setFStatus("all"); setFTipo("all"); setFUf("all"); setFTagList([]); setFSemCompra("all"); setFSaldo("all"); setFNovos(false); setFGrupo("all"); };
  const open = openId ? enriched.find((e) => e.c.id === openId) : null;

  // Ordenação por coluna (produção: name · last_os_at · valor_aberto · total_os).
  const ordenados = useMemoC(() => {
    const mul = sort.dir === "asc" ? 1 : -1;
    const val = ({ c, stats, derived }) => ({
      name: c.name.toLowerCase(),
      frescor: derived?.frescor?.dias || 0,
      saldo: derived.saldo - derived.credito,
      os: stats.count,
    })[sort.key];
    return filtered.slice().sort((a, b) => {
      const va = val(a), vb = val(b);
      return va === vb ? 0 : (va > vb ? mul : -mul);
    });
  }, [filtered, sort]);

  const totalPag = Math.max(1, Math.ceil(ordenados.length / porPagina));
  const pagina = Math.min(page, totalPag);
  const visiveis = ordenados.slice((pagina - 1) * porPagina, pagina * porPagina);
  const de = ordenados.length === 0 ? 0 : (pagina - 1) * porPagina + 1;
  const ate = Math.min(pagina * porPagina, ordenados.length);
  useEffectC(() => { setPage(1); setCursor(-1); }, [q, fStatus, fTipo, fUf, fSemCompra, fSaldo, fNovos, fGrupo, fTagList.length, porPagina]);

  // KPI-filtro: clique aplica (substitutivo), 2º clique desliga.
  const kpiOn = {
    ativos: fStatus === "ativo" && fSemCompra === "all",
    vips: fTagList.length === 1 && fTagList[0] === "vip",
    saldo: fSaldo === "negativo",
    sem90: fSemCompra === "90",
    novos: fNovos };
  const kpiFiltro = (k) => () => {
    const ligado = kpiOn[k];
    limpar();
    if (ligado) return;
    if (k === "ativos") setFStatus("ativo");
    if (k === "vips") setFTagList(["vip"]);
    if (k === "saldo") setFSaldo("negativo");
    if (k === "sem90") { setFStatus("ativo"); setFSemCompra("90"); }
    if (k === "novos") setFNovos(true);
  };

  useEffectC(() => { onResumo?.({ ativos: kpis.ativos, comSaldo: kpis.comSaldo }); }, [kpis.ativos, kpis.comSaldo]);

  // Exportar CSV — respeita filtro e ordem da tela.
  const exportar = () => {
    const head = ["Cliente","Tipo","Documento","Cidade","UF","Status","Grupo","Saldo","OS","Tags","Última OS"];
    const linhas = filtered.map(({ c, stats, derived }) => [c.name, derived.tipo, c.doc, derived.city, derived.uf,
      CLI_STATUS_LABEL[derived.status], cliGrupoNome(derived.grupoId), derived.saldo ? derived.saldo.toFixed(2).replace(".", ",") : "0,00",
      stats.count, derived.tags.join(" · "), c.lastOs || ""]);
    const csv = [head, ...linhas].map((r) => r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(";")).join("\n");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8" }));
    a.download = `clientes-${cliHoje()}.csv`;
    a.click(); URL.revokeObjectURL(a.href);
  };
  useEffectC(() => { window.__cliExport = exportar; return () => { if (window.__cliExport === exportar) delete window.__cliExport; }; });

  // Atalhos KB-9.75 Slice A: J/K navega · Enter abre · ? mostra a lista.
  useEffectC(() => {
    const onKey = (e) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || "")) return;
      if (openId) return;
      const k = e.key;
      if (k === "?") { e.preventDefault(); setAjuda((v) => !v); return; }
      if (k === "Escape" && ajuda) { setAjuda(false); return; }
      if (k === "j" || k === "J" || k === "ArrowDown") { e.preventDefault(); setCursor((i) => Math.min(filtered.length - 1, i + 1)); }
      if (k === "k" || k === "K" || k === "ArrowUp") { e.preventDefault(); setCursor((i) => Math.max(0, i - 1)); }
      if (k === "Enter" && cursor >= 0 && filtered[cursor]) { e.preventDefault(); setOpenId(filtered[cursor].c.id); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [filtered.length, cursor, openId, ajuda]);

  return (
    <>
      <div className="cli-kpihero">
        <KpiHero l="Clientes ativos" v={kpis.ativos} s={kpiOn.ativos ? "filtro ligado — clique pra desligar" : "cadastro ativo"} icon={I.users} tone="primary" onClick={kpiFiltro("ativos")} on={kpiOn.ativos}/>
        <KpiHero l="VIPs" v={kpis.vips} s={kpiOn.vips ? "filtro ligado" : "prioridade total"} icon={I.starFill} tone="amber" onClick={kpiFiltro("vips")} on={kpiOn.vips}/>
        <KpiHero l="Com saldo" v={kpis.comSaldo} aside={kpis.saldoTotal > 0 ? fmtBRLshort(kpis.saldoTotal) : null} s={kpiOn.saldo ? "filtro ligado" : "inadimplência"} icon={I.cash} tone="rose" onClick={kpiFiltro("saldo")} on={kpiOn.saldo}/>
        <KpiHero l="Sem compra 90d" v={kpis.sem90d} s={kpiOn.sem90 ? "filtro ligado" : "risco churn"} icon={I.clock} tone="emerald" onClick={kpiFiltro("sem90")} on={kpiOn.sem90}/>
        <KpiHero l="Novos este mês" v={kpis.novos} s={kpiOn.novos ? "filtro ligado" : "desde dia 1"} icon={I.user} tone="violet" onClick={kpiFiltro("novos")} on={kpiOn.novos}/>
        <KpiHero l="Faturamento" v={fmtBRLshort(kpis.faturamento)} s={<>hoje · <span className="cli-kpihero-delta">+12%</span> vs ontem</>} icon={I.chart} dark/>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        unidade="cliente" placeholder="Buscar nome, CNPJ/CPF, contato, telefone, cidade…"
        filtersCount={activeF} onClear={limpar}
        resultCount={filtered.length}>
        <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
          { id:"all", label:"Todos" },
          { id:"ativo", label:"Ativo", count: kpis.ativos },
          { id:"inativo", label:"Inativo", count: enriched.filter((e) => e.derived.status === "inativo").length },
          { id:"bloqueado", label:"Bloqueado", count: enriched.filter((e) => e.derived.status === "bloqueado").length },
        ]}/>
        <FilterDropdown label="Tipo" value={fTipo} onChange={setFTipo} options={[
          { id:"all", label:"Todos" }, { id:"PJ", label:"Pessoa jurídica" }, { id:"PF", label:"Pessoa física" },
        ]}/>
        <FilterDropdown label="UF" value={fUf} onChange={setFUf} options={[
          { id:"all", label:"Todas" },
          ...CLI_UF_ALL.map((u) => ({ id: u, label: u, count: (ufList.find(([x]) => x === u) || [u, 0])[1] })),
        ]}/>
        <FilterDropdown label="Tags" multi value={fTagList} onChange={setFTagList} options={
          ["vip", ...CLI_TAGS_POOL].map((t) => ({ id: t, label: t, count: enriched.filter((e) => e.derived.tags.includes(t)).length }))
        }/>
        <FilterDropdown label="Sem compra há" value={fSemCompra} onChange={setFSemCompra} options={[
          { id:"all", label:"Sem filtro" },
          ...[15, 30, 90, 180, 365].map((d) => ({ id: String(d), label: `${d} dias`, count: enriched.filter((e) => (e.derived?.frescor?.dias || 0) >= d).length })),
        ]}/>
        <FilterDropdown label="Grupo" value={fGrupo} onChange={setFGrupo} options={[
          { id:"all", label:"Todos" },
          ...cliGruposLer().map((g) => ({ id: g.id, label: g.nome, count: enriched.filter((e) => e.derived.grupoId === g.id).length })),
        ]}/>
        <FilterDropdown label="Saldo" value={fSaldo} onChange={setFSaldo} options={[
          { id:"all", label:"Todos" },
          { id:"negativo", label:"Em aberto", count: kpis.comSaldo },
          { id:"credito", label:"Com crédito a favor", count: kpis.comCredito },
          { id:"zero", label:"Zerado" },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <ThSort k="name" sort={sort} onSort={ordenar}>Cliente</ThSort>
            <th>Tipo</th><th>Documento</th><th>Cidade/UF</th>
            <ThSort k="frescor" sort={sort} onSort={ordenar}>Frescor</ThSort>
            <ThSort k="saldo" sort={sort} onSort={ordenar} num>Saldo</ThSort>
            <ThSort k="os" sort={sort} onSort={ordenar} num>OS</ThSort>
            <th>Tags</th><th>Última OS</th><th></th><th></th>
          </tr></thead>
          <tbody>
            {carregando && [0,1,2,3,4,5].map((i) => (
              <tr key={"sk" + i} className="cli-row cli-row-skel">
                <td colSpan={11}><span className="cli-skel"/></td>
              </tr>
            ))}
            {!carregando && visiveis.map(({ c, stats, derived }, i) => (
              <tr key={c.id} className={"cli-row" + (i === cursor ? " cli-row-cursor" : "")}
                onClick={() => { setCursor(i); setAbrirEm({ aba: "identificacao", sub: "ledger" }); setOpenId(c.id); }}>
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={c.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">
                        {c.name}
                        {derived.isVip && <span className="cli-vip">VIP</span>}
                        {derived.isNew && <span className="cli-new">Novo</span>}
                        {derived.status !== "ativo" && <span className={"cli-cadastro-status cli-cadastro-status--" + derived.status}>{CLI_STATUS_LABEL[derived.status]}</span>}
                      </div>
                      <div className="cli-name-sub"><I.phone size={10}/><span>{c.phone}</span></div>
                    </div>
                  </div>
                </td>
                <td><span className={`cli-tipo cli-tipo-${derived.tipo.toLowerCase()}`}>{derived.tipo}</span></td>
                <td><span className="cli-doc-mono">{c.doc}</span></td>
                <td className="cli-td-city">
                  <div className="cli-city-line"><I.mapPin size={10}/><span>{derived.city}</span></div>
                  <div className="cli-city-uf">{derived.uf}</div>
                </td>
                <td><CliFrescorPill state={derived?.frescor?.state} label={derived?.frescor?.label}/></td>
                <td className="num"><SaldoNeg value={derived.saldo} credito={derived.credito}/></td>
                <td className="num">{stats.count || <span className="cli-cell-muted">0</span>}</td>
                <td className="cli-td-tags">
                  {derived.tags.length === 0 && <span className="cli-cell-muted">—</span>}
                  {derived.tags.slice(0, 2).map((t, i) => (
                    <span key={i} className={`cli-tag cli-tag-${cliSlug(t)}`}>{t}</span>
                  ))}
                  {derived.tags.length > 2 && <span className="cli-tag-more">+{derived.tags.length - 2}</span>}
                </td>
                <td>{c.lastOs ? <a className="cli-lastos-link" onClick={(e) => e.stopPropagation()}>{c.lastOs}</a> : <span className="cli-cell-muted">—</span>}</td>
                <td className="cli-td-fav"><FavStar id={c.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab onOpenDetail={() => setOpenId(c.id)} items={[
                  { label:"Ver detalhes", icon: I.eye || I.users, action: () => { setAbrirEm({ aba: "identificacao", sub: "ledger" }); setOpenId(c.id); } },
                  { label:"Editar", icon: I.pencil, action: () => { setAbrirEm({ aba: "identificacao", sub: "ledger" }); setOpenId(c.id); } },
                  { label:"Extrato", icon: I.cash, action: () => { setAbrirEm({ aba: "operacoes", sub: "ledger" }); setOpenId(c.id); } },
                  { sep: true },
                  { label:"Lançar crédito", icon: I.cash, action: () => setCreditoDe(c) },
                  derived.status === "ativo"
                    ? { label:"Desativar cadastro", icon: I.close, action: () => alternarStatus(c, derived.status) }
                    : { label:"Ativar cadastro", icon: I.check || I.plus, action: () => alternarStatus(c, derived.status) },
                  // Walk-in (consumidor final) nunca é excluído — regra do backend.
                  !derived.isDefault && { sep: true },
                  !derived.isDefault && { label:"Excluir", icon: I.close, danger: true, action: () => setExcluirAlvo({ c, stats }) },
                ]}/></td>
              </tr>
            ))}
            {!carregando && ordenados.length === 0 && (
              <tr><td colSpan={11} className="os-empty">
                {activeF > 0 || q
                  ? <>Nenhum cliente com esses filtros. <button className="cli-empty-a" onClick={() => { limpar(); setQ(""); }}>Limpar filtros</button></>
                  : "Nenhum cliente cadastrado ainda."}
              </td></tr>
            )}
          </tbody>
        </table>
      </div>

      {!carregando && ordenados.length > 0 && (
        <div className="cli-pag">
          <div className="cli-pag-l">
            <button className="cli-pag-atalhos" onClick={() => setAjuda(true)} title="Atalhos de teclado">
              <kbd>?</kbd> atalhos
            </button>
            <span>Mostrando {de}–{ate} de {ordenados.length.toLocaleString("pt-BR")}</span>
          </div>
          <div className="cli-pag-r">
            <label className="cli-pag-pp">Por página
              <select value={porPagina} onChange={(e) => setPorPagina(Number(e.target.value))}>
                {[25, 50, 100].map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
            </label>
            <div className="cli-pag-nav">
              <button onClick={() => setPage(1)} disabled={pagina === 1} aria-label="Primeira página">«</button>
              <button onClick={() => setPage(pagina - 1)} disabled={pagina === 1} aria-label="Página anterior">‹</button>
              <span className="tabular">{pagina} <em>/ {totalPag}</em></span>
              <button onClick={() => setPage(pagina + 1)} disabled={pagina === totalPag} aria-label="Próxima página">›</button>
              <button onClick={() => setPage(totalPag)} disabled={pagina === totalPag} aria-label="Última página">»</button>
            </div>
          </div>
        </div>
      )}

      {excluirAlvo && (
        <div className="cli-modal-scrim" onClick={() => setExcluirAlvo(null)}>
          <div className="cli-modal cli-modal-sm" role="alertdialog" aria-label="Excluir contato" onClick={(e) => e.stopPropagation()}>
            <header><b>Excluir contato?</b><span>{excluirAlvo.c.name}</span></header>
            <div className="cli-modal-body cli-modal-body-1col">
              <p className="cli-modal-nota">
                {excluirAlvo.stats.count > 0
                  ? <>Este contato tem <strong>{excluirAlvo.stats.count} OS</strong> no histórico. Com venda, compra ou OS lançada, o sistema não deixa excluir — desative o cadastro no lugar.</>
                  : <>O cadastro sai das listas e das buscas, mas continua recuperável e fica registrado na auditoria.</>}
              </p>
            </div>
            <footer>
              <button className="os-btn ghost" onClick={() => setExcluirAlvo(null)}>Cancelar</button>
              {excluirAlvo.stats.count > 0
                ? <button className="os-btn" onClick={() => { alternarStatus(excluirAlvo.c, "ativo"); setExcluirAlvo(null); }}>Desativar em vez de excluir</button>
                : <button className="os-btn danger" onClick={() => {
                    const alvo = excluirAlvo.c;
                    setExcluidos((sx) => new Set([...sx, alvo.id]));
                    setToast({ msg: `${alvo.name} foi excluído.`, desfazer: () => { setExcluidos((sx) => { const n = new Set(sx); n.delete(alvo.id); return n; }); setToast(null); } });
                    setExcluirAlvo(null);
                  }}>Excluir contato</button>}
            </footer>
          </div>
        </div>
      )}

      {creditoDe && (
        <CliCreditoModal client={creditoDe} onClose={() => setCreditoDe(null)}
          onSalvar={(l) => {
            const entrouDinheiro = l.origem === CLI_CREDITO_ORIGENS[0];
            const lanc = { ...l, finId: (entrouDinheiro ? "R" : "P") + "-CR" + Date.now().toString().slice(-6) };
            lancarCredito(creditoDe.id, lanc);
            try { if (Array.isArray(window.FIN_ROWS)) window.FIN_ROWS.unshift(cliCreditoRow(creditoDe, lanc)); } catch (e) {}
            setToast({ msg: `${fmtBRL(l.valor)} de crédito lançado pra ${creditoDe.name} · ${l.origem}. No Financeiro entrou como ${lanc.finId} — ${entrouDinheiro ? "entrada liquidada" : "obrigação com o cliente, em aberto"}.` });
            setCreditoDe(null);
          }}/>
      )}

      {toast && (
        <div className="cli-toast" role="status">
          <span>{toast.msg}</span>
          {toast.desfazer && <button onClick={toast.desfazer}>Desfazer</button>}
          <button className="cli-toast-x" onClick={() => setToast(null)} aria-label="Fechar">✕</button>
        </div>
      )}

      {ajuda && (
        <div className="cli-ajuda-scrim" onClick={() => setAjuda(false)}>
          <div className="cli-ajuda" onClick={(e) => e.stopPropagation()}>
            <b>Atalhos</b>
            <dl>
              <div><dt><kbd>/</kbd></dt><dd>focar a busca</dd></div>
              <div><dt><kbd>J</kbd> <kbd>K</kbd></dt><dd>descer e subir na lista</dd></div>
              <div><dt><kbd>↵</kbd></dt><dd>abrir o cliente marcado</dd></div>
              <div><dt><kbd>⌘K</kbd></dt><dd>busca global do sistema</dd></div>
              <div><dt><kbd>?</kbd></dt><dd>mostrar ou esconder esta lista</dd></div>
            </dl>
          </div>
        </div>
      )}

      {open && <window.ClienteDrawer760 client={open.c} stats={open.stats} derived={open.derived} osList={OS_LIST}
        abaInicial={abrirEm.aba} subInicial={abrirEm.sub} onClose={() => setOpenId(null)}/>}
    </>
  );
}

// ── Endereços do cliente (cards + adicionar inline) ──
function CliEnderecoSection({ client }) {
  const [addrs, setAddrs] = useStateC(() => (client.addresses || []).map((a) => ({ ...a })));
  const [adding, setAdding] = useStateC(false);
  const [form, setForm] = useStateC({ label: "", cep: "", logradouro: "", numero: "", complemento: "", bairro: "", cidade: "", uf: "SP" });
  const [copied, setCopied] = useStateC(null);

  const copyAddr = (a) => {
    const txt = `${a.logradouro}, ${a.numero}${a.complemento ? " · " + a.complemento : ""} — ${a.bairro}, ${a.cidade}/${a.uf} — CEP ${a.cep}`;
    try { navigator.clipboard?.writeText(txt); } catch (_) {}
    setCopied(a.id); setTimeout(() => setCopied((c) => (c === a.id ? null : c)), 1600);
  };
  const setEntrega = (id) => setAddrs((prev) => prev.map((a) => ({ ...a, entrega: a.id === id })));
  const saveNew = () => {
    if (!form.logradouro.trim() || !form.cidade.trim()) return;
    const id = "ad-new-" + Date.now();
    setAddrs((prev) => [...prev, { ...form, id, label: form.label.trim() || "Adicional", principal: prev.length === 0, entrega: prev.length === 0 }]);
    setAdding(false);
    setForm({ label: "", cep: "", logradouro: "", numero: "", complemento: "", bairro: "", cidade: "", uf: "SP" });
  };

  return (
    <div className="cli-section">
      <div className="cli-section-title cli-addr-head">
        Endereços
        <button className="cli-addr-add" onClick={() => setAdding((v) => !v)}>
          <I.plus size={12}/> Adicionar
        </button>
      </div>

      {addrs.length === 0 && !adding && (
        <div className="cli-addr-empty"><I.mapPin size={14}/> Nenhum endereço cadastrado. Adicione para usar na entrega de vendas.</div>
      )}

      <div className="cli-addr-list">
        {addrs.map((a) => (
          <div key={a.id} className={`cli-addr-card${a.entrega ? " is-entrega" : ""}`}>
            <div className="cli-addr-top">
              <div className="cli-addr-tags">
                <span className="cli-addr-label"><I.mapPin size={11}/> {a.label}</span>
                {a.principal && <span className="cli-addr-flag principal">Cadastro</span>}
                {a.entrega
                  ? <span className="cli-addr-flag entrega">Entrega padrão</span>
                  : <button className="cli-addr-setentrega" onClick={() => setEntrega(a.id)}>Usar p/ entrega</button>}
              </div>
              <button className="cli-addr-copy" onClick={() => copyAddr(a)} title="Copiar endereço">
                {copied === a.id ? <I.check size={13}/> : <I.copy size={13}/>}
              </button>
            </div>
            <div className="cli-addr-line">{a.logradouro}, <strong>{a.numero}</strong>{a.complemento ? ` · ${a.complemento}` : ""}</div>
            <div className="cli-addr-sub">
              <span>{a.bairro}</span><span className="cli-addr-dot">·</span>
              <span>{a.cidade}/{a.uf}</span><span className="cli-addr-dot">·</span>
              <span className="cli-addr-cep">CEP {a.cep}</span>
            </div>
          </div>
        ))}
      </div>

      {adding && (
        <div className="cli-addr-form">
          <div className="cli-addr-frow">
            <label className="cli-addr-f cep"><span>CEP</span><input value={form.cep} onChange={(e) => setForm({ ...form, cep: e.target.value })} placeholder="00000-000"/></label>
            <label className="cli-addr-f rotulo"><span>Rótulo</span><input value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} placeholder="Entrega, Filial…"/></label>
          </div>
          <div className="cli-addr-frow">
            <label className="cli-addr-f log"><span>Logradouro</span><input value={form.logradouro} onChange={(e) => setForm({ ...form, logradouro: e.target.value })} placeholder="Rua / Avenida"/></label>
            <label className="cli-addr-f num"><span>Número</span><input value={form.numero} onChange={(e) => setForm({ ...form, numero: e.target.value })} placeholder="nº"/></label>
          </div>
          <div className="cli-addr-frow">
            <label className="cli-addr-f comp"><span>Complemento</span><input value={form.complemento} onChange={(e) => setForm({ ...form, complemento: e.target.value })} placeholder="Sala, bloco…"/></label>
            <label className="cli-addr-f bairro"><span>Bairro</span><input value={form.bairro} onChange={(e) => setForm({ ...form, bairro: e.target.value })} placeholder="Bairro"/></label>
          </div>
          <div className="cli-addr-frow">
            <label className="cli-addr-f cidade"><span>Cidade</span><input value={form.cidade} onChange={(e) => setForm({ ...form, cidade: e.target.value })} placeholder="Cidade"/></label>
            <label className="cli-addr-f uf"><span>UF</span><input value={form.uf} maxLength={2} onChange={(e) => setForm({ ...form, uf: e.target.value.toUpperCase() })} placeholder="UF"/></label>
            <div className="cli-addr-fact">
              <button className="cli-addr-cancel" onClick={() => setAdding(false)}>Cancelar</button>
              <button className="cli-addr-save" onClick={saveNew}><I.check size={12}/> Salvar</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function ClienteDetailDrawer({ client, stats, derived, osList, onClose }) {
  const own = stats.ownList.slice().sort((a, b) => parseInt(b.id) - parseInt(a.id));
  return (
    <div className="os-drawer-back" onClick={onClose}>
      <div className="os-drawer wide cli-drawer" onClick={(e) => e.stopPropagation()}>
        <div className="os-drawer-head">
          <div className="cli-head">
            <Avatar name={client.name} size={44}/>
            <div>
              <div className="cli-head-name">{client.name}{derived.isVip && <span className="cli-vip">VIP</span>}</div>
              <div className="cli-head-doc">
                <span className={`cli-tipo cli-tipo-${derived.tipo.toLowerCase()}`}>{derived.tipo}</span>
                <span>{client.doc}</span><span className="cli-head-sep">·</span>
                <span>{derived.city}/{derived.uf}</span>
              </div>
            </div>
          </div>
          <button className="os-icon-btn" onClick={onClose}><I.close size={16}/></button>
        </div>
        <div className="os-drawer-body">
          <div className="cli-kpis">
            <div className="cli-kpi"><div className="cli-kpi-v">{stats.count}</div><div className="cli-kpi-l">OS no total</div></div>
            <div className="cli-kpi"><div className="cli-kpi-v">{stats.openCount}</div><div className="cli-kpi-l">Em aberto</div></div>
            <div className={`cli-kpi ${stats.lateCount > 0 ? "danger" : ""}`}><div className="cli-kpi-v">{stats.lateCount}</div><div className="cli-kpi-l">Atrasadas</div></div>
            <div className="cli-kpi"><div className="cli-kpi-v">{fmtBRL(stats.totalValue)}</div><div className="cli-kpi-l">Valor total</div></div>
          </div>
          <div className="cli-section">
            <div className="cli-section-title">Frescor & Saldo</div>
            <div className="cli-info-grid">
              <div><div className="cli-info-l">Frescor</div><div className="cli-info-v"><CliFrescorPill state={derived?.frescor?.state} label={derived?.frescor?.label}/></div></div>
              <div><div className="cli-info-l">Saldo em aberto</div><div className="cli-info-v"><SaldoNeg value={derived.saldo}/></div></div>
              <div><div className="cli-info-l">Tags</div><div className="cli-info-v cli-info-tags">
                {derived.tags.length === 0 && <span className="cli-cell-muted">—</span>}
                {derived.tags.map((t, i) => <span key={i} className="cli-tag">{t}</span>)}
              </div></div>
            </div>
          </div>
          <div className="cli-section">
            <div className="cli-section-title">Contato</div>
            <div className="cli-info-grid">
              <div><div className="cli-info-l">Nome</div><div className="cli-info-v">{client.contact}</div></div>
              <div><div className="cli-info-l">Telefone</div><div className="cli-info-v">{client.phone}</div></div>
              <div><div className="cli-info-l">CNPJ/CPF</div><div className="cli-info-v cli-doc-mono">{client.doc}</div></div>
              <div><div className="cli-info-l">Última OS</div><div className="cli-info-v">{client.lastOs || "—"}</div></div>
            </div>
          </div>
          <CliEnderecoSection client={client}/>
          <div className="cli-section">
            <div className="cli-section-title">Histórico de OS ({own.length})</div>
            <div className="cli-history">
              {own.length === 0 && <div className="cli-empty">Nenhuma OS registrada.</div>}
              {own.map((o) => (
                <div className="cli-os" key={o.id}>
                  <div className="cli-os-id">#{o.id}</div>
                  <div className="cli-os-prod">{o.product}</div>
                  <div className={`cli-os-stage stage-${o.stage}`}>{window.OS_DATA.OS_STAGES.find((s) => s.id === o.stage)?.label || o.stage}</div>
                  <div className="cli-os-deadline">{o.deadline}</div>
                  <div className="cli-os-value">{o.value}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
        <div className="os-drawer-actions">
          <button className="os-btn primary"><I.plus size={14}/> Nova OS</button>
          <button className="os-btn"><I.pencil size={14}/> Editar cliente</button>
          <button className="os-btn ghost">Ver financeiro completo</button>
        </div>
      </div>
    </div>
  );
}

// ════════════════════════════════════════════════════════════════════
// COMPORTAMENTO COMUM DAS LISTAS (ordenação · paginação · atalhos ·
// skeleton · exportar · ativar/desativar). A view de Cliente já trazia
// isso inline; aqui vira peça reusável pros outros papéis.
// ════════════════════════════════════════════════════════════════════
function useLista(chaveInicial) {
  const [sort, setSort] = useStateC({ key: chaveInicial, dir: "asc" });
  const [page, setPage] = useStateC(1);
  const [porPagina, setPorPagina] = useStateC(25);
  const [cursor, setCursor] = useStateC(-1);
  const [ajuda, setAjuda] = useStateC(false);
  const [carregando, setCarregando] = useStateC(true);
  useEffectC(() => { const t = setTimeout(() => setCarregando(false), 520); return () => clearTimeout(t); }, []);
  const ordenar = (key) => setSort((s2) => ({ key, dir: s2.key === key && s2.dir === "asc" ? "desc" : "asc" }));
  return { sort, ordenar, page, setPage, porPagina, setPorPagina, cursor, setCursor, ajuda, setAjuda, carregando };
}

function cliOrdena(lista, sort, valor) {
  const mul = sort.dir === "asc" ? 1 : -1;
  return lista.slice().sort((a, b) => {
    const va = valor(a, sort.key), vb = valor(b, sort.key);
    return va === vb ? 0 : (va > vb ? mul : -mul);
  });
}

// Atalhos J/K e ? — Enter só quando a lista tem detalhe pra abrir.
function useAtalhosLista(L, total, onEnter) {
  useEffectC(() => {
    const onKey = (e) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || "")) return;
      const k = e.key;
      if (k === "?") { e.preventDefault(); L.setAjuda((v) => !v); return; }
      if (k === "Escape" && L.ajuda) { L.setAjuda(false); return; }
      if (k === "j" || k === "J" || k === "ArrowDown") { e.preventDefault(); L.setCursor((i) => Math.min(total - 1, i + 1)); }
      if (k === "k" || k === "K" || k === "ArrowUp") { e.preventDefault(); L.setCursor((i) => Math.max(0, i - 1)); }
      if (k === "Enter" && onEnter && L.cursor >= 0) { e.preventDefault(); onEnter(L.cursor); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [total, L.cursor, L.ajuda]);
}

function CliToast({ toast, onClose }) {
  useEffectC(() => { const t = setTimeout(onClose, 6000); return () => clearTimeout(t); }, [toast]);
  return (
    <div className="cli-toast" role="status">
      <span>{toast.msg}</span>
      {toast.desfazer && <button onClick={toast.desfazer}>Desfazer</button>}
      <button className="cli-toast-x" onClick={onClose} aria-label="Fechar">✕</button>
    </div>
  );
}

function SkelRows({ cols }) {
  return [0,1,2,3,4,5].map((i) => (
    <tr key={"sk" + i} className="cli-row cli-row-skel"><td colSpan={cols}><span className="cli-skel"/></td></tr>
  ));
}

function Paginacao({ L, total, onAjuda }) {
  const totalPag = Math.max(1, Math.ceil(total / L.porPagina));
  const pagina = Math.min(L.page, totalPag);
  const de = total === 0 ? 0 : (pagina - 1) * L.porPagina + 1;
  const ate = Math.min(pagina * L.porPagina, total);
  if (total === 0) return null;
  return (
    <div className="cli-pag">
      <div className="cli-pag-l">
        <button className="cli-pag-atalhos" onClick={onAjuda} title="Atalhos de teclado"><kbd>?</kbd> atalhos</button>
        <span>Mostrando {de}–{ate} de {total.toLocaleString("pt-BR")}</span>
      </div>
      <div className="cli-pag-r">
        <label className="cli-pag-pp">Por página
          <select value={L.porPagina} onChange={(e) => { L.setPorPagina(Number(e.target.value)); L.setPage(1); }}>
            {[25, 50, 100].map((n) => <option key={n} value={n}>{n}</option>)}
          </select>
        </label>
        <div className="cli-pag-nav">
          <button onClick={() => L.setPage(1)} disabled={pagina === 1} aria-label="Primeira página">«</button>
          <button onClick={() => L.setPage(pagina - 1)} disabled={pagina === 1} aria-label="Página anterior">‹</button>
          <span className="tabular">{pagina} <em>/ {totalPag}</em></span>
          <button onClick={() => L.setPage(pagina + 1)} disabled={pagina === totalPag} aria-label="Próxima página">›</button>
          <button onClick={() => L.setPage(totalPag)} disabled={pagina === totalPag} aria-label="Última página">»</button>
        </div>
      </div>
    </div>
  );
}

function AtalhosModal({ onClose, comEnter }) {
  return (
    <div className="cli-ajuda-scrim" onClick={onClose}>
      <div className="cli-ajuda" onClick={(e) => e.stopPropagation()}>
        <b>Atalhos</b>
        <dl>
          <div><dt><kbd>/</kbd></dt><dd>focar a busca</dd></div>
          <div><dt><kbd>J</kbd> <kbd>K</kbd></dt><dd>descer e subir na lista</dd></div>
          {comEnter && <div><dt><kbd>↵</kbd></dt><dd>abrir o registro marcado</dd></div>}
          <div><dt><kbd>⌘K</kbd></dt><dd>busca global do sistema</dd></div>
          <div><dt><kbd>?</kbd></dt><dd>mostrar ou esconder esta lista</dd></div>
        </dl>
      </div>
    </div>
  );
}

// Exportar CSV — mesma mecânica da lista de clientes, respeitando filtro e ordem.
function cliExportarCSV(nome, head, linhas) {
  const csv = [head, ...linhas].map((r) => r.map((v) => `"${String(v ?? "").replace(/"/g, '""')}"`).join(";")).join("\n");
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8" }));
  a.download = `${nome}-${cliHoje()}.csv`;
  a.click(); URL.revokeObjectURL(a.href);
}

function useExport(fn) {
  useEffectC(() => { window.__cliExport = fn; return () => { if (window.__cliExport === fn) delete window.__cliExport; }; });
}

// ════════════════════════════════════════════════════════════════════
// VIEW: FORNECEDOR
// Vocabulário: Categoria · Lead time · Frequência · A pagar · Crítico
// ════════════════════════════════════════════════════════════════════
function SupplierView({ papeisOutros = {} }) {
  // Cadastros de Outros que ganharam o papel de fornecedor entram aqui — mesmo
  // cadastro, papel a mais (ADR 0246), ainda sem histórico de compra.
  const SUPPLIERS = useMemoC(() => {
    const base = window.SUPPLIERS || [];
    const vindos = (window.OTHERS || [])
      .filter((o) => (papeisOutros[o.id] || []).includes("supplier"))
      .map((o) => ({ id: o.id, name: o.name, doc: o.doc || "—", contact: o.contact, phone: o.phone,
        category: "Sem categoria", leadDays: 0, freq: "—", aPagar: 0, dueDate: "—",
        lastOrder: null, openOrders: 0, critical: false, tags: [] }));
    return [...base, ...vindos];
  }, [papeisOutros]);
  const [q, setQ] = useStateC("");
  const [fCat, setFCat] = useStateC("all");
  const [fStatus, setFStatus] = useStateC("all");
  const [fAPagar, setFAPagar] = useStateC("all");
  const [favs, toggleFav] = useFavorites("fornecedores");
  const [statusOv, setStatusOv] = useStatusCadastro("fornecedores");
  const [toast, setToast] = useStateC(null);
  const L = useLista("name");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  const kpis = useMemoC(() => ({
    total:    SUPPLIERS.length,
    criticos: SUPPLIERS.filter((s) => s.critical).length,
    aPagar:   SUPPLIERS.reduce((sum, s) => sum + (s.aPagar || 0), 0),
    aPagarN:  SUPPLIERS.filter((s) => s.aPagar > 0).length,
    leadAvg:  Math.round(SUPPLIERS.reduce((sum, s) => sum + s.leadDays, 0) / Math.max(SUPPLIERS.length, 1)),
    ativos:   SUPPLIERS.filter((s) => s.openOrders > 0).length,
  }), [SUPPLIERS]);

  const catList = useMemoC(() => {
    const m = {}; SUPPLIERS.forEach((s) => { m[s.category] = (m[s.category] || 0) + 1; });
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, [SUPPLIERS]);

  const filtered = SUPPLIERS.filter((s) => {
    if (fCat !== "all" && s.category !== fCat) return false;
    if (fStatus === "critical" && !s.critical) return false;
    if (fStatus === "active" && s.openOrders === 0) return false;
    if (fAPagar === "pending" && s.aPagar <= 0) return false;
    if (fAPagar === "zero" && s.aPagar > 0) return false;
    if (q && !`${s.name} ${s.doc} ${s.contact} ${s.category}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const activeF = [fCat, fStatus, fAPagar].filter((v) => v !== "all").length;

  const ordenados = cliOrdena(filtered, L.sort, (s2, k) => ({
    name: s2.name.toLowerCase(), lead: s2.leadDays, apagar: s2.aPagar || 0, abertos: s2.openOrders || 0,
  })[k]);
  const totalPag = Math.max(1, Math.ceil(ordenados.length / L.porPagina));
  const pagina = Math.min(L.page, totalPag);
  const visiveis = ordenados.slice((pagina - 1) * L.porPagina, pagina * L.porPagina);
  useAtalhosLista(L, ordenados.length, null);
  useExport(() => cliExportarCSV("fornecedores",
    ["Fornecedor","Categoria","CNPJ","Lead (dias)","Frequência","A pagar","Vencimento","Pedidos abertos","Situação"],
    ordenados.map((s2) => [s2.name, s2.category, s2.doc, s2.leadDays, s2.freq,
      (s2.aPagar || 0).toFixed(2).replace(".", ","), s2.dueDate, s2.openOrders, statusOv[s2.id] === "inativo" ? "Inativo" : "Ativo"])));

  const alternar = (s2) => {
    const inativo = statusOv[s2.id] === "inativo";
    setStatusOv(s2.id, inativo ? null : "inativo");
    setToast({ msg: inativo ? `${s2.name} está ativo de novo.` : `${s2.name} foi desativado — some das compras, o histórico fica.`,
      desfazer: () => { setStatusOv(s2.id, inativo ? "inativo" : null); setToast(null); } });
  };

  return (
    <>
      <div className="cli-kpihero" style={{ gridTemplateColumns: "repeat(5, 1fr)" }}>
        <KpiHero l="Fornecedores" v={kpis.total} s="cadastrados"/>
        <KpiHero l="Críticos" v={kpis.criticos} s="atenção urgente" tone={kpis.criticos > 0 ? "danger" : null}/>
        <KpiHero l="Com pedido aberto" v={kpis.ativos} s={`${SUPPLIERS.reduce((a, s) => a + s.openOrders, 0)} ordens`}/>
        <KpiHero l="Lead time médio" v={`${kpis.leadAvg}d`} s="da emissão à entrega"/>
        <KpiHero l="A pagar" v={fmtBRLshort(kpis.aPagar)} s={`${kpis.aPagarN} fornecedores`} dark/>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        unidade="fornecedor" placeholder="Buscar fornecedor, CNPJ, contato, categoria…"
        filtersCount={activeF} onClear={() => { setFCat("all"); setFStatus("all"); setFAPagar("all"); }}
        resultCount={filtered.length}>
        <FilterDropdown label="Categoria" value={fCat} onChange={setFCat} options={[
          { id: "all", label: "Todas" }, ...catList.map(([c, n]) => ({ id: c, label: c, count: n })),
        ]}/>
        <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
          { id: "all", label: "Todos" },
          { id: "active", label: "Com pedido aberto", count: kpis.ativos },
          { id: "critical", label: "Críticos", count: kpis.criticos },
        ]}/>
        <FilterDropdown label="A pagar" value={fAPagar} onChange={setFAPagar} options={[
          { id: "all", label: "Todos" },
          { id: "pending", label: "Com saldo a pagar", count: kpis.aPagarN },
          { id: "zero", label: "Em dia" },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <ThSort k="name" sort={L.sort} onSort={L.ordenar}>Fornecedor</ThSort>
            <th>Categoria</th><th>CNPJ</th>
            <ThSort k="lead" sort={L.sort} onSort={L.ordenar} num>Lead</ThSort>
            <th>Frequência</th>
            <ThSort k="apagar" sort={L.sort} onSort={L.ordenar} num>A pagar</ThSort>
            <th>Vencimento</th>
            <th>Último pedido</th>
            <ThSort k="abertos" sort={L.sort} onSort={L.ordenar} num>Pedidos abertos</ThSort>
            <th></th><th></th>
          </tr></thead>
          <tbody>
            {L.carregando && <SkelRows cols={11}/>}
            {!L.carregando && visiveis.map((s, i) => (
              <tr key={s.id} className={"cli-row" + (i === L.cursor ? " cli-row-cursor" : "")}>
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={s.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">
                        {s.name}
                        {s.critical && <span className="cli-pill-danger">Crítico</span>}
                        {statusOv[s.id] === "inativo" && <span className="cli-cadastro-status cli-cadastro-status--inativo">Inativo</span>}
                      </div>
                      <div className="cli-name-sub"><I.phone size={10}/><span>{s.contact} · {s.phone}</span></div>
                    </div>
                  </div>
                </td>
                <td><span className="cli-tag-cat">{s.category}</span></td>
                <td><span className="cli-doc-mono">{s.doc}</span></td>
                <td className="num">{s.leadDays ? <span className="cli-num-strong">{s.leadDays}d</span> : <span className="cli-cell-muted">—</span>}</td>
                <td className="cli-cell-muted" style={{ textTransform: "capitalize" }}>{s.freq}</td>
                <td className="num"><SaldoNeg value={s.aPagar} title="Saldo a pagar"/></td>
                <td><span className="cli-cell-mono">{fmtDataBR(s.dueDate)}</span></td>
                <td>{fmtAgo(s.lastOrder)}</td>
                <td className="num">{s.openOrders || <span className="cli-cell-muted">0</span>}</td>
                <td className="cli-td-fav"><FavStar id={s.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab items={[
                  { label:"Ver fornecedor", icon: I.pencil },
                  { label:"Novo pedido", icon: I.plus },
                  { label:"Lançar contas a pagar", icon: I.briefcase },
                  { label:"Histórico de cotações", icon: I.list },
                  { sep: true },
                  statusOv[s.id] === "inativo"
                    ? { label:"Ativar cadastro", icon: I.check || I.plus, action: () => alternar(s) }
                    : { label:"Desativar cadastro", icon: I.close, action: () => alternar(s) },
                ]}/></td>
              </tr>
            ))}
            {!L.carregando && ordenados.length === 0 && <tr><td colSpan={11} className="os-empty">Nenhum fornecedor encontrado.</td></tr>}
          </tbody>
        </table>
      </div>

      {!L.carregando && <Paginacao L={L} total={ordenados.length} onAjuda={() => L.setAjuda(true)}/>}
      {L.ajuda && <AtalhosModal onClose={() => L.setAjuda(false)}/>}
      {toast && <CliToast toast={toast} onClose={() => setToast(null)}/>}
    </>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: FUNCIONÁRIO
// Vocabulário: Cargo · Setor · Vínculo · Admissão · Acesso · Aniversário
// ════════════════════════════════════════════════════════════════════
function EmployeeView() {
  const EMPLOYEES = window.EMPLOYEES || [];
  const [q, setQ] = useStateC("");
  const [fDept, setFDept] = useStateC("all");
  const [fVinc, setFVinc] = useStateC("all");
  const [fStatus, setFStatus] = useStateC("all");
  const [favs, toggleFav] = useFavorites("funcionarios");
  const [statusOv, setStatusOv] = useStatusCadastro("funcionarios");
  const [toast, setToast] = useStateC(null);
  const L = useLista("name");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  const kpis = useMemoC(() => {
    const today = new Date();
    const month = today.getMonth() + 1;
    const aniv = EMPLOYEES.filter((e) => {
      const m = parseInt((e.birth || "").split("/")[1]);
      return m === month;
    }).length;
    const ferias = EMPLOYEES.filter((e) => e.status === "férias").length;
    const dept = {};
    EMPLOYEES.forEach((e) => { dept[e.department] = (dept[e.department] || 0) + 1; });
    const topDept = Object.entries(dept).sort((a, b) => b[1] - a[1])[0] || ["—", 0];
    return {
      total:    EMPLOYEES.length,
      ativos:   EMPLOYEES.filter((e) => e.status === "ativo").length,
      producao: dept["Produção"] || 0,
      comercial:dept["Comercial"] || 0,
      aniv, ferias, topDept,
    };
  }, [EMPLOYEES]);

  const deptList = useMemoC(() => {
    const m = {}; EMPLOYEES.forEach((e) => { m[e.department] = (m[e.department] || 0) + 1; });
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, [EMPLOYEES]);

  const filtered = EMPLOYEES.filter((e) => {
    if (fDept !== "all" && e.department !== fDept) return false;
    if (fVinc !== "all" && e.vinculo !== fVinc) return false;
    if (fStatus !== "all" && e.status !== fStatus) return false;
    if (q && !`${e.name} ${e.doc} ${e.role} ${e.department}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const activeF = [fDept, fVinc, fStatus].filter((v) => v !== "all").length;

  const ordenados = cliOrdena(filtered, L.sort, (e, k) => ({
    name: e.name.toLowerCase(), role: (e.role || "").toLowerCase(), admissao: e.admittedAt || "",
  })[k]);
  const totalPag = Math.max(1, Math.ceil(ordenados.length / L.porPagina));
  const pagina = Math.min(L.page, totalPag);
  const visiveis = ordenados.slice((pagina - 1) * L.porPagina, pagina * L.porPagina);
  useAtalhosLista(L, ordenados.length, null);
  useExport(() => cliExportarCSV("funcionarios",
    ["Funcionário","CPF","Cargo","Setor","Vínculo","Admissão","Turno","Acesso","Situação no trabalho","Cadastro"],
    ordenados.map((e) => [e.name, e.doc, e.role, e.department, e.vinculo, e.admittedAt, e.shift, e.access, e.status,
      statusOv[e.id] === "inativo" ? "Inativo" : "Ativo"])));

  const alternar = (e) => {
    const inativo = statusOv[e.id] === "inativo";
    setStatusOv(e.id, inativo ? null : "inativo");
    setToast({ msg: inativo ? `${e.name} está ativo de novo.` : `${e.name} foi desativado — perde acesso ao sistema, o histórico de ponto fica.`,
      desfazer: () => { setStatusOv(e.id, inativo ? "inativo" : null); setToast(null); } });
  };

  return (
    <>
      <div className="cli-kpihero" style={{ gridTemplateColumns: "repeat(5, 1fr)" }}>
        <KpiHero l="No quadro" v={kpis.total} s={`${kpis.ativos} ativos`}/>
        <KpiHero l="Comercial" v={kpis.comercial} s="atendimento e vendas"/>
        <KpiHero l="Produção" v={kpis.producao} s="impressão e acabamento"/>
        <KpiHero l="Em férias" v={kpis.ferias} s="ausentes no momento" tone={kpis.ferias > 0 ? "warning" : null}/>
        <KpiHero l="Aniversários" v={kpis.aniv} s="este mês" dark/>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        unidade="funcionário" placeholder="Buscar nome, CPF, cargo, setor…"
        filtersCount={activeF} onClear={() => { setFDept("all"); setFVinc("all"); setFStatus("all"); }}
        resultCount={filtered.length}>
        <FilterDropdown label="Setor" value={fDept} onChange={setFDept} options={[
          { id: "all", label: "Todos" }, ...deptList.map(([d, n]) => ({ id: d, label: d, count: n })),
        ]}/>
        <FilterDropdown label="Vínculo" value={fVinc} onChange={setFVinc} options={[
          { id: "all", label: "Todos" },
          { id: "CLT", label: "CLT" }, { id: "PJ", label: "PJ" },
          { id: "Estagiário", label: "Estagiário" }, { id: "Sócio", label: "Sócio" },
        ]}/>
        <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
          { id: "all", label: "Todos" },
          { id: "ativo", label: "Ativo" }, { id: "férias", label: "Em férias" },
          { id: "afastado", label: "Afastado" },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <ThSort k="name" sort={L.sort} onSort={L.ordenar}>Funcionário</ThSort>
            <ThSort k="role" sort={L.sort} onSort={L.ordenar}>Cargo</ThSort>
            <th>Setor</th><th>Vínculo</th>
            <ThSort k="admissao" sort={L.sort} onSort={L.ordenar}>Admissão</ThSort>
            <th>Turno</th><th>Acesso</th>
            <th>Status</th><th>Aniversário</th>
            <th></th><th></th>
          </tr></thead>
          <tbody>
            {L.carregando && <SkelRows cols={11}/>}
            {!L.carregando && visiveis.map((e, i) => (
              <tr key={e.id} className={"cli-row" + (i === L.cursor ? " cli-row-cursor" : "")}>
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={e.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">{e.name}
                        {statusOv[e.id] === "inativo" && <span className="cli-cadastro-status cli-cadastro-status--inativo">Inativo</span>}
                      </div>
                      <div className="cli-name-sub"><span className="cli-doc-mono">{e.doc}</span></div>
                    </div>
                  </div>
                </td>
                <td>{e.role}</td>
                <td><span className="cli-tag-dept">{e.department}</span></td>
                <td><span className={`cli-tipo cli-tipo-${e.vinculo.toLowerCase().replace(/[^a-z]/g, "")}`}>{e.vinculo}</span></td>
                <td className="cli-cell-mono">{fmtDataBR(e.admittedAt)}</td>
                <td className="cli-cell-muted">{e.shift}</td>
                <td><span className="cli-pill-access">{e.access}</span></td>
                <td>
                  <span className={`cli-status-pill cli-status-${e.status === "férias" ? "ferias" : e.status}`}>
                    {e.status}
                  </span>
                </td>
                <td className="cli-cell-mono">{e.birth}</td>
                <td className="cli-td-fav"><FavStar id={e.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab items={[
                  statusOv[e.id] === "inativo"
                    ? { label:"Ativar cadastro", icon: I.check || I.plus, action: () => alternar(e) }
                    : { label:"Desativar cadastro", icon: I.close, action: () => alternar(e) },
                  { label:"Ver perfil", icon: I.pencil },
                  { label:"Editar acesso", icon: I.briefcase },
                  { label:"Registrar férias", icon: I.list },
                  { label:"Folha de pagamento", icon: I.upload },
                  { sep: true },
                  { label:"Desligar", icon: I.close, danger: true },
                ]}/></td>
              </tr>
            ))}
            {!L.carregando && ordenados.length === 0 && <tr><td colSpan={11} className="os-empty">Nenhum funcionário encontrado.</td></tr>}
          </tbody>
        </table>
      </div>

      {!L.carregando && <Paginacao L={L} total={ordenados.length} onAjuda={() => L.setAjuda(true)}/>}
      {L.ajuda && <AtalhosModal onClose={() => L.setAjuda(false)}/>}
      {toast && <CliToast toast={toast} onClose={() => setToast(null)}/>}
    </>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: REPRESENTANTE
// Vocabulário: Região · Comissão · Carteira · Vendas mês · A pagar comissão
// ════════════════════════════════════════════════════════════════════
function RepresentativeView() {
  const REPS = window.REPRESENTATIVES || [];
  const [q, setQ] = useStateC("");
  const [fRegion, setFRegion] = useStateC("all");
  const [fStatus, setFStatus] = useStateC("all");
  const [favs, toggleFav] = useFavorites("representantes");
  const [statusOv, setStatusOv] = useStatusCadastro("representantes");
  const [toast, setToast] = useStateC(null);
  const L = useLista("name");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  const kpis = useMemoC(() => {
    const vendasMes = REPS.reduce((s, r) => s + r.vendasMes, 0);
    const comissao = REPS.reduce((s, r) => s + r.aPagarComissao, 0);
    const carteira = REPS.reduce((s, r) => s + r.portfolio, 0);
    const top = REPS.slice().sort((a, b) => b.vendasMes - a.vendasMes)[0];
    return {
      total:   REPS.length,
      ativos:  REPS.filter((r) => r.status === "ativo").length,
      vendasMes, comissao, carteira, top: top?.name || "—",
    };
  }, [REPS]);

  const regionsList = useMemoC(() => {
    const m = {};
    REPS.forEach((r) => r.regions.forEach((reg) => { m[reg] = (m[reg] || 0) + 1; }));
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, [REPS]);

  const filtered = REPS.filter((r) => {
    if (fRegion !== "all" && !r.regions.includes(fRegion)) return false;
    if (fStatus !== "all" && r.status !== fStatus) return false;
    if (q && !`${r.name} ${r.doc} ${r.contact} ${r.regions.join(" ")}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const activeF = [fRegion, fStatus].filter((v) => v !== "all").length;

  const ordenados = cliOrdena(filtered, L.sort, (r, k) => ({
    name: r.name.toLowerCase(), pct: r.pct || 0, carteira: r.portfolio || 0,
    vendas: r.vendasMes || 0, comissao: r.aPagarComissao || 0,
  })[k]);
  const totalPag = Math.max(1, Math.ceil(ordenados.length / L.porPagina));
  const pagina = Math.min(L.page, totalPag);
  const visiveis = ordenados.slice((pagina - 1) * L.porPagina, pagina * L.porPagina);
  useAtalhosLista(L, ordenados.length, null);
  useExport(() => cliExportarCSV("representantes",
    ["Representante","CNPJ","Regiões","Comissão (%)","Carteira","Vendas no mês","Comissão a pagar","Status","Cadastro"],
    ordenados.map((r) => [r.name, r.doc, r.regions.join(" · "), r.pct, r.portfolio,
      (r.vendasMes || 0).toFixed(2).replace(".", ","), (r.aPagarComissao || 0).toFixed(2).replace(".", ","),
      r.status, statusOv[r.id] === "inativo" ? "Inativo" : "Ativo"])));

  const alternar = (r) => {
    const inativo = statusOv[r.id] === "inativo";
    setStatusOv(r.id, inativo ? null : "inativo");
    setToast({ msg: inativo ? `${r.name} está ativo de novo.` : `${r.name} foi desativado — não recebe carteira nova, a comissão em aberto continua.`,
      desfazer: () => { setStatusOv(r.id, inativo ? "inativo" : null); setToast(null); } });
  };

  return (
    <>
      <div className="cli-kpihero" style={{ gridTemplateColumns: "repeat(5, 1fr)" }}>
        <KpiHero l="Representantes" v={kpis.total} s={`${kpis.ativos} ativos`}/>
        <KpiHero l="Carteira total" v={kpis.carteira} s="clientes sob representação"/>
        <KpiHero l="Vendas no mês" v={fmtBRLshort(kpis.vendasMes)} s="faturamento via representante"/>
        <KpiHero l="Top performer" v={kpis.top.split(" ")[0]} s="maior vendas no mês"/>
        <KpiHero l="A pagar comissão" v={fmtBRLshort(kpis.comissao)} s="ciclo corrente" dark/>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        unidade="representante" placeholder="Buscar representante, CNPJ, contato, região…"
        filtersCount={activeF} onClear={() => { setFRegion("all"); setFStatus("all"); }}
        resultCount={filtered.length}>
        <FilterDropdown label="Região" value={fRegion} onChange={setFRegion} options={[
          { id: "all", label: "Todas" }, ...regionsList.map(([r, n]) => ({ id: r, label: r, count: n })),
        ]}/>
        <FilterDropdown label="Status" value={fStatus} onChange={setFStatus} options={[
          { id: "all", label: "Todos" },
          { id: "ativo", label: "Ativos" }, { id: "ociosa", label: "Ociosos" },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <ThSort k="name" sort={L.sort} onSort={L.ordenar}>Representante</ThSort>
            <th>CNPJ</th><th>Região</th>
            <ThSort k="pct" sort={L.sort} onSort={L.ordenar} num>Comissão</ThSort>
            <ThSort k="carteira" sort={L.sort} onSort={L.ordenar} num>Carteira</ThSort>
            <ThSort k="vendas" sort={L.sort} onSort={L.ordenar} num>Vendas no mês</ThSort>
            <ThSort k="comissao" sort={L.sort} onSort={L.ordenar} num>A pagar</ThSort>
            <th>Última venda</th><th>Status</th>
            <th></th><th></th>
          </tr></thead>
          <tbody>
            {L.carregando && <SkelRows cols={11}/>}
            {!L.carregando && visiveis.map((r, i) => (
              <tr key={r.id} className={"cli-row" + (i === L.cursor ? " cli-row-cursor" : "")}>
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={r.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">{r.name}
                        {statusOv[r.id] === "inativo" && <span className="cli-cadastro-status cli-cadastro-status--inativo">Inativo</span>}
                      </div>
                      <div className="cli-name-sub"><I.phone size={10}/><span>{r.contact} · {r.phone}</span></div>
                    </div>
                  </div>
                </td>
                <td><span className="cli-doc-mono">{r.doc}</span></td>
                <td>{r.regions.map((reg, i) => <span key={i} className="cli-tag-region">{reg}</span>)}</td>
                <td className="num"><span className="cli-num-strong">{r.pct}%</span></td>
                <td className="num">{r.portfolio}</td>
                <td className="num"><span className="cli-num-strong">{fmtBRLshort(r.vendasMes)}</span></td>
                <td className="num"><SaldoNeg value={r.aPagarComissao} title="Comissão a pagar"/></td>
                <td>{fmtAgo(r.lastDeal)}</td>
                <td>
                  <span className={`cli-status-pill cli-status-${r.status === "ociosa" ? "ociosa" : "ativo"}`}>{r.status}</span>
                </td>
                <td className="cli-td-fav"><FavStar id={r.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab items={[
                  statusOv[r.id] === "inativo"
                    ? { label:"Ativar cadastro", icon: I.check || I.plus, action: () => alternar(r) }
                    : { label:"Desativar cadastro", icon: I.close, action: () => alternar(r) },
                  { label:"Ver representante", icon: I.pencil },
                  { label:"Ver carteira", icon: I.list },
                  { label:"Lançar comissão", icon: I.briefcase },
                  { label:"Histórico de vendas", icon: I.upload },
                  { sep: true },
                  { label:"Desativar", icon: I.close, danger: true },
                ]}/></td>
              </tr>
            ))}
            {!L.carregando && ordenados.length === 0 && <tr><td colSpan={11} className="os-empty">Nenhum representante encontrado.</td></tr>}
          </tbody>
        </table>
      </div>

      {!L.carregando && <Paginacao L={L} total={ordenados.length} onAjuda={() => L.setAjuda(true)}/>}
      {L.ajuda && <AtalhosModal onClose={() => L.setAjuda(false)}/>}
      {toast && <CliToast toast={toast} onClose={() => setToast(null)}/>}
    </>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: OUTROS (ADR 0246)
// Vocabulário: Origem · Interesse · Sem documento · Último contato · Converter
// ════════════════════════════════════════════════════════════════════
function OtherView({ setRole, papeis = {}, alternarPapel }) {
  const OTHERS = window.OTHERS || [];
  const [q, setQ] = useStateC("");
  const [fOrigem, setFOrigem] = useStateC("all");
  const [fDoc, setFDoc] = useStateC("all");
  const [toast, setToast] = useStateC(null);
  const [favs, toggleFav] = useFavorites("outros");
  const L = useLista("nome");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  const label = { customer: "cliente", supplier: "fornecedor" };
  const converter = (o, papel) => {
    const tinha = (papeis[o.id] || []).includes(papel);
    alternarPapel?.(o.id, papel);
    setToast({
      msg: tinha
        ? `${o.name} deixou de ser ${label[papel]}. Continua em Outros, como sempre esteve.`
        : `${o.name} agora também é ${label[papel]}. O cadastro é o mesmo — ganhou um papel, não mudou de lugar.`,
      desfazer: () => { alternarPapel?.(o.id, papel); setToast(null); },
    });
  };

  const origens = useMemoC(() => {
    const m = {}; OTHERS.forEach((o) => { m[o.origem] = (m[o.origem] || 0) + 1; });
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, [OTHERS]);

  const semDoc = OTHERS.filter((o) => !o.doc).length;
  const daMigracao = OTHERS.filter((o) => o.origem === "Migração WR").length;
  const frios = OTHERS.filter((o) => { const dd = daysSince(o.ultimoContato); return dd != null && dd > 90; }).length;
  const comPapel = OTHERS.filter((o) => (papeis[o.id] || []).length > 0).length;

  const filtered = OTHERS.filter((o) => {
    if (fOrigem !== "all" && o.origem !== fOrigem) return false;
    if (fDoc === "sem" && o.doc) return false;
    if (fDoc === "com" && !o.doc) return false;
    if (q && !`${o.name} ${o.contact} ${o.phone} ${o.interesse} ${o.origem}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const activeF = [fOrigem, fDoc].filter((v) => v !== "all").length;
  const ordenados = cliOrdena(filtered, L.sort, (o, k) => ({
    nome: o.name.toLowerCase(), origem: o.origem, contato: o.ultimoContato || "", criado: o.criadoEm || "",
  })[k]);
  const totalPag = Math.max(1, Math.ceil(ordenados.length / L.porPagina));
  const pagina = Math.min(L.page, totalPag);
  const visiveis = ordenados.slice((pagina - 1) * L.porPagina, pagina * L.porPagina);
  useAtalhosLista(L, ordenados.length, null);
  useExport(() => cliExportarCSV("outros",
    ["Nome","Documento","Contato","Telefone","Origem","Interesse","Cadastrado em","Último contato","Responsável"],
    ordenados.map((o) => [o.name, o.doc || "sem documento", o.contact, o.phone, o.origem, o.interesse, o.criadoEm, o.ultimoContato, o.responsavel])));

  return (
    <>
      <div className="cli-kpihero" style={{ gridTemplateColumns: "repeat(4, 1fr)" }}>
        <KpiHero l="Em Outros" v={OTHERS.length} s={comPapel > 0 ? `${comPapel} já com outro papel` : "prospects, leads e avulsos"} icon={I.tag} tone="primary"/>
        <KpiHero l="Sem documento" v={semDoc} s="CPF/CNPJ não é obrigatório aqui" icon={I.list}/>
        <KpiHero l="Da migração" v={daMigracao} s="vieram do sistema antigo" icon={I.upload}/>
        <KpiHero l="Sem contato há 90d" v={frios} s="esfriaram" icon={I.clock} tone="rose"/>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        unidade="cadastro" placeholder="Buscar nome, contato, telefone, interesse…"
        filtersCount={activeF} onClear={() => { setFOrigem("all"); setFDoc("all"); }}
        resultCount={ordenados.length}>
        <FilterDropdown label="Origem" value={fOrigem} onChange={setFOrigem} options={[
          { id: "all", label: "Todas" }, ...origens.map(([o, n]) => ({ id: o, label: o, count: n })),
        ]}/>
        <FilterDropdown label="Documento" value={fDoc} onChange={setFDoc} options={[
          { id: "all", label: "Todos" },
          { id: "sem", label: "Sem CPF/CNPJ", count: semDoc },
          { id: "com", label: "Com documento", count: OTHERS.length - semDoc },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <ThSort k="nome" sort={L.sort} onSort={L.ordenar}>Contato</ThSort>
            <th>Documento</th>
            <ThSort k="origem" sort={L.sort} onSort={L.ordenar}>Origem</ThSort>
            <th>Interesse</th>
            <ThSort k="criado" sort={L.sort} onSort={L.ordenar}>Cadastrado</ThSort>
            <ThSort k="contato" sort={L.sort} onSort={L.ordenar}>Último contato</ThSort>
            <th>Responsável</th><th></th><th></th>
          </tr></thead>
          <tbody>
            {L.carregando && <SkelRows cols={9}/>}
            {!L.carregando && visiveis.map((o, i) => (
              <tr key={o.id} className={"cli-row" + (i === L.cursor ? " cli-row-cursor" : "")}>
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={o.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">
                        {o.name}
                        {(papeis[o.id] || []).map((p) => (
                          <span key={p} className="cli-papel-extra">também {label[p]}</span>
                        ))}
                      </div>
                      <div className="cli-name-sub"><I.phone size={10}/><span>{o.contact} · {o.phone}</span></div>
                    </div>
                  </div>
                </td>
                <td>{o.doc ? <span className="cli-doc-mono">{o.doc}</span> : <span className="cli-sem-doc">sem documento</span>}</td>
                <td><span className="cli-tag-cat">{o.origem}</span></td>
                <td className="cli-cell-muted">{o.interesse}</td>
                <td className="cli-cell-mono">{fmtDataBR(o.criadoEm)}</td>
                <td>{fmtAgo(o.ultimoContato)}</td>
                <td className="cli-cell-muted">{o.responsavel}</td>
                <td className="cli-td-fav"><FavStar id={o.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab items={[
                  (papeis[o.id] || []).includes("customer")
                    ? { label:"Tirar papel de cliente", icon: I.users, action: () => converter(o, "customer") }
                    : { label:"Tornar cliente", icon: I.users, action: () => converter(o, "customer") },
                  (papeis[o.id] || []).includes("supplier")
                    ? { label:"Tirar papel de fornecedor", icon: I.truck, action: () => converter(o, "supplier") }
                    : { label:"Tornar fornecedor", icon: I.truck, action: () => converter(o, "supplier") },
                  { sep: true },
                  { label:"Registrar contato", icon: I.message || I.phone },
                  { label:"Nova OS", icon: I.plus },
                ]}/></td>
              </tr>
            ))}
            {!L.carregando && ordenados.length === 0 && (
              <tr><td colSpan={9} className="os-empty">
                {activeF > 0 || q
                  ? <>Nenhum cadastro com esses filtros. <button className="cli-empty-a" onClick={() => { setFOrigem("all"); setFDoc("all"); setQ(""); }}>Limpar filtros</button></>
                  : "Nenhum cadastro em Outros."}
              </td></tr>
            )}
          </tbody>
        </table>
      </div>

      {!L.carregando && <Paginacao L={L} total={ordenados.length} onAjuda={() => L.setAjuda(true)}/>}
      {L.ajuda && <AtalhosModal onClose={() => L.setAjuda(false)}/>}
      {toast && <CliToast toast={toast} onClose={() => setToast(null)}/>}
    </>
  );
}

// ════════════════════════════════════════════════════════════════════
// VIEW: TODOS (diretório minimal — só campos comuns)
// Vocabulário: Nome · Tipo · Documento · Contato · ⭐
// ════════════════════════════════════════════════════════════════════
function AllView({ setRole, counts = {}, papeisOutros = {} }) {
  const OS_CLIENTS  = window.OS_DATA?.OS_CLIENTS || [];
  const SUPPLIERS   = window.SUPPLIERS || [];
  const EMPLOYEES   = window.EMPLOYEES || [];
  const REPS        = window.REPRESENTATIVES || [];
  const OTHERS      = window.OTHERS || [];

  const all = useMemoC(() => [
    ...OS_CLIENTS.map((c) => ({ id: `c-${c.id}`, kind: "customer", papeis: ["customer"], name: c.name, doc: c.doc, contact: c.contact, sub: c.phone })),
    ...SUPPLIERS.map((s)   => ({ id: s.id, kind: "supplier", papeis: ["supplier"], name: s.name, doc: s.doc, contact: s.contact, sub: s.category })),
    ...EMPLOYEES.map((e)   => ({ id: e.id, kind: "employee", papeis: ["employee"], name: e.name, doc: e.doc, contact: e.role, sub: e.department })),
    ...REPS.map((r)        => ({ id: r.id, kind: "representative", papeis: ["representative"], name: r.name, doc: r.doc, contact: r.contact, sub: r.regions.join(", ") })),
    // ADR 0246: papel é aditivo — o cadastro de Outros pode ser também cliente
    // e/ou fornecedor. Filtrar por "tipo primário" esconderia justamente isso.
    ...OTHERS.map((o)      => ({ id: o.id, kind: "other", papeis: ["other", ...(papeisOutros[o.id] || [])], name: o.name, doc: o.doc, contact: o.contact, sub: o.origem })),
  ], [OS_CLIENTS, SUPPLIERS, EMPLOYEES, REPS, OTHERS, papeisOutros]);

  const [q, setQ] = useStateC("");
  const [fKind, setFKind] = useStateC("all");
  const [favs, toggleFav] = useFavorites("pessoas-all");
  const searchRef = useRefC(null);
  useSearchShortcut(searchRef);

  // Mesma fonte da barra de abas (CliListPage já soma os papéis aditivos):
  // dois cálculos paralelos pro mesmo número é o que faz a tela se contradizer.
  const kpis = {
    total:    counts.all ?? all.length,
    customer: counts.customer ?? 0,
    supplier: counts.supplier ?? 0,
    employee: counts.employee ?? 0,
    rep:      counts.representative ?? 0,
    other:    counts.other ?? 0,
  };

  const L = useLista("name");
  const filtered = all.filter((p) => {
    if (fKind !== "all" && !p.papeis.includes(fKind)) return false;
    if (q && !`${p.name} ${p.doc} ${p.contact}`.toLowerCase().includes(q.toLowerCase())) return false;
    return true;
  });

  const KIND_LABEL = {
    customer: "Cliente", supplier: "Fornecedor",
    employee: "Funcionário", representative: "Representante", other: "Outros",
  };

  const ordenados = cliOrdena(filtered, L.sort, (p, k) => ({
    name: p.name.toLowerCase(), kind: KIND_LABEL[p.kind] || p.kind,
  })[k]);
  const totalPag = Math.max(1, Math.ceil(ordenados.length / L.porPagina));
  const pagina = Math.min(L.page, totalPag);
  const visiveis = ordenados.slice((pagina - 1) * L.porPagina, pagina * L.porPagina);
  useAtalhosLista(L, ordenados.length, (i) => { const p = ordenados[i]; if (p) setRole(p.kind); });
  useExport(() => cliExportarCSV("contatos",
    ["Nome","Tipo","Documento","Contato","Detalhe"],
    ordenados.map((p) => [p.name, KIND_LABEL[p.kind] || p.kind, p.doc, p.contact, p.sub])));

  return (
    <>
      <div className="cli-kpihero" style={{ gridTemplateColumns: "repeat(6, 1fr)" }}>
        <KpiHero l="No diretório" v={kpis.total} s="todas as pessoas"/>
        <KpiHero l="Clientes" v={kpis.customer} s="abrir tela ›" onClick={() => setRole("customer")}/>
        <KpiHero l="Fornecedores" v={kpis.supplier} s="abrir tela ›" onClick={() => setRole("supplier")}/>
        <KpiHero l="Funcionários" v={kpis.employee} s="abrir tela ›" onClick={() => setRole("employee")}/>
        <KpiHero l="Representantes" v={kpis.rep} s="abrir tela ›" onClick={() => setRole("representative")}/>
        <KpiHero l="Outros" v={kpis.other} s="abrir tela ›" onClick={() => setRole("other")} dark/>
      </div>

      <div className="cli-all-hint">
        <I.info size={12}/>
        <span>Esta é uma <strong>visão consolidada</strong> com campos comuns. Pra ver KPIs e colunas específicas de cada tipo, abra a aba dedicada.</span>
      </div>

      <Toolbar searchRef={searchRef} q={q} setQ={setQ}
        unidade="contato" placeholder="Buscar em todas as pessoas…"
        filtersCount={fKind !== "all" ? 1 : 0} onClear={() => setFKind("all")}
        resultCount={filtered.length}>
        <FilterDropdown label="Tipo" value={fKind} onChange={setFKind} options={[
          { id: "all", label: "Todos" },
          { id: "other", label: "Outros", count: kpis.other },
          { id: "customer", label: "Clientes", count: kpis.customer },
          { id: "supplier", label: "Fornecedores", count: kpis.supplier },
          { id: "employee", label: "Funcionários", count: kpis.employee },
          { id: "representative", label: "Representantes", count: kpis.rep },
        ]}/>
      </Toolbar>

      <div className="os-table-wrap">
        <table className="os-table cli-table cli-table-v2">
          <thead><tr>
            <ThSort k="name" sort={L.sort} onSort={L.ordenar}>Nome</ThSort>
            <ThSort k="kind" sort={L.sort} onSort={L.ordenar}>Tipo</ThSort>
            <th>Documento</th>
            <th>Contato / Cargo / Categoria</th><th>Detalhe</th>
            <th></th><th></th>
          </tr></thead>
          <tbody>
            {L.carregando && <SkelRows cols={7}/>}
            {!L.carregando && visiveis.map((p, i) => (
              <tr key={p.id} className={"cli-row" + (i === L.cursor ? " cli-row-cursor" : "")} onClick={() => setRole(p.kind)}>
                <td className="cli-td-cli">
                  <div className="cli-name">
                    <Avatar name={p.name}/>
                    <div className="cli-name-block">
                      <div className="cli-name-text">{p.name}</div>
                    </div>
                  </div>
                </td>
                <td>
                  <span className={`cli-kind cli-kind-${p.kind}`}>{KIND_LABEL[p.kind]}</span>
                  {(papeisOutros[p.id] || []).map((x) => (
                    <span key={x} className="cli-papel-extra">também {x === "customer" ? "cliente" : "fornecedor"}</span>
                  ))}
                </td>
                <td>{p.doc ? <span className="cli-doc-mono">{p.doc}</span> : <span className="cli-sem-doc">sem documento</span>}</td>
                <td>{p.contact}</td>
                <td className="cli-cell-muted">{p.sub}</td>
                <td className="cli-td-fav"><FavStar id={p.id} favs={favs} toggle={toggleFav}/></td>
                <td className="cli-td-kebab"><RowKebab items={[
                  { label:"Abrir na aba dedicada", icon: I.pencil, action: () => setRole(p.kind) },
                ]}/></td>
              </tr>
            ))}
            {!L.carregando && ordenados.length === 0 && <tr><td colSpan={7} className="os-empty">Nenhum resultado.</td></tr>}
          </tbody>
        </table>
      </div>

      {!L.carregando && <Paginacao L={L} total={ordenados.length} onAjuda={() => L.setAjuda(true)}/>}
      {L.ajuda && <AtalhosModal onClose={() => L.setAjuda(false)} comEnter/>}
    </>
  );
}

// ════════════════════════════════════════════════════════════════════
// KPI Hero card + Toolbar (compartilhados)
// ════════════════════════════════════════════════════════════════════
function KpiHero({ l, v, s, aside, dark, tone, icon: Icon, onClick, on }) {
  const Tag = onClick ? "button" : "div";
  return (
    <Tag className={`cli-kpihero-card ${dark ? "cli-kpihero-dark" : ""} ${tone ? `cli-kpihero-tone-${tone}` : ""} ${onClick ? "cli-kpihero-clickable" : ""} ${on ? "cli-kpihero-on" : ""}`}
      aria-pressed={onClick ? !!on : undefined} onClick={onClick}>
      {Icon && <span className="cli-kpihero-icon" aria-hidden="true"><Icon size={18}/></span>}
      <span className="cli-kpihero-body">
        <span className="cli-kpihero-l">{l}</span>
        <span className="cli-kpihero-v">
          {v}
          {aside && <span className="cli-kpihero-v-aside">{aside}</span>}
        </span>
        <span className="cli-kpihero-s">{s}</span>
      </span>
    </Tag>
  );
}

function ThSort({ k, sort, onSort, num, children }) {
  const ativo = sort.key === k;
  return (
    <th className={(num ? "num " : "") + "cli-th-sort" + (ativo ? " on" : "")}>
      <button onClick={() => onSort(k)} aria-label={`Ordenar por ${children}`}>
        {children}<span className="cli-th-ic" aria-hidden="true">{ativo ? (sort.dir === "asc" ? "↑" : "↓") : "↕"}</span>
      </button>
    </th>
  );
}

function Toolbar({ searchRef, q, setQ, placeholder, filtersCount, onClear, resultCount, unidade = "registro", children }) {
  const plural = { cliente: "clientes", fornecedor: "fornecedores", funcionário: "funcionários",
    representante: "representantes", contato: "contatos", cadastro: "cadastros", registro: "registros" }[unidade] || unidade + "s";
  return (
    <div className="cli-toolbar-v2">
      <div className="cli-toolbar-search-v2">
        <I.search size={13}/>
        <input ref={searchRef} placeholder={placeholder} value={q} onChange={(e) => setQ(e.target.value)}/>
        <span className="cli-toolbar-hint"><kbd>/</kbd> pra focar · <kbd>⌘K</kbd> global</span>
      </div>
      <div className="cli-fdrop-row">
        {children}
        {filtersCount > 0 && <button className="cli-fdrop-clear" onClick={onClear}>Limpar ({filtersCount})</button>}
        <div className="cli-toolbar-count">
          {resultCount.toLocaleString("pt-BR")} {resultCount === 1 ? `${unidade} encontrado` : `${plural} ${/[ao]$/.test(unidade) ? "encontrados" : "encontrados"}`}
        </div>
      </div>
    </div>
  );
}

window.CliListPage = CliListPage;
// Peças reusadas pelo drawer 760 (cliente-drawer760.jsx → window.ClienteDrawer760).
window.CLI_SPG = CLI_SPG;
Object.assign(window, { cliGruposLer, cliGruposGravar, cliGrupoNome });
Object.assign(window, { CliRowKebab: RowKebab, cliDeriveCli: deriveCli, cliClientStats: clientStats, CliEnderecoSection, CliFrescorPill, SaldoNeg, CliAvatar: Avatar, cliFmtBRL: fmtBRL, ClienteDetailDrawerLegacy: ClienteDetailDrawer });
