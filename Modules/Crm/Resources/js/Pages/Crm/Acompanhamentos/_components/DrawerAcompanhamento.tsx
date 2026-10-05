// Drawer de detalhe do acompanhamento (thread Crm/07). Âncora: protótipo `crm-blade.jsx` →
// TelaAcompanhamentos (Drawer: "Informações de acompanhamento", "Descrição", rodapé "Log de
// acompanhamento" + "Marcar concluído"), mais a lista de registros que a Blade mostrava no modal de log.
// Registros vêm de GET /crm/follow-up-log?schedule_id=…&lista=1; concluir usa o MESMO PUT do modal de edição.

import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { enviar, type Listas, type Opcao, type Valores } from './acompanhamento';

export interface Detalhe {
  id: number; titulo: string; contato: string; inicio: string | null; fim: string | null; status: string | null;
  tipo: string | null; categoria: string | null; atribuidos: string[]; descricao: string;
  adicionado_por: string; adicionado_em: string | null; editar: Omit<Valores, 'title'>;
}
interface Registro { id: number; assunto: string; tipo: string; inicio: string | null; fim: string | null; descricao: string; por: string | null }

const rotulo = (lista: Opcao[] | undefined, v: string | null) => lista?.find((o) => o.value === v)?.label ?? v ?? '—';

function Linha({ rotulo: r, valor }: { rotulo: string; valor: React.ReactNode }) {
  return (
    <div className="flex justify-between gap-4 py-1 text-sm">
      <span className="text-muted-foreground">{r}</span>
      <span className="text-right">{valor || '—'}</span>
    </div>
  );
}

export default function DrawerAcompanhamento({ item, opcoes, onFechar, onRegistrar, onConcluido }: {
  item: Detalhe; opcoes?: Listas; onFechar: () => void; onRegistrar: () => void; onConcluido: () => void;
}) {
  const [registros, setRegistros] = useState<Registro[] | null>(null);
  const [concluindo, setConcluindo] = useState(false);

  useEffect(() => {
    let vivo = true;
    fetch(`/crm/follow-up-log?schedule_id=${item.id}&lista=1`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then((r) => r.json())
      .then((j) => { if (vivo) setRegistros(j.success ? j.registros : []); })
      .catch(() => { if (vivo) setRegistros([]); });
    return () => { vivo = false; };
  }, [item.id]);

  async function concluir() {
    setConcluindo(true);
    try {
      const e = item.editar;
      const r = await enviar(`/crm/follow-ups/${item.id}`, 'PUT', {
        ...e, title: item.titulo, status: 'completed',
        notify_via: { sms: e.notify_via.sms ? 1 : 0, mail: e.notify_via.mail ? 1 : 0 },
        allow_notification: e.allow_notification ? 1 : 0,
      });
      if (!r.ok || !r.json.success) return toast.error(r.json.msg || 'Não foi possível concluir.');
      toast.success(`${item.titulo} concluído.`);
      onConcluido();
    } catch { toast.error('Falha de rede. Tente de novo.'); } finally { setConcluindo(false); }
  }

  return (
    <Sheet open onOpenChange={(aberto) => { if (!aberto) onFechar(); }}>
      <SheetContent className="flex w-full flex-col sm:max-w-lg" data-contract="crm-acompanhamento-drawer">
        <SheetHeader>
          <Badge variant="neutral" className="w-fit">{rotulo(opcoes?.status, item.status)}</Badge>
          <SheetTitle>{item.titulo}</SheetTitle>
          <SheetDescription>{item.contato} · {rotulo(opcoes?.tipos, item.tipo)}</SheetDescription>
        </SheetHeader>
        <div className="flex-1 space-y-5 overflow-y-auto px-4">
          <section>
            <h3 className="mb-1 text-sm font-semibold">Informações de acompanhamento</h3>
            <Linha rotulo="Início" valor={item.inicio} />
            <Linha rotulo="Fim" valor={item.fim} />
            <Linha rotulo="Categoria" valor={item.categoria} />
            <Linha rotulo="Atribuído a" valor={item.atribuidos.join(', ')} />
            <Linha rotulo="Adicionado por" valor={[item.adicionado_por, item.adicionado_em].filter(Boolean).join(' · ')} />
          </section>
          <section>
            <h3 className="mb-1 text-sm font-semibold">Descrição</h3>
            <p className="whitespace-pre-line text-sm text-muted-foreground">{item.descricao || 'Sem descrição.'}</p>
          </section>
          <section data-contract="crm-acompanhamento-registros">
            <h3 className="mb-1 text-sm font-semibold">Registros{registros ? ` (${registros.length})` : ''}</h3>
            {registros === null ? <Skeleton className="h-16 w-full" />
              : registros.length === 0 ? <p className="text-sm text-muted-foreground">Nenhum registro ainda.</p>
              : (
                <ul className="space-y-3">{registros.map((r) => (
                  <li key={r.id} className="rounded-md border p-2 text-sm">
                    <div className="flex justify-between gap-2"><b>{r.assunto}</b><span className="text-xs text-muted-foreground">{rotulo(opcoes?.tipos, r.tipo)}</span></div>
                    <div className="text-xs text-muted-foreground">{[r.inicio, r.fim].filter(Boolean).join(' → ')}{r.por ? ` · ${r.por}` : ''}</div>
                    {r.descricao ? <p className="mt-1 whitespace-pre-line">{r.descricao}</p> : null}
                  </li>
                ))}</ul>
              )}
          </section>
        </div>
        <SheetFooter className="flex-row justify-end gap-2">
          <Button variant="outline" onClick={onRegistrar}>Log de acompanhamento</Button>
          <Button onClick={concluir} disabled={concluindo || item.status === 'completed'}>{concluindo ? 'Concluindo…' : 'Marcar concluído'}</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
