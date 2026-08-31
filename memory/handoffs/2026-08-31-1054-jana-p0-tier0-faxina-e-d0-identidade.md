---
id: handoff-2026-08-31-1054-jana
title: "Jana — P0 Tier 0 fechado, faxina, e o D0 que impede comparar a tela errada"
type: handoff
date: "2026-08-31"
slug: jana-p0-tier0-faxina-e-d0-identidade
tldr: "P0 Tier 0 de vazamento cross-tenant fechado; faxina de components; D0 que impede comparar a tela errada. Pendencia seria: a 2a porta do conserto e codigo morto."
owner: W
---

# Handoff — Jana: P0 Tier 0, faxina, e o D0 de identidade de view

> Sessão longa (28→31/ago), fechada a pedido de [W] com o trabalho restante
> distribuído em **8 chips** de sessão limpa.

## O que entrou no main

| PR | o quê |
|---|---|
| [#6421](https://github.com/wagnerra23/oimpresso.com/pull/6421) | **P0 Tier 0** — `/ia/superadmin/metas` entregava metas de TODOS os tenants pro dono de qualquer negócio |
| [#6424](https://github.com/wagnerra23/oimpresso.com/pull/6424) | `Pages/Jana/components/` (sem underscore) → canon `_components/` |
| [#6430](https://github.com/wagnerra23/oimpresso.com/pull/6430) | UC-JPERM-08 — `/ia/pro` e o preview admin são duas telas |
| [#6439](https://github.com/wagnerra23/oimpresso.com/pull/6439) | conserto do teste que o #6430 deixou vermelho |

## O P0 — a cadeia, elo a elo

`Gate::before` (`app/Providers/AuthServiceProvider.php:34-47`) devolve `true` em
QUALQUER ability fora de `['backup','superadmin','manage_modules']` pra quem tem
`Admin#{business_id}`. `jana.superadmin` não está nessa allowlist → todo dono de
negócio passava no `abort_unless` e recebia, do `withoutGlobalScope`, as metas de
todos os tenants — com o link visível no menu (`topnav.php:44`).

Duas hipóteses minhas que a medição derrubou, e por isso o conserto ficou local:
`ScopeByBusiness` **não** vaza (lê o próprio business + `NULL`, como o docblock
diz), e o `JanaProController` **já** estava protegido por `user_type`.

## ⚠️ A correção ao próprio conserto — e ela é a pendência mais séria

O #6421 pôs duas portas: `hasPermissionTo` (Spatie direto) **ou** `user_type`
elevado. **A segunda é código morto.** `app/Http/Middleware/CheckUserLogin.php:18`
aborta 403 para qualquer `user_type != 'user'` fora de `/home`, e ele está no
grupo `/ia` (`routes.php:50`). Quem satisfaz o controller é barrado antes.

Provado pelo **corpo da resposta** em duas voltas de CI: HTML = middleware ·
JSON `tenant_violation` = controller. As duas travas se fecham em pinça, e por
isso o preview de outro business é inalcançável para todos.

**Consequência viva:** a única porta é `hasPermissionTo`. Se ninguém tiver
`jana.superadmin` atribuída, `/ia/superadmin/metas` está **inacessível desde
28/ago**. Não consegui verificar — CT 100 em 502 durante toda a sessão.
→ chip "Verificar se /ia/superadmin/metas ficou inacessível".

## O D0 — impede comparar a tela errada (branch pronta, sem PR)

`claude/design-diff-d0-identidade` (`145668ecf5`). `ancora.mjs` responde QUAL
ARQUIVO é a âncora, **nunca QUAL VIEW** — e **38 telas** apontam para âncora
compartilhada (`ponto-telas.jsx` serve 17). O shell do Cowork carrega os
protótipos juntos, então comparar sem provar a view mede a tela errada e devolve
veredito plausível.

Eu caí nisso: medi "3 KPIs no protótipo × 4 na prod" olhando o cockpit de
cobrança em vez do Painel. A **primeira versão do D0 também falhou** — julgava
cada lado isolado, e a view errada passou casando só `"plano Pro"`, copy do
header. O veredito virou **relacional** (assimetria), e o caso `3 × 1` virou
assert permanente. A fixture não pegou; o render real pegou.

## Paridade Painel — a lista, medida nos dois lados

| item | protótipo | prod | destino |
|---|---|---|---|
| abas | 6 | 3 | chips Alertas/Ações/Plataforma |
| título do shell | "Painel" | `title="Jana — Dashboard"` | chip de copy |
| botão Exportar | "Exportar" | `"Exportar relatório (em breve)"` | chip de copy |
| subtítulo análises | "clique num card pra ver de onde vem o número" | contrato pina "Acompanhamento contínuo" | chip de copy |
| KPIs | 3 | 4 (tem "PIX hoje") | chip de KPI |
| card de análise | Cheques | metodos | chip de KPI |

**Não mexer** (o protótipo é que está atrás): título 22px (ADR 0189) e rótulo
"Receita 30 dias" (o dado é de 30 dias deslizantes — `UC-JPAIN-14`).

## Estado MCP no momento do fechamento

⚠️ **Não consultado.** O servidor MCP não respondeu no início da sessão (hook
`brief-fetch` caiu em fallback por timeout) e não foi retentado. Este handoff é
derivado de medição direta em git/gh, não de `cycles-active`/`my-work`. Declarado
porque snapshot ausente ≠ snapshot vazio.

## Aberto

- [#6427](https://github.com/wagnerra23/oimpresso.com/pull/6427) e
  [#6425](https://github.com/wagnerra23/oimpresso.com/pull/6425) — draft, conflitos
  resolvidos; a falha era **herdada** do teste que o #6439 acabou de consertar
- `claude/design-diff-d0-identidade` e `claude/jana-uc-perm-grupo` — pushadas, sem PR
- **CT 100 em 502** desde 28/ago: nenhum Pest rodou local nesta sessão, e os
  baselines `governance/jana-ragas-*.json` estão parados há 61 dias pela mesma causa
  (o RAGAS real roda lá; o canary do CI roda em mock e não escreve baseline)
