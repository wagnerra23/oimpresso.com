---
page: /import-sales
component: (F1) venda-blade.jsx
owner: wagner
status: draft
last_validated: "2026-08-22"
parent_module: Sells
related_prototype: prototipo-ui/cowork/venda-menu/
---

# Charter — Importação de vendas

**Fonte legado:** `import_sales/index + preview` · **Permissão:** `sell.create (importar) · sell.delete (reverter lote)`
**Frescor:** 🟠 catch-up real — medido no `main` em 2026-08-22 (tree `6a8e45998ee5`): nenhum `Inertia::render` no controller, a tela ainda é Blade.

## Missão
Trazer venda de fora por planilha, com prévia e mapeamento de colunas antes de gravar.

## Regras
- R1 Campos importáveis são 14 (+5 de tipos de serviço quando o módulo está ligado), com os rótulos do `__importFields()`.
- R2 A prévia chega **pré-mapeada** por semelhança de nome (≥ 50%).
- R3 Obrigatórios: telefone OU e-mail do cliente; produto OU SKU; quantidade; preço unitário. Um campo não pode ser usado em duas colunas.
- R4 "Agrupar por" define o que vira uma venda só.
- R5 A venda importada nasce **finalizada**, baixa estoque e cria o cliente se não existir.
- R6 Erro para na linha e diz o número dela (produto, imposto ou unidade não encontrados, data inválida).
- R7 Reverter lote **apaga** as vendas do lote.

## Achados
- A1 A prévia é bloqueada em ambiente de demonstração (`notAllowedInDemo`).
- A2 A importação roda com `max_execution_time = 0` e `memory_limit = -1` — planilha grande derruba o processo sem fila.
