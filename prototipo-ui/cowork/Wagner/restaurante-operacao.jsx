// restaurante-operacao.jsx — Restaurante: Reservas, Cozinha e Pedidos (P1 do playbook restaurante,
// 2026-10-07). Fonte de forma = Blades vivas lidas @a6a073502b1e:
//   · restaurant/booking/{create,show}: local · cliente · correspondente · mesa · atendente ·
//     início · fim · nota do cliente · "enviar notificação ao cliente"; status da reserva
//     (aguardando · reservado · concluído · cancelado) trocado no detalhe; excluir reserva.
//   · restaurant/kitchen/index + partials/show_orders: cartão por pedido (#nota · feito às ·
//     situação · cliente · mesa · local) + "Marcar como preparado".
//   · restaurant/orders/index: filtro por atendente (só p/ quem não é atendente), "Itens do
//     pedido" (line orders) com "Marcar como servido" por item, e "Todos os seus pedidos".
// Situação do pedido DERIVADA dos itens (show_orders.blade.php): todos preparados → preparado;
// todos servidos → servido; parte servida → servido em parte; parte preparada → preparado em
// parte; senão recebido. Cozinha e garçom mexem nos MESMOS itens — uma fonte só.
// Mesas e atendentes vêm de window.RestauranteDados (integra-extras.jsx).
// Expõe window.RestauranteOperacaoPage.
(() => {
const { useState, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const UI = () => window.PBUI || {};
const MP = () => window.ModuloPadrao || {};
const CU = () => window.CatchupUI || {};
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const RD = () => window.RestauranteDados || { MESAS: [], REST_STORE: { ats: [] } };

const ABAS = [
  { key: "cfg-mesas", label: "Mesas" }, { key: "cfg-atendentes", label: "Atendentes" },
  { key: "cfg-reservas", label: "Reservas" }, { key: "cfg-cozinha", label: "Cozinha" }, { key: "cfg-pedidos-rest", label: "Pedidos" },
];

const RESERVA_ST = { waiting: "Aguardando", booked: "Reservado", completed: "Concluído", cancelled: "Cancelado" };
const RESERVAS0 = [
  { id: 1, cli: "Rota Livre Transportes", loc: "Matriz", mesa: "Mesa 02", at: "Larissa Prado", corr: "Wagner Rocha", ini: "08/10 10:00", fim: "08/10 11:00", nota: "Aprovação da arte da frota — trazer provas impressas.", st: "booked" },
  { id: 2, cli: "Padaria Bom Grão", loc: "Matriz", mesa: "Mesa 01", at: "Eliana Souza", corr: "—", ini: "08/10 14:30", fim: "08/10 15:00", nota: "", st: "waiting" },
  { id: 3, cli: "Clínica Sorriso", loc: "Filial Centro", mesa: "Mesa 03", at: "Marcos Vinícius", corr: "—", ini: "07/10 16:00", fim: "07/10 16:30", nota: "Retirada de fachada em ACM.", st: "completed" },
  { id: 4, cli: "Auto Peças Zé", loc: "Matriz", mesa: "—", at: "—", corr: "—", ini: "09/10 09:00", fim: "09/10 09:30", nota: "", st: "cancelled" },
];
const PEDIDOS0 = [
  { id: 11, nota: "0S00141", feito: "07/10 11:42", cli: "Rota Livre Transportes", mesa: "Mesa 02", loc: "Matriz", at: "Larissa Prado",
    itens: [{ id: 1, nome: "Café expresso", q: 2, st: "served" }, { id: 2, nome: "Pão de queijo (porção)", q: 1, st: "cooked" }] },
  { id: 12, nota: "0S00142", feito: "07/10 11:55", cli: "Cliente padrão", mesa: "Mesa 01", loc: "Matriz", at: "Larissa Prado",
    itens: [{ id: 3, nome: "Água sem gás", q: 1, st: "received" }, { id: 4, nome: "Bolo de cenoura", q: 1, st: "received" }] },
  { id: 13, nota: "0S00143", feito: "07/10 12:03", cli: "Clínica Sorriso", mesa: "Mesa 03", loc: "Filial Centro", at: "Marcos Vinícius",
    itens: [{ id: 5, nome: "Suco de laranja", q: 2, st: "cooked" }, { id: 6, nome: "Misto quente", q: 2, st: "cooked" }] },
];
const STORE = { reservas: RESERVAS0, pedidos: PEDIDOS0 };
const AGORA = "07/10 12:10"; // relógio do mock — fixo pra a fila não mudar sozinha entre telas
const min = (s) => { const [d, h] = s.split(" "); const [dd, mm] = d.split("/").map(Number); const [hh, mi] = h.split(":").map(Number); return ((mm * 31 + dd) * 24 + hh) * 60 + mi; };
const espera = (p) => Math.max(0, min(AGORA) - min(p.feito));
const ATRASO_MIN = 15;
const diaRel = (s) => { const [d, h] = s.split(" "); const [ad] = AGORA.split(" "); const dd = Number(d.split("/")[0]) - Number(ad.split("/")[0]);
  return (dd === 0 ? "Hoje" : dd === 1 ? "Amanhã" : dd === -1 ? "Ontem" : d) + " · " + h; };
// conflito: mesma mesa, intervalos que se cruzam, reserva não cancelada
const conflito = (lista, n) => n.mesa && n.ini && n.fim && /^\d{2}\/\d{2} \d{2}:\d{2}$/.test(n.ini) && /^\d{2}\/\d{2} \d{2}:\d{2}$/.test(n.fim)
  ? lista.find((r) => r.mesa === n.mesa && r.st !== "cancelled" && r.st !== "completed" && min(r.ini) < min(n.fim) && min(n.ini) < min(r.fim)) : null;

const situacao = (p) => {
  const n = p.itens.length, c = p.itens.filter((i) => i.st === "cooked").length, s = p.itens.filter((i) => i.st === "served").length;
  if (c === n) return "preparado"; if (s === n) return "servido";
  if (s > 0) return "servido em parte"; if (c > 0) return "preparado em parte"; return "recebido";
};
const TOM = { recebido: "info", "preparado em parte": "warning", preparado: "warning", "servido em parte": "info", servido: "success" };

function Ticket({ p, acao, onDetalhe, bad, soFalta }) {
  const st = situacao(p);
  return (
    <article style={{ border: "1px solid " + (soFalta && espera(p) >= ATRASO_MIN ? "var(--warn)" : "var(--border)"), borderRadius: 8, background: "var(--surface)", display: "flex", flexDirection: "column", minWidth: 0 }} aria-label={"Pedido " + p.nota}>
      <header style={{ display: "flex", alignItems: "center", gap: 8, padding: "10px 12px", borderBottom: "1px solid var(--border)" }}>
        <b className="mono" style={{ fontSize: "var(--fs-4)" }}>#{p.nota}</b>
        {soFalta && <span className="mono" style={{ fontSize: "var(--fs-2)", color: espera(p) >= ATRASO_MIN ? "var(--warn)" : "var(--text-mute)" }} title={"Esperando desde " + p.feito}>há {espera(p)} min</span>}
        <span style={{ marginLeft: "auto" }}>{bad(st, TOM[st])}</span>
      </header>
      <dl style={{ display: "grid", gridTemplateColumns: "auto 1fr", gap: "4px 10px", margin: 0, padding: "10px 12px", fontSize: "var(--fs-3)" }}>
        <dt style={{ color: "var(--text-mute)" }}>Feito às</dt><dd className="mono" style={{ margin: 0 }}>{p.feito}</dd>
        <dt style={{ color: "var(--text-mute)" }}>Cliente</dt><dd style={{ margin: 0 }}>{p.cli}</dd>
        <dt style={{ color: "var(--text-mute)" }}>Mesa</dt><dd style={{ margin: 0 }}>{p.mesa}</dd>
        <dt style={{ color: "var(--text-mute)" }}>Local</dt><dd style={{ margin: 0 }}>{p.loc}</dd>
      </dl>
      <ul style={{ listStyle: "none", margin: 0, padding: "0 12px 10px", display: "grid", gap: 2, fontSize: "var(--fs-3)" }}>
        {p.itens.filter((i) => !soFalta || i.st === "received").map((i) => <li key={i.id} style={{ display: "flex", gap: 6, color: i.st === "received" ? "var(--text)" : "var(--text-mute)" }}><span className="mono">{i.q}×</span>{i.nome}</li>)}
        {soFalta && p.itens.some((i) => i.st !== "received") && <li style={{ color: "var(--text-mute)" }}>+ {p.itens.filter((i) => i.st !== "received").length} já preparado(s)</li>}
      </ul>
      <footer style={{ marginTop: "auto", display: "flex", gap: 6, padding: 10, borderTop: "1px solid var(--border)" }}>
        {acao}
        <button className="os-btn sm ghost" style={{ marginLeft: "auto" }} onClick={onDetalhe}>Detalhes</button>
      </footer>
    </article>
  );
}

function RestauranteOperacaoPage({ view = "cfg-reservas" }) {
  const M = MP();
  const { Widget, Kebab, Fld, Sel } = UI();
  const { Alert, StatusBadge, EmptyState } = DS();
  const { Grade, Toolbar, Kpis, Painel, Def, useNav } = CU();
  const { MESAS, REST_STORE } = RD();
  const [, tick] = useState(0);
  const set = (k, fn) => { STORE[k] = fn(STORE[k]); tick((t) => t + 1); };
  const [busca, setBusca] = useState("");
  const [densa, setDensa] = useState(false);
  const [sel, setSel] = useState(null);
  const [nova, setNova] = useState(null);
  const [atFiltro, setAtFiltro] = useState("");
  const [resFiltro, setResFiltro] = useState("aberto");
  const [avisoNode, avisar] = M.useAviso ? M.useAviso() : [null, () => {}];
  const boxRef = useRef(null), buscaRef = useRef(null);
  if (useNav) useNav(boxRef, buscaRef, setDensa);
  const ir = (r) => window.__selectRoute && window.__selectRoute(r);
  const fechar = () => { setSel(null); setNova(null); };
  const tomDe = { Aguardando: "warning", Reservado: "info", "Concluído": "success", Cancelado: "danger" };
  const bad = (v, tom) => StatusBadge ? <StatusBadge kind="documento" value={v} tone={tom || tomDe[v]} /> : v;
  const casa = (s) => !busca || String(s).toLowerCase().includes(busca.toLowerCase());
  const ats = (REST_STORE.ats || []).map((a) => a.nome);
  const R = STORE.reservas, P = STORE.pedidos;

  const marcarItem = (pid, iid, st, msg) => { set("pedidos", (L) => L.map((p) => p.id !== pid ? p : { ...p, itens: p.itens.map((i) => i.id === iid ? { ...i, st } : i) })); avisar(msg, "ok"); };
  const marcarPedido = (p, st, msg) => { set("pedidos", (L) => L.map((x) => x.id !== p.id ? x : { ...x, itens: x.itens.map((i) => ({ ...i, st: st === "cooked" ? (i.st === "received" ? "cooked" : i.st) : "served" })) })); fechar(); avisar(msg, "ok"); };

  // ── Reservas
  const colsRes = [
    { key: "acao", label: "Ação", width: 72, resizable: false },
    { key: "ini", label: "Início", width: 120, mono: true, sortable: true },
    { key: "cli", label: "Cliente", width: 220 },
    { key: "mesa", label: "Mesa", width: 100 },
    { key: "loc", label: "Local", width: 130 },
    { key: "at", label: "Atendente", width: 150 },
    { key: "st", label: "Situação", width: 130 },
  ];
  const RES_GRUPO = { aberto: ["waiting", "booked"], completed: ["completed"], cancelled: ["cancelled"], todas: Object.keys(RESERVA_ST) };
  const linhasRes = R.filter((r) => RES_GRUPO[resFiltro].includes(r.st) && casa(r.cli + " " + r.mesa + " " + r.loc)).sort((a, b) => min(a.ini) - min(b.ini)).map((r) => ({
    id: r.id, _r: r, state: r.st === "cancelled" ? "archived" : undefined,
    acao: Kebab ? <Kebab acoes={[
      { l: "Ver reserva", ic: "search", on: () => setSel({ k: "res", d: r }) },
      { l: "Enviar notificação ao cliente", ic: "send", on: () => avisar("Modelo \"nova reserva\" aberto para " + r.cli + " — nada sai sem você confirmar.", "ok") },
      "-",
      { l: "Excluir reserva", ic: "x", tone: "danger", on: () => { set("reservas", (L) => L.filter((x) => x.id !== r.id)); avisar("Reserva de " + r.cli + " excluída.", "ok"); } },
    ]} /> : null,
    ini: diaRel(r.ini), cli: r.cli, mesa: r.mesa, loc: r.loc, at: r.at, st: bad(RESERVA_ST[r.st]),
  }));
  const salvarReserva = () => {
    const n = nova;
    if (!n.loc || !n.cli || !n.ini || !n.fim) return avisar("Local, cliente, início e fim são obrigatórios.", "warn");
    set("reservas", (L) => [{ id: Date.now(), cli: n.cli, loc: n.loc, mesa: n.mesa || "—", at: n.at || "—", corr: n.corr || "—", ini: n.ini, fim: n.fim, nota: n.nota || "", st: "booked" }, ...L]);
    fechar(); avisar("Reserva de " + n.cli + " salva" + (n.notificar !== false ? " — notificação ao cliente na fila." : "."), "ok");
  };

  // ── Pedidos (garçom)
  const meus = P.filter((p) => !atFiltro || p.at === atFiltro);
  const linhasItens = meus.flatMap((p) => p.itens.filter((i) => i.st === "cooked").map((i) => ({ p, i }))).sort((a, b) => espera(b.p) - espera(a.p));

  const painel = () => {
    if (!Painel) return null;
    const choque = nova ? conflito(R, nova) : null;
    if (nova) return <Painel aberto onClose={fechar} titulo="Nova reserva" sub="Restaurante" largura={520}
      secoes={[...(choque && Alert ? [{ t: "Atenção", c: <Alert tone="warn" title={nova.mesa + " já está reservada nesse horário"}>{choque.cli} · {choque.ini} → {choque.fim} ({RESERVA_ST[choque.st].toLowerCase()}). Dá pra salvar mesmo assim — o legado não bloqueia —, mas confira antes.</Alert> }] : []), { t: "Reserva", c: <div className="pb-grid c2">
        <Fld label="Local do negócio" req span={2}><Sel value={nova.loc || ""} onChange={(v) => setNova({ ...nova, loc: v, mesa: "" })} options={["Matriz", "Filial Centro"]} vazio="Selecione" /></Fld>
        <Fld label="Cliente" req><input value={nova.cli || ""} onChange={(e) => setNova({ ...nova, cli: e.target.value })} placeholder="Nome do cliente" /></Fld>
        <Fld label="Correspondente"><Sel value={nova.corr || ""} onChange={(v) => setNova({ ...nova, corr: v })} options={["Wagner Rocha", ...ats]} vazio="Nenhum" /></Fld>
        <Fld label="Mesa"><Sel value={nova.mesa || ""} onChange={(v) => setNova({ ...nova, mesa: v })} options={MESAS.filter((m) => !nova.loc || m.loc === nova.loc).map((m) => m.nome)} vazio="Sem mesa" /></Fld>
        <Fld label="Atendente"><Sel value={nova.at || ""} onChange={(v) => setNova({ ...nova, at: v })} options={ats} vazio="Nenhum" /></Fld>
        <Fld label="Início" req><input value={nova.ini || ""} onChange={(e) => setNova({ ...nova, ini: e.target.value })} placeholder="dd/mm hh:mm" /></Fld>
        <Fld label="Fim" req><input value={nova.fim || ""} onChange={(e) => setNova({ ...nova, fim: e.target.value })} placeholder="dd/mm hh:mm" /></Fld>
        <Fld label="Nota do cliente" span={2}><textarea rows={3} value={nova.nota || ""} onChange={(e) => setNova({ ...nova, nota: e.target.value })} /></Fld>
        <label className="pb-chk" style={{ gridColumn: "span 2" }}><input type="checkbox" checked={nova.notificar !== false} onChange={(e) => setNova({ ...nova, notificar: e.target.checked })} /><b>Enviar notificação ao cliente</b></label>
      </div> }]}
      acoes={<div className="cu-dr-acoes"><button className="os-btn" onClick={fechar}>Cancelar</button><div className="sp" /><button className="os-btn primary" onClick={salvarReserva}>Salvar</button></div>} />;
    if (!sel) return null;
    if (sel.k === "res") {
      const r = sel.d;
      return <Painel aberto onClose={fechar} titulo={r.cli} sub={r.ini + " → " + r.fim} badge={bad(RESERVA_ST[r.st])} largura={480}
        secoes={[
          { t: "Reserva", c: <Def pares={[["Local", r.loc], ["Mesa", r.mesa], ["Atendente", r.at], ["Correspondente", r.corr], ["Início", r.ini], ["Fim", r.fim]]} /> },
          ...(r.nota ? [{ t: "Nota do cliente", c: <p className="cu-nota">{r.nota}</p> }] : []),
          { t: "Mudar situação", c: <div style={{ display: "flex", flexWrap: "wrap", gap: 6 }}>{Object.entries(RESERVA_ST).map(([k, l]) =>
              <button key={k} className={"os-btn sm" + (r.st === k ? " primary" : "")} aria-pressed={r.st === k}
                onClick={() => { set("reservas", (L) => L.map((x) => x.id === r.id ? { ...x, st: k } : x)); setSel({ k: "res", d: { ...r, st: k } }); avisar("Reserva de " + r.cli + ": " + l.toLowerCase() + ".", "ok"); }}>{l}</button>)}</div> },
        ]}
        acoes={<div className="cu-dr-acoes">
          <button className="os-btn danger" onClick={() => { set("reservas", (L) => L.filter((x) => x.id !== r.id)); fechar(); avisar("Reserva de " + r.cli + " excluída.", "ok"); }}>Excluir reserva</button>
          <div className="sp" />
          <button className="os-btn" onClick={() => avisar("Modelo \"nova reserva\" aberto para " + r.cli + ".", "ok")}><Ic name="send" size={13} /> Enviar notificação</button>
        </div>} />;
    }
    const p = P.find((x) => x.id === sel.d.id) || sel.d, st = situacao(p);
    return <Painel aberto onClose={fechar} titulo={"Pedido #" + p.nota} sub={p.cli + " · " + p.mesa} badge={bad(st, TOM[st])} largura={480}
      secoes={[
        { t: "Pedido", c: <Def pares={[["Feito às", p.feito], ["Local", p.loc], ["Mesa", p.mesa], ["Atendente", p.at]]} /> },
        { t: "Itens", c: <ul style={{ listStyle: "none", margin: 0, padding: 0, display: "grid", gap: 6 }}>{p.itens.map((i) =>
            <li key={i.id} style={{ display: "flex", alignItems: "center", gap: 8 }}><span className="mono">{i.q}×</span>{i.nome}<span style={{ marginLeft: "auto" }}>{bad({ received: "recebido", cooked: "preparado", served: "servido" }[i.st], { received: "info", cooked: "warning", served: "success" }[i.st])}</span></li>)}</ul> },
        { t: "Como a situação é apurada", c: <p className="cu-nota">O pedido não guarda situação própria: ela sai dos itens. A cozinha marca preparado, o atendente marca servido — os dois mexem nos mesmos itens.</p> },
      ]}
      acoes={<div className="cu-dr-acoes">
        {view === "cfg-cozinha" && p.itens.some((i) => i.st === "received") && <button className="os-btn primary" onClick={() => marcarPedido(p, "cooked", "Pedido #" + p.nota + " preparado — o atendente já vê.")}>Marcar como preparado</button>}
        {view === "cfg-pedidos-rest" && st !== "servido" && <button className="os-btn primary" onClick={() => marcarPedido(p, "served", "Pedido #" + p.nota + " servido.")}>Marcar como servido</button>}
        <div className="sp" />
        <button className="os-btn" onClick={() => { fechar(); ir("vendas"); }}>Abrir em Vendas</button>
      </div>} />;
  };

  const vazio = (t, d) => EmptyState ? <EmptyState variant="done" title={t} description={d} /> : <p className="cu-nota">{t}</p>;
  const falta = (p) => p.itens.some((i) => i.st === "received");
  const cozinha = P.filter((p) => falta(p) && casa(p.nota + " " + p.cli + " " + p.mesa)).sort((a, b) => espera(b) - espera(a));
  const atrasados = cozinha.filter((p) => espera(p) >= ATRASO_MIN).length;
  const titulo = { "cfg-reservas": "Reservas", "cfg-cozinha": "Cozinha", "cfg-pedidos-rest": "Pedidos" }[view];

  return (
    <div className="pb-root vb-root" data-screen-label={"Restaurante · " + titulo} ref={boxRef}>
      {M.Header &&
        <M.Header modulo="Restaurante" papel={titulo}
          contexto={["OFFICEIMPRESSO", "matriz", view === "cfg-reservas" ? R.filter((r) => r.st === "booked" || r.st === "waiting").length + " reservas em aberto" : P.length + " pedidos hoje"]}
          atualizadoAs={new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}
          glyph={<Ic name="grid" />}
          acoes={view === "cfg-reservas"
            ? <button className="os-btn primary" onClick={() => setNova({ loc: "Matriz", notificar: true })}><Ic name="plus" size={13} /> Nova reserva</button>
            : <button className="os-btn" onClick={() => { tick((t) => t + 1); avisar("Pedidos atualizados.", "ok"); }}><Ic name="refresh" size={13} /> Atualizar</button>} />}
      <div className="pb-body">
        {window.CliTabs && <window.CliTabs ariaLabel="Restaurante" className="vb-nav" tabs={ABAS} active={view} onChange={ir} />}

        {view === "cfg-reservas" && Grade && <>
          <Kpis itens={[
            { l: "Hoje e adiante", v: String(R.filter((r) => r.st === "booked" || r.st === "waiting").length), n: "reservado + aguardando" },
            { l: "Aguardando confirmação", v: String(R.filter((r) => r.st === "waiting").length), tom: R.some((r) => r.st === "waiting") ? "warn" : "pos", n: "cliente ainda não confirmou" },
            { l: "Concluídas", v: String(R.filter((r) => r.st === "completed").length), tom: "pos", n: "atendimento feito" },
          ]} />
          <Widget flush titulo={<><Ic name="calendar" size={13} /> Todas as reservas</>} nota={linhasRes.length + " reserva(s)"}>
            <div style={{ display: "flex", gap: 6, padding: "8px 12px 0" }} role="group" aria-label="Situação da reserva">
              {[["aberto", "Em aberto"], ["completed", "Concluídas"], ["cancelled", "Canceladas"], ["todas", "Todas"]].map(([k, l]) =>
                <button key={k} className={"os-btn sm" + (resFiltro === k ? " primary" : " ghost")} aria-pressed={resFiltro === k} onClick={() => setResFiltro(k)}>{l} <span className="mono">{R.filter((r) => RES_GRUPO[k].includes(r.st)).length}</span></button>)}
            </div>
            <Toolbar busca={busca} setBusca={setBusca} buscaRef={buscaRef} ph="Buscar cliente, mesa ou local…" densa={densa} setDensa={setDensa} />
            <Grade densa={densa} columns={colsRes} rows={linhasRes} altura={280} onRowClick={(r) => setSel({ k: "res", d: (r.cells || r)._r })} />
          </Widget>
        </>}

        {view === "cfg-cozinha" && <>
          {Kpis && <Kpis itens={[
            { l: "Na fila", v: String(cozinha.length), n: "pedidos com item por preparar" },
            { l: "Itens por preparar", v: String(cozinha.reduce((a, p) => a + p.itens.filter((i) => i.st === "received").reduce((x, i) => x + i.q, 0), 0)), n: "somando as quantidades" },
            { l: "Esperando há " + ATRASO_MIN + " min ou mais", v: String(atrasados), tom: atrasados ? "warn" : "pos", n: "o mais antigo fica primeiro" },
          ]} />}
          {Alert && <Alert tone="info" title="A fila da cozinha é o que falta preparar">O pedido mais antigo vem primeiro. Ele sai daqui quando todos os itens estão preparados — o atendente passa a ver em Pedidos.</Alert>}
          <Widget titulo={<><Ic name="list" size={13} /> Pedidos na cozinha</>} nota={cozinha.length + " pedido(s)"}>
            {cozinha.length
              ? <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(240px, 1fr))", gap: 12 }}>
                  {cozinha.map((p) => <Ticket key={p.id} p={p} bad={bad} soFalta onDetalhe={() => setSel({ k: "ped", d: p })}
                    acao={<button className="os-btn sm primary" onClick={() => marcarPedido(p, "cooked", "Pedido #" + p.nota + " preparado.")}><Ic name="check" size={12} /> Marcar como preparado</button>} />)}
                </div>
              : vazio("Nada para preparar", "Os pedidos novos aparecem aqui assim que o atendente lança.")}
          </Widget>
        </>}

        {view === "cfg-pedidos-rest" && <>
          <div className="pb-filters-h" style={{ maxWidth: 360 }}>
            <Fld label="Atendente"><Sel value={atFiltro} onChange={setAtFiltro} options={ats} vazio="Todos os atendentes" /></Fld>
          </div>
          <Widget titulo={<><Ic name="check" size={13} /> Itens prontos para servir</>} nota={linhasItens.length + " item(ns)"}>
            {linhasItens.length
              ? <div style={{ display: "grid", gap: 12 }}>
                  {[...new Set(linhasItens.map((x) => x.p.mesa))].map((mesa) => {
                    const doMesa = linhasItens.filter((x) => x.p.mesa === mesa);
                    return <section key={mesa} aria-label={mesa}>
                      <h4 style={{ margin: "0 0 6px", fontSize: "var(--fs-3)", color: "var(--text-dim)", display: "flex", gap: 8 }}>{mesa}<span style={{ color: "var(--text-mute)", fontWeight: 400 }}>{doMesa[0].p.cli}</span>
                        {doMesa.length > 1 && <button className="os-btn sm ghost" style={{ marginLeft: "auto" }} onClick={() => { doMesa.forEach(({ p, i }) => { STORE.pedidos = STORE.pedidos.map((x) => x.id !== p.id ? x : { ...x, itens: x.itens.map((k) => k.id === i.id ? { ...k, st: "served" } : k) }); }); tick((t) => t + 1); avisar(doMesa.length + " itens servidos na " + mesa + ".", "ok"); }}>Servir tudo da mesa</button>}</h4>
                      <ul style={{ listStyle: "none", margin: 0, padding: 0, display: "grid", gap: 6 }}>
                        {doMesa.map(({ p, i }) => <li key={p.id + "-" + i.id} style={{ display: "flex", alignItems: "center", gap: 10, padding: "8px 10px", border: "1px solid var(--border)", borderRadius: 6 }}>
                          <span className="mono">#{p.nota}</span><span className="mono">{i.q}×</span><span>{i.nome}</span>
                          <span className="mono" style={{ color: "var(--text-mute)" }}>há {espera(p)} min</span>
                          <button className="os-btn sm primary" style={{ marginLeft: "auto" }} onClick={() => marcarItem(p.id, i.id, "served", i.nome + " servido — #" + p.nota + ".")}>Marcar como servido</button>
                        </li>)}
                      </ul>
                    </section>; })}
                </div>
              : vazio("Nenhum item esperando", "Quando a cozinha marca um item como preparado, ele aparece aqui.")}
          </Widget>
          <Widget titulo={<><Ic name="orders" size={13} /> Todos os seus pedidos</>} nota={meus.length + " pedido(s)"}>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(240px, 1fr))", gap: 12 }}>
              {meus.map((p) => <Ticket key={p.id} p={p} bad={bad} onDetalhe={() => setSel({ k: "ped", d: p })}
                acao={situacao(p) !== "servido" ? <button className="os-btn sm primary" onClick={() => marcarPedido(p, "served", "Pedido #" + p.nota + " servido.")}>Marcar como servido</button> : null} />)}
            </div>
          </Widget>
        </>}
      </div>
      {painel()}
      {avisoNode}
    </div>
  );
}

Object.assign(window, { RestauranteOperacaoPage, RestauranteAbas: ABAS, RestauranteProximaReserva: (mesa) => STORE.reservas.filter((r) => r.mesa === mesa && (r.st === "booked" || r.st === "waiting")).sort((a, b) => min(a.ini) - min(b.ini))[0] || null });
})();
