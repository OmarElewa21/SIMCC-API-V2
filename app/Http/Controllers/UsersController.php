<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PasswordReset;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPassword;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsersController extends Controller
{
    private $secret_key = "8A0B0DC579F3DA6A417409BFB9071FDA663457F347B735D621FC9B3AA90796D3";

    public function login(Request $request)
    {
        // Request Validation
        $request->validate([
            'identifier' => 'required',
            'password' => 'required'
        ]);

        if(filter_var($request->identifier, FILTER_VALIDATE_EMAIL)){
            $identifier = 'email';
        }else{
            $identifier = 'username';
        }

        // Check if user exists
        if( User::where($identifier, $request->identifier)->doesntExist() ){
            return response()->json(["message" => "User not found"], 404);
        }

        // Auth attempt for given credintials
        if(!auth()->attempt([$identifier => $request->identifier, 'password' => $request->password])){
            return response()->json(["message" => "Invalid credentials"], 422);
        }

        $user = User::where($identifier, $request->identifier)->with(['role:id,name,uuid'])->firstOrFail();      // get user
        $authToken = $user->createToken("auth_token")->plainTextToken;                                        // generate token

        return response()->json([
            "user"    =>   $user,
            "token"   =>   $authToken
        ], 200);
    }

    public function logout()
    {
        if(!auth()->check()){
            return response()->json(["message" => "No User Authinticated"], 400);
        }
        auth()->user()->tokens()->delete();
        return response()->json(["message" => "User logged out successfully"], 200);
    }

    public function sendResetLink(Request $request){
        if(filter_var($request->identifier, FILTER_VALIDATE_EMAIL)){
            $identifier = 'email';
        }else{
            $identifier = 'username';
        }

        if($this->secret_key !== $request->secret_key){
            return response()->json(['message' => 'Unauthorized Request'], 401);
        }
        if(User::where($identifier, $request->identifier)->doesntExist()){
            return response()->json(['message' => "Username doesn't exists"], 404); 
        }else{
            $user = User::where($identifier, $request->identifier)->first();
        }

        if(PasswordReset::where('username', $request->username)->exists()){
            PasswordReset::where('username', $request->username)->delete();
        }

        $user_key = Str::random(10);
        PasswordReset::create([
            'username'      => $user->username,
            'user_key'      => $user_key,
            'created_at'    => now()->toDateTimeString()
        ]);

        $link = 'https://simcc.org/reset_password?username='. 
                    $user->username . '&' . 'user_key=' . $user_key;

        try {
            Mail::to($user)->send(new ResetPassword($link));
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Mail sent successfully'], 200);
    }

    public function changePassword(ChangePasswordRequest $request){
        $user = User::where('username', $request->username)->firstOrFail();
        if(PasswordReset::where('username', $request->username)->value('user_key') !== $request->user_key){
            return response()->json(['message' => 'user key and username doesnnot match'], 401);
        }
        $user->update([
            'password'  => bcrypt($request->password),
        ]);
        PasswordReset::where('username', $request->username)->delete();
        return response()->json(['message' => 'Password changed successfully'], 200);
    }

    /************************************ Index Section *************************************************/
    protected function getIndexData($data){
        return $data->with('personal_access:tokenable_id,last_used_at as last_login')
                ->select('users.*', 'r.name as role', 'c.name as country');
    }

    /**
     * Get User model with the correct data based on role
     * @return User model
     */
    protected function indexfilterByRole(){
        switch (auth()->user()->role->name) {
            case 'super admin':
                $data = User::withTrashed()->whereRelation('role', 'name', '<>', 'super admin');
                break;
            case 'admin':
                $data = User::withTrashed()->whereRelation('role', 'name', '<>', 'super admin')->whereRelation('role', 'name', '<>', 'admin');
                break;
            case 'country partner':
                $data = User::withTrashed()
                        ->whereRelation('countryPartnerAssistant', 'country_partner_id', auth()->id())
                        ->orWhereRelation('schoolManager', 'country_partner_id', auth()->id())
                        ->orWhereRelation('teacher', 'country_partner_id', auth()->id());
                break;
            case 'country partner assistant':
                $data = User::withTrashed()
                        ->whereRelation('schoolManager', 'country_partner_id', auth()->user()->countryPartnerAssistant->country_partner_id)
                        ->orWhereRelation('teacher', 'country_partner_id', auth()->user()->countryPartnerAssistant->country_partner_id);
                break;
            case 'school manager':
                $data = User::withTrashed()->whereRelation('role', 'name', '=', 'teacher')
                            ->whereRelation('teacher', 'school_id', '=', auth()->user()->school->id);
                break;
            default:
                // Todo custom roles
                break;
        }
        return $data;
    }

     /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = $this->indexfilterByRole();

        if($request->has('filterOptions')){
            $request->validate([
                'filterOptions'                 => 'array',
                'filterOptions.role'            => 'exists:roles,name',
                'filterOptions.country'         => 'exists:countries,id',
                'filterOptions.status'          => ['string', Rule::in(['enabled', 'disabled', 'deleted'])]
            ]);
            $data = User::applyFilter($request->get('filterOptions'), $data);
        }
        $filterOptions = User::getFilterForFrontEnd($data);
        
        return response($filterOptions->merge($this->getIndexData($data)
                ->paginate(is_numeric($request->paginationNumber) ? $request->paginationNumber : 5)
                )->forget(['links', 'first_page_url', 'last_page_url', 'next_page_url', 'path', 'prev_page_url']), 200);
    }

    public function mass_enable(Request $request)
    {
        DB::beginTransaction();
        try {
            foreach($request->all() as $user_uuid){
                if(Str::isUuid($user_uuid) && User::withTrashed()->whereUuid($user_uuid)->exists()){
                    $user = User::withTrashed()->whereUuid($user_uuid)->firstOrFail();
                    $user->update([
                        'status'        => 'enabled',
                        'deleted_at'    => null,
                        'deleted_by'    => null
                    ]);
                }else{
                    throw new Exception("data is not valid");
                }
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(["message" => $e->getMessage()], 500);
        }
        DB::commit();
        return $this->index(new Request);
    }

    public function mass_disable(Request $request)
    {
        DB::beginTransaction();
        try {
            foreach($request->all() as $user_uuid){
                if(Str::isUuid($user_uuid) && User::withTrashed()->whereUuid($user_uuid)->exists()){
                    $user = User::withTrashed()->whereUuid($user_uuid)->firstOrFail();
                    $user->update([
                        'status'        => 'disabled',
                        'deleted_at'    => null,
                        'deleted_by'    => null
                    ]);
                }else{
                    throw new Exception("data is not valid");
                }
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(["message" => $e->getMessage()], 500);
        }
        DB::commit();
        return $this->index(new Request);
    }

    public function mass_delete(Request $request)
    {
        DB::beginTransaction();
        try {
            foreach($request->all() as $user_uuid){
                if(Str::isUuid($user_uuid) && User::whereUuid($user_uuid)->exists()){
                    $user = User::whereUuid($user_uuid)->firstOrFail();
                    $user->update([
                        'status'        => 'deleted',
                        'deleted_by'    => auth()->id()    
                    ]);
                    $user->delete();
                }else{
                    throw new Exception("data is not valid");
                }
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(["message" => $e->getMessage()], 500);
        }
        DB::commit();
        return $this->index(new Request);
    }

    /**
     * Get profile info for authinticated user
     */
    public function profile()
    {
        $data = User::where('users.id', auth()->id())->joinRelationship('role')->leftJoinRelationship('country')
                            ->select('users.name', 'users.uuid', 'users.email', 'users.about', 'countries.name as country',
                                        'users.created_at as user_since', 'users.img', 'users.username', 'roles.name as role');

        switch (auth()->user()->role->name) {
            case 'country partner':
                $data->joinRelationship('countryPartner.organization')
                        ->addSelect('organizations.name as organization');
                break;
            case 'country partner assistant':
                $data->joinRelationship('countryPartnerAssistant.countryPartner.organization')
                        ->addSelect('organizations.name as organization');
                break;
            case 'school manager':
                $data->joinRelationship('schoolManager.countryPartner.organization')
                        ->joinRelationship('schoolManager.school')
                        ->addSelect('organizations.name as organization', 'schools.name as school');
                break;
            case 'teacher':
                $data->joinRelationship('schoolManager.countryPartner.organization')
                        ->joinRelationship('schoolManager.school')
                        ->addSelect('organizations.name as organization', 'schools.name as school');
                break;
            default:
                break;
        }
        
        return response($data->firstOrFail()->makeVisible('img', 'about'), 200);
    }

    /**
     * update user profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate(['mode' => 'required|in:user_data,user_password']);
        if($request->mode === 'user_data'){
            $request->validate([
                'name'      => 'string|max:132',
                'email'     => 'string|max:132',
                'about'     => 'string',
                'img'       => 'string',
            ]);
            
            auth()->user()->update($request->all());
        }else{
            $request->validate([
                'password'              => ['required',
                                                Password::min(8)
                                                    ->letters()
                                                    ->numbers()
                                                    ->symbols()
                                                    ->uncompromised(), 'confirmed'],
            ]);
            auth()->user()->update([
                'password'      =>  bcrypt($request->password),
            ]);
        }

        return $this->profile();
    }
}
