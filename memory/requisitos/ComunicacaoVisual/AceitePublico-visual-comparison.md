---
id: requisitos-comunicacaovisual-aceitepublico-visual-comparison
title: "Comparação design × produção — ComunicacaoVisual/AceitePublico"
module: ComunicacaoVisual
tela: ComunicacaoVisual/AceitePublico
owner: W
status: rascunho
last_validated: "2026-11-11"
---

# Comparação design × produção — `ComunicacaoVisual/AceitePublico`

> **Âncora computada** (`node scripts/design/ancora.mjs ComunicacaoVisual/AceitePublico`):
> **sem âncora**, `related_prototype: n/a (tela pública fora do AppShellV2; segue o padrão de
> Pages/OficinaAuto/AprovacaoPublica.tsx)`. Declaração legítima: não há protótipo desta tela.
>
> Primeiro registro desta tela. Lote 4 da auditoria do módulo (achados A7 e A9).

## Comparação com o protótipo: **não se aplica**

Sem âncora, não há lado de design para a sonda. O `vendas-orcamento-aprovacao-page.jsx` do
espelho é a aprovação pública de orçamento de **venda**, outra tela (auditoria 2026-11-05, A4).

## Smoke em produção (2026-11-11, biz=1)

**Não há link de aceite válido de biz=1 para abrir.** O "Orçamentos salvos" do hub de biz=1 diz
*"Nenhum orçamento salvo ainda"*, e o banco guarda só o sha256 do token
(`AceiteDoOrcamento::hash`), então não existe link a recuperar. Criar um exigiria gravar
orçamento e gerar link em produção (o orçamento muda para `enviado`); isso não foi feito.

O que foi medido, sem login (`fetch` com `credentials: 'omit'`):

| Pedido | Status | Corpo |
|---|---|---|
| `GET /orcamento-cv/` + 48 caracteres que não são token | **404** | página de erro de 6,5 KB, sem `data-page` do Inertia e sem número nem valor de orçamento |
| `GET /orcamento-cv/curto` (fora do padrão `[A-Za-z0-9]{48}`) | **404** | idem |

Isso confirma o UC-CVACEP-02 em produção: token desconhecido não revela nada.

## Onde está a prova do render

O render da página com link válido, o aceite virando OS e o axe estão no E2E do lote 4
(`tests/Browser/ComunicacaoVisual/ComunicacaoVisualTelasBrowserTest.php`, UC-CVACEP-01/02/03),
no tenant 98, com o orçamento criado e apagado pelo próprio teste.

## Não medido

- Página com link válido em produção (motivo acima).
- Os cabeçalhos `X-Robots-Tag` e `Referrer-Policy` vêm só na resposta da página válida
  (`OrcamentoAceitePublicoController::pagina`); no 404 eles não aparecem, e a página válida não
  foi aberta.
