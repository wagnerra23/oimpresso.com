// patrimonio-page.jsx — módulo Patrimônio (Modules/AssetManagement) no shell Cockpit V2.
// Montado nos componentes do DS: DataTablePro (grid), TabBar (sub-abas), StatusBadge,
// Button, DropdownMenu, Pagination, KpiCard, Alert, Progress, Drawer/DrawerSection,
// PlacaVeiculo, EmptyState/Skeleton/Toast (via modulo-padrao). Domínio em patrimonio-data.jsx.
// IIFE — expõe window.PatrimonioPage. CSS residual em patrimonio-page.css (escopo .ptr-root).
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const D = () => window.PatData;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const Ic = (p) => { const F = window.JcIcon; return F ? <F {...p} /> : null; };
const pct = (v, t) => (t ? Math.round((v / t) * 100) : 0);
const irPara = (rota) => { if (typeof window.__go === "function") window.__go(rota); };

// Ícones Lucide (24-grid, stroke 1.75) que o Icon do DS ainda não traz —
// wrench/pencil/trash/eye/printer/columns. Paths oficiais do Lucide, não glifos.
const PTR_ICONES = {
  plus: <><path d="M5 12h14" /><path d="M12 5v14" /></>,
  wrench: <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94z" />,
  pencil: <><path d="M21.2 6.8a2.8 2.8 0 0 0-4-4L3.8 16.2a2 2 0 0 0-.5.8l-1.3 4.4a.5.5 0 0 0 .6.6l4.4-1.3a2 2 0 0 0 .8-.5z" /><path d="m15 5 4 4" /></>,
  trash: <><path d="M3 6h18" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M10 11v6" /><path d="M14 11v6" /></>,
  eye: <><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0" /><circle cx="12" cy="12" r="3" /></>,
  download: <><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path d="m7 10 5 5 5-5" /><path d="M12 15V3" /></>,
  printer: <><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path d="M6 9V3h12v6" /><rect x="6" y="14" width="12" height="8" rx="1" /></>,
  columns: <><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M12 3v18" /><path d="M18 3v18" /></>,
};
function PtrIc({ name, size = 15 }) {
  const d = PTR_ICONES[name];
  if (!d) return null;
  return <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
    strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{d}</svg>;
}

// Botão-ícone do módulo: o Button do DS não repassa aria-label/title, então o nome
// acessível vem do conteúdo (recortado) e o tooltip do span pai. Todo ícone da tela
// passa por aqui — nenhuma chamada pode esquecer o nome.
// Dica: `title` nativo, de propósito. O Tooltip do DS é DOM posicionado e a célula do
// DataTablePro tem overflow:hidden — o balão sai cortado. O nome acessível vem do .ptr-sr.
function BotaoIcone({ icone, nome, perigo, onClick }) {
  const { Button } = DS();
  if (!Button) return null;
  return (
    <span title={nome} style={{ display: "inline-flex" }}>
      <Button size="sm" variant={perigo ? "danger" : "ghost"} icon
        onClick={(e) => { e.stopPropagation(); onClick(); }}>
        <PtrIc name={icone} />
        <span className="ptr-sr">{nome}</span>
      </Button>
    </span>
  );
}

// Altura disponível pro grid do DS (DataTablePro precisa de px, não de flex).
// Sem piso no JS: o mínimo é do CSS (.ptr-grid{min-height}) — dois pisos brigando
// fazia o filho ficar maior que o pai com overflow:hidden. clientHeight exclui a borda.
function useAltura(ref) {
  const [h, setH] = useState(300);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const medir = () => setH(el.clientHeight);
    medir();
    const ro = new ResizeObserver(medir);
    ro.observe(el);
    return () => ro.disconnect();
  }, []);
  return h;
}

// ═══════════════════ Estado do módulo ═══════════════════
function useDb(papel) {
  const P = D();
  const [bens, setBens] = useState(P.SEED_BENS);
  const [alocacoes, setAlocacoes] = useState(P.SEED_ALOCACOES);
  const [manutencoes, setManutencoes] = useState(P.SEED_MANUTENCOES);
  const [log, setLog] = useState(P.SEED_LOG);
  const quem = P.PAPEIS[papel].quem;
  const registrar = (alvo, evento, campos = []) =>
    setLog((l) => [{ id: P.proximoCodigo("LOG-", l), dia: P.iso(P.HOJE), hora: P.hhmm(new Date()), quem, alvo, evento, campos }, ...l]);
  const diff = (antes, depois) => Object.keys(P.CAMPOS_AUDITADOS)
    .filter((k) => String(antes[k]) !== String(depois[k]))
    .map((k) => ({ campo: k, de: String(antes[k]), para: String(depois[k]) }));

  return {
    bens, alocacoes, manutencoes, log,
    criarBem: (b) => { setBens((l) => [{ ...b }, ...l]); registrar(b.id, "Bem criado"); },
    editarBem: (b) => {
      const antes = bens.find((x) => x.id === b.id);
      setBens((l) => l.map((x) => (x.id === b.id ? { ...x, ...b } : x)));
      const c = diff(antes, b);
      registrar(b.id, c.length ? "Bem atualizado" : "Bem salvo sem mudança em campo auditado", c);
    },
    excluirBem: (b) => { setBens((l) => l.filter((x) => x.id !== b.id)); registrar(b.id, "Bem excluído"); },
    alocar: (a) => {
      const id = P.proximoCodigo("ALO-", alocacoes);
      setAlocacoes((l) => [{ id, por: quem, revoke: null, ...a }, ...l]);
      registrar(id, "Alocado a " + a.para + " · " + a.qtd + " un. de " + a.bem);
    },
    revogar: (aloc, r) => {
      const id = P.proximoCodigo("REV-", alocacoes.filter((x) => x.revoke).map((x) => x.revoke));
      setAlocacoes((l) => l.map((x) => (x.id === aloc.id ? { ...x, revoke: { id, por: quem, ...r } } : x)));
      registrar(aloc.id, "Alocação revogada (" + id + ") · " + r.qtd + " un. devolvida(s)");
    },
    criarManutencao: (m) => {
      const id = P.proximoCodigo("MAN-", manutencoes);
      setManutencoes((l) => [{ id, titulo: null, ...m }, ...l]);
      registrar(m.bem, "Enviado pra manutenção (" + id + ") · " + m.prestador);
    },
    concluirManutencao: (m) => {
      const titulo = "TIT-" + (4500 + manutencoes.length);
      setManutencoes((l) => l.map((x) => (x.id === m.id ? { ...x, status: "concluida", fim: P.iso(P.HOJE), titulo } : x)));
      registrar(m.bem, "Manutenção " + m.id + " concluída · título " + titulo + " no Financeiro");
    },
  };
}

// ═══════════════════ Selos de domínio (StatusBadge do DS) ═══════════════════
function SeloGarantia({ b }) {
  const { StatusBadge } = DS();
  const g = D().garantia(b);
  if (!StatusBadge) return null;
  if (g.st === "sem") return <StatusBadge kind="sla" value="—" tone="outline" label="sem garantia" />;
  if (g.st === "in") return <StatusBadge kind="sla" value="fresh" label="na garantia" />;
  if (g.st === "soon") return <StatusBadge kind="sla" value="aging" label={g.dias + " dias"} />;
  return <StatusBadge kind="sla" value="expired" label="vencida" />;
}
function SeloSituacao({ emManut }) {
  const { StatusBadge } = DS();
  if (!StatusBadge) return null;
  return emManut
    ? <StatusBadge kind="sla" value="aging" label="manutenção" />
    : <StatusBadge kind="sla" value="fresh" label="operando" />;
}
function SeloManutencao({ status }) {
  const { StatusBadge } = DS();
  if (!StatusBadge) return null;
  const M = { agendada: ["aging", "agendada"], andamento: ["late", "em manutenção"], concluida: ["fresh", "concluída"] }[status];
  return <StatusBadge kind="sla" value={M[0]} label={M[1]} />;
}
function SeloAlocacao({ a }) {
  const { StatusBadge } = DS();
  const P = D();
  if (!StatusBadge) return null;
  if (a.revoke) return <StatusBadge kind="sla" value="—" tone="neutral" label={"revogada " + P.d2(a.revoke.em)} />;
  if (a.ate && P.dias(P.HOJE, a.ate) < 0) return <StatusBadge kind="sla" value="expired" label="prazo vencido" />;
  return <StatusBadge kind="sla" value="fresh" label="em uso" />;
}

