<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddParentIdToKbCategories extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('kb_categories', 'parent_id')) {
            Schema::table('kb_categories', function (Blueprint $table) {
                $table->unsignedInteger('parent_id')->default(0)->after('mailbox_id');
                $table->index('parent_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('kb_categories', 'parent_id')) {
            Schema::table('kb_categories', function (Blueprint $table) {
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }
    }
}
