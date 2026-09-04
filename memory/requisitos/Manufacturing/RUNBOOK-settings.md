---
last_validated: "2026-09-03"
slug: runbook-manufacturing-settings
title: "RUNBOOK — /manufacturing/v2/settings (Fabricação · Configurações)"
type: runbook
module: Manufacturing
page: /manufacturing/v2/settings
component: resources/js/Pages/Manufacturing/Settings.tsx
status: rascunho
updated_at: 2026-09-03
version: 0.1
owner: F
---

# RUNBOOK — `/manufacturing/v2/settings` (Fabricação · Configurações)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> US-MANU-003 (SPEC.md) — terceira onda da família Fabricação, ordem de custo crescente
> decidida por [M] 2026-09-02. Fonte visual:
> `prototipo-ui/cowork/manufacturing-producao.jsx::MfgConfig`.
>
> **Primeira tela da família que ESCREVE.** Backend não muda: `SettingsController@store`
> já existe, já escreve as 3 chaves certas (`ref_no_prefix`, `disable_editing_ingredient_qty`,
> `enable_updating_product_price`) scoped por `business_id`, e já devolve `redirect()->back()`
> — compatível com Inertia sem alteração (o client segue o redirect e re-busca as props da
> página atual). Só o `index()` ganha uma variante Inertia (`indexV2`); `store()` é reusado tal
> qual.
>
> **Rota nova, sem cutover** — mesmo padrão de `/v2/report` e `/v2/production`:
> `/manufacturing/v2/settings` novo, `/manufacturing/settings` (Blade) intocado.

## 1. O que NÃO entra (declarado, não esquecido)

- **Cartão "Permissões (simulação)"** — é ferramenta do PRÓPRIO protótipo (chips que ligam/desligam
  permissão fake pra testar telas). Não existe equivalente real na app; SPEC.md DoD exclui
  explicitamente.
- **Escrita nova no backend** — `store()` não muda. Se o form mandar uma chave que o controller
  não lê, ela é ignorada (`$request->only(['ref_no_prefix'])` + os 2 booleanos lidos por nome) —
  sem risco de escrever campo espúrio.

## 2. Estrutura de arquivos

| Papel | Arquivo |
|---|---|
| Tela | `resources/js/Pages/Manufacturing/Settings.tsx` |
| Charter | `resources/js/Pages/Manufacturing/Settings.charter.md` |
| Casos | `resources/js/Pages/Manufacturing/Settings.casos.md` |
| Controller (GET novo) | `Modules/Manufacturing/Http/Controllers/SettingsController@indexV2` |
| Controller (POST reusado) | `Modules/Manufacturing/Http/Controllers/SettingsController@store` (sem mudança) |
| Fonte de design | `prototipo-ui/cowork/manufacturing-producao.jsx::MfgConfig` |
| Teste | `Modules/Manufacturing/Tests/Feature/Wave31SettingsInertiaTest.php` |

## 3. Smoke prod (R1)

```bash
curl -sv https://oimpresso.com/manufacturing/v2/settings 2>&1 | grep '^< HTTP'
```

Regressão adjacente:

```bash
curl -sv https://oimpresso.com/manufacturing/settings 2>&1 | grep '^< HTTP'
```

Depois do 200, screenshot obrigatório + **testar o submit de verdade** (mudar o prefixo,
clicar Atualizar, confirmar que persistiu) — é a primeira tela da família que escreve.

## 4. Rollback

Rota aditiva — reverter o PR remove só a rota nova; `/manufacturing/settings` nunca saiu do ar.
Sem migration. `store()` não foi alterado, então não há risco de regressão na escrita mesmo
que o front novo tenha bug — o pior caso é o formulário novo não funcionar, não gravar errado
(o controller ignora qualquer campo que não seja um dos 3 esperados).
