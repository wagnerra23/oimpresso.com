# Fisio — protótipo (canvas Prescreve Pro)

Telas do módulo Fisio desenhadas no Artefato de design **Prescreve Pro**
(`https://claude.ai/artifact/2qtFppKCQKgwGpPDtW2C5N`). Copiadas para o git em 2026-11-04,
da versão `1793811641-3c35` do Artefato: os 40 `project/*.dc.html` e o `canvas.json`.

A partir daqui a fonte é esta pasta ([ADR 0438](../../../../../memory/decisions/0438-artefato-substitui-cowork-como-superficie-do-prototipo.md) D1).
Mudança de tela entra por PR aqui; o Artefato é republicado a partir destes arquivos, nunca o contrário.

## O que mudou na cópia

Só o runtime. Os arquivos do Artefato carregam `./support.js`; aqui apontam para
`../../../../design-system/support.js`, porque o `cowork-ssot-guard` não aceita uma segunda cópia
do mesmo arquivo. O resto é byte a byte o que estava no Artefato.

## Para ver

Sirva `prototipo-ui/` (não esta pasta, senão o `support.js` não resolve) e abra
`cowork/Wagner/prototipos/fisio/Main.dc.html`. Exemplo:
`python -m http.server 5598 --directory prototipo-ui`.

## Telas

| Clínica | Paciente (celular) |
|---|---|
| Main (Painel), Agenda, Avaliacao, Evolucao, EvolucaoDitada, Prescricao, Contraindicacao, Biblioteca, BuscaBiblioteca, Protocolos, Questionarios, Laudo, LaudoBiopsicossocial, Teleconsulta, Financeiro, FiscalFila, Planos, Onboarding, PacienteNovo, CameraLiberacao, ConfigAgendamentoOnline, ConfigWhatsApp | PacienteAcesso, PacienteHoje, PacienteCheckin, PacienteCrise, PacienteNota, PacienteJornada, PacienteProgresso, PacienteConsultas, PacienteAgendar, PacienteAgendaConfirmar, PacienteSerieConfirmar, PacienteQuestionario, PacientePreAnamnese, PacienteCamera, PacienteCameraPreparo, PacienteCameraClasse2, PacienteConfig, PacienteInstalar |

`ComparativoAgendamento` é pesquisa de mercado, não tela.

## Charters que esperavam esta pasta

Os charters do Fisio declaram `related_prototype: n/a` porque o canvas não estava no repo.
Ligá-los a estes arquivos é um passo separado:

- `Painel.charter.md` → `Main.dc.html`
- `Biblioteca/Index.charter.md` → `Biblioteca.dc.html`
- `Public/Portal.charter.md` → `PacienteAcesso`, `PacienteHoje`, `PacienteCheckin`, `PacienteCrise`, `PacienteNota`

## Telas geradas no git depois da cópia

- `LaudoBiopsicossocial.dc.html` (2026-11-11, US-FISIO-060): cartão do laudo biopsicossocial na ficha do paciente, campo por campo do modelo da SES-SC (Deliberação 500/CIB/2026). Não veio do Artefato: o Code desenhou ancorado no DS porque faltava fonte visual (ADR 0282 §0.1). O PDF desse laudo segue o padrão do `Laudo.dc.html`.