// ═══════════════════ PAINEL (asset/dashboard) ═══════════════════
function painelData(db, papel) {
  const P = D();
  const bens = db.bens.filter((b) => P.podeVerLocal(papel, b.loc));
  const bruto = bens.reduce((s, b) => s + b.valor * b.qtd, 0);
  const residual = bens.reduce((s, b) => s + P.depreciacao(b).residual, 0);
  const unidades = bens.reduce((s, b) => s + b.qtd, 0);
  const alocadas = db.alocacoes.filter((a) => !a.revoke).reduce((s, a) => s + a.qtd, 0);
  const alocaveis = bens.filter((b) => b.alocavel).reduce((s, b) => s + b.qtd, 0);
  const vencendo = bens.map((b) => ({ b, g: P.garantia(b) })).filter((x) => ["out", "soon"].includes(x.g.st)).sort((a, b) => a.g.dias - b.g.dias);
  const manut = db.manutencoes.filter((m) => m.status !== "concluida").sort((a, b) => a.ini.localeCompare(b.ini));
  const custoManut = db.manutencoes.reduce((s, m) => s + m.custo, 0);
  const porCat = Object.keys(P.CATEGORIAS).map((k) => ({
    label: P.CATEGORIAS[k], v: bens.filter((b) => b.cat === k).reduce((s, b) => s + b.valor * b.qtd, 0),
    n: bens.filter((b) => b.cat === k).reduce((s, b) => s + b.qtd, 0),
  })).filter((c) => c.n > 0).sort((a, b) => b.v - a.v);

  return {
    bruto, residual, unidades, alocadas, alocaveis, vencendo, manut, custoManut,
    kpis: [
      { label: "Patrimônio bruto", value: P.fmt(bruto), icon: "coins", sub: unidades + " unidades em " + bens.length + " bens" },
      { label: "Valor residual", value: P.fmt(residual), icon: "trendDown", sub: "depreciação linear · " + pct(bruto - residual, bruto) + "% depreciado" },
      { label: "Alocados", value: alocadas + " de " + alocaveis, icon: "target", sub: (alocaveis - alocadas) + " unidades livres pra alocar" },
      { label: "Garantia vencida ou vencendo", value: String(vencendo.length), icon: "alert", emphasize: vencendo.length > 0,
        sub: vencendo.length ? P.fmt(vencendo.filter((x) => x.g.st === "out").reduce((s, x) => s + x.b.valor * x.b.qtd, 0)) + " sem cobertura" : "tudo coberto" },
    ],
    analises: [
      { id: "cat", kind: "bars", icon: "chart", title: "Patrimônio por categoria", sub: P.fmt(bruto) + " · " + porCat.length + " categorias",
        pill: porCat[0] ? { tone: "warn", label: pct(porCat[0].v, bruto) + "% em " + porCat[0].label.toLowerCase() } : null,
        bars: porCat.map((c) => ({ label: c.label, bar: pct(c.v, bruto), pct: P.fmtK(c.v) })),
        footer: "Metade do patrimônio está em duas máquinas de impressão — parada de uma delas para a produção.",
        origem: ["Soma de valor unitário × quantidade dos bens visíveis pro seu papel, agrupada por categoria.", "Valor de aquisição, sem depreciação."] },
      { id: "gar", kind: "buckets", icon: "shield", title: "Situação da garantia", sub: bens.length + " bens cadastrados",
        buckets: [["in", "Na garantia", "var(--pos)"], ["soon", "Vence em 30 dias", "var(--warn)"], ["out", "Vencida", "var(--neg)"], ["sem", "Sem garantia", "var(--text-mute)"]]
          .map(([k, l, cor]) => { const nas = bens.filter((b) => P.garantia(b).st === k);
            return { label: l, bar: pct(nas.length, bens.length), val: nas.length + (nas.length ? " · " + P.fmtK(nas.reduce((s, b) => s + b.valor * b.qtd, 0)) : ""), color: cor }; }),
        footer: P.fmt(bens.filter((b) => P.garantia(b).st === "out").reduce((s, b) => s + b.valor * b.qtd, 0)) + " em equipamento sem cobertura — conserto sai integral do caixa.",
        origem: ["Janela start_date/end_date de asset_warranties comparada com hoje (21/08/2026).", "Bem sem registro de garantia entra em “sem garantia”, não em “vencida”."] },
      { id: "man", kind: "list", icon: "settings", title: "Manutenção em aberto", sub: "o que está fora de operação ou agendado",
        list: manut.map((m) => ({ left: P.d2(m.ini) + " · " + (db.bens.find((b) => b.id === m.bem) || {}).nome, right: P.fmt(m.custo) })),
        footer: <span className="mp-total">custo de manutenção no ano <b>{P.fmt(custoManut)}</b></span>,
        footnote: "Custo adicional vira título a pagar no Financeiro quando a manutenção fecha.",
        origem: ["asset_maintenances com status agendada ou em andamento.", "O título do Financeiro é criado no fechamento, não na abertura."] },
    ],
  };
}

function PatPainel({ db, papel, onAba }) {
  const P = D();
  const MP = window.ModuloPadrao;
  const [drill, setDrill] = useState(null);
  const d = useMemo(() => painelData(db, papel), [db.bens, db.alocacoes, db.manutencoes, papel]);
  if (!MP) return null;
  const gVenc = d.vencendo[0];
  const acoes = [
    { id: "gar", tone: "peach", icon: "shield", title: "Equipamento sem cobertura de garantia",
      sub: d.vencendo.length + " bens vencidos ou vencendo. A troca de cabeça da HP Latex em julho saiu " + P.fmt(4200) + " integral — cotar contrato antes da próxima.",
      cta: { label: "Ver bens críticos" }, aba: "bens", filtro: "garantia" },
    { id: "man", tone: "grey", icon: "settings", title: d.manut.length + " manutenções em aberto",
      sub: "Enquanto a VS-640 não volta, a fila de lona depende só da Latex.",
      cta: { label: "Ver manutenções", tone: "ghost" }, aba: "manutencoes" },
    { id: "livres", tone: "violet", icon: "target", title: (d.alocaveis - d.alocadas) + " unidades alocáveis paradas",
      sub: "Equipamento sem alocação não aparece em ficha de colaborador nenhuma — alocar é o que dá rastro de responsabilidade.",
      cta: { label: "Ver alocações", tone: "ghost" }, aba: "alocacoes" },
  ];
  return (
    <>
      <MP.Resumo quando="21/08, 09:42"
        linhas={[
          <>{db.bens.length} bens cadastrados, <b>{d.unidades} unidades</b> e {P.fmt(d.bruto)} de patrimônio bruto — {P.fmt(d.residual)} de valor residual depois da depreciação.</>,
          <>O que pesa hoje é a <b>cobertura</b>: {d.vencendo.length} bens com garantia vencida ou vencendo — sem cobertura há mais tempo, {gVenc ? gVenc.b.nome : "nenhum"} {gVenc && "(" + (gVenc.g.st === "out" ? "vencida em " + P.d2(gVenc.g.fim) : gVenc.g.dias + " dias") + ")"}. Mais caro, porém, é o risco da HP Latex: {P.fmt(4200)} por cabeça de impressão. E a VS-640 está fora de operação em manutenção preventiva.</>,
        ]}
        destaque={<>Comece pela garantia da HP Latex: sem contrato, cada conserto sai inteiro do caixa.</>}
        chips={[
          { label: "Garantia crítica", icon: "shield", tone: "warn", aba: "bens", filtro: "garantia" },
          { label: "Em manutenção", icon: "settings", aba: "manutencoes" },
          { label: "Alocados", icon: "target", aba: "alocacoes" },
          { label: "Auditoria", icon: "lock", aba: "auditoria" },
        ]}
        onChip={(c) => onAba(c.aba, c.filtro)} />
      <MP.Kpis kpis={d.kpis} />
      <MP.Secao titulo="ANÁLISES DO MÓDULO" sub="clique num card pra ver de onde vem o número" />
      <MP.Analises analises={d.analises} onDrill={setDrill} />
      <MP.Secao titulo="O QUE FAZER PRIMEIRO" icon="bulb" />
      <MP.Acoes acoes={acoes} onCta={(a) => onAba(a.aba, a.filtro)} />
      <MP.Drill item={drill} onClose={() => setDrill(null)} />
    </>
  );
}

