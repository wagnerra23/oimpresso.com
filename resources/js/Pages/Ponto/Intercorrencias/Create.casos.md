---
id: resources-js-pages-ponto-intercorrencias-create-casos
casos: Registrar intercorrência (atestado/abono/ajuste) · /ponto/intercorrencias/create
irmaos: Create.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F4 + §6.2 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a porta pela qual o mundo real entra na apuração — atestado, esquecimento de marcação, HE autorizada.
owner: wagner
last_run: "2026-09-08"
last_run_ci: "69 de 69 UC do Ponto com veredito pass no manifesto scripts/casos-test-results.json (fonte: test-results/pest-ponto-junit.xml). Lane PHP / Pest (Ponto - MySQL) run 34215745965 em main (sha dced5fd3d8, 2026-09-08T10:32Z): 302 passed - 1 skipped - 1009 assertions, coherent=true, provou_algo=true. Li ASSERTIONS, nao a conclusion: 1009 > 0 prova que a suite rodou e nao caiu no skip-as-pass da lane (LC-13). O unico skipped da run nao e UC (o coletor trata skip como nao-pass, e os 69 vieram pass). A lane e ADVISORY: reprova e visivel, nao bloqueia merge."
---

# Casos de Uso & Aceite — Registrar intercorrência

> **Âncora:** `CU-PONTO-05` (§6.2) e `CU-PONTO-12` (§6.5) do
> [SDD](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + **US-PONTO-003**
> (estados canon) · fluxo **F4** (§5.3). Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: fica vermelha
> visível, não bloqueia merge (SDD §8.1).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-INTCRE-01 | Registrar uma intercorrência cria o rascunho | must | `CU-PONTO-05` + US-PONTO-003 | `IntercorrenciaContratoTest` | ✅ verde na lane (predição de vermelho caducou) |
| UC-INTCRE-02 | A lista de colaboradores traz só os do meu empregador | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `IntercorrenciaContratoTest` | ✅ verde na lane |
| UC-INTCRE-03 | A tela não promete enviar ao RH ao salvar | should | charter Anti-hooks + US-PONTO-003 | `IntercorrenciaContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` **Anexar comprovante a uma intercorrência** (`D-INTERC-ANEXO`, [W] 2026-09-14, ata
  bloco 4 linha 43; emenda E4 da thread 27). Aceite proposto: dado um atestado em PDF/JPG/PNG,
  quando o solicitante anexa ao rascunho, então o arquivo vai para disco **privado** e fica
  vinculado à intercorrência; só quem tem permissão de aprovação consegue baixá-lo; nenhum caminho
  ou URL do arquivo aparece em log (atestado é dado de saúde, LGPD Art. 11). **Não é UC ainda:** em
  2026-09-28 o `Create.tsx` não tem campo de arquivo (só a coluna `anexo_path` existe, desde a
  migration de 2026-04-18). Um teste agora seria vermelho por construção. Vira UC (próximo id
  `UC-INTCRE-04`; a thread o chamou de `UC-PONT-INT-07`) no PR que construir o anexo, junto do
  teste que o cita.
- `[BACKLOG]` A classificação por IA (`POST /ponto/intercorrencias-ai/classify`) **sugere, nunca
  decide** — o estado só muda por ação humana (SDD §5.3 F4). Vira UC quando houver um contrato
  escrito sobre o que a sugestão pode e não pode fazer; hoje afirmar isso em teste seria derivar
  do código.
- `[BACKLOG]` **Validação sem escopo de tenant:** `IntercorrenciaRequest` valida
  `colaborador_config_id` com `exists:ponto_colaborador_config,id` — **sem** `where business_id`.
  Em tese permite anexar intercorrência a colaborador de outro empregador. **Não virou UC porque
  não consegui exercer o caminho:** o `store()` não chega a gravar (ver UC-INTCRE-01), então o
  teste não distinguiria "barrou por tenant" de "quebrou antes". Vira UC quando o UC-01 estiver
  verde — aí o caminho fica observável. Registrado como **hipótese medida na leitura**, não como
  achado ([§5 2026-07-15](../../../../memory/proibicoes.md)).

---

## UC-INTCRE-01 · Registrar uma intercorrência cria o rascunho · `must`

- **Persona:** RH lançando o atestado que o colaborador entregou hoje. É a entrada do fluxo de
  aprovação — sem ela, a justificativa nunca chega à apuração e o dia vira falta.
- **Aceite:** Dado um colaborador do meu business · Quando envio o formulário com tipo, data e
  justificativa válidos · Então a intercorrência **fica gravada** no estado `RASCUNHO`.
- **Teste:** `Modules/Ponto/Tests/Feature/IntercorrenciaContratoTest.php` — `UC-INTCRE-01`.
- **Contrato:** `CU-PONTO-05` (SDD §6.2, *"a intercorrência nasce `RASCUNHO`"*) · US-PONTO-003
  (estados canon) · F4 (§5.3).
- **Achado que motiva (medido 2026-08-02 — cadeia completa, varredura contada):**
  o `business_id` **nunca é atribuído** no caminho de criação:

  | elo | o que faz com `business_id` |
  |---|---|
  | `IntercorrenciaRequest::rules()` | não declara a chave → `validated()` não a devolve |
  | `IntercorrenciaController@store` | passa `$request->validated()` cru |
  | `IntercorrenciaService::criar()` | seta `codigo`, `solicitante_id`, `estado` — **não** `business_id` |
  | `Intercorrencia::boot()::creating` | só gera o UUID |
  | trait `HasBusinessScope` | só adiciona o **scope de leitura**; não injeta no `creating` |
  | `observe()` no módulo | **0 ocorrências** (exit 1) |
  | migration | `business_id` **NOT NULL** + FK para `business`, **sem default** |

  O próprio Service **denuncia** que sabia: a linha do span usa `(int) ($dados['business_id'] ?? 0)`
  — o `?? 0` só existe porque a chave pode não vir.
- **Regressão que defende:** este é o `[must]` mais direto do módulo — **a feature não funciona**.
  E o SDD §5.3 F4 descreve o fluxo como se funcionasse, o SPEC marca US-PONTO-003 como
  implementada: a documentação e o código discordam, e **nenhum teste exercitava o `store()` por
  HTTP** para desempatar. É exatamente o buraco que o trio existe para fechar.
- **Nota de escrita:** o assert lê o **estado persistido** (a intercorrência existe, no estado
  `RASCUNHO`), não um status HTTP — assim vale para qualquer correção (injetar no Service,
  no `creating`, ou adicionar a chave ao FormRequest).
- **PREDIÇÃO: vermelho.** O veredito real vem da lane, não desta leitura (G-7).
- **Status: 🧪 vermelho ESPERADO.**

---

## UC-INTCRE-02 · A lista de colaboradores traz só os do meu empregador · `must` `[T0]`

- **Persona:** plataforma multi-tenant. O seletor de colaborador é onde nome e matrícula de
  pessoas aparecem — é a superfície mais direta de vazamento de PII do módulo.
- **Aceite:** Dado um colaborador ativo em **outro** business · Quando abro
  `/ponto/intercorrencias/create` · Então ele **não** aparece entre os selecionáveis; os do meu,
  sim.
- **Teste:** `IntercorrenciaContratoTest.php` — `UC-INTCRE-02`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) · LGPD Art. 7º.
- **Regressão que defende:** o filtro do `create()` é triplo — `business_id`, `controla_ponto` e
  `desligamento IS NULL`. Os dois últimos são regra de negócio (só quem bate ponto pode ter
  intercorrência); o primeiro é Tier 0. Um refactor que "simplifique" a query mexendo nos três
  juntos derruba o isolamento sem sintoma visível na tela do próprio business.
