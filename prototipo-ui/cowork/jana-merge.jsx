// jana-merge.jsx — FUSÃO das telas do módulo Jana num só destino (F1 [CC]).
// Propósito: hoje o main tem 4 telas disputando o mesmo trabalho — /ia (Chat, live),
// /ia/cockpit (draft, anti-pattern), /ia/painel (draft) e /ia/dashboard (metas/farol, live).
// Aqui: 1 tela com abas de área — Painel (brief+KPIs+METAS+análises+ações) · Conversa · Memória.
// /ia/cockpit morre · /ia/dashboard vira a SEÇÃO Metas (ou aba própria, via Tweak).
// Reusa os componentes de chat-jana.jsx (window.*). Sem tela nova, sem .html novo.
const { useState: useStateJM, useMemo: useMemoJM } = React;

// Metas: série numérica é a fonte — realizado, %, farol e projeção são derivados do período escolhido.
const JM_METAS_BASE = [
  { id: "m1", nome: "Faturamento mensal", alvo: 145, fmt: "brlk", acumula: true,
    serie: [138, 141, 129, 133, 147, 152, 144, 121, 98, 84, 71, 47],
    nota: "Projeção linear com 14 dias corridos — mix de produto puxando pra baixo." },
  { id: "m2", nome: "Recuperação de vencidos", alvo: 400, fmt: "brlk", acumula: true,
    serie: [180, 205, 240, 260, 231, 198, 226, 254, 241, 219, 233, 212],
    nota: "Régua HITL ainda não disparada nos 8 clientes >90d." },
  { id: "m3", nome: "Utilização de frota", alvo: 70, fmt: "pct",
    serie: [58, 61, 57, 54, 52, 49, 47, 44, 41, 38, 35, 33],
    nota: "8 caçambas paradas >7d puxam a curva — 3 overdue hoje." },
  { id: "m4", nome: "Ticket médio", alvo: 2400, fmt: "brl",
    serie: [2430, 2411, 2385, 2320, 2298, 2244, 2190, 2101, 2044, 1988, 1932, 1890],
    nota: "Margem estável — mix migrando pra caçamba pequena." },
  { id: "m5", nome: "Novos clientes", alvo: 30, fmt: "int", acumula: true,
    serie: [22, 26, 31, 28, 33, 29, 35, 30, 38, 31, 36, 34],
    nota: "Origem: balcão + indicação — zero investimento em mídia." },
  // Meta sem apuração na janela corrente: a produção degrada pra farol 'cinza'
  // (Index.tsx §farolDaMeta) e mostra "Aguardando apuração…" em vez de inventar veredito.
  { id: "m6", nome: "Margem de contribuição", alvo: 38, fmt: "pct",
    serie: [36, 35, 37, 34, 33, 35, 32, 31, 30, 32, 31, null],
    nota: "Apuração da janela corrente ainda não rodou — o job de margem roda no fechamento." }];

const JM_PERIODOS = [
  { key: "mai/2026", idx: 11, corrente: true },
  { key: "abr/2026", idx: 10 },
  { key: "mar/2026", idx: 9 }];

function jmFmt(v, fmt) {
  const n = Math.round(v);
  if (fmt === "brlk") return "R$ " + n + "k";
  if (fmt === "pct") return n + "%";
  if (fmt === "brl") return "R$ " + n.toLocaleString("pt-BR");
  return String(n);
}

function jmMeta(base, per) {
  const atualN = base.serie[per.idx];
  // Farol é veredito do SERVIDOR (`ApuracaoService::farol`) — o frontend só consome
  // (Index.charter §Goals + §Anti-hooks). Sem apuração na janela, 'cinza' já é o rótulo
  // de "não dá pra dizer" da própria regra: nada é chutado aqui.
  if (atualN == null) {
    return { ...base, periodo: per.key, pct: null, farol: "cinza",
      atual: null, alvo: jmFmt(base.alvo, base.fmt), proj: "aguardando apuração",
      delta: null, deltaBom: null,
      serie: base.serie.slice(Math.max(0, per.idx - 11), per.idx + 1).filter((v) => v != null) };
  }
  const pct = Math.round(atualN / base.alvo * 100);
  const farol = pct >= 95 ? "verde" : pct >= 50 ? "amarelo" : "vermelho";
  // Cumulativa (faturamento, recuperação, novos clientes): extrapola o ritmo do mês corrente.
  // Média/taxa (ticket, utilização): projeta a TENDÊNCIA da série — nunca multiplica o valor.
  let proj;
  const anterior = per.idx > 0 ? base.serie[per.idx - 1] : null;
  const deltaN = anterior == null ? null : atualN - anterior;
  if (!per.corrente) {
    proj = "fechado em " + jmFmt(atualN, base.fmt);
  } else if (base.acumula) {
    proj = jmFmt(atualN * 1.3, base.fmt) + " no fechamento";
  } else {
    const janela = base.serie.slice(-4);
    const delta = (janela[janela.length - 1] - janela[0]) / (janela.length - 1);
    proj = jmFmt(atualN + delta * 0.6, base.fmt) + " no fechamento (tendência)";
  }
  return { ...base, periodo: per.key, pct, farol,
    atual: jmFmt(atualN, base.fmt), alvo: jmFmt(base.alvo, base.fmt), proj,
    delta: deltaN == null ? null : (deltaN > 0 ? "+" : deltaN < 0 ? "−" : "") + jmFmt(Math.abs(deltaN), base.fmt) + " vs janela anterior",
    deltaBom: deltaN == null ? null : deltaN >= 0,
    serie: base.serie.slice(Math.max(0, per.idx - 11), per.idx + 1) };
}

// Taxonomia da PRODUÇÃO (Memoria.tsx §CATEGORIA_LABELS) — a lista antiga
// (preferência/operação/financeiro/cliente/sazonalidade/equipe) era a taxonomia do MOCK.
// Escala de relevância /10 também é da produção: decisão [W] 2026-08-07 — mudar a escala
// seria migrar `metadata.relevancia` já gravado, e não há razão de domínio pra isso.
const JM_CAT_LABELS = {
  meta: "Meta",
  preferencia: "Preferência",
  restricao: "Restrição",
  contexto: "Contexto",
  acao_pendente: "Ação pendente" };

const JM_FATOS = [
  { id: "f1", fato: "Martinho prefere ser chamado de \"Seu Martinho\" no WhatsApp.", cat: "preferencia", origem: "chat", desde: "12/03/2026 09:14", rel: 6 },
  { id: "f2", fato: "Caçamba de 5m³ é o produto de maior giro — 61% dos pedidos avulsos.", cat: "contexto", origem: "brief auto", desde: "02/04/2026 06:02", rel: 9 },
  { id: "f3", fato: "Cheque só é depositado na terça e na quinta (rotina da Larissa).", cat: "preferencia", origem: "chat", desde: "18/04/2026 11:37", rel: 8 },
  { id: "f4", fato: "VARGAS LEANDRO negocia parcela toda sexta — histórico de 246 parcelas.", cat: "contexto", origem: "brief auto", desde: "22/04/2026 06:03", rel: 9 },
  { id: "f5", fato: "Obra da Rodovia BR-459 é a origem do pico de out–fev.", cat: "contexto", origem: "inserção manual", desde: "05/05/2026 15:20", rel: 8 },
  { id: "f6", fato: "Eliana responde pelo financeiro; aprovação de baixa passa por ela.", cat: "restricao", origem: "chat", desde: "09/05/2026 08:41", rel: 7 },
  { id: "f7", fato: "Régua de reativação dos 8 clientes ouro aguarda aprovação antes do primeiro envio.", cat: "acao_pendente", origem: "chat", desde: "12/05/2026 17:05", rel: 8 },
  { id: "f8", fato: "Alvo combinado de recuperação de vencidos: R$ 400k no trimestre.", cat: "meta", origem: "chat", desde: "14/05/2026 10:12", rel: 7 }];

