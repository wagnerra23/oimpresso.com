// producao-cv-page.jsx — módulo Produção (comunicação visual): a OS como projeto.
// Vistas: Painel do gestor · Quadro por setor · Linha do tempo por máquina · Custo real × orçado ·
// Instalação · Apontamento (celular) · Roteiros · Postos e cadastros. A ficha da OS (projeto) abre em drawer PT-02 de 760px.
// Diferenciais sobre o Mubisys (grade 33-grade-producao-concorrentes.md): quadro + linha do tempo, custo real × orçado,
// refugo com motivo, instalação com foto e assinatura, aprovação de arte online, encaixe na bobina, alerta de gargalo.
// Casos de uso e como testar: cowork-inbox/producao/Producao.casos.md (UC-PCV-01..28).
// Expõe window.ProducaoCvPage. Depende de window.PCVD, window.PcvOs (ficha/custo/roteiros), window.PcvCampo.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const P = () => window.PCVD;
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <span aria-hidden="true" style={{ display: "inline-flex" }}><F size={size} /></span> : null; };

const VISTAS = [
  { key: "painel", label: "Painel" }, { key: "quadro", label: "Quadro" }, { key: "linha", label: "Linha do tempo" },
  { key: "custo", label: "Custo real × orçado" }, { key: "instalacao", label: "Instalação" },
  { key: "apontamento", label: "Apontamento" }, { key: "roteiros", label: "Roteiros" },
  { key: "postos", label: "Postos e cadastros" },
];
const PRIO = { urgente: ["Urgente", "danger"], alta: ["Alta", "warning"], normal: ["Normal", "neutral"] };
const ARTE = { aprovada: ["Arte aprovada", "success"], aguardando: ["Aguardando cliente", "warning"], ajuste: ["Ajuste pedido", "danger"] };

function Badge({ par }) { const { StatusBadge } = DS(); return par ? (StatusBadge ? <StatusBadge label={par[0]} tone={par[1]} /> : <span>{par[0]}</span>) : null; }
window.PcvBadge = Badge;

// ── Filtro compartilhado (quadro e linha) ──
const FILTROS = [{ value: "todas", label: "Todas" }, { value: "atrasadas", label: "Atrasadas ou vencendo" }, { value: "arte", label: "Travadas por arte" }, { value: "urgentes", label: "Urgentes" }];
const passa = (D, o, f) => {
  if (f.q) { const s = f.q.toLowerCase(); if (![o.id, o.cliente, o.titulo].some((v) => v.toLowerCase().includes(s))) return false; }
  if (f.so === "atrasadas" && (D.atual(o).estado === "pronto" || !(D.atraso(o) === "atrasada" || D.atraso(o) === "vence hoje"))) return false;
  if (f.so === "arte" && o.arte === "aprovada") return false;
  if (f.so === "urgentes" && !(o.prioridade === "urgente" || o.prioridade === "alta")) return false;
  if (f.resp && D.atual(o).resp !== f.resp) return false;
  return true;
};
function BarraFiltro({ f, setF }) {
  const D = P(); const { Input, Segmented, Select } = DS();
  const resps = [...new Set(D.OS.map((o) => D.atual(o).resp))];
  return (
    <div className="pcv-filtro" role="search" aria-label="Filtrar OS">
      {Input && <div className="pcv-filtro-q"><Input value={f.q} placeholder="OS, cliente ou serviço" onChange={(e) => setF({ ...f, q: e.target.value })} /></div>}
      {Segmented && <Segmented size="sm" value={f.so} onChange={(v) => setF({ ...f, so: v })} options={FILTROS.map((x) => ({ ...x, count: x.value === "todas" ? undefined : D.OS.filter((o) => passa(D, o, { so: x.value })).length }))} />}
      {Select && <div className="pcv-filtro-r"><Select value={f.resp} onChange={(e) => setF({ ...f, resp: e.target.value })} options={[{ value: "", label: "Qualquer responsável" }, ...resps.map((r) => ({ value: r, label: r }))]} /></div>}
      {(f.q || f.so !== "todas" || f.resp) && <button className="pcv-link" onClick={() => setF({ q: "", so: "todas", resp: "" })}>Limpar</button>}
    </div>
  );
}

// ── Cartão de OS (quadro) ── o cartão abre a OS; o menu ⋮ move a etapa (PCP) sem arrastar — funciona no teclado
function CartaoOs({ os, onAbrir, setorAtual, onMover, onPegar, arrastando }) {
  const D = P(); const { Progress, Kebab } = DS();
  const at = D.atual(os); const pr = D.progresso(os); const atr = D.atraso(os);
  return (
    <div className={"pcv-card" + (atr === "atrasada" ? " is-late" : "") + (onPegar ? " is-movel" : "") + (arrastando ? " is-arr" : "")}
      onPointerDown={(e) => { if (!onPegar || e.button !== 0 || e.target.closest("[data-sem-arrastar]")) return; e.preventDefault(); onPegar(e, os.id); }}
      onClickCapture={(e) => { if (window.__pcvArrastou) { e.stopPropagation(); e.preventDefault(); window.__pcvArrastou = false; } }}>
      <div className="pcv-card-h"><button className="pcv-card-open" onClick={() => onAbrir(os.id)} aria-label={`Abrir ${os.id} · ${os.titulo}`}><b className="mono">{os.id}</b></button>{B2(PRIO[os.prioridade])}
        {Kebab && onMover && <span data-sem-arrastar><Kebab label={"Mais ações de " + os.id} items={[{ id: "abrir", label: "Abrir a OS", onSelect: () => onAbrir(os.id) }, { separator: true }, ...D.SETORES.filter((s) => s.id !== setorAtual).map((s) => ({ id: s.id, label: "Mover para " + s.nome, onSelect: () => onMover(os.id, s.id) }))]} /></span>}
      </div>
      <button className="pcv-card-body" onClick={() => onAbrir(os.id)} tabIndex={-1} aria-hidden="true">
        <div className="pcv-card-t">{os.titulo}</div>
        <div className="pcv-card-c">{os.cliente}</div>
        <div className="pcv-card-et"><span className="pcv-dot" style={{ "--c": D.setorDe(at.setor).cor }} />{at.nome}{at.maq ? " · " + D.maqDe(at.maq).nome : ""}</div>
      </button>
      {Progress && <Progress value={pr} size={6} tone={atr === "atrasada" ? "danger" : "default"} label={"Progresso " + pr + "%"} />}
      {(() => { const ev = D.eventosDe(os.id)[0]; return ev ? <div className="pcv-card-ev" title={ev.quando + " · " + ev.quem}><time className="mono">{ev.quando.slice(6)}</time> {ev.quem}: {ev.texto}</div> : null; })()}
      <div className="pcv-card-f">
        <span className={atr ? "pcv-late" : ""}>{atr ? atr : "entrega " + D.d2(os.prazo)}</span>
        {os.arte !== "aprovada" && B2(ARTE[os.arte])}
        {os.material === "aguardando" && B2(["Aguardando material", "warning"])}
        {window.PcvTarefas && <window.PcvTarefas.SeloCartao id={os.id} />}
        <span className="pcv-who">{at.resp}</span>
      </div>
    </div>
  );
}
const B2 = (par) => <Badge par={par} />;

