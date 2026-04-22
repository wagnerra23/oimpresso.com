<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOfficeOimpressoUpdatedAtToContacts extends Migration
{
    public $withinTransaction = false;

    public function up()
    {
        if (! Schema::hasColumn('contacts', 'office_oimpresso_updated_at')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->timestamp('office_oimpresso_updated_at')->nullable()->after('is_sincronizado');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('contacts', 'office_oimpresso_updated_at')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn('office_oimpresso_updated_at');
            });
        }
    }
}
