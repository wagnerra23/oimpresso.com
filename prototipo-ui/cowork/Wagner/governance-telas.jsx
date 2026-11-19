// governance-telas.jsx — vistas de auditoria e drift de escopo do Governança.
// Espelha AuditController (mcp_audit_log, teto de 200, 4 filtros) e DriftAlertsController (scan de
// SCOPE.md × filesystem), lidos no main. A vista "Notas dos módulos" saiu (G-NOTAS, [W] 2026-09-24;
// rubrica aposentada pela ADR 0399) e o código dela também.
// Carrega junto de governance-page.jsx. Expõe window.GovernanceTelas.
(() => {
const { useState, useMemo, useEffect } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const FB = {
  Kpi: ({ l, v, sub, unit }) => <div className="gov-kpi-fb"><span className="gov-kpi-fb-v tabular">{v}{unit || ""}</span><span className="gov-kpi-fb-l">{l}</span>{sub && <small>{sub}</small>}</div>,
  Nota: ({ title, children }) => <div className="gov-nota-fb"><b>{title}</b><p>{children}</p></div>,
  Vazio: ({ title, description, action }) => <div className="gov-vazio-fb"><b>{title}</b><p>{description}</p>{action}</div>,
};
const ADS = () => { const a = window.AcessosDS || {}; return { Kpi: a.Kpi || FB.Kpi, Nota: a.Nota || FB.Nota, Vazio: a.Vazio || FB.Vazio }; };
const D = () => window.GovernanceData || {};

function Selo({ tone = "mute", children }) { return <span className={`gov-selo ${tone}`}>{children}</span>; }

function Secao({ titulo, sub, direita, contrato, children }) {
  return (
    <section className="gov-sec" data-contract={contrato}>
      <header className="gov-sec-h">
        <div><h2>{titulo}</h2>{sub && <p>{sub}</p>}</div>
        {direita}
      </header>
      {children}
    </section>
  );
}

function Esqueleto({ variant = "row", count = 4 }) {
  const { Skeleton } = ds();
  if (!Skeleton) return <div className="gov-skel" aria-hidden="true">{Array.from({ length: count }, (_, i) => <i key={i}></i>)}</div>;
  return <Skeleton variant={variant} count={count} />;
}

// ───────────────────────── Auditoria ─────────────────────────

const PERIODOS_AUD = [{ id: "1h", l: "1 h", horas: 1 }, { id: "24h", l: "24 h", horas: 24 }, { id: "7d", l: "7 dias", horas: 168 }, { id: "30d", l: "30 dias", horas: 720 }];
const TETO = 200;
const AGORA = new Date(2026, 7, 23, 19, 0, 0);

function AuditoriaView() {
  const A = ADS();
  const { RES_LABEL, ENDPOINTS, ATORES, auditoria } = D();
  const todos = useMemo(() => auditoria(), [auditoria]);
  const [periodo, setPeriodo] = useState("24h");
  const [ator, setAtor] = useState("");
  const [endpoint, setEndpoint] = useState("");
  const [res, setRes] = useState("");

  const filtrado = useMemo(() => {
    const horas = (PERIODOS_AUD.find((p) => p.id === periodo) || PERIODOS_AUD[1]).horas;
    const corte = new Date(AGORA.getTime() - horas * 3600 * 1000);
    return todos.filter((e) => e.ts >= corte
      && (!ator || e.ator === ator)
      && (!endpoint || e.ep === endpoint)
      && (!res || e.res === res));
  }, [todos, periodo, ator, endpoint, res]);

  const amostra = filtrado.slice(0, TETO);
  const erros = amostra.filter((e) => e.res === "error").length;
  const atoresDistintos = new Set(amostra.map((e) => e.ator)).size;
  const limpo = () => { setPeriodo("24h"); setAtor(""); setEndpoint(""); setRes(""); };
  const temFiltro = ator || endpoint || res || periodo !== "24h";
  const tom = (r) => (r === "ok" ? "ok" : r === "error" ? "danger" : "warn");
  const hora = (d) => `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")} ${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;

  return (
    <>
      <div className="gov-pad" data-contract="auditoria-selo">
        <A.Nota tone="info" title="Registro imutável">
          O registro de auditoria é append-only por trigger no banco: nenhuma tela, rotina ou administrador altera uma linha. Alterar uma é incidente P0 — por isso aqui não existe editar, apagar nem corrigir.
        </A.Nota>
      </div>

      <div className="gov-pad gov-filtros" data-contract="auditoria-filtros">
        <div className="gov-filtro">
          <label>Período</label>
          <window.CliSeg ariaLabel="Período" value={periodo} onChange={setPeriodo} options={PERIODOS_AUD.map((p) => ({ key: p.id, label: p.l }))} />
        </div>
        <div className="gov-filtro">
          <label htmlFor="gov-ator">Ator</label>
          <select id="gov-ator" className="gov-sel" value={ator} onChange={(e) => setAtor(e.target.value)}>
            <option value="">Todos</option>
            {ATORES.map((a) => <option key={a} value={a}>{a}</option>)}
          </select>
        </div>
        <div className="gov-filtro">
          <label htmlFor="gov-ep">Endpoint</label>
          <select id="gov-ep" className="gov-sel" value={endpoint} onChange={(e) => setEndpoint(e.target.value)}>
            <option value="">Todos</option>
            {ENDPOINTS.map((a) => <option key={a} value={a}>{a}</option>)}
          </select>
        </div>
        <div className="gov-filtro">
          <label htmlFor="gov-res">Resultado</label>
          <select id="gov-res" className="gov-sel" value={res} onChange={(e) => setRes(e.target.value)}>
            <option value="">Todos</option>
            {Object.entries(RES_LABEL).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
          </select>
        </div>
        {temFiltro && <button className="os-btn ghost" onClick={limpo}>Limpar filtros</button>}
        <span className="gov-hint">Não existe janela maior que 30 dias — a consulta sem índice composto fica cara demais.</span>
      </div>

      <div className="gov-kpis gov-kpis-pad" data-contract="auditoria-kpis">
        <A.Kpi l="Registros na janela" v={amostra.length} sub={filtrado.length > TETO ? `de ${filtrado.length} no período` : "no período filtrado"} />
        <A.Kpi l="Com erro" v={erros} tone={erros ? "danger" : "success"} sub="resultado erro na amostra" />
        <A.Kpi l="Atores distintos" v={atoresDistintos} tone="info" sub="na mesma amostra" />
      </div>

      <div className="gov-pad" data-contract="auditoria-tabela">
        {amostra.length === 0 ? (
          <A.Vazio variant="filtered" title="Essa combinação de filtros não devolve nada"
            description={`Período ${periodo}${ator ? ` · ator ${ator}` : ""}${endpoint ? ` · endpoint ${endpoint}` : ""}${res ? ` · resultado ${RES_LABEL[res]}` : ""}. Volte ao padrão de 24 horas para ver o movimento recente.`}
            action={<button className="os-btn" onClick={limpo}>Limpar filtros</button>} />
        ) : (
          <>
            <table className="gov-table gov-table-aud">
              <thead>
                <tr>
                  <th className="gov-th-q">Quando</th>
                  <th>Ator</th>
                  <th className="gov-th-ep">Endpoint</th>
                  <th>Ferramenta / recurso</th>
                  <th className="gov-th-r">Resultado</th>
                  <th className="gov-th-ms">Duração</th>
                </tr>
              </thead>
              <tbody>
                {amostra.map((e) => (
                  <tr key={e.id} className={e.res === "ok" ? "" : "alerta"}>
                    <td className="mono">{hora(e.ts)}</td>
                    <td>{e.ator}</td>
                    <td className="mono gov-dim">{e.ep}</td>
                    <td className="mono">{e.alvo}</td>
                    <td><Selo tone={tom(e.res)}>{RES_LABEL[e.res]}</Selo></td>
                    <td className="mono tabular gov-right">{e.ms} ms</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <p className="gov-teto" data-contract="auditoria-teto">
              Teto de {TETO} registros por consulta{filtrado.length > TETO ? ` — o período tem ${filtrado.length}` : ""}. Refine os filtros para ver mais: o que está fora da amostra não entra nos indicadores acima.
            </p>
          </>
        )}
      </div>
    </>
  );
}

// ───────────────────────── Drift de escopo ─────────────────────────

function DriftView() {
  const A = ADS();
  const { DRIFT, SEM_SCOPE, MODULOS_TOTAL, YAML_ILEGIVEL } = D();
  const [pronto, setPronto] = useState(false);
  useEffect(() => { const t = setTimeout(() => setPronto(true), 800); return () => clearTimeout(t); }, []);
  const totalDrift = DRIFT.reduce((s, d) => s + d.undeclared.length, 0);
  const zero = totalDrift === 0;

  return (
    <>
      <div className="gov-kpis gov-kpis-pad" data-contract="drift-kpis">
        <A.Kpi l="Controllers em drift" v={pronto ? totalDrift : "—"} tone={zero ? "success" : "warning"} sub="fora do contains[] declarado" />
        <A.Kpi l="Módulos com drift" v={pronto ? DRIFT.length : "—"} tone={zero ? "success" : "warning"} sub="entre os que têm escopo" />
        <A.Kpi l="Sem SCOPE.md" v={pronto ? SEM_SCOPE.length : "—"} tone="info" sub="não é drift — é ausência de contrato" />
        <A.Kpi l="Módulos no total" v={MODULOS_TOTAL} sub="varridos a cada abertura da tela" />
      </div>

      <Secao titulo="Divergência detectada em runtime" contrato="drift-lista"
        sub="Controller que existe no disco mas não está declarado no escopo do módulo. Divergência é sintoma de mudança não registrada — a regra primária é mexeu, registra.">
        {!pronto ? <Esqueleto variant="row" count={4} /> : zero ? (
          <A.Vazio variant="done" title={`Os ${MODULOS_TOTAL} módulos batem com o declarado`}
            description="Nenhum controller fora do escopo. O estado de sucesso aqui é informação — não é lista vazia." />
        ) : (
          <ul className="gov-drift">
            {DRIFT.map((d) => (
              <li key={d.mod}>
                <div className="gov-drift-h">
                  <b>{d.mod}</b>
                  <Selo tone="warn">{d.undeclared.length} não {d.undeclared.length === 1 ? "declarado" : "declarados"}</Selo>
                  <span className="gov-dim">{d.total} controllers no módulo</span>
                </div>
                <ul className="gov-drift-c">
                  {d.undeclared.map((c) => <li key={c}><span className="mono">{c}</span></li>)}
                </ul>
                <p className="gov-drift-fix">Três saídas, e nenhuma é automática: declare em <span className="mono">SCOPE.md contains[]</span>, mova o arquivo para o módulo dono, ou registre em <span className="mono">drift_alerts[]</span> enquanto a migração não fecha.</p>
              </li>
            ))}
          </ul>
        )}
        {pronto && YAML_ILEGIVEL.length > 0 && (
          <div className="gov-pad-0">
            <A.Nota tone="warn" title="Escopo ilegível">
              {YAML_ILEGIVEL.map((y) => <span key={y.mod}>{y.mod} — {y.erro}. O módulo fica fora da comparação; o erro vai para o log estruturado e o resto da lista continua de pé.</span>)}
            </A.Nota>
          </div>
        )}
      </Secao>

      <Secao titulo="Módulos sem SCOPE.md" contrato="drift-sem-scope"
        sub="Sem arquivo de escopo não há o que comparar. É achado próprio, e não drift zero — confundir os dois faz o painel parecer mais saudável do que é.">
        <div className="gov-chips">
          {SEM_SCOPE.map((m) => <span key={m} className="gov-chip">{m}</span>)}
        </div>
      </Secao>

      <Secao titulo="Histórico (últimos 30 dias)" contrato="drift-historico">
        <A.Vazio variant="offline" title="Ainda não há histórico para mostrar"
          description="O cron de detecção não roda, e a tabela de alertas não aceita a categoria de drift de módulo — o enum não tem o valor. Aceitar exige migração e ADR próprios, então a lista nasce vazia com o motivo escrito, não como nenhum resultado." />
      </Secao>
    </>
  );
}

window.GovernanceTelas = { AuditoriaView, DriftView };
})();
