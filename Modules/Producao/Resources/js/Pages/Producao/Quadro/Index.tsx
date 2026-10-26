// @memcofre
//   tela: /producao/quadro
//   module: Producao
//   stories: playbook Produção thread 06 (UC-PCV-02, UC-PCV-04, UC-PCV-05, UC-PCV-06) · UC-FLX-10 (US-PCP-029) · UC-FLX-13 (US-PCP-024)
//   permissao: producao.access · alocar com producao.etapa.mover · aceitar arranjo com o papel producao.liberar
//
// Quadro da produção: uma OS por cartão, na coluna do setor (ou da máquina) da etapa atual. Âncora
// visual: `Quadro` de prototipo-ui/cowork/Wagner/producao-cv-page.jsx (classes `pcv-*` do bundle).
// Recebe os dois botões que tinham backend sem tela: "Alocar" no cartão (UC-FLX-10) e a ação em lote
// "Simular bobina" sobre as OS da comunicação visual marcadas (UC-FLX-13).
// O Painel (`/producao/painel`) abre esta tela já filtrada pela URL (?so=atrasadas|arte, ?agrupar=maquina).
// UC-PCV-06: o filtro inteiro (busca, situação, responsável, setor) fica na URL e a regra é a de
// `../_lib/filtro.ts`, a mesma que a Linha do tempo (thread 07) vai usar.
// UC-PCV-07: no modo "Por setor" o PCP move a OS para outra coluna pelo menu ⋮ ("Mover para …", funciona no
// teclado) ou arrastando o cartão, como no protótipo. Só a coluna muda; o evento fica no histórico da OS.
// Fora deste corte: o painel de eventos ao lado do quadro.
import '../../../../../../../resources/css/cowork-producao-bundle.css';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useMemo, useRef, useState, type PointerEvent as ReactPointerEvent, type ReactNode } from 'react';
import { MoreVertical } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { Input } from '@/Components/ui/input';
import { Segmented } from '@/Components/ui/segmented';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import ProducaoAbas from '../_components/ProducaoAbas';
import Alocar from './_components/Alocar';
import SimuladorBobina from './_components/SimuladorBobina';
import { SEM_SETOR, setorDe, vaiNaBobina, type Cartao, type Midia, type Pessoas, type Posto } from './_lib/tipos';
import { LIMPO, colunaDe, filtrado as temFiltro, passa, paraUrl, type Filtro } from '../_lib/filtro';
import { json } from './_lib/json';

interface Props {
  hoje: string; filtro: Filtro & { agrupar: 'setor' | 'maquina' }; pode_alocar: boolean; pode_aceitar_arranjo: boolean; postos: Posto[];
  cartoes?: Cartao[]; midias?: Midia[]; pessoas?: Pessoas;
}
const TODOS = '__todos__';
const d2 = (iso: string) => iso.slice(8, 10) + '/' + iso.slice(5, 7);

interface Arraste { chave: string; x0: number; y0: number; x: number; y: number; moveu: boolean; sobre: string | null }

