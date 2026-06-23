# Cowork (design) — nova estrutura, como buscar info, e telas a desenvolver

> **LER NO INÍCIO DE CADA SESSÃO DE DESIGN.** Contrato de adaptação à estrutura SSOT (Wagner 2026-06-23, pós-adversário). Substitui o hábito de "exportar tudo + manter memória própria". Detalhe do método na [ADR-proposta SSOT](../memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md) (§Método operacional endurecido) + livro-razão [`RECONCILIACAO-COWORK-MEMORIA.md`](RECONCILIACAO-COWORK-MEMORIA.md).

## Por que mudou
Antes: cada handoff era um zip flat despejado em pastas espalhadas (`prototipos/<tela>/`) + cópia da memória → **bagunça, sem diff, fonte stale, duplicação**. Agora: **1 fonte da verdade com histórico**, memória única canônica, e um canal que te diz onde a produção já te passou.

## A estrutura (o que é o quê)
| Camada | Onde | Quem manda | Você (design) |
|---|---|---|---|
| **Seu export visual** | `prototipo-ui/cowork/` (sobrescrito a cada handoff) | **Cowork** | exporta aqui (só fonte: jsx/tsx/css/html) |
| **Memória** (decisões, sessões, regras) | `memory/**` → **MCP** | **repo/canon** | **só LÊ** (nunca mantenha memória paralela) |
| **Charters & casos** (contrato por tela) | `resources/js/Pages/**/*.charter.md` · `*.casos.md` | **repo/canon** | **só LÊ** (NÃO re-crie/duplique no seu export) |
| **Tela viva** (produção) | `resources/js/Pages/**/*.tsx` no `main` | **repo/canon** | **só LÊ** (é o estado real, não sua fotocópia) |

## Como buscar a informação (fonte única = a memória canônica / MCP)
1. **Estado real de uma tela** → leia o `.tsx` + `.charter.md` dela **no `main`** (MCP/git), **nunca** sua cópia local (ela envelhece — é o seu próprio PORTÃO 1).
2. **Decisões/regras/identidade** → `memory/decisions/` (ADRs) via MCP `decisions-search`; a memória da tela → o **charter** dela.
3. **O que a produção já passou de você** → [`FRESCOR-PRODUCAO-vs-PROTOTIPO.md`](FRESCOR-PRODUCAO-vs-PROTOTIPO.md) (🔵 produção à frente = **puxe o vivo**, não re-exporte como fonte · 🟠 produção atrás = **desenvolva** · ⚪ fundação = espere [W]).
4. **O que já foi conciliado** (idempotência) → [`RECONCILIACAO-COWORK-MEMORIA.md`](RECONCILIACAO-COWORK-MEMORIA.md).

## O que você NÃO faz mais
- ❌ **Não mantenha memória própria** nem exporte `memory/` — a única é a canônica. Conhecimento novo seu é **destilado** (resumo ancorado por-tela no charter/SPEC), nunca despejado como sessão crua.
- ❌ **Não exporte transporte**: screenshots/PNG, duplicatas `?v=`, `.bak`, prompts já processados, DS antigos. Só fonte.
- ❌ **Não re-crie charters/casos** no seu export — são canon; duplicar gera conflito (foram removidos do `cowork/`).
- ❌ **Não trate seu export como fonte** de uma tela 🔵 — a produção já passou; puxe o vivo.

## O loop (cada handoff)
`Você exporta → cowork/ (overwrite+commit = diff)` → `Code: git diff = o que mudou → mapeia só o que mudou → destila memória nova no charter/SPEC` → `frescor volta pra você`. Telas/DS compartilhado (sidebar, PageHeader) = PR de fundação sequencial, nunca em paralelo.

## Telas a desenvolver (estado 2026-06-23 — do FRESCOR)
**🟠 DESENVOLVER (produção atrás — valor real):**
- **Compras · Grade Matrix** (tam×cor) — gap de produto pro vestuário (Larissa/ROTA LIVRE). Componente `GradeMatrixInput.tsx` existe mas órfão + falta endpoint backend. Caller canônico = `Purchase/Create.tsx` (não a Index). Gated por ADR de convergência compras↔purchase.

**🔵 NÃO re-exportar como fonte — produção já passou (puxe o vivo):**
- **Atendimento/CaixaUnificada** (V4, charter v19) — é o OURO; "não repintar" (LEI). Use-a como referência de DS pras outras telas, não a refaça.
- **Cliente** (Crm) — drawer 760 + US-078 já no vivo.
- **PageHeader** — já é canon (roxo 295 universal).

**⚪ FUNDAÇÃO — espera decisão [W] (não paraleliza com telas):**
- **Sidebar** — trava no desempate dark (`cockpit.css`) × light (UI-0014). Cmd+K visível é o único ganho barato livre.

**📋 RESTO DO WORKSPACE (vendas, financeiro, oficina, kb, norte, forja…):** não foi feito frescor por-tela ainda — **não assuma**; rode o frescor (compara seu export × `Pages/<Mod>/<Tela>.tsx` no `main`) antes de tratar como "a desenvolver".

---
_Origem: handoff 2026-06-23 + red-team adversarial da integração de memória. Pareado com a [ADR-proposta SSOT](../memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md) (método) e [`FRESCOR-PRODUCAO-vs-PROTOTIPO.md`](FRESCOR-PRODUCAO-vs-PROTOTIPO.md) (frescor por-tela)._
