---
slug: arte-whatsapp-naoficiais
title: "Estado da arte — libs WhatsApp não-oficiais (whatsmeow/WuzAPI vs Baileys vs Evolution vs WAHA) + decisão evoluir-vs-trocar"
type: session
authority: advisory
lifecycle: ativo
session_date: '2026-06-18'
quarter: 2026-Q2
related:
  - '0286'  # channel_health corroborado por mensagem real (nosso fix)
  - '0204'  # whatsmeow daemon (WuzAPI CT 100)
  - '0202'  # Baileys descontinuado / whatsmeow in
  - '0096'  # Meta Cloud API direto (default universal)
  - '0268'  # broadcast opt-in/HSM (vetor de ban)
pii: false
---

# Estado da arte — libs WhatsApp não-oficiais (2026-06)

> **Doc VIVO.** A paisagem dessas libs muda rápido (Meta muda protocolo, libs
> ganham/perdem manutenção). Revise a cada incidente relevante de canal ou ~trimestre.
> Última revisão: **2026-06-18** (ver "Log de revisões" no fim).

> Origem: incidente 2026-06-18 — banner "WhatsApp · Suporte fora do ar" falso na
> Caixa/Atendimento. Verificação ao vivo no daemon CT 100 provou o canal **no ar**
> (logado, ~48 msg/h, webhook 200). Disparou esta pesquisa: estamos no melhor stack
> não-oficial? Ver [ADR 0286](../decisions/0286-channel-health-corroborado-por-mensagem-real.md).

## Veredito

**A base (whatsmeow) é a melhor entre as não-oficiais — a escolha está certa.** O elo
fraco não é o whatsmeow, é o *wrapper* **WuzAPI** (asternic): simples, mantido por ~1
dev, e — confirmado na doc — **não expõe o evento `LoggedOut`** (raiz do nosso falso
"fora do ar"). O concorrente que resolve isso melhor é o **WAHA**, que roda o *mesmo*
whatsmeow no motor GOWS, porém com API madura e multi-motor. Evolution/Baileys = mais
instáveis e/ou maior risco — não são upgrade.

## Tabela 1 — stacks não-oficiais

| Stack | Base / protocolo | Ling. | Estabilidade sessão | REST pronto | Ponto fraco crítico | Ban (uso bulk) |
|---|---|---|---|---|---|---|
| **whatsmeow** (lib) | WA multidevice nativo | Go | ★★★★☆ a mais estável; pouco memory-leak/auto-logout | não (é lib) | exige tratar eventos você mesmo | 🔴 2–8 sem |
| **WuzAPI** ← *nosso wrapper* | whatsmeow | Go | herda whatsmeow | sim (simples) | **não assina `LoggedOut`; sem "All"** → sessão zumbi invisível; manutenção 1-dev | 🔴 2–8 sem |
| **Baileys** | WA multidevice (WebSocket) | TS/Node | ★★★☆☆ auto-logout + memory-leak a escala | não (lib) | menos estável que whatsmeow | 🔴 2–8 sem |
| **Evolution API** | Baileys (+Cloud API opcional) | TS/Node | ★★☆☆☆ "instance stuck", sync perdido pós-reboot, erro 515 | sim (completo) | instabilidade recorrente; **PROIBIDO Tier 0** (ADR 0096) | 🔴 alta |
| **WAHA** | 3 motores: **GOWS=whatsmeow** / NOWEB=Baileys / WEBJS=browser | Go+Node | ★★★★☆ (GOWS) | sim (Docker 1-click, polido) | WAHA Plus é pago; +1 dependência | 🔴 alta (menor no WEBJS) |
| **wppconnect / Venom** | WA Web (browser/puppeteer) | TS/Node | ★★★☆☆ | sim | pesado (Chromium/sessão) | 🟠 1–3 meses (browser dura+) |
| **Cloud API (Meta)** | oficial | — | ★★★★★ | oficial | custo/conversa + verificação Business | 🟢 ~zero |

## Tabela 2 — problemas × soluções

| # | Problema | Camada | Causa raiz | Solução | Status oimpresso |
|---|---|---|---|---|---|
| 1 | Banner "fora do ar" falso | app | confia no `loggedIn` do WuzAPI | corroborar com **inbound real** | ✅ ADR 0286 / PR #2985 |
| 2 | "Sessão ativa" pintado de erro | app | contrato `paired` ≠ `connected` | unificar vocabulário | ✅ PR #2984 (mergeado) |
| 3 | **WuzAPI não repassa `LoggedOut`** | wrapper | só assina Message/ReadReceipt/HistorySync/ChatPresence (+Connected/Disconnected) | (a) probe periódico ✓ · (b) **WAHA-GOWS** · (c) fork WuzAPI add evento | 🟡 mitigado; raiz aberta |
| 4 | Sessão zumbi (connected sem fluxo) | lib/protocolo | logout remoto não capturado | **health por mensagem real** | ✅ ADR 0286 |
| 5 | Ban por bulk/uniforme | protocolo | detecção 4 camadas: fingerprint · cadência robótica · denúncia · IP | jitter humano + opt-in + HSM + não-bulk; volume crítico → Cloud API | 🟡 broadcast opt-in/HSM (ADR 0268); disparo fase 2 pendente |
| 6 | Memory-leak / auto-logout | lib | conhecido no **Baileys** | já estamos no whatsmeow | ✅ N/A (escolha certa) |
| 7 | Meta muda protocolo e quebra | protocolo | toda lib é reverse-eng | manter lib atualizada + **fallback Cloud API** | 🟡 roadmap US-WA-310 |
| 8 | "Sua conta pode estar em risco" | protocolo | afeta whatsmeow **e** Baileys (é o método, não a lib) | reduzir sinais; contas críticas no oficial | 🟡 estratégico |

