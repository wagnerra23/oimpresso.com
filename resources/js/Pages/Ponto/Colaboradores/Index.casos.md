---
id: resources-js-pages-ponto-colaboradores-index-casos
casos: Lista de colaboradores com controle de ponto · /ponto/colaboradores
irmaos: Index.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §6.5 (contrato) · Edit.casos.md (a tela irmã)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a porta de entrada para configurar quem entra na apuração CLT — e é uma tela de BUSCA, onde o filtro de empregador é a coisa mais fácil de perder sem ninguém notar.
owner: wagner
last_run: "2026-09-08"
last_run_ci: "69 de 69 UC do Ponto com veredito pass no manifesto scripts/casos-test-results.json (fonte: test-results/pest-ponto-junit.xml). Lane PHP / Pest (Ponto - MySQL) run 34215745965 em main (sha dced5fd3d8, 2026-09-08T10:32Z): 302 passed - 1 skipped - 1009 assertions, coherent=true, provou_algo=true. Li ASSERTIONS, nao a conclusion: 1009 > 0 prova que a suite rodou e nao caiu no skip-as-pass da lane (LC-13). O unico skipped da run nao e UC (o coletor trata skip como nao-pass, e os 69 vieram pass). A lane e ADVISORY: reprova e visivel, nao bloqueia merge."
---

# Casos de Uso & Aceite — Lista de colaboradores

> **Âncora:** `CU-PONTO-12` do [SDD §6.5](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md)
> (invariante transversal de isolamento) + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
> + o `Index.charter.md` ao lado. Os UC derivam do **contrato**, nunca do `Index.tsx` — teste
> derivado do código é tautológico ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-06-05).
>
> **Fonte 4 (Delphi) ausente neste módulo** — declarado no SDD §0.1, não inventado.
>
> ⚖️ **Força do veredito:** quem responde por bloqueio de merge é
> [`governance/required-checks-baseline.json`](../../../../../governance/required-checks-baseline.json),
> não esta linha.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-COLIDX-01 | Buscar por matrícula ou CPF não alcança colaborador de outro empregador | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `ColaboradorContratoTest` | ✅ verde na lane |
| UC-COLIDX-02 | Busca que não casa ninguém devolve lista vazia, não a lista inteira | must | charter §Goals (busca + empty state de "busca sem resultado") | `ColaboradorContratoTest` | ✅ verde na lane |
| UC-COLIDX-03 | Lista redige CPF e PIS (3 últimos dígitos) — documento inteiro só no form de edição | must | charter §Pendências resolvida por [W] 2026-09-14 + `D-COLAB-CPF` do protótipo | `ponto-colaboradores-redacao.test.tsx` | 🧪 teste cita o UC, sem veredito |

**[BACKLOG]** (medido nesta sessão, sem teste que o defenda — vira UC quando ganhar um):

- ~~`[BACKLOG]` o filtro manual do controller não defende esta busca~~ — **RESOLVIDO 2026-09-05**.
  O fato medido em 2026-09-04 fica registrado porque é o que explica o conserto: com termo de busca
  a cláusula saía `(business_id = ? and exists(users…) or matricula like ? or cpf like ?)` — o
  `where('business_id', …)` escrito à mão ficava do lado esquerdo de um `OR`, então bastava casar a
  matrícula para ele deixar de valer, e quem segurava sozinho era o **global scope** do trait
  `HasBusinessScope`, que o Laravel adiciona como um segundo grupo ligado por `AND`
  (`callScope` → `addNewWheresWithinGroup`) — a **defesa única** que o
  [SDD §9 D-5](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) nomeia.
  O bloco de busca ganhou grupo próprio (`$q->where(function ($sub) …)`) em
  `ColaboradorController@index` e a defesa voltou a ser **dupla**. Comportamento observável não
  mudou — `UC-COLIDX-01` provava antes e segue provando, e foi justamente por isso que o conserto
  coube. A sessão que mediu registrou isto como decisão de [W]; discordo com razão declarada e a
  mudança é minha: **como** escrever a query é técnica (o COMO), não escopo de produto, e devolver
  isso é o anti-padrão LC-28 de
  [proibicoes §Comportamento Claude](../../../../../memory/proibicoes.md). O que segue sendo de [W]
  é **o que** a busca deve encontrar — não como ela agrupa.
