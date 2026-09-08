---
id: resources-js-pages-ponto-colaboradores-edit-casos
casos: Configuração de ponto de um colaborador · /ponto/colaboradores/{id}/editar
irmaos: Edit.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §6.5 (contrato) · Index.casos.md (a tela irmã)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é aqui que se decide quem entra na apuração CLT e com qual período de vínculo — um período impossível contamina todo cálculo de jornada a jusante.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "2 UC rodados por mim no CT 100 (container oimpresso-staging, MySQL real), NAO em CI. Codigo identico ao main no container (mesma medicao declarada no Index.casos.md irmao). CT100 != CI: base persiste entre runs — verde la e CANDIDATURA, nao veredito. Nota de fixture, porque ela quase produziu um achado falso: a 1a sonda pegou o colaborador do meu business com DB::table (que ignora soft delete) e recebeu 404 na tela; a causa NAO era isolamento, era `deleted_at` — o Model aplica SoftDeletes e o registro escolhido estava apagado. Com um colaborador VIVO a mesma rota devolveu 200. Por isso o caso do 404 usa colaborador de OUTRO empregador criado na hora, e nao um id qualquer."
---

# Casos de Uso & Aceite — Configuração de ponto do colaborador

> **Âncora:** `CU-PONTO-12` do [SDD §6.5](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md)
> + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) + o
> `Edit.charter.md` ao lado. Os UC derivam do **contrato**, nunca do `Edit.tsx` — teste derivado do
> código é tautológico ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-06-05).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-COLEDT-01 | Abrir a configuração de colaborador de outro empregador → 404 | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `ColaboradorContratoTest` | 🧪 verde no CT 100, sem veredito de lane |
| UC-COLEDT-02 | Desligamento anterior à admissão é recusado | must | charter §Automation hooks + CLT (período de vínculo) | `ColaboradorContratoTest` | 🧪 verde no CT 100, sem veredito de lane |

**[BACKLOG]** (contrato em uma fonte só, ou pergunta ainda aberta ao [W] — não vira UC sem teste):

- `[BACKLOG]` O charter pergunta em §Pendências se alternar `controla_ponto` / `usa_banco_horas` tem
  efeito **retroativo** sobre apuração já gravada. Hoje o §Anti-hooks só *afirma* que o efeito é
  prospectivo, com um "(confirmar com backend)" ao lado — ou seja, é inferência pendente, e o
  [SDD §6.6](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) é explícito em não
  promover inferência de charter a lei. Testar isso agora seria carimbar uma resposta que ninguém deu.
- `[BACKLOG]` A tela grava `escala_atual_id` validando só `exists:ponto_escalas,id` — sem cláusula de
  empregador na regra. O que **de fato** impede escolher escala alheia é o select vir escopado
  (medido: a escala de outro empregador não aparece no payload do formulário). É o mesmo formato de
  defesa única do `UC-COLIDX-01`, num campo diferente; virar UC exige um caso que poste o id alheio
  direto na rota, e isso é trabalho próprio.
- `[BACKLOG]` Nenhum caso cobre o efeito de salvar sobre a **apuração já existente** do colaborador
  (o `ApuracaoDia` não é recalculado). É comportamento de serviço, não de tela, e o dono do tema é o
  `ApuracaoService`.

---

## UC-COLEDT-01 · Abrir a configuração de colaborador de outro empregador → 404 · `must` `[T0]`

- **Persona:** plataforma multi-tenant. Matrícula, CPF, PIS e datas de admissão/desligamento são dado
  pessoal de trabalhador (LGPD Art. 7º) — e a rota é adivinhável, porque o id é sequencial.
- **Aceite:** Dado o id de um colaborador que pertence a **outro** empregador · Quando acesso
  `/ponto/colaboradores/{id}/editar` · Então recebo **404** — nunca 200 com o dado dele, nunca 500.
- **Teste:** `Modules/Ponto/Tests/Feature/ColaboradorContratoTest.php` — `UC-COLEDT-01`.
- **Contrato:** `CU-PONTO-12` (*"abrir por id um recurso de outro business responde 404"*) ·
  US-PONTO-007 · charter §Non-Goals (*"Não edita colaborador de outro business"*).
- **Regressão que defende:** `ColaboradorController@edit` usa `Colaborador::findOrFail($id)` **sem**
  filtro de empregador escrito à mão — quem devolve o 404 é o global scope, que faz o `findOrFail`
  não achar. É defesa única, e é a mesma família que o [SDD §9 D-5](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md)
  nomeia em outros três handlers. Se o trait sair do model, esta rota passa a servir o cadastro de
  qualquer trabalhador da instalação, e nenhum outro teste do módulo cobre este caminho.
- **Por que o assert é o status, e não a ausência de uma chave:** existe mais de uma correção legítima
  (escopar a query, uma policy, um middleware). O contrato é *"não me deixa ver"*, e 404 é a forma que
  o `CU-PONTO-12` fixa — deliberadamente 404 e não 403, porque 403 confirmaria que o id existe.
- **Status: 🧪 verde no CT 100, sem veredito de lane.**

---

## UC-COLEDT-02 · Desligamento anterior à admissão é recusado · `must`

- **Persona:** gestor de RH corrigindo cadastro. Um vínculo que termina antes de começar não é um
  detalhe cosmético: admissão e desligamento delimitam a janela em que a jornada é apurada, e uma
  janela invertida produz período negativo em tudo que consome essas datas.
- **Aceite:** Dado um colaborador do meu empregador · Quando salvo com desligamento **anterior** à
  admissão · Então a gravação é **recusada** e o operador recebe o erro apontando o campo de
  desligamento — e o cadastro permanece como estava.
- **Teste:** `ColaboradorContratoTest.php` — `UC-COLEDT-02`.
- **Contrato:** charter §Automation hooks (*"validação server-side (`desligamento > admissao`, `escala
  exists`)"*) · charter §Goals (*"admissão (obrigatória)"*) · CLT (o vínculo tem início e, se houver
  fim, ele é posterior).
- **Regressão que defende:** validação de data cruzada é o primeiro tipo de regra que some num
  refactor de `FormRequest` — some sem erro, sem log e sem ninguém notar, porque o caminho feliz
  continua funcionando. O caso afirma **as duas metades**: que a resposta não é sucesso **e** que o
  erro chega ao operador no campo certo. Só a primeira passaria também num 500.
- **Status: 🧪 verde no CT 100, sem veredito de lane.**
