---
id: requisitos-comunicacaovisual-index-visual-comparison
title: "Comparação design × produção — ComunicacaoVisual/Index"
module: ComunicacaoVisual
tela: ComunicacaoVisual/Index
owner: W
status: rascunho
last_validated: "2026-11-11"
---

# Comparação design × produção — `ComunicacaoVisual/Index`

> **Âncora computada** (`node scripts/design/ancora.mjs ComunicacaoVisual/Index`):
> `related_prototype` declarado `n/a — ferramenta bespoke`, e o `bundle_source` resolve
> `prototipo-ui/cowork/Wagner/comunicacao-visual-page.jsx`. O próprio arquivo se declara porte
> reverso do vivo (auditoria 2026-11-05, A1/A2).
>
> Primeiro registro desta tela. Lote 4 da auditoria do módulo (achados A7 e A9).

## Como foi medido (2026-11-11)

| Item | Valor |
|---|---|
| Produção | https://oimpresso.com/comunicacao-visual, sessão de **biz=1** (WR2 Sistemas), código do deploy `93cc0a40cf` (run 43087401347, sucesso), que já contém os lotes 0 e 1 |
| Protótipo | espelho no git em `07d9047f13` (último commit do `.jsx`: `5e653fdbeb`), servido por http local sob `/prototipo-ui/cowork/Wagner/`, `localStorage["oimpresso.route"]="cv"`, nenhuma folha CSS em 404 |
| Viewport | iframe de **1280×900** nos dois lados (a janela tinha zoom de 50%, então a medição foi dentro do iframe, `innerWidth` 1280 conferido) |
| Tema | **claro** nos dois lados. A conta de biz=1 usa `data-theme=light` vindo do servidor. Forçar `dark` só pela classe gera um estado que o app nunca mostra (`.cockpit` claro com cards escuros), então o escuro **não foi medido** em produção |
| Sonda | `node scripts/design/design-diff.mjs --probe`, sha256 `358fd0f0…ebe20`, injetada idêntica nos dois lados; papéis = `ROLES_PADRAO` do `design-diff-lote.mjs` + `--shell-roles` |
| Snapshots (fora do repo) | `prod-index.json` sha256 `9d25342df0705dc8…` · `design-index.json` sha256 `a0922793d9e1a209…` |
| Comparação | `design-diff.mjs --compare prod design --contrato governance/design/contracts/comunicacao-visual.contract.json --check` → **rc=0**, `DIVERGE(bug): 0` |

## Veredito por dimensão

| Dim | Resultado | Leitura |
|---|---|---|
| D0 identidade | **OK**, 19×19 copies do contrato nos dois lados | é a mesma tela |
| D2 layout | IGUAL | |
| D4 tipografia | IGUAL | título 22px/600 nos dois lados (a medição de 2026-09-18, contra staging, dava 24px na produção: hoje bate) |
| D6 cor | IGUAL, **sem valor de prova** | a própria sonda acusou `SAÚDE tokens.prod: NÃO MEDI` — `--accent --pos --neg --warn --text-dim --sunken --border` não resolvem na raiz da produção, então a cor cai em fallback |
| D8 alinhamento | IGUAL (a tela não tem KPI) | |
| D9 regiões | **divergem** | ver a tabela abaixo |
| D1 rede | ok | 1ª navegação 49 KB; `recentes` chega numa XHR parcial de 529 bytes (Inertia defer). Só uma chamada extra: `GET /comunicacao-visual/api/roteiro-padrao` |
| Shell | 14 itens "a classificar" | o mapa de papéis do shell não casou elemento visível no protótipo (sidebar recolhida, 55px), então a lista não é diagnóstico; fica sem classificar |

### Regiões (`data-contract`)

| Região | Produção | Protótipo | Veredito |
|---|---|---|---|
| `cabecalho`, `calculadora`, `total`, `em-breve` | sim | sim | presentes nos dois |
| `pcp` (miniatura "Na fábrica agora") | **não** | sim | **DÍVIDA A FECHAR** — a produção não tem o bloco que o protótipo desenha (já apontado na auditoria, A1) |
| `ajustes`, `arquivos`, `orcamentos-salvos`, `roteiro-padrao` | sim | não | **PROTÓTIPO ATRASADO** — capacidade da produção (ajustes do total, anexar arquivo, orçamentos salvos, roteiro padrão) que o protótipo não desenha |

**Canário:** a sonda pegou a região `pcp` que existe só no protótipo, diferença já conhecida pela auditoria. A sonda enxerga ausência de região.

## Smoke funcional (DOM, biz=1)

- A tela abre com o pacote de biz=1 (o `ComunicacaoVisualLiberado` do lote 1 deixa passar).
- O seletor de material tem **6 opções** (5 materiais do catálogo de biz=1 + a vazia).
- Botões visíveis: "Adicionar peça", "Conferir no servidor", "Salvar orçamento".
- "Orçamentos salvos": *"Nenhum orçamento salvo ainda"*. **biz=1 não tem orçamento gravado.**
- Rolagem horizontal da página a 1280px: não.

## Achados (registrados, não consertados aqui)

1. **Cartões "Em breve" desatualizados.** O texto diz que *Ordens de serviço* e *Materiais* estão por vir, mas a tela já aprova orçamento em OS e o seletor já lista 5 materiais do catálogo. Microcopy que contradiz a própria tela.
2. **`pcp` só no protótipo** (D9). Decisão de produto: ou a produção ganha a miniatura, ou o protótipo a perde.
3. **Protótipo atrasado** em 4 regiões (`ajustes`, `arquivos`, `orcamentos-salvos`, `roteiro-padrao`). O lado a mover é o protótipo.
4. **Tokens de cor da produção** não resolvem na raiz (`SAÚDE tokens.prod`). Isso tira o valor de prova da D6 aqui e vale para toda tela medida com esta sonda; não é defeito desta tela.

## Não medido

- Tema escuro (motivo acima).
- Fluxo de escrita (conferir, salvar, link, aprovar): não executado em produção de propósito, porque grava orçamento de biz=1. O E2E do lote 4 (`tests/Browser/ComunicacaoVisual/`) cobre o render e o axe no tenant 98.
