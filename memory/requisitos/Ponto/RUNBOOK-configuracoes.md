---
owner: W
last_validated: "2026-08-28"
slug: ponto-runbook-configuracoes
title: "Ponto — Runbook das Configurações (/ponto/configuracoes · Configuracoes/Index + Configuracoes/Reps)"
type: runbook
module: Ponto
tela: Ponto/Configuracoes/Index
status: ativo
date: 2026-08-27
---

# RUNBOOK — Configurações do Ponto (`Ponto/Configuracoes/Index` + `Ponto/Configuracoes/Reps`)

> **Este RUNBOOK é RETROATIVO, e isso importa para ler o resto.** A tela já está em
> Inertia/React desde antes deste documento — o F3 do MWART não está pendente, está *feito*.
> Ele nasceu porque o hook `block-mwart-violation` (ADR 0104 §F1) barra `Edit` em
> `Pages/Ponto/Configuracoes/*.tsx` enquanto não existir `RUNBOOK-configuracoes.md`, e o
> bloqueio **não tem escape** — a mensagem do próprio hook diz isso com todas as letras.
>
> Conteúdo **derivado** do controller, do `.tsx` e do charter. Onde algo não foi medido,
> está dito que não foi. Anti-padrão inventado em RUNBOOK é pior que ausente: parece canon.

---

## 1. O que é a tela

Painel **read-only** dos parâmetros vigentes do módulo de ponto. O gestor consulta como a
apuração está parametrizada — não altera nada por aqui.

| Rota | Componente | Controller |
|---|---|---|
| `/ponto/configuracoes` | `Ponto/Configuracoes/Index` | `ConfiguracaoController@index` |
| `/ponto/configuracoes/reps` | `Ponto/Configuracoes/Reps` | `ConfiguracaoController@reps` |

**Índice** (`@index`) é trivial por construção: lê `config('pontowr2')` e passa inteiro como
prop `config`. Sem query, sem `business_id`, sem paginação.

**Reps** (`@reps`) é a que toca banco: `Rep::where('business_id', …)->paginate(20)`, com
`business_id` vindo de `session('business.id') ?? $request->user()->business_id`.

Os 4 cards do Index (medidos no `.tsx`, linhas 76–128):

1. **CLT — tolerâncias e limites** · `Art. 58, 59, 66, 71, 73`
2. **Banco de Horas** — limite e expiração
3. **REPs & Imutabilidade** · `Portaria MTP 671/2021`
4. **AFD & eSocial**

---

## 2. Fontes canônicas (ordem de precedência)

