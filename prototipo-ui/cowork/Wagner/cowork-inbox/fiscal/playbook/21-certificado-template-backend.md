---
sessao: "21"
titulo: Configurar pelo certificado — leitura do CNPJ da empresa + template sugerido (backend)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 11d035d368ea (2026-10-06)
prefixo: Modules/NfeBrasil/Services/<EmpresaFiscalLookupService novo> · Modules/NfeBrasil/Services/Tributacao/TributacaoTemplateService.php (sugerir · aplicar exige ncm_default) · Modules/NfeBrasil/Http/Controllers/<endpoint read-only> · Tests/Feature/<novos>
nao_toca: SefazConsultaCadastroService::consultar (contrato da ADR 0186 é estável: só CHAMAR) · CertificadoService::carregarParaSefazComFallback (ordem da chain imutável) · resources/js/
depende: — (vaga 1). Toca `TributacaoTemplateService`: não rodar em paralelo com outra thread que mexa nele.
decisao: _DECISOES-W-2026-10-06.md (D-SUPORTE · configuração inicial)
implementa: UC-NFTR-14 · UC-NFTR-15 · UC-NFTR-16 · UC-NFTR-17 · R-NFE-028
us: US-NFE-TPL-001 (templates L1; o próprio service já prevê "L2 = wizard 5 perguntas") · ADR 0186 (reuso, sem mudar invariante) · auditoria 2026-05 bug #3 (templates não gravam `ncm_default`)
---
# 21 · Configurar pelo certificado (backend)

## O que já existe e só se reusa
- `CertificadoService::validar` lê o CNPJ do certificado.
- `SefazConsultaCadastroService::consultar($cnpj, $uf, $businessId)` (ADR 0186, **irrevogável**) devolve IE, situação, `regime_apuracao` e endereço, com o contrato fixo de 13 campos. Hoje é usado para **cliente**; aqui é chamado para o **CNPJ da própria empresa**. Não muda invariante; só um consumidor novo.
- O lookup BrasilAPI que o `ClienteLookupController` já usa (CNAE principal e secundários, `simples_optante`). Conferir no turno o nome do método e reusar.
- `TributacaoTemplateService::listar/buscar/aplicar` com os 11 templates de `Resources/templates/*.php`.

