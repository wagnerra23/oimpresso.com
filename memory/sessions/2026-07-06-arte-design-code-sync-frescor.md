# Estado da arte 2026 — frescor design↔código (drift, proveniência, espelho vivo)

**Data:** 2026-07-06 · **Agent:** estado-da-arte · **Escopo:** manter fonte-de-design viva
(Cowork/DesignSync) em sincronia com o repo — detecção de drift, identidade de arquivo,
frescor de espelho. **Ponto de partida:** relatório adversarial que matou `cowork-mirror-freshness.mjs`.

> Pesquisa Fase 1 feita LIMPA (sem ler memória oimpresso). Só depois comparei com as camadas do projeto.

---

## Fase 1 — PESQUISE OS MELHORES

| Player | Como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|
| **Figma Code Connect + Webhooks V2** | Design é fonte. Webhook `FILE_UPDATE`/`FILE_VERSION_UPDATE`/`LIBRARY_PUBLISH` empurra evento quando o arquivo muda (não polling). Code Connect mapeia componente→snippet no Dev Mode. **NÃO sincroniza estrutura** — mapeia, humano mantém. | Padrão de facto de ecossistema; webhook push é o modelo canônico. **Limite documentado:** `FILE_UPDATE` é instável — debounce agressivo (só dispara após parar de editar; casos de não-disparo em publish/version). |
| **Tokens Studio → Style Dictionary (DTCG estável out/2025)** | Design é fonte, **git recebe espelho GERADO**: tokens editados no plugin → JSON DTCG commitado → **bot abre PR** no repo → Style Dictionary transforma em CSS/TS. Round-trip design→código automatizado por PR. | DTCG v1 estável (out/2025), suportado por Figma/Penpot/Sketch/Terrazzo. É **o** modelo "design vivo é fonte + git recebe mirror gerado por bot". |
| **Chromatic + Storybook (TurboSnap)** | Detecta drift comparando o **RENDER**, não o arquivo: screenshot de cada story por commit, diff pixel-a-pixel; TurboSnap só re-snapshota o que o git-diff afetou (−50-80% CI). Baseline por branch, promovida ao merge. | Referência em "drift = mudou o que o usuário vê", robusto a ruído de bytes (CRLF/encoding/ordenação não muda pixel). |
| **Builder.io / Supernova / Specify** | "**Code is source of truth**" (Builder): Figma é sugestão; o que conta é o que entra no código. Governança = lint de cobertura + CI que bloqueia merge + **IA aplica update de design direto via PR**. Shopify: 14% de UI drifta em 1 ano mesmo com adoção forte. | Prova que o oposto (código-fonte) também é estado-da-arte quando o design "detacha" fácil. Supernova/Specify = motor de distribuição de token com entrega de código automatizada. |
| **Git / Nx / Turborepo (content-addressable)** | Identidade = **hash de conteúdo NORMALIZADO por path completo**. Git normaliza EOL→LF no index ANTES de hashear (via `.gitattributes eol`), depois monta o path pela árvore. Nx/Turborepo: cache content-addressable keyed por arquivo+deps. | Ninguém sério compara bytes crus. A identidade canônica é `hash(normalize(conteúdo))` sob o **path inteiro**, nunca basename. |

**Veredito Fase 1 — os 4 modelos vivos em 2026:**
1. **Webhook push** (Figma) > polling/cron de diff. Os melhores recebem "mudou", não varrem.
2. **Design-fonte + mirror gerado por bot-PR** (Tokens Studio/DTCG) — quando o design é o dado primário (tokens).
3. **Código-fonte + governança no CI** (Builder.io) — quando o design detacha fácil e o código é o que embarca.
4. **Comparar RENDER** (Chromatic) > comparar bytes, pra "o design mudou de verdade".

Identidade de arquivo: **todos** = `hash(conteúdo-normalizado)` keyed por **path completo**. Byte cru é anti-padrão.

---

## Fase 2 — COMPARE COM O OIMPRESSO

O oimpresso tem 4 camadas reais (todas advisory, ADR 0314; wiradas no `design-memory-gate.yml`):
`ancora.mjs` (proveniência), `anchor-content-check.mjs` (correção), `cowork-ssot-guard.mjs`
(1 fonte), INDEX §0.2 (registro: projeto Cowork `019dcfd3`, mirror `prototipo-ui/cowork/`).

