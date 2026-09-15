---
id: resources-js-pages-vozdocliente-caixa-charter
page: /voz-do-cliente
component: resources/js/Pages/VozDoCliente/Caixa.tsx (hoje Modules/VozDoCliente/Resources/views/caixa.blade.php)
related_prototype: prototipo-ui/cowork/modulos-faltantes/voz-do-cliente-page.jsx
related_contrato: prototipo-ui/contrato/voz-do-cliente.contract.json
related_casos: voz-do-cliente.casos.md
owner: wagner
status: draft
parent_module: VozDoCliente
related_adrs: [93, 180]
tier: C
charter_version: 1
mission: "Deixar quem cuida do produto ver, num lugar só, o que as pessoas relataram de dentro do sistema — com a tela em que aconteceu — e decidir à mão o que vira US."
---

# Page Charter — /voz-do-cliente (DRAFT)

> **Status:** draft criado pelo [CC] na onda O4 (cobertura de charter das telas importadas). [W] aprova **Non-Goals + Anti-hooks** antes de virar `status: live`.
> Backend hoje: `Modules/VozDoCliente` — controller devolve `\$sinais` paginado; a entry de sidebar nasce em `DataController::modifyAdminMenu()` com label PT-BR literal (o LegacyMenuAdapter não resolve `__()`).

## Mission

O relato entra por dentro do sistema (widget de feedback), guardando **quem disse, o que disse e em que tela**. Esta caixa existe pra transformar isso em decisão de produto — nada mais.

## Persona-alvo

Wagner (escritório, 1440px) lendo a caixa pra priorizar. Larissa e Eliana **produzem** relato, não consomem esta tela.

## Goals — faz

- Lista cronológica: Quando · Quem · O que disse · Onde (`url_vista`) · Gravidade · Situação.
- Filtro por situação (todos/pendentes/triados/fechados) e contagem por situação.
- **Triagem manual**: relato pendente → US do backlog (com código) **ou** fechado com motivo.
- Frescor visível (o relato de 4 dias não é o de 2 h).

## Non-Goals — NÃO faz

- ❌ NÃO é helpdesk: não abre chamado, não promete SLA, não responde quem relatou.
- ❌ NÃO notifica ninguém — nem ao triar, nem ao fechar.
- ❌ NÃO edita nem apaga o texto do relato (é registro do que a pessoa disse).
- ❌ NÃO cruza tenants (`business_id` scope, Tier 0).
- ❌ NÃO expõe a caixa a papel de operação (texto + autor + tela = pessoa identificada, LGPD Art. 7º).

## Automation hooks (faz)

- Classifica frescor pela data do relato; agrupa contagem por situação.

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO cria US por severidade nem por volume de relatos parecidos.
- ❌ NÃO escala nem reordena backlog sozinho.
- ❌ NÃO deduplica relato automaticamente (duplicado é decisão de quem tria).

## UX targets

- Cabe em 1280px; coluna "O que disse" nunca esmagada (a tabela rola na horizontal).
- Copy do vazio literal do blade vivo: "Nenhum relato ainda…".
- Estados: cheia · filtrada-vazia · vazia · carregando · erro · sem-permissão.

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks.
- [ ] Definir o papel/permissão que abre a caixa (hoje qualquer autenticado com a entry).
- [ ] Confirmar destino da triagem: campo `triado_para_us` já existe no schema.