// Histórico de conversas com a Jana (Chat.charter: lista à esquerda + pills de filtro).
const JM_THREADS = [
  { id: "t1", title: "Por que a receita caiu 68%?", preview: "Decomposição da queda — mix, evasão e sazonalidade", quando: "09:38", n: 12, escopo: "minhas" },
  { id: "t2", title: "Top devedores ativos", preview: "4.255 títulos · top 20 concentram 47%", quando: "ontem", n: 7, escopo: "minhas" },
  { id: "t3", title: "Reativação dos clientes ouro", preview: "8 contas LTV >R$ 50k · régua HITL preparada", quando: "ter", n: 21, escopo: "minhas" },
  { id: "t4", title: "Caçambas paradas há mais de 7 dias", preview: "8 paradas · 3 overdue · sugestão de outbound", quando: "seg", n: 9, escopo: "minhas", fix: true },
  { id: "t5", title: "Fechamento de abril", preview: "DRE conferido · divergência de R$ 1.240 explicada", quando: "05/mai", n: 34, escopo: "arquivadas" },
  { id: "t6", title: "Cheques a depositar na semana", preview: "Rotina terça/quinta · lembrete criado", quando: "02/mai", n: 5, escopo: "arquivadas" }];

// 2 filtros, não 4 (produção, Chat.tsx §ConvSidePanel, medido 2026-08-07):
// "minhas" era TAUTOLÓGICA — a lista já é `where('user_id', $userId)`; "compartilhadas"
// seria SEMPRE vazia — não existe compartilhamento (403 em 4 pontos do ChatController).
const JM_FILTROS = ["todas", "arquivadas"];

// Propostas de meta que a Jana devolve na conversa (produção: `sugestoesPendentes`
// + PropostaCard do Chat.tsx). Nada vira meta sem você escolher.
const JM_PROPOSTAS = [
  { id: "p1", nome: "Recuperar R$ 120k de vencidos até junho", metrica: "recuperacao_vencidos",
    valor_alvo: 120000, periodo: "jun/2026", dificuldade: "realista",
    racional: "O top 20 concentra 47% do vencido — a régua HITL já cobre os 8 maiores, sem contato novo.",
    dependencias: ["régua de cobrança ativa", "aprovação da Eliana"] },
  { id: "p2", nome: "Ticket médio de volta em R$ 2.400", metrica: "ticket_medio",
    valor_alvo: 2400, periodo: "mai/2026", dificuldade: "ambicioso",
    racional: "O mix migrou pra caçamba pequena; voltar ao ticket de janeiro exige empurrar 5m³ no balcão.",
    dependencias: ["tabela de preço revisada"] }];

// KPI → análise que EXPLICA aquele número. Só mapeia quando o card existe e fala do mesmo dado:
// ticket médio (trendDown) não tem análise própria, então fica não-clicável em vez de abrir faturamento.
const JM_KPI_DRILL = { coins: "fat", alert: "inad", truck: "frota" };

// Cada conversa tem thread própria — trocar no histórico troca o conteúdo (P0).
function jmSeed(id, data, threads) {
  if (id === "t1") return data.seed;
  const nova = (threads || []).find((t) => t.id === id);
  if (nova && nova.pergunta) return [
  { from: "user", kind: "text", text: nova.pergunta },
  { from: "jana", kind: "tool_use", tool: "contexto_negocio", detail: "brief de hoje · biz=164" },
  { from: "jana", kind: "markdown", body: [
    "Puxei os números por trás dessa pergunta antes de responder:",
    "• O padrão começa em **março** e se concentra em poucas contas.",
    "• O caminho mais curto é tratar o **topo da curva** antes de prospectar [1]."],
    sources: [{ n: 1, label: "Pareto de clientes · base atual", href: "#pareto" }] }];

  const S = {
    t2: [
    { from: "user", kind: "text", text: "Quem deve mais agora?" },
    { from: "jana", kind: "tool_use", tool: "titulos_vencidos", detail: "4.255 títulos · biz=164" },
    { from: "jana", kind: "markdown", body: [
      "São **R$ 4.535.636** vencidos, mas a fila real é curta:",
      "• **Top 20 clientes = R$ 2.142k (47%)** — cobrar esses vinte resolve metade [1].",
      "• R$ 770k passaram de 365d — candidatos a baixa, não a cobrança."],
      sources: [{ n: 1, label: "Top 20 devedores · base atual", href: "#top20" }] }],

    t3: [
    { from: "user", kind: "text", text: "Quais clientes ouro devo reativar essa semana?" },
    { from: "jana", kind: "markdown", body: [
      "Oito contas com LTV acima de R$ 50k pararam de comprar há mais de 90 dias.",
      "As três maiores somam **R$ 220k de LTV** e têm obra ativa na região — começaria por elas."] },
    { from: "jana", kind: "action_card", state: "confirm",
      title: "Preparar régua de reativação para os 8 clientes ouro?",
      sub: "LTV combinado R$ 612k · cada mensagem passa pela sua aprovação antes de sair." }],

    t4: [
    { from: "user", kind: "text", text: "Quais caçambas estão paradas há mais de 7 dias?" },
    { from: "jana", kind: "data_table",
      title: "Ativos parados · mai/2026",
      cols: [{ k: "ativo", label: "Caçamba" }, { k: "dias", label: "Dias parada", num: true }, { k: "ult", label: "Último cliente" }],
      rows: [
      { ativo: "CC-014", dias: "19", ult: "CONSTRUFERRO IND." },
      { ativo: "CC-027", dias: "14", ult: "EXTREMA SOLDAS" },
      { ativo: "CC-031", dias: "11", ult: "CAPITAL CARGAS" },
      { ativo: "CC-044", dias: "9", ult: "MARTINS OBRAS" }] }],


    t5: [
    { from: "user", kind: "text", text: "Fecha abril pra mim?" },
    { from: "jana", kind: "markdown", body: [
      "Abril fechou em **R$ 71.320**. A divergência de R$ 1.240 no DRE era um cheque lançado duas vezes — já sinalizei pra Eliana."] }],

    t6: [
    { from: "user", kind: "text", text: "Quais cheques deposito essa semana?" },
    { from: "jana", kind: "markdown", body: [
      "Terça: 3 cheques (**R$ 8.410**). Quinta: 2 cheques (**R$ 5.190**).",
      "Criei o lembrete na rotina da Larissa — mesma janela de sempre."] }] };


  return S[id] || [];
}

function JmFarol({ farol }) {
  const cor = farol === "verde" ? "var(--pos)" : farol === "amarelo" ? "var(--warn)" : farol === "cinza" ? "var(--text-mute)" : "var(--neg)";
  return <span className="jm-farol" style={{ background: cor }} title={"Farol " + farol} />;
}

function JmMetaCard({ m, onOpen }) {
  const cor = m.farol === "verde" ? "var(--pos)" : m.farol === "amarelo" ? "var(--warn)" : m.farol === "cinza" ? "var(--text-mute)" : "var(--neg)";
  const semApuracao = m.pct == null;
  return (
    <button className={"jm-meta" + (semApuracao ? " cinza" : "")} onClick={() => onOpen(m)}
    aria-label={m.nome + " · " + (semApuracao ? "aguardando apuração" : m.atual + " de " + m.alvo) + " · farol " + m.farol}>
      <div className="jm-meta-h">
        <span className="jm-meta-n"><JmFarol farol={m.farol} />{m.nome}</span>
        <span className="jm-meta-p">{m.periodo}</span>
      </div>
      {semApuracao ?
      <div className="jm-meta-apurando">Aguardando apuração…<small>alvo {m.alvo}</small></div> :

      <>
          <div className="jm-meta-v">
            <b>{m.atual}</b>
            <small>de {m.alvo}</small>
          </div>
          <div className="jm-meta-track"><div style={{ width: Math.min(m.pct, 100) + "%", background: cor }} /></div>
          <div className="jm-meta-f">
            <span>{m.pct}% do alvo</span>
            <span className="jm-meta-proj">{m.proj}</span>
          </div>
        </>}

    </button>);

}

