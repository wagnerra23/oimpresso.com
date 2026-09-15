---
sessao: "05"
titulo: Job de retenção LGPD — assetmanagement:retention-purge (nasce desligado)
dono: "[CL]"
base: cb475c0ca2f4
constituicao: CONSTITUICAO-COWORK.md (C1–C12)
prefixo: Modules/AssetManagement/Console/Commands/ · Config/retention.php · Tests/Feature/LgpdComplianceTest.php
nao_toca: Services/** · Http/** · resources/js/**
depende: — (vaga 2). D-CANARY-LGPD decide QUANDO ligar, não se o código nasce.
antes:  retention.php declara janelas com enabled=false; nenhum executor
depois: comando existe, testado, e continua enabled=false até [W] ligar
---
# 05 · Retenção LGPD

## ÂNCORA
```
arquivo  Modules/AssetManagement/Config/retention.php                              3.391 B  sha 6d4af578e839
arquivo  Modules/AssetManagement/Console/Commands/AssetManagementHealthCommand.php 9.936 B  sha e2cccc1c4b55
         ↑ NÃO é o alvo: é o MOLDE. O módulo já tem um comando registrado —
           copiar a forma dele (assinatura, registro no provider, saída, teste).
arquivo  Modules/AssetManagement/Tests/Feature/LgpdComplianceTest.php              7.537 B  sha 160b008925a8
NÃO ler  os controllers · as views · os Services
```

## A · O estado
`retention.php` declara as janelas de retenção e está com `enabled=false`. **Não há executor** — a política existe no papel e nada a cumpre. Como o módulo já tem `AssetManagementHealthCommand` registrado, o caminho de comando está pavimentado: não é infraestrutura nova, é um segundo comando no mesmo padrão.

## B · Leis que ESTA thread pode violar (repetidas aqui de propósito — §13.6.1)
1. **A trilha de auditoria nunca é purgada**, mesmo quando o dado-fonte é anonimizado — está escrito no próprio `retention.php`. Um purge que apague `activity_log` é o pior defeito possível nesta thread.
2. **Append-only:** `asset_transactions` não sofre `DELETE`. Retenção sobre transação é **anonimização de campo pessoal**, nunca remoção de linha.
3. **Nasce desligado.** `enabled=false` permanece; ligar é [W] (D-CANARY-LGPD).
4. **Multi-tenant (Tier 0):** o purge roda **por empresa**, jamais em varredura global.

## Execução
```
PASSO   1) ler retention.php inteiro (3,4 KB) — as janelas são o contrato
        2) ler a FORMA do AssetManagementHealthCommand (assinatura + registro
           no ServiceProvider + como o teste dele monta)
        3) escrever o comando: --dry-run PADRÃO, --business-id obrigatório,
           relatório do que SERIA feito
        4) testes no LgpdComplianceTest: (a) dry-run não altera nada
           (b) trilha de auditoria intacta (c) transação anonimizada, não
           deletada (d) empresa B intocada quando roda em A
        5) manter enabled=false e dizer isso no corpo do PR
        6) _saida-05.md
PARAR SE (a) cumprir a janela exigir DELETE em asset_transactions → PARE: o
             conflito entre retenção e append-only é decisão [W], não código
         (b) alguma janela do retention.php não tiver campo correspondente no
             schema → declare; não invente coluna (C7)
```

## Checklist de saída
1. comando registrado · 2. `--dry-run` é o padrão · 3. os 4 testes · 4. trilha de auditoria intacta (provado por teste) · 5. `enabled=false` mantido · 6. escopo por `business_id` · 7. placar no PR
