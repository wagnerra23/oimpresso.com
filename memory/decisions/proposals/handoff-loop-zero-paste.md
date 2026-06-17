---
proposal_id: handoff-loop-zero-paste
status: proposed
type: protocolo-handoff
created: 2026-06-17
proposed_by: claude-code
decided_by: wagner            # PREENCHER-NO-ACEITE
decided_at: PREENCHER-NO-ACEITE
parent_adr: "ADR 0114 (prototipo-ui/PROTOCOL.md)"
related_adrs: [0114-prototipo-ui-cowork-loop-formalizado, 0107-emendation-0104-visual-comparison-gate-f3, 0094-constituicao-v2-7-camadas-8-principios, 0093-multi-tenant-isolation-tier-0]
resulting_adr: "NNNN — a numerar no aceite ([CL])"
title: "Loop de handoff zero-paste — repo fonte única, sem auto-merge até a rede existir"
---

> **Ao ACEITAR:** [CL] converte pra ADR numerada (frontmatter `type: adr` + `slug`/`number`/`decided_at` per `scripts/memory-schemas/adr.schema.json`), move pra `memory/decisions/NNNN-…`, roda `memory-schema-preflight`.

# ADR NNNN — Loop de handoff zero-paste (repo-nativo · **sem auto-merge até a rede existir**)

> **Status:** 🟡 PROPOSTA por [CC] em 2026-06-17 — **aguarda autorização de [W]**.
> **Red-teamada e verificada** contra os workflows reais (`[AH]`, log:
> `memory/sessions/2026-06-17-adversario-handoff-loop.md`). Conclusão mudou:
> **liga só a Fase 0 (1-clique de [W]); auto-merge fica BLOQUEADO até 5 controles existirem e
> serem auto-testados.** Soberania: [CC] propõe; [W] decide; [CL] numera/commita (ADR 0094 §10).

---

## 0. Contexto — "[W] quer sair do meio"
Autorizado por [W] em 2026-06-17. O loop atual (F1 [CC] no Cowork → F3 [CL] no repo) tem 3 dores:
**(1)** [W] é o fio de integração (copia/cola + lembra de conferir); **(2)** duas línguas — Cowork
manda `.om-*`/oklch cru, o repo é Tailwind+tokens, o Code **improvisa**; **(3)** o gate "pronto
quando…" vive na memória de [W]. A meta é mecanizar o transporte e o DoD.

**Mas "sair do meio" tem um teto duro, descoberto pelo red-team (§3):** dá pra tirar [W] do
**transporte** e da **revisão linha-a-linha** — **não** da **decisão de merge**, ainda. Os gates que
substituiriam [W] **não cobrem** o que o auto-merge miraria.

---

## 1. O que JÁ EXISTE vs o que é A-CONSTRUIR (verificado no `.github/workflows/`)

| Peça | Status real | Evidência |
|---|---|---|
| Conformance (cor-crua DS) | ✅ determinístico, existe | `conformance-gate.yml` + `scripts/conformance-gate.mjs` |
| Score UI determinístico | ✅ existe, mas **cego** | `UiDeterministicScorer.php:215-224` — regex 10/4; **não** mede render/vazamento/XSS/lógica |
| a11y | ⚠️ **não cobre Pages** | `a11y-axe-gate.yml` é path-scoped a `Components/ui/**`; jsdom não vê contraste |
| Multi-tenant / Tier-0 | ❌ **SKIP-AS-PASS em `.tsx`** | `multi-tenant-gate.yml:57-63` só roda em `Modules/.../Controllers/`; `.tsx` → verde sem rodar |
| scope-guard por-handoff | ❌ **não é isso** | `scope-guard.yml` = controller vs `SCOPE.md` (`bin/check-scope.php`); `files_json` = 0 no git |
| Assinatura `sig` + `ingest`/`pending`/`ack` | ❌ a-construir | fundação `Modules/TeamMcp/Services/McpTokenIssuer.php` existe; tools de handoff = 0 linha |
| Auto-merge → deploy | ⚠️ **acoplado e imediato** | `deploy.yml:12-19` publica em prod no push pra main; `paths-ignore` **não** inclui `resources/js/**` |

---

## 2. Decisão

