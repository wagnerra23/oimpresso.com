---
modulo: recepcao-pacote
titulo: "Recepção do pacote Design → Code: regenerar deixa de ser lembrança e vira catraca"
dono_pedido: "[CC]"
executor: "[CL]"
gerado: 2026-09-11
base_lido: nenhum arquivo do main lido NESTE turno
alvo_medido: NAO
---
# Recepção do pacote — 1 thread

> **Leia este arquivo + `01-recepcao-regenera.md`.** Não precisa da conversa.
>
> ⚠️ **Não é export de layout** (sem ALVO medido → sem os 10 blocos). É pedido de **infra de recepção**.
> ⚠️ **Nada aqui foi medido no `main` neste turno.** Os caminhos e nomes de guarda vêm de leituras de ciclos anteriores e do meu `CLAUDE.md`: **reconfira cada um antes de escrever**. Se um nome divergir, o nome do repo manda.

## O problema em uma frase
O `sync/` deste projeto está congelado em **2026-09-07** enquanto o build andou em 10 e 11/09 (7+ arquivos). O CI passa verde porque **guarda não é gerador**: `cowork-ssot-guard` e `cowork-mirror-freshness` impedem referência velha e órfão entrar — nenhum dos dois regenera o pacote. O passo "regenerar" existe só como item humano do ciclo de saída, e **passo que depende de alguém lembrar apodrece** (ADR 0256).

## Por que não pode ser resolvido do lado do Design
1. `gerar-payload-partes.mjs --root <dir>` varre **disco** e calcula sha por arquivo; aqui não há execução de `node`.
2. Escrever as partes pelo contexto do agente **é transcrição** — ADR 0374 **ativa** (a 0389 só abre exceção onde **não existe** rota de máquina; aqui existe).
3. Eu **não escrevo no git**: sem branch, sem commit, sem PR. Quem recebe o ZIP é o Code — logo o dono da recepção é o Code.

```json
{
  "modulo": "recepcao-pacote",
  "base_lido": "NAO_LIDO_NESTE_TURNO",
  "variaveis": {
    "GERADOR": "scripts/design-sync/gerar-payload-partes.mjs",
    "MANIFESTO": "sync/bundle.manifest.json",
    "GATE": ".github/workflows/design-memory-gate.yml",
    "FRESHNESS": "scripts/design-sync/cowork-mirror-freshness.mjs",
    "SSOT_GUARD": "scripts/governance/cowork-ssot-guard.mjs"
  },
  "decisoes": [
    {
      "id": "D-RECEPCAO-FALHA",
      "pergunta": "Quando a recepcao detecta que o pacote divergiu do projeto, o CI FALHA (bloqueia o PR) ou apenas AVISA com recibo? Falhar para o trabalho de quem colou o ZIP; avisar mantem o apodrecimento possivel. Minha recomendacao: falhar, porque avisar e exatamente o estado de hoje.",
      "respondida": false,
      "dono": "[W]",
      "define": "SEVERIDADE_DA_RECEPCAO"
    },
    {
      "id": "D-QUEM-REGENERA",
      "pergunta": "A recepcao REGENERA o pacote automaticamente (commit do bot no PR) ou so DETECTA e exige que o humano rode o gerador? Regenerar sozinha e conveniente e cria um segundo autor de pacote; detectar mantem um dono so.",
      "respondida": false,
      "dono": "[W]",
      "define": "AUTORIA_DO_PACOTE"
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Recepcao do ZIP: conferir projeto x manifesto e falhar quando divergir",
      "dono": "CL",
      "arquivo": "01-recepcao-regenera.md",
      "prefixo": ["scripts/design-sync", ".github/workflows"],
      "nao_toca": ["prototipo-ui/cowork/**", "resources/js/**", "memory/**"],
      "depende_threads": [],
      "depende_decisoes": ["D-RECEPCAO-FALHA", "D-QUEM-REGENERA"],
      "nota_provas": "prova e EXECUCAO com recibo antes/depois — nao 'o arquivo contem a string'",
      "provas": [
        { "tipo": "execucao", "cmd": "node scripts/design-sync/<verificador> --root <dir> --manifest sync/bundle.manifest.json", "recibo": "_saida-01.md", "exige": "imprime iguais / divergentes / ausentes-no-manifesto / orfaos, somando ao total de arquivos do projeto" },
        { "tipo": "execucao", "cmd": "caso de sanidade: tocar 1 byte em um arquivo do projeto e rodar de novo", "exige": "o contador de divergentes sobe em 1 — sonda que nao reage a mudanca conhecida nao e sonda" },
        { "tipo": "execucao", "cmd": "caso de sanidade inverso: rodar com projeto e manifesto em paridade", "exige": "exit 0 e zero divergentes — senao o detector marca tudo" }
      ]
    }
  ]
}
```

| # | thread | prefixo | veredito |
|---|---|---|---|
| **01** | recepção confere e falha ao divergir | `scripts/design-sync` · `.github/workflows` | **CABE** · depende de 2 decisões de [W] |

## O que este pacote NÃO resolve (bloco 7)
- **Não regenera o `sync/` de hoje.** Continua defasado (07/09) até alguém rodar o gerador com os arquivos em disco. Esta thread evita o **próximo** congelamento; não desfaz o atual.
- **Não substitui `cowork-mirror-freshness` nem `cowork-ssot-guard`** — são guardas, e seguem donos do que já cobrem (`--absent-local`, `--check-orfaos` em delta, `--check-refs`, R1/R2/R3). Não recriar o que existe: **estender ou chamar**.
- **C6 segue sem dono:** rota no `app.jsx` sem componente. Declarado, não coberto aqui.
- **Não vi o fluxo de recepção do ZIP** no repo neste turno. Se já existir algo com esse papel, **reescreva aquilo** em vez de criar arquivo novo (anti-scatter).
