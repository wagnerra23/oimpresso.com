// integra-extras.jsx — WooCommerce (Modules/Woocommerce) e Restaurante (restaurant/table +
// service staff). Onda 5 (paridade com o main, lido 2026-08-22, tree 6a8e45998ee5):
//   · a navegação viva do módulo tem só duas telas próprias — Log de sincronização e
//     Configurações da API (layouts/nav.blade.php); o resto vive no index (Sincronizar
//     produtos "todos"/"só novos", Sincronizar pedidos, mapa de impostos, configurações de
//     produto/pedido/webhook, redefinir sincronizados, auto sync).
//   · sync_log.blade.php: Data · Tipo de sincronização · Operação · Sincronizado por ·
//     Registros, com linha expansível de detalhes.
//   · status de pedido do Woo (lang.php): pending · processing · on-hold · completed ·
//     cancelled · refunded · failed · shipped, cada um mapeado para um status de venda do POS.
//   · erros do vivo têm forma fixa: "ERRO:IGNORADO: pedido #N ignorado porque …".
//   · restaurant/table/index: Mesa · Local do negócio · Descrição · Ação (ação por último),
//     permissão única `access_tables`.
// Refino: (1) coerência — o log conta a MESMA sincronização dos produtos, o pedido da loja
// aponta a venda de origem online, e a ocupação da mesa é derivada de quem está em atendimento
// no mesmo local; (2) drawer PT-02 com ação que muda estado (vincular SKU, reenviar, abrir e
// encerrar atendimento); (3) teclado j/k · ↵ · / · d.
// Expõe window.WooCommercePage e window.RestauranteExtrasPage.
(() => {
const { useState, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const MP = () => window.ModuloPadrao || {};
const CU = () => window.CatchupUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const brl = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ─────────── WooCommerce ───────────
const PRODUTOS0 = [
  { id: 1, sku: "PRD-0001", nome: "Lona 380g impressa", woo: "#1042", preco: 55, estoque: 1240, sync: "ok", quando: "22/08 09:40" },
  { id: 2, sku: "PRD-0004", nome: "Cartão de visita 1.000 un", woo: "#1051", preco: 149.8, estoque: null, sync: "ok", quando: "22/08 09:40" },
  { id: 3, sku: "PRD-0009", nome: "Adesivo de recorte", woo: "—", preco: 39.5, estoque: 418, sync: "pendente", quando: "—" },
  { id: 4, sku: "PRD-0010", nome: "Banner 440g com bastão", woo: "#0998", preco: 75, estoque: 0, sync: "erro", quando: "21/08 18:12" },
  { id: 5, sku: "PRD-0021", nome: "Serviço de instalação (hora)", woo: "—", preco: 120, estoque: null, sync: "desativado", quando: "—" },
];
const MOTIVO = {
  pendente: "Sem vínculo na loja: o SKU ainda não existe no catálogo do WooCommerce. Enquanto não casa, preço e estoque não sobem.",
  erro: "A loja recusou o envio: o produto está inativo no catálogo do ERP. Reative ou tire da fila de sincronização.",
  ok: "Preço e estoque enviados na última execução — a loja confirmou o recebimento.",
  desativado: "O produto tem a sincronização desativada na própria ficha (\"não sincronizar com o WooCommerce\") — serviço sem estoque não vai pra loja.",
};
// Status de pedido do Woo e o equivalente que a venda recebe no ERP (order_sync_settings).
const WOO_STATUS = {
  pending: { l: "Pendente", erp: "Rascunho" }, processing: { l: "Processando", erp: "Finalizada" },
  "on-hold": { l: "Em espera", erp: "Rascunho" }, completed: { l: "Concluído", erp: "Finalizada" },
  cancelled: { l: "Cancelado", erp: "Cancelada" }, refunded: { l: "Reembolsado", erp: "Devolução" },
  failed: { l: "Falhou", erp: "Rascunho" }, shipped: { l: "Enviado", erp: "Finalizada · enviado" },
};
const pedidosLoja = () => (window.VENDAS_MOCK || []).filter((v) => v && v.source === "online").map((v, i) => {
  const st = v.total_paid >= v.final_total ? "completed" : v.total_paid > 0 ? "processing" : "pending";
  return {
    id: v.id, woo: "#" + (3391 - i * 3), data: v.dia + "/2026 " + v.hora, cli: v.cli,
    itens: String(v.items_summary || "").split("+").length, resumo: v.items_summary,
    total: v.final_total, pago: v.total_paid, venda: v.invoice_no, st,
    status: WOO_STATUS[st].l, erp: WOO_STATUS[st].erp,
  };
});
// Estado fora do componente (aba/rota remonta a página): um SKU vinculado continua vinculado.
const WOO_STORE = { produtos: PRODUTOS0 };
const plural = (n, um, muitos) => n + " " + (n === 1 ? um : muitos);

function WooCommercePage() {
  const M = MP();
  const { Widget, Fld, Sel, Kebab } = UI();
  const { Alert, StatusBadge } = DS();
  const { Grade, Toolbar, Kpis, Painel, Def, Itens, useNav } = CU();
  const [, tick] = useState(0);
  const produtos = WOO_STORE.produtos;
  const setProdutos = (fn) => { WOO_STORE.produtos = fn(WOO_STORE.produtos); tick((t) => t + 1); };
  const [aba, setAba] = useState("produtos");
  const [busca, setBusca] = useState("");
  const [densa, setDensa] = useState(false);
  const [sel, setSel] = useState(null);
  const [cfg, setCfg] = useState({ url: "https://loja.oimpresso.com.br", auto: "A cada hora", local: "Matriz" });
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  const boxRef = useRef(null), buscaRef = useRef(null);
  if (useNav) useNav(boxRef, buscaRef, setDensa);
  const fechar = () => setSel(null);
  const selo = (s) => { const l = { ok: "Sincronizado", pendente: "Pendente", erro: "Com erro", desativado: "Desativada" }[s] || s; return StatusBadge ? <StatusBadge kind="documento" value={l} /> : l; };
  const [logAberto, setLogAberto] = useState(null);
  const casa = (s) => !busca || String(s).toLowerCase().includes(busca.toLowerCase());
  const conta = produtos.reduce((a, p) => { a[p.sync] = (a[p.sync] || 0) + 1; return a; }, {});
  const agora = "22/08 " + new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });

  const vincular = (p) => {
    const novo = "#" + (1100 + p.id);
    setProdutos((L) => L.map((x) => x.id === p.id ? { ...x, woo: novo, sync: "ok", quando: agora } : x));
    fechar(); avisar(p.sku + " vinculado a " + novo + " — preço e estoque subiram nesta execução.", "ok");
  };
  const reenviar = (p) => {
    setProdutos((L) => L.map((x) => x.id === p.id ? { ...x, sync: "ok", quando: agora } : x));
    fechar(); avisar(p.sku + " reenviado — a loja aceitou.", "ok");
  };
  const tirarDaFila = (p) => {
    setProdutos((L) => L.filter((x) => x.id !== p.id));
    fechar(); avisar(p.sku + " saiu da fila de sincronização — continua no catálogo do ERP.", "warn");
  };

  // O log descreve a execução dos produtos acima: as duas abas nunca contam diferente.
  const log = () => {
    const partes = [plural(conta.ok || 0, "produto enviado", "produtos enviados")];
    if (conta.pendente) partes.push(plural(conta.pendente, "pendente de vínculo", "pendentes de vínculo"));
    if (conta.erro) partes.push(plural(conta.erro, "recusado pela loja", "recusados pela loja"));
    if (conta.desativado) partes.push(plural(conta.desativado, "com sincronização desativada", "com sincronização desativada"));
    const ped = pedidosLoja();
    return [
      { id: 1, quando: "22/08/2026 09:40", tipo: "Produtos", op: "Atualizado", quem: "Sincronização automática (cron)", regs: plural(conta.ok || 0, "registro", "registros"), r: conta.erro ? "erro" : "ok", det: partes.join(" · ") },
      { id: 2, quando: "22/08/2026 09:40", tipo: "Pedidos", op: "Criado", quem: "Sincronização automática (cron)", regs: plural(ped.length, "registro", "registros"), r: "ok", det: ped.map((p) => p.woo + " → " + p.venda + " (" + p.status + ")").join(" · ") },
      { id: 3, quando: "21/08/2026 18:12", tipo: "Produtos", op: "Atualizado", quem: "Wagner Ramos", regs: "1 registro", r: "erro", det: "ERRO:IGNORADO: PRD-0010 ignorado porque o produto está inativo no catálogo do ERP." },
    ];
  };

  const colsProd = [
    { key: "acao", label: "Ação", width: 84, resizable: false },
    { key: "sku", label: "SKU", width: 124, mono: true },
    { key: "nome", label: "Produto", width: 226 },
    { key: "woo", label: "ID na loja", width: 116, mono: true },
    { key: "preco", label: "Preço", width: 124, align: "right", mono: true },
    { key: "estoque", label: "Estoque enviado", width: 148, align: "right", mono: true },
    { key: "sync", label: "Situação", width: 146 },
    { key: "quando", label: "Última sincronização", width: 172, mono: true },
    { key: "fila", label: "Na fila de sincronização", width: 190 },
  ];
  const colsPed = [
    { key: "woo", label: "Pedido na loja", width: 136, mono: true },
    { key: "data", label: "Data", width: 156, mono: true },
    { key: "cli", label: "Cliente", width: 200 },
    { key: "itens", label: "Itens", width: 84, align: "right", mono: true },
    { key: "total", label: "Total", width: 136, align: "right", mono: true },
    { key: "status", label: "Status na loja", width: 160 },
    { key: "erp", label: "Equivalente no ERP", width: 178 },
    { key: "venda", label: "Venda no ERP", width: 152, mono: true },
  ];

  const painel = () => {
    if (!sel || !Painel) return null;
    if (sel.k === "prod") {
      const p = sel.d;
      return <Painel aberto onClose={fechar} titulo={p.nome} sub={p.sku + (p.woo !== "—" ? " · loja " + p.woo : " · sem vínculo")} badge={selo(p.sync)} largura={500}
        secoes={[
          { t: "Produto", c: <Def pares={[["SKU", p.sku], ["ID na loja", p.woo], ["Preço enviado", brl(p.preco)], ["Estoque enviado", p.estoque == null ? "não controla estoque" : p.estoque + " un"], ["Local do estoque", cfg.local], ["Última sincronização", p.quando]]} /> },
          { t: "Situação", c: <p className="cu-nota">{MOTIVO[p.sync]}</p> },
        ]}
        acoes={<div className="cu-dr-acoes">
          {p.sync === "pendente" && <button className="os-btn primary" onClick={() => vincular(p)}><Ic name="plug" size={13} /> Vincular SKU na loja</button>}
          {p.sync === "erro" && <button className="os-btn primary" onClick={() => reenviar(p)}><Ic name="refresh" size={13} /> Reenviar</button>}
          <div className="sp" />
          <button className="os-btn danger" onClick={() => tirarDaFila(p)}>Tirar da fila</button>
        </div>} />;
    }
    const o = sel.d;
    return <Painel aberto onClose={fechar} titulo={"Pedido " + o.woo} sub={o.cli + " · " + o.data} badge={StatusBadge ? <StatusBadge kind="documento" value={o.status} /> : o.status} largura={500}
      secoes={[
        { t: "Pedido da loja", c: <Def pares={[["Cliente", o.cli], ["Data", o.data], ["Total", brl(o.total)], ["Pago na loja", brl(o.pago)], ["Venda no ERP", o.venda], ["Origem no ERP", "Online"]]} /> },
        { t: "Itens", c: <p className="cu-nota">{o.resumo || "sem resumo de itens"}</p> },
        { t: "Como entra no ERP", c: <p className="cu-nota">Pedido pago vira venda com origem Online e cai em Todas as vendas — o número da loja fica guardado na venda, então o mesmo pedido não entra duas vezes.</p> },
      ]}
      acoes={<div className="cu-dr-acoes"><button className="os-btn primary" onClick={() => { fechar(); window.__selectRoute && window.__selectRoute("venda-todas"); }}>Abrir {o.venda}</button></div>} />;
  };

  const pedidos = pedidosLoja();
  return (
    <div className="pb-root vb-root" data-screen-label="Integrações · WooCommerce" ref={boxRef}>
      {M.Header &&
        <M.Header modulo="WooCommerce" papel="Sincronização da loja"
          contexto={["OFFICEIMPRESSO", cfg.url.replace("https://", ""), (conta.ok || 0) + " de " + produtos.length + " produtos sincronizados"]}
          atualizadoAs={new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}
          glyph={<Ic name="plug" />}
          acoes={<div style={{ display: "flex", gap: 8, alignItems: "center" }}>
            <button className="os-btn" onClick={() => avisar("Sincronizando só os produtos novos — processo mais curto; não saia da tela.", "ok")}>Só novos</button>
            <button className="os-btn primary" onClick={() => avisar("Sincronizando todos os produtos — processo demorado; não recarregue a página.", "ok")}><Ic name="refresh" size={13} /> Sincronizar todos</button>
          </div>} />}
      <div className="pb-body">
        <nav className="cli-moduletopnav vb-nav" aria-label="Áreas do WooCommerce">
          {[["produtos", "Produtos"], ["pedidos", "Pedidos"], ["log", "Log de sincronização"], ["config", "Configuração"]].map(([k, l]) => (
            <button key={k} className={"cli-moduletopnav-tab " + (aba === k ? "active" : "")} onClick={() => setAba(k)}>{l}</button>
          ))}
        </nav>

        {aba === "produtos" && Grade &&
          <>
            <Kpis itens={[
              { l: "Sincronizados", v: String(conta.ok || 0), tom: "pos", n: "preço e estoque na loja" },
              { l: "Pendentes de vínculo", v: String(conta.pendente || 0), tom: conta.pendente ? "warn" : "", n: "SKU sem par no catálogo da loja" },
              { l: "Com erro", v: String(conta.erro || 0), tom: conta.erro ? "warn" : "pos", n: conta.erro ? "a loja recusou o envio" : "nenhuma recusa" },
              { l: "Fora da fila", v: String(conta.desativado || 0), n: "sincronização desativada na ficha do produto" },
            ]} />
            <Widget flush titulo={<><Ic name="product" size={13} /> Produtos na loja</>} nota={produtos.length + " produto(s)"}>
              <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar SKU ou produto…" densa={densa} setDensa={setDensa} />
              <Grade densa={densa} columns={colsProd} onRowClick={(r) => setSel({ k: "prod", d: (r.cells || r)._p })} rows={produtos.filter((p) => casa(p.sku + " " + p.nome)).map((p) => ({
                id: p.id, _p: p, state: p.sync === "erro" ? "urgent" : undefined,
                acao: Kebab ? <Kebab acoes={[
                  { l: "Ver produto", ic: "search", on: () => setSel({ k: "prod", d: p }) },
                  ...(p.sync === "pendente" ? [{ l: "Vincular SKU", ic: "plug", on: () => vincular(p) }] : []),
                  ...(p.sync === "erro" ? [{ l: "Reenviar", ic: "refresh", on: () => reenviar(p) }] : []),
                  "-",
                  { l: "Tirar da fila", ic: "x", tone: "danger", on: () => tirarDaFila(p) },
                ]} /> : null,
                sku: p.sku, nome: p.nome, woo: p.woo, preco: brl(p.preco),
                estoque: p.estoque == null ? "não controla" : p.estoque, sync: selo(p.sync), quando: p.quando,
                fila: p.sync === "desativado" ? "fora — desativada na ficha" : "sim",
              }))} />
              <div className="pb-pag"><span className="pb-help">Produto sem vínculo não vai pra loja: primeiro casa o SKU, depois o preço e o estoque sobem.</span></div>
            </Widget>
          </>}

        {aba === "pedidos" && Grade &&
          <>
            <Kpis itens={[
              { l: "Pedidos importados", v: String(pedidos.length), n: "viraram venda de origem Online" },
              { l: "Valor na loja", v: brl(pedidos.reduce((a, p) => a + p.total, 0)), n: "mesmo total das vendas no ERP" },
              { l: "Aguardando pagamento", v: String(pedidos.filter((p) => p.status === STATUS_LOJA.due).length), tom: pedidos.filter((p) => p.status === STATUS_LOJA.due).length ? "warn" : "pos", n: "pedido não pago não libera produção" },
            ]} />
            <Widget flush titulo={<><Ic name="orders" size={13} /> Pedidos da loja</>} nota={pedidos.length + " pedido(s)"}>
              <Grade densa={densa} columns={colsPed} altura={240} onRowClick={(r) => setSel({ k: "ped", d: (r.cells || r)._o })} rows={pedidos.map((p) => ({
                id: p.id, _o: p, woo: p.woo, data: p.data, cli: p.cli, itens: p.itens, total: brl(p.total),
                status: StatusBadge ? <StatusBadge kind="documento" value={p.status} /> : p.status, erp: p.erp, venda: p.venda,
              }))} />
              <div className="pb-pag"><span className="pb-help">Pedido pago vira venda com origem <span className="mono">Online</span> — a mesma origem que aparece em Todas as vendas. Pedido sem cliente ou com produto que não existe no ERP é <b>ignorado</b> e vai pro log, não entra pela metade.</span></div>
            </Widget>
          </>}

        {aba === "log" &&
          <Widget titulo={<><Ic name="clock" size={13} /> Log de sincronização</>} nota={log().length + " execução(ões)"}>
            <table className="pb-tbl">
              <thead><tr><th style={{ width: 28 }}> </th><th>Data</th><th>Tipo de sincronização</th><th>Operação</th><th>Sincronizado por</th><th>Registros</th></tr></thead>
              <tbody>{log().map((l) => [
                <tr key={l.id} onClick={() => setLogAberto(logAberto === l.id ? null : l.id)} style={{ cursor: "pointer" }}>
                  <td className="mono" aria-hidden="true">{logAberto === l.id ? "−" : "+"}</td>
                  <td className="mono">{l.quando}</td><td>{l.tipo}</td><td>{l.op}</td><td>{l.quem}</td>
                  <td>{l.regs} {selo(l.r)}</td>
                </tr>,
                logAberto === l.id ? <tr key={l.id + "-d"}><td /><td colSpan="5" className="pb-help">{l.det}</td></tr> : null,
              ])}</tbody>
            </table>
            <div className="pb-pag"><span className="pb-help">Clique na linha para ver os registros da execução. A linha de produtos conta a fila desta tela: vincule ou reenvie um item e o log muda com ela.</span></div>
          </Widget>}

        {aba === "config" &&
          <>
            {Alert && <Alert tone="warn" title="Chaves ficam no servidor">A consumer key e o secret do WooCommerce não aparecem nesta tela — quem configura é o administrador, e a tela só mostra se a conexão responde.</Alert>}
            <Widget titulo={<><Ic name="cog" size={13} /> Configuração</>}>
              <div className="pb-grid c2">
                <Fld label="Endereço da loja" req><input value={cfg.url} onChange={(e) => setCfg({ ...cfg, url: e.target.value })} /></Fld>
                <Fld label="Local do estoque enviado" req><Sel value={cfg.local} onChange={(v) => setCfg({ ...cfg, local: v })} options={["Matriz", "Filial Centro"]} /></Fld>
                <Fld label="Sincronização automática"><Sel value={cfg.auto} onChange={(v) => setCfg({ ...cfg, auto: v })} options={["Desligada", "A cada hora", "A cada 6 horas", "Uma vez por dia"]} /></Fld>
                <div className="pb-fld" style={{ justifyContent: "flex-end" }}>
                  <button className="os-btn" onClick={() => avisar("Conexão testada — a loja respondeu em 240 ms.", "ok")}>Testar conexão</button>
                </div>
              </div>
              <p className="cu-nota">Outras seções da tela viva de configurações: mapa de impostos (taxa do ERP ↔ taxa do WooCommerce), campos do produto que sobem na criação e na atualização, equivalência entre status do pedido da loja e status da venda, webhooks de pedido (criado, atualizado, excluído, restaurado) e redefinir produtos/categorias sincronizados.</p>
            </Widget>
          </>}
      </div>
      {painel()}
      {avisoNode}
    </div>
  );
}

// ─────────── Restaurante: mesas e atendentes ───────────
const MESAS = [
  { id: 1, nome: "Mesa 01", loc: "Matriz", desc: "Balcão de atendimento — 2 lugares" },
  { id: 2, nome: "Mesa 02", loc: "Matriz", desc: "Mesa de reunião com cliente" },
  { id: 3, nome: "Mesa 03", loc: "Filial Centro", desc: "Atendimento rápido" },
];
const ATEND0 = [
  { id: 1, nome: "Larissa Prado", loc: "Matriz", pedidos: 12, status: "Em atendimento" },
  { id: 2, nome: "Marcos Vinícius", loc: "Filial Centro", pedidos: 7, status: "Disponível" },
  { id: 3, nome: "Eliana Souza", loc: "Matriz", pedidos: 0, status: "Fora do turno" },
];
// Mesa não guarda "ocupada": ela está ocupada se existe atendente EM ATENDIMENTO no mesmo
// local. Uma fonte só — mudar o atendente muda a mesa na hora.
const REST_STORE = { ats: ATEND0 };
const ocupacao = (ats) => {
  const mapa = {}; const fila = {};
  ats.filter((a) => a.status === "Em atendimento").forEach((a) => {
    const mesasLoc = MESAS.filter((m) => m.loc === a.loc);
    const k = fila[a.loc] || 0; fila[a.loc] = k + 1;
    const m = mesasLoc[k]; if (m) mapa[m.id] = a;
  });
  return mapa;
};

function RestauranteExtrasPage({ view = "cfg-mesas" }) {
  const M = MP();
  const { Widget, Kebab } = UI();
  const { Alert, StatusBadge } = DS();
  const { Grade, Toolbar, Kpis, Painel, Def, useNav } = CU();
  const [, tickR] = useState(0);
  const ats = REST_STORE.ats;
  const setAts = (fn) => { REST_STORE.ats = fn(REST_STORE.ats); tickR((t) => t + 1); };
  const [busca, setBusca] = useState("");
  const [densa, setDensa] = useState(false);
  const [sel, setSel] = useState(null);
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  const boxRef = useRef(null), buscaRef = useRef(null);
  if (useNav) useNav(boxRef, buscaRef, setDensa);
  const ir = (r) => window.__selectRoute && window.__selectRoute(r);
  const mesas = view === "cfg-mesas";
  const fechar = () => setSel(null);
  const bad = (v) => StatusBadge ? <StatusBadge kind="documento" value={v} /> : v;
  const casa = (s) => !busca || String(s).toLowerCase().includes(busca.toLowerCase());
  const oc = ocupacao(ats);
  const mesaDe = (a) => { const k = Object.keys(oc).find((id) => oc[id].id === a.id); return k ? (MESAS.find((m) => m.id === +k) || {}).nome : null; };

  const abrirAtendimento = (a) => {
    const livre = MESAS.filter((m) => m.loc === a.loc && !oc[m.id])[0];
    setAts((L) => L.map((x) => x.id === a.id ? { ...x, status: "Em atendimento" } : x));
    fechar(); avisar(a.nome + " em atendimento" + (livre ? " na " + livre.nome : " — sem mesa livre em " + a.loc) + ".", livre ? "ok" : "warn");
  };
  const encerrar = (a) => {
    const m = mesaDe(a);
    setAts((L) => L.map((x) => x.id === a.id ? { ...x, status: "Disponível", pedidos: x.pedidos + 1 } : x));
    fechar(); avisar(a.nome + " encerrou o atendimento" + (m ? " — " + m + " ficou livre" : "") + ".", "ok");
  };

  const colsMesa = [
    { key: "nome", label: "Mesa", width: 140, sortable: true },
    { key: "loc", label: "Local do negócio", width: 170 },
    { key: "desc", label: "Descrição", width: 280 },
    { key: "estado", label: "Situação", width: 140 },
    { key: "quem", label: "Atendente", width: 180 },
    { key: "acao", label: "Ação", width: 84, resizable: false },
  ];
  const colsAt = [
    { key: "acao", label: "Ação", width: 84, resizable: false },
    { key: "nome", label: "Atendente", width: 190, sortable: true },
    { key: "loc", label: "Local do negócio", width: 170 },
    { key: "mesa", label: "Mesa agora", width: 140 },
    { key: "pedidos", label: "Pedidos hoje", width: 134, align: "right", mono: true },
    { key: "status", label: "Situação", width: 164 },
  ];
  const linhas = mesas
    ? MESAS.filter((m) => casa(m.nome + " " + m.loc)).map((m) => ({
        id: m.id, _m: m, state: oc[m.id] ? "urgent" : undefined,
        acao: Kebab ? <Kebab acoes={[
          { l: "Ver mesa", ic: "search", on: () => setSel({ k: "mesa", d: m }) },
          { l: "Editar", ic: "pencil", on: () => avisar("Editando " + m.nome + ".", "ok") },
          "-",
          { l: "Excluir", ic: "x", tone: "danger", on: () => avisar(oc[m.id] ? m.nome + " está em atendimento com " + oc[m.id].nome + " — não se exclui agora." : m.nome + " excluída.", oc[m.id] ? "warn" : "ok") },
        ]} /> : null,
        nome: m.nome, loc: m.loc, desc: m.desc,
        estado: bad(oc[m.id] ? "Ocupada" : "Livre"), quem: oc[m.id] ? oc[m.id].nome : "—",
      }))
    : ats.filter((a) => casa(a.nome + " " + a.loc)).map((a) => ({
        id: a.id, _a: a, state: a.status === "Fora do turno" ? "archived" : undefined,
        acao: Kebab ? <Kebab acoes={[
          { l: "Ver atendente", ic: "search", on: () => setSel({ k: "at", d: a }) },
          ...(a.status === "Em atendimento"
            ? [{ l: "Encerrar atendimento", ic: "check", on: () => encerrar(a) }]
            : [{ l: "Abrir atendimento", ic: "play", on: () => abrirAtendimento(a) }]),
        ]} /> : null,
        nome: a.nome, loc: a.loc, mesa: mesaDe(a) || "—", pedidos: a.pedidos, status: bad(a.status),
      }));

  const painel = () => {
    if (!sel || !Painel) return null;
    if (sel.k === "mesa") {
      const m = sel.d, a = oc[m.id];
      return <Painel aberto onClose={fechar} titulo={m.nome} sub={m.loc} badge={bad(a ? "Ocupada" : "Livre")} largura={460}
        secoes={[
          { t: "Mesa", c: <Def pares={[["Local do negócio", m.loc], ["Situação", a ? "ocupada" : "livre"], ["Atendente", a ? a.nome : "—"], ["Pedidos do atendente hoje", a ? String(a.pedidos) : "—"]]} /> },
          { t: "Descrição", c: <p className="cu-nota">{m.desc}</p> },
          { t: "Como a situação é apurada", c: <p className="cu-nota">A mesa não guarda um campo "ocupada": ela está ocupada enquanto existe atendente em atendimento neste local. Encerre o atendimento em Atendentes e ela fica livre aqui na hora.</p> },
        ]}
        acoes={<div className="cu-dr-acoes">
          {a && <button className="os-btn primary" onClick={() => encerrar(a)}>Encerrar atendimento</button>}
          <div className="sp" />
          <button className="os-btn" onClick={() => { fechar(); ir("cfg-atendentes"); }}>Ver atendentes</button>
        </div>} />;
    }
    const a = sel.d, m = mesaDe(a);
    return <Painel aberto onClose={fechar} titulo={a.nome} sub={a.loc} badge={bad(a.status)} largura={460}
      secoes={[
        { t: "Atendente", c: <Def pares={[["Local do negócio", a.loc], ["Situação", a.status], ["Mesa agora", m || "nenhuma"], ["Pedidos hoje", String(a.pedidos)]]} /> },
        { t: "De onde vem", c: <p className="cu-nota">O atendente é um usuário do ERP marcado como pessoal de atendimento — não se cadastra pessoa nova aqui, marca-se em Usuários.</p> },
      ]}
      acoes={<div className="cu-dr-acoes">
        {a.status === "Em atendimento"
          ? <button className="os-btn primary" onClick={() => encerrar(a)}>Encerrar atendimento</button>
          : <button className="os-btn primary" onClick={() => abrirAtendimento(a)}>Abrir atendimento</button>}
        <div className="sp" />
        <button className="os-btn" onClick={() => { fechar(); ir("usuarios"); }}>Abrir em Usuários</button>
      </div>} />;
  };

  const ocupadas = MESAS.filter((m) => oc[m.id]).length;
  return (
    <div className="pb-root vb-root" data-screen-label={"Restaurante · " + (mesas ? "Mesas" : "Atendentes")} ref={boxRef}>
      {M.Header &&
        <M.Header modulo="Restaurante" papel={mesas ? "Mesas" : "Atendentes"}
          contexto={["OFFICEIMPRESSO", "matriz", ocupadas + " de " + MESAS.length + " mesas ocupadas"]}
          atualizadoAs={new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}
          glyph={<Ic name="grid" />}
          acoes={<button className="os-btn primary" onClick={() => avisar(mesas ? "Nova mesa — nome e local obrigatórios." : "Atendente vem de Usuários, marcado como pessoal de atendimento.", "ok")}><Ic name="plus" size={13} /> Adicionar</button>} />}
      <div className="pb-body">
        <nav className="cli-moduletopnav vb-nav" aria-label="Restaurante">
          <button className={"cli-moduletopnav-tab " + (mesas ? "active" : "")} onClick={() => ir("cfg-mesas")}>Mesas</button>
          <button className={"cli-moduletopnav-tab " + (!mesas ? "active" : "")} onClick={() => ir("cfg-atendentes")}>Atendentes</button>
        </nav>
        {Alert && <Alert tone="info" title="Fora do piloto de comunicação visual">Mesa e atendente pertencem ao módulo Restaurante do legado — a permissão é uma só (<span className="mono">access_tables</span>) e vale pra tela inteira. Ficam aqui para quem liga o módulo; o relatório de mesas segue declarado fora de escopo no catálogo de relatórios.</Alert>}
        {Grade &&
          <>
            <Kpis itens={[
              { l: mesas ? "Mesas ocupadas" : "Em atendimento", v: mesas ? ocupadas + " de " + MESAS.length : String(ats.filter((a) => a.status === "Em atendimento").length), tom: ocupadas ? "warn" : "pos", n: "derivado de quem está em atendimento" },
              { l: mesas ? "Livres" : "Disponíveis", v: String(mesas ? MESAS.length - ocupadas : ats.filter((a) => a.status === "Disponível").length), tom: "pos", n: mesas ? "prontas para receber cliente" : "no turno, sem cliente na mesa" },
              { l: "Pedidos hoje", v: String(ats.reduce((a, x) => a + x.pedidos, 0)), n: "somando o pessoal de atendimento" },
            ]} />
            <Widget flush titulo={<><Ic name="grid" size={13} /> {mesas ? "Todas as suas mesas" : "Pessoal de atendimento"}</>} nota={linhas.length + (mesas ? " mesa(s)" : " atendente(s)")}>
              <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph={mesas ? "Buscar mesa ou local…" : "Buscar atendente ou local…"} densa={densa} setDensa={setDensa} />
              <Grade densa={densa} columns={mesas ? colsMesa : colsAt} rows={linhas} altura={240}
                onRowClick={(r) => { const c = r.cells || r; setSel(mesas ? { k: "mesa", d: c._m } : { k: "at", d: c._a }); }} />
            </Widget>
          </>}
      </div>
      {painel()}
      {avisoNode}
    </div>
  );
}

Object.assign(window, { WooCommercePage, RestauranteExtrasPage });
})();
