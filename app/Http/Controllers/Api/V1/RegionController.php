<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\V1\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

/**
 * @group Gestion des regions
 * Gestion de parametrage de l'application Haola+
 */
class RegionController extends BaseController
{
    /**
     * Liste des pays.
     */
    public function index()
    {
        $args = array();
        $args['regions'] = Region::with('pays')->with('villes')->get();
        return $this->sendResponse($args, 'Liste des regions.');
    }

    /**
     * Enregistrement de region.
     */
    public function store(Request $request)
    {
        $args = array();
        $args['error'] = false;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'pays_id' => 'required',

        ]);
        if ($validator->fails()) {
            return $this->sendError('Erreur lors de la validation des données.', $validator->errors(), 422);
        }
        try {
            DB::beginTransaction();
            $region = new Region();
            $region->name = $request->name;
            $region->pays_id = $request->pays_id;
            $region->save();
            DB::commit();
            $args['region'] = $region;
            return $this->sendResponse($args, 'Element enregistrer avec succès.', 201);
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            return $this->sendError("Erreur lors de l'enregistrement des données", $args, 500);
        }

    }

    /**
     * details d'une region.
     */
    public function show($region_id)
    {
        $args = array();
        $args['error'] = false;
        if(is_null($region_id)){
            return $this->sendError("Veuillez fournir l'id de la region", $args, 404);
        }
        $region = Region::whereId($region_id)->with('pays')->with('villes')->first();
        if (is_null($region)) {
            $args['error'] = true;
            return $this->sendError("Impossible de voir les informations de cette region", $args, 404);
        }
        $args['region'] = $region;
        return $this->sendResponse($args, 'Details de la region.');

    }


    /**
     * modification de la region.
     */
    public function update(Request $request, $regioin_id)
    {
        $args = array();
        $args['error'] = false;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'pays_id' => 'required',

        ]);
        if ($validator->fails()) {
            $args['error'] = true;
            $args['data'] = $validator->errors();
            return $this->sendError('Erreur lors de la validation des données.', $args, 422);
        }
        try {
            $region = Region::find($regioin_id);
            if (is_null($region)) {
                $args['error'] = true;
                return $this->sendError("Impossible de modifier les informations de cette region", $args, 404);
            }
            DB::beginTransaction();
            $region->name = $request->name;
            $region->pays_id = $request->pays_id;
            $region->update();
            DB::commit();
            $args['region'] = $region;
            return $this->sendResponse($args, 'Element enregistrer avec succès.');
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            $args['message'] = $e->getMessage();
            return $this->sendError("Erreur lors de l'enregistrement des données", $args, 500);
        }
    }

    /**
     * Suppression region.
     */

    public function destroy(Request $request, $region_id)
    {
        $args = array();
        $args['error'] = false;
        try {
            $region = Region::find($region_id);
            if (!$region) {
                $args['error'] = true;
                return $this->sendError("Impossible  de supprimer cette region", $args, 404);
            }
            DB::beginTransaction();
            $region->delete();
            DB::commit();
            return $this->sendResponse($args, 'Element supprimer avec succès.');
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            return $this->sendError("Erreur lors de la suppression des données", $args, 500);
        }
    }
}
