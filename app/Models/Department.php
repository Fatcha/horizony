<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Department extends Model {
    protected $table = 'departments';

//    public function users() {
//        return $this->hasMany(User::class,'department_id');
//    }

    public function users() {
        return $this->belongsToMany('App\User','company_user')->withPivot('role');
       // return $this->belongsToMany('App\User')->withPivot('role');
    }

}
