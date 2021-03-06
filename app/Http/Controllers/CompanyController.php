<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Mail\InviteUser;
use App\Models\AccountBuying;
use App\Models\AccountType;
use App\Models\Company;
use App\Models\Department;
use App\Models\Fatcha\Crypt\CryptId;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\View;
use Auth;
use Mail;


use Illuminate\Http\Request;
use \App\Models\Country;


class CompanyController extends Controller {
    /*
      |--------------------------------------------------------------------------
      | Welcome Controller
      |--------------------------------------------------------------------------
      |
      | This controller renders the "marketing page" for the application and
      | is configured to only allow guests. Like most of the other sample
      | controllers, you are free to modify or remove it as you desire.
      |
     */

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {

    }

    public function home(Request $request, $company_key) {


        $company = Company::where('key', '=', $company_key)->first();
        if(!$company){
            return redirect(route('connected_dashboard'));
        }
        // -- test if user can send test for this company
        $user = Auth::user();
        if (!$company->userIsMember(Auth::user())) {
            return redirect(route('connected_dashboard'));
        }

        $departmentsArray = [];
        foreach ($company->departments as $department){

            $departmentsArray[$department->id] =  $department->name;
        }

        return view('company.home', [
            'company' => $company,
            'rolesArray' => array_combine(Company::MEMBER_TYPE_ARRAY,Company::MEMBER_TYPE_ARRAY),
            'isAdmin' => $company->userIsAdmin(Auth::user()),
            'departments' => $departmentsArray
        ]);
    }


    public function inviteMemberOnCompany(Request $request, $company_key) {

        $this->validate($request, ['name' => 'required|max:150|min:2', 'email' => 'required|email']);


        $company = Company::where('key', '=', $company_key)->first();

        if (!$company->userIsAdmin(Auth::user())) {
            return redirect(route('connected_dashboard'));
        }

        $nbrCompanyMember = $company->users()->count();
        $authorizedMemberNumber = $company->accountType()->users_limit;

        if ($authorizedMemberNumber != -1 && $nbrCompanyMember >= $authorizedMemberNumber) {

            return redirect(route('company_home', [$company_key]))->with('number_of_users_already_reached', true);
        }

        if(!in_array($request->input('role'),Company::MEMBER_TYPE_ARRAY)){
            return redirect(route('company_home', [$company_key]))->with('number_of_users_already_reached', true);
        }

        $newUser = User::getUserByEmail($request->input('email'));
        if (!$newUser) {
            $newUser = User::createUserWhenAnotherAddInformations($request->input('name'), $request->input('email'));
        }



        // -- add role
        $company->users()->attach($newUser, ['role' => $request->input('role') ,'department_id' => $request->input('department_id')]);

        Mail::to($newUser)
            ->send(new InviteUser($newUser, $company));


        return redirect(route('company_home', [$company_key]));


    }

    public function deleteMemberOnCompany(Request $request, $company_key, $userCid) {
        $company = Company::where('key', '=', $company_key)->first();


        if (!$company->userIsAdmin(Auth::user())) {
            return redirect(route('connected_dashboard'));
        }

        $user = User::find(CryptId::unCryptHashToId($userCid));

        // -- Not authorize if user is the same as the one who remove
        if ($user == Auth::user()) {
            return redirect(route('company_home', [$company_key]));
        }

        $company->users()->detach($user);

        return redirect(route('company_home', [$company_key]));

    }

    public function editMemberOnCompany(Request $request, $company_key, $userCid) {
        $company = Company::where('key', '=', $company_key)->first();


        if (!$company->userIsAdmin(Auth::user())) {
            return redirect(route('connected_dashboard'));
        }

        $user = User::find(CryptId::unCryptHashToId($userCid));

        // -- Not authorize if user is the same as the one who remove
        if ($user == Auth::user()) {
            return redirect(route('company_home', [$company_key]));
        }

//        die('ok');

        $departmentsArray = [];
        foreach ($company->departments as $department){

            $departmentsArray[$department->id] =  $department->name;
        }

        $userPivot = $company->users()->where('user_id','=',$user->id)->withPivot(['role','department_id'])->first();
        if($request->isMethod("POST")){

            $userPivot->pivot->role = $request->role;
            $userPivot->pivot->department_id = $request->department_id;
            $userPivot->pivot->save();


            return redirect(route('company_home',['company_key'=>$company->key]));
        }


        return view('member.form',[
            'company' => $company,
            'user' => $user,
            'rolesArray' => array_combine(Company::MEMBER_TYPE_ARRAY,Company::MEMBER_TYPE_ARRAY),
            'departments' => $departmentsArray,
            'userPivot' => $userPivot
            ]);
//        return redirect(route('company_home', [$company_key]));

    }

    /**
     * ACCOUNT
     */
    public function account(Request $request, $company_key) {


        $company = Company::where('key', '=', $company_key)->first();

        //return $company->name;
        if (!$company->userCanManage(Auth::user())) {
            return redirect(route('connected_dashboard'));
        }



        $accountsType =  AccountType::get();


        return view('company.account_home', [
            'company' => $company,
            'accountsType' => $accountsType,
            'company_key' => $company_key,
        ]);
    }
    public function doChange(Request $request, $company_key, $accountKey) {
        $company = Company::where('key', '=', $company_key)->first();

        //return $company->name;
        if (!$company->userCanManage(Auth::user())) {
            return redirect(route('connected_dashboard'));
        }


        $accountsType =  AccountType::get();

//        $today = date('Y-m-d 00:00:00');
//        $endDate = date('Y-m-d 00:00:00', strtotime("+1 months +1 day", strtotime($today)));

        $accountBuying = new AccountBuying;
        $accountBuying->account_type    = $accountKey;
        $accountBuying->company_id      = $company->id;
        $accountBuying->start_date      =  Carbon::now();
        $accountBuying->end_date        = Carbon::now()->addMonth();
        $accountBuying->save();


        return redirect(route('company_account_home',['company_key'=>$company_key]));


    }


    public function listCompanies(Request $request) {
        $user = Auth::user();
        $companies = $user->companies;



        return view('company.list',[
            'companies'=>$companies,
            'company' => null
        ]);


    }

    public function editDepartment(Request $request, $company_key, $cid = null) {
        $company = Company::where('key', '=', $company_key)->first();
        if(!$company){
            return redirect(route('connected_dashboard'));
        }
        // -- test if user can send test for this company
        $user = Auth::user();
        if (!$company->userIsMember(Auth::user())) {
            return redirect(route('connected_dashboard'));
        }

        $department = new Department();
        if($cid != null){
            $department = Department::find(CryptId::unCryptHashToId($cid));

        }

        if($request->isMethod("POST")){
            $department->name = $request->input('name');
            $department->company_id = $company->id;
            $department->view_weight = $request->input('view_weight');

            $department->save();
            return redirect(route('company_home',['company_key'=>$company->key]));
        }

        return view('company.department_form',[
            'company' => $company,
            'department' => $department,
        ]);
    }
}
