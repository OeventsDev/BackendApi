<?php


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\V1\User;
use Illuminate\Http\Request;


class BaseController extends Controller

{

    public function sendResponse($result, $message, $code=200)

    {

        $response = [

            'success' => true,

            'data'    => $result,

            'message' => $message,

        ];


        return response()->json($response, $code);

    }


    public function sendError($error, $errorMessages = [], $code = 404)

    {

        $response = [

            'success' => false,

            'message' => $error,

        ];


        if(!empty($errorMessages)){

            $response['data'] = $errorMessages;

        }


        return response()->json($response, $code);

    }

    public function getUserByEmail($email){
       $thisUser = User::whereEmail($email)->first();
       return $thisUser;
    }

}
