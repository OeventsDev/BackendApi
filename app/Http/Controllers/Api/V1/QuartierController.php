<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\V1\Quartier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

/**
 * @group Gestion des Quartiers
 * Gestion de parametrage de l'application Haola+
 */
class QuartierController extends BaseController
{
    /**
     * Liste des Quartiers.
     * */

    public function index()
    {
        $args = array();
        $args['quartiers'] = Quartier::with('ville')->get();
        return $this->sendResponse($args, 'Liste des quartiers.');
    }

    /**
     * Enregistrement de quartier.
     */
    public function store(Request $request)
    {
        $args = array();
        $args['error'] = false;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'ville_id' => 'required',

        ]);
        if ($validator->fails()) {
            return $this->sendError('Erreur lors de la validation des données.', $validator->errors(), 422);
        }
        try {
            DB::beginTransaction();
            $quartier = new Quartier();
            $quartier->name = $request->name;
            $quartier->ville_id = $request->ville_id;
            $quartier->save();
            DB::commit();
            $args['quartier'] = $quartier;
            return $this->sendResponse($args, 'Element enregistrer avec succès.', 201);
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            return $this->sendError("Erreur lors de l'enregistrement des données", $args, 500);
        }

    }

    /**
     * details du quartier.
     */
    public function show($quartier_id)
    {
        $args = array();
        $args['error'] = false;
        if(is_null($quartier_id)){
            return $this->sendError("Veuillez fournir l'id du quartier", $args, 404);
        }
        $quartier = Quartier::whereId($quartier_id)->first();
        if (is_null($quartier)) {
            $args['error'] = true;
            return $this->sendError("Impossible de voir les informations de ce quartier", $args, 404);
        }
        $args['quartier'] = $quartier;
        return $this->sendResponse($args, 'Details du quartier.');

    }


    /**
     * modification du quartier.
     */
    public function update(Request $request, $quartier_id)
    {
        $args = array();
        $args['error'] = false;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'ville_id' => 'required',

        ]);
        if ($validator->fails()) {
            $args['error'] = true;
            $args['data'] = $validator->errors();
            return $this->sendError('Erreur lors de la validation des données.', $args, 422);
        }
        try {
            $quartier = Quartier::find($quartier_id);
            if (is_null($quartier)) {
                $args['error'] = true;
                return $this->sendError("Impossible de modifier les informations de ce quartier", $args, 404);
            }
            DB::beginTransaction();
            $quartier->name = $request->name;
            $quartier->ville_id = $request->ville_id;
            $quartier->update();
            DB::commit();
            $args['quartier'] = $quartier;
            return $this->sendResponse($args, 'Element enregistrer avec succès.');
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            $args['message'] = $e->getMessage();
            return $this->sendError("Erreur lors de l'enregistrement des données", $args, 500);
        }
    }

    /**
     * Suppression quartier.
     */
    public function destroy(Request $request, $quartier_id)
    {
        $args = array();
        $args['error'] = false;
        try {
            $quartier = Quartier::find($quartier_id);
            if (!$quartier) {
                $args['error'] = true;
                return $this->sendError("Impossible  de supprimer ce quartier", $args, 404);
            }
            DB::beginTransaction();
            $quartier->delete();
            DB::commit();
            return $this->sendResponse($args, 'Element supprimer avec succès.');
        } catch (\Exception $e) {
            DB::rollback();
            $args['error'] = true;
            return $this->sendError("Erreur lors de la suppression des données", $args, 500);
        }
    }
}
