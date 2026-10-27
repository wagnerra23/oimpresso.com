// Compras/EntradaXml — conferência da NF-e do fornecedor antes de virar compra (US-COM-003).
// Carimbada do PT-01 Lista por criar-tela.mjs (UI-0013). Desenho: aba Entradas de
// prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx (TrEntradas · UC-TRB-18).
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Grid, Inline, Stack } from '@/Components/layout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';

interface Nota { id: number; numero: string; fornecedor: string; valor_total: number; data: string | null; origem: 'nsu' | 'upload' }
interface Produto { variation_id: number; nome: string; sku: string | null }
interface Item {
  id: number; codigo: string | null; descricao: string; ncm: string | null; cfop: string | null;
  cfop_entrada: string | null; cst: string | null; st_retida: boolean; icms: number | null;
  quantidade: number; valor_total: number; unidade: string | null; produto: Produto | null; sugestao: Produto | null;
}
interface Conferencia { id: number; fornecedor: string; cnpj: string; valor_total: number; fornecedor_cadastrado: boolean; itens: Item[] }
interface Props { notas: Nota[]; nota: Conferencia | null; locais: Array<{ id: number; name: string }> }

// Vínculo "crie o produto": o servidor cria fora de venda, com o custo da nota.
const NOVO = -1;
const brl = (n: number) => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function VincularProduto({ item, valor, onEscolher }: { item: Item; valor: Produto | null; onEscolher: (p: Produto | null) => void }) {
  const [q, setQ] = useState('');
  const [achados, setAchados] = useState<Produto[]>([]);
  if (valor) {
    return (
      <Inline gap={2}>
        <Badge variant="secondary">{valor.nome}</Badge>
        <Button variant="ghost" size="sm" onClick={() => onEscolher(null)} aria-label={'Trocar produto de ' + item.descricao}>trocar</Button>
      </Inline>
    );
  }
  const buscar = async (texto: string) => {
    setQ(texto);
    if (texto.trim().length < 2) return setAchados([]);
    const r = await fetch('/compras/entrada-xml/produtos?q=' + encodeURIComponent(texto), { headers: { Accept: 'application/json' } });
    setAchados(r.ok ? await r.json() : []);
  };
  return (
    <Stack gap={1}>
      {item.sugestao && (
        <Button variant="outline" size="sm" onClick={() => onEscolher(item.sugestao)}>Usar {item.sugestao.nome} (mesmo EAN)</Button>
      )}
      <Button variant="outline" size="sm" onClick={() => onEscolher({ variation_id: NOVO, nome: 'Produto novo (fora de venda)', sku: null })}>Criar produto novo</Button>
      <Input value={q} onChange={(e) => buscar(e.target.value)} placeholder="Vincular produto…" aria-label={'Vincular ' + item.descricao} />
      {achados.map((p) => (
        <Button key={p.variation_id} variant="ghost" size="sm" className="justify-start" onClick={() => onEscolher(p)}>
          {p.nome}{p.sku ? ` · ${p.sku}` : ''}
        </Button>
      ))}
    </Stack>
  );
}

