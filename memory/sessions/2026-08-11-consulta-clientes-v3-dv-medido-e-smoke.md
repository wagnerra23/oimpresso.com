---
date: "2026-08-11"
hour: "18:11 UTC"
topic: "Porte da consulta de clientes pro preview V3 — medir DV antes de copiar, e o instrumento que mentiu na verificação"
authors: [C, W]
prs: [5579]
us: [US-SELL-058]
outcomes:
  - "Consulta de clientes portada e mergeada — último AindaNao da tela removido"
  - "CNPJ real detectado por medição de DV com controle positivo e negativo, antes do commit"
  - "Mutação dos 4 guards: o 1º veredito saiu FALSO porque o sed não casou — refeito provando que a mutação entrou"
  - "Smoke real em prod com sessão [W]: 3 estados de cliente, invariância de valor confirmada"
---

# Consulta de clientes no preview V3 — o que a sessão fez

## Ordem do trabalho

1. **Fonte antes de código.** O `AindaNao` restante era um só (medido por `grep`, não presumido —
   a tarefa avisava que há **dois** botões "Consultar cadastro… F2" na tela e que um `find`
   ingênuo pega o da Entrega). A fonte é `sells-create.jsx:515`, lida por inteiro antes de escrever.
2. **Padrão das ondas 2-6 lido, não inventado.** `EntregaFrete.tsx` já tinha a consulta de
   transportadoras com o mesmo desenho (Dialog 880px, tabela com `<button>` por linha, sticky
   header, empty state nomeando o termo). Portei espelhando, com 6 colunas em vez de 5.
3. **Domínio separado** (`cliente-consulta-dominio.ts`) porque `react-refresh/only-export-components`
   reprova componente + constante no mesmo módulo — a catraca mordeu isso na onda 2.
4. **Cena no controller, não no `.tsx`** — regra do bundle: *"nenhuma lista de domínio nasce em
   arquivo de UI"*. Enumeração (`GRUPOS_DE_PRECO`) fica no domínio, seguindo o precedente de
   `entrega-dominio.ts` (`UFS`/`MODALIDADES` são const; transportadoras, que são **registros**,
   vêm do controller).

## O momento que mudou o PR

Antes de copiar os clientes do protótipo, rodei um validador de DV com **dois controles**:

- **positivo** — CNPJ público conhecido → `VÁLIDO` (prova que o validador reconhece DV bom, senão
  "tudo inválido" não significaria nada);
- **negativo** — os 4 CNPJs já no controller → `inválidos`, **4/4** batendo com o que o arquivo
  declara em comentário.

Com o instrumento assim calibrado, os alvos: o CNPJ do atacadista inválido ✓, o CPF da pessoa
física inválido ✓, e o do cliente Governo **VÁLIDO** — documento real. Barrado. O nome do órgão também
saiu (prefeitura é entidade real; parear nome real com CNPJ fake fabricaria um registro), e
"Rota Livre" saiu por ser o cliente-piloto de verdade.

Depois disso o `pii-scan` ainda mordeu **duas vezes**, e as duas foram minhas: eu tinha deixado a
base real num **docblock** como exemplo de busca sem máscara, e um CNPJ-placeholder de dígitos
repetidos (usado num caso de "não acha nada"), sem marcador. A 1ª é a instrutiva — *o número saiu
do dado e foi parar na documentação sobre o dado*.

E o scan mordeu **este session log** na primeira redação, pelo mesmo motivo: eu tinha citado os
documentos sintéticos literalmente ao contar a história. Saíram — a lição sobrevive sem os dígitos,
que é exatamente a regra que eu acabara de aplicar ao código. Três mordidas, todas minhas, todas
na mesma classe.

## O erro de método, e ele foi na própria verificação

Mutei os 4 guards do domínio pra provar que os testes mordem. O 3º (busca sem acento) reportou
**"NÃO MORDEU — teste é carimbo"**. Ia registrar isso. Antes, conferi se a mutação tinha entrado:
**não tinha** — o `sed` não casou o padrão (escape de `\p{M}` no shell). O arquivo estava intacto,
e eu tinha medido o teste rodando contra o código **original**.

Refeito com script que **falha explicitamente** se o alvo não for encontrado (`ALVO NAO ENCONTRADO
-> instrumento quebrado`), os 4 guards mordem. Fica registrado porque é a classe LC-08 acontecendo
**dentro da defesa contra LC-08**: o instrumento devolveu um veredito plausível e eu quase publiquei.

Mais dois no mesmo dia, sem dano porque cruzei fontes: contador de falhas com `grep` errado (dizia
"0 casos" pra mutação que derrubou o teste) e `npx vitest && grep || echo FALHOU` reportando FALHOU
com `rc=0` (o `grep` do encadeamento é que falhou, por ANSI). **O exit code era o sinal; o texto não.**

## Verificação

`build:inertia` (chunk emitido) · `vitest` **70 → 93** — o total subiu, não só ficou verde (LC-13) ·
`tsc` **372 = baseline da árvore**, 0 nos arquivos novos · `lint:baseline` · `layout:check` ·
`a11y:check` · `components:check` · `reuse:check` · `casos-guard` nos **dois** modos (G-1/G-2 e
`--check-baseline-shrink` — o job roda os dois, e verde num não é verde no job) · `charter-us-lint` ·
`anchor-content-check` · `module-surface --check` · `pii-scan`. Tudo re-rodado **depois** do merge de
`origin/main`, porque eu estava 11 commits atrás e o `--check-baseline-shrink` compara contra o main
atual.

CI: **118 pass · 2 skipping · 0 falhas**. Merge por [W].

## Smoke real em prod

Com a sessão do [W] no Chrome, após confirmar que um deploy **bem-sucedido continha o commit**
(`git merge-base --is-ancestor`, não "o deploy do meu commit" — esse foi cancelado por concorrência):

- modal abre com 6 colunas, 4 cadastros, pills de ICMS nos três estados, rodapé com contagem;
- **zero erros de console**; sem tela branca — o risco que nenhum dos 118 checks cobria;
- busca por `29417508` (sem máscara) e `itajai` (sem acento): ambas acham;
- seleção: cliente muda, detalhes abrem, desfazer aparece, e **total/subtotal/imposto não se movem**;
- cadastro mínimo: botão desabilitado sem nome, código `0392` (largura preservada), nome **trimado**,
  e a tabela de preço **volta ao padrão do balcão** — o `tabela: null` visível end-to-end.

Um comportamento **não declarado** apareceu aqui: fechar o modal não limpa o termo de busca (só a
seleção limpa). Idêntico ao protótipo, então não é regressão — mas não estava em nenhum caso.

## Contexto de infra que atrapalhou

MCP inacessível a sessão inteira (`brief-fetch` timeout; CT 100 em 502), então **nenhum `php -l`** e
**nenhum registro em `mcp_tasks`**. Um deploy falhou por SSH timeout pro Hostinger — flakiness já
catalogada, resolvida com o warm-up documentado por curl.
