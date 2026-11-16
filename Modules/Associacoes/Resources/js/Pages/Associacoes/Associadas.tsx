// @memcofre
//   tela: /associacoes/associadas
//   module: Associacoes
//   stories: US-ASSOC-003 (UC-ASSOCI-00..06)
//   permissao: associacoes.access · cadastrar e desligar exigem associacoes.editar
//
// Lista de associadas (PT-01). A associada é um contato do núcleo (ADR 0451 D3/D5): o cadastro
// busca um contato existente no servidor ou cria um novo (nome, celular, cidade — sem CPF) e
// registra o consentimento por finalidade. O celular chega mascarado do servidor; o nome só
// vem quando quem olha representa a associação ou a associada aceitou aparecer para a rede.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { Plus } from 'lucide-react';
import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/Components/shared/DataTable';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PhoneInput } from '@/Components/ui/phone-input';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Segmented } from '@/Components/ui/segmented';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';

interface Associada {
  id: number;
  nome: string | null;
  celular: string;
  cidade: string;
  associacao: string;
  situacao: string;
  entrou_em: string | null;
  desligada_em: string | null;
}
interface AssociacaoOpcao { id: number; nome: string; cidade: string }
interface Termo { finalidade: string; versao: string; titulo: string; texto: string; obrigatorio: boolean }
interface ContatoOpcao { id: number; nome: string; telefone: string }
interface Props {
  filtros: { associacao: string; situacao: string };
  pode_editar: boolean;
  situacoes: string[];
  termos: Termo[];
  associacoes?: AssociacaoOpcao[];
  associadas?: Associada[];
}

const TODAS = '__todas__';
const ROTULO_SITUACAO: Record<string, string> = { ativa: 'Ativa', desligada: 'Desligada' };
const hoje = () => new Date().toLocaleDateString('en-CA');
const dataBr = (d: string | null) => (d ? d.split('-').reverse().join('/') : '—');
const Erro = ({ msg }: { msg?: string }) => (msg ? <p className="text-xs text-destructive">{msg}</p> : null);

function colunas(podeEditar: boolean, onDesligar: (a: Associada) => void): ColumnDef<Associada, unknown>[] {
  const cols: ColumnDef<Associada, unknown>[] = [
    {
      id: 'nome',
      accessorFn: (a) => a.nome ?? '',
      header: 'Associada',
      cell: ({ row: { original: a } }) => (a.nome
        ? <span className="font-medium">{a.nome}</span>
        : <span className="text-muted-foreground" title="Sem aceite para aparecer à diretoria da rede">Nome reservado à associação</span>),
    },
    { id: 'celular', accessorFn: (a) => a.celular, header: 'Celular', cell: ({ row: { original: a } }) => <span className="tabular-nums">{a.celular || '—'}</span> },
    { id: 'cidade', accessorFn: (a) => a.cidade, header: 'Cidade' },
    { id: 'associacao', accessorFn: (a) => a.associacao, header: 'Associação' },
    {
      id: 'situacao',
      accessorFn: (a) => a.situacao,
      header: 'Situação',
      cell: ({ row: { original: a } }) => (
        <Badge variant={a.situacao === 'ativa' ? 'default' : 'outline'}>
          {ROTULO_SITUACAO[a.situacao] ?? a.situacao}{a.desligada_em ? ` em ${dataBr(a.desligada_em)}` : ''}
        </Badge>
      ),
    },
    { id: 'entrou_em', accessorFn: (a) => a.entrou_em ?? '', header: 'Entrada', cell: ({ row: { original: a } }) => dataBr(a.entrou_em) },
  ];
  if (podeEditar) {
    cols.push({
      id: 'acoes',
      header: '',
      enableSorting: false,
      cell: ({ row: { original: a } }) => (a.situacao === 'ativa'
        ? <Button variant="ghost" size="sm" onClick={() => onDesligar(a)}>Desligar</Button>
        : null),
    });
  }
  return cols;
}

