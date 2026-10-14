// @memcofre
//   tela: /crm/proposals
//   module: Crm
//   stories: thread Crm/14 (ProposalController@index · Blade → Inertia, detalhe em drawer)
//   permissao: crm_module (assinatura); Mandar exige crm.access_proposal
//
// Lista de propostas enviadas. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Propostas()
// Contrato: governance/design/contracts/crm-propostas.contract.json
//
// Atrás da flag useV2CrmPropostas (default OFF). "Mandar" e "Modelo de proposta" levam às telas
// Blade do ProposalTemplateController; `?classico=1` devolve a lista Blade.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type DensidadeDaTabela, type PaginatorShape } from '@/Components/shared/DataTable';

const ROTA = '/crm/proposals';
const MODELO = '/crm/proposal-template';
const MANDAR = '/crm/send-proposal';

interface Proposta {
  id: number; contato: string | null; assunto: string | null; enviado_por: string | null;
  data: string | null; anexos: number;
}
interface Detalhe extends Proposta {
  cc: string | null; bcc: string | null; corpo: string; arquivos: { nome: string; url: string }[];
}
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  pode_enviar: boolean;
  propostas?: PaginatorShape<Proposta>;
  proposta?: Detalhe | null;
}

function verProposta(filtros: Filtros, id: number | null) {
  router.get(ROTA, id ? { ...filtros, proposta: String(id) } : filtros, {
    only: ['proposta'], preserveState: true, preserveScroll: true, replace: true,
  });
}

export default function PropostasIndex({ filtros, pode_enviar, propostas, proposta }: Props) {
  const [densidade, setDensidade] = useState<DensidadeDaTabela>('default');

  const colunas: ColumnDef<Proposta>[] = [
    texto('Contato', (r) => r.contato, 220),
    texto('Assunto', (r) => r.assunto, 340),
    texto('Enviado por', (r) => r.enviado_por, 160),
    texto('Data', (r) => r.data, 160),
    texto('Anexos', (r) => String(r.anexos), 100),
    {
      id: 'Ação', header: 'Ação', meta: { width: 120 },
      cell: ({ row }) => (
        <Button variant="outline" size="sm" onClick={(e) => { e.stopPropagation(); verProposta(filtros, row.original.id); }}>
          Ver proposta
        </Button>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Propostas" subtitle="Propostas enviadas a contatos: assunto, quem enviou e anexos." />

      <Card data-contract="crm-propostas">
        <CardContent className="flex flex-col gap-3 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-sm font-medium">Propostas</h3>
            {propostas ? <span className="text-xs text-muted-foreground">{propostas.total} enviada(s)</span> : null}
            <div className="ml-auto flex flex-wrap gap-2" data-contract="crm-toolbar">
              <Button variant={densidade === 'default' ? 'secondary' : 'outline'} size="sm" aria-pressed={densidade === 'default'} onClick={() => setDensidade('default')}>Confortável</Button>
              <Button variant={densidade === 'dense' ? 'secondary' : 'outline'} size="sm" aria-pressed={densidade === 'dense'} onClick={() => setDensidade('dense')}>Compacto</Button>
              <Button asChild variant="outline" size="sm"><a href={MODELO}>Modelo de proposta</a></Button>
              {pode_enviar ? <Button asChild size="sm"><a href={MANDAR}>Mandar</a></Button> : null}
            </div>
          </div>

          <Deferred data="propostas" fallback={<Skeleton className="h-64 w-full" />}>
            {propostas ? (
              <DataTable<Proposta>
                caption="Propostas"
                columns={colunas}
                data={propostas.data}
                pagination={propostas}
                endpoint={ROTA}
                filters={filtros}
                initialSearch={filtros.q ?? ''}
                searchPlaceholder="Buscar por contato ou assunto"
                emptyMessage="Nenhuma proposta enviada"
                rowKey={(r) => r.id}
                onRowClick={(r) => verProposta(filtros, r.id)}
                density={densidade}
              />
            ) : null}
          </Deferred>
        </CardContent>
      </Card>

      <Sheet open={!!proposta} onOpenChange={(aberto) => { if (!aberto) verProposta(filtros, null); }}>
        {proposta ? (
          <SheetContent side="right" className="w-full sm:max-w-[760px]">
            <SheetHeader>
              <SheetTitle>{proposta.assunto || '—'}</SheetTitle>
              <SheetDescription>
                {[proposta.contato, proposta.enviado_por, proposta.data].filter(Boolean).join(' · ') || '—'}
              </SheetDescription>
            </SheetHeader>
            <div className="flex flex-col gap-4 overflow-y-auto px-4">
              <dl className="grid grid-cols-[140px_1fr] gap-x-3 gap-y-1 text-sm">
                {([['Enviado para', proposta.contato], ['Cc', proposta.cc], ['Bcc', proposta.bcc]] as [string, string | null][]).map(([rotulo, valor]) => (
                  <div key={rotulo} className="contents">
                    <dt className="text-muted-foreground">{rotulo}</dt>
                    <dd>{valor || '—'}</dd>
                  </div>
                ))}
              </dl>
              <section className="flex flex-col gap-2">
                <h4 className="text-sm font-medium">Corpo do e-mail</h4>
                <p className="whitespace-pre-line text-sm">{proposta.corpo || '—'}</p>
              </section>
              <section className="flex flex-col gap-2">
                <h4 className="text-sm font-medium">Anexos</h4>
                {proposta.arquivos.length ? (
                  <ul className="flex flex-col gap-1 text-sm">
                    {proposta.arquivos.map((a) => (
                      <li key={a.url}><a className="underline" href={a.url} target="_blank" rel="noreferrer">{a.nome}</a></li>
                    ))}
                  </ul>
                ) : <p className="text-sm text-muted-foreground">Sem anexos</p>}
              </section>
            </div>
            <SheetFooter className="flex-row justify-end gap-2">
              <Button variant="outline" onClick={() => verProposta(filtros, null)}>Fechar</Button>
            </SheetFooter>
          </SheetContent>
        ) : null}
      </Sheet>
    </div>
  );
}

const texto = (header: string, acc: (r: Proposta) => ReactNode, width = 150): ColumnDef<Proposta> => ({
  id: header, header, cell: ({ row }) => acc(row.original) || '—', meta: { width },
});

PropostasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
