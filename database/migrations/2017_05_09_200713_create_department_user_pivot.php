<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDepartmentUserPivot extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if (!Schema::hasColumn('company_user', 'department_id')) {
            Schema::table('company_user', function (Blueprint $table) {
                $table->unsignedInteger('department_id')->nullable()->after('role');
                $table->foreign('department_id')->references('id')->on('departments');
            });
        }

//        Schema::create('department_user', function (Blueprint $table) {
//            $table->engine = 'InnoDB';
//
//            $table->increments('id');
//            $table->unsignedInteger('user_id');
//            $table->unsignedInteger('department_id');
//
//            $table->string('role')->nullable();;
//
//            $table->timestamps();
//
//            $table->foreign('user_id')->references('id')->on('users');
//            $table->foreign('department_id')->references('id')->on('departments');
//        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('department_user');
        if (Schema::hasColumn('company_user', 'department_id')) {
            Schema::table('company_user', function (Blueprint $table) {

                $table->dropForeign('department_id');
                $table->dropColumn('department_id');
            });
        }
    }
}
