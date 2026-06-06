// Primitivos de layout (ADR 0253 · F3) — prova que a camada compõe e que
// props viram TOKEN (classe utilitária), nunca px/hex literal.
//
// Nota: sem @testing-library/jest-dom no setup do projeto (ver tests/js/setup.ts) —
// uso querySelector + className/tagName direto, padrão de tests/fiscal-status-badge.test.tsx.

import { describe, it, expect, afterEach } from "vitest"
import { render, cleanup } from "@testing-library/react"

import { Box, Stack, Inline, Grid, Container, Text } from "@/Components/layout"

afterEach(cleanup)

describe("primitivos — props viram classe-token", () => {
  it("Stack: gap/align/justify mapeiam pros utilitários de token", () => {
    const { container } = render(<Stack gap={6} align="center" justify="between" />)
    const el = container.querySelector('[data-slot="stack"]')!
    expect(el.className).toContain("flex")
    expect(el.className).toContain("flex-col")
    expect(el.className).toContain("gap-6")
    expect(el.className).toContain("items-center")
    expect(el.className).toContain("justify-between")
  })

  it("Stack: gap default = 4 quando não passado", () => {
    const { container } = render(<Stack />)
    expect(container.querySelector('[data-slot="stack"]')!.className).toContain("gap-4")
  })

  it("Inline: wrap liga flex-wrap; default é horizontal centralizado", () => {
    const { container } = render(<Inline wrap />)
    const el = container.querySelector('[data-slot="inline"]')!
    expect(el.className).toContain("flex-row")
    expect(el.className).toContain("flex-wrap")
    expect(el.className).toContain("items-center")
  })

  it("Grid: cols + gap viram grid-cols-N / gap-N", () => {
    const { container } = render(<Grid cols={3} gap={8} />)
    const el = container.querySelector('[data-slot="grid"]')!
    expect(el.className).toContain("grid")
    expect(el.className).toContain("grid-cols-3")
    expect(el.className).toContain("gap-8")
  })

  it("Box: p/px/py viram padding-token", () => {
    const { container } = render(<Box p={4} px={6} />)
    const el = container.querySelector('[data-slot="box"]')!
    expect(el.className).toContain("p-4")
    expect(el.className).toContain("px-6")
  })

  it("Container: size vira max-width-token e centraliza", () => {
    const { container } = render(<Container size="lg" />)
    const el = container.querySelector('[data-slot="container"]')!
    expect(el.className).toContain("mx-auto")
    expect(el.className).toContain("max-w-screen-lg")
  })

  it("Text: tone/size viram token semântico; default tag = p", () => {
    const { container } = render(<Text tone="muted" size="sm">oi</Text>)
    const el = container.querySelector('[data-slot="text"]')!
    expect(el.tagName).toBe("P")
    expect(el.className).toContain("text-muted-foreground")
    expect(el.className).toContain("text-sm")
    expect(el.textContent).toBe("oi")
  })
})

describe("primitivos — polimorfismo (semântica desacoplada do estilo)", () => {
  it("Text as=h2 renderiza H2 mantendo as classes-token", () => {
    const { container } = render(<Text as="h2" size="2xl" weight="bold">Título</Text>)
    const el = container.querySelector('[data-slot="text"]')!
    expect(el.tagName).toBe("H2")
    expect(el.className).toContain("text-2xl")
    expect(el.className).toContain("font-bold")
  })

  it("Stack asChild projeta as classes no filho (sem div extra)", () => {
    const { container } = render(
      <Stack asChild gap={2}>
        <section data-testid="secao" />
      </Stack>,
    )
    const el = container.querySelector("section")!
    expect(el.getAttribute("data-slot")).toBe("stack")
    expect(el.className).toContain("flex-col")
    expect(el.className).toContain("gap-2")
  })
})

describe("primitivos — composição (prova viva: zero flex solto)", () => {
  it("uma tela-cartão composta 100% por primitivos rende a árvore esperada", () => {
    const { container } = render(
      <Container size="md">
        <Stack gap={4}>
          <Text as="h1" size="2xl" weight="semibold">Cabeçalho</Text>
          <Grid cols={2} gap={4}>
            <Box p={4}><Text tone="muted">A</Text></Box>
            <Box p={4}><Text tone="muted">B</Text></Box>
          </Grid>
          <Inline gap={2} justify="end">
            <button>Cancelar</button>
            <button>Salvar</button>
          </Inline>
        </Stack>
      </Container>,
    )
    expect(container.querySelector('[data-slot="container"]')).toBeTruthy()
    expect(container.querySelectorAll('[data-slot="box"]').length).toBe(2)
    expect(container.querySelector('[data-slot="grid"]')!.className).toContain("grid-cols-2")
    expect(container.querySelector("h1")!.textContent).toBe("Cabeçalho")
  })
})
