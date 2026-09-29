---
id: requisitos-ponto-configuracoes-index-gap
tela: Ponto/Configuracoes/Index (/ponto/configuracoes)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Configuracoes/Index.tsx
gerado_em: 2026-09-28
charter: resources/js/Pages/Ponto/Configuracoes/Index.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/26-gap-configuracoes.md
---

# GAP-SPEC — Ponto/Configuracoes/Index

> **Fonte do contrato:** charter `Configuracoes/Index.charter.md` + protótipo `ponto-telas.jsx`,
> símbolo `Configuracoes` (`:937-1059`, ramo `tela === "config"`). Lado vivo medido em
> `Configuracoes/Index.tsx`, `ConfiguracaoController.php` e na fonte real dos parâmetros,
> `Modules/Ponto/Config/config.php` (publicado como `config_path('pontowr2.php')` pelo
> `PontoServiceProvider`). Base `origin/main` e4289e688. O `.tsx` vivo tem **0** `data-contract`.
> `D-CFG-POR-BUSINESS` está **ADIADA** por [W] e fica fora deste gap: a tela segue read-only.

## Achado principal — o vivo lê chaves que o config não tem

O `.tsx` vivo declara 15 chaves de config (`Index.tsx:18-43`). Contei cada uma em
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
| `rep.hash_algoritmo` | existe, mas em `marcacao`, e o controller não envia `marcacao` (`ConfiguracaoController.php:39-50`) |
| `afd.versao_portaria` · `afd.validar_hash_encadeado` | não |

Consequência provável: a tela mostra "—" (ou "desligada"/"Não") na maior parte dos parâmetros.
**Medido por leitura do código, não em runtime** — a prova de tela fica para quem for consertar.
O protótipo usa os nomes **reais** do config (`c.clt.tolerancia_minutos_por_marcacao`, `:999`
etc.), então aqui o protótipo está certo e o vivo errado no **dado**, não só na forma.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Nota somente leitura e atalho de REPs | **Paridade.** Vivo: "read-only por enquanto… edite `config/pontowr2.php`" (`Index.tsx:63`) + botão "Gerenciar REPs" (`:67-69`). Protótipo: nota com a mesma fonte (`:993-995`) e botão dentro do bloco REP (`:1033`). | Nada — paridade de conteúdo; a posição do botão é FORMA. |
| Regras CLT e Reforma Trabalhista | **Diverge — dado quebrado.** Vivo: 7 linhas com artigo (`Index.tsx:74-90`), das quais 5 leem chave inexistente (tabela acima). Protótipo: 9 parâmetros com o nome real e o artigo em cada um, incluindo hora noturna ficta, adicionais e DSR (`:997-1009`). | **Nada — corrigido no vivo pelo #8078.** Era: corrigir o vivo para ler as chaves reais — é defeito, não decisão. Copy de lei literal, como o charter exige. |
| Banco de Horas | **Diverge — dado quebrado.** Vivo: 2 linhas, ambas com chave inexistente (`Index.tsx:92-102`). Protótipo: 7 parâmetros reais (`:1010-1020`). | **Nada — corrigido no vivo pelo #8078.** Era: corrigir o vivo para ler `banco_horas.*` real. |
| REP e imutabilidade | **Diverge — dado quebrado.** Vivo: 3 linhas (`Index.tsx:104-122`), 2 com chave inexistente e o hash no bloco errado. Protótipo: 7 parâmetros de `rep` e `marcacao` (`:1021-1035`). O controller já remove o segredo do certificado ICP (`ConfiguracaoController.php:47`). | **Nada — corrigido no vivo pelo #8078.** Era: corrigir o vivo. Enviar `marcacao` exige estender a allowlist do controller — manter o `Arr::except` do certificado. |
| AFD e eSocial | **Diverge — dado quebrado.** Vivo: 2 linhas com chave inexistente + texto fixo de eSocial (`Index.tsx:124-135`). Protótipo: 7 parâmetros de `afd` e `esocial` (`:1036-1046`). O controller não envia `esocial` (`ConfiguracaoController.php:39-50`). | **Nada — corrigido no vivo pelo #8078.** Era: corrigir o vivo. Enviar `esocial` é decisão de allowlist: conferir se nada sensível entra. |
| IA do Ponto | **Ausente no vivo.** `grep -n "ai\." Configuracoes/Index.tsx` = 0 e o controller não envia `ai`. Protótipo: 5º bloco com master switch, 3 recursos e modelo (`:1047-1055`). | **Incorporar** — `D-CFG-IA` = INCORPORA, com a condição da ata. Ver `D-CFG-IA-CAMINHO` abaixo: a condição **não** está satisfeita hoje. |

## `D-CFG-IA-CAMINHO` — medido, e a resposta é "não tem caminho"

A ata pediu: *as flags de IA têm caminho de pacote/permissão hoje? Medir antes de construir.*
Medido em `Modules/Ponto/Config/config.php:120-126`: as 5 chaves de `ai` vêm de **`env()`**
(`AI_ENABLED`, `AI_CLASSIFICACAO_INTERCORRENCIA`, …) — é config **global do servidor**, sem
`business_id`, sem pacote e sem permissão. O comentário do próprio arquivo (`:95-119`) registra
que elas são expostas como prop Inertia `ai.*` pelo `HandleInertiaRequests`.

Logo, pela condição de [W] (*liga/desliga por business passa pela UI canônica de
pacote/permissão, nunca por `if` no código*), o bloco pode **exibir** o estado das flags, mas
**não** pode ganhar liga/desliga. **Pendente [W]:** se o bloco nasce só leitura (o que a tela
inteira já é) ou espera o caminho de pacote existir. Não inventei a resposta.

## Estado após o #8078 (2026-09-28)

As quatro partes marcadas **"Diverge — dado quebrado"** acima foram medidas em `e4289e688` e
continuam registradas como estavam naquele dia. O [#8078](https://github.com/wagnerra23/oimpresso.com/pull/8078)
(mergeado 2026-09-28 20:09Z) corrigiu a leitura e as fechou. Re-medido no `Index.tsx` pós-merge:

| Parte | Linhas vivas | Parâmetros vivo × protótipo | Estado |
|---|---|---|---|
| Regras CLT e Reforma Trabalhista | `Index.tsx:111-129` | 9 × 9 (`:997-1009`) | paridade |
| Banco de Horas | `Index.tsx:131-146` | 7 × 7 (`:1010-1020`) | paridade |
| REP e imutabilidade | `Index.tsx:148-176` | 7 × 7 (`:1021-1035`); `marcacao` agora chega do controller | paridade |
| AFD e eSocial | `Index.tsx:178-193` | 7 × 7 (`:1036-1046`); `esocial` chega por allowlist | paridade |

A nota somente leitura e o botão "Gerenciar REPs" moveram-se para `Index.tsx:97-107`; o
conteúdo segue em paridade. O bloco IA do Ponto segue **ausente no vivo** (`D-CFG-IA` pendente)
e por isso não recebe `data-contract` na thread 17.

O `configuracoes-index.map.json` foi atualizado junto: status `paridade`, faixas re-medidas e
`vivo.ancora` declarada nos quatro cards (thread 17, PR-4).
