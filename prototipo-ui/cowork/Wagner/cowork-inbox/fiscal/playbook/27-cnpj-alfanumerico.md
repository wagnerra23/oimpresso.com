---
sessao: "27"
titulo: CNPJ alfanumérico em cliente, fornecedor e XML
dono: "[CL]"
base: wagnerra23/oimpresso.com@main (2026-10-06)
prefixo: Modules/NfeBrasil (validadores, máscara, XML) · app/ (validação de cadastro) — **medir antes:** a busca por "alfanum" em `Modules/NfeBrasil` voltou vazia em 2026-10-06, o que não prova ausência
depende: — (pode rodar já)
decisao: _DECISOES-W-2026-10-06.md
---
# 27 · CNPJ alfanumérico em cliente, fornecedor e XML

Empresas abertas desde julho/2026 recebem CNPJ com letras. Cadastro ou XML que só aceita dígitos trava a emissão antes de chegar na SEFAZ. Cobrir: validação do dígito verificador novo, máscara no front, armazenamento como texto (sem cast numérico), chave de acesso e QR Code.

## Fontes (lidas pelo [CC] em 2026-10-06; o Code cita a lei literal no docblock)
- Fonte de mercado (weepulse, 2026): CNPJ com letras desde julho trava ambiente com validação antiga.

## Casos de uso
**Números provisórios:** confirmar o próximo livre no turno.

### R-NFE-033 · Aceita CNPJ alfanumérico válido e recusa o inválido · `must` `[T0]` `[fiscal]`
- **Destino:** `Index.casos.md` (NfeBrasil/Tributacao) ou SPEC do módulo
- **Aceite:** Dado um CNPJ alfanumérico válido de destinatário · Quando cadastro e emito · Então o XML leva o CNPJ como está e a nota é montada. DV errado → 422 com mensagem clara. Controle positivo: CNPJ só numérico continua válido.
- **Teste:** `CnpjAlfanumericoTest` — `R-NFE-033`
- **Contrato:** IN RFB 2.229/2024 (citar literal) · schema NF-e vigente
- **Regressão que defende:** cliente novo que o sistema não consegue cadastrar.

## Prova
Testes verdes com controle positivo · biz de teste conforme ADR 0358.

Terminou: `_saida-27.md`. Pare.