function CartaoOs({ c, podeAlocar, marcado, onMarcar, onAlocar, destinos, onMover, onPegar, arrastando }: {
  c: Cartao; podeAlocar: boolean; marcado: boolean; onMarcar: (v: boolean) => void; onAlocar: () => void;
  destinos: string[]; onMover: ((setor: string) => void) | null; onPegar: ((ev: ReactPointerEvent) => void) | null; arrastando: boolean;
}) {
  const e = c.etapa;
  return (
    <div className={'pcv-card' + (c.atraso === 'atrasada' ? ' is-late' : '') + (onPegar ? ' is-movel' : '') + (arrastando ? ' is-arr' : '')}
      data-contract="producao-quadro-cartao" data-os={c.chave}
      onPointerDown={(ev) => { if (!onPegar || ev.button !== 0 || (ev.target as HTMLElement).closest('[data-sem-arrastar], button, [role="checkbox"]')) return; onPegar(ev); }}>
      <div className="pcv-card-h">
        <b className="mono">{c.numero}</b>
        {onMover && destinos.length > 0 && (
          <span data-sem-arrastar><DropdownMenu>
            <DropdownMenuTrigger asChild><Button size="icon" variant="ghost" className="h-7 w-7" aria-label={`Mais ações de ${c.numero}`}><MoreVertical className="h-4 w-4" aria-hidden="true" /></Button></DropdownMenuTrigger>
            <DropdownMenuContent align="end">{destinos.map((s) => <DropdownMenuItem key={s} onSelect={() => onMover(s)}>Mover para {s}</DropdownMenuItem>)}</DropdownMenuContent>
          </DropdownMenu></span>
        )}
        {vaiNaBobina(c) && <Checkbox checked={marcado} onCheckedChange={(v) => onMarcar(v === true)} aria-label={`Marcar ${c.numero} para simular a bobina`} />}
      </div>
      {c.titulo && <div className="pcv-card-t">{c.titulo}</div>}
      {c.cliente && <div className="pcv-card-c">{c.cliente}</div>}
      <div className="pcv-card-et">{e.nome ?? 'Etapa'}{e.posto ? ' · ' + e.posto : ''}{e.status === 'iniciada' ? ' · em andamento' : ''}</div>
      <div className="h-1.5 rounded bg-muted" role="progressbar" aria-label={`Progresso ${c.progresso}%`} aria-valuenow={c.progresso} aria-valuemin={0} aria-valuemax={100}>
        <div className="h-1.5 rounded bg-primary" style={{ width: `${c.progresso}%` }} />
      </div>
      <div className="pcv-card-f">
        {c.arte && <Badge variant={c.arte === 'ajuste' ? 'danger' : 'warning'}>{c.arte === 'ajuste' ? 'Ajuste pedido' : 'Aguardando cliente'}</Badge>}
        <span className={c.atraso ? 'pcv-late' : ''}>{c.atraso ?? (c.prazo ? 'entrega ' + d2(c.prazo) : 'sem prazo')}</span>
        <span className="pcv-who">{e.responsavel ?? 'sem responsável'} · {d2(e.inicio_previsto)} {e.inicio_previsto.slice(11, 16)}</span>
        {podeAlocar && e.status === 'pendente' && <Button size="sm" variant="ghost" onClick={onAlocar} aria-label={`Alocar ${e.nome ?? 'etapa'} de ${c.numero}`}>Alocar</Button>}
      </div>
    </div>
  );
}

