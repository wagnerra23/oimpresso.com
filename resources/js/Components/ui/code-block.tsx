import * as React from "react"
import { Check, Copy } from "lucide-react"
import { cn } from "@/Lib/utils"

interface CodeBlockProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Linguagem (apenas display). Highlighting real virá no PR 2 (shiki). */
  language?: string
  /** Código em string. */
  code: string
  /** Esconde header. */
  hideHeader?: boolean
  /** Esconde botão copiar. */
  hideCopy?: boolean
}

/**
 * CodeBlock — bloco de código com header (linguagem) + botão copiar.
 *
 * Fase 1 (este PR): visual polido, sem syntax highlight (pra manter bundle leve).
 * Fase 2 (Copiloto chat): adicionar shiki pra highlight real.
 *
 * <CodeBlock language="bash" code="npm install" />
 */
function CodeBlock({
  language,
  code,
  hideHeader,
  hideCopy,
  className,
  ...props
}: CodeBlockProps) {
  const [copied, setCopied] = React.useState(false)

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(code)
      setCopied(true)
      setTimeout(() => setCopied(false), 1600)
    } catch {
      /* silent — clipboard API pode falhar em iframe */
    }
  }

  return (
    <div
      data-slot="code-block"
      className={cn(
        "group relative overflow-hidden rounded-lg border bg-surface-1 font-mono text-small",
        className
      )}
      {...props}
    >
      {!hideHeader && (
        <div className="flex items-center justify-between border-b bg-surface-2 px-3 py-1.5">
          <span className="text-caption font-medium uppercase tracking-wider text-muted-foreground">
            {language ?? "code"}
          </span>
          {!hideCopy && (
            <button
              type="button"
              onClick={handleCopy}
              aria-label={copied ? "Copiado" : "Copiar código"}
              className="inline-flex h-7 items-center gap-1.5 rounded-md px-2 text-caption text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
            >
              {copied ? (
                <>
                  <Check className="size-3" />
                  <span>Copiado</span>
                </>
              ) : (
                <>
                  <Copy className="size-3" />
                  <span>Copiar</span>
                </>
              )}
            </button>
          )}
        </div>
      )}
      <pre className="overflow-x-auto p-4 text-foreground">
        <code>{code}</code>
      </pre>
    </div>
  )
}

export { CodeBlock }