export default function EntradaXml({ notas, nota, locais }: Props) {
  const erros = (usePage().props.errors ?? {}) as Record<string, string>;
  const [vinculos, setVinculos] = useState<Record<number, Produto | null>>({});
  const envio = useForm<{ arquivo: File | null }>({ arquivo: null });
  const geracao = useForm({ location_id: locais[0]?.id ?? 0, vinculos: {} as Record<number, number | 'novo'> });

  const escolhido = (i: Item) => (i.id in vinculos ? vinculos[i.id] : i.produto);
  const faltam = nota ? nota.itens.filter((i) => !escolhido(i)).length : 0;

  const colunasNotas: ColumnDef<Nota>[] = [
    { accessorKey: 'fornecedor', header: 'Fornecedor' },
    { accessorKey: 'numero', header: 'Nota', meta: { mono: true } },
    { accessorKey: 'data', header: 'Emissão' },
    { accessorKey: 'origem', header: 'Origem', cell: ({ row }) => (row.original.origem === 'upload' ? 'Arquivo' : 'SEFAZ') },
    { accessorKey: 'valor_total', header: 'Valor', meta: { align: 'right', mono: true }, cell: ({ row }) => brl(row.original.valor_total) },
  ];
  const colunasItens: ColumnDef<Item>[] = [
    { accessorKey: 'descricao', header: 'Item do XML', cell: ({ row }) => <><b>{row.original.descricao}</b><div className="text-xs text-muted-foreground">{row.original.codigo} · {row.original.quantidade} {row.original.unidade}</div></> },
    { accessorKey: 'ncm', header: 'NCM', meta: { mono: true } },
    { id: 'cfop', header: 'CFOP fornecedor → entrada', meta: { mono: true }, cell: ({ row }) => <>{row.original.cfop} → <b>{row.original.cfop_entrada ?? '?'}</b></> },
    { accessorKey: 'cst', header: 'CST', meta: { mono: true }, cell: ({ row }) => <>{row.original.cst}{row.original.st_retida && <div className="text-xs text-amber-700">ST retida</div>}</> },
    { accessorKey: 'icms', header: 'ICMS destacado', meta: { align: 'right', mono: true }, cell: ({ row }) => (row.original.icms === null ? '—' : brl(row.original.icms)) },
    { accessorKey: 'valor_total', header: 'Valor', meta: { align: 'right', mono: true }, cell: ({ row }) => brl(row.original.valor_total) },
    { id: 'produto', header: 'Seu produto', cell: ({ row }) => <VincularProduto item={row.original} valor={escolhido(row.original)} onEscolher={(p) => setVinculos({ ...vinculos, [row.original.id]: p })} /> },
  ];

  const gerar = () => {
    if (!nota) return;
    const ids: Record<number, number | 'novo'> = {};
    nota.itens.forEach((i) => { const p = escolhido(i); if (p) ids[i.id] = p.variation_id === NOVO ? 'novo' : p.variation_id; });
    geracao.transform((d) => ({ ...d, vinculos: ids }));
    geracao.post(`/compras/entrada-xml/${nota.id}/gerar`);
  };

  return (
    <AppShellV2>
      <Head title="Entrada por XML" />
      <div data-contract="cabecalho">
        <PageHeader title="Entrada por XML" subtitle="Confira a nota do fornecedor antes de ela virar compra" />
      </div>
      <Inline data-contract="filtros" gap={2} wrap>
        <Input type="file" accept=".xml,text/xml" aria-label="XML da nota" className="max-w-xs"
          onChange={(e) => envio.setData('arquivo', e.target.files?.[0] ?? null)} />
        <Button disabled={!envio.data.arquivo || envio.processing} onClick={() => envio.post('/compras/entrada-xml/upload', { forceFormData: true })}>Enviar XML</Button>
        {erros.arquivo && <span role="alert" className="text-sm text-destructive">{erros.arquivo}</span>}
      </Inline>
      <div data-contract="lista">
        <DataTable columns={colunasNotas} data={notas} emptyMessage="Nenhuma nota esperando virar compra. Envie o XML ou aguarde a busca diária na SEFAZ."
          onRowClick={(n) => router.get('/compras/entrada-xml', { nota: n.id }, { only: ['nota'], preserveState: true })} />
      </div>

      {nota && (
        <Stack data-contract="conferencia" gap={4} className="mt-6" aria-label="Conferência da nota">
          <Grid fit="md" gap={4}>
            <Card><CardHeader><CardTitle>Como a entrada é lida</CardTitle></CardHeader><CardContent className="text-sm">
              <p>Fornecedor: <b>{nota.fornecedor}</b> · total da nota <b>{brl(nota.valor_total)}</b></p>
              <p>Produto: o vínculo código do fornecedor → seu produto fica lembrado para a próxima nota.</p>
              <p>Crédito de ICMS: no Simples não credita, vai para o custo.</p>
            </CardContent></Card>
            <Card><CardHeader><CardTitle>O que trava a entrada</CardTitle></CardHeader><CardContent className="text-sm">
              <p>Item sem produto vinculado: {faltam === 0 ? 'nenhum' : <b>{faltam}</b>}</p>
              <p>Fornecedor: {nota.fornecedor_cadastrado ? 'cadastrado' : <b>CNPJ {nota.cnpj} não cadastrado</b>}</p>
              <p>CST 060 (ST retida): marcado no item; o produto não muda sozinho.</p>
            </CardContent></Card>
          </Grid>
          <DataTable columns={colunasItens} data={nota.itens} />
          <Inline gap={2} wrap>
            <label className="text-sm" htmlFor="local">Local de estoque</label>
            <select id="local" className="h-9 rounded-md border px-2 text-sm" value={geracao.data.location_id}
              onChange={(e) => geracao.setData('location_id', Number(e.target.value))}>
              {locais.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
            </select>
            <Button disabled={faltam > 0 || !nota.fornecedor_cadastrado || geracao.processing} onClick={gerar}>Gerar compra</Button>
            {erros.gerar && <span role="alert" className="text-sm text-destructive">{erros.gerar}</span>}
          </Inline>
        </Stack>
      )}
    </AppShellV2>
  );
}
