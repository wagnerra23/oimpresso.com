---
sessao: "02"
titulo: "Recibo — título 24→22px em Repair/Index e JobSheet/Index"
autor: "[CL]"
data: 2026-10-02
base: "claude/repair-thread-A1-alvos, sobre origin/main 13bc079894"
thread: 04-titulo.md
veredito: "código entregue; a prova D4 está pela METADE — o lado design foi medido (22px), o lado produção só pode ser medido depois do deploy"
---

# _saída 02 · Título 24→22px

## Diagnóstico — o defeito estava no componente que as telas usavam

Os 24px não vinham das duas telas. As duas importavam o header **shared**,
`resources/js/Components/shared/PageHeader.tsx`. O `<h1>` desse componente fixa
`text-xl md:text-2xl` na linha 71, o que dá 24px a partir de 768px. O próprio arquivo se declara
`@deprecated CONGELADO`: tela nova usa o canon `@/Components/PageHeader`, e tela existente migra
o header no PR em que for tocada.

O canon `resources/js/Components/PageHeader/PageHeader.tsx` já renderiza o `<h1>` em
`text-[22px]` (`--fs-7`). O `Repair/Settings/Index` já o usa.

**Corrigir no uso** foi, portanto, trocar o import das duas telas para o canon. Nenhum dos dois
componentes compartilhados foi tocado. O shared continua 24px nas outras 60 telas que ainda o
usam (contagem do `pageheader-migration-guard`).

## O que mudou

| tela | antes | depois |
|---|---|---|
| `resources/js/Pages/Repair/Index.tsx` | `shared/PageHeader` · `icon` · `description` · `action` | `@/Components/PageHeader` · `leading` (ícone wrench) · `subtitle` · `actions` |
| `resources/js/Pages/Repair/JobSheet/Index.tsx` | idem (ícone clipboard-list) | idem |

Título, texto do subtítulo e botão "Nova OS" ficaram iguais. Muda junto, pelo próprio canon:

- o subtítulo cai para `text-xs`;
- o header ganha `border-b` e padding próprio;
- o ícone deixa de ter a caixa de 40px e passa a 18px na linha do título.

## Prova D4 — pela metade

- **Lado design (medido).** Sonda canônica do `design-diff` (`--probe`) no espelho servido
  (porta 5577, dark, 1280×900), com `__DD_ROLES.title = ".rep-root .cli-ph h1"`.
  - `rep-reparos` → `title.fontPx = 22`, `weight = 600`.
  - `rep-folhas` → `title.fontPx = 22`, `weight = 600`.
- **Lado produção (NÃO medido).** Para rodar a mesma sonda na tela, o código deste branch precisa
  estar no ar. O staging roda o `main` e não há render local de Inertia (testes só no CT 100). A
  prova que fica é estática: o canon emite `text-[22px]`, e o Tailwind compila isso como
  `font-size: 22px`.
- **Para fechar, depois do deploy:**
  1. Rodar a sonda em `/repair/repair` e em `/repair/job-sheet` com
     `__DD_ROLES.title = "header[role=banner] h1"`.
  2. Rodar `node scripts/design/design-diff.mjs --compare prod.json design.json --check`.
  3. Esperado: D4 título `22px × 22px` IGUAL.

  Os `design.json` de cada tela saem da rota própria (`rep-reparos` · `rep-folhas`), não do
  blob `86af1436070d`.

## Gates locais

- `node scripts/pageheader-migration-guard.mjs` → 60 telas no header antigo (dívida registrada:
  62). Nenhuma adoção nova. A baseline **não** foi regravada: `--write` é ato do dono.
- `node scripts/typecheck-baseline.mjs` → sem regressão (302 × 333). Os erros de tipo que
  existem nas duas telas já existiam antes e estão na baseline.
- `node scripts/casos-coverage-guard.mjs` → sem violação nova deste PR.

## Required vermelho no CI — `Casos-coverage · ratchet` (G-6, frescor) — PARADO aqui

No PR #8531 o job required acusa:

- 2 violações **novas**: `stale:resources/js/Pages/Repair/Index.casos.md` e
  `stale:resources/js/Pages/Repair/JobSheet/Index.casos.md`;
- em ambos, o `.tsx` passou a ter commit em 2026-10-02, depois do `last_run` do `.casos.md`
  (2026-10-01);
- a run é 37009334748.

O meu `casos-coverage-guard` local deu verde porque rodou **antes** do commit. Com o `.tsx` só no
working tree, o gate lê a data git antiga (§5 2026-08-20). É a lápide §5 2026-07-27: tocar o
`.tsx` acorda o G-6.

**Por que não consertei:**

- O conserto é revalidar os casos e subir o `last_run` dos dois `.casos.md`.
- O `Repair/Index.casos.md` está em edição pelo PR aberto #8524, e esta thread recebeu a ordem de
  não tocá-lo.
- Subir o `last_run` sem rodar os UCs seria afirmar uma revalidação que não aconteceu. A lane
  Verticais, que roda os contratos, não roda neste PR empilhado. O CT 100 roda outro checkout.

**Para fechar:** depois do #8524, com a lane `PHP / Pest (Verticais · MySQL)` verde no branch,
subir o `last_run` dos dois `.casos.md` para a data do run e citar o run. O `JobSheet/Index.casos.md`
pode ser feito já; o `Repair/Index.casos.md` precisa ser combinado com o dono do #8524.

## Ficou fora

- **Charter de `Repair/Index`** (linha 36: *"AppShellV2 + PageHeader shared"*). Ficou desatualizado
  e precisa virar *"PageHeader canon"*. Não toquei: o prefixo desta thread é só os `.tsx`.
- **`Repair/Index.casos.md` e `RepairController.php`**: não toquei. A sessão do PR #8524 está
  nesses arquivos.
- O subtítulo de `Repair/Index` é texto de desenvolvimento ("Listagem MWART (Sprint 2)…"). Trocar
  essa copy é decisão do dono, não desta thread.
