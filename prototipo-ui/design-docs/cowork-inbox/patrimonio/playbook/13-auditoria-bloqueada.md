---
sessao: "13"
titulo: Auditoria — BLOQUEADA (decisão [W] 5)
dono: "[W]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: — (nenhum)
nao_toca: tudo
depende: decisão [W] 5
---
# 13 · Auditoria — BLOQUEADA (decisão [W] 5)

## ÂNCORA (congelada — remedir se o sha mudou)
```
tabela    activity_log    NAO tem migration no repo (vem do pacote spatie/laravel-activitylog)
modulo    Modules/Auditoria JA EXISTE, com telas proprias e RevertService
rota      NAO EXISTE
proto     patrimonio-page.jsx  aba "Auditoria"  (5 itens no mock)
```

## A · O alvo
A pergunta que trava: **a Auditoria do Patrimônio é aba deste módulo, ou pertence ao
`Modules/Auditoria`?** (RESÍDUO item 5).

O `Modules/Auditoria` já existe e já é o dono da trilha por-registro — duplicar aqui cria dois donos
do mesmo tema, que é a LC-19. Mas o protótipo desenhou a aba dentro do Patrimônio.

Enquanto [W] não decidir, esta thread **não é pendência do Code**.

## B · Não inventar
- Não crie tabela de auditoria própria do Patrimônio. `activity_log` já existe e é append-only.
- Não replique o `RevertService` do `Modules/Auditoria`.

## Execução
```
BLOQUEADA. Respondida a decisao 5, esta thread vira:
  (a) aba do Patrimonio lendo activity_log filtrado por subject_type=Asset, OU
  (b) deep-link para a tela do Modules/Auditoria ja filtrada — e ai nao ha tela a construir.
A opcao (b) e mais barata e nao cria segundo dono.
```

## Checklist de saída
— (bloqueada)
