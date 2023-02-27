<?php


namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Request;
use App\Models\V1\LogActivity as LogActivityModel;


class LogActivity
{
    public static function addToLog($subject, $response = null)
    {
        try {
            DB::beginTransaction();
            $log = [];
            $log['subject'] = $subject;
            $log['url'] = Request::fullUrl();
            $log['method'] = Request::method();
            $log['ip'] = Request::ip();
            $log['agent'] = Request::header('user-agent');
            $log['response'] = json_encode($response);
            if (auth()->check()) {
                $log['user_id'] = auth()->user()->id;
                $log['user_name'] = auth()->user()->nom . " " . auth()->user()->prenom;
            }
            LogActivityModel::create($log);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
        }

    }


    public static function logActivityLists()
    {
        return LogActivityModel::latest()->get();
    }

    public static function logActivityByUser($user_id)
    {
        return LogActivityModel::where('user_id', $user_id)->get();
    }


}
