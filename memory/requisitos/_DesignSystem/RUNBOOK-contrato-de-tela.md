# RUNBOOK — Contrato de Tela (a perna de fidelidade visual do trio-de-tela)

> **Data:** 2026-06-18 · **Owner:** Wagner · **Origem:** sessão 2026-06-18 (Wagner: _"o design não está sendo aplicado em produção… o protocolo tem erros na aplicação… crie o mecanismo, não me deixe errar de novo. Crie um adversário e valide tudo antes de aplicar"_).
> **Validação:** método v1 validado por **2 adversários** (processo + técnico) que DERRUBARAM o v0 antes de aplicar — ver §"O que morreu (v0)".
> **Plugado em:** charter (`*.charter.md`) · casos ([ADR 0264](../../decisions/0264-governanca-executavel-trio-de-tela.md)) · claim-evidence (`infra-contract-required.yml` + [`INFRA-CONTRACT.md`](../../templates/INFRA-CONTRACT.md)) · anti-tautológico (`.claude/hooks/nudge-test-contract-anchor.ps1`) · [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) (Wagner aprova screenshot).
> **NÃO cunha ADR** (Tier 0 = decisão do Wagner). Este RUNBOOK documenta o padrão; a ratificação como ADR fica a critério do [W].

---

## 1. O padrão de erro (por que o design não chega em produção)

A tradução **Cowork (design) → Inertia/React (produção)** vem divergindo de forma sistemática e **invisível**. Cinco modos de falha, todos observados com evidência na sessão de 2026-06-18:

1. **Importação que nunca completa.** O conector do Claude Design não autentica na sessão do agente (`CLAUDE_CODE_OAUTH_TOKEN` não expande design scopes). O passo 1 — trazer o design — trava na largada e se repete sem mudar.
2. **Base errada.** A sessão abriu num worktree **vazio** (0 arquivos) numa branch **769 deleções atrás** de `origin/main`; o repo tem **20+ worktrees**. Trabalho cai em código velho, em worktree desconectado, ou colide com sessão paralela.
3. **Fonte-da-verdade fantasma.** `prototipo-ui/SYNC_LOG.md` **vazio** apesar de meses de Cowork; o protótipo canônico da Caixa foi movido pra `_BACKUP-NAO-USAR/`; **3 versões** do protótipo sem fonte única.
4. **Handoff com premissa stale.** O próprio charter admite: _"o handoff chegou com premissa stale ('a tela não avisa')"_ — o design descreve trabalho já feito ou assume código que mudou.
5. **Paridade auto-certificada.** Um comparativo anterior se deu _"12/15 paridade"_ que o [W] contesta. **Não existe diff automático protótipo↔prod.** Pior: o "score de design" que existe (`prototipo-ui/audit/score-mechanized.mjs` → `design-report.json`, **nota 99**) mede **higiene de token** (sem hex cru, sem `<select>` nativo) — **não fidelidade ao design**. Verde ≠ "bate com o protótipo".

### Diagnóstico preciso (a perna que falta)

Uma tela tem **três pernas de qualidade**. O projeto mecanizou duas e deixou a terceira no olho humano:

| Perna | Contrato | Check mecânico |
|---|---|---|
| Comportamento | charter "Métricas vivas" + Pest `R-*` | ✅ `casos-coverage-guard` / Pest |
| Uso | `*.casos.md` (UC Dado/Quando/Então) | ✅ G-2 manifesto `casos-test-results.json` |
| **Fidelidade visual** | **— nenhum —** | ❌ **só `score-mechanized` (higiene, não fidelidade)** |

**A divergência visual acumula calada porque não há leg que a prenda.** O "Contrato de Tela" é essa leg — **não um sistema novo, a perna que falta**, no mesmo idioma das outras.

---

## 2. O método (v1 validado) — "Contrato de Tela"

Princípio (já é a lei do projeto, via `nudge-test-contract-anchor`): **ancorar no CONTRATO externo, não no código.** O contrato visual é **declarado por um humano** (no charter), **não extraído** do protótipo (extração = tautologia + protótipo-fantasma). O check é **estático e determinístico** (sem render, sem auth, sem CDN). O juízo subjetivo (cor/ícone/densidade/hover) **continua humano** (screenshot, ADR 0114) — este gate **não** o automatiza.

