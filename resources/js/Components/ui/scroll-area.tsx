import * as React from "react"
import { cn } from "@/Lib/utils"

// Implementação simples sem @radix-ui/react-scroll-area para evitar dependência extra.
function ScrollArea({
  className,
  children,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      data-slot="scroll-area"
      className={cn("relative overflow-auto", className)}
      {...props}
    >
      {children}
    </div>
  )
}

export { ScrollArea }
