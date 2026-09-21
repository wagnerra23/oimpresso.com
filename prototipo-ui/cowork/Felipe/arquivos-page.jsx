// arquivos-page.jsx — Arquivos · DMS backbone (Modules/Arquivos · ADR 0123 · US-ARQ-013).
// FATO DO REPO: o módulo NÃO tem tela — `DataController::modifyAdminMenu()` é NO-OP de propósito
// ("backbone consumido via trait HasArquivos") e o docblock aponta o destino: Pages/Arquivos no
// Admin Center (Sprint 2). Esta é a proposta F1 dessa tela. Dados/domínio em arquivos-data.jsx.
// Quatro vistas: acervo · retenção (grace + avisos) · cofre (achados + dry-run) · trilha.
// Ondas de refino: classificar (classified_by/at + LogsActivity) · restaurar no grace ·
// anonymize como estratégia (retention.php) · aviso ao titular (LGPD Art. 18 §VI) · dono clicável ·
// dry-run do retention-cleanup · alvo de toque · onde a tela mora (Tweak, decisão de [W]).
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
const BUCKET = { sensitive: { t: "danger", l: "sensitive" }, common: { t: "neutral", l: "common" }, public: { t: "info", l: "public" } };
const ACAO = { upload: { t: "info", l: "upload" }, download: { t: "neutral", l: "download" }, signed_url: { t: "warning", l: "signed_url" }, soft_delete: { t: "warning", l: "soft_delete" }, restore: { t: "success", l: "restore" }, hard_delete: { t: "danger", l: "hard_delete" }, classify: { t: "info", l: "classify" }, anonymize: { t: "warning", l: "anonymize" }, notice: { t: "neutral", l: "notice" } };