// ── Eventos: uma fonte (PCVD.EVENTOS), muitas vistas ──
function useEventos() {
  const [, setN] = useState(0);
  React.useEffect(() => { const f = () => setN((n) => n + 1); window.addEventListener("pcv:evento", f); return () => window.removeEventListener("pcv:evento", f); }, []);
  return P().EVENTOS;
}
function Feed({ eventos, onAbrir, max = 10, compacto, vazio = "Nada por aqui ainda." }) {
  const D = P();
  const lst = eventos.slice(0, max);
  if (!lst.length) return <p className="pcv-help" style={{ padding: "8px 14px" }}>{vazio}</p>;
  return (
    <ol className={"pcv-feed" + (compacto ? " is-compacto" : "")} aria-live="polite" aria-relevant="additions">
      {lst.map((e, i) => (
        <li key={i + e.quando + e.texto} className={"is-" + e.tipo}>
          <span className="pcv-feed-dot" aria-hidden="true" />
          <div className="pcv-feed-m">
            <span className="pcv-feed-t">{e.texto}</span>
            <small><time className="mono">{e.quando}</time> · {e.quem}{e.maq && D.maqDe(e.maq) ? " · " + D.maqDe(e.maq).nome : ""}</small>
          </div>
          {onAbrir && e.os && <button className="pcv-link mono" onClick={() => onAbrir(e.os)}>{e.os}</button>}
        </li>))}
    </ol>
  );
}
window.PcvEventos = { Feed, useEventos };

