# LIÇÕES [CODE] — erros de código a não repetir

> Escopo: **código backend/infra** (PHP, Eloquent, jobs, migrations, controllers, services, CI).
> Equivalente de `LICOES_CC.md` (que cobre design/[CC]) pro lado de engenharia.
> Subordinado a `memory/proibicoes.md` (canônico). **Append-only.**
> Lido no início de toda sessão pelo hook `licoes-code-two-strikes.ps1`.
>
> **Por que existe:** fechar o elo manual do loop de aprendizado — quando uma classe de
> erro de IA-code repete, ela precisa virar **defesa mecânica** (gate/hook/baseline), não
> ficar só na memória de quem lembrou. Origem: sessão 2026-06-06 (Wagner: "meu sistema está
> preparado a evoluir quando esses erros aparecem? quando deve ser acionado o aprendizado?").
>
> ## Regra "two-strikes" (gatilho do aprendizado)
> - **1ª ocorrência** de um erro → conserta o bug. NÃO codifica gate ainda.
> - **2ª ocorrência da mesma `Classe`** → PARA. Vira defesa mecânica (gate/hook/baseline).
> - **Chegou em PROD / cliente pagante** → codifica SEMPRE (mesmo na 1ª). Espelha ADR 0105.
> - **Override de gate usado** → revisa se o gate está errado (aprendizado reverso).
>
> O hook alarma quando uma lição tem `Ocorrências >= 2` **e** `Gate: none`.
>
> ## Formato por entrada (campos lidos pela máquina — não renomear)
> ```
> ## LC-NN — <título curto>
> - **Erro:** o que aconteceu
> - **Sintoma:** como apareceu
> - **Regra:** o que fazer pra não repetir
> - **Classe:** <slug-estável>   (agrupa ocorrências da mesma família)
> - **Ocorrências:** <int>       (incrementar a cada vez que a classe reaparece)
> - **Gate:** none | <nome-do-gate/hook/baseline que já impede>
> - **Ref:** <ADR/PR/sessão>
> ```
> Ao consertar um erro de código: ache a `Classe` aqui. Existe? Incrementa `Ocorrências`.
> Não existe? Cria `LC-NN` novo com `Ocorrências: 1`. Quando virar gate, troca `Gate: none`
> pelo nome do gate e o alarme some sozinho (catraca — só sobe).

---

## LC-01 — Query sem global scope vazando entre tenants
- **Erro:** Eloquent/Service consultando entidade com `business_id` sem o global scope (ou em job na fila que perdeu o tenant).
- **Sintoma:** dado de um business aparecendo pra outro. Pior bug possível do projeto.
- **Regra:** todo model de negócio usa global scope; jobs re-resolvem o tenant; CLI/superadmin trata cross-business explicitamente (skill `multi-tenant-patterns`).
- **Classe:** multi-tenant-scope-missing
- **Ocorrências:** 2
- **Gate:** multi-tenant-gate (.github/workflows/multi-tenant-gate.yml) + skill Tier A
- **Ref:** ADR 0093

## LC-02 — Mock/stub deixado em código de produção
- **Erro:** scaffolding com dados mockados (cowork/demo) sobrevivendo no caminho de produção.
- **Sintoma:** tela "funciona" com dado falso; quebra com dado real.
- **Regra:** mock só em teste/seed. Caminho de prod nunca importa fixture.
- **Classe:** mock-in-prod
- **Ocorrências:** 2
- **Gate:** scripts/no-mock-in-prod.mjs (+ no-mock-baseline.json)
- **Ref:** PR #2262

---

> Abaixo: classes **identificadas no audit 2026-06-06 como sem catraca** (`Gate: none`).
> Ainda em `Ocorrências: 0` — registradas proativamente, viram alarme se reincidirem.
> (Honestidade: 0 = nenhuma reincidência *observada* ainda, não "nunca aconteceu".)

## LC-03 — Teste só de caminho feliz (sem prova de que pega bug)
- **Erro:** suíte Pest verde que não exercita borda/erro; IA tende a gerar exatamente isso.
- **Sintoma:** cobertura "verde" enquanto bug passa. Teste vira teatro.
- **Regra:** teste tem que falhar quando o código quebra. Mutation testing (infection `--min-msi`) prova isso.
- **Classe:** happy-path-only-test
- **Ocorrências:** 0
- **Gate:** mutation-gate (advisory, escopo v1 app/Services via Pest --mutate) — .github/workflows/mutation-gate.yml
- **Ref:** audit 2026-06-06 (gap nº1); gate advisory landeado sessão 2026-06-06. Expandir escopo + promover a bloqueante no CT 100 (MySQL real).

## LC-04 — N+1 / query dentro de loop
- **Erro:** loop com query por iteração (IA adora gerar); sem eager-load.
- **Sintoma:** tela lenta sob dado real; explode com volume.
- **Regra:** eager-load + `Inertia::defer` em props pesadas (skill `inertia-defer-default`). Falta gate de contagem de queries.
- **Classe:** n-plus-one-query
- **Ocorrências:** 0
- **Gate:** none
- **Ref:** audit 2026-06-06 (gap nº5)

## LC-05 — Injeção genérica (SQLi/XSS/path-traversal) fora da regra custom
- **Erro:** input não-sanitizado em caminho sem gate específico (multi-tenant é gateado; injeção genérica não).
- **Sintoma:** vulnerabilidade que phpstan (type-level) não pega.
- **Regra:** prepared statements; escape na borda. Falta SAST/taint (semgrep/psalm-taint).
- **Classe:** injection-generic
- **Ocorrências:** 0
- **Gate:** none
- **Ref:** audit 2026-06-06 (gap nº2)

## LC-06 — Comparação design×prod NO OLHO em vez de MEDIDA
- **Erro:** comparei protótipo (Cowork vivo) × produção por screenshot/olho e declarei "estruturalmente iguais" — deixei passar diferenças MEDÍVEIS: KPI `center` na prod × `left` no design (causa: `<button>` herda center-default × `<div>` é left), dark-mode com texto `stone` invisível (escuro no escuro), e roxo do primary escuro `0.55` × roxinho `0.72` (o design clareia o accent no dark, a prod trava inline). Wagner teve que apontar cada uma.
- **Sintoma:** agente diz "aplicado/igual/pronto"; Wagner acha a divergência que o agente não mediu. "Print igual" esconde center×left, full-reload, contraste.
- **Regra:** comparação design×prod SÓ por MEDIÇÃO (computed style/DOM, MESMA sonda nos dois lados — `prototipo-ui/design-diff.mjs`), NUNCA no olho. Antes de comparar: provar a fonte `SYNC` (`cowork-mirror-freshness`) + validar com uma pista-canário conhecida (o alinhamento foi o canário do Wagner). Screenshot é ilustração, não prova de igualdade. Mesmo tema nos dois lados.
- **Classe:** visual-compare-eyeball
- **Ocorrências:** 2   (strike 1 = comparação v1 rasa "essencialmente igual" que gerou o `PROTOCOLO-COMPARACAO-RUNTIME` em 2026-07-06; strike 2 = esta sessão 2026-07-07, mesma classe)
- **Gate:** `prototipo-ui/design-diff.mjs` — comparador determinístico D2/D4/D6/D8 + probe medida (mesma sonda) + `--selftest` no CI (design-memory-gate). ⚠️ honestidade: a comparação em si é DISPATCH do agente pós-merge de tela (browser + design vivo — CI não renderiza), como o `cowork-mirror-freshness`; o que a máquina garante é que o veredito vem de medida, não do olho. Wirado no protocolo (Regra 0 pós-deploy).
- **Ref:** ADR 0299 (/design-diff previsto), `memory/requisitos/_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md` (D8), sessão 2026-07-07
