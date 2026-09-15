---
modulo: ancora
tipo: patch-de-indice
gerado: 2026-09-10
base_lido: ed4398d77437
dono: "[CL]"
---
# PATCH do 00-INDICE.md — ancora (2026-09-10)

> **Por que patch e não índice reescrito:** a pasta local do Cowork é **cache** (5 arquivos) e o `main` pode estar à frente — reescrever o índice sobrescreveria saídas e erratas que eu não li. Aplique os objetos abaixo **dentro** do bloco ```json do `00-INDICE.md`, sem tocar no resto.
>
> **Leitura que gerou este patch** (árvore `ed4398d77437`, 10/09/2026): busca `^(related_prototype|bundle_source|visual_source):` em `resources/js/Pages/` — varredura ampla **bounded** (343 de 400 de 808 candidatos), completada por prefixo em Ponto (21) · OficinaAuto (9) · Patrimônio (5) · Sells (11) · Produto (10) · Repair (17). ~150 declarações. **Nenhum `node` rodado daqui.**

## 1. Acrescentar em `variaveis`
```json
{ "CONTRATOS": "prototipo-ui/contrato" }
```

## 2. Acrescentar em `decisoes`
```json
[
 {
  "id": "D-PRECEDENCIA",
  "pergunta": "Quando related_prototype e bundle_source apontam para arquivos DIFERENTES na mesma tela, qual vence? Medido em Fiscal/{Config,Dfe,Eventos,Sped}: related=fiscal-subpages.jsx x bundle=fiscal-page.jsx. Hoje a maquina escolhe por ordem de campo, sem dizer que houve conflito.",
  "respondida": false,
  "define": "PRECEDENCIA_DE_CAMPO"
 },
 {
  "id": "D-VISUALSOURCE",
  "pergunta": "visual_source e campo legado vivo em 3 telas (OficinaAuto/ServiceOrders/{Board,Show}, Sells/Index). Migra para related_prototype e morre, ou vira sinonimo declarado?",
  "respondida": false,
  "define": "MORTE_DO_VISUAL_SOURCE"
 },
 {
  "id": "D-PRODUTO-INDEX",
  "pergunta": "Produto/Index.bundle_source aponta produtos-page.jsx, que se declara porte do UNIFICADO. O charter mantem de proposito: remover faz ancora.mjs:266-278 reancorar em silencio na mesma coisa. Corrigir exige a heuristica startsWith(dir) morrer junto.",
  "respondida": false,
  "define": "FIM_DA_HEURISTICA_STARTSWITH"
 }
]
```

## 3. Acrescentar em `threads`
```json
[
 {
  "id": "04",
  "titulo": "fechar o denominador: --list --json cobre 100% dos charters e imprime o numero de cobertura",
  "dono": "CL",
  "arquivo": "04-denominador-cobertura.md",
  "prefixo": ["prototipo-ui/ancora.mjs"],
  "nao_toca": ["resources/js/Pages/**", "scripts/governance/**"],
  "depende_threads": ["03"],
  "depende_decisoes": [],
  "nota_provas": "mesmo arquivo das 01-03 — remedir o sha antes de escrever",
  "provas": [
   { "tipo": "execucao", "cmd": "node prototipo-ui/ancora.mjs --list --json > /tmp/ancora.json", "recibo": "_saida-04.md", "exige": "total de charters, com_ancora, na_declarado, sem_campo, por_via (related|bundle|visual|component), somando ao total" },
   { "tipo": "execucao", "cmd": "node prototipo-ui/ancora.mjs --selftest", "exige": "exit 0" }
  ]
 },
 {
  "id": "05",
  "titulo": "campo duplo divergente para de ser silencio: conflito vira aviso e entra no --list",
  "dono": "CL",
  "arquivo": "05-campo-duplo-divergente.md",
  "prefixo": ["prototipo-ui/ancora.mjs"],
  "nao_toca": ["resources/js/Pages/**", ".claude/hooks/**"],
  "depende_threads": ["04"],
  "depende_decisoes": ["D-PRECEDENCIA"],
  "provas": [
   { "tipo": "execucao", "cmd": "node prototipo-ui/ancora.mjs Fiscal/Config", "exige": "saida cita OS DOIS caminhos e marca conflito; caso de sanidade: Cliente/Index (campo unico) NAO marca conflito" },
   { "tipo": "execucao", "cmd": "node prototipo-ui/ancora.mjs --list --json", "exige": "campo conflito:true nas 4 telas do Fiscal e nas que a varredura achar" }
  ]
 },
 {
  "id": "06",
  "titulo": "contrato fora do glob: financeiro-unificado.intent.json nao e cobrado como os outros 32",
  "dono": "CL",
  "arquivo": "06-contrato-fora-do-glob.md",
  "prefixo": ["scripts/qa", "prototipo-ui/contrato"],
  "nao_toca": ["prototipo-ui/ancora.mjs", "resources/js/Pages/**"],
  "depende_threads": [],
  "depende_decisoes": [],
  "provas": [
   { "tipo": "execucao", "cmd": "<comando do gate de contrato de tela>", "exige": "o gate lista quantos contratos carregou; antes e depois no recibo, e o .intent.json aparece OU e declarado fora do gate com motivo" }
  ]
 }
]
```

## 4. Acrescentar na tabela de threads (prosa)
| # | thread | prefixo | veredito |
|---|---|---|---|
| **04** | denominador fechado + número de cobertura no `--list` | `prototipo-ui/ancora.mjs` | **CABE** · depende 03 |
| **05** | campo duplo divergente vira aviso | `prototipo-ui/ancora.mjs` | **CABE** · depende 04 + `D-PRECEDENCIA` |
| **06** | contrato fora do glob `*.contract.json` | `scripts/qa` | **CABE** |
| — | `Produto/Index` reancorado | `resources/js/Pages` | **BLOQUEADA** por `D-PRODUTO-INDEX` ([W]) |
