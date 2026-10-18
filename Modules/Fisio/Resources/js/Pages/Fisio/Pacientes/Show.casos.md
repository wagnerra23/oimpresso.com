---
id: modules-fisio-pages-fisio-pacientes-show-casos
casos: Fisio · Ficha do paciente · /fisio/pacientes/{id}
irmaos: Show.charter.md (lei) · Show.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: a ficha é o prontuário legal (Res. COFFITO 414/2012, guarda de 20 anos pela Lei 13.787/2018) e o editor do programa que o paciente executa sozinho em casa. Editar evolução assinada, apagar paciente ou prescrever contra uma contraindicação sem aviso são falhas legais e clínicas.
owner: wagner
last_run: "2026-10-18"
last_run_ci: "_pendente_ — o trio nasce com a suíte TDD da F1 (antes do código). O veredito entra quando a lane MySQL rodar FisioProntuarioTest, FisioProgramaEditorTest, FisioPacienteCrudTest, FisioMultiTenantTest e FisioWhatsappJobTest."
related_us: []
---

# Casos de Uso & Aceite — Fisio · Ficha do paciente (`/fisio/pacientes/{id}`)

> **Âncora:** ADR 0437 §4 a §8 e handoff F1 (T-05, T-06, T-09). Padrão Cockpit + Apps Vinculados: abas Evolução, Programa, Prontuário, Questionários. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FPS-01 | ficha e escritas de outra clínica: 404, nada muda | must | `FisioMultiTenantTest` |
| UC-FPS-02 | editar queixa, objetivos, meta e marcadores | should | `FisioPacienteCrudTest` |
| UC-FPS-03 | registro carimbado; campos mínimos da Res. 414 | must | `FisioProntuarioTest` |
| UC-FPS-04 | registro assinado não aceita UPDATE/DELETE | must | `FisioProntuarioTest` |
| UC-FPS-05 | correção só por adendo com `amends_id` | must | `FisioProntuarioTest` |
| UC-FPS-06 | criar programa com instrução de parada | must | `FisioProgramaEditorTest` |
| UC-FPS-07 | excluir = bloquear; nada clínico é apagado | must | `FisioPacienteCrudTest`, `FisioModuloContratoTest` |
| UC-FPS-08 | adicionar item com dose padrão; editar dose | must | `FisioProgramaEditorTest` |
| UC-FPS-09 | reordenar e remover item | must | `FisioProgramaEditorTest` |
| UC-FPS-10 | alerta de contraindicação avisa e registra a confirmação | must | `FisioProgramaEditorTest` |
| UC-FPS-11 | enviar o programa pelo WhatsApp (fila, idempotente) | must | `FisioWhatsappJobTest` |
| UC-FPS-12 | sem envio sem módulo, sem consentimento, bloqueado | must | `FisioWhatsappJobTest` |
| UC-FPS-13 | questionário grava com a pontuação calculada no servidor | must | `FisioQuestionarioTest`, `PontuacaoQuestionarioTest` |
| UC-FPS-14 | bloqueado por licença, desconhecido ou incompleto é recusado | must | `FisioQuestionarioTest`, `PontuacaoQuestionarioTest` |
| UC-FPS-15 | questionário exige `fisio.editar`; outra clínica 404; bloqueado não recebe | must | `FisioQuestionarioTest` |
| UC-FPS-16 | histórico de questionários na ficha, deferido, só do paciente e da clínica | should | `FisioQuestionarioTest` |

---

## UC-FPS-01 · Ficha de outra clínica não abre `[T0]` · `must`
- **Persona:** invariante multi-tenant (ADR 0093).
- **Aceite:** Dado um paciente da empresa 98 · Quando um usuário da 99 abre, edita, bloqueia, registra evolução, mexe no programa ou envia pelo WhatsApp · Então tudo responde 404 e o registro da 98 fica intacto. Criar paciente com contact da 99 a partir da 98 é recusado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioMultiTenantTest.php`
- **Status: 🧪**

## UC-FPS-02 · Atualizar o perfil clínico · `should`
- **Aceite:** Quando o fisioterapeuta edita queixa, objetivos, sessões por semana e marcadores · Então os valores gravam com a mesma validação da criação.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacienteCrudTest.php`
- **Status: 🧪**

