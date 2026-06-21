// a11y RUNTIME (axe-core em jsdom) nos componentes canon — Fase 2 da determinização de a11y.
//
// POR QUE (auditoria 2026-06-06-arte-llm-judge-para-deterministico + benchmark vs SOTA):
// o jsx-a11y ESTÁTICO (Fase 1, ratchet #2359) pega ~40% — nome de import, role óbvio.
// O axe RUNTIME renderiza o componente e inspeciona o DOM real: aria-* coerente, role
// computado, nome acessível, associação label↔control. Determinístico (axe é regra, não LLM).
//
// ESCOPO: jsdom NÃO calcula layout → NÃO vê CONTRASTE de cor nem ordem de FOCO visual.
// Isso é a Fase 3 (axe em browser real, Pest 4 Browser — já mergeada #2360). Aqui: estrutura/ARIA.
//
// Asserta 0 violações de impacto `serious`+`critical` (as que importam; minor/moderate = degrau
// futuro do ratchet, como a Fase 3 começou em critical-only). Uso VÁLIDO dos componentes
// (Input com Label associado) — testamos o componente canon, não uso-errado.

import { describe, it, expect, afterEach } from "vitest"
import { render, cleanup } from "@testing-library/react"
import axe from "axe-core"

import { Button } from "@/Components/ui/button"
import { Input } from "@/Components/ui/input"
import { Label } from "@/Components/ui/label"
import { Checkbox } from "@/Components/ui/checkbox"
import { Textarea } from "@/Components/ui/textarea"
import { Badge } from "@/Components/ui/badge"
import { Card, CardHeader, CardTitle, CardContent } from "@/Components/ui/card"
import { Popover, PopoverTrigger, PopoverContent } from "@/Components/ui/popover"
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from "@/Components/ui/dropdown-menu"

afterEach(cleanup)

const IMPACTFUL = new Set(["serious", "critical"])

async function impactfulViolations(container: HTMLElement) {
  const { violations } = await axe.run(container, { resultTypes: ["violations"] })
  return violations
    .filter((v) => IMPACTFUL.has(v.impact ?? ""))
    .map((v) => `${v.impact}: ${v.id} — ${v.help}`)
}

describe("a11y axe (jsdom) — componentes canon, uso válido, 0 violações serious/critical", () => {
  it("form canon: Label+Input, Label+Textarea, Checkbox+Label, Button", async () => {
    const { container } = render(
      <form aria-label="formulário de teste">
        <Label htmlFor="nome">Nome</Label>
        <Input id="nome" />
        <Label htmlFor="obs">Observações</Label>
        <Textarea id="obs" />
        <div>
          <Checkbox id="aceito" />
          <Label htmlFor="aceito">Aceito os termos</Label>
        </div>
        <Button>Salvar</Button>
      </form>,
    )
    expect(await impactfulViolations(container)).toEqual([])
  })

  it("conteúdo canon: Card + Badge", async () => {
    const { container } = render(
      <Card>
        <CardHeader>
          <CardTitle>Título</CardTitle>
        </CardHeader>
        <CardContent>
          Conteúdo do cartão <Badge>novo</Badge>
        </CardContent>
      </Card>,
    )
    expect(await impactfulViolations(container)).toEqual([])
  })

  it("overlays canon (gatilho fechado): Popover + DropdownMenu com nome acessível", async () => {
    const { container } = render(
      <div>
        <Popover>
          <PopoverTrigger asChild>
            <Button>Filtros</Button>
          </PopoverTrigger>
          <PopoverContent>conteúdo do popover</PopoverContent>
        </Popover>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button>Status</Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent>
            <DropdownMenuItem>Todas</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>,
    )
    expect(await impactfulViolations(container)).toEqual([])
  })
})
