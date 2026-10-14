// @memcofre
//   tela: /modules/modifiers
//   module: Restaurante
//   stories: thread restaurante/playbook/02 (Modificadores)
//   permissao: product.view | product.create
//
// Conjuntos de modificadores do negócio. Atrás da flag `useV2RestauranteModificadores` (ModifierSetsController::FLAG_V2);
// desligada, a rota segue na Blade `restaurant.modifier_sets.index`. `?classico=1` abre a Blade com a flag ligada — é lá
// que se cadastra, edita, liga produtos e exclui.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Restaurante/RUNBOOK-modificadores.md
// Âncora de design: governance/design/targets/restaurante--modificadores--index.alvo.json (A1) ← configuracoes-cadastros.jsx
//   Modificadores (rota cfg-modificadores).
//
// Sem preço na lista: a Blade mostra só os nomes, e exibir valor é decisão [W] (regra mestre).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { ExternalLink, Info, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';
import ConfiguracoesSubNav from '@/Pages/Configuracoes/_shared/ConfiguracoesSubNav';

export interface Conjunto {
  id: number;
  nome: string;
  modificadores: string[];
  produtos: string[];
}
interface Props {
  conjuntos?: Conjunto[];
  pode_gerenciar: boolean;
}

function ModificadoresIndex({ conjuntos, pode_gerenciar }: Props) {
  const todos = useMemo(() => conjuntos ?? [], [conjuntos]);
  const [q, setQ] = useState('');
  const busca = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, []);

  const termo = q.trim().toLowerCase();
  const visiveis = todos.filter((c) => !termo
    || [c.nome, ...c.modificadores, ...c.produtos].join(' ').toLowerCase().includes(termo));

  const colunas: ColumnDef<Conjunto, unknown>[] = [
    { id: 'nome', header: 'Conjunto', cell: ({ row: { original: c } }) => <span className="font-medium">{c.nome}</span> },
    { id: 'modificadores', header: 'Modificadores', cell: ({ row: { original: c } }) => c.modificadores.join(' · ') || '—' },
    {
      id: 'produtos', header: 'Produtos',
      cell: ({ row: { original: c } }) => c.produtos.length > 0
        ? c.produtos.join(', ')
        : <span className="text-muted-foreground">nenhum produto ligado</span>,
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Modificadores"
          subtitle={conjuntos ? <><strong>{todos.length}</strong> {todos.length === 1 ? 'conjunto' : 'conjuntos'} de modificadores</> : 'acabamentos e extras do item'}
          subnav={<ConfiguracoesSubNav />}
          actions={pode_gerenciar ? (
            <Button asChild variant="outline">
              <a href="/modules/modifiers?classico=1"><ExternalLink className="size-4" /> Cadastrar e editar na tela clássica</a>
            </Button>
          ) : undefined} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar conjunto, modificador ou produto" aria-label="Buscar conjunto, modificador ou produto" />
          </div>
        </Inline>

        <Deferred data="conjuntos" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando modificadores…</p>}>
          {todos.length > 0 ? (
            <div data-contract="modificadores-table">
              <DataTable columns={colunas} data={visiveis} caption="Conjuntos de modificadores do negócio" rowKey={(c) => c.id}
                emptyMessage="Nenhum conjunto com esse termo." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhum conjunto de modificadores" description="Acabamento e serviço extra do item moram aqui — bastão, ilhós, instalação."
                action={pode_gerenciar ? <Button asChild><a href="/modules/modifiers?classico=1">Cadastrar na tela clássica</a></Button> : undefined} />
            </div>
          )}
        </Deferred>

        <Alert data-contract="nota">
          <Info className="size-4" />
          <AlertTitle>Permissão emprestada do catálogo</AlertTitle>
          <AlertDescription>
            O conjunto é ligado a produtos e, no PDV, aparece como opção do item na venda. Não existe
            permissão própria de modificador: quem vê ou cadastra produto vê esta lista.
          </AlertDescription>
        </Alert>
      </Stack>
    </div>
  );
}

ModificadoresIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ModificadoresIndex;
