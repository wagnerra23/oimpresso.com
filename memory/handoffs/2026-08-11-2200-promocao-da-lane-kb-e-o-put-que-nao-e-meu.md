---
slug: promocao-da-lane-kb-e-o-put-que-nao-e-meu
date: "2026-08-11"
time: "22:00"
tldr: "Promoção da lane KB a required feita na camada de baseline (#5643: 43→44 contexts, terminal required, promote_by removido) + errata dos textos stale (#5622). O PUT na protection NÃO foi aplicado: escrita em branch protection é bloqueada pro token do agente, e o canon diz que o flip é Wagner-only por desenho. Estado seguro: protection-drift 🔴 aponta a lacuna e não é required, então bloqueia nada."
autor: "[CL] Claude Code"
sessao: eloquent-easley-37d93a
prs: [5622, 5643]
next_steps:
  - "[W] aplicar o PUT na protection (web UI, selecionando da lista) OU pedir reversão do baseline pra 43 — deixar 🔴 permanente treina o time a ignorar o detector"
  - "Decidir descarte da branch claude/kb-flake-403-probe (andaime, não vazou pra main)"
  - "Avisar a sessão da branch claude/kb-403-arraystore — hipótese refutada por medição"
  - "Drift herdado: hooks-manifest-generate --check falha em main (advisory, não introduzido aqui) — rodar --write em PR próprio"
related_adrs:
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0093-multi-tenant-isolation-tier-0
---

# Handoff — a promoção da lane KB, e o PUT que não é meu

> Continuação de [`2026-08-11-1900-o-403-da-lane-kb-nunca-foi-flake`](2026-08-11-1900-o-403-da-lane-kb-nunca-foi-flake.md)
> (mergeado, append-only). Aquele fecha a causa-raiz; este cobre o que veio depois.

## O que fechou

**[#5622](https://github.com/wagnerra23/oimpresso.com/pull/5622) — errata.** O header do
`kb-pest.yml` e o `anchor` do `gates-registry.json` ainda diziam "flakiness Spatie
order-dependent" e "flake AINDA vivo, não-determinístico". Ambas falsas depois do #5606.
Não é cosmético: já havia uma branch (`claude/kb-403-arraystore`) perseguindo a hipótese
refutada. O texto antigo **não foi apagado** — ganhou marcador `SUPERADO` + errata datada
(fato datado em passado sobrevive; afirmação em presente apodrece).
Isso fecha o 1º `next_step` do handoff anterior.

**[#5643](https://github.com/wagnerra23/oimpresso.com/pull/5643) — promoção, camada
baseline.** Flip [W] explícito. `governance/required-checks-baseline.json` 43 → 44
contexts; `gates-registry.json` `terminal: advisory → required`, `promote_by` removido
(padrão das outras 12 entries required).

## O que NÃO fechou — e por que não é falha de caminho

**A protection viva continua em 43.** O `PUT` não foi aplicado.

Medido, não suposto: `GET` no sub-recurso funciona; `PUT` e `PATCH` devolvem **404** —
inclusive um **no-op com payload idêntico ao vivo**, o que descarta o meu payload como
causa. Token OAuth (`gho_`) com escopo `repo` e `admin=true` no repo; o GitHub mascara 403
como 404 nesses endpoints.

E o canon confirma que isso é **desenho, não gap**:
`.github/workflows/sdd-scorecard-ratchet.yml` diz textualmente *"Flip (Wagner-only,
ADR 0275 §5)"*, e `protection-drift.yml` roda com `contents: read` — só detecta, nunca
aplica. Não existe automação pra isso **de propósito**. Consultei o
[`_INDEX-SECRETS`](../_INDEX-SECRETS.md) antes de concluir (regra Tier A): o único token
com escrita é `COWORK_BOT_PAT`, que só existe no runtime do Actions e cujo valor é só [W].

⚠️ **Não construir automação pra isso.** Seria criar caminho de escalação de privilégio
pra ato Tier 0 que o canon reserva a [W].

## Estado é seguro (verificado, não presumido)

| item | estado |
|---|---|
| `protection-drift` | 🔴 — aponta corretamente a lacuna baseline(44) × vivo(43) |
| esse 🔴 bloqueia merge? | **NÃO** — `protection-drift` não está entre os 45 required |
| `baseline-tamper-guard` | ✓ nenhum afrouxamento (a mudança foi **aditiva**: +1, −0) |
| protection intacta | 43 contexts · `enforce_admins=true` · `linear=true` · force-push off |

O 🔴 é o alarme correto dizendo *"decisão tomada, enforcement pendente"*.

## Como [W] termina (1 passo)

Recomendado pelo **web UI** (Settings → Branches → `main` → Require status checks),
porque lá se **seleciona da lista** em vez de digitar — e digitar é exatamente o que
deadlockou `main` em 2026-07-02 (o `·` U+00B7 double-encodado pelo shell do Windows).
Nome do context:

```
PHP / Pest (KB · MySQL)
```

Validar depois por **bytes**, nunca por contagem (mojibake preserva a contagem):

```bash
node scripts/governance/protection-drift.mjs
```

Payload já gerado e verificado nesta sessão (18× `C2 B7` corretos, **0** mojibake, sem
BOM) — mas ele vive no scratchpad da sessão; o caminho reprodutível é gerar do baseline
canônico conforme
[`RUNBOOK-branch-protection`](../requisitos/Infra/RUNBOOK-branch-protection.md).

## Pré-requisito anti-deadlock, verificado ANTES do flip

O incidente 2026-08-05→08-08 deadlockou 4 PRs por 2 dias porque o check recém-promovido
**não nascia** nos PRs já abertos. Medido aqui antes de mexer: **11 de 11 PRs abertos já
possuíam** `PHP / Pest (KB · MySQL)`, porque esta lane nasceu *always-run com skip-as-pass
interno* ([ADR 0271](../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md)
onda 2). O vetor não existe aqui.

## Encaixe na política da ADR 0314 (required = Tier-0)

A lane defende multi-tenant Tier 0 via `CrossTenantIsolationTest` — que **só passou a ser
elegível hoje**: antes do [#5604](https://github.com/wagnerra23/oimpresso.com/pull/5604)
ele só tinha asserts negativos, então 403-em-tudo passava e o gate **não conseguia ficar
vermelho**. Promover um gate que não pode reprovar teria sido teatro.

## Nota de método

O que separou "payload torto" de "sem permissão" foi um **`PUT` no-op** com os mesmos 43
contexts do vivo: falhou igual. Sem esse controle eu teria passado a sessão mexendo no
payload — que estava certo desde o começo.

Também registro um vício que reapareceu: um `rc=0` que eu quase reportei como sucesso era
o exit do `tail` depois do pipe, não o do comando. A evidência válida foi a **mensagem**
impressa, não o código de saída.

## Ressalva honesta

Não consigo distinguir se o 404 é **permissão** ou **método**: o runbook prescreve
`-X PUT` nesse sub-recurso, mas a API documenta `PATCH` (o `PUT` é do `/protection`
inteiro). Como **nenhum dos dois** escreveu, não tenho evidência pra corrigir o runbook —
e não vou "consertá-lo" no palpite. Fica declarado como **não-verificado**, não como
defeito.

## Estado MCP no fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `brief-fetch` (SessionStart) → 5 HITL pendentes [W]; 671 US não atribuídas (520 sem dono);
  SDD composta 55,2 (Δ-0,2)
- Nenhuma ADR nova — a promoção usa o calendário da ADR 0275, não cria decisão
