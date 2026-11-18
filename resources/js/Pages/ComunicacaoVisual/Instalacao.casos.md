---
id: resources-js-pages-comunicacao-visual-instalacao-casos
casos: Instalação no celular · /comunicacao-visual/instalacoes/{id}
irmaos: Instalacao.charter.md (lei) · Instalacao.tsx (código)
tecnica: Caso de uso = narrativa do instalador + critério de aceite verificável (Dado/Quando/Então)
por_que: o instalador só deixa o comprovante se a tela abrir a instalação certa e se a foto recusada não parecer enviada; instalação ou cliente de outra empresa na tela é vazamento.
owner: wagner
last_run: "2026-11-18"
last_run_ci: "2026-11-18 — nasce com a tela; veredito vem do CI do PR (Pest ComunicacaoVisual MySQL e vitest)."
---

# Casos de Uso & Aceite — Instalação no celular (`/comunicacao-visual/instalacoes/{id}`)

> **Âncora:** SPEC [US-COMVIS-007](../../../../memory/requisitos/ComunicacaoVisual/SPEC.md) — "Comprovante
> instalação: foto + assinatura digital" — e o UC-PCV-25 do protótipo da Produção. A regra do que o
> comprovante aceita é da API e está em [`Index.casos.md`](Index.casos.md) UC-CVINST-11..14; aqui fica só o
> que é da tela.

> Sem impacto em valor ou estoque.

| UC | Caso | Prioridade | Origem | Teste | Status |
|---|---|---|---|---|---|
| UC-CVCEL-01 | A tela mostra OS, cliente, endereço, horário, equipe e o que o comprovante já tem | must | US-COMVIS-007 · UC-PCV-25 | `InstalacaoTelaContratoTest` | 🧪 |
| UC-CVCEL-02 | Instalação de outra empresa é não encontrada | must `[T0]` | US-COMVIS-007 | `InstalacaoTelaContratoTest` | 🧪 |
| UC-CVCEL-03 | Sem permissão não abre; quem só vê abre sem poder registrar | must | US-COMVIS-007 | `InstalacaoTelaContratoTest` | 🧪 |
| UC-CVCEL-04 | A foto vai para a API; enviada recarrega o comprovante, recusada aparece no alerta | must | US-COMVIS-007 · UC-PCV-25 | `cv-instalacao.test.tsx` | 🧪 |

## UC-CVCEL-01 · A tela mostra OS, cliente, endereço, horário, equipe e o que o comprovante já tem · `must`

**Dado** uma instalação agendada de 19/11/2026 09:00 a 11:00, de uma OS cujo orçamento é de um cliente com
endereço, e a foto antes já enviada **Quando** o instalador abre a instalação **Então** a tela traz a OS, o
nome e o endereço do cliente, o horário, a equipe, foto antes marcada, foto depois e assinatura pendentes.

Status: 🧪

## UC-CVCEL-02 · Instalação de outra empresa é não encontrada · `must` `[T0]`

**Quando** o instalador abre o endereço de uma instalação da empresa 99 **Então** 404, e nada dela aparece.
Controle: a da própria empresa abre.

Status: 🧪

## UC-CVCEL-03 · Sem permissão não abre; quem só vê abre sem poder registrar · `must`

**Quando** quem não tem permissão da CV abre a instalação **Então** 403. Com `comvis.os.view` abre e não pode
registrar; com `comvis.os.update_status` pode registrar.

Status: 🧪

## UC-CVCEL-04 · A foto vai para a API; enviada recarrega o comprovante, recusada aparece no alerta · `must`

**Quando** o instalador escolhe a foto antes **Então** ela vai para `POST …/instalacoes/{id}/fotos` com o tipo
`foto_antes`; com 201 a tela recarrega só o comprovante. Com 422 "A instalação está concluída e não recebe mais
comprovante." aparece o alerta "Não enviado" com essa mensagem e nada recarrega.

Status: 🧪
