// Seção "Dados Fiscais BR" — compartilhada entre Cliente/Create.tsx e Cliente/Edit.tsx.
// PR-A (Onda F): migrada pros componentes canon @/Components/ui —
//   CNPJ lookup → InputGroup + InputGroupButton(loading/done)
//   feedback do lookup → FieldError / FieldSuccess
//   selects nativos (indicador IE, regime) → Select (@/ui)
//   checkboxes nativos (flags) → Checkbox (@/ui)
//   <section>/<Field> hand-rolled → FormSection/FormGrid + Field comum
//
// Charter/LGPD: máscara visual no input não é display de PII; Rule BR mod-11
// roda server-side. Lookup BrasilAPI é só preenchimento assistido (não Receita).

import { useState } from 'react';
import { FileText, Search } from 'lucide-react';

import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { FormSection, FormGrid } from '@/Components/ui/form-section';
import { InputGroup, InputGroupButton } from '@/Components/ui/input-group';
import { FieldError, FieldSuccess } from '@/Components/ui/field-state';
import { Field } from './Field';
import {
  formatCpfCnpj,
  unmaskDigits,
  INDICADOR_IE_OPTIONS,
  REGIME_TRIBUTARIO_OPTIONS,
} from '@/Lib/format-br';

/** Payload de GET /contacts/lookup/cnpj/{cnpj} (BrasilApiService normalized). */
export interface BrasilApiCnpjData {
  cnpj: string;
  razao_social: string | null;
  nome_fantasia: string | null;
  cep: string | null;
  logradouro: string | null;
  numero: string | null;
  bairro: string | null;
  municipio: string | null;
  uf: string | null;
}

/** Campos BR usados nos dois forms (migration 2026_05_21_140000). */
export interface DadosFiscaisBRData {
  cpf_cnpj: string;
  rg: string;
  inscricao_estadual: string;
  inscricao_municipal: string;
  indicador_ie: string; // '' | '1' | '2' | '9'
  nome_fantasia: string;
  consumidor_final: boolean;
  contribuinte: boolean;
  regime: string;
  suframa: string;
}

export type DadosFiscaisBRErrors = Partial<Record<keyof DadosFiscaisBRData, string>>;

interface Props<T extends DadosFiscaisBRData> {
  data: T;
  setData: <K extends keyof T>(key: K, value: T[K]) => void;
  errors: DadosFiscaisBRErrors;
  /** PF esconde campos PJ-only (IE, IM, suframa, nome fantasia, indicador). */
  isJuridica: boolean;
  /** Slice 5a — pai preenche campos fora de DadosFiscaisBRData (razão social + endereço). */
  onCnpjLookup?: (data: BrasilApiCnpjData) => void;
}

