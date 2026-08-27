// arquivos-page.jsx — Arquivos · DMS backbone (Modules/Arquivos · ADR 0123 · US-ARQ-013).
// FATO DO REPO: o módulo NÃO tem tela — `DataController::modifyAdminMenu()` é NO-OP de propósito
// ("backbone consumido via trait HasArquivos") e o docblock aponta o destino: Pages/Arquivos no
// Admin Center (Sprint 2). Esta é a proposta F1 dessa tela. Dados/domínio em arquivos-data.jsx.
// Quatro vistas: acervo · retenção (grace + avisos) · cofre (achados + dry-run) · trilha.
// Ondas de refino: classificar (classified_by/at + LogsActivity) · restaurar no grace ·
// anonymize como estratégia (retention.php) · dono clicável · dry-run do retention-cleanup ·
// alvo de toque.
// 2026-08-26 (paridade com o vivo): o aviso ao titular deixou de ser botão ligado — LGPD
// Art. 18 §VI é config ASPIRACIONAL, não existe caminho que envie (o vivo declara isso). E a
// Retenção agora diz se o `arquivos:retention-cleanup` está agendado, em vez de mandar conferir.
// Proibições que o desenho carrega: sem upload aqui · sem PII na vista de governança ·
// sem purge cross-tenant · sem hard-delete direto pela UI · trilha nunca editada.
// Expõe window.ArquivosPage.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const A = () => window.AcessosDS || {};
const D = () => window.ArqData || {};

const VISTAS = [
  { id: "acervo", label: "Acervo" },
  { id: "retencao", label: "Retenção" },
  { id: "cofre", label: "Cofre" },
  { id: "trilha", label: "Trilha" },
];
// Os buckets que o CuradorEngine de fato grava. `common` e `public` NUNCA existiram no enum
// do banco — eram palpite meu, e a tela derivada daqui nasceu filtrando por valor inexistente
// (lista sempre vazia, pega no smoke de produção em 2026-08-25). O enum tem 7; `user`/`spec`/
// `ambiguous` não são escritos por caminho vivo, ficam fora do filtro até que sejam.
// Rótulo em PT-BR: o valor do enum é do banco, não da tela (`v` guarda o técnico pê title).
// ⚠️ DRIFT DS↔REPO (achado 2026-08-25, com recibo nos dois arquivos):
// No espelho DS (`_ds_bundle.js` StatusBadge, caminho `tone`), `danger` é **SÓLIDO**:
// `{ bg: var(--color-destructive), fg: '#fff' }`. No repo (`Components/ui/badge.tsx`),
// `danger` é o par SOFT (`bg-destructive-soft`) e o sólido chama-se `destructive`.
// Mesma palavra, renderização oposta — e é por isso que a travessia pro `Index.tsx`
// trocou `tone="danger"` por `variant="destructive"`: **visualmente fiel ao espelho**.
// Quem viola o AP7 ("fundo tintado 6% + borda 22%, nunca fill") primeiro é o próprio
// caminho `tone` do espelho. Consertar lá é decisão [W] — aqui eu paro de usar `tone`.
//
// As únicas famílias SOFT do espelho são as namespaced (`sla-*`, `fresc-*`, `tipo-*`,
// `canal-*`). Classificação não tem família própria, então uso `kind="sla"` pela COR e
// sobrescrevo o rótulo (`label` vence `e.label` no componente). É empréstimo de paleta,
// não de semântica — declarado aqui pra ninguém ler `sla` e achar que é prazo.
const BUCKET = { sensitive: { k: "expired", l: "Sensível", v: "sensitive" }, active: { k: "fresh", l: "Em uso", v: "active" }, memory: { k: "late", l: "Histórico", v: "memory" }, discard: { k: "aging", l: "Descartar", v: "discard" } };
const BUCKET_FALLBACK = { k: null, l: "sem classificação", v: "—" };
// Visibilidade é outro eixo (quem vê), com enum próprio — mesmo tratamento: valor do banco
// no `title`, rótulo em PT-BR na tela.
// `business` existe no enum e cai no mesmo significado de `internal` (quem tem acesso ao
// negócio) — sem a chave, arquivo com visibility=business mostrava "—" aqui e "Equipe" na
// produção. Divergência de mapa, não de regra.
const VIS = { private: "Restrito", internal: "Equipe", business: "Equipe", public: "Aberto" };
const VS = (v) => VIS[v] || "—";
const BK = (b) => BUCKET[b] || BUCKET_FALLBACK;
// A trilha é LOG: cor marca só o excepcional, o resto fica mudo (chip neutro). Nove cores
// numa tabela de auditoria é ruído, não informação. `k` = família soft do espelho; `null` =
// chip neutro. Ver a nota de DRIFT acima: no espelho, `tone` colorido é fill sólido.
const ACAO = { upload: { k: null, l: "Envio" }, download: { k: null, l: "Baixa" }, signed_url: { k: "aging", l: "Link assinado" }, soft_delete: { k: "aging", l: "Exclusão" }, restore: { k: "fresh", l: "Restauração" }, hard_delete: { k: "expired", l: "Exclusão definitiva" }, classify: { k: null, l: "Classificação" }, anonymize: { k: "aging", l: "Anonimização" }, notice: { k: null, l: "Aviso ao titular" } };
const AC = (a) => ACAO[a] || { k: null, l: a };

