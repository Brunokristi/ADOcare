<?php

namespace App\Http\Responses;

trait ApiResponse
{
    /**
     * Return a standardized success JSON response.
     *
     * @param  mixed  $data
     */
    protected function success($data = null, string $message = 'OK', int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param  mixed  $data
     */
    protected function error(string $message = 'Error', int $status = 400, $data = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
