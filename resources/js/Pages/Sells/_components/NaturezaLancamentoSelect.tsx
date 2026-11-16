// NaturezaLancamentoSelect — escolha da natureza de operação ao emitir a NF-e (onda 5b).
//
// Lista as naturezas criadas na tela de Tributação (GET /nfe-brasil/tributacao/naturezas/lancamento)
// que servem para uma NF-e de saída normal. O endpoint responde 404 com a flag
// useV2NfeNaturezas desligada e 403 sem a permissão de emitir: nos dois casos a lista fica
// vazia e o seletor não aparece, e a emissão segue como antes (sem operacao_id).
// RUNBOOK-tributacao §Naturezas, onda 5.
import { useId } from 'react';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { NATUREZA_PADRAO, type NaturezaLancamento } from './naturezaLancamento';

interface Props {
  naturezas: NaturezaLancamento[];
  value: string;
  onChange: (valor: string) => void;
  disabled?: boolean;
}

export default function NaturezaLancamentoSelect({ naturezas, value, onChange, disabled }: Props) {
  const rotuloId = useId();
  if (naturezas.length === 0) return null;

  return (
    <div className="block text-xs text-muted-foreground" data-contract="natureza-lancamento">
      <span id={rotuloId} className="mb-1 block font-medium text-foreground">
        Natureza da operação (NF-e)
      </span>
      <Select value={value} onValueChange={onChange} disabled={disabled}>
        <SelectTrigger
          className="h-8 w-full max-w-xs text-xs"
          aria-label="Natureza da operação"
          aria-describedby={rotuloId}
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SafeSelectItem value={NATUREZA_PADRAO}>Venda (padrão)</SafeSelectItem>
          {naturezas.map((n) => (
            <SafeSelectItem key={n.id} value={n.id ? String(n.id) : null}>
              {n.nome}
              {n.cfop ? ` · CFOP ${n.cfop}` : ''}
            </SafeSelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}
