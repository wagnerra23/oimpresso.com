// Guarda de ANATOMIA do `ui/card` — playbook ds-atomos thread 01.
//
// POR QUE ESTE ARQUIVO EXISTE: `Card` é consumido por 139 arquivos (varredura contada em
// 2026-09-13, `git grep -l "ui/card"` = 139 de 139 — Ponto 21 · Financeiro 16 · Essentials
// 15 · Whatsapp 17 · governance 9 · Forja 7 · Superadmin 6 · +19 áreas). A lei da pasta é
// ADITIVO OU NADA: `badge`/`note`/`flush` entram como opcionais e, ausentes, NÃO mudam um
// byte do markup. Sem esta guarda a promessa é afirmação; com ela é medida.
//
// O BASELINE ABAIXO É O MARKUP DE 733033864088 (o sha de `card.tsx` em origin/main antes
// desta thread — shadcn puro, 1.987 B). Se alguém mudar um default, este teste fica
// vermelho ANTES de as 139 telas repintarem em silêncio.
//
// ESCOPO HONESTO: jsdom NÃO calcula layout. Logo "não trunca", "quebra em 2 linhas" e
// "sangra até a borda" são assertados ESTRUTURALMENTE (a classe que produz o efeito está
// lá, e a que o impediria não está) — nunca como pixel medido. O pixel é o T7 do
// PROTOCOLO-COMPARACAO-RUNTIME, e ele não roda daqui.

import { describe, it, expect, afterEach } from "vitest"
import { render, cleanup } from "@testing-library/react"

import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardAction,
  CardContent,
  CardFooter,
} from "@/Components/ui/card"

afterEach(cleanup)

/** Markup exato de 733033864088 — um por parte, sem props. */
const BASELINE_733033864088: Record<string, string> = {
  card: '<div data-slot="card" class="flex flex-col gap-6 rounded-xl border bg-card py-6 text-card-foreground shadow-sm"></div>',
  "card-header":
    '<div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6"></div>',
  "card-title": '<div data-slot="card-title" class="leading-none font-semibold"></div>',
  "card-description": '<div data-slot="card-description" class="text-sm text-muted-foreground"></div>',
  "card-action":
    '<div data-slot="card-action" class="col-start-2 row-span-2 row-start-1 self-start justify-self-end"></div>',
  "card-content": '<div data-slot="card-content" class="px-6"></div>',
  "card-footer": '<div data-slot="card-footer" class="flex items-center px-6 [.border-t]:pt-6"></div>',
}

const SEM_PROPS = [
  ["card", <Card key="c" />],
  ["card-header", <CardHeader key="h" />],
  ["card-title", <CardTitle key="t" />],
  ["card-description", <CardDescription key="d" />],
  ["card-action", <CardAction key="a" />],
  ["card-content", <CardContent key="ct" />],
  ["card-footer", <CardFooter key="f" />],
] as const

describe("ui/card — GUARDA de default (aditivo ou nada)", () => {
  it.each(SEM_PROPS)("%s sem props novas: markup idêntico a 733033864088", (slot, node) => {
    const { container } = render(node)
    expect(container.firstElementChild!.outerHTML).toBe(BASELINE_733033864088[slot])
  })

  it("CONTROLE NEGATIVO: a comparação acima morde (uma classe a mais já reprova)", () => {
    const { container } = render(<Card className="ring-2" />)
    expect(container.firstElementChild!.outerHTML).not.toBe(BASELINE_733033864088.card)
  })

  it("props ausentes não emitem nó extra nenhum no header nem no título", () => {
    const { container } = render(
      <CardHeader>
        <CardTitle>Marcações mobile a validar</CardTitle>
      </CardHeader>
    )
    expect(container.querySelector('[data-slot="card-note"]')).toBeNull()
    expect(container.querySelector('[data-slot="card-title-badge"]')).toBeNull()
    // o título continua com o texto como filho DIRETO (sem wrapper) — é o contrato de 733033864088
    const title = container.querySelector('[data-slot="card-title"]')!
    expect(title.children.length).toBe(0)
    expect(title.textContent).toBe("Marcações mobile a validar")
  })
})

describe("ui/card — os três slots do Widget", () => {
  it("badge fica à direita do título, na mesma linha de base, e encolhe por último", () => {
    const { container } = render(
      <CardTitle badge="(3 pendentes · últimos 7 dias)">Marcações mobile a validar</CardTitle>
    )
    const title = container.querySelector('[data-slot="card-title"]')!
    const badge = container.querySelector('[data-slot="card-title-badge"]')!

    // estrutural: título e badge são irmãos na MESMA linha de base (o h3 não os disputa)
    expect(title.className).toContain("items-baseline")
    expect(badge.parentElement).toBe(title)
    expect(badge.previousElementSibling!.textContent).toBe("Marcações mobile a validar")
    expect(badge.textContent).toBe("(3 pendentes · últimos 7 dias)")

    // não trunca a si mesmo e não é o primeiro a ceder espaço
    expect(badge.className).toContain("whitespace-nowrap")
    expect(badge.className).toContain("shrink-0")
    expect(badge.className).not.toContain("truncate")
    // quem cede é o título
    expect(badge.previousElementSibling!.className).toContain("min-w-0")
  })

  it("note fica abaixo do título, na coluna 1, e quebra — nunca trunca", () => {
    const longa = "Sincronizado a cada 15 minutos; divergências acima de 3 minutos entram na fila de conferência"
    const { container } = render(
      <CardHeader note={longa}>
        <CardTitle>Marcações mobile a validar</CardTitle>
        <CardAction>x</CardAction>
      </CardHeader>
    )
    const note = container.querySelector('[data-slot="card-note"]')!
    expect(note.textContent).toBe(longa)
    expect(longa.length).toBeGreaterThan(90)

    // ordem no DOM: vem DEPOIS do título (logo, abaixo dele na grade)
    const header = container.querySelector('[data-slot="card-header"]')!
    const filhos = Array.from(header.children).map((e) => e.getAttribute("data-slot"))
    expect(filhos.indexOf("card-note")).toBeGreaterThan(filhos.indexOf("card-title"))

    // com CardAction presente o header vira 2 colunas — a nota tem de ficar na 1
    expect(note.className).toContain("col-start-1")
    expect(note.className).toContain("break-words")
    expect(note.className).not.toContain("truncate")
    expect(note.className).not.toContain("text-ellipsis")
  })

  it("flush zera só o padding do CORPO — header, footer e o próprio content intactos", () => {
    const { container } = render(
      <Card flush>
        <CardHeader />
        <CardContent />
        <CardFooter />
      </Card>
    )
    const card = container.querySelector('[data-slot="card"]')!
    expect(card.className).toContain("[&_[data-slot=card-content]]:px-0")

    // as partes NÃO mudam de assinatura própria — quem manda é o pai
    expect(container.querySelector('[data-slot="card-content"]')!.outerHTML).toBe(
      BASELINE_733033864088["card-content"]
    )
    expect(container.querySelector('[data-slot="card-header"]')!.outerHTML).toBe(
      BASELINE_733033864088["card-header"]
    )
    expect(container.querySelector('[data-slot="card-footer"]')!.outerHTML).toBe(
      BASELINE_733033864088["card-footer"]
    )
  })

  it("sem flush, o Card não ganha a variante (aditivo)", () => {
    const { container } = render(<Card />)
    expect(container.firstElementChild!.className).not.toContain("card-content")
  })
})
