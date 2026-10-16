---
page: /fluxos
component: resources/js/Pages/Fluxos/Index.tsx
owner: wagner
status: draft
parent_module: Fluxos
related_prototype: prototipo-ui/cowork/Wagner/fluxos-page.jsx
related_runbook: memory/requisitos/Fluxos/RUNBOOK-fluxos-index.md
alcance:
  rota: /fluxos
  rota_nome: fluxos.index        # routes/web.php (núcleo, grupo web do ERP)
  permission: fluxos.ver         # permissão do núcleo (PermissionCatalog + views de papel), não de DataController
  menu_hook: app/Http/Middleware/AdminSidebarMenu.php   # item "Fluxos e regras" no grupo SISTEMA
  pacote: n/a — tela do núcleo, sem módulo nWidart nem pacote
tier: B
charter_version: 1
---

# Page Charter — Fluxos/Index (DRAFT)

> Sistema › Fluxos e regras. Decisão de produto: ADR 0435 "Fluxos por modelo (porte × atividade)"
> (PR #9488), [W] 2026-10-16: o fluxo do pedido segue o protótipo "Fluxos e regras".
> Contrato: [Index.casos.md](Index.casos.md). Sobe de `draft` → `live` com screenshot aprovado por [W].

## Mission

Mostrar ao dono ou gerente quais modelos de fluxo (etapas e regras do motor FSM) servem para o
porte e a atividade da empresa, e deixar trocar o modelo em uso sem mexer nos documentos em
andamento.

## Goals — Features (faz)

- PageHeader "Fluxos e regras" + 4 abas na ordem do protótipo: Modelos · Processos · Fluxos da
  empresa · Casos de uso (`?aba=`).
- Aba Modelos: perfil porte (Micro · Pequena · Média · Grande) × atividade principal; os 7 modelos
  do catálogo ordenados pelo encaixe, com selo Recomendado (4) · Serve (3) · Parcial (1–2) ·
  Outro perfil (0), a frase do motivo, a cadeia do processo de destaque e as contagens.
- Encaixe, selo e motivo vêm do servidor (`CatalogoModelos::encaixe()`); a tela não recalcula.
- Acesso: `fluxos.ver` para abrir; `fluxos.configurar` para trocar o modelo.
- Card abre a gaveta do modelo (processos, regras "ao entrar em", casos cobertos, diferença para o
  modelo em uso). "Usar este modelo" → confirmação dizendo que documentos em andamento ficam onde
  estão → `POST /fluxos/modelo` → `AplicarModelo` (#9492). A empresa é sempre a da sessão.
- Assistente de configuração (aba Modelos): descrição livre + exemplos → `POST /fluxos/assistente`
  → `RecomendarModelo` (agente `RecomendarModeloFluxoAgent`, laravel/ai). Sem IA, ou com resposta
  fora do catálogo, cai na regra de encaixe e a tela diz "Sugestão pelo perfil". Só sugere.
- Aba Processos: processos do `CatalogoProcessos` (#9496) agrupados por área; detalhe com estados
  (filtro pelo estado de origem, legenda início/espera/fim/exceção), roteiro em paralelo, tabela de
  transições, comparativo NF-e × NFS-e nos fiscais e "Conversa com" entre processos.
- Aba Casos de uso: `CatalogoModelos::casos()` (#9501) em tabela com busca e filtros por processo,
  fonte e estado no oimpresso; gaveta do caso com regra, estado, fontes (link) e modelos que usam;
  lista das fontes com quantos casos cada uma anuncia.
- Aba Fluxos da empresa: modelo em uso (ou "processos de antes dos modelos" quando legado), com
  data e perfil, e as etapas e regras de cada processo dele.
- PT-BR em todo texto.

## Non-Goals — Features (NÃO faz)

- ❌ Guardar uma cópia do catálogo no front: os modelos vêm de `App\Domain\Fsm\Modelos\CatalogoModelos`.
- ❌ Mudar a etapa de documento em andamento ao trocar de modelo (ADR 0435).

## Lacunas declaradas (o protótipo mostra, o catálogo ainda não tem)

- Perfil porte × atividade: começa pelo gravado da empresa; sem modelo gravado, Pequena ·
  Comunicação visual, como no protótipo. Só é gravado junto com "Usar modelo".
- Ligar/desligar regra e a Compra "configurável" da aba Fluxos da empresa: gravam a configuração
  da empresa (`ligada` na etapa; `fsm_modelos_empresa.regras_compra`), mas nada executa ainda —
  as regras do catálogo seguem planejadas e o recebimento da compra não lê a configuração.
  Na Compra só os parâmetros do custo aparecem, como no protótipo (a tolerância do
  "Gerar conta a pagar" fica no padrão).

- "Aparece nas telas" da aba Processos: o protótipo aponta rotas dele, não do ERP; fica fora até
  cada processo ter a tela correspondente no ERP.

## UX Targets

- Cabe em 1280px sem scroll horizontal; grade de cards `auto-fill minmax(340px,1fr)` do bundle.

## Refs

- Protótipo: `prototipo-ui/cowork/Wagner/fluxos-page.jsx` + `fluxos-page.css` (copiado inteiro em
  `resources/css/cowork-fluxos-bundle.css`) + `fluxos-data.jsx`.
- Constituição UI v2: UI-0013 · Sidebar SISTEMA: ADR 0180 / UI-0023.