// ═══════════════════ ABA BENS (asset/index) ═══════════════════
const COLUNAS = [
  { id: "acao", l: "Ação", req: true }, { id: "codigo", l: "Código", req: true }, { id: "img", l: "Imagem" },
  { id: "bem", l: "Bem", req: true }, { id: "categoria", l: "Categoria" }, { id: "local", l: "Local" },
  { id: "qtd", l: "Qtd" }, { id: "alocado", l: "Alocado" }, { id: "valor", l: "Valor" },
  { id: "garantia", l: "Garantia" }, { id: "situacao", l: "Situação" },
];
const COLS_PADRAO = COLUNAS.reduce((o, c) => ((o[c.id] = true), o), {});

function AbaBens({ db, papel, dense, filtro, setFiltro, busca, setBusca, sel, onSel, acoes }) {
  const P = D();
  const { DataTablePro, TabBar, Select, Button, DropdownMenu, Pagination, FilterChip, BulkBar } = DS();
  const [selecao, setSelecao] = useState([]);
  const [gridKey, setGridKey] = useState(0);
  const limparSelecao = () => { setSelecao([]); setGridKey((n) => n + 1); };
  const [cat, setCat] = useState("todas");
  const [loc, setLoc] = useState("todos");
  const [tipo, setTipo] = useState("todos");
  const [perPage, setPerPage] = useState(25);
  const [page, setPage] = useState(1);
  const [cols, setCols] = useState(() => {
    try { return { ...COLS_PADRAO, ...JSON.parse(localStorage.getItem("oimpresso.patrimonio.cols") || "{}") }; } catch (e) { return COLS_PADRAO; }
  });
  useEffect(() => { try { localStorage.setItem("oimpresso.patrimonio.cols", JSON.stringify(cols)); } catch (e) {} }, [cols]);
  useEffect(() => { setPage(1); }, [filtro, cat, loc, tipo, busca, perPage]);
  const areaRef = useRef(null);
  const altura = useAltura(areaRef);

  const visiveis = useMemo(() => db.bens.filter((b) => P.podeVerLocal(papel, b.loc)), [db.bens, papel]);
  const filtrados = useMemo(() => {
    let r = visiveis.slice();
    if (filtro === "garantia") r = r.filter((b) => ["out", "soon"].includes(P.garantia(b).st));
    if (filtro === "alocaveis") r = r.filter((b) => b.alocavel);
    if (filtro === "manutencao") r = r.filter((b) => P.emManutencao(db.manutencoes, b.id));
    if (cat !== "todas") r = r.filter((b) => b.cat === cat);
    if (loc !== "todos") r = r.filter((b) => b.loc === loc);
    if (tipo !== "todos") r = r.filter((b) => b.tipo === tipo);
    if (busca.trim()) { const q = busca.trim().toLowerCase(); r = r.filter((b) => (b.id + " " + b.nome + " " + b.modelo + " " + b.serie).toLowerCase().includes(q)); }
    return r;
  }, [visiveis, db.manutencoes, db.alocacoes, filtro, cat, loc, tipo, busca]);
  const pag = filtrados.slice((page - 1) * perPage, page * perPage);
  const soma = filtrados.reduce((s, b) => s + b.valor * b.qtd, 0);
  const locaisOpc = Object.keys(P.LOCAIS).filter((k) => P.podeVerLocal(papel, k));

  if (!DataTablePro) return null;

  const colunas = [
    cols.acao && { key: "acao", label: "Ações", width: 148, resizable: false },
    cols.codigo && { key: "codigo", label: "Código", width: 108, mono: true, sortable: true, sortValue: (r) => r.bem.id },
    cols.img && { key: "img", label: "Imagem", width: 74, resizable: false },
    cols.bem && { key: "bem", label: "Bem", width: 250, sortable: true, sortValue: (r) => r.bem.nome.toLowerCase() },
    cols.categoria && { key: "categoria", label: "Categoria", width: 132, sortable: true },
    cols.local && { key: "local", label: "Local", width: 128, sortable: true },
    cols.qtd && { key: "qtd", label: "Qtd", width: 66, align: "right", sortable: true, sortValue: (r) => r.bem.qtd },
    cols.alocado && { key: "alocado", label: "Alocado", width: 86, align: "right" },
    cols.valor && { key: "valor", label: "Valor", width: 124, align: "right", sortable: true, sortValue: (r) => r.bem.valor * r.bem.qtd },
    cols.garantia && { key: "garantia", label: "Garantia", width: 128, sortable: true, sortValue: (r) => { const g = P.garantia(r.bem); return g.dias == null ? 99999 : g.dias; } },
    cols.situacao && { key: "situacao", label: "Situação", width: 122 },
  ].filter(Boolean);

  const linhas = pag.map((b) => {
    const al = P.alocadoQtd(db.alocacoes, b.id);
    const livre = b.qtd - al;
    const emManut = P.emManutencao(db.manutencoes, b.id);
    // Ações diretas na célula: o DataTablePro corta qualquer popover (td com overflow:hidden),
    // então cada ação é um botão próprio, com nome acessível. "Ver" é o clique na linha.
    const botoes = [
      P.can(papel, "allocate") && b.alocavel && livre > 0 && { id: "aloc", ic: "plus", t: "Alocar recurso", on: () => acoes.alocar(b.id) },
      P.can(papel, "maintenance.create") && { id: "man", ic: "wrench", t: "Enviar pra manutenção", on: () => acoes.manutencao(b.id) },
      P.can(papel, "update") && { id: "edit", ic: "pencil", t: "Editar bem", on: () => acoes.editar(b) },
      P.can(papel, "delete") && { id: "del", ic: "trash", t: "Excluir bem", on: () => acoes.excluir(b), danger: true },
    ].filter(Boolean);
    return {
      id: b.id, bem: b, state: emManut ? "urgent" : undefined,
      cells: {
        acao: <span className="ptr-acoes-cel">
          {botoes.map((x) => <BotaoIcone key={x.id} icone={x.ic} nome={x.t + " — " + b.nome} perigo={x.danger} onClick={x.on} />)}
        </span>,
        codigo: b.id,
        img: b.img
          ? <BotaoIcone icone="eye" nome={"Ver imagem de " + b.nome} onClick={() => acoes.imagem(b)} />
          : <span style={{ color: "var(--text-mute)" }}>—</span>,
        bem: { primary: b.nome, sub: b.modelo + " · " + b.serie },
        categoria: P.CATEGORIAS[b.cat],
        local: P.LOCAIS[b.loc],
        qtd: b.qtd,
        alocado: b.alocavel ? <span style={{ fontFamily: "var(--font-mono)", color: al ? "var(--accent)" : "var(--text-mute)", fontWeight: al ? 600 : 400 }}>{al}</span> : <span style={{ color: "var(--text-mute)" }}>—</span>,
        valor: { primary: P.fmt(b.valor * b.qtd), sub: P.TIPOS[b.tipo].toLowerCase() },
        garantia: <SeloGarantia b={b} />,
        situacao: <SeloSituacao emManut={emManut} />,
      },
    };
  });

  const exportar = (lista) => {
    const alvo = lista || filtrados;
    const cs = colunas.filter((c) => c.key !== "acao");
    P.baixarCsv("patrimonio-bens", cs.map((c) => c.label), alvo.map((b) => cs.map((c) => ({
      codigo: b.id, img: b.img ? "sim" : "não", bem: b.nome + " · " + b.modelo, categoria: P.CATEGORIAS[b.cat], local: P.LOCAIS[b.loc],
      qtd: b.qtd, alocado: P.alocadoQtd(db.alocacoes, b.id), valor: (b.valor * b.qtd).toFixed(2).replace(".", ","),
      garantia: P.garantia(b).st, situacao: P.emManutencao(db.manutencoes, b.id) ? "manutenção" : "operando",
    }[c.key]))));
  };

  // Filtro ativo é visível e removível — o que está filtrando nunca fica escondido no select.
  const chips = [
    filtro !== "todos" && { id: "sub", label: "Recorte", value: { alocaveis: "alocáveis", garantia: "garantia crítica", manutencao: "em manutenção" }[filtro], limpar: () => setFiltro("todos") },
    cat !== "todas" && { id: "cat", label: "Categoria", value: P.CATEGORIAS[cat], limpar: () => setCat("todas") },
    loc !== "todos" && { id: "loc", label: "Local", value: P.LOCAIS[loc], limpar: () => setLoc("todos") },
    tipo !== "todos" && { id: "tipo", label: "Compra", value: P.TIPOS[tipo], limpar: () => setTipo("todos") },
    busca.trim() && { id: "q", label: "Busca", value: busca.trim(), limpar: () => setBusca("") },
  ].filter(Boolean);

  return (
    <div className="ptr-list">
      <div className="ptr-subtabs">
        {TabBar &&
          <TabBar active={filtro} onChange={setFiltro}
            tabs={[
              { key: "todos", label: "Todos", count: visiveis.length },
              { key: "alocaveis", label: "Alocáveis", count: visiveis.filter((b) => b.alocavel).length },
              { key: "garantia", label: "Garantia crítica", count: visiveis.filter((b) => ["out", "soon"].includes(P.garantia(b).st)).length },
              { key: "manutencao", label: "Em manutenção", count: visiveis.filter((b) => P.emManutencao(db.manutencoes, b.id)).length },
            ]} />}
      </div>
      <div className="ptr-toolbar">
        <div className="ptr-filtro"><Select label="Categoria" value={cat} onChange={(e) => setCat(e.target.value)}
          options={[{ value: "todas", label: "todas" }].concat(Object.keys(P.CATEGORIAS).map((k) => ({ value: k, label: P.CATEGORIAS[k] })))} /></div>
        <div className="ptr-filtro"><Select label="Local" value={loc} onChange={(e) => setLoc(e.target.value)}
          options={[{ value: "todos", label: "todos" }].concat(locaisOpc.map((k) => ({ value: k, label: P.LOCAIS[k] })))} /></div>
        <div className="ptr-filtro"><Select label="Tipo de compra" value={tipo} onChange={(e) => setTipo(e.target.value)}
          options={[{ value: "todos", label: "todas" }].concat(Object.keys(P.TIPOS).map((k) => ({ value: k, label: P.TIPOS[k] })))} /></div>
        <div className="sp" />
        {P.can(papel, "export") && <>
          <Button size="sm" variant="ghost" onClick={() => exportar()}><PtrIc name="download" /><span>CSV</span></Button>
          <Button size="sm" variant="ghost" onClick={() => window.print()}><PtrIc name="printer" /><span>Imprimir</span></Button>
        </>}
        {DropdownMenu &&
          <DropdownMenu align="end" width={200}
            trigger={({ onClick }) => <Button size="sm" variant="ghost" onClick={onClick}><PtrIc name="columns" /><span>Colunas</span></Button>}
            items={COLUNAS.map((c) => ({ id: c.id, label: (cols[c.id] ? "✓ " : "  ") + c.l, disabled: c.req,
              onSelect: () => !c.req && setCols((o) => ({ ...o, [c.id]: !o[c.id] })) }))} />}
      </div>
      {chips.length > 0 && FilterChip &&
        <div className="ptr-chips">
          {chips.map((c) => <FilterChip key={c.id} label={c.label} value={c.value} onRemove={c.limpar} />)}
          {chips.length > 1 && <button className="ptr-limpar" onClick={() => chips.forEach((c) => c.limpar())}>limpar tudo</button>}
        </div>}
      {P.locaisPermitidos(papel) !== "all" &&
        <div className="ptr-aviso-perm">Você vê só {P.locaisPermitidos(papel).map((k) => P.LOCAIS[k]).join(" e ")} — locais fora da sua permissão nem entram na contagem.</div>}
      <div className="ptr-grid" ref={areaRef}>
        <DataTablePro key={gridKey} columns={colunas} rows={linhas} height={altura} density={dense ? "compact" : "comfortable"}
          selectable onSelectionChange={setSelecao} onRowClick={(r) => onSel(r.id)} />
      </div>
      {selecao.length > 0 && BulkBar &&
        <BulkBar count={selecao.length} label={selecao.length === 1 ? "bem selecionado" : "bens selecionados"}
          onClose={limparSelecao}
          actions={[
            P.can(papel, "export") && { label: "Exportar seleção", onClick: () => { exportar(filtrados.filter((b) => selecao.includes(b.id))); limparSelecao(); } },
            P.can(papel, "maintenance.create") && { label: "Enviar pra manutenção", onClick: () => { acoes.manutencao(selecao.slice()); limparSelecao(); } },
          ].filter(Boolean)} />}
      <div className="ptr-foot">
        <b>{filtrados.length}</b>/{visiveis.length} bens · <b>{P.fmt(soma)}</b>
        <div className="sp" />
        {Pagination &&
          <Pagination page={page} pageCount={Math.max(1, Math.ceil(filtrados.length / perPage))} onChange={setPage}
            total={filtrados.length} pageSize={perPage} onPageSize={setPerPage} compact />}
      </div>
    </div>
  );
}