const IcLock = () => <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>;
const IcFile = () => <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>;
const IcDown = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3v13M7 12l5 5 5-5M4 21h16"/></svg>;
const IcAlert = () => <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"><path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/></svg>;
const IcTag = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M20 12l-8 8-8-8V4h8z"/><path d="M8.5 8.5h.01"/></svg>;
const IcUndo = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round"><path d="M4 10h10a5 5 0 1 1 0 10H8"/><path d="M4 10l4-4M4 10l4 4"/></svg>;
const IcBell = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>;

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
function Acervo({ arquivos, papel, onBaixar, onExcluir, onClassificar, onAvisar }) {
  const { DataTable, StatusBadge, Input } = DS();
  const { Vazio } = A();
  const { CFG, politica, restam, vence, dt, tam, precisaAviso } = D();
  const [q, setQ] = useState("");
  const [bucket, setBucket] = useState("todos");
  const termo = q.trim().toLowerCase();
  const vivos = arquivos.filter((a) => !a.del);
  const lista = useMemo(() => vivos.filter((a) =>
    (bucket === "todos" || a.bucket === bucket) &&
    (!termo || (a.nome + " " + (a.dono || "") + " " + a.sub).toLowerCase().includes(termo))), [vivos, bucket, termo]);
  const podeMexer = papel === "gestor";

  const colunas = [
    { key: "arq", label: "Arquivo" },
    { key: "dono", label: "Onde está preso", width: 180 },
    { key: "bucket", label: "Classificação", width: 150 },
    { key: "disco", label: "Disco", width: 110 },
    { key: "tamanho", label: "Tamanho", width: 95, align: "right", mono: true },
    { key: "vence", label: "Vence em", width: 165 },
    { key: "acao", label: "", width: 190, align: "right" },
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
              <b>{a.nome}</b>
              <small>{politica(a.sub).label} · <code>{a.sub}</code>{a.porQuem ? ` · classificado por ${a.porQuem}` : " · sem classificação humana"}</small>
            </span>
          </span>
        ),
        dono: <DonoLink a={a} />,
        bucket: (
          <span className="arq-cls">
            {StatusBadge ? <StatusBadge tone={BUCKET[a.bucket].t} label={BUCKET[a.bucket].l} /> : <span>{a.bucket}</span>}
            <small className="mono">{a.vis}</small>
          </span>
        ),
        disco: a.enc ? <span className="arq-vault"><IcLock /> vault</span> : <span className="arq-disk mono">{a.disk}</span>,
        tamanho: a.anon ? "—" : tam(a.bytes),
        vence: <span className="arq-vence"><b className="mono">{dt(vence(a))}</b>{StatusBadge
          ? <StatusBadge kind="frescor" value={r <= 30 ? "distante" : r <= 180 ? "frio" : r <= 720 ? "fresc" : "recente"} rel={r <= 0 ? "prazo vencido" : `em ${r} dias`} />
          : <small>{r} dias</small>}</span>,
        acao: (
          <div className="arq-acts">
            {precisaAviso(a) && podeMexer && <Btn size="sm" onClick={() => onAvisar(a)}><IcBell /> Avisar</Btn>}
            <Btn size="sm" disabled={a.anon} onClick={() => onBaixar(a)}><IcDown /> Baixar</Btn>
            {podeMexer && <Btn size="sm" onClick={() => onClassificar(a)} aria-label="Classificar"><IcTag /></Btn>}
            {podeMexer && <Btn size="sm" onClick={() => onExcluir(a)}>Excluir</Btn>}
          </div>
        ),
      },
    };
  });

  return (
    <>
      <div className="arq-toolbar" data-contract="acervo-filtros">
        <div className="arq-chips">
          {["todos", "sensitive", "common", "public"].map((b) => (
            <button key={b} className={"arq-chip" + (bucket === b ? " active" : "")} onClick={() => setBucket(b)}>
              {b === "todos" ? "Todos" : b}<span className="mono">{b === "todos" ? vivos.length : vivos.filter((a) => a.bucket === b).length}</span>
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
            ? (Vazio ? <Vazio variant="no-results" title="Nada com esse filtro." description="Troque o bucket ou limpe a busca." /> : <p>Nada.</p>)
            : DataTable ? <DataTable columns={colunas} rows={linhas} /> : <p>{lista.length} arquivos</p>}
      </div>

      <p className="arq-fine">
        O acervo é administrativo: o arquivo continua sendo alcançado pela tela do dono (clique no dono e você vai pra lá).
        Baixar do <b>vault</b> passa sempre pelo <code>DownloadController</code> — <code>Storage::url</code> direto não serve arquivo cifrado (ADR 0123 §6). Link assinado expira em {CFG.signedMin} min.
        {!podeMexer && " Sua função lê e baixa; classificar e apagar é do gestor."}
      </p>
    </>
  );
}

// ─── Retenção ───────────────────────────────────────────────────────────────
function Retencao({ arquivos, papel, onRestaurar, onPurgar, onAvisar }) {
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
            O <code>arquivos:retention-cleanup</code> roda com <code>strategy={CFG.strategy}</code> — confira se o agendado está de pé.
          </Nota>
        </div>}

      {avisos.length > 0 &&
        <section className="arq-bloco" data-contract="avisos-titular">
          <header className="arq-bloco-h">
            <h3>Aviso ao titular pendente</h3>
            <small>LGPD Art. 18 §VI · bucket sensitive com titular identificado, a ≤{CFG.notice} dias do prazo</small>
          </header>
          <ul className="arq-rows">
            {avisos.map((a) => (
              <li key={a.id}>
                <span className="arq-row-m"><b>{a.nome}</b><small>titular: {a.titular} · vence em {restam(a)} dias · {politica(a.sub).lei}</small></span>
                {podeMexer
                  ? <Btn size="sm" variant="primary" onClick={() => onAvisar(a)}><IcBell /> Avisar titular</Btn>
                  : <span className="arq-disk">só o gestor avisa</span>}
              </li>
            ))}
          </ul>
          <p className="arq-fine">O aviso sai por canal de Notification e fica na trilha como <code>notice</code>. Avisar não apaga nada — só cumpre o prazo de aviso.</p>
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
        <div className="arq-card"><span className="arq-card-l">Aviso ao titular</span><b className="mono">{CFG.notice} dias</b><small>LGPD Art. 18 §VI, para bucket sensitive com titular</small></div>
        <div className="arq-card"><span className="arq-card-l">Estratégia</span><b className="mono">{CFG.strategy}</b><small>apagar de verdade; <code>anonymize</code> disponível por arquivo</small></div>
        <div className="arq-card"><span className="arq-card-l">Escopo do job</span><b className="mono">business_id</b><small>nunca cross-tenant (ADR 0093)</small></div>
      </div>

      <p className="arq-fine">
        Prazo é lei, não preferência: mudar um número aqui muda <code>Config/retention.php</code> <b>e</b> <code>config.php</code> (são espelho, e divergir é achado de auditoria).
        A trilha (<code>arquivos_audit_log</code>) <b>nunca</b> é purgada, mesmo quando o arquivo é.
      </p>
    </>
  );
}

