---
date: "2026-09-09"
topic: "Espelho Cowork — o eixo NOVO remedido com a lista inteira: 87 live-only, 0 protótipos de tela"
authors: ["C"]
outcomes:
  - "live-only remedido com os 876 paths do vivo: 87 fora do espelho, 0 protótipos de tela"
  - "13 .md de playbook/pedido desceram para prototipo-ui/design-docs/ (87 → 74) — em paralelo com o #7141, e as duas transcrições saíram byte-idênticas nos 13"
  - "§7 item 1 do INVENTARIO-ANCORAS emendado: a claim de ausência de protótipo passa a valer sobre o vivo"
related_adrs: ["0389-emenda-0374-escrita-do-espelho-quando-o-get-file-volta-inline", "0374-emenda-0315-espelho-cowork-e-rota-prevista"]
---

# Espelho Cowork × vivo — o que o `--live-only` respondeu quando recebeu a lista inteira

## O pedido

Fechar a lacuna entre o Cowork vivo e `prototipo-ui/cowork/`, porque ela *"invalida parcialmente
toda claim de ausência de protótipo"* — limite declarado no §7 item 1 do
[INVENTARIO-ANCORAS-2026-09-09](../requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md),
e alarmado pelo hook (`live-only vencido — última medição em 2026-09-01, 157 de 929 paths`).

## A primeira medição estava na base errada — e isso muda o diagnóstico

O worktree abriu **444 commits atrás** de `origin/main`. O `--sla` rodado ali dizia
`última rodada PARCIAL · mediu 1/258` e `live-only vencido há 8d`. No `main` fresco a resposta é
outra: `--compare` **completo e dentro do SLA** (273/273, 0 stale, de 2026-09-08) e live-only
medido **há 1 dia**. O alarme do hook era verdadeiro sobre o checkout stale e falso sobre o canon
— LC-20 na prática, e o motivo de a primeira leitura desta sessão não valer nada.

Base de trabalho: `claude/cowork-espelho-fechar-lacuna`, criada de `origin/main` fresco (0/0).

## A rota — e por que a do bundle não servia aqui

O painel (`protocolo.config.mjs`, fase `-1`) põe o **bundle v2** como rota principal, e o
`github.md` do Cowork registra que ele foi **regenerado em 2026-09-07 (281 arquivos)**. Medido
localmente: o bundle promovido já é esse (`state/active-bundle.json`, `generatedAt 2026-09-08`,
281 arquivos). Ou seja, o eixo *conteúdo do build* já estava fechado antes desta sessão.

E o bundle **não cobre** o que faltava: contado no manifesto, ele tem **0 `.md`** e
**0 `cowork-inbox/`** — é só build (`.jsx`/`.css`/fontes/html). Então, para os docs, a rota
canônica é a que o próprio `--live-only` imprime (`get_file` → dir de JSONs → `--export-from`),
não a do bundle. Isto não é a "rota inferior" da lápide §5 2026-08-21: é o eixo que o bundle
não cobre por construção.

## A medição

`DesignSync.list_files` → **876 paths** salvos em JSON (controle positivo em 5 paths conhecidos,
controle negativo em 1 inexistente, 0 duplicatas) → `--live-only`:

```
LIVE-ONLY — existe no Cowork vivo e NÃO está no espelho (87 de 876 paths)
  ── protótipo de tela (0) — candidatos reais a versionar:
  ── outros (87) — shell, uploads, bundle de DS, docs:
```

**Zero protótipos de tela.** Essa é a resposta ao pedido: a família que invalidava a claim de
ausência está **vazia**, e agora sobre a lista inteira — não sobre a amostra que o §7 tinha.

Anatomia dos 87, contada:

| n | família | desce? |
|---:|---|---|
| 44 | `sync/**` — 43 partes do bundle + manifesto | **não** — é o transporte, não a carga |
| 13 | `.md` de playbook/pedido | **sim** — feito nesta sessão |
| 12 | `cowork-inbox/*/repo/**` — cópia do nosso próprio código | decisão [W] (descer duplicaria o repo) |
| 6 | fixtures `.php` escritas pelo design | decisão [W] |
| 3 | `_ds/**` | dono é o `--preview-ds`, não esta rota |
| 9 | avulsos (`.gitignore`, `.thumbnail`, `AssinaturaAtualizar.tsx`, 4 ferramentas, 1 `.napkin`) | decisão [W] |

## O que desceu — e a colisão que virou o achado mais forte da sessão

Os 13 `.md`, para `prototipo-ui/design-docs/cowork-inbox/` (roteamento por extensão do
`--export-from` — R1 do `cowork-ssot-guard` proíbe `.md` em `cowork/`):

- `ancora/playbook/_contrato-evidencia.md` — a escala E0–E4 de evidência de âncora
- `ds-atomos/playbook/{00-INDICE,01-card-anatomia,02-kpicard-filter,03-toolbar-criar}.md`
- `patrimonio/playbook/{14..20}.md` + `_PATCH-INDICE-2026-09-09.md`

