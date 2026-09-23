---
sessao: "11"
titulo: Configurações — prefixos e notificações
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Configuracoes/ · AssetSettingsController
nao_toca: _shared/ · Config/retention.php (é da thread 05, barrada)
depende: — (fundação no main)
---
> ⚠️ **DESTRAVADA em 2026-09-08 [CL] — a fundação existe; `depende: 07` caiu.**
> O `_shared/PatrimonioSubNav.tsx` está no `main` desde o PR
> [#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035) (17:42Z), fundado pela tela
> de **Bens** e não pela 07, porque [W] reordenou a frente. Esta thread **importa** o SubNav;
> ele segue `nao_toca`.
>
> **Quatro coisas medidas que você herda — não redescubra:**
> 1. **Escreva o charter ANTES do `.tsx`.** O hook `block-mwart-violation` deriva o RUNBOOK
>    do nome da pasta de `Pages/` (`Patrimonio` ⇒ `memory/requisitos/Patrimonio/`, que não
>    existe **nem deve** — o dono é `AssetManagement/`) e **bloqueia sem override**. A saída
>    prevista pelo próprio hook é `related_runbook:` no charter, apontando pro RUNBOOK real.
> 2. **O SubNav DERIVA as abas** de `shell.menu` (`DataController::modifyAdminMenu`). Não
>    declare lista de abas — seria um segundo dono, que droga no primeiro rename.
> 3. **São 6 ghosts vivos, não as 7 do protótipo:** tem *Devoluções*, não tem
>    *Garantias*/*Auditoria* (decisão ABERTA do [W] — §6, itens 4 e 5).
> 4. **`PAGES_NS` já declara `Patrimonio → AssetManagement`** (`module-surface.mjs`), pelas 7
>    telas de uma vez. Ao acrescentar uma tela, **regenere o derivado**
>    (`node scripts/governance/module-surface.mjs AssetManagement --write`) — o gate roda
>    DOIS modos (`--namespaces --check` e `--all --check`) e o segundo cobra o `SUPERFICIE.md`.
>
> Modelo de referência (charter + casos + teste de contrato, com os UC citados por `it()`):
> `Bens.charter.md` · `Bens.casos.md` · `BensContratoTest.php`. Recibos em
> [`_saida-06-bens.md`](_saida-06-bens.md).

# 11 · Configurações — prefixos e notificações

## ÂNCORA (congelada — remedir se o sha mudou)
```
rota      Route::resource('settings', as: 'asset.')  ->  AssetSettingsController   7.122 B  sha 21fe88a55030
views     views/settings/ (3: index, notification_settings, prefix_settings)
proto     patrimonio-page.jsx  aba "Config"
```

## A · O alvo
A menor das cinco. Duas seções: prefixo de código e notificações por e-mail.

## B · Não inventar
- **Não toque em `Config/retention.php`.** Ele pertence à thread 05, BARRADA pela lápide §5 de
  2026-07-27 (num ERP não se apaga PII). E as tabelas que ele declara (`am_assets`,
  `am_maintenance_logs`) **não existem** — migration nenhuma as cria.

## Execução
```
PASSO  1) confirmar 07 mergeada
       2) RUNBOOK + charter + casos
       3) Inertia nas 3 views
       4) _saida-06e.md
PARAR SE alguma config apontar para tabela inexistente -> declare; NAO crie a tabela
```

## Checklist de saída
1. 07 mergeada · 2. charter + casos · 3. 3 views em Inertia · 4. `retention.php` intocado · 5. 9 Pest verdes · 6. placar