| Dimensão | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| **Proveniência da âncora** | Code Connect mapeia componente→fonte declarada | `ancora.mjs` computa âncora do charter, barra print-no-olho. **Iguala/supera** (máquina, não convenção) | **curta** — já bate |
| **Correção da âncora** | — (Figma não abre pra checar) | `anchor-content-check.mjs` abre o arquivo (MISSING/SHELL/NO-MODULE) | **curta** — oimpresso à frente |
| **1 fonte / anti-dupla-fonte** | SSOT implícito no design tool | `cowork-ssot-guard.mjs` enforça `cowork/` único | **curta** |
| **Identidade de arquivo** | `hash(normalizado)` por **path completo** | `mirror-freshness` (retirado) chaveava por **basename** + **md5 de bytes crus** → colisão homônima + falso-STALE por CRLF | **longa** — errado por construção |
| **Detecção de frescor** | webhook push (Figma) OU bot-PR (Tokens Studio) | md5(repo) vs md5(vivo via `DesignSync.get_file`), **manual/agente**, nunca em CI (auth `/design-login` interativa) | **longa** — teatro estrutural (só selftest roda no CI) |
| **Drift por render** | Chromatic pixel-diff | **inexistente** (Storybook/Chromatic não usados) | **longa** — camada ausente |
| **Modelo de fonte** | design-fonte+mirror-gerado (tokens) OU código-fonte+CI (Builder) | INDEX §0.2 diz git/Cowork é fonte, "diffar antes de concluir"; §0.2 **se contradiz**: "byte-idêntico… a diferença é só CRLF" (impossível) | **média** — modelo certo, definição incoerente |

