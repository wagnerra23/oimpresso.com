---
id: resources-js-pages-ponto-configuracoes-index-casos
casos: Painel de parâmetros do módulo de ponto · /ponto/configuracoes
irmaos: Index.charter.md (lei) · Reps.casos.md (a tela irmã) · RUNBOOK-configuracoes.md
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é um painel de compliance — o que ele afirma sobre imutabilidade e hash é o que o RH vai repetir numa fiscalização; e é a única tela do módulo que despeja a configuração do servidor no browser.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "1 UC rodado por mim no CT 100 (container oimpresso-staging, MySQL real), NAO em CI. O achado que originou o UC-CFGIDX-01 foi MEDIDO com sonda antes de existir teste: definindo `pontowr2.rep.certificado_icp_pass` com uma sentinela e batendo em /ponto/configuracoes, a sentinela aparecia no corpo da resposta (status 200) — o controller passava `config('pontowr2')` INTEIRO como prop Inertia, 14 blocos, e prop Inertia viaja no HTML servido ao browser. O conserto entra no MESMO PR, entao o UC nasce verde em vez de avermelhar uma lane required. CT100 != CI: la a base persiste entre runs; verde la e CANDIDATURA, nao veredito."
---

# Casos de Uso & Aceite — Painel de parâmetros do ponto

> **Âncora:** `Index.charter.md` §Mission/§Goals + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
> + [proibicoes.md](../../../../../memory/proibicoes.md) (segredo nunca em superfície que o cliente lê)
> + Portaria MTP 671/2021 (o que o painel afirma sobre imutabilidade é afirmação regulatória).
> Os UC derivam do **contrato**, nunca do `Index.tsx`.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-CFGIDX-01 | O painel não entrega ao browser a senha do certificado ICP | must `[T0]` | proibicoes (segredo) + charter §Mission (painel de leitura de *parâmetros*) | `ConfiguracaoContratoTest` | 🧪 verde no CT 100, sem veredito de lane |

**[BACKLOG]** — o painel está quase todo fantasma: **13 das 15 chaves que ele lê não existem**

- `[BACKLOG]` **Este painel de compliance quase não mostra dado real.** Contagem exaustiva (as 15
  chaves distintas que o `Index.tsx` lê no formato `config.<bloco>?.<chave>`, cruzadas com
  `Modules/Ponto/Config/config.php`): **2 existem, 13 não**. Medido em **runtime**, não por leitura —
  `config('pontowr2.<chave>')` com sentinela de ausência devolveu `<<AUSENTE>>` para as 13.
  - **Existem (2):** `clt.tolerancia_maxima_diaria_minutos` (10) · `clt.intrajornada_minima_minutos` (60).
  - **Fantasma (13):** `clt.tolerancia_marcacao_minutos` · `clt.interjornada_minima_minutos` ·
    `clt.he_maxima_diaria_minutos` · `clt.noturno_inicio` · `clt.noturno_fim` · `clt.dsr_percentual` ·
    `banco_horas.limite_credito_minutos` · `banco_horas.prazo_expiracao_meses` ·
    `rep.imutabilidade_mysql` · `rep.hash_algoritmo` · `rep.nsr_autoincrement` ·
    `afd.versao_portaria` · `afd.validar_hash_encadeado`.
  - **Boa parte é só nome trocado**, o que torna o conserto barato e o diagnóstico fácil de perder: a
    configuração tem `tolerancia_minutos_por_marcacao`, `interjornada_minima_horas`,
    `limite_he_diaria_horas` e `adicional_dsr_percentual` — a tela procura outros nomes (e, em dois
    casos, outra **unidade**: horas × minutos).
  - **O efeito não é cosmético.** Como o `.tsx` renderiza `?? '—'` e `? 'Sim' : 'Não'`, o painel exibe
    **"Inativa"** para a imutabilidade dos REPs e **"Não"** para NSR sequencial e validação de hash
    chain — quando o módulo tem triggers de imutabilidade. É afirmação falsa numa tela que sustenta
    conversa com fiscalização (Portaria MTP 671/2021), e o erro é do lado que **subestima** a
    conformidade.
