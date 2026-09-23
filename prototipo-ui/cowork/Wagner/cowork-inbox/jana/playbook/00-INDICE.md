# Playbook — Jana · **Painel** (`/ia`)

> Pasta é a unidade de descida. **1 thread = 1 seção = 1 PR ≤300 linhas = 1 prefixo**, sessão limpa, read-order lido no `main`.
> Página única ⇒ **onda = seção** (§Granularidade do PROTOCOLO).
> **Absorve** `COLAR-NO-CODE-jana-tabs-cor-e-icone.md` (anti-scatter §12): aquele arquivo foi apagado, as duas ondas dele fecharam no `main`. Nenhum doc novo foi espalhado.
> Ponte, não canon. **Não escrevo no git** — isto desce por `cowork-inbox/` ou Issue → PR.

> 🔧 **Corrigido 2026-09-21 (2ª emissão).** A 1ª emissão declarava `"ondas"` com campo `"estado"`. O consumidor in-repo (`scripts/qa/placar-indice.mjs`, `validarIndice()`) exige **`threads`** com **`provas`** — e como ele avalia os 13 índices num `map`, o índice incompatível lançava `NaoMedi` e **derrubava a medição dos outros 12** (`rc=2`, `placar-de-lista` vermelho no `main` desde 11:09Z). Não era sinônimo: `estado` é **DERIVADO** das provas + `_saida-NN.md` (Lei 2) e o consumidor **ignora** o campo escrito. Devolutiva: `memory/reference/prototipo-ui/CODE_NOTES.indice-jana-usa-ondas-nao-threads-2026-09-21.md`.

```json
{
  "modulo": "jana",
  "view": "painel",
  "rota": "/ia",
  "controller": "Modules\\Jana\\Http\\Controllers\\IndexController@index",
  "page": "resources/js/Pages/Jana/Index.tsx",
  "ancora_layout": "prototipo-ui/cowork/Wagner/jana-merge.jsx §JanaPage",
  "contrato": "governance/design/contracts/jana-painel.contract.json",
  "granularidade": "secao",
  "base_lido": "19ff53c88491",
  "lido_em": "2026-09-21",
  "variaveis": {
    "CHARTER": "resources/js/Pages/Jana/Index.charter.md",
    "CASOS": "resources/js/Pages/Jana/Index.casos.md",
    "COCKPIT": "resources/js/Pages/Jana/_components/JanaCockpit.tsx",
    "PROTOTIPO": "prototipo-ui/cowork/Wagner/jana-merge.jsx"
  },
  "threads": [
    {
      "id": "01",
      "titulo": "Painel: o tier Pro governa brief, análises e ações",
      "dono": "CL",
      "ficha": "01-painel.gating-pro.md",
      "prefixo": [
        "feat/jana-painel-gating-pro"
      ],
      "nao_toca": [
        "resources/js/Pages/Jana/Chat.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${COCKPIT}",
          "padrao": "O brief diário é do plano Pro"
        },
        {
          "tipo": "contem",
          "path": "${COCKPIT}",
          "padrao": "As 5 análises são do plano Pro"
        },
        {
          "tipo": "contem",
          "path": "${CASOS}",
          "padrao": "UC-JPAIN-28"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Painel sem histórico mostra um estado de página, não 6 caixas vazias",
      "dono": "CL",
      "ficha": "02-painel.estado-vazio.md",
      "prefixo": [
        "feat/jana-painel-estado-vazio"
      ],
      "nao_toca": [
        "resources/js/Pages/Jana/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${COCKPIT}",
          "padrao": "A Jana ainda não tem histórico pra analisar"
        },
        {
          "tipo": "contem",
          "path": "${CASOS}",
          "padrao": "UC-JPAIN-29"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Jana/Pro: tirar os style={{}} inline (cores → tokens/classes)",
      "dono": "CL",
      "ficha": "03-pro.sem-inline.md",
      "prefixo": [
        "resources/js/Pages/Jana/Pro.tsx",
        "resources/css/cockpit.css"
      ],
      "nao_toca": [
        "resources/js/Pages/Jana/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Jana/Pro.tsx",
          "padrao": "style={{ color: PROOF_MUTE }}"
        },
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Jana/Pro.tsx",
          "padrao": "style={{ background: BUB_JANA }}"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Permissão da Jana provada por teste (6 Pest do emenda de casos)",
      "dono": "CL",
      "ficha": "04-permissao.testes.md",
      "prefixo": [
        "Modules/Jana/Tests/Feature/Http/"
      ],
      "nao_toca": [
        "resources/js/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Jana/Tests/Feature/Http/IaPermissaoGrupoTest.php"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Jana/Tests/Feature/Http/MetasPermissaoTest.php"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Jana/Tests/Feature/Http/CustosVazamentoTest.php"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Jana/Tests/Feature/Http/ConversaAcessoTest.php"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Jana/Tests/Feature/Http/MemoriaPermissaoTest.php"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Jana/Tests/Feature/Http/ProPreviewPermissaoTest.php"
        }
      ]
    }
  ]
}
```

