---
date: "2026-08-11"
slug: consulta-clientes-v3-e-o-cnpj-que-era-real
hour: "18:11 UTC"
topic: "Consulta de clientes no preview V3 — o extra do handoff, e o CNPJ do protótipo que era documento real"
authors: [C, W]
prs: [5579]
us: [US-SELL-058]
tldr: "Último AindaNao da tela morreu. O CNPJ do cliente Governo do protótipo tinha DV VÁLIDO — documento real, barrado antes de entrar. Smoke em prod provou o Tier 0 por 3 caminhos: trocar cliente muda o que a tela DIZ e não move o total."
outcomes:
  - "PR #5579 mergeado [W] — 118 checks verdes, 0 falhas; consulta de clientes viva em /sells/create-v3"
  - "CNPJ real do protótipo (DV medido VÁLIDO) barrado antes do commit — allowlist do pii-scan proíbe nominalmente"
  - "Smoke real em prod com sessão [W]: total, subtotal e imposto INALTERADOS nas 3 trocas de cliente"
  - "23 testes novos em tests/js/cliente-consulta-dominio.test.ts — rodando na lane, não só localmente"
  - "9 itens [BACKLOG] da consulta agora ELEGÍVEIS a UC (o teste existe e o CI executa)"
---

# Consulta de clientes no preview V3 — e o CNPJ que era real

## O que entrou