// ═══════════════════ ABA ALOCAÇÕES ═══════════════════
function AbaAlocacoes({ db, papel, dense, onAbrirBem, onRevogar, onAlocar }) {
  const P = D();
  const { DataTablePro, TabBar, Button, Avatar, TagChip } = DS();
  const [modo, setModo] = useState("ativas");
  const areaRef = useRef(null);
  const altura = useAltura(areaRef);
  const todas = db.alocacoes.filter((a) => { const b = db.bens.find((x) => x.id === a.bem); return b && P.podeVerLocal(papel, b.loc); });
  const rows = todas.filter((a) => (modo === "ativas" ? !a.revoke : modo === "revogadas" ? a.revoke : true));
  const podeRevogar = P.can(papel, "revoke");
  if (!DataTablePro) return null;

  // A ação vem primeiro: a tabela é mais larga que a janela e o botão primário
  // não pode nascer fora do viewport (mesma ordem de Bens).
  const colunas = [
    { key: "acao", label: "Ação", width: 108, resizable: false },
    { key: "codigo", label: "Código", width: 116, mono: true, sortable: true },
    { key: "bem", label: "Bem", width: 250, sortable: true },
    { key: "para", label: "Alocado a", width: 216, sortable: true, sortValue: (r) => r.nome },
    { key: "em", label: "Alocado em", width: 110, mono: true, sortable: true },
    { key: "ate", label: "Alocado até", width: 120, mono: true },
    { key: "qtd", label: "Qtd", width: 62, align: "right", sortable: true },
    { key: "situacao", label: "Situação", width: 150 },
  ];
  const linhas = rows.map((a) => {
    const b = db.bens.find((x) => x.id === a.bem) || {};
    return { id: a.id, nome: a.para.toLowerCase(), state: a.revoke ? "archived" : undefined, cells: {
      codigo: a.revoke ? { primary: a.id, sub: a.revoke.id } : a.id,
      bem: { primary: b.nome, sub: a.bem + " · " + a.motivo },
      para: <span className="ptr-pessoa">
        {Avatar && <Avatar name={a.para} size="sm" />}
        <span><b>{a.para}</b>{TagChip && <TagChip label={a.papel} />}</span>
      </span>,
      em: P.d2(a.em),
      ate: a.ate ? P.d2(a.ate) : <span style={{ color: "var(--text-mute)" }}>indeterminado</span>,
      qtd: a.qtd,
      situacao: <SeloAlocacao a={a} />,
      acao: !a.revoke && podeRevogar
        ? <Button size="sm" variant="ghost" onClick={(e) => { e.stopPropagation(); onRevogar(a); }}>Revogar</Button>
        : <span style={{ color: "var(--text-mute)" }}>—</span>,
    } };
  });

  return (
    <div className="ptr-list">
      <div className="ptr-subtabs">
        {TabBar &&
          <TabBar active={modo} onChange={setModo}
            tabs={[{ key: "ativas", label: "Ativas", count: todas.filter((a) => !a.revoke).length },
              { key: "revogadas", label: "Revogadas", count: todas.filter((a) => a.revoke).length },
              { key: "todas", label: "Todas", count: todas.length }]} />}
        <div className="sp" />
        {P.can(papel, "allocate") && <Button size="sm" variant="ghost" onClick={() => onAlocar(null)}>+ Alocar recurso</Button>}
      </div>
      <div className="ptr-grid" ref={areaRef}>
        <DataTablePro columns={colunas} rows={linhas} height={altura} density={dense ? "compact" : "comfortable"}
          onRowClick={(r) => { const a = rows.find((x) => x.id === r.id); if (a) onAbrirBem(a.bem); }} />
      </div>
      <div className="ptr-foot">
        <b>{todas.filter((a) => !a.revoke).reduce((s, a) => s + a.qtd, 0)}</b> unidades alocadas · {todas.filter((a) => !a.revoke).length} alocações ativas
        <div className="sp" />
        <span>Revogar devolve a unidade ao saldo e registra o código REV- na auditoria.</span>
      </div>
    </div>
  );
}

