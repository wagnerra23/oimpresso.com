---
id: resources-js-pages-ponto-importacoes-show-casos
casos: Resultado da importação AFD · /ponto/importacoes/{id}
irmaos: Show.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F7 + §6.4 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a ponte entre o relógio físico (REP-A homologado) e a jornada apurada — duplicar aqui infla a folha.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Resultado da importação AFD

> **Âncora:** `CU-PONTO-10`, `CU-PONTO-11` e `CU-PONTO-12` do
> [SDD §6.4/§6.5](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + **US-PONTO-002** ·
> **Portaria MTE 1.510/2009** (AFD legacy, REP-A INMETRO) e **Portaria MTP 671/2021 Anexo I**
> (rastreabilidade). Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: não bloqueia merge.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-IMPSHOW-01 | Reimportar o mesmo arquivo não duplica marcação | must | `CU-PONTO-10` + US-PONTO-002 | `BancoHorasImportacaoContratoTest` | 🧪 sem veredito |
| UC-IMPSHOW-02 | A dedup é do meu empregador, não global | must `[T0]` | `CU-PONTO-10` + ADR 0093 | `BancoHorasImportacaoContratoTest` | 🧪 sem veredito |
| UC-IMPSHOW-03 | Importação de outro empregador → 404 | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `BancoHorasImportacaoContratoTest` | 🧪 sem veredito |
| UC-IMPSHOW-04 | As contagens exibidas refletem o que foi processado | must | `CU-PONTO-11` + US-PONTO-002 | `BancoHorasImportacaoContratoTest` | 🧪 **vermelho ESPERADO** (predição) |

**[BACKLOG]:**

- `[BACKLOG]` O processamento assíncrono resolve o tenant sem sessão — o job recebe o `business_id` no
  construtor ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)); provar isso
  é teste de job, não de tela.
- `[BACKLOG]` Validação de integridade do AFD (NSR sequencial, hash encadeado, faltas detectadas) —
  US-PONTO-002 lista a aceitação, mas o contrato mora no `AfdParserService`; a tela só exibe o resultado.
- `[BACKLOG]` AFD legacy (1.510/2009) está **parcial** e AFDT está **deprecated** regulatoriamente
  (substituído por AEJ, Anexo VI — US-PONTO-009, `_pendente_`). US sem código **não** vira UC agora:
  UC órfão trava o merge de quem for implementar ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).

---

## UC-IMPSHOW-01 · Reimportar o mesmo arquivo não duplica marcação · `must`

- **Persona:** RH que subiu o AFD do relógio, não teve certeza se funcionou, e subiu de novo. Cenário
  banal — e o erro dele não pode virar jornada em dobro na folha.
- **Aceite:** Dado um arquivo já importado com sucesso · Quando envio **exatamente o mesmo arquivo** de
  novo · Então a importação é **recusada** com aviso de que já foi importada (identificando quando), e
  **nenhuma** marcação nova é criada.
- **Teste:** `Modules/Ponto/Tests/Feature/BancoHorasImportacaoContratoTest.php` — `UC-IMPSHOW-01`.
- **Contrato:** `CU-PONTO-10` (SDD §6.4) · US-PONTO-002 (aceitação: *"Importação idempotente (mesma AFD
  pode ser re-uploadada sem duplicar marcacoes)"*) · `ImportacaoController@store` (dedup por
  `hash_file('sha256')`).
- **Regressão que defende:** a idempotência é garantida por **hash de conteúdo**, não por nome de arquivo.
  Trocar para nome (ou remover a checagem "porque o parser já ignora repetido") reintroduz duplicação —
  e marcação duplicada infla a jornada apurada, que vira HE paga em duplicidade.
- **Status: 🧪 sem veredito.**

---

## UC-IMPSHOW-02 · A dedup é do meu empregador, não global · `must` `[T0]`

- **Persona:** dois empregadores diferentes que usam o **mesmo modelo de REP-A**. Nada impede que gerem
  arquivos AFD byte-idênticos (mesmo layout, mesmo período, relógio recém-instalado sem marcações).
- **Aceite:** Dado que um arquivo com determinado conteúdo já foi importado por **outro** business ·
  Quando eu importo um arquivo de conteúdo idêntico no **meu** business · Então a importação é **aceita**
  (a dedup não me bloqueia por causa do arquivo alheio).
