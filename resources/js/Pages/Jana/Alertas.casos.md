---
id: resources-js-pages-jana-alertas-casos
casos: Jana Alertas · desvios de meta · conta server-side · aba da área · /ia/alertas
irmaos: Alertas.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-alertas.md (runbook) · prototipo-ui/contrato/jana-alertas.contract.json (contrato visual)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-02"
---

# Casos de uso — /ia/alertas (aba Alertas da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do `Alertas.charter.md` (§Goals/§Anti-hooks), do `jana-alertas.contract.json` e da
> âncora (`node prototipo-ui/ancora.mjs Jana/Alertas` → `jana-telas-novas.jsx` §`JmAlertas`) —
> **não** do `Alertas.tsx`. Derivar do código seria tautológico (§5 2026-06-05).
>
> ⚠️ A âncora usa dados FIXOS (`JTN_ALERTAS`, 6 metas do Martinho) e guarda config/silêncio em
> `localStorage`. O que ela diz sobre **forma** vale; o que ela diz sobre **fonte** não — a fonte
> é `AlertaService::calcular`, e o que o servidor não honra fica fora (charter §Anti-hooks).

## UC-ALERTA-00 — A aba existe na barra da área e leva a `/ia/alertas`
Status: 🧪 (`AlertasContratoTest` — aguarda run verde na lane MySQL)

**Persona:** Larissa abre a área Jana e vê **Alertas** como 3ª aba (Painel · Conversa · Alertas ·
Memória), sem digitar URL.

**Aceite:** Dado usuário com `jana.access` · Quando abre `/ia/alertas` · Então o `shell.menu` do grupo
`ia` traz o ghost `{key: alertas, label: Alertas, href: /ia/alertas}` na 3ª posição, entre `copiloto`
e `memorias`, e a página responde 200 com o componente `Jana/Alertas`.

**Regressão que defende:** ghost removido "por limpeza" — a tela seguiria respondendo 200 e ninguém
a alcançaria (o defeito que tirou o ghost `metas` em 2026-05-23 era o inverso: aba pra Blade).

## UC-ALERTA-01 — A lista mostra o desvio que o SERVIDOR calculou, e só o que dispara
Status: 🧪 (`AlertasContratoTest`)

**Aceite:** Dado uma meta ativa do business com período vigente e apuração · Quando abro
`/ia/alertas` · Então a prop `alertas` traz a linha com `projetado`, `realizado`, `desvio_pct`,
`severidade` e `dispara` **iguais** ao `AlertaService::calcular` daquela meta, e `corte` é o
`config('copiloto.alertas.desvio_threshold_default')`. Meta sem apuração **não** vira linha.

**Regressão que defende:** frontend recalculando (a lista e o sino discordariam no 1º ajuste), e
meta sem base virando alerta de zero.

## UC-ALERTA-02 — Escopo `business_id` da sessão (Tier 0)
Status: 🧪 (`AlertasContratoTest`)

**Aceite:** Dado uma meta com desvio em OUTRO business · Quando abro `/ia/alertas` · Então ela não
está em `alertas` — mesmo recorte do Painel (`business_id` da sessão ou `NULL`, ADR 0093).

## UC-ALERTA-03 — Copy e ordem do contrato estão na tela
Status: 🧪 (`AlertasContratoTest` — asserção de ARQUIVO sobre o `alvo` do contrato)

**Aceite:** toda seção do `jana-alertas.contract.json` tem âncora `data-contract` no alvo, toda copy
pinada está presente, e a ordem das âncoras no fonte respeita `ordem`.

**Regressão que defende:** paráfrase da copy da âncora ("Nenhum alerta" no lugar de "Nenhum desvio
acima do corte"), e seção que some sem sair do contrato.

## UC-ALERTA-04 — Severidade é múltiplo do corte (1× baixa · 1,5× média · 3× alta)
Status: 🧪 (`AlertasContratoTest` — unidade sobre `AlertaService::calcular`, fixture em memória)

**Aceite:** com corte 10: desvio −12% → `baixa`, dispara; −15% → `media`; −30% → `alta`; +90% →
`alta` (superar também alerta); −8% → `dispara=false`; sem período ou sem apuração → `null`.

**Regressão que defende:** o refactor `avaliar()` → `calcular()` mudar a fórmula em silêncio.

## Backlog de casos (sem id — entram quando tiverem teste)

- **[BACKLOG]** Drawer de config com os campos do `UpdateAlertasConfigRequest` + aviso de topo da
  âncora — PR próprio; persistência é a US-COPI-061.
- **[BACKLOG]** Contador `n` na aba (`nAlertas` da âncora) — nasce no `DataController`, afeta as 4
  telas (Index-visual-comparison §R2).

## Trilha do tempo
- 2026-09-02 · trio nascido junto (charter + casos + Pest + e2e stub + contrato). Refs: UI-0013 · ADR 0264 G-1/G-2.
