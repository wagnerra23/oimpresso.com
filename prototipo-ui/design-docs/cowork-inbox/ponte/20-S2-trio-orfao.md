---
sessao: S2
titulo: Trio órfão — Onda 2b
autor: "[CC]"
criado: 2026-08-23
base: wagnerra23/oimpresso.com@main (tree d1ccdff91be9)
regra: sessão FRESCA — não herda contexto de chat anterior; lê a read-order abaixo do main e só depois age
---

# S2 · 13 charters/casos que só existem aqui

## Contrato de paralelismo (Lei 1 — `03-REGRAS-DE-PARALELISMO.md`)

| | |
|---|---|
| **Sessão** | S2 |
| **Escreve em** | `resources/js/Pages/Ponto/**` |
| **NÃO toca** | `Pages/**` não-Ponto (é do S9) · `contrato/` (é do S5) |
| **Estado** | só em `_saida-S2.md`. Não editar `01-LISTA-COMPLETA.md`, `github.md`, `memory/**`, `COWORK_NOTES.md`, `governance/required-checks-baseline.json` |

## Read-order obrigatória (do `main`, nunca de cópia local)
1. `CLAUDE.md` (raiz) — limites operacionais
2. `prototipo-ui/PROTOCOL.md` — papéis F1→F3.5, fases
3. `prototipo-ui/PRE-FLIGHT-TELA.md` — resolvedor de pré-requisito por tela
4. `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` — catraca (ADR 0286)
5. `memory/INDEX.md` + `memory/proibicoes.md` + `memory/LICOES_CC.md`
6. O charter da tela em questão (`resources/js/Pages/**/<Tela>.charter.md`)

> ⚠️ `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` é citado pelo `CLAUDE.md` mas **não apareceu** na varredura do `main` em 2026-08-23. Confirmar o caminho antes de citá-lo como lei.


## Escopo fechado (inventário fechado 2026-08-23 15:15Z, 28 locais × 33 no main)

**Trio ausente no main (charter + casos):**
`Ponto/Conformidade` · `Ponto/Fechamento` · `Ponto/Index` · `Ponto/RepP` · `Relatorios/Index`

**Só o `.casos.md` falta (charter já está no main):**
`Ponto/Colaboradores/Index` · `Ponto/Configuracoes/Index` · `Ponto/Escalas/Index`

## Processo
1. Read-order. Para cada um dos 8 caminhos, **reconfirmar ausência no `main` neste turno** (o inventário tem data; data envelhece).
2. Conferir o frontmatter contra o padrão vivo do `main` (ex.: `resources/js/Pages/Ponto/Espelho/Index.casos.md`): `id`/`casos`/`irmaos`/`tecnica`/`por_que`/`owner`/`last_run`/`last_run_ci`.
3. Charter precisa das seções canônicas: Mission · Goals · Non-Goals · UX targets · Automation hooks · Anti-hooks · Pendências antes de `status: live`.
4. Casos precisa de: tabela de Rastreabilidade (UC · prio · âncora · teste · status) + um bloco por UC com **Dado/Quando/Então** + `[BACKLOG]` explícito.
5. Todo UC de tenant leva `[T0]` + ADR 0093 + nota "biz=1 vs fictício, **nunca biz=4**" (ADR 0101).
6. Status honesto: sem lane executada, é `⬜ não verificado` — nunca ✅.
7. Escrever `_saida-S2.md`.

## Critério de pronto
- [ ] 8 telas × arquivos completos, frontmatter no padrão
- [ ] nenhum UC marcado ✅ sem lane real
- [ ] cada charter tem Non-Goals **e** Anti-hooks (é o que [W] aprova)
- [ ] `_saida-S2.md` escrito

## Não fazer
❌ Não tocar nas 10 telas que só existem no `main` (Welcome, Dashboard/Index, BancoHoras/Show, Colaboradores/Edit, Configuracoes/Reps, Escalas/Form, Importacoes/Create+Show, Intercorrencias/Create+Show) — produção à frente é correto.
