import { type FormEvent } from "react"
import { Banknote, Contact2, MapPin, Save, User2 } from "lucide-react"

import { Button } from "@/Components/ui/button"
import { Input } from "@/Components/ui/input"
import { Textarea } from "@/Components/ui/textarea"
import { Segmented } from "@/Components/ui/segmented"
import { FormSection, FormGrid } from "@/Components/ui/form-section"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select"
import DadosFiscaisBRSection, {
  type BrasilApiCnpjData,
} from "./DadosFiscaisBRSection"
import { Field } from "./Field"
import { ClienteRail } from "./ClienteRail"
import type { ClienteFormShared, CustomerGroup } from "./cliente-form-types"

// Sentinela do Radix Select (não aceita value=""), mapeado pra '' no estado.
const GRUPO_NENHUM = "__none__"

/**
 * ClienteForm — corpo compartilhado do cadastro (Create + Edit dividem ~90%).
 * Aplica a Onda F: Segmented (PF/PJ), Select (@/ui), FormSection/FormGrid,
 * FieldError, e envolve com `.cw-form-layout` + rail de contexto sticky.
 *
 * As páginas (Create/Edit) ficam finas: montam o useForm + submit + chrome e
 * delegam o corpo pra cá.
 */
export function ClienteForm<T extends ClienteFormShared>({
  data,
  setData,
  errors,
  types,
  customerGroups,
  isJuridica,
  onCnpjLookup,
  processing,
  submitLabel,
  cancelHref,
  onSubmit,
}: {
  data: T
  setData: <K extends keyof T>(key: K, value: T[K]) => void
  errors: Partial<Record<keyof T, string>>
  types: Record<string, string>
  customerGroups: CustomerGroup[]
  isJuridica: boolean
  onCnpjLookup: (api: BrasilApiCnpjData) => void
  processing: boolean
  submitLabel: string
  cancelHref: string
  onSubmit: (e: FormEvent<HTMLFormElement>) => void
}) {
  const set = (key: keyof ClienteFormShared, value: unknown) =>
    setData(key as keyof T, value as T[keyof T])
  // Acesso solto pros erros por nome de campo (a prop genérica tipa as chaves).
  const err = errors as Record<string, string | undefined>
  const showFinanceiro = data.type === "customer" || data.type === "both"

  return (
    <form onSubmit={onSubmit} className="cw-form-layout">
      <div className="min-w-0 space-y-3">
        {/* ── Identificação ── */}
        <FormSection title="Identificação" icon={<User2 />}>
          <FormGrid>
            <Field label="Tipo" error={err.type}>
              <Select value={data.type} onValueChange={(v) => set("type", v)}>
                <SelectTrigger className="cw-input" aria-label="Tipo de contato">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(types).map(([k, v]) => (
                    <SelectItem key={k} value={k}>
                      {v}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field label="Pessoa" error={err.contact_type_radio}>
              <Segmented
                accent
                aria-label="Tipo de pessoa"
                value={data.contact_type_radio}
                onValueChange={(v) => set("contact_type_radio", v)}
                options={[
                  { value: "person", label: "Física" },
                  { value: "business", label: "Jurídica" },
                ]}
              />
            </Field>

            <Field
              label={isJuridica ? "Razão social" : "Nome completo"}
              error={err.first_name}
              required
              fullRow
            >
              <Input
                variant="cowork"
                value={data.first_name}
                onChange={(e) => set("first_name", e.target.value)}
                required
                maxLength={100}
              />
            </Field>

            {!isJuridica && (
              <Field label="Sobrenome" error={err.last_name}>
                <Input
                  variant="cowork"
                  value={data.last_name}
                  onChange={(e) => set("last_name", e.target.value)}
                  maxLength={100}
                />
              </Field>
            )}

            <Field label="Tax number (legado UPOS)" error={err.tax_number}>
              <Input
                variant="cowork"
                value={data.tax_number}
                onChange={(e) => set("tax_number", e.target.value)}
                placeholder="Use CPF / CNPJ abaixo (legacy)"
              />
            </Field>
          </FormGrid>
        </FormSection>

        {/* ── Dados fiscais BR ── */}
        <DadosFiscaisBRSection<T>
          data={data}
          setData={setData}
          errors={errors}
          isJuridica={isJuridica}
          onCnpjLookup={onCnpjLookup}
        />

        {/* ── Contato ── */}
        <FormSection title="Contato" icon={<Contact2 />}>
          <FormGrid>
            <Field label="Celular" error={err.mobile}>
              <Input
                variant="cowork"
                type="tel"
                value={data.mobile}
                onChange={(e) => set("mobile", e.target.value)}
                placeholder="(00) 00000-0000"
              />
            </Field>
            <Field label="Telefone fixo" error={err.landline}>
              <Input
                variant="cowork"
                type="tel"
                value={data.landline}
                onChange={(e) => set("landline", e.target.value)}
                placeholder="(00) 0000-0000"
              />
            </Field>
            <Field label="E-mail" error={err.email} fullRow>
              <Input
                variant="cowork"
                type="email"
                value={data.email}
                onChange={(e) => set("email", e.target.value)}
                placeholder="cliente@exemplo.com.br"
              />
            </Field>
          </FormGrid>
        </FormSection>

        {/* ── Endereço ── */}
        <FormSection title="Endereço" icon={<MapPin />}>
          <FormGrid>
            <Field label="Endereço" error={err.address_line_1} fullRow>
              <Input
                variant="cowork"
                value={data.address_line_1}
                onChange={(e) => set("address_line_1", e.target.value)}
              />
            </Field>
            <Field label="Cidade" error={err.city}>
              <Input variant="cowork" value={data.city} onChange={(e) => set("city", e.target.value)} />
            </Field>
            <Field label="UF" error={err.state}>
              <Input
                variant="cowork"
                value={data.state}
                onChange={(e) => set("state", e.target.value)}
                maxLength={2}
              />
            </Field>
            <Field label="CEP" error={err.zip_code}>
              <Input
                variant="cowork"
                value={data.zip_code}
                onChange={(e) => set("zip_code", e.target.value)}
                placeholder="00000-000"
              />
            </Field>
            <Field label="Endereço de entrega" error={err.shipping_address} fullRow>
              <Textarea
                variant="cowork"
                value={data.shipping_address}
                onChange={(e) => set("shipping_address", e.target.value)}
                placeholder="Preencha se a entrega for em endereço diferente do cadastro acima."
                rows={2}
              />
            </Field>
          </FormGrid>
        </FormSection>

        {/* ── Financeiro (só cliente/ambos) ── */}
        {showFinanceiro && (
          <FormSection title="Financeiro" icon={<Banknote />}>
            <FormGrid>
              <Field label="Saldo inicial" error={err.opening_balance}>
                <Input
                  variant="cowork"
                  value={data.opening_balance}
                  onChange={(e) => set("opening_balance", e.target.value)}
                  placeholder="0,00"
                />
              </Field>
              <Field label="Limite de crédito" error={err.credit_limit}>
                <Input
                  variant="cowork"
                  value={data.credit_limit}
                  onChange={(e) => set("credit_limit", e.target.value)}
                  placeholder="0,00"
                />
              </Field>
              {customerGroups.length > 0 && (
                <Field label="Grupo de clientes" error={err.customer_group_id} fullRow>
                  <Select
                    value={data.customer_group_id || GRUPO_NENHUM}
                    onValueChange={(v) => set("customer_group_id", v === GRUPO_NENHUM ? "" : v)}
                  >
                    <SelectTrigger className="cw-input" aria-label="Grupo de clientes">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={GRUPO_NENHUM}>— Nenhum —</SelectItem>
                      {customerGroups.map((g) => (
                        <SelectItem key={g.id} value={String(g.id)}>
                          {g.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>
              )}
            </FormGrid>
          </FormSection>
        )}

        {/* ── Ações ── */}
        <div className="flex items-center justify-end gap-2 pt-2">
          <Button type="button" variant="cowork-ghost" asChild>
            <a href={cancelHref}>Cancelar</a>
          </Button>
          <Button type="submit" variant="cowork-primary" disabled={processing}>
            <Save className="mr-1.5 h-4 w-4" />
            {processing ? "Salvando…" : submitLabel}
          </Button>
        </div>
      </div>

      {/* ── Rail de contexto ── */}
      <aside className="cw-form-rail">
        <ClienteRail data={data} isJuridica={isJuridica} />
      </aside>
    </form>
  )
}
