// Posição e equipamento no formulário do exercício da clínica (UC-FBI-10). Só valores da lista
// fechada do servidor. "Nenhum" exclui os outros equipamentos (o servidor recusa a combinação).
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { alternarEquipamento, type Facetas } from './facetas';

const SEM_POSICAO = '__sem__';

export default function CamposFacetas({ facetas, pose, equipment, erros, onPose, onEquipment }: {
  facetas: Facetas;
  pose: string;
  equipment: string[];
  erros: Record<string, string | undefined>;
  onPose: (valor: string) => void;
  onEquipment: (valor: string[]) => void;
}) {
  const erroEquipamento = erros.equipment ?? Object.entries(erros).find(([k]) => k.startsWith('equipment.'))?.[1];

  return (
    <>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="fx-pose">Posição</Label>
        <Select value={pose || SEM_POSICAO} onValueChange={(v) => onPose(v === SEM_POSICAO ? '' : v)}>
          <SelectTrigger id="fx-pose"><SelectValue placeholder="Posição" /></SelectTrigger>
          <SelectContent>
            <SafeSelectItem value={SEM_POSICAO}>Não informada</SafeSelectItem>
            {facetas.posicoes.map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
          </SelectContent>
        </Select>
        {erros.pose && <p className="text-xs text-destructive">{erros.pose}</p>}
      </div>
      <fieldset className="flex flex-col gap-2">
        <legend className="mb-1 text-sm font-medium">Equipamento</legend>
        <div className="grid grid-cols-2 gap-2">
          {facetas.equipamentos.map((o) => {
            const id = `fx-eq-${o.value}`;
            return (
              <div key={o.value} className="flex items-center gap-2">
                <Checkbox
                  id={id}
                  checked={equipment.includes(o.value)}
                  onCheckedChange={(v) => onEquipment(alternarEquipamento(equipment, o.value, v === true))}
                />
                <Label htmlFor={id} className="font-normal">{o.label}</Label>
              </div>
            );
          })}
        </div>
        {erroEquipamento && <p className="text-xs text-destructive">{erroEquipamento}</p>}
      </fieldset>
    </>
  );
}
