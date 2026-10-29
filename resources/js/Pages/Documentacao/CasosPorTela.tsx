// Documentacao/CasosPorTela — os pedidos de clientes por tela e o contrato de cada tela.
//
// DERIVADO, não escrito: `dados` chega do servidor, lido dos *.casos.md a cada acesso por
// app/Services/Documentacao/CasosPorTela.php (porte do leitor de scripts/casos-backlog, com teste de
// paridade). Nenhum pedido, número, cliente ou texto de caso é escrito neste arquivo — só layout,
// filtro e as ações de conferir e responder. Fonte visual: o layout aprovado do artefato
// (scripts/casos-backlog/template.html). Charter: CasosPorTela.charter.md · casos: CasosPorTela.casos.md.
import { useMemo, useState, type ReactNode } from 'react';
import { Deferred, router } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Checkbox } from '@/Components/ui/checkbox';
import { Skeleton } from '@/Components/ui/skeleton';
import { Grid, Inline } from '@/Components/layout';
import SubNav from '@/Components/shared/SubNav';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import {
  FILTROS, TOM, fmtData, partesRicas,
  type Caso, type Contrato, type Dados, type Marca, type Marcas, type Tom,
} from './_components/casosPorTela';

interface Props {
  /** Deferred: `undefined` enquanto chega. `{erro}` quando um casos.md tem dado inválido. */
  dados?: Dados | { erro: string };
  /** Deferred. `null` = tabela das marcas ausente (não medido), diferente de "nada marcado". */
  marcas?: Marcas | null;
  podeConferir: boolean;
  podeDecidir: boolean;
}

/** Sentinela do item "Todos" (Radix Select não aceita value vazio — §5 2026-06-29). */
const TODOS = '__todos__';

type Visao = 'casos' | 'contratos' | 'regras' | 'decisoes' | 'clientes';
const VISOES: [Visao, string][] = [
  ['casos', 'Casos'], ['contratos', 'O que as telas garantem'], ['regras', 'Regras'],
  ['decisoes', 'Decisões para o Wagner'], ['clientes', 'Clientes'],
];
const VEREDITO: Record<string, [Tom, string]> = { pass: ['success', 'teste verde'], fail: ['danger', 'teste vermelho'], 'sem-prova': ['neutral', 'sem prova'] };
const STATUS_REGRA: Record<string, [Tom, string]> = { producao: ['success', 'Em produção'], parcial: ['warning', 'Em parte'], planejado: ['danger', 'Planejado'] };

function Rico({ texto }: { texto: string }) {
  return <>{partesRicas(texto).map((p, i) => (p.codigo ? <code key={i} className="rounded bg-muted px-1 text-[0.86em]">{p.texto}</code> : <span key={i}>{p.texto}</span>))}</>;
}

function Num({ n, rotulo, alerta }: { n: number; rotulo: string; alerta?: boolean }) {
  return (
    <Grid gap={null} className="min-w-0 rounded-md border bg-card px-3 py-2">
      <b className={`text-xl tabular-nums leading-tight ${alerta ? 'text-primary' : ''}`}>{n}</b>
      <span className="text-xs text-muted-foreground">{rotulo}</span>
    </Grid>
  );
}