function JmSerie({ serie }) {
  const max = Math.max(...serie);
  return (
    <div className="jm-serie">
      {serie.map((v, i) =>
      <div key={i} className="jm-serie-col" title={v}>
          <div className="jm-serie-bar" style={{ height: Math.max(4, v / max * 100) + "%" }} />
        </div>
      )}
    </div>);

}

function JmMetaDrawer({ meta, onClose, onFalarComJana, onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Drawer, DrawerSection, Button } = DS;
  if (!Drawer || !meta) return null;
  const tone = meta.farol === "verde" ? "success" : meta.farol === "amarelo" ? "warning" : meta.farol === "cinza" ? "neutral" : "danger";
  return (
    <Drawer
      open={!!meta} onClose={onClose} width={520}
      title={meta.nome}
      subtitle={"Meta ativa · " + meta.periodo + " · apuração das últimas 12 janelas"}
      badge={<span className={"jm-badge " + tone}><JmFarol farol={meta.farol} />farol {meta.farol}</span>}
      footer={
      <div className="jm-drawer-foot">
          {Button ?
        <>
              <Button variant="ghost" onClick={onClose}>Fechar</Button>
              <span className="jm-foot-spacer" />
              <Button variant="ghost" onClick={() => onAviso?.("Editar meta abre a tela própria (/ia/metas/" + meta.id + ") — fora deste protótipo.")}>Editar meta</Button>
              <Button variant="primary" onClick={onFalarComJana}>Conversar com a Jana</Button>
            </> :

        <button className="jm-btn" onClick={onFalarComJana}>Conversar com a Jana</button>}
        </div>}>


      <DrawerSection title="Situação">
        <div className="jm-dr-kpis">
          <div><small>Realizado</small><b>{meta.atual || "—"}</b></div>
          <div><small>Alvo</small><b>{meta.alvo}</b></div>
          <div><small>Projeção</small><b>{meta.proj.replace(" no fechamento", "").replace("fechado em ", "")}</b></div>
        </div>
        {meta.delta && <p className={"jm-dr-delta" + (meta.deltaBom ? " bom" : "")}>{meta.delta}</p>}
        <p className="jm-dr-nota">{meta.nota}</p>
      </DrawerSection>
      <DrawerSection title={"Série · " + meta.serie.length + " janelas"}>
        <JmSerie serie={meta.serie} />
        <div className="jm-serie-range"><span>início da série</span><span>{meta.periodo}</span></div>
      </DrawerSection>
      <DrawerSection title="Origem do número">
        <ul className="jm-dr-src">
          <li>Veredito do farol calculado no servidor · <code>ApuracaoService::farol</code></li>
          <li>Escopo <code>business_id</code> da sessão (Tier 0)</li>
          <li>Editar a meta abre a tela própria em modo foco — <code>/ia/metas/{meta.id}</code></li>
        </ul>
      </DrawerSection>
    </Drawer>);

}

function JmMetasSecao({ standalone, onOpen, vazio, erro, onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { EmptyState } = DS;
  const JcIcon = window.JcIcon;
  const [per, setPer] = useStateJM(JM_PERIODOS[0]);
  const metas = useMemoJM(() => JM_METAS_BASE.map((b) => jmMeta(b, per)), [per]);
  return (
    <section className={"jm-metas" + (standalone ? " standalone" : "")}>
      <h2 className="jc-h2">
        {JcIcon && <JcIcon name="target" className="ic" />} METAS ATIVAS
        <span className="jm-per">
          {JM_PERIODOS.map((p) =>
          <button key={p.key} className={"jm-cat" + (per.key === p.key ? " active" : "")} onClick={() => setPer(p)}>{p.key}</button>
          )}
          <button className="jm-btn ghost" onClick={() => onAviso?.("Criar meta abre a tela própria (/ia/metas/nova) — fora deste protótipo.")}>Nova meta</button>
        </span>
      </h2>
      {vazio ?
      EmptyState ?
      <EmptyState
        variant={erro ? "error" : "first"}
        icon={JcIcon ? <JcIcon name={erro ? "alert" : "target"} /> : null}
        title={erro ? "Não consegui apurar as metas" : "Nenhuma meta ativa neste período"}
        description={erro ?
        "A apuração falhou nesta janela — os números seriam chute, então preferi não mostrar." :
        "Sem meta definida, a Jana não tem farol pra comparar. Criar meta abre a tela própria, em modo foco."}
        action={<button className="jm-btn" onClick={() => onAviso?.(erro ?
        "Reapuração é job do servidor — fora deste protótipo." :
        "Criar meta abre a tela própria (/ia/metas/nova) — fora deste protótipo.")}>{erro ? "Reapurar" : "Criar meta"}</button>} /> :

      <div className="jm-mem-empty"><b>Nenhuma meta ativa neste período.</b><small>Criar meta abre a tela própria.</small></div> :

      <div className="jm-metas-grid">
          {metas.map((m) => <JmMetaCard key={m.id} m={m} onOpen={onOpen} />)}
        </div>}

    </section>);

}

function JmMemoria({ estado = "dados" }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Alert, EmptyState } = DS;
  const JcIcon = window.JcIcon;
  const [busca, setBusca] = useStateJM("");
  const [cat, setCat] = useStateJM("todas");
  const [apagando, setApagando] = useStateJM(null);
  const [editando, setEditando] = useStateJM(null);
  const [rascunho, setRascunho] = useStateJM({ fato: "", cat: "", rel: 5, motivo: "" });
  const [fatos, setFatos] = useStateJM(estado === "dados" ? JM_FATOS : []);

  // Chips DERIVADOS do dado (produção, Memoria.tsx) — categoria nova aparece sozinha,
  // categoria que sumiu do dado não deixa chip morto na barra.
  const cats = useMemoJM(() => {
    const vistas = [];
    fatos.forEach((f) => {if (!vistas.includes(f.cat)) vistas.push(f.cat);});
    return ["todas", ...vistas.sort((a, b) => (JM_CAT_LABELS[a] || a).localeCompare(JM_CAT_LABELS[b] || b, "pt-BR"))];
  }, [fatos]);

  const abrirEdicao = (f) => {
    setApagando(null);
    setEditando(f.id);
    setRascunho({ fato: f.fato, cat: f.cat, rel: f.rel, motivo: "" });
  };
  const salvar = (f) => {
    setFatos((fs) => fs.map((x) => x.id === f.id ?
    { ...x, fato: rascunho.fato.trim(), cat: rascunho.cat, rel: rascunho.rel, editado: "você · agora", motivo: rascunho.motivo.trim() } : x));
    setEditando(null);
  };

  const lista = useMemoJM(() => fatos.filter((f) =>
  (cat === "todas" || f.cat === cat) &&
  f.fato.toLowerCase().includes(busca.trim().toLowerCase())),
  [fatos, cat, busca]);

  const semNada = fatos.length === 0;
  const filtrado = !semNada && lista.length === 0;

  return (
    <div className="jm-mem">
      {Alert ?
      <Alert tone="info" title="Memória da Jana — LGPD Art. 18">
          Você vê, corrige e apaga qualquer fato que a Jana aprendeu sobre o seu negócio. Toda alteração registra autor e motivo no log de auditoria.
        </Alert> :

      <div className="jm-mem-lgpd">Memória da Jana — LGPD Art. 18. Você vê, corrige e apaga qualquer fato aprendido.</div>}


      <div className="jm-mem-bar">
        <label className="jm-search">
          {JcIcon && <JcIcon name="search" className="ic" />}
          <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar em fatos…" />
        </label>
        <div className="jm-mem-cats">
          {cats.map((c) =>
          <button key={c} className={"jm-cat" + (cat === c ? " active" : "")} onClick={() => setCat(c)}>{c === "todas" ? "todas" : JM_CAT_LABELS[c] || c}</button>
          )}
        </div>
        <span className="jm-mem-n">{lista.length} de {fatos.length} {fatos.length === 1 ? "fato" : "fatos"}</span>
      </div>

      {semNada || filtrado ?
      EmptyState ?
      <EmptyState
        variant={semNada ? "first" : "no-results"}
        icon={JcIcon ? <JcIcon name={semNada ? "database" : "search"} /> : null}
        title={semNada ? "A Jana ainda não aprendeu nada sobre o seu negócio" : "Nenhum fato com esse filtro"}
        description={semNada ?
        "Ela guarda o que você conta durante a conversa — rotinas, preferências, jeito de cobrar. Comece perguntando algo na aba Conversa." :
        "Nada casa com a busca e a categoria escolhidas."}
        action={semNada ? null :
        <button className="jm-btn ghost" onClick={() => {setBusca("");setCat("todas");}}>Limpar filtro</button>} /> :


      <div className="jm-mem-empty">
            <b>{semNada ? "A Jana ainda não aprendeu nada." : "Nenhum fato com esse filtro."}</b>
            <small>Ela guarda o que você conversa com ela — pergunte algo na aba Conversa.</small>
          </div> :


      <div className="jm-mem-list">
          {lista.map((f) =>
        <div key={f.id} className={"jm-fato" + (apagando === f.id ? " danger" : "") + (editando === f.id ? " editando" : "")}>
              {editando === f.id ?
          <div className="jm-fato-edit">
                  <textarea value={rascunho.fato} rows={2}
            onChange={(e) => setRascunho((r) => ({ ...r, fato: e.target.value }))}
            aria-label="Texto do fato" />
                  <div className="jm-fato-edit-l">
                    <label>Categoria
                      <select value={rascunho.cat} onChange={(e) => setRascunho((r) => ({ ...r, cat: e.target.value }))}>
                        {Object.keys(JM_CAT_LABELS).map((c) => <option key={c} value={c}>{JM_CAT_LABELS[c]}</option>)}
                      </select>
                    </label>
                    <label>Relevância
                      <select value={rascunho.rel} onChange={(e) => setRascunho((r) => ({ ...r, rel: Number(e.target.value) }))}>
                        {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((n) => <option key={n} value={n}>{n}</option>)}
                      </select>
                    </label>
                    <label className="grow">Motivo da correção
                      <input value={rascunho.motivo} placeholder="fica no log de auditoria"
              onChange={(e) => setRascunho((r) => ({ ...r, motivo: e.target.value }))} />
                    </label>
                  </div>
                  <div className="jm-fato-edit-f">
                    <span>Toda correção registra autor, horário e motivo.</span>
                    <button className="jm-btn" disabled={!rascunho.fato.trim() || !rascunho.motivo.trim()} onClick={() => salvar(f)}>Salvar</button>
                    <button className="jm-btn ghost" onClick={() => setEditando(null)}>Cancelar</button>
                  </div>
                </div> :

          <>
                  <div className="jm-fato-bd">
                    <p>{f.fato}</p>
                    <div className="jm-fato-meta">
                      <span className={"jm-tag cat-" + f.cat}>{JM_CAT_LABELS[f.cat] || f.cat}</span>
                      <span>origem: {f.origem}</span>
                      <span>desde {f.desde}</span>
                      <span className="jm-rel-n" title={"Relevância " + f.rel + " de 10"}>relevância {f.rel}/10</span>
                      {f.editado && <span className="jm-fato-ed">editado por {f.editado}{f.motivo ? " · " + f.motivo : ""}</span>}
                    </div>
                  </div>
                  {apagando === f.id ?
            <div className="jm-fato-conf">
                      <span>Apagar é irreversível.</span>
                      <button className="jm-btn solid-danger" onClick={() => {setFatos((fs) => fs.filter((x) => x.id !== f.id));setApagando(null);}}>Apagar</button>
                      <button className="jm-btn ghost" onClick={() => setApagando(null)}>Manter</button>
                    </div> :

            <div className="jm-fato-acts">
                      <button className="jm-btn ghost" onClick={() => abrirEdicao(f)}>Editar</button>
                      <button className="jm-btn ghost danger" onClick={() => setApagando(f.id)}>Apagar</button>
                    </div>}

                </>}

            </div>
        )}
        </div>}

    </div>);

}

