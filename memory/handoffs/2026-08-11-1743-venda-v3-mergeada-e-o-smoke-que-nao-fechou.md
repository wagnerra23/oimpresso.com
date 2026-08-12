---
date: "2026-08-11"
slug: venda-v3-mergeada-e-o-smoke-que-nao-fechou
hour: "17:43 BRT (20:43 UTC)"
topic: "Venda V3 — #5613 mergeada, e o smoke pós-deploy que NÃO fechou"
authors: [C, W]
prs: [5613]
us: [US-SELL-058]
tldr: "Complemento do handoff das 15:14. O #5613 mergeou (por [W], 20:36Z) com 119 contexts verdes. O smoke pós-deploy NÃO fechou: medido na prod, a regra `.venda-v3` não está no CSS servido — o deploy do SHA seguia `queued`. R1 em aberto, e é o próximo passo."
outcomes:
  - "#5613 MERGED por [W] — 119 contexts verdes/skip, zero falhas"
  - "3 vermelhos consertados, cada um lido do relatório da ferramenta (o nome do check enganava em 1 deles)"
  - "Smoke pós-deploy NEGATIVO: deploy queued, prod ainda com 30/32px — R1 NÃO fechado"
  - "Corrida estrutural medida: o gate de sincronia exige descendência estrita e o main anda dezenas de vezes/dia"
---

# Venda V3 — mergeada, e o smoke que não fechou

Complemento do handoff das [15:14](2026-08-11-1514-venda-v3-densidade-e-a-utilitaria-que-nao-mordia.md),
que fechou com o trabalho **não commitado**. [W] então pediu `merge`, e depois
`PR → merge → smoke pós-deploy`. Este registra o que aconteceu nesses três passos.

## PR #5613 — mergeado

Aberto, 119 contexts, **mergeado por [W]** em `2026-08-11T20:36:22Z`
(`8e8ba116a74`). Confirmei que o código está **no main de verdade** em vez de
confiar no título do commit: as regras `.venda-v3` no CSS, o wrapper na raiz da
tela e o `@import` no `inertia.css` — todos presentes em `origin/main`.

## Os 3 vermelhos, e o que cada um ensinou

Cada um foi diagnosticado **lendo o relatório da ferramenta**, não deduzindo do
arquivo suspeito (§5 2026-08-05). O primeiro é o instrutivo:

| check vermelho | causa REAL | conserto |
|---|---|---|
| `cor-crua ratchet (LEI)` | **o nome engana** — o `conformance-gate` (cor crua) passou `rc=0`; quem falhou no mesmo job foi o `foundation-guard`: *"arquivo CSS NOVO sem autorização"* | `venda-v3.css` em `.foundation-guard-files.json`, que o próprio guard define como *"diff revisado = aprovação humana"* |
| `CSS size ratchet` | crescimento consciente: +88 (arquivo novo) e +7 (o `@import`) | `--write`, como o próprio script instrui |
| `Preflight + contratos ativos` | branch atrás de `origin/main` | reconciliado |

**Se eu tivesse diagnosticado o primeiro pelo NOME do check**, teria ido caçar cor
crua num CSS que não tem nenhuma. Nome de check ≠ causa da falha — é a mesma
família do gitleaks de 2026-08-05, onde o arquivo acusado era o próprio
`.gitleaksignore`.

No `--write` do baseline apliquei **teste de identidade**: mudaram só
`generated_at`, `total_lines` 20831→20926, `file_count` 35→36, `inertia.css`
305→312 e a entrada nova. Nenhum outro CSS foi tocado — regravar baseline é
exatamente onde drift alheio entra de carona.

## ⛔ O smoke pós-deploy NÃO fechou — R1 em aberto

Medido em `https://oimpresso.com/sells/create-v3`, às 20:43 UTC:

```
DEPLOY_CHEGOU   : false   (a regra .venda-v3 NAO esta no CSS servido)
wrapper_no_dom  : false
campos .cw-input: 3x 30px    (esperado 34,19px)
campos da grade : 8x 32px    (esperado 34,19px)
h1              : 1x 28px    (esperado 28,59px)
folhasCegas     : 0          (medi tudo — sem cegueira de CSSOM)
```

Causa: o `Deploy to Hostinger` do SHA `8e8ba116` seguia **`queued`**, com outro
deploy (`c1ce2162`) atrás na fila. **Não é defeito do código** — é o build que
ainda não chegou ao servidor.

⚠️ **O que está provado e o que NÃO está.** Está provado que a regra produz a
caixa certa: injetei o CSS **já compilado** na prod real e medi `34,19px` dentro
do escopo contra `30px` fora dele (controle negativo). Isso prova a regra, e
**não** prova que o build chegou. O smoke é justamente o que separa as duas
coisas, e ele está **negativo por ora**. Declarar "pronto" aqui seria o R1
violado.

**Próximo passo, e é o único que falta:** quando o deploy concluir, recarregar a
tela sem cache e repetir a medição acima. O critério de aceite é literal —
`DEPLOY_CHEGOU: true` e os campos em `34,19px`.

## A corrida estrutural (não é defeito deste PR)

O `Preflight + contratos ativos` exige que a branch seja **descendente estrita**
de `origin/main`. Este repo merga dezenas de vezes por dia: medido, a branch
saiu de `0` para **7 commits atrás em poucos minutos**, e o CI leva ~50min. Isso
cria uma corrida real entre sincronizar e terminar o ciclo.

O que funcionou: reconciliar **enquanto os 75 checks ainda rodavam** (eles seriam
refeitos de qualquer forma) em vez de esperar o ciclo terminar para só então
descobrir que precisava sincronizar de novo. Foram **3 reconciliações** até o
ciclo fechar verde.

Registro sem propor máquina: transformar isso em gate seria um 2º oráculo de
sincronia, e o dono do tema já existe.

## Conflito de índice, resolvido preservando os dois

Uma das reconciliações deu conflito em `memory/08-handoff.md` — outra sessão
inseriu no topo ao mesmo tempo. Resolvido **preservando os DOIS lados** (o
já-mergeado em cima, o meu abaixo). Escolher um lado é como se apaga handoff
alheio, e o resultado foi verificado por contagem, não no olho.

## Erros meus neste trecho

- **`awk '{print $2}'` sobre `gh pr checks`** — nomes de check têm espaços, então
  a coluna 2 não é o status. A saída era ruído com cara de contagem. É a lápide
  §5 2026-08-08 **cometida de novo**, no mesmo eixo. Refeito com `--json`.
- **Dois watches em background reportaram estado obsoleto** (houve pushes depois
  deles). Não tomei o resultado como atual — remedi antes de concluir. O hábito
  que salvou é o mesmo do §5 2026-07-27: snapshot velho inverte diagnóstico.

## Estado

- `main` contém o trabalho (`8e8ba116a74`).
- **R1 NÃO fechado** — smoke pós-deploy negativo por deploy pendente.
- Resíduos que continuam abertos (do handoff das 15:14, nenhum resolvido aqui):
  os 14 `h-8 text-[12.5px]` inertes (higiene, PR separado) e o alinhamento global
  do `.cw-input` (decisão de produto, ~120 telas).
- **MCP indisponível** a sessão toda — as tools não aparecem no registro. R12
  passo 1 por fallback filesystem, declarado e não inventado.
