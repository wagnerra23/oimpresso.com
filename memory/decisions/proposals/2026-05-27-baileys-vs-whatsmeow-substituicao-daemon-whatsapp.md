---
proposal_id: baileys-vs-whatsmeow-substituicao-daemon-whatsapp
status: rejected
rejected_at: 2026-05-27
rejected_by: wagner
rejected_reason: |
  Substituída horas depois pela proposta `2026-05-27-whatsapp-profissionalizacao-baileys-out.md`
  com sinal qualificado novo do Wagner ("ninguém está ativo no Baileys, é instável não deu
  pra usar"). Recomendação D híbrida experimental (manter Baileys + experimentar whatsmeow
  paralelo 90d) ficou obsoleta — sem produção pra preservar e Wagner já abandonou a tecnologia.
  Decisão final: Baileys OUT integral + Meta Cloud default universal + Z-API opcional. Veja
  dossier `memory/sessions/2026-05-27-dossier-whatsapp-profissionalizacao.md` e ADR companion.
created: 2026-05-27
proposed_by: claude-code (opus-4.7)
prompted_by: wagner
parent_adr: 0096
related_adrs: [0058, 0062, 0093, 0094, 0117, 0140]
type: substituicao-runtime-tier-0
recommendation: D-hibrido-experimental
superseded_by: 2026-05-27-whatsapp-profissionalizacao-baileys-out
---

# Proposta · Substituir Baileys (Node.js) por whatsmeow (Go) no daemon WhatsApp do CT 100

> **Status:** 🟡 **PROPOSED** — aguardando decisão Wagner.
> **Recomendação Claude:** **Opção D híbrida experimental** (NÃO substituir agora; abrir trilho `whatsmeow` paralelo como driver opcional pra biz novos + cutover gradual condicionado a métricas).
> **NÃO executar nada antes do Wagner decidir entre A/B/C/D.**

## TL;DR

Wagner perguntou: *"acho que vamos substituir o Baileys do WhatsApp?"*

**Resposta curta:** **provavelmente não agora**, e essa proposta explica por quê com dados.

1. **Premissa de comparação anterior estava errada** — o oimpresso **nunca usou Evolution API** ([ADR 0096](../0096-modulo-whatsapp-meta-cloud-api-direto.md) marca Evolution **PROIBIDO permanente**). O que está em produção é **BaileysDriver custom + Z-API default + Meta Cloud fallback**.
2. **Investimento sunk em Baileys é alto** — daemon Node TypeScript com `antiBan`/`banDetector`/`mysqlAuthState`/`historySync`/OTel/Prometheus/Pest tests + migration scripts (filesystem→MySQL) + rotação de chave de criptografia. Migrado pra **Baileys 7.0.0-rc11 em 2026-05-15** (~12 dias atrás), versão que incorpora melhorias do próprio whatsmeow.
3. **Ban risk é igual** entre Baileys e whatsmeow — ambos falam o mesmo protocolo WhatsApp Web reverse-engineered. ML do WhatsApp olha reply-ratio, contact-graph, temporal patterns — **trocar lib não te protege de ban**.
4. **Vantagem real de whatsmeow** é estabilidade de sessão long-running + footprint menor (~50MB vs ~80MB por instance) + mantenedor pago (Beeper). Baileys v7 fechou parte do gap (memory leak fixes).
5. **Time não tem Go expert hoje** — Wagner + Felipe + Maiara/Eliana operam TypeScript/PHP. Substituir = adicionar nova linguagem ao stack runtime crítico.
6. **Não há sinal cliente-qualificado** ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)) pedindo essa mudança. Sem cliente reportando dor ou métrica detectando drift, é hipótese sem âncora.

## Contexto

### Estado atual real (verificado 2026-05-27)

**Arquitetura WhatsApp do oimpresso ([ADR 0096](../0096-modulo-whatsapp-meta-cloud-api-direto.md), [ARCHITECTURE.md](../../requisitos/Whatsapp/ARCHITECTURE.md)):**

