<?php

declare(strict_types=1);

namespace Modules\Arquivos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Entities\Arquivo;

/**
 * CofreStatsReader — saúde do armazenamento do acervo (US-ARQ-013, onda 1 · PR-4).
 *
 * Responde o que a vista **Cofre** do charter pede: *"espaço por disco, arquivo acima
 * do cap de 50 MB que o `VaultEncryptionService` recusa, órfão sem `arquivable`, MD5
 * repetido"*. Leitura pura — nenhum caminho aqui escreve, apaga ou enfileira.
 *
 * ## Por que não é um método a mais no `CuradorStatsReader`
 *
 * Ele é o dono declarado de outro tema — estatísticas do **pipeline Curador**
 * (US-ARQ-018: contagem por bucket, ações nas últimas 24h, taxa de dedupe) — e nenhuma
 * das 4 métricas daqui está lá. Duas razões concretas o desqualificam como casa desta:
 *
 *  1. o `fetch()` dele resolve o tenant com `?? 1` no fim da cadeia, ou seja, **sem
 *     sessão ele responde pelo business 1**. Numa tela de governança essa é a resposta
 *     errada, e não a herdo: aqui, sem tenant resolvido, o retorno é vazio (ver abaixo);
 *  2. a taxa de dedupe dele lê `arquivos_dedupe`, que é **cross-business por desenho**
 *     (ADR 0123 §3, mitigação de side-channel). Serve de número global pra admin, não de
 *     achado de um cliente. O duplicado desta vista é derivado de `arquivos`, dentro do
 *     escopo do próprio business.
 *
 * ## Multi-tenant Tier 0 (ADR 0093) — o recorte vem do model, o portão vem daqui
 *
 * Lê pelo model `Arquivo`, que aplica global scope por `business_id`. **Não** há `where`
 * manual: repeti-lo esconderia uma quebra do scope — mesma regra do acervo, e o oposto
 * do que vale na trilha, que lê uma tabela sem model.
 *
 * O que se acrescenta é um **portão de entrada fail-closed**: sem `business_id` na
 * sessão o método devolve o retrato vazio, em vez de deixar a query passar sem filtro
 * (o global scope faz `if ($businessId !== null)`, logo é fail-open). O portão não
 * duplica o filtro — só recusa perguntar quando não se sabe por quem.
 *
 * @see resources/js/Pages/Arquivos/Index.charter.md
 * @see memory/requisitos/Arquivos/RUNBOOK-index.md
 * @see Modules/Arquivos/Services/VaultEncryptionService.php  (quem recusa acima do cap)
 */
class CofreStatsReader
{
    /** Quantos arquivos citar por achado. Achado é sinal, não listagem — a lista é o acervo. */
    private const EXEMPLOS = 5;

    /**
     * Teto de grupos de duplicados percorridos.
     *
     * Existe pra que a contagem não dependa de uma segunda query de agregação: com o
     * teto, `grupos` é o tamanho da coleção e `truncado` diz honestamente quando o
     * número é um piso. Sem ele, contar o total exigiria `fromSub()`, que sai do model —
     * e sair do model é sair do global scope.
     */
    private const CAP_GRUPOS = 200;

    /**
     * @return array{
     *   disponivel: bool,
     *   cap_mb: int,
     *   discos: array<int, array{disco: string, arquivos: int, bytes: int, cifrados: int}>,
     *   acima_do_cap: array{total: int, exemplos: array<int, array<string, mixed>>},
     *   orfaos: array{total: int, exemplos: array<int, array<string, mixed>>},
     *   duplicados: array{grupos: int, registros: int, truncado: bool, exemplos: array<int, array<string, mixed>>}
     * }
     */
    public function fetch(): array
    {
        // Portão fail-closed (ver docblock da classe) + módulo não instalado.
        if ($this->businessIdDaSessao() === null || ! Schema::hasTable('arquivos')) {
            return $this->vazio();
        }

        return [
            'disponivel'   => true,
            'cap_mb'       => $this->capMb(),
            'discos'       => $this->discos(),
            'acima_do_cap' => $this->acimaDoCap(),
            'orfaos'       => $this->orfaos(),
            'duplicados'   => $this->duplicados(),
        ];
    }

