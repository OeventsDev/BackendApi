<?php


namespace App\Http\Controllers\Api\V1;
use App\Helpers\LogActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
/**
 * @group Logging système
 *
 * Liste des requêtes pour un système de gestion d'activité de journal personnalisé
 */
class LogActivityController extends BaseController
{
    public function __construct(){

    }


    /**
     * Liste des logs.
     * Ce point de terminaison permet de lister les logs des utilisateurs enregistré sur la plateforme Haola+
     * @response  200 {
     *  "success": true,
     *  "message": "Liste des logs",
     *  "data" : {
     *   }
     * }
     */
    function logActivity(){
        $args = array();
        $args['logs']  = LogActivity::logActivityLists();
        return $this->sendResponse($args, 'Liste des logs', 200);
    }


    /**
     * Liste des logs utilisateurs.
     * Ce point de terminaison permet de lister les logs d'un utilisateur en particulier enregistré sur la plateforme Haola+
     * @response  200 {
     *  "success": true,
     *  "message": "Liste des logs utilisateurs.",
     *  "data" : {
     *
     *   }
     * }
     */
    public function logActivityByUser($user_id){
        $args = array();
        $args['logs']  = LogActivity::logActivityByUser($user_id);
        return $this->sendResponse($args, 'Liste des logs d\'un utilisateur ', 200);
    }

}
