// Drawer de detalhe do acompanhamento (thread Crm/07). Âncora: protótipo `crm-blade.jsx` →
// TelaAcompanhamentos (Drawer: "Informações de acompanhamento", "Descrição", rodapé "Log de
// acompanhamento" + "Marcar concluído"), mais a lista de registros que a Blade mostrava no modal de log.
// Forma (CRM-33): gaveta do protótipo via `CrmGaveta` — 480px, seções com título em caixa alta e
// tabela rótulo/valor mono. "Prazo" do protótipo não entra: não há prazo de acompanhamento no backend.
// Registros vêm de GET /crm/follow-up-log?schedule_id=…&lista=1; concluir usa o MESMO PUT do modal de edição.

import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { TOM, enviar, type Listas, type Opcao, type Valores } from './acompanhamento';
import { CrmGaveta, CrmMini, CrmPilula, CrmSecao } from '../../_components/CrmBlocos';

export interface Detalhe {
  id: number; titulo: string; contato: string; inicio: string | null; fim: string | null; status: string | null;
  tipo: string | null; categoria: string | null; atribuidos: string[]; descricao: string; informacao_adicional?: string | null;
  adicionado_por: string; adicionado_em: string | null; editar: Omit<Valores, 'title'>;
}
interface Registro { id: number; assunto: string; tipo: string; inicio: string | null; fim: string | null; descricao: string; por: string | null }

const rotulo = (lista: Opcao[] | undefined, v: string | null) => lista?.find((o) => o.value === v)?.label ?? v ?? '—';

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
    <CrmGaveta data-contract="crm-acompanhamento-drawer" onFechar={onFechar}
      selo={<CrmPilula tom={TOM[item.status ?? ''] ?? 'mute'}>{rotulo(opcoes?.status, item.status)}</CrmPilula>}
      titulo={item.titulo}
      subtitulo={`${item.contato} · ${rotulo(opcoes?.tipos, item.tipo)}`}
      rodape={<>
        <Button variant="outline" onClick={onRegistrar}>Log de acompanhamento</Button>
        <Button className="text-white" onClick={concluir} disabled={concluindo || item.status === 'completed'}>{concluindo ? 'Concluindo…' : 'Marcar concluído'}</Button>
      </>}
    >
      <CrmSecao titulo="Informações de acompanhamento">
        <CrmMini linhas={[
          ['Início', item.inicio || '—'],
          ['Fim', item.fim || '—'],
          ['Categoria', item.categoria || '—'],
          ['Atribuído a', item.atribuidos.join(', ') || '—'],
          ['Adicionado por', [item.adicionado_por, item.adicionado_em].filter(Boolean).join(' · ') || '—'],
        ]} />
      </CrmSecao>
      <CrmSecao titulo="Descrição">
        <p className="m-0 whitespace-pre-line text-[12.5px]" style={{ color: 'var(--text-dim)' }}>{item.descricao || 'Sem descrição.'}</p>
        {item.informacao_adicional ? (
          <p className="mb-0 mt-1.5 text-[12.5px]" style={{ color: 'var(--text-dim)' }}><b>Informação adicional:</b> {item.informacao_adicional}</p>
        ) : null}
      </CrmSecao>
      <CrmSecao data-contract="crm-acompanhamento-registros" titulo={`Registros${registros ? ` (${registros.length})` : ''}`}>
        {registros === null ? <Skeleton className="h-16 w-full" />
          : registros.length === 0 ? <p className="m-0 text-[12.5px]" style={{ color: 'var(--text-mute)' }}>Nenhum registro ainda.</p>
          : (
            <ul className="m-0 list-none space-y-2 p-0">{registros.map((r) => (
              <li key={r.id} className="rounded-md border p-2 text-[12.5px]" style={{ borderColor: 'var(--border-2)' }}>
                <div className="flex justify-between gap-2"><b>{r.assunto}</b><span className="text-[11px]" style={{ color: 'var(--text-mute)' }}>{rotulo(opcoes?.tipos, r.tipo)}</span></div>
                <div className="font-mono text-[11px]" style={{ color: 'var(--text-mute)' }}>{[r.inicio, r.fim].filter(Boolean).join(' → ')}{r.por ? ` · ${r.por}` : ''}</div>
                {r.descricao ? <p className="mb-0 mt-1 whitespace-pre-line">{r.descricao}</p> : null}
              </li>
            ))}</ul>
          )}
      </CrmSecao>
    </CrmGaveta>
  );
}
