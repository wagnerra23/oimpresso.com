// Filtros de posição, equipamento e "só favoritos" da biblioteca (US-FISIO-004, UC-FBI-08/09).
// As opções vêm da lista fechada do servidor (`config('fisio.biblioteca.*')`); a tela não inventa
// valor. O estado vive na URL (query string), então recarregar ou compartilhar o link mantém o filtro.
import { Star } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import type { Facetas, FiltrosFacetasValor } from './facetas';

/** Sentinela do "todas" — nunca value="" (o Radix derruba a tela). */
const TODAS = '__todas__';

export default function FiltrosFacetas({ facetas, valor, onChange }: {
  facetas: Facetas;
  valor: FiltrosFacetasValor;
  onChange: (proximo: FiltrosFacetasValor) => void;
}) {
  return (
    <div className="flex flex-wrap items-end gap-3" data-contract="fisio-biblioteca-facetas">
      <Select value={valor.pose || TODAS} onValueChange={(v) => onChange({ ...valor, pose: v === TODAS ? '' : v })}>
        <SelectTrigger className="w-48" aria-label="Filtrar por posição"><SelectValue placeholder="Posição" /></SelectTrigger>
        <SelectContent>
          <SafeSelectItem value={TODAS}>Todas as posições</SafeSelectItem>
          {facetas.posicoes.map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
        </SelectContent>
      </Select>
      <Select value={valor.equipment || TODAS} onValueChange={(v) => onChange({ ...valor, equipment: v === TODAS ? '' : v })}>
        <SelectTrigger className="w-52" aria-label="Filtrar por equipamento"><SelectValue placeholder="Equipamento" /></SelectTrigger>
        <SelectContent>
          <SafeSelectItem value={TODAS}>Qualquer equipamento</SafeSelectItem>
          {facetas.equipamentos.map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
        </SelectContent>
      </Select>
      <Button
        type="button"
        variant={valor.fav ? 'default' : 'outline'}
        aria-pressed={valor.fav}
        onClick={() => onChange({ ...valor, fav: !valor.fav })}
      >
        <Star className="size-4" aria-hidden="true" /> Só favoritos
      </Button>
    </div>
  );
}
