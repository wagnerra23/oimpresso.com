// Seção "Dados do lead" (fonte · estágio de vida · atribuído a) — compartilhada pelo
// Cliente/Create e pelo Cliente/Edit quando a tela vem do LeadController (threads Crm/06 e 09).
// As opções chegam numa prop deferida `lead_opcoes`: a PÁGINA embrulha esta seção em
// `<Deferred data="lead_opcoes">` (o InertiaDeferredFrontendGuardTest lê o import na Page).
// O `useForm` é o da página.

import { Target } from 'lucide-react';
import { FormSection, FormGrid } from '@/Components/ui/form-section';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Field } from './Field';

export interface Opcao {
  value: string;
  label: string;
}

export interface LeadOpcoes {
  fontes: Opcao[];
  estagios: Opcao[];
  usuarios: Opcao[];
}

export interface DadosLeadValores {
  crm_source?: string;
  crm_life_stage?: string;
  user_id?: string[];
}

// Sentinela do Radix Select (não aceita value=""), mapeado pra '' no estado.
const NENHUM = '__none__';

export function DadosLeadSection({
  valores,
  opcoes,
  erros,
  onChange,
}: {
  valores: DadosLeadValores;
  opcoes?: LeadOpcoes;
  erros: Record<string, string | undefined>;
  onChange: <K extends keyof DadosLeadValores>(campo: K, valor: DadosLeadValores[K]) => void;
}) {
  return (
    <div className="mb-3 space-y-3">
      {erros.msg && (
        <p role="alert" className="text-sm text-destructive">
          {erros.msg}
        </p>
      )}
      <FormSection title="Dados do lead" icon={<Target />}>
        <FormGrid>
          <Field label="Fonte" error={erros.crm_source}>
            <OpcaoSelect
              rotulo="Fonte"
              valor={valores.crm_source ?? ''}
              opcoes={opcoes?.fontes ?? []}
              onChange={(v) => onChange('crm_source', v)}
            />
          </Field>
          <Field label="Estágio de vida" error={erros.crm_life_stage}>
            <OpcaoSelect
              rotulo="Estágio de vida"
              valor={valores.crm_life_stage ?? ''}
              opcoes={opcoes?.estagios ?? []}
              onChange={(v) => onChange('crm_life_stage', v)}
            />
          </Field>
          <Field label="Atribuído a" error={erros.user_id} fullRow>
            <OpcaoSelect
              rotulo="Atribuído a"
              valor={valores.user_id?.[0] ?? ''}
              opcoes={opcoes?.usuarios ?? []}
              onChange={(v) => onChange('user_id', v ? [v] : [])}
            />
          </Field>
        </FormGrid>
      </FormSection>
    </div>
  );
}

function OpcaoSelect({
  rotulo,
  valor,
  opcoes,
  onChange,
}: {
  rotulo: string;
  valor: string;
  opcoes: Opcao[];
  onChange: (v: string) => void;
}) {
  return (
    <Select value={valor || NENHUM} onValueChange={(v) => onChange(v === NENHUM ? '' : v)}>
      <SelectTrigger className="cw-input" aria-label={rotulo}>
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={NENHUM}>— Nenhum —</SelectItem>
        {opcoes
          .filter((o) => Boolean(o.value))
          .map((o) => (
            <SelectItem key={o.value} value={o.value}>
              {o.label}
            </SelectItem>
          ))}
      </SelectContent>
    </Select>
  );
}
