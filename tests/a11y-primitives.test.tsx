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
import { render, cleanup, within, fireEvent } from "@testing-library/react"
import axe from "axe-core"
import type { ColumnDef } from "@tanstack/react-table"

import { Button } from "@/Components/ui/button"
import { Input } from "@/Components/ui/input"
import { Label } from "@/Components/ui/label"
import { Checkbox } from "@/Components/ui/checkbox"
import { Textarea } from "@/Components/ui/textarea"
import { Badge } from "@/Components/ui/badge"
import StatusBadge from "@/Components/shared/StatusBadge"
import DataTable, { type PaginatorShape } from "@/Components/shared/DataTable"
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

  // ── AP7 · o dot ────────────────────────────────────────────────────────────────
  // Estes asserts existem por causa da LC-30 ("correção que passa no CI inteiro e é INERTE
  // no runtime"): typecheck + lint + build verdes NÃO provam que o elemento chegou ao DOM.
  // O dot é a 3ª perna do AP7 (PRE-MERGE-UI:69) e nasceu em 2026-09-01; aqui se prova que ele
  // RENDERIZA, com controle negativo — sem o negativo, um `dot` sempre-ligado passaria igual.
  it("AP7: StatusBadge renderiza o dot, e Badge cru só com opt-in", async () => {
    const { container } = render(
      <div>
        <span data-t="status"><StatusBadge kind="arquivo_prazo" value="vencendo" /></span>
        <span data-t="opt-in"><Badge variant="danger" dot>Sensível</Badge></span>
        <span data-t="sem-dot"><Badge variant="danger">Sensível</Badge></span>
      </div>,
    )
    const dots = (sel: string) =>
      container.querySelectorAll(`[data-t="${sel}"] [data-slot="badge-dot"]`).length

    expect(dots("status")).toBe(1) // status é status por definição → dot por padrão
    expect(dots("opt-in")).toBe(1) // Badge cru honra o opt-in
    expect(dots("sem-dot")).toBe(0) // CONTROLE NEGATIVO: sem a prop, nada muda nas 82 telas

    // decorativo: o texto ao lado já diz o estado, então o dot não pode virar ruído de leitor
    const dot = container.querySelector('[data-slot="badge-dot"]')
    expect(dot?.getAttribute("aria-hidden")).toBe("true")
    expect(await impactfulViolations(container)).toEqual([])
  })

  // ── DataTable · nome acessível ────────────────────────────────────────────────
  // O `shared/DataTable` renderizava `<table>` SEM `<caption>` e SEM `aria-label`: tabela
  // sem nome acessível, em 8 pontos de render de 6 telas — e DUAS delas (`Arquivos/Index`,
  // `Jana/Plataforma`) põem duas tabelas na MESMA página, indistinguíveis pra quem navega
  // por tabela (NVDA `T`, lista de tabelas do JAWS, rotor do VoiceOver). Corrigido com
  // `caption` OBRIGATÓRIA → `<caption class="sr-only">` (técnica WCAG H39).
  //
  // ⚠️ POR QUE ESTE TESTE NÃO É `impactfulViolations` — e é a parte que não pode se perder:
  // o axe **não tem regra** que exija nome acessível em tabela. MEDIDO (axe-core 4.12.1 em
  // jsdom, 2026-09-04) nos 5 arranjos — sem nada · só `scope` · só `caption` · os dois · só
  // `aria-label` — **0 violações em TODOS, em qualquer impacto**. Logo o axe fica verde
  // antes e depois do conserto: usá-lo aqui seria presence-gate que não mede nada (LC-11).
  // Corolário pro `UC-DASH-18`: subir aquele `assertNoAccessibilityIssues(level: 0)` pra
  // `level: 1` NÃO pegaria isto — o piso não está baixo, o axe é cego a esta classe.
  //
  // O que este teste mede, então, é o **nome COMPUTADO**: `getByRole('table', { name })`
  // resolve o accname pela mesma cadeia que a AT usa, em vez de conferir o atributo que eu
  // mesmo escrevi (§5 2026-07-16 — medir a propriedade resolvida, não a que você mandou).
  it("DataTable: a tabela tem nome acessível vindo da prop `caption`", async () => {
    type Linha = { id: number; nome: string }
    const LINHAS: Linha[] = [{ id: 1, nome: "a.pdf" }]
    const COLUNAS: ColumnDef<Linha, any>[] = [
      { accessorKey: "nome", header: "Nome" },
      { accessorKey: "id", header: "Código" },
    ]
    const umaPagina = (l: Linha[]): PaginatorShape<Linha> => ({
      data: l, total: l.length, current_page: 1, last_page: 1, from: 1, to: l.length, links: [],
    })

    const { container, getByRole, queryByRole, getAllByRole } = render(
      <div>
        <div data-t="canon">
          <DataTable<Linha>
            columns={COLUNAS}
            data={LINHAS}
            pagination={umaPagina(LINHAS)}
            endpoint="/arquivos"
            caption="Acervo de arquivos"
            showSearch={false}
            rowKey={(r) => r.id}
          />
        </div>
        {/* CONTROLE NEGATIVO — mesma estrutura, à mão, SEM caption. Sem ele, uma asserção
            de nome passaria mesmo que o `getByRole` casasse por qualquer outro motivo. */}
        <div data-t="sem-nome">
          <table>
            <thead><tr><th>Nome</th><th>Código</th></tr></thead>
            <tbody><tr><td>a.pdf</td><td>1</td></tr></tbody>
          </table>
        </div>
      </div>,
    )

    // 1) POSITIVO: o nome computado é a copy da prop.
    expect(getByRole("table", { name: "Acervo de arquivos" })).toBeTruthy()

    // 2) NEGATIVO: a tabela sem caption EXISTE (são 2 tabelas) mas não atende pelo nome —
    //    é isso que prova que o nome, e não a mera presença de <table>, é o que discrimina.
    expect(getAllByRole("table")).toHaveLength(2)
    expect(
      within(container.querySelector('[data-t="sem-nome"]') as HTMLElement)
        .queryByRole("table", { name: "Acervo de arquivos" }),
    ).toBeNull()
    void queryByRole

    // 3) O nome é INVISÍVEL: caption visível mexeria no layout das 6 telas já em produção.
    //    E `<caption>` só é válida como PRIMEIRO filho de <table> — fora dali o parser a
    //    reposiciona ou descarta, e o nome sumiria sem erro nenhum.
    const tabela = container.querySelector('[data-t="canon"] table') as HTMLTableElement
    const caption = tabela.querySelector("caption") as HTMLElement
    expect(caption.textContent).toBe("Acervo de arquivos")
    expect(caption.classList.contains("sr-only")).toBe(true)
    expect(tabela.firstElementChild).toBe(caption)

    // 4) `scope="col"` em todo <th> do cabeçalho — técnica WCAG H63 e o padrão que
    //    `Pages/Home/Index.tsx:424-425` já escrevia à mão.
    const ths = Array.from(tabela.querySelectorAll("thead th"))
    expect(ths).toHaveLength(2)
    expect(ths.every((th) => th.getAttribute("scope") === "col")).toBe(true)

    // 5) O axe continua limpo (não é a defesa — ver o bloco acima —, é higiene).
    expect(await impactfulViolations(container)).toEqual([])
  })

  // ── DataTable · alça de redimensionar coluna (`resizable`) ───────────────────────────────
  // Ligada na lista de produtos (UC-PIDX-16, `resources/js/Pages/Produto/Index.casos.md`).
  // A alça é só de mouse, como no `DataGrid` do DS. O que este caso mede é que ela NÃO
  // aparece pra quem não usa mouse — nem na tabulação, nem no nome do cabeçalho, nem como
  // elemento exposto — e que arrastar muda a largura da coluna sem tirar nada disso do lugar.
  // É o único caso do `resizable` que roda em lane; o conjunto completo está em
  // `tests/js/datatable-redimensionar.test.tsx`.
  it("DataTable resizable: a alça fica fora da árvore de acessibilidade, e arrastar só muda a largura", async () => {
    type Linha = { id: number; nome: string; local: string }
    const LINHAS: Linha[] = [{ id: 1, nome: "Camiseta", local: "Matriz" }]
    const COLUNAS: ColumnDef<Linha, any>[] = [
      { id: "acao", header: "Ação", enableSorting: false, meta: { width: 96, resizable: false }, cell: () => "…" },
      { id: "nome", header: "Nome", accessorFn: (l) => l.nome, meta: { width: 300 } },
      { id: "local", header: "Local", enableSorting: false, meta: { width: 150 }, cell: ({ row }) => row.original.local },
    ]
    const pagina: PaginatorShape<Linha> = {
      data: LINHAS, total: 1, current_page: 1, last_page: 1, from: 1, to: 1, links: [],
    }
    const montar = (resizable: boolean) => (
      <DataTable<Linha>
        columns={COLUNAS}
        data={LINHAS}
        pagination={pagina}
        caption="Itens"
        showSearch={false}
        density="grid"
        resizable={resizable}
        rowKey={(r) => r.id}
      />
    )
    const SELETOR = '[data-slot="datatable-redimensionar"]'
    const nomes = (c: HTMLElement) => within(c).getAllByRole("columnheader").map((h) => h.textContent)
    const tabulaveis = (c: HTMLElement) => c.querySelectorAll("a[href], button, input, select, textarea, [tabindex]").length

    // CONTROLE: a mesma tabela SEM a prop não tem alça. Sem ele, "a alça não muda nada"
    // passaria também se a alça nunca fosse renderizada.
    const sem = render(montar(false))
    expect(sem.container.querySelectorAll(SELETOR)).toHaveLength(0)
    const nomesSem = nomes(sem.container)
    const tabulaveisSem = tabulaveis(sem.container)
    sem.unmount()

    const { container, getByRole } = render(montar(true))

    // 1) A alça existe só onde deve: não na coluna que se declara fixa, não na última.
    const alcas = Array.from(container.querySelectorAll<HTMLElement>(SELETOR))
    expect(alcas).toHaveLength(1)
    const thNome = within(container).getAllByRole("columnheader")[1] as HTMLElement
    expect(thNome.contains(alcas[0] as HTMLElement)).toBe(true)

    // 2) Invisível pra tecnologia assistiva e fora da tabulação.
    expect(alcas[0]?.getAttribute("aria-hidden")).toBe("true")
    expect(alcas[0]?.hasAttribute("tabindex")).toBe(false)
    expect(tabulaveis(container)).toBe(tabulaveisSem)

    // 3) O nome de cada cabeçalho é o mesmo com e sem a alça, e a tabela segue nomeada.
    expect(nomes(container)).toEqual(nomesSem)
    expect(getByRole("table", { name: "Itens" })).toBeTruthy()

    // 4) Arrastar +50 muda a largura daquela coluna e de nenhuma outra. O jsdom não faz
    //    layout, então a largura renderizada do cabeçalho (a partida do arrasto) é informada.
    const larguras = () => Array.from(container.querySelectorAll("colgroup col")).map((c) => (c as HTMLElement).style.width)
    expect(larguras()).toEqual(["96px", "300px", "150px"])
    thNome.getBoundingClientRect = () => ({ width: 300, height: 28, top: 0, left: 0, right: 300, bottom: 28, x: 0, y: 0, toJSON: () => ({}) })
    fireEvent.mouseDown(alcas[0] as HTMLElement, { clientX: 784 })
    fireEvent.mouseMove(document, { clientX: 834 })
    fireEvent.mouseUp(document)
    expect(larguras()).toEqual(["96px", "350px", "150px"])

    // 5) Depois do arrasto, nomes e axe seguem iguais.
    expect(nomes(container)).toEqual(nomesSem)
    expect(await impactfulViolations(container)).toEqual([])
  })
})