// ═══════════════════ ABA MANUTENÇÕES ═══════════════════
function AbaManutencoes({ db, papel, dense, onAbrirBem, onNova, onConcluir }) {
  const P = D();
  const { DataTablePro, KpiCard, Button, Alert } = DS();
  const areaRef = useRef(null);
  const altura = useAltura(areaRef);
  const quem = P.PAPEIS[papel].quem;
  const todas = P.can(papel, "view_all_maintenance") ? db.manutencoes : db.manutencoes.filter((m) => m.resp === quem);
  const restrito = !P.can(papel, "view_all_maintenance");
  if (!DataTablePro) return null;

  // Ação primeiro, pelo mesmo motivo de Bens e Alocações.
  const colunas = [
    { key: "financeiro", label: "Ação", width: 130, resizable: false },
    { key: "codigo", label: "Código", width: 116, mono: true, sortable: true },
    { key: "bem", label: "Bem", width: 250, sortable: true },
    { key: "prestador", label: "Prestador", width: 150, sortable: true },
    { key: "ini", label: "Enviado", width: 108, mono: true, sortable: true },
    { key: "fim", label: "Devolvido", width: 108, mono: true },
    { key: "custo", label: "Custo", width: 118, align: "right", sortable: true, sortValue: (r) => r.custoN },
    { key: "situacao", label: "Situação", width: 140 },
  ];
  const linhas = todas.map((m) => ({
    id: m.id, custoN: m.custo, mrec: m, state: m.status === "andamento" ? "urgent" : undefined,
    cells: {
      codigo: { primary: m.id, sub: "resp. " + m.resp },
      bem: { primary: (db.bens.find((b) => b.id === m.bem) || {}).nome || m.bem, sub: m.notas },
      prestador: m.prestador,
      ini: P.d2(m.ini),
      fim: m.fim ? P.d2(m.fim) : <span style={{ color: "var(--text-mute)" }}>—</span>,
      custo: P.fmt(m.custo),
      situacao: <SeloManutencao status={m.status} />,
      financeiro: m.titulo
        ? <Button size="sm" variant="ghost" onClick={(e) => { e.stopPropagation(); irPara("financeiro"); }}>{m.titulo}</Button>
        : P.can(papel, "maintenance.create")
          ? <Button size="sm" variant="primary" onClick={(e) => { e.stopPropagation(); onConcluir(m); }}>Concluir</Button>
          : <span style={{ color: "var(--text-mute)" }}>—</span>,
    },
  }));

  return (
    <div className="ptr-list">
      <div className="ptr-kpis">
        {KpiCard && <>
          <KpiCard label="Em aberto" value={todas.filter((m) => m.status !== "concluida").length} tone={todas.some((m) => m.status === "andamento") ? "warning" : "default"} description="bens fora de operação ou agendados" />
          <KpiCard label="Custo no ano" value={P.fmt(todas.reduce((s, m) => s + m.custo, 0))} description="vira título a pagar no fechamento" />
          <KpiCard label="Maior conserto" value={P.fmt(todas.length ? Math.max(...todas.map((m) => m.custo)) : 0)} tone="danger" description="fora de garantia, custo integral" />
        </>}
      </div>
      {restrito && Alert &&
        <div style={{ marginBottom: 10 }}>
          <Alert tone="info" title="Você vê só as suas manutenções">Seu papel tem asset.view_own_maintenance — a lista mostra apenas onde você é o responsável.</Alert>
        </div>}
      <div className="ptr-grid" ref={areaRef}>
        <DataTablePro columns={colunas} rows={linhas} height={altura} density={dense ? "compact" : "comfortable"}
          onRowClick={(r) => { const m = todas.find((x) => x.id === r.id); if (m) onAbrirBem(m.bem); }} />
      </div>
      <div className="ptr-foot">
        Concluir a manutenção fecha a OS e cria o título a pagar no Financeiro.
        <div className="sp" />
        {P.can(papel, "maintenance.create") && <Button size="sm" variant="ghost" onClick={() => onNova(null)}>+ Enviar bem pra manutenção</Button>}
      </div>
    </div>
  );
}

