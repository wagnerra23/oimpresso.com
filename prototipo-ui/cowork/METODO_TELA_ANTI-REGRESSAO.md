---
metodo: Desenvolvimento de Tela Sem Regressão (casos de uso = contrato)
escopo: reutilizável — toda tela. 1ª aplicação: Oficina (Produção + OS).
ancora: ADR 0243 (processo) · _PROPOSTA-0244 (teste estado-arte) · <tela>.casos.md (contrato) · <tela>.charter.md (lei)
status: método [CC] — proposto a canon (próximo handoff)
owner: wagner · last_update: 2026-06-02
---

# MÉTODO — Desenvolvimento de Tela Sem Regressão

> **Os casos de uso são o contrato de não-regressão da tela.** Nenhuma mudança é autorizada se quebra um UC **✅ aprovado**. Se quebra → **PARA, revisa o pensamento, não força.** Conserto silencioso é proibido.

## Princípio
Um UC ✅ é um **comportamento prometido**. Mudar a tela e quebrar um ✅ = **regressão = bloqueio** até revisão consciente. Mudar o contrato (rebaixar/alterar um ✅) é decisão de [W], registrada — nunca para "fazer o teste passar".

## O contrato: `<tela>.casos.md`
UCs em estados: **✅ aprovado** (o contrato) · 🧪 testando · ⬜ não-verificado · ❌ quebrado. Os ✅ são o que toda mudança deve preservar.

