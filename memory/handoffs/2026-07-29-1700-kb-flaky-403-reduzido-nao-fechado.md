---
date: "2026-07-29"
time: "17:00 UTC"
slug: kb-flaky-403-reduzido-nao-fechado
tldr: "O flaky 403 da lane KB foi diagnosticado em parte e REDUZIDO, não eliminado — declarei 'resolvido' e estava errado. kbActAsUser não sincronizava business_id de usuário já existente (users é core, sobrevive ao teardown); corrigido com bite-test. Mas 3 runs do mesmo código deram ✅/❌L3/❌V2b: sobrou vetor sem diagnóstico. 4 hipóteses eliminadas e registradas no PR."
prs: [5032]
decided_by: [W]
next_steps:
  - "Vetor restante do flaky: instrumentar o CI (única réplica fiel — DB fresco + seed + os 25 arquivos). Pista viva: em L3 quebrou o CONTROLE POSITIVO (assertOk no nó do próprio business), não a asserção de isolamento"
  - "CT 100 NÃO serve pra essa investigação: checkout em 2026-07-23 com 20 dos 25 arquivos de teste KB — faltam os que falham (KbNodeBodyReaderTest veio do #5018 às 14:08)"
  - "96 ocorrências de `bizId: 1` em 18 arquivos de teste do KB — a WR2, empresa REAL, em banco que é clone de prod. Zero adoção do seededTenant() que Financeiro e Governance já usam (ADR 0358). Sweep de legado, decisão [W]"
  - "/kb/graph segue fachada (closure sem props; /kb/graph/data hardcoded vazio)"
  - "KbArticleService:49 — integer('category') recebe slug → where('category_id',0) → filtra por zero EM SILÊNCIO"
related_adrs:
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0093-multi-tenant-isolation-tier-0
---

# O flaky foi reduzido, não fechado — e eu disse que estava resolvido

Continuação do [handoff das 14:10](2026-07-29-1410-kb-categoria-classificada-backfill.md). Aquele deixou como próximo passo *"o flaky da lane KB não é meu e vai continuar avermelhando PRs de terceiros"*. Esta sessão atacou isso.

## O que foi encontrado e corrigido

`kbActAsUser` resolvia o usuário com `find($userId)` e só setava `business_id` no ramo de **criação**. A tabela `users` é core e **não** é resetada pelo `kbTeardownSchema`, então o usuário sobrevive entre testes: da 2ª chamada em diante fica preso ao tenant da primeira, enquanto a `session()` afirma o novo.

Sobreviveu porque o helper já tinha **dois** bloqueadores documentados para flaky 403, ambos sobre o registry de **permissões** do Spatie. Nenhum alcança o **tenant do próprio usuário**. Este virou o BLOQUEADOR 3.

**Bite-test provado** (CT 100, MySQL real): 2 vermelhos sem o fix → **6/6 verdes** com ele, incluindo 3 controles negativos e uma guarda de premissa (se os dois ids de tenant colidirem, os bites viram tautologia silenciosa).

## Por que NÃO fechou

3 runs do **mesmo código** — entre dois deles só um `.md` de diferença:

| run | resultado | teste |
|---|---|---|
| 15:20 | ✅ | — |
| 15:52 | ❌ | `KbNodeBodyReaderTest > L3` |
| 16:03 (re-run, zero mudanças) | ❌ | `KbIndexV2ContractTest > V2b` |

Sempre **exatamente 1 failed**, sempre `14 skipped`, teste diferente. Assinatura de flaky — a mesma que usei pra diagnosticar. **Eu havia declarado "resolvido"; publiquei errata no PR e mudei o título pra "REDUZ … (não elimina)".**

## Hipóteses ELIMINADAS (o produto mais útil daqui)

Na stack de `/kb/v2` (`web · SetSessionData · auth · language · timezone · AdminSidebarMenu · CheckUserLogin`) a rota **não tem `can:`** e existe **um único** `abort(403)`: `CheckUserLogin.php:19` (`user_type != 'user' || allow_login != 1`).

| # | hipótese | como caiu |
|---|---|---|
| 1 | Spatie em modo **teams** — o fix trocaria o team e perderia a permissão | `config/permission.php` não tem `teams` |
| 2 | helper cria user sem `user_type`/`allow_login` | defaults do schema corretos: `'user'` / `1` (`mysql-schema.sql:8968,8991`) |
| 3 | seed do CI cria o user 42 mal | cria `ci_admin` por `insertGetId`, id auto-increment, mesmos defaults |
| 4 | `UserFactory` injeta valores | não define nenhum dos dois |

**Pista viva:** em `L3` quebrou o **controle positivo**, não a asserção de isolamento — o autor do teste previu isso (*"403-em-tudo não é isolamento"*). E o `SetSessionData` só reconstrói a sessão quando falta `user` **ou** falta `business_id`; o helper pré-seta ambos, então o middleware **pula** e a sessão fica com o que o helper montou à mão. Mesma guarda que mordeu o #5018 hoje. **Não provado** — é por onde eu continuaria.

## ⚠️ Ressalva sobre a medição do PR

O `32 → 25` (7 consertados, 0 regressões, incluindo `CrossTenantIsolationTest > bridge job`) foi medido no CT 100, cujo checkout está em **2026-07-23 com 20 dos 25** arquivos de teste do KB. **Não cobre os 5 novos.** O bite-test é determinístico e vale; o delta agregado é parcial. Registrado no PR.

## Erros meus catalogados hoje (LC-08)

Quatro conclusões anunciadas com confiança, **todas derrubadas pela medição seguinte**:

1. *"falta o classificador"* → existia há 12 dias (#4465)
2. *"V5 contamina V4 empurrando o user pra 99"* → o inverso; o user já estava em 1 e nunca ia pra 99
3. *"o #5008 quebrou Compras/Estoque/Ponto"* → lanes com `paths-filter` só rodam quando o PR toca os paths delas; comparei populações diferentes, e os testes são failing-first por desenho
4. *"o flaky está resolvido"* → reduzido; o re-run derrubou em 20 min

As 3 primeiras eu mesmo derrubei em minutos. **O trabalho que sobreviveu foi inteiramente o que tinha recibo.**

## Estado MCP no fechamento

- `cycles-active` → nenhum cycle ATIVO em COPI
- PRs desta sessão, **todos MERGED**: #5017 (13:06) · #5021 (13:38) · #5025 (14:27) · **#5032 (16:02)**
- O #5032 mergeou **com a lane KB vermelha** — decisão [W], coerente: reduz falha medida, não regride, e o vermelho é o próprio flaky que ele não fecha