// ── Painel do gestor ──
function Painel({ onAbrir, ir, filtrar }) {
  const eventos = useEventos();
  const [reenviado, setReenviado] = useState({});
  const [novaProva, setNovaProva] = useState({});
  const D = P(); const { KpiCard, Widget, Alert, Progress, Button, Input } = DS();
  const abertas = D.emProducao();
  const atrasadas = D.OS.filter((o) => D.atraso(o) === "atrasada" && D.atual(o).estado !== "pronto");
  const hoje = D.OS.filter((o) => D.atraso(o) === "vence hoje");
  const aguardArte = D.OS.filter((o) => o.arte !== "aprovada");
  const orc = D.OS.reduce((s, o) => s + o.orcado, 0), real = D.OS.reduce((s, o) => s + o.real, 0);
  const refugo = D.OS.flatMap((o) => o.refugo || []);
  return (
    <div className="pcv-body">
      <div className="pcv-kpis">
        <button className="pcv-kpi-btn" onClick={() => filtrar("todas")} aria-label="Ver todas as OS em produção no quadro">{KpiCard && <KpiCard label="OS em produção" value={abertas.length} description={hoje.length + " vencem hoje"} tone="info" />}</button>
        <button className="pcv-kpi-btn" onClick={() => filtrar("atrasadas")} aria-label="Ver atrasadas no quadro">{KpiCard && <KpiCard label="Atrasadas ou vencendo" value={atrasadas.length + hoje.length} description={[...atrasadas, ...hoje].map((o) => o.id).join(", ") || "nenhuma"} tone={atrasadas.length ? "danger" : "warning"} />}</button>
        <button className="pcv-kpi-btn" onClick={() => filtrar("arte")} aria-label="Ver OS travadas por arte no quadro">{KpiCard && <KpiCard label="Arte travando" value={aguardArte.length} description="aguardando aprovação ou ajuste" tone="warning" />}</button>
        <button className="pcv-kpi-btn" onClick={() => ir("custo")} aria-label="Ver perdas no custo">{KpiCard && <KpiCard label="Refugo na semana" value={D.fmt(D.PERDAS.reduce((s, r) => s + r.custo, 0))} description={D.PERDAS.length + " ocorrência(s)"} tone="danger" />}</button>
      </div>

      <div className="pcv-alertas" role="region" aria-label="Alertas de produção">
        {Alert && <Alert tone="danger" title="Gargalo previsto: Roland VG3-640 passa da capacidade do dia">Carga de {D.h1(D.cargaMaquina("eco"))} h para uma capacidade de {D.maqDe("eco").horasDia} h no dia. Mover a OS-2608 (adesivo de aplicação, 1,1 h) para a HP Latex 365, que tem {D.h1(D.maqDe("latex").horasDia - D.cargaMaquina("latex"))} h livres.<div className="pcv-al-a"><Button size="sm" onClick={() => ir("linha")}>Ver na linha do tempo</Button></div></Alert>}
        {Alert && <Alert tone="warn" title="2 OS esperando arte do cliente">OS-2614 (Clínica Sorriso) trava 6,3 h de CNC e UV hoje. OS-2619 pediu ajuste. O link de aprovação foi reenviado às 09:12.<div className="pcv-al-a"><Button size="sm" onClick={() => onAbrir("OS-2614")}>Abrir OS-2614</Button></div></Alert>}
        {/* US-PCP-032 · D3: OS liberada sem material. Um alerta por OS, com a ação de abrir a ficha (UC-PCV-01). */}
        {Alert && D.OS.filter((o) => o.material === "aguardando").map((o) => { const fl = D.faltaDe(o.id); return (
          <Alert key={o.id} tone="warn" title={o.id + " está aguardando material"}>{fl.length
            ? "Falta " + fl.map((f) => D.h1(f.falta) + " " + f.un + " de " + f.insumo + (f.caminho.length ? " (compra " + f.caminho.map((c) => c.compra).join(", ") + " chega " + D.d2(f.caminho[0].chega) + ")" : " (nenhuma compra pedida)")).join("; ") + ". Nenhuma etapa começa até o PCP conferir."
            : "A compra chegou. Falta o PCP conferir para a OS voltar para Na fila."}
            <div className="pcv-al-a"><Button size="sm" onClick={() => onAbrir(o.id)}>Abrir {o.id}</Button></div></Alert>); })}
        {Alert && <Alert tone="warn" title="OS-2603 vence hoje e está 7,8% acima do orçado">Refugo de 9,4 m² na impressão (banding na cabeça 3). Faltam bainha e ilhós.<div className="pcv-al-a"><Button size="sm" onClick={() => onAbrir("OS-2603")}>Abrir OS-2603</Button></div></Alert>}
      </div>

      {Widget && <Widget title="Fila de aprovação de arte" note="o cliente aprova pelo link de prova, sem login" flush>
        <ul className="pcv-fila-arte">
          {aguardArte.map((o) => (
            <li key={o.id}><div><b className="mono">{o.id}</b> · {o.cliente}<small>{o.arte === "ajuste" ? "pediu ajuste às 08:47 — \"aumentar o logo e trocar o telefone\"" : "link enviado ontem 17:30 · visto 2 vezes"}</small></div>
              <Badge par={ARTE[o.arte]} />
              <Button size="sm" disabled={reenviado[o.id]} onClick={() => { if (o.arte === "ajuste") { setNovaProva({ ...novaProva, [o.id]: "" }); return; } setReenviado({ ...reenviado, [o.id]: true }); D.registrar({ os: o.id, tipo: "arte", texto: "Reenviou o link de prova ao cliente" }); }}>{reenviado[o.id] ? (o.arte === "ajuste" ? "Nova prova enviada" : "Reenviado agora") : o.arte === "ajuste" ? "Enviar nova prova" : "Reenviar link"}</Button>
              <button className="pcv-link" onClick={() => onAbrir(o.id)}>Abrir</button>
              {/* UC-PCART-07 (liberado por [W] 2026-10-27): a nova prova precisa do link do arquivo novo; o link anterior deixa de valer. */}
              {novaProva[o.id] !== undefined && !reenviado[o.id] && (
                <form className="pcv-prova-form" style={{ flexBasis: "100%", display: "flex", flexWrap: "wrap", gap: 8, alignItems: "end" }}
                  onSubmit={(e) => { e.preventDefault(); setReenviado({ ...reenviado, [o.id]: true }); D.registrar({ os: o.id, tipo: "arte", texto: "Enviou nova prova ao cliente" }); }}>
                  <label style={{ flex: "1 1 320px", display: "flex", flexDirection: "column", gap: 4, fontSize: 12 }}>Arquivo da nova prova (link)
                    {Input && <Input type="url" required value={novaProva[o.id]} placeholder="https://drive.exemplo.com/prova-v2.pdf" onChange={(e) => setNovaProva({ ...novaProva, [o.id]: e.target.value })} />}</label>
                  <Button size="sm" type="submit">Enviar ao cliente</Button>
                  <button type="button" className="pcv-link" onClick={() => { const n = { ...novaProva }; delete n[o.id]; setNovaProva(n); }}>Cancelar</button>
                </form>)}
              {reenviado[o.id] && <small style={{ flexBasis: "100%" }}>{o.cliente === "Clínica Sorriso" ? "Cliente sem WhatsApp cadastrado. Mande o link você mesmo: https://oimpresso.com/arte/…" : "Link novo enviado ao cliente pelo WhatsApp. O link anterior deixou de valer."}</small>}</li>))}
        </ul>
      </Widget>}

      {window.PcvTarefas && <window.PcvTarefas.TarefasPainel onAbrir={onAbrir} />}

      {Widget && <Widget title="Acontecendo agora" note="o que a fábrica fez por último — vem do apontamento, do cliente e do PCP" flush><Feed eventos={eventos} onAbrir={onAbrir} max={6} /></Widget>}

      <div className="pcv-grid2">
        {/* D4: a capacidade é a de cada posto (pcp_postos.horas_por_dia), não um turno fixo de 10 h. */}
        {Widget && <Widget title="Carga das máquinas hoje" note="horas programadas · capacidade de cada posto no dia">
          <ul className="pcv-carga">
            {D.MAQUINAS.filter((m) => m.horasDia).map((m) => { const c = D.cargaMaquina(m.id); const pct = Math.round((c / m.horasDia) * 100); return (
              <li key={m.id}><span>{m.nome}</span>{Progress && <Progress value={Math.min(pct, 100)} size={6} tone={pct > 100 ? "danger" : pct > 85 ? "warning" : "default"} label={m.nome + " " + pct + "%"} />}<b className={pct > 100 ? "pcv-late" : ""}>{D.h1(c)} de {m.horasDia} h</b></li>); })}
          </ul>
        </Widget>}
        {Widget && <Widget title="Aproveitamento de mídia" note={D.ENCAIXE.bobina}>
          <div className="pcv-encaixe">
            <div className="pcv-enc-bar" role="img" aria-label={`Usado ${D.h1(D.ENCAIXE.usado)}%, desperdício ${D.h1(D.ENCAIXE.desperdicio)}%`}><i style={{ width: D.ENCAIXE.usado + "%" }} /><span style={{ width: D.ENCAIXE.desperdicio + "%" }} /></div>
            <div className="pcv-enc-l"><span><b>{D.h1(D.ENCAIXE.usado)}%</b> aproveitado</span><span><b>{D.h1(D.ENCAIXE.desperdicio)}%</b> sobra</span></div>
            {/* D-ENCAIXE (15/10): na 1ª versão só o % aproveitado, com a mídia que o operador informa. A sugestão de
                encaixe na bobina, com economia em m² e R$, é da versão 2. */}
            <p className="pcv-help">Calculado com a mídia que o operador informa no apontamento. A sugestão de encaixe na bobina fica para a versão 2.</p>
          </div>
        </Widget>}
      </div>

      {window.PcvQualidade && <window.PcvQualidade.NcsPainel onAbrir={onAbrir} />}

      {Widget && <Widget title="Produtividade por setor (semana)" note="horas apontadas ÷ previstas" flush>
        <table className="pcv-tbl">
          <thead><tr><th scope="col">Setor</th><th scope="col" className="ta-r">Previsto</th><th scope="col" className="ta-r">Apontado</th><th scope="col" className="ta-r">Eficiência</th><th scope="col">Quem</th></tr></thead>
          <tbody>{D.SETORES.slice(0, 5).map((s) => { const es = D.OS.flatMap(D.etapas).filter((e) => e.setor === s.id && (e.estado === "feito" || e.estado === "pronto")); const p = es.reduce((a, e) => a + e.prev, 0), r = es.reduce((a, e) => a + e.real, 0); const ef = r ? Math.round((p / r) * 100) : null; return (
            <tr key={s.id}><td><span className="pcv-dot" style={{ "--c": s.cor }} />{s.nome}</td><td className="ta-r mono">{D.h1(p)} h</td><td className="ta-r mono">{D.h1(r)} h</td><td className={"ta-r mono" + (ef !== null && ef < 90 ? " pcv-late" : "")}>{ef === null ? "—" : ef + "%"}</td><td>{[...new Set(es.map((e) => e.resp))].join(", ") || "—"}</td></tr>); })}</tbody>
        </table>
      </Widget>}
    </div>
  );
}

