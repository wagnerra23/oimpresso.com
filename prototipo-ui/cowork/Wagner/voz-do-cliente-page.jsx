// voz-do-cliente-page.jsx — Voz do Cliente (Modules/VozDoCliente · /voz-do-cliente).
// Espelho do blade vivo Resources/views/caixa.blade.php: Quando · Quem · O que disse · Onde
// (url_vista) · Grav. (severidade) · Situação (pending → Pendente · triaged → Triado [· US] ·
// closed → Fechado). Copy do vazio é literal do blade.
// ONDA O5: triagem MANUAL (nunca automática) — o relato pendente vira US do backlog ou fecha com
// motivo. Nada é notificado nem escalado sozinho: priorizar é decisão de produto.
// ONDA O2/O6: estado (dados/vazio/carregando/erro) · papel · densidade por Tweak.
// Expõe window.VozDoClientePage.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const A = () => window.AcessosDS || {};

const p2 = (n) => String(n).padStart(2, "0");
const dt = (d) => `${p2(d.getDate())}/${p2(d.getMonth() + 1)}/${d.getFullYear()} ${p2(d.getHours())}:${p2(d.getMinutes())}`;
const AGORA = new Date(2026, 7, 24, 10, 40);
const atras = (h) => { const d = new Date(AGORA); d.setHours(d.getHours() - h); return d; };
const idade = (d) => { const h = Math.round((AGORA - d) / 36e5); if (h < 1) return "agora"; if (h < 24) return `há ${h} h`; const x = Math.round(h / 24); return x === 1 ? "há 1 dia" : `há ${x} dias`; };
const frescor = (d) => { const h = (AGORA - d) / 36e5; return h <= 24 ? "recente" : h <= 48 ? "fresc" : h <= 96 ? "frio" : "distante"; };

const INICIAIS = [
  { id: 91, at: atras(2), autor: "Larissa Prado", texto: "O total da venda mudou depois que troquei o cliente — precisei refazer a linha do banner.", vista: "/sells/create", sev: "alta", status: "pending" },
  { id: 90, at: atras(5), autor: "Eliana Souza", texto: "Baixa de boleto pago não aparece no extrato no mesmo dia. Fico sem saber se entrou.", vista: "/financeiro/receber", sev: "alta", status: "triaged", us: "US-FIN-114" },
  { id: 89, at: atras(9), autor: "Larissa Prado", texto: "Na busca de cliente, digitar CNPJ com ponto não acha ninguém.", vista: "/contacts?type=all", sev: "média", status: "triaged", us: "US-CLI-042" },
  { id: 88, at: atras(26), autor: "Marcos Vinícius", texto: "No tablet da oficina o botão de iniciar execução fica atrás do teclado.", vista: "/oficina-auto/os/8815", sev: "média", status: "pending" },
  { id: 87, at: atras(31), autor: null, texto: "Etiqueta saiu com o preço cortado na direita.", vista: "/vestuario/etiquetas", sev: "baixa", status: "pending" },
  { id: 86, at: atras(52), autor: "Wagner Ramos", texto: "Relatório de estoque demora uns 20 segundos com filtro de local vazio.", vista: "/reports/stock-report", sev: "baixa", status: "closed", motivo: "resolvido no índice da query" },
  { id: 85, at: atras(74), autor: "Larissa Prado", texto: "Não consigo imprimir a OS sem passar pelo orçamento antes.", vista: "/os/1187", sev: "média", status: "closed", motivo: "é o fluxo desenhado — virou treinamento" },
  { id: 84, at: atras(96), autor: "Eliana Souza", texto: "A conciliação some do menu quando entro pela empresa da matriz.", vista: "/financeiro/conciliacao", sev: "alta", status: "closed", motivo: "duplicado do 79" },
];

const FILTROS = [
  { id: "todos", label: "Todos" },
  { id: "pending", label: "Pendentes" },
  { id: "triaged", label: "Triados" },
  { id: "closed", label: "Fechados" },
];
const SEV = { alta: { t: "danger", l: "Alta" }, "média": { t: "warning", l: "Média" }, baixa: { t: "neutral", l: "Baixa" } };

