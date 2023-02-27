<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\V1\Pays;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;



class PaysController extends BaseController
{

    public function index()
    {
        $args = array();
        $args['pays'] = Pays::with('regions')->get();
        return $this->sendResponse($args, 'Liste des pays.');
        // return json_decode(Pays::with('regions')->get());
    }

    /**
     * Enregistrement de pays.
     */
    public function store(Request $request)
    {
        $args = array();
        $args['error'] = false;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'code' => 'required',
            'indicatif' => 'required',

        ]);
        if($validator->fails()){
            return $this->sendError('Erreur lors de la validation des données.', $validator->errors(), 422);
        }
        try {
            DB::beginTransaction();
            $pays = new Pays();
            $pays->name = $request->name;
            $pays->indicatif = $request->indicatif;
            $pays->code = $request->code;
            $pays->save();
            DB::commit();
            $args['pays'] = $pays;
            return $this->sendResponse($args, 'Element enregistrer avec succès.', 201);
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            return $this->sendError("Erreur lors de l'enregistrement des données", $args, 500);
        }

    }

    /**
     * details d'un pays.
     */
    public function show($pays_id)
    {
        $args = array();
        $args['error'] = false;


        if(is_null($pays_id)){
            return $this->sendError("Veuillez fournir l'id du pays", $args, 404);
        }
        $pays = Pays::whereId($pays_id)->with('regions')->first();
        if (is_null($pays)) {
            return $this->sendError("Impossible de voir les informations de ce pays", $args, 404);
        }
        $args['pays'] = $pays;
        return $this->sendResponse($args, 'Details du pays.');

    }


    /**
     * modification de pays.
     */
    public function update(Request $request, $pays_id)
    {
        $args = array();
        $args['error'] = false;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'code' => 'required',
            'indicatif' => 'required',

        ]);
        if($validator->fails()){
            $args['error'] = true;
            $args['data'] = $validator->errors();
            return $this->sendError('Erreur lors de la validation des données.', $args, 422);
        }
        try {
            $pays = Pays::find($pays_id);
            if(is_null($pays)){
                $args['error'] = true;
                return $this->sendError("Impossible de trouver les informations de ce pays", $args, 404);
            }
            DB::beginTransaction();
            $pays->name = $request->name;
            $pays->indicatif = $request->indicatif;
            $pays->code = $request->code;
            $pays->update();
            DB::commit();
            $args['pays'] = $pays;
            return $this->sendResponse($args, 'Element enregistrer avec succès.');
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            $args['message'] = $e->getMessage();
            return $this->sendError("Erreur lors de l'enregistrement des données", $args, 500);
        }
    }
    /**
     * Suppression  pays.
     */
    public function destroy(Request $request, $pays_id)
    {
        $args = array();
        $args['error'] = false;
        try {
            $pays = Pays::find($pays_id);
            if(!$pays){
                return $this->sendError("Impossible  de supprimer ce pays", "", 404);
            }
            DB::beginTransaction();
            $pays->delete();
            DB::commit();
            return $this->sendResponse($args, 'Element supprimer avec succès.');
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            return $this->sendError("Erreur lors de la suppression des données", "", 500);
        }
    }
}
