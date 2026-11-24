<?php

namespace LucaPon\StatamicContentBackup\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LucaPon\StatamicContentBackup\Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['products', 'orders'] as $table) {
            Schema::dropIfExists($table);
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            });
            DB::table($table)->insert([['name' => 'first'], ['name' => 'second']]);
        }

        config(['statamic-content-backup.include_tables' => ['products']]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('orders');

        parent::tearDown();
    }

    public function test_it_backs_up_the_configured_tables(): void
    {
        $backupName = $this->actingAsSuperUser()->createBackup();

        $this->assertZipEntries($this->backupFolder($backupName), ['database/products.sql', 'manifest.json']);
    }

    public function test_it_restores_the_configured_tables(): void
    {
        $backupName = $this->actingAsSuperUser()->createBackup();

        DB::table('products')->where('name', 'first')->delete();
        DB::table('products')->where('name', 'second')->update(['name' => 'changed']);
        DB::table('products')->insert(['name' => 'new']);
        DB::table('orders')->insert(['name' => 'new']);

        $this->assertSame($backupName, $this->restoreBackup($backupName)['success']);

        $this->assertSame(['first', 'second'], DB::table('products')->orderBy('id')->pluck('name')->all());
        $this->assertSame(['first', 'second', 'new'], DB::table('orders')->orderBy('id')->pluck('name')->all());
    }

    public function test_restored_tables_keep_generating_ids_after_the_restored_rows(): void
    {
        $backupName = $this->actingAsSuperUser()->createBackup();
        DB::table('products')->truncate();

        $this->restoreBackup($backupName);

        $this->assertSame(3, DB::table('products')->insertGetId(['name' => 'third']));
    }

    public function test_tables_missing_from_the_backup_are_left_untouched(): void
    {
        $backupName = $this->actingAsSuperUser()->createBackup();

        config(['statamic-content-backup.include_tables' => ['products', 'orders']]);
        DB::table('orders')->insert(['name' => 'new']);

        $this->assertSame($backupName, $this->restoreBackup($backupName)['success']);

        $this->assertSame(3, DB::table('orders')->count());
    }
}
