// @memcofre
//   tela: /producao/linha
//   module: Producao
//   stories: playbook Produção thread 07 (UC-PCV-08, UC-PCV-09, UC-PCV-30) · US-PCP-015 · US-PCP-012 (gargalo, parte)
//   permissao: producao.access · reprogramar e aplicar a sugestão com producao.etapa.mover
//
// Linha do tempo por máquina. Âncora visual: `Linha` de prototipo-ui/cowork/Wagner/producao-cv-page.jsx (classes
// `pcv-*` do bundle), já ajustada às regras de Fluxos no #9763:
// - só a etapa que não começou se reprograma (alocador UC-FLX-10); concluída e em andamento ficam fixas;
// - trocar de máquina só dentro do setor; se o responsável não opera a de destino, pergunta quem faz (UC-FLX-08);
// - o estouro é o fim do turno da empresa e a carga é contra a capacidade do posto (D-TURNO, D4);
// - o dia seguinte é o próximo dia útil (UC-FLX-06); travado por arte ou falta de material (D3) move e segue travado;
// - passar da entrega prometida (US-PCP-028) move, mas avisa.
// O filtro é o do Quadro (`../_lib/filtro.ts`, UC-PCV-06) e mora na URL. Reprogramar = PUT /producao/etapas/{id}/reprogramar.
import '../../../../../../../resources/css/cowork-producao-bundle.css';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useRef, useState, type KeyboardEvent as ReactKeyboardEvent, type PointerEvent as ReactPointerEvent, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Segmented } from '@/Components/ui/segmented';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import ProducaoAbas from '../_components/ProducaoAbas';
import { LIMPO, filtrado as temFiltro, passa, paraUrl, setorDe, type Filtravel, type Filtro } from '../_lib/filtro';
import { json, Recusa } from '../Quadro/_lib/json';

interface Pessoa { id: number; nome: string }
interface Posto { id: number; nome: string; setor: string | null; horas: number; capacidade: number; operadores: Pessoa[] }
interface Bloco {
  id: number; numero: string; cliente: string | null; etapa: string | null; posto_id: number; status: 'pendente' | 'iniciada' | 'concluida';
  user_id: number | null; responsavel: string | null; inicio: string; fim: string; travas: string[]; estouro: boolean; prazo: string | null; passa_da_promessa: boolean;
}
interface Ponta { posto_id: number; nome: string; horas: number; depois: number; capacidade: number; inicio: string }
interface Sugestao { programacao_id: number; numero: string; etapa: string | null; horas: number; de: Ponta; para: Ponta }
interface Linha { postos: Posto[]; blocos: Bloco[]; sugestao: Sugestao | null }
interface Evento { quando: string; texto: string; etapa: string | null; quem: string | null; os: string | null; posto_id: number | null }
interface Props {
  hoje: string; dias: { hoje: string; proximo: string }; dia: 'hoje' | 'proximo'; turno: { inicio: string; fim: string };
  filtro: Filtro; pode_mover: boolean; linha?: Linha; eventos?: Evento[];
}
interface Msg { tom: 'ok' | 'aviso' | 'recusa'; texto: string; desfazer?: () => void }
interface Escolha { b: Bloco; posto: Posto; inicio: number; opcoes: Pessoa[] }
interface Arraste { id: number; x0: number; ini0: number; w: number; ini: number; posto: number; moveu: boolean }

const TODOS = '__todos__';
const SNAP = 0.25;
const horaDe = (iso: string) => Number(iso.slice(11, 13)) + Number(iso.slice(14, 16)) / 60;
const hhmm = (h: number) => String(Math.floor(h)).padStart(2, '0') + ':' + String(Math.round((h % 1) * 60)).padStart(2, '0');
const h1 = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const d2 = (iso: string) => iso.slice(8, 10) + '/' + iso.slice(5, 7);
const semana = (iso: string) => ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'][new Date(iso + 'T12:00:00').getDay()];

