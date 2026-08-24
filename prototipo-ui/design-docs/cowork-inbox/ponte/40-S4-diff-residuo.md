---
sessao: S4
titulo: Diff do resíduo de patch — Onda 4
autor: "[CC]"
criado: 2026-08-23
base: wagnerra23/oimpresso.com@main (tree d1ccdff91be9)
regra: sessão FRESCA — não herda contexto de chat anterior; lê a read-order abaixo do main e só depois age
---

# S4 · `prototipo-ui-patch/` — diff antes de qualquer delete

## Contrato de paralelismo (Lei 1 — `03-REGRAS-DE-PARALELISMO.md`)

| | |
|---|---|
| **Sessão** | S4 |
| **Escreve em** | **nenhum — read-only** |
| **NÃO toca** | tudo. Esta sessão não escreve nem deleta |
| **Estado** | só em `_saida-S4.md`. Não editar `01-LISTA-COMPLETA.md`, `github.md`, `memory/**`, `COWORK_NOTES.md`, `governance/required-checks-baseline.json` |

## Read-order obrigatória (do `main`, nunca de cópia local)
1. `CLAUDE.md` (raiz) — limites operacionais
2. `prototipo-ui/PROTOCOL.md` — papéis F1→F3.5, fases
3. `prototipo-ui/PRE-FLIGHT-TELA.md` — resolvedor de pré-requisito por tela
4. `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` — catraca (ADR 0286)
5. `memory/INDEX.md` + `memory/proibicoes.md` + `memory/LICOES_CC.md`
6. O charter da tela em questão (`resources/js/Pages/**/<Tela>.charter.md`)

> ⚠️ `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` é citado pelo `CLAUDE.md` mas **não apareceu** na varredura do `main` em 2026-08-23. Confirmar o caminho antes de citá-lo como lei.


> Esta sessão **não deleta nada**. Produz a lista de delete-seguro. Deletar é ato de [W].

## Escopo
4 prompts (`ONDAS-FINANCEIRO-APLICAR`, `ERRADICA-LOCACAO-ACTIONS`, `FORJA-ABSORCAO-TEAMMCP`, `PROMPT_MESTRE_SESSAO_2026-06-29`) + 10 subpastas espelho (`Modules/`, `Pages/`, `app/`, `memory/`, `resources/`, `routes/`, `scripts/`, `prototipos/`, `prototipo-ui/`, `pageheader-canon-v4/`) + `resources/` e `scripts/` na raiz local.

## Processo
1. Read-order.
2. Para cada arquivo do espelho: existe o mesmo caminho no `main`?
   - **Não existe** → 🔴 **cópia única**: sobe (vira onda mecânica própria), não deleta.
   - **Existe e é idêntico** → 🟢 delete-seguro.
   - **Existe e diverge** → 🟠 **triagem**: qual lado é mais novo? Local mais novo = pode ser trabalho perdido; main mais novo = stale clássico (L-42).
3. Amostrar antes de varrer: começar por `memory/` e `scripts/` (maior risco de cópia única).
4. Produzir 3 listas nomeadas. **Nenhuma linha sem as três colunas: caminho local · caminho main · veredito.**

## Critério de pronto
- [ ] 100% dos arquivos classificados em 🔴/🟢/🟠 (nada em "provavelmente")
- [ ] lista 🔴 vira pedido de onda mecânica
- [ ] lista 🟠 vira pauta de [W]
- [ ] `_saida-S4.md`

## Não fazer
❌ Nenhum `rm`. ❌ Nenhuma afirmação de identidade sem ter lido os dois lados no mesmo turno.
