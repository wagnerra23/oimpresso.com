---
slug: sells-runbook-create-v3
title: "Sells — Runbook da tela Venda V3 (preview de design, paralelo)"
type: runbook
module: Sells
status: active
date: 2026-08-06
---

# RUNBOOK — Venda V3 (`/sells/create-v3`)

> **Tipo:** runbook de tela NOVA e PARALELA — não é migração, não substitui nada.
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) (MWART) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant) · [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) (staging CT 100)
> **Dono:** [L] Luiz — assumiu a tela de cadastro de venda em 2026-08-06.

## §F1 PLAN

### Por que esta tela existe

Restrição de negócio declarada por [L] em 2026-08-06, textual:

> *"Tela do Guilherme e da Larissa não pode ser alterada de forma alguma, se não eles quebram contrato e perdemos dinheiro."*

A tela viva de cadastro de venda é `Pages/Sells/Create.tsx`, servida por
[`SellPosController@create`](../../../app/Http/Controllers/SellPosController.php) na rota `/pos/create`.
Quem opera nela é a **ROTA LIVRE** (`business_id=4` — Larissa dona, Guilherme retaguarda),
que responde por 99% do volume de vendas do oimpresso novo.

**A feature flag não protege.** Medido em `origin/main` (2026-08-06):
`FeatureFlagService::$fallbackDefaults['useV2SellsCreate'] = true`, com a nota
*"Wagner: 'ative para todos pronto' → flip fallback default p/ TRUE"* (2026-05-27).
Ou seja, a V2 React está ligada por padrão para todos — mexer em `Create.tsx` e
deployar atinge o cliente **direto**, sem intermediário.

Daí o desenho: **arquivo novo + rota nova**, sem tocar em nada que a tela viva consome.

### Fronteira — o que este trabalho NÃO pode fazer

| ⛔ Proibido | Por quê |
|---|---|
| Editar `Pages/Sells/Create.tsx` | é a tela deles |
| Editar `SellPosController@create` | serve a tela deles; duplicar as ~200 linhas de props geraria drift |
| Editar componente compartilhado que `Create.tsx` importa | a alteração vaza pra tela deles pelo import |
| Calcular valor / total / desconto / estoque na V3 | território **[V0]** (REGRA MESTRE, `memory/proibicoes.md`) — foi assim que nasceu o incidente `num_uf` de 2026-06-05 (`final_total` inflado ~×100.000 em 16 vendas do biz=4) |
| Gravar qualquer coisa | não há `store()`, não há POST, não há rota de escrita |

Precisando de variação de um componente existente, **nasce cópia local** em
`Pages/Sells/_components/` — nunca edição do original.

### Escopo da v1

Tela **apresentacional**: os números chegam prontos do controller como dados de cena
(`SellsV3Controller::dadosDeCena()`), espelhando
`prototipo-ui/design-oimpresso/04-modulos/vendas/sells-data.js`.

Ligar props reais (as ~24 de `SellPosController@create`) é **decisão separada**, depois que o
desenho assentar — e aí volta a valer a REGRA MESTRE de valor/estoque.

## §F2 BACKEND BASELINE

Não se aplica: não há backend a preservar. O controller novo
([`SellsV3Controller`](../../../app/Http/Controllers/SellsV3Controller.php)) é `GET`-only,
gate de permissão idêntico ao da tela real (`superadmin` ou `sell.create`), sem escrita.

`business_id` vem de `session('user.business_id')` e é passado à view — a tela não consulta
tabela de negócio nenhuma, então não há query a escopar (ADR 0093 satisfeito por ausência).

## §F3 FRONTEND

- `Pages/Sells/CreateV3.tsx` — layout `AppShellV2`, componentes de `@/Components/ui/*`, ícones `lucide-react`.
- 3 passos numerados: **Cliente · Itens · Fechamento** (âncora de design abaixo).
- Faixa de aviso permanente no topo: quem abrir por engano sabe em 1 segundo que não é produção.
- Botão "Finalizar venda" **desabilitado** por construção.

**Âncora de design:** `prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx`
(cockpit "Venda — Guia de Produção", importado do projeto de design Oimpresso `019e2365` em 2026-08-06).
Roda local em `http://localhost:5570` via a config `cockpit-vendas` do `.claude/launch.json`.

## §F4 QA

Ambiente: **staging** (`https://staging.oimpresso.com`, CT 100), publicado por branch:

```bash
tailscale ssh root@ct100-mcp 'bash /opt/oimpresso-staging/code/docker/oimpresso-staging/deploy.sh claude/sells-create-v3'
```

Staging é máquina separada da produção (Hostinger), com banco próprio
(`oimpresso-staging-db`), APP_KEY própria e credenciais/certificados truncados —
não cobra, não emite NFe, não conecta WhatsApp.

⚠️ **Staging é compartilhado.** Deployar substitui a branch publicada; conferir com o time antes.

Smoke: abrir `/sells/create-v3` autenticado e conferir (a) faixa de preview visível,
(b) 3 passos renderizados, (c) botão de finalizar desabilitado, (d) zero erro no console.
Nunca usar `biz=4` em teste — [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).

## §F5 CUTOVER

**Não há cutover previsto.** Esta tela não substitui `/pos/create`.

Se um dia a V3 for candidata a virar a tela real, isso é decisão [W] e exige processo próprio:
paridade contra a Blade + todo CU `must` verde na lane + canary — e aí a fronteira acima deixa de valer,
porque o alvo passa a ser justamente o arquivo que hoje é intocável.

## Pendências declaradas

- Trio (charter + casos) ainda não existe para esta tela — nasce quando o desenho assentar.
- `php -l` local não valida este módulo: a máquina tem **PHP 7.4** e o projeto exige **^8.3**.
  Sintaxe PHP 8 (`?->`) reprova localmente mesmo em arquivo íntegro do `main`. Validação real é CI/CT 100.
