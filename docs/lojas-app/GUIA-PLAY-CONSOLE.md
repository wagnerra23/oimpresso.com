# Guia — Play Console, tela a tela (app "Oimpresso.com", `com.oimpresso.app`)

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