[PR #5579](https://github.com/wagnerra23/oimpresso.com/pull/5579) (squash `f185a51675d`, merge [W] 12:28 UTC) porta o
modal de 880px de `prototipo-ui/cowork/venda-v3/sells-create.jsx:515` pra `/sells/create-v3`.
Era o **último `AindaNao`** da tela — não há mais gatilho inerte em `CreateV3.tsx`. Fecha o
"extra" do mapa do handoff `design_handoff_cadastro_venda`, depois das 6 ondas
([#5560](https://github.com/wagnerra23/oimpresso.com/pull/5560)…[#5564](https://github.com/wagnerra23/oimpresso.com/pull/5564)).

Arquivos: `_components/v3/ConsultaCliente.tsx` (315 ln) · `_components/v3/cliente-consulta-dominio.ts` (182 ln) ·
`tests/js/cliente-consulta-dominio.test.ts` (238 ln) · `clientes` na cena do `SellsV3Controller` ·
`CreateV3.tsx` (cliente virou estado; `Cliente` virou alias de `ClienteConsulta`).

## O achado que vale mais que o código

**O CNPJ do cliente Governo do protótipo tem dígito verificador VÁLIDO.** Medi os DVs *antes* de
copiar, com controle positivo (CNPJ público conhecido → VÁLIDO, provando que o validador
reconhece DV bom) e controle negativo (os 4 já no controller → inválidos, **4/4** batendo com o
que o próprio arquivo declara). DV válido significa **documento real** — e a allowlist do
`pii-scan` é explícita: *"Só aceita PII SINTÉTICA/fake — CPF/CNPJ real JAMAIS entra"*.

Não entrou, **nem em comentário**: a 1ª redação usava a base real como exemplo de busca sem
máscara num docblock, e o `pii-scan` mordeu. Corrigido pra base sintética. Pelo mesmo motivo
**"Rota Livre" saiu da cena** — é o cliente-piloto de verdade (biz=4), não personagem.

> **Perene:** dado de cena que vem de protótipo **não é presumidamente fake**. Medir DV com
> controle positivo *e* negativo custa um script de 20 linhas e é a diferença entre cena e
> incidente de privacidade reportável.

## Tier 0 — trocar cliente não reprecifica, provado em prod

Duas metades com **forças diferentes**, e o handoff não as achata:

- **MEDIDO** (`grep` em `CreateV3.tsx`): `tabelaCadastro`/`tabelaAtiva`/`tabelaTrocada` só aparecem
  no cartão "Tabela de preço" — nunca em `linhaTotal`/`subtotal`/`total`. O preço unitário mora
  em `itens`, que a seleção não toca.
- **PROVADO** (23/23): cliente novo nasce com `tabela: null`; `ClienteConsulta` não carrega campo
  de preço — a ausência é a defesa.

Smoke real em prod (sessão [W], Chrome), três estados — valores omitidos por política
(§"NUNCA commitar valores BRL"); o que importa é a **invariância**:

| | inicial | Atacado (0288) | Padaria nova (0392) |
|---|---|---|---|
| Total da venda | referência | **idêntico** | **idêntico** |
| Subtotal | referência | idêntico | idêntico |
| Imposto | referência | idêntico | idêntico |
| Tabela **exibida** | Balcão | Atacado — a partir de 50m² | **Balcão — preço padrão** |

A 3ª coluna é a prova mais forte: cliente novo tem `tabela: null`, então a tela **voltou** ao padrão
do balcão em vez de herdar a tabela Atacado do anterior. Mudou o que a tela **diz**; o número nunca
se moveu. É o caminho oposto ao da lápide de 2026-07-15 (`CustomerSearchAutocomplete` reaplicando
`selling_price_group_id` no `onSelect`).

## Divergências conscientes da fonte

**A busca.** O protótipo compara `(cod+nome+doc+cidade).toLowerCase().includes(termo)` literal —
então o placeholder promete *"Buscar por nome, CNPJ/CPF, cidade ou código…"* e não acha o documento
**sem máscara** (como o operador digita do papel) nem cidade **sem acento**. Aqui o documento casa
por dígito e o texto casa sem acento. Continua `includes` e não fuzzy; termo vazio devolve a lista
inteira; ordem preservada. Há **controle negativo** pro eixo numérico — sem ele `soDigitos('atacado')`
daria `''` e `''.includes('')` casaria **toda** linha, devolvendo tudo pra qualquer termo textual.
Confirmado em prod nas duas formas.

**O "Novo cadastro" veio junto** porque o rodapé da consulta **é** o botão dele — portar a consulta e
deixar o botão morto criaria o que o cabeçalho da Page recusa (*"botão que promete e não entrega é
pior que botão ausente"*). Cria em memória e devolve selecionado, como a fonte promete; o protótipo
só fecha o modal sem selecionar nada.

## Método — o que deu errado no meu próprio instrumento

Mutei os 4 guards do domínio pra confirmar que os testes mordem. **O primeiro veredito saiu falso**:
o `sed` não casou o padrão, o arquivo não mudou, e eu quase registrei "não mordeu" — que teria sido
LC-08 dentro da própria verificação anti-LC-08. Refiz com script que **prova que a mutação entrou**
antes de rodar. Os 4 mordem. Árvore restaurada byte-idêntica.

Outros dois instrumentos meus mentiram na mesma sessão, sem consequência porque cruzei com outra
fonte: `grep` de contagem de falhas devolvendo 0 pra mutação que **derrubou** o teste (padrão errado),
e `&&`/`||` sobre `npx vitest` reportando FALHOU quando o `rc` era 0 (o `grep` do encadeamento é que
falhou, por causa dos códigos ANSI). Em ambos, o sinal confiável era o **exit code**, não o texto.

## Estado MCP no momento do fechamento

⚠️ **O checklist MCP-first da [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) NÃO
foi executado — e isso é medição, não desculpa.** O servidor MCP esteve **inacessível a sessão
inteira**:

- hook `brief-fetch` do SessionStart: **timeout** (fallback ativado, registrado no próprio log da sessão);
- `tailscale ssh root@ct100-mcp`: **502 Bad Gateway** + `connectex` timeout na porta 22;
- `ToolSearch` por tools `mcp__oimpresso__*` (cycles-active · my-work · sessions-recent · decisions-search):
  **nenhuma disponível** nesta sessão.

Logo: `cycles-active`, `my-work`, `sessions-recent` e `decisions-search` **não foram consultados**, e
**nada foi registrado em `mcp_tasks`**. Se a `US-SELL-058` precisa de mudança de status, é manual.

## O que fica aberto

1. **Promover os 9 `[BACKLOG]` da consulta a `UC-V3xx`.** Agora são **elegíveis**: o
   [#5578](https://github.com/wagnerra23/oimpresso.com/pull/5578) criou a lane
   `sells-v3-dominio-gate.yml` e **incluiu `tests/js/cliente-consulta-dominio.test.ts`** nela
   (linha 110) — verificado rodando: `c14d99613fb` success, `23 tests passed`. Falta só o teste
   **citar** o id do UC no título, que é a condição do G-2.
2. **A busca não limpa ao fechar o modal** — só a seleção limpa. Idêntico ao protótipo (que só faz
   `setBuscaCli('')` no clique da linha), então não é regressão; descoberto smokando e **não
   declarado** em nenhum caso. Uma linha (`setBusca('')` no `onFechar`) se incomodar.
3. **`php -l` nunca rodou** no `SellsV3Controller`: `php` fora do PATH e CT 100 em 502. Fiz só
   checagem estrutural (aspas simples pares, colchetes 48/48), que **não é um parse**. O deploy ter
   passado é evidência mais forte na prática, porém **indireta**.

## Deploy — e a falha que NÃO era minha

O deploy `fb3be038a69` **falhou**, e a causa foi `ssh: connect to host ... Connection timed out` no
passo *Pré-deploy check* — a flakiness de SSH pro Hostinger já catalogada em
[`how-trabalhar.md`](../how-trabalhar.md). **Nem chegou no código.** Apliquei o warm-up documentado
(5 hits curl); o deploy seguinte `c14d99613fb` fechou com sucesso e contém o commit. Os `503` durante
o warm-up eram a janela de manutenção — voltou a `200` e estabilizou.

Nas últimas 25 execuções do `deploy.yml`: 14 success, **8 cancelled**, 1 failure. A concorrência
cancela deploys antigos quando chegam commits novos — esperar o deploy "do seu commit" é a pergunta
errada; a certa é *"algum deploy bem-sucedido CONTÉM meu commit?"* (`git merge-base --is-ancestor`).
