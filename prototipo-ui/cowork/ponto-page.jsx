// ponto-page.jsx — módulo Ponto no app único (rota `ponto`). Import das telas Blade do main
// (Modules/Ponto/Resources/views): dashboard/index + espelho/index+show+imprimir vivem aqui;
// as outras oito telas em ponto-telas.jsx. Abas de ÁREA dentro da página (canon [W] 2026-06-22),
// shell ModuloPadrao, tokens do DS vivo. Expõe window.PontoPage.
(() => {
const { useState, useEffect, useMemo } = React;
const P = () => window.PONTO;
const U = () => window.PontoUI;

const ABAS = [
  { key: "painel", label: "Painel", icon: "chart" },
  { key: "espelho", label: "Espelho de Ponto", icon: "calendar" },
  { key: "aprovacoes", label: "Aprovações", icon: "check" },
  { key: "intercorrencias", label: "Intercorrências", icon: "alert" },
  { key: "banco-horas", label: "Banco de Horas", icon: "coins" },
  { key: "fechamento", label: "Fechamento", icon: "lock" },
  { key: "conformidade", label: "Conformidade", icon: "shield" },
  { key: "escalas", label: "Escalas", icon: "clock" },
  { key: "colaboradores", label: "Colaboradores", icon: "database" },
  { key: "mobile", label: "REP-P (celular)", icon: "send" },
  { key: "importacoes", label: "Importações", icon: "download" },
  { key: "relatorios", label: "Relatórios", icon: "receipt" },
  { key: "configuracoes", label: "Configurações", icon: "settings" },
];

// ═══════════════════════════ PAINEL (dashboard/index.blade.php) ═══════════════════════════
function Painel({ onIr, intercorrencias }) {
  const D = P();
  const { Card, Kpi, Tabela, Vazio, Nota, PillIntercorrencia, PillPrioridade, Legal } = U();
  const k = D.KPIS;
  const lista = intercorrencias || D.INTERCORRENCIAS;
  const pendentes = lista.filter((i) => i.estado === "PENDENTE");
  const urgentes = pendentes.filter((i) => i.prioridade === "URGENTE").length;
  const divergencias = D.COLABORADORES.filter((c) => c.controla_ponto).reduce((n, c) => n + D.totaisEspelho(D.dias(D.MES, c.id)).divergencias, 0);

  return (
    <>
      <Nota contrato="painel-nota-fechamento" tom={pendentes.length || divergencias ? "warn" : "ok"} titulo={"O que trava o fechamento de " + D.comp(D.MES).extenso.split("/")[0].toLowerCase()}>
        {pendentes.length === 0 && divergencias === 0
          ? <>Nenhuma intercorrência aguardando decisão e nenhum dia em divergência — a competência pode consolidar.</>
          : pendentes.length === 0
            ? <>Nenhuma intercorrência aguardando decisão, mas {divergencias} {divergencias === 1 ? "dia está" : "dias estão"} em <b>DIVERGENCIA</b> na apuração — o espelho não consolida assim, e o AFD gerado sai com a jornada errada.</>
            : <>{pendentes.length === 1 ? "Uma intercorrência espera" : pendentes.length + " intercorrências esperam"} decisão e {divergencias} {divergencias === 1 ? "dia está" : "dias estão"} em <b>DIVERGENCIA</b> na apuração. Enquanto isso, o espelho do mês não consolida — e o AFD gerado sai com a jornada errada.</>}
      </Nota>

      <div className="pt-kpis" data-contract="painel-kpis">
        <Kpi label="Colaboradores ativos" valor={k.colaboradores_ativos} ln="com controle de ponto" onClick={() => onIr("colaboradores")} />
        <Kpi label="Presentes agora" valor={k.presentes_agora} tom="ok" ln="última marcação 13:02" onClick={() => onIr("espelho")} />
        <Kpi label="Atrasos hoje" valor={k.atrasos_hoje} tom="warn" ln={"além da tolerância de " + D.CONFIG.clt.tolerancia_minutos_por_marcacao + " min"} onClick={() => onIr("espelho")} />
        <Kpi label="Faltas hoje" valor={k.faltas_hoje} tom="neg" ln="sem marcação e sem intercorrência" onClick={() => onIr("espelho")} />
        <Kpi label="HE do mês" valor={D.fmtMin(k.he_mes_minutos)} tom="acc" ln={"limite " + D.CONFIG.clt.limite_he_diaria_horas + "h/dia (Art. 59)"} onClick={() => onIr("banco-horas")} />
        <Kpi label="Aprovações pendentes" valor={pendentes.length} tom={pendentes.length ? "warn" : "ok"} ln={urgentes ? urgentes + (urgentes === 1 ? " urgente" : " urgentes") : "nada urgente"} onClick={() => onIr("aprovacoes")} />
      </div>

      <div className="pt-cols-2">
        <Card contrato="painel-fila-aprovacoes" icon="check" titulo="Fila de aprovações" sub={"(" + pendentes.length + " pendentes)"}
          acao={<button className="pt-btn" onClick={() => onIr("aprovacoes")}>Ver fila completa</button>}>
          <Tabela cols={[{ l: "Colaborador" }, { l: "Tipo" }, { l: "Data / intervalo" }, { l: "Estado" }, { l: "Prioridade" }]}>
            {pendentes.length === 0 && <Vazio icon="check" colSpan={5}>Nenhuma intercorrência aguardando decisão.</Vazio>}
            {pendentes.map((a) => {
              const c = D.colab(a.colaborador_config_id);
              return (
                <tr key={a.id} className="hit" onClick={() => onIr("aprovacoes")}>
                  <td><b>{c.nome}</b><small>{c.matricula} · {c.cargo}</small></td>
                  <td>{D.TIPOS_INTERC[a.tipo]}</td>
                  <td><span className="mono">{a.data}</span><small>{a.dia_todo ? "Dia todo" : a.intervalo_inicio ? a.intervalo_inicio + " – " + a.intervalo_fim : "—"}</small></td>
                  <td><PillIntercorrencia estado={a.estado} /></td>
                  <td><PillPrioridade p={a.prioridade} /></td>
                </tr>
              );
            })}
          </Tabela>
        </Card>

        <Card contrato="painel-atividade" icon="clock" titulo="Atividade recente" sub="marcações de hoje">
          <div className="pt-feed">
            {D.ATIVIDADE.map((m, i) => (
              <div className="pt-feed-it" key={i}>
                <b>{m.nome}</b>
                <time>{m.hora}</time>
                <span>{D.TIPOS_MARCACAO[m.tipo] || m.tipo} · NSR {m.nsr} · {D.ORIGENS_MARCACAO[m.origem] || m.origem}</span>
              </div>
            ))}
          </div>
        </Card>
      </div>
      <Legal />
    </>
  );
}

// ═══════════════════════════ ESPELHO — lista (espelho/index.blade.php) ═══════════════════════════
function EspelhoLista({ mes, setMes, onAbrir }) {
  const D = P();
  const { Card, Tabela, Vazio, PillSimNao, usePagina, Pager, Ic } = U();
  const [escala, setEscala] = useState("");
  const [soDiverg, setSoDiverg] = useState(false);
  const [q, setQ] = useState("");
  const busca = q.trim().toLowerCase();
  const base = D.COLABORADORES.filter((c) => !c.desligamento);
  const lista = base.filter((c) => {
    if (escala && String(c.escala_atual_id) !== escala) return false;
    if (busca && !(c.nome + " " + c.matricula).toLowerCase().includes(busca)) return false;
    if (soDiverg && D.totaisEspelho(D.dias(mes, c.id)).divergencias === 0) return false;
    return true;
  });
  const pg = usePagina(lista.length, 15);
  const totalDiverg = base.reduce((n, c) => n + D.totaisEspelho(D.dias(mes, c.id)).divergencias, 0);
  return (
    <>
      <div className="pt-toolbar">
        <div className="pt-fld"><label htmlFor="es-mes">Mês de referência</label>
          <select id="es-mes" value={mes} onChange={(e) => setMes(e.target.value)}>
            {D.MESES.map((m) => <option key={m.key} value={m.key}>{m.extenso}</option>)}
          </select></div>
        <div className="pt-fld"><label htmlFor="es-esc">Escala</label>
          <select id="es-esc" value={escala} onChange={(e) => setEscala(e.target.value)}>
            <option value="">Todas</option>
            {D.ESCALAS.map((e) => <option key={e.id} value={e.id}>{e.nome}</option>)}
          </select></div>
        <div className="pt-fld wide"><label htmlFor="es-q">Buscar</label>
          <input id="es-q" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nome ou matrícula" /></div>
        <label className="pt-check"><input type="checkbox" checked={soDiverg} onChange={(e) => setSoDiverg(e.target.checked)} />Só com divergência</label>
        {(escala || busca || soDiverg) && <button className="pt-btn" onClick={() => { setEscala(""); setQ(""); setSoDiverg(false); }}>Limpar</button>}
        <span className="pt-sp" />
        <span style={{ fontSize: 12, color: "var(--text-dim)" }}>{totalDiverg} dias em divergência na competência</span>
      </div>
      <Card icon="database" titulo="Colaboradores" sub={"(" + lista.length + " de " + base.length + " ativos)"}>
        <Tabela cols={[{ l: "Matrícula", w: "92px" }, { l: "Colaborador" }, { l: "Escala" }, { l: "Trabalhado", num: true }, { l: "HE", num: true }, { l: "Saldo BH", num: true }, { l: "Controla ponto" }, { l: "Divergências", num: true }, { l: "Ação", num: true, w: "120px" }]}>
          {lista.length === 0 && <Vazio colSpan={9}>Nenhum colaborador com esse filtro nesta competência.</Vazio>}
          {pg.fatia(lista).map((c) => {
            const t = D.totaisEspelho(D.dias(mes, c.id));
            const bh = (D.BH_SALDOS.find((s) => s.colaborador_config_id === c.id) || {}).saldo_minutos;
            return (
              <tr key={c.id} className={c.controla_ponto ? "hit" : ""} onClick={() => c.controla_ponto && onAbrir(c.id)}>
                <td className="mono">{c.matricula || "—"}</td>
                <td><b>{c.nome}</b><small>{c.cargo} · {c.email || "sem e-mail"}</small></td>
                <td>{D.escala(c.escala_atual_id)?.nome || <span className="pt-dim">—</span>}</td>
                <td className="num">{c.controla_ponto ? D.fmtMin(t.trabalhado) : <span className="pt-dim">—</span>}</td>
                <td className="num">{c.controla_ponto && t.he_diurna + t.he_noturna ? <span className="pt-acct">{D.fmtMin(t.he_diurna + t.he_noturna)}</span> : <span className="pt-dim">—</span>}</td>
                <td className="num">{c.controla_ponto && bh != null ? <span className={bh > 0 ? "pt-pos" : bh < 0 ? "pt-neg" : "pt-dim"}>{D.fmtMin(bh)}</span> : <span className="pt-dim">—</span>}</td>
                <td><PillSimNao v={c.controla_ponto} /></td>
                <td className="num">{c.controla_ponto ? (t.divergencias ? <span className="pt-warnt">{t.divergencias}</span> : <span className="pt-dim">0</span>) : <span className="pt-dim">—</span>}</td>
                <td className="num" onClick={(e) => e.stopPropagation()}>
                  {c.controla_ponto
                    ? <button className="pt-btn primary" onClick={() => onAbrir(c.id)}>Ver espelho</button>
                    : <span className="pt-dim">—</span>}
                </td>
              </tr>
            );
          })}
        </Tabela>
        <Pager p={pg} rotulo="colaboradores" />
      </Card>
    </>
  );
}

// ═══════ Grade do mês: falta/divergência/HE de relance (o que a tabela não dá) ═══════
function GradeMes({ dias, mes, onDia }) {
  const D = P();
  const comp = D.comp(mes);
  const primeiroDow = new Date(comp.ano, comp.mesNum - 1, 1).getDay();
  const celulas = [];
  for (let i = 0; i < primeiroDow; i++) celulas.push(null);
  dias.forEach((d) => celulas.push(d));
  while (celulas.length % 7 !== 0) celulas.push(null);
  return (
    <>
      <div className="pt-cal">
        {D.DIA_SEMANA.map((s) => <div className="pt-cal-dow" key={s}>{s}</div>)}
        {celulas.map((d, i) => {
          if (!d) return <div className="pt-cal-cell vazio" key={"v" + i} />;
          const he = d.he_diurna + d.he_noturna;
          const cls = d.folga ? "folga" : d.falta ? "falta" : d.estado === "DIVERGENCIA" ? "diverg" : "";
          const Cell = d.folga ? "div" : "button";
          return (
            <Cell className={"pt-cal-cell " + cls} key={d.dia} type={d.folga ? undefined : "button"}
              onClick={d.folga ? undefined : () => onDia(d.dia)}
              title={d.folga ? "Folga" : "Ver marcações do dia " + d.dia}>
              <span className="pt-cal-d"><b>{String(d.dia).padStart(2, "0")}</b><span>{D.DIA_SEMANA[d.dow]}</span></span>
              {d.folga
                ? <span className="pt-cal-h">folga</span>
                : <>
                    <span className="pt-cal-h">{d.realizada_entrada ? d.realizada_entrada + "–" + (d.realizada_saida || "??") : "sem marcação"}</span>
                    <span className="pt-cal-tags">
                      {d.falta > 0 && <span className="pt-cal-tag flt">falta</span>}
                      {d.estado === "DIVERGENCIA" && d.falta === 0 && <span className="pt-cal-tag div">diverg.</span>}
                      {d.atraso > 0 && <span className="pt-cal-tag atr">+{d.atraso}min</span>}
                      {he > 0 && <span className="pt-cal-tag he">HE {D.fmtMin(he)}</span>}
                    </span>
                    <span className="pt-cal-t">{D.fmtMin(d.trabalhado)}</span>
                  </>}
            </Cell>
          );
        })}
      </div>
      <div className="pt-cal-legend">
        <span><i style={{ background: "color-mix(in oklch,var(--neg) 18%,transparent)", borderColor: "color-mix(in oklch,var(--neg) 40%,transparent)" }} />falta</span>
        <span><i style={{ background: "color-mix(in oklch,var(--warn) 18%,transparent)", borderColor: "color-mix(in oklch,var(--warn) 40%,transparent)" }} />divergência</span>
        <span><i style={{ background: "var(--bg-2)", borderColor: "var(--border)" }} />folga / fim de semana</span>
        <span>clique no dia para as marcações (NSR, origem, hash)</span>
      </div>
    </>
  );
}

// ═══════ Drawer do dia: marcações com NSR/origem/hash + anulação (append-only) ═══════
function DiaDrawer({ dia, colab, mes, onClose, onAnular }) {
  const D = P();
  const ds = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Tabela, Vazio, Nota, PillApuracao, Pill } = U();
  if (!dia || !ds.Drawer) return null;
  const Sec = ds.DrawerSection || (({ title, children }) => <div style={{ padding: "12px 16px" }}>{title && <div style={{ fontSize: 10, textTransform: "uppercase", letterSpacing: ".06em", color: "var(--text-mute)", marginBottom: 6 }}>{title}</div>}{children}</div>);
  const comp = D.comp(mes);
  const fechado = dia.estado === "FECHADO";
  const data = String(dia.dia).padStart(2, "0") + "/" + String(comp.mesNum).padStart(2, "0") + "/" + comp.ano;
  return (
    <ds.Drawer open={!!dia} onClose={onClose} width={640} title={"Dia " + data} subtitle={colab.nome + " · " + D.DIA_SEMANA[dia.dow] + " · " + (dia.folga ? "folga" : dia.prevista_entrada + "–" + dia.prevista_saida)}>
      <Sec title="Apuração do dia">
        <div className="pt-ficha">
          <div>
            <p><b>Estado:</b> <PillApuracao estado={dia.estado} /></p>
            <p><b>Previsto:</b> {dia.prevista_entrada ? dia.prevista_entrada + "–" + dia.prevista_saida : "—"}</p>
            <p><b>Realizado:</b> {dia.realizada_entrada ? dia.realizada_entrada + "–" + (dia.realizada_saida || "??") : "—"}</p>
          </div>
          <div>
            <p><b>Trabalhado:</b> {D.fmtMin(dia.trabalhado)}</p>
            <p><b>Atraso:</b> {D.fmtMin(dia.atraso)}</p>
            <p><b>Falta:</b> {D.fmtMin(dia.falta)}</p>
          </div>
          <div>
            <p><b>HE diurna:</b> {D.fmtMin(dia.he_diurna)}</p>
            <p><b>HE noturna:</b> {D.fmtMin(dia.he_noturna)}</p>
            <p><b>Adicional noturno:</b> {D.fmtMin(dia.adicional_noturno)}</p>
          </div>
          <div>
            <p><b>BH crédito:</b> {D.fmtMin(dia.bh_credito)}</p>
            <p><b>BH débito:</b> {D.fmtMin(dia.bh_debito)}</p>
            <p><b>Marcações:</b> {dia.marcacoes.length}{dia.marcacoes.length % 2 ? " (ímpar)" : ""}</p>
          </div>
        </div>
      </Sec>
      <Sec title="Marcações do dia (append-only)">
        <Tabela cols={[{ l: "Hora", w: "64px" }, { l: "NSR", w: "82px" }, { l: "Origem" }, { l: "REP" }, { l: "Hash", w: "120px" }, { l: "Ação", num: true, w: "84px" }]}>
          {dia.marcacoes.length === 0 && <Vazio icon="alert" colSpan={6}>Nenhuma marcação neste dia — falta sem justificativa aplicada.</Vazio>}
          {dia.marcacoes.map((m, i) => (
            <tr key={i} className={m.anulada ? "folga" : ""}>
              <td className="mono"><b>{m.hora}</b></td>
              <td className="mono">{m.nsr}</td>
              <td>{D.ORIGENS_MARCACAO[m.origem] || m.origem}</td>
              <td className="mono">{m.rep || <span className="pt-dim">—</span>}</td>
              <td><span className="pt-hash">{m.hash.slice(0, 16)}…</span></td>
              <td className="num">
                {m.anulada || m.origem === "ANULACAO"
                  ? <Pill tom="danger">{m.anulada ? "anulada" : "anulação"}</Pill>
                  : <button className="pt-btn danger" disabled={fechado} title={fechado ? "Competência fechada" : "Anula esta marcação com registro de contrapartida"}
                      onClick={() => { if (window.confirm("Anular a marcação " + m.hora + " (NSR " + m.nsr + ")? A original permanece; entra um registro de anulação.")) onAnular(dia.dia, i); }}>Anular</button>}
              </td>
            </tr>
          ))}
        </Tabela>
      </Sec>
      <Sec title="Regra">
        <Nota tom="warn">A marcação nunca é editada nem apagada: passada a janela de {D.CONFIG.marcacao.janela_correcao_minutos} min, a correção é <b>anulação + nova marcação</b>, ambas com NSR próprio e hash <span className="mono">{D.CONFIG.marcacao.hash_algoritmo}</span> (Portaria MTP 671/2021).</Nota>
      </Sec>
    </ds.Drawer>
  );
}

// ═══════ Folha de impressão (reports/espelho-pdf.blade.php) — visível só no print ═══════
function FolhaEspelho({ colab, mes, dias, t }) {
  const D = P();
  const comp = D.comp(mes);
  const esc = D.escala(colab.escala_atual_id);
  const mm = String(comp.mesNum).padStart(2, "0");
  const dsr = Math.round((t.he_diurna + t.he_noturna) * (D.CONFIG.clt.adicional_dsr_percentual / 100) / 6);
  return (
    <div className="pt-folha" data-contract="espelho-folha-impressao" aria-hidden="true">
      <h1>Espelho de Ponto Eletrônico</h1>
      <table className="cab"><tbody>
        <tr><td className="lbl">Colaborador:</td><td>{colab.nome}</td><td className="lbl">Matrícula:</td><td>{colab.matricula || "—"}</td></tr>
        <tr><td className="lbl">CPF:</td><td>{colab.cpf || "—"}</td><td className="lbl">PIS:</td><td>{colab.pis || "—"}</td></tr>
        <tr><td className="lbl">Escala:</td><td>{esc ? esc.nome : "—"}</td><td className="lbl">Competência:</td><td>{comp.extenso} (01/{mm}/{comp.ano} a {comp.ate}/{mm}/{comp.ano})</td></tr>
      </tbody></table>
      <h2>Apuração diária</h2>
      <table className="dados">
        <thead><tr>
          <th>Data</th><th>Dia</th><th>Prev. Ent.</th><th>Prev. Saí.</th><th>Real. Ent.</th><th>Real. Saí.</th><th>Marcações</th>
          <th className="num">Trab.</th><th className="num">Atraso</th><th className="num">Falta</th><th className="num">HE Diu.</th><th className="num">HE Not.</th><th className="num">BH +</th><th className="num">BH −</th><th>Estado</th>
        </tr></thead>
        <tbody>
          {dias.map((d) => (
            <tr key={d.dia} className={d.estado === "DIVERGENCIA" ? "diverg" : ""}>
              <td>{String(d.dia).padStart(2, "0")}/{mm}</td>
              <td>{D.DIA_SEMANA[d.dow]}</td>
              <td>{d.prevista_entrada || "—"}</td>
              <td>{d.prevista_saida || "—"}</td>
              <td>{d.realizada_entrada || "—"}</td>
              <td>{d.realizada_saida || "—"}</td>
              <td>{d.marcacoes.length ? d.marcacoes.map((m, i) => <span className="chip" key={i}>{m.hora}</span>) : "—"}</td>
              <td className="num">{D.fmtMin(d.trabalhado)}</td>
              <td className="num">{d.atraso ? D.fmtMin(d.atraso) : "—"}</td>
              <td className="num">{d.falta ? D.fmtMin(d.falta) : "—"}</td>
              <td className="num">{d.he_diurna ? D.fmtMin(d.he_diurna) : "—"}</td>
              <td className="num">{d.he_noturna ? D.fmtMin(d.he_noturna) : "—"}</td>
              <td className="num">{d.bh_credito ? D.fmtMin(d.bh_credito) : "—"}</td>
              <td className="num">{d.bh_debito ? D.fmtMin(d.bh_debito) : "—"}</td>
              <td>{d.folga ? "—" : d.estado}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <h2>Totais do mês</h2>
      <table className="totais"><tbody>
        <tr><td>Trabalhado</td><td className="num">{D.fmtMin(t.trabalhado)}</td><td>Atrasos</td><td className="num">{D.fmtMin(t.atraso)}</td></tr>
        <tr><td>Faltas</td><td className="num">{D.fmtMin(t.falta)}</td><td>Adicional noturno</td><td className="num">{D.fmtMin(t.adicional_noturno)}</td></tr>
        <tr><td>HE diurna</td><td className="num">{D.fmtMin(t.he_diurna)}</td><td>HE noturna</td><td className="num">{D.fmtMin(t.he_noturna)}</td></tr>
        <tr><td>Banco de horas — crédito</td><td className="num">{D.fmtMin(t.bh_credito)}</td><td>Banco de horas — débito</td><td className="num">{D.fmtMin(t.bh_debito)}</td></tr>
        <tr><td>DSR (repercussão sobre HE)</td><td className="num">{D.fmtMin(dsr)}</td><td /><td /></tr>
      </tbody></table>
      <table className="assin"><tbody><tr><td>Colaborador</td><td>Responsável RH</td></tr></tbody></table>
      <div className="rodape">Gerado em 20/08/2026 09:18 — Portaria MTP 671/2021 Art. 85. Dados imutáveis em marcações (append-only); divergências destacadas em amarelo.</div>
    </div>
  );
}

// ═══════════════════════════ ESPELHO — detalhe (espelho/show.blade.php) ═══════════════════════════
function EspelhoShow({ colabId, mes, setMes, onVoltar, avisar }) {
  const D = P();
  const { Card, Kpi, Tabela, Nota, Legal, PillApuracao, Voltar, Ic } = U();
  const c = D.colab(colabId);
  const esc = D.escala(c.escala_atual_id);
  const comp = D.comp(mes);
  const [dias, setDias] = useState(() => D.dias(mes, colabId));
  const [diaFoco, setDiaFoco] = useState(null);
  const [modo, setModo] = useState(() => { try { return localStorage.getItem("oimpresso.ponto.espelho.modo") || "tabela"; } catch (e) { return "tabela"; } });
  useEffect(() => { try { localStorage.setItem("oimpresso.ponto.espelho.modo", modo); } catch (e) {} }, [modo]);
  useEffect(() => { setDias(D.dias(mes, colabId)); setDiaFoco(null); }, [mes, colabId]);
  const t = D.totaisEspelho(dias);

  const idx = D.MESES.findIndex((m) => m.key === mes);
  const anterior = D.MESES[idx - 1], proximo = D.MESES[idx + 1];

  const anular = (numDia, i) => {
    setDias((ds) => ds.map((d) => {
      if (d.dia !== numDia) return d;
      const ms = d.marcacoes.map((m, k) => k === i ? { ...m, anulada: true } : m);
      const orig = d.marcacoes[i];
      return { ...d, estado: "DIVERGENCIA",
        marcacoes: [...ms, { hora: orig.hora, origem: "ANULACAO", nsr: orig.nsr + 900000, hash: orig.hash.slice(0, 38) + "aa", rep: orig.rep, ref: orig.nsr }] };
    }));
    avisar("Marcação anulada — registro de anulação gravado, a original permanece.", "warn");
  };
  const diaAberto = diaFoco != null ? dias.find((d) => d.dia === diaFoco) : null;

  return (
    <>
      <div className="pt-sub">
        <Voltar onClick={onVoltar}>Voltar à lista</Voltar>
        <div className="pt-group">
          <button className="pt-btn" disabled={!anterior} onClick={() => anterior && setMes(anterior.key)}>{anterior ? anterior.extenso : "Mês anterior"}</button>
          <button className="pt-btn" disabled={!proximo} onClick={() => proximo && setMes(proximo.key)}>{proximo ? proximo.extenso : "Próximo mês"}</button>
        </div>
        <div><h2>{c.nome}</h2><span className="pt-sub-sub">{comp.extenso} · matrícula {c.matricula} · {c.cargo}</span></div>
        <span className="pt-sp" />
        <button className="pt-btn" onClick={() => { avisar("Abrindo a folha do espelho para impressão."); setTimeout(() => window.print(), 120); }}><Ic name="receipt" />Imprimir PDF</button>
      </div>

      <Card contrato="espelho-dados-colaborador" icon="database" titulo="Dados do colaborador">
        <div className="pt-ficha">
          <div>
            <p><b>Nome:</b> {c.nome}</p>
            <p><b>Matrícula:</b> {c.matricula || "—"}</p>
            <p><b>E-mail:</b> {c.email || "—"}</p>
          </div>
          <div>
            <p><b>CPF:</b> <span className="mono">{c.cpf || "—"}</span></p>
            <p><b>PIS:</b> <span className="mono">{c.pis || "—"}</span></p>
            <p><b>Controla ponto:</b> {c.controla_ponto ? "Sim" : "Não"}</p>
          </div>
          <div>
            <p><b>Escala atual:</b> {esc ? esc.nome : "—"}</p>
            <p><b>Carga diária:</b> {esc ? D.fmtMin(esc.carga_diaria_minutos) : "—"}</p>
            <p><b>Banco de horas:</b> {c.usa_banco_horas ? "Sim" : "Não"}</p>
          </div>
          <div>
            <p><b>Admissão:</b> {c.admissao}</p>
            <p><b>Desligamento:</b> {c.desligamento || "—"}</p>
            <p><b>Jornada prevista:</b> {esc ? esc.turnos[0].entrada + "–" + esc.turnos[0].saida : "—"}</p>
          </div>
        </div>
      </Card>

      <div className="pt-kpis" data-contract="espelho-totais">
        <Kpi label="Trabalhado" valor={D.fmtMin(t.trabalhado)} ln="realizado no mês" />
        <Kpi label="Atraso" valor={D.fmtMin(t.atraso)} tom={t.atraso ? "warn" : ""} ln={"tolerância " + D.CONFIG.clt.tolerancia_maxima_diaria_minutos + " min/dia"} />
        <Kpi label="Falta" valor={D.fmtMin(t.falta)} tom={t.falta ? "neg" : ""} ln="sem justificativa aplicada" />
        <Kpi label="Hora extra" valor={D.fmtMin(t.he_diurna + t.he_noturna)} tom="acc" ln={"diurna " + D.fmtMin(t.he_diurna) + " · noturna " + D.fmtMin(t.he_noturna)} />
        <Kpi label="Banco hrs (+)" valor={D.fmtMin(t.bh_credito)} tom="ok" ln="crédito do mês" />
        <Kpi label="Banco hrs (−)" valor={D.fmtMin(t.bh_debito)} tom="neg" ln="débito do mês" />
      </div>

      {t.divergencias > 0 &&
        <Nota tom="warn">
          <b>{t.divergencias}</b> {t.divergencias === 1 ? "dia apresenta divergência" : "dias apresentam divergência"} no mês — marcação ímpar ou jornada fora da escala. Clique na linha para ver as marcações do dia (NSR, origem, hash) antes de consolidar.
        </Nota>}

      <Card contrato="espelho-apuracao-diaria" icon="calendar" titulo={"Apuração diária — " + comp.extenso} sub={dias.length + " dias apurados"}
        acao={<span className="pt-seg" data-contract="espelho-modo-visao">
          <button className={modo === "tabela" ? "on" : ""} onClick={() => setModo("tabela")}>Tabela</button>
          <button className={modo === "grade" ? "on" : ""} onClick={() => setModo("grade")}>Grade do mês</button>
        </span>}>
        {modo === "grade" ? <GradeMes dias={dias} mes={mes} onDia={setDiaFoco} /> :
        <Tabela cols={[{ l: "Data", w: "78px" }, { l: "Previsto", w: "104px" }, { l: "Realizado", w: "104px" }, { l: "Marcações" }, { l: "Atraso", num: true }, { l: "HE", num: true }, { l: "BH (+/−)", num: true }, { l: "Estado", w: "120px" }]}>
          {dias.map((d) => {
            const he = d.he_diurna + d.he_noturna;
            const bh = d.bh_credito - d.bh_debito;
            return (
              <tr key={d.dia} className={(d.estado === "DIVERGENCIA" ? "diverg" : d.folga ? "folga" : "") + (d.folga ? "" : " hit")}
                onClick={() => !d.folga && setDiaFoco(d.dia)}>
                <td><b className="mono">{String(d.dia).padStart(2, "0")}/{String(comp.mesNum).padStart(2, "0")}</b><small>{D.DIA_SEMANA[d.dow]}</small></td>
                <td className="mono">{d.prevista_entrada ? d.prevista_entrada + "–" + d.prevista_saida : <span className="pt-dim">folga</span>}</td>
                <td className="mono">{d.realizada_entrada ? d.realizada_entrada + "–" + (d.realizada_saida || "??") : <span className="pt-dim">—</span>}</td>
                <td>
                  {d.marcacoes.length > 0 ? <>
                    {d.marcacoes.map((m, i) => <span key={i} className={"pt-hora" + (m.origem === "MANUAL" || m.origem === "ANULACAO" ? " manual" : "")} title={"NSR " + m.nsr + " · " + (D.ORIGENS_MARCACAO[m.origem] || m.origem)}>{m.hora}</span>)}
                    <small>{d.marcacoes.length} marcação(ões){d.marcacoes.length % 2 ? " · ímpar" : ""}</small>
                  </> : <span className="pt-dim">—</span>}
                </td>
                <td className="num">{d.atraso ? <span className="pt-warnt">{D.fmtMin(d.atraso)}</span> : <span className="pt-dim">—</span>}</td>
                <td className="num">{he ? <span className="pt-acct">{D.fmtMin(he)}</span> : <span className="pt-dim">—</span>}</td>
                <td className="num">{bh ? <span className={bh > 0 ? "pt-pos" : "pt-neg"}>{bh > 0 ? "+" : ""}{D.fmtMin(bh)}</span> : <span className="pt-dim">—</span>}</td>
                <td>{d.folga ? <span className="pt-dim">—</span> : <PillApuracao estado={d.estado} />}</td>
              </tr>
            );
          })}
        </Tabela>}
      </Card>
      <Legal />
      <DiaDrawer dia={diaAberto} colab={c} mes={mes} onClose={() => setDiaFoco(null)} onAnular={anular} />
      <FolhaEspelho colab={c} mes={mes} dias={dias} t={t} />
    </>
  );
}

// ═══════════════════════════ SHELL DO MÓDULO ═══════════════════════════
function PontoPage({ view }) {
  const MP = window.ModuloPadrao || {};
  const D = P();
  const T = window.PontoTelas || {};
  const F = window.PontoFechamento || {};
  const M = window.PontoMobile || {};
  const [aba, setAba] = (MP.useAba || ((k, i) => useState(i)))("oimpresso.ponto.aba", view || "painel");
  const [avisoNode, avisar] = (MP.useAviso || (() => [null, () => {}]))();
  const [mes, setMes] = useState(D.MES);
  const [espelhoDe, setEspelhoDe] = useState(null);
  const [intercFoco, setIntercFoco] = useState(null);
  // Estado das intercorrências vive no SHELL: aprovar em Aprovações tem que apagar o bloqueio
  // no Fechamento e o badge da aba — antes cada aba tinha a sua cópia local.
  const [intercs, setIntercs] = useState(D.INTERCORRENCIAS);
  const [hora, setHora] = useState("09:18");

  useEffect(() => { if (view) setAba(view); }, [view]);

  const pendentes = intercs.filter((i) => i.estado === "PENDENTE").length;
  const nConf = F.achados ? Object.values(F.achados(mes)).reduce((n, l) => n + l.length, 0) : null;
  const abas = ABAS.map((a) => a.key === "aprovacoes" ? { ...a, n: pendentes }
    : a.key === "conformidade" ? { ...a, n: nConf }
    : a.key === "intercorrencias" ? { ...a, n: intercs.length }
    : a.key === "colaboradores" ? { ...a, n: D.COLABORADORES.length } : a);

  const irPara = (k) => { setAba(k); setEspelhoDe(null); };
  const abrirEspelho = (id) => { setEspelhoDe(id); setAba("espelho"); };

  let corpo = null;
  if (aba === "painel") corpo = <Painel onIr={irPara} intercorrencias={intercs} />;
  else if (aba === "espelho") corpo = espelhoDe
    ? <EspelhoShow colabId={espelhoDe} mes={mes} setMes={setMes} onVoltar={() => setEspelhoDe(null)} avisar={avisar} />
    : <EspelhoLista mes={mes} setMes={setMes} onAbrir={setEspelhoDe} />;
  else if (aba === "aprovacoes" && T.Aprovacoes) corpo = <T.Aprovacoes avisar={avisar} rows={intercs} setRows={setIntercs} onVerIntercorrencia={(id) => { setIntercFoco(id); setAba("intercorrencias"); }} />;
  else if (aba === "intercorrencias" && T.Intercorrencias) corpo = <T.Intercorrencias avisar={avisar} rows={intercs} setRows={setIntercs} foco={intercFoco} onFoco={setIntercFoco} />;
  else if (aba === "banco-horas" && T.BancoHoras) corpo = <T.BancoHoras avisar={avisar} />;
  else if (aba === "fechamento" && F.Fechamento) corpo = <F.Fechamento mes={mes} setMes={setMes} avisar={avisar} onIr={irPara} onVerColaborador={abrirEspelho} intercorrencias={intercs} />;
  else if (aba === "conformidade" && F.Conformidade) corpo = <F.Conformidade mes={mes} onVerColaborador={abrirEspelho} />;
  else if (aba === "escalas" && T.Escalas) corpo = <T.Escalas avisar={avisar} />;
  else if (aba === "colaboradores" && T.Colaboradores) corpo = <T.Colaboradores avisar={avisar} onVerEspelho={abrirEspelho} />;
  else if (aba === "mobile" && M.Mobile) corpo = <M.Mobile avisar={avisar} rows={intercs} setRows={setIntercs} />;
  else if (aba === "importacoes" && T.Importacoes) corpo = <T.Importacoes avisar={avisar} />;
  else if (aba === "relatorios" && T.Relatorios) corpo = <T.Relatorios avisar={avisar} />;
  else if (aba === "configuracoes" && T.Configuracoes) corpo = <T.Configuracoes avisar={avisar} />;

  return (
    <div className="ponto-root mp-page" data-screen-label="01 Ponto">
      {MP.Header &&
        <MP.Header modulo="Ponto" papel="Ponto eletrônico · Portaria MTP 671/2021"
          contexto={["ROTA LIVRE", "matriz", D.comp(mes).extenso, D.KPIS.colaboradores_ativos + " colaboradores no ponto"]}
          atualizadoAs={hora}
          onRefresh={() => { setHora(new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })); avisar("Reapurado agora — marcações, apuração do dia e saldos.", "ok"); }}
          glyph={<window.JcIcon name="clock" />}
          acoes={<>
            <button className="pt-btn" onClick={() => irPara("fechamento")}><window.JcIcon name="lock" className="ic" />Fechamento</button>
            <button className="pt-btn" onClick={() => irPara("importacoes")}><window.JcIcon name="download" className="ic" />Importar AFD</button>
            <button className="pt-btn primary" onClick={() => irPara("intercorrencias")}><window.JcIcon name="plus" className="ic" />Nova intercorrência</button>
          </>} />}
      {MP.Tabs && <MP.Tabs tab={aba} onTab={irPara} aria="Telas do Ponto" tabs={abas} />}
      <div className="pt-body">{corpo}</div>
      {avisoNode}
    </div>
  );
}

window.PontoPage = PontoPage;
})();
