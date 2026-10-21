// Campo NCM do formulário de produto (UC-PCAD-13 e UC-PCAD-14).
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { avisoNcm, ncmDoCadastro } from './ncm';

export function CampoNcm({
  value,
  onChange,
  error,
}: {
  value: string;
  onChange: (v: string) => void;
  error?: string;
}) {
  const aviso = error ? null : avisoNcm(value);

  return (
    <div>
      <Label htmlFor="ncm">NCM</Label>
      <Input
        id="ncm"
        inputMode="numeric"
        autoComplete="off"
        placeholder="8 dígitos, ex. 49111090"
        value={value}
        onChange={(e) => onChange(ncmDoCadastro(e.target.value))}
        aria-invalid={error ? true : undefined}
        aria-describedby="ncm-ajuda"
      />
      <p id="ncm-ajuda" className="text-[12px] text-muted-foreground mt-1">
        Classificação fiscal da mercadoria. Vai em cada item da nota.
      </p>
      {aviso && (
        <p data-testid="ncm-aviso" className="text-[12px] text-warning-fg mt-1">
          {aviso}
        </p>
      )}
      {error && <p className="text-[12px] text-destructive mt-1">{error}</p>}
    </div>
  );
}
