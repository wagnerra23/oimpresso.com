---
date: "2026-08-08"
time: "17:21 BRT"
slug: jana-fatia-a-barra-unica-pageheader
tldr: "Fatia A da fusão da Jana — duas barras de header viram uma no <PageHeader> canon. PR #5429 mergeado por [W] com 111 checks verdes. Charter Index v4 corrige MetricasApurador::farol (método inexistente) para ApuracaoService::farol. Gate F1.5 ficou SEM screenshot: ambiente local não sobe."
prs: [5429]
decided_by: [W]
related_adrs: [0180-sidebar-v3-href-direto-ghosts-pageheader, 0189-pageheader-canon-v3-8]
next_steps:
  - "Smoke real em prod (/ia) com screenshot — pendente, exige sessão autenticada"
  - "Decidir a aba Jana Pro: existe em produção e NÃO existe no protótipo"
  - "Avaliar se o dot azul da área JANA deve voltar (o PageHeader canon não tem slot de ícone)"
---

# Jana — Fatia A da fusão: barra única no PageHeader canon

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 10 tasks, **todas em REVIEW** (nenhuma DOING) — US-TR-309/310/311, US-PG-008, US-PROD-027, US-INFRA-023/048, US-TR-305/306, US-KB-002
- `Glob memory/handoffs/2026-08-0*` → 26 handoffs; irmãos do mesmo dia: `2026-08-08-1936-jana-memoria-fatia-d-lgpd-motivo.md`, `2026-08-07-1524-jana-fusao-fechada-e-5-chips.md`
- Brief #484 → sem PRs aguardando review, visual-regression limpo, Jana fora das flags

## O que aconteceu

Fatia A do pacote `JANA-FUSAO-2026-08-06` (US-COPI-148). A tela `/ia` tinha **duas** barras de header e **nenhuma** usava o `<PageHeader>` shared: o `JanaAreaHeader` em cima (dot JANA + abas + Conversar) e um `<header>` próprio dentro do `JanaCockpit` embaixo (Jana · Analista IA + biz + Atualizado + Configurar/Exportar).

Três fontes concordavam e discordavam da produção: o protótipo (`jana-merge.jsx` renderiza `<JanaHeader/>` e **só depois** `{tabs}`), o canon do repo (`Financeiro/Caixa/Index.tsx:95-112` põe o SubNav **dentro** do PageHeader) e o `PT-04-Dashboard` §Anatomia slot 1 + R6. Colapsei numa barra só: o `JanaAreaHeader` passou a **ser** o PageHeader canon, e o header do Cockpit saiu.

"Atualizado HH:MM" virou **botão** (protótipo `.jc-updated-b` + `onRefresh`) — antes era um `<span>` com a hora do *render*, que não reapurava nada.

**Dois itens do brief não se confirmaram na fonte** e por isso não foram feitos: (a) o *contador nas abas* — o `JmTabs` do protótipo renderiza só `<span>{t.label}</span>`, e `cli-moduletopnav-n` não existe na Jana (existe em `clientes-page`/`os-page`, e o canon **já suporta** via prop `badge` do `PageHeaderTabs`, mas quem popula os ghosts é o `DataController` PHP); (b) o *selo de plano Pro/Grátis* — não há dado de plano no DataController.

## Artefatos gerados

| Arquivo | Δ | Nota |
|---|---|---|
| `resources/js/Pages/Jana/components/JanaAreaHeader.tsx` | +128/−34 | vira o `<PageHeader>` canon; props novas `businessName`/`businessId`/`actions` |
| `resources/js/Pages/Jana/_components/JanaCockpit.tsx` | −45 | header próprio removido + órfãos (`Settings`/`Download`/`formatTimeShort`/2 props) |
| `resources/js/Pages/Jana/Index.tsx` | +26/−5 | passa identidade + ações Configurar/Exportar à barra única |
| `resources/js/Pages/Jana/Index.charter.md` | v3→**v4** | corrige `MetricasApurador::farol` → `ApuracaoService::farol`; §Goals do header |

