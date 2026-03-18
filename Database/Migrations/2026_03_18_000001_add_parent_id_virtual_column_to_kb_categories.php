<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddParentIdVirtualColumnToKbCategories extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('kb_categories', 'parent_id')) {
            DB::statement('ALTER TABLE kb_categories ADD COLUMN parent_id INT GENERATED ALWAYS AS (COALESCE(kb_category_id, 0)) VIRTUAL');
            DB::statement('ALTER TABLE kb_categories ADD INDEX idx_kb_categories_parent_id (parent_id)');
        }
    }

    public function down()
    {
        if (Schema::hasColumn('kb_categories', 'parent_id')) {
            DB::statement('ALTER TABLE kb_categories DROP INDEX idx_kb_categories_parent_id');
            DB::statement('ALTER TABLE kb_categories DROP COLUMN parent_id');
        }
    }
}
