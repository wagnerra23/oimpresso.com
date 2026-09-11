# PROMPT pra o Cowork — a ADR 0374 está VIVA; corrigir 5 pontos (cole no chat do Design)

> **De:** Claude Code → **Para:** Cowork (o Claude do `claude.ai/design`) · **Data:** 2026-09-08
> **O que é:** prompt único e auto-suficiente. **[W] precisa colar isto no chat do Design** — o
> `CLAUDE.md` do projeto do Cowork nomeia 6 documentos de read-order e **nenhum deles é um
> `CODE_NOTES`**, então deixar este arquivo no `main` não faz o Cowork lê-lo (errata do
> [`CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md`](CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md)).
> **Decisão [W] de hoje:** a 0374 **não** será revogada — nem inteira, nem a linha de transcrição.
> Append-only. Antecessor do tema: [`CODE_NOTES.errata-0374-nao-revogada-2026-09-08.md`](CODE_NOTES.errata-0374-nao-revogada-2026-09-08.md)
> (manhã, sobre o ciclo anterior) e [`CODE_NOTES.errata-playbook-patrimonio-2026-09-08.md`](CODE_NOTES.errata-playbook-patrimonio-2026-09-08.md) (o lote de hoje).

---

## O bloco colável

> **A ADR 0374 está viva.** Medido em `origin/main` hoje: `status: aceito` · `lifecycle: ativo` ·
> `superseded_by: []` · nenhuma ADR de 0375 a 0392 a supersede. O ciclo de 08/09 afirma o contrário
> em **5 pontos**, e isso já tinha sido corrigido por errata na manhã do mesmo dia — é reincidência,
> não estreia.
>
> **A emenda real é a ADR 0389** (`decided_at: 2026-09-03`, `supersedes: []`), e ela **toca uma
> linha**: libera a escrita inline **só** quando o `get_file` devolve o conteúdo inline e **não
> existe rota de máquina** para aquele arquivo, sob 4 condições, nenhuma opcional — origem única
> resolvida por `list_files`, `truncated: false`, verificação pós-escrita rodando o consumidor, e a
> escrita inline declarada no PR. **Onde a rota existe, transcrever segue proibido.**
>
> Isso não é teoria: o lote Patrimônio de hoje desceu exatamente assim. Os 9 arquivos pequenos
> vieram inline e foram escritos sob a 0389; os 2 grandes passaram do teto, voltaram em arquivo e
> foram escritos por script. **10 dos 11 sha256 bateram byte-a-byte** — é a fidelidade que a 0374
> existe para garantir, e ela só é verificável porque a norma continua de pé.
>
> **Os 5 pontos a corrigir:**
>
> | arquivo | linha | o que diz hoje | o que passa a dizer |
> |---|---|---|---|
> | `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` | `:14` | *"a ADR 0374, revogada em 07/09"* | *"a ADR 0374, emendada pela 0389 em 03/09"* |
> | idem | `:330` | *"até 2026-09-07, quando [W] revogou a ADR 0374"* | *"até 2026-09-03, quando a ADR 0389 emendou a 0374"* |
> | idem | `:408` | *"era ADR 0374, revogada por [W] em 2026-09-07. O pacote agora se gera dos dois lados"* | *"ADR 0374, viva; a 0389 libera a escrita inline só onde não há rota de máquina, sob 4 condições"* |
> | idem | `:468` | *"regenera o pacote quando pedido (ADR 0374 revogada em 2026-09-07)"* | *"regenera o pacote quando pedido (ADR 0374 viva; 0389 emenda uma linha)"* |
> | `CONSTITUICAO-COWORK.md` | `:5` | *"a ADR 0374 foi revogada em 07/09 e seguiu citada como vigente"* | o exemplo está **invertido**: a ADR está vigente e o protocolo é que a declarava revogada. Ou se corrige o sentido, ou se troca por outro exemplo de regra copiada que envelheceu |
>
> **Por que a `:408` é a que mais importa:** ela não erra uma data — ela **inverte uma norma**. Quem
> a lê conclui que transcrever pelo contexto está liberado em geral, que é mais amplo do que a 0389
> concedeu, e é justamente o comportamento que produz pacote sem fidelidade verificável.
>
> **Por que a `:5` da Constituição pesa mais que as outras 4:** a Constituição é citada por sha no
> `§0` de *todo* pacote de módulo. Um exemplo motivador falso ali se propaga por construção — e é
> especialmente irônico num arquivo cujo argumento é *"regra repetida é regra que se contradiz
> sozinha"*. A própria Constituição resolve o conflito na linha 3 (*"se divergir do repo, manda o
> repo"*), mas o exemplo continua ensinando o oposto de dentro.
>
> **O que NÃO muda:** a 0374 segue valendo inteira — espelhar o projeto Cowork para
> `prototipo-ui/cowork/Wagner/` continua sendo a **rota prevista**, pela razão operacional que [W] registrou
> em 11/08: *"vai ter computadores que não vão ter acesso ao design dessa máquina… por isso baixar
> para git sempre"*. [F], [M], [L] e [E] trabalham só com o git.

---

## Nota de método (para o Code, não para colar)

Esta é a **segunda** errata sobre a mesma afirmação no mesmo dia. A primeira foi escrita no `main` e
não alterou o ciclo seguinte — previsível, já que o Cowork não lê `CODE_NOTES`. Se a afirmação
reincidir num terceiro ciclo, o caminho deixa de ser errata e passa a ser **read-order**: pedir a
[W] que um dos 6 documentos que o Cowork de fato lê (`PROTOCOL.md` é o candidato natural) cite a
0374/0389 pelo estado real, para que a correção viaje pelo canal que o Cowork abre por conta.
Não armar isso agora: são 2 ocorrências, e a regra do projeto é que a 1ª conserta e a 2ª codifica —
esta é a 2ª, mas a codificação aqui depende de [W] mexer no read-order do outro lado.