function Conteudo({ p, linha, eventos }: { p: Props; linha: Linha; eventos: Evento[] }) {
  const [f, setFiltro] = useState<Filtro>({ q: p.filtro?.q ?? '', so: p.filtro?.so ?? 'todas', resp: p.filtro?.resp ?? '', setor: p.filtro?.setor ?? '' });
  const [msg, setMsg] = useState<Msg | null>(null);
  const [escolha, setEscolha] = useState<Escolha | null>(null);
  const [maqEv, setMaqEv] = useState<number | null>(null);
  const [arraste, setArraste] = useState<Arraste | null>(null);
  const arrasteRef = useRef<Arraste | null>(null);
  const diaIso = p.dias[p.dia];
  const fimTurno = horaDe('0000-00-00T' + p.turno.fim);
  const H0 = Math.min(Math.floor(horaDe('0000-00-00T' + p.turno.inicio)), ...linha.blocos.map((b) => Math.floor(horaDe(b.inicio))));
  const H1 = Math.max(Math.ceil(fimTurno) + 1, ...linha.blocos.map((b) => Math.ceil(horaDe(b.fim))));
  const pos = (h: number) => ((h - H0) / (H1 - H0)) * 100;
  const posto = (id: number) => linha.postos.find((x) => x.id === id);
  const urlDo = (nf: Filtro, dia: string) => window.location.pathname + paraUrl(nf, dia === 'proximo' ? { dia } : {});
  const setF = (nf: Filtro) => { setFiltro(nf); window.history.replaceState(window.history.state, '', urlDo(nf, p.dia)); };
  // O mesmo `passa` do Quadro (UC-PCV-06): o bloco vira um "cartão" com atraso e arte vindos do prazo e das travas.
  const comoCartao = (b: Bloco): Filtravel => ({ numero: b.numero, cliente: b.cliente, titulo: b.etapa,
    atraso: b.prazo === null ? null : b.prazo < p.hoje ? 'atrasada' : b.prazo === p.hoje ? 'vence hoje' : null,
    arte: b.travas.find((t) => t.includes('arte')) ?? null, etapa: { responsavel: b.responsavel, setor: posto(b.posto_id)?.setor ?? null } });
  const visiveis = linha.blocos.filter((b) => passa(comoCartao(b), f));
  const resps = [...new Set(linha.blocos.map((b) => b.responsavel).filter((r): r is string => !!r))].sort();
  const setores = [...new Set(linha.postos.map((x) => setorDe(x)))];

  const reprogramar = async (b: Bloco, destino: Posto, ini: number, userId?: number): Promise<boolean> => {
    const origem = posto(b.posto_id);
    if (b.status !== 'pendente') {
      setMsg({ tom: 'recusa', texto: `${b.numero} ${b.status === 'concluida' ? 'já foi concluída' : 'já começou'} em ${origem?.nome ?? 'sua máquina'}: só a etapa que ainda não começou se reprograma.${b.status === 'iniciada' ? ' Para mudar, o operador pausa ou conclui no apontamento.' : ''}` });
      return false;
    }
    if (origem && setorDe(destino) !== setorDe(origem)) {
      setMsg({ tom: 'recusa', texto: `Não dá para levar ${b.numero} para ${destino.nome}: é de outro setor (${setorDe(destino)}).` });
      return false;
    }
    const dur = horaDe(b.fim) - horaDe(b.inicio);
    const ni = Math.max(H0, Math.min(H1 - dur, Math.round(ini / SNAP) * SNAP));
    try {
      const r = await json<{ user_id: number | null }>('PUT', `/producao/etapas/${b.id}/reprogramar`,
        { posto_id: destino.id, inicio_previsto: `${diaIso} ${hhmm(ni)}:00`, ...(userId ? { user_id: userId } : {}) });
      const quem = destino.operadores.find((x) => x.id === r.user_id)?.nome ?? (r.user_id === b.user_id ? b.responsavel : null);
      const avisos = [ni + dur > fimTurno ? `termina ${hhmm(ni + dur)}, depois do fim do turno (${p.turno.fim})` : null,
        b.prazo !== null && (diaIso > b.prazo || (diaIso === b.prazo && ni + dur > fimTurno)) ? `passa da entrega prometida (${d2(b.prazo)})` : null].filter(Boolean);
      const aviso = quem ? ` ${quem} recebeu o aviso no app.` : '';
      setEscolha(null);
      setMsg({ tom: avisos.length ? 'aviso' : 'ok', texto: `${b.numero} em ${destino.nome}, ${hhmm(ni)}–${hhmm(ni + dur)}${avisos.length ? ': ' + avisos.join(' · ') : ''}.${aviso}` });
      router.reload({ only: ['linha', 'eventos'] });
      return true;
    } catch (err) {
      if (err instanceof Recusa && err.codigo === 'operador_destino') {
        const ids = (err.contexto?.operadores as number[] | undefined) ?? [];
        setMsg(null);
        setEscolha({ b, posto: destino, inicio: ni, opcoes: destino.operadores.filter((x) => ids.includes(x.id)) });
      } else {
        setMsg({ tom: 'recusa', texto: (err as Error).message });
      }
      return false;
    }
  };

  // Arrastar com o ponteiro: horizontal muda o horário (15 em 15 min); para outra linha, troca a máquina.
  const pegar = (b: Bloco) => (ev: ReactPointerEvent<HTMLButtonElement>) => {
    if (!p.pode_mover || ev.button !== 0 || b.status !== 'pendente') return;
    const w = (ev.currentTarget.parentElement as HTMLElement).getBoundingClientRect().width;
    arrasteRef.current = { id: b.id, x0: ev.clientX, ini0: horaDe(b.inicio), w, ini: horaDe(b.inicio), posto: b.posto_id, moveu: false };
    const mv = (e: PointerEvent) => {
      const d = arrasteRef.current; if (!d) return;
      const dx = e.clientX - d.x0; const moveu = d.moveu || Math.abs(dx) > 4;
      const linhaEl = (document.elementFromPoint(e.clientX, e.clientY) as HTMLElement | null)?.closest('[data-posto]');
      arrasteRef.current = { ...d, moveu, ini: Math.round((d.ini0 + (dx / d.w) * (H1 - H0)) / SNAP) * SNAP, posto: linhaEl ? Number(linhaEl.getAttribute('data-posto')) : d.posto };
      if (moveu) { e.preventDefault(); setArraste(arrasteRef.current); }
    };
    const up = () => {
      const d = arrasteRef.current;
      window.removeEventListener('pointermove', mv); window.removeEventListener('pointerup', up);
      arrasteRef.current = null; setArraste(null);
      const destino = d && posto(d.posto);
      if (d?.moveu && destino) void reprogramar(b, destino, d.ini);
    };
    window.addEventListener('pointermove', mv); window.addEventListener('pointerup', up);
  };
  const tecla = (b: Bloco) => (e: ReactKeyboardEvent<HTMLButtonElement>) => {
    if (!e.altKey || !p.pode_mover) return;
    const atual = posto(b.posto_id); if (!atual) return;
    const mesmos = linha.postos.filter((x) => setorDe(x) === setorDe(atual)); const ix = mesmos.findIndex((x) => x.id === atual.id);
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); void reprogramar(b, atual, horaDe(b.inicio) + (e.key === 'ArrowRight' ? SNAP : -SNAP)); }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); const alvo = mesmos[ix + (e.key === 'ArrowDown' ? 1 : -1)]; if (alvo) void reprogramar(b, alvo, horaDe(b.inicio)); }
  };

  // UC-PCV-09: aplicar a sugestão e desfazer são duas reprogramações (o histórico guarda as duas).
  const s = linha.sugestao;
  const aplicar = async () => {
    const b = s && linha.blocos.find((x) => x.id === s.programacao_id);
    const de = s && posto(s.de.posto_id); const para = s && posto(s.para.posto_id);
    if (!s || !b || !de || !para) return;
    if (await reprogramar(b, para, horaDe(s.para.inicio))) {
      setMsg({ tom: 'ok', texto: `${s.numero} foi para a ${para.nome} às ${hhmm(horaDe(s.para.inicio))}. A ${de.nome} fecha o dia com ${h1(s.de.depois)} de ${h1(s.de.capacidade)} h.`,
        desfazer: () => { void reprogramar({ ...b, posto_id: para.id, inicio: s.para.inicio, fim: `${diaIso}T${hhmm(horaDe(s.para.inicio) + s.horas)}` }, de, horaDe(s.de.inicio)); } });
    }
  };
  const estourados = linha.postos.filter((x) => x.capacidade > 0 && x.horas > x.capacidade);
  const arrastado = arraste?.moveu ? linha.blocos.find((b) => b.id === arraste.id) ?? null : null;
  const horas = Array.from({ length: H1 - H0 + 1 }, (_, i) => H0 + i);
  const evs = eventos.filter((e) => maqEv === null || e.posto_id === maqEv);

  return (
    <div className="pcv-body">
      <div className="pcv-filtro" role="search" aria-label="Filtrar OS">
        <div className="pcv-filtro-q"><Input value={f.q} placeholder="OS, cliente ou serviço" aria-label="Buscar OS" onChange={(e) => setF({ ...f, q: e.target.value })} /></div>
        <Segmented value={f.so} onValueChange={(v) => v && setF({ ...f, so: v as Filtro['so'] })} aria-label="Situação"
          options={[{ value: 'todas', label: 'Todas' }, { value: 'atrasadas', label: 'Atrasadas ou vencendo' }, { value: 'arte', label: 'Travadas por arte' }]} />
        <div className="pcv-filtro-r"><Select value={f.setor || TODOS} onValueChange={(v) => setF({ ...f, setor: v === TODOS ? '' : v })}>
          <SelectTrigger aria-label="Setor"><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value={TODOS}>Qualquer setor</SelectItem>{setores.map((x) => <SelectItem key={x} value={x}>{x}</SelectItem>)}</SelectContent>
        </Select></div>
        <div className="pcv-filtro-r"><Select value={f.resp || TODOS} onValueChange={(v) => setF({ ...f, resp: v === TODOS ? '' : v })}>
          <SelectTrigger aria-label="Responsável"><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value={TODOS}>Qualquer responsável</SelectItem>{resps.map((r) => <SelectItem key={r} value={r}>{r}</SelectItem>)}</SelectContent>
        </Select></div>
        {temFiltro(f) && <Button size="sm" variant="link" onClick={() => setF(LIMPO)}>Limpar</Button>}
      </div>
      <div className="pcv-bar">
        <Segmented value={p.dia} aria-label="Dia" onValueChange={(v) => v && v !== p.dia && router.visit(urlDo(f, v))}
          options={[{ value: 'hoje', label: `Hoje, ${semana(p.dias.hoje)} ${d2(p.dias.hoje)}` }, { value: 'proximo', label: `Próximo dia útil, ${semana(p.dias.proximo)} ${d2(p.dias.proximo)}` }]} />
        <span className="pcv-help">
          {p.pode_mover ? 'Arraste um bloco que ainda não começou para mudar o horário ou levar para outra máquina do mesmo setor. No teclado: Alt + setas. ' : ''}
          Tracejado = travado por arte ou falta de material · borda vermelha = passa do fim do turno ({p.turno.inicio}–{p.turno.fim}) ou da entrega prometida. O dia seguinte pula sábado, domingo e feriados.
        </span>
      </div>
      {msg && (
        <Alert variant={msg.tom === 'ok' ? 'default' : 'destructive'} data-contract="producao-linha-retorno">
          <AlertTitle>{msg.tom === 'recusa' ? 'Não movido' : msg.tom === 'aviso' ? 'Movido, com aviso' : 'Movido'}</AlertTitle>
          <AlertDescription className="flex flex-wrap items-center gap-3">{msg.texto}
            {msg.desfazer && <Button size="sm" variant="outline" onClick={() => { const d = msg.desfazer; setMsg(null); d?.(); }}>Desfazer</Button>}
            <Button size="sm" variant="ghost" onClick={() => setMsg(null)}>Fechar</Button>
          </AlertDescription>
        </Alert>
      )}
      {escolha && (
        <Alert data-contract="producao-linha-quem-faz">
          <AlertTitle>Quem faz na {escolha.posto.nome}?</AlertTitle>
          <AlertDescription className="flex flex-wrap items-center gap-2">
            {escolha.b.responsavel ?? 'O responsável'} não opera a {escolha.posto.nome}. Escolha um operador dela para levar {escolha.b.numero} ({hhmm(escolha.inicio)}); quem você escolher passa a responder pela etapa e recebe o aviso no app.
            {escolha.opcoes.map((o) => <Button key={o.id} size="sm" onClick={() => void reprogramar(escolha.b, escolha.posto, escolha.inicio, o.id)}>{o.nome}</Button>)}
            <Button size="sm" variant="ghost" onClick={() => setEscolha(null)}>Cancelar</Button>
          </AlertDescription>
        </Alert>
      )}
      <div className="pcv-gantt" role="table" aria-label={`Linha do tempo por máquina — ${p.dia === 'hoje' ? 'hoje' : 'próximo dia útil'}, ${d2(diaIso)}`} data-contract="producao-linha-gantt">
        <div className="pcv-g-row pcv-g-head" role="row">
          <div className="pcv-g-lab" role="columnheader">Máquina</div>
          <div className="pcv-g-track" role="columnheader">{horas.map((h) => <span key={h} style={{ left: pos(h) + '%' }}>{String(h).padStart(2, '0')}h</span>)}</div>
        </div>
        {linha.postos.length === 0 && <p className="pcv-help p-4">Nenhuma máquina cadastrada. Cadastre em Postos e cadastros.</p>}
        {linha.postos.map((m) => {
          const pode = arrastado && setorDe(posto(arrastado.posto_id) ?? m) === setorDe(m);
          const cheio = m.capacidade > 0 && m.horas > m.capacidade;
          return (
            <div key={m.id} role="row" data-posto={m.id} className={'pcv-g-row' + (arrastado ? (pode ? (arraste?.posto === m.id ? ' is-alvo is-sobre' : ' is-alvo') : ' is-fora') : '')}>
              <div className="pcv-g-lab" role="rowheader">
                <button type="button" className="pcv-g-maq" onClick={() => setMaqEv(m.id)} aria-label={`Ver eventos de ${m.nome}`}><b>{m.nome}</b></button>
                <small>{setorDe(m)} · {m.operadores.length ? m.operadores.map((o) => o.nome).join(', ') : 'qualquer operador'} · <span className={cheio ? 'pcv-late' : ''}>{h1(m.horas)} de {h1(m.capacidade)} h</span></small>
              </div>
              <div className="pcv-g-track" role="cell">
                <i className="pcv-g-fim" style={{ left: pos(fimTurno) + '%' }} aria-hidden="true" />
                {arrastado && arraste?.posto === m.id && (() => { const dur = horaDe(arrastado.fim) - horaDe(arrastado.inicio); const ini = Math.max(H0, arraste.ini);
                  return <i className="pcv-g-prev" style={{ left: pos(ini) + '%', width: pos(ini + dur) - pos(ini) + '%' }} aria-hidden="true"><span>{hhmm(ini)}</span></i>; })()}
                {visiveis.filter((b) => b.posto_id === m.id).map((b) => {
                  const fixo = b.status !== 'pendente';
                  const aviso = [b.estouro ? `termina depois do fim do turno (${p.turno.fim})` : null, b.passa_da_promessa && b.prazo ? `passa da entrega prometida (${d2(b.prazo)})` : null].filter(Boolean).join(' · ');
                  const movel = p.pode_mover && !fixo;
                  return (
                    <button key={b.id} type="button" data-bloco={b.id}
                      className={`pcv-g-blk is-${b.status === 'pendente' ? 'fila' : b.status === 'iniciada' ? 'andamento' : 'feito'}` + (b.travas.length ? ' is-bloq' : '') + (aviso ? ' is-conf' : '') + (arrastado?.id === b.id ? ' is-arr' : '') + (movel ? ' is-movel' : '')}
                      style={{ left: pos(horaDe(b.inicio)) + '%', width: pos(horaDe(b.fim)) - pos(horaDe(b.inicio)) + '%' }}
                      aria-label={`${b.numero}${b.cliente ? ', ' + b.cliente : ''}, ${hhmm(horaDe(b.inicio))} a ${hhmm(horaDe(b.fim))} em ${m.nome}, responsável ${b.responsavel ?? 'não definido'}${b.travas.length ? ', ' + b.travas.join(', ') : ''}${aviso ? ', ' + aviso : ''}${fixo ? (b.status === 'concluida' ? ', concluída' : ', em andamento, não se reprograma') : ''}.${movel ? ' Alt + setas move.' : ''}`}
                      title={`${b.numero} · ${hhmm(horaDe(b.inicio))}–${hhmm(horaDe(b.fim))} · ${b.responsavel ?? 'sem responsável'}${b.travas.length ? ' — ' + b.travas.join(' · ') : ''}${aviso ? ' — ' + aviso : ''}`}
                      onPointerDown={pegar(b)} onKeyDown={tecla(b)}>
                      <b className="mono">{b.numero}</b><span>{b.cliente ?? b.etapa ?? ''}</span>
                    </button>
                  );
                })}
              </div>
            </div>
          );
        })}
      </div>
      {p.dia === 'hoje' && (
        <Card data-contract="producao-linha-eventos">
          <CardHeader className="flex-row items-center justify-between gap-2">
            <div><CardTitle>Eventos das máquinas hoje</CardTitle>
              <CardDescription>{maqEv !== null ? posto(maqEv)?.nome : 'Todas as máquinas — clique numa máquina para filtrar.'}</CardDescription></div>
            {maqEv !== null && <Button size="sm" variant="link" onClick={() => setMaqEv(null)}>Ver todas</Button>}
          </CardHeader>
          <CardContent className="p-0">
            {evs.length ? (
              <ol className="pcv-feed is-compacto" aria-live="polite" aria-relevant="additions">
                {evs.map((e, k) => (
                  <li key={k + e.quando + e.texto}>
                    <span className="pcv-feed-dot" aria-hidden="true" />
                    <div className="pcv-feed-m">
                      <span className="pcv-feed-t">{e.texto}{e.etapa ? ' · ' + e.etapa : ''}</span>
                      <small><time className="mono">{e.quando.slice(11, 16)}</time>{e.quem ? ' · ' + e.quem : ''}{e.posto_id !== null && posto(e.posto_id) ? ' · ' + posto(e.posto_id)?.nome : ''}</small>
                    </div>
                    {e.os && <span className="mono text-xs">{e.os}</span>}
                  </li>
                ))}
              </ol>
            ) : <p className="pcv-help px-4 pb-4">Sem eventos {maqEv !== null ? 'desta máquina' : 'das máquinas'} hoje.</p>}
          </CardContent>
        </Card>
      )}
      {s ? (
        <Alert data-contract="producao-linha-sugestao">
          <AlertTitle>Sugestão de reprogramação</AlertTitle>
          <AlertDescription className="flex flex-wrap items-center gap-3">
            Mover {s.numero} ({h1(s.horas)} h) da {s.de.nome} para a {s.para.nome} às {hhmm(horaDe(s.para.inicio))} tira a {s.de.nome} do vermelho ({h1(s.de.horas)} h → {h1(s.de.depois)} h, capacidade de {h1(s.de.capacidade)} h no dia). Ou arraste o bloco você mesmo.
            {p.pode_mover && <Button size="sm" onClick={() => void aplicar()}>Aplicar</Button>}
          </AlertDescription>
        </Alert>
      ) : estourados.length > 0 && (
        <Alert variant="destructive" data-contract="producao-linha-sugestao">
          <AlertTitle>{estourados.map((x) => x.nome).join(', ')} {estourados.length === 1 ? 'passa' : 'passam'} da capacidade</AlertTitle>
          <AlertDescription>Nenhuma etapa pendente sozinha tira {estourados.length === 1 ? 'a máquina' : 'as máquinas'} do vermelho sem estourar outra do mesmo setor. Arraste os blocos ou ajuste o turno.</AlertDescription>
        </Alert>
      )}
    </div>
  );
}

export default function LinhaIndex(props: Props) {
  return (
    <div className="pcv-root" data-screen-label="Produção · Linha do tempo">
      <div className="pcv-ph"><PageHeader title="Produção" suffix=" · Linha do tempo" below={<ProducaoAbas ativa="/producao/linha" />} /></div>
      <Deferred data={['linha', 'eventos']} fallback={<div className="pcv-body"><Skeleton className="h-64 w-full" /></div>}>
        <Conteudo p={props} linha={props.linha ?? { postos: [], blocos: [], sugestao: null }} eventos={props.eventos ?? []} />
      </Deferred>
    </div>
  );
}

LinhaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