const IcLock = () => <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>;
const IcFile = () => <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>;
const IcDown = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3v13M7 12l5 5 5-5M4 21h16"/></svg>;
const IcAlert = () => <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"><path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/></svg>;
const IcTag = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M20 12l-8 8-8-8V4h8z"/><path d="M8.5 8.5h.01"/></svg>;
const IcUndo = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="M4 10h10a5 5 0 1 1 0 10H8"/><path d="M4 10l4-4M4 10l4 4"/></svg>;
const IcBell = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>;
const IcTrash = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg>;

function Btn({ children, ...p }) {
  const { Button } = DS();
  // O Button do DS não repassa aria-label: para ação só-ícone o nome vai num
  // <span class="sr-only"> dentro do children, senão o leitor de tela diz só "botão".
  const rotulo = p["aria-label"];
  const conteudo = rotulo ? <>{children}<span className="sr-only">{rotulo}</span></> : children;
  if (!Button) return <button className={"os-btn " + (p.variant === "primary" ? "primary" : "ghost")} aria-label={rotulo} disabled={p.disabled} onClick={p.onClick}>{conteudo}</button>;
  return <Button {...p} title={p.title || rotulo}>{conteudo}</Button>;
}
function DonoLink({ a }) {
  const { ROTA_DONO } = D();
  const { Tooltip } = DS();
  if (!a.dono) {
    const sel = <span className="arq-orfao"><IcAlert /> órfão</span>;
    return Tooltip ? <Tooltip content="Arquivo sem arquivable — ninguém alcança pela tela do dono. Órfão é achado, não item de lista.">{sel}</Tooltip> : sel;
  }
  const rota = ROTA_DONO[a.tipo];
  return (
    <span className="arq-dono">
      {rota
        ? <button className="arq-dono-lk" onClick={(e) => { e.stopPropagation(); window.__selectRoute?.(rota); }}>{a.dono}</button>
        : <b>{a.dono}</b>}
      <small className="mono">{a.tipo}</small>
    </span>
  );
}