// ═══════════════════ ABA AUDITORIA (LogsActivity) ═══════════════════
function AbaAuditoria({ db }) {
  const P = D();
  const { Alert, PeriodBar, EmptyState } = DS();
  const [per, setPer] = useState({ from: null, to: null, preset: null });
  // Presets ancorados no HOJE do módulo (21/08/2026), não no hoje do navegador —
  // senão "Dia" filtra um dia em que o protótipo não tem dado.
  const janela = (dias) => () => { const fim = P.dataLocal(P.iso(P.HOJE)); const ini = P.dataLocal(P.iso(P.HOJE)); ini.setDate(ini.getDate() - dias); return { from: ini, to: fim }; };
  const presets = [
    { id: "dia", label: "Dia", range: janela(0) },
    { id: "semana", label: "Semana", range: janela(7) },
    { id: "mes", label: "Mês", range: janela(30) },
  ];
  const de = per.from ? P.iso(per.from) : null;
  const ate = per.to ? P.iso(per.to) : null;
  const eventos = db.log.filter((l) => (!de || l.dia >= de) && (!ate || l.dia <= ate));
  return (
    <div className="ptr-cfg">
      <section>
        <h3>Trilha de auditoria</h3>
        {Alert &&
          <Alert tone="info" title="Append-only · log assetmanagement.asset">
            Só campos da whitelist do módulo entram. <b>Descrição não é auditada</b> — pode carregar dado pessoal (LGPD).
          </Alert>}
        {PeriodBar &&
          <div className="ptr-periodo">
            <PeriodBar value={per} onChange={setPer} presets={presets} label="Período do evento" />
          </div>}
        {eventos.length === 0 && EmptyState &&
          <EmptyState variant="no-results" title="Nenhum evento no período"
            description="A trilha é append-only: nada foi apagado — só não houve alteração auditada nessas datas." />}
        <div className="ptr-log" style={{ marginTop: 12 }}>
          {eventos.map((l) => (
            <div key={l.id} className="ptr-log-item">
              <div className="h"><b>{l.evento}</b><span className="mono">{l.alvo}</span></div>
              <small className="mono">{P.logQuando(l)} · {l.quem} · {l.id}</small>
              {l.campos && l.campos.length > 0 &&
                <ul className="ptr-log-campos">
                  {l.campos.map((c, i) => <li key={i}><b>{P.CAMPO_LABEL[c.campo] || c.campo}</b> <span className="mono">({P.CAMPOS_AUDITADOS[c.campo]})</span>: {c.de} → <b>{c.para}</b></li>)}
                </ul>}
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}

// ═══════════════════ ABA CONFIGURAÇÕES ═══════════════════
function AbaConfig({ papel, avisar }) {
  const P = D();
  const { Input, Switch, Button } = DS();
  const [pref, setPref] = useState({ asset: "PAT-", alloc: "ALO-", revoke: "REV-" });
  const [notif, setNotif] = useState({ garantia: true, manut: true, aloc: false });
  const pode = P.can(papel, "settings");
  if (!pode) {
    const MP = window.ModuloPadrao || {};
    return <div>{MP.Estado && <MP.Estado titulo="Configuração é do gestor do patrimônio"
      descricao="Seu papel não tem a permissão asset.settings. Prefixos e notificações mudam a numeração e o disparo de e-mail de toda a empresa — por isso ficam com quem responde pelo módulo." />}</div>;
  }
  return (
    <div className="ptr-cfg">
      <section>
        <h3>Prefixos de código</h3>
        <p>Cada sequência é por empresa. Mudar o prefixo não renumera o que já existe.</p>
        <div className="ptr-cfg-grid">
          <Input label="Prefixo do código do ativo" value={pref.asset} help="PAT-0012 é o próximo" onChange={(e) => setPref((o) => ({ ...o, asset: e.target.value }))} />
          <Input label="Prefixo do código de alocação" value={pref.alloc} help="ALO-0032 é o próximo" onChange={(e) => setPref((o) => ({ ...o, alloc: e.target.value }))} />
          <Input label="Prefixo do código de revogação" value={pref.revoke} help="REV-0013 é o próximo" onChange={(e) => setPref((o) => ({ ...o, revoke: e.target.value }))} />
        </div>
      </section>
      <section>
        <h3>Notificações</h3>
        <p>Disparadas pelo módulo — chegam por e-mail e no sino do sistema.</p>
        <div className="ptr-cfg-switches">
          <Switch checked={notif.garantia} onChange={(v) => setNotif((o) => ({ ...o, garantia: v }))}
            label="Garantia expirando em 30 dias" sublabel="E-mail pro responsável quando a janela entra no último mês." />
          <Switch checked={notif.manut} onChange={(v) => setNotif((o) => ({ ...o, manut: v }))}
            label="Bem enviado ou devolvido de manutenção" sublabel="Notifica quem alocou o bem — AssetSentForMaintenance / AssetAssignedForMaintenance." />
          <Switch checked={notif.aloc} onChange={(v) => setNotif((o) => ({ ...o, aloc: v }))}
            label="Alocação registrada pro colaborador" sublabel="Avisa o colaborador que passou a responder pelo bem." />
        </div>
      </section>
      <div className="ptr-cfg-acoes">
        <Button variant="primary" onClick={() => avisar("Configuração de patrimônio salva neste protótipo — sem gravar no banco.", "ok")}>Salvar configurações</Button>
        <span>Retenção do histórico de alocação: 5 anos (Config/retention.php).</span>
      </div>
    </div>
  );
}

// ═══════════════════ DRAWER DO BEM (PT-02 do DS) ═══════════════════
function BemDrawer({ b, db, papel, close, acoes }) {
  const P = D();
  const { Drawer, DrawerSection, TabBar, Button, Alert, Progress, PlacaVeiculo, Breadcrumb, Chart } = DS();
  const [tab, setTab] = useState("resumo");
  const g = P.garantia(b);
  const dep = P.depreciacao(b);
  const alocs = db.alocacoes.filter((a) => a.bem === b.id);
  const mans = db.manutencoes.filter((m) => m.bem === b.id);
  const logs = db.log.filter((l) => l.alvo === b.id || alocs.some((a) => a.id === l.alvo));
  const livre = b.qtd - P.alocadoQtd(db.alocacoes, b.id);
  if (!Drawer) return null;
  const F = ({ l, v, mono }) => <div className="f"><label>{l}</label><span className={mono ? "mono" : ""}>{v}</span></div>;
  const LINKS = [
    { l: "Financeiro", s: mans.filter((m) => m.titulo).length + " títulos de manutenção", r: "financeiro" },
    { l: "HRM", s: alocs.filter((a) => !a.revoke).length + " ficha(s) de colaborador com este bem", r: "hrm" },
    ...(b.placa ? [{ l: "Oficina Auto", s: "OS pela placa " + b.placa, r: "oficina-os" }] : []),
    { l: "BI · Relatórios", s: "patrimônio e depreciação por categoria", r: "relatorios" },
  ];

  return (
    <Drawer open onClose={close} width={720} badge={<SeloGarantia b={b} />}
      title={b.nome} subtitle={b.id + " · " + P.CATEGORIAS[b.cat] + " · " + b.modelo + " · série " + b.serie + " · " + P.LOCAIS[b.loc] + " · " + P.TIPOS[b.tipo]}
      footer={<>
        {P.can(papel, "allocate") && b.alocavel && livre > 0 && <Button variant="ghost" onClick={() => acoes.alocar(b.id)}>Alocar recurso</Button>}
        {P.can(papel, "maintenance.create") && <Button variant="ghost" onClick={() => acoes.manutencao(b.id)}>Enviar pra manutenção</Button>}
        {P.can(papel, "update")
          ? <Button variant="primary" onClick={() => acoes.editar(b)}>Editar bem</Button>
          : <span style={{ fontSize: 11.5, color: "var(--text-mute)", alignSelf: "center" }}>Seu papel não edita bens.</span>}
      </>}>
      {Breadcrumb &&
        <div className="ptr-trilha">
          <Breadcrumb items={[{ label: "Patrimônio" }, { label: "Bens" }, { label: b.id }]} />
        </div>}
      <div className="ptr-drawer-nav">
        {TabBar &&
          <TabBar active={tab} onChange={setTab}
            tabs={[{ key: "resumo", label: "Resumo" }, { key: "garantia", label: "Garantia" },
              { key: "alocacoes", label: "Alocações", count: alocs.length }, { key: "manutencao", label: "Manutenção", count: mans.length },
              { key: "depreciacao", label: "Depreciação" }, { key: "historico", label: "Histórico", count: logs.length }]} />}
      </div>

      {tab === "resumo" && <>
        <DrawerSection title="Identificação">
          <div className="ptr-fields">
            <F l="Código do ativo" v={b.id} mono /><F l="Série/Modelo" v={b.modelo} />
            <F l="Número de série" v={b.serie} mono /><F l="Categoria" v={P.CATEGORIAS[b.cat]} />
            <F l="Local" v={P.LOCAIS[b.loc]} /><F l="Tipo de compra" v={P.TIPOS[b.tipo]} />
            <F l="Data da compra" v={P.d2(b.compra)} mono /><F l="É atribuível?" v={b.alocavel ? "sim — pode ser alocado" : "não"} />
            <F l="Valor unitário" v={P.fmt(b.valor)} mono /><F l="Valor de aquisição" v={P.fmt(b.valor * b.qtd)} mono />
          </div>
        </DrawerSection>
        {b.placa &&
          <DrawerSection title="Placa">
            <div className="ptr-placa">
              {PlacaVeiculo ? <PlacaVeiculo placa={b.placa} uf="SP" padrao="mercosul" size="md" categoria="comercial" /> : <span className="mono">{b.placa}</span>}
              <small>A OS da Oficina puxa a mesma placa — patrimônio e manutenção veicular falam do mesmo veículo.</small>
            </div>
          </DrawerSection>}
        <DrawerSection title="Descrição">{b.desc}</DrawerSection>
        <DrawerSection title="Onde este bem aparece">
          <div className="ptr-links">
            {LINKS.map((k) => <button key={k.r} className="ptr-link-card" onClick={() => irPara(k.r)}><b>{k.l}</b><small>{k.s}</small><span>→</span></button>)}
          </div>
        </DrawerSection>
      </>}

      {tab === "garantia" &&
        <DrawerSection title="Garantia">
          {b.garantia ? <>
            {Alert &&
              <Alert tone={g.st === "in" ? "success" : g.st === "soon" ? "warn" : "danger"}
                title={g.st === "in" ? "Na garantia" : g.st === "soon" ? "Vence em " + g.dias + " dias" : "Garantia vencida"}>
                {P.d2(b.garantia.ini)} → {P.d2(b.garantia.fim)} · {b.garantia.fornec}
              </Alert>}
            <div className="ptr-fields" style={{ marginTop: 12 }}>
              <F l="Início" v={P.d2(b.garantia.ini)} mono /><F l="Fim" v={P.d2(b.garantia.fim)} mono />
              <F l="Meses de garantia" v={Math.round(P.dias(b.garantia.ini, b.garantia.fim) / 30.44) + " meses"} />
              <F l="Dias restantes" v={g.dias >= 0 ? g.dias + " dias" : "vencida há " + Math.abs(g.dias) + " dias"} mono />
            </div>
          </> : Alert && <Alert tone="warn" title="Bem sem garantia registrada">
            Sem janela início/fim, todo conserto é custo integral — cadastrar a garantia é o que faz o aviso de 30 dias funcionar.
          </Alert>}
        </DrawerSection>}

      {tab === "alocacoes" &&
        <DrawerSection title="Alocações">
          {b.alocavel ? <>
            <div className="ptr-saldo">
              <span><small>quantidade</small><b>{b.qtd}</b></span>
              <span><small>alocada</small><b>{P.alocadoQtd(db.alocacoes, b.id)}</b></span>
              <span className="ok"><small>livre</small><b>{livre}</b></span>
            </div>
            {alocs.map((a) =>
              <div key={a.id} className={"ptr-aloc" + (a.revoke ? " off" : "")}>
                <div>
                  <b>{a.para}</b>
                  <small>{a.papel} · {a.qtd} un. · {P.d2(a.em)}{a.ate ? " → " + P.d2(a.ate) : " · indeterminado"}</small>
                  <small className="mono">{a.id}{a.revoke ? " · revogada " + P.d2(a.revoke.em) + " (" + a.revoke.id + ")" : ""}</small>
                </div>
                {!a.revoke && P.can(papel, "revoke") && <Button size="sm" variant="ghost" onClick={() => acoes.revogar(a)}>Revogar</Button>}
              </div>)}
            {alocs.length === 0 && Alert && <Alert tone="info" title="Nenhuma alocação registrada">Alocar é o que liga a unidade a um responsável.</Alert>}
          </> : Alert && <Alert tone="info" title="Bem não atribuível">Fica no local, não vai pra mão de colaborador.</Alert>}
        </DrawerSection>}

      {tab === "manutencao" &&
        <DrawerSection title="Manutenções">
          {mans.length ? mans.map((m) =>
            <div key={m.id} className="ptr-man">
              <div className="h"><b>{m.prestador}</b><SeloManutencao status={m.status} /></div>
              <small className="mono">{m.id} · {P.d2(m.ini)}{m.fim ? " → " + P.d2(m.fim) : " → em aberto"} · custo {P.fmt(m.custo)}{m.titulo ? " · " + m.titulo : ""}</small>
              <p>{m.notas}</p>
            </div>) : Alert && <Alert tone="info" title="Sem manutenção registrada">Nenhum envio pra manutenção neste bem.</Alert>}
        </DrawerSection>}

      {tab === "depreciacao" &&
        <DrawerSection title="Depreciação">
          {Progress && <Progress value={dep.pct} label={"Depreciado · linear " + b.dep + " anos"} showValue tone={dep.pct > 80 ? "warn" : "accent"} />}
          {Chart &&
            <div className="ptr-curva">
              <span className="ptr-curva-lb">Valor residual ano a ano</span>
              <Chart type="line" height={110} formatValue={(v) => P.fmt(v)}
                data={Array.from({ length: b.dep + 1 }, (_, i) => ({ label: "ano " + i, value: Math.max(0, dep.bruto * (1 - i / b.dep)) }))} />
            </div>}
          <div className="ptr-fields" style={{ marginTop: 12 }}>
            <F l="Meses decorridos" v={dep.meses + " de " + dep.total} mono />
            <F l="Valor de aquisição" v={P.fmt(dep.bruto)} mono />
            <F l="Valor residual" v={P.fmt(dep.residual)} mono />
            <F l="Depreciação acumulada" v={P.fmt(dep.bruto - dep.residual)} mono />
          </div>
          <small className="ptr-nota">Regra do protótipo: prazo do cadastro do bem (padrão da categoria). A regra fiscal ainda não tem ADR — decidir antes de virar número contábil.</small>
        </DrawerSection>}

      {tab === "historico" &&
        <DrawerSection title="Histórico auditado">
          <div className="ptr-log">
            {logs.map((l) =>
              <div key={l.id} className="ptr-log-item">
                <div className="h"><b>{l.evento}</b><span className="mono">{l.alvo}</span></div>
                <small className="mono">{P.logQuando(l)} · {l.quem}</small>
                {l.campos && l.campos.length > 0 &&
                  <ul className="ptr-log-campos">{l.campos.map((c, i) => <li key={i}><b>{P.CAMPO_LABEL[c.campo] || c.campo}</b>: {c.de} → <b>{c.para}</b></li>)}</ul>}
              </div>)}
            {logs.length === 0 && Alert && <Alert tone="info" title="Sem eventos auditados">Nada foi alterado neste bem ainda.</Alert>}
          </div>
        </DrawerSection>}
    </Drawer>
  );
}

// ═══════════════════ SHELL DO MÓDULO ═══════════════════
function PatrimonioPage({ view, dense, estado = "dados", toque, papel: papelProp }) {
  const P = D();
  const MP = window.ModuloPadrao || {};
  const FORMS = window.PatForms || {};
  const { Button } = DS();
  const inicial = { "pat-bens": "bens", "pat-alocacoes": "alocacoes", "pat-manutencao": "manutencoes", "pat-config": "config" }[view];
  const [aba, setAba] = (MP.useAba || ((k, i) => useState(i)))("oimpresso.patrimonio.aba", inicial || "painel");
  const papel = P.PAPEIS[papelProp] ? papelProp : "gestor";
  const [avisoNode, avisar] = (MP.useAviso || (() => [null, () => {}]))();
  const db = useDb(papel);
  const [filtro, setFiltro] = useState("todos");
  const [busca, setBusca] = useState("");
  const [sel, setSel] = useState(null);
  const [modal, setModal] = useState(null);
  const [hora, setHora] = useState("09:42");
  useEffect(() => { if (inicial) setAba(inicial); }, [view]);

  const irAba = (a, f) => { if (f) setFiltro(f); if (a) setAba(a); };
  const abrirBem = (id) => { setAba("bens"); setSel(id); };
  const vazio = estado === "vazio";
  const dbv = vazio ? { ...db, bens: [], alocacoes: [], manutencoes: [], log: [] } : db;
  const selecionado = dbv.bens.find((b) => b.id === sel);
  const vencendo = dbv.bens.filter((b) => P.podeVerLocal(papel, b.loc) && ["out", "soon"].includes(P.garantia(b).st)).length;

  const acoes = {
    novo: () => setModal({ t: "bem", modo: "novo" }),
    editar: (b) => setModal({ t: "bem", modo: "editar", bem: b }),
    excluir: (b) => setModal({ t: "excluir", bem: b }),
    alocar: (bemId) => setModal({ t: "alocar", bemId }),
    revogar: (a) => setModal({ t: "revogar", aloc: a }),
    // bemId aceita um id ou uma lista (ação em lote da BulkBar).
    manutencao: (bemId) => setModal({ t: "manutencao", bemId: Array.isArray(bemId) ? null : bemId, bemIds: Array.isArray(bemId) ? bemId : null }),
    imagem: (b) => avisar("A imagem de " + b.id + " abre em aba nova a partir do media do bem."),
  };

  return (
    <div className={"ptr-root mp-page" + (dense ? " ptr-dense" : "") + (toque === "tablet" ? " ptr-toque" : "")} data-screen-label="01 Patrimônio">
      {MP.Header &&
        <MP.Header modulo="Patrimônio" papel={P.PAPEIS[papel].label}
          contexto={["OFFICEIMPRESSO", P.locaisPermitidos(papel) === "all" ? "todos os locais" : P.locaisPermitidos(papel).map((k) => P.LOCAIS[k]).join(" · "), db.bens.length + " bens"]}
          atualizadoAs={hora}
          onRefresh={() => { setHora(new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })); avisar("Reapurado agora — patrimônio, garantias e alocações.", "ok"); }}
          glyph={<Ic name="database" />}
          acoes={<>
            <div className="mp-busca">
              <span>⌕</span>
              <input placeholder="Buscar bem, código, série..." value={busca} onChange={(e) => { setBusca(e.target.value); setAba("bens"); }} />
              <kbd>/</kbd>
            </div>
            {Button && P.can(papel, "allocate") && <Button variant="ghost" onClick={() => acoes.alocar(null)}>Alocar recurso</Button>}
            {Button && P.can(papel, "create") && <Button variant="primary" onClick={acoes.novo}>Adicionar recurso</Button>}
          </>} />}
      {MP.Tabs &&
        <MP.Tabs tab={aba} onTab={setAba} aria="Áreas de Patrimônio"
          tabs={[
            { key: "painel", label: "Painel", icon: "chart" },
            { key: "bens", label: "Bens", icon: "list", n: dbv.bens.filter((b) => P.podeVerLocal(papel, b.loc)).length },
            { key: "alocacoes", label: "Alocações", icon: "target", n: dbv.alocacoes.filter((a) => !a.revoke).length },
            { key: "manutencoes", label: "Manutenções", icon: "settings", n: dbv.manutencoes.filter((m) => m.status !== "concluida").length },
            { key: "garantias", label: "Garantias", icon: "shield", n: vencendo },
            { key: "auditoria", label: "Auditoria", icon: "lock", n: dbv.log.length },
            { key: "config", label: "Configurações", icon: "settings" },
          ]} />}

      {estado === "carregando" && MP.Skeleton && <MP.Skeleton />}
      {estado === "erro" && MP.Estado &&
        <div className="mp-body">
          <MP.Estado erro titulo="Não consegui carregar o patrimônio"
            descricao="A consulta de bens, alocações e manutenções falhou. Nada foi perdido — o que você via continua no banco. Tente reapurar; se insistir, o módulo pode estar sem permissão de assinatura (assetmanagement_module)."
            acao={Button ? <Button variant="ghost" onClick={() => avisar("Reapurando… fora deste protótipo a consulta seria refeita.")}>Tentar de novo</Button> : null} />
        </div>}
      {vazio && aba === "painel" && MP.Estado &&
        <div className="mp-body">
          <MP.Estado titulo="Nenhum bem cadastrado ainda"
            descricao="O patrimônio começa pelo que já está na casa: as duas impressoras, o plotter, a Fiorino. Cadastre um bem e o painel passa a mostrar valor, garantia e alocação."
            acao={Button && P.can(papel, "create") ? <Button variant="primary" onClick={acoes.novo}>Adicionar o primeiro recurso</Button> : null} />
        </div>}

      {estado === "dados" && aba === "painel" && <div className="mp-body"><PatPainel db={db} papel={papel} onAba={irAba} /></div>}
      {estado !== "carregando" && estado !== "erro" && <>
        {aba === "bens" && <AbaBens db={dbv} papel={papel} dense={dense} filtro={filtro} setFiltro={setFiltro} busca={busca} setBusca={setBusca} sel={sel} onSel={setSel} acoes={acoes} />}
        {aba === "alocacoes" && <AbaAlocacoes db={dbv} papel={papel} dense={dense} onAbrirBem={abrirBem} onRevogar={acoes.revogar} onAlocar={acoes.alocar} />}
        {aba === "manutencoes" && <AbaManutencoes db={dbv} papel={papel} dense={dense} onAbrirBem={abrirBem} onNova={acoes.manutencao}
          onConcluir={(m) => { db.concluirManutencao(m); avisar("Manutenção " + m.id + " concluída — título criado no Financeiro.", "ok"); }} />}
        {aba === "garantias" && <div className="mp-body">
          {MP.Estado && <MP.Estado titulo="A garantia mora dentro do bem"
            descricao="O módulo real não tem tela própria de garantia: a janela início/fim vive em asset_warranties, dentro da ficha do bem. Aqui o atalho é a lista já filtrada pela garantia crítica."
            acao={Button ? <Button variant="ghost" onClick={() => irAba("bens", "garantia")}>Ver os {vencendo} bens com garantia crítica</Button> : null} />}
        </div>}
        {aba === "auditoria" && <div className="mp-body"><AbaAuditoria db={dbv} /></div>}
        {aba === "config" && <div className="mp-body"><AbaConfig papel={papel} avisar={avisar} /></div>}
      </>}

      {selecionado && !modal && <BemDrawer b={selecionado} db={db} papel={papel} close={() => setSel(null)} acoes={acoes} />}

      {modal && modal.t === "bem" && FORMS.BemForm &&
        <FORMS.BemForm modo={modal.modo} bem={modal.bem} bens={db.bens} papel={papel} onClose={() => setModal(null)}
          onSalvar={(b, novo) => { novo ? db.criarBem(b) : db.editarBem(b); setModal(null); setAba("bens"); avisar(novo ? "Bem " + b.id + " cadastrado." : "Bem " + b.id + " atualizado — mudanças na trilha de auditoria.", "ok"); }} />}
      {modal && modal.t === "alocar" && FORMS.AlocarForm &&
        <FORMS.AlocarForm bemId={modal.bemId} bens={db.bens} alocacoes={db.alocacoes} papel={papel} onClose={() => setModal(null)}
          onSalvar={(a) => { db.alocar(a); setModal(null); setAba("alocacoes"); avisar("Alocado " + a.qtd + " un. para " + a.para + ".", "ok"); }} />}
      {modal && modal.t === "revogar" && FORMS.RevogarForm &&
        <FORMS.RevogarForm alocacao={modal.aloc} bens={db.bens} alocacoes={db.alocacoes} onClose={() => setModal(null)}
          onSalvar={(a, r) => { db.revogar(a, r); setModal(null); avisar("Alocação " + a.id + " revogada — " + r.qtd + " un. de volta ao saldo.", "ok"); }} />}
      {modal && modal.t === "manutencao" && FORMS.ManutencaoForm &&
        <FORMS.ManutencaoForm bemId={modal.bemId} bemIds={modal.bemIds} bens={db.bens} manutencoes={db.manutencoes} papel={papel} onClose={() => setModal(null)}
          onSalvar={(m) => {
            const alvos = modal.bemIds && modal.bemIds.length ? modal.bemIds : [m.bem];
            alvos.forEach((id) => db.criarManutencao({ ...m, bem: id }));
            setModal(null); setAba("manutencoes");
            avisar(alvos.length > 1 ? alvos.length + " bens enviados pra " + m.prestador + "." : "Manutenção registrada em " + m.prestador + ".", "ok");
          }} />}
      {modal && modal.t === "excluir" && FORMS.ExcluirModal &&
        <FORMS.ExcluirModal bem={modal.bem} alocacoes={db.alocacoes} onClose={() => setModal(null)}
          onConfirmar={(b) => { db.excluirBem(b); setModal(null); setSel(null); avisar("Bem " + b.id + " excluído — a trilha de auditoria permanece.", "warn"); }} />}
      <div className="ptr-aviso-live" role="status" aria-live="polite" aria-atomic="true">{avisoNode}</div>
    </div>
  );
}

window.PatrimonioPage = PatrimonioPage;
})();
