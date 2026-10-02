---
page: /import-sales/preview
component: resources/js/Pages/ImportSales/Preview.tsx
owner: wagner
status: draft
last_validated: "2026-10-02"
parent_module: Sells
related_prototype: prototipo-ui/cowork/Wagner/venda-blade-telas.jsx
related_runbook: memory/requisitos/Sells/RUNBOOK-import-sales.md
tier: B
charter_version: 1
---

# Page Charter — Prévia da importação de vendas (`POST /import-sales/preview`)

> Texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Importacao.charter.md`.
> Thread 05 do playbook `venda-menu`. **Protótipo:** `VendaImportPreview` em `venda-blade-telas.jsx`.
> **Fonte legado:** `import_sales/preview.blade.php`. A tela é a resposta do POST da planilha — não
> tem URL própria para abrir direto (recarregar volta para a importação).

## Mission

Conferir a planilha linha a linha e dizer qual coluna é qual campo antes de gravar.

## Regras

- R1 Mostra as 100 primeiras linhas, numeradas como na planilha (cabeçalho = linha 1), igual ao
  Blade; o total de linhas aparece ao lado.
- R2 Cada coluna chega pré-mapeada por semelhança de nome (≥ 50%); "Ignorar" deixa a coluna de fora.
- R3 Enviar só fica liberado com: telefone OU e-mail, produto OU SKU, quantidade, preço unitário,
  nenhum campo em duas colunas, local do negócio e "Agrupar por" escolhidos. A tela diz o que falta.
- R4 Escolhido "Agrupar por", a tela diz quantas vendas serão criadas (contadas sobre todas as linhas).
- R5 Acima de `limiteSincrono` linhas a tela avisa que a importação vai para a fila.

## Goals — Features (faz)

- Mapeamento coluna → campo com aviso do que falta.
- Local do negócio e "Agrupar por".
- Envio para `POST /import-sales` com o mesmo formato do Blade (`file_name`, `import_fields[coluna]`, `group_by`, `location_id`).
- PT-BR em todo label/mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Editar valor de célula da planilha na prévia.
- ❌ Rota nova.

## UX Targets

- Cabe em 1280px: a tabela rola dentro do card, não a página.

## Refs

- Casos: `Preview.casos.md` ao lado.
- Padrão de Tela: PT-02 Form.
- Constituição UI v2: UI-0013.
