// @memcofre
//   tela: /producao/postos
//   module: Producao
//   stories: playbook Produção thread 03 + E03 (UC-PCV-33)
//   permissao: producao.access · custo-hora da pessoa só com producao.desempenho.ver
//   custeio RKW (US-FIN-070 · UC-PCCH-05): producao.desempenho.ver + financeiro.relatorios.view
//
// Cadastros do PCP: postos e máquinas (com os motivos de pausa e de refugo de cada posto),
// veículos, ferramentas com QR e custo-hora da pessoa. Não há vista própria no protótipo
// (`pcv-roteiros` é a âncora da thread); a forma segue as classes `pcv-*` do mesmo bundle e o
// celular `Mobile Producao.dc.html` A4/A5/I7 para o que é motivo, veículo e ferramenta.
import '../../../../../../../resources/css/cowork-producao-bundle.css';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Pencil, Plus } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import ProducaoAbas from '../_components/ProducaoAbas';
import EditorPosto, { type Posto } from './_components/EditorPosto';
import CusteioRkw from './_components/CusteioRkw';

interface Ferramenta { id: number; codigo_qr: string; nome: string; ativo: boolean }
interface Colaborador { id: number; nome: string; custo_hora: string | null }
interface Props {
  motivos_padrao: Record<'pausa' | 'refugo', string[]>;
  pode_ver_custo_pessoa: boolean;
  pode_aplicar_rkw?: boolean; // US-FIN-070: producao.desempenho.ver + financeiro.relatorios.view
  postos?: Posto[]; ferramentas?: Ferramenta[]; colaboradores?: Colaborador[];
}

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const brl = (v: string | null) => (v === null ? '—' : BRL.format(Number(v)));

function Bloco({ titulo, nota, acao, children }: { titulo: string; nota: string; acao?: ReactNode; children: ReactNode }) {
  return (
    <Card><CardContent className="flex flex-col gap-3 p-4">
      <div className="flex items-start gap-3"><div className="min-w-0 flex-1"><h2 className="pcv-h2">{titulo}</h2><p className="pcv-help">{nota}</p></div>{acao}</div>
      {children}
    </CardContent></Card>
  );
}

function ListaPostos({ postos, onEditar }: { postos: Posto[]; onEditar: (p: Posto) => void }) {
  if (postos.length === 0) return <p className="pcv-help">Nenhum cadastrado ainda.</p>;
  return (
    <ul className="flex flex-col gap-2">
      {postos.map((p) => (
        <li key={p.id} className="flex flex-wrap items-center gap-3 rounded-lg border border-border p-3" data-contract="producao-posto">
          <div className="min-w-0 flex-1"><b className="text-sm">{p.nome}</b>
            <span className="pcv-help block">{[p.setor, p.veiculo ? p.placa : null, p.tipo_custo === 'maquina' ? 'custo da máquina' : 'custo da pessoa'].filter(Boolean).join(' · ')}</span>
            {!p.veiculo && <span className="pcv-help block">Pausa: {p.motivos.pausa.join(', ') || '—'} · Refugo: {p.motivos.refugo.join(', ') || '—'}</span>}</div>
          <span className="mono text-sm">{brl(p.custo_hora)}/h{p.veiculo && p.custo_km ? ` · ${brl(p.custo_km)}/km` : ''}</span>
          {!p.ativo && <Badge variant="outline">Inativo</Badge>}
          <Button size="sm" variant="ghost" onClick={() => onEditar(p)} aria-label={`Editar ${p.nome}`}><Pencil className="size-3.5" aria-hidden="true" /></Button>
        </li>
      ))}
    </ul>
  );
}

function Ferramentas({ itens }: { itens: Ferramenta[] }) {
  const form = useForm({ codigo_qr: '', nome: '' });
  return (
    <Bloco titulo="Ferramentas" nota="Cada ferramenta tem um código QR para sair e voltar com a equipe de campo.">
      <form className="flex flex-wrap items-start gap-2" onSubmit={(e) => { e.preventDefault(); form.post('/producao/ferramentas', { preserveScroll: true, onSuccess: () => form.reset() }); }}>
        <div><Input aria-label="Código QR" placeholder="Código QR" value={form.data.codigo_qr} onChange={(e) => form.setData('codigo_qr', e.target.value)} />
          {form.errors.codigo_qr && <p className="text-xs text-destructive">{form.errors.codigo_qr}</p>}</div>
        <div className="w-64"><Input aria-label="Nome da ferramenta" placeholder="Furadeira de impacto" value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} /></div>
        <Button type="submit" variant="outline" disabled={form.processing}><Plus className="size-3.5" aria-hidden="true" /> Adicionar</Button>
      </form>
      <ul className="flex flex-wrap gap-2">{itens.map((f) => <li key={f.id}><Badge variant="outline"><span className="mono">{f.codigo_qr}</span>&nbsp;{f.nome}</Badge></li>)}</ul>
    </Bloco>
  );
}

