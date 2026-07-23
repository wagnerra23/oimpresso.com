# oimpresso — ERP multi-tenant brasileiro, construído e governado por agentes de IA

<!-- documentation-entrypoint: canonical -->

O oimpresso é uma plataforma ERP brasileira, **multi-tenant e especializada por vertical de negócio** ([ADR 0121](memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md)). Um núcleo comum — isolamento por `business_id`, financeiro, fiscal (NFC-e/NF-e/NFSe), ordens de serviço com máquina de estados auditável e ponto eletrônico — atende qualquer PME; módulos verticais aprofundam onde existe cliente pagante real. Sobre o ERP roda a **Jana**, o produto de IA de decisão (chat com memória por empresa, brief diário, metas, alertas). E o repositório inteiro é operado por um **sistema operacional de IA (IA-OS)**: uma constituição versionada, um servidor MCP próprio e um contrato de agente onde **propor é permitido e decidir o merge não é**.

> **Estado vivo — cycle, tarefas, brief, custo, notas — nunca sai deste README.** Vem das tools MCP (`brief-fetch`, `my-work`, `cycles-active`). Este documento descreve a **estrutura** do sistema, não o estado; qualquer contagem aqui aponta para a fonte viva em vez de fixar um número que apodrece ([ADR 0256](memory/decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)).

---

## Arquitetura em três camadas

O repositório carrega três produtos empilhados ([ADR 0334](memory/decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)). A camada de cima **herda** da de baixo e nunca a contradiz. Cada uma tem **um** documento de entrada — comece pela que te trouxe aqui.

| Camada | O que é | Entrada |
|---|---|---|
| **A — Produto (ERP)** | O oimpresso que o cliente usa: núcleo multi-tenant + verticais | [Guia do Sistema](memory/GUIA-DO-SISTEMA.md) |
| **B — Jana (produto de IA)** | Copiloto de decisão sobre o negócio: chat com memória, metas, brief, alertas | [BRIEFING da Jana](memory/requisitos/Jana/BRIEFING.md) |
| **C — IA-OS (engenharia por agentes)** | Constituição, agentes, servidor MCP e a Forja — o que faz a IA construir e governar o repo | [`CLAUDE.md`](CLAUDE.md) |

```
┌──────────────────────────────────────────────────────────────┐
│  C · IA-OS      Constituição v2 · ADRs · Skills · MCP · Forja  │  ← constrói e governa
├──────────────────────────────────────────────────────────────┤
│  B · Jana       Chat + memória + metas + brief + alertas       │  ← produto de IA
├──────────────────────────────────────────────────────────────┤
│  A · Produto    Verticais: Vestuário · ComVis · Oficina        │  ← vendável por setor
│                 Núcleo: Financeiro · Fiscal · Repair/FSM        │  ← comum a todos
│                 Kernel UltimatePOS + business_id global scope   │  ← base multi-tenant
└──────────────────────────────────────────────────────────────┘
```

Invariante anti-atrofia ([ADR 0334](memory/decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)): a camada C existe para **servir** A e B; ela nunca deve crescer enquanto o produto atrofia sem sinal de cliente — há um sentinela que dispara quando a governança domina a janela de merges.

---

## A — Produto (ERP)

**Isolamento multi-tenant é Tier 0 IRREVOGÁVEL** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)): toda tabela de negócio carrega `business_id` sob global scope obrigatório. Vazamento cross-tenant é o pior defeito possível — há teste de arquitetura e gate de CI dedicados a impedi-lo.

**Máquina de estados auditável (FSM Pipeline)** — LIVE em produção desde 2026-05-12 ([ADR 0143](memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)). Toda transição de estado em Vendas (`Sells`) e Ordens de Serviço (`Repair`) passa por `app/Domain/Fsm/…/ExecuteStageActionService`, com RBAC por empresa. O trait `GuardsFsmTransitions` **bloqueia UPDATE direto** no estágio; o histórico (`sale_stage_history`) é append-only; os efeitos colaterais são isolados (`ReservarEstoque`, `CancelarVendaCascade`, que orquestra cancelamento de NFe na SEFAZ + estorno Asaas/Inter + notificação ao cliente).

**Fiscal brasileiro nativo** — `Modules/NfeBrasil` (NFC-e/NF-e via SEFAZ, certificado A1, cancelamento que preserva o sequencial oficial) e `Modules/NFSe` (NFS-e). A emissão vincula-se à venda pelo FSM.

### Módulos

**Núcleo** — comum a qualquer empresa:

| Módulo | Função |
|---|---|
| `Modules/Jana/` | Copiloto de IA + memória persistente + agents ([ADR 0035](memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) |
| `Modules/TeamMcp/` | Servidor MCP + Forja + Identity Mesh + audit log ([ADR 0053](memory/decisions/0053-mcp-server-governanca-como-produto.md)) |
| `Modules/Financeiro/` | Contas a pagar e receber, boleto, conciliação — integração Asaas/Inter |
| `Modules/NfeBrasil/` · `Modules/NFSe/` | Emissão fiscal — NFC-e/NF-e e NFS-e |
| `Modules/Repair/` | Ordens de serviço + Kanban FSM — infraestrutura compartilhada entre verticais |
| `Modules/RecurringBilling/` · `Modules/PaymentGateway/` | Cobrança recorrente e gateways de pagamento |
| `Modules/Ponto/` | Marcação de ponto eletrônico (Portaria MTP 671/2021) |
| `Modules/Brief/` | Daily Brief — estado consolidado do projeto ([ADR 0091](memory/decisions/0091-daily-brief.md)) |

**Verticais** — especialização profunda onde há cliente real ([ADR 0121](memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md)):

| Módulo | CNAE | Estado |
|---|---|---|
| `Modules/Vestuario/` | 4781-4/00 | ✅ **em produção** há 2+ anos — piloto ROTA LIVRE |
| `Modules/OficinaAuto/` | 4520-0/01 | 🟡 **piloto LIVE** — reparo/mecânica, nunca locação ([ADR 0265](memory/decisions/0265-oficina-reparo-erradica-locacao.md)) |
| `Modules/ComunicacaoVisual/` | 1813-0/01 | 🟡 **em construção** — sem cliente em produção ainda |

Estado vivo de cada módulo (nota, cobertura, gaps): `memory/requisitos/<Modulo>/BRIEFING.md`.

---

## B — Jana (produto de IA)

A Jana não é um chatbot: é o **front de decisão do dono do negócio** sobre um ERP multi-tenant. Ao cliente ela entrega chat com **memória persistente por empresa**, **brief diário** auto-gerado (faturamento, tendência, clientes ativos — sem clique), **sugestão de metas estruturadas** (propostas comparáveis que viram metas com apuração agendada) e **alertas de desvio**.

- **Três camadas de IA** ([ADR 0035](memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)): **(A)** wrapper `laravel/ai`; **(B)** agents próprios em `Modules/Jana/Ai/Agents/` — o framework Vizra ADK foi avaliado e **rejeitado** ([ADR 0048](memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md)); **(C)** memória `MemoriaContrato` + Meilisearch + embeddings Ollama no CT 100, com recall reordenado por time-decay.
- **Isolamento mecânico no LLM** — o `business_id` vem do constructor da tool, **nunca do modelo** ([ADR 0141](memory/decisions/0141-agents-tool-use-pattern-claude-code.md) + [0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)). Mesmo que o LLM tente injetá-lo, a tool ignora. As tools de leitura de negócio são read-only e escopadas por empresa.
- **Qualidade versionada que morde o CI** — evals de recall e RAGAS + sentinela de drift (`jana:recall-eval`, `jana:ragas-real-eval`, `jana:drift-sentinel`), com baseline em `governance/` que reprova regressão.
- **Observabilidade LLM** — Langfuse (trace por `business_id`) e Jaeger/OTel rodando no CT 100.
- **Memória governada** — canônica em git, sincronizada ao MCP; zero auto-memória privada ([ADR 0061](memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)).

---

## C — IA-OS (engenharia por agentes)

O que distingue este repositório é a camada C ser **real e operante**, não um conjunto de convenções: a governança é código que bloqueia, não PDF.

- **Constituição v2** ([ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)) — 7 camadas (MCP Core → ADS → Skills → Playbooks → ADRs → Charters → Daily Brief) e **8 princípios duros**: (1) contexto como produto · (2) custo em tiers · (3) charter > spec · (4) loop fechado por métrica · (5) separação de responsabilidades brutal · (6) multi-tenant Tier 0 irrevogável · (7) transparência · (8) confiabilidade com fallback. ADRs são **append-only** — mudar uma decisão exige uma nova que a supersede. O estado real das camadas vive num retrato datado à parte ([ADR 0330](memory/decisions/0330-mapa-dos-niveis-estado-real-2026-07-constituicao.md)).
- **Servidor MCP canônico** — `mcp.oimpresso.com` no CT 100 ([ADR 0053](memory/decisions/0053-mcp-server-governanca-como-produto.md)). Autentica por tokens Sanctum, sincroniza `memory/*` do git para `mcp_memory_documents` via webhook (PII redigida no caminho), e serve o estado vivo (`brief-fetch`, `tasks-*`, `cycles-*`, `decisions-search`). Toda ação vai para um **audit log imutável** (`mcp_audit_log`, trigger MySQL).
- **Identity Mesh** ([ADR 0081](memory/decisions/0081-identity-mesh-mcp-actors.md)) — cada ator (humano ou IA) tem manifest com trust level L0–L4 e cadeia de delegação (a IA herda o contexto do humano que a pareia). Sem manifest, sem ação: default-deny.
- **Forja — cockpit do cowork loop** (`Modules/TeamMcp`) — seis abas: Triagem → Backlog → Quadro (fases F0 Brief → F1 Design → F3 Code → F4 Merged) → Changelog → MCP → Saúde. O contrato é a linha vermelha: agentes têm **`read` + `propose`**; **`git.merge` e `constituicao.edit` são negados por token** — a decisão final é sempre humana ([ADR 0114](memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) · [0282](memory/decisions/0282-protocolo-v2-colapso-ratificacao.md)).
- **Enforcement que morde** — hooks PreToolUse bloqueiam em runtime (auto-memória privada, teste fora do CT 100, claim sem evidência, PII em commit); gates de CI required cobrem append-only, cobertura de tela, âncoras e regressão visual; e um `gate-selftest` prova, contra fixtures, que cada catraca realmente reprova.
- **Doutrina de sobrevivência do conhecimento** ([ADR 0256](memory/decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)) — *derivado e enforçado sobrevive; escrito e lembrado apodrece*. Por isso mapas e contagens são recalculados de comandos vivos, nunca mantidos à mão.

---

## Infraestrutura (runtime)

Dois ambientes com fronteira **IRREVOGÁVEL** ([ADR 0062](memory/decisions/0062-separacao-runtime-hostinger-ct100.md)): a aplicação web em shared hosting; **todo** daemon, serviço auxiliar e ferramenta de IA no servidor da empresa. Detalhe canônico em [`INFRA-ACESSO-CANON.md`](memory/reference/INFRA-ACESSO-CANON.md) e [`infra-proxmox-ct100.md`](memory/reference/infra-proxmox-ct100.md).

```
 PRODUÇÃO (só a aplicação)                    PLATAFORMA (daemons, IA, dados)
 ┌──────────────────────────┐                ┌──────────────────────────────────────────────┐
 │ Hostinger Cloud Startup  │                │ Proxmox (Xeon E5-2680v4 · ~128 GB · 2 TB · IP fixo)│
 │ LiteSpeed · LSPHP 8.4    │◀── autossh ────│  └─ CT 100 docker-host (Debian 12):            │
 │ oimpresso.com            │    túnel MySQL │     mcp (FrankenPHP+Octane) · traefik ·        │
 │ MySQL de produção        │                │     centrifugo · meilisearch + ollama-embedder │
 └──────────────────────────┘                │     staging + MariaDB 11 · vaultwarden ·       │
                                             │     langfuse · jaeger · growthbook · whatsmeow │
                                             └──────────────────────────────────────────────┘
```

| Serviço | Onde | Papel |
|---|---|---|
| `oimpresso.com` + MySQL prod | Hostinger | Aplicação PHP servida ao cliente (LiteSpeed/LSPHP) |
| `mcp.oimpresso.com` | CT 100 | Servidor MCP — estado vivo, memória, tasks (FrankenPHP + Octane) |
| `staging.oimpresso.com` | CT 100 | Clone anonimizado LGPD-safe da produção ([ADR 0235](memory/decisions/0235-staging-ct100-clone-anonimizado.md)) |
| Centrifugo + FrankenPHP | CT 100 | Realtime canônico ([ADR 0058](memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)) |
| Meilisearch + Ollama | CT 100 | Memória semântica e embeddings da Jana |
| Langfuse · Jaeger | CT 100 | Observabilidade LLM e tracing OTel |
| Vaultwarden | CT 100 | Cofre de segredos — nenhum secret vive no git |

Acesso à plataforma via Tailscale (`tailscale ssh root@ct100-mcp`); o MCP lê o MySQL de produção por túnel autossh. ⛔ **Nunca** rodar daemon, `laravel/octane` ou ferramenta MCP exposta no Hostinger — shared hosting é só a aplicação web.

**Deploy** — merge em `main` (fora de `memory/**`, `**.md`, `prototipo-ui/**`) dispara o pipeline automático ([ADR 0269](memory/decisions/0269-deploy-automatico-build-no-runner.md)): build Vite determinístico no runner → deploy por SSH com backup rotacionado, `maintenance`, `git reset --hard`, `composer install`, migrate, reset de OPcache via HTTP, smoke em `/login` e failsafe que mantém 503 gracioso se o boot falhar.

**Testes** — rodam **exclusivamente no CT 100**, contra MySQL real com biz=1 em dogfooding ([ADR 0062](memory/decisions/0062-separacao-runtime-hostinger-ct100.md)); um hook bloqueia `pest`/`php artisan test` fora dele:

```bash
tailscale ssh root@ct100-mcp \
  "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=NomeDoTeste"
```

O **gate de merge é o CI (GitHub Actions)** — dezenas de checks required (Pest, PHPStan/ESLint com ratchet, hardcode `business_id` Tier 0, PII/secret scan, regressão visual, governança). **Verde local não conta.**

---

## Cronologia — três eras

Linhagem completa e datada em [`HISTORIA-LINHAGEM.md`](memory/HISTORIA-LINHAGEM.md).

| Era | Período | O que aconteceu |
|---|---|---|
| **I — Delphi** | ~26 anos, até 2026 | WR Sistemas / OfficeImpresso: Object Pascal + Firebird (um `.FDB` por cliente, WIN1252), setor gráfico, sistema offline. |
| **II — A decisão** | 2026-04 | Estender **UltimatePOS v6** em vez de reescrever ou forkar ([ADR 0001](memory/decisions/0001-estender-ultimatepos-opcao-c.md), por Eliana); módulos com nWidart ([ADR 0002](memory/decisions/0002-nwidart-laravel-modules.md)). Nasce como o módulo de ponto eletrônico **PontoWr2** — hoje `Modules/Ponto` — sob a Portaria MTP 671/2021. |
| **III — oimpresso modular** | 2026+ | Pivô multi-vertical ([ADR 0121](memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md)) e Constituição v2 ([ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)). O Delphi legado segue vivo, com integração **aditiva** ([ADR 0113](memory/decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md)) e pipeline de migração Firebird → oimpresso ([ADR 0203](memory/decisions/0203-legacy-migration-pipeline-firebird-oimpresso-w29.md)). |

---

## Stack canônica

- **Laravel 13.6** + PHP 8.4 · **Inertia v3** + React 19 + Tailwind 4 (SPA sobre Blade)
- **MySQL 8** (produção) / **MariaDB 11** (staging) — multi-tenant por `business_id` global scope ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- **nWidart Modules** — arquitetura modular `Modules/<Nome>/`
- **Pest v4** + PHPUnit v12 — testes **só no CT 100**
- **`laravel/ai`** + agents próprios + `MemoriaContrato`/Meilisearch — stack de IA canônica ([ADR 0035](memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md))
- **`laravel/mcp`** + Sanctum — servidor MCP, exposto **só** no CT 100

---

## Navegação da documentação

Este `README.md` é a **única porta global**. Escolhida a camada, o resto é destino pontual:

| Quero... | Vá para |
|---|---|
| Mapa técnico (arc42, trust levels, runtime C4) | [`ARCHITECTURE.md`](memory/governance/ARCHITECTURE.md) |
| Decisões arquiteturais | [`memory/decisions/`](memory/decisions/) — ADRs Nygard ([índice vivo](memory/decisions/_INDEX-GENERATED.md)) |
| Alterar um módulo | `memory/requisitos/<Modulo>/BRIEFING.md` → `SPEC.md` → `RUNBOOK` |
| Preparar o ambiente e executar | [Staging no CT 100](memory/requisitos/Infra/RUNBOOK-staging-ct100.md) |
| Infraestrutura e deploy | [`INFRA-ACESSO-CANON.md`](memory/reference/INFRA-ACESSO-CANON.md) |
| Procurar um documento | [Catálogo de documentos](memory/INDEX.md) |
| Classificar ou realocar documentação | [Realocação documental](memory/governance/REALOCACAO-DOCUMENTAL.md) |

> Regra de estrutura: **uma autoridade por assunto**. Outros `README.md` existem como portas **locais** de módulos e pastas, mas nenhum pode se declarar porta global nem duplicar uma autoridade existente.

---

## Licença

Software proprietário. A base UltimatePOS está sob a [Codecanyon Standard License](https://codecanyon.net/licenses/standard); as modificações e os módulos próprios pertencem à oimpresso.

Contato: wagnerra@gmail.com (Wagner — dono e operador).
