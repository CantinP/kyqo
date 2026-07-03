<?php

use Kyqo\Database\Schema\Blueprint;
use Kyqo\Database\Schema\Migration;
use Kyqo\Database\Connection;

return new class extends Migration
{
    public function up(Connection $db): void
    {
        $db->schema()->create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });
    }

    public function down(Connection $db): void
    {
        $db->schema()->dropIfExists('users');
    }
};
