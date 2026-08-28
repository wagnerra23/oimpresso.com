---
owner: W
last_validated: "2026-08-28"
slug: ponto-runbook-relatorios
title: "Ponto — Runbook do catálogo de Relatórios (/ponto/relatorios · Relatorios/Index)"
type: runbook
module: Ponto
tela: Ponto/Relatorios/Index
status: ativo
date: 2026-08-28
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Catálogo de Relatórios (`Ponto/Relatorios/Index`)

> **Este RUNBOOK é RETROATIVO, e isso importa para ler o resto.** A tela já está em Inertia/React
> desde antes deste documento — o F3 do MWART não está pendente, está *feito*.
> Ele nasce porque o hook `block-mwart-violation` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) §F1)
> barra `Edit` em `Pages/Ponto/Relatorios/*.tsx` enquanto não existir `RUNBOOK-relatorios.md`
> aqui, e o bloqueio **não tem escape**.
>
> Conteúdo **derivado** do SDD, do controller e do `.tsx`. Onde algo não foi medido, está dito
> que não foi. Anti-padrão inventado em RUNBOOK é pior que ausente: parece canon.

---

## 1. O que é a tela

**Catálogo** de relatórios do módulo — 8 cards, cada um com flag `disponivel`, sobre um filtro
comum de período (`<input type="month">`) e, opcionalmente, colaborador.

| Rota | Método | Controller |
|---|---|---|
| `GET /ponto/relatorios` | `index` | `RelatorioController@index` |
| `GET /ponto/relatorios/{chave}` | `gerar` | idem |

Título: **"Relatórios · Geração de documentos"** (`Index.tsx:132`). SubNav ativo: `relatorios`.

As 8 chaves (medidas no controller): `afd`, `afdt`, `aej`, `espelho`, `he`, `banco-horas`,
`atrasos`, `esocial`.

---

## 2. 🔴 O estado real: o catálogo lista 8 e entrega 0 POR AQUI

Isto é o mais importante do arquivo, e é **medido**, não inferido:

- **`RelatorioController@gerar()` é `abort(501)` para QUALQUER chave** — uma linha só, sem
  ramificação. Inclusive `espelho`.
- **Só `espelho` tem `disponivel: true`**; os outros 7 são `false`.
- No `.tsx`, `disponivel: true` renderiza `<Badge>Disponível</Badge>` e um botão **habilitado**
  que aponta para `/ponto/relatorios/espelho?periodo=...` (`hrefGerar`, `Index.tsx`).

⚠️ **Logo: o único card marcado "Disponível" leva a um 501.** O SDD §5.3 F8 e §5.4 item 4 já
registram isso, e o SDD explica por quê não é tão grave quanto parece: **o PDF do espelho existe e
funciona — ele sai pelo F3** (`GET /ponto/espelho/{id}/imprimir` → `ReportService::espelhoPdf()`),
que é outro caminho, alcançado pela tela do Espelho. O catálogo é que não está ligado nele.

Isso é exatamente o que `CU-PONTO-14` `[should]` cobra: *"o catálogo não promete o que não entrega;
nenhum botão leva a erro 501 sem aviso"*. **Hoje um leva.** ⚠️ Não exercitei a rota em ambiente
nenhum — a afirmação vem da leitura do `abort(501)` no controller e do `disabled={!r.disponivel}`
no `.tsx`, que juntos são inequívocos, mas não são um request executado.

**Não conserte isto de carona.** Há pelo menos três saídas plausíveis (apontar o card para o F3;
implementar `gerar('espelho')`; marcar `espelho` como `disponivel: false`) e escolher entre elas é
decisão de produto do [W], não refinamento meu.

---

## 3. Uma prop que o backend nunca envia

`Props.colaboradores?` é **opcional** no `.tsx`, e o filtro de colaborador só renderiza sob
`listaColaboradores.length > 0`. Medido no controller: `index()` devolve **somente** `relatorios`.

