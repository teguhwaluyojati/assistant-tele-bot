<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\LoginModel;
use Illuminate\Support\Facades\Log;
use App\Traits\ApiResponse;


class DashboardController extends Controller
{
    protected $model_login;
    use ApiResponse;

    public function __construct()
    {
        $this->model_login = new LoginModel();
    }
    public function getUsers()
    {
        $users = TelegramUser::latest('last_interaction_at')->paginate(15);

        return response()->json($users);
    }

    public function lastLogin()
    {
        Log::info('Cek: Fungsi lastLogin dipanggil');
       try{

        $data = $this->model_login->lastLogin();
        if(!$data){
            return $this->errorResponse('No login history found', 404);

            Log::error('No login history found for user: ' . auth()->user()->email);
        }

        return $this->successResponse($data, 'Login history retrieved successfully.');

       }catch(\Exception $e){
        Log::error('Error retrieving login history: ' . $e->getMessage());

        return $this->errorResponse('An error occurred while retrieving login history.', 500);
       }
    }

}
