---
slug: app-mobile-guia-construcao
title: "Hierarquia documental e guia de construção — App Oimpresso Mobile"
type: guia
module: AppMobile
status: ativo
owner: wagner
version: 1.0.0
last_updated: 2026-06-19
related_docs:
  - SDD-app-oimpresso-mobile.md
  - HANDOFF-PROTOCOL.md
---

# Hierarquia documental e guia de construção — App Oimpresso Mobile

> **Pra que serve:** dar assertividade na criação. Antes de escrever qualquer linha de código no app mobile, este guia responde três perguntas: **(1)** qual documento manda sobre o quê, **(2)** onde cada tipo de informação vive, **(3)** qual o passo a passo obrigatório pra construir sem regressão.
>
> Par do [SDD-app-oimpresso-mobile.md](SDD-app-oimpresso-mobile.md) — o SDD diz *o que* o app é; este guia diz *como* construir nele. Espelha o [GUIA-CONSTRUCAO-VENDAS.md](GUIA-CONSTRUCAO-VENDAS.md) do web.

---

## 1. Hierarquia de autoridade (o que manda sobre o quê)

Em caso de conflito, **a camada de cima sempre vence**. Mudar camada de cima exige ADR nova append-only (`supersedes`) — nunca edição do documento existente.

```
N0 · TIER 0 — IRREVOGÁVEL                    ← nunca muda sem ADR mãe nova
│   multi-tenant (tenant_id em tudo) · LGPD/PII · regras fiscais SEFAZ
│
N1 · CONSTITUIÇÃO v2                          ← processo, charter > spec, append-only
│
N2 · CONSTITUIÇÃO UI v2                        ← 4 camadas de design
│   Fundações (oimpresso-tokens.css) → Shell (tab bar + nav stack) →
│   Padrão de Tela (lista-detalhe + bottom sheet) → Módulo (oi-*)
│
N3 · CHARTER da tela (Tier A)                 ← contrato por screens-*.jsx
│   Mission · Goals · Non-Goals · UX Targets · Anti-hooks
│
N4 · SPEC + CASOS DE USO                       ← CU-M-NNN executáveis (SDD §6)
│
N5 · RUNBOOK / este guia                       ← operacional, golden path, proibições
│
N6 · CÓDIGO + ESTADO                           ← screens-*.jsx + OIStore; nunca contradiz N0-N5
```

**Regras de leitura:**
- **Non-Goal não é gap** — é decisão. Reverter = versionar o charter via PR, não "implementar do mesmo jeito".
- **Anti-hook é proibição mecânica** — vale como Tier 0 local da tela (ex.: nunca emitir fiscal on-mount).
- **Casos de uso (N4) são a spec executável** — todo CU-M `must` mapeia para verificação (carga limpa + fluxo ao vivo).
- **Backend é fonte única de regra de negócio** — o mobile **não duplica** lógica de orçamento/fiscal/financeiro; consome `oimpresso-erp/`.

---

## 2. Mapa documental — onde cada coisa vive

| Pergunta | Documento | Caminho |
|---|---|---|
| "O que o app É e por quê?" | **SDD** | `SDD-app-oimpresso-mobile.md` |
| "Como exporto design → código?" | **Protocolo de Handoff** | `HANDOFF-PROTOCOL.md` |
| "O que esta tela faz / não faz?" | **Charter** | ao lado de cada `screens-*.jsx` (quando promovida) |
| "Qual o comportamento esperado?" | **Casos de uso** | SDD §6 (CU-M01..M16) |
| "Qual o padrão visual canônico?" | **Design System** | `oimpresso-tokens.css` + classes `oi-*` |
| "Onde mora a regra de negócio?" | **Backend** | `oimpresso-erp/apps/server/src/domain/*` + `routers/*` |
| "Como os arquivos se relacionam?" | **Índice** | `INDEX.md` + `manifest.yaml` |

**Regra de ouro:** informação duplicada entre documentos é bug documental. Cada fato tem UMA casa; os outros **linkam**.

---

## 3. Pipeline de construção (passo a passo obrigatório)

Todo item novo no app — tela, campo, fluxo, módulo — passa por estas etapas, **nesta ordem**.

### Etapa 0 · Sinal qualificado (gate de entrada)
- [ ] Cliente pede/reporta OU métrica de uso comprova (≥30%). Sem sinal → vira wish, não entra no backlog.
- [ ] Pedido vago → **perguntar antes de implementar**.