function Pilula({ ativo, onClick, children }: { ativo: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button type="button" aria-pressed={ativo} onClick={onClick}
      className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-0.5 text-sm ${ativo ? 'border-foreground bg-foreground text-background' : 'bg-card'}`}>
      {children}
    </button>
  );
}

function Carregando() {
  return <Grid gap={null} className="gap-2" data-contract="lista"><Skeleton className="h-16" /><Skeleton className="h-10" /><Skeleton className="h-10" /><Skeleton className="h-10" /></Grid>;
}

export default function CasosPorTela({ dados, marcas, podeConferir, podeDecidir }: Props) {
  return (
    <AppShellV2 title="Casos de uso por tela" breadcrumbItems={[{ label: 'Documentação', href: '/documentacao' }, { label: 'Casos de uso por tela' }]}>
      <div data-contract="cabecalho">
        <PageHeader
          title="Casos de uso por tela"
          subtitle="Os pedidos dos clientes e o que cada tela já garante, lidos dos casos.md a cada acesso."
          actions={
            <Button asChild variant="outline" size="sm">
              <a href="/documentacao">Documentação</a>
            </Button>
          }
        />
      </div>
      <Deferred data={['dados', 'marcas']} fallback={<Carregando />}>
        {dados && 'erro' in dados ? (
          <p className="rounded-md border border-destructive p-4 text-sm" role="alert" data-contract="lista">
            Um casos.md tem um dado inválido e a página não pôde ser montada: {dados.erro}
          </p>
        ) : dados ? (
          <Pagina dados={dados} marcas={marcas ?? null} podeConferir={podeConferir} podeDecidir={podeDecidir} />
        ) : <Carregando />}
      </Deferred>
    </AppShellV2>
  );
}

function Pagina({ dados, marcas, podeConferir, podeDecidir }: { dados: Dados; marcas: Marcas | null; podeConferir: boolean; podeDecidir: boolean }) {
  const [visao, setVisao] = useState<Visao>(() => {
    const h = typeof window === 'undefined' ? '' : window.location.hash.slice(1);
    return (VISOES.find(([v]) => v === h)?.[0]) ?? 'casos';
  });
  const [abrirCaso, setAbrirCaso] = useState<string | null>(null);
  const [abrirTela, setAbrirTela] = useState<string | null>(null);
  const [clienteFiltro, setClienteFiltro] = useState('');
  const [recorte, setRecorte] = useState('tudo');
  const conf = marcas?.conferencia ?? {};
  const resp = marcas?.decisoes ?? {};
  const C = dados.casos;
  const clientes = useMemo(() => [...new Set(C.flatMap((c) => c.clientes))].sort((a, b) => a.localeCompare(b, 'pt-BR')), [C]);

  const mostrar = (v: Visao) => { setVisao(v); window.history.replaceState(null, '', '#' + v); };
  const irParaCaso = (n: number) => { const c = C.find((x) => x.n === n); if (!c) return; setRecorte('tudo'); setClienteFiltro(''); setAbrirCaso(c.id); mostrar('casos'); };
  const irParaContrato = (tela: string) => { setAbrirTela(tela); mostrar('contratos'); };
  const irParaTela = (tela: string) => { setRecorte(tela); setAbrirCaso(null); mostrar('casos'); };
  const irParaCliente = (nome: string) => { setClienteFiltro(nome); setRecorte('tudo'); setAbrirCaso(null); mostrar('casos'); };

  const nConf = C.filter((c) => conf[c.id]).length;
  const etapas: [string, number, string][] = [
    ['Pedido', C.length, 'ligados a este documento'],
    ['Conferido', nConf, 'contra as regras'],
    ['Virou caso de uso', C.filter((c) => c.estagio !== 'pedido').length, 'tem id UC no casos.md'],
    ['Testado', C.filter((c) => c.estagio === 'testado').length, 'com o teste verde'],
  ];
  const totalUcs = dados.contratos.reduce((s, t) => s + t.ucs.length, 0);
  const contagem: Record<Visao, number> = {
    casos: C.length, contratos: totalUcs, regras: dados.regras.length,
    decisoes: dados.decisoes.filter((d) => !resp[d.id]).length, clientes: clientes.length,
  };

  return (
    <Grid gap={null} className="gap-4">
      <Grid gap={null} className="gap-3" data-contract="filtros">
        <p className="max-w-[72ch] text-sm text-muted-foreground">
          <Rico texto="Cada caso é um item do casos.md da sua tela, ligado a este documento pelo subitem `Pedido: P-NNN`; as regras vêm do catálogo de Fluxos (`UC-FLX`). Para mudar um caso, edite o casos.md da tela: a página muda no próximo acesso." />
        </p>
        <Inline gap={null} align={null} className="flex-wrap gap-2" data-contract="numeros">
          <Num n={C.length} rotulo={`casos em ${dados.telas.length} telas`} />
          <Num n={clientes.length} rotulo="clientes ligados" />
          <Num n={C.filter((c) => c.tags.includes('valor')).length} rotulo="mexem em valor ou estoque" />
          <Num n={C.filter((c) => c.tags.includes('decisao')).length} rotulo="dependem do Wagner" alerta />
          <Num n={C.filter((c) => !c.regras.length).length} rotulo="não citam regra de Fluxos" />
        </Inline>
        <Inline gap={null} align={null} className="flex-wrap overflow-hidden rounded-md border bg-card" aria-label="Ciclo de vida dos casos">
          {etapas.map(([nome, n, sub]) => (
            <Grid gap={null} key={nome} className="flex-[1_1_140px] gap-0.5 border-r px-3 py-2 last:border-r-0">
              <span className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{nome}</span>
              <b className="text-lg tabular-nums">{n}</b>
              <small className="text-xs text-muted-foreground">{sub}</small>
              <div className="h-1 overflow-hidden rounded bg-muted" aria-hidden="true">
                <i className="block h-full bg-primary" style={{ width: `${C.length ? Math.round((100 * n) / C.length) : 0}%` }} />
              </div>
            </Grid>
          ))}
        </Inline>
        {marcas === null ? <p className="rounded-md bg-muted px-3 py-2 text-sm">A conferência e as respostas não estão disponíveis agora (a tabela das marcas não existe neste ambiente).</p> : null}
        {dados.problemas.length ? (
          <div className="rounded-md border px-3 py-2 text-sm" role="status">
            <b>Os casos.md têm {dados.problemas.length} problema(s) de ligação:</b>
            <ul className="ml-4 list-disc">{dados.problemas.map((p) => <li key={p}>{p}</li>)}</ul>
          </div>
        ) : null}
      </Grid>

      <SubNav ariaLabel="Visões" value={visao} onChange={(v) => mostrar(v as Visao)}
        items={VISOES.map(([v, rotulo]) => ({ value: v, label: rotulo, badge: contagem[v] }))} />

      <div data-contract="lista" role="tabpanel">
        {visao === 'casos' ? (
          <VisaoCasos dados={dados} conf={conf} clientes={clientes} podeConferir={podeConferir}
            recorte={recorte} setRecorte={setRecorte} cliente={clienteFiltro} setCliente={setClienteFiltro}
            aberto={abrirCaso} setAberto={setAbrirCaso} irParaContrato={irParaContrato} />
        ) : visao === 'contratos' ? (
          <VisaoContratos contratos={dados.contratos} aberta={abrirTela} setAberta={setAbrirTela} irParaTela={irParaTela} />
        ) : visao === 'regras' ? (
          <VisaoRegras dados={dados} irParaCaso={irParaCaso} />
        ) : visao === 'decisoes' ? (
          <VisaoDecisoes dados={dados} resp={resp} podeDecidir={podeDecidir} irParaCaso={irParaCaso} />
        ) : (
          <VisaoClientes dados={dados} clientes={clientes} irParaCaso={irParaCaso} irParaCliente={irParaCliente} />
        )}
      </div>
    </Grid>
  );
}

function LinkCaso({ n, titulo, onClick }: { n: number; titulo?: string; onClick: () => void }) {
  return <button type="button" title={titulo} onClick={onClick} className="rounded border bg-card px-1.5 font-mono text-xs text-primary hover:border-primary">#{n}</button>;
}

function VisaoCasos(p: {
  dados: Dados; conf: Record<string, Marca>; clientes: string[]; podeConferir: boolean;
  recorte: string; setRecorte: (v: string) => void; cliente: string; setCliente: (v: string) => void;
  aberto: string | null; setAberto: (v: string | null) => void; irParaContrato: (tela: string) => void;
}) {
  const { dados, conf } = p;
  const C = dados.casos;
  const [busca, setBusca] = useState('');
  const [filtros, setFiltros] = useState<Set<string>>(new Set());
  const areaDaTela = useMemo(() => Object.fromEntries(dados.areas.flatMap((a) => a.telas.map((t) => [t, a.id]))), [dados.areas]);
  const telaDe = useMemo(() => Object.fromEntries(dados.telas.map((t) => [t.id, t])), [dados.telas]);
  const contratoDe = useMemo(() => Object.fromEntries(dados.contratos.map((t) => [t.id, t])), [dados.contratos]);
  const regraPorId = useMemo(() => Object.fromEntries(dados.regras.map((r) => [r.id, r])), [dados.regras]);

  const naBusca = (c: Caso) => {
    const q = busca.trim().toLowerCase();
    if (!q) return true;
    const m = q.match(/^#?([0-9]+)$/);
    if (m) return c.n === Number(m[1]);
    return [c.titulo, c.aceite, c.texto, c.ancora, c.fazer, c.uc, c.hoje.nota, c.clientes.join(' ')].join(' ').toLowerCase().includes(q);
  };
  const base = (c: Caso, ignorar?: string) => naBusca(c) && (!p.cliente || c.clientes.includes(p.cliente))
    && FILTROS.every(([id, , f]) => id === ignorar || !filtros.has(id) || f(c, conf));
  const noRecorte = (c: Caso) => p.recorte === 'tudo' || c.tela === p.recorte || areaDaTela[c.tela] === p.recorte;
  const vis = C.filter((c) => noRecorte(c) && base(c));
  const conta = (pred: (c: Caso) => boolean) => C.filter((c) => pred(c) && base(c)).length;
  const alternar = (id: string) => setFiltros((s) => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n; });
  const sujo = filtros.size > 0 || busca !== '' || p.cliente !== '' || p.recorte !== 'tudo';
  const limpar = () => { setFiltros(new Set()); setBusca(''); p.setCliente(''); p.setRecorte('tudo'); };

  const BotaoRecorte = ({ id, rotulo, n, forte }: { id: string; rotulo: string; n: number; forte?: boolean }) => (
    <Inline asChild gap={null} align={null} className={`w-full justify-between gap-2 rounded px-2 py-1 text-left hover:bg-muted ${p.recorte === id ? 'bg-accent font-semibold' : ''} ${forte ? 'font-bold' : ''}`}><button type="button" aria-pressed={p.recorte === id} onClick={() => p.setRecorte(id)}>
      <span>{rotulo}</span><span className="font-mono text-xs text-muted-foreground">{n}</span>
    </button></Inline>
  );

  return (
    <Grid gap={null} className="items-start gap-5 md:grid-cols-[230px_minmax(0,1fr)]">
      <Grid asChild gap={null} className="gap-3 text-sm max-md:hidden md:sticky md:top-0"><aside aria-label="Áreas e telas">
        <BotaoRecorte id="tudo" rotulo="Todas as telas" n={conta(() => true)} />
        {dados.areas.map((a) => (
          <div key={a.id}>
            <BotaoRecorte id={a.id} rotulo={a.nome} n={conta((c) => areaDaTela[c.tela] === a.id)} forte />
            <Grid asChild gap={null} className="mt-0.5 gap-px pl-2"><ul>
              {a.telas.map((t) => <li key={t}><BotaoRecorte id={t} rotulo={telaDe[t]?.nome ?? t} n={conta((c) => c.tela === t)} /></li>)}
            </ul></Grid>
          </div>
        ))}
      </aside></Grid>
      <Grid gap={null} className="min-w-0 gap-3">
        <div className="md:hidden">
          <Select value={p.recorte} onValueChange={p.setRecorte}>
            <SelectTrigger aria-label="Área ou tela" className="w-full bg-card"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="tudo">Todas as telas</SelectItem>
              {dados.areas.map((a) => (
                <SelectGroup key={a.id}>
                  <SelectLabel>{a.nome}</SelectLabel>
                  <SafeSelectItem value={a.id}>Toda a área: {a.nome}</SafeSelectItem>
                  {a.telas.map((t) => <SafeSelectItem key={t} value={t}>{telaDe[t]?.nome ?? t}</SafeSelectItem>)}
                </SelectGroup>
              ))}
            </SelectContent>
          </Select>
        </div>
        <Inline gap={null} align={null} className="flex-wrap gap-2">
          <Input type="search" className="min-w-0 flex-[1_1_260px]" value={busca} onChange={(e) => { setBusca(e.target.value); p.setAberto(null); }}
            placeholder="Buscar no título, aceite, âncora ou número (#45)" aria-label="Buscar casos" />
          <Select value={p.cliente || TODOS} onValueChange={(v) => p.setCliente(v === TODOS ? '' : v)}>
            <SelectTrigger aria-label="Cliente" className="w-auto max-w-full bg-card"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value={TODOS}>Todos os clientes</SelectItem>
              {p.clientes.map((c) => <SafeSelectItem key={c} value={c}>{c}</SafeSelectItem>)}
            </SelectContent>
          </Select>
        </Inline>
        <Inline gap={null} align={null} className="flex-wrap gap-1.5" role="group" aria-label="Filtros (somam entre si)">
          {FILTROS.map(([id, rotulo, f]) => (
            <Pilula key={id} ativo={filtros.has(id)} onClick={() => alternar(id)}>
              {rotulo}<span className="font-mono text-xs opacity-75">{C.filter((c) => noRecorte(c) && base(c, id) && f(c, conf)).length}</span>
            </Pilula>
          ))}
        </Inline>
        <div className="text-sm text-muted-foreground" data-contract="resultado">
          {vis.length === C.length ? `${C.length} casos` : `${vis.length} de ${C.length} casos`}{' '}
          {sujo ? <button type="button" className="text-primary underline" onClick={limpar}>Limpar filtros</button> : null}
        </div>
        <Grid gap={null} className="gap-1.5">
          {!C.length ? (
            <div className="rounded-md border border-dashed p-7 text-center text-muted-foreground">Nenhum caso ligado ainda. Um caso entra aqui quando o item dele no casos.md da tela ganha o subitem "Pedido: P-NNN".</div>
          ) : !vis.length ? (
            <div className="rounded-md border border-dashed p-7 text-center text-muted-foreground">Nenhum caso com esses filtros. Tire um filtro ou limpe a busca.</div>
          ) : vis.map((c) => (
            <CasoItem key={c.id} c={c} aberto={p.aberto === c.id} alternar={() => p.setAberto(p.aberto === c.id ? null : c.id)}
              marca={conf[c.id]} podeConferir={p.podeConferir} tela={telaDe[c.tela]} contrato={contratoDe[c.tela]}
              regraTitulo={(r) => regraPorId[r]?.titulo ?? ''} irParaContrato={p.irParaContrato} />
          ))}
        </Grid>
        <details className="rounded-md border bg-card">
          <summary className="cursor-pointer px-3.5 py-2.5 font-semibold">Como entra um pedido novo</summary>
          <Grid gap={null} className="gap-2 px-3.5 pb-3.5 text-sm">
            <p className="max-w-[75ch]"><Rico texto="Um pedido novo não se escreve aqui: ele entra no `casos.md` da tela, como um item de backlog com o subitem `Pedido`. No próximo acesso ele aparece nesta lista." /></p>
            <pre className="overflow-x-auto rounded bg-muted px-3 py-2.5 font-mono text-xs leading-normal">{[
              '- **[BACKLOG] Título curto do pedido** `[V0]`', `  - Pedido: ${dados.proximoPedido}`,
              '  - Aceite: Dado … · Quando … · Então …', '  - Âncora: UC-FLX-NN · US-XXX-NNN',
              '  - Fazer: o que precisa ser construído', '  - Hoje: miss | Não tem | o que existe hoje',
              '  - Comunicação visual: ok | Sim | por quê', '  - Clientes: Cliente A, Cliente B', '  - Etiquetas: valor, miss, cv',
            ].join('\n')}</pre>
            <p className="max-w-[75ch]"><Rico texto="O número no exemplo é o próximo livre. Se duas pessoas usarem o mesmo número, a página avisa e não monta a lista. Quando o pedido ganhar um teste e virar caso de uso com id, troque o item pelo bloco do UC e deixe nele a linha `- **Pedido de origem:** P-NNN`." /></p>
          </Grid>
        </details>
      </Grid>
    </Grid>
  );
}

function CasoItem({ c, aberto, alternar, marca, podeConferir, tela, contrato, regraTitulo, irParaContrato }: {
  c: Caso; aberto: boolean; alternar: () => void; marca?: Marca; podeConferir: boolean;
  tela?: Dados['telas'][number]; contrato?: Contrato; regraTitulo: (r: string) => string; irParaContrato: (t: string) => void;
}) {
  const [salvando, setSalvando] = useState(false);
  const conferir = (marcar: boolean) => {
    setSalvando(true);
    router.post(`/documentacao/casos-por-tela/conferencia/${c.id}`, { conferido: marcar }, {
      preserveScroll: true, preserveState: true, only: ['marcas'], onFinish: () => setSalvando(false),
    });
  };
  const sel = (s: Caso['hoje']) => (s.texto ? <span><Badge variant={TOM[s.tipo] ?? 'neutral'}>{s.texto}</Badge> <Rico texto={s.nota} /></span> : null);
  const linhas: [string, ReactNode][] = [];
  if (c.aceite) linhas.push(['Aceite', <Rico texto={c.aceite} />]); else if (c.texto) linhas.push(['Descrição', <Rico texto={c.texto} />]);
  if (c.fazer) linhas.push(['O que fazer', <Rico texto={c.fazer} />]);
  if (c.hoje.texto) linhas.push(['Hoje no sistema', sel(c.hoje)]);
  if (c.cv.texto) linhas.push(['Comunicação visual', sel(c.cv)]);
  if (c.ancora) linhas.push(['Regra e âncora', <Rico texto={c.ancora} />]);
  linhas.push(['Clientes', c.clientes.length ? c.clientes.join(', ') : <span className="text-muted-foreground">nenhum ligado</span>]);
  linhas.push(['Onde está', <span>{tela?.nome ?? c.tela} · <a className="text-primary" href={`${tela?.url ?? ''}?plain=1#L${c.linha}`} target="_blank" rel="noopener noreferrer"><code>{c.arquivo}:{c.linha}</code></a></span>]);
  if (contrato) linhas.push(['A tela já garante', <button type="button" className="text-primary underline" onClick={() => irParaContrato(c.tela)}>{contrato.ucs.length} casos de uso</button>]);

  return (
    <article className={`rounded-md border bg-card ${marca ? 'border-l-[3px] border-l-success' : ''}`} data-caso={c.id}>
      <Grid asChild gap={null} className="w-full grid-cols-[3.2em_minmax(0,1fr)_auto] items-start gap-2.5 px-3 py-2.5 text-left"><button type="button" aria-expanded={aberto} onClick={alternar}>
        <span className="font-mono tabular-nums text-muted-foreground">#{c.n}</span>
        <Grid asChild gap={null} className="min-w-0 gap-1 font-semibold"><span>
          <span>{c.titulo}</span>
          <Inline asChild gap={null} align={null} className="flex-wrap gap-1 font-normal"><span>
            {c.uc ? <Badge variant="success" title={c.estagio === 'testado' ? 'Virou caso de uso, com o teste verde' : 'Virou caso de uso'}>{c.uc}</Badge> : null}
            {c.marcas.map((m) => <Badge key={m} variant="warning" title={m === 'V0' ? 'Mexe em valor' : m === 'E0' ? 'Mexe em estoque' : 'Mexe em tributo'}>{m}</Badge>)}
            {c.hoje.texto ? <Badge variant={TOM[c.hoje.tipo] ?? 'neutral'}>{c.hoje.texto}</Badge> : null}
            {c.tags.includes('decisao') ? <Badge variant="info">Depende do Wagner</Badge> : null}
            {c.regras.length ? c.regras.map((r) => <Badge key={r} variant="neutral" title={regraTitulo(r)} className="font-mono">{r}</Badge>)
              : <Badge variant="outline">sem regra citada</Badge>}
            {marca ? <Badge variant="success">Conferido</Badge> : null}
          </span></Inline>
        </span></Grid>
        <span className={`text-muted-foreground transition-transform motion-reduce:transition-none ${aberto ? 'rotate-90' : ''}`} aria-hidden="true">›</span>
      </button></Grid>
      {aberto ? (
        <Grid gap={null} className="gap-3 px-3.5 pb-3.5 md:pl-[calc(3.2em+22px)]">
          <Grid asChild gap={null} className="m-0 gap-x-3.5 gap-y-1.5 md:grid-cols-[9.5em_minmax(0,1fr)]"><dl>
            {linhas.map(([k, v]) => [<dt key={k + 't'} className="text-sm text-muted-foreground">{k}</dt>, <dd key={k + 'd'} className="m-0 min-w-0 [overflow-wrap:anywhere]">{v}</dd>])}
          </dl></Grid>
          <Inline gap={null} align={null} className="flex-wrap items-center gap-2.5 border-t pt-2.5 text-sm">
            <label htmlFor={`conferido-${c.id}`} className="inline-flex cursor-pointer items-center gap-2">
              <Checkbox id={`conferido-${c.id}`} checked={!!marca} disabled={!podeConferir || salvando} onCheckedChange={(v) => conferir(v === true)} aria-label={`Conferido contra as regras: caso ${c.n}`} />
              Conferido contra as regras
            </label>
            <span className="text-muted-foreground">{marca ? `${marca.por} · ${fmtData(marca.em)}` : podeConferir ? '' : 'Você pode ver o que foi conferido, mas não marcar.'}</span>
          </Inline>
        </Grid>
      ) : null}
    </article>
  );
}

function VisaoContratos({ contratos, aberta, setAberta, irParaTela }: { contratos: Contrato[]; aberta: string | null; setAberta: (v: string | null) => void; irParaTela: (t: string) => void }) {
  const [busca, setBusca] = useState('');
  const [modulo, setModulo] = useState('');
  const [semProva, setSemProva] = useState(false);
  const modulos = useMemo(() => [...new Set(contratos.map((t) => t.modulo))].sort((a, b) => a.localeCompare(b, 'pt-BR')), [contratos]);
  const total = contratos.reduce((s, t) => s + t.ucs.length, 0);
  const verdes = contratos.reduce((s, t) => s + t.ucs.filter((u) => u.veredito === 'pass').length, 0);
  const q = busca.trim().toLowerCase();
  const vis = contratos.filter((t) => !modulo || t.modulo === modulo).map((t) => {
    const casaTela = !q || [t.nome, t.id, t.modulo].join(' ').toLowerCase().includes(q);
    return { t, ucs: t.ucs.filter((u) => (!semProva || u.veredito !== 'pass') && (casaTela || [u.id, u.titulo].join(' ').toLowerCase().includes(q))) };
  }).filter((x) => x.ucs.length);
  const nUcs = vis.reduce((s, x) => s + x.ucs.length, 0);

  return (
    <Grid gap={null} className="gap-3">
      <div>
        <h2 className="text-lg font-semibold">O que as telas já garantem</h2>
        <p className="max-w-[70ch] text-sm text-muted-foreground">{total} casos de uso em {contratos.length} telas, {verdes} com o teste verde. É o contrato do que o sistema já faz: cada caso tem id e um teste que o prova. O veredito vem do resultado dos testes, não do que está escrito no arquivo.</p>
      </div>
      <Inline gap={null} align={null} className="flex-wrap gap-2">
        <Input type="search" className="min-w-0 flex-[1_1_260px]" value={busca} onChange={(e) => { setBusca(e.target.value); setAberta(null); }} placeholder="Buscar tela, módulo, id ou título do caso de uso" aria-label="Buscar casos de uso" />
        <Select value={modulo || TODOS} onValueChange={(v) => { setModulo(v === TODOS ? '' : v); setAberta(null); }}>
          <SelectTrigger aria-label="Módulo" className="w-auto max-w-full bg-card"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value={TODOS}>Todos os módulos</SelectItem>
            {modulos.map((m) => <SafeSelectItem key={m} value={m}>{m}</SafeSelectItem>)}
          </SelectContent>
        </Select>
        <Pilula ativo={semProva} onClick={() => setSemProva(!semProva)}>Só os casos sem teste verde</Pilula>
      </Inline>
      <div className="text-sm text-muted-foreground">{vis.length} {vis.length === 1 ? 'tela' : 'telas'} · {nUcs} {nUcs === 1 ? 'caso de uso' : 'casos de uso'}</div>
      <Grid gap={null} className="gap-1.5">
        {!vis.length ? <div className="rounded-md border border-dashed p-7 text-center text-muted-foreground">Nenhum caso de uso com essa busca.</div> : vis.map(({ t, ucs }) => {
          const ok = t.ucs.filter((u) => u.veredito === 'pass').length;
          const aberto = aberta === t.id;
          return (
            <article key={t.id} className="rounded-md border bg-card" data-tela={t.id}>
              <Grid asChild gap={null} className="w-full grid-cols-[6em_minmax(0,1fr)_auto] items-start gap-2.5 px-3 py-2.5 text-left"><button type="button" aria-expanded={aberto} onClick={() => setAberta(aberto ? null : t.id)}>
                <span className="truncate font-mono text-xs text-muted-foreground">{t.modulo}</span>
                <Grid asChild gap={null} className="min-w-0 gap-1 font-semibold"><span>
                  <span>{t.nome}</span>
                  <Inline asChild gap={null} align={null} className="flex-wrap gap-1 font-normal"><span>
                    <Badge variant={ok === t.ucs.length ? 'success' : 'warning'}>{ok} de {t.ucs.length} com teste verde</Badge>
                    {t.pedidos ? <Badge variant="info">{t.pedidos} pedido{t.pedidos > 1 ? 's' : ''} em aberto</Badge> : null}
                  </span></Inline>
                </span></Grid>
                <span className={`text-muted-foreground ${aberto ? 'rotate-90' : ''}`} aria-hidden="true">›</span>
              </button></Grid>
              {aberto ? (
                <Grid gap={null} className="gap-2 px-3.5 pb-3.5">
                  <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-sm">
                      <thead><tr className="bg-muted text-left text-xs uppercase text-muted-foreground"><th className="px-3 py-2">Caso de uso</th><th className="px-3 py-2">O que garante</th><th className="px-3 py-2">Teste</th></tr></thead>
                      <tbody>
                        {ucs.map((u) => { const [tom, rot] = VEREDITO[u.veredito] ?? ['neutral', u.veredito]; return (
                          <tr key={u.id + u.linha} className="border-t align-top">
                            <td className="px-3 py-2"><a className="text-primary" href={`${t.url}?plain=1#L${u.linha}`} target="_blank" rel="noopener noreferrer"><code>{u.id}</code></a></td>
                            <td className="px-3 py-2">{u.titulo}</td>
                            <td className="px-3 py-2"><Badge variant={tom}>{rot}</Badge></td>
                          </tr>); })}
                      </tbody>
                    </table>
                  </div>
                  {t.pedidos ? <div><button type="button" className="text-sm text-primary underline" onClick={() => irParaTela(t.id)}>Ver os {t.pedidos} pedidos desta tela</button></div> : null}
                </Grid>
              ) : null}
            </article>
          );
        })}
      </Grid>
    </Grid>
  );
}

function VisaoRegras({ dados, irParaCaso }: { dados: Dados; irParaCaso: (n: number) => void }) {
  const [soSemCaso, setSoSemCaso] = useState(false);
  const casosDa = (id: string) => dados.casos.filter((c) => c.regras.includes(id));
  const citadas = dados.regras.filter((r) => casosDa(r.id).length).length;
  return (
    <Grid gap={null} className="gap-3">
      <Inline gap={null} align={null} className="flex-wrap items-end justify-between gap-2.5">
        <div>
          <h2 className="text-lg font-semibold">Regras do catálogo de Fluxos</h2>
          <p className="max-w-[70ch] text-sm text-muted-foreground">{citadas} das {dados.regras.length} regras são citadas por algum caso. As outras {dados.regras.length - citadas} não aparecem em nenhum: ou não se aplicam a estes pedidos, ou ninguém conferiu. {dados.casos.filter((c) => !c.regras.length).length} casos não citam regra nenhuma.</p>
        </div>
        <Pilula ativo={soSemCaso} onClick={() => setSoSemCaso(!soSemCaso)}>Só as regras sem caso</Pilula>
      </Inline>
      <div className="overflow-x-auto rounded-md border bg-card">
        <table className="w-full text-sm">
          <thead><tr className="bg-muted text-left text-xs uppercase text-muted-foreground"><th className="px-3 py-2">Regra</th><th className="px-3 py-2">O que diz</th><th className="px-3 py-2">Etapa</th><th className="px-3 py-2">No sistema</th><th className="px-3 py-2">Casos que dependem dela</th></tr></thead>
          <tbody>
            {dados.regras.filter((r) => !soSemCaso || !casosDa(r.id).length).map((r) => {
              const cs = casosDa(r.id); const [tom, rot] = STATUS_REGRA[r.status] ?? ['neutral', r.status];
              return (
                <tr key={r.id} className={`border-t align-top ${cs.length ? '' : 'shadow-[inset_3px_0_0_var(--color-warning)]'}`}>
                  <td className="px-3 py-2"><code>{r.id}</code></td>
                  <td className="px-3 py-2"><div className="font-semibold">{r.titulo}</div><div className="text-muted-foreground">{r.efeito}</div></td>
                  <td className="px-3 py-2">{r.etapa}</td>
                  <td className="px-3 py-2"><Badge variant={tom}>{rot}</Badge></td>
                  <td className="px-3 py-2">{cs.length ? <Inline gap={null} align={null} className="flex-wrap gap-1">{cs.map((c) => <LinkCaso key={c.id} n={c.n} titulo={c.titulo} onClick={() => irParaCaso(c.n)} />)}</Inline> : <span className="text-muted-foreground">nenhum</span>}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </Grid>
  );
}

function VisaoDecisoes({ dados, resp, podeDecidir, irParaCaso }: { dados: Dados; resp: Marcas['decisoes']; podeDecidir: boolean; irParaCaso: (n: number) => void }) {
  const dep = dados.casos.filter((c) => c.tags.includes('decisao')).map((c) => c.n);
  const cobertos = new Set(dados.decisoes.flatMap((d) => d.casos));
  const soltos = dep.filter((n) => !cobertos.has(n));
  const abertas = dados.decisoes.filter((d) => !resp[d.id]).length;
  return (
    <Grid gap={null} className="gap-3">
      <div>
        <h2 className="text-lg font-semibold">Decisões para o Wagner</h2>
        <p className="max-w-[70ch] text-sm text-muted-foreground">
          {abertas} de {dados.decisoes.length} perguntas em aberto. {dep.length} casos dependem de você; {dep.length - soltos.length} deles estão ligados a uma pergunta abaixo.{' '}
          {soltos.length ? <span>Os outros {soltos.length} ainda não têm pergunta escrita: <span className="inline-flex flex-wrap gap-1">{soltos.map((n) => <LinkCaso key={n} n={n} onClick={() => irParaCaso(n)} />)}</span></span> : null}
        </p>
      </div>
      {!dados.decisoes.length ? <div className="rounded-md border border-dashed p-7 text-center text-muted-foreground">Nenhuma decisão escrita nos casos.md ainda.</div> : dados.decisoes.map((d) => (
        <DecisaoItem key={d.id} d={d} r={resp[d.id]} podeDecidir={podeDecidir} irParaCaso={irParaCaso} />
      ))}
    </Grid>
  );
}

function DecisaoItem({ d, r, podeDecidir, irParaCaso }: { d: Dados['decisoes'][number]; r?: Marcas['decisoes'][string]; podeDecidir: boolean; irParaCaso: (n: number) => void }) {
  const [opcao, setOpcao] = useState(r?.opcao ?? '');
  const [nota, setNota] = useState(r?.nota ?? '');
  const [erro, setErro] = useState('');
  const [salvando, setSalvando] = useState(false);
  const responder = () => {
    if (!opcao && !nota.trim()) { setErro('Escolha uma opção ou escreva a resposta antes de registrar.'); return; }
    setSalvando(true);
    router.post(`/documentacao/casos-por-tela/decisao/${d.id}`, { opcao, nota }, {
      preserveScroll: true, preserveState: true, only: ['marcas', 'errors'],
      onError: (e) => setErro(String(Object.values(e)[0] ?? 'Não deu para registrar a resposta.')),
      onSuccess: () => setErro(''), onFinish: () => setSalvando(false),
    });
  };
  return (
    <Grid asChild gap={null} className={`gap-2.5 rounded-md border border-l-[3px] bg-card px-4 py-3.5 ${r ? 'border-l-success' : 'border-l-primary'}`}><article data-decisao={d.id}>
      <div className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{r ? 'Respondida' : d.estado}</div>
      <h3 className="font-semibold">{d.pergunta}</h3>
      {d.texto ? <p className="max-w-[75ch]">{d.texto}</p> : null}
      {d.opcoes.length ? (
        <RadioGroup value={opcao} onValueChange={setOpcao} disabled={!podeDecidir} aria-label={d.pergunta}
          className="gap-2 sm:grid-cols-[repeat(auto-fit,minmax(240px,1fr))]">
          {d.opcoes.map((o) => (
            <Grid key={o.nome} asChild gap={null} className={`cursor-pointer gap-1 rounded-md border px-3 py-2.5 ${opcao === o.nome ? 'border-success' : ''}`}><label htmlFor={`op-${d.id}-${o.nome}`}>
              <Inline gap={2} align="center" className="font-bold"><RadioGroupItem id={`op-${d.id}-${o.nome}`} value={o.nome} />{o.nome}</Inline>
              <span className="text-sm text-muted-foreground">{o.texto}</span>
            </label></Grid>
          ))}
        </RadioGroup>
      ) : null}
      {d.casos.length ? <Inline gap={null} align={null} className="flex-wrap items-center gap-1"><span className="text-muted-foreground">Destrava: </span>{d.casos.map((n) => <LinkCaso key={n} n={n} onClick={() => irParaCaso(n)} />)}</Inline> : null}
      <Inline gap={null} align={null} className="flex-wrap items-center gap-2">
        <Input className="min-w-0 flex-[1_1_260px]" value={nota} onChange={(e) => setNota(e.target.value)} disabled={!podeDecidir}
          placeholder={d.opcoes.length ? 'Observação (opcional)' : 'Sua resposta'} aria-label={`Resposta da decisão ${d.id}`} />
        <Button type="button" size="sm" disabled={!podeDecidir || salvando} onClick={responder}>{r ? 'Atualizar resposta' : 'Registrar resposta'}</Button>
      </Inline>
      {erro ? <p className="text-sm text-destructive" role="alert">{erro}</p> : null}
      <div className="text-sm text-muted-foreground">
        {r ? `Respondida por ${r.por} em ${fmtData(r.em)}${r.opcao ? ' · ' + r.opcao : ''}` : podeDecidir ? null : 'Só o Wagner registra a resposta.'}
      </div>
    </article></Grid>
  );
}

function VisaoClientes({ dados, clientes, irParaCaso, irParaCliente }: { dados: Dados; clientes: string[]; irParaCaso: (n: number) => void; irParaCliente: (n: string) => void }) {
  const areaDaTela = Object.fromEntries(dados.areas.flatMap((a) => a.telas.map((t) => [t, a])));
  const lista = clientes.map((nome) => ({ nome, casos: dados.casos.filter((c) => c.clientes.includes(nome)) }))
    .sort((a, b) => b.casos.length - a.casos.length || a.nome.localeCompare(b.nome, 'pt-BR'));
  return (
    <Grid gap={null} className="gap-3">
      <div>
        <h2 className="text-lg font-semibold">Clientes</h2>
        <p className="max-w-[70ch] text-sm text-muted-foreground">{lista.length} clientes ligados a algum caso, os que mais pediram primeiro. Clique num número para abrir o caso ou no nome para ver só os casos dele.</p>
      </div>
      <Grid gap={null} className="gap-2 sm:grid-cols-[repeat(auto-fill,minmax(260px,1fr))]">
        {lista.map((cl) => (
          <Grid key={cl.nome} asChild gap={null} className="content-start gap-2 rounded-md border bg-card px-3.5 py-3"><article>
            <Inline gap={null} align={null} className="items-baseline justify-between gap-2">
              <button type="button" className="font-semibold text-primary underline" onClick={() => irParaCliente(cl.nome)}>{cl.nome}</button>
              <span className="font-mono text-xs text-muted-foreground">{cl.casos.length} {cl.casos.length === 1 ? 'caso' : 'casos'}</span>
            </Inline>
            <Inline gap={null} align={null} className="flex-wrap gap-1">{[...new Set(cl.casos.map((c) => areaDaTela[c.tela]?.nome ?? 'Outras telas'))].map((a) => <Badge key={a} variant="neutral">{a}</Badge>)}</Inline>
            <Inline gap={null} align={null} className="flex-wrap gap-1">{cl.casos.map((c) => <LinkCaso key={c.id} n={c.n} titulo={c.titulo} onClick={() => irParaCaso(c.n)} />)}</Inline>
          </article></Grid>
        ))}
      </Grid>
    </Grid>
  );
}
