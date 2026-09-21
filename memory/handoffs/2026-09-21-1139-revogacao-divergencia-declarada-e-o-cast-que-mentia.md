---
date: "2026-09-21"
time: "11:39 BRT"
slug: revogacao-divergencia-declarada-e-o-cast-que-mentia
tldr: "[W] revogou a 'divergência DECLARADA (autorizada)' no eixo FORMA. 11 PRs mergeados: 8 de canon (regra-mãe, skill, 5 módulos, resíduo), 1 armando o par MSYS no block-sonda-que-mente, 1 lápide §5 e 1 fix de produção — o cast `array` do DeviceModel quebrava o checklist do Repair nos dois sentidos desde 2025-05. Um adversário derrubou a tese central do fechamento antes de virar canon."
prs: [7548, 7549, 7550, 7552, 7553, 7554, 7559, 7568, 7573, 7581, 7588]
decided_by: [W]
related_adrs: [0344-two-strikes-cobre-processo]
next_steps:
  - "Decidir se o P7 CANDIDATO (campo derivado de PR sem campo de estado) vale armar — barrado por CUSTO de rede em PreToolUse, não por FP"
  - "A lane verticais-pest segue com 16 falhas pré-existentes (JobSheet multi-tenant, FSM, ProducaoOficina, anexos, MWART) — nenhuma tocada nesta sessão"
---

# Revogação da "divergência DECLARADA" — e o cast que mentia nos dois sentidos

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **sem tasks ativas** pra `@wr23`
- 11 PRs da sessão: **todos MERGED**
- Nenhum handoff irmão em `2026-09-2*` no momento da escrita

## O que aconteceu

[W], textual: *"eu revogo tudo, de todos. a regra mudou agora é o Protótipo quem manda, e a paridade deve ser o objetivo"*.

**Fase 1 — o canon (7 PRs).** `rg --hidden` devolveu **24 ocorrências** do rótulo fora de `scripts/`+`hooks/`, com nomes que **não batiam** com a lista do enunciado. Triadas uma a uma: **10 eram FORMA**, 14 não (dado, permissão, comportamento, metodologia). As duas peças que mais importavam não estavam na lista — `RESPEITAR-PROTOTIPO.md` (a regra-mãe, que institui `divergence_from_blueprint` como autorização) e `comparar-design-prod/SKILL.md`, que ainda ensinava `DIVERGE(decisão)`/`PROD-À-FRENTE` e é o que carrega no momento do trabalho.

**Achado do Arquivos:** os dois `PROD-A-FRENTE` estavam **invertidos** — protótipo 5 ações × prod **zero**; 4 abas × 1. O rótulo marcava a produção como adiantada e com isso **blindava a dívida de aparecer como dívida**.

**Fase 2 — as máquinas (#7559).** Dos **3 caminhos** que davam `CLEARED` na M1, só **um** foi revogado; renomear o estado inteiro teria quebrado os dois legítimos. O caminho do desvio virou estado próprio `DIVIDA` (`◐`, fora das "limpas"); `related_prototype` e `SYNC_LOG` seguem `✓`. O `reconcile-triplet` ganhou `DIVIDA_REGISTRADA`.

**Fase 3 — o adversário.** Rodado **antes** de escrever a lápide, derrubou a tese central: o *"15 de 17 escritas depois da UI-0029"* **não é reproduzível** — 12/18/29 conforme o corpus, **7 eventos** (3 antes / 4 depois), e no recorte mais estreito dá **empate 6/6**. Pior: a evidência-vitrine nasceu em **2026-08-25**, *antes* da ratificação. Ele também impediu a generalização *"rótulo positivo não é conferido"* (N=1, com contra-exemplo no AssetManagement) e a criação de classe nova (o eixo já está em §5 2026-08-11).

**Fase 4 — o par MSYS (#7581).** [W]: *"arma o par MSYS"*. **FP medido antes**: 991 hits, **655 (66%) já protegidos** → isentados; os **336 nus** abertos um a um = 138 revspecs, todos arquivos reais. Zero FP. Cheguei nisso em **4 rodadas** — as 3 primeiras tinham FP visível só abrindo a amostra.

**Fase 5 — o CT 100 (#7588).** A lane `Verticais` vermelha desde 09/09 não era dívida vaga: o Model declara `'repair_checklist' => 'array'` desde 2025-05, mas a coluna é **string separada por `|`**. Quebrava leitura (accessor → `NULL`) **e escrita** (gravava com aspas JSON), em **8 consumidores**.

**Fase 6 — o resíduo.** Declarei backfill pendente; [W] mandou fazer. Medido em produção com controle positivo: `repair_device_models` = **0**. **Não havia resíduo** — o bug era real e nunca atingiu dado de cliente.

## Artefatos gerados

| PR | o quê |
|---|---|
| #7548 | regra-mãe + skill (+ a 2ª ponta solta declarada) |
| #7549 · #7550 · #7552 · #7553 · #7554 | Arquivos · AssetManagement · Dashboard · Produto · Forja |
| #7568 | resíduo Superadmin — o que minha varredura **listou e eu não triei** |
| #7559 | M1 + `reconcile-triplet` falando o vocabulário novo |
| #7573 | lápide §5 + 2 `rec` sob LC-08 |
| #7581 | **P6 armado** no `block-sonda-que-mente` (+ P6→P7 renumerado) |
| #7588 | fix do cast — 1 arquivo, com o porquê medido no docblock |

## Persistência

- **git canônico:** os 11 PRs no `main`
- **MCP:** webhook propaga `memory/**` em ~2min
- **CT 100:** container **restaurado ao estado do git** (o fix vive no PR, não no checkout compartilhado)

## Próximos passos pra retomar

```bash
node .claude/hooks/licoes-code-two-strikes.mjs --reconcile   # ledger em dia?
```

## Lições catalogadas

- **§5 2026-09-18 (nova):** rótulo de veredito que **inverte a direção** da contagem ao lado — e a tese que eu ia registrar junto caiu na própria medição.
- **LC-08 ×2 (`rec` no #7573):** o veredito invertido (1 evento, não 2) e o `git show "origin/main:.claude/…"` que devolveu `0` porque o **comando nem rodou** — **strike 2**, citando §5 2026-08-23 nominalmente para não apagar o strike.
- **Recorrentes minhas nesta sessão:** contei `../` errado **2×** (`path.relative` resolve); meu `grep` saiu cego **3×** (`Trailing backslash`, `tail -12` que eu mesmo pedi, escape que colapsou); li `rc` de `tail` em vez do `node`.
- **O que mais pesou:** aceitei quase um resultado errado ao comparar suítes com **seeds diferentes** (1392 / 1304 / 1217 assertions no mesmo código). O veredito veio de **diff de conjunto de nomes**, não de totais.

## Pointers detalhados

- [`memory/licoes-rejeitadas.md`](../licoes-rejeitadas.md) §2026-09-18 — a lápide inteira, com a tabela das 4 operacionalizações
- [`memory/LICOES_CODE.md`](../LICOES_CODE.md) LC-08 — campo `Gate:` com o **P6 ARMADO** e o **P7 CANDIDATO**
- [`memory/requisitos/_DesignSystem/RESPEITAR-PROTOTIPO.md`](../requisitos/_DesignSystem/RESPEITAR-PROTOTIPO.md) — a norma, com as 2 pontas fechadas
- [UI-0029 §41](../requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) — *"não se abre exceção per-tela"*, o fundamento mais direto
