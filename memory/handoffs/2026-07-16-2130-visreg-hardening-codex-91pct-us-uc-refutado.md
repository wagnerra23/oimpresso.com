---
date: 2026-07-16
time: "2130"
slug: visreg-hardening-codex-91pct-us-uc-refutado
tldr: Fechou o plano do Codex de 64% → 91% (10/11) — a zona cinza esvaziou SOZINHA (12→1→0), sem [W] aprovar drift. Antes disso, 2 céticos + 7 verificadores refutaram "US ou UC como canal de pedido do dono" — o canal real, medido, é o CHAT.
owners: [W]
---

# Handoff — hardening do gate visual (plano Codex) + veredito US/UC + higiene de labels

## TL;DR do próximo passo

O **item 7** é o único aberto do plano do Codex, e **a nota 4,5 da auditoria foi refutada** (honesta ≈7,0). Chip `task_b0073212` está rodando e já mergeou as sub-fases 3a/3b (#4385, #4387, #4388). **Não siga a receita da auditoria cegamente** — os 5 erros dela estão no chip e resumidos abaixo.

O **#3916** (98 verdes, 0 falhas) espera **só [W]**: o corpo diz *"Merge = batch APROVADO"*.

---

## 1. Veredito US vs UC — o pedido do dono NÃO é US nem UC

**Pergunta [W]:** *"onde eu deveria informar apenas os US? ou UC?"*

**Resposta medida (2 céticos + 7 verificadores contexto-zero, ~1.3M tokens, tudo re-medido em git/gh):** nenhum dos dois. Consolidado no **#4345** (lápide §5 + `how-trabalhar.md §Pedido de tela/feature`).

| Claim | Veredito |
|---|---|
| Precedência + trio sem US (nenhum dos 27 required exige US pra tela nova) | ✅ CONFIRMADO |
| `_pendente_` conta como coberto (`anchor-lint.mjs:542`); 967 US, 372 pend, 327 ok, 84.2% | 🔧 números batem 1:1 — **mas o gate de entrada NÃO é advisory** |
| Zero US/UC escritos por [W] sozinho em 60d (197 commits, 182 co-authored) | ✅ CONFIRMADO |
| Ordem de nascimento (Csat 61d sem US) | 🔧 razão real **47:26** (não 56:26); **279 de 352** US nasceram sem âncora |
| Mecânica do casos-gate (UC órfão quebra required) | 🔧 `[BACKLOG]`: 53 totais, **48** sem id |
| Contrato visual 2 telas + `criar-tela.mjs` não toca SPEC | ✅ CONFIRMADO |

**2 correções que eu devia:**
1. **O gate de entrada É required desde 2026-06-24** (PR #3320, confirmado via `gh api`). Eu tinha dito advisory. O que é grandfathered é só a dívida legada (264/392).
2. **O git NÃO é cego ao MCP** — `tasks-create` persiste via SPEC→git→webhook→DB. Só 1 row DB-only catalogada em 2 meses (US-RB-052).

**Canal real, medido:** **CHAT**. [W] pede em linguagem natural → agente propõe → [W] aprova no chat → agente roda `tasks-create` + SPEC + PR. Zero sessões no último mês começaram puxando task via `my-work`.

---

## 2. Plano do Codex: 64% → **91% (10/11)**

O Codex ficou sem créditos com 16/17 PRs mergeados. Retomei e fechei a cadeia.

| # | Critério | Nota orig | Estado |
|---|---|---|---|
| 1,2 | Page direta / universo de arquivos | 8,0 / 5,0 | ✅ #4339 |
| 3 | Consumidores (grafo reverso de imports) | 4,0 🔴 | ✅ #4341 |
| 4 | Contrato source → rota → render | 7,0 | ✅ #4342 |
| 5 | Baseline explícita | 9,0 | ✅ #4353 |
| 6 | Canário anti-verde (ledger) | 8,5 | ✅ #4349 |
| **7** | **Fidelidade da captura** | **4,5 🔴** | ⏳ **chip — nota REFUTADA (≈7,0)** |
| 8 | Cobertura (3→8 E2E; universo 279→234) | 4,0 🔴 | ✅ #4343/#4350/#4351/#4352 |
| **9** | **Threshold e aprovação humana** | 6,5 | ✅ **#4364** |
| 10 | Ambiente + Firefox/WebKit | 6,0 | ✅ #4348 + #4356 |
| 11 | Evidência CI (`screen-coverage-gate` required, 28 contexts) | 7,5 | ✅ #4354 |

### A cadeia que fechou o item 9 (ordem imposta pela evidência)

**#4369 → #4366 → #4373 → #4364.** Zona cinza: **12 → 1 → 0**.

**[W] NUNCA aplicou o label `visreg-gray-approved`.** A zona cinza esvaziou sozinha quando as baselines certas entraram — o gate entrou **armado e sem dívida**, em vez de gravar drift como aceito.

### Dois trabalhos prontos e invisíveis (mesmo padrão, 2×)

1. **Lote órfão do Codex (#4369):** ele dividiu 59 baselines em 3 PRs (núcleo 6 · estados 20 · **fluxos 33**), publicou 2 e ficou sem crédito. O commit `93cd8528d3` existia íntegro **só numa branch local**, nunca no remoto. 11 das 12 telas da zona cinza eram esse lote.
2. **Baselines do #4373:** o dispatch (run `29527490569`) **gerou as 59 corretamente** e morreu no step "Abrir PR" — **403 do `COWORK_BOT_PAT`**. Branch ficou 25min com 0 snaps. Recuperei do artifact.

**→ O `get-secret.sh`/Vaultwarden (gap Tier 0) é o que fecha essa classe.** Ver §4.

---

## 3. Item 7 — a auditoria errou 5 pontos (NÃO seguir a receita)

Veredito do cético: nota honesta **≈7,0**, não 4,5.

1. **Linha errada** — o force de Arial está na **177**, não na 140.
2. **"data/hora" ERRADO — a máscara é JUSTIFICADA**: `Carbon::setTestNow` NÃO alcança o render (browser = processo HTTP separado, `ServerManager.php`); e `Sells/Create.tsx:1129` cai em `nowLocalIso()` = `new Date()` **no browser** → muda minuto a minuto.
3. **Receita insuficiente**: "fixar fontes no ambiente" não vence o `@font-face` do **Google Fonts CDN com `display=swap`** (`inertia.blade.php:30-32`). Precisa self-host + `document.fonts.ready`.
4. **"testar controles" já é feito**: **33 snaps ENFORCING × 3 viewports** (`visreg-flows.json`).
5. **Acoplamento invisível**: só **6** (PixelBaselineTest) usam `baselineFile`+`screenshot()`; as outras **53** nascem via `assertScreenshotMatches`, que **também injeta Arial** (`MakesScreenshotAssertions.php:19-27`). Remoção ingênua estoura 53 de 59.

**O que é real:** o force cega **font-family** (só ela — size/weight/spacing seguem cobertos) num gate bloqueante.

**Progresso do chip:** 3a (comment rot, #4385) e 3b (select, #4387) ✅. Faltam 3c (self-host fontes, 59 baselines) e 3d (fullPage, 59 baselines + risco de skeleton via `Inertia::defer`).

---

## 4. Achados de infra (fora do plano)

**CT 100 caiu ~1h15 — e NÃO foi o servidor.** Medido:
- `eth0: Gained carrier 15:59:56` e **nunca mais piscou** → **o cabo não caiu** (refuta a 1ª hipótese do #4374)
- `uptime` contínuo → a máquina nunca caiu depois do reset de [W]
- **4.598** falhas `derp.Recv`/`bootstrapDNS`; 8.8.8.8 **e** 1.1.1.1 **e** os 5 DERPs mortos ao mesmo tempo
- **→ o suspeito é o `192.168.0.1`** (roteador/link). Nenhum container faz isso.

**Sem sinal de invasão** (varredura): `btmp` vazio desde 1º/jul, 1 login histórico (console 29/abr), 3 users (`root`/`maiara`/`felipe`), 1 chave SSH, portas quase todas em localhost, cron 100% `/opt/oimpresso-*`, 22 containers reconhecíveis. **Limite honesto:** é LXC (`dmesg: Operation not permitted`) — o host Proxmox me é invisível; e não rodei `debsums`/anti-rootkit.

**"Robin" não existe** no CT 100 — nenhum container/service/processo/user. No repo só como *round-robin*. Se vive no roteador, é lá a resposta.

**Compose do CT 100 versionado (#4381):** a stack (`traefik`+`portainer`+`vaultwarden`) vivia **só no servidor**, sem git/PR/CI. Reverb erradicado (ADR 0058): bloco do compose + container órfão + imagem 964MB. Provado `git == servidor` via `docker compose config` resolvido.

**Gap Tier 0 do Vaultwarden — mais perto do que o canon diz:** `bw` v2026.6.0 ✅, `get-secret.sh` ✅, e o **`.vaultwarden-agent-creds` EXISTE** (perm 600) — o canon dizia "AUSENTE". É **100% placeholder comentado**; falta [W] descomentar 3 linhas. Ver §6.

---

## 5. Higiene: os labels que mentiam (#4346)

**Strike 2 da classe** (P14 2026-07-01 foi o strike 1). Um verificador adversarial foi **enganado** por `anchor-lint` imprimindo `Gate de entrada (advisory)` quando é **required desde 2026-06-24**.

**As 2 máquinas óbvias caíram (medido, não estimado):**
- **Gate de vocabulário** (grep `advisory` em job required): **130 falso-positivos em árvore limpa (100% FP)** — obrigaria apagar verdades (o `TERMINAL_VALIDO` do memory-health, as 5 fixtures do gate-selftest).
- **Label derivado do baseline**: o baseline **laga o vivo por design** — flip 08/07 (#3972), baseline 12/07 (#4167) = **4 dias** de mentira automatizada **com certificado de frescor**.

**O que ficou (subtração):** o lint parou de declarar enforcement. Script afirmando status de gate é **violação de camada**. Convenção na §5: **fato datado em passado sim, afirmação em presente não**.

**8 sites corrigidos**, incl. o `nfebrasil-pest.yml` **auto-contraditório** (L11 "não derruba" × L90 "agora MORDE") num gate **fiscal** ×150 clientes, e o `gate-selftest.mjs:708` se auto-rotulando advisory.

---

## 6. Pendências [W] (só ele pode)

1. **#3916** — batch MV, **98 verdes / 0 falhas**. *"Merge = batch APROVADO"*.
2. **Vaultwarden `claude-agent`** — criar user + API key + **descomentar 3 linhas** em `/root/.vaultwarden-agent-creds` + compartilhar itens. DoD: `get-secret.sh hostinger-api-token` retorna o token. **Fecha o gap Tier 0 e a classe do 403 do PAT.**
3. **Roteador `192.168.0.1`** — logs da janela 16:05→17:25. E o que é o "Robin".
4. **Resíduos do Reverb**: A record `reverb.oimpresso.com` → 177.74.67.30 órfão; 4 vars `REVERB_*` no `.env`.
5. **Oficina dark**: 2 das 6 colunas ainda renderizam claras (`bg-muted/40` translúcido) — raio **Fundação**, chip aberto, exige mandato [W].
6. **`/opt/docker-host/` deveria virar `git pull`** do canon — enquanto for cópia manual, o canon é sugestão, não lei.

---

## 7. Lições da sessão (o instrumento contaminando a medida — 3×)

1. **Grep de OOM casou com os meus próprios comandos** — o `tailscaled` loga o texto do SSH, e eu já rodara greps com "out of memory". Quase reportei causa errada da queda.
2. **`docker compose config` "DIFERE"** — era artefato meu: rodei de `/tmp`, onde o Docker não acha o `.env`. Quase reportei drift inexistente.
3. **`git ls-files | grep '.snap$'` → 0 (falso)** — o git escapa `·` U+00B7 e cita entre aspas. Use `core.quotepath=false`.

**E a lição que virou canon (#4375):** eu **olhei** o dark da Oficina e disse "é estética"; a outra sessão **mediu por elemento** e achou **1,92:1** — AA reprovado. Medida agregada mascara par ruim. É o **strike 2 do LC-06**, e valeu pra mim primeiro.

**Varredura parcial:** [W] perguntou *"olhou todos?"* sobre os PRs abertos. Eu tinha olhado **3 de 9**. Ao olhar todos, achei **3 verdes** parados só por required que nunca reportou — #4321, #3914, #4370 mergearam com um rebase.

---

## Estado MCP no momento do fechamento

**`brief-fetch`** (SessionStart): HITL pending [W] = 2 · Brain B 0% · flags 🟢 (migration/PRs/visual-regression) · Brief #369.

**`my-work`** (6 em REVIEW): US-TR-309, US-TR-310, US-PG-008, US-PROD-027, US-TR-305, US-TR-306.

**`git log origin/main`**: `d249a36dc9` — já com #4385/#4387/#4388 do chip.

**PRs desta sessão (12/12 mergeados):** #4345 · #4346 · #4358 · #4364 · #4369 · #4373 · #4375 · #4381 + os destravados por rebase: #4321 · #3914 · #4370 · #4068.

**Chips ativos:** `task_b0073212` (item 7 — rodando, 3 PRs já). Encerrados: `task_c55c89fe` (→ #4366/#4367/#4373), `task_e9110a47` (→ #4368), `task_681bc138` (→ #4344/#4347).