## Persistência

- **git**: PR [#5429](https://github.com/wagnerra23/oimpresso.com/pull/5429), mergeado por [W] 20:18Z, merge commit `9a4ca3b0d84`
- **CI**: 111 pass · 2 skipping · 0 fail
- **deploy**: o run do meu merge (20:18:17) saiu `cancelled` — **não é falha**: `deploy.yml:43-45` usa `concurrency: deploy-production` com `cancel-in-progress: false`, que coalesce a fila. Verificado que `9a4ca3b0d84` **é ancestral de `origin/main`**, logo o código entra no run seguinte (20:20:39)

## Próximos passos pra retomar

```bash
gh pr view 5429 --json state,mergedAt && curl -s -o /dev/null -w "%{http_code}\n" https://oimpresso.com/ia
```

## Lições catalogadas

1. **`MetricasApurador::farol` não existe** — a classe existe (`Modules/Jana/Services/Metricas/MetricasApurador.php`), o método não. A implementação é `ApuracaoService::farol` (`:151`, #5394). O charter apontava pro lugar errado em dois pontos, mandando a próxima sessão procurar a regra onde ela não está.
2. **Colisão de `charter_version`** — o main já tinha levado o charter a v3 (Fatia B, #5416) enquanto eu numerava v3 na Fatia A. Resolvido pra v4 mantendo os dois registros (append-only). Sintoma de duas fatias do mesmo pacote em voo paralelo.
3. **Base envelhece sozinha** — 0/0 no início, **25 commits atrás** no dia seguinte sem eu tocar em nada, com 4 PRs na própria Jana no intervalo. Medir a base no instante do dispatch, não confiar em "criei fresco há pouco" (§5 2026-08-03 eixo 2).
4. **O gate `Preflight + contratos ativos` é corrida com alvo móvel** — falhou porque o main andou 3 commits entre o meu merge e o CI rodar. O predicado é `git merge-base --is-ancestor origin/main HEAD`; o conserto é remergear. Não é defeito de código.
5. **LC-08 (afirmar sem medir a fonte certa), cometida e pega**: concluí que `cli-moduletopnav-n` não existia em lugar nenhum a partir de uma busca cujo **cwd estava contaminado** por um `cd` anterior. Quem corrigiu foi `git grep` no índice (oráculo). A conclusão errada teria ido ao relatório com cara de fato medido.

## Pointers detalhados

- Charter: `resources/js/Pages/Jana/Index.charter.md` §Goals + §Anti-hooks + version log v4
- Fatia B (irmã, já em prod): [#5416](https://github.com/wagnerra23/oimpresso.com/pull/5416) + handoff `2026-08-07-1524-jana-fusao-fechada-e-5-chips.md`
- Protótipo (fonte): `prototipo-ui/cowork/jana-merge.jsx` (`JanaPage` + `JmTabs`) — via DesignSync, projeto `019dcfd3-6ef2-7ee6-8512-b1b0e5544e58`

## ⚠️ Caveat aberto — gate F1.5 sem screenshot

A Fatia A é mudança visual em tela live e **mergeou sem o screenshot 1280px** do gate F1.5. Não foi esquecimento: o ambiente local não sobe (`php` fora do PATH; worktree sem `.env`/`vendor/`), e montar exigiria junction de vendor — manobra que já esvaziou o `vendor/`/`node_modules` reais deste repo (2026-05-11, 2026-07-14). Preferi declarar a ausência a fabricar imagem que não prova nada.

Fica devendo **smoke real em prod** (`/ia` responde 302→/login; exige sessão autenticada). Dois pontos a conferir no olho: o **dot azul da área "JANA" saiu** (o PageHeader canon não tem slot de ícone) e **Chat/Memória herdaram a mesma barra** — em `Memoria.tsx` isso deixa o `<h1>` próprio logo abaixo do novo header, redundância parecida com a que este PR matou, mas em outra tela.
