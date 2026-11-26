// Grade da aba Garantia (US-CRM-094, fatia 3; CRM-30). Âncora: protótipo `crm-blade.jsx` →
// TelaAcompanhamentos, `colsGar` (mesmas colunas, larguras e ordem pelo vencimento, que vem do servidor)
// e o rodapé "Abertos: n · No prazo · Vencendo · Vencido". A coluna "Ação" (botão "Ver") fica de fora:
// a gaveta de acompanhamento ainda não sabe mostrar um chamado. A frase "Alerta ao responsável 4h antes
// do vencimento" também: o aviso é a fatia 2, que não existe.

import type { ColumnDef } from '@tanstack/react-table';
import type { ReactNode } from 'react';
import DataTable from '@/Components/shared/DataTable';
import { CrmPilula } from '../../_components/CrmBlocos';
import { TOM, type Opcao } from './acompanhamento';
import { PRAZO, type Chamado, type Garantia } from './garantia';

const col = (header: string, acc: (c: Chamado) => ReactNode, width: number, mono = false): ColumnDef<Chamado> => ({
  id: header, header, cell: ({ row }) => acc(row.original) ?? '—', meta: { width, mono },
});

export default function TabelaGarantia({ garantia, status, compacta }: { garantia: Garantia; status?: Opcao[]; compacta: boolean }) {
  const rotulo = (v: string | null) => status?.find((o) => o.value === v)?.label ?? v ?? '—';
  const colunas: ColumnDef<Chamado>[] = [
    col('Prazo', (c) => <CrmPilula tom={PRAZO[c.prazo].tom}>{PRAZO[c.prazo].rotulo}</CrmPilula>, 118),
    col('Vence em', (c) => c.vence, 150, true),
    col('Contato', (c) => c.contato, 190),
    col('Venda / OS', (c) => c.origem, 170, true),
    col('Tipo', (c) => c.tipo, 150),
    col('Fila', (c) => c.fila || '—', 130),
    col('Responsável', (c) => c.responsavel || '—', 160),
    col('Status', (c) => (c.status ? <CrmPilula tom={TOM[c.status] ?? 'mute'}>{rotulo(c.status)}</CrmPilula> : '—'), 120),
    col('Defeito relatado', (c) => c.relato || '—', 300),
  ];
  const n = garantia.contagem;

  return (
    <div data-contract="crm-garantia" className="flex flex-col gap-3">
      <DataTable<Chamado>
        caption="Chamados de garantia"
        columns={colunas}
        data={garantia.itens}
        emptyMessage="Nenhum chamado de garantia"
        density={compacta ? 'grid' : 'grid-relaxed'}
        zebra={false}
        tableWrapperClassName="overflow-hidden rounded-md border border-border"
        rowKey={(c) => c.id}
        showSearch={false}
      />
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground" data-contract="crm-garantia-rodape">
        <span>Abertos: {n.abertos}</span>
        <span className="ml-auto">{`No prazo: ${n.ok} · Vencendo: ${n.vencendo} · Vencido: ${n.vencido}`}</span>
      </div>
    </div>
  );
}
