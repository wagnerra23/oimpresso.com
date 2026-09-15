---
id: cowork-inbox-fiscal-dfe-charter
page: /fiscal/dfe
component: resources/js/Pages/Fiscal/Dfe.tsx (alvo da tradução)
related_prototype: "F1 Cowork — fiscal-subpages.jsx §FxDfePage (herda PT-01 Lista + PT-04 Modal)"
page_id: fiscal-dfe-ondas-f1
module: Fiscal
status: draft
owner: wagner
autor: "[CC]"
created: 2026-08-24
related_us: [US-FISCAL-008, US-FISCAL-012]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0116-pivot-gold-manifestacao-destinatario, 0286-contrato-de-tela]
contrato: prototipo-ui/contrato/fiscal-dfe.contract.json
---

# Charter — Manifesto DF-e · ondas F1 (delta sobre o vivo)

> A lei da tela é `resources/js/Pages/Fiscal/Dfe.charter.md` no `main`. Aqui só o delta: **lote** e **histórico**, os dois declarados backlog lá.

## Mission

Tirar a manifestação do modo "uma nota por vez": quando chegam seis notas do mesmo fornecedor recusadas na portaria, [W] resolve as seis com uma justificativa — e consegue provar depois **o que** foi manifestado, **por quem** e **quando**.

## Goals (faz)

1. **Duas abas** — *Pendências* (fila de trabalho) e *Histórico* (manifestações já realizadas com ação, autor, observação e cstat).
2. **Seleção múltipla** só nas linhas manifestáveis (pendente ou com ciência dada) — "selecionar todas" marca essas e ignora as encerradas.
3. **Manifestação em lote** para as quatro ações da SEFAZ, com **uma justificativa que vale para todas** nas duas que negam a operação.
4. **Aviso de irreversibilidade no lote**: "Manifestação é definitiva por nota — não há desfazer em lote."
5. **Uma requisição por nota** (o lote é da interface, não do protocolo) — a SEFAZ não tem manifestação coletiva.
6. **Prazo continua vindo do valor calculado pela SEFAZ**, com três níveis de urgência; nada de 90 dias fixos na tela.

## Non-Goals (NÃO faz)

- ❌ Manifestar nota já encerrada (confirmada / desconhecida / não realizada) — some da seleção.
- ❌ Desfazer manifestação, individual ou em lote.
- ❌ Detalhe com itens da NF-e recebida e visualizador de XML (Non-Goals do charter do vivo).
- ❌ Histórico real — a aba é demonstração declarada, esperando decisão [W].

## Anti-hooks

- 🚫 Não mandar lote como uma requisição coletiva: se metade falhar, é preciso saber **qual** nota falhou.
- 🚫 Não reaproveitar a justificativa de "desconhecer" numa ação que não a exige — confirmar não pede texto, e pedir treina o operador a escrever qualquer coisa.
- 🚫 Não deixar o Histórico sem selo de procedência enquanto o ator/observação forem inventados.
- 🚫 `nome_emitente`/`cnpj_emitente` são PII de terceiros: exibir é legítimo, agregar em log não.
