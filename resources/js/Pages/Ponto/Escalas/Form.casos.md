---
id: resources-js-pages-ponto-escalas-form-casos
casos: Criar/editar padrão de jornada (escala) · /ponto/escalas/{create,{id}/edit}
irmaos: Form.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §10 Onda 1 (varredura de atributo fantasma)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a escala é o padrão contra o qual toda a jornada é apurada — atraso, HE e falta saem da comparação com ela.
owner: wagner
last_run: "2026-08-02"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Formulário de escala

> **Âncora:** charter §Mission/§Goals + **achado medido nesta sessão** (2026-08-02), não
> inferência. O SDD do módulo **não tem `CU-PONTO-*` para Escalas** — o §10 Onda 1 pede
> literalmente *"varrer as 4 famílias de tela não auditadas (Dashboard, Colaboradores,
> **Escalas**, Configuracoes) atrás de outros atributos fantasma"* e declara *"não fiz nesta
> corrida"*. **Esta é aquela varredura**, e ela achou dois defeitos — os UC-01 e UC-02 abaixo
> nascem deles, com recibo. Nenhum UC aqui foi derivado do `.tsx`.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: fica vermelha
> visível, não bloqueia merge (SDD §8.1).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-ESCFORM-01 | Os horários dos turnos configurados aparecem na edição | must | charter §Mission + achado 2026-08-02 | `EscalaFormContratoTest` | 🧪 **vermelho ESPERADO** (predição) |
| UC-ESCFORM-02 | Salvar a edição da escala persiste os campos | must | charter §Goals + achado 2026-08-02 | `EscalaFormContratoTest` | 🧪 **vermelho ESPERADO** (predição) |
| UC-ESCFORM-03 | Escala de outro empregador → 404 | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `EscalaFormContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` CRUD de turnos por dia da semana — o charter marca como **Non-Goal explícito**
  hoje (*"a UI de turnos é read-only (iteração futura)"*) e a Pendência §live pede a [W]
  *"definir escopo da UI de CRUD de turnos"*. Enquanto for Non-Goal, não vira UC.
- `[BACKLOG]` Carga diária/semanal coerente com os turnos cadastrados (a soma dos turnos bate
  com `carga_semanal_minutos`?). É `[V0]` — minuto de jornada é valor, e a apuração compara
  contra esses números. Exige o protocolo da REGRA MESTRE antes de virar assert.
- `[BACKLOG]` `EscalaTurno` é a **única** das 10 entities sem `HasBusinessScope` direto (usa
  `BelongsToBusinessViaParent`) — SDD §9 D-6. Fechar é migration + decisão [W].

---

## UC-ESCFORM-01 · Os horários dos turnos configurados aparecem na edição · `must`

- **Persona:** gestor abrindo uma escala já cadastrada para conferir os turnos antes de
  atribuí-la a um colaborador. A escala é o padrão contra o qual atraso, HE e falta são
  apurados — conferir os horários é a razão de existir da tela.
- **Aceite:** Dado um turno gravado com `hora_entrada`, `hora_almoco_inicio`,
  `hora_almoco_fim` e `hora_saida` · Quando abro `/ponto/escalas/{id}/edit` · Então a tela
  apresenta **os horários gravados** — não vazios.
