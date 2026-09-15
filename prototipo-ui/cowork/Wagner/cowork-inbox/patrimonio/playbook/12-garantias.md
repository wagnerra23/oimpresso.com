---
sessao: "12"
titulo: Garantias — tela nova sobre dado que já existe
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Garantias/ · rota nova em Routes/web.php · controller novo
nao_toca: _shared/ · os 5 controllers existentes
depende: **07** — e a decisão [W] 4 (tela própria ou filtro de Bens?)
---
# 12 · Garantias — tela nova sobre dado que já existe

## ÂNCORA (congelada — remedir se o sha mudou)
```
tabela    asset_warranties          migration EXISTE (1 no repo)
rota      NAO EXISTE — esta thread cria
proto     patrimonio-page.jsx  aba "Garantias"  (5 itens no mock)
consumo   AssetController:482-:497 ja le asset_warranties no dashboard ($expiring_assets)
```

## A · O alvo
**Não é construção do zero.** A tabela `asset_warranties` existe e já é lida pelo `dashboard()`. O que
falta é rota, controller e tela — a capacidade está no banco, sem porta.

⚠️ **Decisão [W] 4 em aberto**: Garantias é **tela própria** ou **filtro da tela de Bens**? O protótipo
mostra aba própria; o backend só tem a leitura embutida no dashboard. **Se [W] não tiver decidido
quando esta thread abrir, PARE** — construir a tela errada custa refazer.

## B · Não inventar
- Não crie coluna nova em `asset_warranties`. Se faltar campo para o desenho, declare.
- O `dashboard()` já tem a query de garantia vencendo (`:482-:497`, corrigida no PR #7018) — **reuse**,
  não escreva uma segunda contagem. Duas fontes para o mesmo número é como o bug renasce.

## Execução
```
PASSO  0) CONFIRMAR a decisao [W] 4. Sem ela, PARE.
       1) confirmar 07 mergeada
       2) RUNBOOK + charter + casos
       3) rota + controller + Inertia
       4) _saida-06f.md
PARAR SE a decisao 4 nao existir, ou se o desenho exigir campo que a tabela nao tem
```

## Checklist de saída
1. decisão [W] 4 confirmada · 2. 07 mergeada · 3. charter + casos · 4. rota + controller · 5. query de garantia REUSADA do dashboard · 6. 9 Pest verdes · 7. placar
