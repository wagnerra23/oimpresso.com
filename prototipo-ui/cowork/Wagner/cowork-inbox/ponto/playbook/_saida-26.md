---
thread: "26 · gap.md — Configurações"
dono: "[CL]"
estado: feito
base_lida: wagnerra23/oimpresso.com@main e4289e688 (2026-09-28)
prefixo_tocado: memory/requisitos/Ponto/configuracoes-{index,reps}-gap.md
veredito: "entregue — 2 gap.md; achado: o vivo lê 12 chaves que o config não tem; D-CFG-IA-CAMINHO medido = sem caminho"
---
# _saida-26 · gap.md — Configurações

## Achados

- **Defeito no vivo, maior que qualquer divergência de forma:** `Configuracoes/Index.tsx:18-43` declara 15 chaves de config; **12 não existem** em `Modules/Ponto/Config/config.php` e `hash_algoritmo` está em `marcacao`, bloco que o controller não envia. A tela deve mostrar "—" na maior parte dos parâmetros. Medido por leitura, **não em runtime**. O protótipo usa os nomes reais.
- **`D-CFG-IA-CAMINHO` medido:** as flags de IA vêm de `env()` (`config.php:120-126`) — global, sem pacote nem permissão. Pela condição de [W], o bloco pode exibir o estado, não ligar/desligar. **Pendente [W]:** nasce só leitura ou espera o caminho de pacote.
- **Reps:** o vivo **já tem** a coluna Ativo (`Reps.tsx:161`) — "PARAR SE" cumprido. Falta a ação de inativar (`D-REP-ATIVO` = ENTRA, nunca delete). Coluna CNPJ na lista fica para decidir.
- `D-CFG-POR-BUSINESS` segue **ADIADA** — fora destes gaps.

## Como foi medido

- Protótipo relido no build importado no #8067 (`ponto-telas.jsx`, 1.062 linhas). As linhas que a thread citava eram de um build anterior e **não** foram reaproveitadas.
- Lado vivo lido no `.tsx` e no Controller desta base, com linha. Nenhum `.tsx` destas telas tem `data-contract` (`grep -n data-contract` = 0 nas 2 telas), então toda âncora é linha-only até a thread 17.
- O caso vem do charter e do protótipo, nunca do código (§5 2026-06-05). O código só confirma o que o vivo faz.

## Fora do prefixo, declarado

- `memory/requisitos/Ponto/configuracoes-{index,reps}.map.json` — derivados por `scripts/design/gerar-map.mjs` (esqueleto no scratch, depois cópia) com as âncoras medidas preenchidas. `design-code-map-check.mjs --check --strict` rc=0.

## O que não fiz

- Nenhuma mudança em `resources/js/Pages/**` (`nao_toca`). Os pedidos ficam nos gap.md para as threads 17, 27 e 28.
