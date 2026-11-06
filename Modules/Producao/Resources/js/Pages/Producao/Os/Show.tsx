// @memcofre
//   tela: /producao/ordens/{id}
//   module: Producao
//   stories: UC-PCV-10 (Ficha da OS) · UC-PCOSF-01..03 · auditoria Producao lote 3.2
//   permissao: producao.access · Liberar e Refazer com producao.liberar · Cancelar item com vendas.gerente
//
// Ficha da OS por item. Âncora visual: `FichaOs` de prototipo-ui/cowork/Wagner/producao-cv-os.jsx (classes `pcv-*`
// do bundle). As ações chamam POST /producao/ordens/{id}/{liberar|refazer|cancelar} (lote 2.2), que conferem o
// papel de novo; aqui o botão só aparece na situação em que a ação vale (ProcessoDaOrdem::ACOES).
// Aba Custo (lote 3.3): só leitura, os valores vêm prontos do backend (PrevistoRealizadoDaOrdem + CustoRealService);
// a tela só formata. Materiais e Arte entram no lote 3.4.
import '../../../../../../../resources/css/cowork-producao-bundle.css';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Segmented } from '@/Components/ui/segmented';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import ProducaoAbas from '../_components/ProducaoAbas';
import { json, Recusa } from '../Quadro/_lib/json';

interface Etapa {
  id: number; nome: string | null; status: 'pendente' | 'iniciada' | 'concluida' | 'cancelada'; trilha: number; junta: string | null;
  posto_id: number | null; posto: string | null; setor: string | null; user_id: number | null; responsavel: string | null;
  previsto_h: number; apontado_h: number;
}
interface Os {
  id: number; numero: string; produto: string | null; cliente: string | null; venda: string | null; quantidade: string | null;
  prazo: string | null; situacao: string | null; situacao_nome: string | null;
}
interface PrevReal { status: 'em_andamento' | 'realizado' | 'cancelada'; receita: string; orcado: string | null; realizado: string | null;
  diferenca: string | null; diferenca_pct: string | null; margem_orcada_pct: string | null; margem_real_pct: string | null }
interface Custo { previsto_realizado: PrevReal; composicao: { maquina: string; pessoa: string; material: string; terceiros: string; total: string } | null }
interface Pessoas { pessoas: { id: number; nome: string }[]; por_posto: Record<number, number[]> }
interface Props { os: Os; etapas: Etapa[]; pode: { liberar: boolean; cancelar: boolean; custo: boolean }; pessoas?: Pessoas; custo?: Custo | null }

const EST: Record<Etapa['status'], string> = { pendente: 'Na fila', iniciada: 'Em andamento', concluida: 'Feito', cancelada: 'Cancelada' };
const h1 = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const brl = (v: string | null) => (v === null ? '—' : BRL.format(Number(v)));
const pct = (v: string | null) => (v === null ? '—' : v.replace('.', ',') + '%');
const d2 = (iso: string) => iso.slice(8, 10) + '/' + iso.slice(5, 7);

function EtapaLi({ e, i }: { e: Etapa; i: number }) {
  return (
    <li className={'is-' + (e.status === 'concluida' ? 'feito' : e.status === 'iniciada' ? 'andamento' : 'fila')} data-etapa={e.id}>
      <span className="pcv-et-n" aria-hidden="true">{i + 1}</span>
      <div className="pcv-et-m"><b>{e.nome ?? 'Etapa'}</b><small>{[e.trilha > 0 ? 'Trilha ' + String.fromCharCode(64 + e.trilha) : null, e.setor, e.posto, e.responsavel ?? 'sem responsável'].filter(Boolean).join(' · ')}</small></div>
      <div className="pcv-et-t mono" aria-label={`${h1(e.apontado_h)} de ${h1(e.previsto_h)} horas`}>
        <span className={e.apontado_h > e.previsto_h ? 'pcv-late' : ''}>{h1(e.apontado_h)}</span>/{h1(e.previsto_h)} h
      </div>
      <span className="pcv-chip">{EST[e.status]}</span>
    </li>
  );
}