Logo o **seletor de colaborador nunca aparece hoje** — não é bug de render, é prop que ninguém
manda. O `.tsx` foi escrito preparado para recebê-la. Quem for ligar o filtro precisa mandar a lista
do backend (com `business_id` escopado e sem CPF/PIS no payload — ver o RUNBOOK de Colaboradores).

---

## 4. Fontes canônicas (ordem de precedência)

Vale a regra-mestre de [proibicoes.md](../../proibicoes.md):
**teste verde citando o UC > `.casos.md` > `.charter.md` > `SPEC.md`**.

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | [`SDD-espelho-e-jornada-v1.0.md`](SDD-espelho-e-jornada-v1.0.md) §5.3 **F8**, §5.4 item 4, §6.5 **CU-PONTO-14** | fluxo, dívida e caso de uso |
| 2 | `Relatorios/Index.casos.md` | contrato executável (UC) |
| 3 | `Relatorios/Index.charter.md` — **`status: draft`** | intenção-lei, não ratificada |
| 4 | [`SPEC.md`](SPEC.md) US-PONTO-006 (AFD legacy) · **US-PONTO-009 (AEJ canon)** | escopo |
| 5 | Lei: **Portaria MTP 671/2021 Anexo VI** (AEJ) | contrato de domínio |

⚠️ **`AFDT` está OUTDATED** — o SPEC registra que a Portaria 671/2021 o substituiu por **AEJ canon**
(US-PONTO-009, marcada crítico-regulatório). O card `afdt` do catálogo é herança; **não invista nele
sem ler a US-PONTO-009 antes**.

---

## 5. Estado MEDIDO em 2026-08-28

| Item | Estado |
|---|---|
| Tela em Inertia/React sobre `AppShellV2` | ✅ |
| `Index.charter.md` | ✅ existe — **`status: draft`** |
| `Index.casos.md` | ✅ existe |
| Scorecard | ✅ `ponto-relatorios-index.yaml` |
| Cor crua no `.tsx` | 1: `:132 text-stone-400` (padrão do `os-page-h`) |
| Mapa `cor` → token | ✅ já tokenizado (`corClasses`), com nota no fonte de que **azul hue 240 é proibido** no DS roxo |
| Relatórios funcionais **por esta rota** | **0 de 8** — ver §2 |

O bloco `// @docvault` no topo do `.tsx` cita `US-PONT-012`, `R-PONT-001/005` e um teste em
`Modules/PontoWr2/Tests/...` — ⚠️ **vocabulário legacy `PontoWr2`, não `Ponto`**. Não verifiquei se
esses ids e esse teste existem hoje; **não os trate como âncora** sem conferir.

Cobertura **não é restateada aqui** — rode `npm run screen-coverage:report` e `npm run casos:report`.

---

## 6. Verificação

```bash
node .claude/hooks/block-mwart-violation.mjs   # path no stdin: rc=0 após este RUNBOOK
node scripts/memory-schemas/validate.mjs memory/requisitos/Ponto/RUNBOOK-relatorios.md
npm run typecheck:baseline:check               # delta deve ser +0
npm run casos:check
```

Pest e PHPStan **não** rodam local nem no Hostinger — CT 100, sempre
([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## 7. Não fazer

- ❌ **Não marcar relatório como `disponivel: true` antes de o `gerar()` responder** por aquela
  chave. É a violação direta de `CU-PONTO-14` — e o card do `espelho` já é esse caso (§2).
- ❌ **Não "consertar" o 501 do espelho por conta própria** — as três saídas são decisão [W] (§2).
- ❌ **Não usar cor crua nos cards.** O mapa `corClasses` já traduz a "cor" lógica do backend para
  token do DS, e o fonte registra que **azul (hue 240) é proibido** — `'blue'` cai no primary roxo.
- ❌ **Não investir no `afdt`** sem ler US-PONTO-009 antes — ver §4.
- ❌ **Não promover o charter a `status: live`** — depende de [W] aprovar Non-Goals + Anti-hooks.
