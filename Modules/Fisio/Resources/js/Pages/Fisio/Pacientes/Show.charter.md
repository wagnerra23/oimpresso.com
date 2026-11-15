---
id: modules-fisio-pages-fisio-pacientes-show-charter
page: /fisio/pacientes/{id}
component: Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.tsx
related_prototype: "n/a (o canvas Prescreve Pro não desenha o editor de programa; herda o padrão Cockpit)"
owner: wagner
status: draft
last_validated: "2026-10-18"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/pacientes/{id}
  rota_nome: fisio.pacientes.show
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/pacientes/{id} (DRAFT · perfil clínico, prontuário e programa)

> Nasce na US-FISIO-005 (ADR 0437 §4 e §5; Res. COFFITO 414/2012). Casos: [`Show.casos.md`](Show.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar e editar o perfil clínico do paciente, manter o prontuário (avaliação, evolução e adendo, cada um carimbado com usuário, CREFITO e hora do servidor) e montar o programa de exercícios que o paciente faz em casa.

## Goals — Features (faz)

- Editar queixa, objetivos, dias por semana e marcadores — UC-FPS-02.
- Assinar avaliação (campos mínimos da Res. 414) e evolução, com carimbo do servidor — UC-FPS-03.
- Corrigir por adendo apontando o original, que não muda — UC-FPS-04, UC-FPS-05.
- Bloquear o paciente sem apagar nada — UC-FPS-07.
- Montar e editar o programa de exercícios: criar com instrução de parada, limiar e modo do alerta de dor, versão de crise; adicionar da biblioteca com a dose padrão, ajustar dose, reordenar, remover — UC-FPS-06, UC-FPS-08, UC-FPS-09 (US-FISIO-006).
- Avisar contraindicação ao prescrever (marcador do paciente × exercício), sem bloquear: o profissional mantém com justificativa, que vai ao prontuário; o paciente não vê o aviso (ADR 0437 §13.2) — UC-FPS-10.
- Aplicar protocolo-modelo ao programa e copiar o modelo global para a clínica (lógica visível, "A decisão clínica é do fisioterapeuta").
- Mostrar o fisioterapeuta responsável e transferir o paciente para outro profissional da empresa, com o histórico das transferências — UC-FPS-30..32.
- Exportar o prontuário em PDF (identificação, perfil clínico e todos os registros assinados em ordem cronológica, com carimbo; rodapé com empresa, emissão e "Página X de Y"); cada exportação fica no `activity_log` sem PII — UC-FPS-19, UC-FPS-20. Exportar exige só `fisio.access`.
- Registrar questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) com pontuação do servidor e ver o histórico — UC-FPS-13..16 (US-FISIO-011). Mostra o número e a referência; não interpreta (SaMD, ADR 0437 §5).
- Registrar os questionários de fibromialgia (critérios ACR 2016, FIQR, PGIC, sentar e levantar em 30 s) só quando liberados em `fisio.instrumentos_liberados` (desligado em produção até a licença) — UC-FPS-39..41 (etapa 07, P-03). Os critérios ACR aparecem como informação para o profissional, com o aviso de que não é diagnóstico; a versão do instrumento fica registrada com a resposta. Grupo "Fibromialgia · pesquisa" no protótipo `prototipo-ui/cowork/Wagner/prototipos/fisio/Questionarios.dc.html`.
- Montar o laudo de avaliação biopsicossocial para fibromialgia no modelo da SES-SC (US-FISIO-060; Deliberação 500/CIB/2026): identificação, matriz IFBr-M com o total calculado (menor valor de cada domínio), fatores contextuais com o apoio da fisioterapia, conclusão marcada pela equipe, equipe com um médico e outra área, local e data; finalizar congela, corrigir abre nova versão; PDF no layout oficial. Avisa que a emissão é da Atenção Primária e a carteira segue o fluxo da FCEE. Protótipo `prototipo-ui/cowork/Wagner/prototipos/fisio/LaudoBiopsicossocial.dc.html`. Contrato: [`laudo-biopsicossocial.casos.md`](../../../../../../../memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md) — UC-FBP-01..15.
- Anexar exames e receitas que o paciente trouxe (US-FISIO-063): foto ou PDF com tipo, data e observação, guardado pelo `Modules/Arquivos` e aberto só por rota que confere a empresa; o documento faz parte do prontuário, tirar da lista pede motivo e grava quem tirou. Protótipo `prototipo-ui/cowork/Wagner/prototipos/fisio/ExamesReceitas.dc.html`. Lista de medicamentos em uso estruturada (nome, dose, frequência, desde, até), sem conferir interação nem dose; o texto livre da avaliação continua. Transcrição da foto pela IA, desligada até a clínica ligar: a IA só copia o que está escrito (receita: data, médico, CRM, medicamentos; exame: data, nome e conclusão literal), cada campo é sugestão até alguém confirmar ou corrigir, e quem conferiu fica gravado. O que o paciente envia pelo portal aparece como pendente, com "Aceitar no prontuário" (acertando tipo e data) e "Não aceitar" com motivo; nada entra sozinho. Contrato: [`exames-e-receitas.casos.md`](../../../../../../../memory/requisitos/Fisio/_telas/exames-e-receitas.casos.md) — UC-FER-01..20.

## Non-Goals — Features (NÃO faz)

- ❌ Editar ou apagar registro assinado (não existe rota; o banco recusa).
- ❌ Envio do programa pelo WhatsApp (US-FISIO-009).
- ❌ Avisar o profissional que recebeu o paciente, ou comparar resultados entre profissionais (fora do escopo do responsável).
- ❌ A IA interpretar o exame ou a receita: dizer se um resultado é normal ou alterado, classificar, resumir com opinião ou sugerir conduta. Ela só copia o que está escrito e nada vai ao prontuário sem alguém conferir (pedido [W] 2026-11-11; ADR 0437 §5; proposta da ADR 0450).
- ❌ Responder a conclusão do laudo biopsicossocial (impedimento de longo prazo, barreiras, equiparação) a partir da pontuação, ou escrever que o paciente é pessoa com deficiência: quem conclui é a equipe (pedido [W] 2026-11-11; ADI 5033647-81.2025.8.24.0000; ADR 0437 §5).
- ❌ PDF do programa de exercícios (vem depois da US-FISIO-006); o PDF daqui é só o prontuário.

## Refs

- ADR 0437 · `memory/requisitos/Fisio/HANDOFF-F1.md` (T-05) · Res. COFFITO 414/2012 · Lei 13.787/2018 · UI-0013