Conferido antes de escrever: o `_PATCH-INDICE` afirma que o `main` tem **24** arquivos no
playbook do Patrimônio contra 7 na pasta local do Cowork. Medido no repo: **24**. A afirmação
procede, e é por isso que desceu o *patch* e as *threads novas*, nunca a pasta local.

### A colisão — e o controle que ela produziu de graça

No rebase, os 13 sumiram do meu commit: **já estavam no `main`**, trazidos pelo
[#7141](https://github.com/wagnerra23/oimpresso.com/pull/7141), de uma sessão paralela do mesmo
dia, num dos 4 commits que subiram enquanto eu trabalhava. Erro meu de processo: o gatilho desta
sessão foi um **alarme de máquina compartilhada** (o hook do live-only), que é justamente o caso
de maior probabilidade de colisão — e a regra do §5 2026-08-13 manda rodar `whats-active` antes.
Não rodei.

Mas a colisão pagou um controle que eu não teria como montar sozinho. Comparei arquivo a arquivo
o que **eu** produzi contra o que o **#7141** landou:

```
IDENTICOS (byte a byte): 13
DIVERGENTES: 0
```

Duas sessões, dois `get_file` inline, duas transcrições independentes, **sha256 igual nos 13**.
Isso não torna a rota "fiel por construção" — mas é *corroboração por réplica independente* de
uma fidelidade que a ADR 0389 só permite **declarar**, e é evidência bem mais forte que o
indício de determinismo abaixo. Vale registrar como precedente: quando duas sessões trazem o
mesmo arquivo inline, comparar os dois é barato e mede o que o `--origem agente` não consegue.

## Ressalva de fidelidade — declarada, não verificada

Os 13 `.md` voltaram **inline** do `get_file` (`truncated: false`), e o harness só persiste em
arquivo saídas grandes. Logo os bytes passaram pelo meu contexto: é o caso previsto pela
**ADR 0389**, e o export foi feito com `--origem agente`, que faz o script imprimir
*"FIDELIDADE DECLARADA, não por construção"* em vez de carimbar verificado. As 4 condições:
origem única resolvida por `list_files` antes · `truncated: false` em todos · consumidores
rodados depois (abaixo) · declarado aqui e no PR.

Indício de determinismo, não prova: os 2 primeiros arquivos foram escritos em duas rodadas
separadas e a segunda saiu `inalterado` (mesmo sha) nas duas.

## Verificação pós-escrita (os consumidores)

| consumidor | veredito |
|---|---|
| `cowork-ssot-guard` | ✓ fonte única OK (nenhum `.md` pousou em `cowork/`) |
| `--live-only` (remedido) | **87 → 74**, e os 13 saíram da lista |
| `--sla-live-only` | ✓ exit 0 · medido há 0d · registrado no ledger |
| `--sla` (geral) | ⬜ **INCONCLUSIVO** — segue assim por causa dos 74 |

## O que NÃO foi feito, e por quê

- **O `--sla` geral continua INCONCLUSIVO**, e isso é desenho do instrumento, não pendência de
  design: ele não distingue *transporte* (`sync/**`, 44 dos 74) de *fonte*, e a própria saída diz
  *"lista, não veredito: o que merece descer é decisão [W]"*. Zerá-lo exigiria descer o bundle
  dentro do espelho do bundle, o que não faz sentido.
- **Não editei `prototipo-ui/cowork/` à mão** (§5 2026-08-13/14): tudo que pousou passou pelo
  `--export-from`.
- **Não medi frescor contra `sync/bundle.manifest.json`** (§5 2026-08-25): o veredito de `--compare`
  citado é o do ledger, produzido pelo `--compare-bundle` contra o bundle promovido.
- **Não afirmo nada sobre `ancora.mjs`/`design-coverage` terem mudado de resposta.** Nenhum dos 13
  arquivos é âncora de design — são docs de processo. O ganho é sobre a *claim de ausência*, que
  agora tem a lista inteira por trás, não sobre a cobertura de âncora.
- **Não trouxe os 12 `cowork-inbox/*/repo/`** (cópia do nosso código) nem as 6 fixtures `.php`:
  descer isso duplicaria o repo dentro do espelho, e o próprio `--live-only` diz que a decisão é [W].

## Decisão aberta para [W]

Os **74** restantes se resolvem em três respostas, não uma:

1. `sync/**` (44) e `_ds/**` (3) — **nunca descem** por esta rota; se incomodam no `--sla`, o
   conserto é no instrumento (excluir transporte do denominador), não no espelho.
2. `cowork-inbox/*/repo/` (12) + fixtures `.php` (6) — descer duplica código nosso. **Recomendo
   não descer.**
3. Avulsos (9) — `AssinaturaAtualizar.tsx` é o único com cara de artefato de produto; os outros são
   ferramenta e sujeira de projeto.