### Etapa 1 · Contexto
- [ ] Ler o **charter** da tela-alvo + Non-Goals + Anti-hooks.
- [ ] Ler o **SDD** (§4 design system, §5 arquitetura, §3 governança).
- [ ] Ler a seção relevante do **backend** (`oimpresso-erp/`) — a regra já existe lá.

### Etapa 2 · Especificar
- [ ] CU-M Given/When/Then com prio `must/should/could`.
- [ ] Se muda comportamento de tela: **versionar o charter** no mesmo PR.
- [ ] Decisão arquitetural → ADR `proposed` ANTES do código.

### Etapa 3 · Estado primeiro
- [ ] Toda mutação cross-tela passa por `OIStore.set` (chave canônica). Nunca mutar só React state local quando precisa refletir noutra tela.
- [ ] Multi-tenant: chave `localStorage` sempre `oimpresso.*`; nunca `sessionStorage`; nunca cruzar empresa.

### Etapa 4 · Implementar
- [ ] Respeitar o slot do padrão de tela (lista-detalhe + `OISheet`) — não inventar estrutura.
- [ ] Componente local na própria tela primeiro; **extrai pra compartilhado só no 2º uso** (R-DS-001).
- [ ] Classes `oi-*` + tokens — **nunca cor crua/hex**; ícones via `window.Ic`, nunca emoji decorativo.
- [ ] Estados completos: default · pressed · disabled · loading · empty (`oi-empty`) · erro inline.
- [ ] Alvos de toque ≥ 44px; safe-area iOS; teclado não cobre o campo.

### Etapa 5 · Gate de papel & coerência
- [ ] Ação financeira/fiscal/de pausa gated por papel (`operador` não faz).
- [ ] Número de dashboard **derivado** de fonte única (`finResumo`, jobs) — nunca literal.

### Etapa 6 · Verificação
- [ ] 0 erro JS no console em **ambas** as molduras (iOS + Android).
- [ ] Fluxo E2E percorre o pipeline e reflete no destino (ex.: faturar → `fin.extra` → Financeiro).
- [ ] Estado sobrevive a refresh (`OIStore`).

### Etapa 7 · PR disciplinado
- [ ] 1 PR = 1 intent · ≤300 linhas · charter/SDD atualizados no MESMO PR.

### Etapa 8 · Rollout
- [ ] Quando produção: trocar dado simulado por tRPC; flag por empresa; canary Wagner → operadores com aviso → monitor 30d.

---

## 4. Árvore de decisão por tipo de mudança

### 4.1 "Quero adicionar um CAMPO numa tela de detalhe/form"
1. Usado com frequência? Não → colapsar em "Mais opções".
2. Específico de uma vertical (comvis/oficina)? → gate por perfil/empresa, não visível a todos.
3. Persiste via store/`POST` existente — não criar rota paralela.
4. Validação → erro inline (`FieldError`), não toast.

### 4.2 "Quero adicionar item numa LISTA (Pedidos/Produção/Financeiro)"
1. Evidência de uso? Sem sinal → wish.
2. Encaixa no padrão lista-detalhe? Estrutura não muda; o módulo só preenche.
3. Dado vem da mesma fonte (store/router) — nunca fetch paralelo.
4. Monetário/data → `oi-mono`/`tabular-nums`; status → `oi-status` sem bg-fill.

### 4.3 "Quero um FLUXO MULTI-ETAPA (aprovação, produção, faturamento)"
1. **Nunca** inventar máquina de estados própria — usar o pipeline canônico (`OIPedidos` / `MANUT_PIPE`), espelho da FSM do backend.
2. Transição via helper (`OIPedidos.advance`) + `OIStore.set` — nunca escrever etapa "na mão" fora do fluxo.
3. Ação crítica (faturar, pausar, emitir) exige papel.
4. Faturamento → `OIFlow.faturar` (idempotente, vínculo por id) — não criar emissor de título paralelo.

### 4.4 "Quero algo FISCAL (NF-e/NFS-e)"
1. Emissão **sempre** por clique humano explícito — auto só via FSM action.
2. Regra ISS×ICMS por natureza vive no backend (`domain/fiscal/natureza.ts`) — a UI mobile só dispara e mostra status.
3. Idempotência + numeração atômica são do backend — o mobile não numera nada.