- **Por que não virou UC nem foi consertado aqui:** o tema **já tem dona** —
  [US-PONTO-012](../../../../../memory/requisitos/Ponto/SPEC.md) ("Corrigir os atributos fantasma do
  módulo"), que hoje lista 4 instâncias (`tem_divergencia` no espelho, `entrada/saida/almoco_*` na
  escala, `linhas_criadas/linhas_ignoradas` e `erro_mensagem` na importação). Estas são a **5ª**, e o
  caminho canônico é **estender o dono, não abrir paralelo** ([proibicoes §5](../../../../../memory/proibicoes.md)
  2026-07-09). Consertar exige decidir, campo a campo, *qual* é a verdade — renomear a leitura?
  converter unidade? criar a chave? ler do schema, no caso da imutabilidade? — e essa é decisão de
  [W], não inferência minha. Um UC agora nasceria vermelho numa lane **required**, bloqueando todo PR
  que toca o módulo, sem que ninguém tenha decidido o conserto.

Outros `[BACKLOG]` desta tela:

- `[BACKLOG]` O charter pergunta em §Pendências se os parâmetros devem passar a ser **editáveis por
  business** (hoje é config de arquivo, global). Enquanto a resposta não vier, não há contrato para
  testar — e o §Non-Goals atual ainda diz "não escopada por `business_id`… (confirmar com Wagner)",
  que é inferência pendente, não lei.
- `[BACKLOG]` A tela não tem teste que prove que os parâmetros CLT exibidos são os **vigentes** (Art.
  58/59/66/71/73). O `UC-CFGIDX-01` afirma que o bloco `clt` chega, mas não que cada número bate com
  a apuração — quem apura é o `ApuracaoService`, e amarrar os dois é trabalho próprio.

---

## UC-CFGIDX-01 · O painel não entrega ao browser a senha do certificado ICP · `must` `[T0]`

- **Persona:** qualquer usuário com `ponto.access` — inclusive um que só deveria consultar tolerâncias.
  A senha do certificado ICP-Brasil assina marcação de ponto (PKCS#7 A1); quem a tem pode assinar
  registro de jornada em nome do empregador.
- **Aceite:** Dado que o certificado ICP está configurado (caminho e senha definidos) · Quando abro
  `/ponto/configuracoes` · Então **nem a senha nem o caminho** do certificado aparecem no que é
  entregue à tela — e, ao mesmo tempo, os parâmetros que o painel existe para mostrar (as tolerâncias
  CLT) **continuam chegando**.
- **Teste:** `Modules/Ponto/Tests/Feature/ConfiguracaoContratoTest.php` — `UC-CFGIDX-01`.
- **Contrato:** charter §Mission (*"painel de leitura… tolerâncias e limites CLT"* — parâmetros, não
  credenciais) · [proibicoes.md](../../../../../memory/proibicoes.md) (segredo não vive em superfície
  que o cliente lê; a fonte canônica é o Vaultwarden) · LGPD/segurança básica.
- **Regressão que defende:** a que **já tinha acontecido** — este UC nasce de um achado, não de uma
  hipótese. `ConfiguracaoController@index` fazia `Inertia::render(…, ['config' => config('pontowr2')])`,
  e prop Inertia viaja inteira no HTML. A sonda com sentinela confirmou: a senha saía no corpo da
  resposta. Reintroduzir "manda o config inteiro, a tela filtra" traz o vazamento de volta — filtro em
  TypeScript não filtra nada, porque o dado já viajou.
- **As duas metades do assert, e por que as duas:** só *"o segredo não aparece"* passaria também num
  cenário em que a tela parou de receber configuração nenhuma (verde por vácuo — LC-13). Por isso o
  caso afirma **junto** que o bloco `clt` continua chegando com as tolerâncias.
- **Como o assert é escrito:** ele procura o **valor** da sentinela e o **nome da chave**, não a forma
  do filtro. Existe mais de uma correção legítima (allowlist de blocos, `Arr::except`, um Resource) e
  um assert sobre a forma reprovaria as outras.
- **Status: 🧪 verde no CT 100, sem veredito de lane.**
