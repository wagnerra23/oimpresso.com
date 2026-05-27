import * as React from "react"

import { cn } from "@/Lib/utils"

type InputVariant = "cowork" | "shadcn"

interface InputProps extends React.ComponentProps<"input"> {
  /**
   * Visual variant.
   *
   * **Default desde 2026-05-27 (ADR UI-0015):** `cowork` — bg sólido `--cw-surface`,
   * text 13px, label dim, ring accent-soft. Aplica classes `.cw-input` definidas
   * em resources/css/cowork-fields.css.
   *
   * **Opt-in legacy:** `shadcn` — visual canônico shadcn (bg-transparent,
   * text-sm, ring-ring/50). Use APENAS quando precisar em containers escuros
   * onde bg branco quebra hierarquia (raro).
   *
   * Histórico: até PR #1698, default era shadcn e cowork era opt-in. PRs #1698→#1700
   * provaram fidelidade ao protótipo Cowork no drawer Cliente. Wagner 2026-05-27
   * pediu padronização do site inteiro → invertemos o default.
   */
  variant?: InputVariant
}

function Input({ className, type, variant = "cowork", ...props }: InputProps) {
  if (variant === "shadcn") {
    return (
      <input
        type={type}
        data-slot="input"
        className={cn(
          "h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30",
          "focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50",
          "aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40",
          className
        )}
        {...props}
      />
    )
  }

  return (
    <input
      type={type}
      data-slot="input"
      className={cn("cw-input", className)}
      {...props}
    />
  )
}

export { Input }
export type { InputVariant, InputProps }
