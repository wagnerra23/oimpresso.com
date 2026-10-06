---
sessao: "16"
titulo: Bloqueio com saída de 1 clique + conserto cadastral
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b (2026-10-06)
prefixo: Modules/NfeBrasil/Services/NfeService.php (mensagem do RuntimeException de NCM padrão) · tela de origem do erro (confirmar no turno) · Pages/Fiscal/_lib/sefaz-actions.ts · Tests
nao_toca: MotorTributarioService.php (NCM continua obrigatório)
depende: 17 · 14 · 13 (a medição diz qual bloqueio priorizar)
decisao: _DECISOES-W-2026-10-06.md
implementa: R-NFE-022 · R-NFE-022b
us: US-NFE-007 · `sefaz-actions.ts` (determinístico, sem IA)
---
# 16 · Bloqueio com saída de 1 clique + conserto cadastral

**Corrigido 13:45:** o bloqueio que o usuário vê hoje é o `RuntimeException` de **NCM padrão ausente** (`NfeService` · `emitirParaTransaction`/`emitirParaInvoice`), não o `NcmObrigatorioException` do motor (inalcançável, porque a emissão sempre passa o padrão). Saída de 1 clique: abrir a escolha do NCM padrão já no campo, e depois da 17, aceitar a sugestão do produto ou usar o padrão e marcar revisão. "NCM da categoria" **não existe** no código e não será criado. **Emitir sem NCM não é opção.** Para cStat de causa cadastral (110 · 539), a ação "corrigir e reenviar" abre o cadastro já no campo e reenvia ao salvar.

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| R-NFE-022 | Bloqueio por NCM padrão sempre oferece a saída e nunca emite sem NCM | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-022b | Rejeição cadastral (cStat 110/539): corrigir o cadastro e retransmitir na mesma ação | `must` `[fiscal]` | SPEC NfeBrasil |

### R-NFE-022 · Bloqueio por NCM padrão sempre oferece a saída e nunca emite sem NCM · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** Larissa no balcão
- **Aceite:** Dado empresa sem NCM padrão válido · Quando tenta emitir · Então a emissão **não** acontece e o erro devolve a ação "escolher NCM padrão" (rota + campo), não só texto. Controle positivo: com NCM padrão válido, emite.
- **Teste:** `NfeBloqueioComSaidaTest` — `R-NFE-022 · bloqueio de NCM com saída e sem emissão`
- **Contrato:** D-SUPORTE · NF-e exige NCM
- **Regressão que defende:** bloqueio sem caminho vira chamado.

### R-NFE-022b · Rejeição cadastral (cStat 110/539): corrigir o cadastro e retransmitir na mesma ação · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** Larissa com nota rejeitada por IE ou CNPJ
- **Aceite:** Dado nota rejeitada com cStat 110 ou 539 · Quando uso "corrigir e reenviar" · Então abre o cadastro do cliente no campo da receita (`sefaz-actions.ts`) e, ao salvar, chama `NfeService::retransmitir` (número novo; a antiga vira `inutilizada`, sem hard-delete). Controle positivo: cStat sem receita mostra só a dica genérica e **não** oferece reenvio automático.
- **Teste:** `ConsertoCadastralTest` — `R-NFE-022b · corrigir e retransmitir 110/539`
- **Contrato:** US-FISCAL-014 · `sefaz-actions.ts` (determinístico) · SINIEF 07/2005 Art. 14
- **Regressão que defende:** conserto "automático" mudando dado fiscal sem receita conhecida.

## Prova
Teste: produto sem NCM e sem NCM de categoria continua bloqueado · com NCM de categoria aplica e cria pendência · reenviar reusa a chave/numeração conforme a regra do emissor.

Antes de editar: confirmar no turno os símbolos citados (C12). Terminou: `_saida-16.md` com o sha e a saída dos testes. Pare.