// ── Quadro por setor ──
function Quadro({ onAbrir, f, setF }) {
  const D = P(); const { Segmented, Alert, Button } = DS();
  const eventos = useEventos();
  const [verEv, setVerEv] = useState(true);
  const [drag, setDrag] = useState(null);
  const dragRef = React.useRef(null);
  const pegar = (e, id) => {
    const ini = { id, x0: e.clientX, y0: e.clientY, x: e.clientX, y: e.clientY, moveu: false, over: null };
    dragRef.current = ini;
    document.body.classList.add("pcv-arrastando"); window.getSelection && window.getSelection().removeAllRanges();
    const mv = (ev) => { const d = dragRef.current; if (!d) return; const moveu = d.moveu || Math.abs(ev.clientX - d.x0) + Math.abs(ev.clientY - d.y0) > 6;
      const col = [...document.querySelectorAll("[data-col]")].find((r) => { const q = r.getBoundingClientRect(); return ev.clientX >= q.left && ev.clientX < q.right && ev.clientY >= q.top - 40 && ev.clientY < q.bottom + 40; });
      dragRef.current = { ...d, x: ev.clientX, y: ev.clientY, moveu, over: col ? col.getAttribute("data-col") : null }; if (moveu) { ev.preventDefault(); setDrag(dragRef.current); } };
    const up = () => { const d = dragRef.current; document.body.classList.remove("pcv-arrastando"); window.removeEventListener("pointermove", mv); window.removeEventListener("pointerup", up); dragRef.current = null; setDrag(null);
      if (d && d.moveu) { window.__pcvArrastou = true; setTimeout(() => { window.__pcvArrastou = false; }, 0); if (d.over && d.over !== setorDe(D.osDe(d.id))) mover(d.id, d.over); } };
    window.addEventListener("pointermove", mv); window.addEventListener("pointerup", up);
  };
  const [agrupar, setAgrupar] = useState("setor");
  const [mov, setMov] = useState({});
  const [aviso, setAviso] = useState(null);
  const setorDe = (o) => mov[o.id] || D.atual(o).setor;
  const lista = D.OS.filter((o) => passa(D, o, f));
  const cols = agrupar === "setor" ? D.SETORES.map((s) => ({ id: s.id, nome: s.nome, cor: s.cor, itens: lista.filter((o) => setorDe(o) === s.id) }))
    : D.MAQUINAS.map((m) => ({ id: m.id, nome: m.nome, cor: D.setorDe(m.setor).cor, itens: lista.filter((o) => D.atual(o).maq === m.id) })).filter((c) => c.itens.length);
  const mover = (id, setor) => { setMov({ ...mov, [id]: setor }); D.registrar({ os: id, tipo: "pcp", texto: "Moveu para " + D.setorDe(setor).nome + " (à mão)" }); setAviso(id + " movida para " + D.setorDe(setor).nome + " pelo PCP. Fica no histórico da OS com o seu nome."); };
  return (
    <div className="pcv-body">
      <BarraFiltro f={f} setF={setF} />
      <div className="pcv-bar">
        {Segmented && <Segmented size="sm" value={agrupar} onChange={setAgrupar} options={[{ value: "setor", label: "Por setor" }, { value: "maquina", label: "Por máquina" }]} />}
        <span className="pcv-help">{lista.length} de {D.OS.length} OS. A OS anda sozinha quando o operador fecha a etapa. Para mover à mão: arraste o cartão ou use o menu ⋮.</span>
        {Button && <Button size="sm" onClick={() => setVerEv(!verEv)} aria-pressed={verEv}>{verEv ? "Esconder eventos" : "Eventos"}</Button>}
      </div>
      {aviso && Alert && <Alert tone="info" title="Etapa movida" onClose={() => setAviso(null)}>{aviso}</Alert>}
      <div className={"pcv-quadro" + (verEv ? " com-ev" : "")}>
      <div className="pcv-board" role="list" aria-label={"Quadro por " + agrupar}>
        {cols.map((c) => (
          <section key={c.id} data-col={c.id} className={"pcv-col" + (drag && drag.moveu ? (drag.over === c.id ? (setorDe(D.osDe(drag.id)) === c.id ? " is-origem" : " is-alvo") : "") : "")} role="listitem" aria-label={c.nome + ", " + c.itens.length + " OS"} style={{ "--c": c.cor }}>
            <header className="pcv-col-h"><span className="pcv-dot" />{c.nome}<b>{c.itens.length}</b></header>
            <div className="pcv-col-b">{c.itens.length ? c.itens.map((o) => <CartaoOs key={o.id} os={o} onAbrir={onAbrir} setorAtual={setorDe(o)} onMover={agrupar === "setor" ? mover : null} onPegar={agrupar === "setor" ? pegar : null} arrastando={drag && drag.id === o.id && drag.moveu} />) : <p className="pcv-vazio">{f.q || f.so !== "todas" || f.resp ? "Nada com esse filtro." : "Nada aqui agora."}</p>}</div>
          </section>
        ))}
      </div>
      {drag && drag.moveu && <div className="pcv-ghost" style={{ left: drag.x + 12, top: drag.y + 12 }} aria-hidden="true"><b className="mono">{drag.id}</b> → {drag.over ? D.setorDe(drag.over).nome : "solte numa coluna"}</div>}
      {verEv && <aside className="pcv-evpanel" aria-label="Eventos das OS do quadro"><header><b>Eventos</b><small>das {lista.length} OS do quadro</small><button className="pcv-link" onClick={() => setVerEv(false)}>Fechar</button></header><Feed eventos={eventos.filter((e) => lista.some((o) => o.id === e.os))} onAbrir={onAbrir} max={20} compacto /></aside>}
      </div>
    </div>
  );
}

