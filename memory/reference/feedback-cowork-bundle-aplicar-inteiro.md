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

**Última atualização:** 2026-05-18 (Wagner)
