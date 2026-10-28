// relatorio-montavel-page.jsx — RELATÓRIO MONTÁVEL (US-REL-001). Tela NOVA, não existe no vivo.
// O usuário escolhe a FONTE (vendas · títulos · estoque · OS), os CAMPOS, os FILTROS, o AGRUPAMENTO
// e o GRÁFICO; vê o resultado; SALVA com um nome e REABRE depois.
// Fontes confirmadas por [W] em 2026-10-27 (vendas = transactions sell final · títulos = fin_titulos ·
// estoque = variation_location_details · OS = repair_job_sheets).
// Casco herdado de relatorios-page.jsx (rel-*: filtros, colvis, totais, rodapé) + componentes do DS
// (PageHeader · Select · PeriodBar · DataTablePro · EmptyState · Modal · Input · Alert). Nada bespoke de tabela/botão.
// Custo e valor em estoque só aparecem para quem vê preço de compra (toggle "Ver como" simula o papel).
// Dados = exemplo fixo; os números reais saem do servidor e batem com o relatório pronto (regra mestre).
// Expõe window.RelatorioMontavelPage.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const BRL = (v) => (v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
const NUM = (v) => (v || 0).toLocaleString("pt-BR");

// Cada fonte: campos (k, rótulo, tipo, custo?), filtros, agrupamentos e linhas de exemplo.
const FONTES = {
  vendas: {
    label: "Vendas", desc: "Vendas finalizadas", icone: "chart",
    campos: [["data", "Data", "data"], ["numero", "Número", "txt"], ["cliente", "Cliente", "txt"], ["vendedor", "Vendedor", "txt"],
      ["local", "Local", "txt"], ["desconto", "Desconto", "brl"], ["imposto", "Imposto", "brl"], ["total", "Total", "brl"], ["pagamento", "Pagamento", "txt"]],
    padrao: ["data", "numero", "cliente", "vendedor", "total"],
    filtros: { Local: ["Todos", "Loja centro", "Loja shopping"], Vendedor: ["Todos", "Ana", "Bruno", "Carla"], Pagamento: ["Todos", "Pago", "Parcial", "Em aberto"] },
    grupos: ["Nenhum", "Dia", "Mês", "Vendedor", "Local", "Cliente"],
    linhas: [
      { data: "01/10/2026", numero: "V-1021", cliente: "Padaria Estrela", vendedor: "Ana", local: "Loja centro", desconto: 0, imposto: 18.4, total: 412.9, pagamento: "Pago" },
      { data: "03/10/2026", numero: "V-1034", cliente: "Mercado União", vendedor: "Bruno", local: "Loja shopping", desconto: 20, imposto: 41.2, total: 980, pagamento: "Parcial" },
      { data: "08/10/2026", numero: "V-1057", cliente: "Farmácia Vida Plena", vendedor: "Ana", local: "Loja centro", desconto: 0, imposto: 12, total: 265.5, pagamento: "Pago" },
      { data: "12/10/2026", numero: "V-1080", cliente: "Escola Saber Mais", vendedor: "Carla", local: "Loja centro", desconto: 15, imposto: 60.3, total: 1450, pagamento: "Em aberto" },
      { data: "19/10/2026", numero: "V-1102", cliente: "Padaria Estrela", vendedor: "Bruno", local: "Loja shopping", desconto: 0, imposto: 9.9, total: 199.9, pagamento: "Pago" },
    ],
  },
  titulos: {
    label: "Títulos", desc: "Contas a receber e a pagar", icone: "cash",
    campos: [["tipo", "Tipo", "txt"], ["cliente", "Cliente/Fornecedor", "txt"], ["emissao", "Emissão", "data"], ["vencimento", "Vencimento", "data"],
      ["categoria", "Categoria", "txt"], ["status", "Situação", "txt"], ["valor", "Valor", "brl"], ["aberto", "Em aberto", "brl"]],
    padrao: ["tipo", "cliente", "vencimento", "status", "valor", "aberto"],
    filtros: { Tipo: ["Todos", "A receber", "A pagar"], Situação: ["Todas", "Aberto", "Vencido", "Pago"] },
    grupos: ["Nenhum", "Mês de vencimento", "Categoria", "Cliente/Fornecedor", "Situação"],
    linhas: [
      { tipo: "A receber", cliente: "Mercado União", emissao: "03/10/2026", vencimento: "03/11/2026", categoria: "Vendas", status: "Aberto", valor: 490, aberto: 490 },
      { tipo: "A receber", cliente: "Escola Saber Mais", emissao: "12/10/2026", vencimento: "20/10/2026", categoria: "Vendas", status: "Vencido", valor: 1450, aberto: 1450 },
      { tipo: "A pagar", cliente: "Distribuidora Brasil", emissao: "05/10/2026", vencimento: "05/11/2026", categoria: "Mercadoria", status: "Aberto", valor: 2300, aberto: 2300 },
      { tipo: "A pagar", cliente: "Imobiliária Lar Bom", emissao: "01/10/2026", vencimento: "10/10/2026", categoria: "Aluguel", status: "Pago", valor: 3200, aberto: 0 },
    ],
  },
  estoque: {
    label: "Estoque", desc: "Saldo por produto e local", icone: "archive",
    campos: [["produto", "Produto", "txt"], ["sku", "SKU", "txt"], ["local", "Local", "txt"], ["categoria", "Categoria", "txt"],
      ["qtd", "Quantidade", "num"], ["custo", "Custo unitário", "brl", true], ["valorEstoque", "Valor em estoque", "brl", true]],
    padrao: ["produto", "sku", "local", "qtd", "valorEstoque"],
    filtros: { Local: ["Todos", "Loja centro", "Loja shopping"], Categoria: ["Todas", "Camisetas", "Calças", "Acessórios"] },
    grupos: ["Nenhum", "Local", "Categoria"],
    linhas: [
      { produto: "Camiseta básica P", sku: "CB-P", local: "Loja centro", categoria: "Camisetas", qtd: 34, custo: 18, valorEstoque: 612 },
      { produto: "Camiseta básica M", sku: "CB-M", local: "Loja shopping", categoria: "Camisetas", qtd: 21, custo: 18, valorEstoque: 378 },
      { produto: "Calça jeans 40", sku: "CJ-40", local: "Loja centro", categoria: "Calças", qtd: 8, custo: 62, valorEstoque: 496 },
      { produto: "Cinto couro", sku: "CI-01", local: "Loja shopping", categoria: "Acessórios", qtd: 15, custo: 25, valorEstoque: 375 },
    ],
  },
  os: {
    label: "OS", desc: "Ordens de serviço", icone: "receipt",
    campos: [["numero", "Número", "txt"], ["cliente", "Cliente", "txt"], ["status", "Status", "txt"], ["tecnico", "Técnico", "txt"],
      ["entrada", "Entrada", "data"], ["entrega", "Entrega prevista", "data"], ["custoEst", "Custo estimado", "brl"]],
    padrao: ["numero", "cliente", "status", "tecnico", "entrega", "custoEst"],
    filtros: { Status: ["Todos", "Recebido", "Em reparo", "Pronto", "Entregue"], Técnico: ["Todos", "Diego", "Eva"] },
    grupos: ["Nenhum", "Status", "Técnico", "Mês de entrada"],
    linhas: [
      { numero: "OS-311", cliente: "Auto Posto Águia", status: "Em reparo", tecnico: "Diego", entrada: "02/10/2026", entrega: "09/10/2026", custoEst: 380 },
      { numero: "OS-318", cliente: "Salão Beleza Pura", status: "Pronto", tecnico: "Eva", entrada: "06/10/2026", entrega: "13/10/2026", custoEst: 150 },
      { numero: "OS-325", cliente: "Construtora Horizonte", status: "Recebido", tecnico: "Diego", entrada: "15/10/2026", entrega: "22/10/2026", custoEst: 920 },
    ],
  },
};
const GRAFICOS = ["Sem gráfico", "Barras", "Linha"];
const SALVOS_INICIAIS = [
  { id: 1, nome: "Vendas do mês por vendedor", fonte: "vendas", campos: ["vendedor", "total"], grupo: "Vendedor", grafico: "Barras", autor: "Ana" },
  { id: 2, nome: "Títulos vencidos", fonte: "titulos", campos: ["cliente", "vencimento", "aberto"], grupo: "Nenhum", grafico: "Sem gráfico", autor: "Wagner" },
];
const fmt = (tipo, v) => (tipo === "brl" ? BRL(v) : tipo === "num" ? NUM(v) : v);
// Agrupamento pelo rótulo da coluna equivalente ("Mês" agrupa pela data).
const chaveGrupo = (fonte, g) => {
  if (g === "Nenhum") return null;
  const achado = fonte.campos.find((c) => g.toLowerCase().startsWith(c[1].toLowerCase().split("/")[0]));
  if (achado) return { k: achado[0], mes: false };
  const data = fonte.campos.find((c) => c[2] === "data");
  return data ? { k: data[0], mes: true } : null;
};

