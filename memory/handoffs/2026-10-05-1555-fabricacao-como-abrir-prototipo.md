---
date: "2026-10-05"
time: "15:55 BRT"
slug: fabricacao-como-abrir-prototipo
tldr: "Como abrir o protótipo da Fabricação na próxima sessão: servir a cópia do git (prototipo-ui/cowork/Wagner) com servirEspelho do design-diff-lote.mjs, que resolve o _ds/ a partir de prototipo-ui/design-system. Servidor de arquivos simples (http.server) abre o shell mas a Fabricação falha por 404 no _ds. Testado em 2026-10-05."
prs: []
---

# Handoff — Fabricação: como abrir o protótipo

Complementa o [handoff de 15:16](2026-10-05-1516-fabricacao-grade-ordens-seguranca-receita.md), que
não registrava este caminho.

## O caminho que funciona (testado em 2026-10-05)

```bash
node --input-type=module -e "import('./scripts/design/design-diff-lote.mjs').then(m=>m.servirEspelho('prototipo-ui/cowork/Wagner',5594))"
```

Depois abrir `http://localhost:5594/oimpresso.com.html` no navegador do app e clicar em **Fabricação** no
menu: o 1º clique só expande o grupo, o 2º abre a tela (Receitas, com a tabela). O servidor fica rodando
enquanto o comando estiver ativo, então rode em segundo plano.

Por que este e não um servidor de arquivos comum: o espelho **não tem** a pasta `_ds/`. O Design System é
ligado, não copiado (ADR 0401), e o `servirEspelho` resolve `_ds/<slug>/` lendo `prototipo-ui/design-system/`.
Medido: `colors_and_type.css`, `cockpit_domains.css`, `_ds_bundle.js` e as fontes respondem 200.

## O que NÃO funciona

- **Servidor de arquivos simples** sobre `prototipo-ui/cowork` (ex.: a entrada `cowork-ponto-ancora` do
  `.claude/launch.json` desta máquina, porta 5611): o shell abre, mas a Fabricação mostra
  *"A Fabricação não abriu — os componentes do design system não carregaram"*, com 404 nos 3 arquivos de `_ds/`.
- **Projeto vivo do Cowork:** deu 404 para o login da [M] nesta sessão.

## Cuidados

- A fonte da Fabricação é a pasta **`Wagner/`** (charter, D-MFG-FONTE). Existe também `prototipo-ui/cowork/Felipe/`,
  que não é a fonte.
- A cópia foi sincronizada pela última vez em 01/10. Se o protótipo mudar no Cowork depois disso, a cópia
  fica atrás até ser baixada de novo (rota do bundle, `node scripts/design/protocolo.config.mjs`).
- O `.claude/launch.json` é só desta máquina, não está no git, e várias entradas dele apontam para pastas
  de worktree que não existem mais.

## Estado MCP no momento do fechamento

- Servidor MCP `oimpresso` indisponível nesta sessão (HTTP 401 no cabeçalho de autorização): o checklist
  MCP-first não rodou. O conteúdo acima vem do teste feito no navegador do app às 15:50.