### R1 — Entrega SEMPRE repo-nativa e auditada contra o `main` (vale JÁ, risco zero)
- [CC] **lê os arquivos reais do `main`** antes da ponte; feito → "NÃO TOCAR", inexistente → descarta.
- Diff na **língua do repo** (Tailwind + tokens existentes). **Proibido** `.om-*` cru / oklch literal.
- **Pronto quando:** cita **arquivo+linha do arquivo sobre o qual afirma** (não do vizinho — foi assim
  que a auditoria errou `ContextSidebarV4` vs `Index.tsx:437`) e marca o que não muda.

### R2 — O barramento é o repo, não o clipboard de [W] (vale JÁ, risco baixo)
- Handoff commitado; canal canônico **único** = `prototipo-ui/COWORK_NOTES.md` (as ~9 cópias em
  `_BACKUP-NAO-USAR/`/exports/worktree `epic-hermann` = arquivo morto, declarar). Sem URLs efêmeras.

### R3 — DoD por máquina, com a honestidade do que os gates NÃO checam
- Gates required precisam passar. **Mas** (verificado §1) os gates atuais **não checam**, num `.tsx`:
  vazamento cross-tenant, XSS (`dangerouslySetInnerHTML`), PII em log, contraste, nem render. Logo
  R3 sozinha **não** habilita auto-merge — só sustenta o 1-clique informado de [W] (Fase 0).

### Emendas pós-red-team
- **E1 — gate de score é determinístico, mas cego.** Tirar o juiz LLM foi certo; o regex que entra
  (`UiDeterministicScorer`) é reproduzível porém não vê segurança/qualidade. **Não** é gate de merge —
  é sinal. _(antes eu vendia E1 como suficiente; é INSUFICIENTE.)_
- **E2 — NÃO promover tier-0/multi-tenant a required como "rede". É TEATRO.** `multi-tenant-gate`
  faz SKIP-AS-PASS em `.tsx` (`:57-63`) → check verde que não testou o handoff. Promover dá **falsa
  segurança, pior que nada**. _(reversão total da E2 anterior.)_
- **E3 — "presentacional" é por CONTEÚDO do diff, não por extensão.** `.tsx` **não** é inerte: dirige
  dados via `router.*`/`useForm`/`only:[]`/query e injeta HTML. Allowlist `.tsx`/`.css` é insuficiente.
  Elegível a auto-merge só diff **render-only** (sem `router`/`useForm`/`only:`/`data:`/
  `dangerouslySetInnerHTML`/fetch). _(MITIGA-PARCIAL → vira regra de conteúdo.)_
- **E4 — digest pós-merge é INSUFICIENTE e reintroduz o humano.** Controla **depois** do dano
  irreversível (prod já serviu Larissa; tenant vazado/XSS não voltam) e depende de [W] LER.
- **E5 (nova) — desacoplar auto-merge de `deploy.yml`.** Auto-merge ≠ auto-deploy imediato. Exige
  janela de quarentena/canário antes de publicar em prod (senão merge ruim = prod ruim em minutos).
- **E6 (nova) — qualquer automação de fechamento é FAIL-CLOSED.** Sem `ack`/digest no SLA → **pausa**
  o loop, não solta. Gate flaky → bloqueia, nunca "passa na dúvida".

---

## 3. Red-team verificado — os 3 furos que sozinhos barram o auto-merge
(log completo: `memory/sessions/2026-06-17-adversario-handoff-loop.md`)

1. **Gates Tier-0 não mordem `.tsx` (E2 é teatro).** Vazamento cross-tenant (o pior bug do projeto)
   passa verde via `only:[]`/query num `.tsx` "presentacional" — `multi-tenant-gate.yml:57-63`
   SKIP-AS-PASS; `Cliente/Index.tsx` é cheio de `router.reload({only})`.
2. **A infra de contenção inteira é a-construir.** `sig`/`ingest`/`ack`/`files_json` = 0 linha.
   Auto-merge confiaria num escopo que nenhum código verifica.
3. **Auto-merge = deploy de prod imediato e irreversível-de-fato.** `deploy.yml` publica no push;
   `git revert` não recolhe tenant vazado / XSS já executado.