## UC-FPS-03 · Registro carimbado, com o mínimo da Res. 414 · `must`
- **Persona:** fisioterapeuta registrando avaliação ou evolução.
- **Aceite:** Quando assina um registro · Então ele grava `signed_by` (usuário logado), `signed_crefito` e `signed_at` (relógio do servidor) — nada disso vem do corpo. Sem CREFITO não assina. Avaliação sem queixa principal, história, exame físico, diagnóstico cinesiológico, objetivos ou plano de tratamento é recusada. `telemonitoramento` não é criável pela tela (só o portal gera).
- **Regressão que defende:** prontuário sem identificação do profissional; data retroativa forjada.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProntuarioTest.php`
- **Status: 🧪**

## UC-FPS-04 · Assinado não se edita `[must]`
- **Persona:** invariante legal.
- **Aceite:** Dado um registro assinado · Quando qualquer caminho tenta UPDATE ou DELETE (SQL direto, Eloquent, rota HTTP) · Então falha; a rota de edição não existe (405). A tabela mutável do paciente continua aceitando update (controle).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProntuarioTest.php`
- **Status: 🧪**

## UC-FPS-05 · Corrigir é acrescentar · `must`
- **Aceite:** Quando o fisioterapeuta corrige um registro · Então nasce um `adendo` com `amends_id` para o original, carimbado com a própria hora; o original fica igual e a linha do tempo mostra os dois. Adendo sem `amends_id`, ou apontando registro de outro paciente ou de outra clínica, é recusado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProntuarioTest.php`
- **Status: 🧪**

## UC-FPS-06 · Montar o programa · `must`
- **Aceite:** Quando o fisioterapeuta cria um programa · Então ele nasce ativo, `origin=manual`, `created_by` da sessão, limiar de dor padrão 7, e exige a instrução de parada escrita pelo profissional (é o único texto que o paciente vê em dia de dor alta). Novo programa ativo encerra o anterior.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProgramaEditorTest.php`
- **Status: 🧪**

## UC-FPS-07 · Excluir paciente é bloquear `[must]`
- **Persona:** invariante legal (Lei 13.787/2018, guarda de 20 anos).
- **Aceite:** Quando o fisioterapeuta "exclui" um paciente · Então só `blocked_at` é preenchido; paciente, prontuário, programas e check-ins ficam. Ele sai da lista de ativos, a ficha segue legível, o portal fecha. Excluir de novo não muda nada. Nenhum código do módulo usa `forceDelete` ou `truncate`.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacienteCrudTest.php` · `Modules/Fisio/Tests/Feature/FisioModuloContratoTest.php`
- **Status: 🧪**

## UC-FPS-08 · Adicionar exercício e ajustar a dose · `must`
- **Aceite:** Adicionar herda séries, repetições, sustentação e vezes por dia do exercício e vai para o fim · editar dose valida limites e não mexe em outro item · exercício próprio de outra clínica não entra.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProgramaEditorTest.php`
- **Status: 🧪**

## UC-FPS-09 · Reordenar e remover · `must`
- **Aceite:** Reordenar grava exatamente a ordem pedida; lista com item de outro programa ou incompleta é recusada e nada muda · remover recompacta as posições e não apaga check-in antigo que citou o item.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProgramaEditorTest.php`
- **Status: 🧪**

## UC-FPS-10 · Aviso de contraindicação · `must`
- **Persona:** fisioterapeuta prescrevendo para paciente com prótese de quadril.
- **Aceite:** Dado um paciente com marcador de condição · Quando adiciona exercício cujo `contraindication_flags` cruza com o marcador · Então recebe aviso (409 `contraindicacao` com as flags) e nada é gravado; confirmando, o item grava com quem e quando confirmou e quais flags. Sem cruzamento, entra direto sem confirmação. Aviso, não bloqueio — a conduta é do profissional.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProgramaEditorTest.php`
- **Status: 🧪**

## UC-FPS-11 · Enviar pelo WhatsApp · `must`
- **Aceite:** "Enviar" enfileira o job (202) com business e programa · o job manda link e PIN em duas mensagens, o PIN atrasado, e o link abre o portal · rodar duas vezes no mesmo dia não manda duas vezes.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioWhatsappJobTest.php`
- **Status: 🧪**

## UC-FPS-12 · Quando não enviar · `must`
- **Aceite:** Nenhuma mensagem para business sem o módulo, programa de outra clínica, paciente sem consentimento de WhatsApp (ou revogado), paciente bloqueado ou sem telefone.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioWhatsappJobTest.php`
- **Status: 🧪**

