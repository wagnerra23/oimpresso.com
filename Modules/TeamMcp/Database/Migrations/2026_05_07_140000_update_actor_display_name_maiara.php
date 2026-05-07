<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('mcp_actors')
            ->where('slug', 'maira')
            ->where('display_name', 'Maíra (suporte+dev)')
            ->update(['display_name' => 'Maiara (suporte+dev)']);
    }

    public function down(): void
    {
        DB::table('mcp_actors')
            ->where('slug', 'maira')
            ->where('display_name', 'Maiara (suporte+dev)')
            ->update(['display_name' => 'Maíra (suporte+dev)']);
    }
};
