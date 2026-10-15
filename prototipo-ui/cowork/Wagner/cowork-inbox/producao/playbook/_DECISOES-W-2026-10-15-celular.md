# Produção no celular — decisões [W] e sugestões · 2026-10-15

Fonte: chat do projeto "Mobile app structure review". Telas: `Mobile Producao.dc.html` (A1–A11, I1–I9, V1, T1, L1).
Complementa `cowork-inbox/producao/playbook/_DECISOES-W-2026-10-15.md`.

| id | pergunta | resposta | estado | efeito |
|---|---|---|---|---|
| D-CUSTO | Custo por máquina ou por pessoa | Máquina e veículo (impressão, transporte): custo-hora da máquina. Resto da produção: custo-hora da pessoa. | decidido [W] | Posto ganha `tipo_custo: maquina\|pessoa`. Apontamento de máquina não ocupa a pessoa: operador pode ter máquina rodando + etapa manual (A1). Muda a thread 02. |
| D-PONTO | Apontamento × ponto (Portaria 671) | Ponto é fonte legal, nunca alterado pelo apontamento. Horas apontadas por pessoa ≤ presença no ponto. Diferença > 30 min/dia vira pendência do gestor. Saída no ponto fecha o cronômetro de pessoa (A7); sem saída, fecha no fim do turno. | sugestão | UC-PCV-22 ganha a regra da saída do ponto. Apontamento offline é permitido (não é registro legal). |
| D-APARELHO | Celular pessoal ou terminal | Os dois, configurável por empresa. Terminal (T1) padrão nas máquinas; celular pessoal opcional, aceito pelo colaborador, no Wi-Fi da empresa. | sugestão ([W]: "acho que vai ter os dois") | Modo terminal da PWA: preso à máquina, troca de operador por crachá QR ou PIN. |
| D-ETIQUETA | Tipo de etiqueta | Configurável: térmica 100×50 com QR por etapa (empresa grande) ou A4 com QR por OS + lista de etapas (pequena). QR de OS abre "escolha sua etapa". | decidido [W] | Thread 05 com 2 layouts. |
| D-TERCEIRO | Serviço de terceiros | Instalador terceirizado entra por link com código, sem conta (I9). Custo da OS = valor combinado; protocolo concluído gera conta a pagar. | decidido [W] | UC novo. Mesmo mecanismo do D-PROVA. |
| D-FOTO | Prazo das fotos | Guardar sem prazo. Sugestão: comprimir no celular (1600 px, ~300 KB) e manter original só 90 dias. | decidido [W] · ajuste sugerido | |
| D-ASSINATURA | Força da prova | Máxima: traço + código no WhatsApp do cliente + geolocalização, hora, aparelho + hash SHA-256 do PDF + fotos carimbadas. Sem sinal: código vai depois por link. | decidido [W] | I5/I6. |
| D-PILOTO-METRICA | Critério do piloto biz=1 | 4 semanas. Passa se ≥ 90% das etapas com apontamento · ≤ 5% fechadas pelo turno · previsto × real dentro de 20% · 100% das instalações com protocolo. | sugestão | Thread 11 ganha critério de saída. |

## UC a criar no `Producao.casos.md`
A9 mídia pelo RIP · A10 imprimir junto (estende UC-PCV-09) · A11 retalho · V1 visita técnica · I4 não foi possível instalar · I7 ferramentas e veículo · I8 pesquisa de satisfação · I9 instalador terceirizado · T1 modo terminal · D-CUSTO (dois cronômetros).

## Pendente com [W]
- I4: "cliente ausente" vira taxa de nova visita?
- Confirmar as 3 sugestões (D-PONTO, D-APARELHO, D-PILOTO-METRICA).