## UC-FPS-13 · Questionário pontuado pelo servidor · `must`
- **Persona:** fisioterapeuta aplicando EVA, Roland-Morris, PSFS, LEFS ou SPADI (ADR 0437 §8).
- **Aceite:** Quando registra as respostas de um questionário de uso livre · Então a resposta grava com `business_id` da sessão, a data/hora do servidor e a pontuação calculada pela regra publicada do instrumento (RMDQ = nº de "sim"; PSFS = média das atividades; LEFS = soma; SPADI = dor ÷50, incapacidade ÷80, total ÷130). `score` vindo do corpo é ignorado. O sistema mostra o número, não uma interpretação (SaMD, ADR 0437 §5).
- **Fonte:** estrutura de cada instrumento conforme as referências no catálogo (`CatalogoQuestionarios`). Enunciados da versão brasileira validada **pendentes** de conferência pela Dra. Dejeane — a tela pede a transcrição pelo número do item.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioQuestionarioTest.php` · `Modules/Fisio/Tests/Unit/PontuacaoQuestionarioTest.php`
- **Status: 🧪**

## UC-FPS-14 · Só os de uso livre, e completos · `must`
- **Aceite:** ODI, NDI, KOOS, QuickDASH/DASH e WOMAC (bloqueados por licença) e códigos desconhecidos respondem 422 em `instrumento`. Item faltando, a mais ou fora da faixa responde 422 no campo do item. Nada grava. Sem rateio de item faltante enquanto a regra de cada manual não for conferida.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioQuestionarioTest.php` · `Modules/Fisio/Tests/Unit/PontuacaoQuestionarioTest.php`
- **Status: 🧪**

## UC-FPS-15 · Quem pode registrar `[T0]` · `must`
- **Aceite:** Sem `fisio.editar` responde 403. Paciente de outra clínica responde 404 e nada grava lá. Paciente bloqueado não recebe questionário novo (422).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioQuestionarioTest.php`
- **Status: 🧪**

## UC-FPS-16 · Histórico na ficha · `should`
- **Aceite:** A ficha recebe o catálogo (com referências, sem os bloqueados) e o histórico como prop deferida, mais recente primeiro, só do paciente aberto e da clínica da sessão.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioQuestionarioTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Gráfico de dor na aba Evolução (a série chega na prop `serie_dor`, provada em UC-FPP-20; o render ainda sem teste).
- `[BACKLOG]` Protocolos-modelo da clínica aplicados ao programa (`origin=protocolo`) — fora desta suíte.
- `[BACKLOG]` Justificativa da contraindicação confirmada também como registro no prontuário (hoje fica no item: `contraindication_justification`, com quem, quando e quais marcadores; o registro carimbado exige CREFITO).
- `[BACKLOG]` "Enviar" o programa pelo WhatsApp: a rota existe e responde 501 até a US-FISIO-009 (UC-FPS-11).
- `[BACKLOG]` Enunciados literais da versão brasileira validada de EVA, RMDQ, PSFS, LEFS e SPADI na tela do questionário (aguarda conferência da Dra. Dejeane contra a publicação).

## Trilha do tempo
- 2026-10-18 · US-FISIO-006 — a seção Programa entra no `.tsx` (editor com dose, ordem, remoção e aviso de contraindicação que não bloqueia) para os casos UC-FPS-06, 08, 09 e 10; os UCs não mudaram e seguem derivados do HANDOFF F1 (T-06) e da ADR 0437 §13. Veredito: lane Verticais (`FisioProgramaEditorTest`).
- 2026-10-18 · US-FISIO-011 — questionários de uso livre viram UC-FPS-13..16 (estrutura e pontuação; enunciados pendentes).
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código; prontuário, bloqueio e contraindicação entram pela atualização de escopo do mesmo dia.
- 2026-10-18 · US-FISIO-005 — a tela `.tsx` nasce (#9546) para os casos UC-FPS-02..05 e 07; os UCs não mudaram e seguem derivados do HANDOFF F1, não do `.tsx`. Veredito: lane Verticais.
