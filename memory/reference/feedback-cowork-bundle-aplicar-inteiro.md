# Feedback canon — Pacote Cowork novo de módulo: copiar CSS inteiro primeiro

**Origem:** Wagner 2026-05-18 após 3 tentativas falhas no Financeiro (PR #1085 → #1091 → #1092) onde cherry-pick incremental de classes do bundle Cowork v2 deixou drawer/painéis quebrados (textos colados, cores erradas, abas faltando).

> "não deu certo, acredito que deva copiar o css na integra do financeiro. depois ver o que precisa fazer para integrar. 3 vez que errou. e deu certo nos outros módulos quando na primeiras vez do modulo copiar tudo. e depois customizar."

## Regra IRREVOGÁVEL

**Quando chega pacote Cowork novo de um módulo (ex: `prototipo-ui-patch/vendas-financeiro-completo/styles.css`), a PRIMEIRA aplicação é COPIAR o CSS INTEIRO direto pro projeto. Sem cherry-pick.**

Cherry-pick incremental:
- Erra detalhes (cor ligeira, hue 280 vs 295, padding canon)
- Gasta 3-5 ondas iterando bugs visuais
- Wagner reabre gap report toda vez

Bundle copy completo:
- 1 ação, contemplam todas as classes do design system
- Diff vira "o que falta integrar no Inertia" (componentes React + state)
- Customização vem DEPOIS quando visual já bate 100%

## Quando aplicar

✅ **Aplicar regra quando:**
- Wagner manda pacote Cowork novo (Anthropic API design fetch / tarball / pasta `prototipo-ui-patch/<modulo-X>`)
- Módulo ainda não tem CSS canonical absorvido
- Bundle tem `styles.css` global > 5000 LOC

❌ **NÃO aplicar quando:**
- Ajuste pontual de 1-2 classes em módulo já 90% migrado (pode cherry-pick)
- Hotfix visual urgente (não é Cowork v3 chegando — só ajuste tático)

## Procedimento canônico

1. **Extrair bundle** pra `/tmp/<modulo>-bundle/` (NÃO commitar tarball)
2. **Copiar `styles.css` inteiro** pra `resources/css/cowork-<modulo>-bundle.css` (renaming explícito da origem)
3. **Importar** no Inertia layout / `app.css` na ordem CORRETA:
   - bundle Cowork PRIMEIRO (canônico)
   - overrides locais DEPOIS (cherry-picks históricos como fin-curadoria.css / fin-output.css ficam por enquanto, deprecam depois)
4. **Smoke Chrome MCP** validando que CSS aplica sem regressões em outros módulos
5. **Commit + PR ÚNICO** com mensagem "feat(<modulo>): copia CSS Cowork v<N> inteiro (base canônica)"
6. **PR seguinte** customiza Inertia/React (componentes React + state + props) usando base já correta visualmente
7. **PRs subsequentes** podem deprecar `fin-curadoria.css` / `fin-output.css` / `fin-cowork.css` legacy quando confirmado que bundle absorveu tudo

## Validação que funcionou (referência histórica)

- ✅ **Vendas** (Sells/Index, Sells/Create): primeira aplicação foi bundle inteiro → 7+ ondas de customização Inertia rodaram suaves
- ✅ **Pedidos** (POS layout v1): mesma estratégia, primeira aplicação bundle inteiro
- ✅ **Cockpit** (AppShellV2): cockpit.css importado inteiro primeiro

## O que falhou (Financeiro 2026-05-18)

- ❌ Onda 5/6/7/7b/7c/8/8b/8c/9: cherry-pick incremental criou fin-curadoria.css + fin-output.css + fin-cowork.css fragmentados
- ❌ Drawer Cowork V2 (PR #1091): faltavam 14 classes
- ❌ Drawer Cowork V2.1 (PR #1092): cores erradas (azul 240 em vez de verde 145, IA 280 em vez de 295)
- ❌ 3 round-trips de gap report Wagner

## Pra evitar repetir

Adicionar a este memory:
- Comando exato no template de PR: "Estratégia: copia bundle inteiro (regra feedback-cowork-bundle-aplicar-inteiro)"
- Skill `mcp-first` deve sugerir esta regra quando user pede aplicação Cowork
- Próximas ondas Financeiro (Fluxo, DRE, Caixa, Edições): aplicar regra antes de tocar código

## Vínculo com outras regras

- Tier 0 Multi-tenant (ADR 0093) — bundle copy não viola
- Constituição v2 princípio 5 SoC brutal — bundle CSS escopado por módulo
- Skill `multi-tenant-patterns` — bundle não toca queries
- [[feedback-brave-mcp-primeiro-sempre]] — também sobre não repetir erros sistêmicos

## Auditoria

Próxima vez que Wagner pedir aplicação Cowork de módulo NOVO:
1. Verifica esta regra ANTES de Edit/Write
2. Reporta: "Aplicando regra feedback-cowork-bundle-aplicar-inteiro — copia styles.css completo primeiro"
3. PR ÚNICO de bundle copy ANTES de qualquer customização

---

## Apêndice — Plano B canônico (revert pro shell nu) 2026-05-18 noite

Quando cherry-pick Cowork falha 3+ vezes no mesmo módulo E não há tempo/disposição pra fazer bundle copy inteiro AGORA, a saída pragmática é **reverter pro `AppShellV2` nu + sidebar UltimatePOS canônico via DataController + flag default false no config**, até estar pronto pra fazer bundle copy direito.

**Não é desistência** — é estancar a sangria visual de um módulo crítico (Financeiro tem Eliana usando dia a dia) sem se comprometer com 4ª/5ª tentativa cherry-pick que vai falhar igual.

### Pattern do revert (validado PR [#1115](https://github.com/wagnerra23/oimpresso.com/pull/1115) Financeiro 2026-05-18 noite)

1. **Flag canônica no config do módulo** — `config/<modulo>.php` tem 2 defaults:
   - `mock_cowork_mode` (controllers retornam HTML literal do bundle Cowork via trait `RendersMockCowork`)
   - `sidebar_wrap_enabled` (bridge JS injeta sidebar wrap no mock)
2. **Revert = trocar defaults pra `false`** + manter env vars `FINANCEIRO_MOCK_COWORK` / `FINANCEIRO_SIDEBAR_WRAP` pra reversibilidade futura
3. **Não deletar artefatos** — preserva `public/cowork-preview/*.html`, bridges JS, trait, `Pages/Financeiro/_components/Fin*.tsx`. Tudo dormente, ativável via `.env` quando bundle copy estiver pronto.
4. **PR pequeno** — só `config/<modulo>.php` (Financeiro: +35/-8). Não mexer em Pages/Controllers (já compatíveis com ambos caminhos via trait `if-null-return`).
5. **Comentário do config explica os 2 caminhos + 5 camadas de reversibilidade** — futuro Claude/dev entende decisão sem precisar reler 3 PRs falhos.

### Reversibilidade 5 camadas (canon)

1. `.env` produção: `FINANCEIRO_MOCK_COWORK=true` (volta mock literal)
2. `.env` produção: `FINANCEIRO_SIDEBAR_WRAP=true` (volta wrap se mock on)
3. localStorage runtime: `__OIMPRESSO_SIDEBAR_OFF__='1'` (kill switch JS — bridge respeita; linha 55 `_oimpresso-bridge-sidebar.js`)
4. `git revert` do commit do revert
5. Branch/tag snapshot pré-revert preservada

### Critério de evidência (smoke pós-deploy)

- `curl -sv https://oimpresso.com/<rota_modulo> 2>&1 | grep -iE '^< (HTTP|X-Mock)'`
  - **Esperado:** ausência do header `X-Mock-Cowork: 1` (que o trait injetava quando ativo)
  - Esperado: comportamento idêntico a rota Inertia não-mockada (ex: `/pos/sells` redirecionando 302 → `/login` quando não autenticado)
- Validação `bootstrap/cache/config.php` literal: `'mock_cowork_mode' => false,`
- Chrome MCP screenshot logado biz=4 (Larissa) + biz=1: AppShellV2 + sidebar UltimatePOS canônica renderiza

### Quando ATIVAR Plano B vs insistir cherry-pick

| Sinal | Decisão |
|---|---|
| 2 PRs cherry-pick falharam visualmente + Wagner reabriu gap | Insiste só se a 3ª falha for SÓ 1-2 classes faltando |
| 3+ PRs cherry-pick falharam + Wagner pediu pausa | **ATIVAR Plano B** — não tentar 4ª. Bundle copy fica em PR separado |
| Wagner diz "tira o css e me devolve o que funcionava" | **ATIVAR Plano B imediatamente** — não negociar mais cherry-pick |
| Cliente piloto reporta "tela quebrada" e produção tá com mock on | **ATIVAR Plano B emergência** — `.env FINANCEIRO_MOCK_COWORK=false` + cache:clear até PR revert mergear |

### Vínculo com outras regras

- Regra principal deste arquivo (bundle copy inteiro 1ª vez): **continua valendo** — Plano B só pausa até estar pronto pra fazer bundle copy direito
- Tier 0 universal preservado: sem hardcode `biz=N`. AppShellV2 + DataController + `package_details` + Spatie permissions decidem o que aparece (3 camadas — ver `proibicoes.md §Multi-tenant Tier 0`)
- Não conflita com [ADR 0104 MWART canônico](../decisions/0104-processo-mwart-canonico-unico-caminho.md) — Pages MWART continuam onde estavam, só o caminho mock/wrap fica off

---

**Última atualização:** 2026-05-18 noite (Wagner) — Plano B canon após [PR #1115](https://github.com/wagnerra23/oimpresso.com/pull/1115) revert Financeiro