## Escopo
1. **Leitura (read-only):** um endpoint que, para o business da sessão, devolve `{campo, valor, fonte}` para CNPJ, razão social, UF, IE, situação, CNAEs e regime. Faz SEFAZ e BrasilAPI **em paralelo** e aplica a autoridade da ADR 0186 (IE e situação: SEFAZ · Simples e CNAE: BrasilAPI). Regime divergente entre as fontes volta como `{divergente: true, opcoes: [...]}`, **sem escolher**. **Não grava nada.**
2. **Sugestão:** `TributacaoTemplateService::sugerir(regime, uf, cnaes)` ordena os templates por aderência (regime + UF + CNAE) e devolve os valores de cada um.
3. **Aplicar:** passa a **exigir `ncm_default`** com 8 dígitos e ≠ `00000000` (o bug #3). Continua preservando as regras NCM (UC-NFTR-03) e passa a gravar `activity('nfe.tributacao')->log('template.aplicado')`, a lacuna registrada no `Index.charter.md` (US-NFE-062).

## Casos de uso que esta thread implementa
Formato do `main`. **Números provisórios:** confirmar o próximo livre no turno.

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| UC-NFTR-14 | A leitura pelo certificado não grava configuração | `must` `[T0]` `[fiscal]` | `Index.casos.md` |
| UC-NFTR-15 | Template sugerido casa regime + UF + CNAE e mostra os valores antes | `must` `[fiscal]` | `Index.casos.md` |
| UC-NFTR-16 | Fontes divergentes não são resolvidas sozinhas | `must` `[fiscal]` | `Index.casos.md` |
| UC-NFTR-17 | Aplicar template exige NCM padrão válido e registra quem aplicou | `must` `[fiscal]` | `Index.casos.md` |
| R-NFE-028 | A consulta do próprio CNPJ segue a chain e a autoridade da ADR 0186 | `must` `[T0]` | SPEC NfeBrasil |

### UC-NFTR-14 · A leitura pelo certificado não grava configuração · `must` `[T0]` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** empresa nova que acabou de enviar o certificado
- **Aceite:** Dado um business com certificado válido · Quando chamo a leitura · Então recebo os campos com fonte e **nenhuma** linha muda em `nfe_business_configs`, `nfe_fiscal_rules` e `business`. Controle positivo: a leitura de outro business não retorna dados deste (e vice-versa).
- **Teste:** `EmpresaFiscalLookupTest` — `UC-NFTR-14 · leitura pelo certificado não grava`
- **Contrato:** `Index.charter.md` ("❌ Auto-aplicar template sem clique") · ADR 0093
- **Regressão que defende:** pré-preencher virando aplicar em silêncio.

### UC-NFTR-15 · Template sugerido casa regime + UF + CNAE e mostra os valores antes · `must` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** gráfica no Simples em SP
- **Aceite:** Dado regime Simples, UF SP e CNAE 1813-0/01 · Quando peço sugestão · Então o 1º template casa os três e cada um vem com os valores que aplicaria (CFOP, CSOSN/CST, alíquotas). Controle positivo: trocar o regime para presumido muda o 1º sugerido.
- **Teste:** `TributacaoTemplateSugestaoTest` — `UC-NFTR-15 · sugestão por regime, UF e CNAE`
- **Contrato:** US-NFE-TPL-001 · `ConfigDefault.charter` ("mostra valores antes de aplicar")
- **Regressão que defende:** sugerir template de outro regime/UF.

### UC-NFTR-16 · Fontes divergentes não são resolvidas sozinhas · `must` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** empresa cuja BrasilAPI diz Simples e a SEFAZ diz outro regime
- **Aceite:** Dado BrasilAPI `simples_optante=true` e SEFAZ `regime_apuracao` ≠ Simples · Quando leio · Então o campo regime volta `divergente` com as duas opções e **sem** valor escolhido. Controle positivo: com as fontes concordando, o regime volta preenchido.
- **Teste:** `EmpresaFiscalLookupTest` — `UC-NFTR-16 · divergência não é resolvida sozinha`
- **Contrato:** ADR 0186 (autoridade por campo) · D-SUPORTE (o risco é de quem aplica)
- **Regressão que defende:** o sistema escolher o regime errado e o cliente emitir meses assim.

### UC-NFTR-17 · Aplicar template exige NCM padrão válido e registra quem aplicou · `must` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** qualquer empresa aplicando template
- **Aceite:** Dado `ncm_default` vazio ou `00000000` · Quando aplico · Então 422 e a config não muda. Com NCM válido · Então aplica, preserva as regras NCM (UC-NFTR-03) e grava `activity` `template.aplicado` com o autor. Controle positivo: re-aplicar o mesmo template continua idempotente.
- **Teste:** `TributacaoTemplateAplicarTest` — `UC-NFTR-17 · aplicar exige NCM padrão e loga`
- **Contrato:** auditoria 2026-05 bug #3 · US-NFE-062 (único caminho sem `activity`)
- **Regressão que defende:** empresa "configurada" que não consegue emitir a primeira nota.

### R-NFE-028 · A consulta do próprio CNPJ segue a chain e a autoridade da ADR 0186 · `must` `[T0]`
- **Destino:** SPEC NfeBrasil
- **Persona:** —
- **Aceite:** Dado certificado primário do business · Quando leio · Então a consulta SEFAZ usa o primário (o institucional não é tentado), e IE/situação vêm da SEFAZ mesmo que a BrasilAPI traga algo. UF fora de `config/fiscal.php` → campos de SEFAZ vêm `uf_unsupported` pra digitar. Controle positivo: os guardas `SefazInvariantesAntiRegressaoTest` continuam verdes.
- **Teste:** `EmpresaFiscalLookupTest` — `R-NFE-028 · chain e autoridade da ADR 0186`
- **Contrato:** ADR 0186 invariantes 1, 5, 7 e 11
- **Regressão que defende:** um consumidor novo contornando a ordem da chain ou a autoridade por campo.

## Prova
Os 5 testes verdes · `SefazInvariantesAntiRegressaoTest` e `SefazConsultaCadastroChainTest` seguem verdes · `TributacaoIndexContratoTest` (UC-NFTR-03) segue verde.

Terminou: `_saida-21.md`. Pare.