| Driver | Tipo | Status | Usado em |
|---|---|---|---|
| **`ZapiDriver`** | SaaS BR Z-API (não-oficial via Baileys deles) | ✅ default Sprint 1 | maioria PME — onboarding 5 min |
| **`MetaCloudDriver`** | Oficial Meta Cloud API | ✅ fallback obrigatório | gating duro FormRequest |
| **`BaileysDriver`** | Custom oimpresso (daemon Node CT 100) | ✅ Sprint 3 implementado | businesses com estrutura customizada |
| **`NullDriver`** | Dev/CI | ✅ Sprint 1 | Pest sem rede |
| **`EvolutionDriver`** | Evolution API | ❌ **PROIBIDO permanente** | nunca |

**Daemon Node TypeScript (`Modules/Whatsapp/daemon-node/`) — entregue:**

- **Versão Baileys:** `@whiskeysockets/baileys@7.0.0-rc11` pinned (migrado 6.7.18 → 7.0.0-rc11 em 2026-05-15)
- **Runtime:** Node 20 LTS, ESM-only, TypeScript estrito 5.6
- **HTTP:** Fastify 4.28 + zod validation + Bearer auth + IP whitelist Traefik
- **Persistência:** **`mysqlAuthState.ts`** — sessão Baileys em MySQL (não filesystem); migration script `migrate-fs-to-mysql.ts` testado
- **Segurança:** `rotate-encryption-key.ts` testado, secrets via Docker secrets, PII redacted em logs
- **Observabilidade:** OpenTelemetry SDK + Prometheus (`prom-client`) + Pino logger estruturado
- **Resiliência:** `antiBan.ts` (anti-ban heuristics custom) + `banDetector.ts` (Meta TOS heuristics) + `WebhookDispatcher.ts` (retry exponencial)
- **Multi-instance:** `InstanceManager.ts` orquestra N sessões; `Instance.historySync.test.ts` cobre sync histórico
- **Tests:** Vitest com `antiBan.test.ts`, `mysqlAuthState.test.ts`, `Instance.historySync.test.ts`, `InstanceManager.bootstrap.test.ts`, `media.test.ts`, `messages.history.test.ts`, `messages.interactive.test.ts`, `schemas.interactive.test.ts`, `schemas.test.ts`, `migrate-fs-to-mysql.test.ts`, `rotate-encryption-key.test.ts`, `health.test.ts`, `env.test.ts`

**PHP driver layer (`Modules/Whatsapp/Services/Drivers/`) — 12 classes:** `BaileysDriver`, `ChannelDriverFactory`, `DriverDoesNotSupport`, `DriverFactory`, `DriverHealthStatus`, `DriverInterface`, `MessageStatus`, `NotImplementedDriverException`, `NullDriver`, `WhatsappSendResult`, `ZapiDriver`, `MetaCloudDriver`.

**Pest tests existentes:** `BaileysDriverTest`, `MultiTenantIsolationTest`, `VerifyBaileysSignature`, `BaileysConnectJob` — ~15 testes.

**Runbooks existentes:** `baileys-daemon-deploy-ct100.md`, `baileys-troubleshoot-ban.md`, `baileys-upgrade-lib.md`, `migrar-baileys-7x.md`, `daemon-ct100-rebuild.md`.

**Capacidade CT 100:** ~30 instances WhatsApp Web (config `MAX_INSTANCES=30`), limite RAM ~80MB/instance × 30 = 2.4GB num CT 100 com 4GB.

### Métricas relevantes (sem dado real ainda — Wagner precisa preencher)

| Métrica | Valor atual | Threshold preocupante |
|---|---|---|
| Instances `BaileysDriver` ativas em produção | ? (Wagner) | ≥ 25 (próximo do MAX 30) |
| Bans Meta nos últimos 90 dias (qualquer business) | ? (Wagner) | ≥ 3 em 24h cross-tenant ([ADR 0096 §16.10](../0096-modulo-whatsapp-meta-cloud-api-direto.md)) |
| Container restart 24h | ? (Loki/Grafana) | > 1×/h ([ARCHITECTURE.md §16.7](../../requisitos/Whatsapp/ARCHITECTURE.md)) |
| Memory creep 7d (RSS daemon Node) | ? (Prometheus) | > 200MB drift acumulado |
| Hours Wagner debugando daemon/mês | ? (Wagner) | > 4h/mês ([ADR 0096 §16.11](../0096-modulo-whatsapp-meta-cloud-api-direto.md) trigger) |
| Auto-logout/desconexão sustained 5min+ | ? (Loki) | ≥ 3 ocorrências/semana por instance |

