// Filtro da lista de pacientes por fisioterapeuta responsável (UC-FPI-20).
// Valores: '' = todos, 'meus' = o usuário logado, ou o id de um profissional da empresa.
// O Radix Select não aceita value vazio: "Todos" usa um sentinela e vira '' ao enviar.
import { Label } from '@/Components/ui/label';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import type { Profissional } from './ResponsavelPaciente';

const TODOS = '__todos__';

export default function FiltroResponsavel({ valor, profissionais, onChange }: {
  valor: string; profissionais: Profissional[]; onChange: (valor: string) => void;
}) {
  return (
    <div className="flex max-w-md flex-col gap-1.5">
      <Label>Responsável</Label>
      <Select value={valor || TODOS} onValueChange={(v) => onChange(v === TODOS ? '' : v)}>
        <SelectTrigger aria-label="Filtrar por responsável"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SafeSelectItem value={TODOS}>Todos os pacientes</SafeSelectItem>
          <SafeSelectItem value="meus">Meus pacientes</SafeSelectItem>
          {profissionais.map((p) => <SafeSelectItem key={p.id} value={String(p.id)}>{p.nome}</SafeSelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );
}
