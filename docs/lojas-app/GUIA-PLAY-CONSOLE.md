# Guia — Play Console, tela a tela (app "Oimpresso.com", `com.oimpresso.app`)

> ## Estado no console em 2026-10-06 (lido no próprio console)
>
> - **O app certo é o `oimpresso`, ID `4972639962981895861`, pacote `com.oimpresso.app`**, criado em 2026-10-06.
>   O app "Oimpresso.com" (ID `4976163601282243225`) estava preso ao pacote `com.Oimpresso.com` e recusou o AAB
>   ("precisa ter o nome de pacote com.Oimpresso.com"). Na Play o pacote não muda, então ele ficou em rascunho, sem uso.
>   Decisão do agente, delegada pelo [W] ("decida"): manter `com.oimpresso.app`, que é o que Capacitor, `build.gradle` e OAuth usam.
> - **Teste interno:** versão `7 (1.0)` publicada (AAB do run 37527813712, `sem_push`). Ainda sem testadores.
> - **Salvo no app novo:** Anúncios (não) · Apps governamentais (não) · Recursos financeiros (nenhum) · Saúde (nenhum) ·
>   ID de publicidade (não usa) · Classificação de conteúdo: **Livre (L)**. "Conteúdo online" foi respondido **Sim**,
>   porque o Assistente responde com IA. · Categoria **Empresa** · Página "Detalhes do app" com os textos D16, ícone, banner e 8 capturas.
> - **Pendente:** Política de Privacidade, Detalhes do login (senhas: [W]), Público-alvo (só abre depois do login),
>   Segurança dos dados (a tabela 8 abaixo é do escopo só-Ponto; o app atual também tem cadastro de pessoas e Assistente),
>   e-mail de contato público, e a faixa de **teste fechado** com 12 testadores. A conta é **pessoal**, então valem os 14 dias.
> - **Atualização do fim do dia (2026-10-06):** configuração completa. Salvos também: política de privacidade,
>   contato público `wagner@oimpresso.com` + `https://oimpresso.com`, Detalhes do login (`gestor.demo`; a senha foi
>   colada pelo [W]), Público-alvo 18+ e Segurança dos dados (10 tipos, coletados e não compartilhados).
>   Teste fechado "Alpha": Brasil, versão 7 (1.0). **12 mudanças enviadas para revisão do Google** (até 7 dias).
>   ⏳ Faltam os **12 testadores** na aba Testadores da faixa, e depois os 14 dias.
>   ⚠️ `lgpd@oimpresso.com.br`, citado em `/privacidade`, **não recebe e-mail**: `oimpresso.com.br` tem MX nulo.
> - O `oimpresso-app` virou **público** em 2026-10-06. A cota de Actions de repo privado tinha orçamento de 0 e travou o CI.
>   Antes de abrir, o histórico (301 commits) foi varrido: nenhum segredo encontrado.

> Decisões vigentes em [`DECISOES.md`](DECISOES.md): o app usa telas próprias no `oimpresso-app` (visual do protótipo Mobile),
> não o ERP web nem `/m`. Lista de tarefas lida do Painel do console em 2026-10-01.
> Quem clica e salva é o [W]: cada "Salvar" é declaração em nome da empresa.
> Respostas derivadas de [`textos/listagem-pt-BR.md`](textos/listagem-pt-BR.md) e [`textos/privacidade-lojas.md`](textos/privacidade-lojas.md).
> Senhas nunca entram aqui: vêm do Vaultwarden e são coladas direto no console.
> ⛔ **Página da loja e Segurança dos dados: esperar.** D13 ([W] 2026-10-01): a submissão só sai quando as 7 áreas
> da v1 existirem no app; os textos "só Ponto" e as screenshots do 1º lote não serão enviados.

Caminho: **Painel → "Termine de configurar seu app" → Ver tarefas**. Ordem sugerida (as mais fáceis primeiro):

