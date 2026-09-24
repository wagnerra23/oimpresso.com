---
sessao: "_saida-05"
thread: "05 · Abas do PageHeaderTabs com 36px"
dono: "[C]"
data: 2026-09-24
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main a41ad0086
---
# _saida-05

## Entregue
`resources/js/Components/shared/PageHeaderTabs.tsx` (D-PH-0923, item c — 36px do DS):
- nova constante `TAB_BOX = 'inline-flex items-center h-9 leading-none'`, aplicada a **toda** aba. `inline-flex` é obrigatório: `<a>` é inline e ignora `height`, então `h-9` sozinho seria inerte;
- `default`: `px-3 py-1.5 text-sm` (~30px) → `px-[14px] text-[13px]` + `TAB_BOX` (36px). `compact` (Jana): `px-[14px] py-1.5 text-[13px]` → `px-[14px] text-[13px]` + `TAB_BOX`. A âncora da Jana (`JmTabs`) usa a mesma classe `.cli-moduletopnav-tab`, então os 36px valem para as duas;
- o `inline-flex` condicional (só com ícone/contador, revisão de 2026-07-10) deixou de ser condicional: a mudança de altura agora é deliberada. Com ícone ou contador entra só o `gap-1.5`;
- docblock da prop `density` e o comentário do bloco de classes atualizados; o texto antigo ficou como registro **datado**.

Testes (prefixo da thread; a forma antiga foi **reescrita**, não desabilitada — UI-0029):
- `tests/pageHeaderTabsDensity.spec.tsx`: o caso que travava `text-sm` + `px-3` agora trava `h-9` + `inline-flex` + `text-[13px]` + `px-[14px]` e proíbe `text-sm`/`px-3`/`py-1.5`; o caso `compact` passa a exigir `h-9` + `inline-flex`.
- `tests/pageHeaderTabsFidelity.spec.tsx`: caso novo `altura: 36px`, em aba ativa e inativa.

## Provas
1. Prova do índice (`nao_contem "base: 'px-3 py-1.5 text-sm'"`): o texto não existe mais no componente.
2. `npx vitest run tests/pageHeaderTabsDensity.spec.tsx tests/pageHeaderTabsFidelity.spec.tsx tests/janaAreaHeaderParidade.spec.tsx` → **26 passed** (3 arquivos).
3. Mordida: `TAB_BOX` esvaziado e `default` revertido para `px-3 py-1.5 text-sm` → **3 failed / 17 passed** (os dois casos de densidade + o de altura). Restaurado de cópia byte-exata; sha256 igual antes e depois (`e6ef79409807a348`).
4. Nenhum byte de controle nos 3 arquivos escritos (varredura por bytes < 0x20 fora de TAB/LF/CR = 0).

## Raio
O `PageHeader` canon (`Components/PageHeader/PageHeader.tsx` e `Components/shared/PageHeader.tsx`) renderiza o `PageHeaderTabs`, e 10 arquivos o importam direto — **toda tela com abas no header muda de ~30px para 36px, e o texto de 14px para 13px**. `git grep` em `scripts/ tests/ config/ .github/` não achou gate fixando as classes antigas; `config/pageheader-shared-baseline.json` só lista arquivos, não altura.

**Baseline visual mexe em massa — esperado.** Decodificar com `scripts/tests/snap-diff.mjs`: faixa de ~6px na altura do header e texto das abas 1px menor = esta thread; Δ grande fora do header = investigar.

## Ausente / residual declarado
- **Não medido em runtime:** o computed style (36px no browser) não foi medido nesta sessão — jsdom não carrega CSS, e não subi o app. Fica com o visual-regression do CI e o smoke pós-deploy.
- **Peso da aba inativa:** `default` segue 400, `compact` 500. O `TabBar` do DS e o `.cli-moduletopnav-tab` do protótipo do Clientes medem **500** na inativa. D-PH-0923 não decidiu sobre o peso, então ficou como estava. Com isso, `default` e `compact` só diferem nesse eixo: igualar o peso e aposentar a prop `density` é decisão [W].
- **Item f** do pedido (pílula inativa em hue 240) segue fora, como o próprio pedido registra.
- **PR aberto no mesmo raio:** #7875 (`test(visreg): baselines regeneradas`) regenera snapshots que esta thread também muda. Quem mergear por último precisa regravar.
