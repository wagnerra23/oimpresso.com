// Campo CEST do formulário de produto (UC-PCAD-15 e UC-PEDIT-12).
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { avisoCest, cestDoCadastro } from './cest';

export function CampoCest({
  value,
  onChange,
  error,
}: {
  value: string;
  onChange: (v: string) => void;
  error?: string;
}) {
  const aviso = error ? null : avisoCest(value);

  return (
    <div>
      <Label htmlFor="cest">CEST</Label>
      <Input
        id="cest"
        inputMode="numeric"
        autoComplete="off"
        placeholder="7 dígitos, ex. 2803800"
        value={value}
        onChange={(e) => onChange(cestDoCadastro(e.target.value))}
        aria-invalid={error ? true : undefined}
        aria-describedby="cest-ajuda"
      />
      <p id="cest-ajuda" className="text-[12px] text-muted-foreground mt-1">
        Só para mercadoria com substituição tributária.
      </p>
      {aviso && (
        <p data-testid="cest-aviso" className="text-[12px] text-warning-fg mt-1">
          {aviso}
        </p>
      )}
      {error && <p className="text-[12px] text-destructive mt-1">{error}</p>}
    </div>
  );
}
