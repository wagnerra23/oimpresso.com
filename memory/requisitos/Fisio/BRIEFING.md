---
id: requisitos-fisio-briefing
module: Fisio
status: em-construcao
status_nota: "F1, agenda, pacote, fila fiscal e laudo em produção; falta conteúdo clínico revisado, decisões de valor e as etapas F2, F4 e lançamento"
updated_at: "2026-11-23"
owner: W
related_adrs: ["0437-modulo-fisio-vertical-fisioterapia"]
---

# BRIEFING — `Fisio`

> **Função única:** resumo executivo e índice. O BRIEFING aponta para os donos; não recopia SCOPE, SPEC, tópicos ou contratos de tela.
> **Contrato:** `scripts/memory-schemas/briefing.schema.json`.

## O que é

Vertical de fisioterapia dentro do oimpresso: o profissional monta o programa de exercícios domiciliares, o paciente recebe pelo WhatsApp e faz pelo portal (link + PIN), e o profissional vê quem fez, quem está com dor e quem vai abandonar.

## Estado atual

Medido em 2026-11-23 sobre `main` `ed8598c` (SPEC, telas e lane `Pest (Verticais · MySQL)`, run 44962970620). Decisão: [ADR 0437](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md). Responsável técnica do conteúdo clínico: Dra. Dejeane.

O que está no ar, por bloco (detalhe e âncoras no [`SPEC.md`](SPEC.md)):

- **F1 · programa e constância:** esqueleto, schema, biblioteca, pacientes e prontuário, editor de programa e protocolos, portal do paciente (link + PIN, consentimentos, check-in), jogo de constância, envio e lembrete por WhatsApp, painel e evolução (US-FISIO-002 a 010).
- **Primeira configuração e pré-anamnese:** onboarding de 5 passos e pré-anamnese no portal (US-FISIO-050, 051).
- **Agenda:** schema, regras (conflito, encaixe, bloqueio, falta, cancelamento tardio, lista de espera), tela da clínica, configuração do agendamento pelo app e agenda no portal do paciente com marcar, confirmar, remarcar e "Cheguei" (US-FISIO-013 a 016).
- **Pacote e fiscal:** pacote de sessões com saldo vindo de ledger sem valor em dinheiro, aba "Pacote e cobrança" com comprovante em PDF, ligar a venda do núcleo ao pacote e estorno quando a venda é cancelada, fila fiscal do dia com NFS-e e Receita Saúde (US-FISIO-020, 021, 023; 022 parcial).
- **Fibromialgia e associação:** dados sociais opcionais, relatório agregado para a associação, laudo biopsicossocial no modelo SES-SC, exames e receitas por foto com transcrição conferida, medicamentos em uso, atestado e estudo com consentimento (US-FISIO-060 a 063, 060 e 063 em construção).

O que falta:

- **Fechar com a Dra. Dejeane:** textos dos questionários (US-FISIO-011), posição e equipamento dos exercícios globais da biblioteca, e o fechamento da F1 (US-FISIO-012).
- **Terminar:** laudo biopsicossocial (US-FISIO-060) e exames por foto (US-FISIO-063); na agenda do portal, a nota 1–5 e o confirmar pelo link da mensagem não têm caso próprio.
- **Decisão de [W]:** renovar pacote pela venda do núcleo (US-FISIO-022, mexe em valor), planos do Fisio no Superadmin (US-FISIO-025).
- **Bloqueado fora do Fisio:** lote do Receita Saúde sem o layout oficial (US-FISIO-024); NFS-e sem IBS/CBS, tela de venda sem contato e produto pré-preenchidos, e mensalidade do RecurringBilling que não vira título no Financeiro (lacunas do núcleo no SPEC).
- **Etapas não iniciadas:** câmera no aparelho ([F2](etapas/03-f2-camera.md)), IA de documentação e mais questionários ([F4](etapas/05-f4-ia-questionarios.md)) e lançamento com piloto ([etapa 06](etapas/06-lancamento.md)).

## Portas canônicas

- **Herança geral (componentes/layouts/templates compartilhados):** [`../_Geral/BRIEFING.md`](../_Geral/BRIEFING.md)
- **Fronteira/ownership:** [`SCOPE.md`](SCOPE.md)
- **Requisitos:** [`SPEC.md`](SPEC.md)
- **Execução da F1:** [`HANDOFF-F1.md`](HANDOFF-F1.md)
- **Especificações por etapa:** [`etapas/`](etapas/)
- **Telas:** `Modules/Fisio/Resources/js/Pages/Fisio/` + charters/casos ao lado das Pages

- **Biblioteca — mídia do exercício próprio (2026-10-18):** a clínica anexa foto ou vídeo MP4 curto ao exercício dela, pelo backbone `Modules/Arquivos`, visível só para a empresa (UC-FBI-05..07, `FisioBibliotecaMidiaTest`).

## Decisões e riscos que exigem atenção

- WhatsApp com canal escolhido pela clínica (`meta_cloud` recomendado, `whatsmeow` com aceite de risco e mitigações, ou `wa.me` manual) — [ADR 0437 §13.1](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md)
- App do paciente instalável com agendamento no estilo do AppBarber Cliente (link + PIN segue valendo; sem promoção, venda extra nem nota pública) — [ADR 0437 §13.7](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md) e [etapa F3 A14–A21](etapas/04-f3-operacao.md)
- Linha da Anvisa (o que vira SaMD) — [ADR 0437 §5](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md)
- Decisões ainda abertas — [ADR 0437 §14](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md)
- Fisioterapeuta responsável pelo paciente (2026-10-18): a transferência fica no `activity_log`, sem tabela própria; pacientes anteriores à coluna ficam sem responsável até alguém atribuir — [SPEC US-FISIO-005](SPEC.md)
- Biblioteca com favoritos por profissional e filtros de posição e equipamento (listas fechadas em `Modules/Fisio/Config/config.php`, de `etapas/01-biblioteca.md` §3.3) — casos UC-FBI-08..10 em [`Biblioteca/Index.casos.md`](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.casos.md). Os exercícios globais ainda não declaram posição nem equipamento: isso vem com o seed revisado pela Dra. Dejeane.

## Próxima ação verificável

- US-FISIO-011 (textos dos questionários) e seed da biblioteca revisados pela Dra. Dejeane — dono: [W] — evidência: PR com a revisão anotada e `FisioQuestionarioTest` verde; depois fecha a US-FISIO-012.

## Regra de manutenção

1. Mudou requisito: altere `SPEC.md`/charter/casos.
2. Mudou um tema: altere um único `topicos/<slug>.md` e ajuste só a linha-resumo deste índice.
3. Conteúdo clínico (biblioteca, protocolos, contraindicações, sinais de alerta, textos ao paciente) só muda com revisão da responsável técnica anotada no PR.
4. Componente/layout/template compartilhado não é copiado para o módulo: consulte `_Geral`.