// A largura dos campos vai num <div> em volta: o .cw-input (cowork-fields.css) fica fora de @layer
// e põe width:100%, que vence o w-28/w-64 do Tailwind no próprio <Input> (§5 2026-10-14).
function CustoPessoa({ c }: { c: Colaborador }) {
  const [valor, setValor] = useState(c.custo_hora ?? '');
  const salvar = () => router.put(`/producao/colaboradores/${c.id}/custo-hora`, { custo_hora: valor.replace(',', '.') }, { preserveScroll: true });
  return (
    <li className="flex items-center gap-3"><span className="min-w-0 flex-1 text-sm">{c.nome}</span>
      <div className="w-28 shrink-0"><Input inputMode="decimal" aria-label={`Custo-hora de ${c.nome}`} value={valor} onChange={(e) => setValor(e.target.value)} /></div>
      <Button size="sm" variant="outline" onClick={salvar}>Salvar</Button></li>
  );
}

function Conteudo({ postos, ferramentas, colaboradores, podeVerCusto, podeAplicarRkw }: { postos: Posto[]; ferramentas: Ferramenta[]; colaboradores: Colaborador[]; podeVerCusto: boolean; podeAplicarRkw: boolean }) {
  const [editando, setEditando] = useState<Posto | 'posto' | 'veiculo' | null>(null);
  return (
    <div className="pcv-body">
      <Bloco titulo="Postos e máquinas" nota="Capacidade e custo-hora são da sua empresa. Os motivos de pausa e de refugo do posto são a lista que o operador vê no celular."
        acao={<Button size="sm" variant="outline" onClick={() => setEditando('posto')}><Plus className="size-3.5" aria-hidden="true" /> Novo posto</Button>}>
        <ListaPostos postos={postos.filter((p) => !p.veiculo)} onEditar={setEditando} />
      </Bloco>
      <Bloco titulo="Veículos" nota="Veículo é posto de máquina: entra no custo da OS por hora e por km."
        acao={<Button size="sm" variant="outline" onClick={() => setEditando('veiculo')}><Plus className="size-3.5" aria-hidden="true" /> Novo veículo</Button>}>
        <ListaPostos postos={postos.filter((p) => p.veiculo)} onEditar={setEditando} />
      </Bloco>
      <Ferramentas itens={ferramentas} />
      {podeVerCusto && (
        <Bloco titulo="Custo-hora da pessoa" nota="Usado no custo real das etapas manuais. Só quem vê o desempenho da equipe vê e altera este valor.">
          <ul className="flex flex-col gap-2">{colaboradores.map((c) => <CustoPessoa key={c.id} c={c} />)}</ul>
        </Bloco>
      )}
      {podeAplicarRkw && (
        <Bloco titulo="Custo-hora pelo rateio das despesas fixas" nota="As despesas fixas do mês, classificadas no plano de contas do Financeiro, divididas pelas horas apontadas nos postos. Aplicar troca o custo-hora do posto; cada mês se aplica uma vez.">
          <CusteioRkw />
        </Bloco>
      )}
      {editando && <EditorPosto posto={typeof editando === 'string' ? null : editando} veiculo={editando === 'veiculo' || (typeof editando !== 'string' && editando.veiculo)} onClose={() => setEditando(null)} />}
    </div>
  );
}

export default function PostosIndex({ pode_ver_custo_pessoa, pode_aplicar_rkw = false, postos, ferramentas, colaboradores }: Props) {
  return (
    <div className="pcv-root" data-screen-label="Produção · Postos e cadastros">
      <div className="pcv-ph"><PageHeader title="Produção" suffix=" · Postos e cadastros" below={<ProducaoAbas ativa="/producao/postos" />} /></div>
      <Deferred data={['postos', 'ferramentas', 'colaboradores']} fallback={<div className="pcv-body"><Skeleton className="h-64 w-full" /></div>}>
        <Conteudo postos={postos ?? []} ferramentas={ferramentas ?? []} colaboradores={colaboradores ?? []} podeVerCusto={pode_ver_custo_pessoa} podeAplicarRkw={pode_aplicar_rkw} />
      </Deferred>
    </div>
  );
}

PostosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
