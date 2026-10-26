---
last_validated: "2026-10-26"
slug: runbook-comunicacao-visual-aceite-publico
title: "RUNBOOK — /orcamento-cv/{token} (cliente aceita o orçamento)"
type: runbook
module: ComunicacaoVisual
page: /orcamento-cv/{token}
component: resources/js/Pages/ComunicacaoVisual/AceitePublico.tsx
status: rascunho
updated_at: 2026-10-26
version: 0.1
owner: W
---

# RUNBOOK — /orcamento-cv/{token} (cliente aceita o orçamento)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para tela
> **nova** e **pública**, sem Blade anterior. Charter:
> [`AceitePublico.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/AceitePublico.charter.md) · casos:
> [`AceitePublico.casos.md`](../../../resources/js/Pages/ComunicacaoVisual/AceitePublico.casos.md) · SPEC: US-COMVIS-023.

## 1. Estrutura

- `GET /orcamento-cv/{token}` → `Public\OrcamentoAceitePublicoController@mostrar` →
  `Inertia::render('ComunicacaoVisual/AceitePublico')`. Sem login, fora do AppShellV2.
- `POST /orcamento-cv/{token}/aceitar` e `POST /orcamento-cv/{token}/ajuste` → `AceiteDoOrcamento::decidir`
  (backend do PR anterior da US-COMVIS-023). Depois da decisão, redireciona para a própria página com a
  mensagem de sucesso; recusa volta com o erro em `decisao`.
- A página não calcula nada: mostra os números gravados no orçamento (`paraCliente`).
- Token com 48 caracteres alfanuméricos (`where` na rota); desconhecido = 404, sem dizer por quê.
- Cabeçalhos `X-Robots-Tag: noindex, nofollow` e `Referrer-Policy: no-referrer`. Por causa do
  `no-referrer`, o controller nunca usa `back()`: o navegador não manda o Referer.

## 2. Fonte de design

Sem protótipo próprio: a tela segue a aprovação pública da oficina
(`Pages/OficinaAuto/AprovacaoPublica.tsx`) — cartão central de no máximo `max-w-xl`, componentes do DS
(`Alert`, `Button`, `Textarea`), tokens canônicos, sem cor fixa.

## 3. Smoke pós-deploy

```bash
curl -sv https://oimpresso.com/orcamento-cv/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA 2>&1 | grep '^< HTTP'
# esperado: < HTTP/1.1 404 Not Found  (token que não existe)
```

Com um link gerado de um orçamento de teste: abrir no celular, conferir itens e total, aceitar, ver
a mensagem de sucesso e a OS criada na calculadora.
