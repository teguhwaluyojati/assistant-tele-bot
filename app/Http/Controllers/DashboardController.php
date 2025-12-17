<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\LoginModel;
use Illuminate\Support\Facades\Log;


class DashboardController extends Controller
{
    protected $model_login;

    public function __construct()
    {
        $this->model_login = new LoginModel();
    }
    public function getUsers()
    {
        $users = TelegramUser::latest('last_interaction_at')->paginate(15);

        return response()->json($users);
    }

    public function historyLogin()
    {
       try{

        $data = $this->model_login->lastLogin();
        if(!$data){
            return $this->errorResponse('No login history found', 404);
        }

        return $this->successResponse($data, 'Login history retrieved successfully.');

       }catch(\Exception $e){
        Log::error('Error retrieving login history: ' . $e->getMessage());

        return $this->errorResponse('An error occurred while retrieving login history.', 500);
       }
    }

    /**
     * Format respons JSON yang sukses.
     *
     * @param  mixed  $data
     * @param  string $message
     * @param  int    $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    private function successResponse($data, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Format respons JSON yang gagal.
     *
     * @param  string $message
     * @param  int    $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    private function errorResponse($message, $statusCode = 404, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if($errors !== null){
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}