    /**
     * Espaço por disco — o que o protótipo desenha como os dois cards.
     *
     * Os discos NÃO são escritos aqui: saem de um `GROUP BY` do próprio acervo, pelo
     * mesmo motivo que os chips da trilha saem do log. Os nomes vivem em config
     * (`disk_default`/`disk_vault`) e já variam por ambiente ('local' em dev, 'arquivos'
     * no CT 100) — uma lista em PHP diria o disco errado em metade das instalações, e
     * ainda esconderia um disco terceiro que aparecesse por backfill.
     *
     * `cifrados` acompanha porque é o que distingue os dois cards: vault com cifrado
     * abaixo do total é o mesmo sinal do check #5 do `arquivos:health-check`.
     *
     * @return array<int, array{disco: string, arquivos: int, bytes: int, cifrados: int}>
     */
    private function discos(): array
    {
        return Arquivo::query()
            ->select([
                'disk',
                DB::raw('COUNT(*) as qtd_arquivos'),
                DB::raw('COALESCE(SUM(size_bytes), 0) as soma_bytes'),
                DB::raw('COALESCE(SUM(encrypted), 0) as qtd_cifrados'),
            ])
            ->groupBy('disk')
            ->orderByDesc('soma_bytes')
            ->get()
            ->map(fn ($r) => [
                'disco'    => (string) $r->disk,
                'arquivos' => (int) $r->qtd_arquivos,
                'bytes'    => (int) $r->soma_bytes,
                'cifrados' => (int) $r->qtd_cifrados,
            ])
            ->all();
    }

    /**
     * Achado 1 — acima do cap que o vault recusa.
     *
     * O cap é o mesmo que o `VaultEncryptionService` cobra em runtime
     * (`arquivos.vault_max_file_size_mb`, 50 MB por default): ele carrega o arquivo
     * inteiro em memória pra cifrar e, acima do cap, **recusa** em vez de arriscar OOM
     * (chunked encryption é a ADR 0126). Ler a config, em vez de escrever 50 aqui, é o
     * que mantém a tela verdadeira quando alguém ajustar o `.env`.
     *
     * A lista inclui arquivo de qualquer disco, não só do vault: o cap morde na hora em
     * que o arquivo VAI pro cofre, então um arquivo comum grande é o que quebra a
     * próxima reclassificação pra `sensitive`. Por isso o exemplo carrega `disco` e
     * `cifrado` — quem já está no vault sem cifra é o caso urgente.
     *
     * @return array{total: int, exemplos: array<int, array<string, mixed>>}
     */
    private function acimaDoCap(): array
    {
        $capBytes = $this->capMb() * 1024 * 1024;

        $base = Arquivo::query()->where('size_bytes', '>', $capBytes);

        $exemplos = (clone $base)
            ->orderByDesc('size_bytes')
            ->limit(self::EXEMPLOS)
            ->get()
            ->map(fn (Arquivo $a) => [
                'id'      => (int) $a->id,
                'nome'    => (string) $a->original_name,
                'bytes'   => (int) $a->size_bytes,
                'disco'   => $a->disk,
                'cifrado' => (bool) $a->encrypted,
            ])
            ->all();

        return ['total' => (clone $base)->count(), 'exemplos' => $exemplos];
    }

    /**
     * Achado 2 — órfão: linha sem `arquivable`.
     *
     * Ninguém o alcança pela tela do dono; ou se vincula, ou se apaga. O acervo já marca
     * a linha órfã, mas lá ela some no meio da paginação — aqui ela é contada.
     *
     * @return array{total: int, exemplos: array<int, array<string, mixed>>}
     */
    private function orfaos(): array
    {
        $base = Arquivo::query()->whereNull('arquivable_type');

        $exemplos = (clone $base)
            ->orderByDesc('size_bytes')
            ->limit(self::EXEMPLOS)
            ->get()
            ->map(fn (Arquivo $a) => [
                'id'    => (int) $a->id,
                'nome'  => (string) $a->original_name,
                'bytes' => (int) $a->size_bytes,
            ])
            ->all();

        return ['total' => (clone $base)->count(), 'exemplos' => $exemplos];
    }

