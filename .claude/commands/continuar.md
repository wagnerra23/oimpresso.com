---
description: Retomar a sessão de onde parou — lê CURRENT.md, handoff e último session log, abre os arquivos do próximo passo, mostra resumo curto e pede confirmação antes de agir.
---

Você está retomando uma sessão anterior. Siga estes passos exatamente, **sem desviar**:

## 1. Lê o estado vivo (nessa ordem)

Lê os 3 arquivos abaixo na ordem. Não pula. Não re-explora o resto do repo nessa fase.

1. @CURRENT.md — sprint atual, em-andamento, próximo passo, bloqueios, última sessão
2. @memory/08-handoff.md — handoff longo (estado canônico mais recente)
3. O **último** arquivo em `memory/sessions/` — pega o nome via `ls -t memory/sessions/ | head -1` e abre

## 2. Abre o que importa pra retomar

Olhando o "próximo passo concreto" do CURRENT.md, abre **só** os arquivos diretamente envolvidos. Tipicamente:

- 1-3 arquivos de código que estavam sendo trabalhados (controller / service / page React / spec)
- 1 ADR ou SPEC se a tarefa referenciar uma

Não abre o repo inteiro. Não roda `find` / `glob` exploratório. Não relê ADRs já citados no handoff.

## 3. Resume em 3-5 linhas

Texto curto pro Wagner, em PT-BR:

- 1 linha: o que estava sendo feito (sprint + US/feature + branch)
- 1 linha: estado real (código pronto / aguardando validação / bloqueado)
- 1 linha: próximo passo concreto
- 1 linha (opcional): se houver bloqueio ou ambiguidade que precisa ser resolvida antes de continuar

## 4. PEDE confirmação antes de agir

Termina perguntando: **"Confirma que retomo daqui? Ou tem mudança de escopo desde o último handoff?"**

**NÃO faça nada destes:**
- Re-explorar o codebase com Glob / Grep / Agent além dos 3 arquivos da etapa 1 e dos 1-3 da etapa 2.
- Refazer trabalho que o handoff diz "completed" ou "done".
- Mudar escopo, sugerir refactor adjacente, ou empilhar trabalho extra.
- Commitar, fazer push, abrir PR, ou rodar `npm run build` / migrations sem o Wagner pedir.
- Atualizar handoff/CURRENT.md ainda — espera o trabalho da sessão acontecer primeiro.

Aguarda a resposta dele antes de tocar em qualquer arquivo.