**Os 5 controles que PRECISAM existir + passar no `gate-selftest.yml` (fixture adversarial) antes de
ligar auto-merge:** (a) lint **render-only** por conteúdo de diff; (b) **scope-guard `files_json`**
real; (c) assinatura com **nonce + expiração + bind**; (d) **quarentena/canário** antes do
`deploy.yml`; (e) **digest fail-closed com SLA**.

---

## 4. Rollout — para na Fase 0 até a rede existir
- **Fase 0 (autorizável já — risco baixo):** R1 + R2 + assinatura básica + `pending`/`ack`. **Para em
  auto-PR + 1-clique de [W].** [W] sai do **transporte** e da **revisão linha-a-linha** — ainda clica
  o botão de merge. **Ganho real, sem risco novo.**
- **Fase 1 (BLOQUEADA):** auto-merge só Camada-4 **render-only** — **somente depois** dos 5 controles
  (§3) existirem e passarem fixture boa/ruim no `gate-selftest`. **Nunca** Camada 1/2 (Shell/Fundações;
  ex.: a *Onda A* drawer é human-gated por construção).
- **Fase 2 (norte):** sync Cowork→repo (zero paste). LLM critique permanece advisory.

---

## 5. Proibições (duro)
- ❌ Ligar auto-merge antes dos 5 controles (§3) passarem no `gate-selftest`.
- ❌ Tratar `multi-tenant-gate`/`tier0-guards` como rede pra `.tsx` (SKIP-AS-PASS).
- ❌ Auto-merge acoplado a `deploy.yml` sem quarentena (E5).
- ❌ Auto-merge de `.php`/controller/migration/route, Camada 1/2, ou `.tsx` com `router`/`useForm`/
  `only:`/`dangerouslySetInnerHTML` (E3).
- ❌ `ingest` aceitar handoff sem `sig` válida; `ack=applied` sem `gate_status` verde (422).
- ❌ SECRET no Cowork/Code (só pipeline export + servidor MCP; rotacionar se vazar).
- ❌ Juiz LLM como bloqueador de merge (é advisory); `Cache::flush()` global.
- ❌ Deletar handoff → nova `version`, anterior `superseded` (append-only, ADR 0003).
- ❌ [CC] entregar `.om-*` cru; [CC] escrever no git / numerar ADR.

## 6. Checklist de [W] — Fase 0 (UMA VEZ)
- [ ] SECRET de assinatura → `config/teammcp.handoff_secret` (env servidor MCP) + secret do export Cowork. Não versionar.
- [ ] Emitir token MCP scope `handoff.pending` + `handoff.ack` pro ator-Code (`McpTokenIssuer`).
- [ ] Autorizar [CL] a construir Fase 0 (assinatura + `pending`/`ack`, **sem** auto-merge).
- [ ] (Fase 1, futuro) priorizar os 5 controles + fixtures no `gate-selftest`.

## 7. Residual honesto
Mesmo na Fase 0, [W] ainda clica o merge — de propósito. O ganho é deixar de **transportar** e de
**revisar linha-a-linha**: chega um PR repo-nativo, auditado, em tokens, com screenshots. Auto-merge
só quando "confiar" virar "mecanismo provado" (os 5 controles auto-testados). Reverter via `git revert`
(append-only); nada some.

## 8. Decisões abertas pra [W]
1. **Autoriza a Fase 0** (R1+R2+assinatura+`pending`/`ack`, sem auto-merge)?
2. **Canal R2** = `COWORK_NOTES.md` agora + MCP/sync como norte — confirma?
3. **Numerar/PR:** autoriza [CL] a numerar esta ADR e abrir PR (após `memory-schema-preflight`)?

## 9. Histórico
| Data | Autor | Mudança |
|---|---|---|
| 2026-05-09 | [W]+[CC] | PR #295 — PROTOCOL v1.0 |
| 2026-06-17 | [W]+[CC] | ADR handoff-v2 + ORDENS zero-paste (propostas) |
| 2026-06-17 | [CC] | v-corrigida — gate determinístico, tier0-required, auto-merge faseado |
| 2026-06-17 | [CC]+[AH] | **v-red-team (esta):** E2 revertida (teatro), E1/E3/E4 insuficientes, +E5/E6, **auto-merge bloqueado até 5 controles auto-testados**; furos verificados em `multi-tenant-gate.yml:57`/`deploy.yml:12`/`scope-guard.yml` |
| 2026-06-17 | [W] | _aguardando autorização_ |
