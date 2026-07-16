---
date: "2026-07-08"
time: "10:44 BRT"
slug: financeiro-fidelidade-fingerprint-furos
tldr: "Fidelidade do Financeiro/Unificado + fingerprint virou MECANISMO. 9 PRs: pills do period bar, roxinho --primary dark 0.62→0.7 (ADR UI-0021), alinhamento right-justify, e o fingerprint ganhou furo 1 (divisórias), furo 2 (glifo ⇅), furo 6 (posição xnorm) + resumoCampos (a máquina nomeia o padrão dominante). Ela acusa sozinha: bgEfetivo+borderColor 56/57 SISTEMÁTICO = superfície+borda fria, próximo alvo (sweep hardcoded)."
prs: [3939, 3940, 3942, 3943, 3944, 3947, 3948, 3951, 3953]
decided_by: [W]
related_adrs: [0021-primary-dark-clareado-0190]
next_steps:
  - "Sweep surface+border: as bordas do Financeiro são HARDCODED oklch(0.3 0.012 282) (proto quer 0.335) e as superfícies achatadas (proto tem painel 0.238) — grep+troca arquivo-por-arquivo, com o fingerprint validando. NÃO é fix de token (testei 2 hipóteses ao vivo, ambas reprovadas)."
  - "Fila pontual: KPI label warm 240→282, KPI value 28→22, header 10→10.5px, --accent legado 0.55 no dark."
  - "Furos restantes do fingerprint: nº3 (compostos filtrados por childElementCount), nº4 (KPI por chave ambígua <BRL>), nº5 (forçar triagem dos SO_*)."
---

# Handoff — Financeiro fidelidade + fingerprint como mecanismo (furos 1/2/6 + resumoCampos)

## Estado MCP no momento
- **cycles-active:** nenhum cycle ativo em COPI (off-cycle).
- **my-work:** 30 tasks padrão (REVIEW/BLOCKED/TODO) — nenhuma é o trabalho desta sessão (foi ad-hoc, dirigido turn-a-turn pelo Wagner; não tracked como task MCP).
- **decisions:** ADR UI-0021 criada+aceita nesta sessão (emenda à 0190).

## O que aconteceu
Retomei via `/continuar` ("o financeiro ainda não está bom o protocolo"). Virou duas frentes entrelaçadas: **corrigir a tela** e **endurecer o fingerprint** — cada erro que o Wagner apontava virava (a) fix na tela E (b) um furo fechado no mecanismo, pra a máquina pegar sozinha da próxima.

**Fidelidade da tela (Unificado, dark):**
1. **#3940 pills** — period bar (Dia/Semana/Mês…) era segmentado; proto usa pills 999px + ativa roxa cheia. Smoke ✅.
2. **#3942 roxinho** — `--color-primary` dark `0.62→0.7` app-wide (**ADR UI-0021**, emenda à 0190, [W] "app inteiro"). DTCG `tokens:build`. **#3943** regenerou baselines VRT (modo UPDATE, anti-#3297). Smoke ✅ (pill "Mês" = 0.7).
3. **#3947+#3951 alinhamento** — grupo de período era left-packed (folga 1283px); proto right-justify (37px). `ml-auto` sozinho não bastou (grupo não era full-width) → **#3951 `w-full`**. **Testei ao vivo ANTES de deployar** (folga 1283→35px) — evitou 2º ciclo falho. Smoke ✅.

**Fingerprint virou mecanismo (o "protocolo consta tudo"):**
- **#3939 furo 2** — `normTexto` strippa glifos ⇅ que forkavam a chave (header "Vencimento⇅" caía em SO_*). +6 divergências reais recuperadas na captura real.
- **#3944 furo 1** — 2ª passada estrutural captura **divisórias/bordas sem texto** (linha/régua que o vetor de texto era cego).
- **#3948 furo 6** — `xnorm` (posição horizontal): "mesmo elemento em lugar diferente" vira DIVERGE, não IDENTICO mentiroso. Respondeu "por que não pegou o alinhamento?".
- **#3953 resumoCampos** — a máquina NOMEIA o padrão dominante ([W] "quero a máquina pegar o erro"). Selftest 18/18.

## A máquina pegando o erro (payoff)
Rodei o `--compare` real (prod×proto v3) e a ferramenta cuspiu sozinha:
```
bgEfetivo    56/57  ⚠ SISTEMÁTICO
borderColor  56/57  ⚠ SISTEMÁTICO
```
→ **superfície + borda** são o erro dominante (o "retângulo atrás do filtro" + "linhas mais claras/full-width" que o Wagner via). Diagnóstico mecânico, não contagem na mão.

## Artefatos gerados
- **Código:** `Pages/Financeiro/Unificado/{Index.tsx,_components/FinPeriodBar.tsx}` + `Index.casos.md` (bump last_run G-6).
- **Tokens:** `semantic.tokens.json` primary.dark 0.7 + `_generated-inertia-dark.css`.
- **Mecanismo:** `prototipo-ui/style-fingerprint.mjs` (furos 1/2/6 + resumoCampos; selftest 18/18).
- **Canon:** ADR UI-0021.

## Persistência
- **git:** 9 PRs mergeados em main (#3939–#3953); este handoff em PR próprio.
- **MCP:** webhook GitHub→MCP propaga ADR UI-0021 (~2min).
- **BRIEFING:** Financeiro tocado — `brief-update` aplicável na retomada.

## Próximos passos pra retomar
`/continuar` → **sweep surface+border** (o erro que a máquina achou): grep dos `oklch(0.3 0.012 282)` hardcoded em borda → `0.335`, e restaurar superfície `0.238` onde o prod achatou. A máquina (fingerprint v3) já valida.

## Lições catalogadas
- **Bordas/superfícies do Financeiro são HARDCODED, não token-driven** — bumpar `--color-border`/`--border` não muda nada (testado ao vivo 2×). O `.cockpit[data-theme=dark] .fin-cowork` do bundle é **bloco morto** (data-theme está no `<html>`, não no `.cockpit`).
- **Testar a hipótese no browser ANTES de deployar** salvou 3 deploys ruins nesta sessão (w-full incompleto pego pelo smoke; 2 fixes de cor reprovados no teste ao vivo). Disciplina que virou reflexo.
- **Errar o diagnóstico 2× seguidas = sinal de fadiga de sessão** → foi o gatilho honesto pra fechar (R12) em vez de forçar.

## Pointers detalhados
- ADR: [`memory/requisitos/_DesignSystem/adr/ui/0021-primary-dark-clareado-0190.md`](../requisitos/_DesignSystem/adr/ui/0021-primary-dark-clareado-0190.md).
- Mecanismo: [`prototipo-ui/style-fingerprint.mjs`](../../prototipo-ui/style-fingerprint.mjs) header (MATCHING v2, furos 1/2/6).
- Handoff anterior: [2026-07-07 17:46](2026-07-07-1746-financeiro-fidelidade-dark-mecanismos-comparacao.md) (dark WARM UI-0020 + fingerprint v1).