Vale a regra-mestre de [proibicoes.md](../../proibicoes.md):
**teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`**.

1. `resources/js/Pages/Ponto/Configuracoes/Index.charter.md` — **`status: draft`**, não `live`
2. `config/pontowr2.php` — a fonte real dos valores exibidos
3. `Modules/Ponto/Http/Controllers/ConfiguracaoController.php`
4. Lei: CLT Art. 58/59/66/71/73 · Portaria MTP 671/2021

---

## 3. Estado MEDIDO em 2026-08-27

| Item | Estado |
|---|---|
| Tela em Inertia/React sobre `AppShellV2` | ✅ |
| `Index.charter.md` | ✅ existe — mas **`status: draft`** |
| `Index.casos.md` | ❌ **não existe** |
| `Reps.casos.md` | ❌ **não existe** |
| Scorecard | ✅ `ponto-configuracoes-index.yaml` · `ponto-configuracoes-reps.yaml` |
| E2E / a11y / VRT | ❌ nenhum (o módulo tinha VRT 0/20 até o PR #6365) |
| Token de cor | ❌ **3 sites crus** — `Index.tsx:74` (`border-t-blue-500`) · `:104` (`border-t-violet-500`) · `:124` (`border-t-amber-500`) — ⚠️ **ver errata abaixo** |

Fonte dos números: `npm run screen-coverage:report` e `npm run casos:report` — não os
reproduza à mão aqui, **rode as portas** (§5 2026-07-17: doc canônico não restateia número
que outro sistema sabe melhor).

> ⚠️ **Errata 2026-08-28 — a contagem de token estava errada NO PRÓPRIO DIA, não apodreceu.**
> A linha dizia "2 sites crus" e listava `blue` e `violet`. São **3**: `:124 border-t-amber-500`
> (card *AFD & eSocial*) nunca foi contado. Medido no histórico: o amber está no arquivo desde
> `1676c196ca` (**2026-05-08**) e atravessou todos os commits seguintes — nunca foram 2.
>
> **Por que corrigir uma medição datada, em vez de preservá-la.** A doutrina preserva o que
> **era verdade** numa data; ela não protege um número que nunca foi verdade. "2" nunca foi.
> O que se preserva aqui é o *fato datado real* — em 2026-08-27 havia 3 — e a errata declara
> a diferença em vez de reescrever a história em silêncio.

---

## 4. O trabalho que este RUNBOOK destravou

> ⚠️ **Errata 2026-08-28 — este bloco descrevia como *pendente* um trabalho que o PRÓPRIO
> commit deste RUNBOOK já tinha feito.** Medido: `149f1e5b0f` (o commit que criou este
> arquivo, [#6369](https://github.com/wagnerra23/oimpresso.com/pull/6369)) sai com **1** cor
> crua; o commit anterior, `424b23523f`, tinha **3**. Os dois fixes entraram no mesmo diff.
> Afirmação de pendência em **tempo presente** é a forma que apodrece (LC-10) — vai a passado.

Ele existiu para permitir **um** Edit específico, e ele **foi aplicado**:

- `Index.tsx:74` — `border-t-blue-500` → **`border-t-info`** ✅ aplicado
- `Index.tsx:104` — `border-t-violet-500` → **`border-t-primary`** ✅ aplicado
- `Index.tsx:124` — `border-t-amber-500` → **segue cru**, e nunca esteve nesta lista (§3 errata)

É a continuação do PR-A1 ([#6362](https://github.com/wagnerra23/oimpresso.com/pull/6362)),
que fez o mesmo no `Espelho/Show` mas **não pôde** tocar esta tela: o guard barrou, e a
tentativa de contornar por `sed` (que passa por baixo do hook, por ele ser `PreToolUse`) foi
revertida de propósito. Guard que barra arquivo é pré-requisito, não obstáculo.

Controle positivo já medido no A1: `border-info` (7 usos), `border-l-primary` (4),
`border-b-primary` (1) já existem em produção ⇒ o utility direcional é gerado pelo Tailwind
a partir dos tokens semânticos, e a classe não nasce morta.

---

## 5. Verificação

```bash
npm run typecheck:baseline:check     # Delta deve ser +0
npm run ds:canon:check               # paleta crua em ui/ + shared/
npm run casos:check                  # sem violação nova
node .claude/hooks/block-mwart-violation.mjs   # com o path no stdin: rc=0 após este RUNBOOK
```

Pest e PHPStan **não** rodam local nem no Hostinger — CT 100, sempre ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## 6. Não fazer

- ❌ **Não tornar os parâmetros editáveis pela UI** sem decisão do [W]. O charter marca isso
  como Non-Goal explícito, e a pergunta "deve virar por-business?" está **aberta** lá — hoje
  é config de arquivo, global, fora do escopo de `business_id`.
- ❌ **Não ligar/desligar imutabilidade de REP pela tela.** Os triggers MySQL são infra sob
  Portaria MTP 671/2021; a tela só *reporta* o estado.
- ❌ **Não tratar os stubs de eSocial (S-1010/S-2230/S-2240) como implementados.**
- ❌ **Não editar `Configuracoes/*.tsx` por `sed`/codemod para escapar do guard.** Se o hook
  barra, o caminho é o pré-requisito que ele nomeia — nunca a porta dos fundos.
- ❌ **Não promover o charter a `status: live`** — depende de [W] aprovar Non-Goals +
  Anti-hooks, e isso é soberania dele, não inferência minha.