- ~~`[BACKLOG]` A coluna de CPF aparece inteira na lista~~ — **RESOLVIDO 2026-09-14**, virou `UC-COLIDX-03`:
  em **2026-09-14** [W] respondeu a pergunta do charter — *"é liberado ser igual ao protótipo"* — e o
  protótipo `D-COLAB-CPF` ([`ponto-telas.jsx`](../../../../../prototipo-ui/cowork/Wagner/ponto-telas.jsx))
  mascara CPF **e** PIS nos 3 últimos dígitos na lista, deixando os inteiros só no form de edição.
  A posição anterior — **não mascarar** (*"pode deixar os dados sim é um ERP"*, **2026-08-21**, o
  controle é por permissão de acesso, não por ocultação) — **segue verdadeira como fato daquela data
  e sobre o espelho**, e está superseded para esta coluna. O `.tsx` foi aplicado no mesmo ciclo e o caso
  virou `UC-COLIDX-03`, com teste. A pegadinha que este bullet registrava — `maskCPF` de
  [`Lib/br-mask.ts`](../../../../../resources/js/Lib/br-mask.ts) **formata** (insere pontos e
  hífen), não redige, e redação de exibição não existia no front — foi paga criando
  `redigirDigitos` em [`Lib/format-br.ts`](../../../../../resources/js/Lib/format-br.ts). Ela segue
  valendo como aviso: trocar um pelo outro entrega o documento inteiro, formatado, e o assert do
  `UC-COLIDX-03` existe justamente pra distinguir os dois.
- `[BACKLOG]` A busca por nome usa só `first_name`; sobrenome (`last_name`) não entra. Quem procura
  "Silva" não acha ninguém. Não virou UC porque o charter diz "busca por matrícula, nome ou CPF" sem
  definir o que é "nome" — é ambiguidade de contrato, e inventar a resposta seria pior que registrar.

---

## UC-COLIDX-01 · Buscar por matrícula ou CPF não alcança colaborador de outro empregador · `must` `[T0]`

- **Persona:** gestor de RH de um empregador. Matrícula e CPF são chaves que ele digita todo dia; se a
  busca atravessar o empregador, um CPF digitado errado devolve gente de outra empresa — e jornada é
  dado sensível (LGPD Art. 7º + sigilo trabalhista).
- **Aceite:** Dado um colaborador cadastrado em **outro** empregador, com matrícula e CPF conhecidos ·
  Quando eu busco na lista por aquela matrícula, e depois por aquele CPF · Então **nenhuma** das duas
  buscas traz o registro dele.
- **Teste:** `Modules/Ponto/Tests/Feature/ColaboradorContratoTest.php` — `UC-COLIDX-01`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
  charter §Non-Goals (*"Não lista colaborador de outro business"*).
- **Regressão que defende — e o limite dela, medido:** desde 2026-09-05 a defesa é **dupla** (o
  global scope do `HasBusinessScope` **e** o filtro do controller, que voltou a valer quando o bloco
  de busca ganhou grupo próprio — ver `[BACKLOG]` acima, com o SQL dos dois estados). Consequência
  honesta: este caso **só morde quando as duas caem**. Bite-test por mutação no CT 100 — só o trait
  removido: `1 passed`; só o agrupamento desfeito: `1 passed`; **as duas juntas: `1 failed`** (3
  asserts). Ele é rede contra a perda **completa** do isolamento desta busca, não um detector de
  defesa enfraquecida. Mesmo desenho de `UC-ESCIDX-01` e `UC-CFGREP-01`.
- **Como o assert é escrito, e por quê:** o caso busca pelo **CPF** do colaborador alheio e então
  procura a **matrícula** dele na resposta. Parece torto e é deliberado: o controller devolve o termo
  buscado na prop `search`, então procurar o termo que você mesmo buscou casa por **eco** e não prova
  nada. Foi exatamente o falso-positivo que a primeira sonda desta sessão produziu.
- **Status: 🧪 verde no CT 100, sem veredito de lane** — CT 100 é candidatura; quem decide é a lane.

---

## UC-COLIDX-03 · Lista redige CPF e PIS (3 últimos dígitos) — documento inteiro só no form de edição · `must`

