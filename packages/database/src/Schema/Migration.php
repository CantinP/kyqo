<?php

namespace Kyqo\Database\Schema;

use Kyqo\Database\Connection;

/**
 * Base class for all migrations.
 *
 * Usage:
 *   class CreateUsersTable extends Migration
 *   {
 *       public function up(Connection $db): void
 *       {
 *           $db->schema()->create('users', function (Blueprint $t) {
 *               $t->id();
 *               $t->string('email')->unique();
 *               $t->string('password');
 *               $t->rememberToken();
 *               $t->timestamps();
 *           });
 *       }
 *
 *       public function down(Connection $db): void
 *       {
 *           $db->schema()->dropIfExists('users');
 *       }
 *   }
 */
abstract class Migration
{
    /**
     * Run the migration.
     */
    abstract public function up(Connection $db): void;

    /**
     * Reverse the migration.
     */
    abstract public function down(Connection $db): void;
}
