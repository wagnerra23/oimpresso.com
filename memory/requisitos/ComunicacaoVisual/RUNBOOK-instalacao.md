---
last_validated: "2026-11-18"
slug: runbook-comunicacao-visual-instalacao
title: "RUNBOOK — /comunicacao-visual/instalacoes/{id} (Instalação no celular)"
type: runbook
module: ComunicacaoVisual
page: /comunicacao-visual/instalacoes/{id}
component: resources/js/Pages/ComunicacaoVisual/Instalacao.tsx
status: rascunho
updated_at: 2026-11-18
version: 0.1
owner: W
---

# RUNBOOK — /comunicacao-visual/instalacoes/{id} (Instalação no celular)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para tela
> **nova**, sem Blade anterior: o backend nasceu como API em US-COMVIS-007 (comprovante da instalação,
> UC-CVINST-11..14). Charter: [`Instalacao.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Instalacao.charter.md) ·
> casos: [`Instalacao.casos.md`](../../../resources/js/Pages/ComunicacaoVisual/Instalacao.casos.md) · SPEC: US-COMVIS-007.

## 1. Estrutura

- Rota `GET /comunicacao-visual/instalacoes/{id}` → `InstalacaoCampoController@show` →
  `Inertia::render('ComunicacaoVisual/Instalacao')`. Só lê.
- Foto, assinatura e concluir vão pelos `POST /comunicacao-visual/api/instalacoes/{id}/fotos|assinatura|concluir`
  que já existiam (`InstalacaoComprovanteController`). A tela não reimplementa regra: mostra a recusa 422 da API;
  no sucesso recarrega só `comprovante` (e também `instalacao` ao concluir, porque o estado muda).
- O quadro de assinatura é `Components/shared/QuadroAssinatura` (só desenha e devolve o PNG).
- Protocolo: `GET /comunicacao-visual/instalacoes/{id}/protocolo` → `InstalacaoCampoController@protocolo` →
  `ProtocoloInstalacao` (dompdf, view `comunicacao-visual::pdf.protocolo-instalacao`). Só de instalação concluída
  (senão 422). Fotos e assinatura vêm do Arquivos por `AnexosDoApp::conteudo` e entram como data URI; foto
  maior que 1200 px é reduzida; HEIC vira nota no lugar da foto.
- As três props são eager: é uma instalação só, e no celular a tela inteira precisa estar no primeiro paint.
- Endereço = o do cliente da OS (`contacts.address_line_1/2`, `city`, `state`), pelo orçamento que virou a OS.
  Sem cliente ou sem endereço, a tela diz que falta.

## 2. Âncoras de contrato

| âncora | elemento |
|---|---|
| `cabecalho` | `<div>` em volta do `PageHeader` |
| `instalacao` | `<Card>` com dia e hora, cliente, endereço, mapa, equipe e veículo |
| `checklist` | `<Card>` "Comprovante" com foto antes, foto depois e assinatura |
| `assinatura` | quadro, nome de quem assinou, aceite e "Salvar assinatura" (só enquanto pendente) |
| `concluir` | botão "Concluir instalação" e o aviso do que falta |
| `concluida` | alerta "Instalação concluída" |
| `erro` | alerta "Não enviado" com a mensagem da API |

## 3. Âncora de design

A tela "Instalação" dentro do quadro de celular em `prototipo-ui/cowork/Wagner/producao-cv-campo.jsx`:
cabeçalho com OS e dia/hora, cliente em destaque, endereço com "Abrir no mapa" e o checklist. Desta tela só
entram os três itens que o comprovante grava (foto antes, foto depois, assinatura); os itens de EPI, nível e
vedação do protótipo não têm onde ser gravados e ficam fora.

## 4. Verificação

- Pest: `Modules/ComunicacaoVisual/Tests/Feature/InstalacaoTelaContratoTest.php` e `InstalacaoProtocoloContratoTest.php`
  (tenant 98 × 99, MySQL; no CI e no CT 100).
- Vitest: `tests/js/cv-instalacao.test.tsx` (envio da foto e a recusa visível).
- Smoke: abrir `/comunicacao-visual/instalacoes/{id}` no celular pela Agenda, tirar as duas fotos, colher a
  assinatura e concluir; o Concluir só libera no fim.