## Threads 03–04 (acrescentadas 2026-09-23 pela triagem dos soltos)

- **03** absorve `JANA-PRO-SEM-INLINE-2026-08-26.md` (movido para `../`). Medido @ebe1fc8be7e4: `Pro.tsx` ainda tem 13 `style={{}}` (linhas 239–295).
- **04** absorve `JANA-CASOS-EMENDA-PERMISSAO-2026-08-27.md` (movido para `../`). Medido: busca por `IaPermissaoGrupo|MetasPermissao|CustosVazamento|ConversaAcesso` em `Modules/Jana/Tests` → **0**. Se os testes existirem com outro nome/pasta, corrigir as provas, não criar duplicata.

## As provas, e por que são estas

Todas são **estruturais** (`contem`), logo medíveis sem o avaliador de recibo — nenhuma sai `NÃO MEDIDA`. Todas foram **verificadas por busca no `main` neste turno** (`19ff53c88491`), não escolhidas de memória:

| thread | prova | onde bate hoje |
|---|---|---|
| 01 | `O brief diário é do plano Pro` | `JanaCockpit.tsx:730` |
| 01 | `As 5 análises são do plano Pro` | `JanaCockpit.tsx:1089` |
| 01 | `UC-JPAIN-28` | `Index.casos.md:1437` |
| 02 | `A Jana ainda não tem histórico pra analisar` | `JanaCockpit.tsx:535` |
| 02 | `UC-JPAIN-29` | `Index.casos.md:1549` |

**Descartada de propósito:** `pro={pro}` no `Index.tsx`. O padrão já existia antes das ondas (`:289`, no `JanaPlanoBadge`) — prova que fica verde sem a entrega é prova que mente.

## O que o placar vai derivar (e não é "0 de 2" por defeito meu)

As duas threads estão **implementadas e mergeadas** (#7587 · #7591). Mesmo assim o placar não as dará como `feito` enquanto não existir `_saida-01.md` / `_saida-02.md` **nesta pasta** — é a prova implícita da Lei 2, e o recibo é de quem executa. Eu **não** escrevo `_saida` por [CL]: seria escrever o dado que o medidor consome, exatamente a via que a devolutiva recusou (§4.1). Com as provas acima e sem `_saida`, a leitura honesta é `em curso`/`pendente`, não `0 de 0`.

## Leitura do `main` feita neste turno (2026-09-21)

| # | arquivo | pra quê |
|---|---|---|
| 1 | `memory/reference/prototipo-ui/CODE_NOTES.indice-jana-usa-ondas-nao-threads-2026-09-21.md` | o pedido deste ciclo |
| 2 | `scripts/qa/placar-indice.mjs` | o contrato real (`validarIndice`, `TIPOS_ESTRUTURAIS`, `avaliarIndice`) |
| 3 | `cowork-inbox/financeiro/playbook/00-INDICE.md` | forma de um índice aceito |
| 4 | busca em `resources/js/Pages/Jana/**` | confirmar as 5 provas por `arquivo:linha` |

**Não lido ⇒ não afirmado:** `tests/janaPainelEstadoVazio.spec.tsx` (citado pelo charter, não verificado — por isso **não** virou prova), `PainelContratoTest.php`, `jana-painel.contract.json`.

## PLACAR deste ciclo

```
threads emitidas no formato do consumidor ... 2 (01 · 02)
provas estruturais .......................... 5 — todas verificadas por arquivo:linha
provas de recibo (NÃO MEDIDA) ............... 0
implementação ............................... fechada em código (#7587 · #7591, 21/09)
falta pro placar dar "feito" ................ _saida-01.md · _saida-02.md ([CL])
achado de [CL] pendente no próximo ciclo .... EmptyState não tem variant="first"
                                              (ficha 02 §5 pediu; caiu no default)
"0 bug" ..................................... NÃO. Só o T7 afirma.
```

## Regra de saída (ADR 0387)

Este ciclo fecha **sem** pacote regenerado — o gerador exige os arquivos em disco e **não roda do lado do agente** (ADR 0374), então **não afirmo que regenerei**:

```
node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
```
