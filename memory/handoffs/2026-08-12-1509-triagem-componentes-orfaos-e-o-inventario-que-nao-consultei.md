---
date: "2026-08-12"
time: "15:09 BRT"
slug: triagem-componentes-orfaos-e-o-inventario-que-nao-consultei
tldr: "Triagem dos 8 componentes React órfãos: 2 PRs de remoção (#5691 mergeado, #5693 verde), 3 vereditos revistos por medição e 1 erro meu — declarei zero consumidores sem consultar o SUPERFICIE.md, e os gates pegaram."
prs: [5691, 5693]
decided_by: [W]
next_steps:
  - "Mergear #5693 (verde, mergeable) — remove ThemeToggle + tira da allowlist do guard"
  - "Decidir resizable: consertar pra API v4 da lib, ou remover do REGISTRY e deletar"
  - "Decidir adoção document-input/phone-input/avatar (hand-roll vivo, mexe com PII)"
  - "[W] escolher o recorte de PII que quer atacar — pergunta feita e dispensada"
---

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 10 tasks, **todas em REVIEW** (US-TR-309/310/311, US-PROD-025/027, US-INFRA-023/048, US-TR-305/306, US-KB-002)
- `whats-active` → ⚠️ **CEGO** (ingest sem heartbeat: fresh=0 · stale=0 · dead=95). Não assumi escopo livre
- Handoffs irmãos hoje: **8** (07:49 · 09:25 · 09:34 · 10:26 · 10:43 · 12:31 · 15:08 · 16:17) — dia de alta colisão no índice

## O que aconteceu

Chip de triagem: 8 componentes em `resources/js/Components/` apontados como órfãos por revisão adversarial. **Re-medi antes de tocar** — controle positivo (`ui/button` = 236 arquivos), 2 barrels conferidos, zero `import()` por template string. Os 8 estão órfãos de fato; nenhum falso-positivo.

Mas o veredito por componente **não** é "apagar", e três leituras minhas mudaram durante a execução:

1. **`document-input`/`phone-input` — a hipótese do chip não se confirmou.** O chip supunha "a regra está sendo ignorada". Datação diz outra coisa: os componentes nasceram no [#2540](https://github.com/wagnerra23/oimpresso.com/pull/2540) em **2026-06-11**, *depois* do drawer Cliente. Foram **extraídos** daquele hand-roll (`IdentificacaoTab.tsx:23` · `ContatoTab.tsx:20`), e a regra diz "não hand-wirar em form **novo**" — o drawer é grandfathered. É **extração inacabada**, não regra violada. O passo de adoção nunca foi dado, nem no toque de 06-13.

2. **`resizable` não é "canon disponível" — está quebrado.** O typecheck mostrou `TS2339`: o componente usa `PanelGroup`/`PanelResizeHandle`, mas `react-resizable-panels@4.12.2` exporta `Group, Panel, Separator`. API v2/v3 contra lib v4. Passa despercebido porque typecheck não é gate aqui. Quem seguir o REGISTRY e importar, quebra o build.

3. **`NfceStatusBadge` — o charter não mente; eu li errado.** Afirmei que ele declarava arquitetura que a tela não segue. O charter declara um **alvo com emenda [W] de 2026-07-28**, com a implementação marcada como pendente em texto explícito. O hook que a emenda pede (`useEmissoesPorTransaction`) já existe e roda em `Sells/_components/FiscalSection.tsx`. O arquivo atual é a encarnação **antiga**. Não é ponteiro podre a corrigir — é trabalho declarado a implementar. Ficou fora da faxina.

**PII:** [W] levantou o tema. Verifiquei o que era barato: zero CPF/CNPJ/telefone nas linhas que adicionei, gate `PII scan` SUCCESS, e — corrigindo alarme meu — as chamadas `SEFAZ`/`BrasilAPI` do `IdentificacaoTab` **não** vazam pra terceiro: vão a rotas do próprio backend, e só com CNPJ (dado público). Observação menor, não achado: CNPJ viaja no path da URL (4 sites). Perguntei o recorte que [W] quer atacar; a pergunta foi dispensada.

## Artefatos gerados

| Artefato | Onde |
|---|---|
| PR #5691 (mergeado por [W]) | remove `shared/SimpleMarkdown` + `shared/PageHeaderActions` + 2 docblocks + regenera SUPERFICIE — 107 SUCCESS |
| PR #5693 (verde, aguarda merge) | remove `ThemeToggle` + tira da `ALLOWED_FILES` + regenera SUPERFICIE — 106 SUCCESS |
| `memory/LICOES_CODE.md` | LC-08 87 → **88**, recibo desta sessão |

## Persistência

- **git:** 2 PRs + este handoff + índice + ledger
- **MCP:** webhook GitHub→MCP propaga em ~2min após push
- **BRIEFING:** não aplicável — nenhuma capacidade de módulo mudou (remoção de código morto)

## Próximos passos pra retomar

```bash
gh pr view 5693 --json mergeStateStatus,mergeable
```

## Lições catalogadas

**LC-08 (n+2) — declarei "0 consumidores" sem abrir o dono do inventário.** Varri o repo inteiro em código executável e parei aí. `memory/requisitos/_Geral/SUPERFICIE.md` — o inventário de superfície — linkava os três arquivos. O `deadlink-gate` e o `SUPERFICIE.md == árvore` reprovaram **os dois PRs**, nomeando arquivo e linha. A §5 2026-07-28 já manda: claim de ausência = repo inteiro **+ dono do inventário**; cumpri (a), pulei (b).

A nuance que me enganou, e é a parte reaproveitável: **o controle positivo valida o DETECTOR, nunca o ESCOPO.** Meu detector estava certo (`ui/button` = 236 provou isso) e o universo é que estava incompleto — então o verde do controle deu falsa confiança justamente onde eu precisava desconfiar.

Conserto: **regenerar**, não editar (`authority: generated`). Custo: 2 PRs reprovados + 1 conflito no merge do irmão. **O gate já existia e mordeu** — 1ª ocorrência desta variante, nada novo a codificar (two-strikes, ADR 0344).

Segundo recibo, menor: o conflito do `SUPERFICIE.md` entre os 2 PRs foi resolvido **regenerando**, não escolhendo hunk — resolver derivado no olho produz arquivo que nenhum gerador reproduz (passa o merge, falha o `--check` depois).

## Pointers detalhados

- Triagem completa dos 8 com recibo por componente: corpo dos PRs [#5691](https://github.com/wagnerra23/oimpresso.com/pull/5691) e [#5693](https://github.com/wagnerra23/oimpresso.com/pull/5693)
- Regra dos BR inputs: [`.claude/rules/components.md`](../../.claude/rules/components.md) §BR inputs · [`prototipo-ui/REGISTRY_DS_COMPONENTES.md`](../../prototipo-ui/REGISTRY_DS_COMPONENTES.md) L34-35, L50, L54
- Emenda do NfceStatus: [`NfceStatus.charter.md`](../../resources/js/Pages/NfeBrasil/Transactions/NfceStatus.charter.md) §Goals + §Automation Hooks
- Lápide da classe: [`memory/proibicoes.md`](../proibicoes.md) §5 2026-07-28