### 2.1 O artefato: contrato visual no charter

Cada tela ganha uma seção `## Contrato visual` no `*.charter.md` (declarada pelo design/[W]), com, por seção da tela:

- **`id`** — slug estável (ex: `lista-conversas`, `thread`, `composer`, `contexto`).
- **`copy`** — strings **literais** que o design exige (ex: `"Selecione uma conversa"`, `"Canal fora do ar — reconecte pra enviar"`).
- **`estados`** (opcional) — nomes de estado (`empty`/`loading`/`selected`/`error`).
- **`ordem`** — sequência das seções top→down.

E, no nível do contrato (não da seção), `acordos_estado` (opcional) — o vocabulário de `state` que backend e frontend **têm de falar igual** (catraca semântica · §2.3b).

### 2.2 A ponte: âncoras `data-contract`

O protótipo é Cowork-CSS (`.om-*`, `oklch()`); o prod é Tailwind + tokens semânticos. **Não existe match de classe↔classe não-tautológico.** A ponte é uma âncora explícita no JSX de produção:

```tsx
<section data-contract="lista-conversas"> … </section>
<div data-contract="thread"> … </div>
```

A âncora é a única coisa que existe **idêntica** nos dois sistemas sem um mapa-de-equivalência mantido à mão (= a whitelist que engole divergência).

### 2.3 O gate: `scripts/contrato-de-tela.mjs` (estático, determinístico)

Pra cada seção do contrato, contra os arquivos-alvo da tela (`.tsx`/`_components`):

1. **Âncora presente** — `data-contract="<id>"` existe → senão **FALHA** (`seção <id> sem âncora`).
2. **Copy literal presente** — cada string `copy` existe no alvo → senão **FALHA** (`copy ausente: "<str>"`). _(divergência de copy = o caso mais barato e mais valioso de pegar.)_
3. **Ordem** — a sequência das âncoras no fonte = a `ordem` do contrato → senão **FALHA** (`ordem divergente`).

Cor: **não** automatiza match OKLCH↔Tailwind (tautológico). Reaproveita `prototipo-ui/ds-guard.mjs` / `ds:canon:check` (sem hex cru + token semântico).

### 2.3b Catraca semântica: acordo de `state` backend↔frontend ([ADR 0286](../../decisions/0286-channel-health-corroborado-por-mensagem-real.md) §5)