export default function DadosFiscaisBRSection<T extends DadosFiscaisBRData>({
  data,
  setData,
  errors,
  isJuridica,
  onCnpjLookup,
}: Props<T>) {
  const [lookupLoading, setLookupLoading] = useState(false);
  const [lookupError, setLookupError] = useState<string | null>(null);
  const [lookupSuccess, setLookupSuccess] = useState<string | null>(null);

  // Cast helper — chaves são keyof DadosFiscaisBRData ⊆ keyof T.
  const set = (key: keyof DadosFiscaisBRData, value: unknown) =>
    setData(key as keyof T, value as T[keyof T]);

  const cpfCnpjDigits = unmaskDigits(data.cpf_cnpj);
  const isCnpjComplete = isJuridica && cpfCnpjDigits.length === 14;

  const handleLookupCnpj = async () => {
    if (cpfCnpjDigits.length !== 14) {
      setLookupError('Digite o CNPJ completo (14 dígitos).');
      return;
    }
    setLookupLoading(true);
    setLookupError(null);
    setLookupSuccess(null);

    try {
      const resp = await fetch(`/contacts/lookup/cnpj/${cpfCnpjDigits}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      if (!resp.ok) {
        const j = await resp.json().catch(() => ({}));
        setLookupError(j.message ?? `CNPJ não encontrado (HTTP ${resp.status}).`);
        return;
      }

      const json = await resp.json();
      const api = json?.data as BrasilApiCnpjData | undefined;
      if (!api) {
        setLookupError('Resposta inesperada da BrasilAPI.');
        return;
      }

      if (api.nome_fantasia) set('nome_fantasia', api.nome_fantasia);
      onCnpjLookup?.(api);
      setLookupSuccess(`Dados preenchidos: ${api.razao_social ?? 'razão social'}.`);
    } catch {
      setLookupError('Erro de rede ao consultar BrasilAPI.');
    } finally {
      setLookupLoading(false);
    }
  };

  const onCpfCnpjChange = (raw: string) => {
    set('cpf_cnpj', formatCpfCnpj(raw));
    if (lookupError) setLookupError(null);
    if (lookupSuccess) setLookupSuccess(null);
  };

  return (
    <FormSection title="Dados fiscais BR" icon={<FileText />}>
      <FormGrid>
        {/* CNPJ/CPF — InputGroup + lookup (PJ) ou Input simples (PF) */}
        <div className="cw-field full-row">
          <Label className="cw-label">{isJuridica ? 'CNPJ' : 'CPF'}</Label>
          {isJuridica ? (
            <InputGroup>
              <Input
                variant="cowork"
                inputMode="numeric"
                value={formatCpfCnpj(data.cpf_cnpj)}
                onChange={(e) => onCpfCnpjChange(e.target.value)}
                aria-invalid={errors.cpf_cnpj ? true : undefined}
                placeholder="00.000.000/0000-00"
                maxLength={18}
                autoComplete="off"
              />
              <InputGroupButton
                loading={lookupLoading}
                done={!!lookupSuccess && !lookupError}
                onClick={handleLookupCnpj}
                disabled={!isCnpjComplete}
                title={
                  isCnpjComplete
                    ? 'Buscar dados na BrasilAPI'
                    : 'Digite o CNPJ completo (14 dígitos) pra habilitar'
                }
              >
                {!lookupLoading && !lookupSuccess && <Search />}
                <span className="hidden sm:inline">Buscar CNPJ</span>
              </InputGroupButton>
            </InputGroup>
          ) : (
            <Input
              variant="cowork"
              inputMode="numeric"
              value={formatCpfCnpj(data.cpf_cnpj)}
              onChange={(e) => onCpfCnpjChange(e.target.value)}
              aria-invalid={errors.cpf_cnpj ? true : undefined}
              placeholder="000.000.000-00"
              maxLength={14}
              autoComplete="off"
            />
          )}
          <FieldError>{errors.cpf_cnpj}</FieldError>
          <FieldError>{lookupError}</FieldError>
          {lookupSuccess && !lookupError && <FieldSuccess>{lookupSuccess}</FieldSuccess>}
        </div>

        {!isJuridica && (
          <Field label="RG" error={errors.rg}>
            <Input
              variant="cowork"
              value={data.rg}
              onChange={(e) => set('rg', e.target.value)}
              maxLength={20}
              autoComplete="off"
            />
          </Field>
        )}

        {isJuridica && (
          <>
            <Field label="Nome fantasia" error={errors.nome_fantasia} fullRow>
              <Input
                variant="cowork"
                value={data.nome_fantasia}
                onChange={(e) => set('nome_fantasia', e.target.value)}
                maxLength={150}
                placeholder="Como aparece na fachada (opcional)"
              />
            </Field>

            <Field label="Inscrição estadual (IE)" error={errors.inscricao_estadual}>
              <Input
                variant="cowork"
                value={data.inscricao_estadual}
                onChange={(e) => set('inscricao_estadual', e.target.value)}
                maxLength={30}
                placeholder="ISENTO se for o caso"
              />
            </Field>

            <Field label="Inscrição municipal (IM)" error={errors.inscricao_municipal}>
              <Input
                variant="cowork"
                value={data.inscricao_municipal}
                onChange={(e) => set('inscricao_municipal', e.target.value)}
                maxLength={30}
              />
            </Field>

            <Field label="Indicador IE (NFe)" error={errors.indicador_ie}>
              <Select
                value={data.indicador_ie || undefined}
                onValueChange={(v) => set('indicador_ie', v)}
              >
                <SelectTrigger className="cw-input" aria-label="Indicador de IE">
                  <SelectValue placeholder="— Selecione —" />
                </SelectTrigger>
                <SelectContent>
                  {INDICADOR_IE_OPTIONS.filter((o) => o.value).map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                      {o.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field label="Regime tributário" error={errors.regime}>
              <Select value={data.regime || undefined} onValueChange={(v) => set('regime', v)}>
                <SelectTrigger className="cw-input" aria-label="Regime tributário">
                  <SelectValue placeholder="— Selecione —" />
                </SelectTrigger>
                <SelectContent>
                  {REGIME_TRIBUTARIO_OPTIONS.filter((o) => o.value).map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                      {o.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field label="SUFRAMA" error={errors.suframa}>
              <Input
                variant="cowork"
                value={data.suframa}
                onChange={(e) => set('suframa', e.target.value)}
                maxLength={20}
                placeholder="Apenas Zona Franca"
              />
            </Field>
          </>
        )}

        {/* Flags fiscais */}
        <div className="cw-field full-row">
          <Label className="cw-label">Flags</Label>
          <div className="flex flex-wrap items-center gap-x-6 gap-y-2 pt-1">
            <label className="inline-flex cursor-pointer items-center gap-2 text-[12px] text-foreground">
              <Checkbox
                checked={data.contribuinte}
                onCheckedChange={(c) => set('contribuinte', c === true)}
              />
              Contribuinte ICMS
            </label>
            <label className="inline-flex cursor-pointer items-center gap-2 text-[12px] text-foreground">
              <Checkbox
                checked={data.consumidor_final}
                onCheckedChange={(c) => set('consumidor_final', c === true)}
              />
              Consumidor final (NFe)
            </label>
          </div>
        </div>
      </FormGrid>
    </FormSection>
  );
}