// ── Linha do tempo por máquina ──
// Arrastar um bloco: na horizontal muda o horário (encaixa de 15 em 15 min); para outra linha, só para máquina
// do MESMO setor (impressão ↔ impressão). Teclado: foco no bloco + Alt+←/→ (15 min) e Alt+↑/↓ (máquina do setor).
// Regras de Fluxos que valem aqui (main, 2026-10-19..21), na mesma ordem em que o `mover` confere:
//   · só etapa que NÃO começou se reprograma (alocador UC-FLX-10): concluída e em andamento ficam fixas, com o motivo;
//   · outro setor não aceita; choque com outro bloco recusa e diz com qual OS;
//   · máquina com operadores cadastrados (UC-FLX-08): se o responsável não opera a de destino, pergunta quem faz;
//   · o estouro é o fim do turno DA EMPRESA (pcp_turnos), e a carga vira gargalo contra a capacidade do POSTO;
//   · passar da entrega prometida ao cliente (US-PCP-028) move, mas avisa, como o estouro de turno;
//   · bloco travado por arte ou por falta de material (D3) move, mas segue travado.
const SNAP = 0.25;
function Linha({ onAbrir, f, setF }) {
  const D = P(); const { Alert, Segmented, Button, Widget } = DS();
  const T = D.TURNO;
  const eventos = useEventos();
  const [maqEv, setMaqEv] = useState(null);
  const [dia, setDia] = useState("hoje");
  const [ag, setAg] = useState(() => ({ hoje: D.AGENDA.map((b, k) => ({ ...b, k })), proximo: D.AGENDA_AMANHA.map((b, k) => ({ ...b, k: 100 + k })) }));
  const [aplicado, setAplicado] = useState(false);
  const [msg, setMsg] = useState(null);
  const [escolha, setEscolha] = useState(null);
  const [arr, setArr] = useState(null);
  const H0 = 8, H1 = 19, horas = Array.from({ length: H1 - H0 + 1 }, (_, i) => H0 + i);
  const pos = (h) => ((h - H0) / (H1 - H0)) * 100;
  const agoraH = 11.3;
  const blocos = ag[dia];
  const diaIso = D.DIAS[dia].iso;
  const visiveis = blocos.filter((b) => { const o = D.osDe(b.os); return !o || passa(D, o, f); });
  const hhmm = (h) => String(Math.floor(h)).padStart(2, "0") + ":" + String(Math.round((h % 1) * 60)).padStart(2, "0");
  // Responsável da etapa do bloco: o que o bloco já carrega (depois de uma troca) ou o da etapa da OS naquela máquina.
  const respDe = (b) => { if (b.resp) return b.resp; const o = D.osDe(b.os); const e = o && D.etapas(o).find((x) => x.maq === b.maq && x.estado !== "feito"); return e ? e.resp : D.maqDe(b.maq).operador; };
  const travaDe = (b) => [b.bloqueio, b.material].filter(Boolean).join(" · ");
  // Entrega prometida: terminar depois do fim do turno no dia prometido (ou em dia posterior) atrasa a entrega.
  const passaDaPromessa = (b, nf) => { const o = D.osDe(b.os); return o && (diaIso > o.prazo || (diaIso === o.prazo && nf > T.fim)) ? o.prazo : null; };
  const mover = (k, maqId, ini, origem, respEscolhido) => {
    const b = blocos.find((x) => x.k === k); if (!b) return false;
    const mOrig = D.maqDe(b.maq), mDest = D.maqDe(maqId);
    if (b.estado !== "fila") { setMsg({ tone: "warn", t: b.os + " " + (b.estado === "feito" ? "já foi concluída" : "já começou") + " em " + mOrig.nome + ": só a etapa que ainda não começou se reprograma." + (b.estado === "andamento" ? " Para mudar, o operador pausa ou conclui no apontamento." : "") }); return false; }
    if (mDest.setor !== mOrig.setor) { setMsg({ tone: "warn", t: "Não dá para levar " + b.os + " para " + mDest.nome + ": é de outro setor (" + D.setorDe(mDest.setor).nome + ")." }); return false; }
    const dur = b.fim - b.ini; let ni = Math.max(H0, Math.min(H1 - dur, Math.round(ini / SNAP) * SNAP)); const nf = ni + dur;
    const choca = blocos.find((x) => x.k !== k && x.maq === maqId && ni < x.fim && nf > x.ini);
    if (choca) { setMsg({ tone: "warn", t: "Choca com " + choca.os + " (" + hhmm(choca.ini) + "–" + hhmm(choca.fim) + ") em " + mDest.nome + ". Escolha outro horário." }); return false; }
    const resp = respEscolhido || respDe(b);
    if (maqId !== b.maq && mDest.operadores.length && !mDest.operadores.includes(resp)) { setEscolha({ k, maqId, ini: ni, origem, resp, opcoes: mDest.operadores }); setMsg(null); return false; }
    const turno = nf > T.fim ? "termina " + hhmm(nf) + " — passa do fim do turno (" + hhmm(T.fim) + ")" : null;
    const promessa = passaDaPromessa(b, nf);
    const conf = [turno, promessa ? "passa da entrega prometida (" + D.d2(promessa) + ")" : null].filter(Boolean).join(" · ") || null;
    setAg({ ...ag, [dia]: blocos.map((x) => x.k === k ? { ...x, maq: maqId, ini: ni, fim: nf, conflito: conf, movido: true, resp } : x) });
    setEscolha(null);
    D.registrar({ os: b.os, tipo: "pcp", maq: maqId, texto: "Reprogramou " + (maqId !== b.maq ? "de " + mOrig.nome + " para " + mDest.nome + ", " : "") + hhmm(ni) + "–" + hhmm(nf) + (resp !== respDe(b) ? " · responsável " + resp : "") + (origem === "teclado" ? " (teclado)" : "") + (conf ? " — " + conf : "") });
    const aviso = resp + (resp !== respDe(b) ? " passa a responder pela etapa e recebeu o aviso no app." : " recebeu o aviso no app.");
    setMsg(conf ? { tone: "danger", t: b.os + " em " + mDest.nome + ", " + hhmm(ni) + "–" + hhmm(nf) + ": " + conf + ". " + aviso } : { tone: "success", t: b.os + " reprogramada: " + mDest.nome + ", " + hhmm(ni) + "–" + hhmm(nf) + ". " + aviso });
    return true;
  };
  const aplicar = (sim) => {
    const b = blocos.find((x) => x.maq === "eco" && x.os === "OS-2608");
    if (sim && b) { const c = blocos.find((x) => x.conflito && x.maq === "eco"); setAg({ ...ag, hoje: blocos.map((x) => x === b ? { ...x, maq: "latex", ini: 10.75, fim: 11.85, movido: true } : x === c ? { ...x, ini: 14.5, fim: 17.6, conflito: null } : x) }); }
    else setAg({ ...ag, hoje: D.AGENDA.map((x, k) => ({ ...x, k })) });
    setAplicado(sim);
    D.registrar({ os: "OS-2608", tipo: "pcp", maq: sim ? "latex" : "eco", texto: sim ? "Reprogramou Adesivo de aplicação da Roland VG3-640 para a HP Latex 365 (10:45)" : "Desfez a reprogramação — volta para a Roland VG3-640" });
  };
  const arrRef = React.useRef(null);
  const pegarBloco = (e, b) => {
    if (e.button !== 0 || b.estado !== "fila") return;
    const track = e.currentTarget.parentElement; const w = track.getBoundingClientRect().width;
    e.preventDefault(); document.body.classList.add("pcv-arrastando"); window.getSelection && window.getSelection().removeAllRanges();
    arrRef.current = { k: b.k, maq: b.maq, x0: e.clientX, ini0: b.ini, w, ini: b.ini, over: b.maq, moveu: false };
    const mv = (ev) => { const d = arrRef.current; if (!d) return; const dx = ev.clientX - d.x0; const moveu = d.moveu || Math.abs(dx) > 4;
      const row = [...document.querySelectorAll("[data-maq]")].find((r) => { const q = r.getBoundingClientRect(); return ev.clientY >= q.top && ev.clientY < q.bottom; });
      arrRef.current = { ...d, moveu, ini: Math.round((d.ini0 + (dx / d.w) * (H1 - H0)) / SNAP) * SNAP, over: row ? row.getAttribute("data-maq") : d.over }; if (moveu) { ev.preventDefault(); setArr(arrRef.current); } };
    const up = () => { const d = arrRef.current; document.body.classList.remove("pcv-arrastando"); window.removeEventListener("pointermove", mv); window.removeEventListener("pointerup", up); arrRef.current = null; setArr(null);
      if (d && d.moveu) { window.__pcvArrastou = true; setTimeout(() => { window.__pcvArrastou = false; }, 0); mover(d.k, d.over, d.ini, "arrastar"); } };
    window.addEventListener("pointermove", mv); window.addEventListener("pointerup", up);
  };
  const tecla = (e, b) => {
    if (!e.altKey) return;
    const mesmos = D.MAQUINAS.filter((x) => x.setor === D.maqDe(b.maq).setor); const ix = mesmos.findIndex((x) => x.id === b.maq);
    if (e.key === "ArrowRight" || e.key === "ArrowLeft") { e.preventDefault(); mover(b.k, b.maq, b.ini + (e.key === "ArrowRight" ? SNAP : -SNAP), "teclado"); }
    if (e.key === "ArrowDown" || e.key === "ArrowUp") { e.preventDefault(); const alvo = mesmos[ix + (e.key === "ArrowDown" ? 1 : -1)]; if (alvo) mover(b.k, alvo.id, b.ini, "teclado"); }
  };
  const bEsc = escolha && blocos.find((x) => x.k === escolha.k);
  return (
    <div className="pcv-body">
      <BarraFiltro f={f} setF={setF} />
      <div className="pcv-bar">
        {Segmented && <Segmented size="sm" value={dia} onChange={(v) => { setDia(v); setMsg(null); setEscolha(null); }} options={[{ value: "hoje", label: D.DIAS.hoje.rotulo }, { value: "proximo", label: D.DIAS.proximo.rotulo }]} />}
        <span className="pcv-help">Arraste um bloco que ainda não começou para mudar o horário ou levar para outra máquina do mesmo setor. No teclado: Alt + setas. Tracejado = travado por arte ou falta de material · borda vermelha = passa do fim do turno ({T.rotulo}) ou da entrega prometida. O dia seguinte pula sábado, domingo e feriados.</span>
      </div>
      {msg && Alert && <Alert tone={msg.tone} title={msg.tone === "warn" ? "Não movido" : msg.tone === "danger" ? "Movido, com aviso" : "Movido"} onClose={() => setMsg(null)}>{msg.t}</Alert>}
      {escolha && bEsc && Alert && <Alert tone="info" title={"Quem faz na " + D.maqDe(escolha.maqId).nome + "?"} onClose={() => setEscolha(null)}>
        {escolha.resp} não opera a {D.maqDe(escolha.maqId).nome}. Escolha um operador dela para levar {bEsc.os} ({hhmm(escolha.ini)}–{hhmm(escolha.ini + bEsc.fim - bEsc.ini)}); quem você escolher passa a responder pela etapa e recebe o aviso no app.
        <div className="pcv-al-a">{escolha.opcoes.map((p) => Button && <Button key={p} size="sm" variant="primary" onClick={() => mover(escolha.k, escolha.maqId, escolha.ini, escolha.origem, p)}>{p}</Button>)}{Button && <Button size="sm" onClick={() => setEscolha(null)}>Cancelar</Button>}</div>
      </Alert>}
      <div className="pcv-gantt" role="table" aria-label={"Linha do tempo por máquina — " + D.DIAS[dia].rotulo}>
        <div className="pcv-g-row pcv-g-head" role="row"><div className="pcv-g-lab" role="columnheader">Máquina</div><div className="pcv-g-track" role="columnheader">{horas.map((h) => <span key={h} style={{ left: pos(h) + "%" }}>{String(h).padStart(2, "0")}h</span>)}</div></div>
        {D.MAQUINAS.map((m) => { const carga = visiveis.filter((b) => b.maq === m.id).reduce((s, b) => s + (b.fim - b.ini), 0); const pode = arr && D.maqDe(arr.maq).setor === m.setor; return (
          <div className={"pcv-g-row" + (arr ? (pode ? (arr.over === m.id ? " is-alvo is-sobre" : " is-alvo") : " is-fora") : "")} role="row" key={m.id} data-maq={m.id}>
            <div className="pcv-g-lab" role="rowheader"><button className="pcv-g-maq" onClick={() => setMaqEv(m.id)} aria-label={"Ver eventos de " + m.nome}><b>{m.nome}</b></button><small>{m.cap} · {m.operadores.join(", ")} · <span className={m.horasDia && carga > m.horasDia ? "pcv-late" : ""}>{D.h1(carga)} de {D.h1(m.horasDia)} h</span></small></div>
            <div className="pcv-g-track" role="cell">
              {dia === "hoje" && <i className="pcv-g-now" style={{ left: pos(agoraH) + "%" }} aria-hidden="true" />}
              <i className="pcv-g-fim" style={{ left: pos(T.fim) + "%" }} aria-hidden="true" />
              {arr && arr.over === m.id && (() => { const b = blocos.find((x) => x.k === arr.k); const dur = b.fim - b.ini; return <i className="pcv-g-prev" style={{ left: pos(Math.max(H0, arr.ini)) + "%", width: (pos(Math.max(H0, arr.ini) + dur) - pos(Math.max(H0, arr.ini))) + "%" }} aria-hidden="true"><span>{hhmm(Math.max(H0, arr.ini))}</span></i>; })()}
              {visiveis.filter((b) => b.maq === m.id).map((b) => { const o = D.osDe(b.os); const trava = travaDe(b); const fixo = b.estado !== "fila"; return (
                <button key={b.k} className={"pcv-g-blk is-" + b.estado + (trava ? " is-bloq" : "") + (b.conflito ? " is-conf" : "") + (b.movido ? " is-mov" : "") + (arr && arr.k === b.k ? " is-arr" : "") + (fixo ? "" : " is-movel")}
                  style={{ left: pos(b.ini) + "%", width: (pos(b.fim) - pos(b.ini)) + "%", "--c": D.setorDe(m.setor).cor }}
                  aria-label={`${b.os}, ${o ? o.cliente : ""}, ${hhmm(b.ini)} a ${hhmm(b.fim)} em ${m.nome}, responsável ${respDe(b)}${trava ? ", " + trava : ""}${b.conflito ? ", " + b.conflito : ""}${fixo ? (b.estado === "feito" ? ", concluída" : ", em andamento, não se reprograma") : ""}. Enter abre${fixo ? "" : "; Alt+setas move"}.`}
                  title={`${b.os} · ${hhmm(b.ini)}–${hhmm(b.fim)} · ${respDe(b)}${trava ? " — " + trava : ""}${b.conflito ? " — " + b.conflito : ""}`}
                  onPointerDown={(e) => pegarBloco(e, b)} onKeyDown={(e) => tecla(e, b)}
                  onClick={() => { if (window.__pcvArrastou) return; onAbrir(b.os); }}><b className="mono">{b.os}</b><span>{o ? o.cliente : ""}</span></button>); })}
            </div>
          </div>); })}
      </div>
      {dia === "hoje" && Widget && <Widget title="Eventos das máquinas hoje" note={maqEv ? (D.maqDe(maqEv) || {}).nome : "todas as máquinas — clique numa máquina para filtrar"} actions={maqEv && <button className="pcv-link" onClick={() => setMaqEv(null)}>Ver todas</button>} flush>
        <Feed eventos={eventos.filter((e) => e.maq && e.quando.startsWith("15/10") && (!maqEv || e.maq === maqEv))} onAbrir={onAbrir} max={12} compacto vazio="Sem eventos desta máquina hoje." />
      </Widget>}
      {dia === "hoje" && Alert && (() => { const b = blocos.find((x) => x.os === "OS-2619" && x.maq === "latex"); const junto = b && Math.abs(b.ini - 9.5) < 0.01; return junto
        ? <Alert tone="success" title="Agrupado por mídia">OS-2619 entrou logo depois da OS-2603 na HP Latex, com a mesma Lona 440 g. Uma troca de mídia a menos (~12 min).</Alert>
        : b && <Alert tone="info" title="Mesma mídia: dá para agrupar">OS-2603 e OS-2619 usam Lona 440 g na HP Latex. Programar a OS-2619 logo depois (09:30) evita uma troca de mídia (~12 min). Regra simples; o encaixe na bobina é da versão 2.<div className="pcv-al-a"><Button size="sm" onClick={() => mover(b.k, "latex", 9.5, "agrupar")}>Agrupar</Button></div></Alert>; })()}
      {dia === "hoje" && Alert && (aplicado
        ? <Alert tone="success" title="Reprogramado">OS-2608 foi para a HP Latex 365 às 10:45. A Roland VG3-640 fecha o dia com 9,3 de 10 h e termina às 17h36. O Diego foi avisado no app.<div className="pcv-al-a"><Button size="sm" onClick={() => aplicar(false)}>Desfazer</Button></div></Alert>
        : <Alert tone="info" title="Sugestão de reprogramação">Mover OS-2608 (1,1 h) da Roland VG3-640 para a HP Latex 365 às 10:45 tira a Roland do vermelho (10,4 h → 9,3 h, capacidade de 10 h no dia) sem atrasar a fachada. O Diego opera as duas, então o responsável não muda. Ou arraste o bloco você mesmo.<div className="pcv-al-a"><Button size="sm" variant="primary" onClick={() => aplicar(true)}>Aplicar</Button></div></Alert>)}
    </div>
  );
}