**Sem esses números, decisão é especulação.** A proposta inclui passo "preencher métricas reais antes de decidir".

### Pesquisa estado-da-arte 2026

**whatsmeow ([github.com/tulir/whatsmeow](https://github.com/tulir/whatsmeow))**

- **Mantenedor:** Tulir Asokan, arquiteto de bridges do **Beeper** (operação comercial Matrix↔WhatsApp em escala)
- **Linguagem:** Go (goroutines naturais para muitas conexões WebSocket persistentes)
- **Protocolo:** WebSocket direto (sem Puppeteer, sem emulador Android)
- **Footprint relatado:** ~50MB/sessão, "no memory creep in long uptimes"
- **Estabilidade:** "Sessions stay stable for weeks as long as the store is durable" — relato do Beeper sobre 1000+ sessões em produção ([Discussion #979](https://github.com/tulir/whatsmeow/discussions/979))
- **Multi-device:** nativo (companion mode + primary phone offline)

**WuzAPI ([github.com/asternic/wuzapi](https://github.com/asternic/wuzapi))**

- Wrapper REST sobre whatsmeow — equivalente conceitual ao nosso `daemon-node`
- Multi-tenant: cada user pode configurar próprio S3 storage (AWS/MinIO/B2)
- Multi-session nativa
- Comunicação WebSocket direta — sem browser headless

**Baileys v7 ([WhiskeySockets/Baileys](https://github.com/WhiskeySockets/Baileys/releases))**

- Versão atual do oimpresso: `7.0.0-rc11` (release candidate)
- **Memory leak fixes** — release notes mencionam "spikes decreased significantly"
- **QR code format novo** copiado da implementação whatsmeow (confirmação que houve copy-cat)
- Comunidade WhiskeySockets ativa, mas mantenimento é coletivo (vs Beeper pago)

**Ban risk — fato neutro:**

> "Both Baileys and WhatsAppMeow connect to WhatsApp by reverse-engineering the WhatsApp Web protocol, which carries inherent ban risks." — [kraya-ai blog](https://blog.kraya-ai.com/whatsapp-automation-ban-risk)
>
> WhatsApp ML olha reply-ratio (<10% → ban), contact-graph distance, temporal patterns (timing robótico). **Trocar de lib não muda esse risco.**

## Decisão proposta — 4 opções

### Opção A · **Status Quo + investir em Baileys** (não substituir)

Manter `BaileysDriver` + `daemon-node` em produção. Investir esforço em:

- `antiBan.ts` v2 — aprimorar heurísticas (jitter aleatório de timing, simulação de digitação, reply-ratio enforcement)
- Métricas reais (preencher tabela acima) → decisão data-driven daqui 90 dias
- Backport de boas práticas do whatsmeow pro nosso wrapper (estudo, não migração)
- Bump pra Baileys 7.0.0 stable quando sair (rc11 → final)

**Custo:** ~16-24h Wagner em 90 dias.
**Risco:** baixo (incremental).
**Ganho:** preserva 100% do investimento sunk; reduz dor real conhecida (memory leaks v6→v7).

### Opção B · **Substituir Baileys por whatsmeow direto** (rewrite daemon em Go)

Rewrite completo do `daemon-node` em Go. Manter contrato REST igual (PHP `BaileysDriver` não precisa mudar) ou usar SDK Go direto.

**O que se perde:**
- 4-5 sprints de Node/TypeScript daemon (antiBan, banDetector, mysqlAuthState, historySync, scripts migração, rotação chave)
- ~13 testes Vitest cobrindo o daemon
- 5 runbooks específicos Baileys
- Familiaridade do time (Wagner + Felipe operam TypeScript hoje, não Go)
- Migration Baileys 7.0.0-rc11 recém-feita (2026-05-15)

**O que se ganha:**
- ~37% redução footprint RAM/sessão (80MB → 50MB) — permite ~50 instances/CT 100 em vez de ~30
- Sessões mais estáveis em uptime longo (claim do Beeper)
- Mantenedor com incentivo econômico direto (Beeper) + telemetria de issues
- Go é mais resiliente que Node pra workloads de muitas conexões WebSocket persistentes

**Custo:** ~80-120h Wagner (rewrite + tests + canary + cutover) + risco operacional alto.
**Risco:** alto (Tier 0 — daemon é canal #1 de vendas).
**Ganho:** ROI só faz sentido se Wagner ultrapassar ~30 instances ativas E tiver dor real de bans/memory creep que Baileys v7 não resolveu.

### Opção C · **Substituir Baileys por WuzAPI wrapper** (menor custo que rewrite puro)

Adotar WuzAPI ([asternic/wuzapi](https://github.com/asternic/wuzapi)) como daemon — wrapper REST sobre whatsmeow já pronto, multi-tenant nativo, S3 storage.

**O que se perde:**
- Mesmas perdas da Opção B
- **+ controle sobre wrapper** — antiBan/banDetector custom não funcionam tal qual (depende do que WuzAPI expõe)
- Roadmap WuzAPI não está sob nosso controle (open-source community-driven, sem Beeper)

**O que se ganha:**
- Vantagens whatsmeow (footprint, estabilidade)
- Sem rewrite — adopta wrapper pronto
- Contrato REST conceitualmente similar ao nosso

**Custo:** ~40-60h Wagner (avaliar API gaps + portar PHP `BaileysDriver` pro contract WuzAPI + canary + cutover).
**Risco:** médio-alto (Tier 0 + perda de controle do wrapper).
**Ganho:** menor que B em custo, mas perde investimento de antiBan custom que é justamente o que justifica o daemon próprio ([ADR 0096 emenda 4](../0096-modulo-whatsapp-meta-cloud-api-direto.md) razão #3 "observabilidade").

### Opção D · **Híbrido experimental** ⭐ recomendado

Manter `BaileysDriver` + `daemon-node` em produção como **driver default não-oficial**.

**Em paralelo:**

1. **Construir `WhatsmeowDriver` (PHP) + `daemon-go` (Go ou WuzAPI containerizado)** como driver opcional novo. Custo controlado: 1 sprint dedicado, scope limitado a "ping + sendText + receiveWebhook" sem antiBan custom (Baileys daemon mantém esse papel pra biz existentes).
2. **Onboard 1 biz piloto novo no `WhatsmeowDriver`** — só clientes novos depois do PR, não migrar nenhum biz existente.
3. **Coletar métricas comparativas** 90 dias entre Baileys daemon (Z biz existentes) e Whatsmeow daemon (X biz novos):
   - Uptime sessão p95/p99
   - Memory RSS por instance
   - Auto-logout events/semana
   - Bans detectados (cross-tenant alarme)
   - Lag p50/p95 send/recv
   - Container restarts/semana
4. **Decision gate aos 90 dias** ([ADR 0105 cliente-como-sinal](../0105-cliente-como-sinal-guiar-sem-mandar.md) + métrica detectada):
   - Se Whatsmeow vencer em ≥4 métricas com diferença ≥30% → planejar cutover gradual Baileys → Whatsmeow (sprint dedicado)
   - Se empate ou Baileys vencer → arquivar `WhatsmeowDriver` como experimento (custo afundado aceito), continuar Baileys com `antiBan.ts` v2
   - Se Whatsmeow vencer apenas em 1-2 métricas → manter ambos como drivers válidos, biz escolhe via Settings

**Custo:** ~40-60h Wagner (sprint dedicado + monitoramento passivo 90 dias).
**Risco:** baixo (Tier 0 preservado — Baileys continua servindo prod existente).
**Ganho:** decisão data-driven em 90d, sem jogar fora sunk cost, sem comprometer produção.

## Justificativa da recomendação D

1. **Princípio "Cliente como sinal" ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md))** — não há cliente paga reportando dor com Baileys; nem métrica detectando drift sustentado. Hipótese pura não merece sprint Tier 0.
2. **Princípio "Loop fechado por métrica" (Constituição v2 #4, [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md))** — decisão de substituição precisa dado real, não opinião comparativa.
3. **Princípio "Confiabilidade com fallback" (Constituição v2 #8, [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md))** — Opção D preserva fallback automático Baileys ⤳ Meta Cloud já testado. Opções B/C criam janela de risco durante cutover.
4. **JANA Pro depende de custo marginal zero ([ADR 0140](../0140-jana-pro-produto-comercial-saas.md))** — qualquer substituição precisa preservar isso ou aumentar (não diminuir).
5. **ADR 0117 múltiplos números/business** — `WhatsmeowDriver` precisa demonstrar que suporta isso antes de ser candidato sério (whatsmeow tem multi-device nativo, então provavelmente sim; mas precisa prova prática).
6. **Estimates 10x recalibrado ([ADR 0106](../0106-recalibracao-velocidade-fator-10x-ia-pair.md))** — sprint dedicado Opção D é 40-60h reais (não 80-120h Opção B), entrega rápida.
7. **Skill `baileys-update-procedure` existe** porque updates Baileys deram problema em 2026-05-11 (5 traps catalogados em ~4h). Esse atrito é REAL — mas é menor que rewrite completo. Aprimorar a skill + antiBan v2 cobre.

## Consequências por opção

| Aspecto | A (Status Quo) | B (whatsmeow direto) | C (WuzAPI) | D (Híbrido) |
|---|---|---|---|---|
| Custo Wagner-horas | 16-24h | 80-120h | 40-60h | 40-60h |
| Sunk cost preservado | 100% | 0% | ~30% | 100% |
| Risco Tier 0 produção | baixo | alto | médio-alto | baixo |
| Decisão data-driven em 90d | ✅ | ❌ (pós-cutover) | ❌ | ✅ |
| Atende ADR 0105 (sinal cliente) | ✅ | ❌ | ❌ | ✅ |
| Atende Constituição #4 (loop fechado) | ✅ | ❌ | ❌ | ✅ |
| Hedge contra Baileys ban surge | médio | alto | alto | alto (após 90d) |
| Linguagem time precisa aprender | nenhuma | Go | Go (operacional) | Go (opcional) |
| Reversibilidade | trivial | difícil | difícil | trivial |

## Riscos da recomendação D

1. **`WhatsmeowDriver` pode revelar bug que Baileys não tinha** — mitigação: biz piloto novo, não migrar existente; cutover só se métricas confirmarem.
2. **Manutenção dupla 90 dias** — 2 daemons rodando, 2 stacks observabilidade. Mitigação: scope reduzido do Whatsmeow daemon (sem antiBan custom no piloto).
3. **WuzAPI vs whatsmeow puro — decidir antes** — proposta sugere experimentar com WuzAPI primeiro (menor custo) e migrar pra whatsmeow puro depois se WuzAPI mostrar limitação. Wagner aprova esse waterfall ou prefere whatsmeow puro desde o experimento?
4. **90 dias pode ser pouco** — Beeper Meta atualiza detection a cada 2-3 meses; 90d podem não capturar 1 ciclo completo. Mitigação: review_trigger automático a cada ciclo Meta detection (alarme cross-tenant em 3 businesses).

## Alternativas consideradas (não escolhidas)

- **Migrar todos os businesses pra Z-API/Meta Cloud só** — descartado pois ADR 0096 emenda 4 estabelece dor de observabilidade Evolution como razão suficiente pro daemon custom; mesma dor se aplicaria abandonar daemon custom.
- **BSP enterprise (Take Blip / Twilio)** — descartado por custo 30× ([ADR 0096 §"Provedores avaliados"](../0096-modulo-whatsapp-meta-cloud-api-direto.md)); reabrir se ≥3 businesses banidos no mesmo trimestre.
- **Esperar Baileys v8** — sem roadmap público; comunidade aberta WhiskeySockets não tem incentivo financeiro pra acelerar. Não é alternativa real.
- **whatsapp-web.js** — já PROIBIDO ([ADR 0096](../0096-modulo-whatsapp-meta-cloud-api-direto.md)). Não reabrir.

## Triggers de reabertura desta proposta

Aceitar Opção A/D não fecha a porta. Reavaliar imediatamente se qualquer destes ocorrer:

1. **≥3 businesses banidos em 24h** — alarme cross-tenant já configurado em [ARCHITECTURE.md §16.7](../../requisitos/Whatsapp/ARCHITECTURE.md). Acionar Opção B/C como emergência.
2. **Memory creep daemon Node > 200MB drift 7d** — sinal de regressão Baileys v7.
3. **Wagner gastar > 4h/mês debugando daemon** — gatilho já documentado em [ADR 0096 §16.11](../0096-modulo-whatsapp-meta-cloud-api-direto.md).
4. **≥25 instances ativas concorrentes** — próximo do limite CT 100 (~30). Foot­print menor whatsmeow viraria vantagem decisiva.
5. **Meta soltar TOS endurecendo Whatsapp Web automation** — ambos quebram, mas migrar pra whatsmeow pode ganhar dias-semanas de runway via Beeper response time.
6. **Cliente paga reportar fricção atribuível a Baileys** — sinal ADR 0105 ativo.

## Métricas de sucesso (se Opção D for aceita)

Sprint 1 do experimento entrega:

- [ ] `WhatsmeowDriver` PHP implementado (interface `DriverInterface` honrada)
- [ ] `daemon-go` ou `wuzapi-container` rodando em CT 100 com IP whitelist Traefik + Bearer auth
- [ ] FormRequest aceita `driver=whatsmeow` (com Meta Cloud fallback obrigatório, igual `baileys`)
- [ ] Migration `add_whatsmeow_columns_to_whatsapp_business_configs`
- [ ] OTel + Prometheus exportando métricas comparáveis
- [ ] Runbook `runbooks/whatsmeow-daemon-deploy-ct100.md`
- [ ] Pest test `WhatsmeowDriverTest` + `MultiTenantIsolationTest` extension
- [ ] 1 biz piloto novo ativo (não migrar existente)
- [ ] Dashboard Grafana `whatsmeow-vs-baileys-comparison`

Métricas a coletar 90 dias (gate Opção D):

| Métrica | Baileys (atual) | Whatsmeow (experimento) | Threshold decisão |
|---|---|---|---|
| Uptime sessão p95 | ? | ? | Whatsmeow ≥ 20% maior → vence |
| Memory RSS p99 / instance | ? | ? | Whatsmeow ≥ 30% menor → vence |
| Auto-logouts/semana/instance | ? | ? | Whatsmeow ≥ 50% menos → vence |
| Bans detectados (90d) | ? | ? | empate esperado |
| Lag p95 send | ? | ? | empate ou Whatsmeow vence em 100ms+ → vence |
| Container restarts/semana | ? | ? | Whatsmeow ≥ 50% menos → vence |

Whatsmeow vence em ≥4 de 6 → cutover gradual aprovado.

## Referências

- [ADR 0096](../0096-modulo-whatsapp-meta-cloud-api-direto.md) — Módulo Whatsapp drivers (mãe desta proposta)
- [ADR 0058](../0058-reverb-substituido-por-centrifugo-frankenphp.md) — Centrifugo runtime CT 100
- [ADR 0062](../0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100
- [ADR 0093](../0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípios duros)
- [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0117](../0117-multiplos-numeros-whatsapp-por-business.md) — Múltiplos números por business
- [ADR 0140](../0140-jana-pro-produto-comercial-saas.md) — JANA Pro custo marginal zero
- [ARCHITECTURE.md](../../requisitos/Whatsapp/ARCHITECTURE.md) — arquitetura módulo Whatsapp
- [runbooks/baileys-daemon-deploy-ct100.md](../../requisitos/Whatsapp/runbooks/baileys-daemon-deploy-ct100.md)
- [github.com/tulir/whatsmeow](https://github.com/tulir/whatsmeow) — biblioteca whatsmeow
- [github.com/asternic/wuzapi](https://github.com/asternic/wuzapi) — wrapper REST WuzAPI
- [github.com/WhiskeySockets/Baileys](https://github.com/WhiskeySockets/Baileys/releases) — releases Baileys
- [Discussion #979 whatsmeow](https://github.com/tulir/whatsmeow/discussions/979) — debate scale 10k devices
- [kraya-ai blog ban risk](https://blog.kraya-ai.com/whatsapp-automation-ban-risk) — análise ban risk 2026

## Aprovação

- [ ] Wagner escolhe opção (A/B/C/D ou outra)
- [ ] Se D: confirma "WuzAPI primeiro vs whatsmeow puro desde o experimento"
- [ ] Se D: confirma cliente piloto novo (não usar Larissa biz=4 ROTA LIVRE — produção crítica)
- [ ] Se A/D: confirma sprint `antiBan v2` aprovado paralelo
- [ ] Após decisão: virar ADR aceita (lifecycle `accepted`) OU rejected (lifecycle `rejected` com motivo)
