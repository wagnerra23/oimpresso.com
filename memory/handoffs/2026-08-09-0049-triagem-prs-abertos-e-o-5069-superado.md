---
date: "2026-08-09"
time: "00:49 UTC"
slug: triagem-prs-abertos-e-o-5069-superado
tldr: "Delta do handoff das 23:40. Triagem dos PRs abertos: 2 verdes represados mergeados, #5069 fechado por medição (367 commits atrás, e mergeá-lo REGREDIRIA 3 coisas), 2 branches mortas apagadas. Nenhum PR tinha a assinatura de deadlock."
decided_by: ["W"]
prs: [5468, 5304, 5069]
related_adrs:
  - "0370-module-surface-catalog-graph-required-emenda-0314"
  - "0130-handoff-append-only-mcp-first"
next_steps:
  - "~35 comandos agendados falhando diariamente em prod, sem causa comum (segue do handoff das 23:40)"
  - "loadMigrationsFrom latente em Forja (5 migrations) e Auditoria (auditoria_audit_notes AUSENTE)"
  - "#5119 aberto desde 31/07, draft, 7 falhas reais — sem dono"
---

# Handoff — Triagem dos PRs abertos e o #5069 superado (2026-08-09 00:49 UTC)

Delta do [handoff das 23:40](2026-08-08-2340-crons-governanca-mortos-em-prod.md), que fechou os
dois crons de governança. Aqui é a triagem do que estava aberto/pendurado.

## O que foi feito

| ação | resultado |
|---|---|
| 2 branches minhas penduradas | **apagadas** (re-conferido `...` = 0 imediatamente antes de cada) |
| [#5468](https://github.com/wagnerra23/oimpresso.com/pull/5468) floor do scorecard SDD | **MERGED** 00:46 UTC (squash, 97 ✅) |
| [#5304](https://github.com/wagnerra23/oimpresso.com/pull/5304) manifesto por-UC | **MERGED** 00:47 UTC (squash, 95 ✅) |
| [#5069](https://github.com/wagnerra23/oimpresso.com/pull/5069) ativação documental | **CLOSED** com recibo |

Nenhum PR aberto tinha a assinatura de deadlock (`BLOCKED` + 0 falhas + 0 pendentes) — era
isso que eu estava caçando, dado o incidente de 08/08.

## O #5069 não era conflito pra resolver — era PR superado

Comecei querendo desconflitar. A medição inverteu o veredito: **367 commits atrás** (14 à frente).

| categoria | arquivos | estado |
|---|---:|---|
| artefato **gerado** (`SUPERFICIE`/`PAINEL`/`ARCHITECTURE`/`_INDEX`) | **41** | stale — merge à mão de derivado é errado por construção; o certo é regerar |
| `scripts/governance/` | 10 | **6 byte-idênticos** a main (já landaram por outra rota) · 4 divergem |
| `.github/workflows/` | 2 | divergem |

Sobravam **52 linhas únicas** contra **303** que main tem e a branch não. Li as 52 —
**três eram regressão ativa**:

1. **`module-surface.yml`** adiciona `paths:` e um comentário afirmando *"NÃO está nos required
   checks"*. Hoje **é required** ([ADR 0370](../decisions/0370-module-surface-catalog-graph-required-emenda-0314.md)) e por isso main **não tem `paths:`**.
   Aplicar recriaria o **deadlock de 08/08** (required que não nasce ⇒ repo travado 2 dias).
   O comentário também é LC-10: enforcement afirmado em presente, apodrecido.
2. **`documentation-loop.mjs`** traz de volta o `normalizeFiles` que main **removeu em 07/08 de
   propósito** — o comentário no lugar dele prevê o caso: *"deixá-lo aqui convidaria a
   reintroduzir o bug no próximo caller"*. O bug: sem `-z` o git envelopa caminho não-ASCII com
   escape octal e o `.replaceAll('\\','/')` vira `\303\272` → `/303/272` ⇒ `unclassified`.
3. **`system-map.mjs`**: o strip de `~~riscado~~` já existe em main (`:750`) **com** o
   `semComentarioHtml()` que a branch não tem.

O CI do PR era de **30/07**, anterior aos 367 commits — não valia como sinal, e o log nem
capturava o step que falhou.

## Dois instrumentos me deram número plausível e errado

Mesmo tema da sessão (LC-08), agora em git:

- **`git diff A B` (dois pontos) × `A...B` (três).** Nas branches penduradas o dois-pontos deu
  **67 linhas** e parecia que carregavam trabalho; o três-pontos — que mede *o que a branch
  adiciona* — deu **0**. As 67 eram main andando 20 commits. Só apaguei após re-conferir o `...`.
- **`git diff <ref>:<path> <ref>:<path>` mangleado pelo MSYS.** Devolveu `so_na_branch=0` pros 2
  workflows **contradizendo o blob hash** que dizia "diferem". Refiz com `git diff A B -- <path>`
  **e controle positivo** (97 linhas): o número real era 3, não 0. Aceitar o primeiro resultado
  teria dado a conclusão certa pelo motivo errado.

Também errei antes disso: um `git cat-file -e` meu reportou os 2 workflows como *"não existe em
main"* — falso, o blob compare provou que existem. Instrumento meu, não fato.

## Nota de método

Tentei abrir `AskUserQuestion` com 4 opções pro #5069. O hook `block-askq-execution-menu`
**barrou, e estava certo**: era fato apurável (o que da branch ainda é inédito?), não decisão de
escopo. Apurei e decidi. O fechamento é reversível — um clique reabre, e o recibo está no PR.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 10 tasks em REVIEW, **nenhuma tocada** nesta sessão
- PRs abertos ao fechar: **#5473**, **#5472**, **#5397** (novos, de outras sessões, CI rodando) e
  **#5119** (draft, 7 falhas reais, parado desde 31/07)

## Pendências

- **~35 comandos agendados falhando diariamente** em prod, sem causa comum — segue do handoff
  das 23:40. O que os esconde é estrutural: `->onFailure()` loga frase genérica e o **stderr é
  descartado**.
- **`loadMigrationsFrom` latente** em `Forja` (5 migrations, tabelas já existem ⇒ migration nova
  seria pulada em silêncio) e `Auditoria` (`auditoria_audit_notes` AUSENTE). Barato, reusa o
  guard que entrou hoje.
- **#5119** sem dono há 9 dias.
