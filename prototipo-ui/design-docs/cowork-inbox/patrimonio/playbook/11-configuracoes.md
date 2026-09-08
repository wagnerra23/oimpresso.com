---
sessao: "11"
titulo: Configurações — prefixos e notificações
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Configuracoes/ · AssetSettingsController
nao_toca: _shared/ · Config/retention.php (é da thread 05, barrada)
depende: **07**
---
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
