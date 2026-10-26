---
id: resources-js-pages-comunicacao-visual-aceite-publico-casos
casos: Aceite público do orçamento · /orcamento-cv/{token}
irmaos: AceitePublico.charter.md (lei) · AceitePublico.tsx (código) · ../../../../memory/requisitos/ComunicacaoVisual/SPEC.md (US-COMVIS-023)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: o link é o único ponto em que alguém de fora decide sobre um orçamento; o que ele mostra e o que ele aceita não podem mudar sem querer.
owner: wagner
last_run: "2026-10-26"
last_run_ci: "0 UC executado na lane — trio nasce neste PR; CT 100 (cópia isolada do branch) no corpo do PR"
related_us: [US-COMVIS-023]
---

# Casos de Uso & Aceite — Aceite público do orçamento (`/orcamento-cv/{token}`)

> Os UC derivam da US-COMVIS-023 do [SPEC](../../../../memory/requisitos/ComunicacaoVisual/SPEC.md) e do
> charter, **não** do `.tsx` ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05). O serviço por
> trás (`AceiteDoOrcamento`) é provado pelos `UC-CVACE-01/02` do `Index.casos.md`; aqui fica a página.

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-CVACEP-01 | link válido abre a página com itens e total, sem custo, sem indexar | must | US-COMVIS-023 | `OrcamentoAceitePublicoContratoTest` |
| UC-CVACEP-02 | token que não existe dá 404 | must `[T0]` | US-COMVIS-023 | `OrcamentoAceitePublicoContratoTest` |
| UC-CVACEP-03 | aceitar pela página vira OS e a página mostra que foi enviado | must `[V0]` | US-COMVIS-023 | `OrcamentoAceitePublicoContratoTest` |
| UC-CVACEP-04 | pedir ajuste exige texto; responder de novo mostra o motivo | must | US-COMVIS-023 | `OrcamentoAceitePublicoContratoTest` |

## UC-CVACEP-01 · Link válido abre a página com itens e total · `must`

**Dado** um link aberto **Quando** o cliente abre `/orcamento-cv/{token}` **Então** a página
`ComunicacaoVisual/AceitePublico` mostra empresa, número, itens e o total gravado, com `situacao: aberto`,
sem nenhum campo de custo, e a resposta traz `X-Robots-Tag: noindex, nofollow` e
`Referrer-Policy: no-referrer`.

Status: 🧪

## UC-CVACEP-02 · Token que não existe dá 404 · `must` `[T0]`

**Dado** um token de 48 caracteres que não está no banco **Quando** alguém o abre ou posta nele
**Então** a resposta é 404, sem dizer por quê, e nada é gravado.

Status: 🧪

## UC-CVACEP-03 · Aceitar pela página vira OS · `must` `[V0]`

**Dado** um link aberto **Quando** o cliente aceita **Então** a resposta volta para a própria página com
"Orçamento aceito", a OS existe com `valor_total` igual ao total do orçamento, e a página passa a
mostrar o link como respondido.

Status: 🧪

## UC-CVACEP-04 · Pedir ajuste exige texto; responder de novo mostra o motivo · `must`

**Dado** um link aberto **Quando** o cliente pede ajuste sem texto **Então** volta com o erro em
`comentario` e o orçamento não muda. **Quando** manda o texto **Então** o orçamento volta a rascunho.
**Quando** tenta responder de novo **Então** volta com o erro "já foi usado" em `decisao`.

Status: 🧪
