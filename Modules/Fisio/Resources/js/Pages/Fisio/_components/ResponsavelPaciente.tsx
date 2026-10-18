// Fisioterapeuta responsável pelo paciente e transferência (UC-FPS-30..32).
// A lista de profissionais e o histórico vêm do servidor (props deferidas, grupo 'responsavel').
// Só profissionais da mesma empresa com acesso ao Fisio aparecem; o servidor recusa qualquer outro.
import { Deferred, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';

export interface Profissional { id: number; nome: string }
export interface Transferencia { id: number; quando: string | null; por: string | null; de: string | null; para: string | null }

const dataHora = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—';

function Transferir({ pacienteId, atualId, profissionais, onPronto }: {
  pacienteId: number; atualId: number | null; profissionais: Profissional[]; onPronto: () => void;
}) {
  const opcoes = profissionais.filter((p) => p.id !== atualId);
  const form = useForm<{ para_user_id: string }>({ para_user_id: '' });
  const enviar = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/fisio/pacientes/${pacienteId}/responsavel`, { preserveScroll: true, onSuccess: () => { form.reset(); onPronto(); } });
  };

  if (opcoes.length === 0) {
    return <p className="text-xs text-muted-foreground">Não há outro profissional com acesso ao Fisio nesta empresa.</p>;
  }
  return (
    <form onSubmit={enviar} className="flex flex-col gap-2" data-contract="fisio-transferir-paciente">
      <Label>Transferir para</Label>
      <Select value={form.data.para_user_id} onValueChange={(v) => form.setData('para_user_id', v)}>
        <SelectTrigger aria-label="Transferir para"><SelectValue placeholder="Escolha o profissional" /></SelectTrigger>
        <SelectContent>
          {opcoes.map((p) => <SafeSelectItem key={p.id} value={String(p.id)}>{p.nome}</SafeSelectItem>)}
        </SelectContent>
      </Select>
      {form.errors.para_user_id && <p className="text-xs text-destructive">{form.errors.para_user_id}</p>}
      <div className="flex gap-2">
        <Button type="submit" size="sm" disabled={form.processing || form.data.para_user_id === ''}>Confirmar transferência</Button>
        <Button type="button" size="sm" variant="ghost" onClick={onPronto}>Cancelar</Button>
      </div>
      <p className="text-xs text-muted-foreground">A transferência fica registrada com o seu usuário, a data e a hora.</p>
    </form>
  );
}

function Historico({ itens }: { itens: Transferencia[] }) {
  if (itens.length === 0) return null;
  return (
    <ol className="flex flex-col gap-1 text-xs text-muted-foreground" data-contract="fisio-transferencias">
      {itens.map((t) => (
        <li key={t.id}>{dataHora(t.quando)} · {t.por ?? 'Usuário'} transferiu de {t.de ?? 'sem responsável'} para {t.para ?? '—'}</li>
      ))}
    </ol>
  );
}

export default function ResponsavelPaciente({ pacienteId, responsavel, podeTransferir, profissionais, transferencias }: {
  pacienteId: number;
  responsavel: Profissional | null;
  podeTransferir: boolean;
  profissionais?: Profissional[];
  transferencias?: Transferencia[];
}) {
  const [transferindo, setTransferindo] = useState(false);

  return (
    <div className="flex flex-col gap-2 border-t border-border pt-3">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <span className="text-muted-foreground">Fisioterapeuta responsável:</span>
        <span className="font-medium" data-contract="fisio-responsavel">{responsavel?.nome ?? 'Sem responsável'}</span>
        {podeTransferir && !transferindo && (
          <Button type="button" size="sm" variant="outline" className="ml-auto" onClick={() => setTransferindo(true)}>Transferir paciente</Button>
        )}
      </div>
      <Deferred data={['profissionais', 'transferencias']} fallback={<Skeleton className="h-6 w-full" />}>
        <>
          {transferindo && (
            <Transferir pacienteId={pacienteId} atualId={responsavel?.id ?? null} profissionais={profissionais ?? []} onPronto={() => setTransferindo(false)} />
          )}
          <Historico itens={transferencias ?? []} />
        </>
      </Deferred>
    </div>
  );
}
