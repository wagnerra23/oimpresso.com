---
date: "2026-07-11"
topic: "Design coverage / pt-conformance / wave-1 PT + adversário 3× + descoberta do PROBLEMA REAL (aplicação integral do design)"
authors: [C, W]
prs: [4104, 4106, 4108, 4109]
related_adrs: [0335-fechamento-loop-diff-first-ds-sync-nota-honesta, 0314-poda-gates-onda-2-lei-fusoes, 0105-cliente-como-sinal-guiar-sem-mandar, 0107-emendation-0104-visual-comparison-gate-f3]
---

# 2026-07-11 — Design coverage → wave-1 PT → adversário 3× → o PROBLEMA REAL

Sessão longa que começou fechando o loop de tokens e terminou **descobrindo que estava na camada errada**. Registro o arco, as lições duras, e os chips que carregam o resto.

## O que landou no main
- **Loop diff-first de tokens fechado** — [ADR 0335](../decisions/0335-fechamento-loop-diff-first-ds-sync-nota-honesta.md) (#4104), com nota HONESTA (B−/C+, não A−/B+).
- **Gates de design** (advisory): `design-coverage` (#4106 — catraca de quantas telas declaram fonte de design), `pt-conformance` (#4108 — torna "herda PT-0X" FALSIFICÁVEL: verifica que a tela tem a assinatura estrutural do Padrão de Tela que declara), + `ds-tokens-build-sync`, `ds-mirror-drift.test`, `ds-token-diff.test` (#4105).
- **Wave 1** (#4109): 7 agentes classificaram 75 telas não-live por CONTEÚDO; a rede `pt-conformance` pegou 9 mismatches (2 gate-crude → refinei; 7 atípicas → revertidas). Resultado: **63 telas com PT declarado E verificado** (0 count-pump). Cobertura 18→81 (56%). Por PT: PT-01 ×27 · PT-02 ×19 · PT-03 ×11 · PT-04 ×4 · PT-05 ×2.

## Lição dura nº1 — auto-nota infla ~1 grau (adversário pegou 3×)
Rodei o adversário 3 vezes sobre notas que EU dei sobre o PRÓPRIO design:
1. Loop diff-first vs SOTA: A−/B+ → **B−/C+** (deflacionou ~1 grau).
2. Sistema design→código vs SOTA: C+/B− → **C** (~1 grau).
3. "Governança-como-código A−" → **B−** (conflei enforcing de token/pixel com advisory de consistência).
**Padrão confirmado:** nota sobre o próprio design infla ~1 grau na dimensão-âncora (dimensão a dedo + strawman do concorrente + N=1 tratado como battle-tested). Só o adversário na NOTA (não só no código) calibra. **Antes de canonizar superioridade, rodar o avaliador adversarial da própria afirmação.** (Reforça `memory/how-trabalhar.md` §degradação.)

## Lição dura nº2 — o PROBLEMA REAL não era governança (descoberto no fim)
Passei a sessão medindo *qual Padrão cada tela segue* (estrutura/governança). No fim o Wagner cortou reto e revelou o problema de verdade:
> **"meu problema é aplicação integral do design. tela o cliente não quer usar porque está feia. mostrando o design ele disse que usa. cada vez que for pedir customizações não são pegas como alterações das máquinas — parece que não sabe que alterou."**

Ou seja:
- **Sinal de cliente PAGO perfeito** (ADR 0105): o cliente (ROTA LIVRE/Larissa biz=4, 99% volume; Martinho biz=164) **rejeita a tela feia e aprova o design**. O design está pronto+validado.
- A dor é **FIDELIDADE**, não estrutura: o design lindo **não chega inteiro** na tela viva (cherry-pick de aplicação = quebrado/feio — histórico proibicoes §Cowork), e **nenhuma máquina pega quando o código drifta do protótipo**. "A máquina não sabe que alterou."
- Wagner **NÃO confia** que o Claude aplique ("já tentei, vai quebrar tudo"). Quer **detecção + documentação**, NÃO auto-aplicação.

**Reframe:** o que ele precisa é o loop diff-first UM NÍVEL ACIMA (protótipo ↔ código, não token), como **detecção**: customização autorizada = desvio declarado; alteração não-pedida = PEGA. Metade já existe — `reconcile-triplet.mjs` faz CONFORME/DIVERGÊNCIA_DECLARADA/DIVERGÊNCIA_MUDA (mas charter↔código, só PT-01). Estender pro protótipo, todas as telas. Juiz final = screenshot (Wagner, gate F1.5). Chip aberto: `respeitar o protótipo` (task_8af136bc).

## Estado honesto (sem inflar)
- Nota global do sistema design→código: **C**. Forte só onde já é LEI (Tier-0/tokens/pixel enforcing + `gate-selftest` metaguard). A camada de consistência de tela é **advisory, nascida hoje, 28% adoção** — promissora, não madura.
- **A jogada que paga não é os 6 chips de infra** — é o cliente pegar a tela que já disse que usaria, FIEL ao design, com a máquina garantindo que ninguém mexe sem o Wagner saber.

## Chips abertos (o programa inteiro, pra sessões frescas)
task_6c0b1541 máquina nascer-consistente · task_96af0712 gates→required/deletar (0271) · task_5628d208 terminar 4 goldens draft · task_fe4154b3 criar 138 charters · task_11404082 destravar 43 live (route-hits real) · task_a53b0dd0 7 atípicas + typo `related_prototipo` · **task_8af136bc respeitar o protótipo (o mais importante)**.