| # | Tarefa | O que responder | Pronto? |
|---|---|---|---|
| 1 | **Anúncios** | "Não, meu app não contém anúncios" | ✅ |
| 2 | **Apps governamentais** | Não | ✅ |
| 3 | **Recursos financeiros** | "Meu app não oferece nenhum desses recursos" (o ERP registra cobranças da empresa; não é banco, crédito nem cripto) | ✅ |
| 4 | **Saúde** | Nenhum recurso de saúde | ✅ |
| 5 | **Público-alvo** | Faixa etária: só **18 anos ou mais**. "O app pode atrair crianças?": Não | ✅ |
| 6 | **Classificação de conteúdo** | E-mail de contato; categoria **"Todos os outros tipos de app"**; "Não" para violência, sexo, linguagem, drogas, jogos de azar; "compartilha localização com outros usuários?": **Não** (vai só ao empregador); "usuários interagem entre si?": Não. Esperado: **Livre (L)** | ✅ |
| 7 | **Acesso ao app** ("Detalhes do login") | "Todas ou algumas funcionalidades estão restritas". Usuários `revisor.ponto` (colaborador) e `gestor.demo` (gestor); senhas dos itens `ponto-demo-revisor` e `ponto-demo-gestor` do Vaultwarden; instruções: copiar de [`memory/requisitos/Ponto/REVISAO-LOJAS-NOTAS.md`](../../memory/requisitos/Ponto/REVISAO-LOJAS-NOTAS.md) (PT e EN) | ✅ contas criadas em produção (business 235, 2026-10-01). ⏳ falta só [W] mover as senhas para o Vaultwarden. O gestor vê só os módulos básicos até existir o pacote "Demo lojas" |
| 8 | **Segurança dos dados** | Seguir `textos/privacidade-lojas.md` (1ª submissão): localização precisa, nome, e-mail, IDs de usuário, justificativas, ID do dispositivo — todos **coletados, não compartilhados**, criptografados em trânsito, com pedido de exclusão. **Não** marcar fotos, telefone, endereço, financeiro, diagnóstico nem analytics | ✅ medido 2026-10-01 |
| 9 | **Política de Privacidade** | `https://oimpresso.com/privacidade` (cobre ERP + ponto) | ✅ `https://oimpresso.com/privacidade` no ar (#8437, 200 sem login em 2026-10-01). ⏳ revisão da Eliana [E] e e-mail `lgpd@oimpresso.com.br` confirmado antes de colar |
| 10 | **Página "Detalhes do app"** | Nome `oimpresso`; descrição curta e longa de `textos/listagem-pt-BR.md`; ícone `play-icon-512.png`; banner `play-feature-graphic-1024x500.jpg`; categoria **Empresas**; e-mail de contato | ⏳ screenshots (mín. 2; 4 de 1080×1920 para destaque) |

Depois: **Testar e lançar → Teste interno** (upload do AAB — sessão ANDROID). Para conta pessoal criada após
nov/2023 o Google exige teste fechado com 12 testadores por 14 dias; conta de **organização** é dispensada —
conferir o tipo da conta antes de planejar prazo.

## App do paciente do Fisio (`com.oimpresso.fisio`) — estado em 2026-10-19

- **App no console:** "oimpresso Fisio", ID `4973690108002117967`, mesma conta (WR2 Sistemas, `5298628569506403208`).
  TWA do portal do paciente, abre `https://oimpresso.com/fisio/app`. Criado como App, gratuito, Português (Brasil).
- **Teste interno:** versão `2 (0.1.0)` publicada em 2026-10-19 (AAB `fisio-release-aab-2`, run 39257781902 do
  `oimpresso-app`). O AAB do run 39253637760 (versão 1) foi **recusado**: a Proteção Automática da Play exige
  minSdk 24 e ele tinha 23. Corrigido em `fisio-twa/app/build.gradle` (commit `15be21c` no PR wagnerra23/oimpresso-app#108).
- **Testadores:** lista de e-mails "Fisio testadores" com `wagnerra@gmail.com` (só ela marcada na faixa).
  Link de participação: `https://play.google.com/apps/internaltest/4701425155280694866`.
  Até o app ser revisado, aparece no celular como "com.oimpresso.fisio (unreviewed)".
- **Integridade do app → Assinatura de apps** (para o `assetlinks.json` do `oimpresso.com`):
  - Chave de assinatura do app (gerenciada pelo Google) — **SHA-256:**
    `37:7E:0C:D1:AB:F7:B2:F8:C8:00:E9:B9:EB:91:C0:5A:E3:F7:99:DB:D3:CF:3A:74:D3:00:82:80:FB:07:73:16`
  - Chave de upload (a mesma do app oimpresso; vale para AAB/APK instalado fora da Play) — SHA-256:
    `4E:DB:F0:2C:68:5B:CA:0B:89:E9:A9:03:A7:6A:FB:D2:F8:85:BF:BA:6B:11:90:DD:0C:B5:40:05:E2:31:C3:50`
  - Entrada a incluir no `assetlinks.json` (o TWA só abre sem barra de URL com ela no ar):
    `{"relation":["delegate_permission/common.handle_all_urls"],"target":{"namespace":"android_app","package_name":"com.oimpresso.fisio","sha256_cert_fingerprints":["<as duas acima>"]}}`
- **Pendente** (declarações em nome da empresa, [W] aprova antes de salvar): política de privacidade, Saúde
  (app de fisioterapia), Segurança dos dados, Público-alvo, Classificação, página da loja. Teste fechado/aberto
  ("beta") só abre depois disso.
