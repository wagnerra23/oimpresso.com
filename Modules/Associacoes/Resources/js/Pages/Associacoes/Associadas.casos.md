---
id: modules-associacoes-pages-associacoes-associadas-casos
casos: Associações · Associadas · /associacoes/associadas
irmaos: Associadas.charter.md (lei) · Associadas.tsx (tela)
tecnica: Caso de uso = narrativa de quem cadastra + critério de aceite verificável (Dado/Quando/Então)
por_que: fibromialgia é dado de saúde (LGPD art. 11) — cadastrar sem aceite, mostrar celular inteiro ou vazar associada de uma associação para outra é dano real a quem a rede existe para proteger.
owner: wagner
last_run: "2026-11-13"
last_run_ci: "_pendente_ — o trio nasce junto com o código da onda 4. O veredito entra quando a lane verticais-pest (MySQL) rodar AssociadasContratoTest."
related_us: [US-ASSOC-003]
---

# Casos de Uso & Aceite — Associações · Associadas (`/associacoes/associadas`)

> **Âncora:** ADR 0451 D3 (dados), D4 (quem acessa) e D5 (consentimento, sem CPF). Padrão de tela PT-01 Lista. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-ASSOCI-00 | item "Associadas" no menu, mesma regra da rota | must | `AssociadasContratoTest` |
| UC-ASSOCI-01 | cadastro com contato novo, aceites na versão da config, sem duplicar celular | must | `AssociadasContratoTest` |
| UC-ASSOCI-02 | empresa 99 não vê nem cadastra associada da 98 | must | `AssociadasContratoTest` |
| UC-ASSOCI-03 | representante só vê e cadastra na associação dele | must | `AssociadasContratoTest` |
| UC-ASSOCI-04 | celular mascarado; nome para a diretoria só com aceite da rede | must | `AssociadasContratoTest` |
| UC-ASSOCI-05 | sem o aceite obrigatório não há cadastro | must | `AssociadasContratoTest` |
| UC-ASSOCI-06 | desligar não apaga; consentimento só revoga | must | `AssociadasContratoTest` |
| UC-ASSOCI-07 | gravar exige `associacoes.editar` | must | `AssociadasContratoTest` |

---

## UC-ASSOCI-00 · Chegar à tela pelo menu `[T0]` · `must`
- **Persona:** representante de uma associação abrindo o sistema.
- **Aceite:** Dado usuário com `associacoes.access` numa empresa com o pacote `associacoes_module` · Quando o menu é montado · Então o dropdown "Associações" tem o item que leva a `/associacoes/associadas`. Sem o pacote, o item não aparece.
- **Regressão que defende:** tela que responde 200 e ninguém alcança.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociadasContratoTest.php`
- **Status: 🧪**

## UC-ASSOCI-01 · Cadastrar com contato novo e aceite `[T0]` · `must`
- **Persona:** representante cadastrando uma associada que chegou no encontro.
- **Aceite:** Dado nome, celular e cidade em "Novo contato" e os aceites marcados · Quando cadastra · Então nasce um contato `customer` da empresa pelo `ContactUtil`, a associada ligada a ele e à associação escolhida, e um consentimento por finalidade marcada, na versão da `config('associacoes.consentimento_versoes')` — nunca a versão vinda do request. Celular já usado na empresa (comparando só os dígitos) é recusado e nada nasce.
- **Regressão que defende:** contato duplicado parte o histórico da associada; versão de aceite forjada pelo cliente.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociadasContratoTest.php`
- **Status: 🧪**

## UC-ASSOCI-02 · Isolamento entre empresas `[T0]` · `must`
- **Persona:** usuário de outra empresa com o mesmo módulo.
- **Aceite:** Dado uma associada da empresa 98 · Quando um usuário da 99 abre a lista, cadastra usando associação ou contato da 98, ou tenta desligá-la · Então ela não aparece, o cadastro é recusado na validação e o desligamento responde 404.
- **Regressão que defende:** vazamento Tier 0 (ADR 0093).
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociadasContratoTest.php`
- **Status: 🧪**

## UC-ASSOCI-03 · Representante vê só a associação dele `[T0]` · `must`
- **Persona:** representante da associação A, sem `associacoes.rede`.
- **Aceite:** Dado associadas nas associações A e B · Quando o representante de A abre a lista · Então vê só as de A, o filtro de associação só oferece A, cadastrar em B responde 403 e desligar uma associada de B responde 404.
- **Regressão que defende:** uma associação enxergando os membros da outra (ADR 0451 D4).
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociadasContratoTest.php`
- **Status: 🧪**

## UC-ASSOCI-04 · Dado pessoal mascarado `[T0]` · `must`
- **Persona:** diretoria da rede olhando a lista.
- **Aceite:** Dado uma associada · Quando a lista é carregada · Então o celular vem do servidor mascarado (DDD, primeiro dígito e os 2 últimos) e o número inteiro não aparece em nenhuma prop. O nome só vem para a diretoria se a associada tem o aceite `visivel_rede` vigente; o representante da associação vê o nome sempre.
- **Regressão que defende:** lista exportável com celular de pessoas com doença crônica; nome exposto sem o aceite que o ADR 0451 D5 exige.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociadasContratoTest.php`
- **Status: 🧪**

## UC-ASSOCI-05 · Sem aceite obrigatório não há cadastro · `must`
- **Persona:** representante; a pessoa não quis aceitar.
- **Aceite:** Dado o aceite "Fazer parte da associação" desmarcado · Quando tenta cadastrar · Então o servidor recusa com mensagem no campo e nenhuma associada nasce.
- **Regressão que defende:** dado de saúde guardado sem base legal.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociadasContratoTest.php`
- **Status: 🧪**

## UC-ASSOCI-06 · Desligar e revogar, nunca apagar · `must`
- **Persona:** representante registrando a saída de uma associada.
- **Aceite:** Dado uma associada ativa · Quando desliga com uma data · Então a situação vira `desligada` com essa data; repetir não muda a data do primeiro desligamento. Revogar um aceite preenche `revoked_at` e ele deixa de valer; o consentimento não se apaga nem pelo Model nem por SQL direto (trigger).
- **Regressão que defende:** perder a prova de que houve consentimento.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociadasContratoTest.php`
- **Status: 🧪**

## UC-ASSOCI-07 · Gravar exige permissão de edição · `must`
- **Persona:** membro da diretoria só com leitura.
- **Aceite:** Dado `associacoes.access` sem `associacoes.editar` · Quando abre a lista, tenta cadastrar, desligar ou buscar contato · Então vê a lista, e as três ações respondem 403.
- **Regressão que defende:** quem só consulta alterando cadastro.
- **Teste:** `Modules/Associacoes/Tests/Feature/AssociadasContratoTest.php`
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** O app da associada (onda 6) mostra os aceites e permite revogar, usando o mesmo `Consentimentos`.
- **[BACKLOG]** A busca de contato mostra contatos da empresa que ainda não são associadas a qualquer representante; restringir a busca por associação depende de decisão [W].

## Trilha do tempo
- 2026-11-13 · [CC] trio carimbado por `criar-tela.mjs` e preenchido com a onda 4 da ADR 0451 (US-ASSOC-003).
