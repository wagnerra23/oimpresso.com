// hrm-extras.jsx — HRM: visões Presença · Turnos · Folha · Metas · Configurações.
// Primitivos e pontes do DS vivem em hrm-ui.jsx; formulários em hrm-forms.jsx.
// Ondas O1 (mecânica) · O2 (formulários) · O3 (espelho mensal, custo, cobertura) · O4 (DS vivo).
// Expõe window.HrmExtras.
(() => {
const { useState, useMemo, useRef } = React;
const H = window.HRM;
const U = window.HrmUI;
const F = window.HrmForms;
const { Badge, Card, Row, Seg, Nota, Kpis, Busca, Drawer, Sec, KV, Campo, Escolha, Periodo, Grafico, Tabela, Paginacao, Bulk, Skel, Vazio, Aviso, useAmbiente, SemPermissao, usePagina, useAviso, useAtalhos, useCarga, usePersist } = U;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

const HOJE = "2026-08-21";
const durMin = (p) => {
  if (!p.ent) return 0;
  const [h1, m1] = p.ent.split(":").map(Number);
  const [h2, m2] = (p.sai || "18:30").split(":").map(Number);
  let min = (h2 * 60 + m2) - (h1 * 60 + m1);
  if (min < 0) min += 1440;
  return min;
};
const hm = (min) => `${Math.floor(min / 60)}h ${String(min % 60).padStart(2, "0")}m`;

// ═══════════════════════ PRESENÇA ═══════════════════════
function Presenca() {
  const A = useAmbiente();
  const [sub, setSub] = useState("lanc");
  const [q, setQ] = useState("");
  const [emp, setEmp] = useState("all");
  const [periodo, setPeriodo] = useState({ from:"2026-08-15", to:"2026-08-21", preset:"semana" });
  const [pontos, setPontos] = usePersist("pre", A.dados.pre);
  const [marcadas, setMarcadas] = useState([]);
  const [sel, setSel] = useState(null);
  const [aviso, setAviso] = useAviso();
  const buscaRef = useRef(null);
  const carregando = useCarga();
  useAtalhos({ busca:buscaRef, onEsc:() => { if (sel) setSel(null); else if (q) setQ(""); } });

  const meu = pontos.find((p) => p.emp === "e-1" && p.data === HOJE && !p.sai);
  const iso = (d) => typeof d === "string" ? d.slice(0, 10) : d ? new Date(d).toISOString().slice(0, 10) : null;
  const de = iso(periodo.from), ate = iso(periodo.to);

  const verTodos = A.pode("ver_todos");
  const filtradas = pontos.filter((p) => {
    if (!verTodos && p.emp !== A.eu) return false;
    if (emp !== "all" && p.emp !== emp) return false;
    if (de && p.data < de) return false;
    if (ate && p.data > ate) return false;
    if (!q) return true;
    return [H.emp(p.emp).nome, p.turno, p.local, p.nEnt, p.nSai].join(" ").toLowerCase().includes(q.toLowerCase());
  });
  const pg = usePagina(filtradas, 8);
  const abertos = pontos.filter((p) => !p.sai);
  const horas = filtradas.reduce((s, p) => s + durMin(p), 0);

  const bater = (tipo) => {
    if (tipo === "saida") { setPontos((ps) => ps.map((p) => p === meu ? { ...p, sai:"18:04" } : p)); setAviso("Saída registrada às 18:04."); return; }
    setPontos((ps) => [{ id:99, emp:"e-1", data:HOJE, ent:"08:00", sai:null, turno:"Turno A", ip:"189.4.22.10", local:"Matriz — portaria", nEnt:"", nSai:"" }, ...ps]);
    setAviso(H.CFG.is_location_required ? "Entrada registrada com a localização do navegador (obrigatória na configuração)." : "Entrada registrada.");
  };
  const fechar = (ids) => {
    setPontos((ps) => ps.map((p) => ids.includes(p.id) ? { ...p, sai:p.turno === "Turno A" ? "17:30" : "22:30", nSai:"Fechada em lote pelo RH" } : p));
    setMarcadas([]);
    setAviso(H.plural(ids.length, "Marcação fechada no horário do turno.", "{n} marcações fechadas no horário do turno."));
  };

  const cols = [
    { key:"quem", label:"Colaborador", sortable:true },
    { key:"data", label:"Data", sortable:true, sortValue:(r) => r.raw.data, width:"110px" },
    { key:"ent", label:"Entrada", sortable:true, width:"120px" },
    { key:"sai", label:"Saída", width:"130px" },
    { key:"dur", label:"Duração", sortable:true, sortValue:(r) => durMin(r.raw), align:"right", width:"110px" },
    { key:"turno", label:"Turno", sortable:true, width:"110px" },
    { key:"origem", label:"Origem" },
  ];
  const rows = pg.fatia.map((p) => ({
    id:String(p.id), raw:p, state:!p.sai ? "urgent" : undefined,
    cells:{
      quem:{ primary:H.emp(p.emp).nome, sub:H.emp(p.emp).cargo },
      data:<span className="hrm-mono">{H.dt(p.data)}</span>,
      ent:<><span className="hrm-mono">{p.ent}</span>{p.nEnt && <div className="hrm-meta">{p.nEnt}</div>}</>,
      sai:p.sai ? <><span className="hrm-mono">{p.sai}</span>{p.nSai && <div className="hrm-meta hrm-clamp">{p.nSai}</div>}</> : <Badge tone="warn">em aberto</Badge>,
      dur:<span className="hrm-mono">{hm(durMin(p))}{!p.sai && <div className="hrm-meta">em curso</div>}</span>,
      turno:p.turno,
      origem:<><span className="hrm-mono">{p.ip}</span><div className="hrm-meta">{p.local || "sem localização"}</div></>,
    },
  }));

  // O3 — espelho do mês por colaborador (dia a dia: presença · licença · feriado · folga)
  const espelho = useMemo(() => {
    const dias = Array.from({ length:21 }, (_, i) => `2026-08-${String(i + 1).padStart(2, "0")}`);
    return (verTodos ? H.EMP : H.EMP.filter((e) => e.id === A.eu)).map((e) => ({
      emp:e,
      dias:dias.map((d) => {
        const wd = new Date(d + "T12:00:00").getDay();
        const turno = H.TUR.find((t) => t.nome === e.turno);
        const folga = turno && turno.folgas.some((f) => ["sunday", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday"][wd] === f);
        const fer = H.FER.some((f) => f.ini <= d && f.fim >= d && (!f.local || f.local === e.local));
        const licenca = H.LIC.some((l) => l.emp === e.id && l.status === "approved" && l.ini <= d && l.fim >= d);
        const ponto = pontos.find((p) => p.emp === e.id && p.data === d);
        return { d, tipo:fer ? "fer" : folga ? "folga" : licenca ? "lic" : ponto ? (ponto.sai ? "ok" : "aberto") : "falta", ponto };
      }),
    }));
  }, [pontos, verTodos]);

  const porTurno = H.TUR.map((t) => {
    const doTurno = pontos.filter((p) => p.data === HOJE && p.turno === t.nome);
    const nomes = [...new Set(doTurno.map((p) => H.emp(p.emp).nome))];
    return { turno:t, presentes:nomes.length, total:t.pessoas, nomes };
  });

  return (
    <>
      <Nota tone="info" title="Presença web ≠ ponto legal">
        Este é o clock-in/out do Essentials (IP + geolocalização opcional). O registro sob a Portaria MTP 671/2021 é o módulo <b>Ponto WR2</b> — hoje os dois não se conversam.
      </Nota>
      <Kpis items={[
        ...(verTodos ? [{ l:"Presentes agora", v:new Set(pontos.filter((p) => p.data === HOJE && !p.sai).map((p) => p.emp)).size, sub:`de ${H.EMP.length} colaboradores`, tone:"success" }] : []),
        ...(verTodos ? [{ l:"Sem saída registrada", v:abertos.length, sub:"turno fixo fecha automático", tone:abertos.length > 2 ? "warning" : "default" }] : []),
        { l:verTodos ? "Horas no período" : "Minhas horas no período", v:hm(horas), sub:"soma das marcações filtradas" },
        { l:"Tolerância", v:`${H.CFG.grace_before_checkin}/${H.CFG.grace_after_checkin} min`, sub:"antes/depois da entrada" },
      ]}/>

      <div className="hrm-grid">
        <Card title="Meu ponto" sub="Larissa Andrade · Turno A (08:00–17:00)">
          {meu
            ? <><Row t="Entrada registrada" s={`${H.dt(meu.data)} · ${meu.local || "sem localização"}`} v={meu.ent}/><Row t="Tempo em curso" v={hm(durMin(meu))}/>
                <div style={{ marginTop:10 }}><button className="os-btn primary" onClick={() => bater("saida")}>Registrar saída</button></div></>
            : <><p className="hrm-empty">Sem entrada registrada hoje.</p><button className="os-btn primary" onClick={() => bater("entrada")}>Registrar entrada</button></>}
        </Card>
        <Card title="Importar presença" sub="Planilha do relógio de ponto (xls/csv)">
          {!A.pode("importar") ? <SemPermissao frase="Importar presença exige a permissão de lançar presença de todos os colaboradores."/> : <>
          <div className="hrm-list">
            <Row t="Colunas esperadas" s="e-mail · entrada · saída · turno · nota entrada · nota saída · IP"/>
            <Row t="Formato de data/hora" s="Y-m-d H:i:s — fora disso a linha quebra o lote inteiro"/>
            <Row t="Colaborador casado por e-mail" s="e-mail não encontrado aborta com rollback"/>
          </div>
          <p className="hrm-card-sub" style={{ marginTop:10 }}>O importador não confere sobreposição de horário — a mesma marcação entra duas vezes se a planilha repetir (achado A7).</p>
          <button className="os-btn ghost" disabled={A.demo} onClick={() => setAviso("Simulação: 6 linhas lidas · 4 importadas · 2 recusadas (e-mail não encontrado na linha 3, hora fora do formato na linha 5).")}>Escolher planilha…</button></>}
        </Card>
      </div>

      <Seg value={sub} onChange={setSub} options={[{ id:"lanc", label:"Lançamentos" }, { id:"espelho", label:"Espelho do mês" }, { id:"turno", label:"Por turno" }, { id:"data", label:"Por data" }]}/>

      {sub === "lanc" && <>
        <div className="hrm-toolbar">
          <Busca value={q} onChange={setQ} inputRef={buscaRef} placeholder="Buscar por colaborador, turno, local ou nota…"/>
          <select className="hrm-sel" value={emp} onChange={(e) => setEmp(e.target.value)} disabled={!verTodos} aria-label="Filtrar por colaborador">
            <option value="all">{verTodos ? "Todos os colaboradores" : "Somente as minhas"}</option>
            {verTodos && H.EMP.map((e) => <option key={e.id} value={e.id}>{e.nome}</option>)}
          </select>
          <span className="usr-count">{filtradas.length} marcações</span>
          <span className="hrm-spacer"></span>
          <span className="hrm-kbd"><kbd>/</kbd> buscar</span>
        </div>
        <Periodo valor={periodo} onChange={setPeriodo} label="Período das marcações"/>
        {carregando ? <Skel n={8}/> : filtradas.length ? <>
          <Tabela cols={cols} rows={rows} selecionavel={A.pode("importar")} onSelecao={(ids) => setMarcadas((ids || []).map(Number))} onLinha={(r) => setSel(r.raw)} altura={420} ordem={{ key:"data", dir:"desc" }}/>
          <Paginacao pagina={pg.pagina} paginas={pg.paginas} onMudar={pg.setPagina} total={pg.total} porPagina={pg.porPagina}/>
        </> : A.primeira
          ? <Vazio variante="first" titulo="Nenhuma marcação ainda" desc="A presença nasce quando alguém registra entrada pela web ou o RH importa a planilha do relógio. Turno fixo aplica tolerância; flexível não compara com escala."/>
          : <Vazio variante="no-results" titulo="Nenhuma marcação no período" desc="Ajuste o período, o colaborador ou a busca." acao={<button className="os-btn ghost" onClick={() => { setQ(""); setEmp("all"); setPeriodo({ from:"2026-08-01", to:"2026-08-21", preset:"mes" }); }}>Ver o mês inteiro</button>}/>}
      </>}

      {sub === "espelho" && <>
        <div className="hrm-esp-legenda">
          <span><i className="ok"></i> presença fechada</span>
          <span><i className="aberto"></i> em aberto</span>
          <span><i className="lic"></i> licença</span>
          <span><i className="fer"></i> feriado</span>
          <span><i className="folga"></i> folga do turno</span>
          <span><i className="falta"></i> sem marcação</span>
        </div>
        <div className="os-table-wrap">
          <table className="os-table hrm-esp">
            <thead><tr><th>Colaborador</th>{espelho[0].dias.map((d) => <th key={d.d} className="hrm-esp-h">{d.d.slice(-2)}</th>)}<th className="hrm-num">Horas</th></tr></thead>
            <tbody>{espelho.map((l) => {
              const min = l.dias.reduce((s, d) => s + (d.ponto ? durMin(d.ponto) : 0), 0);
              return (
                <tr key={l.emp.id}>
                  <td><div className="hrm-name">{l.emp.nome}</div><div className="hrm-meta">{l.emp.turno}</div></td>
                  {l.dias.map((d) => <td key={d.d} className="hrm-esp-c"><i className={`hrm-esp-i ${d.tipo}`} title={`${H.dt(d.d)} · ${{ ok:"presença", aberto:"em aberto", lic:"licença", fer:"feriado", folga:"folga", falta:"sem marcação" }[d.tipo]}`}></i></td>)}
                  <td className="hrm-num hrm-mono">{hm(min)}</td>
                </tr>);
            })}</tbody>
          </table>
        </div>
        <p className="hrm-card-sub" style={{ marginTop:10 }}>O espelho é <b>montado nesta tela</b> cruzando marcação, licença aprovada, feriado e folga do turno — o backend não tem esse relatório: hoje só existe "por turno" e "por data".</p>
      </>}

      {sub === "turno" && <div className="hrm-grid">{porTurno.map((t) => (
        <Card key={t.turno.id} title={t.turno.nome} aside={`${t.presentes} de ${t.total}`}>
          <div className="hrm-bar"><i className={t.presentes >= t.total ? "ok" : ""} style={{ width:`${t.total ? Math.min(100, t.presentes / t.total * 100) : 0}%` }}></i></div>
          <div className="hrm-list" style={{ marginTop:10 }}>
            {t.nomes.length ? t.nomes.map((n) => <Row key={n} t={n} v="presente"/>) : <p className="hrm-empty">Ninguém marcou ponto neste turno hoje.</p>}
          </div>
        </Card>))}</div>}

      {sub === "data" && (() => {
        const dias = ["2026-08-21", "2026-08-20", "2026-08-19"].map((d) => {
          const n = new Set(pontos.filter((p) => p.data === d).map((p) => p.emp)).size;
          return { data:d, presentes:n, ausentes:H.EMP.length - n };
        });
        return (
          <>
            <Card title="Presença por dia" sub="Presentes contra o total de colaboradores do negócio.">
              <Grafico tipo="bar" dados={dias.slice().reverse().map((d) => ({ label:H.dt(d.data).slice(0, 5), value:d.presentes }))} altura={120} destacaUltimo/>
            </Card>
            <div className="os-table-wrap"><table className="os-table">
              <thead><tr><th>Data</th><th className="hrm-num">Presentes</th><th className="hrm-num">Ausentes</th><th>Cobertura</th></tr></thead>
              <tbody>{dias.map((d) => (
                <tr key={d.data}><td className="hrm-mono">{H.dt(d.data)}</td><td className="hrm-num">{d.presentes}</td><td className="hrm-num hrm-neg">{d.ausentes}</td>
                  <td><div className="hrm-bar" style={{ width:140 }}><i style={{ width:`${d.presentes / H.EMP.length * 100}%` }}></i></div></td></tr>))}</tbody>
            </table></div>
            <p className="hrm-card-sub" style={{ marginTop:10 }}>“Ausente” aqui é <b>todo colaborador menos quem marcou</b> — férias e licença aprovada contam como ausente (comportamento do <code>getAttendanceByDate</code>).</p>
          </>);
      })()}

      <Bulk n={marcadas.length} rotulo="marcações selecionadas" onFechar={() => setMarcadas([])} acoes={[
        { label:"Fechar no horário do turno", onClick:() => fechar(marcadas) },
        { label:"Excluir marcações", tone:"danger", onClick:() => { if (window.confirm(H.plural(marcadas.length, "Excluir esta marcação?\nA jornada do dia deixa de existir para efeito de folha.", "Excluir {n} marcações?\nA jornada desses dias deixa de existir para efeito de folha."))) { setPontos((ps) => ps.filter((p) => !marcadas.includes(p.id))); setMarcadas([]); setAviso(H.plural(marcadas.length, "Marcação excluída.", "{n} marcações excluídas.")); } } },
      ]}/>

      {sel && (() => { const e = H.emp(sel.emp); return (
        <Drawer title={`${e.nome} · ${H.dt(sel.data)}`} sub={`${sel.turno} · ${hm(durMin(sel))}`} onClose={() => setSel(null)}
          footer={<><button className="os-btn ghost" onClick={() => setSel(null)}>Fechar</button>{!sel.sai && <button className="os-btn primary" onClick={() => { fechar([sel.id]); setSel(null); }}>Fechar no horário do turno</button>}</>}>
          <Sec title="Marcação">
            <KV pairs={[["Entrada", sel.ent], ["Saída", sel.sai || "em aberto"], ["Nota de entrada", sel.nEnt || "—"], ["Nota de saída", sel.nSai || "—"], ["IP", sel.ip], ["Localização", sel.local || "não informada"]]}/>
          </Sec>
          <Sec title="Contexto do dia">
            {(() => {
              const lic = H.LIC.find((l) => l.emp === sel.emp && l.status === "approved" && l.ini <= sel.data && l.fim >= sel.data);
              const fer = H.FER.find((f) => f.ini <= sel.data && f.fim >= sel.data);
              return <div className="hrm-list">
                <Row t="Licença aprovada no dia" s={lic ? "marcação e licença convivem sem aviso" : "nenhuma"} v={lic ? "sim" : "não"}/>
                <Row t="Feriado no dia" s={fer ? fer.nome : "nenhum"} v={fer ? "sim" : "não"}/>
              </div>;
            })()}
          </Sec>
        </Drawer>); })()}

      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

// ═══════════════════════ TURNOS ═══════════════════════
function Turnos() {
  const A = useAmbiente();
  const [turnos, setTurnos] = usePersist("tur", A.dados.tur);
  const [sel, setSel] = useState(null);
  const [form, setForm] = useState(null);
  const [aviso, setAviso] = useAviso();
  const TIPO = { fixed_shift:"Turno fixo", flexible_shift:"Turno flexível" };
  const podeGerir = A.pode("gerir_turno") && !A.demo;
  const salvar = (t) => {
    setTurnos((ts) => ts.some((x) => x.id === t.id) ? ts.map((x) => x.id === t.id ? t : x) : [...ts, t]);
    setForm(null);
    setAviso(`Turno “${t.nome}” salvo.`);
  };
  return (
    <>
      {!A.pode("ver_todos") ? <SemPermissao frase="A escala de turnos exige acesso à presença de todos os colaboradores."/> : <>
      <Nota tone="warn" title="Turno não tem exclusão">
        <code>ShiftController::destroy</code> está vazio no main — dá para criar, editar e atribuir pessoas, nunca apagar. Turno errado fica no cadastro para sempre.
      </Nota>
      <Kpis items={[
        { l:"Turnos", v:turnos.length, sub:`${turnos.filter((t) => t.tipo === "fixed_shift").length} fixos · ${turnos.filter((t) => t.tipo === "flexible_shift").length} flexível`, tone:"info" },
        { l:"Vínculos vigentes", v:turnos.reduce((s, t) => s + t.pessoas, 0), sub:`de ${H.EMP.length} colaboradores` },
        { l:"Com saída automática", v:turnos.filter((t) => t.autoOut).length, sub:"fecha marcação em aberto" },
      ]}/>
      <div className="hrm-toolbar">
        <span className="usr-count">{turnos.length} turnos</span>
        <span className="hrm-spacer"></span>
        <button className="os-btn primary" disabled={!podeGerir} title={podeGerir ? null : "Só o administrador cria turno"} onClick={() => setForm({})}>Novo turno</button>
      </div>
      {turnos.length ? <><div className="os-table-wrap"><table className="os-table">
        <thead><tr><th>Turno</th><th>Tipo</th><th>Horário</th><th>Folgas</th><th>Saída automática</th><th className="hrm-num">Pessoas</th><th></th></tr></thead>
        <tbody>{turnos.map((t) => (
          <tr key={t.id}>
            <td className="hrm-name">{t.nome}</td>
            <td>{TIPO[t.tipo]}</td>
            <td className="hrm-mono">{t.tipo === "flexible_shift" ? "—" : `${t.ini}–${t.fim}`}</td>
            <td>{t.folgas.map((d) => H.DIA[d]).join(", ")}</td>
            <td>{t.autoOut ? <Badge tone="accent">às {t.autoOutAs}</Badge> : <Badge>desligada</Badge>}</td>
            <td className="hrm-num">{t.pessoas}</td>
            <td style={{ textAlign:"right" }}>
              <span className="hrm-acoes">
                <button className="os-btn ghost" disabled={!podeGerir} onClick={() => setForm(t)}>Editar</button>
                <button className="os-btn ghost" disabled={!podeGerir} onClick={() => setSel(t)}>Colaboradores</button>
              </span>
            </td>
          </tr>))}</tbody>
      </table></div>
      <p className="hrm-card-sub" style={{ marginTop:10 }}>Turno flexível não tem hora de entrada/saída: a marcação nunca é comparada com a escala, e a tolerância das configurações não se aplica.</p></>
      : <Vazio variante="first" titulo="Nenhum turno cadastrado" desc="Turno define escala, folgas e tolerância — sem ele a presença fica solta e a saída automática não roda. Comece por um turno fixo do balcão." acao={<button className="os-btn primary" disabled={!podeGerir} onClick={() => setForm({})}>Criar o primeiro turno</button>}/>}

      {form && <F.FormTurno item={form.id ? form : null} onClose={() => setForm(null)} onSalvar={salvar}/>}

      {sel && <Drawer title={`Colaboradores · ${sel.nome}`} sub={`${TIPO[sel.tipo]}${sel.ini ? ` · ${sel.ini}–${sel.fim}` : ""}`} onClose={() => setSel(null)}
        footer={<><button className="os-btn ghost" onClick={() => setSel(null)}>Cancelar</button><button className="os-btn primary" onClick={() => { setSel(null); setAviso("Vínculos salvos — quem saiu perde o histórico de escala."); }}>Salvar vínculos</button></>}>
        <Sec title="Vigência por pessoa">
          <div className="hrm-list">
            {H.EMP.map((e) => (
              <div className="hrm-row" key={e.id}>
                <span className="hrm-row-l"><span className="hrm-row-t">{e.nome}</span><span className="hrm-row-s">{e.cargo} · {e.local}</span></span>
                <span className="hrm-row-v">{e.turno === sel.nome ? <Badge tone="ok">no turno desde {e.admissao}</Badge> : <button className="os-btn ghost">Incluir</button>}</span>
              </div>))}
          </div>
        </Sec>
        <Sec title="Como o vínculo funciona">
          <p className="hrm-achado-d">Cada vínculo guarda início e fim. Desmarcar alguém <b>apaga</b> o vínculo (não arquiva) — o histórico de qual turno a pessoa cumpria no mês passado se perde, e o relatório “por turno” muda retroativamente.</p>
        </Sec>
      </Drawer>}

      <Aviso msg={aviso} tone="ok"/>
      </>}
    </>
  );
}

// ═══════════════════════ FOLHA DE PAGAMENTO ═══════════════════════
function Folha() {
  const A = useAmbiente();
  const [sub, setSub] = useState(A.pode("gerir_folha") ? "lotes" : "contra");
  const [lotes, setLotes] = usePersist("lotes", A.dados.lotes);
  const [folha, setFolha] = usePersist("folha", A.pode("gerir_folha") ? A.dados.folha : A.dados.folha.filter((f) => f.emp === A.eu));
  const [sel, setSel] = useState(null);
  const [gerar, setGerar] = useState(false);
  const [pagar, setPagar] = useState(null);
  const [aviso, setAviso] = useAviso();
  const carregando = useCarga();
  const ST_LOTE = { draft:{ l:"Rascunho", t:"muted" }, final:{ l:"Fechada", t:"accent" } };

  const aPagar = folha.filter((f) => f.pagamento !== "paid").reduce((s, f) => s + H.totalFolha(f), 0);
  const bruto = folha.filter((f) => f.mes === "08/2026").reduce((s, f) => s + H.totalFolha(f), 0);
  const serie = useMemo(() => ["07/2026", "08/2026"].map((m) => ({ label:m, value:folha.filter((f) => f.mes === m).reduce((s, f) => s + H.totalFolha(f), 0) })), [folha]);
  const pg = usePagina(folha, 10);

  const gerarLote = ({ comp, local, quem }) => {
    const id = Math.max(...lotes.map((l) => l.id)) + 1;
    const novos = quem.map((emp, i) => {
      const e = H.emp(emp);
      return { id:900 + i, ref:`${H.CFG.payroll_ref_no_prefix}${String(20 + i).padStart(4, "0")}`, emp, mes:comp, lote:id, base:e.salario, ganhos:[["Vale-refeição", 660]], deducoes:e.cargo.includes("Estag") ? [] : [["Vale-transporte", Math.round(e.salario * 0.06)]], pagamento:"due", horas:0, faltas:0 };
    });
    setLotes((ls) => [{ id, nome:`Folha ${comp} — ${local}`, mes:comp, local, status:"draft", pagamento:"due", bruto:novos.reduce((s, f) => s + H.totalFolha(f), 0), criadoPor:"Eliana Pereira", criadoEm:"21/08/2026", itens:novos.length }, ...ls]);
    setFolha((fs) => [...novos, ...fs]);
    setGerar(false);
    setAviso(H.plural(novos.length, `1 contracheque gerado em rascunho para ${comp}.`, `{n} contracheques gerados em rascunho para ${comp}.`));
  };
  const fecharLote = (l) => {
    if (!window.confirm(`Fechar a folha ${l.mes}?\nDepois de fechada não dá para excluir o lote — só lançar pagamento. Cada colaborador recebe a notificação.`)) return;
    setLotes((ls) => ls.map((x) => x.id === l.id ? { ...x, status:"final" } : x));
    setAviso(H.plural(l.itens, `Folha ${l.mes} fechada — 1 colaborador notificado.`, `Folha ${l.mes} fechada — {n} colaboradores notificados.`));
  };
  const excluirLote = (l) => {
    if (l.status !== "draft") return;
    if (!window.confirm(`Excluir o lote ${l.nome}?\nOs ${l.itens} contracheques em rascunho são apagados.`)) return;
    setLotes((ls) => ls.filter((x) => x.id !== l.id));
    setFolha((fs) => fs.filter((f) => f.lote !== l.id));
    setAviso("Lote em rascunho excluído.");
  };
  const lancar = ({ lote, parcial }) => {
    setFolha((fs) => fs.map((f) => f.lote === lote.id ? { ...f, pagamento:parcial ? "partial" : "paid" } : f));
    setLotes((ls) => ls.map((x) => x.id === lote.id ? { ...x, pagamento:parcial ? "partial" : "paid" } : x));
    setPagar(null);
    setAviso(parcial ? "Pagamento parcial lançado — o lote fica parcial até fechar todos." : "Pagamento lançado; lote pago.");
  };

  return (
    <>
      <Kpis items={A.pode("gerir_folha") ? [
        { l:"Folha 08/2026", v:A.din(bruto), sub:`${folha.filter((f) => f.mes === "08/2026").length} contracheques`, tone:"info" },
        { l:"A pagar", v:A.din(aPagar), sub:"sem pagamento lançado", tone:"warning" },
        { l:"Lotes fechados", v:lotes.filter((l) => l.status === "final").length, sub:lotes.length ? "07/2026 matriz e filial" : "nenhum lote ainda" },
        { l:"Ganhos e deduções ativos", v:A.dados.gd.length, sub:A.dados.gd.length ? "3 ganhos · 2 deduções" : "nada cadastrado" },
      ] : [
        { l:"Meus contracheques", v:folha.length, sub:"últimas competências", tone:"info" },
        { l:"Último líquido", v:folha.length ? A.din(H.totalFolha(folha[0])) : "—", sub:folha.length ? `competência ${folha[0].mes}` : "nada lançado" },
      ]}/>
      <Seg value={sub} onChange={setSub} options={A.pode("gerir_folha")
        ? [{ id:"lotes", label:"Lotes" }, { id:"contra", label:"Contracheques" }, { id:"gd", label:"Ganhos e deduções" }, { id:"custo", label:"Custo" }]
        : [{ id:"contra", label:"Meus contracheques" }]}/>

      {sub === "lotes" && A.pode("gerir_folha") && <>
        <div className="hrm-toolbar"><span className="usr-count">{lotes.length} lotes</span><span className="hrm-spacer"></span><button className="os-btn primary" disabled={A.demo} onClick={() => setGerar(true)}>Gerar folha do mês</button></div>
        {carregando ? <Skel n={4}/> : lotes.length ? <div className="os-table-wrap"><table className="os-table">
          <thead><tr><th>Lote</th><th>Competência</th><th>Local</th><th>Situação</th><th>Pagamento</th><th className="hrm-num">Bruto</th><th className="hrm-num">Itens</th><th></th></tr></thead>
          <tbody>{lotes.map((l) => (
            <tr key={l.id}>
              <td><div className="hrm-name">{l.nome}</div><div className="hrm-meta">{l.criadoEm} · {l.criadoPor}</div></td>
              <td className="hrm-mono">{l.mes}</td><td>{l.local}</td>
              <td><Badge tone={ST_LOTE[l.status].t}>{ST_LOTE[l.status].l}</Badge></td>
              <td><Badge tone={H.ST_PAG[l.pagamento].t}>{H.ST_PAG[l.pagamento].l}</Badge></td>
              <td className="hrm-num">{A.din(l.bruto)}</td><td className="hrm-num">{l.itens}</td>
              <td style={{ textAlign:"right" }}>
                <span className="hrm-acoes">
                  {l.status === "draft" && <button className="os-btn ghost" disabled={A.demo} onClick={() => fecharLote(l)}>Fechar</button>}
                  {l.pagamento !== "paid" && l.status === "final" && <button className="os-btn ghost" disabled={A.demo} onClick={() => setPagar(l)}>Pagar</button>}
                  {l.status === "draft" && <button className="os-btn ghost" disabled={A.demo} onClick={() => excluirLote(l)}>Excluir</button>}
                </span>
              </td>
            </tr>))}</tbody>
        </table></div>
        : <Vazio variante="first" titulo="Nenhuma folha gerada" desc="A folha nasce por competência e localidade: escolha o mês e quem entra, o sistema soma salário, comissões e ganhos recorrentes. Sem encargo — é folha gerencial." acao={<button className="os-btn primary" disabled={A.demo} onClick={() => setGerar(true)}>Gerar a primeira folha</button>}/>}
        <p className="hrm-card-sub" style={{ marginTop:10 }}>Só lote em <b>rascunho</b> pode ser excluído — depois de fechado, o caminho é lançar pagamento. Fechar com “notificar” avisa cada colaborador por e-mail.</p>
      </>}

      {sub === "contra" && (folha.length ? <>
        <div className="os-table-wrap"><table className="os-table">
          <thead><tr><th>Ref.</th><th>Colaborador</th><th>Competência</th><th className="hrm-num">Base</th><th className="hrm-num">Ganhos</th><th className="hrm-num">Deduções</th><th className="hrm-num">Líquido</th><th>Pagamento</th></tr></thead>
          <tbody>{pg.fatia.map((f) => { const e = H.emp(f.emp); const g = f.ganhos.reduce((s, x) => s + x[1], 0); const d = f.deducoes.reduce((s, x) => s + x[1], 0); return (
            <tr key={f.id} onClick={() => setSel(f)} style={{ cursor:"pointer" }}>
              <td className="hrm-mono">{f.ref}</td>
              <td><div className="hrm-name">{e.nome}</div><div className="hrm-meta">{e.cargo} · {e.setor}</div></td>
              <td className="hrm-mono">{f.mes}</td>
              <td className="hrm-num">{A.din(f.base)}</td>
              <td className="hrm-num hrm-pos">{g ? "+" + A.din(g) : "—"}</td>
              <td className="hrm-num hrm-neg">{d ? "−" + A.din(d) : "—"}</td>
              <td className="hrm-num"><b>{A.din(H.totalFolha(f))}</b></td>
              <td><Badge tone={H.ST_PAG[f.pagamento].t}>{H.ST_PAG[f.pagamento].l}</Badge></td>
            </tr>); })}</tbody>
        </table></div>
        <Paginacao pagina={pg.pagina} paginas={pg.paginas} onMudar={pg.setPagina} total={pg.total} porPagina={pg.porPagina}/>
        <p className="hrm-card-sub" style={{ marginTop:10 }}>Comissão de venda e comissão de meta entram como <b>ganho calculado</b> na geração (percentual do colaborador × faturado, e a faixa de meta atingida). Depois disso, nada é recalculado.</p>
      </> : <Vazio variante="first" titulo="Nenhum contracheque" desc={A.pode("gerir_folha") ? "Gere a folha do mês para ver os contracheques aqui." : "Quando o RH fechar a folha do mês, o seu contracheque aparece nesta lista e você recebe um e-mail."}/>)}

      {sub === "gd" && <>
        <div className="hrm-toolbar"><span className="usr-count">{A.dados.gd.length} lançamentos recorrentes</span><span className="hrm-spacer"></span><button className="os-btn primary" disabled={A.demo || !A.pode("gerir_folha")}>Novo lançamento</button></div>
        {A.dados.gd.length ? <div className="os-table-wrap"><table className="os-table">
          <thead><tr><th>Descrição</th><th>Natureza</th><th>Forma</th><th className="hrm-num">Valor</th><th>Aplica em</th></tr></thead>
          <tbody>{A.dados.gd.map((g) => (
            <tr key={g.id}>
              <td className="hrm-name">{g.desc}</td>
              <td>{g.tipo === "allowance" ? <Badge tone="ok">Ganho</Badge> : <Badge tone="danger">Dedução</Badge>}</td>
              <td>{g.forma === "fixed" ? "Valor fixo" : "Percentual do salário"}</td>
              <td className="hrm-num">{g.forma === "fixed" ? A.din(g.valor) : g.valor.toLocaleString("pt-BR") + "%"}</td>
              <td>{g.aplicaEm}</td>
            </tr>))}</tbody>
        </table></div>
        : <Vazio variante="first" titulo="Nenhum ganho ou dedução recorrente" desc="São os lançamentos que entram sozinhos em toda folha — vale-refeição, vale-transporte, insalubridade, adiantamento. Valor fixo ou percentual do salário."/>}
      </>}

      {sub === "custo" && A.pode("gerir_folha") && (() => {
        const porSetor = {};
        folha.filter((f) => f.mes === "08/2026").forEach((f) => { const s = H.emp(f.emp).setor; porSetor[s] = (porSetor[s] || 0) + H.totalFolha(f); });
        const setores = Object.entries(porSetor).map(([label, value]) => ({ label, value })).sort((a, b) => b.value - a.value);
        const ganhos = {};
        folha.filter((f) => f.mes === "08/2026").forEach((f) => f.ganhos.forEach(([k, v]) => { ganhos[k] = (ganhos[k] || 0) + v; }));
        return (
          <div className="hrm-grid">
            <Card title="Folha por competência" sub="Líquido dos contracheques, sem encargos.">
              <Grafico tipo="bar" dados={serie} altura={130} formata={(v) => A.din(v)} destacaUltimo/>
              <div className="hrm-list" style={{ marginTop:8 }}>{serie.map((s) => <Row key={s.label} t={s.label} v={A.din(s.value)}/>)}</div>
            </Card>
            <Card title="Custo por setor" aside="08/2026">
              <Grafico tipo="bar" dados={setores} altura={130} formata={(v) => A.din(v)}/>
              <div className="hrm-list" style={{ marginTop:8 }}>{setores.map((s) => <Row key={s.label} t={s.label} v={A.din(s.value)}/>)}</div>
            </Card>
            <Card title="Composição dos ganhos" aside="08/2026" sub="Onde o valor acima de salário base é gerado.">
              <div className="hrm-list">{Object.entries(ganhos).sort((a, b) => b[1] - a[1]).map(([k, v]) => <Row key={k} t={k} v={A.din(v)}/>)}</div>
            </Card>
          </div>);
      })()}

      {gerar && <F.FormFolha onClose={() => setGerar(false)} onGerar={gerarLote}/>}
      {pagar && <F.FormPagamento lote={pagar} onClose={() => setPagar(null)} onPagar={lancar}/>}

      {sel && (() => { const e = H.emp(sel.emp); return (
        <Drawer title={`${sel.ref} · ${e.nome}`} sub={`Competência ${sel.mes} · ${e.cargo}`} onClose={() => setSel(null)}
          footer={<><button className="os-btn ghost" onClick={() => setSel(null)}>Fechar</button>{sel.pagamento !== "paid" && A.pode("gerir_folha") && <button className="os-btn primary" disabled={A.demo} onClick={() => { const l = lotes.find((x) => x.id === sel.lote); setSel(null); if (l) setPagar(l); }}>Lançar pagamento</button>}</>}>
          <Sec title="Composição">
            <div className="hrm-list">
              <Row t="Salário base" s={sel.base ? "por mês" : ""} v={A.din(sel.base)}/>
              {sel.ganhos.map((g) => <Row key={g[0]} t={g[0]} s="ganho" v={"+ " + A.din(g[1])}/>)}
              {sel.deducoes.map((d) => <Row key={d[0]} t={d[0]} s="dedução" v={"− " + A.din(d[1])}/>)}
              <Row t="Líquido" v={A.din(H.totalFolha(sel))}/>
            </div>
          </Sec>
          <Sec title="Apuração do mês">
            <KV pairs={[["Horas registradas", `${sel.horas} h (presença web)`], ["Dias de licença", H.plural(sel.faltas, "1 dia", "{n} dias")], ["Local de trabalho", e.local], ["Situação do pagamento", H.ST_PAG[sel.pagamento].l]]}/>
          </Sec>
          <Sec title="O que a folha NÃO faz aqui">
            <p className="hrm-achado-d">Sem INSS, IRRF, FGTS, 13º ou férias proporcionais: o módulo soma ganhos, subtrai deduções e grava uma despesa. Encargos e guias continuam fora do sistema.</p>
          </Sec>
        </Drawer>); })()}

      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

// ═══════════════════════ METAS DE VENDA ═══════════════════════
function Metas() {
  const A = useAmbiente();
  const [metas, setMetas] = usePersist("metas", A.dados.metas);
  const [form, setForm] = useState(null);
  const [aviso, setAviso] = useAviso();
  const faixaDe = (id, v) => (metas[id] || []).find((f) => v >= f.ini && v <= f.fim);
  const salvar = (id, faixas) => {
    setMetas((m) => ({ ...m, [id]:faixas }));
    setForm(null);
    setAviso(faixas.length
      ? H.plural(faixas.length, "1 faixa salva — o conjunto anterior foi substituído.", "{n} faixas salvas — o conjunto anterior foi substituído.")
      : "Todas as faixas removidas: comissão de meta zerada.");
  };
  const comTotal = H.EMP.reduce((s, e) => { const r = H.REALIZADO[e.id] || { mes:0 }; const f = faixaDe(e.id, r.mes); return s + (f ? r.mes * f.pct / 100 : 0); }, 0);
  const podeGerir = A.pode("gerir_meta") && !A.demo;
  const linhas = A.pode("ver_todos") ? H.EMP : H.EMP.filter((e) => e.id === A.eu);

  return (
    <>
      <Nota tone="warn" title="Faixa sobreposta = comissão indeterminada">
        Salvar meta não compara as faixas entre si; a apuração pega a <b>primeira</b> faixa que contém o valor vendido. O formulário desta tela recusa sobreposição — o servidor aceita (achado A5).
      </Nota>
      <Kpis items={[
        { l:A.pode("ver_todos") ? "Comissão de meta apurada" : "Minha comissão de meta", v:A.din(A.pode("ver_todos") ? comTotal : (() => { const r = H.REALIZADO[A.eu] || { mes:0 }; const f = faixaDe(A.eu, r.mes); return f ? r.mes * f.pct / 100 : 0; })()), sub:"mês atual, faixas vigentes", tone:"info" },
        { l:"Com meta cadastrada", v:linhas.filter((e) => (metas[e.id] || []).length).length, sub:H.plural(linhas.length, "de 1 colaborador", "de {n} colaboradores") },
        { l:"Base do cálculo", v:H.CFG.calculate_sales_target_commission_without_tax ? "Sem imposto" : "Com imposto", sub:"configuração do módulo" },
      ]}/>
      <div className="os-table-wrap"><table className="os-table">
        <thead><tr><th>Colaborador</th><th className="hrm-num">Mês anterior</th><th className="hrm-num">Mês atual</th><th>Faixa atingida</th><th>Progresso na faixa</th><th className="hrm-num">Comissão</th><th></th></tr></thead>
        <tbody>{linhas.map((e) => {
          const r = H.REALIZADO[e.id] || { mes:0, anterior:0 };
          const faixas = metas[e.id] || [];
          const f = faixaDe(e.id, r.mes);
          const com = f ? r.mes * f.pct / 100 : 0;
          const prox = faixas.find((x) => x.ini > r.mes);
          return (
            <tr key={e.id}>
              <td><div className="hrm-name">{e.nome}</div><div className="hrm-meta">{e.cargo} · comissão fixa {e.comissao}%</div></td>
              <td className="hrm-num">{r.anterior ? A.din(r.anterior) : "—"}</td>
              <td className="hrm-num">{r.mes ? A.din(r.mes) : "—"}</td>
              <td>{!faixas.length ? <Badge>sem meta</Badge> : f ? <Badge tone="ok">{A.din(f.ini)} – {A.din(f.fim)} · {f.pct}%</Badge> : <Badge tone="warn">fora de toda faixa</Badge>}</td>
              <td>{f
                ? <><div className="hrm-bar" style={{ width:130 }}><i style={{ width:`${Math.min(100, (r.mes - f.ini) / Math.max(1, f.fim - f.ini) * 100)}%` }}></i></div>
                    {prox && <div className="hrm-meta">faltam {A.din(prox.ini - r.mes)} para {prox.pct}%</div>}</>
                : <span className="hrm-meta">—</span>}</td>
              <td className="hrm-num">{com ? A.din(com) : "—"}</td>
              <td style={{ textAlign:"right" }}><button className="os-btn ghost" disabled={!podeGerir} title={podeGerir ? null : "Só o administrador define meta"} onClick={() => setForm(e)}>{faixas.length ? "Editar faixas" : "Definir meta"}</button></td>
            </tr>);
        })}</tbody>
      </table></div>
      <p className="hrm-card-sub" style={{ marginTop:10 }}>A base do cálculo segue a configuração <b>“apurar comissão de meta sem imposto”</b> ({H.CFG.calculate_sales_target_commission_without_tax ? "ligada" : "desligada"}) — ligada, o valor vendido entra sem tributo.</p>

      {form && <F.FormMeta emp={form} faixas={metas[form.id] || []} onClose={() => setForm(null)} onSalvar={salvar}/>}
      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

// ═══════════════════════ CONFIGURAÇÕES ═══════════════════════
function Config() {
  const A = useAmbiente();
  const [c, setC] = useState(H.CFG);
  const [aviso, setAviso] = useAviso();
  const set = (k, v) => setC((s) => ({ ...s, [k]:v }));
  const { Switch } = ds();
  const sujo = JSON.stringify(c) !== JSON.stringify(H.CFG);
  const flag = (k, label, sub) => Switch
    ? <Switch key={k} checked={c[k]} onChange={() => set(k, !c[k])} label={label} sublabel={sub} />
    : <label key={k} className="hrm-flag"><input type="checkbox" checked={c[k]} onChange={() => set(k, !c[k])}/> <span><b>{label}</b><i>{sub}</i></span></label>;

  return (
    <>
      {!A.pode("config") ? <SemPermissao frase="As configurações do módulo são de administrador — o próprio controller recusa quem não é."/> : <>
      <Nota tone="info" title="Esta tela já é Inertia no main">
        <code>EssentialsSettingsController</code> renderiza <code>Essentials/Settings/Index</code> e grava em <code>businesses.essentials_settings</code> (JSON). Só administrador vê e edita — é a única tela do HRM que não é mais Blade.
      </Nota>
      <div className="hrm-grid">
        <Card title="Licenças">
          <div className="hrm-campos">
            <Campo label="Prefixo do número de referência" valor={c.leave_ref_no_prefix} onChange={(v) => set("leave_ref_no_prefix", v)} help="máx. 32 caracteres"/>
            <U.Texto label="Instruções ao colaborador" valor={c.leave_instructions} onChange={(v) => set("leave_instructions", v)} help="aparece no formulário de pedido de licença"/>
          </div>
        </Card>
        <Card title="Folha e tarefas">
          <div className="hrm-campos">
            <Campo label="Prefixo da folha" valor={c.payroll_ref_no_prefix} onChange={(v) => set("payroll_ref_no_prefix", v)}/>
            <Campo label="Prefixo das tarefas" valor={c.essentials_todos_prefix} onChange={(v) => set("essentials_todos_prefix", v)} help="usado ao criar tarefa do Essentials"/>
          </div>
        </Card>
        <Card title="Tolerância de marcação" sub="Em minutos. Só vale para turno fixo — turno flexível ignora.">
          <div className="hrm-campos">
            <Campo label="Antes da entrada" valor={c.grace_before_checkin} onChange={(v) => set("grace_before_checkin", v)}/>
            <Campo label="Depois da entrada" valor={c.grace_after_checkin} onChange={(v) => set("grace_after_checkin", v)}/>
            <Campo label="Antes da saída" valor={c.grace_before_checkout} onChange={(v) => set("grace_before_checkout", v)}/>
            <Campo label="Depois da saída" valor={c.grace_after_checkout} onChange={(v) => set("grace_after_checkout", v)}/>
          </div>
        </Card>
        <Card title="Regras">
          <div className="hrm-cfg-flags">
            {flag("is_location_required", "Exigir localização na marcação", "Sem permissão de local no navegador, a marcação é recusada")}
            {flag("calculate_sales_target_commission_without_tax", "Apurar comissão de meta sem imposto", "O vendido entra sem tributo no cálculo da faixa")}
          </div>
          <p className="hrm-card-sub" style={{ marginTop:12 }}>“Permitir que o colaborador registre a própria presença” <b>não está aqui</b>: virou permissão de função (<code>allow_users_for_attendance_from_web</code>).</p>
        </Card>
      </div>
      <div className="hrm-cfg-acoes">
        <button className="os-btn primary" disabled={!sujo || A.demo} onClick={() => setAviso("Configurações salvas (protótipo — nada gravado no banco).")}>Salvar configurações</button>
        <button className="os-btn ghost" disabled={!sujo} onClick={() => setC(H.CFG)}>Descartar alterações</button>
        {sujo && <span className="hrm-meta">alterações não salvas</span>}
      </div>
      <Aviso msg={aviso} tone="ok"/>
      </>}
    </>
  );
}

window.HrmExtras = { Presenca, Turnos, Folha, Metas, Config };
})();
