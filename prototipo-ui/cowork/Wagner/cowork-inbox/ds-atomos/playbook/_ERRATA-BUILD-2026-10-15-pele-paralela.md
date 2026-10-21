---
sessao: "errata-build"
titulo: "Handoff (55) — dois segmented próprios nos portais de CRM e Repair (pele paralela R2)"
executor: "[CC]"
data: 2026-10-15
base: wagnerra23/oimpresso.com@main b5f2d68c6c
---
# Errata do build (pedido ao Cowork)

O Code não edita o build no espelho: ele é cópia do projeto Cowork e o próximo retorno desfaria
qualquer remendo. A correção nasce aí e desce no próximo handoff. Este arquivo não é recibo.

## O que o guard acusou

`node scripts/qa/cowork-pele-paralela.mjs`, no PR que importou o handoff (55):

```
2 PELE PARALELA NOVA (nao esta no baseline):
  R2  .cp-seg   segmented proprio em 2 arquivo(s): crm-portal.css, crm-portal.jsx
  R2  .rpl-seg  segmented proprio em 2 arquivo(s): repair-page.css, repair-portal.jsx
```

## Onde

- `crm-portal.jsx:330` e `:334` — `<span className="cp-seg" role="group">` para "Tamanho da prévia" e "Tema".
- `repair-portal.jsx:120`, `:124` e `:140` — `<span className="rpl-seg" role="group">` para "Tamanho da prévia", "Tema" e "Como buscar".

## A correção

Trocar os dois por `window.CliSeg` (o dono do segmented no build; uso de referência em
`cliente-drawer760.jsx:208`) e apagar `.cp-seg` / `.rpl-seg` dos CSS. "Como buscar" é escolha
única, então leva `role="radiogroup"` como no exemplo.

Se algum dos dois não for escolha única (é toggle, paginador ou outra coisa), responda aqui com o
motivo: aí ele vira waiver com motivo escrito no guard, como o `R2:hrm`.
