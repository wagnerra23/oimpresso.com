import { Building2, CheckCircle2, Circle, FileCheck2, Sparkles, User2 } from "lucide-react"

import { formatCpfCnpj, unmaskDigits } from "@/Lib/format-br"
import type { ClienteFormShared } from "./cliente-form-types"

/**
 * Rail de contexto do cadastro (pattern .cw-form-rail sticky). O salto 6,2→9,5
 * do diagnóstico Contacts veio de mover preview + prontidão fiscal pra cá.
 * Tudo client-side (computado do form) — sem chamada de backend.
 *
 * Slot do copiloto IA (dedup CNPJ + sugestão de grupo) fica preparado mas
 * inerte — depende de endpoint (PR-A2).
 */
export function ClienteRail<T extends ClienteFormShared>({
  data,
  isJuridica,
}: {
  data: T
  isJuridica: boolean
}) {
  const nome =
    data.supplier_business_name?.trim() ||
    [data.first_name, data.last_name].filter(Boolean).join(" ").trim() ||
    (isJuridica ? "Nova empresa" : "Novo contato")
  const inicial = (nome[0] ?? "?").toUpperCase()
  const docDigits = unmaskDigits(data.cpf_cnpj)
  const tipoLabel =
    data.type === "supplier" ? "Fornecedor" : data.type === "both" ? "Cliente e fornecedor" : "Cliente"

  const checks = isJuridica
    ? [
        { label: "CNPJ completo", done: docDigits.length === 14 },
        { label: "Razão social", done: !!(data.supplier_business_name || data.first_name)?.trim() },
        { label: "Inscrição estadual (ou ISENTO)", done: !!data.inscricao_estadual?.trim() },
        { label: "Regime tributário", done: !!data.regime },
        { label: "Indicador de IE", done: !!data.indicador_ie },
      ]
    : [
        { label: "CPF completo", done: docDigits.length === 11 },
        { label: "Nome", done: !!data.first_name?.trim() },
      ]
  const doneCount = checks.filter((c) => c.done).length
  const allDone = doneCount === checks.length

  return (
    <>
      {/* Preview vivo */}
      <div className="rounded-lg border border-border bg-background p-3.5">
        <div className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-2.5">
          Pré-visualização
        </div>
        <div className="flex items-center gap-2.5">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
            {inicial}
          </div>
          <div className="min-w-0">
            <div className="truncate text-sm font-semibold text-foreground">{nome}</div>
            <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
              {isJuridica ? <Building2 size={11} /> : <User2 size={11} />}
              {isJuridica ? "Pessoa jurídica" : "Pessoa física"} · {tipoLabel}
            </div>
          </div>
        </div>
        <dl className="mt-3 space-y-1 text-[11.5px]">
          {docDigits.length > 0 && (
            <div className="flex justify-between gap-2">
              <dt className="text-muted-foreground">{isJuridica ? "CNPJ" : "CPF"}</dt>
              <dd className="font-mono text-foreground">{formatCpfCnpj(data.cpf_cnpj)}</dd>
            </div>
          )}
          {!!data.email?.trim() && (
            <div className="flex justify-between gap-2">
              <dt className="text-muted-foreground">E-mail</dt>
              <dd className="truncate text-foreground">{data.email}</dd>
            </div>
          )}
          {!!data.mobile?.trim() && (
            <div className="flex justify-between gap-2">
              <dt className="text-muted-foreground">Celular</dt>
              <dd className="text-foreground">{data.mobile}</dd>
            </div>
          )}
        </dl>
      </div>

      {/* Prontidão fiscal (client-side) */}
      <div className="rounded-lg border border-border bg-background p-3.5">
        <div className="mb-2.5 flex items-center justify-between">
          <span className="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
            <FileCheck2 size={13} className="text-primary" />
            Prontidão fiscal
          </span>
          <span className="font-mono text-[10px] text-muted-foreground">
            {doneCount} de {checks.length}
          </span>
        </div>
        <ul className="space-y-1.5">
          {checks.map((c) => (
            <li key={c.label} className="flex items-center gap-2 text-[11.5px]">
              {c.done ? (
                <CheckCircle2 size={13} className="shrink-0 text-emerald-600 dark:text-emerald-400" />
              ) : (
                <Circle size={13} className="shrink-0 text-muted-foreground/50" />
              )}
              <span className={c.done ? "text-foreground" : "text-muted-foreground"}>{c.label}</span>
            </li>
          ))}
        </ul>
        {allDone && (
          <div className="mt-2.5 rounded-md bg-emerald-50 px-2.5 py-1.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
            Pronto pra emitir NF-e
          </div>
        )}
      </div>

      {/* Copiloto IA — slot preparado, inerte (PR-A2 liga ao endpoint) */}
      <div className="rounded-lg border border-dashed border-border bg-muted/30 p-3.5">
        <div className="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
          <Sparkles size={13} className="text-primary" />
          Copiloto
        </div>
        <p className="mt-1.5 text-[11.5px] text-muted-foreground">
          Dedup (&ldquo;já existe esse CNPJ&rdquo;) e sugestão de grupo chegam em breve.
        </p>
      </div>
    </>
  )
}
