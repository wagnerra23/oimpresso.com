---
sessao: "08"
titulo: Scorecard · Jana (Acoes, Alertas, Plataforma)
executor: "[CL]"
base: 317e1b4ec33
---
# _saida 08

## Checklist
1. ✅ Pré-Flight: charter existe nas 3 telas (`Acoes/Alertas/Plataforma.charter.md`, todos `status: draft`) + `casos.md` ao lado — `PARAR SE` de charter **não** acionado
2. ✅ Nota 16-dim por tela, com evidência `arquivo:linha` em cada dimensão (bloco `evidencias:` do YAML)
3. ✅ YAML com o slug exato da espec · `baseline_anterior` = a própria nota · gaps com `best_of_class` + `fix`
4. ✅ Os 3 YAMLs parseiam (`js-yaml`): 16 dimensões e 5 gaps cada
5. ⚪ E2E / axe / smoke (passos 2-4 do agente) — **fora**, como manda a espec

## Notas

| tela | slug | arquétipo | persona | nota | nível | pior dimensão |
|---|---|---|---|---|---|---|
| `Jana/Acoes` | `jana-acoes.yaml` | list | misto | **74** | Advanced | cognitive_load 64 |
| `Jana/Alertas` | `jana-alertas.yaml` | list | misto | **76** | Advanced | error_recovery 68 |
| `Jana/Plataforma` | `jana-plataforma.yaml` | list | wagner | **75** | Advanced | performance_perceived 66 |

Nota = média simples das 16 dimensões, arredondada (pesos 1× — persona `misto`/`wagner`, mesma
convenção dos vizinhos `jana-memoria`/`jana-index`). Média crua: 73,75 · 75,50 · 74,75.

## O que a nota É e o que ela NÃO é

**É leitura de código** — `.tsx` + charter + controller (`AcaoHitlController@index`,
`AlertasController@index`, `SuperadminController@metas`). **Não houve browser de prod**: nenhuma
nota vem de tela renderizada, e o `PARAR SE` "a tela não abrir em prod" **não foi verificado**
(não abri a rota). As dimensões visuais (aesthetic, mobile_fit) são inferidas do código e
valem menos que as estruturais.

## Gaps que se repetem nas 3 (sinal de área, não de tela)

- **Sem `Inertia::defer`** nos 3 controllers — props eager, sem skeleton.
- **Tamanhos arbitrários** (`text-[10.5px]`, `text-[11px]`, `rounded-[10px]`…) — 19 ocorrências nos 3 `.tsx`.
- **Copy com jargão de código** (nomes de rota, tabela, `AlertaService::avaliar`) — pesa em Acoes
  e Alertas, cuja persona é dono/gestor. É copy pinada em `governance/design/contracts/jana-*.contract.json`,
  logo mudar é decisão [W].

Nenhum gap virou task — ficam no YAML até [W] aprovar o batch.

## Saída das máquinas

`node scripts/qa/prototipo-readiness.mjs`: as 3 telas entram em **✅ PRONTAS** (62 no total);
nenhuma Jana resta em 🟡 1-CICLO.

`node scripts/qa/screen-grades-ratchet.mjs`:
```
Catraca screen-grade · 195 telas · ✅ 192 ok/subiu · ✨ 3 novas · 🔻 0 regrediram · 🗑 0 deleção(ões) legítima(s)
✓ CATRACA: nenhuma tela regrediu.
```

## Escopo
Só o prefixo: os 3 YAMLs + este recibo. Zero `.tsx`, zero charter, zero git.