function Liberar({ os, etapas, pessoas, onFeito }: { os: Os; etapas: Etapa[]; pessoas: Pessoas; onFeito: (m: string) => void }) {
  const abertas = etapas.filter((e) => e.status === 'pendente');
  const [resp, setResp] = useState<Record<number, number | ''>>(() => Object.fromEntries(abertas.map((e) => [e.id, e.user_id ?? ''])));
  const [erro, setErro] = useState<string | null>(null);
  const opcoes = (e: Etapa) => { const so = e.posto_id ? pessoas.por_posto[e.posto_id] : undefined; return so && so.length ? pessoas.pessoas.filter((p) => so.includes(p.id)) : pessoas.pessoas; };
  const enviar = async () => {
    setErro(null);
    try {
      await json('POST', `/producao/ordens/${os.id}/liberar`, { responsaveis: Object.fromEntries(Object.entries(resp).filter(([, v]) => v !== '')) });
      onFeito('OS liberada: foi para Na fila.');
    } catch (e) { setErro(e instanceof Recusa ? e.message : 'Não foi possível liberar.'); }
  };
  return (
    <div className="pcv-sec" data-contract="liberar">
      <span className="pcv-eyebrow">Responsável de cada etapa</span>
      <table className="pcv-baixa"><thead><tr><th>Etapa</th><th>Setor</th><th>Responsável</th></tr></thead>
        <tbody>{abertas.map((e) => (
          <tr key={e.id}><td>{e.nome}</td><td>{e.setor ?? '—'}</td><td>
            <Select value={resp[e.id] ? String(resp[e.id]) : undefined} onValueChange={(v) => setResp({ ...resp, [e.id]: Number(v) })}>
              <SelectTrigger aria-label={'Responsável da etapa ' + (e.nome ?? '')}><SelectValue placeholder="Escolha" /></SelectTrigger>
              <SelectContent>{opcoes(e).filter((p) => p.id > 0).map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.nome}</SelectItem>)}</SelectContent>
            </Select></td></tr>))}</tbody></table>
      <p className="pcv-help">Só o responsável aponta na etapa pelo celular. Dá para trocar depois, enquanto a etapa não começou.</p>
      {erro && <p className="pcv-prova-erro" role="alert">{erro}</p>}
      <div className="pcv-bar"><Button onClick={enviar}>Liberar</Button></div>
    </div>
  );
}

function AbaCusto({ c }: { c: Custo | null }) {
  if (!c) return <p className="pcv-help">Custo da OS pede a permissão de ver desempenho e custo.</p>;
  const pr = c.previsto_realizado;
  const acima = pr.diferenca !== null && !pr.diferenca.startsWith('-') && pr.diferenca !== '0.0000';
  return (
    <div className="pcv-sec" data-contract="custo">
      <table className="pcv-tbl" aria-label="Custo orçado e realizado">
        <thead><tr><th scope="col">Custo</th><th scope="col" className="ta-r">Orçado</th><th scope="col" className="ta-r">Realizado</th><th scope="col" className="ta-r">Diferença</th></tr></thead>
        <tbody><tr><td>{pr.status === 'realizado' ? 'Total congelado na conclusão' : pr.status === 'cancelada' ? 'OS cancelada' : 'Até agora'}</td>
          <td className="ta-r mono">{brl(pr.orcado)}</td><td className="ta-r mono">{brl(pr.realizado)}</td>
          <td className={'ta-r mono' + (acima ? ' pcv-late' : '')}>{pr.diferenca === null ? '—' : brl(pr.diferenca) + ' (' + pct(pr.diferenca_pct) + ')'}</td></tr>
        <tr className="pcv-tbl-sub"><td>Margem</td><td className="ta-r mono">{pct(pr.margem_orcada_pct)}</td><td className="ta-r mono">{pct(pr.margem_real_pct)}</td><td className="ta-r mono">receita {brl(pr.receita)}</td></tr></tbody>
      </table>
      {pr.orcado === null && <p className="pcv-help">O item da venda não guardou o custo previsto: sem orçado para comparar.</p>}
      {c.composicao && <table className="pcv-tbl" aria-label="Composição do custo real">
        <thead><tr><th scope="col">De onde vem o realizado</th><th scope="col" className="ta-r">Valor</th></tr></thead>
        <tbody>{([['Hora de máquina', c.composicao.maquina], ['Hora de pessoa', c.composicao.pessoa], ['Material baixado', c.composicao.material], ['Terceiros', c.composicao.terceiros]] as const)
          .map(([n, v]) => <tr key={n}><td>{n}</td><td className="ta-r mono">{brl(v)}</td></tr>)}
          <tr className="pcv-tbl-sub"><td>Total</td><td className="ta-r mono">{brl(c.composicao.total)}</td></tr></tbody>
      </table>}
      <p className="pcv-help">Só leitura. Horas apontadas × custo-hora do posto no início do apontamento, mais o material baixado.</p>
    </div>
  );
}

