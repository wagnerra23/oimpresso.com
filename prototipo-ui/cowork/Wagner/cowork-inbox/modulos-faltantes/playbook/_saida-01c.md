---
sessao: "01c"
titulo: "Vestuario/Etiquetas — contrato entregue depois de migrar o PageHeader para o canon"
autor: "[CL]"
data: 2026-10-13
thread: 01-contratos.md
veredito: "entregue — com o _saida-01b (comunicacao-visual, suporte-log), a thread 01 fica com as 3 telas que existem contratadas."
---
# _saida-01c · Vestuario/Etiquetas

O `_saida-01b` deixou o Vestuário de fora: a tela importava o `PageHeader` antigo e o required "PageHeader · ratchet"
exige migrá-lo no mesmo PR que tocar a tela (ADR 0409). [W] aprovou a migração em 2026-10-13 ("pode fazer, um PR por
tela").

## Entregue
- `Vestuario/Etiquetas/Index.tsx` passa para `@/Components/PageHeader`. `config/pageheader-shared-baseline.json` perde
  esta entrada.
- Contrato `vestuario-etiquetas.contract.json` (alvo `vestuario--etiquetas--index`: `cabecalho` · `config` · `lote`,
  copy 2 · 5 · 10; `previa` fora pela VEST-D2 e `aviso-permissao` fora pela VEST-D1), âncoras e `last_run` do
  `casos.md` (G-6) — o mesmo conteúdo que saiu do PR do `_saida-01b`.

## Achado: o subtítulo nunca aparecia
O `PageHeader` antigo aceita `description`, não `subtitle`. A Page passava `subtitle="ZPL Argox/Zebra + PDF · US-VEST-020"`,
que estava no código e **não renderizava**. Com o canon ele passa a aparecer. Medido no harness (mesmas props, CSS de
produção): antes só o título, bloco de 47px; depois título 22px + subtítulo 12px, bloco de 87px.

**Decisão [W] pendente:** o subtítulo mostra "US-VEST-020", identificador interno, para quem usa a tela. Mantive o
texto literal do código; tirar o id é mudança de copy.

## Provas
`pageheader-migration-guard` ✅ · `--contract` nos ativos 0 falhas · `--map --check` rc=0 (`Vestuario/Etiquetas/Index`
3/3 `🟢 portado`) · `--anti-tautologia` rc=0 · `--preflight origin/main` rc=0 · os dois `.test.mjs` rc=0 · mordida
(`--root`): sem `data-contract="lote"` rc=1 · `casos-coverage-guard` sem violação nova.
