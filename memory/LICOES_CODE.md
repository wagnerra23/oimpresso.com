# LIÇÕES [CODE] — erros de código a não repetir

> Escopo: **código backend/infra** (PHP, Eloquent, jobs, migrations, controllers, services, CI)
> **+ processo/comportamento de agente** (medição, derivação, oráculo, varredura) — o tema é
> *reincidência → defesa mecânica*, e erro de processo é instância disso igual erro de código
> (proposal [two-strikes-cobre-processo](decisions/0344-two-strikes-cobre-processo.md), raio-X 2026-07-20).
> Equivalente de `LICOES_CC.md` (que cobre design/[CC]) pro lado de engenharia.
> Subordinado a `memory/proibicoes.md` (canônico; o §5 de lá é a PROSA-evidência, este é o CONTADOR). **Append-only.**
> Lido no início de toda sessão pelo hook `licoes-code-two-strikes.mjs`.
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
>
> **Convenção do campo `Gate` (ADR 0344):** cobertura só-**advisory** (nudge/warn que não bloqueia)
> conta como *sem defesa mecânica* — declare `Gate: advisory — <hooks>` e a classe **segue
> alarmando** até virar sonda que morde. Se o advisory é a decisão **FINAL by-design** (ADR 0224),
> declare `Gate: advisory-terminal (0224) — <hook>`: o marcador `terminal`/`by-design`/`0224` sai
> do alarme. Um gate REAL que só menciona "advisory" entre parênteses (`mutation-gate (advisory,…)`)
> não é advisory pra o contador — só o prefixo declarado.

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

## LC-07 — Manifest de frescor cobria só as âncoras (cego pras deps do render)
- **Erro:** a rodada de frescor design↔espelho media só os `.jsx` de tela declarados em charter (3 âncoras). O render depende de ~100 arquivos (`app.jsx`, `styles.css`, `ds-v6/tokens.css`, css por módulo) — nenhum era medido. O [W] mudou o PageHeaderNav pra roxo no `app.jsx` do Cowork e a rodada saiu "3 SYNC" verde com o drift passando invisível ("ele não baixou tudo?", 2026-07-07).
- **Sintoma:** rodada de frescor verde + Wagner vê no vivo uma mudança que "não veio" no pull. Verde-parcial lido como verde-total.
- **Regra:** manifest de frescor enumera âncoras **+ deps de render derivadas MECANICAMENTE do shell** (`parseShellDeps` — src/href, strip `?v=`, sem CDN; nunca lista curada na mão). Sem shell → WARN explícito, nunca omissão silenciosa. Rodada mínima: âncoras + deps globais + css do módulo tocado; parcial = UNCHECKED.
- **Classe:** freshness-manifest-partial-coverage
- **Ocorrências:** 1
- **Gate:** `cowork-mirror-freshness.mjs` manifest v3 (kind ancora|dep) + testes §6b no `.test.mjs` (CI design-memory-gate) — o caso `app.jsx` do drift real está travado por assert nomeado.
- **Ref:** sessão 2026-07-07 · PROTOCOLO-COMPARACAO-RUNTIME passo 0 · LC-06 (família: cobertura de medição)

---

> Abaixo: 1ª classe de **PROCESSO/comportamento de agente** promovida ao contador (raio-X 2026-07-20).
> O §5 do `proibicoes.md` já tinha a prosa-evidência; faltava o CONTADOR que torna a reincidência
> visível pro hook. Backfill **forward-only + oportunístico** (a lápide 2026-07-12 do §5 proíbe big-bang):
> só a classe que estava GRITANDO entra agora; as demais viram LC-NN quando reincidirem.

## LC-08 — Afirmar/derivar/medir a partir da FONTE ou MEDIDA errada (sem provar)
- **Erro:** apresentar achado, causa-raiz, número ou veredito derivado da fonte errada — ler código em vez de rodar; medir o atributo (`.hidden`/`offsetTop`) em vez do computado; restatear número que outro sistema (banco/runtime) sabe melhor; parsear o `Kernel.php` em vez de perguntar ao scheduler; `crontab -l` num host que não tem o binário — e chamar de "verificado/medido".
- **Sintoma:** o agente afirma "medido / a raiz é X / verificado"; [W] ou o CI acha que a medida veio do disco/leitura/olho, não do sistema-que-sabe (SELECT no banco, `runsInEnvironment()` no runtime, varredura contada, teste vermelho).
- **Regra:** achado/número/veredito só vira CONCLUSÃO depois de (a) varredura CONTADA (sem `head_limit`, dizendo "N de N"), (b) âncora de contrato citada (UC/SPEC/ADR), (c) oráculo certo (banco/runtime/teste vermelho) — nunca leitura/parse/olho. Recibo DATADO (`query+resultado+data`), não afirmação atemporal. Em dúvida → PERGUNTAR, não inventar.
- **Classe:** afirmar-sem-medir-fonte-certa
- **Ocorrências:** 5   (proibicoes §5: 07-15 achado-sem-varredura · 07-16 medir-propriedade-errada · 07-17 oráculo-errado-restatear-número · 07-17 deduzir-quem-roda-parseando · 07-17 crontab-l-falso-negativo. Adjacente: 07-16 importar-solução-sem-checar-premissa.)
- **Gate:** advisory — `nudge-diagnosis-without-evidence` + `warn-red-first` (nudges que NÃO bloqueiam ESTA classe; ela reincidiu 5× em 3 dias → advisory insuficiente, precisa de sonda que morda). Crédito honesto: `block-ancora-no-olho` já BLOQUEIA (exit 2), mas comportamento **adjacente** (ler PNG semântico como fonte de design), não a medição/derivação desta classe — por isso "nenhum bloqueia ESTA classe" segue verdadeiro. Desescala quando cada sub-comportamento ganhar sonda própria (ex: medir-propriedade-errada → CSS `[hidden]{display:none!important}`; oráculo-número → Check T `fact-anchor`).
- **Ref:** `memory/proibicoes.md` §5 (2026-07-15..17) · raio-X 2026-07-20 · proposal [two-strikes-cobre-processo](decisions/0344-two-strikes-cobre-processo.md)