- **Teste:** `BancoHorasImportacaoContratoTest.php` — `UC-IMPSHOW-02`.
- **Contrato:** `CU-PONTO-10` · `ImportacaoController@store` (a busca de duplicata é
  `where('business_id', $businessId)->where('hash_arquivo', $hash)`) ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** este é o vetor **inverso** do vazamento de dados — não é dado escapando, é
  **negação de serviço cross-tenant**: remover o `business_id` da checagem de duplicata faria o arquivo de
  um empregador **bloquear** a importação de outro. Passa despercebido em review porque "menos filtro"
  parece inofensivo. E o storage segue o mesmo princípio: o arquivo é gravado sob
  `ponto/importacoes/{businessId}`, segregado por tenant.
- **Nota de teste:** biz=1 vs business fictício — **nunca biz=4** ([ADR 0101]).
- **Status: 🧪 sem veredito.**

---

## UC-IMPSHOW-03 · Importação de outro empregador → 404 · `must` `[T0]`

- **Persona:** plataforma multi-tenant. O detalhe da importação expõe nome do arquivo, hash e contagens —
  e a rota irmã permite **baixar o arquivo original**, que contém as marcações brutas do outro empregador.
- **Aceite:** Dado o id de uma importação de **outro** business · Quando acesso
  `/ponto/importacoes/{id}` · Então recebo **404**.
- **Teste:** `BancoHorasImportacaoContratoTest.php` — `UC-IMPSHOW-03`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 · LGPD Art. 7º II.
- **Regressão que defende:** `ImportacaoController@{show,baixarOriginal}` usam `Importacao::findOrFail($id)`
  **sem** filtro explícito — defesa única pelo global scope (SDD §9 D-5). Aqui o risco é maior que nas
  outras telas: o `baixarOriginal` entrega o **arquivo bruto**, não um resumo. Este UC ancora a proteção
  na porta de entrada.
- **Nota de teste:** biz=1 vs id fictício — **nunca biz=4** ([ADR 0101]).
- **Status: 🧪 sem veredito.**

---

## UC-IMPSHOW-04 · As contagens exibidas refletem o que foi processado · `must`

- **Persona:** RH que acabou de subir o AFD do mês. A pergunta é uma só: *"entrou tudo?"*. A tela responde
  com "Marcações criadas" e "Linhas ignoradas" — se esses números mentem, o RH aprova uma importação
  incompleta achando que está completa.
- **Aceite:** Dado uma importação concluída que registrou **N linhas com sucesso** (N > 0) · Quando abro o
  detalhe dela · Então a tela informa **N marcações criadas** — não zero.
- **Teste:** `BancoHorasImportacaoContratoTest.php` — `UC-IMPSHOW-04`.
- **Contrato:** `CU-PONTO-11` (SDD §6.4) · US-PONTO-002 (aceitação: *"`Importacao` registra arquivo +
  checksum + linhas processadas + erros"*) · Portaria MTP 671/2021 Anexo I (rastreabilidade da origem).
- **Regressão que defende:** **a regressão JÁ ACONTECEU** — é a **mesma classe do achado do espelho**
  (`Espelho/Show` UC-ESPSHOW-01), reincidindo em outra tela do mesmo módulo. Varredura contada:
  `linhas_criadas`/`linhas_ignoradas` aparecem **9 vezes** (3 no `ImportacaoController`, 6 consumindo no
  front) e **não existem na tabela** — a migration `ponto_importacoes` tem `linhas_total`,
  `linhas_processadas`, `linhas_sucesso` e `linhas_erro`. O `?? 0` do controller **mascara** o campo
  ausente, então toda importação bem-sucedida exibe **0 marcações criadas** (SDD §9 D-8).
- **Por que o assert é sobre o valor, não sobre a chave:** o contrato é *"a contagem exibida reflete o
  processado"*. Se a correção for expor sob outro nome, front e assert mudam **juntos** — o que não pode
  é a tela seguir dizendo zero quando o banco diz sete.
- **Status: 🧪 vermelho ESPERADO** — **predição**, não veredito. Status real vem da lane.
