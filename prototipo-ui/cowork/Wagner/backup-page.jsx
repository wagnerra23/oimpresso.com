// backup-page.jsx — Backup (rota legada /backup do UltimatePOS: BackUpController + views/backup/index.blade.php).
// Espelho lido do main: colunas arquivo/tamanho/data/idade, ação que roda `backup:run` na própria request,
// download e delete por nome de arquivo, instrução de cron. Fatos do repo: disco =
// config('backup.backup.destination.disks')[0] (BACKUP_DISK, padrão local), pasta = config('backup.backup.name')
// = "UltimatePOS", limpeza = App\Backup\Cleanup\KeepLatestBackups (guarda os 5 últimos).
// Montada com os componentes compilados do DS (PageHeader · Button · DataTable · StatusBadge · Alert ·
// Progress · Modal · EmptyState · Toast · DropdownMenu · KpiCard) — nada de tabela/botão bespoke.
// Expõe window.BackupPage.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const { Kpis, Kpi, Nota, Vazio, Confirm, Meta } = window.AcessosDS;

const AGORA = new Date(2026, 7, 19, 9, 12);
const p2 = (n) => String(n).padStart(2, "0");
const zip = (d) => `${d.getFullYear()}-${p2(d.getMonth()+1)}-${p2(d.getDate())}-${p2(d.getHours())}-${p2(d.getMinutes())}-${p2(d.getSeconds())}.zip`;
const dt = (d) => `${p2(d.getDate())}/${p2(d.getMonth()+1)}/${d.getFullYear()} ${p2(d.getHours())}:${p2(d.getMinutes())}`;
const horas = (d) => (AGORA - d) / 36e5;
const idade = (d) => {
  const h = Math.round(horas(d));
  if (h < 1) return "agora";
  if (h < 24) return `há ${h} h`;
  const dias = Math.round(h / 24);
  return dias === 1 ? "há 1 dia" : `há ${dias} dias`;
};
// frescor do DS: recente ≤24 h · fresc ≤48 h · frio ≤96 h · distante além disso
const frescor = (d) => { const h = horas(d); return h <= 24 ? "recente" : h <= 48 ? "fresc" : h <= 96 ? "frio" : "distante"; };
const tam = (b) => b >= 1073741824 ? `${(b/1073741824).toFixed(2).replace(".", ",")} GB` : `${Math.round(b/1048576)} MB`;

const mk = (dias, hora, bytes, origem) => {
  const d = new Date(AGORA); d.setDate(d.getDate() - dias); d.setHours(hora, 0, 4, 0);
  return { file: zip(d), data: d, bytes, origem };
};
const INICIAIS = [
  mk(0, 3, 268_435_456, "agendado"),
  mk(1, 3, 264_241_152, "agendado"),
  mk(2, 15, 263_192_576, "manual"),
  mk(2, 3, 261_046_272, "agendado"),
  mk(3, 3, 258_998_272, "agendado"),
];

const CRON = "* * * * * /usr/bin/php /var/www/oimpresso.com/artisan schedule:run >> /dev/null 2>&1";
const PASSOS = ["Dump do banco (oimpresso)", "Compactando storage/app", "Gravando no disco local", "Limpando backups antigos"];

const IcPlus = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>;
const IcZip = () => <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>;
const IcKebab = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>;

function Btn({ children, ...p }) {
  const { Button } = DS();
  if (!Button) return <button className={`os-btn ${p.variant === "primary" ? "primary" : "ghost"}`} disabled={p.disabled} onClick={p.onClick}>{children}</button>;
  return <Button {...p}>{children}</Button>;
}

function Copiavel({ valor }) {
  const [ok, setOk] = useState(false);
  return (
    <div className="bkp-cron-line">
      <code>{valor}</code>
      <Btn size="sm" onClick={() => { navigator.clipboard?.writeText(valor); setOk(true); setTimeout(() => setOk(false), 1600); }}>{ok ? "Copiado" : "Copiar"}</Btn>
    </div>
  );
}

function Aviso({ texto, tone }) {
  const { Toast } = DS();
  if (!texto) return null;
  return (
    <div className="bkp-toast">
      {Toast ? <Toast tone={tone || "ok"}>{texto}</Toast> : <div className="bkp-aviso-ok">{texto}</div>}
    </div>
  );
}

