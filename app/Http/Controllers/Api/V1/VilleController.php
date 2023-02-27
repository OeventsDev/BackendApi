<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\V1\Ville;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

/**
 * @group Gestion des villes
 * Gestion de parametrage de l'application Haola+
 */
class VilleController extends BaseController
{
    /**
     * Liste des villes.
     */
    public function index()
    {
        $args = array();
        $args['villes'] = Ville::with('region')->with('quartiers')->get();
        return $this->sendResponse($args, 'Liste des villes.');
    }

    /**
     * Enregistrement ville.
     */
    public function store(Request $request)
    {
        // dd($request->header());
        $args = array();
        $args['error'] = false;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'region_id' => 'required',

        ]);
        if ($validator->fails()) {
            return $this->sendError('Erreur lors de la validation des données.', $validator->errors(), 422);
        }
        try {
            DB::beginTransaction();
            $ville = new Ville();
            $ville->name = $request->name;
            $ville->region_id = $request->region_id;
            $ville->save();
            DB::commit();
            $args['ville'] = $ville;
            return $this->sendResponse($args, 'Element enregistrer avec succès.', 201);
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            return $this->sendError("Erreur lors de l'enregistrement des données", $args, 500);
        }

    }

    /**
     * details ville.
     */
    public function show($ville_id)
    {
        $args = array();
        $args['error'] = false;
        if(is_null($ville_id)){
            return $this->sendError("Veuillez fournir l'id de la ville", $args, 404);
        }
        $ville = Ville::whereId($ville_id)->with('region')->with('quartiers')->first();
        if (is_null($ville)) {
            $args['error'] = true;
            return $this->sendError("Impossible de voir les informations de cette ville", $args, 404);
        }
        $args['ville'] = $ville;
        return $this->sendResponse($args, 'Details de la ville.');

    }


    /**
     * modification ville.
     */
    public function update(Request $request, $ville_id)
    {
        $args = array();
        $args['error'] = false;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'region_id' => 'required',

        ]);
        if ($validator->fails()) {
            $args['error'] = true;
            $args['data'] = $validator->errors();
            return $this->sendError('Erreur lors de la validation des données.', $args, 422);
        }
        try {
            $ville = Ville::find($ville_id);
            if (is_null($ville)) {
                $args['error'] = true;
                return $this->sendError("Impossible de modifier les informations de cette ville", $args, 404);
            }
            DB::beginTransaction();
            $ville->name = $request->name;
            $ville->region_id = $request->region_id;
            $ville->update();
            DB::commit();
            $args['ville'] = $ville;
            return $this->sendResponse($args, 'Element enregistrer avec succès.');
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            $args['message'] = $e->getMessage();
            return $this->sendError("Erreur lors de l'enregistrement des données", $args, 500);
        }
    }

    /**
     * Suppression ville.
     */
    public function destroy(Request $request, $ville_id)
    {
        $args = array();
        $args['error'] = false;
        try {
            $ville = Ville::find($ville_id);
            if (!$ville) {
                $args['error'] = true;
                return $this->sendError("Impossible  de supprimer cette ville", $args, 404);
            }
            DB::beginTransaction();
            $ville->delete();
            DB::commit();
            return $this->sendResponse($args, 'Element supprimer avec succès.');
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            return $this->sendError("Erreur lors de la suppression des données", $args, 500);
        }
    }
}