A catraca 2a valida **presença** (âncora + copy + ordem), não **semântica** — o acordo de *valores* entre backend e frontend. Buraco real (incidente 2026-06-18, [PR #2984](https://github.com/wagnerra23/oimpresso.com/pull/2984)): o `connect` devolve `state:'paired'`, o `status` devolve `state:'connected'`, e o `ReconnectModal` só reconhecia `'connected'` → a resposta de sucesso _"Canal já pareado — sessão ativa"_ caía no ramo de **erro vermelho**. Contrato estruturalmente presente, comportamento quebrado — **passou no gate**.

O contrato pode declarar `acordos_estado`: um VOCABULÁRIO de `state` compartilhado por um conceito (ex: "sessão ativa = sucesso"). O gate prova que **cada `state` acordado aparece como literal entre aspas nos DOIS lados** — o `backend` que o EMITE e o `frontend` que o TRATA. Ainda estático/determinístico (regex sobre o fonte, sem render/auth/DB), mesmo idioma do check de copy:

```json
"acordos_estado": [
  { "id": "sessao-ativa", "verdict": "aprovado", "escopo": "global",
    "valores": ["paired", "connected"],
    "backend":  "Modules/Whatsapp/Http/Controllers/Admin/ChannelsController.php",
    "frontend": ["resources/js/Pages/Atendimento/CaixaUnificada/_components/reconnectState.ts"] }
]
```

**Como casa (anti-teatro):** o literal só conta em **CÓDIGO** — comentários (`/* */`/`//`/`#`) são removidos antes, com strip **string-aware** (um `'http://x//y'` não é confundido com comentário) — e só em **posição de VALOR** (a chave `'paired' => true` não conta como emissão; senão um rename `paired→pareado` passaria verde). O gate prova **menção nos dois lados**, não *handling* (ver Honestidade abaixo). `frontend` é opcional (default = `alvo`). `escopo` (default `global`, sem `.` → sem path-traversal) e `verdict` (default `aprovado`) ancoram o eixo veredito-por-zona escopado por tenant (proposal veredito-ledger).

Três modos de FALHA:

4. **Ignorância total** — o `backend` emite `<state>` mas o `frontend` NÃO o **menciona em código** → **FALHA** (`… o frontend NÃO menciona "<state>" em código`). _Pega a forma do bug `paired`≠`connected` (frontend totalmente sem o state) — não um handler errado que ainda menciona o state (isso é o vitest)._
5. **Drift de contrato** — `valores` declara um `<state>` que o backend NÃO emite (valor morto / renomeado) → **FALHA** (`estado "<state>" declarado mas o backend não emite`).
6. **Escopo inválido** — `escopo` fora do formato (`global` | `vertical:<x>` | `cliente:biz=<n>` | `persona:<p>` | `tela:<rota>`) → **FALHA** (typo/traversal que mis-escoparia o veredito · risco Tier 0).

Travado por self-test (17 controles): `4b.1` positivo, `4b.2` o bug, `4b.3` drift, **`4b.4` comment-blindness**, **`4b.5` key-false-match**, `4b.6/4b.7` escopo válido/inválido, **`4b.8` strip string-aware** (`//` em URL não come o state), **`4b.9` escopo path-traversal**.

> **Honestidade (o que a catraca É e NÃO é):** é uma **catraca de regressão** — trava o vocabulário que um humano JÁ declarou em `valores`; **não descobre** uma divergência nova ainda não-declarada. Prospectivamente ela não teria *achado* o bug de 2026-06-18 (ninguém tinha declarado o acordo); ela impede o **des-conserto** silencioso dele. **Limite conhecido (match léxico):** ela checa *menção*, não *handling* — um `'paired' | 'connected'` num tipo TS satisfaz o gate mesmo se o handler tratar só um. Quem prova o handling é o vitest `tests/reconnect-session-active.test.ts` (#2984); a catraca pega (a) drift/rename no **backend** e (b) **frontend totalmente ignorante** do state, e amarra a costura PHP↔TS que o vitest não enxerga.

### 2.4 Desvios legítimos: claim-evidence (não backdoor de prosa)

Desvio **intencional** do protótipo (lucide no lugar de glyph, Tailwind no lugar de `oklch`, densidade decidida) é **declarado** no corpo do PR — mesmo idioma do `infra-contract-required`:

```
<!-- design-deviation: glyph "W" → <Send/> lucide (canon Cockpit V2 ADR 0110) -->
```

Visível, rastreável, atribuído — **não** um campo "justificativa" que o réu preenche livremente dentro do relatório.

### 2.5 Higiene de base: preflight (Catraca 1)

`scripts/contrato-de-tela.mjs --preflight` antes de codar:

- `git merge-base --is-ancestor origin/main HEAD` — **ancestralidade**, não igualdade (não quebra em rebase/merge legítimo). Falha = branch atrás → rebase.
- worktree órfão (0 arquivos trackeados) = FALHA; diff que remove > 30% dos arquivos = **WARNING** (assinatura do `git worktree --no-checkout`, near-miss catalogado).
- **Advisory por 1–2 semanas** pra medir falso-positivo; depois CI required sob `enforce_admins`.

### 2.6 Omissão: reusa o padrão claim-evidence

O dano real do handoff stale é **omissão** (some um símbolo/rota/teste que o handoff nunca citou). Isso **já tem dono**: o padrão `infra-contract-required` (PR-body section + `evidence-override`). A adoção **estende o escopo desse gate** pros arquivos-alvo de design-port, em vez de criar um terceiro mecanismo. _(diff→handoff, nunca handoff→diff — inverte a fonte pra pegar o omitido.)_

---

## 3. As 3 condições inegociáveis (senão é teatro — veredito dos adversários)

1. **Fonte-da-verdade consertada ANTES de qualquer gate.** 1 protótipo canônico versionado por tela (sair do `_BACKUP-NAO-USAR/`, matar as 3 versões). O design entra **uma vez** (ZIP/anexo → versionado), e o contrato é declarado a partir dele. Gate sobre vazio = teatro garantido.
2. **O gate que morde roda em CI required sob `enforce_admins` — nunca hook local, nunca `continue-on-error`, nunca skip-as-pass.** O repo tem 63 workflows, ≥6 advisory; `visual-regression.yml` **mergeou vermelho 2× em 24h**. Se o [W] precisa do botão `--admin`, o gate não pode ser required — escolha uma das duas.
3. **Veredito mecânico; o réu não escreve a justificativa.** Copy/âncora/ordem saem do `contract.json` vs `.tsx`, binário. Desvio só via `<!-- design-deviation -->` explícito e visível. **Sem "12/15" auto-atribuído, sem screenshot que só prova "renderizou".**

---

## 4. O que morreu (v0 "Fidelity Lock") — registro pra não recriar

Os adversários derrubaram, com evidência do repo:

- ❌ **Screenshot pareado em CI** — inviável: protótipo precisa servidor+3 CDNs+Babel; prod precisa login+tenant+PII. Em CI passa **verde quando os dois lados renderizam erro** (login vs CDN 429). 
- ❌ **"Falha na 1ª divergência injustificada"** — `injustificada` é backdoor de prosa que o agente preenche. Auto-certificação "12/15" com mais YAML.
- ❌ **"Extrair o contrato do protótipo"** automaticamente — frágil (split de JSX muda) e tautológico; e o protótipo-fonte hoje é fantasma (`_BACKUP`).
- ❌ **Adversário de handoff validando a lista de claims do próprio handoff** — viés de seleção; claim omitido = não-verificado = verde. Trocado por: diff→justificativa (claim-evidence existente).
- ❌ **Match OKLCH↔Tailwind mecânico** — exige um mapa mantido à mão = a whitelist tautológica. Cor fica com `ds-guard` (higiene) + olho humano (fidelidade).

---

## 5. Adoção (ordem)

0. **Fonte-da-verdade:** trazer o bundle do design atual (ZIP), versionar em `prototipo-ui/`, declarar a versão. _(Pré-req inegociável #1.)_
1. **Instrumentar a tela** com âncoras `data-contract="<id>"` (uma vez por tela).
2. **Declarar `## Contrato visual`** no `*.charter.md` (seções/copy/ordem) — pelo design/[W].
3. **Ligar o gate advisory** (`scripts/contrato-de-tela.mjs`) — medir falso-positivo 1–2 semanas.
4. **Promover a required** sob `enforce_admins` quando estável; estender o escopo do `infra-contract-required` pros arquivos de design-port (omissão).
5. **Screenshot do prod → [W] aprova** (ADR 0114) — o juízo visual subjetivo que o gate não cobre.

Piloto: **Caixa Unificada** (`resources/js/Pages/Atendimento/CaixaUnificada/`) — não tem `casos.md` (gap) e é a tela que originou esta sessão.

---

## Refs

- [ADR 0264 — Governança executável (trio-de-tela)](../../decisions/0264-governanca-executavel-trio-de-tela.md)
- [ADR 0114 — Loop Cowork formalizado (Wagner aprova screenshot)](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0110 — Cockpit Pattern V2 (origem dos desvios lucide/Tailwind)](../../decisions/0110-cockpit-pattern-v2-ativacao.md)
- [`INFRA-CONTRACT.md`](../../templates/INFRA-CONTRACT.md) + `infra-contract-required.yml` — padrão claim-evidence (Default-FAIL + Evidence Opening)
- `.claude/hooks/nudge-test-contract-anchor.ps1` — ancorar no contrato, não no código (anti-tautológico)
- `prototipo-ui/ds-guard.mjs` / `ds:canon:check` — higiene de cor/token (reaproveitado, não duplicado)
- `prototipo-ui/audit/score-mechanized.mjs` — **prova de que a métrica atual mede higiene, não fidelidade** (nota 99 sem olhar o protótipo)
- `scripts/contrato-de-tela.mjs` + `.test.mjs` — o gate (este RUNBOOK)