function Acoes({ p }: { p: Props }) {
  const [msg, setMsg] = useState<{ tom: 'ok' | 'recusa'; texto: string } | null>(null);
  const s = p.os.situacao;
  const feito = (texto: string) => { setMsg({ tom: 'ok', texto }); router.reload(); };
  const acao = async (rota: 'refazer' | 'cancelar', ok: string) => {
    if (rota === 'cancelar' && !window.confirm('Cancelar este item? As etapas abertas ficam canceladas.')) return;
    try { await json('POST', `/producao/ordens/${p.os.id}/${rota}`, {}); feito(ok); } catch (e) { setMsg({ tom: 'recusa', texto: e instanceof Recusa ? e.message : 'Não foi possível concluir.' }); }
  };
  const podeCancelar = p.pode.cancelar && (s === 'aguardando_liberacao' || s === 'aguardando_material');
  return (
    <div className="pcv-sit" data-contract="acoes">
      {msg && <Alert variant={msg.tom === 'ok' ? 'default' : 'destructive'} role="status"><AlertDescription>{msg.texto}</AlertDescription></Alert>}
      {s === 'aguardando_liberacao' && p.pode.liberar && (
        <Deferred data="pessoas" fallback={<p className="pcv-help">Carregando quem pode ser responsável…</p>}>
          <Liberar os={p.os} etapas={p.etapas} pessoas={p.pessoas ?? { pessoas: [], por_posto: {} }} onFeito={feito} />
        </Deferred>)}
      <div className="pcv-bar">
        {s === 'retrabalho' && p.pode.liberar && <Button onClick={() => acao('refazer', 'A OS voltou para Em execução.')}>Refazer</Button>}
        {podeCancelar && <Button variant="outline" onClick={() => acao('cancelar', 'Item cancelado.')}>Cancelar item</Button>}
      </div>
    </div>
  );
}

export default function OsShow(props: Props) {
  const { os, etapas } = props;
  const validas = etapas.filter((e) => e.status !== 'cancelada');
  const feitas = validas.filter((e) => e.status === 'concluida').length;
  const pct = validas.length ? Math.round((feitas / validas.length) * 100) : 0;
  const prev = validas.reduce((s, e) => s + e.previsto_h, 0), real = validas.reduce((s, e) => s + e.apontado_h, 0);
  const sub = [os.cliente, os.venda ? 'venda ' + os.venda : null, os.prazo ? 'entrega ' + d2(os.prazo) : null].filter(Boolean).join(' · ');
  const paralelas = etapas.some((e) => e.trilha > 0);
  const [aba, setAba] = useState('etapas');
  return (
    <div className="pcv-root" data-screen-label="Produção · Ficha da OS">
      <div className="pcv-ph"><PageHeader title={os.numero} suffix={os.produto ? ' · ' + os.produto : ''} below={<ProducaoAbas ativa="/producao/quadro" />} /></div>
      <div className="pcv-body"><div className="pcv-ficha" data-contract="ficha">
        <p className="pcv-help">{sub || 'Sem venda vinculada'} · <b>{os.situacao_nome ?? 'Sem situação'}</b></p>
        <div className="pcv-ficha-top" data-contract="resumo">
          <div><span className="pcv-eyebrow">Progresso</span><div className="h-1.5 rounded bg-muted" role="progressbar" aria-label={`Progresso ${pct}%`} aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100}><div className="h-1.5 rounded bg-primary" style={{ width: `${pct}%` }} /></div> <small>{pct}%</small></div>
          <div><span className="pcv-eyebrow">Tempo</span><b className="mono">{h1(real)} h</b> <small>de {h1(prev)} h previstas</small></div>
        </div>
        <Acoes p={props} />
        <Segmented value={aba} onValueChange={(v) => v && setAba(v)} aria-label="Seções da OS"
          options={[{ value: 'etapas', label: `Etapas (${etapas.length})` }, ...(props.pode.custo ? [{ value: 'custo', label: 'Custo' }] : [])]} />
        {aba === 'etapas' && <>
          {paralelas && <p className="pcv-help">Etapas em paralelo estão marcadas pela trilha.</p>}
          <ol className="pcv-etapas" data-contract="etapas">{etapas.map((e, i) => <EtapaLi key={e.id} e={e} i={i} />)}</ol>
          {etapas.length === 0 && <p className="pcv-help">Esta OS ainda não tem etapas programadas.</p>}
        </>}
        {aba === 'custo' && <Deferred data="custo" fallback={<Skeleton className="h-32 w-full" />}><AbaCusto c={props.custo ?? null} /></Deferred>}
      </div></div>
    </div>
  );
}

OsShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