// ─── Cofre ──────────────────────────────────────────────────────────────────
function Cofre({ arquivos, onDryRun, dryRun }) {
  const { Progress } = DS();
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
            {Progress && <Progress value={Math.min(100, Math.round(d.bytes / (5 * 1073741824) * 100))} max={100} />}
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
          <Nota tone="info" title="Esta tela não envia arquivo">
            Upload entra pelos módulos, via trait <code>HasArquivos</code> — cap de {CFG.uploadCap} MB por upload.
            Aqui é administração: classificar, achar problema, apagar com prazo.
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
    { key: "payload", label: "Payload" },
  ];
  const linhas = trilha.map((t) => ({
    id: t.id,
    cells: {
      quando: t.quando,
      acao: StatusBadge ? <StatusBadge tone={(ACAO[t.acao] || ACAO.download).t} label={(ACAO[t.acao] || ACAO.download).l} /> : <b>{t.acao}</b>,
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
  const [bucket, setBucket] = useState(alvo ? alvo.bucket : "common");
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
          ? <Select label="Bucket" value={bucket} onChange={(e) => setBucket(e.target.value)}
              help={bucket === "sensitive" ? `Sensitive vai pro cofre cifrado e o default de PII cai pra ${CFG.sensitiveDefault} dias.` : "Common fica no disco comum, servido por URL direta."}
              options={[{ value: "common", label: "common — operacional" }, { value: "sensitive", label: "sensitive — PII / cofre cifrado" }, { value: "public", label: "public — pode ser servido aberto" }]} />
          : <select value={bucket} onChange={(e) => setBucket(e.target.value)}><option value="common">common</option><option value="sensitive">sensitive</option><option value="public">public</option></select>}
        {Select
          ? <Select label="Visibilidade" value={vis} onChange={(e) => setVis(e.target.value)}
              options={[{ value: "private", label: "private — só quem tem o dono" }, { value: "internal", label: "internal — equipe do negócio" }, { value: "public", label: "public — link aberto" }]} />
          : <select value={vis} onChange={(e) => setVis(e.target.value)}><option value="private">private</option><option value="internal">internal</option><option value="public">public</option></select>}
        {Input
          ? <Input label="Retenção (dias)" type="number" min="1" value={ret} onChange={(e) => setRet(e.target.value)}
              help={`Política do contexto ${alvo.sub}: ${p.dias} dias — ${p.lei}.`}
              error={menorQueALei ? "Abaixo do prazo da política: guardar menos do que a lei manda é risco fiscal, não economia." : undefined} />
          : <input type="number" value={ret} onChange={(e) => setRet(e.target.value)} />}
        {bucket === "public" && vis === "public" &&
          <p className="arq-modal-warn">Public + public serve o arquivo <b>sem login</b>. Só para logo e material de marketing — nunca para documento com dado de cliente.</p>}
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

  const abas = VISTAS.map((v) => ({ key: v.id, label: v.label, count: v.id === "acervo" ? vivos.length : v.id === "trilha" ? trilha.length : v.id === "retencao" ? lista.filter((a) => noGrace(a)).length || undefined : undefined }));

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

      <p className="arq-casa" data-contract="onde-mora">
        {casa === "admin-center"
          ? <>Esta tela mora no <b>Admin Center</b> (US-ARQ-013) — o módulo continua sem entry própria de sidebar, como o <code>DataController</code> NO-OP declara hoje.</>
          : <>Esta tela está como <b>destino próprio</b> em SISTEMA. No repo, o caminho declarado é o Admin Center (US-ARQ-013) — trocar é decisão sua, no Tweak “Onde mora”.</>}
      </p>

      <div data-contract="abas">
        {TabBar
          ? <TabBar tabs={abas} active={vista} onChange={irPara} />
          : <nav className="arq-tabs">{VISTAS.map((v) => <button key={v.id} className={"arq-chip" + (vista === v.id ? " active" : "")} aria-current={vista === v.id ? "page" : undefined} onClick={() => irPara(v.id)}>{v.label}</button>)}</nav>}
      </div>

      {vista === "acervo" && <Acervo arquivos={lista} papel={papel}
        onBaixar={(a) => { logar(a.enc ? "signed_url" : "download", a.id, a.enc ? `expira em ${CFG.signedMin} min · DownloadController` : "servido por Storage::url"); fala(a.enc ? `Link assinado gerado pra ${a.nome} — vale ${CFG.signedMin} min e o download passa pelo DownloadController.` : `Download de ${a.nome} iniciado.`); }}
        onExcluir={setExcluir} onClassificar={setClassificar}
        onAvisar={(a) => { setLista((s) => s.map((x) => x.id === a.id ? { ...x, avisado: true } : x)); logar("notice", a.id, `aviso prévio ao titular (${a.titular}) · ${CFG.notice} dias`); fala(`Titular ${a.titular} avisado — o prazo de aviso da LGPD Art. 18 §VI está cumprido.`); }} />}

      {vista === "retencao" && <Retencao arquivos={lista} papel={papel}
        onRestaurar={(a) => { setLista((s) => s.map((x) => x.id === a.id ? { ...x, del: undefined } : x)); logar("restore", a.id, "restaurado dentro do grace"); fala(`${a.nome} voltou pro acervo.`); }}
        onPurgar={(a) => { setLista((s) => s.filter((x) => x.id !== a.id)); logar("hard_delete", a.id, "antecipado pela tela · grace interrompido"); fala(`${a.nome} apagado de verdade. A linha da trilha fica.`); }}
        onAvisar={(a) => { setLista((s) => s.map((x) => x.id === a.id ? { ...x, avisado: true } : x)); logar("notice", a.id, `aviso prévio ao titular (${a.titular}) · ${CFG.notice} dias`); fala(`Titular ${a.titular} avisado.`); }} />}

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