## Duas dimensões do contrato — comportamento + conformância DS (as duas bloqueiam)
Um `casos.md` cobre **duas classes** de UC; um teste de CI por classe, ambos bloqueiam o merge:
1. **UC de comportamento** — o que a tela **FAZ** (render/clique/estado). Ex.: split fiscal aparece, bulk emite, drawer abre. Locator `role`/`data-testid`.
2. **UC de conformância DS** — o que a tela **É**: conforma ao `regua` + `ds` do **charter**. Asserção por **computed-style**, nunca por classe:
   - accent computado **==** o accent do `ds` do charter (ex. roxo v6 `--accent`);
   - **zero cor crua** — toda cor resolve pra token do DS (espelha stylelint anti-hex do repo, #2054);
   - drawer `role=dialog` na largura canon (não modal central);
   - **os 2 temas** passam (sem white-on-white / contraste AA).
   > **Esta classe é a que pega "régua-drift" (L-29).** Se o charter diz `ds:v6`/roxo e a impl renderiza **verde**, a asserção de accent **FALHA e bloqueia** — a régua vira **teste, não memória**. (O erro de 2026-06-03 teria sido barrado aqui, sem depender de eu ler o gabarito.)

## Gate por mudança — autoriza OU bloqueia
1. **PRÉ (ler):** `charter` + `casos` + `register` da tela. Já sei a lei, o debate e o que não pode regredir.
2. **SPEC-FIRST:** muda comportamento? Atualiza/adiciona o UC **antes** de codar.
3. **MUDA:** implementa — reuse-first, DS-GUARD, **locators resilientes** (role/`data-testid`, nunca classe CSS · 0244).
4. **GATE (roda os UCs):** static + live/CI.
   - Todos os ✅ passam → ✅ **AUTORIZADO**, segue a fase.
   - Algum ✅ quebrou → 🛑 **REGRESSÃO — PARA.** Não merge, não avança.
5. **REVISÃO (o "revisar o pensamento") — nesta ordem:**
   a. **A mudança está certa?** Talvez o design esteja errado → corrige a mudança.
   b. **O UC ainda está certo?** Se o comportamento mudou de propósito → **mudar um ✅ = mudança de contrato = registra no `register` (D-NN) + OK de [W]**, nunca silencioso.
   c. Só volta ao passo 4 quando **verde** ou o contrato foi **conscientemente reescrito**.
6. **CRESCE:** comportamento novo → UC novo. Erro que passou → lição (`LICOES_CC`) **+** UC novo (a suíte cresce com os erros).

## Regras de ouro (anti-regressão)
- ❌ "Consertar" um ✅ quebrado mexendo só no código **sem entender por que quebrou** (L-24).
- ❌ Rebaixar/alterar um ✅ pra "fazer passar" sem registrar + OK de [W] (= fraudar o contrato).
- ❌ Locator por classe CSS (quebra em silêncio) — usar role/`data-testid` (0244).
- ✅ Regressão **bloqueia o avanço da fase** (não é aviso) — espelha o próprio StageGate da Oficina.
- ✅ **Controle negativo obrigatório (testar o teste):** um UC só vira ✅ depois de eu ter **visto ele FALHAR** num bug injetado de propósito (mutação) e **voltar a passar** no revert. Teste que nunca foi visto vermelho **não prova nada** — não conta como contrato. (Pedido [W] 2026-06-03; 1ª prova: UC-V09 accent verde→🔴 / roxo→✅.)

## Generalização (skill)
Vale pra **qualquer tela**. Cada `<tela>.charter.md` referencia este método + seu `<tela>.casos.md`. **Invocar** = "rodar o gate anti-regressão da tela X" → passos 1→6.

## Papéis
- **[CC]:** mantém os UCs, roda o gate no protótipo, registra as revisões.
- **[CL]:** roda o gate no CI (0244: Playwright/Storybook) e **bloqueia o merge** em regressão.
- **[W]:** único que autoriza **mudar o contrato** (rebaixar/alterar um ✅).

## Refs
ADR 0243 (processo · Bateria §9 · benchmark §11) · `_PROPOSTA-0244` (teste estado-arte) · `<tela>.casos.md` · `<tela>.charter.md` · `LICOES_CC` (L-23/L-24/L-25).

## Da análise ao vigor (o ciclo completo)
> Responde: o método gera doc igual/melhor? sugere os UCs? autorizado entra em vigor?

1. **Análise/intake (F0)** — dossiê de casos de uso (ex.: `Oficina-casos-dossie.md`: benchmark intl+BR, personas, jornada, máquina de estados, matriz de aderência, lacunas priorizadas). É a **fonte**. O método **consome** dossiês deste nível (não os substitui) — quando não existe, o intake os produz.
2. **Proposta** — cada lacuna/achado vira **UC proposto** no `register` (🔍 Avaliar). *(Já feito: P1–P4 no `OficinaProducao.decisoes.md`.)*
3. **Teste** — [CC] protótipa (🧪), passa pelo gate (não quebra UC ✅).
4. **Vigor** — **[W] autoriza → ✅ no `casos.md` + grava no `charter` (lei da tela) + vira teste de CI**. A partir daí é **contrato que bloqueia regressão**. *Isto é "entrar em vigor".*

> **Igual ou melhor?** O dossiê é **análise** (e não propõe mudança). O método **fecha o loop** que o próprio dossiê diz faltar: análise → contrato testável → anti-regressão → CI. Então: **iguala** a profundidade (consome o dossiê) e **supera** no que o dossiê não faz — torna executável, autorizável e à prova de regressão.

## Formato padrão do `<tela>.casos.md` (texto — NÃO precisa ser HTML)

> Markdown é suficiente e preferível (versionável, diff-friendly, vira teste). HTML/preview é descartável.

**Cabeçalho do arquivo:** front-matter (`casos:` tela · `charter:`/`metodo:` refs) + legenda de estado (✅/🧪/⬜/❌) + **bloco "Contrato"** listando os IDs ✅ que NÃO podem regredir.

**Cada caso, sempre estes campos:**
```
## UC-NN · <título curto>
- **Persona:** <quem> · <contexto/dispositivo (balcão 1280px, tablet…)>
- **Como usa:** <1–2 frases na voz do cliente — o "porquê">
- **Aceite (Dado/Quando/Então):** <comportamento verificável — é o contrato>
- **Locator:** <role / data-testid resiliente — NUNCA classe CSS (0244)>
- **Check:** static | live | ci   **Estado:** ✅/🧪/⬜/❌   **Teste CI:** UC-NN (mesmo ID)
```

**Regras do formato:**
- **ID estável** (`UC-NN`) = mesmo nome do teste de CI e do registry → rastreável das 3 pontas (spec ↔ in-app ↔ CI).
- **1 caso = 1 comportamento** observável pelo cliente (não detalhe de implementação).
- **Locator resiliente** no campo próprio (role/`data-testid`) — o que blinda contra quebra-em-silêncio (L-24).
- Ordenar do mais crítico (P0, trava a tela) ao acessório.
- Estado muda só pelo gate (passos 1–6); ✅ só [W] rebaixa (mudança de contrato).

## Matriz de técnicas — como GARANTIR (pesquisa 2026)
> Achado da pesquisa: **nenhuma técnica única garante** — combina-se em camadas (defense-in-depth). Testes funcionais não pegam CSS/layout; pixel-diff tem flake de render OS; cloud tem custo/soberania. Pontuação 1–5 × peso, no contexto Oimpresso (host único Babel/CDN + repo · falhas reais: drift contrato verde×roxo, cor crua, dark).

| Técnica | drift contrato (×3) | cor crua (×2) | layout/visual (×2) | dark/contraste (×2) | determinístico/baixo-flake (×3) | roda no host único (×2) | bloqueia CI (×2) | manut. (×1) | **/85** |
|---|---|---|---|---|---|---|---|---|---|
| **A. Stylelint token-lint** (estático) | 2 | 5 | 1 | 1 | 5 | 5 | 5 | 5 | **60** |
| **B. Asserção computed-style** (Playwright/Pest-browser) | 5 | 3 | 3 | 4 | 5 | 4 | 5 | 3 | **71** |
| **C. Visual-regression golden** (toHaveScreenshot/BackstopJS/Lost Pixel) | 4 | 3 | 5 | 4 | 2 | 4 | 5 | 2 | **62** |
| **D. Cloud AI visual** (Percy/Chromatic/Applitools) | 4 | 3 | 5 | 4 | 4 | 3 | 5 | 4 | **68**¹ |
| **E. E2E comportamento** (casos.md UC + locator resiliente) | 1 | 1 | 1 | 1 | 5 | 4 | 5 | 3 | **45**² |
| **F. axe-core** (a11y/contraste) | 1 | 1 | 1 | 5 | 5 | 4 | 5 | 4 | **54**² |
| **G. Mutation/controle-negativo** (Stryker + bug injetado) | — | — | — | — | — | — | — | — | **meta**³ |

¹ Cloud: custo escala por snapshot + **soberania** (dado de cliente) + Chromatic exige Storybook (host não tem). ² E/F pontuam "baixo" no eixo contrato mas cobrem **classes que B/C não pegam** (comportamento / contraste) — complementares, não competem. ³ G não pega regressão de produto — **garante que A–F ficam vermelhas no bug** (responde "testar o teste"); sem ela "verde" não vale.

### Stack recomendada (defense-in-depth · cada camada pega uma classe, a meta garante honestidade)
1. **Estática — Stylelint token-lint** → `cor-crua == 0` (UC-V10). Barato, zero flake, espelha #2054 do repo.
2. **Conformância — computed-style + axe** → accent==token roxo (UC-V09), drawer 480 `role=dialog` (UC-V11), 2 temas AA (UC-V12). **Determinístico** (lê valor computado, não pixel) → pega o drift verde×roxo sem flake.
3. **Visual golden — 1 browser pinado (Docker)** → backstop do inesperado; golden só muda com OK de [W] (= mudança de contrato).
4. **Comportamento — casos.md UC → Playwright** (locator resiliente) → o fluxo não quebrou.
5. **META — mutation/controle-negativo (Stryker)** → CI injeta bug e exige que 1–4 fiquem 🔴; senão o gate é fajuto.
> Tudo **gate bloqueante no CI** ([CL]/Tier-0). A camada 2 é a de maior retorno pro nosso erro específico (drift de contrato), por ser determinística; pixel-diff (3) entra como rede, não como base, por causa do flake de render entre OS.

## Integração com o Page Charter (charter → casos → testes = 1 contrato, 3 formas)
O **charter é a LEI** · o **`casos.md` é a projeção TESTÁVEL** · os **testes são o CUMPRIMENTO**. Ligados por **ID de UC estável** + o frontmatter `regua`/`ds`. Não são 3 docs adjacentes — é o mesmo contrato em 3 formas.
- **O frontmatter do charter ALIMENTA a asserção:** UC-V09 lê o `ds:v6`→roxo **do charter** (não hardcode). [W] muda o contrato **editando o charter** → a asserção passa a exigir o novo valor. Charter = fonte única do "esperado".
- **Cada balde do charter → uma classe de teste:**
  - **Goals (PRECISA TER)** → UC de **comportamento** (E2E).
  - **Non-Goals / Anti-patterns (REPROVADO)** → asserção **negativa / must-not** ("não modal"→UC-V11 · "não cor crua"→UC-V10 · "não verde"→UC-V09).
  - **UX Targets** → conformância/perf/**a11y** (1280 sem scroll · AA→UC-V12).
  - **`regua`/`ds`** → conformância DS computed-style.
- **Cobertura é medida CONTRA o charter:** "suficiência §1" = % dos baldes do charter com teste que afirma. **O charter define o que é 100%.**
- **Só [W] move a baseline:** teste 🔴 → conserta o código **OU** (se proposital) [W] **edita o charter** → ✅ do `casos` atualiza → golden re-baseline. O charter é onde a mudança de contrato é autorizada (§Gate 5b).
- **Health-check fecha o elo (L-16 · falta mecanizar):** `charter_sem_regua` · `uc_sem_teste_ci` (todo ✅ tem teste de mesmo ID?) · `regua_nao_asserida` (o `ds`/`regua` é checado por alguma conformância?). Sem esses checks, charter↔teste é **adjacência, não governança.**

## Suficiência — NÃO se declara, se MEDE (resposta ao [W] "os testes são suficientes?")
> Suite que passa ≠ suite suficiente. **Teste só pega o que afirma.** "Suficiente" é número medido (máquina, não minha palavra — L-16), com 5 critérios:
1. **Cobertura de contrato** — % dos elementos/estados do `gabarito` + ✅ do `casos.md` que têm asserção. **1 botão asserido ≠ o contrato** (UC-V09 olha só o primary; um pill/aba/ícone pode driftar e passar). Alvo: cada ✅ + cada elemento canônico do gabarito.
2. **Defense-in-depth (5 camadas)** — estática + computed-style + **visual-golden** + comportamento + a11y. Cada uma pega a classe que a outra perde: **computed-style NÃO pega o visual inesperado** (overflow, espaçamento, z-index, elemento fora de lugar) → o golden é a **rede obrigatória**, não opcional.
3. **Mutation score** — Stryker mata ≥X% dos mutantes. 1 controle-negativo prova **1** asserção; suite suficiente mata um **conjunto representativo** de bugs.
4. **Bloqueia no CI** — advisory não garante.
5. **Cresce por escape** — todo bug que passou (inclusive pego por [W]) vira teste novo. Suficiência é **assintótica, nunca "pronta"**.
> **Estado hoje (honesto):** 1 tela · ~4 asserções · 1 mutante · sem golden · roda ad-hoc, fora do CI. = **longe de suficiente — é o começo (camada 2 só).**

## Evolução / trilha do tempo
- 2026-06-03 · [CC] add **§Matriz de técnicas** (pesquisa 2026 · pedido [W] "pontue as melhores técnicas pra garantir") + **§controle-negativo obrigatório** (testar o teste). Conclusão: defense-in-depth, camada computed-style lidera pro drift de contrato; mutation garante honestidade.
- 2026-06-02 · [CC] criou o método (pedido [W]: "documento padrão anti-regressão; mudança que regride a tela precisa revisar o pensamento antes de seguir"). 1ª aplicação: Oficina. Decisão: texto (markdown) é o formato — HTML é descartável.