**Honestidade:** a **proveniência+correção+SSOT** do oimpresso está no estado-da-arte ou à frente
(máquinas determinísticas, zero-LLM, ancoradas em charter — Figma não tem equivalente de "abrir o
arquivo e checar se bate"). O que quebrou foi **só o frescor**, e quebrou pelos 2 fundamentos que
os melhores acertam há anos: identidade normalizada-por-path e sinal push (não polling manual).

---

## Fase 3 — AVALIE O QUE FALTA (respostas diretas)

**1. A sentinela deve voltar? Em que forma?**
Sim, mas **não** como md5-manual. A auth interativa do DesignSync mata cron-com-secret e webhook
(a plataforma não expõe webhook — **registrar como limite da plataforma**). Forma correta em 2026,
por ordem de preferência: (a) **PR-bot que REGENERA o mirror** quando o agente logado exporta —
o diff do PR *é* a detecção de frescor (modelo Tokens Studio, sem gate especial); (b) na falta de
bot, **dispatch manual com SLA** (`workflow_dispatch` + agente logado gera o snapshot, roda `--compare`
com hash normalizado, loga resultado datado) — não vira gate required (0314: advisory honesto, mas
com **cadência logada**, não selftest-teatro). Cron-com-service-token está fora: DesignSync não tem
service account.

**2. Identidade de arquivo correta.**
`sha256(normalize(conteúdo))` keyed por **path completo** (`prototipo-ui/cowork/<subdir>/<arq>`),
nunca basename. Normalização mínima canônica: **(i) EOL→LF, (ii) strip BOM, (iii) trailing-newline
única, (iv) UTF-8.** Isso é exatamente o que o git faz no index (`eol=lf`) — o mirror "passa por
sorte" hoje porque `.gitattributes` normaliza; a sentinela tem que normalizar **explicitamente**,
não depender de sorte. md5 pode ficar (colisão irrelevante aqui), o pecado era o **basename + bytes crus**.

**3. "Comparar bytes" vs "comparar render" — qual primeiro?**
**Nenhum dos dois primeiro. Primeiro conserta a identidade** (hash normalizado por path) — é o pré-req
barato que destrava tudo. Depois: **render** (Chromatic-like) é a camada certa a *adicionar*, mas é
cara (Storybook não existe no projeto). Byte-normalizado é suficiente pro caso Cowork (`.jsx`/`.html`
são fonte, não render) e barato. Render fica pra V2 quando/se houver Storybook.

**4. Intenção do Wagner ("viver só da API") — é o estado da arte?**
**Meio-certo.** "Apagar as cópias e viver só do vivo" **não** é o estado da arte — Figma/Tokens Studio
mantêm **mirror no git de propósito** (git é o que embarca, roda offline no CI, versiona). O estado da
arte é o **oposto simétrico**: *design vivo é fonte + git recebe espelho GERADO por bot/PR* (Tokens
Studio/DTCG). Ou seja: as cópias **ficam**, mas param de ser mantidas à mão — passam a ser **saída
regenerável** de um export logado. Quem faz cada modelo: **Tokens Studio/Supernova/Specify** = design-fonte+mirror-gerado;
**Builder.io** = código-fonte+CI; **Figma Code Connect** = mapeia sem gerar. O §0.2 não precisa mudar
de fonte — precisa só remover a frase incoerente ("byte-idêntico… diff é CRLF") e trocar por
"idêntico sob normalização canônica".

### Top 5 ações (impacto × esforço IA-pair, ADR 0106 10×)

| # | Ação | Impacto | Esforço | Pré-req |
|---|---|---|---|---|
| 1 | **Corrigir identidade**: rescrever manifesto keyed por **path completo** + `hash(normalize)` (EOL/BOM/UTF-8) antes de re-wirar qualquer coisa | alto | ~30min | nenhum |
| 2 | **Corrigir §0.2 do INDEX**: trocar "byte-idêntico… CRLF" por "idêntico sob normalização canônica" (fecha a contradição que legitima o md5 cru) | alto | ~10min | nenhum |
| 3 | **Reintroduzir frescor como dispatch-com-SLA logado** (não gate required): `workflow_dispatch`, agente logado gera snapshot normalizado, `--compare`, resultado datado no ledger — mata o "selftest-teatro" | médio | ~1h | #1 |
| 4 | **Registrar limite de plataforma**: DesignSync não expõe webhook nem service-token → webhook/cron impossíveis; ADR curta fixando "dispatch-logado é o teto viável" (evita re-propor cron eterno) | médio | ~20min | nenhum |
| 5 | **Avaliar PR-bot regenerador** (modelo Tokens Studio) como V2: export vira PR, diff = frescor. Só ADR de decisão agora, implementação depois | alto (longo prazo) | ~40min ADR | #3 valida cadência |
| — | Render-diff (Chromatic/Storybook) | alto | alto (sem Storybook) | fora de escopo agora |

---

## RECOMENDAÇÃO

**Comece pela ação #1 (corrigir identidade) — alto-impacto, ~30min, zero pré-req bloqueante.** O código
foi retirado com razão, mas 2 dos 5 achados (basename + bytes crus) são bugs de *fundamento* que os
melhores resolveram há uma década; sem consertá-los, qualquer forma da sentinela nasce falsa. Fazer #1+#2
juntos (identidade + fechar a contradição do §0.2) desbloqueia reintroduzir a sentinela com honestidade.

**Próxima ação hoje:** reescrever o manifesto de `cowork-mirror-freshness.mjs` (recuperável em
`git show 19245cdef1:scripts/governance/cowork-mirror-freshness.mjs`) trocando `seen.set(base,…)` por
chave = path relativo completo, e `md5(readFileSync(abs))` por `sha256(normalize(readFileSync(abs,'utf8')))`
com `normalize = s => s.replace(/^﻿/,'').replace(/\r\n/g,'\n').replace(/\n+$/,'\n')`.

---

## Fontes (Fase 1)

- Figma Code Connect — https://help.figma.com/hc/en-us/articles/23920389749655-Code-Connect
- Figma Webhooks V2 — https://developers.figma.com/docs/rest-api/webhooks/ · tipos: https://developers.figma.com/docs/rest-api/webhooks-types/
- Design System Parity Figma vs Code 2026 — https://atomize.tools/blog/figma-design-system-parity-code-sync
- DTCG v1 estável (out/2025) — https://www.w3.org/community/design-tokens/2025/10/28/design-tokens-specification-reaches-first-stable-version/
- Tokens Studio → Style Dictionary — https://docs.tokens.studio/transform-tokens/style-dictionary · https://styledictionary.com/info/dtcg/
- Chromatic TurboSnap — https://www.chromatic.com/docs/turbosnap/ · guia 2026 https://qaskills.sh/blog/chromatic-storybook-visual-testing-guide
- Builder.io "code is source of truth" — https://www.builder.io/blog/governance-beyond-figma
- Supernova/Specify — https://www.supernova.io/blog/the-future-of-enterprise-design-systems-2026-trends-and-tools-for-success · https://specifyapp.com/
- Git EOL normalization / content-addressable — https://git-scm.com/docs/gitattributes · https://www.kernel.org/pub/software/scm/git/docs/technical/hash-function-transition.html
- GitHub Actions workflow_dispatch / auth — https://docs.github.com/actions/reference/authentication-in-a-workflow · https://oneuptime.com/blog/post/2026-01-25-github-actions-workflow-dispatch/view
