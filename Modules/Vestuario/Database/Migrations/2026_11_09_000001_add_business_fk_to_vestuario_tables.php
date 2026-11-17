<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FK `business_id` → `business.id` nas 5 tabelas do Vestuario (ADR 0093, regra de migration).
 *
 * As 3 tabelas antigas deixaram a FK comentada "até validar em homolog"; as 2 da remarcação nem a
 * declararam. Tipo e engine conferem com `business.id` (int unsigned, InnoDB), e a contagem em
 * produção está no PR. ON DELETE CASCADE, como as 3 FKs comentadas previam e o núcleo faz nas
 * tabelas de negócio: `transactions` já some com a empresa.
 *
 * Idempotente. Com linha órfã, para antes de alterar qualquer tabela e diz qual: apagar dado aqui
 * não é decisão de migration, e o deploy roda `migrate --force` sozinho.
 */
return new class extends Migration {
    private const FKS = [
        'vestuario_settings'          => 'fk_vest_settings_business',
        'vestuario_devolucoes'        => 'fk_vest_devol_business',
        'vestuario_creditos_cliente'  => 'fk_vest_credito_business',
        'vestuario_remarcacoes'       => 'fk_vest_remarc_business',
        'vestuario_remarcacao_itens'  => 'fk_vest_remarc_it_business',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $tabelas = array_values(array_filter(
            array_keys(self::FKS),
            fn (string $tabela) => Schema::hasTable($tabela) && ! $this->temFk($tabela)
        ));

        $this->exigirSemOrfas($tabelas);

        foreach ($tabelas as $tabela) {
            Schema::table($tabela, fn (Blueprint $t) => $t->foreign('business_id', self::FKS[$tabela])
                ->references('id')->on('business')->cascadeOnDelete());
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::FKS as $tabela => $nome) {
            if (Schema::hasTable($tabela) && in_array($nome, array_column(Schema::getForeignKeys($tabela), 'name'), true)) {
                Schema::table($tabela, fn (Blueprint $t) => $t->dropForeign($nome));
            }
        }
    }

    /** @param  list<string>  $tabelas */
    public function exigirSemOrfas(array $tabelas): void
    {
        foreach ($tabelas as $tabela) {
            $orfas = DB::table($tabela . ' as x')
                ->leftJoin('business as b', 'b.id', '=', 'x.business_id')
                ->whereNull('b.id')
                ->count();

            if ($orfas > 0) {
                throw new RuntimeException(
                    "{$orfas} linha(s) de {$tabela} apontam para empresa inexistente. "
                    . 'Nenhuma FK foi criada: resolva essas linhas e rode a migration de novo.'
                );
            }
        }
    }

    private function temFk(string $tabela): bool
    {
        foreach (Schema::getForeignKeys($tabela) as $fk) {
            if ($fk['columns'] === ['business_id']) {
                return true;
            }
        }

        return false;
    }
};
