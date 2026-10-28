// @memcofre
//   tela: /woocommerce
//   module: Woocommerce
//   stories: thread woocommerce/playbook/01 (Painel)
//   permissao: superadmin | assinatura woocommerce_module
//
// Painel da sincronização com a loja WooCommerce. Atrás da flag `useV2WoocommercePainel` (WoocommerceController::FLAG_V2);
// desligada, a rota segue na Blade `woocommerce::woocommerce.index`. `?classico=1` abre a Blade com a flag ligada.
// Sincronizar, reiniciar (`_components/SincronizarLoja`) e mapear impostos (`_components/MapaImpostos`) acontecem aqui (F3-2).
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Woocommerce/RUNBOOK-painel.md
// Âncora de design: governance/design/targets/woocommerce--index.alvo.json (A1) ← integra-extras.jsx WooCommercePage.
//
// A tela não chama a loja e não recebe as chaves da API: contagens e situação vêm do banco, pelos mesmos predicados
// dos alertas da Blade (WoocommerceController::contagensDoPainel). Sem preço nem estoque (decisão [W]).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Info, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { PageHeader } from '@/Components/PageHeader';
import WoocommerceAbas from '../_components/WoocommerceAbas';
import SincronizarLoja, { type PodeSincronizar, type Ultimas } from '../_components/SincronizarLoja';
import MapaImpostos from '../_components/MapaImpostos';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import KpiCard from '@/Components/shared/KpiCard';
import type { ColumnDef } from '@tanstack/react-table';
import { Grid, Inline, Stack } from '@/Components/layout';

type Situacao = 'sincronizado' | 'pendente' | 'alterado' | 'fora_da_fila';
export interface ProdutoLoja {
  id: number;
  nome: string;
  sku: string;
  id_na_loja: string | number | null;
  situacao: Situacao;
}
interface Resumo {
  categorias_pendentes: number;
  categorias_alteradas: number;
  produtos_pendentes: number;
  produtos_alterados: number;
  ultima_sincronizacao: string | null;
}
interface Props {
  configurado: boolean;
  resumo?: Resumo;
  produtos?: { itens: ProdutoLoja[]; pagina: number; ultima_pagina: number; total: number };
  ultimas?: Ultimas;
  pode: PodeSincronizar & { configurar: boolean; impostos: boolean };
}

const SITUACAO: Record<Situacao, { rotulo: string; variante: 'success' | 'warning' | 'info' | 'neutral' }> = {
  sincronizado: { rotulo: 'Sincronizado', variante: 'success' },
  pendente: { rotulo: 'Pendente de vínculo', variante: 'warning' },
  alterado: { rotulo: 'Alterado desde a última', variante: 'info' },
  fora_da_fila: { rotulo: 'Fora da fila', variante: 'neutral' },
};

function PainelIndex({ configurado, resumo, produtos, ultimas, pode }: Props) {
  const itens = useMemo(() => produtos?.itens ?? [], [produtos]);
  const [q, setQ] = useState('');
  const termo = q.trim().toLowerCase();
  const visiveis = itens.filter((p) => !termo || `${p.nome} ${p.sku}`.toLowerCase().includes(termo));

  const irPagina = (pagina: number) => router.reload({ only: ['produtos'], data: { page: pagina } });

  const colunas: ColumnDef<ProdutoLoja, unknown>[] = [
    { id: 'sku', header: 'SKU', meta: { mono: true }, cell: ({ row: { original: p } }) => p.sku },
    { id: 'nome', header: 'Produto', cell: ({ row: { original: p } }) => <span className="font-medium">{p.nome}</span> },
    { id: 'id_na_loja', header: 'ID na loja', meta: { mono: true }, cell: ({ row: { original: p } }) => p.id_na_loja ?? '—' },
    {
      id: 'situacao', header: 'Situação',
      cell: ({ row: { original: p } }) => <Badge variant={SITUACAO[p.situacao].variante}>{SITUACAO[p.situacao].rotulo}</Badge>,
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="WooCommerce"
          subtitle={resumo?.ultima_sincronizacao ? <>última sincronização de produtos em <strong>{resumo.ultima_sincronizacao}</strong></> : 'sincronização da loja'}
          subnav={<WoocommerceAbas ativa="/woocommerce" podeConfigurar={pode.configurar} />}
          actions={pode.impostos && configurado ? <MapaImpostos /> : undefined} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        {!configurado && (
          <Alert data-contract="aviso-configuracao">
            <Info className="size-4" />
            <AlertTitle>Loja ainda não configurada</AlertTitle>
            <AlertDescription>
              Sem o endereço da loja salvo, nada sobe. {pode.configurar ? <a className="font-medium underline" href="/woocommerce/api-settings">Configurar a loja</a> : 'Peça ao administrador para configurar.'}
            </AlertDescription>
          </Alert>
        )}

        {configurado && <SincronizarLoja pode={pode} ultimas={ultimas} />}

        <Deferred data={['resumo', 'produtos']} fallback={<p className="py-6 text-sm text-muted-foreground">Carregando a sincronização…</p>}>
          <Grid data-contract="kpis" min="sm" gap={4}>
            <KpiCard label="Pendentes de vínculo" value={resumo?.produtos_pendentes ?? 0} description="produtos sem par na loja" tone={resumo?.produtos_pendentes ? 'warning' : 'default'} />
            <KpiCard label="Alterados" value={resumo?.produtos_alterados ?? 0} description="mudaram depois da última sincronização" tone={resumo?.produtos_alterados ? 'info' : 'default'} />
            <KpiCard label="Categorias a sincronizar" value={(resumo?.categorias_pendentes ?? 0) + (resumo?.categorias_alteradas ?? 0)} description="novas ou alteradas" />
            <KpiCard label="Produtos na fila" value={produtos?.total ?? 0} description="do local que sobe para a loja" />
          </Grid>

          {itens.length > 0 ? (
            <Stack gap={2} data-contract="produtos-table">
              <Inline data-contract="toolbar" wrap gap={2}>
                <div className="relative min-w-64 flex-1">
                  <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
                    placeholder="Buscar produto ou SKU nesta página…" aria-label="Buscar produto ou SKU nesta página" />
                </div>
              </Inline>
              <DataTable columns={colunas} data={visiveis} caption="Produtos na loja" rowKey={(p) => p.id}
                emptyMessage="Nenhum produto com esse termo nesta página." />
              {produtos && produtos.ultima_pagina > 1 && (
                <Inline data-contract="paginacao" gap={2} className="justify-end text-sm">
                  <span className="text-muted-foreground">Página {produtos.pagina} de {produtos.ultima_pagina}</span>
                  <Button variant="outline" size="sm" disabled={produtos.pagina <= 1} onClick={() => irPagina(produtos.pagina - 1)}>Anterior</Button>
                  <Button variant="outline" size="sm" disabled={produtos.pagina >= produtos.ultima_pagina} onClick={() => irPagina(produtos.pagina + 1)}>Próxima</Button>
                </Inline>
              )}
            </Stack>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhum produto para a loja" description="Produtos simples e variáveis do local configurado aparecem aqui." />
            </div>
          )}
        </Deferred>
      </Stack>
    </div>
  );
}

PainelIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default PainelIndex;
