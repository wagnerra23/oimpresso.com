---
id: resources-js-pages-ponto-colaboradores-index-casos
casos: Lista de colaboradores com controle de ponto · /ponto/colaboradores
irmaos: Index.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §6.5 (contrato) · Edit.casos.md (a tela irmã)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a porta de entrada para configurar quem entra na apuração CLT — e é uma tela de BUSCA, onde o filtro de empregador é a coisa mais fácil de perder sem ninguém notar.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "2 UC rodados por mim no CT 100 (container oimpresso-staging, MySQL real oimpresso_staging), NAO em CI. O codigo desta tela e das entidades que ela toca esta IDENTICO ao main no container (medido: git diff c1abe9548..origin/main em Http/Controllers/ColaboradorController.php + Entities/Colaborador.php + Http/routes.php = vazio, com controle positivo em memory/proibicoes.md dando 157 linhas). CT100 != CI (proibicoes §Ambiente): la a base PERSISTE entre runs, aqui cada lane semeia do zero — logo verde no CT100 e CANDIDATURA, nao veredito; quem decide e a lane PHP / Pest (Ponto · MySQL), que e REQUIRED e estava verde nas ultimas 7 runs do main em 2026-09-04. Achado de metodo desta sessao, registrado porque quase virou achado falso: a 1a sonda do UC-COLIDX-01 procurava a MATRICULA do colaborador alheio no corpo da resposta e deu SIM (VAZA) — falso-positivo, porque o controller devolve o proprio termo buscado na prop `search`, entao a string aparece por eco. A prova limpa e buscar pelo CPF do alheio e procurar a MATRICULA dele (nao ha eco possivel): deu `nao`. O SQL confirmou (toSql): o grupo do global scope entra com AND depois do grupo do controller."
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
| UC-COLIDX-01 | Buscar por matrícula ou CPF não alcança colaborador de outro empregador | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `ColaboradorContratoTest` | 🧪 verde no CT 100, sem veredito de lane |
| UC-COLIDX-02 | Busca que não casa ninguém devolve lista vazia, não a lista inteira | must | charter §Goals (busca + empty state de "busca sem resultado") | `ColaboradorContratoTest` | 🧪 verde no CT 100, sem veredito de lane |

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
- `[BACKLOG]` A coluna de CPF aparece inteira na lista. O charter pergunta em §Pendências se deve ser
  mascarada; a decisão de [W] para o espelho foi **não mascarar** (*"pode deixar os dados sim é um
  ERP"*, 2026-08-21 — o controle é por permissão de acesso, não por ocultação). Fica registrado que a
  mesma razão se aplica aqui, mas a pergunta do charter é de [W], não minha.
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
