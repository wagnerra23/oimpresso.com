---
id: resources-js-pages-comunicacao-visual-aceite-publico-charter
page: /orcamento-cv/{token}
component: resources/js/Pages/ComunicacaoVisual/AceitePublico.tsx
runbook: memory/requisitos/ComunicacaoVisual/RUNBOOK-aceite-publico.md
related_us: [US-COMVIS-023]
related_prototype: n/a (tela pública fora do AppShellV2; segue o padrão de Pages/OficinaAuto/AprovacaoPublica.tsx)
status: draft
---

# Charter — Pages/ComunicacaoVisual/AceitePublico.tsx

## Persona-alvo

Cliente da gráfica (não é usuário do sistema), quase sempre no celular, abrindo o link que a gráfica
mandou. Quer conferir o que vai pagar e responder sem criar conta.

## Objetivo desta página

1. **Conferir** — empresa, número do orçamento, validade, cada item (descrição, medida, quantidade,
   valor) e os totais, com os números gravados no orçamento.
2. **Aceitar** — um botão; o orçamento vira OS na gráfica.
3. **Pedir ajuste** — um texto obrigatório dizendo o que mudar; o orçamento volta para a gráfica.
4. **Saber quando não dá** — link vencido, já usado, orçamento fora da validade ou indisponível
   mostram o motivo e não mostram os botões.

Contrato comportamental: [`AceitePublico.casos.md`](AceitePublico.casos.md).

## Non-Goals

- ❌ Mostrar custo, margem ou dado interno da gráfica.
- ❌ Recalcular valor na página: o total é o gravado.
- ❌ Login, cadastro ou PIN nesta versão.

## Fora desta versão

PDF do orçamento e envio pelo WhatsApp (PRs seguintes da US-COMVIS-023).