### 4.5 "Quero atender uma NOVA VERTICAL/MÓDULO"
1. Reusar fundações antes de criar: `OIStore`, `OIFlow`, `domain/*` do backend, tokens, `OISheet`.
2. Tipo de linha novo (item personalizado) → preço calculado **no servidor** (`domain/quote`), nunca digitado.
3. Seguir as ondas do roadmap (SDD §10.5) — dados/estrutura antes de feature.

---

## 5. Lista dura de proibições (resumo never)

| # | Nunca | Fonte |
|---|---|---|
| 1 | Query/estado sem escopo de empresa · cruzar tenant | Tier 0 |
| 2 | Emitir fiscal on-mount / sem clique | Anti-hook |
| 3 | Numerar documento no cliente / reaproveitar nº fiscal | backend |
| 4 | Marcar "pago/faturado" sem o fluxo (`OIFlow`) | FSM |
| 5 | Editar venda/OS finalizada — usar estorno | SDD §6.2 |
| 6 | Cor crua/hex · emoji decorativo · ícone fora de `window.Ic` | DS |
| 7 | `sessionStorage` · `localStorage` sem prefixo `oimpresso.*` | Tier 0 |
| 8 | Modal sobre modal — `OISheet` único / abrir rota | PT mobile |
| 9 | Operador faturando/pausando/emitindo | RBAC |
| 10 | Número de dashboard literal (tem que ser derivado) | coerência |
| 11 | PII completa na listagem (documento mascarado) | LGPD |
| 12 | Duplicar regra de negócio no mobile (vive no backend) | SDD §1 |
| 13 | Mutar React state local quando precisa refletir cross-tela | OIStore |
| 14 | Alvo de toque < 44px | a11y mobile |
| 15 | Inventar máquina de estados fora de `OIPedidos`/`MANUT_PIPE` | FSM |

---

## 6. Definição de pronto (DoD)

Um item do app só está **pronto** quando:
1. ✅ CU-M `must` verde — fluxo percorrido ao vivo
2. ✅ 0 erro JS no console em iOS **e** Android
3. ✅ Estado sobrevive a refresh (`OIStore`)
4. ✅ Gate de papel aplicado (operador não faz ação financeira/fiscal)
5. ✅ Número de dashboard derivado (não literal)
6. ✅ Classes `oi-*` + tokens (sem cor crua); ícones `window.Ic`
7. ✅ Alvos ≥ 44px · 360px usável · safe-area
8. ✅ Charter + SDD atualizados no mesmo PR
9. ✅ Quando produção: consome backend real (não mock), flag + canary

---

## 7. Convenções rápidas

- **IDs:** `CU-M-NN` (casos de uso) · `C/F-M-N` (roadmap SDD §10)
- **localStorage:** `oimpresso.<chave>` — chaves canônicas: `pedidos.mut`, `oficina.os`, `fin.extra`, `fin.liq`, `produtos.estoque`
- **Tipografia/cor:** tokens em `oimpresso-tokens.css`; números `oi-mono`/`oi-money`; status `oi-status` (dot + texto)
- **Componentes:** local na tela → compartilhado só no 2º uso · props tipadas · cleanup de listeners
- **Estado:** `useStore(key, initial)` (de `oi-store.jsx`) é o único caminho de mutação cross-tela
- **Faturamento:** `OIFlow.faturar({origemId,...})` — idempotente, vínculo por id, emite em `fin.extra`
- **Navegação:** `nav.push(tela, params)` / `nav.pop()` / `nav.replace()` (shell em `mobile-app.jsx`)
- **Ordem de carga dos scripts:** ver `Oimpresso Mobile.html` (React → Babel → frames → icons → oi-store → oi-flow → data → screens → mobile-app)

---

## 8. Fluxo resumido (cola de parede)

```
SINAL → CU-M Given/When/Then → ler charter+SDD+backend
  → estado via OIStore → implementa no slot (lista-detalhe + OISheet)
  → tokens/oi-* (sem cor crua) → gate de papel → número derivado
  → 0 erro console iOS+Android → fluxo E2E reflete no destino
  → PR ≤300 linhas (charter+SDD juntos)
  → produção: troca mock por tRPC → flag → canary → monitor 30d
```