function BuscaContato({ contatoId, onContato }: { contatoId: number | null; onContato: (c: ContatoOpcao | null) => void }) {
  const [termo, setTermo] = useState('');
  const [contatos, setContatos] = useState<ContatoOpcao[]>([]);
  const [carregando, setCarregando] = useState(false);

  useEffect(() => {
    const ctl = new AbortController();
    const t = setTimeout(() => {
      setCarregando(true);
      fetch(`/associacoes/associadas/contatos?q=${encodeURIComponent(termo.trim())}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: ctl.signal,
      })
        .then((r) => (r.ok ? r.json() : { contatos: [] }))
        .then((d) => setContatos(d.contatos ?? []))
        .catch(() => undefined)
        .finally(() => setCarregando(false));
    }, 250);
    return () => { clearTimeout(t); ctl.abort(); };
  }, [termo]);

  return (
    <Command shouldFilter={false} className="rounded-lg border border-border">
      <CommandInput aria-label="Buscar contato" placeholder="Buscar por nome ou celular" value={termo} onValueChange={setTermo} />
      <CommandList>
        <CommandEmpty>{carregando ? 'Buscando…' : 'Nenhum contato disponível. Cadastre um contato novo.'}</CommandEmpty>
        <CommandGroup>
          {contatos.map((c) => (
            <CommandItem key={c.id} value={String(c.id)} onSelect={() => onContato(contatoId === c.id ? null : c)} data-checked={contatoId === c.id} className="justify-between">
              <span>{c.nome}</span>
              <span className="tabular-nums text-muted-foreground">{contatoId === c.id ? 'selecionado · ' : ''}{c.telefone}</span>
            </CommandItem>
          ))}
        </CommandGroup>
      </CommandList>
    </Command>
  );
}

function NovaAssociada({ associacoes, termos, onClose }: { associacoes: AssociacaoOpcao[]; termos: Termo[]; onClose: () => void }) {
  const [origem, setOrigem] = useState<'existente' | 'novo'>('existente');
  const form = useForm({
    associacao_id: associacoes.length === 1 && associacoes[0] ? String(associacoes[0].id) : '',
    entrou_em: hoje(),
    contact_id: null as number | null,
    novo_contato: { nome: '', telefone: '', cidade: '' },
    consentimentos: Object.fromEntries(termos.map((t) => [t.finalidade, false])) as Record<string, boolean>,
  });
  const erros = form.errors as Record<string, string>;
  const enviar = (e: FormEvent) => {
    e.preventDefault();
    form.transform((d) => ({
      ...d,
      contact_id: origem === 'existente' ? d.contact_id : null,
      novo_contato: origem === 'novo' ? d.novo_contato : null,
    }));
    form.post('/associacoes/associadas', { onSuccess: onClose });
  };
  const novo = form.data.novo_contato;
  const temContato = origem === 'existente' ? form.data.contact_id !== null : novo.nome.trim() !== '' && novo.telefone.length >= 10;
  const obrigatoriosAceitos = termos.filter((t) => t.obrigatorio).every((t) => form.data.consentimentos[t.finalidade]);
  const falta = !form.data.associacao_id ? 'Escolha a associação.'
    : !temContato ? (origem === 'existente' ? 'Escolha o contato.' : 'Informe nome e celular.')
      : !obrigatoriosAceitos ? 'Sem o aceite para fazer parte da associação, o cadastro não pode ser feito.' : null;

  return (
    <Sheet open onOpenChange={(aberto) => !aberto && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <form onSubmit={enviar} className="flex flex-col gap-4">
          <SheetHeader>
            <SheetTitle>Nova associada</SheetTitle>
            <SheetDescription>Nome, celular e cidade ficam no cadastro de contatos. Não pedimos CPF nem informação de saúde.</SheetDescription>
          </SheetHeader>
          <Secao titulo="1 · Associação">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="flex flex-col gap-1.5">
                <Label>Associação</Label>
                <Select value={form.data.associacao_id} onValueChange={(v) => form.setData('associacao_id', v)}>
                  <SelectTrigger aria-label="Associação"><SelectValue placeholder="Escolha" /></SelectTrigger>
                  <SelectContent>
                    {associacoes.map((a) => <SafeSelectItem key={a.id} value={String(a.id)}>{a.nome}</SafeSelectItem>)}
                  </SelectContent>
                </Select>
                <Erro msg={erros.associacao_id} />
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="assoc-entrou">Data de entrada</Label>
                <Input id="assoc-entrou" type="date" max={hoje()} value={form.data.entrou_em} onChange={(e) => form.setData('entrou_em', e.target.value)} />
                <Erro msg={erros.entrou_em} />
              </div>
            </div>
          </Secao>
          <Secao titulo="2 · Contato">
            <Segmented
              value={origem}
              onValueChange={(v) => v && setOrigem(v as 'existente' | 'novo')}
              options={[{ value: 'existente', label: 'Contato existente' }, { value: 'novo', label: 'Novo contato' }]}
            />
            {origem === 'existente' ? (
              <div className="flex flex-col gap-1.5">
                <BuscaContato contatoId={form.data.contact_id} onContato={(c) => form.setData('contact_id', c?.id ?? null)} />
                <Erro msg={erros.contact_id} />
              </div>
            ) : (
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="assoc-nc-nome">Nome</Label>
                  <Input id="assoc-nc-nome" autoComplete="off" value={novo.nome} onChange={(e) => form.setData('novo_contato', { ...novo, nome: e.target.value })} />
                  <Erro msg={erros['novo_contato.nome']} />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="assoc-nc-celular">Celular (WhatsApp)</Label>
                  <PhoneInput id="assoc-nc-celular" value={novo.telefone} onValueChange={({ digits }) => form.setData('novo_contato', { ...novo, telefone: digits })} />
                  <Erro msg={erros['novo_contato.telefone']} />
                </div>
                <div className="flex flex-col gap-1.5 sm:col-span-2">
                  <Label htmlFor="assoc-nc-cidade">Cidade</Label>
                  <Input id="assoc-nc-cidade" autoComplete="off" value={novo.cidade} onChange={(e) => form.setData('novo_contato', { ...novo, cidade: e.target.value })} />
                </div>
              </div>
            )}
          </Secao>
          <Secao titulo="3 · O que ela autoriza">
            <p className="text-xs text-muted-foreground">Leia cada item para a associada. Ela pode mudar de ideia depois.</p>
            {termos.map((t) => (
              <div key={t.finalidade} className="flex items-start gap-3">
                <Checkbox
                  id={`assoc-aceite-${t.finalidade}`}
                  checked={form.data.consentimentos[t.finalidade] ?? false}
                  onCheckedChange={(v) => form.setData('consentimentos', { ...form.data.consentimentos, [t.finalidade]: v === true })}
                  aria-describedby={`assoc-aceite-${t.finalidade}-texto`}
                />
                <div className="flex flex-col gap-0.5">
                  <Label htmlFor={`assoc-aceite-${t.finalidade}`}>{t.titulo}{t.obrigatorio ? ' (obrigatório)' : ' (opcional)'}</Label>
                  <span id={`assoc-aceite-${t.finalidade}-texto`} className="text-xs text-muted-foreground">{t.texto}</span>
                </div>
              </div>
            ))}
            <Erro msg={erros['consentimentos.associada'] ?? erros.consentimentos} />
          </Secao>
          <SheetFooter className="flex-col items-stretch gap-2">
            {falta && <p id="nova-associada-falta" className="text-sm text-muted-foreground">{falta}</p>}
            <Button type="submit" disabled={form.processing || falta !== null} aria-describedby={falta ? 'nova-associada-falta' : undefined}>Cadastrar associada</Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  );
}

function Desligar({ associada, onClose }: { associada: Associada; onClose: () => void }) {
  const form = useForm({ desligada_em: hoje() });
  const enviar = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/associacoes/associadas/${associada.id}/desligar`, { preserveScroll: true, onSuccess: onClose });
  };
  return (
    <Sheet open onOpenChange={(aberto) => !aberto && onClose()}>
      <SheetContent className="w-full sm:max-w-md">
        <form onSubmit={enviar} className="flex flex-col gap-4">
          <SheetHeader>
            <SheetTitle>Desligar associada</SheetTitle>
            <SheetDescription>O cadastro e os aceites ficam guardados; ela só deixa de contar como ativa.</SheetDescription>
          </SheetHeader>
          <div className="mx-4 flex flex-col gap-1.5">
            <Label htmlFor="assoc-desligada">Data do desligamento</Label>
            <Input id="assoc-desligada" type="date" max={hoje()} value={form.data.desligada_em} onChange={(e) => form.setData('desligada_em', e.target.value)} />
            <Erro msg={form.errors.desligada_em} />
          </div>
          <SheetFooter>
            <Button type="submit" variant="destructive" disabled={form.processing}>Desligar</Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  );
}

function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="mx-4 flex flex-col gap-3 rounded-lg border border-border p-4">
      <h3 className="text-xs font-semibold uppercase tracking-[0.04em] text-muted-foreground">{titulo}</h3>
      {children}
    </section>
  );
}

export default function Associadas({ filtros, pode_editar, situacoes, termos, associacoes, associadas }: Props) {
  const [criando, setCriando] = useState(false);
  const [desligando, setDesligando] = useState<Associada | null>(null);
  const filtrar = (campo: 'associacao' | 'situacao', valor: string) => {
    const f = { ...filtros, [campo]: valor };
    router.get('/associacoes/associadas', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
  };

  return (
    <div data-screen-label="Associações · Associadas">
      <div data-contract="cabecalho">
        <PageHeader
          title="Associadas"
          subtitle="Quem faz parte de cada associação da rede"
          actions={pode_editar ? <Button onClick={() => setCriando(true)} disabled={!associacoes}><Plus className="size-4" aria-hidden="true" /> Nova associada</Button> : undefined}
        />
      </div>
      <div className="flex flex-col gap-4 p-4">
        <div data-contract="filtros" className="flex flex-wrap gap-3">
          <Deferred data="associacoes" fallback={<Skeleton className="h-9 w-56" />}>
            <div className="flex w-64 flex-col gap-1.5">
              <Label>Associação</Label>
              <Select value={filtros.associacao || TODAS} onValueChange={(v) => filtrar('associacao', v === TODAS ? '' : v)}>
                <SelectTrigger aria-label="Filtrar por associação"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SafeSelectItem value={TODAS}>Todas as associações</SafeSelectItem>
                  {(associacoes ?? []).map((a) => <SafeSelectItem key={a.id} value={String(a.id)}>{a.nome}</SafeSelectItem>)}
                </SelectContent>
              </Select>
            </div>
          </Deferred>
          <div className="flex w-48 flex-col gap-1.5">
            <Label>Situação</Label>
            <Select value={filtros.situacao || TODAS} onValueChange={(v) => filtrar('situacao', v === TODAS ? '' : v)}>
              <SelectTrigger aria-label="Filtrar por situação"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SafeSelectItem value={TODAS}>Todas</SafeSelectItem>
                {situacoes.map((s) => <SafeSelectItem key={s} value={s}>{ROTULO_SITUACAO[s] ?? s}</SafeSelectItem>)}
              </SelectContent>
            </Select>
          </div>
        </div>
        <div data-contract="lista">
          <Deferred data="associadas" fallback={<Skeleton className="h-40 w-full" />}>
            <DataTable<Associada>
              columns={colunas(pode_editar, setDesligando)}
              data={associadas ?? []}
              caption="Associadas"
              rowKey={(a) => a.id}
              emptyMessage={filtros.associacao || filtros.situacao ? 'Nenhuma associada com estes filtros.' : 'Nenhuma associada cadastrada ainda.'}
            />
          </Deferred>
        </div>
      </div>
      {criando && associacoes && <NovaAssociada associacoes={associacoes} termos={termos} onClose={() => setCriando(false)} />}
      {desligando && <Desligar associada={desligando} onClose={() => setDesligando(null)} />}
    </div>
  );
}

Associadas.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
