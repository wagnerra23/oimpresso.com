---
sessao: "19b"
titulo: Saída da thread 19 (adendo) — smoke em produção do drawer de Manutenções
dono: "[CL]"
medido_em: 2026-09-30
base_medida: 237d99644 (deploy "Deploy to Hostinger" success 18:08Z; contém o #8264 be06c442f e o #8260)
complementa: "_saida-19.md (2026-09-30), §Validação item 2 — toque ≥44px declarado para o smoke pós-merge"
---

# 19b · Smoke em produção: toque ≥44px confirmado; dois achados de acessibilidade

Adendo datado. O `_saida-19.md` fica intacto.

## Onde
`oimpresso.com`, WR2 Sistemas (biz=1, tenant de smoke), viewport 1280. Nenhum formulário foi
enviado: produção tem dado real, e o smoke é só leitura. O deploy foi confirmado pela
ancestralidade do merge do #8264 (`be06c442f`) no SHA deployado, não por listagem de deploys.

## Resultado

| Cenário | Resultado |
|---|---|
| `/asset/asset-maintenance` | Page `Patrimonio/Manutencoes`; vazio: "Nenhuma manutenção registrada … O envio começa na tela de Bens." — a WR2 não tem manutenção |
| `/asset/asset-maintenance/create` **carregado direto** | Page completa com o drawer "Enviar pra manutenção" aberto |
| Campos | Bem · Situação · Prioridade · Nota da manutenção · Anexos |
| **Toque ≥44px** (item 2 da validação) | **confirmado em runtime**: Bem, Situação, Prioridade, Anexos, Cancelar e Registrar = 44px; Nota = 68px |
| `create?asset_id=999999999` | 200; o id inexistente **não** é pré-selecionado ("Escolha o bem") — a trava de gravação é a do #8260 |
| `/asset/asset-maintenance/999999999/edit` | **404** |
| **Cancelar** | fecha e volta pra `/asset/asset-maintenance` |
| `type=submit` implícito no Cancelar | inofensivo: **não há `<form>`** no drawer (0 forms), então o botão não envia nada — verificado pela estrutura, sem clicar antes de saber |

## Achados (não corrigidos aqui)
1. **Foco depois do Cancelar cai no `BODY`.** Mesmo defeito que Alocações teve e que o #8292
   corrigiu lá. Aqui há um agravante: a lista de Manutenções não tem botão de cadastrar, então
   não existe trigger na tela para onde devolver o foco. O conserto precisa decidir o alvo (por
   exemplo o título da página ou o botão da linha, quando houver linha).
2. **X de fechar do drawer mede 16×16px**, abaixo do alvo de toque de 44px. Vem do componente
   compartilhado `resources/js/Components/ui/sheet.tsx:76` (`SheetPrimitive.Close`, classe `absolute top-4 right-4`), então
   vale também para os drawers de Alocações e de Bens. Mexer nele é mudança de componente do DS,
   com efeito em todas as telas que usam `Sheet` — decisão [W], não conserto de passagem.

## O que NÃO foi exercitado em produção
Editar uma manutenção existente, a lista de anexos já enviados e o selo de garantia: a WR2 não
tem manutenção nem bem com garantia. Cobertura existente: Pest UC-MANU-05 (#8260) e UC-MANU-06
(#8264) em `ManutencoesContratoTest.php`, e o vitest `patrimonio-manutencoes-drawer.test.tsx`.
