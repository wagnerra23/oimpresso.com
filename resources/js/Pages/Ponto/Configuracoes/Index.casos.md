---
id: resources-js-pages-ponto-configuracoes-index-casos
casos: Painel de parâmetros do módulo de ponto · /ponto/configuracoes
irmaos: Index.charter.md (lei) · Reps.casos.md (a tela irmã) · RUNBOOK-configuracoes.md
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é um painel de compliance — o que ele afirma sobre imutabilidade e hash é o que o RH vai repetir numa fiscalização; e é a única tela do módulo que despeja a configuração do servidor no browser.
owner: wagner
last_run: "2026-09-08"
last_run_ci: "69 de 69 UC do Ponto com veredito pass no manifesto scripts/casos-test-results.json (fonte: test-results/pest-ponto-junit.xml). Lane PHP / Pest (Ponto - MySQL) run 34215745965 em main (sha dced5fd3d8, 2026-09-08T10:32Z): 302 passed - 1 skipped - 1009 assertions, coherent=true, provou_algo=true. Li ASSERTIONS, nao a conclusion: 1009 > 0 prova que a suite rodou e nao caiu no skip-as-pass da lane (LC-13). O unico skipped da run nao e UC (o coletor trata skip como nao-pass, e os 69 vieram pass). A lane e ADVISORY: reprova e visivel, nao bloqueia merge."
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
| UC-CFGIDX-01 | O painel não entrega ao browser a senha do certificado ICP | must `[T0]` | proibicoes (segredo) + charter §Mission (painel de leitura de *parâmetros*) | `ConfiguracaoContratoTest` | ✅ verde na lane |
| UC-CFGIDX-02 | Todo parâmetro que o painel exibe chega da configuração real | must | `Modules/Ponto/Config/config.php` (o que a apuração usa) + charter §Goals + [US-PONTO-012](../../../../../memory/requisitos/Ponto/SPEC.md) (5ª instância) | `ConfiguracaoContratoTest` | 🧪 teste cita o UC, sem veredito |

**Resolvido em 2026-09-28 — o painel lia 13 chaves que não existem** (fato datado; o registro de 2026-09-08 está no histórico do git deste arquivo)

- Em 2026-09-08 a contagem **medida em runtime** era: das 15 chaves que o `Index.tsx` lia no formato
  `config.<bloco>?.<chave>`, **2 existiam e 13 não**. Boa parte era nome trocado
  (`tolerancia_marcacao_minutos` × `tolerancia_minutos_por_marcacao`) e duas tinham outra unidade
  (horas × minutos). O efeito não era cosmético: o painel dizia **"desligada"** para a imutabilidade
  dos REPs e **"Não"** para NSR sequencial e validação de hash, numa tela que sustenta conversa com
  fiscalização (Portaria MTP 671/2021).
- **O conserto (decisão [W] 2026-09-28, "conserta a leitura das chaves de config"):** a tela passou
  a ler as chaves reais, com o conjunto de parâmetros do protótipo (que já usava os nomes do config).
  O controller passou a enviar `marcacao` (imutabilidade e hash moram lá, não em `rep`) e `esocial`
  (por allowlist), e do certificado ICP sai só o booleano `certificado_icp_configurado`. As chaves sem
  nenhum equivalente no config (`noturno_inicio/fim`, `versao_portaria`) saíram da tela, em vez de
  virarem chave inventada.
- **Defesa:** `UC-CFGIDX-02`, abaixo. É a 5ª instância da [US-PONTO-012](../../../../../memory/requisitos/Ponto/SPEC.md).

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

---

## UC-CFGIDX-02 · Todo parâmetro que o painel exibe chega da configuração real · `must`

- **Persona:** o gestor de RH que consulta o painel para responder a uma fiscalização (tolerâncias do
  Art. 58, interjornada do Art. 66, imutabilidade das marcações).
- **Aceite:** Dado o config do módulo (`Modules/Ponto/Config/config.php`) · Quando abro
  `/ponto/configuracoes` · Então **toda** leitura `config.<bloco>?.<chave>` que a tela faz encontra a
  chave no payload do controller. Nenhum parâmetro exibido cai no "—"/"Não" por falta de chave.
- **Teste:** `Modules/Ponto/Tests/Feature/ConfiguracaoContratoTest.php` — `UC-CFGIDX-02`.
- **Contrato:** o config do módulo, que é o que a apuração usa; a tela é o lado que pode mentir.
  Por isso o teste extrai as leituras do próprio `.tsx` e confere contra o payload, em vez de listar
  chaves à mão (lista à mão envelhece junto com a tela).
- **Anti-vácuo:** o teste exige ao menos 20 leituras extraídas. Se o `.tsx` mudar a forma de leitura,
  a regex casaria zero e o caso passaria sem verificar nada (LC-13).
- **Mordida medida (2026-09-28, por texto, dois lados):** contra o `.tsx` anterior ao conserto a
  extração acha 15 leituras e **13 sem chave no config**, as mesmas 13 do registro de 2026-09-08;
  contra o `.tsx` corrigido, 30 leituras e 0. O veredito da lane ainda não existe.
- **Status: 🧪 teste cita o UC, sem veredito de lane.**