- **Teste:** `Modules/Ponto/Tests/Feature/EscalaFormContratoTest.php` — `UC-ESCFORM-01`.
- **Contrato:** charter §Mission (*"exibe (read-only por enquanto) os turnos por dia da semana
  **já configurados**"*) · §Goals (*"no edit, listagem read-only dos turnos"*).
- **Achado que motiva (medido 2026-08-02, varredura contada):**
  `EscalaController@edit` monta o turno lendo **4 atributos que não existem**:

  | o controller lê | coluna real (migration `…000003_create_ponto_escalas_table`) |
  |---|---|
  | `$t->entrada` | `hora_entrada` |
  | `$t->almoco_inicio` | `hora_almoco_inicio` |
  | `$t->almoco_fim` | `hora_almoco_fim` |
  | `$t->saida` | `hora_saida` |

  O `$fillable` de `EscalaTurno` lista as 4 formas **com** prefixo `hora_`; a busca por
  accessor/`Attribute`/`appends` na entity retorna **0**. Logo os 4 resolvem `null`, e o
  `.tsx` os tipa `string | null` renderizando `{t.entrada ?? '—'}` — **a tela mostra "—" em
  todos os horários, de toda escala, sempre**.
- **Regressão que defende:** é a **3ª instância** do padrão que o SDD §9 nomeou em D-1
  (`tem_divergencia`) e D-8 (`linhas_criadas`): *o controller lê um atributo que o modelo não
  tem, e a linguagem esconde*. Aqui quem esconde é o `?? '—'` do frontend — o mesmo papel do
  `?? 0` do D-8. O padrão apareceu agora em **3 de 8 famílias** de tela do módulo.
- **Nota de escrita:** o assert é sobre **comportamento** (*"o horário gravado aparece"*), não
  sobre a chave literal do payload — há mais de uma correção legítima (renomear no controller,
  criar accessor, ou expor via `$appends`) e um assert por chave reprovaria as outras
  arbitrariamente. Mesma disciplina que o SDD usou no CU-PONTO-02.
- **PREDIÇÃO: vermelho.** O veredito real vem da lane, não desta leitura (G-7).
- **Status: 🧪 vermelho ESPERADO.**

---

## UC-ESCFORM-02 · Salvar a edição da escala persiste os campos · `must`

- **Persona:** o mesmo gestor, corrigindo a carga diária de uma escala depois de um acordo.
- **Aceite:** Dado uma escala do meu business · Quando envio `PUT /ponto/escalas/{id}` com
  nome e carga novos · Então a alteração **fica gravada** e a tela não quebra.
- **Teste:** `EscalaFormContratoTest.php` — `UC-ESCFORM-02`.
- **Contrato:** charter §Goals (*"Formulário dual: cria (`POST`) ou edita
  (`PUT /ponto/escalas/{id}`)"*) · §Automation hooks (*"Submit via `useForm.post`/`.put`
  conforme modo"*).
- **Achado que motiva (medido 2026-08-02):** `EscalaController@update` recebe
  **`Illuminate\Http\Request`** (import na linha 7) e chama `$request->validated()`.
  Esse método **só existe em `FormRequest`** — medido no vendor:
  `grep -c "function validated" Illuminate/Http/Request.php` → **0**;
  `Illuminate/Foundation/Http/FormRequest.php:365` → é lá que ele mora. `Request` usa
  `Macroable`, então uma macro poderia supri-lo — varredura em `app/`, `Modules/`,
  `bootstrap/`, `config/` (ambas as formas de aspas) retornou **exit 1, zero ocorrências**.
  Logo a chamada lança `BadMethodCallException`.
  **Contraste no mesmo módulo:** `IntercorrenciaController@store/@update` recebem
  `IntercorrenciaRequest` (um `FormRequest`) e chamam `validated()` **legitimamente** — o
  módulo tem o padrão certo ao lado do errado.
- **Regressão que defende:** o `store()` (criar) funciona, então a tela **parece** boa: o erro
  só aparece na **edição**, que é o caminho menos exercitado. Sem teste, um 500 nessa rota
  passa despercebido até o gestor tentar corrigir uma escala.
- **Nota de escrita:** o assert lê o **estado persistido** depois do PUT, em vez de afirmar um
  status HTTP específico — assim ele continua válido qualquer que seja a correção (trocar por
  FormRequest, usar `$request->validate([...])`, ou outra).
- **PREDIÇÃO: vermelho.** O veredito real vem da lane (G-7).
- **Status: 🧪 vermelho ESPERADO.**

---

## UC-ESCFORM-03 · Escala de outro empregador → 404 · `must` `[T0]`

- **Persona:** plataforma multi-tenant. A escala carrega a jornada praticada pelo time —
  informação de organização interna de outro empregador.
- **Aceite:** Dado o id de uma escala de **outro** business · Quando acesso
  `/ponto/escalas/{id}/edit` · Então recebo **404** — nunca 200 com dado, nunca 500.
- **Teste:** `EscalaFormContratoTest.php` — `UC-ESCFORM-03`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** `EscalaController@edit` usa `Escala::with('turnos')->findOrFail($id)`
  — **sem** `where('business_id')`. A defesa é **só** o global scope `HasBusinessScope` da
  entity, o mesmo padrão de defesa-única que o SDD §9 D-5 registra nos handlers de decisão.
  Este UC transforma a única defesa em defesa **observada**.
- **Nota de teste:** biz=1 (WR2 interno) vs stub biz=99 — **nunca biz=4**
  ([ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)). O stub
  do business precisa **existir**: sem ele o INSERT morre na FK e o caso não exerce isolamento
  nenhum (medido na run 30778424885).
- **Status: 🧪 sem veredito.**