- **Persona:** gestor de RH varrendo a lista pra achar quem configurar. Ele precisa **reconhecer** o
  colaborador, não ler o documento dele — e faz isso de pé, com gente atrás, às vezes em
  screenshare. Os 3 últimos dígitos bastam pra desempatar dois homônimos; os 8 primeiros só
  aumentam a superfície de exposição de uma tela que ninguém veio ali pra ler.
- **Aceite:** Dado um colaborador com CPF e PIS cadastrados · Quando abro a lista · Então vejo os
  **3 últimos dígitos** de cada um, o resto substituído por `•` — e o documento **inteiro** não
  aparece em lugar nenhum da lista. Dado um colaborador **sem** PIS · Então leio
  **"PIS não cadastrado"** em vez de um campo vazio.
- **Teste:** [`tests/js/ponto-colaboradores-redacao.test.tsx`](../../../../../tests/js/ponto-colaboradores-redacao.test.tsx) — `UC-COLIDX-03`.
- **Contrato:** `D-COLAB-CPF` em
  [`prototipo-ui/cowork/Wagner/ponto-telas.jsx`](../../../../../prototipo-ui/cowork/Wagner/ponto-telas.jsx)
  (*"lista é tela de varredura — minimização de dado é o default (LGPD). CPF/PIS inteiros só no form
  de edição"*), ratificado por [W] em **2026-09-14** (*"é liberado ser igual ao protótipo"*) ao
  responder a §Pendências do charter. Eixo FORMA ⇒ protótipo soberano
  ([ADR UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).
  ⚠️ A posição anterior — **não** mascarar (*"pode deixar os dados sim é um ERP"*, **2026-08-21**) —
  segue verdadeira como fato daquela data e sobre o **espelho**; está superseded para esta coluna.
- **Regressão que defende:** duas, e a segunda é a que dói. (1) Alguém "simplifica" a célula de
  volta pra `{c.cpf}` — o caso morre na hora. (2) Alguém troca `redigirDigitos` por `maskCPF`
  achando que máscara esconde: **não esconde** — `maskCPF` insere pontos e hífen e entrega o
  documento inteiro, formatado. O assert exige o `•` e proíbe a sequência completa de dígitos, então
  distingue os dois. O caso também cobre o colaborador **sem** PIS, porque PIS ausente é acionável
  (o AFD da Portaria 671/2021 é chaveado por PIS — sem ele não se importa marcação) e um `—` mudo
  esconderia isso do gestor.
- **O que este caso NÃO é:** controle de acesso. Quem não pode ver o dado não deve **receber** o
  dado do backend; isto reduz exposição acidental de tela, nada mais. O controle real é permissão,
  como [W] fixou em 2026-08-21 — e essa parte não mudou.
- **Status: 🧪 teste cita o UC, sem veredito de lane.**

---

## UC-COLIDX-02 · Busca que não casa ninguém devolve lista vazia, não a lista inteira · `must`

- **Persona:** gestor de RH conferindo se um colaborador já foi configurado. Se a busca sem resultado
  devolvesse a lista inteira, ele concluiria "está cadastrado" olhando o primeiro nome que aparece.
- **Aceite:** Dado que existem colaboradores configurados no meu empregador · Quando busco por um termo
  que não casa com matrícula, nome nem CPF de ninguém · Então a lista volta **vazia** — e não o mesmo
  conjunto que eu veria sem buscar.
- **Teste:** `ColaboradorContratoTest.php` — `UC-COLIDX-02`.
- **Contrato:** charter §Goals (*"Busca com debounce (350ms) por matrícula, nome ou CPF"* +
  *"Empty states distintos para 'sem cadastro' e 'busca sem resultado'"*) — os dois empty states só
  fazem sentido se a busca de fato filtra.
- **Regressão que defende:** um `when()` mal montado (condição que nunca é verdadeira, ou `$search`
  lido de chave errada) faz a busca virar decoração: a tela aceita o texto, mostra a lista inteira e
  o operador não percebe. O caso compara os **dois** totais — com e sem busca — porque afirmar só
  "veio vazio" passaria também num cenário em que a lista está vazia por outro motivo.
- **Status: 🧪 verde no CT 100, sem veredito de lane.**