function VozDoClientePage({ estado = "dados", papel = "produto", dense = false }) {
  const { PageHeader, DataTable, StatusBadge, Tooltip, Skeleton, Input, Select } = DS();
  const { Vazio, Kpis, Kpi, Confirm } = A();
  const [filtro, setFiltro] = useState("todos");
  const [lista, setLista] = useState(INICIAIS);
  const [triar, setTriar] = useState(null);
  const [us, setUs] = useState("");
  const [destino, setDestino] = useState("us");
  const [aviso, setAviso] = useState(null);

  const base = estado === "vazio" ? [] : lista;
  const filtrada = useMemo(() => filtro === "todos" ? base : base.filter((s) => s.status === filtro), [filtro, base]);
  const cont = (st) => base.filter((s) => s.status === st).length;
  const podeTriar = papel === "produto";
  const cls = "os-page vdc-page" + (dense ? " dense" : "");

  const fala = (t) => { setAviso(t); setTimeout(() => setAviso(null), 5000); };
  const aplicar = () => {
    const s = triar;
    setLista((l) => l.map((x) => x.id !== s.id ? x : destino === "us"
      ? { ...x, status: "triaged", us: us.trim() || "US-NOVA-001" }
      : { ...x, status: "closed", motivo: us.trim() || "sem ação" }));
    setTriar(null); setUs("");
    fala(destino === "us" ? `Relato #${s.id} triado para ${us.trim() || "US-NOVA-001"}.` : `Relato #${s.id} fechado — nada foi notificado a quem relatou.`);
  };

  const colunas = [
    { key: "quando", label: "Quando", width: 190 },
    { key: "quem", label: "Quem", width: 150 },
    { key: "disse", label: "O que disse" },
    { key: "onde", label: "Onde", width: 210 },
    { key: "sev", label: "Grav.", width: 90 },
    { key: "situacao", label: "Situação", width: 170 },
    { key: "acao", label: "", width: 110, align: "right" },
  ];
  const linhas = filtrada.map((s) => ({
    id: s.id,
    state: s.status === "pending" && s.sev === "alta" ? "urgent" : s.status === "closed" ? "archived" : undefined,
    cells: {
      quando: <span className="vdc-quando"><b className="mono">{dt(s.at)}</b>{StatusBadge
        ? <StatusBadge kind="frescor" value={frescor(s.at)} rel={idade(s.at)} />
        : <small>{idade(s.at)}</small>}</span>,
      quem: s.autor || <span className="vdc-anon">—</span>,
      disse: <span className="vdc-texto">{s.texto}</span>,
      onde: <code className="vdc-vista">{s.vista}</code>,
      sev: StatusBadge ? <StatusBadge tone={SEV[s.sev].t} label={SEV[s.sev].l} /> : <span>{SEV[s.sev].l}</span>,
      situacao: s.status === "pending"
        ? (StatusBadge ? <StatusBadge tone="warning" label="Pendente" /> : <b>Pendente</b>)
        : s.status === "triaged"
          ? <span className="vdc-triado">{StatusBadge ? <StatusBadge tone="info" label="Triado" /> : <b>Triado</b>}<code>{s.us}</code></span>
          : <span className="vdc-triado">{StatusBadge ? <StatusBadge tone="neutral" label="Fechado" /> : <span>Fechado</span>}{s.motivo && <em>{s.motivo}</em>}</span>,
      acao: s.status === "pending" && podeTriar
        ? <button className="vdc-btn" onClick={() => { setTriar(s); setUs(""); setDestino("us"); }}>Triar</button>
        : null,
    },
  }));

  const sub = base.length
    ? `${base.length} relatos · ${cont("pending")} pendentes · o mais novo ${idade(base[0].at)}`
    : "nenhum relato ainda";

  if (papel === "sem-acesso") {
    return (
      <div className={cls} data-screen-label="Sistema · Voz do Cliente">
        {PageHeader && <PageHeader title="Voz do Cliente" subtitle="acesso negado" />}
        {Vazio && <Vazio variant="no-perm" title="Sua função não lê os relatos."
          description="O que as pessoas relataram inclui nome e tela de origem — é dado de pessoa identificada (LGPD Art. 7º). Só quem cuida de produto vê a caixa." />}
      </div>
    );
  }
  if (estado === "carregando") {
    return (
      <div className={cls} data-screen-label="Sistema · Voz do Cliente">
        {PageHeader && <PageHeader title="Voz do Cliente" subtitle={sub} />}
        <div className="vdc-lista">{Skeleton ? <Skeleton variant="row" count={6} /> : <p>Carregando…</p>}</div>
      </div>
    );
  }
  if (estado === "erro") {
    return (
      <div className={cls} data-screen-label="Sistema · Voz do Cliente">
        {PageHeader && <PageHeader title="Voz do Cliente" subtitle={sub} />}
        {Vazio && <Vazio variant="error" title="Não foi possível ler os relatos."
          description="A tabela de sinais não respondeu. Nenhum relato foi perdido — eles continuam gravados; recarregue a tela." />}
      </div>
    );
  }

  return (
    <div className={cls} data-screen-label="Sistema · Voz do Cliente">
      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title="Voz do Cliente" subtitle={sub} />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>Voz do Cliente</h1><p>{sub}</p></div></header>}
      </div>

      <p className="vdc-lead">o que as pessoas relataram — de dentro do sistema, com a tela em que aconteceu</p>

      {base.length > 0 && Kpis &&
        <div data-contract="kpis" className="vdc-kpis">
          <Kpis>
            <Kpi v={cont("pending")} l="Pendentes de triagem" tone={cont("pending") ? "warning" : "default"} sub="ninguém olhou ainda" />
            <Kpi v={cont("triaged")} l="Triados" sub="já viraram US no backlog" />
            <Kpi v={cont("closed")} l="Fechados" sub="resolvidos ou descartados" />
            <Kpi v={base.filter((s) => s.sev === "alta").length} l="Gravidade alta" tone="danger" sub="dor que trava o trabalho" />
          </Kpis>
        </div>}

      {base.length > 0 &&
        <nav className="vdc-filtros" data-contract="filtros" aria-label="Situação">
          {FILTROS.map((f) => (
            <button key={f.id} className={"vdc-f" + (filtro === f.id ? " active" : "")} onClick={() => setFiltro(f.id)}>
              {f.label}<span className="mono">{f.id === "todos" ? base.length : cont(f.id)}</span>
            </button>
          ))}
        </nav>}

      <div className="vdc-lista" data-contract="lista">
        {base.length === 0
          ? (Vazio ? <Vazio variant="first" title="Nenhum relato ainda."
              description="Quando alguém relatar um problema por dentro do sistema, ele aparece aqui — com a tela em que aconteceu." /> : <p>Nenhum relato ainda.</p>)
          : filtrada.length === 0
            ? (Vazio ? <Vazio variant="filtered" title="Nada nesta situação." description="Troque o filtro acima para ver os outros relatos." /> : <p>Nada nesta situação.</p>)
            : DataTable
              ? <DataTable columns={colunas} rows={linhas} />
              : <div className="os-table-wrap"><table className="os-table"><tbody>{filtrada.map((s) => <tr key={s.id}><td className="mono">{dt(s.at)}</td><td>{s.autor || "—"}</td><td>{s.texto}</td></tr>)}</tbody></table></div>}
      </div>

      <p className="vdc-fine">
        O relato entra por dentro do sistema (widget de feedback), guarda a tela de origem e não abre chamado nem notifica ninguém —
        {Tooltip ? <Tooltip content="Virar US no backlog é decisão de quem prioriza. Automatizar isso encheria o backlog de duplicado."><b> a triagem é manual</b></Tooltip> : <b> a triagem é manual</b>}.
        {!podeTriar && " Sua função lê, mas não tria."}
      </p>

      {Confirm &&
        <Confirm open={!!triar} title={triar ? `Triar relato #${triar.id}` : ""} cta={destino === "us" ? "Triar para a US" : "Fechar relato"}
          ctaTone={destino === "us" ? "primary" : "danger"} onClose={() => setTriar(null)} onConfirm={aplicar}>
          {triar && <>
            <p className="vdc-modal-q">“{triar.texto}”</p>
            <p className="vdc-modal-m">{triar.autor || "anônimo"} · <code>{triar.vista}</code> · {idade(triar.at)}</p>
            <div className="vdc-modal-f">
              {Select
                ? <Select label="O que fazer" value={destino} onChange={(e) => setDestino(e.target.value)}
                    options={[{ value: "us", label: "Virar US do backlog" }, { value: "close", label: "Fechar sem ação" }]} />
                : <select value={destino} onChange={(e) => setDestino(e.target.value)}><option value="us">Virar US do backlog</option><option value="close">Fechar sem ação</option></select>}
              {Input
                ? <Input label={destino === "us" ? "Código da US" : "Motivo do fechamento"} value={us} onChange={(e) => setUs(e.target.value)}
                    placeholder={destino === "us" ? "US-CV-0xx" : "duplicado do #79"} help={destino === "us" ? "Sem código, entra como US-NOVA-001 pra alguém nomear depois." : "Quem relatou não é notificado — o motivo é pra você."} />
                : <input value={us} onChange={(e) => setUs(e.target.value)} />}
            </div>
          </>}
        </Confirm>}

      {aviso && <div className="vdc-toast"><b>Feito.</b> {aviso}</div>}
    </div>
  );
}

window.VozDoClientePage = VozDoClientePage;
})();