// ─── Acervo ─────────────────────────────────────────────────────────────────
function Acervo({ arquivos, papel, onBaixar, onExcluir, onClassificar }) {
  const { DataTable, StatusBadge, Input } = DS();
  const { Vazio } = A();
  const { CFG, politica, restam, vence, dt, tam } = D();
  const [q, setQ] = useState("");
  const [bucket, setBucket] = useState("todos");
  const termo = q.trim().toLowerCase();
  const vivos = arquivos.filter((a) => !a.del);
  const lista = useMemo(() => vivos.filter((a) =>
    (bucket === "todos" || a.bucket === bucket) &&
    (!termo || (a.nome + " " + (a.dono || "") + " " + a.sub).toLowerCase().includes(termo))), [vivos, bucket, termo]);
  const podeMexer = papel === "gestor";

  // UMA coluna sem `width` é o que torna a tabela realmente fluida: em `table-layout:fixed`,
  // se TODAS as colunas têm largura declarada, a soma vira o piso da tabela e `width:100%`
  // não encolhe nada — foi por isso que 972px estourava em qualquer container menor. Sem
  // largura, `arq` absorve a sobra (e devolve, quando falta). As 6 fixas somam 722px
  // (160+120+88+84+130+140) — o "702" que este comentário dizia era de uma versão anterior
  // das larguras e virou número que mente: comentário com aritmética envelhece calado, e foi
  // por um número desses (110 vs 88, do export velho) que a travessia discutiu a premissa errada.
  const colunas = [
    { key: "arq", label: "Arquivo" },
    { key: "dono", label: "Vinculado a", width: 160 },
    { key: "bucket", label: "Classificação", width: 120 },
    { key: "disco", label: "Disco", width: 88 },
    { key: "tamanho", label: "Tamanho", width: 84, align: "right", mono: true },
    { key: "vence", label: "Vence em", width: 130 },
    { key: "acao", label: "", width: 140, align: "right" },
  ];
  const linhas = lista.map((a) => {
    const r = restam(a);
    return {
      id: a.id,
      state: !a.dono || r <= 30 ? "urgent" : a.anon ? "archived" : undefined,
      cells: {
        arq: (
          <span className="arq-file">
            <span className="arq-file-ic" aria-hidden="true"><IcFile /></span>
            <span className="arq-file-m">
              <b title={a.nome}>{a.nome}</b>
              <small title={`${politica(a.sub).label} · ${a.sub}${a.porQuem ? ` · classificado por ${a.porQuem}` : " · sem classificação humana"}`}>{politica(a.sub).label} · <code>{a.sub}</code>{a.porQuem ? ` · classificado por ${a.porQuem}` : " · sem classificação humana"}</small>
            </span>
          </span>
        ),
        dono: <DonoLink a={a} />,
        bucket: (
          <span className="arq-cls">
            {StatusBadge && BK(a.bucket).k ? <StatusBadge kind="sla" value={BK(a.bucket).k} label={BK(a.bucket).l} title={BK(a.bucket).v} /> : <span className="arq-disk">{BK(a.bucket).l}</span>}
            <small className="mono" title={a.vis}>{VS(a.vis)}</small>
          </span>
        ),
        disco: a.enc ? <span className="arq-vault"><IcLock /> vault</span> : <span className="arq-disk mono">{a.disk}</span>,
        tamanho: a.anon ? "—" : tam(a.bytes),
        // `frescor` é IDADE (recente/frio/distante) e estava sendo usado pra PRAZO — dava a
        // pílula verde "recente · em 1824 dias". Prazo é domínio do `sla`, que já rotula em PT-BR
        // (No prazo · Vencendo · Vencido). A contagem vai ao lado, não dentro da pílula.
        // A contagem só aparece quando decide algo (≤90 dias ou já vencido). "em 1824 dias"
        // ao lado da data é o mesmo número dito duas vezes — e era ela que estourava a
        // célula (144px de conteúdo em 116px de coluna, medido 2026-08-25).
        vence: <span className="arq-vence"><b className="mono">{dt(vence(a))}</b>{StatusBadge
          ? <span className="arq-vence-l"><StatusBadge kind="sla" value={r <= 0 ? "expired" : r <= 30 ? "aging" : "fresh"} />{r <= 90 ? <small className="mono">{r <= 0 ? `${-r}d vencido` : `${r}d`}</small> : null}</span>
          : <small>{r} dias</small>}</span>,
        // Ação por linha é SÓ-ÍCONE. Com texto ("Avisar·Baixar·Classificar·Excluir") são ~280px
        // de conteúdo numa coluna de 120 — os botões transbordavam a célula e encavalavam na
        // coluna vizinha (relatado por [W] em 2026-08-25). O nome vai no `aria-label`, que o
        // `Btn` transforma em `title` + `sr-only`: leitor de tela e hover continuam dizendo
        // o que faz. Repetir 4 rótulos × 10 linhas também era ruído numa tabela densa.
        acao: (
          <div className="arq-acts">
            {/* O sino saiu daqui em 2026-08-26: aviso ao titular NÃO existe no sistema — o prazo
                de notice é config aspiracional (o vivo diz isso com todas as letras). Ação de
                linha pra função inexistente é a tela prometendo o que o backend não faz. */}
            <Btn size="sm" disabled={a.anon} onClick={() => onBaixar(a)} aria-label="Baixar"><IcDown /></Btn>
            {podeMexer && <Btn size="sm" onClick={() => onClassificar(a)} aria-label="Classificar"><IcTag /></Btn>}
            {podeMexer && <Btn size="sm" onClick={() => onExcluir(a)} aria-label="Excluir"><IcTrash /></Btn>}
          </div>
        ),
      },
    };
  });

  return (
    <>
      <div className="arq-toolbar" data-contract="acervo-filtros">
        <div className="arq-chips">
          {["todos", "sensitive", "active", "memory", "discard"].map((b) => (
            <button key={b} className={"arq-chip" + (bucket === b ? " active" : "")} onClick={() => setBucket(b)} title={b === "todos" ? undefined : BK(b).v}>
              {b === "todos" ? "Todos" : BK(b).l}<span className="mono">{b === "todos" ? vivos.length : vivos.filter((a) => a.bucket === b).length}</span>
            </button>
          ))}
        </div>
        <div className="arq-busca">
          {Input
            ? <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nome, dono ou contexto…" aria-label="Buscar arquivo" />
            : <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar…" />}
        </div>
      </div>

      <div className="arq-lista" data-contract="acervo">
        {vivos.length === 0
          ? (Vazio ? <Vazio variant="first" title="Nenhum arquivo guardado ainda."
              description="O acervo enche sozinho: XML de NF-e autorizada, foto de OS, anexo de ticket. Nada é enviado por esta tela — ela administra o que os módulos guardaram." /> : <p>Nenhum arquivo.</p>)
          : lista.length === 0
            ? (Vazio ? <Vazio variant="no-results" title="Nada com esse filtro." description="Troque a classificação ou limpe a busca." /> : <p>Nada.</p>)
            : DataTable ? <DataTable columns={colunas} rows={linhas} /> : <p>{lista.length} arquivos</p>}
      </div>

      <p className="arq-fine">
        O acervo é administrativo: o arquivo continua sendo alcançado pela tela do dono (clique no dono e você vai pra lá).
        Baixar do <b>vault</b> passa sempre pelo <code>DownloadController</code> — <code>Storage::url</code> direto não serve arquivo cifrado (ADR 0123 §6). Link assinado expira em {CFG.signedMin} min.
        {" "}Esta tela não envia arquivo: upload entra pelos módulos, via trait <code>HasArquivos</code>.
        {!podeMexer && " Sua função lê e baixa; classificar e apagar é do gestor."}
      </p>
    </>
  );
}

// ─── Retenção ───────────────────────────────────────────────────────────────
function Retencao({ arquivos, papel, onRestaurar, onPurgar }) {
  const { DataTable, StatusBadge, Tooltip } = DS();
  const { Nota, Kpis, Kpi } = A();
  const { POLITICA, CFG, restam, noGrace, graceRestante, precisaAviso, politica, tam } = D();
  const vivos = arquivos.filter((a) => !a.del);
  const venceEm = (d) => vivos.filter((a) => restam(a) > 0 && restam(a) <= d);
  const passou = vivos.filter((a) => restam(a) <= 0);
  const grace = arquivos.filter((a) => noGrace(a));
  const avisos = vivos.filter((a) => precisaAviso(a));
  const podeMexer = papel === "gestor";

  const colunas = [
    { key: "ctx", label: "Contexto" },
    { key: "prazo", label: "Prazo", width: 110, align: "right", mono: true },
    { key: "lei", label: "Base legal" },
    { key: "qtd", label: "Arquivos", width: 95, align: "right", mono: true },
  ];
  const linhas = POLITICA.map((p) => ({
    id: p.sub,
    cells: {
      ctx: <span className="arq-dono"><b>{p.label}</b><small className="mono">{p.sub}</small></span>,
      prazo: p.dias >= 365 ? `${Math.round(p.dias / 365)} anos` : `${p.dias} dias`,
      lei: <span className="arq-lei">{p.lei}</span>,
      qtd: vivos.filter((a) => a.sub === p.sub).length,
    },
  }));

  return (
    <>
      {Kpis &&
        <div data-contract="retencao-kpis">
          <Kpis>
            <Kpi v={venceEm(30).length} l="Vence em 30 dias" tone={venceEm(30).length ? "warning" : "default"} sub="ainda dá pra exportar" />
            <Kpi v={venceEm(90).length} l="Vence em 90 dias" sub="janela de planejamento" />
            <Kpi v={grace.length} l="No grace period" sub={`${CFG.grace} dias pra restaurar`} />
            <Kpi v={passou.length} l="Passou do prazo" tone={passou.length ? "danger" : "success"} sub="e não foi deletado" />
          </Kpis>
        </div>}

      {passou.length > 0 && Nota &&
        <div className="arq-nota">
          <Nota tone="danger" title={`${passou.length} arquivo passou do prazo + grace e continua no disco`}>
            É exatamente o WARN do <code>HealthCheckCommand</code> (check #4): guardar dado além da finalidade é o oposto do que a LGPD Art. 16 pede.
            Quem apaga é o <code>arquivos:retention-cleanup</code>, com <code>strategy={CFG.strategy}</code>.
          </Nota>
        </div>}

      {avisos.length > 0 &&
        <section className="arq-bloco" data-contract="avisos-titular">
          <header className="arq-bloco-h">
            <h3>Aviso ao titular pendente</h3>
            <small>LGPD Art. 18 §VI · classificação Sensível com titular identificado, a ≤{CFG.notice} dias do prazo — <b>ainda não implementado</b>, é config aspiracional</small>
          </header>
          <ul className="arq-rows">
            {avisos.map((a) => (
              <li key={a.id}>
                <span className="arq-row-m"><b>{a.nome}</b><small>titular: {a.titular} · vence em {restam(a)} dias · {politica(a.sub).lei}</small></span>
                {Tooltip
                  ? <Tooltip content="O aviso ao titular não existe no sistema: o prazo de notice é config aspiracional. Ligar isto é onda 3 e depende da proposta de ADR arquivos-retencao-ui-aviso-titular."><span><Btn size="sm" disabled><IcBell /> Avisar titular</Btn></span></Tooltip>
                  : <Btn size="sm" disabled><IcBell /> Avisar titular</Btn>}
              </li>
            ))}
          </ul>
          <p className="arq-fine">
            Esta lista é <b>sinal, não fila de trabalho</b>: o aviso ao titular ainda não é enviado por caminho nenhum — nem pela tela, nem por job.
            O prazo de {CFG.notice} dias está em <code>Config/config.php</code> como intenção, e desenhar o botão ligado seria a tela prometendo o que o backend não faz.
            Quando existir, o aviso sai por canal de Notification e entra na trilha como <code>notice</code> (onda 3, proposta de ADR <code>arquivos-retencao-ui-aviso-titular</code>).
          </p>
        </section>}

      <section className="arq-bloco" data-contract="grace">
        <header className="arq-bloco-h">
          <h3>No grace period</h3>
          <small>soft-delete feito · {CFG.grace} dias pra restaurar antes do apagar de verdade</small>
        </header>
        {grace.length === 0
          ? <p className="arq-vazio-in">Nada esperando: nenhum arquivo foi apagado nos últimos {CFG.grace} dias.</p>
          : <ul className="arq-rows">
            {grace.map((a) => (
              <li key={a.id} className={graceRestante(a) <= 3 ? "quente" : undefined}>
                <span className="arq-row-m">
                  <b>{a.nome}</b>
                  <small>{a.dono || "órfão"} · {tam(a.bytes)} · {StatusBadge ? "" : ""}restam <b className="mono">{graceRestante(a)}</b> {graceRestante(a) === 1 ? "dia" : "dias"} de grace</small>
                </span>
                {podeMexer ? <div className="arq-acts">
                  <Btn size="sm" onClick={() => onRestaurar(a)}><IcUndo /> Restaurar</Btn>
                  {Tooltip
                    ? <Tooltip content="Antecipar o hard_delete do job. Depois disso não tem volta — e a trilha guarda quem antecipou."><span><Btn size="sm" onClick={() => onPurgar(a)}>Apagar agora</Btn></span></Tooltip>
                    : <Btn size="sm" onClick={() => onPurgar(a)}>Apagar agora</Btn>}
                </div> : <span className="arq-disk">só o gestor restaura</span>}
              </li>
            ))}
          </ul>}
      </section>

      <div className="arq-lista" data-contract="retencao-politica">
        {DataTable ? <DataTable columns={colunas} rows={linhas} /> : <p>{POLITICA.length} contextos</p>}
      </div>

      <div className="arq-cards" data-contract="retencao-regras">
        <div className="arq-card"><span className="arq-card-l">Grace period</span><b className="mono">{CFG.grace} dias</b><small>janela pra restaurar depois do prazo vencer</small></div>
        <div className="arq-card"><span className="arq-card-l">Aviso ao titular</span><b className="mono">{CFG.notice} dias</b><small>LGPD Art. 18 §VI — <b>ainda não implementado</b>, é config aspiracional</small></div>
        <div className="arq-card"><span className="arq-card-l">Estratégia</span><b className="mono">{CFG.strategy}</b><small>apagar de verdade; <code>anonymize</code> disponível por arquivo</small></div>
        {/* Negócio na tela, técnico no `title`: `ds/no-db-jargon-in-ui` proíbe nome de coluna
            em texto visível, e quem lê quer saber que o job não atravessa empresas. */}
        <div className="arq-card"><span className="arq-card-l">Escopo do job</span><b title="business_id (ADR 0093)">Uma empresa por vez</b><small>nunca atravessa empresas (ADR 0093)</small></div>
      </div>

      <p className="arq-fine">
        {CFG.agendado
          ? <>O <code>arquivos:retention-cleanup</code> está <b>agendado</b> — os números acima são o que ele encontraria na próxima execução. </>
          : <>O <code>arquivos:retention-cleanup</code> <b>não está agendado</b>: ele existe, está registrado, e só roda se alguém digitar o comando. Os números acima dizem o que ele encontraria — não o que vai acontecer sozinho. </>}
        Prazo é lei, não preferência: mudar um número aqui muda <code>Config/retention.php</code> <b>e</b> <code>config.php</code> (são espelho, e divergir é achado de auditoria).
        A trilha (<code>arquivos_audit_log</code>) <b>nunca</b> é purgada, mesmo quando o arquivo é.
      </p>
    </>
  );
}

// ─── Cofre ──────────────────────────────────────────────────────────────────
function Cofre({ arquivos, onDryRun, dryRun }) {
  const { Nota } = A();
  const { CFG, tam, restam, noGrace } = D();
  const vivos = arquivos.filter((a) => !a.del);
  const porDisco = ["vault", "local"].map((d) => ({
    disco: d,
    n: vivos.filter((a) => a.disk === d).length,
    bytes: vivos.filter((a) => a.disk === d).reduce((s, a) => s + a.bytes, 0),
  }));
  const acimaDoCap = vivos.filter((a) => a.bytes > CFG.vaultCap * 1048576);
  const orfaos = vivos.filter((a) => !a.dono);
  const dupes = useMemo(() => {
    const m = {};
    vivos.forEach((a) => { m[a.md5] = (m[a.md5] || []).concat(a); });
    return Object.values(m).filter((g) => g.length > 1);
  }, [vivos]);

  return (
    <>
      <div className="arq-cards" data-contract="cofre-discos">
        {porDisco.map((d) => (
          <div className="arq-card" key={d.disco}>
            <span className="arq-card-l">{d.disco === "vault" ? "Cofre (cifrado)" : "Disco comum"}</span>
            <b className="mono">{tam(d.bytes)}</b>
            <small>{d.n} arquivos · {d.disco === "vault" ? "AES-256 via Crypt::encryptString" : "servido por Storage::url"}</small>
            {/* SEM barra de progresso: o denominador era 5 GB de mock e não existe quota por
                disco em `Config/config.php` — a barra sugeria um teto que ninguém definiu. Se
                um dia houver quota configurada, ela volta com significado. */}
          </div>
        ))}
      </div>

      <div className="arq-achados" data-contract="cofre-achados">
        <div className="arq-achados-h">
          <h3>Achados</h3>
          <Btn size="sm" onClick={onDryRun}>Rodar dry-run do cleanup</Btn>
        </div>
        <ul>
          <li>
            <b className="arq-ach-t">{acimaDoCap.length} arquivo acima do cap de {CFG.vaultCap} MB</b>
            <span>O <code>VaultEncryptionService</code> carrega o arquivo inteiro em memória: acima do cap o processo entra em OOM e a cifragem é <b>recusada</b>, não silenciosa. Chunked encryption é Sprint 2 (ADR 0126).</span>
            {acimaDoCap.map((a) => <code className="arq-ach-file" key={a.id}>{a.nome} · {tam(a.bytes)}</code>)}
          </li>
          <li>
            <b className="arq-ach-t">{orfaos.length} órfão (sem <code>arquivable</code>)</b>
            <span>Ninguém alcança pela tela do dono — ou vincula, ou apaga. Órfão que ninguém apaga é custo de disco com risco de PII.</span>
            {orfaos.map((a) => <code className="arq-ach-file" key={a.id}>{a.nome}</code>)}
          </li>
          <li>
            <b className="arq-ach-t">{dupes.length} grupo com MD5 repetido</b>
            <span>Mesmo conteúdo guardado duas vezes. Nem sempre é erro (foto antes/depois de OS pode ser legítima) — o MD5 só aponta.</span>
            {dupes.map((g, i) => <code className="arq-ach-file" key={i}>{g.map((a) => a.nome).join(" = ")}</code>)}
          </li>
        </ul>
      </div>

      {dryRun &&
        <section className="arq-dry" data-contract="dry-run">
          <header className="arq-bloco-h">
            <h3>Dry-run · nada foi apagado</h3>
            <small><code>php artisan arquivos:retention-cleanup --dry-run</code> · escopo <code>business_id=4</code></small>
          </header>
          <pre className="arq-pre">{dryRun.join("\n")}</pre>
          <p className="arq-fine">O dry-run é leitura: ele diz o que o agendado faria hoje. Rodar de verdade é do job, com a política — não desta tela.</p>
        </section>}

      {Nota &&
        <div className="arq-nota">
          <Nota tone="info" title="Os três achados são sinal, não fila de trabalho">
            Esta tela não apaga, não vincula e não recifra nada. Quem apaga é o <code>arquivos:retention-cleanup</code>, com política;
            quem recifra é o <code>arquivos:reencrypt-vault</code>; e o retrato de saúde, com os 5 sinais de integridade, é o <code>arquivos:health-check</code>.
            Upload entra pelos módulos, via trait <code>HasArquivos</code> — cap de {CFG.uploadCap} MB.
          </Nota>
        </div>}
    </>
  );
}

// ─── Trilha ─────────────────────────────────────────────────────────────────
function Trilha({ trilha }) {
  const { DataTable, StatusBadge } = DS();
  const colunas = [
    { key: "quando", label: "Quando", width: 155, mono: true },
    { key: "acao", label: "Ação", width: 135 },
    { key: "arq", label: "Arquivo", width: 100, mono: true },
    { key: "quem", label: "Quem", width: 125 },
    // "Detalhe", não "Payload": termo cru em inglês na tela, e a tela viva
    // (`Pages/Arquivos/Index.tsx`) já chama esta coluna de Detalhe — âncora divergente
    // da produção é o defeito que este próprio ciclo combate. A chave do dado segue `payload`.
    { key: "payload", label: "Detalhe" },
  ];
  const linhas = trilha.map((t) => ({
    id: t.id,
    cells: {
      quando: t.quando,
      acao: StatusBadge && AC(t.acao).k
        ? <StatusBadge kind="sla" value={AC(t.acao).k} label={AC(t.acao).l} title={t.acao} />
        : <span className="arq-disk" title={t.acao}>{AC(t.acao).l}</span>,
      arq: "#" + t.arq,
      quem: t.quem === "job" || t.quem === "sistema" ? <span className="arq-disk">{t.quem}</span> : <b className="mono">{t.quem}</b>,
      payload: <span className="arq-lei">{t.payload}</span>,
    },
  }));
  return (
    <>
      <div className="arq-lista" data-contract="trilha">
        {DataTable ? <DataTable columns={colunas} rows={linhas} /> : <p>{trilha.length} eventos</p>}
      </div>
      <p className="arq-fine">
        <IcLock /> <code>arquivos_audit_log</code> é append-only e <b>nunca purgado</b> — nem quando o arquivo é apagado.
        A tela não oferece editar nem apagar linha: alterar auditoria é incidente, não conserto.
        Nome de arquivo, caminho e MD5 vivem só aqui — o <code>activity_log</code> guarda apenas mudança de governança (bucket · visibility · encrypted · retention · classified_by), sem PII (LGPD Art. 37).
      </p>
    </>
  );
}

// ─── Classificar (bucket · visibility · retenção) ───────────────────────────
function Classificar({ alvo, onFechar, onAplicar }) {
  const { Select, Input } = DS();
  const { Confirm } = A();
  const { politica, CFG } = D();
  const [bucket, setBucket] = useState(alvo ? alvo.bucket : "active");
  const [vis, setVis] = useState(alvo ? alvo.vis : "internal");
  const [ret, setRet] = useState(alvo ? alvo.ret : 90);
  React.useEffect(() => { if (alvo) { setBucket(alvo.bucket); setVis(alvo.vis); setRet(alvo.ret); } }, [alvo]);
  if (!Confirm || !alvo) return null;
  const p = politica(alvo.sub);
  const menorQueALei = ret < p.dias;
  return (
    <Confirm open={!!alvo} title={`Classificar ${alvo.nome}`} cta="Salvar classificação" ctaTone="primary"
      onClose={onFechar} onConfirm={() => onAplicar({ bucket, vis, ret: Math.max(1, Number(ret) || 1) })}>
      <p className="arq-modal-alt">A classificação decide onde o arquivo mora, quem vê e por quanto tempo fica. Fica gravada com seu nome (<code>classified_by</code>) e a mudança entra no <code>activity_log</code> — sem nome de arquivo, sem caminho.</p>
      <div className="arq-modal-f">
        {Select
          ? <Select label="Classificação" value={bucket} onChange={(e) => setBucket(e.target.value)}
              help={bucket === "sensitive" ? `Sensível vai pro cofre cifrado e o default de PII cai pra ${CFG.sensitiveDefault} dias.` : "Fica no disco comum. Quem vê é a visibilidade, não a classificação."}
              options={[{ value: "sensitive", label: "Sensível — PII / cofre cifrado" }, { value: "active", label: "Em uso — operação corrente" }, { value: "memory", label: "Histórico — guardado por referência" }, { value: "discard", label: "Descartar — candidato a limpeza" }]} />
          : <select value={bucket} onChange={(e) => setBucket(e.target.value)}><option value="sensitive">Sensível</option><option value="active">Em uso</option><option value="memory">Histórico</option><option value="discard">Descartar</option></select>}
        {Select
          ? <Select label="Visibilidade" value={vis} onChange={(e) => setVis(e.target.value)}
              options={[{ value: "private", label: "Restrito — só quem alcança o dono" }, { value: "internal", label: "Equipe — quem tem acesso ao negócio" }, { value: "public", label: "Aberto — link sem login" }]} />
          : <select value={vis} onChange={(e) => setVis(e.target.value)}><option value="private">Restrito</option><option value="internal">Equipe</option><option value="public">Aberto</option></select>}
        {Input
          ? <Input label="Retenção (dias)" type="number" min="1" value={ret} onChange={(e) => setRet(e.target.value)}
              help={`Política do contexto ${alvo.sub}: ${p.dias} dias — ${p.lei}.`}
              error={menorQueALei ? "Abaixo do prazo da política: guardar menos do que a lei manda é risco fiscal, não economia." : undefined} />
          : <input type="number" value={ret} onChange={(e) => setRet(e.target.value)} />}
        {vis === "public" &&
          <p className="arq-modal-warn">Visibilidade <b>Aberto</b> serve o arquivo <b>sem login</b>. Só para logo e material de marketing — nunca para documento com dado de cliente.</p>}
      </div>
    </Confirm>
  );
}

// ─── Excluir (soft-delete · hard_delete ou anonymize) ───────────────────────
function Excluir({ alvo, onFechar, onAplicar }) {
  const { Confirm } = A();
  const { politica, CFG } = D();
  const [estrategia, setEstrategia] = useState("hard_delete");
  React.useEffect(() => { setEstrategia("hard_delete"); }, [alvo]);
  if (!Confirm || !alvo) return null;
  const p = politica(alvo.sub);
  const guardaLegal = p.dias >= 730;
  return (
    <Confirm open={!!alvo} title={`Apagar ${alvo.nome}?`} cta="Apagar (soft-delete)" onClose={onFechar} onConfirm={() => onAplicar(estrategia)}>
      <p>O arquivo sai da lista e entra no <b>grace de {CFG.grace} dias</b>. Passado o grace, o <code>retention-cleanup</code> executa a estratégia escolhida — e aí não tem volta.</p>
      <div className="arq-radios">
        <label className={estrategia === "hard_delete" ? "on" : undefined}>
          <input type="radio" name="arq-estrat" checked={estrategia === "hard_delete"} onChange={() => setEstrategia("hard_delete")} />
          <span><b>hard_delete</b> — apaga o arquivo e a linha. É o default da política (LGPD Art. 18 §VI, direito de eliminação).</span>
        </label>
        <label className={estrategia === "anonymize" ? "on" : undefined}>
          <input type="radio" name="arq-estrat" checked={estrategia === "anonymize"} onChange={() => setEstrategia("anonymize")} />
          <span><b>anonymize</b> — descarta o arquivo, zera <code>storage_path</code>, roda o <code>PiiRedactor</code> e <b>mantém</b> contagem e datas. Use quando o número ainda faz falta no relatório.</span>
        </label>
      </div>
      <p className="arq-modal-alt">
        {guardaLegal
          ? <><b>Atenção:</b> este contexto tem guarda legal de {Math.round(p.dias / 365)} anos ({p.lei}). Apagar antes do prazo é problema fiscal, não faxina.</>
          : <>A ação fica na trilha (<code>arquivos_audit_log</code>) com seu usuário — a auditoria não é apagada junto.</>}
      </p>
    </Confirm>
  );
}

function ArquivosPage({ view = "acervo", estado = "dados", papel = "gestor", dense = false, toque = "mouse", casa = "sistema" }) {
  const { PageHeader, TabBar, Skeleton } = DS();
  const { Vazio } = A();
  const { BASE, CFG, TRILHA_INICIAL, tam, dth, HOJE, politica, restam, noGrace, graceRestante } = D();
  const [vista, setVista] = useState(VISTAS.some((v) => v.id === view) ? view : "acervo");
  const [lista, setLista] = useState(estado === "vazio" ? [] : BASE);
  const [trilha, setTrilha] = useState(TRILHA_INICIAL);
  const [excluir, setExcluir] = useState(null);
  const [classificar, setClassificar] = useState(null);
  const [dryRun, setDryRun] = useState(null);
  const [aviso, setAviso] = useState(null);
  React.useEffect(() => { setLista(estado === "vazio" ? [] : BASE); }, [estado]);
  React.useEffect(() => { if (VISTAS.some((v) => v.id === view)) setVista(view); }, [view]);

  const cls = "os-page arq-page" + (dense ? " dense" : "") + (toque === "tablet" ? " toque" : "");
  const fala = (t) => { setAviso(t); setTimeout(() => setAviso(null), 5200); };
  const irPara = (v) => { if (VISTAS.some((x) => x.id === v)) setVista(v); };
  // Cada ação avança 1 min: a trilha é cronologia, hora repetida (ou zerada) desmente a tela.
  const logar = (acao, arq, payload) => setTrilha((t) => {
    const q = new Date(HOJE); q.setMinutes(q.getMinutes() + (t.length - TRILHA_INICIAL.length) + 1);
    return [{ id: 91205 + t.length, acao, arq, quem: "wagner", quando: dth(q), payload }, ...t];
  });

  const vivos = lista.filter((a) => !a.del);
  const total = vivos.reduce((s, a) => s + (a.anon ? 0 : a.bytes), 0);
  const sub = vivos.length
    ? `${vivos.length} arquivos · ${tam(total)} · ${vivos.filter((a) => a.enc).length} no cofre cifrado`
    : "nenhum arquivo guardado";

  if (papel === "sem-acesso") {
    return (
      <div className={cls} data-screen-label="Sistema · Arquivos">
        {PageHeader && <PageHeader title="Arquivos" subtitle="acesso negado" />}
        {Vazio && <Vazio variant="no-perm" title="Sua função não administra os arquivos."
          description="Falta arquivos.access (default off no pacote). Quem tem acesso à OS continua vendo o anexo da OS — o que esta tela dá é o poder de classificar e apagar." />}
      </div>
    );
  }
  if (estado === "carregando") {
    return (
      <div className={cls} data-screen-label="Sistema · Arquivos">
        {PageHeader && <PageHeader title="Arquivos" subtitle={sub} />}
        <div className="arq-lista">{Skeleton ? <Skeleton variant="row" count={6} /> : <p>Carregando…</p>}</div>
      </div>
    );
  }
  if (estado === "erro") {
    return (
      <div className={cls} data-screen-label="Sistema · Arquivos">
        {PageHeader && <PageHeader title="Arquivos" subtitle={sub} />}
        {Vazio && <Vazio variant="error" title="Não foi possível ler o acervo."
          description="O disco de destino não respondeu. Nenhum arquivo foi apagado — a tela não faz nada em GET."
          action={<Btn variant="primary" onClick={() => window.location.reload()}>Recarregar</Btn>} />}
      </div>
    );
  }

  // Contador só onde a aba É uma lista: acervo e trilha. Retenção e Cofre são retratos
  // (KPIs e achados) — número na aba deles promete uma contagem que a vista não entrega.
  const abas = VISTAS.map((v) => ({ key: v.id, label: v.label, count: v.id === "acervo" ? vivos.length : v.id === "trilha" ? trilha.length : undefined }));

  const rodarDryRun = () => {
    const venc = vivos.filter((a) => restam(a) <= 0);
    const gr = lista.filter((a) => noGrace(a));
    setDryRun([
      `[dry-run] política: strategy=${CFG.strategy} · grace=${CFG.grace}d · notice=${CFG.notice}d`,
      ...venc.map((a) => `[dry-run] soft_delete  #${a.id} ${a.nome} — prazo ${politica(a.sub).dias}d vencido há ${-restam(a)}d`),
      ...gr.map((a) => `[dry-run] aguardando   #${a.id} ${a.nome} — ${graceRestante(a)}d de grace restantes`),
      `[dry-run] ${venc.length} marcaria pra apagar · ${gr.length} no grace · 0 fora do business_id=4`,
      "[dry-run] nada foi escrito. rode sem --dry-run pelo agendado, não pela tela.",
    ]);
    fala("Dry-run concluído — leitura só. Nada foi apagado.");
  };

  return (
    <div className={cls} data-screen-label="Sistema · Arquivos">
      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title="Arquivos" subtitle={sub} actions={<Btn onClick={() => window.__selectRoute?.("auditoria")}>Auditoria</Btn>} />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>Arquivos</h1><p>{sub}</p></div></header>}
      </div>

      {/* A faixa "onde mora" saiu em 2026-08-25: era copy de PROCESSO dentro da UI (debatia
          Admin Center × destino próprio e citava US-ARQ-013 e um Tweak). A pergunta está
          decidida — a tela mora em Pages/Arquivos por decisão [W] de 2026-07-29 (ADR 0360
          deprecou o Admin Center) e o item de sidebar existe desde 2026-08-25. Usuário não
          lê ADR na tela. */}

      <div data-contract="abas">
        {TabBar
          ? <TabBar tabs={abas} active={vista} onChange={irPara} />
          : <nav className="arq-tabs">{VISTAS.map((v) => <button key={v.id} className={"arq-chip" + (vista === v.id ? " active" : "")} aria-current={vista === v.id ? "page" : undefined} onClick={() => irPara(v.id)}>{v.label}</button>)}</nav>}
      </div>

      {vista === "acervo" && <Acervo arquivos={lista} papel={papel}
        onBaixar={(a) => { logar(a.enc ? "signed_url" : "download", a.id, a.enc ? `expira em ${CFG.signedMin} min · DownloadController` : "servido por Storage::url"); fala(a.enc ? `Link assinado gerado pra ${a.nome} — vale ${CFG.signedMin} min e o download passa pelo DownloadController.` : `Download de ${a.nome} iniciado.`); }}
        onExcluir={setExcluir} onClassificar={setClassificar} />}

      {vista === "retencao" && <Retencao arquivos={lista} papel={papel}
        onRestaurar={(a) => { setLista((s) => s.map((x) => x.id === a.id ? { ...x, del: undefined } : x)); logar("restore", a.id, "restaurado dentro do grace"); fala(`${a.nome} voltou pro acervo.`); }}
        onPurgar={(a) => { setLista((s) => s.filter((x) => x.id !== a.id)); logar("hard_delete", a.id, "antecipado pela tela · grace interrompido"); fala(`${a.nome} apagado de verdade. A linha da trilha fica.`); }} />}

      {vista === "cofre" && <Cofre arquivos={lista} onDryRun={rodarDryRun} dryRun={dryRun} />}
      {vista === "trilha" && <Trilha trilha={trilha} />}

      <Classificar alvo={classificar} onFechar={() => setClassificar(null)}
        onAplicar={(p) => {
          const a = classificar;
          setLista((s) => s.map((x) => x.id === a.id ? { ...x, bucket: p.bucket, vis: p.vis, ret: p.ret, enc: p.bucket === "sensitive", disk: p.bucket === "sensitive" ? "vault" : "local", porQuem: "wagner" } : x));
          setClassificar(null);
          logar("classify", a.id, `bucket=${p.bucket} · visibility=${p.vis} · retention=${p.ret}d`);
          fala(`${a.nome} reclassificado — ${p.bucket}/${p.vis}, ${p.ret} dias.${p.bucket === "sensitive" ? " Vai pro cofre cifrado." : ""}`);
        }} />

      <Excluir alvo={excluir} onFechar={() => setExcluir(null)}
        onAplicar={(estrategia) => {
          const a = excluir;
          setLista((s) => s.map((x) => x.id === a.id ? { ...x, del: HOJE, anon: estrategia === "anonymize" } : x));
          setExcluir(null);
          logar("soft_delete", a.id, `grace de ${CFG.grace} dias iniciado · estratégia ${estrategia}`);
          fala(`${a.nome} entrou no grace de ${CFG.grace} dias${estrategia === "anonymize" ? " e será anonimizado (metadados ficam)" : ""} — dá pra restaurar até lá.`);
        }} />

      {aviso && <div className="arq-toast"><b>Pronto.</b> {aviso}</div>}
    </div>
  );
}

window.ArquivosPage = ArquivosPage;
})();
