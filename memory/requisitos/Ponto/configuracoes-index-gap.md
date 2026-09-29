---
id: requisitos-ponto-configuracoes-index-gap
tela: Ponto/Configuracoes/Index (/ponto/configuracoes)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Configuracoes/Index.tsx
gerado_em: 2026-09-29
charter: resources/js/Pages/Ponto/Configuracoes/Index.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/26-gap-configuracoes.md
---

# GAP-SPEC — Ponto/Configuracoes/Index

> **Fonte do contrato:** charter `Configuracoes/Index.charter.md` + protótipo `ponto-telas.jsx`,
> símbolo `Configuracoes` (`:978-1101`; o ramo de configurações é o `return` final, `:1033-1100`,
> quando `tela` — `:982` — não é `"reps"`). Lado vivo medido em
> `Configuracoes/Index.tsx`, `ConfiguracaoController.php` e na fonte real dos parâmetros,
> `Modules/Ponto/Config/config.php` (publicado como `config_path('pontowr2.php')` pelo
> `PontoServiceProvider`). Base `origin/main` e4289e688 (citações de linha re-medidas em 2026-09-29
> no working tree). O `.tsx` vivo tinha **0** `data-contract` em `e4289e688`; em 2026-09-29 tem **4**
> (os quatro cards, desde o #8096).
> `D-CFG-POR-BUSINESS` está **ADIADA** por [W] e fica fora deste gap: a tela segue read-only.

## Achado principal — o vivo lê chaves que o config não tem

Estado de `e4289e688` (2026-09-28), corrigido pelo #8078 — ver §Estado após o #8078. O `.tsx`
vivo declarava 15 chaves de config (no tipo `Props` de então). Contei cada uma em
`Modules/Ponto/Config/config.php` (`grep -c "'<chave>'"`): **12 não existem em lugar nenhum do
arquivo**, e das 3 que existem uma está no bloco errado.

| chave lida pelo vivo | existe no config? |
|---|---|
| `clt.tolerancia_marcacao_minutos` | não — o nome real é `tolerancia_minutos_por_marcacao` |
| `clt.tolerancia_maxima_diaria_minutos` · `clt.intrajornada_minima_minutos` | sim |
| `clt.interjornada_minima_minutos` | não — o real é `interjornada_minima_horas` |
| `clt.he_maxima_diaria_minutos` · `noturno_inicio` · `noturno_fim` · `dsr_percentual` | não |
| `banco_horas.limite_credito_minutos` · `prazo_expiracao_meses` | não |
| `rep.imutabilidade_mysql` · `rep.nsr_autoincrement` | não |
| `rep.hash_algoritmo` | existe, mas em `marcacao`, e o controller não enviava `marcacao` (em 2026-09-29 envia: `ConfiguracaoController.php:54`) |
| `afd.versao_portaria` · `afd.validar_hash_encadeado` | não |

Consequência provável: a tela mostra "—" (ou "desligada"/"Não") na maior parte dos parâmetros.
**Medido por leitura do código, não em runtime** — a prova de tela fica para quem for consertar.
O protótipo usa os nomes **reais** do config (`c.clt.tolerancia_minutos_por_marcacao`, `:1041`
etc.), então aqui o protótipo está certo e o vivo errado no **dado**, não só na forma.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Nota somente leitura e atalho de REPs | **Diverge desde 2026-09-29.** Vivo: a nota "read-only… edite `config/pontowr2.php`" saiu junto com o header próprio da tela no #8118; ficou só o botão "Gerenciar REPs" (`Index.tsx:99-103`), e `grep -n "pontowr2" Index.tsx` só acha o comentário da `:19`. Protótipo: nota "Somente leitura" com a fonte e o atalho para o cadastro de REPs (`:1035-1037`) e botão dentro do bloco REP (`:1075`). | **Restaurar no vivo** a nota somente leitura — é conteúdo que o vivo tinha até o #8118 (2026-09-29), não FORMA. A posição do botão segue sendo FORMA. |
| Regras CLT e Reforma Trabalhista | **Paridade desde o #8078 (2026-09-28).** Vivo: 9 parâmetros com o nome real e o artigo em cada um (card `Index.tsx:106-124`). Protótipo: 9 (`:1039-1051`). O estado anterior ("dado quebrado", medido em `e4289e688`) está no histórico git deste arquivo, desde o #8073 (`82a6d6532`). | **Nada** — corrigido no vivo pelo #8078 (2026-09-28); o que sobra é FORMA. |
| Banco de Horas | **Paridade desde o #8078 (2026-09-28).** Vivo: 7 parâmetros de `banco_horas.*` (card `Index.tsx:126-141`). Protótipo: 7 (`:1052-1062`). | **Nada** — corrigido no vivo pelo #8078 (2026-09-28); o que sobra é FORMA. |
| REP e imutabilidade | **Paridade desde o #8078 (2026-09-28).** Vivo: 7 parâmetros de `rep` e `marcacao` (card `Index.tsx:143-171`). Protótipo: 7 (`:1063-1077`). O controller envia `marcacao` (`ConfiguracaoController.php:54`) e segue tirando o segredo do certificado ICP (`:51-52`). | **Nada** — corrigido no vivo pelo #8078 (2026-09-28); o que sobra é FORMA. |
| AFD e eSocial | **Paridade desde o #8078 (2026-09-28).** Vivo: 7 parâmetros de `afd` e `esocial` (card `Index.tsx:173-188`). Protótipo: 7 (`:1078-1088`). O controller envia `esocial` por allowlist (`ConfiguracaoController.php:57`). | **Nada** — corrigido no vivo pelo #8078 (2026-09-28); o que sobra é FORMA. |
| IA do Ponto | **Ausente no vivo.** `grep -n "ai\." Configuracoes/Index.tsx` = 0 (re-contado em 2026-09-29: 0) e o controller não envia `ai`. Protótipo: 5º bloco com master switch, 3 recursos e modelo (`:1089-1097`). | **Incorporar** — `D-CFG-IA` = INCORPORA, com a condição da ata. Ver `D-CFG-IA-CAMINHO` abaixo: a condição **não** está satisfeita hoje. |

## `D-CFG-IA-CAMINHO` — medido, e a resposta é "não tem caminho"

A ata pediu: *as flags de IA têm caminho de pacote/permissão hoje? Medir antes de construir.*
Medido em `Modules/Ponto/Config/config.php:138-142`: as 5 chaves de `ai` vêm de **`env()`**
(`AI_ENABLED`, `AI_CLASSIFICACAO_INTERCORRENCIA`, …) — é config **global do servidor**, sem
`business_id`, sem pacote e sem permissão. O comentário do próprio arquivo (`:111-136`) registra
que elas são expostas como prop Inertia `ai.*` pelo `HandleInertiaRequests`.

Logo, pela condição de [W] (*liga/desliga por business passa pela UI canônica de
pacote/permissão, nunca por `if` no código*), o bloco pode **exibir** o estado das flags, mas
**não** pode ganhar liga/desliga. **Pendente [W]:** se o bloco nasce só leitura (o que a tela
inteira já é) ou espera o caminho de pacote existir. Não inventei a resposta.

2026-09-29: o charter (emenda da thread 27, #8095, 2026-09-28) registra no Non-Goal que, enquanto
esse caminho não existir, o bloco "IA do Ponto" é somente leitura (`Index.charter.md:45-47`), e no
Goal que ele está "a construir" (`:31-35`).

## Estado após o #8078 (2026-09-28)

As quatro partes que a tabela de partes marcava **"Diverge — dado quebrado"** foram medidas em
`e4289e688`; aquele registro está no histórico git deste arquivo, desde o #8073 (em 2026-09-29 a tabela foi re-medida). O [#8078](https://github.com/wagnerra23/oimpresso.com/pull/8078)
(mergeado 2026-09-28 20:09Z) corrigiu a leitura e as fechou. Linhas re-medidas em 2026-09-29, depois
do #8118 (que deslocou o arquivo):

| Parte | Linhas vivas | Parâmetros vivo × protótipo | Estado |
|---|---|---|---|
| Regras CLT e Reforma Trabalhista | `Index.tsx:106-124` | 9 × 9 (`:1039-1051`) | paridade |
| Banco de Horas | `Index.tsx:126-141` | 7 × 7 (`:1052-1062`) | paridade |
| REP e imutabilidade | `Index.tsx:143-171` | 7 × 7 (`:1063-1077`); `marcacao` agora chega do controller | paridade |
| AFD e eSocial | `Index.tsx:173-188` | 7 × 7 (`:1078-1088`); `esocial` chega por allowlist | paridade |

Em 2026-09-28 a nota somente leitura e o botão "Gerenciar REPs" seguiam em paridade de conteúdo.
2026-09-29: a nota saiu do vivo com o #8118 — ver §Estado em 2026-09-29. O bloco IA do Ponto segue **ausente no vivo** (`D-CFG-IA` pendente)
e por isso não recebe `data-contract` na thread 17.

O `configuracoes-index.map.json` foi atualizado junto: status `paridade`, faixas re-medidas e
`vivo.ancora` declarada nos quatro cards (thread 17, PR-4).

## Estado em 2026-09-29 (após o #8118)

O #8118 (header de módulo do Ponto, 2026-09-29) trocou o header próprio da tela e deslocou as
linhas de `Index.tsx` (as tabelas deste arquivo já estão re-medidas). Efeito que não é só deslocamento: **a nota
somente leitura saiu do vivo** junto com o header — ver a linha "Nota somente leitura e atalho
de REPs" da tabela de partes.