function AcoesLinha({ b, unico, onBaixar, onExcluir, podeBaixar = true, podeExcluir = true, motivo }) {
  const { DropdownMenu } = DS();
  const itens = [
    { id: "baixar", label: podeBaixar ? "Baixar arquivo" : `Baixar — ${motivo}`, disabled: !podeBaixar, onSelect: onBaixar },
    { id: "sep", separator: true },
    { id: "excluir", label: unico ? "Excluir — é o único backup" : podeExcluir ? "Excluir arquivo" : `Excluir — ${motivo}`, tone: "danger", disabled: unico || !podeExcluir, onSelect: onExcluir },
  ];
  return (
    <div className="bkp-acts" onClick={(e) => e.stopPropagation()}>
      <Btn size="default" disabled={!podeBaixar} onClick={onBaixar}>Baixar</Btn>
      {DropdownMenu
        ? <DropdownMenu align="end" items={itens} trigger={<span className="bkp-kebab" aria-hidden="true"><IcKebab /></span>} />
        : <Btn size="sm" icon onClick={unico ? undefined : onExcluir}><IcKebab /></Btn>}
    </div>
  );
}

function BackupPage({ destino = "local", estado = "dados", permissao = "total" }) {
  const { PageHeader, DataTable, StatusBadge, Tooltip, KpiCard } = DS();
  const [lista, setLista] = useState(estado === "vazio" ? [] : INICIAIS);
  React.useEffect(() => { setLista(estado === "vazio" ? [] : INICIAIS); }, [estado]);
  const [gerar, setGerar] = useState(false);
  const [rodando, setRodando] = useState(null);
  const [excluir, setExcluir] = useState(null);
  const [aviso, setAviso] = useState(null);

  const total = useMemo(() => lista.reduce((a, b) => a + b.bytes, 0), [lista]);
  const ultimo = lista[0];
  const semBackup = !ultimo;
  const remoto = destino !== "local";
  const demo = estado === "demo";
  const erro = estado === "erro";
  const podeEscrever = permissao === "total" && !demo;
  const podeBaixar = !demo;
  const motivoBloqueio = demo ? "Desabilitado em ambiente de demonstração." : "Sua função não tem a permissão backup de escrita.";
  // O agendado das 03:00 rodou? (sem arquivo agendado nas últimas 27 h, o cron está parado)
  const agendadoOk = lista.some((b) => b.origem === "agendado" && horas(b.data) <= 27);
  const usoPct = Math.min(100, Math.round((total / (5 * 1073741824)) * 100));
  const fala = (t, ms = 5000) => { setAviso(t); setTimeout(() => setAviso(null), ms); };

  const roda = () => {
    setGerar(false);
    setRodando({ passo: 0, pct: 8 });
    let p = 0;
    const t = setInterval(() => {
      p += 1;
      if (p >= PASSOS.length) {
        clearInterval(t);
        const d = new Date(AGORA);
        setLista((s) => [{ file: zip(d), data: d, bytes: 269_484_032, origem: "manual" }, ...s].slice(0, 5));
        setRodando(null);
        fala("Backup gerado. O mais antigo saiu do disco — a retenção guarda os 5 últimos.", 6000);
        return;
      }
      setRodando({ passo: p, pct: Math.round((p / PASSOS.length) * 100) });
    }, 1100);
  };

  const colunas = [
    { key: "arquivo", label: "Arquivo", width: 300 },
    { key: "origem",  label: "Origem", width: 110 },
    { key: "tamanho", label: "Tamanho", align: "right", mono: true, width: 100 },
    { key: "data",    label: "Data", mono: true, width: 150 },
    { key: "idade",   label: "Idade", width: 150 },
    { key: "acoes",   label: "", align: "right", width: 120 },
  ];
  const linhas = lista.map((b, i) => ({
    id: b.file,
    cells: {
      arquivo: (
        <div className="bkp-file">
          <span className="bkp-file-ic" aria-hidden="true"><IcZip /></span>
          <span className="bkp-file-meta">
            <b className="mono">{b.file}</b>
            <small>banco + storage/app{i === 0 && <span className="bkp-tag-novo">mais recente</span>}</small>
          </span>
        </div>
      ),
      origem: StatusBadge
        ? <StatusBadge tone="neutral" label={b.origem === "manual" ? "Manual" : "Agendado"} />
        : <span className={`bkp-origem ${b.origem}`}>{b.origem === "manual" ? "Manual" : "Agendado"}</span>,
      tamanho: tam(b.bytes),
      data: dt(b.data),
      idade: <span className="bkp-idade">{StatusBadge
        ? <StatusBadge kind="frescor" value={frescor(b.data)} rel={idade(b.data)} />
        : <span className="usr-last">{idade(b.data)}</span>}</span>,
      acoes: <AcoesLinha b={b} unico={lista.length === 1} podeBaixar={podeBaixar} podeExcluir={podeEscrever} motivo={motivoBloqueio}
        onBaixar={() => fala(`Download iniciado: ${b.file}`, 4000)}
        onExcluir={() => setExcluir(b)} />,
    },
  }));

  const btnGerar = <Btn variant="primary" disabled={!!rodando || !podeEscrever} onClick={() => setGerar(true)}><IcPlus /> Gerar backup agora</Btn>;
  const acoes = (
    <div className="bkp-h-acts">
      <Btn onClick={() => window.__selectRoute?.("auditoria")}>Auditoria</Btn>
      {podeEscrever || !Tooltip ? btnGerar : <Tooltip content={motivoBloqueio}><span>{btnGerar}</span></Tooltip>}
    </div>
  );
  const sub = semBackup
    ? "Nenhum backup no disco — nada para restaurar"
    : `${lista.length} de 5 arquivos guardados · último ${idade(ultimo.data)} · ${tam(total)} no disco`;

  return (
    <div className="os-page bkp-page" data-screen-label="Sistema · Backup">
      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title="Backup" subtitle={sub} actions={acoes} />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>Backup</h1><p>{sub}</p></div><div className="os-page-h-r">{acoes}</div></header>}
      </div>

      <div data-contract="kpis" className="bkp-kpis">
        {KpiCard ? <>
          <KpiCard hero label="Último backup" value={semBackup ? "—" : dt(ultimo.data).slice(11)}
            description={semBackup ? "nunca — nada para restaurar" : `${dt(ultimo.data).slice(0,10)} · ${idade(ultimo.data)}`}
            spark={lista.length > 1 ? [...lista].reverse().map((b) => Math.round(b.bytes / 1048576)) : undefined} />
          <KpiCard label="Backups guardados" value={lista.length}
            description="de 5 · a retenção apaga o resto" progress={lista.length / 5} />
          <KpiCard label="Espaço ocupado" value={tam(total)}
            description={`${usoPct}% do limite de 5 GB`} progress={usoPct / 100} tone={usoPct > 80 ? "warning" : "default"} />
          <KpiCard label="Agendamento diário" value="03:00"
            description={agendadoOk ? "rodou hoje · schedule:run" : "não rodou nas últimas 27 h"}
            tone={agendadoOk ? "success" : "danger"} />
        </> : (
          <Kpis>
            <Kpi v={lista.length} l="Backups guardados" />
            <Kpi v={semBackup ? "—" : dt(ultimo.data).slice(11)} l="Último backup" />
            <Kpi v={tam(total)} l="Espaço ocupado" />
            <Kpi v="03:00" l="Agendamento diário" />
          </Kpis>
        )}
      </div>

      <div className="bkp-nota" data-contract="alerta-destino">
        {remoto ? (
          <Nota tone="success" title="Destino remoto configurado">
            Os arquivos vão para <code>{destino}</code> além do disco local — uma perda do servidor não leva o backup com ela.
          </Nota>
        ) : (
          <Nota tone="warn" title="Backup no mesmo servidor não é backup">
            O disco de destino é o <code>local</code> (<code>storage/app/UltimatePOS/</code>) enquanto <code>BACKUP_DISK</code> não apontar
            para S3 ou outro remoto. Se o servidor cair, cai com ele o backup — <b>baixe o arquivo do dia</b> ou configure o disco remoto.
          </Nota>
        )}
        {!agendadoOk && lista.length > 0 && (
          <Nota tone="danger" title="O agendado não rodou nas últimas 27 h">
            Só existe backup manual na janela recente. Confira se a linha de cron abaixo está no crontab e se a fila está de pé —
            enquanto isso, o backup depende de alguém clicar.
          </Nota>
        )}
        {erro && (
          <Nota tone="danger" title="A última geração falhou">
            <code>mysqldump: Got error: 28: No space left on device</code> — libere espaço ou aponte <code>BACKUP_DISK</code> para um disco com folga.
            A lista abaixo é o que ainda existe.
          </Nota>
        )}
        {demo && (
          <Nota tone="info" title="Ambiente de demonstração">
            Gerar, baixar e excluir estão desabilitados aqui e também no servidor (<code>APP_ENV=demo</code>).
          </Nota>
        )}
      </div>

      {rodando && (
        <div className="bkp-rodando">
          <div className="bkp-rodando-h"><b>Gerando backup…</b><span>{PASSOS[rodando.passo]}</span></div>
          <Meta pct={rodando.pct} label={`${rodando.pct}%`} />
          <small>Não feche nem recarregue a tela: o processo roda na requisição (<code>Artisan::call('backup:run')</code>).</small>
        </div>
      )}

      <div className="bkp-lista" data-contract="lista">
        {lista.length === 0
          ? <Vazio variant="first" title="Nenhum backup no disco." description={podeEscrever ? "Gere o primeiro agora ou espere o agendamento das 03:00 — sem arquivo, uma falha de servidor não tem volta." : "Ninguém gerou backup ainda, e sua função não pode gerar. Peça a um administrador."} />
          : DataTable
            ? <DataTable columns={colunas} rows={linhas} />
            : <div className="os-table-wrap"><table className="os-table"><tbody>{lista.map((b) => <tr key={b.file}><td className="mono">{b.file}</td><td>{tam(b.bytes)}</td><td>{dt(b.data)}</td></tr>)}</tbody></table></div>}
      </div>

      <div className="bkp-cron" data-contract="cron">
        <div className="bkp-cron-h">
          <b>Backup automático</b>
          <p>O agendado só roda se esta linha existir no crontab do servidor. Sem ela, backup é só o que você clicar.</p>
        </div>
        <Copiavel valor={CRON} />
        <ul className="bkp-cron-fatos">
          <li><span className="bkp-cron-k">Pasta</span><span className="bkp-cron-v"><code>storage/app/UltimatePOS/</code></span></li>
          <li><span className="bkp-cron-k">Retenção</span><span className="bkp-cron-v"><code>KeepLatestBackups</code> — guarda os 5 últimos, apaga o resto</span></li>
          <li><span className="bkp-cron-k">Permissão</span><span className="bkp-cron-v"><code>backup</code> — sem ela a rota devolve 403</span></li>
        </ul>
      </div>

      <Aviso texto={aviso} />

      <Confirm open={gerar} title="Gerar backup agora?" cta="Gerar agora" ctaTone="primary" onConfirm={roda} onClose={() => setGerar(false)}>
        <p>O backup roda <b>dentro desta requisição</b> — a tela fica esperando até o dump do banco e a compactação terminarem. Em base do tamanho da ROTA LIVRE, leva de 1 a 3 minutos.</p>
        <p className="bkp-modal-alt">Evite no horário de balcão cheio: o dump segura conexões do banco. O agendado das 03:00 já cobre o dia.</p>
      </Confirm>

      <Confirm open={!!excluir} title="Excluir este backup?" cta="Excluir arquivo"
        onConfirm={() => { const b = excluir; setLista((s) => s.filter((x) => x.file !== b.file)); setExcluir(null); fala(`Arquivo excluído: ${b.file}`); }}
        onClose={() => setExcluir(null)}>
        {excluir && <>
          <p>O arquivo <b className="mono">{excluir.file}</b> ({tam(excluir.bytes)}) sai do disco na hora. Não tem lixeira e não tem volta.</p>
          <p className="bkp-modal-alt">Se ele é o único backup recente, gere um novo antes de excluir.</p>
        </>}
      </Confirm>
    </div>
  );
}

window.BackupPage = BackupPage;
})();
