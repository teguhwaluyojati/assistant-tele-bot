<?php

namespace App\Traits;


trait ApiResponse
{
    /**
     * Standard Success Response
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
     * Standard Error Response
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