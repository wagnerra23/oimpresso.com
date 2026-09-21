// governance-page.jsx — módulo Governança no app único. Espelha as telas vivas de
// resources/js/Pages/governance/ (Dashboard · Policies · Audit · DriftAlerts · ModuleGrades/Index),
// lidas no main junto de routes.php, topnav.php e dos quatro controllers.
// Cinco vistas nesta rota, nenhum .html novo: painel · politicas · auditoria · drift · notas.
// Auditoria, drift e notas vivem em governance-telas.jsx. Dados em governance-data.jsx.
// DS vivo via acessos-ds.jsx (Kpi · Nota · Sw · Vazio) + StatusBadge/Chart/Skeleton do bundle;
// tabela, abas e cabeçalho seguem do shell de propósito.
// Expõe window.GovernancePage.
(() => {
const { useState, useEffect, useMemo } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
// Ponte pro adaptador do DS (acessos-ds.jsx). Os módulos entram em carga diferida, então a tela
// precisa aguentar renderizar antes do adaptador chegar — sem isso, abrir a rota cedo derruba o slot.
const FB = {
  Kpi: ({ l, v, sub }) => <div className="gov-kpi-fb"><span className="gov-kpi-fb-v tabular">{v}</span><span className="gov-kpi-fb-l">{l}</span>{sub && <small>{sub}</small>}</div>,
  Nota: ({ title, children }) => <div className="gov-nota-fb"><b>{title}</b><p>{children}</p></div>,
  Vazio: ({ title, description, action }) => <div className="gov-vazio-fb"><b>{title}</b><p>{description}</p>{action}</div>,
  Sw: ({ on, onToggle, label }) => <button type="button" className={`gov-sw-fb ${on ? "on" : ""}`} onClick={onToggle} aria-pressed={on} aria-label={label}><i></i></button>,
};
const ADS = () => { const a = window.AcessosDS || {}; return { Kpi: a.Kpi || FB.Kpi, Nota: a.Nota || FB.Nota, Vazio: a.Vazio || FB.Vazio, Sw: a.Sw || FB.Sw }; };
const D = () => window.GovernanceData || {};
const T = () => window.GovernanceTelas || {};

const VIEWS = [
  { id: "painel", label: "Painel" },
  { id: "politicas", label: "Políticas" },
  { id: "auditoria", label: "Auditoria" },
  { id: "drift", label: "Drift" },
  { id: "notas", label: "Notas dos módulos" },
];

const TITULOS = {
  painel: "Governança — painel",
  politicas: "Governança — políticas",
  auditoria: "Governança — auditoria",
  drift: "Governança — drift de escopo",
  notas: "Governança — notas dos módulos",
};

// Carrega depois — representa Inertia::defer nas props caras (SDD, MCP, notas dos módulos).
function useDefer(ms = 700, dep) {
  const [pronto, setPronto] = useState(false);
  useEffect(() => { setPronto(false); const t = setTimeout(() => setPronto(true), ms); return () => clearTimeout(t); }, [ms, dep]);
  return pronto;
}

function Esqueleto({ variant = "row", count = 3 }) {
  const { Skeleton } = ds();
  if (!Skeleton) return <div className="gov-skel" aria-hidden="true">{Array.from({ length: count }, (_, i) => <i key={i}></i>)}</div>;
  return <Skeleton variant={variant} count={count} />;
}

function Selo({ tone = "mute", children }) {
  return <span className={`gov-selo ${tone}`}>{children}</span>;
}

function Secao({ titulo, sub, direita, contrato, children }) {
  return (
    <section className="gov-sec" data-contract={contrato}>
      <header className="gov-sec-h">
        <div>
          <h2>{titulo}</h2>
          {sub && <p>{sub}</p>}
        </div>
        {direita}
      </header>
      {children}
    </section>
  );
}

// ───────────────────────── Painel ─────────────────────────

function Conformidade() {
  const { ARTIGOS } = D();
  const plenos = ARTIGOS.filter((a) => a.estado === "pleno").length;
  const parciais = ARTIGOS.filter((a) => a.estado === "parcial").length;
  const pendentes = ARTIGOS.filter((a) => a.estado === "pendente").length;
  const pct = plenos * 10 + parciais * 5;
  return (
    <div className="gov-conf" data-contract="conformidade-regua">
      <div className="gov-conf-n">
        <span className="gov-conf-v tabular">{pct}%</span>
        <Selo tone="warn">auto-declarado</Selo>
      </div>
      <div className="gov-conf-b">
        <p className="gov-conf-l">Conformidade com a Constituição</p>
        <p className="gov-conf-d">O número é escrito à mão no controller — <span className="mono">{plenos}×10 + {parciais}×5 + 0</span> —, não apurado por checagem. Enquanto for assim, a tela o rotula como declarado.</p>
        <ul className="gov-artigos">
          {ARTIGOS.map((a) => (
            <li key={a.n} className={a.estado}>
              <span className="mono">Art. {a.n}</span>
              <b>{a.t}</b>
              <em>{a.estado === "pleno" ? "pleno" : a.estado === "parcial" ? "parcial" : "pendente"}</em>
            </li>
          ))}
        </ul>
        <p className="gov-conf-d">{plenos} artigos plenos · {parciais} parciais · {pendentes} pendente — o pendente é o gate de política, que segue em modo aviso.</p>
      </div>
    </div>
  );
}

function CardSdd() {
  const { SDD } = D();
  const pronto = useDefer(900);
  return (
    <div className="gov-card gov-sdd" data-contract="card-sdd">
      <header className="gov-card-h"><h3>Scorecard SDD</h3><Selo tone="mute">chega depois</Selo></header>
      {!pronto ? <Esqueleto variant="card" count={1} /> : (
        <>
          <div className="gov-sdd-v">
            <span className="tabular">{SDD.composta.toFixed(1)}</span>
            <small className={SDD.delta >= 0 ? "up" : "down"}>{SDD.delta >= 0 ? "+" : ""}{SDD.delta.toFixed(1)}</small>
          </div>
          <p className="gov-card-d">composta do snapshot de {SDD.data} · {SDD.vivas} de {SDD.total} métricas vivas</p>
          {SDD.alertas.map((a, i) => <p key={i} className="gov-alerta">{a}</p>)}
        </>
      )}
    </div>
  );
}

function Saude({ fonteAusente }) {
  const A = ADS();
  const { SAUDE, NARRATIVAS } = D();
  return (
    <Secao titulo="Saúde do ecossistema" sub="Três fontes das últimas 24 horas. Fonte não instalada nesta base aparece como travessão — nunca como zero." contrato="saude-ecossistema">
      <div className="gov-kpis">
        {SAUDE.map((k) => (
          <A.Kpi key={k.id} l={k.l} v={fonteAusente ? "—" : k.v} tone={fonteAusente ? "default" : k.tone}
            sub={fonteAusente ? "fonte não instalada nesta base" : k.sub} />
        ))}
      </div>
      {fonteAusente ? (
        <A.Vazio variant="offline" title="As tabelas de saúde não existem nesta base"
          description="jana_health_narratives, jana_mensagens e failed_jobs são opcionais: sem elas o painel continua de pé e os três indicadores ficam em travessão." />
      ) : (
        <ul className="gov-narrativas">
          {NARRATIVAS.map((n, i) => (
            <li key={i} className={n.sev}>
              <span className="mono">{n.h}</span>
              <p>{n.txt}</p>
            </li>
          ))}
        </ul>
      )}
    </Secao>
  );
}

function Decisoes() {
  const { ADRS, OCORRENCIAS, RES_LABEL } = D();
  const tomRes = (r) => (r === "error" ? "danger" : r === "denied" ? "warn" : "warn");
  return (
    <div className="gov-cols">
      <Secao titulo="Aguardando decisão" sub="ADRs em estado proposto, do mais recente ao mais antigo. Teto de dez — o painel é visão de topo." contrato="aguardando-decisao">
        <ul className="gov-adrs">
          {ADRS.map((a) => (
            <li key={a.slug}>
              <span className="mono">{a.slug.slice(0, 4)}</span>
              <b>{a.t}</b>
              <em>{a.dias === 1 ? "há 1 dia" : `há ${a.dias} dias`}</em>
            </li>
          ))}
        </ul>
      </Secao>
      <Secao titulo="Ocorrências em 24 horas" sub="Chamadas ao servidor MCP com resultado diferente de concluído. Detalhe completo na vista de auditoria." contrato="aguardando-decisao">
        <ul className="gov-ocor">
          {OCORRENCIAS.map((o, i) => (
            <li key={i}>
              <span className="mono gov-ocor-h">{o.h}</span>
              <span className="gov-ocor-a">{o.ator}</span>
              <span className="mono gov-ocor-t">{o.alvo}</span>
              <Selo tone={tomRes(o.res)}>{RES_LABEL[o.res]}</Selo>
              <span className="mono gov-ocor-ms">{o.ms} ms</span>
            </li>
          ))}
        </ul>
      </Secao>
    </div>
  );
}

function SecaoMcp({ semPermissao, fonteAusente }) {
  const A = ADS();
  const { MCP, PERIODOS } = D();
  const { Chart } = ds();
  const [preset, setPreset] = useState("30d");
  const [aba, setAba] = useState("consumo");
  const pronto = useDefer(1100, preset);

  if (semPermissao) return null; // sem jana.mcp.usage.all a seção não é renderizada nem consultada

  return (
    <Secao titulo="Governança MCP" contrato="mcp-secao"
      sub="Consumo cross-team do servidor MCP. A seção herda a permissão da tela de origem — jana.mcp.usage.all —, não a do painel."
      direita={
        <div className="gov-seg" role="group" aria-label="Período">
          {PERIODOS.map((p) => (
            <button key={p.id} className={preset === p.id ? "on" : ""} aria-pressed={preset === p.id} onClick={() => setPreset(p.id)}>{p.l}</button>
          ))}
        </div>
      }>
      {fonteAusente ? (
        <A.Vazio variant="offline" title="mcp_audit_log não existe nesta base"
          description="Sem a tabela, a seção não consulta nada e o painel segue inteiro. É a mesma degradação das fontes de saúde." />
      ) : !pronto ? <Esqueleto variant="card" count={2} /> : (
        <>
          <div className="gov-kpis">
            <A.Kpi l="Chamadas" v={MCP.chamadas.toLocaleString("pt-BR")} sub="no período" />
            <A.Kpi l="Taxa de sucesso" v={`${MCP.sucesso.toString().replace(".", ",")}%`} tone="success" sub="resultado concluído" />
            <A.Kpi l="Latência p95" v={`${MCP.p95} ms`} tone="info" sub={`p50 ${MCP.p50} · p99 ${MCP.p99} · máx ${MCP.max}`} />
            <A.Kpi l="Custo" v={MCP.custo} sub="tokens de entrada e saída" />
          </div>
          <nav className="gov-subtabs" role="group" aria-label="Recorte da seção MCP">
            {[["consumo", "Consumo"], ["acesso", "Acesso e permissões"], ["uso", "Usuários e ferramentas"]].map(([id, l]) => (
              <button key={id} className={aba === id ? "on" : ""} aria-pressed={aba === id} onClick={() => setAba(id)}>{l}</button>
            ))}
          </nav>
          {aba === "consumo" && (
            <div className="gov-card">
              {Chart ? <Chart type="area" data={MCP.serie} height={140} highlightLast formatValue={(v) => `${v} chamadas`} />
                : <div className="gov-spark">{MCP.serie.map((v, i) => <i key={i} style={{ height: `${(v / 1700) * 100}%` }}></i>)}</div>}
              <p className="gov-card-d">Chamadas por dia no período. Trocar o período recarrega só esta seção — a Constituição e o SDD não são reconsultados.</p>
            </div>
          )}
          {aba === "acesso" && (
            <div className="gov-cols">
              <div className="gov-card">
                <h3>Por resultado</h3>
                <ul className="gov-barras">
                  {MCP.resultado.map((r) => (
                    <li key={r.l}><span>{r.l}</span><i className={r.tone}><b style={{ width: `${(r.v / MCP.chamadas) * 100}%` }}></b></i><span className="mono">{r.v.toLocaleString("pt-BR")}</span></li>
                  ))}
                </ul>
              </div>
              <div className="gov-card">
                <h3>Negadas por código</h3>
                <ul className="gov-lista-k">
                  {MCP.negadas.map((n) => <li key={n.cod}><span className="mono">{n.cod}</span><b className="tabular">{n.v}</b></li>)}
                </ul>
                <p className="gov-card-d">Código de recusa é vocabulário do servidor — aparece cru aqui de propósito, é o que o operador procura no log.</p>
              </div>
            </div>
          )}
          {aba === "uso" && (
            <div className="gov-cols">
              <div className="gov-card">
                <h3>Atores</h3>
                <ul className="gov-lista-k">{MCP.usuarios.map((u) => <li key={u.n}><span>{u.n}</span><b className="tabular">{u.v.toLocaleString("pt-BR")}</b></li>)}</ul>
              </div>
              <div className="gov-card">
                <h3>Ferramentas</h3>
                <ul className="gov-lista-k">{MCP.tools.map((t) => <li key={t.n}><span className="mono">{t.n}</span><b className="tabular">{t.v.toLocaleString("pt-BR")}</b></li>)}</ul>
              </div>
            </div>
          )}
        </>
      )}
    </Secao>
  );
}

function PainelView({ cenario }) {
  const A = ADS();
  const { KPIS_CONST } = D();
  const fonteAusente = cenario === "fonte";
  const semPermissao = cenario === "sem-mcp";
  return (
    <>
      <Secao titulo="Constituição" sub="Estado dos checks nas últimas 24 horas. As tabelas mcp_* são cross-tenant por design — exceção formal ao Tier 0, Art. 6 e 8." contrato="kpis-constituicao">
        <div className="gov-kpis">
          {KPIS_CONST.map((k) => <A.Kpi key={k.id} l={k.l} v={k.v} tone={k.tone} sub={k.sub} />)}
        </div>
        <div className="gov-cols gov-cols-2-1">
          <Conformidade />
          <CardSdd />
        </div>
      </Secao>
      <Saude fonteAusente={fonteAusente} />
      <Decisoes />
      <SecaoMcp semPermissao={semPermissao} fonteAusente={fonteAusente} />
      {semPermissao && (
        <Secao titulo="Governança MCP" contrato="mcp-secao">
          <A.Vazio variant="no-perm" title="Esta seção pede jana.mcp.usage.all"
            description="A permissão é a mesma da tela de origem. Somar a permissão do painel deixaria a régua mais apertada que ontem e esconderia a seção de quem já a enxergava — por isso o gate não muda aqui." />
        </Secao>
      )}
    </>
  );
}

// ───────────────────────── Políticas ─────────────────────────

function PoliticasView({ toast }) {
  const A = ADS();
  const { POLITICAS } = D();
  const [regras, setRegras] = useState(POLITICAS);
  const [busca, setBusca] = useState("");

  const filtradas = useMemo(() => {
    const q = busca.trim().toLowerCase();
    const base = q ? regras.filter((r) => (r.k + r.n + r.d + r.cat).toLowerCase().includes(q)) : regras;
    return [...base].sort((a, b) => (b.on - a.on) || a.cat.localeCompare(b.cat, "pt-BR") || a.k.localeCompare(b.k));
  }, [regras, busca]);

  const cats = useMemo(() => {
    const m = new Map();
    filtradas.forEach((r) => { if (!m.has(r.cat)) m.set(r.cat, []); m.get(r.cat).push(r); });
    return [...m.entries()];
  }, [filtradas]);

  const ativas = regras.filter((r) => r.on).length;
  const disparos = regras.reduce((s, r) => s + r.disparos, 0);

  const alternar = (r) => {
    setRegras((rs) => rs.map((x) => (x.id === r.id ? { ...x, on: !x.on } : x)));
    toast(`Política #${r.id} ${r.on ? "desativada" : "ativada"} — sem registro no histórico`);
  };

  return (
    <>
      <div className="gov-kpis gov-kpis-pad" data-contract="politicas-kpis">
        <A.Kpi l="Regras no total" v={regras.length} sub="catálogo do ActionGate" />
        <A.Kpi l="Ativas" v={ativas} tone="success" sub="valendo em runtime agora" />
        <A.Kpi l="Disparos" v={disparos.toLocaleString("pt-BR")} tone="info" sub="desde a última reinicialização" />
        <A.Kpi l="Categorias" v={new Set(regras.map((r) => r.cat)).size} sub="agrupamento da lista" />
      </div>

      <div className="gov-pad" data-contract="politicas-aviso-historico">
        <A.Nota tone="warn" title="Alternar não deixa rastro">
          A tabela de histórico de regras ainda não existe. Enquanto for assim, ligar ou desligar uma política muda o enforcement em runtime e a auditoria fica cega justamente para essa mudança — quem alterna precisa saber disso na hora, não depois.
        </A.Nota>
      </div>

      <div className="gov-pad gov-toolbar">
        <label className="gov-busca">
          <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar por chave, nome ou categoria…" aria-label="Buscar política" />
        </label>
        <span className="gov-hint">Desligadas continuam na lista — são elas que você precisa achar para reativar.</span>
      </div>

      <div className="gov-pad" data-contract="politicas-lista">
        {cats.length === 0 ? (
          <A.Vazio variant="no-results" title="Nenhuma política bate com essa busca"
            description="Limpe o campo para ver o catálogo inteiro — ativas primeiro, depois por categoria e chave."
            action={<button className="os-btn" onClick={() => setBusca("")}>Limpar busca</button>} />
        ) : cats.map(([cat, rs]) => (
          <section key={cat} className="gov-grupo">
            <h3>{cat}<span className="gov-grupo-n mono">{rs.length}</span></h3>
            <ul className="gov-regras">
              {rs.map((r) => (
                <li key={r.id} className={r.on ? "" : "off"}>
                  <div className="gov-regra-sw">
                    <A.Sw on={r.on} onToggle={() => alternar(r)} label={`${r.on ? "Desativar" : "Ativar"} ${r.n}`} />
                  </div>
                  <div className="gov-regra-b">
                    <div className="gov-regra-t">
                      <span className="mono gov-regra-k">{r.k}</span>
                      <Selo tone={r.on ? "ok" : "mute"}>{r.on ? "Ativa" : "Desligada"}</Selo>
                      <span className="gov-regra-v mono">versão {r.v}</span>
                    </div>
                    <b>{r.n}</b>
                    <p>{r.d}</p>
                  </div>
                  <div className="gov-regra-d">
                    <span className="tabular">{r.disparos.toLocaleString("pt-BR")}</span>
                    <small>disparos</small>
                  </div>
                </li>
              ))}
            </ul>
          </section>
        ))}
      </div>
    </>
  );
}

// ───────────────────────── Shell ─────────────────────────

const CENARIOS = [
  { id: "normal", label: "Base completa" },
  { id: "fonte", label: "Fontes ausentes" },
  { id: "sem-mcp", label: "Sem permissão MCP" },
];

function GovernancePage({ view = "painel" }) {
  const [aba, setAba] = useState(view);
  const [cenario, setCenario] = useState("normal");
  const [toast, setToast] = useState(null);
  useEffect(() => setAba(view), [view]);
  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 2800);
    return () => clearTimeout(t);
  }, [toast]);
  const t = T();

  return (
    <div className="os-page gov-page" data-screen-label={`Governança · ${VIEWS.find((v) => v.id === aba)?.label || aba}`}>
      <header className="os-page-h" data-contract="page-header">
        <div className="os-page-h-l">
          <h1>{TITULOS[aba]}</h1>
          <p className="tabular">A raiz /governance redireciona para /ia · o painel vive em /governance/dashboard</p>
        </div>
        <div className="os-page-h-r">
          {aba === "painel" && (
            <div className="gov-seg" role="group" aria-label="Estado da base">
              {CENARIOS.map((c) => (
                <button key={c.id} className={cenario === c.id ? "on" : ""} aria-pressed={cenario === c.id} onClick={() => setCenario(c.id)}>{c.label}</button>
              ))}
            </div>
          )}
          <span className="mod-scope">superadmin · cross-tenant</span>
        </div>
      </header>

      <nav className="gov-tabs" role="tablist" data-contract="tabs">
        {VIEWS.map((v) => (
          <button key={v.id} role="tab" aria-selected={aba === v.id} className={`gov-tab ${aba === v.id ? "on" : ""}`} onClick={() => setAba(v.id)}>{v.label}</button>
        ))}
      </nav>

      <div className="gov-body">
        {!D().ARTIGOS ? <div className="gov-pad"><p className="gov-hint">Carregando os dados do módulo…</p></div> : <>
        {aba === "painel" && <PainelView key={cenario} cenario={cenario} />}
        {aba === "politicas" && <PoliticasView toast={setToast} />}
        {aba === "auditoria" && t.AuditoriaView && <t.AuditoriaView />}
        {aba === "drift" && t.DriftView && <t.DriftView />}
        {aba === "notas" && t.NotasView && <t.NotasView />}
        </>}
      </div>

      {toast && <div className="mod-toast">{toast}</div>}
    </div>
  );
}

Object.assign(window, { GovernancePage, GovernanceUi: { Secao, Selo, Esqueleto, useDefer } });
})();