function ProducaoCvPage({ view = "painel", estado = "dados" }) {
  const { PageHeader, TabBar, Button, Skeleton, EmptyState, Toast } = DS();
  const [nova, setNova] = useState(false);
  const [aviso, setAviso] = useState(null);
  const [, setVer] = useState(0);
  const RESP = { arte: "Ana", impressao: "Diego", acabamento: "Felipe", serralheria: "Marcos", instalacao: "Jonas", expedicao: "Larissa" };
  const criar = (orc, etapas, rot) => {
    const n = Math.max(...D.OS.map((x) => +x.id.slice(3))) + 1;
    const nova = { id: "OS-" + n, cliente: orc.cliente, titulo: orc.desc, produto: orc.produto, qtd: orc.qtd + " " + orc.un, prazo: orc.prazo, prioridade: "normal", arte: "aguardando", resp: "Larissa", orcado: orc.valor, real: 0, perda: 0,
      junta: (rot.etapas.find((e) => e.paralelo) || {}).junta,
      etapas: etapas.map((e, i) => [e.setor, e.nome, RESP[e.setor] || "Larissa", i === 0 ? "andamento" : "fila", e.prev, 0, e.maq, e.trilha]) };
    D.OS.unshift(nova); D.ORCAMENTOS.splice(D.ORCAMENTOS.indexOf(orc), 1); D.registrar({ os: nova.id, tipo: "pcp", texto: orc.id + " aprovado virou OS (roteiro " + rot.nome + ")" }); setVer((v) => v + 1);
    return nova;
  };
  const D = P();
  const [vista, setVista] = useState(VISTAS.some((v) => v.key === view) ? view : "painel");
  const [aberta, setAberta] = useState(null);
  const [f, setF] = useState({ q: "", so: "todas", resp: "" });
  const filtrar = (so) => { setF({ q: "", so, resp: "" }); setVista("quadro"); };
  const O = window.PcvOs || {}; const C = window.PcvCampo || {};
  if (!D) return <div className="pcv-root"><p className="pcv-help" style={{ padding: 24 }}>Carregando a produção…</p></div>;
  const atrasadas = D.OS.filter((o) => D.atual(o).estado !== "pronto" && (D.atraso(o) === "atrasada" || D.atraso(o) === "vence hoje")).length;
  const corpoDados = vista === "painel" ? <Painel onAbrir={setAberta} ir={setVista} filtrar={filtrar} />
    : vista === "quadro" ? <Quadro onAbrir={setAberta} f={f} setF={setF} />
    : vista === "linha" ? <Linha onAbrir={setAberta} f={f} setF={setF} />
    : vista === "custo" && O.Custo ? <O.Custo onAbrir={setAberta} />
    : vista === "roteiros" && O.Roteiros ? <O.Roteiros />
    : vista === "postos" && window.PcvPostos ? <window.PcvPostos.Postos />
    : vista === "instalacao" && C.Instalacao ? <C.Instalacao onAbrir={setAberta} />
    : vista === "apontamento" && C.Apontamento ? <C.Apontamento />
    : <p className="pcv-help" style={{ padding: 24 }}>Carregando…</p>;
  const corpo = estado === "carregando" ? <div className="pcv-body" aria-busy="true">{Skeleton ? <Skeleton variant="card" count={3} /> : <p className="pcv-help">Carregando…</p>}</div>
    : estado === "erro" ? <div className="pcv-body">{EmptyState && <EmptyState variant="error" title="Não foi possível carregar a produção" description="Nada foi alterado. Tente de novo; se continuar, os apontamentos do celular ficam guardados e entram quando voltar." action={Button && <Button onClick={() => location.reload()}>Tentar de novo</Button>} />}</div>
    : estado === "vazio" ? <div className="pcv-body">{EmptyState && <EmptyState variant="first" title="Nenhuma OS em produção" description="Quando um orçamento é aprovado, ele vira OS com as etapas do roteiro da família. Comece por um orçamento aprovado." action={Button && <Button variant="primary" onClick={() => setNova(true)}>Nova OS do orçamento</Button>} />}</div>
    : corpoDados;
  return (
    <div className="pcv-root" data-screen-label={"Produção · " + (VISTAS.find((v) => v.key === vista) || {}).label}>
      {PageHeader && <div className="pcv-ph"><PageHeader title="Produção" context="COMUNICAÇÃO VISUAL · OS COMO PROJETO"
        stats={estado === "dados" ? [{ label: "em produção", value: D.emProducao().length }, { label: "atrasadas ou vencendo", value: atrasadas, tone: atrasadas ? "danger" : undefined }] : undefined}
        actions={Button && <Button variant="primary" onClick={() => setNova(true)}><Ic name="plus" size={13} /> Nova OS do orçamento</Button>} /></div>}
      {TabBar && <TabBar ariaLabel="Vistas da produção" tabs={VISTAS} active={vista} onChange={setVista} inset={24} />}
      {corpo}
      {aberta && O.FichaOs && <O.FichaOs id={aberta} onClose={() => setAberta(null)} />}
      {nova && O.NovaOs && D.ORCAMENTOS.length > 0 && <O.NovaOs onClose={() => setNova(false)} onCriada={(orc, etapas, rot) => { const nv = criar(orc, etapas, rot); setNova(false); setAviso(orc.id + " virou " + nv.id + " com " + etapas.length + " etapas. Ela já está no quadro, na coluna Arte e aprovação."); }} />}
      {aviso && <div className="pcv-toast" role="status">{aviso}<button className="pcv-link" onClick={() => setAviso(null)}>Fechar</button></div>}
    </div>
  );
}

window.ProducaoCvPage = ProducaoCvPage;
})();
