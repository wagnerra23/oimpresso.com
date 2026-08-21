// modulo-padrao.jsx — O "padrão Jana" extraído como shell reusável de módulo (F1 [CC]).
// Origem: chat-jana.jsx + jana-merge.jsx (fusão 2026-08). O que a Jana tem e os outros
// módulos não têm, virou peça: header com contexto + reapuração, abas de ÁREA dentro da
// página, painel de abertura (brief → KPIs → análises → ações) e estados dados/vazio/erro.
// Reusa as classes jc-*/cli-moduletopnav (mesma pele, zero paleta paralela) e os
// componentes já publicados em window (KPICard, AnaliseCard, AcaoRow, JcIcon).
// Expõe window.ModuloPadrao. Nenhuma tela nova: cada módulo monta o seu painel com isto.
(() => {
const { useState, useEffect, useRef } = React;
const DSx = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

function Pagina({ label, className, children }) {
  return <div className={"jc-page mp-page" + (className ? " " + className : "")} data-screen-label={label}>{children}</div>;
}

// Header de módulo — o equivalente do JanaHeader: quem sou, de qual empresa,
// quando foi apurado (clicável = reapura) e as ações da tela.
function Header({ modulo, papel, contexto = [], atualizadoAs, onRefresh, glyph, acoes }) {
  const { JcIcon } = window;
  return (
    <header className="jc-header mp-header">
      <div className="jc-header-l">
        <div className="mp-glyph" aria-hidden="true">{glyph || <JcIcon name="list" />}</div>
        <div className="jc-id">
          <h1>{modulo}{papel && <> <span className="dot">·</span> {papel}</>}</h1>
          <p>{contexto.map((c, i) => <React.Fragment key={i}>{i > 0 && <span className="jc-sep">·</span>}{i === 0 ? <span className="jc-tenant">{c}</span> : c}</React.Fragment>)}</p>
        </div>
      </div>
      <div className="jc-header-r">
        {atualizadoAs && <span className="jc-updated">{onRefresh ?
          <button className="jc-updated-b" onClick={onRefresh} title="Reapurar agora"><span className="d" />Atualizado {atualizadoAs}</button> :
          <><span className="d" />Atualizado {atualizadoAs}</>}</span>}
        {acoes}
      </div>
    </header>
  );
}

// Abas de ÁREA — dentro da página, abaixo do header (canon [W] 2026-06-22).
function Tabs({ tabs = [], tab, onTab, aria = "Áreas do módulo" }) {
  const { JcIcon } = window;
  return (
    <nav className="cli-moduletopnav jm-tabs" aria-label={aria}>
      {tabs.map((t) =>
        <button key={t.key} className={"cli-moduletopnav-tab " + (tab === t.key ? "active" : "")}
          onClick={() => onTab?.(t.key)} aria-current={tab === t.key ? "page" : undefined}>
          {t.icon && JcIcon && <JcIcon name={t.icon} className="jm-tab-ic" />}
          <span>{t.label}</span>
          {t.n != null && <span className="cli-moduletopnav-n">{t.n}</span>}
        </button>
      )}
    </nav>
  );
}

// Resumo do dia — a leitura do módulo em texto, com atalhos que levam ao trabalho.
// Sem IA obrigatória: quando `ia` é falso, é apuração do próprio módulo.
function Resumo({ titulo = "Resumo de hoje", quando, linhas = [], destaque, chips = [], onChip, ia }) {
  const { JcIcon } = window;
  return (
    <section className="jc-brief mp-resumo">
      <div className="jc-brief-h">
        <span className="jc-brief-h-l"><JcIcon name="calendar" className="ic" /> <b>{titulo}</b>{quando && <> <span className="sep">·</span> {quando}</>}</span>
        {ia && <span className="jc-pill ia">IA</span>}
      </div>
      {linhas.map((l, i) => <p key={i}>{l}</p>)}
      {destaque && <p className="jc-brief-action"><JcIcon name="bulb" className="ic" /> {destaque}</p>}
      {chips.length > 0 && <>
        <div className="jc-brief-sep" />
        <div className="jc-brief-chips">
          {chips.map((c, i) =>
            <button key={i} className={"jc-chip " + (c.tone || "")} onClick={() => onChip?.(c)}>
              {c.icon && <JcIcon name={c.icon} className="ic" />} {c.label}
            </button>
          )}
        </div>
      </>}
    </section>
  );
}

// KPIs do módulo. onDrill torna o card clicável — todo número mostra de onde vem.
function Kpis({ kpis = [], onDrill }) {
  const { KPICard } = window;
  if (!KPICard) return null;
  return (
    <div className="jc-kpis">
      {kpis.map((k, i) => onDrill ?
        <div key={i} className="jm-an-hit" role="button" tabIndex={0} aria-label={"Ver origem de " + k.label}
          onClick={() => onDrill(k)}
          onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); onDrill(k); } }}>
          <KPICard kpi={k} />
        </div> :
        <KPICard key={i} kpi={k} />
      )}
    </div>
  );
}

