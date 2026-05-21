// Seção "Dados Fiscais BR" — compartilhada entre Cliente/Create.tsx e Cliente/Edit.tsx.
// Slice 2 da restauração dos campos BR (PRs #1313 backend → este PR UI).
//
// Recebe a tipagem mínima necessária via genéricos pra ambos os forms
// (Create usa post '/contacts', Edit usa put '/contacts/{id}') sem
// duplicação visual.
//
// Charter compliance:
//   - Cliente/Create.charter.md: PT-BR labels, Non-Goal "Validação CNPJ via Receita
//     Federal" → aqui só faz máscara visual + Rule BR mod-11 server-side
//   - LGPD: máscara visual no input não é display de PII (input value é o que
//     o user digitou, não dado retornado de banco)

import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { FileText } from 'lucide-react';
import { type ReactNode } from 'react';
import { formatCpfCnpj, INDICADOR_IE_OPTIONS, REGIME_TRIBUTARIO_OPTIONS } from '@/Lib/format-br';

/**
 * Campos BR usados nos dois forms (Create + Edit). Espelha colunas da
 * migration 2026_05_21_140000_restore_br_fields_to_contacts.php.
 */
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

/**
 * Erros tipados (chave string -> mensagem) — Inertia useForm errors object.
 */
export type DadosFiscaisBRErrors = Partial<Record<keyof DadosFiscaisBRData, string>>;

interface Props<T extends DadosFiscaisBRData> {
  data: T;
  setData: <K extends keyof T>(key: K, value: T[K]) => void;
  errors: DadosFiscaisBRErrors;
  /** Quando o tipo do contato é 'PF' a UI esconde campos PJ-only (IE, IM, suframa, nome fantasia). */
  isJuridica: boolean;
}

export default function DadosFiscaisBRSection<T extends DadosFiscaisBRData>({
  data,
  setData,
  errors,
  isJuridica,
}: Props<T>) {
  return (
    <section className="rounded-lg border border-border bg-background p-5">
      <h3 className="text-sm font-semibold text-foreground mb-4 flex items-center gap-2">
        <FileText size={16} className="text-muted-foreground" />
        Dados fiscais BR
      </h3>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Field
          label={isJuridica ? 'CNPJ' : 'CPF'}
          error={errors.cpf_cnpj}
        >
          <Input
            type="text"
            inputMode="numeric"
            value={formatCpfCnpj(data.cpf_cnpj)}
            onChange={(e) => {
              // Persiste sempre formatado pro re-render manter máscara consistente.
              // Backend normaliza com Util::onlyNumbers via Rule BR\CpfCnpj.
              setData('cpf_cnpj' as keyof T, formatCpfCnpj(e.target.value) as T[keyof T]);
            }}
            placeholder={isJuridica ? '00.000.000/0000-00' : '000.000.000-00'}
            maxLength={18}
            autoComplete="off"
          />
        </Field>

        {!isJuridica && (
          <Field label="RG" error={errors.rg}>
            <Input
              type="text"
              value={data.rg}
              onChange={(e) => setData('rg' as keyof T, e.target.value as T[keyof T])}
              maxLength={20}
              autoComplete="off"
            />
          </Field>
        )}

        {isJuridica && (
          <>
            <Field label="Nome fantasia" error={errors.nome_fantasia} colSpan={2}>
              <Input
                type="text"
                value={data.nome_fantasia}
                onChange={(e) => setData('nome_fantasia' as keyof T, e.target.value as T[keyof T])}
                maxLength={150}
                placeholder="Como aparece na fachada (opcional)"
              />
            </Field>

            <Field label="Inscrição estadual (IE)" error={errors.inscricao_estadual}>
              <Input
                type="text"
                value={data.inscricao_estadual}
                onChange={(e) =>
                  setData('inscricao_estadual' as keyof T, e.target.value as T[keyof T])
                }
                maxLength={30}
                placeholder="ISENTO se for o caso"
              />
            </Field>

            <Field label="Inscrição municipal (IM)" error={errors.inscricao_municipal}>
              <Input
                type="text"
                value={data.inscricao_municipal}
                onChange={(e) =>
                  setData('inscricao_municipal' as keyof T, e.target.value as T[keyof T])
                }
                maxLength={30}
              />
            </Field>

            <Field label="Indicador IE (NFe)" error={errors.indicador_ie}>
              <select
                value={data.indicador_ie}
                onChange={(e) =>
                  setData('indicador_ie' as keyof T, e.target.value as T[keyof T])
                }
                className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
              >
                {INDICADOR_IE_OPTIONS.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </select>
            </Field>

            <Field label="Regime tributário" error={errors.regime}>
              <select
                value={data.regime}
                onChange={(e) => setData('regime' as keyof T, e.target.value as T[keyof T])}
                className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
              >
                {REGIME_TRIBUTARIO_OPTIONS.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </select>
            </Field>

            <Field label="SUFRAMA" error={errors.suframa}>
              <Input
                type="text"
                value={data.suframa}
                onChange={(e) => setData('suframa' as keyof T, e.target.value as T[keyof T])}
                maxLength={20}
                placeholder="Apenas Zona Franca"
              />
            </Field>
          </>
        )}

        <Field label="Flags" colSpan={2}>
          <div className="flex flex-wrap items-center gap-x-6 gap-y-2 h-9">
            <label className="inline-flex items-center gap-1.5 text-sm">
              <input
                type="checkbox"
                checked={data.contribuinte}
                onChange={(e) =>
                  setData('contribuinte' as keyof T, e.target.checked as T[keyof T])
                }
              />
              Contribuinte ICMS
            </label>
            <label className="inline-flex items-center gap-1.5 text-sm">
              <input
                type="checkbox"
                checked={data.consumidor_final}
                onChange={(e) =>
                  setData('consumidor_final' as keyof T, e.target.checked as T[keyof T])
                }
              />
              Consumidor final (NFe)
            </label>
          </div>
        </Field>
      </div>
    </section>
  );
}

// ─── Subcomponents ──────────────────────────────────────────────────────────

function Field({
  label,
  children,
  error,
  colSpan,
}: {
  label: string;
  children: ReactNode;
  error?: string;
  colSpan?: number;
}) {
  return (
    <div className={colSpan === 2 ? 'sm:col-span-2' : ''}>
      <Label className="text-xs font-medium text-muted-foreground mb-1.5 block">{label}</Label>
      {children}
      {error && <p className="text-xs text-rose-600 mt-1">{error}</p>}
    </div>
  );
}
