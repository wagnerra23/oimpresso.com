---
owner: W
last_validated: "2026-08-28"
slug: ponto-runbook-welcome
title: "Ponto — Runbook do hub de boas-vindas (/ponto/react · Welcome)"
type: runbook
module: Ponto
tela: Ponto/Welcome
status: ativo
date: 2026-08-28
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Hub de boas-vindas (`Ponto/Welcome`)

> **Este RUNBOOK é RETROATIVO, e isso importa para ler o resto.** A tela já está em Inertia/React
> desde antes deste documento — o F3 do MWART não está pendente, está *feito*.
> Ele nasce porque o hook `block-mwart-violation` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) §F1)
> barra `Edit` em `Pages/Ponto/Welcome.tsx` enquanto não existir `RUNBOOK-welcome.md` aqui,
> e o bloqueio **não tem escape**.
>
> ⚠️ Esta é a **única tela flat** do módulo (sem subpasta), então o hook resolve o nome pela
> própria tela — `RUNBOOK-welcome.md` — e não por rota, como nas outras sete famílias.

---

## 1. O que é a tela — e a pergunta que está aberta sobre ela

Página de boas-vindas com saudação + **4 cards-atalho** (Aprovações, Banco de horas, Espelho de
ponto, Importações). Renderiza **sem props do backend**: nome do usuário e do business vêm dos
shared props via `useAuth()` / `useBusiness()`.

| Rota | Backend | Renderiza |
|---|---|---|
| `GET /ponto/react` | **closure** em `Modules/Ponto/Http/routes.php` — não há controller | `Inertia::render('Ponto/Welcome')` |

🔴 **Ela nasceu como PILOTO, e o próprio código diz isso.** Comentário literal no `routes.php`
(medido, linhas 33-34):

> *"Piloto React/Inertia — página de boas-vindas para validar pipeline TW4+shadcn.
> **Substituir pelo Dashboard real quando piloto estiver aprovado visualmente.**"*

O charter repete a dúvida em §Pendências: *"Decidir se `/ponto/react` (piloto) permanece ou é
substituído pelo Dashboard real"*.

**Consequência prática para quem chega aqui:** a home real do módulo é **`/ponto` →
`DashboardController@index`**, não esta. Antes de investir esforço em `Welcome.tsx`, note que
**pode ser trabalho numa tela destinada a sair**. Essa é decisão [W] e está **aberta** — não a
resolvi, e não a resolva por inferência.

---

## 2. Fontes canônicas (ordem de precedência)

Vale a regra-mestre de [proibicoes.md](../../proibicoes.md):
**teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`**.

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | `Welcome.charter.md` — **`status: draft`** | única fonte específica desta tela |
| 2 | [`SDD-espelho-e-jornada-v1.0.md`](SDD-espelho-e-jornada-v1.0.md) §1.1 (grupo "Painéis") | contexto na família de telas |
| 3 | `Modules/Ponto/Http/routes.php` (a closure + o comentário) | backend real |

⚠️ **Não existe fluxo F nem CU-PONTO-* para esta tela** — o SDD a lista no §1.1 e não lhe dá caso de
uso, o que é coerente com ela ser navegação pura. **Não inventei um.** O charter declara
`related_prototype: n/a` com razão escrita ("não segue um dos 5 Padrões de Tela") — isso é decisão
declarada, não default silencioso.

---

## 3. Estado MEDIDO em 2026-08-28

| Item | Estado |
|---|---|
| Tela em Inertia/React sobre `AppShellV2` | ✅ |
| `Welcome.charter.md` | ✅ existe — **`status: draft`** |
| `Welcome.casos.md` | ❌ **não existe** (fora do escopo deste PR — ver nota) |
| Scorecard | ✅ `ponto-welcome.yaml` |
| Cor crua no `.tsx` | **0** — usa `primary` / `foreground` / `muted-foreground`, sem paleta crua |
| Props do backend | **nenhuma** — a closure não passa nada |
| Destino da tela | ⚠️ **em aberto** — ver §1 |

O `casos.md` faltante **não é deste PR**: há decisão registrada de atacar primeiro os UC órfãos.
Mais contrato declarado com a mesma prova zero seria dobrar a aposta do presence-gate — e aqui
pesa ainda mais, porque a tela pode ser descontinuada.

⚠️ Os 4 atalhos são **hrefs literais** no array `SECOES` (`/ponto/aprovacoes`, `/ponto/banco-horas`,
`/ponto/espelho`, `/ponto/importacoes`), não `route()`. Se alguma rota mudar de path, **estes links
não acompanham** e nenhum gate avisa.

Cobertura **não é restateada aqui** — rode `npm run screen-coverage:report` e `npm run casos:report`.

---

## 4. Verificação

```bash
node .claude/hooks/block-mwart-violation.mjs   # path no stdin: rc=0 após este RUNBOOK
node scripts/memory-schemas/validate.mjs memory/requisitos/Ponto/RUNBOOK-welcome.md
npm run typecheck:baseline:check               # delta deve ser +0
```

Pest e PHPStan **não** rodam local nem no Hostinger — CT 100, sempre
([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## 5. Não fazer

- ❌ **Não decidir sozinho o destino da tela** (manter, aposentar, virar a home) — é [W], e está
  aberto desde o comentário original do `routes.php` e do charter. Ver §1.
- ❌ **Não adicionar KPI, marcação ou dado de ponto aqui.** Non-Goal explícito do charter: a tela é
  navegação, sem props do backend. Quem mostra número é o `Dashboard/Index`.
- ❌ **Não confundir com a home do módulo** — `/ponto` é o `DashboardController`; esta é `/ponto/react`.
- ❌ **Não introduzir cor crua** — hoje a tela está limpa (0 ocorrências), e é a única do módulo que
  está. Não seja quem quebra isso.
- ❌ **Não promover o charter a `status: live`** — depende de [W] aprovar Non-Goals + Anti-hooks
  **e** de resolver a pendência do §1.
