---
sessao: "21"
titulo: Saída — Configurar pelo certificado (backend)
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main a8e0624504
---
# _saida-21 · Configurar pelo certificado (backend)

## 1 · Feito
- `Modules/NfeBrasil/Services/EmpresaFiscalLookupService.php` (novo): `ler($businessId)` devolve `{campo, valor, fonte}` para CNPJ · UF · razão social · IE · situação · CNAEs · regime. CNPJ do certificado ativo (`nfe_certificados.cnpj_titular`), com o `business.tax_number_1` como reserva (`fonte: cadastro`). Chama `SefazConsultaCadastroService::consultar` sem mudar nada nele. Autoridade da ADR 0186 inv. 5: IE e situação só da SEFAZ; razão social da SEFAZ com a BrasilAPI de reserva; CNAE e Simples/MEI da BrasilAPI. Regime em `mei · simples · normal`; fontes em conflito voltam `divergente: true` + `opcoes`, **sem valor**. **Não grava nada.**
- `Modules/NfeBrasil/Http/Controllers/EmpresaFiscalLookupController.php` (novo) + rota `GET /nfe-brasil/tributacao/empresa-fiscal` (`nfe-brasil.tributacao.empresa-fiscal`). Exige `nfe.tributacao.manage`; o tenant vem só da sessão. Devolve `campos` + `sugestoes`. As sugestões só levam regime quando as fontes concordam ou quando chega `?regime=` (a escolha do operador).
- `TributacaoTemplateService::sugerir(regime, uf, cnaes)`: ordena **todos** os templates por regime (3) + UF (2) + setor do CNAE (1). Cada item traz o template inteiro com `tributacao_default` e um bloco `aderencia`. `normal` casa presumido e real.
- `TributacaoTemplateService::aplicar($biz, $slug, ?$ncmDefault)`:
  - **exige NCM padrão** de 8 dígitos e diferente de `00000000`, senão lança `ValidationException` (422);
  - se o NCM não vier informado, usa o `ncm_default` da config atual e depois o `business.ncm_padrao` (D-SUPORTE);
  - grava o NCM dentro de `tributacao_default` (bug #3: o template apagava o `ncm_default`);
  - continua sem tocar `nfe_fiscal_rules`;
  - grava `activity('nfe.tributacao')->log('template.aplicado')` com o autor, quando muda (US-NFE-062);
  - a idempotência passou a comparar o array decodificado com `==`, porque o MySQL reordena as chaves do campo `json`.
- Testes novos, na lane `nfebrasil-pest.yml`:
  - `EmpresaFiscalLookupTest`: UC-NFTR-14 · UC-NFTR-16 · R-NFE-028;
  - `TributacaoTemplateSugestaoTest`: UC-NFTR-15;
  - `TributacaoTemplateAplicarTest`: UC-NFTR-17.

  Tenant de teste: biz=99 (fictício, criado pelo helper), com o seed canônico de vizinho.
- `TributacaoIndexContratoTest`: o fixture `nftrConfig` passou a ter `ncm_default` válido. Sem ele, o UC-NFTR-03 receberia o 422 novo, que é exatamente o contrato desta thread.

## 2 · Não feito e por quê
- **A premissa do playbook sobre a BrasilAPI está errada (medido).** A thread diz que o lookup do `ClienteLookupController` já traz CNAE e `simples_optante`. Não traz: `Modules\Crm\Services\BrLookupService::lookupCnpj` e `App\Services\BR\BrasilApiService::lookupCnpj` não expõem CNAE nem Simples. O serviço novo faz a chamada à BrasilAPI por conta própria, com cache separado (`nfe_empresa_fiscal:brasilapi:{cnpj}`). Isso deixa **três** chamadas à mesma API no repo. Unificar exige mudar o shape de um cache de 30 dias em `Modules/Crm`, que está fora do prefixo.
- **SEFAZ e BrasilAPI rodam em sequência, não em paralelo.** O PHP não tem `await`; o `Concurrency` do Laravel (driver process) não foi adotado. O paralelismo que a ADR 0186 inv. 4 exige é o do **front** (`Promise.all`). O backend não está coberto por ele.
- **O `TributacaoController::aplicarTemplate` não repassa o `ncm_default` da request.** Não toquei no arquivo porque é o mesmo controller da thread 18 (`destroy`), e a Lei 3 proíbe dois PRs no mesmo prefixo. Hoje a rota aplica com o NCM que a empresa já tem; sem ele, responde 422. **A thread 22 precisa desse repasse** (são duas linhas: `$request->input('ncm_default')` → `aplicar(..., $ncm)`), e ela não toca `Modules/`. Pedido no item 3.
- **UC-NFTR-14..17 não foram escritos no `Index.casos.md`, nem o R-NFE-028 no SPEC.** O destino da thread é `resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md`, mas a mesma thread tem `nao_toca: resources/js/`, e esse arquivo é prefixo da thread 22. Os testes já citam os ids. Falta a linha no casos.md e no SPEC (item 3).
- **O `aplicarTemplate` continua sem gate de permissão** (o `TributacaoGatesContratoTest` registra isso). Está fora do escopo desta thread.
- `Modules/NfeBrasil/Tests/Feature/TributacaoTemplateServiceTest.php` **não roda em lane nenhuma** e já estava errado antes desta thread (afirma 3 templates; existem 11). Com o NCM obrigatório, os casos de `aplicar()` dele também ficam desatualizados. Não editei: ele não é um dos testes nomeados e não roda.
- Pest só no CI (lane `nfebrasil-pest.yml`). Localmente rodei apenas `php -l` nos 8 arquivos.

## 3 · Pedido literal pro [CL]/[W]
1. **Thread 22 ou uma sessão de backend depois do merge da 18:** em `TributacaoController::aplicarTemplate`, passar `$request->input('ncm_default')` como 3º argumento de `TributacaoTemplateService::aplicar`.
2. **Thread 22:** escrever no `Index.casos.md` os UC-NFTR-14..17 conforme `21-certificado-template-backend.md`, citando os testes `EmpresaFiscalLookupTest`, `TributacaoTemplateSugestaoTest` e `TributacaoTemplateAplicarTest`. **Consolidação:** escrever o R-NFE-028 no SPEC NfeBrasil.
3. **[W], se quiser:** unificar as 3 chamadas BrasilAPI (`BrLookupService`, `BrasilApiService`, `EmpresaFiscalLookupService`) numa só, que exponha CNAE e Simples.

## 4 · Descobertas que mudam outra sessão
- **Thread 22:** o endpoint é `GET /nfe-brasil/tributacao/empresa-fiscal[?regime=…]`, e a resposta é `{campos:{cnpj,uf,razao_social,ie,situacao,cnaes,regime}, sugestoes:[...]}`. Regime divergente vem com `valor: null` e `opcoes:[{valor,fonte}]`. Sem SEFAZ, `ie` e `situacao` vêm com `motivo` (`uf_unsupported`, `env_homolog`, `no_cert`, `sefaz_error`, `sem_uf`), para o operador digitar. Aplicar sem NCM válido devolve **422** com o erro em `ncm_default`.
- **Thread 07 (D-OPERACAO):** o `tributacao_default` agora carrega `ncm_default`. Quem migrar o default para "regra geral da operação Venda" deve preservar essa chave.
- **Thread 18:** o fixture `nftrConfig` do `TributacaoIndexContratoTest` ganhou `ncm_default`. É uma linha; se houver conflito, mantenha a chave.

## 5 · Prefixo tocado
`Modules/NfeBrasil/Services/EmpresaFiscalLookupService.php` (novo) · `Modules/NfeBrasil/Services/Tributacao/TributacaoTemplateService.php` · `Modules/NfeBrasil/Http/Controllers/EmpresaFiscalLookupController.php` (novo) · `Modules/NfeBrasil/Routes/web.php` (+1 rota, necessária para o endpoint) · `Modules/NfeBrasil/Tests/Feature/{EmpresaFiscalLookup,TributacaoTemplateSugestao,TributacaoTemplateAplicar}Test.php` (novos) · `Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php` (fixture) · `.github/workflows/nfebrasil-pest.yml` (+3 testes na lane) · este `_saida-21.md`. Não toquei em `SefazConsultaCadastroService` nem `CertificadoService`. Os arquivos fora do prefixo nomeado são a rota, a lane e o fixture: cada um é condição para o teste rodar ou para o UC-NFTR-03 seguir verde.