## Evoluir vs Trocar — análise sincera

**A pergunta certa não é "trocar de lib", é "trocar de wrapper".** A lib base
(whatsmeow) é a *mesma* no WuzAPI e no WAHA-GOWS. Logo:
- Trocar pra **Baileys/Evolution** = downgrade (menos estável, mesmo/maior ban; Evolution é Tier 0 proibido). **Descartado.**
- Trocar pra **WAHA** = na real é trocar o **wrapper**, mantendo a base. Risco menor que trocar de lib, mas ainda é **reescrever a ponte** (driver + reconciler + ingestão de webhook + provisioning + QR + mapeamento multi-tenant).
- **Evoluir** = manter WuzAPI + camada de app (dor aguda já resolvida) + opcional fechar a raiz.

| Critério | Evoluir (WuzAPI + app) | Migrar wrapper (WAHA-GOWS) |
|---|---|---|
| Base / protocolo / ban | whatsmeow | whatsmeow — **zero ganho aqui** |
| Dor aguda (falso fora do ar) | ✅ já resolvida no app | resolvida nativa |
| Raiz `LoggedOut` | mitigada por probe; patch WuzAPI possível | **nativo (ganho real)** |
| Multi-engine (WEBJS p/ ban) | ❌ não tem | ✅ hedge |
| Sustentabilidade da dependência | WuzAPI = 1 dev, comunidade pequena (**risco**) | devlikeapro, backing comercial |
| Custo | grátis | WAHA Plus pago (multi-sessão) |
| Esforço de migração | ~0 (já investido) | **alto** (reescrever WhatsmeowDriver + Reconciler ADR 0206 + webhook + QR + Tier 0) |
| Risco de migração | baixo | médio-alto (regredir a ponte que já funciona) |

**Conclusão honesta: EVOLUIR agora; WAHA é plano B com POC time-boxed.** O que doeu
(falso fora do ar) já foi corrigido no app, *independente* do wrapper. O ganho real do
WAHA (LoggedOut nativo + multi-engine) não justifica, hoje, reescrever uma ponte que
funciona. "Copiar WAHA" no bom sentido = adotar os **padrões** dele sem migrar: eventos
de ciclo de sessão completos (incl. logout), endpoint de health, multi-engine como
conceito — parte disso dá pra trazer pro WuzAPI/app sem trocar nada.

### Quando trocar pra WAHA (gatilhos)
Migra o wrapper SE/QUANDO:
1. WuzAPI ficar **sem manutenção** > ~6 meses (risco de dependência morta); **ou**
2. Gaps de ciclo de sessão custarem **> N incidentes** que o probe não cobre; **ou**
3. Precisar do motor **WEBJS** (browser) pra contas ban-sensíveis.
Senão → fica e evolui.

### O hedge estratégico real não é o WAHA — é o Cloud API
Para tenants **críticos / alto volume**, o caminho oficial (Meta Cloud, **US-WA-310**
Embedded Signup) é o único **ban-zero**. Modelo certo: **não-oficial (whatsmeow) por
custo/flexibilidade + oficial por criticidade**. Já é o default universal do app (ADR 0096).

## Recomendação ranqueada (impacto × esforço)

1. **✅ Feito** — corroboração no app (#2984/#2985). Alto impacto, baixo esforço.
2. **🔬 POC WAHA-GOWS** (time-boxed, paralelo no CT 100, 1 canal de teste) — valida LoggedOut nativo + webhook + multi-sessão; de-risca a migração futura sem comprometer. Médio/médio.
3. **🎯 Acelerar Cloud API (US-WA-310)** pra tenants críticos — único ban-zero. Alto impacto estratégico.
4. **📉 Broadcast fase 2 com rate-limit + jitter humano** (ADR 0268) — maior vetor de ban; nascer com cadência humana.

> **Nuance de ban:** os artigos gritam "2–8 semanas" pensando em **bulk/spam**. O uso
> real do oimpresso — inbox **humano** + broadcast **opt-in/HSM** — é o cenário de
> **menor** risco. O perigo mora no disparo em massa (fase 2), não no atendimento.

## Como manter este doc vivo

Revisar quando: (a) incidente de canal relevante; (b) WuzAPI/WAHA mudarem manutenção
ou features; (c) ~trimestral. Em cada revisão, atualizar as tabelas + a data no topo +
acrescentar linha no log abaixo.

### Log de revisões
- **2026-06-18** — criação. Pós-incidente falso "fora do ar"; veredito "evoluir, não trocar"; POC WAHA como plano B; Cloud API como hedge estratégico.

## Fontes (2026-06)
- whatsmeow — github.com/tulir/whatsmeow · disc #979 (whatsmeow > Baileys estabilidade) · issue #810 ("conta em risco" afeta ambos)
- WuzAPI — github.com/asternic/wuzapi · API.md (eventos assináveis: Message/ReadReceipt/HistorySync/ChatPresence — **sem LoggedOut/All**)
- Evolution API — github.com/EvolutionAPI/evolution-api · issues #1153 (instance stuck = zumbi), #2026 (sync lost pós-reboot)
- WAHA — github.com/devlikeapro/waha · 3 motores (GOWS/NOWEB/WEBJS); WAHA 2025.3 GOWS 1.0
- Ban risk 2026 — blog.kraya-ai.com/whatsapp-automation-ban-risk (tabela de risco + 4 camadas de detecção)
- Alternativas 2026 — indiehackers (wppconnect/Venom posicionamento), apidog top-10
