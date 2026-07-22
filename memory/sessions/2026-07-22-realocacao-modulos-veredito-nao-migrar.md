---
date: "2026-07-22"
topic: "Realocação documental — veredito memory/modulos/: NÃO migra (saída viva de module:specs)"
authors: [C]
outcomes:
  - "Sweep 53/53 classificados: 50 REVIEW + 1 APPROVE-misclassificado + 2 REJECT — zero movimentos legítimos"
  - "Provado que memory/modulos/ é output gerado (GenerateModuleSpecsCommand hardcoded)"
  - "Limite registrado: não re-tentar doc-relocation deste tronco"
---

# Veredito — `memory/modulos/` (53 arquivos) NÃO migra: é saída viva de máquina

## Mandato
Migrar os 53 arquivos de `memory/modulos/` pra `memory/requisitos/<Modulo>/` ou histórico
(proposta [estrutura-canon-memoria](../decisions/proposals/estrutura-canon-memoria.md) Parte II),
via classificador + adversário + executor ([REALOCACAO-DOCUMENTAL.md](../governance/REALOCACAO-DOCUMENTAL.md)).

## Veredito: NADA se move — e é o desfecho correto, não uma falha

Os 53 são **artefato GERADO** por `php artisan module:specs` — não docs curados de produto:
- [`GenerateModuleSpecsCommand.php:34`](../../app/Console/Commands/GenerateModuleSpecsCommand.php) — `$outDir = base_path('memory/modulos')` hardcoded; `:56` `File::put` por módulo; `:165` grava `INDEX.md`
- O próprio `INDEX.md` se declara: *"Gerado por `php artisan module:specs` em 2026-05-29 08:06"*
- Movê-los seria desfeito pelo próximo `module:specs` (drift instantâneo) e o runbook manda o adversário rejeitar arquivo gerado

## Recibo do sweep (classificador+adversário rodados nos 53 · 2026-07-22, HEAD cb9bd0609a)

| Veredicto | Qtd | Detalhe |
|---|---|---|
| REVIEW (`LOW_CONFIDENCE`, conf 0.72) | **50** | 37 iam pra `governance/` + 13 pra `reference/` — camada ERRADA (a palavra "hook(s)" no inventário dispara heurística de processo; sem frontmatter `module:`, `inferModule` nunca resolve) |
| APPROVE | **1** | `Auditoria.md` → `audits/` — **misclassificação por nome** (contém "AUDITORIA"; é o inventário do módulo Auditoria de produto). Aplicar seria regressão |
| REJECT (`IMMUTABLE_REFERRER`) | **2** | `INDEX.md` + `PontoWr2.md` — backlink em ADR 0237 + 3 sessions append-only |

Zero movimentos legítimos. Runbook honrado: REVIEW/REJECT = parar e reportar. Worktree limpa.

## O limite (pra próxima sessão não re-tentar)
- **Não re-propor migração de `memory/modulos/`** por doc-relocation — é output de máquina, mesma
  família da lápide §5 2026-07-12 (normalização em massa de legado) + regra "não mova arquivo gerado".
- A convergência de corpus pendente (passo 9 da proposta) mira **negócio** (`dominios/`+`clientes/`),
  não este tronco.
- Se o incômodo for `memory/modulos/` parecer legado: o conserto é **retargetar o gerador**
  (gravar em path obviamente-gerado + gitignore) — decisão [W], mexe em comando vivo ligado ao
  reconcile loop ([ADR 0237](../decisions/0237-jana-reconcile-loop-unico.md)). Mesmo isso deixa
  `INDEX.md`/`PontoWr2.md` no lugar (backlinks append-only).

## Nota da mesma sessão
O incidente de infra que interrompeu esta sessão (MCP fora do ar) tem log próprio:
[2026-07-22-incidente-mcp-link-flap-ct100.md](2026-07-22-incidente-mcp-link-flap-ct100.md).
