---
date: "2026-08-12"
slug: "pages-no-modulo-dono-mergeado-e-a-refutacao-que-reprovou"
tldr: "PR #5686 MERGED: 73 de 445 telas Inertia migraram para o módulo dono, sem mudar nenhum Inertia::render. 13 máquinas aprenderam a 2ª raiz. A refutação GT-G5 REPROVOU na rodada 1 e achou 2 números que eu afirmei sem nunca ter medido. Falta smoke real."
topic: "Pages no módulo dono — mergeado, e a refutação que reprovou primeiro"
title: "Pages no módulo dono — mergeado, e a refutação que reprovou primeiro"
type: reference
authority: historical
lifecycle: ativo
owner: W
---

# Pages no módulo dono — mergeado, e a refutação que reprovou primeiro

## Estado

**[PR #5686](https://github.com/wagnerra23/oimpresso.com/pull/5686) MERGED** 2026-08-12T20:51:07Z
(squash, auto-merge) — **132 checks SUCCESS · 3 skipped · 0 falhas**.
**[PR #5710](https://github.com/wagnerra23/oimpresso.com/pull/5710) MERGED** (upsert de comentário de gate).

## A decisão

[W]: *"eles têm que ficar nos seus respectivos módulos e o Inertia tem que achar e o Vite tem que
compilar"*. Etapa 1 = **mover**; renomear namespace ficou para outro PR.

## O mecanismo, e por que não mudou call-site

Onde as Pages vivem é **convenção nossa**, não imposição do Inertia. `app.tsx` e `ssr.tsx` declaram
**dois** globs e normalizam a chave do módulo para o namespace do núcleo. O namespace **não muda com
o local do arquivo** → nenhum `Inertia::render` mudou.

| Namespace | Dono(s) |
|---|---|
| `Settings` | PaymentGateway (piloto, 12 arquivos) |
| `Atendimento` | Whatsapp (38) |
| `ads` | Forja (4) + KB (1) — **dividido, sem renomear** (ADR 0363: URLs/permissions congeladas) |
| `Site` | Cms (4) + Superadmin (1) — **Login/Register ficam no núcleo** (são `Auth`) |
| `team-mcp` + `Forja` | Forja — **juntos**, porque um importava `ForjaHub` do outro |

**73 de 445** páginas no módulo dono. O resto é namespace homônimo — migra quando alguém tocar.

## O custo real não era o `git mv` — eram 13 máquinas

Toda máquina que resolvia tela por path tinha a raiz `resources/js/Pages` **fixa**. Mover 73 telas
as tirava do denominador de todas de uma vez: catracas reprovando **por cegueira**, não por regressão.

`page-path` · `module-surface` · `screen-coverage` (169→206 telas) · `casos-coverage` ·
`ui-impact` (+ grafo de consumidores) · `ModuleGradeService` · `OrphanRenderGateTest` ·
`module-group.json` · `deadlink-gate` · `maquinas-inventario` · `config/inertia.php::pages.paths` ·
imports em `tests/` · paths em 9 arquivos PHP.

## As armadilhas (todas medidas, nenhuma teórica)

1. **Casing** — a convenção nWidart é `Resources/` maiúsculo e o glob do Vite é case-sensitive. No
   Windows o `mkdir` funde; só o CI Linux acusaria. **Mordeu 3× na mesma sessão.**
2. **Build verde não prova nada** — controle negativo: sem o glob de módulos o build **também** sai
   exit 0; a tela só não entra no bundle (0 chunks contra 1). A prova é o **manifest**.
3. **Prefixo ≠ existência** ao decidir se um import saiu da área.
4. **Um comprimento de `../` não é a família toda.**
5. **Reescrita sem âncora come o vizinho** — aconteceu 3× (re-key, fixtures, e um regex que
   apagou 120 linhas do `config/inertia.php`; restaurado com `git checkout HEAD --`).
6. **Direção inversa** — o que **ficou** importando o que **saiu**: não está no diff, e com alias
   `@/` o resolvedor nem olha. Sintoma de **recorte errado**.

## A refutação GT-G5 reprovou primeiro — e é isso que a valida

Rodada em subagente de contexto próprio (mecanismo já registrado na entry do #5680).

**Rodada 1: REPROVADO — 2,08% (10/480).** Achou 3 defeitos meus:
- *"os 232 `Inertia::render`"* — **nenhum comando reproduz 232**. Eu repeti esse número a sessão
  inteira: em commits, no corpo do PR, nas respostas ao [W]. Nunca o medi. Real: **218**.
- *"1.785 imports usam `@/`"* — não reproduz. Real: **1.791** sob `Pages/`, contra 85 relativos.
- `ADS/UI-CATALOG.md` apontando para `resources/js/Pages/ads/` — **0 arquivos** (re-key incompleto).

**Rodada 2: aprovado — 1,64% (8/487)**, e ainda achou 2:
- **Eu inverti a lápide que estava citando.** Escrevi *"o `*` não atravessa `/`"* citando a §5
  2026-07-28, cujo título diz o oposto. Medido: `'Modules/*.php'` → 3.921 (atravessa);
  `':(glob)Modules/*.php'` → 0. E o `:(glob)` **não mudava nada** naquele comando — o que fazia o
  218 estar certo era a **ancoragem das raízes**.
- O "232" corrigido no doc **continuava vivo** em `app.tsx:106` — linha adicionada pelo mesmo PR.
  Fechei a **instância**, não a **classe**. É o recibo empírico de por que o §2.6 manda re-verificar
  o lote inteiro.

Entry no ledger registra o resultado **real** (rodada 1 reprovou), o denominador declarado (487) e
a aritmética robusta ao recorte (1,64% / 1,69% — todas < 2%). PII = 0 **com controle positivo**
(o detector mordeu 7/7 em linhas plantadas).

## Decisões que recusei

- **Não carimbei baseline** para fazer passar. A queda do `Forja` (85→81) era **fantasma** — código
  idêntico, só mudou de lugar; consertei o **medidor**. O gate oferecia "atualize o baseline" e
  "aplique label de exceção": as duas gravariam uma regressão que não existe.
- **Não usei o `evidence-override`** do Infra Contract (é para hotfix). Declarei a medição: 280
  arquivos no PR, **0** em paths de runtime crítico.
- **Não pedi a label `adr-body-edit-W`** que eu mesmo tinha pedido 3× — quando medi o critério do
  gate, descobri que os 2 deadlinks eram **meus** (não dívida herdada) e que o conserto certo era
  ensinar o `deadlink-gate` a 2ª raiz, não editar ADR append-only.

## Efeito colateral que virou PR próprio

[W]: *"isso é chato… esse erro é recorrente. e custa muito"*. Medido: 27 comentários no PR, **22
redundantes** — cada push criava um novo. O padrão de upsert já existia (`pr-critic/comentar.mjs`),
e **5 dos 6** workflows que comentam o ignoravam. [#5710](https://github.com/wagnerra23/oimpresso.com/pull/5710)
estendeu o dono. **Provado em produção**: 2 runs de cada gate → **1 comentário cada**.

## Dívida honesta (não fingir que fechou)

- ⚠️ **Smoke real NÃO foi feito.** Provei que **compila e resolve** (build client + SSR exit 0,
  manifest apontando para os paths do módulo), **não** que renderiza igual. R1 exige o smoke antes
  de "pronto" — prioridade em `Whatsapp/Atendimento` (38 arquivos, a maior onda).
- **O re-key piorou o sinal** de 4 paths mortos pré-existentes: antes tinham forma obviamente
  legada, agora são indistinguíveis dos vivos. Dívida igual, **camuflagem maior**. O refutador
  sugeriu marcação inline no padrão do `P09-sa-a4-sanear-placeholders`.
- **3 links com profundidade `../../../../` errada** em `TeamMcp/*-visual-comparison.md`
  (pré-existentes, mecânicos de corrigir).
- **`bootstrap/ssr/` não está no `.gitignore`** (ao contrário de `public/build-inertia/`) — quem
  rodar build SSR suja o working tree.
- **1.654 branches locais**, 1.543 órfãs (remota apagada no merge). 868 seguras para limpar; 675
  nunca tiveram upstream e exigem conferência antes.

## Próximos passos

1. **Smoke real pós-merge** (R1) — a dívida mais importante.
2. **Etapa 2**: renomear namespaces divergentes para casar com o módulo (`Atendimento`→`Whatsapp`),
   PR separado como combinado.
3. Migrar o resto conforme as telas forem tocadas — receita em
   [`RUNBOOK-migrar-pages-para-modulo.md`](../requisitos/_DesignSystem/RUNBOOK-migrar-pages-para-modulo.md).

## Estado MCP no fechamento

`cycles-active` → **nenhum cycle ativo** em COPI. `my-work` → **8 tasks em REVIEW** (US-TR-309/310/311,
US-PROD-027, US-INFRA-023/048, US-TR-305/306) — nenhuma relacionada a este trabalho.
Handoffs de hoje (não sobrescritos): 1724, 1730, 1755, 1810, 1822 — este é o 6º.