function Grafico({ tipo, pontos }) {
  if (tipo === "Sem gráfico" || pontos.length === 0) return null;
  const max = Math.max(...pontos.map((p) => p.v), 1);
  const W = 560, H = 160, passo = W / pontos.length;
  return (
    <figure className="rm-grafico" aria-label={"Gráfico de " + tipo.toLowerCase()}>
      <svg viewBox={`0 0 ${W} ${H + 24}`} role="img">
        {tipo === "Barras" && pontos.map((p, i) =>
          <rect key={p.r} x={i * passo + passo * 0.2} y={H - (p.v / max) * H} width={passo * 0.6} height={(p.v / max) * H} rx="3" className="rm-barra"><title>{p.r}: {p.t}</title></rect>)}
        {tipo === "Linha" && <polyline className="rm-linha" fill="none" points={pontos.map((p, i) => `${i * passo + passo / 2},${H - (p.v / max) * H}`).join(" ")} />}
        {pontos.map((p, i) => <text key={"t" + p.r} x={i * passo + passo / 2} y={H + 16} textAnchor="middle" className="rm-eixo">{p.r}</text>)}
      </svg>
    </figure>);
}

function RelatorioMontavelPage() {
  const { PageHeader, Select, PeriodBar, DataTablePro, EmptyState, Modal, Input, Alert } = DS();
  const [fonteId, setFonteId] = useState("vendas");
  const fonte = FONTES[fonteId];
  const [campos, setCampos] = useState(fonte.padrao);
  const [filtros, setFiltros] = useState({});
  const [periodo, setPeriodo] = useState("Este mês");
  const [grupo, setGrupo] = useState("Nenhum");
  const [grafico, setGrafico] = useState("Sem gráfico");
  const [veCusto, setVeCusto] = useState(true);
  const [salvos, setSalvos] = useState(SALVOS_INICIAIS);
  const [abertoId, setAbertoId] = useState(null);
  const [salvando, setSalvando] = useState(false);
  const [nome, setNome] = useState("");
  const [aviso, setAviso] = useState(null);

  const trocarFonte = (id) => { setFonteId(id); setCampos(FONTES[id].padrao); setFiltros({}); setGrupo("Nenhum"); setAbertoId(null); };
  const abrir = (s) => { setFonteId(s.fonte); setCampos(s.campos); setGrupo(s.grupo); setGrafico(s.grafico); setFiltros({}); setAbertoId(s.id); };
  const disponiveis = fonte.campos.filter((c) => veCusto || !c[3]);
  const visiveis = disponiveis.filter((c) => campos.includes(c[0]));
  const alternar = (k) => setCampos((cs) => (cs.includes(k) ? cs.filter((x) => x !== k) : [...cs, k]));

  const linhas = useMemo(() => fonte.linhas.filter((l) => Object.entries(filtros).every(([f, v]) => {
    if (!v || v.startsWith("Tod")) return true;
    const col = fonte.campos.find((c) => c[1].toLowerCase().startsWith(f.toLowerCase().slice(0, 4)));
    return col ? String(l[col[0]]).startsWith(v.replace("A receber", "A receber")) : true;
  })), [fonte, filtros]);

  const somaveis = visiveis.filter((c) => c[2] === "brl" || c[2] === "num");
  const g = chaveGrupo(fonte, grupo);
  const grupos = useMemo(() => {
    if (!g) return null;
    const m = new Map();
    linhas.forEach((l) => {
      const r = g.mes ? String(l[g.k]).slice(3) : l[g.k];
      const acc = m.get(r) || { r, n: 0 };
      acc.n++;
      somaveis.forEach((c) => { acc[c[0]] = (acc[c[0]] || 0) + l[c[0]]; });
      m.set(r, acc);
    });
    return [...m.values()];
  }, [linhas, g && g.k, grupo, somaveis.length]);

  const colunas = grupos
    ? [{ key: "r", label: grupo }, { key: "n", label: "Linhas", align: "right" }, ...somaveis.map((c) => ({ key: c[0], label: c[1], align: "right" }))]
    : visiveis.map((c) => ({ key: c[0], label: c[1], align: c[2] === "brl" || c[2] === "num" ? "right" : "left" }));
  const tipoDe = (k) => (fonte.campos.find((c) => c[0] === k) || [])[2];
  const base = grupos || linhas;
  const rows = base.map((l, i) => {
    const r = { id: i };
    colunas.forEach((c) => { r[c.key] = c.key === "r" || c.key === "n" ? l[c.key] : fmt(tipoDe(c.key), l[c.key]); });
    return r;
  });
  const metrica = somaveis[somaveis.length - 1];
  const pontos = (grupos || []).map((x) => ({ r: x.r, v: metrica ? x[metrica[0]] : x.n, t: metrica ? fmt(metrica[2], x[metrica[0]]) : x.n }));

  const salvar = () => {
    if (!nome.trim()) return;
    const novo = { id: Date.now(), nome: nome.trim(), fonte: fonteId, campos, grupo, grafico, autor: "você" };
    setSalvos((s) => [novo, ...s]); setAbertoId(novo.id); setSalvando(false); setNome("");
    setAviso("Relatório salvo. Ele fica para a empresa toda, e cada pessoa vê só as colunas que a função dela permite.");
  };
  const aberto = salvos.find((s) => s.id === abertoId);

  return (
    <div className="jc-page rel-page rm-page" data-screen-label="Relatório montável">
      {PageHeader && <PageHeader title={aberto ? aberto.nome : "Relatório montável"}
        stats={[{ value: fonte.label, label: "fonte" }, { value: base.length, label: grupos ? "grupos" : "linhas" }]}
        actions={<>
          <label className="rm-papel"><input type="checkbox" checked={veCusto} onChange={(e) => setVeCusto(e.target.checked)} /> Ver como quem vê custo</label>
          <button className="rel-btn" onClick={() => setAviso("CSV gerado — o download real sai no Inertia.")}><Ic name="sheet" size={12} /> CSV</button>
          <button className="rel-btn" data-primary onClick={() => { setNome(aberto ? aberto.nome : ""); setSalvando(true); }}><Ic name="save" size={12} /> {aberto ? "Salvar alterações" : "Salvar"}</button>
        </>} />}
      {aviso && Alert && <Alert tone="success" title={aviso} onClose={() => setAviso(null)} />}
      <div className="rm-corpo">
        <aside className="rm-montar" aria-label="Montar relatório">
          <section className="rm-bloco">
            <h3>Fonte</h3>
            <div className="rm-fontes" role="radiogroup" aria-label="Fonte de dados">
              {Object.entries(FONTES).map(([id, f]) =>
                <button key={id} role="radio" aria-checked={id === fonteId} className="rm-fonte" data-on={id === fonteId ? "1" : null} onClick={() => trocarFonte(id)}>
                  <Ic name={f.icone} size={13} /> <b>{f.label}</b><small>{f.desc}</small>
                </button>)}
            </div>
          </section>
          <section className="rm-bloco">
            <h3>Campos <span className="rel-n">{visiveis.length}/{disponiveis.length}</span></h3>
            {disponiveis.map((c) =>
              <label className="rel-colvis-item" key={c[0]}><input type="checkbox" checked={campos.includes(c[0])} onChange={() => alternar(c[0])} /> {c[1]}</label>)}
            {!veCusto && fonte.campos.some((c) => c[3]) && <p className="rel-nota">Custo e valor em estoque não aparecem para a sua função.</p>}
          </section>
          <section className="rm-bloco">
            <h3>Filtros</h3>
            {PeriodBar && <PeriodBar value={periodo} onChange={setPeriodo} label="Período" />}
            {Select && Object.entries(fonte.filtros).map(([f, opts]) =>
              <Select key={fonteId + f} label={f} options={opts} value={filtros[f] || opts[0]} onChange={(e) => setFiltros((x) => ({ ...x, [f]: e.target.value }))} />)}
          </section>
          <section className="rm-bloco">
            <h3>Agrupar e mostrar</h3>
            {Select && <Select label="Agrupar por" options={fonte.grupos} value={grupo} onChange={(e) => setGrupo(e.target.value)} />}
            {Select && <Select label="Gráfico" options={GRAFICOS} value={grafico} onChange={(e) => setGrafico(e.target.value)} help={grupo === "Nenhum" ? "O gráfico aparece quando há agrupamento." : null} />}
          </section>
          <section className="rm-bloco">
            <h3>Salvos <span className="rel-n">{salvos.length}</span></h3>
            {salvos.map((s) =>
              <button key={s.id} className="rm-salvo" data-on={s.id === abertoId ? "1" : null} onClick={() => abrir(s)}>
                <b>{s.nome}</b><small>{FONTES[s.fonte].label} · por {s.autor}</small>
              </button>)}
          </section>
        </aside>
        <main className="rm-resultado">
          {grupos && <Grafico tipo={grafico} pontos={pontos} />}
          {base.length === 0 && EmptyState
            ? <EmptyState variant="filtered" title="Nenhuma linha nesse recorte" description="Amplie o período ou limpe um filtro."
                action={<button className="rel-btn" data-primary onClick={() => setFiltros({})}>Limpar filtros</button>} />
            : DataTablePro && <DataTablePro columns={colunas} rows={rows} height={Math.min(480, 92 + rows.length * 41)} density="compact" />}
          {somaveis.length > 0 && linhas.length > 0 &&
            <div className="rel-totais">
              {somaveis.map((c) => <span className="rel-total" key={c[0]}><span className="rel-total-l">Total — {c[1]}</span>
                <span className="rel-total-v">{fmt(c[2], linhas.reduce((s, l) => s + l[c[0]], 0))}</span></span>)}
            </div>}
          <div className="rel-rodape"><span className="rel-nota">Os totais batem com o relatório pronto do mesmo período. Exemplo com dados fictícios.</span></div>
        </main>
      </div>
      {Modal && <Modal open={salvando} onClose={() => setSalvando(false)} title={aberto ? "Salvar alterações" : "Salvar relatório"}
        footer={<><button className="rel-btn" onClick={() => setSalvando(false)}>Cancelar</button><button className="rel-btn" data-primary disabled={!nome.trim()} onClick={salvar}>Salvar</button></>}>
        {Input && <Input label="Nome" value={nome} onChange={(e) => setNome(e.target.value)} placeholder="Ex.: Vendas do mês por vendedor" help="Fica disponível para toda a empresa." />}
      </Modal>}
    </div>);
}
window.RelatorioMontavelPage = RelatorioMontavelPage;
})();