function Conteudo({ p, cartoes, midias, pessoas }: { p: Props; cartoes: Cartao[]; midias: Midia[]; pessoas: Pessoas }) {
  const [f, setFiltro] = useState<Filtro>({ q: p.filtro?.q ?? '', so: p.filtro?.so ?? 'todas', resp: p.filtro?.resp ?? '', setor: p.filtro?.setor ?? '' });
  const [agrupar, setAgruparEstado] = useState<'setor' | 'maquina'>(p.filtro?.agrupar ?? 'setor');
  // UC-PCV-06: cada mudança regrava a URL (sem navegar), para recarregar ou trocar de vista com o mesmo filtro.
  const naUrl = (nf: Filtro, ag: 'setor' | 'maquina') =>
    window.history.replaceState(window.history.state, '', window.location.pathname + paraUrl(nf, ag === 'maquina' ? { agrupar: ag } : {}));
  const setF = (nf: Filtro) => { setFiltro(nf); naUrl(nf, agrupar); };
  const setAgrupar = (ag: 'setor' | 'maquina') => { setAgruparEstado(ag); naUrl(f, ag); };
  const [marcados, setMarcados] = useState<string[]>([]);
  const [alocando, setAlocando] = useState<Cartao | null>(null);
  const [simulando, setSimulando] = useState(false);
  const [aviso, setAviso] = useState<string | null>(null);
  const [arraste, setArraste] = useState<Arraste | null>(null);
  const arrasteRef = useRef<Arraste | null>(null);

  const lista = cartoes.filter((c) => passa(c, f));
  const setores = useMemo(() => [...new Set([...p.postos.map((x) => x.setor?.trim() || SEM_SETOR), ...cartoes.map((c) => setorDe(c.etapa))])], [p.postos, cartoes]);
  const resps = [...new Set(cartoes.map((c) => c.etapa.responsavel).filter((r): r is string => !!r))].sort();
  const cols = agrupar === 'setor'
    ? setores.filter((s) => !f.setor || s === f.setor).map((s) => ({ id: s, nome: s, itens: lista.filter((c) => colunaDe(c) === s) }))
    : p.postos.map((x) => ({ id: String(x.id), nome: x.nome, itens: lista.filter((c) => c.etapa.posto_id === x.id) })).filter((c) => c.itens.length);
  const selecionados = cartoes.filter((c) => marcados.includes(c.chave));
  const filtrado = temFiltro(f);
  const recarregar = (msg: string) => { setAviso(msg); setAlocando(null); setSimulando(false); setMarcados([]); router.reload({ only: ['cartoes', 'midias'] }); };
  // UC-PCV-07: mover só existe por setor (como no protótipo) e pede producao.etapa.mover (a mesma flag do alocador).
  const podeMover = p.pode_alocar && agrupar === 'setor';
  const destinosDe = (c: Cartao) => setores.filter((s) => s && s !== colunaDe(c));
  const mover = async (c: Cartao, setor: string) => {
    try {
      await json('POST', '/producao/quadro/mover', { os_tipo: c.os_tipo, os_id: c.os_id, setor });
      recarregar(`${c.numero} movida para ${setor} pelo PCP. Fica no histórico da OS com o seu nome.`);
    } catch (err) {
      setAviso((err as Error).message);
    }
  };
  // Arrastar com o ponteiro (o mesmo gesto do protótipo): soltar numa coluna = "Mover para" aquela coluna.
  const pegar = (c: Cartao) => (ev: ReactPointerEvent) => {
    arrasteRef.current = { chave: c.chave, x0: ev.clientX, y0: ev.clientY, x: ev.clientX, y: ev.clientY, moveu: false, sobre: null };
    const mv = (e: PointerEvent) => {
      const d = arrasteRef.current; if (!d) return;
      const moveu = d.moveu || Math.abs(e.clientX - d.x0) + Math.abs(e.clientY - d.y0) > 6;
      const col = (document.elementFromPoint(e.clientX, e.clientY) as HTMLElement | null)?.closest('[data-col]');
      arrasteRef.current = { ...d, x: e.clientX, y: e.clientY, moveu, sobre: col ? col.getAttribute('data-col') : null };
      if (moveu) { e.preventDefault(); setArraste(arrasteRef.current); }
    };
    const up = () => {
      const d = arrasteRef.current;
      window.removeEventListener('pointermove', mv); window.removeEventListener('pointerup', up);
      arrasteRef.current = null; setArraste(null);
      if (d?.moveu && d.sobre && d.sobre !== colunaDe(c)) void mover(c, d.sobre);
    };
    window.addEventListener('pointermove', mv); window.addEventListener('pointerup', up);
  };
  const arrastado = arraste?.moveu ? cartoes.find((c) => c.chave === arraste.chave) ?? null : null;

  return (
    <div className="pcv-body">
      <div className="pcv-filtro" role="search" aria-label="Filtrar OS">
        <div className="pcv-filtro-q"><Input value={f.q} placeholder="OS, cliente ou serviço" aria-label="Buscar OS" onChange={(e) => setF({ ...f, q: e.target.value })} /></div>
        <Segmented value={f.so} onValueChange={(v) => v && setF({ ...f, so: v as Filtro['so'] })} aria-label="Situação"
          options={[{ value: 'todas', label: 'Todas' }, { value: 'atrasadas', label: `Atrasadas ou vencendo (${cartoes.filter((c) => c.atraso).length})` },
            { value: 'arte', label: `Travadas por arte (${cartoes.filter((c) => c.arte).length})` }]} />
        <div className="pcv-filtro-r"><Select value={f.setor || TODOS} onValueChange={(v) => setF({ ...f, setor: v === TODOS ? '' : v })}>
          <SelectTrigger aria-label="Setor"><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value={TODOS}>Qualquer setor</SelectItem>{setores.filter(Boolean).map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}</SelectContent>
        </Select></div>
        <div className="pcv-filtro-r"><Select value={f.resp || TODOS} onValueChange={(v) => setF({ ...f, resp: v === TODOS ? '' : v })}>
          <SelectTrigger aria-label="Responsável"><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value={TODOS}>Qualquer responsável</SelectItem>{resps.map((r) => <SelectItem key={r} value={r}>{r}</SelectItem>)}</SelectContent>
        </Select></div>
        {filtrado && <Button size="sm" variant="link" onClick={() => setF(LIMPO)}>Limpar</Button>}
      </div>
      <div className="pcv-bar">
        <Segmented value={agrupar} onValueChange={(v) => v && setAgrupar(v as 'setor' | 'maquina')} aria-label="Agrupar"
          options={[{ value: 'setor', label: 'Por setor' }, { value: 'maquina', label: 'Por máquina' }]} />
        <span className="pcv-help">{lista.length} de {cartoes.length} OS. A OS anda sozinha quando o operador fecha a etapa.{podeMover ? ' Para mover à mão: arraste o cartão ou use o menu ⋮.' : ''}</span>
        {selecionados.length > 0 && (
          <span className="flex items-center gap-2" data-contract="producao-quadro-lote">
            <Badge variant="outline">{selecionados.length} marcada(s)</Badge>
            <Button size="sm" onClick={() => setSimulando(true)}>Simular bobina</Button>
            <Button size="sm" variant="ghost" onClick={() => setMarcados([])}>Desmarcar</Button>
          </span>
        )}
      </div>
      {aviso && <Alert><AlertDescription className="flex items-center gap-3">{aviso}<Button size="sm" variant="ghost" onClick={() => setAviso(null)}>Fechar</Button></AlertDescription></Alert>}
      {cartoes.length === 0 ? <p className="pcv-help">Nenhuma OS com etapa aberta. As OS entram aqui quando o roteiro gera as etapas.</p> : (
        <div className="pcv-board" role="list" aria-label={agrupar === 'setor' ? 'Quadro por setor' : 'Quadro por máquina'}>
          {cols.map((c) => (
            <section key={c.id} data-col={agrupar === 'setor' ? c.id : undefined} role="listitem" aria-label={`${c.nome}, ${c.itens.length} OS`} style={{ ['--c' as string]: 'var(--accent)' }}
              className={'pcv-col' + (arrastado && arraste?.sobre === c.id ? (colunaDe(arrastado) === c.id ? ' is-origem' : ' is-alvo') : '')}>
              <header className="pcv-col-h"><span className="pcv-dot" />{c.nome}<b>{c.itens.length}</b></header>
              <div className="pcv-col-b">{c.itens.length ? c.itens.map((o) => (
                <CartaoOs key={o.chave} c={o} podeAlocar={p.pode_alocar} marcado={marcados.includes(o.chave)} onAlocar={() => setAlocando(o)}
                  onMarcar={(v) => setMarcados((m) => (v ? [...m, o.chave] : m.filter((k) => k !== o.chave)))}
                  destinos={destinosDe(o)} onMover={podeMover ? (s) => void mover(o, s) : null} onPegar={podeMover ? pegar(o) : null}
                  arrastando={arrastado?.chave === o.chave} />
              )) : <p className="pcv-vazio">{filtrado ? 'Nada com esse filtro.' : 'Nada aqui agora.'}</p>}</div>
            </section>
          ))}
        </div>
      )}
      {arrastado && arraste && <div className="pcv-ghost" style={{ left: arraste.x + 12, top: arraste.y + 12 }} aria-hidden="true"><b className="mono">{arrastado.numero}</b> → {arraste.sobre ?? 'solte numa coluna'}</div>}
      {alocando && <Alocar cartao={alocando} pessoas={pessoas} onClose={() => setAlocando(null)} onSalvo={recarregar} />}
      {simulando && <SimuladorBobina cartoes={selecionados} midias={midias} podeAceitar={p.pode_aceitar_arranjo} onClose={() => setSimulando(false)} onAceito={recarregar} />}
    </div>
  );
}

export default function QuadroIndex(props: Props) {
  return (
    <div className="pcv-root" data-screen-label="Produção · Quadro">
      <div className="pcv-ph"><PageHeader title="Produção" suffix=" · Quadro" below={<ProducaoAbas ativa="/producao/quadro" />} /></div>
      <Deferred data={['cartoes', 'midias', 'pessoas']} fallback={<div className="pcv-body"><Skeleton className="h-64 w-full" /></div>}>
        <Conteudo p={props} cartoes={props.cartoes ?? []} midias={props.midias ?? []} pessoas={props.pessoas ?? { pessoas: [], por_posto: {} }} />
      </Deferred>
    </div>
  );
}

QuadroIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
