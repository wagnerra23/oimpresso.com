# ADR 0404 — `Tooltip` do DS é recortado dentro de contêiner de rolagem

**Data:** 2026-08-19 · **Status:** proposto (aguarda decisão) · **Camada:** 0 · Design System

## Contexto

O `Tooltip` do DS posiciona o balão em `position: absolute` dentro de um wrapper `relative`.
Qualquer ancestral com `overflow` diferente de `visible` recorta o balão.

Na Consulta de Produtos a tabela tem colunas de largura fixa (soma 1000px) e precisa de rolagem
horizontal para que cabeçalho, linhas e o rodapé de `Pagination` permaneçam alinhados em viewport
estreita — medido: sem contêiner de rolagem, o encolhimento por `flex-shrink` faz a MESMA coluna
medir 149px, 152px e 134px em três linhas seguidas, porque cada linha é um flex container
independente. Ou seja: a rolagem é obrigatória, e com ela o recorte do tooltip é inevitável.

Tentativas registradas: (a) sem rolagem → colunas desalinham e a tabela transborda o card em 203px;
(b) rolagem no wrapper interno → tooltip recortado e rodapé desalinhado das linhas;
(c) rolagem no card (adotado) → tudo alinhado, tooltip recortado nas bordas.

## Decisão

**Nesta tela (aplicado):** rolagem horizontal no card, colunas com base fixa
(`flex: 0 0 Npx; min-width: 0; box-sizing: border-box`) e tooltips com `side="top"` nas células
internas, onde o recorte é menor.

**No DS (proposto):** renderizar o balão do `Tooltip` em portal com coordenadas `position: fixed`
calculadas do rect do gatilho (mesmo tratamento que o `DatePicker` já exigiu em outro módulo),
ou aceitar um prop `portal` opcional.

## Consequências

- **Se aprovado:** tooltips e menus ancorados funcionam dentro de qualquer tabela rolável.
- **Se não aprovado:** cada tela com tabela rolável precisa escolher entre alinhamento de coluna e
  detalhe sob demanda.

## Referências

- `_ds/wagner-office-impresso-design-system-49a36f76-…/_ds_bundle.js` — `Tooltip`, `DropdownMenu`. (O caminho citado na medição original, `_ds/office-impresso-atual-d7f88676-…`, foi apagado em 21/09/2026.)
- ADRs 0401–0403 (dívidas do DS nesta tela).
