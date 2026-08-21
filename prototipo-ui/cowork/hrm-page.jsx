// hrm-page.jsx — /hrm · módulo HRM (Essentials) no app único.
// Espelha o topnav de nav_hrm.blade (main tree b719732f3188). Ondas aplicadas:
// O1 mecânica (ordenar, paginar, selecionar em lote, atalhos, skeleton, vazio com
// motivo) · O2 formulários (hrm-forms.jsx) · O3 profundidade (fila do dia, saldo
// por tipo) · O4 DS vivo (hrm-ui.jsx). Expõe window.HrmPage.
(() => {
const { useState, useMemo, useRef } = React;
const H = window.HRM;
const U = window.HrmUI;
const F = window.HrmForms;
const X = window.HrmExtras;
const { Badge, Card, Row, Seg, Nota, Kpis, Busca, Drawer, Sec, KV, Tabela, Paginacao, Bulk, Skel, Vazio, Aviso, Grafico, Ambiente, useAmbiente, SemPermissao, usePagina, useAviso, useAtalhos, useCarga, usePersist, limparDados } = U;

const TABS = [
  { id:"hrm",           label:"Painel" },
  { id:"hrm-licencas",  label:"Licenças",   n:(s) => s.pend },
  { id:"hrm-presenca",  label:"Presença",   n:(s) => s.dados.pre.filter((p) => !p.sai).length },
  { id:"hrm-turnos",    label:"Turnos",     n:(s) => s.dados.tur.length },
  { id:"hrm-folha",     label:"Folha de pagamento" },
  { id:"hrm-feriados",  label:"Feriados",   n:(s) => s.dados.fer.length },
  { id:"hrm-metas",     label:"Metas de venda" },
  { id:"hrm-config",    label:"Configurações" },
];
const tipoNome = (id) => (H.TIPOS.find((t) => t.id === id) || {}).nome || "—";

// ═══════════════════════ PAINEL ═══════════════════════
function Painel({ lic }) {
  const A = useAmbiente();
  const eu = A.eu;
  const carregando = useCarga();
  const verTodos = A.pode("ver_todos");
  const pend = (verTodos ? lic : lic.filter((l) => l.emp === eu)).filter((l) => l.status === "pending");
  const minhas = lic.filter((l) => l.emp === eu);
  const hoje = H.PRE.filter((p) => p.data === "2026-08-21");
  const abertos = H.PRE.filter((p) => !p.sai);
  const folhaMes = H.FOLHA.filter((f) => f.mes === "08/2026").reduce((s, f) => s + H.totalFolha(f), 0);
  const loteAberto = H.LOTES.find((l) => l.status === "draft");
  const porSetor = useMemo(() => {
    const m = {};
    H.EMP.forEach((e) => { m[e.setor] = (m[e.setor] || 0) + 1; });
    return Object.entries(m).sort((a, b) => b[1] - a[1]);
  }, []);
  const proximos = H.FER.filter((f) => f.ini >= "2026-08-21").sort((a, b) => a.ini.localeCompare(b.ini)).slice(0, 4);
  const r = H.REALIZADO[eu];

  // O3 — fila: o que precisa de decisão humana, com o motivo e para onde ir
  const fila = [
    ...(A.pode("aprovar") ? pend : []).map((l) => ({
      id:"lic" + l.id, urg:l.ini <= "2026-08-28",
      t:`Licença de ${H.emp(l.emp).nome} sem resposta`,
      s:`${tipoNome(l.tipo)} · começa em ${H.dt(l.ini)} · ${H.plural(H.dias(l.ini, l.fim), "1 dia", "{n} dias")}`,
      go:"hrm-licencas", cta:"Analisar",
    })),
    ...(abertos.length && verTodos ? [{
      id:"pre", urg:abertos.length > 2,
      t:`${abertos.length} marcações sem saída registrada`,
      s:"turno fixo fecha automático; flexível fica aberto para sempre",
      go:"hrm-presenca", cta:"Ver presença",
    }] : []),
    ...(loteAberto && A.pode("gerir_folha") ? [{
      id:"folha", urg:true,
      t:`Folha ${loteAberto.mes} ainda em rascunho`,
      s:`${loteAberto.itens} contracheques · ${H.brl(loteAberto.bruto)} sem pagamento lançado`,
      go:"hrm-folha", cta:"Abrir folha",
    }] : []),
    ...(A.pode("gerir_meta") ? H.EMP.filter((e) => e.comissao > 0 && !(H.METAS[e.id] || []).length) : []).map((e) => ({
      id:"meta" + e.id, urg:false,
      t:`${e.nome} tem comissão mas nenhuma faixa de meta`,
      s:`comissão fixa de ${e.comissao}% sem meta cadastrada — comissão de meta fica zero`,
      go:"hrm-metas", cta:"Definir meta",
    })),
  ].sort((a, b) => (b.urg ? 1 : 0) - (a.urg ? 1 : 0));

  const custoSetor = useMemo(() => {
    const m = {};
    H.FOLHA.filter((f) => f.mes === "08/2026").forEach((f) => {
      const s = H.emp(f.emp).setor;
      m[s] = (m[s] || 0) + H.totalFolha(f);
    });
    return Object.entries(m).map(([label, value]) => ({ label, value })).sort((a, b) => b.value - a.value);
  }, []);

  if (carregando) return <Skel n={8}/>;

  return (
    <>
      <Kpis items={[
        ...(verTodos ? [{ l:"Colaboradores", v:H.EMP.length, sub:`${porSetor.length} setores`, tone:"info" }] : []),
        { l:verTodos ? "Licenças pendentes" : "Minhas licenças pendentes", v:pend.length, sub:"esperando aprovação", tone:pend.length ? "warning" : "default" },
        ...(verTodos ? [{ l:"Presentes hoje", v:new Set(hoje.map((p) => p.emp)).size, sub:`${hoje.filter((p) => !p.sai).length} sem saída registrada` }] : []),
        ...(A.pode("gerir_folha") ? [{ l:"Folha 08/2026", v:A.din(folhaMes), sub:"lote em rascunho", tone:"info" }] : []),
      ]}/>

      <div className="hrm-grid">
        <Card title="O que fazer primeiro" aside={`${fila.length} pendências`} sub="Fila derivada do próprio dado — licença sem resposta, marcação aberta, folha não paga, meta faltando.">
          <div className="hrm-list">
            {fila.map((f) => (
              <div className={`hrm-row ${f.urg ? "urg" : ""}`} key={f.id}>
                <span className="hrm-row-l"><span className="hrm-row-t">{f.urg && <i className="hrm-dot"></i>}{f.t}</span><span className="hrm-row-s">{f.s}</span></span>
                <span className="hrm-row-v"><button className="os-btn ghost" onClick={() => window.__go && window.__go(f.go)}>{f.cta}</button></span>
              </div>))}
            {!fila.length && <Vazio variante="done" titulo="Nada esperando decisão" desc="Licenças respondidas, marcações fechadas e folha paga."/>}
          </div>
        </Card>

        <Card title="Custo de folha por setor" aside="08/2026" sub="Líquido dos contracheques da competência — ganhos menos deduções, sem encargos.">
          {A.pode("gerir_folha")
            ? <><Grafico tipo="bar" dados={custoSetor} altura={130} formata={(v) => A.din(v)}/>
                <div className="hrm-list" style={{ marginTop:8 }}>{custoSetor.map((s) => <Row key={s.label} t={s.label} v={A.din(s.value)}/>)}</div></>
            : <SemPermissao frase="O custo de folha exige acesso à folha de pagamento."/>}
        </Card>

        <Card title="Minhas licenças" sub="Larissa Andrade">
          <div className="hrm-list">
            {minhas.map((l) => (
              <div className="hrm-row" key={l.id}>
                <span className="hrm-row-l">
                  <span className="hrm-row-t">{H.dt(l.ini)} – {H.dt(l.fim)} <span className="hrm-meta">({H.dias(l.ini, l.fim)} dia{H.dias(l.ini, l.fim) > 1 ? "s" : ""})</span></span>
                  <span className="hrm-row-s">{tipoNome(l.tipo)}</span>
                </span>
                <Badge tone={H.ST_LIC[l.status].t}>{H.ST_LIC[l.status].l}</Badge>
              </div>))}
            {!minhas.length && <p className="hrm-empty">Nenhuma licença registrada.</p>}
          </div>
        </Card>

        <Card title="Minhas metas de venda">
          <div className="hrm-list">
            <Row t="Vendido no mês anterior" v={H.brl(r.anterior)}/>
            <Row t="Vendido neste mês" v={H.brl(r.mes)}/>
          </div>
          <div style={{ marginTop:10 }} className="hrm-list">
            {(H.METAS[eu] || []).map((f) => {
              const ativa = r.mes >= f.ini && r.mes <= f.fim;
              return <Row key={f.id} t={`${H.brl(f.ini)} – ${H.brl(f.fim)}`} s={ativa ? "faixa atingida neste mês" : null} v={`${f.pct}%`}/>;
            })}
          </div>
        </Card>

        <Card title="Próximos feriados" aside={`${H.FER.length} no ano`}>
          <div className="hrm-list">
            {proximos.map((f) => (
              <Row key={f.id} t={f.nome} s={f.local ? `só ${f.local}` : "todas as localidades"}
                v={f.ini === f.fim ? H.dt(f.ini) : `${H.dt(f.ini)} – ${H.dt(f.fim)}`}/>))}
          </div>
        </Card>

        {verTodos && <Card title="Presença de hoje" aside={H.dt("2026-08-21")}>
          <div className="hrm-list">
            {hoje.map((p) => <Row key={p.id} t={H.emp(p.emp).nome} s={p.nEnt || p.turno} v={`${p.ent} → ${p.sai || "—"}`}/>)}
          </div>
        </Card>}

        {verTodos && <Card title="Colaboradores por setor">
          <div className="hrm-list">
            {porSetor.map(([s, n]) => (
              <div className="hrm-row" key={s}>
                <span className="hrm-row-l"><span className="hrm-row-t">{s}</span></span>
                <span className="hrm-row-v" style={{ display:"flex", alignItems:"center", gap:8 }}>
                  <span className="hrm-bar" style={{ width:80 }}><i style={{ width:`${n / H.EMP.length * 100}%` }}></i></span>{n}
                </span>
              </div>))}
          </div>
        </Card>}
      </div>

      <Card title="Achados desta leitura do main" sub="O que a tela precisa dizer antes de virar produção — cada item tem origem no código, não é opinião.">
        <div className="hrm-achados">
          {H.ACHADOS.map((a) => (
            <div className={`hrm-achado ${a.tom}`} key={a.id}>
              <div className="hrm-achado-t"><i>{a.id}</i>{a.t}</div>
              <p className="hrm-achado-d">{a.d}</p>
            </div>))}
        </div>
      </Card>
    </>
  );
}

// ═══════════════════════ LICENÇAS ═══════════════════════
function Licencas({ lic, setLic }) {
  const A = useAmbiente();
  const [subview, setSub] = useState("lic");
  const [q, setQ] = useState("");
  const [fSt, setFSt] = useState("all");
  const [fTipo, setFTipo] = useState("all");
  const [sel, setSel] = useState(null);
  const [marcadas, setMarcadas] = useState([]);
  const [form, setForm] = useState(false);
  const [aviso, setAviso] = useAviso();
  const buscaRef = useRef(null);
  const carregando = useCarga();
  useAtalhos({ busca:buscaRef, onNovo:() => setForm(true), onEsc:() => { if (sel) setSel(null); else if (form) setForm(false); else if (q) setQ(""); } });

  const visiveis = A.pode("ver_todos") ? lic : lic.filter((l) => l.emp === A.eu);
  const filtradas = visiveis.filter((l) => {
    if (fSt !== "all" && l.status !== fSt) return false;
    if (fTipo !== "all" && String(l.tipo) !== fTipo) return false;
    if (!q) return true;
    return [l.ref, H.emp(l.emp).nome, tipoNome(l.tipo), l.motivo].join(" ").toLowerCase().includes(q.toLowerCase());
  });
  const pg = usePagina(filtradas, 8);
  const diasMes = lic.filter((l) => l.status === "approved" && l.ini >= "2026-08-01").reduce((s, l) => s + H.dias(l.ini, l.fim), 0);

  const mudar = (ids, status) => {
    setLic((ls) => ls.map((x) => ids.includes(x.id) ? { ...x, status } : x));
    setSel((s) => s && ids.includes(s.id) ? { ...s, status } : s);
    setMarcadas([]);
    setAviso(status === "approved"
      ? H.plural(ids.length, "1 licença aprovada. O colaborador recebe a notificação.", "{n} licenças aprovadas. Os colaboradores recebem a notificação.")
      : H.plural(ids.length, "1 licença cancelada. O colaborador recebe a notificação.", "{n} licenças canceladas. Os colaboradores recebem a notificação."));
  };
  const criar = ({ tipo, quem, ini, fim, motivo }) => {
    const base = Math.max(...lic.map((l) => l.id)) + 1;
    const novas = quem.map((emp, i) => ({ id:base + i, ref:`${H.CFG.leave_ref_no_prefix}${String(10 + i).padStart(4, "0")}`, tipo, emp, ini, fim, status:"pending", motivo, nota:"" }));
    setLic((ls) => [...novas, ...ls]);
    setForm(false);
    setAviso(H.plural(novas.length, "Pedido registrado — administradores notificados.", "{n} pedidos registrados — administradores notificados."));
  };

  // O3 — saldo por tipo × colaborador
  const saldo = useMemo(() => H.TIPOS.map((t) => {
    const usados = lic.filter((l) => l.tipo === t.id && l.status === "approved").reduce((s, l) => s + H.dias(l.ini, l.fim), 0);
    const pedidos = lic.filter((l) => l.tipo === t.id && l.status === "pending").reduce((s, l) => s + H.dias(l.ini, l.fim), 0);
    return { ...t, usados, pedidos, estouro: t.max ? usados + pedidos > t.max : false };
  }), [lic]);

  const cols = [
    { key:"ref", label:"Ref.", sortable:true, width:"96px" },
    { key:"tipo", label:"Tipo", sortable:true },
    { key:"quem", label:"Colaborador", sortable:true },
    { key:"periodo", label:"Período", sortable:true, sortValue:(r) => r.raw.ini },
    { key:"motivo", label:"Motivo" },
    { key:"status", label:"Situação", sortable:true, sortValue:(r) => r.raw.status },
    { key:"acao", label:"", width:"160px", resizable:false },
  ];
  const rows = pg.fatia.map((l) => {
    const e = H.emp(l.emp);
    return {
      id:String(l.id), raw:l, state:l.status === "pending" && l.ini <= "2026-08-28" ? "urgent" : l.status === "cancelled" ? "archived" : undefined,
      cells:{
        ref:<span className="hrm-mono">{l.ref}</span>,
        tipo:tipoNome(l.tipo),
        quem:{ primary:e.nome, sub:`${e.cargo} · ${e.setor}` },
        periodo:{ primary:`${H.dt(l.ini)} – ${H.dt(l.fim)}`, sub:H.plural(H.dias(l.ini, l.fim), "1 dia", "{n} dias") },
        motivo:<span className="hrm-meta hrm-clamp">{l.motivo}</span>,
        status:<Badge tone={H.ST_LIC[l.status].t}>{H.ST_LIC[l.status].l}</Badge>,
        acao:l.status === "pending" && A.pode("aprovar")
          ? <span className="hrm-acoes" onClick={(e2) => e2.stopPropagation()}>
              <button className="os-btn ghost" onClick={() => mudar([l.id], "approved")}>Aprovar</button>
              <button className="os-btn ghost" onClick={() => mudar([l.id], "cancelled")}>Cancelar</button>
            </span>
          : <span className="hrm-meta">{l.nota ? "com observação" : "—"}</span>,
      },
    };
  });

  return (
    <>
      <Kpis items={[
        { l:A.pode("ver_todos") ? "Pendentes" : "Minhas pendentes", v:visiveis.filter((l) => l.status === "pending").length, sub:A.pode("aprovar") ? "aprovar ou cancelar" : "esperando o RH", tone:"warning" },
        { l:"Aprovadas", v:visiveis.filter((l) => l.status === "approved").length, sub:"no período carregado", tone:"success" },
        { l:"Dias de licença em agosto", v:diasMes, sub:"soma dos períodos aprovados" },
        { l:"Tipos cadastrados", v:A.dados.tipos.length, sub:"com limite por ano ou mês" },
      ]}/>

      <Seg value={subview} onChange={setSub} options={[{ id:"lic", label:"Licenças" }, { id:"saldo", label:"Saldo por tipo" }, { id:"tipos", label:"Tipos de licença" }]}/>

      {subview === "lic" && <>
        <div className="hrm-toolbar">
          <Busca value={q} onChange={setQ} inputRef={buscaRef} placeholder="Buscar por referência, colaborador, tipo ou motivo…"/>
          <select className="hrm-sel" value={fSt} onChange={(e) => setFSt(e.target.value)} aria-label="Filtrar por situação">
            <option value="all">Todas as situações</option>
            {Object.entries(H.ST_LIC).map(([k, v]) => <option key={k} value={k}>{v.l}</option>)}
          </select>
          <select className="hrm-sel" value={fTipo} onChange={(e) => setFTipo(e.target.value)} aria-label="Filtrar por tipo">
            <option value="all">Todos os tipos</option>
            {H.TIPOS.map((t) => <option key={t.id} value={String(t.id)}>{t.nome}</option>)}
          </select>
          <span className="usr-count">{filtradas.length} de {visiveis.length}</span>
          <span className="hrm-spacer"></span>
          <span className="hrm-kbd"><kbd>/</kbd> buscar{!A.demo && <> · <kbd>n</kbd> novo</>}</span>
          <button className="os-btn primary" disabled={A.demo} title={A.demo ? "Indisponível em demonstração" : null} onClick={() => setForm(true)}>Pedir licença</button>
        </div>

        {carregando ? <Skel n={8}/> : filtradas.length ? <>
          <Tabela cols={cols} rows={rows} selecionavel={A.pode("aprovar")} onSelecao={(ids) => setMarcadas((ids || []).map(Number))} onLinha={(r) => setSel(r.raw)} altura={420} ordem={{ key:"periodo", dir:"desc" }}/>
          <Paginacao pagina={pg.pagina} paginas={pg.paginas} onMudar={pg.setPagina} total={pg.total} porPagina={pg.porPagina}/>
        </> : A.primeira
          ? <Vazio variante="first" titulo="Nenhuma licença registrada ainda"
              desc="Licença é todo afastamento combinado: férias, atestado, falta justificada. O pedido nasce pendente, o administrador aprova ou cancela, e o colaborador é avisado por e-mail."
              acao={<button className="os-btn primary" disabled={A.demo} onClick={() => setForm(true)}>Registrar o primeiro pedido</button>}/>
          : <Vazio variante="no-results"
          titulo={q ? `Nada casa com “${q}”` : "Nenhuma licença nesse filtro"}
          desc={q ? "A busca cobre referência, colaborador, tipo e motivo." : "Ajuste situação e tipo, ou registre o primeiro pedido."}
          acao={<button className="os-btn ghost" onClick={() => { setQ(""); setFSt("all"); setFTipo("all"); }}>Limpar busca e filtros</button>}/>}

        <p className="hrm-card-sub" style={{ marginTop:10 }}>Pedir licença hoje <b>não valida nada</b> no servidor: fim antes do início passa, e o limite do tipo não é conferido (achados A2 e A3). O formulário desta tela recusa os dois.</p>
      </>}

      {subview === "saldo" && <>
        <div className="os-table-wrap"><table className="os-table">
          <thead><tr><th>Tipo</th><th className="hrm-num">Limite</th><th className="hrm-num">Aprovado</th><th className="hrm-num">Em análise</th><th>Consumo</th><th>Risco</th></tr></thead>
          <tbody>{saldo.map((t) => (
            <tr key={t.id}>
              <td className="hrm-name">{t.nome}<div className="hrm-meta">{t.intervalo === "year" ? "por ano" : "por mês"}</div></td>
              <td className="hrm-num">{t.max ? `${t.max} d` : "sem limite"}</td>
              <td className="hrm-num">{t.usados} d</td>
              <td className="hrm-num">{t.pedidos ? `${t.pedidos} d` : "—"}</td>
              <td>{t.max
                ? <div className="hrm-bar" style={{ width:140 }}><i className={t.usados + t.pedidos > t.max ? "" : "ok"} style={{ width:`${Math.min(100, (t.usados + t.pedidos) / t.max * 100)}%` }}></i></div>
                : <span className="hrm-meta">—</span>}</td>
              <td>{t.estouro ? <Badge tone="danger">aprovar estoura o limite</Badge> : <Badge tone="ok">dentro</Badge>}</td>
            </tr>))}</tbody>
        </table></div>
        <p className="hrm-card-sub" style={{ marginTop:10 }}>O saldo é <b>calculado aqui</b>, não no servidor: aprovar uma licença que estoura o limite é possível hoje e ninguém avisa.</p>
      </>}

      {subview === "tipos" && <>
        <Nota tone="warn" title="Tipo de licença não pode ser excluído">
          <code>EssentialsLeaveTypeController::destroy</code> está vazio — o cadastro só cresce. O limite é <b>informativo</b>: nada bloqueia o pedido que passa dele.
        </Nota>
        <div className="hrm-toolbar"><span className="usr-count">{A.dados.tipos.length} tipos</span><span className="hrm-spacer"></span><button className="os-btn primary" disabled={!A.pode("gerir_licenca") || A.demo}>Novo tipo</button></div>
        {A.dados.tipos.length ? <div className="os-table-wrap"><table className="os-table">
          <thead><tr><th>Tipo</th><th className="hrm-num">Limite</th><th>Intervalo</th><th className="hrm-num">Pedidos no ano</th><th></th></tr></thead>
          <tbody>{A.dados.tipos.map((t) => (
            <tr key={t.id}>
              <td className="hrm-name">{t.nome}</td>
              <td className="hrm-num">{t.max ? `${t.max} dias` : "sem limite"}</td>
              <td>{t.intervalo === "year" ? "por ano" : "por mês"}</td>
              <td className="hrm-num">{lic.filter((l) => l.tipo === t.id).length}</td>
              <td style={{ textAlign:"right" }}><button className="os-btn ghost" disabled={!A.pode("gerir_licenca")}>Editar</button></td>
            </tr>))}</tbody>
        </table></div>
        : <Vazio variante="first" titulo="Nenhum tipo de licença cadastrado" desc="Sem tipo não dá para pedir licença — comece por Férias (30 dias/ano) e Licença médica." acao={<button className="os-btn primary" disabled={A.demo}>Cadastrar o primeiro tipo</button>}/>}
      </>}

      <Bulk n={marcadas.length} rotulo="licenças selecionadas" onFechar={() => setMarcadas([])} acoes={[
        { label:"Aprovar", onClick:() => mudar(marcadas, "approved") },
        { label:"Cancelar licenças", tone:"danger", onClick:() => { if (window.confirm(H.plural(marcadas.length, "Cancelar esta licença?\nO colaborador recebe a notificação da mudança.", "Cancelar {n} licenças?\nCada colaborador recebe a notificação da mudança."))) mudar(marcadas, "cancelled"); } },
      ]}/>

      {form && <F.FormLicenca onClose={() => setForm(false)} onSalvar={criar}/>}

      {sel && (() => { const e = H.emp(sel.emp); const t = H.TIPOS.find((x) => x.id === sel.tipo) || {}; return (
        <Drawer title={`${sel.ref} · ${tipoNome(sel.tipo)}`} sub={`${e.nome} · ${H.dt(sel.ini)} – ${H.dt(sel.fim)} (${H.dias(sel.ini, sel.fim)} dias)`} onClose={() => setSel(null)}
          footer={sel.status === "pending" && A.pode("aprovar")
            ? <><button className="os-btn ghost" onClick={() => mudar([sel.id], "cancelled")}>Cancelar licença</button><button className="os-btn primary" onClick={() => mudar([sel.id], "approved")}>Aprovar</button></>
            : <button className="os-btn ghost" onClick={() => setSel(null)}>Fechar</button>}>
          <Sec title="Pedido">
            <KV pairs={[["Colaborador", `${e.nome} — ${e.cargo}`], ["Setor", e.setor], ["Situação", H.ST_LIC[sel.status].l], ["Motivo", sel.motivo], ["Observação do RH", sel.nota || "—"]]}/>
          </Sec>
          <Sec title="Saldo do tipo">
            <div className="hrm-list">
              <Row t="Limite do tipo" v={t.max ? `${t.max} dias ${t.intervalo === "year" ? "por ano" : "por mês"}` : "sem limite"}/>
              <Row t="Consumido" v={`${t.usadas} dias`}/>
              <Row t="Este pedido" v={`${H.dias(sel.ini, sel.fim)} dias`}/>
            </div>
          </Sec>
          <Sec title="Conflitos no período">
            {(() => {
              const fer = H.FER.filter((f) => f.ini <= sel.fim && f.fim >= sel.ini);
              const pres = H.PRE.filter((p) => p.emp === sel.emp && p.data >= sel.ini && p.data <= sel.fim);
              return (
                <div className="hrm-list">
                  <Row t="Feriados dentro do período" s={fer.length ? fer.map((f) => f.nome).join(", ") : "nenhum"} v={fer.length}/>
                  <Row t="Marcações de presença no período" s={pres.length ? "a licença não apaga marcação já lançada" : "nenhuma"} v={pres.length}/>
                </div>);
            })()}
          </Sec>
          <Sec title="Histórico">
            <div className="hrm-list">
              <Row t="Pedido criado" s={`${e.nome} · referência com prefixo ${H.CFG.leave_ref_no_prefix}`} v={H.dt(sel.ini)}/>
              {sel.status !== "pending" && <Row t={`Situação alterada para ${H.ST_LIC[sel.status].l.toLowerCase()}`} s="registrado no log de atividade (spatie/activitylog)" v="—"/>}
            </div>
          </Sec>
        </Drawer>); })()}

      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

// ═══════════════════════ FERIADOS ═══════════════════════
function Feriados() {
  const A = useAmbiente();
  const [fer, setFer] = usePersist("fer", A.dados.fer);
  const [fLocal, setFLocal] = useState("all");
  const [ordem, setOrdem] = useState({ col:"ini", dir:"asc" });
  const [form, setForm] = useState(null);
  const [aviso, setAviso] = useAviso();

  const rows = fer.filter((f) => fLocal === "all" || (fLocal === "todas" ? !f.local : f.local === fLocal))
    .slice().sort((a, b) => {
      const s = ordem.dir === "asc" ? 1 : -1;
      if (ordem.col === "nome") return a.nome.localeCompare(b.nome, "pt-BR") * s;
      if (ordem.col === "dias") return (H.dias(a.ini, a.fim) - H.dias(b.ini, b.fim)) * s;
      return a.ini.localeCompare(b.ini) * s;
    });
  const ord = (col) => setOrdem((o) => o.col === col ? { col, dir:o.dir === "asc" ? "desc" : "asc" } : { col, dir:"asc" });
  const marca = (col) => ordem.col === col ? (ordem.dir === "asc" ? " ↑" : " ↓") : "";
  const totalDias = rows.reduce((s, f) => s + H.dias(f.ini, f.fim), 0);
  const podeGerir = A.pode("gerir_feriado") && !A.demo;

  const salvar = (item) => {
    setFer((fs) => item.id && fs.some((f) => f.id === item.id) ? fs.map((f) => f.id === item.id ? item : f) : [...fs, { ...item, id:Math.max(0, ...fs.map((f) => f.id)) + 1 }]);
    setForm(null);
    setAviso(`Feriado “${item.nome}” salvo.`);
  };
  const excluir = (f) => {
    if (!window.confirm(`Excluir “${f.nome}”?\nO feriado sai da escala de todos os turnos — marcações já lançadas não mudam.`)) return;
    setFer((fs) => fs.filter((x) => x.id !== f.id));
    setAviso(`Feriado “${f.nome}” excluído.`);
  };

  return (
    <>
      <Kpis items={[
        { l:"Feriados no ano", v:fer.length, sub:`${totalDias} dias no filtro`, tone:"info" },
        { l:"Só de uma localidade", v:fer.filter((f) => f.local).length, sub:"as outras operam normal" },
        { l:"Maior parada", v:fer.length ? `${Math.max(...fer.map((f) => H.dias(f.ini, f.fim)))} dias` : "—", sub:"recesso de fim de ano" },
      ]}/>
      <div className="hrm-toolbar">
        <select className="hrm-sel" value={fLocal} onChange={(e) => setFLocal(e.target.value)} aria-label="Filtrar por localidade">
          <option value="all">Todas as localidades</option>
          <option value="todas">Vale para o negócio inteiro</option>
          <option value="Matriz">Matriz</option>
          <option value="Filial Norte">Filial Norte</option>
        </select>
        <span className="usr-count">{rows.length} feriados</span>
        <span className="hrm-spacer"></span>
        <button className="os-btn primary" disabled={!podeGerir} title={podeGerir ? null : "Só o administrador cadastra feriado"} onClick={() => setForm({})}>Novo feriado</button>
      </div>
      {rows.length ? <div className="os-table-wrap"><table className="os-table">
        <thead><tr>
          <th><button className="mod-sort" onClick={() => ord("nome")}>Feriado{marca("nome")}</button></th>
          <th><button className="mod-sort" onClick={() => ord("ini")}>Início{marca("ini")}</button></th>
          <th>Fim</th>
          <th className="hrm-num"><button className="mod-sort" onClick={() => ord("dias")}>Dias{marca("dias")}</button></th>
          <th>Localidade</th><th>Observação</th><th></th>
        </tr></thead>
        <tbody>{rows.map((f) => (
          <tr key={f.id}>
            <td className="hrm-name">{f.nome}</td>
            <td className="hrm-mono">{H.dt(f.ini)}</td>
            <td className="hrm-mono">{H.dt(f.fim)}</td>
            <td className="hrm-num">{H.dias(f.ini, f.fim)}</td>
            <td>{f.local ? <Badge tone="accent">{f.local}</Badge> : <span className="hrm-meta">negócio inteiro</span>}</td>
            <td><span className="hrm-meta hrm-clamp">{f.nota || "—"}</span></td>
            <td style={{ textAlign:"right" }}>
              <span className="hrm-acoes">
                <button className="os-btn ghost" disabled={!podeGerir} onClick={() => setForm(f)}>Editar</button>
                <button className="os-btn ghost" disabled={!podeGerir} onClick={() => excluir(f)}>Excluir</button>
              </span>
            </td>
          </tr>))}</tbody>
      </table></div>
      : A.primeira
        ? <Vazio variante="first" titulo="Nenhum feriado cadastrado" desc="Feriado sem localidade vale para o negócio inteiro; com localidade, só a unidade escolhida para. A escala dos turnos usa esta lista." acao={<button className="os-btn primary" disabled={!podeGerir} onClick={() => setForm({})}>Cadastrar o primeiro</button>}/>
        : <Vazio variante="filtered" titulo="Nenhum feriado nessa localidade" desc="Feriado sem localidade vale para o negócio inteiro." acao={<button className="os-btn ghost" onClick={() => setFLocal("all")}>Ver todos</button>}/>}
      <p className="hrm-card-sub" style={{ marginTop:10 }}>Criar, editar e excluir é <b>só do administrador</b> — os demais veem a lista filtrada pelas localidades a que têm acesso.</p>
      {form && <F.FormFeriado item={form.id ? form : null} onClose={() => setForm(null)} onSalvar={salvar}/>}
      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

// ═══════════════════════ SHELL ═══════════════════════
function HrmPage({ view = "hrm" }) {
  const [papel, setPapel] = usePersist("papel", "admin");
  const [estado, setEstado] = usePersist("estado", "normal");
  const [lic, setLic] = usePersist("lic", H.LIC);
  const trocar = (setter) => (e) => { limparDados(); setter(e.target.value); };
  const go = (id) => window.__go && window.__go(id);
  const cur = TABS.find((t) => t.id === view) || TABS[0];

  const primeira = estado === "primeira";
  const demo = estado === "demo";
  const licAmb = primeira ? [] : lic;
  const ambiente = {
    papel, estado, eu:"e-1", demo, primeira,
    pode:(acao) => H.PAPEIS[papel].pode.includes(acao),
    din:(v) => demo ? "R$ ••••" : H.brl(v),
    dados:{
      lic:licAmb,
      pre:primeira ? [] : H.PRE,
      tur:primeira ? [] : H.TUR,
      fer:primeira ? [] : H.FER,
      lotes:primeira ? [] : H.LOTES,
      folha:primeira ? [] : H.FOLHA,
      tipos:primeira ? [] : H.TIPOS,
      gd:primeira ? [] : H.GD,
      metas:primeira ? {} : H.METAS,
    },
  };
  const pend = (ambiente.pode("ver_todos") ? licAmb : licAmb.filter((l) => l.emp === "e-1")).filter((l) => l.status === "pending").length;

  const body =
    view === "hrm-licencas" ? <Licencas lic={licAmb} setLic={setLic} /> :
    view === "hrm-presenca" ? <X.Presenca /> :
    view === "hrm-turnos" ? <X.Turnos /> :
    view === "hrm-folha" ? <X.Folha /> :
    view === "hrm-feriados" ? <Feriados /> :
    view === "hrm-metas" ? <X.Metas /> :
    view === "hrm-config" ? <X.Config /> :
    <Painel lic={licAmb} />;

  return (
    <Ambiente valor={ambiente}>
    <div className="os-page hrm-page" data-screen-label={`HRM · ${cur.label}`}>
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>HRM</h1>
          <p className="tabular">{H.EMP.length} colaboradores · {pend} licença{pend === 1 ? "" : "s"} a aprovar · folha 08/2026 em rascunho</p>
        </div>
        <div className="os-page-h-r">
          <span className="hrm-sim" title="Afordância de protótipo — não vai pro F3">
            <select className="hrm-sel" value={papel} onChange={trocar(setPapel)} aria-label="Papel simulado">
              {Object.entries(H.PAPEIS).map(([k, v]) => <option key={k} value={k}>{v.l}</option>)}
            </select>
            <select className="hrm-sel" value={estado} onChange={trocar(setEstado)} aria-label="Estado da tela">
              {Object.entries(H.ESTADOS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </span>
          <span className="hrm-scope">Essentials · /hrm</span>
          <button className="os-btn ghost" onClick={() => go("ponto")}>Ponto WR2</button>
        </div>
      </header>

      <nav className="hrm-tabs" role="tablist">
        {TABS.map((t) => {
          const n = t.n ? t.n({ pend, dados:ambiente.dados }) : null;
          return (
            <button key={t.id} role="tab" aria-selected={t.id === view} className={t.id === view ? "on" : ""} onClick={() => go(t.id)}>
              {t.label}{n ? <span className="hrm-tab-n">{n}</span> : null}
            </button>);
        })}
      </nav>

      {(demo || papel !== "admin") && (
        <div className="hrm-note-ds">
          <Nota tone={demo ? "warn" : "info"} title={demo ? "Modo demonstração" : `Visão de ${H.PAPEIS[papel].l.toLowerCase()}`}>
            {demo
              ? <>Ambiente de demonstração (<code>APP_ENV=demo</code>): criar, importar e excluir ficam indisponíveis, o atalho <kbd>n</kbd> desliga e valor de salário não aparece.</>
              : <>Permissões simuladas: <code>{H.PAPEIS[papel].d}</code>. O que este papel não pode fazer aparece bloqueado <b>com motivo</b>, nunca escondido sem explicação.</>}
          </Nota>
        </div>)}

      <div className="hrm-body" key={papel + estado}>{body}</div>
    </div>
    </Ambiente>
  );
}

window.HrmPage = HrmPage;
})();