function Secao({ titulo, sub, icon = "chart" }) {
  const { JcIcon } = window;
  return <h2 className="jc-h2">{JcIcon && <JcIcon name={icon} className="ic" />} {titulo}{sub && <span className="jm-h2-sub">{sub}</span>}</h2>;
}

function Analises({ analises = [], onDrill }) {
  const { AnaliseCard } = window;
  if (!AnaliseCard) return null;
  return (
    <div className="jc-grid">
      {analises.map((a) => onDrill ?
        <div key={a.id} className="jm-an-hit" role="button" tabIndex={0} aria-label={"Ver origem de " + a.title}
          onClick={() => onDrill(a)}
          onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); onDrill(a); } }}>
          <AnaliseCard a={a} />
        </div> :
        <AnaliseCard key={a.id} a={a} />
      )}
    </div>
  );
}

function Acoes({ acoes = [], onCta }) {
  const { AcaoRow } = window;
  if (!AcaoRow) return null;
  return <div className="jc-acoes">{acoes.map((a) => <AcaoRow key={a.id} a={a} onCta={onCta} />)}</div>;
}

// Estado sem dados — sempre POR QUE + O QUE FAZER (canon EmptyState do DS).
function Estado({ erro, titulo, descricao, acao }) {
  const { EmptyState } = DSx();
  const { JcIcon } = window;
  if (!EmptyState) return <div className="jm-mem-empty"><b>{titulo}</b><small>{descricao}</small></div>;
  return <EmptyState variant={erro ? "error" : "first"} icon={JcIcon ? <JcIcon name={erro ? "alert" : "sparkles"} /> : null}
    title={titulo} description={descricao} action={acao} />;
}

function Skeleton({ compacto }) {
  const { Skeleton: S } = DSx();
  if (!S) return null;
  return (
    <div className="mp-skel">
      {!compacto && <S variant="card" />}
      <div className="jc-kpis">{[0, 1, 2, 3].map((i) => <S key={i} variant="card" />)}</div>
      {!compacto && <div className="jc-grid">{[0, 1, 2].map((i) => <S key={i} variant="card" />)}</div>}
    </div>
  );
}

// Drill-down genérico: de onde vem o número + o que fazer com ele.
function Drill({ item, onClose, footer }) {
  const { Drawer, DrawerSection } = DSx();
  if (!Drawer || !item) return null;
  return (
    <Drawer open={!!item} onClose={onClose} width={520} title={item.title || item.label} subtitle={item.sub} footer={footer}>
      {item.origem && <DrawerSection title="De onde vem">
        <ul className="jm-dr-src">{item.origem.map((o, i) => <li key={i}>{o}</li>)}</ul>
      </DrawerSection>}
      {item.detalhe && <DrawerSection title="Detalhe">{item.detalhe}</DrawerSection>}
    </Drawer>
  );
}

// Toast curto — confirmação sem tirar o operador do fluxo.
function useAviso(ms = 2600) {
  const [aviso, setAviso] = useState(null);
  const ref = useRef(null);
  useEffect(() => () => clearTimeout(ref.current), []);
  const avisar = (msg, tone = "default") => {
    setAviso({ msg, tone });
    clearTimeout(ref.current);
    ref.current = setTimeout(() => setAviso(null), ms);
  };
  const { Toast } = DSx();
  const node = aviso ?
    <div className="jm-toast-wrap">{Toast ? <Toast tone={aviso.tone}>{aviso.msg}</Toast> : <div className="jm-toast">{aviso.msg}</div>}</div> :
    null;
  return [node, avisar];
}

// Aba de área persistida — cada módulo tem a sua chave (nunca colidir).
function useAba(chave, inicial) {
  const [aba, setAba] = useState(() => { try { return localStorage.getItem(chave) || inicial; } catch (e) { return inicial; } });
  useEffect(() => { try { localStorage.setItem(chave, aba); } catch (e) {} }, [chave, aba]);
  return [aba, setAba];
}

window.ModuloPadrao = { Pagina, Header, Tabs, Resumo, Kpis, Secao, Analises, Acoes, Estado, Skeleton, Drill, useAviso, useAba };
})();
