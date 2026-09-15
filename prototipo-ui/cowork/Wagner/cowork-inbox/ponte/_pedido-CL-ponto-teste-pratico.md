---
para: "[CL] Claude Code"
de: "[CC]"
data: 2026-08-23
tipo: pedido colável — teste prático do módulo Ponto
escopo: resources/js/Pages/Ponto/** + Modules/Ponto/Tests/**
base: main @ d1ccdff91be9 (charter e contrato lidos 2026-08-23)
---

# Pedido [CL] — teste prático do módulo Ponto

> **Por que esta é a tela certa para o primeiro teste real:** o `ponto-painel.contract.json` já desceu com o alvo corrigido, o charter existe, o `casos.md` acabou de ser escrito — e **não existe uma única lane executada**. É a menor distância entre "contrato ativo" e "veredito real" no repo inteiro.

## O que eu já produzi (entra no PR junto)

`cowork-inbox/ponto-dashboard/Index.casos.md` → destino `resources/js/Pages/Ponto/Dashboard/Index.casos.md`
6 UC no padrão do repo (frontmatter + rastreabilidade + Dado/Quando/Então), todos marcados `⬜ não verificado` — é exatamente isso que este pedido vem converter em veredito.

---

## Tarefa 1 · `PontoDashboardContratoTest` — 6 UC

Criar `Modules/Ponto/Tests/Feature/PontoDashboardContratoTest.php`, um teste por UC, **citando o id do UC no nome do teste** (é assim que a rastreabilidade fecha).

| UC | O que provar | Como |
|---|---|---|
| **UC-PAINEL-01** | Os 6 KPIs na ordem contratada, copy literal | assert na ordem: "Colaboradores ativos" → "Presentes agora" → "Atrasos hoje" → "Faltas hoje" → "HE do mês" → "Aprovações pendentes" |
| **UC-PAINEL-02** `[T0]` | Nenhum dado de outro business em KPI, presença, feed ou fila | seed em 2 business · assert ausência total do segundo · **biz=1 vs fictício, nunca biz=4** (ADR 0101) |
| **UC-PAINEL-03** | Fila vazia continua visível com "Nenhuma intercorrência aguardando decisão." | zero intercorrências pendentes · assert seção presente **e** frase presente |
| **UC-PAINEL-04** | Read-only: nenhuma escrita parte da tela | assert que nenhuma rota POST/PUT/PATCH/DELETE é alcançável a partir do `DashboardController@index` |
| **UC-PAINEL-05** | Polling recarrega só props de leitura e morre no unmount | assert `only: ['kpis','presenca_agora','atividade_recente','alertas','server_time']` |
| **UC-PAINEL-06** | Nota de divergência acima dos KPIs, e ausente quando não há | 2 cenários · assert ordem no DOM |

**Não invente fixture nova** se o módulo já tiver factory de marcação/intercorrência — estenda a que existe.

## Tarefa 2 · Custo do polling (a pendência do próprio charter)

O charter lista, em §Pendências antes de `status: live`: *"Validar custo do polling 30s com defer (evitar N queries repetidas)"*.

Medir **query count** de um ciclo de reload com `Inertia::defer` ativo e reportar o número. Não otimizar ainda — **medir e reportar**. Se passar de um teto que você julgue razoável, diga qual é o teto e por quê; a decisão de otimizar é [W].

## Tarefa 3 · Âncoras `data-contract` (portão 6.4)

```bash
grep -c data-contract resources/js/Pages/Ponto/Dashboard/Index.tsx
```

Esperado: **4** — `painel-nota-fechamento`, `painel-kpis`, `painel-fila-aprovacoes`, `painel-atividade`.
Se faltar alguma, adicionar. **Âncora é wiring seu; copy e ordem são lei [W] — não altere nenhuma das duas.**

## Tarefa 4 · Rodar a catraca

```bash
node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/ponto-painel.contract.json
node scripts/qa/prototipo-readiness.mjs
```

Reportar exit code real dos dois. Se o readiness ainda não marcar ✅ com o trio completo, **diga qual critério faltou** — isso é informação sobre a máquina, não sobre a tela.

---

## Regras que este PR não negocia

- **Não altere copy nem ordem** do `ponto-painel.contract.json`. Se a tela divergir, o veredito é vermelho — é o sinal honesto que a catraca existe pra dar.
- **Não marque nenhum UC ✅** no `casos.md` sem lane executada. Atualize a coluna Status com o resultado real e preencha `last_run` / `last_run_ci`.
- Prefixo deste PR: `resources/js/Pages/Ponto/**` + `Modules/Ponto/Tests/**`. **Nada fora disso** (Lei 1 do `03-REGRAS-DE-PARALELISMO.md`) — há outra thread possível no mesmo módulo.
- Se algo quebrar de forma instrutiva, a lição vai em `memory/LICOES_CC.md` — **proposta no PR**, não commit direto.

---

## ⚠️ PRÉ-FLIGHT — leia antes de recusar

Este pedido foi escrito **prevendo os três motivos pelos quais você recusaria**. Se bater em algum, a instrução está aqui — não pare, faça o que está escrito e reporte.

### Motivo 1 — "o `casos.md` não existe no repo"
**Correto: ainda não existe.** Ele está em `cowork-inbox/ponto-dashboard/Index.casos.md` e **o primeiro passo deste PR é movê-lo**:

```
cowork-inbox/ponto-dashboard/Index.casos.md
  → resources/js/Pages/Ponto/Dashboard/Index.casos.md
```

Sem esse move, o trio não fecha e o `prototipo-readiness.mjs` reprova com razão. **O move é a Tarefa 0.**

### Motivo 2 — "o charter está `status: draft`, o gate ADR 0107 bloqueia"
**Verdade, e não bloqueia ESTE PR.** Distinção que este pedido depende:

| O que o draft bloqueia | O que o draft NÃO bloqueia |
|---|---|
| ir a `status: live` | escrever teste |
| merge de mudança de comportamento | rodar a catraca |
| implantar no cliente (S7) | medir query count |
| — | adicionar âncora `data-contract` |

Este PR **não muda comportamento da tela**: adiciona teste, adiciona âncora, move um `.md`. Se a sua leitura do gate diz o contrário, **não recuse — reporte a leitura** ("o gate X bloqueia porque Y") e entregue o que couber. Um gate mal-interpretado que trava teste é pior que teste faltando.

**Não altere o `status:` do charter.** Virar `live` é ato de [W] (portão 6.1), depois do screenshot.

### Motivo 3 — "os UC citam teste que não existe"
O `casos.md` diz `Teste: —` e Status `⬜` de propósito: **este PR é que cria o teste**. Ao terminar, preencha a coluna Teste com `PontoDashboardContratoTest` e o Status com o resultado real. Isso não é editar lei — é preencher o campo que existe pra isso (`last_run`, `last_run_ci`).

### O que é recusa legítima
Recuse (ou pare e pergunte) **só** nestes casos:
- Fazer o teste passar exigiria **mudar copy ou ordem** do contrato → é lei [W], pare e reporte a divergência.
- O UC-PAINEL-02 revelar **vazamento real entre tenants** → pare tudo, isso é Tier 0, vira incidente e não PR.
- Precisar escrever fora do prefixo `resources/js/Pages/Ponto/**` + `Modules/Ponto/Tests/**` → reporte o que falta e por quê.

### Ordem de execução (não pule)
```
T0  mover o casos.md                       → sem isso nada fecha
T3  grep -c data-contract (medir ANTES)    → saber de onde parte
T1  escrever os 6 testes
T2  medir query count do polling
T3b adicionar âncora que faltar
T4  rodar as 2 catracas, reportar exit code
T5  preencher Teste/Status/last_run no casos.md
```

### Resposta esperada mesmo em falha
Falhar e reportar **é entrega**. O que não é entrega: recusar sem número. Volte com os 5 itens da seção anterior, mesmo que três deles sejam ❌.

## O que eu quero de volta (é isto que fecha o ciclo)

1. Exit code real das duas catracas
2. Status por UC: ✅ / ❌ / 🧪 — com o motivo de cada ❌
3. O número do query count do polling
4. A contagem real do `grep -c data-contract`
5. Divergência protótipo↔vivo que apareceu no caminho (vira pendência [W], não conserto seu)

---

## Depois deste PR, o portão que sobra

| Portão | Estado após este pedido |
|---|---|
| 6.1 charter aprovado | ⛔ [W] — Non-Goals + Anti-hooks ainda `status: draft` |
| 6.2 casos.md | ✅ fecha com este PR |
| 6.3 contrato no schema | ✅ já está |
| 6.4 âncoras | ✅ fecha com Tarefa 3 |
| 6.5 copy bate | ✅/❌ Tarefa 4 |
| 6.6 readiness | ✅/❌ Tarefa 4 |
| 6.7 lane Pest | ✅ fecha com Tarefa 1 |
| 6.8 a11y [CA] | ⬜ F3.5 |
| 6.9 screenshot 1280+1440 [W2] | ⬜ |

**Ponto/Dashboard sai deste PR com 5 dos 9 portões fechados** — e vira a primeira tela do produto com veredito real, não com contrato acreditado.


---

## Nota de handoff (V.01 — validador da ponte)

O `casos.md` citado acima **não existe no repo**: ele vive no Cowork. Para colar, use
**`cowork-inbox/ponte/COLAR-NO-CODE-ponto.md`**, que é este mesmo pedido **com o conteúdo
integral do arquivo anexado** (ANEXO A, entre linhas de corte). Colar este arquivo sozinho
faz o [CL] procurar um caminho que ele não vê — e parar com razão.