    /**
     * Achado 3 — mesmo conteúdo com mais de um registro.
     *
     * ⚠️ **Isto não deveria acontecer pelo caminho normal, e é o que torna o achado
     * interessante:** `ArquivosService::attachInternal()` faz lookup de dedupe por hash
     * dentro do mesmo business ANTES de gravar e devolve o registro existente sem criar
     * linha nova. Repetição aqui significa linha que entrou por outro caminho — o
     * backfill de NF-e insere com `DB::table('arquivos')->insert()`, sem passar pelo
     * dedupe — ou dedupe que não pegou.
     *
     * **O que NÃO se afirma:** desperdício de disco. O caminho de gravação é derivado do
     * próprio hash (`biz-{id}/{ano}/{mês}/{hash}.{ext}`), então duas linhas do mesmo mês
     * apontam pro MESMO arquivo físico — seria registro repetido, não byte repetido. Em
     * meses diferentes, não. Como a diferença é medível, ela é medida: `caminhos` conta
     * caminhos de storage distintos no grupo. `caminhos = 1` é ruído de registro;
     * `caminhos > 1` é disco ocupado duas vezes. Somar bytes e chamar de economia sem
     * isso seria inventar um número.
     *
     * O hash NÃO sai deste método: ele agrupa, e o que volta são nomes e contagens.
     *
     * @return array{grupos: int, registros: int, truncado: bool, exemplos: array<int, array<string, mixed>>}
     */
    private function duplicados(): array
    {
        $grupos = Arquivo::query()
            ->select([
                'md5',
                DB::raw('COUNT(*) as copias'),
                DB::raw('COUNT(DISTINCT storage_path) as caminhos'),
                DB::raw('COALESCE(SUM(size_bytes), 0) as soma_bytes'),
            ])
            ->groupBy('md5')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('copias')
            ->limit(self::CAP_GRUPOS + 1)
            ->get();

        $truncado = $grupos->count() > self::CAP_GRUPOS;
        $grupos   = $grupos->take(self::CAP_GRUPOS);
        $topo     = $grupos->take(self::EXEMPLOS);

        // Segunda query só pros grupos exibidos: o nome não cabe no GROUP BY, e
        // GROUP_CONCAT trunca em silêncio ao bater o `group_concat_max_len`.
        $nomes = $topo->isEmpty()
            ? collect()
            : Arquivo::query()
                ->whereIn('md5', $topo->pluck('md5')->all())
                ->orderBy('id')
                ->get(['id', 'md5', 'original_name'])
                ->groupBy('md5');

        return [
            'grupos'    => $grupos->count(),
            'registros' => (int) $grupos->sum('copias'),
            'truncado'  => $truncado,
            'exemplos'  => $topo->map(fn ($g) => [
                'copias'   => (int) $g->copias,
                'caminhos' => (int) $g->caminhos,
                'bytes'    => (int) $g->soma_bytes,
                'nomes'    => $nomes->get($g->md5, collect())
                    ->pluck('original_name')
                    ->map(fn ($n) => (string) $n)
                    ->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Cap do vault em MB — a MESMA config que o `VaultEncryptionService::capBytes()` lê.
     *
     * Lá o valor `<= 0` vira `RuntimeException` (o cap não pode ser desligado). Aqui, que
     * é tela, a mesma condição cai no default em vez de derrubar a página: um `.env`
     * malformado quebra a cifragem no upload, com a exceção que descreve o problema — e
     * não é papel de uma vista de leitura ser a mensageira disso.
     */
    private function capMb(): int
    {
        $mb = (int) config('arquivos.vault_max_file_size_mb', 50);

        return $mb > 0 ? $mb : 50;
    }

    /**
     * O `business_id` da sessão — MESMA fonte que o global scope do `Arquivo` usa.
     *
     * Duplica de propósito as 3 linhas que o `ArquivosAdminController` tem: o portão é
     * desta classe (ela pode ser chamada de outro lugar amanhã), e um helper
     * compartilhado exigiria mover o do controller, que é outro intent.
     */
    private function businessIdDaSessao(): ?int
    {
        $id = session('user.business_id') ?? session('business.id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Retrato vazio — mesmo formato do cheio, com `disponivel: false`.
     *
     * A tela distingue "não há nada guardado" de "não consegui medir": zero com
     * `disponivel: true` é acervo limpo; `false` é ausência de resposta, e dizer
     * "0 achados" nesse caso seria afirmar saúde sem ter medido.
     *
     * @return array<string, mixed>
     */
    private function vazio(): array
    {
        return [
            'disponivel'   => false,
            'cap_mb'       => $this->capMb(),
            'discos'       => [],
            'acima_do_cap' => ['total' => 0, 'exemplos' => []],
            'orfaos'       => ['total' => 0, 'exemplos' => []],
            'duplicados'   => ['grupos' => 0, 'registros' => 0, 'truncado' => false, 'exemplos' => []],
        ];
    }
}