- **Nota de teste:** biz=1 vs stub biz=99 — **nunca biz=4**
  ([ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)). O stub
  precisa existir: sem ele o INSERT morre na FK e o caso não exerce isolamento (medido na run
  30778424885).
- **Status: 🧪 sem veredito.**

---

## UC-INTCRE-03 · A tela não promete enviar ao RH ao salvar · `should`

- **Persona:** RH lançando a intercorrência. Se a tela diz que o registro "será submetido ao RH",
  quem salva acha que já enviou — e a intercorrência fica parada em rascunho sem ninguém saber.
- **Aceite:** Dado o formulário de registro · Quando leio a descrição do card "Dados da
  ocorrência" · Então ela diz que salvar cria um **rascunho** e que submeter é feito depois, no
  detalhe — e **não** diz que os campos serão submetidos ao RH.
- **Teste:** `IntercorrenciaContratoTest.php` — `UC-INTCRE-03`.
- **Contrato:** [`Create.charter.md`](Create.charter.md) Anti-hooks — *"Salvar não dispara
  aprovação nem notifica o RH (submeter é ação separada no `Show`)"* · US-PONTO-003 (estados
  canon). Redação a partir do protótipo `prototipo-ui/cowork/Wagner/ponto-telas.jsx`
  (`FormIntercorrencia`).
- **Achado que motiva (medido 2026-09-28, `origin/main` @ e4289e688):** a descrição dizia
  *"Confirme/ajuste os campos. Eles serão submetidos ao RH para aprovação."* — o oposto do
  charter. Registro em `memory/requisitos/Ponto/intercorrencias-create-gap.md`.
- **Nota de escrita:** a copy vive no `.tsx` (não é prop Inertia), então o teste lê o fonte da
  página com as quebras de linha normalizadas. Controle positivo feito antes: o mesmo regex
  acusa a versão antiga e aceita a nova.
- **Status: 🧪 sem veredito.**
