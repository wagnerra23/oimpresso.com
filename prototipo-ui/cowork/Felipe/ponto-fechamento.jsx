// ponto-fechamento.jsx — Onda 3: o fechamento da competência como FLUXO (não existe no Blade,
// que só tem telas soltas) + painel de conformidade CLT apurado dos próprios dados.
// Regras vindas de Modules/Ponto/Config/config.php: interjornada Art. 66, intrajornada Art. 71,
// limite de HE Art. 59, tolerância Art. 58 §1º, sequência de NSR (Portaria 671/2021 Anexo I).
// Expõe window.PontoFechamento = { Fechamento, Conformidade, achados }.
(() => {
const { useState, useMemo, useEffect } = React;
const P = () => window.PONTO;
const U = () => window.PontoUI;

const hm = (s) => { const [h, m] = s.split(":").map(Number); return h * 60 + m; };

// ── Apuração das violações: uma passada pelos dias de quem controla ponto ──
function achados(mes) {
  const D = P();
  const C = D.CONFIG.clt;
  const out = { interjornada: [], intrajornada: [], he: [], nsr: [], impar: [], sem_pis: [] };
  D.COLABORADORES.filter((c) => c.controla_ponto && !c.desligamento).forEach((c) => {
    if (!c.pis) out.sem_pis.push({ colab: c, detalhe: "PIS não cadastrado — a linha do AFD é rejeitada na importação" });
    const dias = D.dias(mes, c.id);
    let ultimoNsr = null;
    dias.forEach((d, i) => {
      if (d.folga) return;
      // interjornada: saída do dia anterior → entrada de hoje
      const ant = [...dias.slice(0, i)].reverse().find((x) => !x.folga && x.realizada_saida);
      if (ant && d.realizada_entrada) {
        const gap = 24 * 60 - hm(ant.realizada_saida) + hm(d.realizada_entrada) - (d.dia - ant.dia - 1) * 0;
        const dist = (d.dia - ant.dia - 1) * 24 * 60 + gap;
        if (dist < C.interjornada_minima_horas * 60) out.interjornada.push({ colab: c, dia: d.dia, valor: D.fmtMin(dist), limite: C.interjornada_minima_horas + "h", detalhe: "saiu " + ant.realizada_saida + " dia " + ant.dia + ", entrou " + d.realizada_entrada });
      }
      // intrajornada: 2ª → 3ª marcação
      if (d.marcacoes.length >= 3) {
        const almoco = hm(d.marcacoes[2].hora) - hm(d.marcacoes[1].hora);
        if (almoco < C.intrajornada_minima_minutos) out.intrajornada.push({ colab: c, dia: d.dia, valor: D.fmtMin(almoco), limite: C.intrajornada_minima_minutos + " min", detalhe: d.marcacoes[1].hora + " → " + d.marcacoes[2].hora });
      }
      // limite de HE diária
      const he = d.he_diurna + d.he_noturna;
      if (he > C.limite_he_diaria_horas * 60) out.he.push({ colab: c, dia: d.dia, valor: D.fmtMin(he), limite: C.limite_he_diaria_horas + "h", detalhe: "excedente autorizado? conferir intercorrência do dia" });
      // marcação ímpar (jornada aberta)
      if (d.marcacoes.length % 2 === 1) out.impar.push({ colab: c, dia: d.dia, valor: d.marcacoes.length + " marcações", limite: "par", detalhe: "jornada sem fechamento — apuração fica em DIVERGENCIA" });
      if (d.marcacoes.length === 0 && d.falta > 0) out.impar.push({ colab: c, dia: d.dia, valor: "sem marcação", limite: "par", detalhe: "falta sem intercorrência aplicada" });
      // sequência de NSR
      d.marcacoes.forEach((m) => {
        if (m.origem === "ANULACAO") return;
        if (ultimoNsr != null && m.nsr < ultimoNsr) out.nsr.push({ colab: c, dia: d.dia, valor: "NSR " + m.nsr, limite: "> " + ultimoNsr, detalhe: "NSR fora de ordem no arquivo de origem" });
        ultimoNsr = Math.max(ultimoNsr == null ? m.nsr : ultimoNsr, m.nsr);
      });
    });
  });
  return out;
}

const REGRAS = [
  { id: "impar", titulo: "Jornada sem fechamento", lei: "marcação ímpar / falta sem justificativa", tom: "warn" },
  { id: "interjornada", titulo: "Interjornada abaixo do mínimo", lei: "Art. 66 CLT — 11h entre jornadas", tom: "danger" },
  { id: "intrajornada", titulo: "Intrajornada abaixo do mínimo", lei: "Art. 71 CLT — 60 min acima de 6h", tom: "danger" },
  { id: "he", titulo: "Hora extra acima do limite diário", lei: "Art. 59 CLT — 2h/dia", tom: "warn" },
  { id: "nsr", titulo: "NSR fora de sequência", lei: "Portaria 671/2021 Anexo I", tom: "danger" },
  { id: "sem_pis", titulo: "Colaborador sem PIS", lei: "bloqueia AFD e eSocial S-2230", tom: "warn" },
];

// ═══════════════════════════ CONFORMIDADE ═══════════════════════════
function Conformidade({ mes, onVerColaborador }) {
  const D = P();
  const { Card, Kpi, Tabela, Vazio, Nota, Pill } = U();
  const a = useMemo(() => achados(mes), [mes]);
  const comp = D.comp(mes);
  const [regra, setRegra] = useState(REGRAS.find((r) => a[r.id].length)?.id || "impar");
  const total = REGRAS.reduce((n, r) => n + a[r.id].length, 0);
  const bloqueantes = a.interjornada.length + a.intrajornada.length + a.nsr.length;
  const sel = REGRAS.find((r) => r.id === regra);

  return (
    <>
      {total === 0
        ? <Nota tom="ok" titulo="Competência limpa">Nenhuma violação apurada nas seis verificações. O AFD sai fiel e o eSocial não trava.</Nota>
        : <Nota tom={bloqueantes ? "danger" : "warn"} titulo={total + (total === 1 ? " apontamento na competência" : " apontamentos na competência")}>
            {bloqueantes > 0
              ? <>{bloqueantes === 1 ? "Um é de regra dura" : bloqueantes + " são de regra dura"} (interjornada, intrajornada, NSR) — fechar assim expõe a empresa em fiscalização. O resto é conferência.</>
              : <>Nenhum apontamento de regra dura; o que resta é conferência de jornada aberta e hora extra.</>}
          </Nota>}

      <div className="pt-kpis" data-contract="conformidade-regras">
        {REGRAS.map((r) => (
          <Kpi key={r.id} label={r.titulo} valor={a[r.id].length} ln={r.lei}
            tom={a[r.id].length === 0 ? "" : r.tom === "danger" ? "neg" : "warn"}
            onClick={() => setRegra(r.id)} />
        ))}
      </div>

      <Card icon="shield" titulo={sel.titulo} sub={sel.lei + " · " + a[regra].length + (a[regra].length === 1 ? " caso" : " casos")}>
        <Tabela cols={[{ l: "Colaborador" }, { l: "Dia", w: "70px" }, { l: "Apurado" }, { l: "Limite" }, { l: "Detalhe" }, { l: "Ação", num: true, w: "112px" }]}>
          {a[regra].length === 0 && <Vazio icon="check" colSpan={6}>Nenhum caso nesta verificação.</Vazio>}
          {a[regra].map((x, i) => (
            <tr key={i}>
              <td><b>{x.colab.nome}</b><small>{x.colab.matricula} · {x.colab.cargo}</small></td>
              <td className="mono">{x.dia ? String(x.dia).padStart(2, "0") + "/" + String(comp.mesNum).padStart(2, "0") : "—"}</td>
              <td><Pill tom={sel.tom === "danger" ? "danger" : "warn"} mono>{x.valor}</Pill></td>
              <td className="mono">{x.limite || "—"}</td>
              <td><small style={{ color: "var(--text-dim)" }}>{x.detalhe}</small></td>
              <td className="num"><button className="pt-btn" onClick={() => onVerColaborador(x.colab.id)}>Ver espelho</button></td>
            </tr>
          ))}
        </Tabela>
      </Card>
    </>
  );
}

// ═══════════════════════════ FECHAMENTO ═══════════════════════════
const PASSOS = [
  { id: "checagem", label: "Pré-checagem" },
  { id: "consolidado", label: "Consolidar apuração" },
  { id: "fechado", label: "Fechar competência" },
  { id: "arquivos", label: "Gerar AFD / AEJ" },
];

function Fechamento({ mes, setMes, avisar, onVerColaborador, onIr, intercorrencias }) {
  const D = P();
  const { Card, Kpi, Tabela, Vazio, Nota, Legal, Pill, Ic } = U();
  const chave = "oimpresso.ponto.fechamento." + mes;
  const [estado, setEstado] = useState(() => { try { return localStorage.getItem(chave) || (mes === D.MES ? "aberto" : "fechado"); } catch (e) { return "aberto"; } });
  const [excecoes, setExcecoes] = useState(0);
  useEffect(() => { try { setEstado(localStorage.getItem(chave) || (mes === D.MES ? "aberto" : "fechado")); } catch (e) {} setExcecoes(0); }, [mes]);
  const mudar = (e, msg, tom) => { setEstado(e); try { localStorage.setItem(chave, e); } catch (x) {} avisar(msg, tom); };

  const comp = D.comp(mes);
  const daComp = (br) => { // "dd/mm/aaaa" ou "dd/mm/aaaa hh:mm" dentro da competência
    const p = (br || "").slice(0, 10).split("/");
    return Number(p[1]) === comp.mesNum && Number(p[2]) === comp.ano;
  };
  const a = useMemo(() => achados(mes), [mes]);
  const lista = intercorrencias || D.INTERCORRENCIAS;
  const pendentes = lista.filter((i) => (i.estado === "PENDENTE" || i.estado === "RASCUNHO") && daComp(i.data));
  const divergentes = D.COLABORADORES.filter((c) => c.controla_ponto && !c.desligamento)
    .map((c) => ({ c, n: D.totaisEspelho(D.dias(mes, c.id)).divergencias })).filter((x) => x.n > 0);
  const importando = D.IMPORTACOES.filter((i) => (i.estado === "PROCESSANDO" || i.estado === "PENDENTE") && daComp(i.created_at));
  const duro = a.interjornada.length + a.intrajornada.length + a.nsr.length;

  const plural = (n, s, p) => n + " " + (n === 1 ? s : p);
  const bloqueios = [
    { id: "diverg", grave: true, titulo: plural(divergentes.reduce((n, x) => n + x.n, 0), "dia em DIVERGENCIA", "dias em DIVERGENCIA"), sub: plural(divergentes.length, "colaborador", "colaboradores") + " — apuração não consolida com dia divergente", acao: "Ver espelhos", ir: () => onIr("espelho"), n: divergentes.reduce((n, x) => n + x.n, 0) },
    { id: "interc", grave: true, titulo: plural(pendentes.length, "intercorrência em aberto", "intercorrências em aberto"), sub: "rascunho ou pendente — decidir antes de consolidar, senão a correção fica fora do mês", acao: "Abrir fila", ir: () => onIr("aprovacoes"), n: pendentes.length },
    { id: "pis", grave: false, titulo: plural(a.sem_pis.length, "colaborador sem PIS", "colaboradores sem PIS"), sub: "o AFD rejeita a linha e o eSocial S-2230 não sobe", acao: "Configurar", ir: () => onIr("colaboradores"), n: a.sem_pis.length },
    { id: "import", grave: false, titulo: plural(importando.length, "importação em andamento", "importações em andamento"), sub: "esperar o worker terminar para não fechar com marcação faltando", acao: "Ver importações", ir: () => onIr("importacoes"), n: importando.length },
    { id: "clt", grave: true, titulo: plural(duro, "violação de regra dura da CLT", "violações de regra dura da CLT"), sub: "interjornada, intrajornada e NSR — o painel de conformidade lista caso a caso", acao: "Conformidade", ir: () => onIr("conformidade"), n: duro },
  ];
  const abertos = bloqueios.filter((b) => b.n > 0);
  const graves = abertos.filter((b) => b.grave);
  const idxPasso = PASSOS.findIndex((p) => p.id === (estado === "aberto" ? "checagem" : estado));

  const totalMes = D.COLABORADORES.filter((c) => c.controla_ponto && !c.desligamento)
    .reduce((acc, c) => { const t = D.totaisEspelho(D.dias(mes, c.id)); acc.trab += t.trabalhado; acc.he += t.he_diurna + t.he_noturna; acc.falta += t.falta; acc.bh += t.bh_credito - t.bh_debito; return acc; }, { trab: 0, he: 0, falta: 0, bh: 0 });

  return (
    <>
      <div className="pt-toolbar" data-contract="fechamento-acoes">
        <div className="pt-fld"><label htmlFor="fc-mes">Competência</label>
          <select id="fc-mes" value={mes} onChange={(e) => setMes(e.target.value)}>
            {D.MESES.map((m) => <option key={m.key} value={m.key}>{m.extenso}</option>)}
          </select></div>
        <div className="pt-fld"><label>Situação</label>
          <div style={{ paddingTop: 4 }}>
            <Pill tom={estado === "fechado" ? "ok" : estado === "consolidado" ? "info" : "warn"}>
              {estado === "fechado" ? "Fechada" : estado === "consolidado" ? "Consolidada" : "Aberta"}
            </Pill>
          </div></div>
        <span className="pt-sp" />
        {estado === "aberto" && <>
          {graves.length > 0 &&
            <button className="pt-btn" title="Consolida registrando os bloqueios como exceção assinada"
              onClick={() => {
                const n = graves.reduce((s, b) => s + b.n, 0);
                if (!window.confirm("Consolidar " + comp.extenso + " COM " + n + " exceções?\n\nOs bloqueios ficam registrados no fechamento com o seu nome — caminho para quando o mês precisa fechar e a pendência será tratada depois.")) return;
                setExcecoes(n);
                mudar("consolidado", "Consolidada com " + n + " exceções registradas — constam no fechamento.", "warn");
              }}>Consolidar com exceções</button>}
          <button className="pt-btn primary" disabled={graves.length > 0}
            title={graves.length ? "Resolva os bloqueios graves — ou consolide com exceções" : "Consolida a apuração do mês"}
            onClick={() => { setExcecoes(0); mudar("consolidado", "Apuração consolidada — dias passam a CONSOLIDADO e o espelho vira base do fechamento.", "ok"); }}>
            <Ic name="check" />Consolidar apuração
          </button>
        </>}
        {estado === "consolidado" && <>
          <button className="pt-btn" onClick={() => mudar("aberto", "Consolidação revertida — a competência volta a aceitar ajuste.", "warn")}>Reabrir</button>
          <button className="pt-btn primary" onClick={() => { if (window.confirm("Fechar " + D.comp(mes).extenso + "? Depois disso a marcação só muda por anulação com trilha de auditoria.")) mudar("fechado", "Competência fechada — edição travada, só anulação com auditoria.", "ok"); }}>
            <Ic name="lock" />Fechar competência
          </button>
        </>}
        {estado === "fechado" &&
          <button className="pt-btn" onClick={() => onIr("relatorios")}><Ic name="download" />Gerar AFD / AEJ</button>}
      </div>

      <div className="pt-passos" data-contract="fechamento-passos">
        {PASSOS.map((p, i) => (
          <div key={p.id} className={"pt-passo" + (i < idxPasso ? " ok" : i === idxPasso ? " atual" : "")}>
            <span className="n">{i + 1}</span>
            <div><b>{p.label}</b><small>{i === 0 ? abertos.length + " itens abertos" : i === 1 ? "dias → CONSOLIDADO" : i === 2 ? "trava de edição" : "Portaria 671/2021"}</small></div>
          </div>
        ))}
      </div>

      {estado === "consolidado" && excecoes > 0 &&
        <Nota tom="warn" titulo={excecoes + " exceções registradas na consolidação"}>
          Assinadas por Wagner Ramos em 20/08/2026 — os bloqueios seguem listados abaixo e precisam ser tratados antes do próximo fechamento.
        </Nota>}
      {estado === "fechado" &&
        <Nota tom="ok" titulo={"Competência " + comp.extenso + " fechada"}>
          Marcação, apuração e banco de horas do mês estão travados. Correção agora é <b>anulação + nova marcação</b>, com NSR próprio e registro de auditoria — nunca edição da original.
        </Nota>}

      <div className="pt-cols-2">
        <Card contrato="fechamento-pre-checagem" icon="alert" titulo="Pré-checagem do fechamento" sub={abertos.length === 0 ? "nada bloqueia" : abertos.length + " itens abertos"}>
          <Tabela cols={[{ l: "Bloqueio" }, { l: "Grau", w: "92px" }, { l: "Ação", num: true, w: "142px" }]}>
            {abertos.length === 0 && <Vazio icon="check" colSpan={3}>Nada pendente — a competência pode consolidar.</Vazio>}
            {abertos.map((b) => (
              <tr key={b.id}>
                <td><b>{b.titulo}</b><small>{b.sub}</small></td>
                <td><Pill tom={b.grave ? "danger" : "warn"}>{b.grave ? "bloqueia" : "conferir"}</Pill></td>
                <td className="num"><button className="pt-btn" onClick={b.ir}>{b.acao}</button></td>
              </tr>
            ))}
          </Tabela>
        </Card>

        <Card contrato="fechamento-totais" icon="chart" titulo="Totais da competência" sub="quem controla ponto">
          <div className="pt-kpis" style={{ gridTemplateColumns: "1fr 1fr" }}>
            <Kpi label="Trabalhado" valor={D.fmtMin(totalMes.trab)} />
            <Kpi label="Hora extra" valor={D.fmtMin(totalMes.he)} tom="acc" />
            <Kpi label="Faltas" valor={D.fmtMin(totalMes.falta)} tom={totalMes.falta ? "neg" : ""} />
            <Kpi label="Banco de horas" valor={D.fmtMin(totalMes.bh)} tom={totalMes.bh >= 0 ? "ok" : "neg"} />
          </div>
          <div style={{ marginTop: 12 }}>
            <Nota tom="info">O fechamento não recalcula nada por conta própria: consolida o que a apuração já produziu. Reapurar é ação explícita no cabeçalho do módulo.</Nota>
          </div>
        </Card>
      </div>

      {divergentes.length > 0 &&
        <Card icon="calendar" titulo="Divergências por colaborador" sub="resolver antes de consolidar">
          <Tabela cols={[{ l: "Colaborador" }, { l: "Escala" }, { l: "Dias em divergência", num: true }, { l: "Ação", num: true, w: "112px" }]}>
            {divergentes.map((x) => (
              <tr key={x.c.id} className="hit" onClick={() => onVerColaborador(x.c.id)}>
                <td><b>{x.c.nome}</b><small>{x.c.matricula} · {x.c.cargo}</small></td>
                <td>{D.escala(x.c.escala_atual_id)?.nome || "—"}</td>
                <td className="num"><span className="pt-warnt">{x.n}</span></td>
                <td className="num" onClick={(e) => e.stopPropagation()}><button className="pt-btn" onClick={() => onVerColaborador(x.c.id)}>Ver espelho</button></td>
              </tr>
            ))}
          </Tabela>
        </Card>}
      <Legal>Fechamento não apaga histórico: a competência fechada guarda a apuração como estava, e qualquer correção posterior entra como novo lançamento (Portaria MTP 671/2021).</Legal>
    </>
  );
}

window.PontoFechamento = { Fechamento, Conformidade, achados };
})();