function JmThreadItem({ t, ativa, onEscolher }) {
  return (
    <button className={"jm-thread" + (ativa === t.id ? " active" : "")} onClick={() => onEscolher(t.id)}>
      <div className="jm-thread-h">
        <b>{t.title}</b>
        <span className="jm-thread-q">{t.quando}</span>
      </div>
      <small>{t.preview}</small>
      <div className="jm-thread-f">
        <span>{t.quando === "agora" ? "criada agora" : "última em " + t.quando}</span>
        {t.fix && <span className="jm-thread-fix">fixada</span>}
      </div>
    </button>);

}

function JmPropostas({ onAviso }) {
  const [pend, setPend] = useStateJM(JM_PROPOSTAS);
  const [aberto, setAberto] = useStateJM(false);
  const DIF = { facil: "Fácil", realista: "Realista", ambicioso: "Ambicioso" };
  if (pend.length === 0) return null;
  const fecha = (p, msg, tone) => {setPend((ps) => ps.filter((x) => x.id !== p.id));onAviso?.(msg, tone);};
  return (
    <div className={"jm-props" + (aberto ? " aberto" : "")}>
      <button className="jm-props-t" onClick={() => setAberto((v) => !v)} aria-expanded={aberto}>
        Propostas de metas<span className="jm-props-n">{pend.length}</span>
        <span className="jm-props-cx">{aberto ? "recolher" : "ver"}</span>
      </button>
      {aberto &&
      <div className="jm-props-grid">
        {pend.map((p) =>
        <div key={p.id} className="jm-prop">
            <div className="jm-prop-h">
              <b>{p.nome}</b>
              <span className={"jm-dif " + p.dificuldade}>{DIF[p.dificuldade]}</span>
            </div>
            <div className="jm-prop-tags">
              <span>{p.metrica}</span>
              <span>{p.periodo}</span>
              <span className="val">{p.valor_alvo.toLocaleString("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 })}</span>
            </div>
            <p>{p.racional}</p>
            {p.dependencias.length > 0 &&
          <div className="jm-prop-deps">{p.dependencias.map((d) => <span key={d}>{d}</span>)}</div>}
            <div className="jm-prop-acts">
              <button className="jm-btn" onClick={() => fecha(p, "Meta criada com sucesso!", "ok")}>Escolher esta meta</button>
              <button className="jm-btn ghost" onClick={() => fecha(p, "Proposta rejeitada.")}>Rejeitar</button>
            </div>
          </div>
        )}
      </div>}

    </div>);

}

function JmConversa({ data, ConverseComJana, threads, ativa, onAtiva, onNew, onAviso }) {
  const JcIcon = window.JcIcon;
  const [aberto, setAberto] = useStateJM(() => {
    try {return localStorage.getItem("oimpresso.jana.hist") !== "0";} catch (e) {return true;}
  });
  const [filtro, setFiltro] = useStateJM("todas");
  const [busca, setBusca] = useStateJM("");
  const liveRef = React.useRef(null);
  const buscaRef = React.useRef(null);
  // Tela estreita: o histórico vira sobreposição — nunca desaparece, o rail sempre tem o atalho.
  const [estreito, setEstreito] = useStateJM(() => typeof window.matchMedia === "function" && window.matchMedia("(max-width:1100px)").matches);
  React.useEffect(() => {
    if (typeof window.matchMedia !== "function") return;
    const mq = window.matchMedia("(max-width:1100px)");
    const on = (e) => {setEstreito(e.matches);if (e.matches) setAberto(false);};
    mq.addEventListener ? mq.addEventListener("change", on) : mq.addListener(on);
    if (mq.matches) setAberto(false);
    return () => {mq.removeEventListener ? mq.removeEventListener("change", on) : mq.removeListener(on);};
  }, []);

  const alternar = () => setAberto((v) => {
    const nv = !v;
    try {localStorage.setItem("oimpresso.jana.hist", nv ? "1" : "0");} catch (e) {}
    return nv;
  });

  // Busca acento-insensível + Fixadas/Recentes — as duas coisas que a Conversa viva
  // tem e o protótipo não tinha (Chat.tsx §ConvSidePanel, lido no `main`).
  const norm = (s) => s.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
  const q = norm(busca.trim());
  const cabe = (t) =>
  (filtro === "arquivadas" ? t.escopo === "arquivadas" : t.escopo !== "arquivadas") && (
  !q || norm(t.title + " " + (t.preview || "")).includes(q));
  const fixadas = threads.filter((t) => t.fix && cabe(t));
  const recentes = threads.filter((t) => !t.fix && cabe(t));
  const lista = [...fixadas, ...recentes];
  const atual = threads.find((t) => t.id === ativa);
  const anunciar = (t) => {if (liveRef.current && t) liveRef.current.textContent = "Conversa: " + t.title;};
  const escolher = (id) => {
    onAtiva(id);
    anunciar(threads.find((x) => x.id === id));
    if (estreito) alternar();
  };

  // Teclado (Larissa/Wagner trabalham no teclado): J/K anda no histórico, ⌘⇧H recolhe.
  React.useEffect(() => {
    const onKey = (e) => {
      const el = document.activeElement;
      const digitando = el && (el.tagName === "INPUT" || el.tagName === "TEXTAREA" || el.isContentEditable);
      if ((e.metaKey || e.ctrlKey) && e.shiftKey && (e.key === "h" || e.key === "H")) {e.preventDefault();alternar();return;}
      // ⌘K e ⌘N: os rótulos já prometem os dois — atalho que não funciona é rótulo mentindo.
      if ((e.metaKey || e.ctrlKey) && (e.key === "k" || e.key === "K")) {
        e.preventDefault();
        if (!aberto) alternar();
        setTimeout(() => buscaRef.current?.focus(), 0);
        return;
      }
      if ((e.metaKey || e.ctrlKey) && (e.key === "n" || e.key === "N") && onNew) {e.preventDefault();onNew();return;}
      if (digitando || e.metaKey || e.ctrlKey || e.altKey) return;
      if (e.key !== "j" && e.key !== "k" && e.key !== "J" && e.key !== "K") return;
      const i = lista.findIndex((t) => t.id === ativa);
      const prox = e.key.toLowerCase() === "j" ? Math.min(i + 1, lista.length - 1) : Math.max(i - 1, 0);
      if (lista[prox] && lista[prox].id !== ativa) {
        e.preventDefault();
        onAtiva(lista[prox].id);
        if (liveRef.current) liveRef.current.textContent = "Conversa: " + lista[prox].title;
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  });

  return (
    <div className={"jm-conv" + (aberto ? "" : " collapsed") + (estreito && aberto ? " overlay" : "")}>
      <aside className="jm-hist">
        <div className="jm-hist-h">
          <button className="jm-hist-toggle" onClick={alternar}
          title={aberto ? "Recolher histórico" : "Expandir histórico"}
          aria-expanded={aberto}>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              {aberto ? <path d="M15 6l-6 6 6 6" /> : <path d="M9 6l6 6-6 6" />}
            </svg>
          </button>
          {aberto && <span className="jm-hist-t">Conversas</span>}
          {aberto && <span className="jm-hist-n">{lista.length}</span>}
          {aberto && onNew &&
          <button className="jm-hist-new" onClick={() => onNew()} title="Nova conversa · ⌘N" aria-label="Nova conversa">
              {JcIcon ? <JcIcon name="plus" className="jm-tab-ic" /> : "+"}
            </button>}
        </div>
        {!aberto &&
        <button className="jm-hist-peek" onClick={alternar} title="Expandir histórico" aria-label="Expandir histórico">
            {JcIcon && <JcIcon name="list" className="jm-tab-ic" />}
            <span className="jm-hist-peek-l">Histórico</span>
            <span className="jm-hist-peek-n">{lista.length}</span>
          </button>}
        {aberto &&
        <>
            <label className="jm-hist-busca">
              {JcIcon && <JcIcon name="search" className="ic" />}
              <input type="search" ref={buscaRef} value={busca} onChange={(e) => setBusca(e.target.value)}
            placeholder="Buscar conversas..." aria-label="Buscar conversas" />
              <span className="kbd">⌘K</span>
            </label>
            <div className="jm-hist-filtros" role="tablist" aria-label="Filtros de conversa">
              {JM_FILTROS.map((f) =>
            <button key={f} role="tab" aria-selected={filtro === f}
            className={"jm-cat" + (filtro === f ? " active" : "")} onClick={() => setFiltro(f)}>{f}</button>
            )}
            </div>
            <div className="jm-hist-list">
              {filtro !== "arquivadas" &&
            <>
                  <div className="jm-hist-sec">Fixadas</div>
                  {fixadas.length === 0 ?
              <div className="jm-hist-empty">{q ? "Nenhuma fixada nesta busca" : "Arraste para fixar"}</div> :
              fixadas.map((t) => <JmThreadItem key={t.id} t={t} ativa={ativa} onEscolher={escolher} />)}
                </>}
              <div className="jm-hist-sec">{filtro === "arquivadas" ? "Arquivadas" : "Recentes"}</div>
              {recentes.length === 0 ?
            <div className="jm-hist-empty">{q ? "Nenhuma conversa nesta busca" : filtro === "arquivadas" ? "Nenhuma conversa arquivada" : "Nenhuma conversa ainda"}</div> :
            recentes.map((t) => <JmThreadItem key={t.id} t={t} ativa={ativa} onEscolher={escolher} />)}
            </div>
            <div className="jm-hist-keys"><span className="kbd">J</span><span className="kbd">K</span> anda · <span className="kbd">⌘K</span> busca · <span className="kbd">⌘⇧H</span> recolhe</div>
          </>}

      </aside>
      <div className="jm-conv-thread">
        <span ref={liveRef} className="jm-sr" aria-live="polite" />
        {atual &&
        <div className="jm-conv-h">
            <b>{atual.title}</b>
            {/* Conversa é sempre só sua: não existe compartilhamento na produção. */}
            <span className="jm-conv-h-m">só sua</span>
            {atual.escopo === "arquivadas" && <span className="jm-conv-h-arq">arquivada</span>}
          </div>}

        <ConverseComJana key={ativa} data={data} />
        {ativa === "t1" && <JmPropostas onAviso={onAviso} />}
      </div>
      {estreito && aberto && <button className="jm-conv-scrim" aria-label="Fechar histórico" onClick={alternar} />}
    </div>);

}

function JmConfigDrawer({ open, onClose, onGoTab, cfg, setCfg }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Drawer, DrawerSection, Button, Alert } = DS;

  const set = (k, v) => setCfg((c) => ({ ...c, [k]: v }));
  const Toggle = ({ k, label, sub, fixo }) =>
  <div className="jm-cfg-row">
      <div className="jm-cfg-tx"><b>{label}</b>{sub && <small>{sub}</small>}</div>
      <button className={"jm-sw" + (cfg[k] ? " on" : "") + (fixo ? " fixo" : "")}
    onClick={() => !fixo && set(k, !cfg[k])} aria-pressed={!!cfg[k]} aria-label={label}><i /></button>
    </div>;

  if (!Drawer || !open) return null;
  return (
    <Drawer open={open} onClose={onClose} width={520}
    title="Configurar a Jana"
    subtitle="O que ela observa, quando ela fala e até onde ela pode agir"
    footer={
    <div className="jm-drawer-foot">
          {Button ?
      <Button variant="ghost" onClick={onClose}>Fechar</Button> :
      <button className="jm-btn ghost" onClick={onClose}>Fechar</button>}
        </div>}>

      <DrawerSection title="Brief diário">
        <Toggle k="brief" label="Enviar brief todo dia" sub={"Gerado às " + cfg.briefHora + " — horário da empresa"} />
        <Toggle k="audio" label="Versão em áudio" sub="Narração do brief (TTS) — recurso do plano Pro" />
      </DrawerSection>
      <DrawerSection title="Análises que ela roda">
        <Toggle k="inad" label="Inadimplência" sub="Top devedores + buckets por idade" />
        <Toggle k="fat" label="Faturamento" sub="Curva 24 meses + sazonalidade" />
        <Toggle k="conc" label="Concentração" sub="Pareto de clientes" />
        <Toggle k="churn" label="Churn ouro" sub="LTV alto inativo >90d" />
        <Toggle k="frota" label="Frota" sub="Ativos parados >7d" />
        <Toggle k="cheq" label="Cheques" sub="Previsão de depósito" />
      </DrawerSection>
      <DrawerSection title="Até onde ela age">
        <Toggle k="hitl" label="Aprovação obrigatória (HITL)" sub="Toda ação passa por você — não pode ser desligado" fixo />
        {Alert ?
        <Alert tone="info" title="Isolamento por empresa">
            A Jana só lê e escreve dentro da empresa da sessão. Regra Tier 0, sem exceção.
          </Alert> :

        <p className="jm-dr-nota">A Jana só lê e escreve dentro da empresa da sessão (Tier 0).</p>}

      </DrawerSection>
      <DrawerSection title="Memória e privacidade">
        <div className="jm-cfg-row">
          <div className="jm-cfg-tx"><b>Retenção dos fatos</b><small>{cfg.retencao} · depois disso ela esquece sozinha</small></div>
          <button className="jm-btn ghost" onClick={() => {onClose?.();onGoTab?.("memoria");}}>Ver fatos</button>
        </div>
      </DrawerSection>
      <DrawerSection title="Plano">
        <div className="jm-cfg-row">
          <div className="jm-cfg-tx"><b>{cfg.pro ? "Jana Pro" : "Jana Grátis"}</b><small>Brief das 06h, análises e ações sugeridas são do Pro. Conversa, memória e metas são dos dois planos.</small></div>
          <button className={"jm-sw" + (cfg.pro ? " on" : "")} onClick={() => setCfg((c) => ({ ...c, pro: !c.pro }))} aria-pressed={!!cfg.pro} aria-label="Simular plano Pro"><i /></button>
        </div>
        <p className="jm-dr-nota">Aqui o Pro é simulação pra ver o gating. A ativação real vive em modo foco (<code>/ia/pro</code>, já em produção): página de decisão, sem abas.</p>
        <button className="jm-btn ghost" onClick={() => {onClose?.();onGoTab?.("pro");}}>Abrir Jana Pro</button>
      </DrawerSection>
    </Drawer>);

}

// Ação HITL: prévia da mensagem + idle → enviando → feito (nada sai sem aprovação).
function JmAcaoModal({ acao, onClose, onAviso }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Modal, Button } = DS;
  const [fase, setFase] = useStateJM("idle");
  if (!Modal || !acao) return null;
  const previa = {
    a1: "Seu Martinho, aqui é da ROTA LIVRE. Temos R$ 12.480 em aberto desde março. Consigo dividir em 3× sem juros se fecharmos hoje — posso gerar?",
    a2: "Seu Martinho, faz tempo que a gente não atende sua obra. Separei uma condição de retorno na caçamba de 5m³ — quer que eu reserve pra esta semana?",
    a3: "Lista dos 8 ativos parados com o último cliente de cada um, pra ligação hoje — começando pelos 3 overdue.",
    a4: "2.470 títulos acima de 365 dias, R$ 770k, marcados como candidatos a baixa. Nada é apagado — só sai do painel ativo depois da sua revisão." }[
  acao.id];
  const enviar = () => {
    setFase("enviando");
    setTimeout(() => {
      setFase("feito");
      onAviso?.(acao.cta.label + " aprovado — cada mensagem ainda pede seu OK antes de sair.", "ok");
    }, 900);
  };
  return (
    <Modal open={!!acao} onClose={onClose} width={520} title={acao.title}
    footer={
    <div className="jm-drawer-foot">
          {Button ?
      <>
              <Button variant="ghost" onClick={onClose}>{fase === "feito" ? "Fechar" : "Cancelar"}</Button>
              {fase !== "feito" &&
        <Button variant="primary" onClick={enviar} disabled={fase === "enviando"}>
                  {fase === "enviando" ? "Enviando…" : acao.cta.label}
                </Button>}
            </> :

      <button className="jm-btn" onClick={enviar}>{acao.cta.label}</button>}
        </div>}>

      <p className="jm-dr-nota">{acao.sub}</p>
      <div className="jm-previa">
        <span className="jm-previa-t">Prévia da mensagem</span>
        <p>{previa}</p>
      </div>
      <ul className="jm-dr-src">
        <li>Você aprova <b>cada</b> mensagem antes do envio — a Jana não dispara sozinha.</li>
        <li>Escopo <code>business_id</code> da sessão — nada cruza empresa.</li>
        {fase === "feito" && <li className="jm-ok">Fila criada. As mensagens aparecem na sua aprovação conforme a Jana prepara.</li>}
      </ul>
    </Modal>);

}

// Drill-down: todo número mostra de onde vem (anti-hook do charter do Painel).
function JmDrillDrawer({ analise, onClose, onPerguntar }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Drawer, DrawerSection, Button } = DS;
  if (!Drawer || !analise) return null;
  const FONTE = {
    // Fontes REAIS lidas de `SellsCockpitAggregator` (produção, JanaDrillDrawer.tsx).
    // As classes que estavam aqui (`AnaliseInadimplenciaService` etc.) não existem no repo:
    // um drawer chamado "de onde vem esse número" citando classe inexistente mente com selo
    // de autoridade. Recorte de base das quatro: type=sell · status=final · sub_type NULL.
    inad: ["transactions + contacts",
    "Vencida = a receber (paga em parte ou não paga) com prazo de pagamento definido e vencimento anterior a hoje. Venda sem prazo cadastrado não entra. As faixas agrupam pelos dias de atraso.",
    "SellsCockpitAggregator::buildInsightsAggregates"],
    fat: ["transactions",
    "Soma do total de cada venda, dia a dia, nos últimos 30 dias. Dia sem venda entra como zero — por isso a linha encosta no eixo em vez de pular o dia.",
    "SellsCockpitAggregator::buildCoworkAggregates"],
    conc: ["transactions + contacts",
    "Soma do total das vendas por cliente, os 5 maiores. Venda sem cliente identificado é somada como \"Cliente padrão\".",
    "SellsCockpitAggregator::buildInsightsAggregates"],
    metodos: ["transaction_payments + transactions",
    "Soma dos pagamentos registrados por forma de pagamento, as 5 maiores. Uma venda paga em duas formas aparece nas duas.",
    "SellsCockpitAggregator::buildInsightsAggregates"] }[
  analise.id];
  return (
    <Drawer open={!!analise} onClose={onClose} width={480}
    title={analise.title}
    subtitle={"De onde vem esse número · " + analise.sub}
    footer={
    <div className="jm-drawer-foot">
          {Button ?
      <>
              <Button variant="ghost" onClick={onClose}>Fechar</Button>
              {/* Sem semear a pergunta: medido na produção que `ChatController@novaConversa`
                  não aceita pergunta inicial — o rótulo promete só o que a rota entrega. */}
              <Button variant="primary" onClick={() => onPerguntar?.()}>Conversar com a Jana</Button>
            </> :

      <button className="jm-btn" onClick={onClose}>Fechar</button>}
        </div>}>

      {FONTE ?
      <DrawerSection title="Fonte">
          <ul className="jm-dr-src jm-dr-fonte">
            <li><span>Tabelas</span><code>{FONTE[0]}</code></li>
            <li><span>Regra</span>{FONTE[1]}</li>
            <li><span>Calculado por</span><code>{FONTE[2]}</code></li>
          </ul>
        </DrawerSection> :

      <DrawerSection title="Fonte">
          <p className="jm-dr-nota">Esta análise ainda não tem apurador na produção — o número aqui é do mock do protótipo. Enquanto não houver serviço real, este drawer não cita tabela nem método: nomear classe que não existe é pior que não explicar.</p>
        </DrawerSection>}

      {analise.footer &&
      <DrawerSection title="Leitura">
          <p className="jm-dr-nota">{analise.footer}</p>
        </DrawerSection>}

      <DrawerSection title="Escopo">
        <p className="jm-dr-nota">Apurado no carregamento desta página, restrito ao seu negócio (<code>business_id</code> da sessão). Nenhum dado de outro negócio entra nesta conta.</p>
      </DrawerSection>
    </Drawer>);

}

// Carregamento do painel (o real usa Inertia::defer — aqui o mesmo ritmo visual).
function JmPainelSkeleton({ compacto }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { Skeleton } = DS;
  if (!Skeleton) return <div className="jm-sk-nota">Carregando…</div>;
  return (
    <div className="jm-sk">
      {!compacto &&
      <div className="jm-sk-brief"><Skeleton variant="title" /><Skeleton variant="text" count={4} /></div>}
      {!compacto &&
      <div className="jm-sk-kpis">{[0, 1, 2, 3].map((i) => <div key={i} className="jm-sk-card"><Skeleton variant="caption" /><Skeleton variant="title" /></div>)}</div>}
      <div className="jm-sk-grid">{[0, 1, 2, 3].map((i) => <Skeleton key={i} variant="card" />)}</div>
    </div>);

}

function JmTabs({ tab, onGoTab, metasMode, nConversas }) {
  const JcIcon = window.JcIcon;
  const tabs = [
  { key: "painel", label: "Painel", icon: "chart" },
  ...(metasMode === "aba" ? [{ key: "metas", label: "Metas", icon: "target", n: JM_METAS_BASE.length }] : []),
  { key: "conversa", label: "Conversa", icon: "sparkles", n: nConversas },
  { key: "memoria", label: "Memória", icon: "database" }];

  return (
    <nav className="cli-moduletopnav jm-tabs" aria-label="Área Jana">
      {tabs.map((t) =>
      <button key={t.key}
      className={"cli-moduletopnav-tab " + (tab === t.key ? "active" : "")}
      onClick={() => onGoTab?.(t.key)}
      aria-current={tab === t.key ? "page" : undefined}>
          {JcIcon && <JcIcon name={t.icon} className="jm-tab-ic" />}
          <span>{t.label}</span>
          {t.n != null && <span className="cli-moduletopnav-n">{t.n}</span>}
        </button>
      )}
    </nav>);

}

function JanaPage({ company, tab = "painel", metasMode = "secao", estado = "dados", onGoTab }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const { EmptyState, Button } = DS;
  const { JanaHeader, BriefDiario, KPICard, AnaliseCard, AcaoRow, ConverseComJana, getJanaData, JcIcon } = window;
  const data = getJanaData ? getJanaData(company) : null;
  const [meta, setMeta] = useStateJM(null);
  const [config, setConfig] = useStateJM(false);
  const [threads, setThreads] = useStateJM(JM_THREADS);
  const [ativa, setAtiva] = useStateJM("t1");
  const [drill, setDrill] = useStateJM(null);
  const [acao, setAcao] = useStateJM(null);
  const [cfg, setCfg] = useStateJM(() => {
    const base = { brief: true, briefHora: "06:00", audio: false, pro: true,
      inad: true, fat: true, conc: true, churn: true, frota: true, cheq: true,
      hitl: true, retencao: "12 meses" };
    try {return { ...base, ...JSON.parse(localStorage.getItem("oimpresso.jana.cfg") || "{}") };} catch (e) {return base;}
  });
  React.useEffect(() => {
    try {localStorage.setItem("oimpresso.jana.cfg", JSON.stringify(cfg));} catch (e) {}
  }, [cfg]);
  const [hora, setHora] = useStateJM(null);
  const [carregando, setCarregando] = useStateJM(true);
  const cargaRef = React.useRef(null);
  React.useEffect(() => {
    setCarregando(true);
    clearTimeout(cargaRef.current);
    cargaRef.current = setTimeout(() => setCarregando(false), 650);
    return () => clearTimeout(cargaRef.current);
  }, [company && company.id]);
  const [aviso, setAviso] = useStateJM(null);
  const [tentando, setTentando] = useStateJM(false);
  const avisoRef = React.useRef(null);
  React.useEffect(() => () => clearTimeout(avisoRef.current), []);
  const avisar = (msg, tone) => {
    setAviso({ msg, tone: tone || "default" });
    clearTimeout(avisoRef.current);
    avisoRef.current = setTimeout(() => setAviso(null), 3200);
  };
  const tentarDeNovo = () => {
    setTentando(true);
    setTimeout(() => {
      setTentando(false);
      avisar("A consulta falhou de novo. Registrei o erro — o brief de hoje vai sair atrasado.", "danger");
    }, 1100);
  };
  if (!data) return null;

  const novaConversa = (pergunta) => {
    const id = "n" + Date.now();
    const titulo = pergunta || "Nova conversa";
    setThreads((ts) => [{ id, title: titulo, preview: pergunta ? "Puxei os números por trás dessa pergunta…" : "Sem mensagens ainda", quando: "agora", n: pergunta ? 3 : 0, escopo: "minhas", pergunta }, ...ts]);
    setAtiva(id);
  };
  const perguntar = (q) => {setMeta(null);setDrill(null);novaConversa(q);onGoTab?.("conversa");};

  const hh = hora || data.updatedAt;
  const upsell = (o) =>
  EmptyState ?
  <EmptyState variant="first" icon={JcIcon ? <JcIcon name={o.icon || "sparkles"} /> : null} title={o.t} description={o.d}
    action={<button className="jm-btn" onClick={() => onGoTab?.("pro")}>Ver Jana Pro</button>} /> :
  <div className="jm-mem-empty"><b>{o.t}</b><small>{o.d}</small></div>;
  const abrirMeta = (m) => setMeta(m);
  const analises = (data.analises || []).filter((a) => cfg[a.id] !== false);
  const pro = !!cfg.pro;
  const atualizar = () => {
    setCarregando(true);
    clearTimeout(cargaRef.current);
    cargaRef.current = setTimeout(() => {
      setCarregando(false);
      setHora(new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }));
      avisar(pro ?
      "Reapurado agora — brief, KPIs e análises deste período." :
      "Reapurado agora — KPIs e metas deste período (brief e análises são do Pro).", "ok");
    }, 750);
  };
  const falarComJana = () => {setMeta(null);onGoTab?.("conversa");};
  const tabs = <JmTabs tab={tab} onGoTab={onGoTab} metasMode={metasMode}
  nConversas={threads.filter((t) => t.escopo !== "arquivadas").length} />;
  const plano = <button className={"jm-plano" + (pro ? " pro" : "")} onClick={() => setConfig(true)} title="Plano atual · abre Configurar">plano {pro ? "Pro" : "Grátis"}</button>;
  const configDrawer = <JmConfigDrawer open={config} onClose={() => setConfig(false)} onGoTab={onGoTab} cfg={cfg} setCfg={setCfg} />;
  const { Toast, DropdownMenu } = DS;
  const exportar = DropdownMenu ?
  <DropdownMenu align="right" trigger={<button className="jm-btn ghost">Exportar</button>} items={[
    { id: "pdf", label: "Painel em PDF", onSelect: () => avisar("Gerando o painel em PDF — fora deste protótipo.") },
    { id: "csv", label: "Metas em CSV", onSelect: () => avisar("Exportando as metas do período em CSV — fora deste protótipo.") },
    { id: "sep", separator: true },
    { id: "fatos", label: "Fatos da memória (LGPD)", onSelect: () => avisar("Export de fatos exige log de auditoria — fora deste protótipo.", "warn") }]} /> :
  null;
  const toast = aviso && Toast ?
  <div className="jm-toast-wrap"><Toast tone={aviso.tone}>{aviso.msg}</Toast></div> :
  aviso ? <div className="jm-toast-wrap"><div className="jm-toast">{aviso.msg}</div></div> : null;
  const vazio = estado === "vazio";
  const erro = estado === "erro";
  const semDados = (titulo, desc, acao) =>
  EmptyState ?
  <EmptyState variant={erro ? "error" : "first"} icon={JcIcon ? <JcIcon name={erro ? "alert" : "sparkles"} /> : null} title={titulo} description={desc} action={acao} /> :
  <div className="jm-mem-empty"><b>{titulo}</b><small>{desc}</small></div>;

  // Jana Pro — modo FOCO (produção: /ia/pro, sem SubNav): decisão de compra, sem abas.
  if (tab === "pro") {
    const Pro = window.JanaProPage;
    if (Pro) return <Pro plano={pro ? "pro" : "free"} onVoltar={() => onGoTab?.("conversa")} onAviso={avisar} />;
  }

  if (tab === "conversa") {
    return (
      <div className="jc-page jc-page--ia" data-screen-label="Jana — Conversa">
        <JanaHeader company={company} person={data.person} biz={data.biz} updatedAt={hh}
        isChat onNew={novaConversa} plano={plano} exportar={exportar} onConfig={() => setConfig(true)} />
        {tabs}
        <JmConversa ConverseComJana={ConverseComJana} threads={threads} ativa={ativa} onAtiva={setAtiva}
        onNew={() => novaConversa()} onAviso={avisar}
        data={{ ...data, seed: jmSeed(ativa, data, threads) }} />
        {configDrawer}
        {toast}
      </div>);

  }

  if (tab === "memoria") {
    return (
      <div className="jc-page" data-screen-label="Jana — Memória">
        <JanaHeader company={company} person={data.person} biz={data.biz} updatedAt={hh} onConfig={() => setConfig(true)} plano={plano} exportar={exportar} onRefresh={atualizar} />
        {tabs}
        {carregando ? <JmPainelSkeleton compacto /> : <JmMemoria estado={estado} />}
        {configDrawer}
        {toast}
      </div>);

  }

  if (tab === "metas") {
    return (
      <div className="jc-page" data-screen-label="Jana — Metas">
        <JanaHeader company={company} person={data.person} biz={data.biz} updatedAt={hh} onConfig={() => setConfig(true)} plano={plano} exportar={exportar} onRefresh={atualizar} />
        {tabs}
        {carregando ? <JmPainelSkeleton compacto /> :
        <JmMetasSecao standalone onOpen={abrirMeta} vazio={vazio || erro} erro={erro} onAviso={avisar} />}
        <JmMetaDrawer meta={meta} onClose={() => setMeta(null)} onFalarComJana={falarComJana} onAviso={avisar} />
        {configDrawer}
        {toast}
      </div>);

  }

  return (
    <div className="jc-page" data-screen-label="Jana — Painel">
      <JanaHeader company={company} person={data.person} biz={data.biz} updatedAt={hh} onConfig={() => setConfig(true)} plano={plano} exportar={exportar} onRefresh={atualizar} />
      {tabs}
      <div className="jm-nota-mob">O painel foi desenhado pro escritório (1280px). No celular, a Conversa dá conta — os cards abaixo ficam apertados.</div>
      {carregando ? <JmPainelSkeleton /> :
      vazio || erro ?
      semDados(
        erro ? "Não consegui ler os dados da empresa agora" : "A Jana ainda não tem histórico pra analisar",
        erro ?
        "A consulta ao ERP falhou. Nada foi perdido — tente de novo em instantes; se insistir, o brief de hoje sai atrasado." :
        "Ela precisa de pelo menos um mês de movimento pra montar o brief, os KPIs e as análises. Enquanto isso, pergunte o que quiser na aba Conversa.",
        Button ?
        <Button variant={erro ? "primary" : "ghost"} onClick={() => erro ? tentarDeNovo() : onGoTab?.("conversa")} disabled={tentando}>
            {erro ? tentando ? "Tentando…" : "Tentar de novo" : "Ir para a Conversa"}
          </Button> :
        <button className="jm-btn" onClick={() => erro ? tentarDeNovo() : onGoTab?.("conversa")}>{erro ? "Tentar de novo" : "Ir para a Conversa"}</button>) :

      <>
          {pro ?
        cfg.brief && <BriefDiario today={data.today} brief={data.brief} onChip={perguntar}
        onAudio={cfg.audio ? () => avisar("Narração do brief (TTS) entra na M2 — fora deste protótipo.") : null} /> :
        upsell({ t: "O brief diário é do plano Pro", icon: "calendar",
          d: "Toda manhã às 06h a Jana escreve o que aconteceu, o que está crítico e o que fazer hoje — com os números da sua empresa. No Grátis, você pergunta; no Pro, ela adianta." })}

          <div className="jc-kpis">
            {data.kpis.map((k, i) => {
            const alvo = pro ? analises.find((a) => a.id === JM_KPI_DRILL[k.icon]) : null;
            return alvo ?
            <div key={i} className="jm-an-hit" role="button" tabIndex={0}
            onClick={() => setDrill(alvo)}
            onKeyDown={(e) => {if (e.key === "Enter" || e.key === " ") {e.preventDefault();setDrill(alvo);}}}
            aria-label={"Ver origem de " + k.label}>
                  <KPICard kpi={k} />
                </div> :
            <KPICard key={i} kpi={k} />;
          })}
          </div>

          {metasMode === "secao" && <JmMetasSecao onOpen={abrirMeta} onAviso={avisar} />}

          <h2 className="jc-h2">
            {JcIcon && <JcIcon name="chart" className="ic" />} ANÁLISES PRINCIPAIS
            {pro && <span className="jm-h2-sub">clique num card pra ver de onde vem o número</span>}
          </h2>
          {!pro ?
        upsell({ t: "As 6 análises são do plano Pro", icon: "chart",
          d: "Inadimplência, faturamento, concentração, churn ouro, frota e cheques — recalculadas todo dia, com drill-down até a origem do número." }) :
        analises.length === 0 ?
        <div className="jm-mem-empty"><b>Todas as análises estão desligadas.</b><small>Ligue de volta em Configurar → Análises que ela roda.</small></div> :

        <div className="jc-grid">
              {analises.map((a) =>
          <div key={a.id} className="jm-an-hit" role="button" tabIndex={0}
          onClick={() => setDrill(a)}
          onKeyDown={(e) => {if (e.key === "Enter" || e.key === " ") {e.preventDefault();setDrill(a);}}}
          aria-label={"Ver origem de " + a.title}>
                  <AnaliseCard a={a} />
                </div>
          )}
            </div>}


          {pro &&
        <>
              <h2 className="jc-h2">{JcIcon && <JcIcon name="bulb" className="ic" />} AÇÕES QUE {data.person.name.toUpperCase()} SUGERE</h2>
              <div className="jc-acoes">
                {data.acoes.map((a) => <AcaoRow key={a.id} a={a} onCta={setAcao} />)}
              </div>
            </>}

        </>}


      <JmMetaDrawer meta={meta} onClose={() => setMeta(null)} onFalarComJana={falarComJana} onAviso={avisar} />
      <JmDrillDrawer analise={drill} onClose={() => setDrill(null)} onPerguntar={perguntar} />
      <JmAcaoModal acao={acao} onClose={() => setAcao(null)} onAviso={avisar} />
      {configDrawer}
      {toast}
    </div>);

}

Object.assign(window, { JanaPage, JmTabs, JmThreadItem, JmPropostas, JmMetasSecao, JmMemoria, JmConversa, JmConfigDrawer, JmAcaoModal, JmDrillDrawer });
