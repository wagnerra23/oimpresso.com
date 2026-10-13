---
sessao: "01"
titulo: "App das lojas v1 — Ponto dentro do Mobile (desenho)"
autor: "[CL]"
data: 2026-10-13
thread: 01-mobile-com-ponto.md
base: wagnerra23/oimpresso.com@main 94c005aec5
veredito: "entregue em duas partes — telas 36/37/38 já estavam no projeto Design; (a)-(d) desenhados em mobile/ref/proposta-01-ponto-v1/index.html para o Design incorporar"
---

# _saida-01 · Ponto dentro do app das lojas

## Onde a entrega está

| Parte | Projeto | Arquivo |
|---|---|---|
| 36 Bater ponto · 37 Meu espelho · 38 Justificar | Claude Design "Mobile app structure review" (`b29cacda-da7c-4c02-b91e-4064b35ba951`) | `Oimpresso Mobile.dc.html` (espelho no repo: `mobile/ref/design-v4/project/Oimpresso Mobile.dc.html`, telas `s36`–`s38`, l.3043–3330) |
| (a) barra da v1 · (b) perfil Colaborador · (c) tela 40 Pessoas · (d) safe-area | este repo, para o Design incorporar | `mobile/ref/proposta-01-ponto-v1/index.html` |

A proposta não fica em `cowork-inbox/`: um `.html` ali é acusado pelo `cowork-mirror-freshness --check-orfaos` (nenhum `<link>` do shell o carrega). Ela fica ao lado das outras cópias de referência do app, em `mobile/ref/`, e lê os tokens de `prototipo-ui/design-system/colors_and_type.css`.

## Leitura de escopo (para o [W] corrigir se discordar)

**Vale a D16**, não a v1 enxuta que esta thread descreve:

- A thread e a D13 pediam 7 áreas, com a Fila do gestor fora do app.
- `docs/lojas-app/DECISOES.md` **D16** ([W] 2026-10-02, textual: *"todas as 40 telas"*) revoga D13 e D15: as 40 telas, incluindo 35 Dashboard e 39 Fila do gestor, entram no app antes do envio.
- O `00-INDICE.md` (D-35, 2026-10-07) diz "Dashboard fica para depois da v1", mas como **recomendação [CC]** em resposta a *"o que recomenda?"*, sem ratificação textual do [W]. Recomendação não revoga decisão do dono.

Resultado no desenho: Dashboard (35), Validar ponto (39) e a nova 40 ficam em **Mais**. Leitura confirmada pelo "Gerente da fila de merges (13/10 · 3)" nesta sessão.

## O que o projeto Design já tinha (medido no espelho, não refeito)

- 36/37/38 no padrão do Mobile, com tokens do DS, sem moldura de aparelho e sem selfie ou biometria.
- **Portaria 671 respeitada:** nenhuma tela edita ou apaga marcação. A 38 diz *"A marcação original não muda"*; a 39 só grava anulação (*"A marcação original continua no registro"*).
- Recibo com NSR + hash; GPS com "Atualizar local"; bloqueio quando o usuário não tem cadastro de ponto.

## O que faltava e foi desenhado

1. **(a) Ponto na barra de baixo.** Perfil padrão da loja: Início · Tarefas · Ponto · Pedidos · Mais (5 itens). `MODS` ganha a chave `ponto` → `s36`, e a tela 30 "Meu menu" passa a oferecê-lo. Produção, Pessoas, Dashboard e Validar ponto seguem em Mais.
2. **(b) Perfil Colaborador (D6).** Abre direto na 36. A barra tem Ponto · Tarefas · Conta. Tarefas traz as justificativas pendentes (D11); Conta é a tela 00. Não há Início nem Mais para quem só tem o ponto.
3. **(c) 40 · Quem está trabalhando.** Tela nova: a 17 é clientes e fornecedores, e a 26 Equipe mostra carga de OS. Lista estado e hora vindos da última marcação do dia: trabalhando, no almoço, saiu, sem marcação, fora da área. É só leitura e não mostra endereço nem mapa. "Fora da área" leva à 39.
4. **(d) Margens do aparelho.** `viewport-fit=cover`, e `env(safe-area-inset-*)` no topo, na barra e nas laterais, no lugar do `padding: 40px 0 24px` fixo. A proposta já usa as duas regras.

## Suposições a confirmar ([W])

- **Quem vê a 40.** Desenhei pela permissão do ERP de ver o ponto da equipe; sem ela, a entrada some de Mais. Não achei decisão registrada sobre isso.
- **Barra do Colaborador.** Ponto · Tarefas · Conta é escolha do desenho (a thread delega a navegação ao Design), não decisão do [W].

## Verificação

- `node scripts/design/ds-guard.mjs mobile/ref/proposta-01-ponto-v1/index.html` → limpo.
- Render local (http, tema escuro): `--accent` = `oklch(0.55 0.15 295)`, fonte IBM Plex Sans, alvo da aba = 48 px.
- **Não verificado:** o `Oimpresso Mobile.dc.html` vivo tem 443 KB, acima do teto de 256 KiB do `get_file`. A lista de arquivos do projeto bate com o espelho, mas o conteúdo vivo da 36–39 foi lido do espelho (sync de 05/10), não do projeto.

## Próximo passo

O Design incorpora (a)–(d) em `Oimpresso Mobile.dc.html`; o código segue no `wagnerra23/oimpresso-app`. Nada foi escrito no Cowork nem no projeto Design: o gerente sobe este recibo.
