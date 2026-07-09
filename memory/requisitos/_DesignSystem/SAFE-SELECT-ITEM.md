---
doc: SAFE-SELECT-ITEM
camada: componente-ds
status: ativo
created: 2026-07-09
parent_adr: UI-0013
related: [proibicoes.md §5, PR #3405, PR #3411]
---

# `<SafeSelectItem>` — defesa de borda contra o crash de value vazio do Radix Select

> **Classe de erro (Tier 0 — render):** o Radix `<Select.Item>` **lança e derruba o
> render INTEIRO** da árvore React — **tela branca em produção**, não degradação
> parcial — se qualquer item tiver `value=""` (string vazia):
> `A <Select.Item /> must have a value prop that is not an empty string`.

## Por que dói (e por que CI verde não salva)

Já aconteceu ([proibicoes §5, 2026-06-29](../../proibicoes.md) · PR #3405 → hotfix #3411):
opções **data-driven** (distinct do banco) com um `slug`/`endpoint` **NULO/vazio**
viraram `<SelectItem value="">` e **derrubaram a tela**. Os **20 checks de CI
passaram VERDES** — `ds-report`, `tsc`, `lint`, tudo — porque **nenhum gate
exercita o render com dado real vazio**. Só o **smoke real** (browser MCP, console
`EXCEPTION`) pegou. Nenhum ESLint sintático enxerga `value` possivelmente vazio
quando ele vem de `map`/`reduce`/helper/`?? ''`/`distinct`.

## Duas defesas (uma por sub-caso)

| Sub-caso | Defesa | Onde |
|---|---|---|
| `value` vem de **DADO** (map/distinct/`Object.keys`/prop) e um membro vazio é plausível | **`<SafeSelectItem>`** — se `value` chegar `null`/`undefined`/`''`/só-espaços, a opção **some** em vez de crashar | [`@/Components/ui/SafeSelectItem`](../../../resources/js/Components/ui/SafeSelectItem.tsx) |
| Existe um item **fixo** com significado "Todos"/"Nenhum" | **SENTINELA não-vazio** (`__all__`) + mapear `'' ↔ sentinela` na borda do `<Select>` | ex.: [`governance/Audit.tsx`](../../../resources/js/Pages/governance/Audit.tsx), [`Nfse/Index.tsx`](../../../resources/js/Pages/Nfse/Index.tsx) |

**Nunca** `<SelectItem value="">`. Nunca troque um "Todos" por `<SafeSelectItem value="">`
(ele sumiria — o usuário perde o reset); use o **sentinela**.

### A garantia mora na BORDA — de propósito

A defesa real é o **componente**, não um ESLint de análise de fluxo. Um lint
sintático só vê o `.map` literal — `map`/`reduce`/helper/`?? ''` **furam**. O
`<SafeSelectItem>` é seguro **por construção**: qualquer que seja a forma de gerar
o `value`, o guard roda no render. Aceita `value: string | null | undefined` de
propósito (value inválido → não renderiza).

## Lint advisory (EXTRA, não é a defesa)

Regra ESLint `ds/no-radix-item-empty-value` ([eslint.config.js](../../../eslint.config.js),
bloco `ds/*`) — **nó único** que pega só a forma **LITERAL** vazia (`value=""` e
`value={''}`) num `<SelectItem>`. É um cinto barato contra reintrodução literal;
**não** tenta (nem consegue) cobrir o caso data-driven — pra isso é o componente.

## ⚠️ Honestidade: isto NÃO substitui o smoke real (R1)

"CI verde" continua **NÃO** provando render. Estas defesas evitam o **crash** da
classe empty-value; elas **não** dispensam o smoke real pós-deploy (browser MCP,
console sem `EXCEPTION`) exigido pela **R1**. E uma opção que some silenciosamente
é sinal de **bug de dado a montante** (distinct trazendo NULL) — investigue a
origem, não só abafe.

## Adoção nesta PR

**Migrado pra `<SafeSelectItem>`** (telas ativas cujo `value` vem de distinct/texto-livre):

- `Pages/kb/Index.tsx` — `Object.keys(kpis.tipos)`, `Object.keys(kpis.modulos)`
- `Pages/team-mcp/Tasks/Index.tsx` — `modulos`, `owners`, `sprints`
- `Pages/team-mcp/CcSessions/Index.tsx` — `projList`
- `Pages/ProjectMgmt/Backlog/Index.tsx` — `owners` (×2), `sprints`
- `Pages/ProjectMgmt/Activity/Index.tsx` — `event_types`, `authors`

**Corrigido via sentinela** (item "Todos" literal `value=""` — crash latente vivo):

- `Pages/Nfse/Index.tsx` — filtro de status (`STATUS_ALL = '__all__'`)

**Já defendido antes desta PR** (via `.filter(Boolean)`/`.filter(a => a.slug)`):

- `Pages/governance/Audit.tsx` — a tela do incidente #3405/#3411.

## O resto (sweep não concluído — sem cap silencioso)

Há **~184** `<SelectItem value={…}>` data-driven no repo. Esta PR migrou o
subconjunto ativo de maior sinal; o sweep completo é trabalho futuro. Classificação
pra priorizar:

- **Baixo risco — não urgente:** `value={String(x.id)}` / `x.id.toString()` (PK nunca
  é `''`) e maps sobre **arrays constantes** (`CARD_TYPES`, `CARD_MONTHS`, `UFS`,
  `[20,50,100…]`, `Object.values(DRIVERS)`, listas `{value,label}` de enum fixo).
- **Candidatos a adotar `<SafeSelectItem>` (rever):** onde o `value` é uma **string
  distinct/texto-livre** — ex. `ProjectMgmt/Triage` e `ProjectMgmt/Board` (`owners`),
  e qualquer `value={k}`/`value={u}` cujo `k`/`u` venha de `Object.keys`/distinct de
  dado (checar por arquivo antes de assumir; muitos `k` são enum constante).

**Regra pra código novo:** `value` de item que vem de dado → **`<SafeSelectItem>`**.
Item fixo "Todos"/"Nenhum" → **sentinela `__all__`**, jamais `value=""`.
