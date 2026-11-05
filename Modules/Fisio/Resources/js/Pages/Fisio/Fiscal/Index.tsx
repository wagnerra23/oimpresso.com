// @memcofre
//   tela: /fisio/fiscal
//   module: Fisio
//   stories: US-FISIO-023 (UC-FFI-01..05)
//   permissao: fisio.access · marcar recibo enviado exige fisio.editar
//
// Documentos fiscais do dia (fonte: prototipo-ui/cowork/Wagner/prototipos/fisio/FiscalFila.dc.html).
// Aba CNPJ: NFS-e lidas do módulo NFSe. Aba CPF: fila do Receita Saúde sobre os pagamentos do
// núcleo; o Fisio só marca o envio. O lote oficial espera o layout do Manual v2.1 (US-FISIO-024).
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import SubNav from '@/Components/shared/SubNav';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';

interface Nota { id: number; numero: string | null; status: string; tomador: string; erro: string | null }
interface Recibo { payment_id: number; pagador: string; valor: string; cpf_ok: boolean; cpf_fim: string | null; status: string; numero: string | null }
interface Props { dia: string; pode_editar: boolean; notas?: Nota[]; recibos?: Recibo[] }

const moeda = (v: string) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const ROTULO_NOTA: Record<string, string> = { emitida: 'autorizada', erro: 'rejeitada', processando: 'enviando', rascunho: 'rascunho', cancelada: 'cancelada' };

function Recibos({ recibos, podeEditar }: { recibos: Recibo[]; podeEditar: boolean }) {
  const marcar = (r: Recibo) => {
    const numero = window.prompt('Número do recibo no Receita Saúde (opcional)') ?? undefined;
    router.post(`/fisio/fiscal/recibos/${r.payment_id}/enviado`, { receipt_number: numero || null }, { preserveScroll: true });
  };
  if (recibos.length === 0) return <p className="text-sm text-muted-foreground">Nenhum pagamento de paciente hoje.</p>;
  return (
    <ul className="divide-y rounded-md border">
      {recibos.map((r) => (
        <li key={r.payment_id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
          <span className="min-w-0 flex-1 truncate">{r.pagador}</span>
          <span className="font-mono tabular-nums">{moeda(r.valor)}</span>
          {!r.cpf_ok ? <Badge variant="outline">falta CPF do pagador</Badge>
            : <Badge variant={r.status === 'enviado' ? 'secondary' : 'outline'}>{r.status === 'enviado' ? `enviado${r.numero ? ` · ${r.numero}` : ''}` : `pendente · CPF …${r.cpf_fim}`}</Badge>}
          {podeEditar && r.cpf_ok && r.status !== 'enviado' && <Button size="sm" variant="outline" onClick={() => marcar(r)}>Marquei como enviado</Button>}
        </li>
      ))}
    </ul>
  );
}

export default function FiscalDoDia({ dia, pode_editar, notas, recibos }: Props) {
  const [aba, setAba] = useState<'pj' | 'pf'>('pj');
  return (
    <>
      <PageHeader title="Documentos fiscais do dia" subtitle="Cada pagamento recebido no Fisio gera o documento certo para quem atende." />
      <div className="mx-auto grid w-full max-w-4xl gap-4 p-4">
        <SubNav variant="segmented" ariaLabel="Tipo de atendimento" value={aba} onChange={(v) => setAba(v === 'pf' ? 'pf' : 'pj')}
          items={[{ value: 'pj', label: 'Clínica (CNPJ)' }, { value: 'pf', label: 'Autônomo (CPF)' }]} />
        <p className="text-xs text-muted-foreground">Dia {new Date(`${dia}T12:00:00`).toLocaleDateString('pt-BR')}</p>

        {aba === 'pj' ? (
          <Card><CardContent className="grid gap-3 pt-6">
            <p className="text-sm text-muted-foreground">Serviço LC 116 item 4.08 (fisioterapia). Emissão pelo módulo NFS-e do oimpresso.</p>
            <Deferred data="notas" fallback={<Skeleton className="h-24 w-full" />}>
              {(notas ?? []).length === 0 ? <p className="text-sm text-muted-foreground">Nenhuma NFS-e de paciente hoje.</p> : (
                <ul className="divide-y rounded-md border">{(notas ?? []).map((n) => (
                  <li key={n.id} className="grid gap-1 p-3 text-sm">
                    <span className="flex flex-wrap items-center gap-3"><span className="flex-1">{n.tomador}</span>
                      <span className="font-mono">{n.numero ?? '—'}</span><Badge variant={n.status === 'erro' ? 'destructive' : 'secondary'}>{ROTULO_NOTA[n.status] ?? n.status}</Badge></span>
                    {n.erro && <span className="text-destructive">{n.erro} Corrija o contato e reenvie pelo NFS-e.</span>}
                  </li>))}</ul>)}
            </Deferred>
          </CardContent></Card>
        ) : (
          <Card><CardContent className="grid gap-3 pt-6">
            <p className="text-sm text-muted-foreground">Quem atende como pessoa física emite recibo pelo Receita Saúde, obrigatório desde 2025. Sem API pública: confira os dados e envie no app ou no Carnê-Leão.</p>
            <Deferred data="recibos" fallback={<Skeleton className="h-24 w-full" />}>
              <Recibos recibos={recibos ?? []} podeEditar={pode_editar} />
            </Deferred>
            <div><Button variant="outline" disabled title="Espera o layout oficial do lote (Manual do Receita Saúde v2.1)">Baixar o lote do dia</Button></div>
            <p className="text-xs text-muted-foreground">O layout do lote ainda precisa de conferência antes do piloto.</p>
          </CardContent></Card>
        )}
      </div>
    </>
  );
}

FiscalDoDia.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
