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
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param  mixed  $data
     */
    protected function error(string $message = 'Error', int $status = 400, $errors = null)
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }



    protected function notFound(string $message = 'Not Found', $errors = null)
    {
        return $this->error($message, 404, $errors);
    }
    protected function forbidden(string $message = 'Forbidden', $errors = null)
    {
        return $this->error($message, 403, $errors);
    }

}
