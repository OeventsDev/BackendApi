<?php


namespace App\Http\Controllers\Api\V1;


use App\Helpers\LogActivity;
use App\Http\Controllers\Api\V1\BaseController as BaseController;
use App\Mail\SendCodeResetPassword;
use App\Models\V1\ResetCodePassword;
use App\Models\V1\User;
use App\Models\V1\UserSmsCode;
use App\Models\V1\VerificationCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use Twilio\Rest\Client;
use Validator;


/**
 * @group Authentification
 *
 * Liste des requêtes  pour l'authentification
 */
class AuthentificationController extends BaseController
{
    /**
     * requêtes d'enregistrement
     *
     * Ce point de terminaison créera un nouvel utilisateur a l'aide des informations de base suivante
     *
     * Le champ adresse e-mail est obligatoire quand telephone n'est pas présent ou le champ telephone est obligatoire quand adresse e-mail n'est pas présent.
     * @bodyParam nom string required le nom de l'utilisateur. Example: Abalo
     * @bodyParam prenom string required le prenom de l'utilisateur. Example: Jack
     * @bodyParam email string required l'email de l'utilisateur. Example: exemple@exemple.com
     * @bodyParam telephone int required le numéro de téléphone de l'utilisateur précéder de l'indicatif. Example: 22890909090
     * @bodyParam pays_id int required l'id du pays. Example: 3
     * @bodyParam role_id int required l'id du role de l'utilisateur. Example: 3
     * @bodyParam parent_id int l'id de l'utilisateur parent. Example: 1
     * @bodyParam password string required mot de passe de l'utilisateur. Example: p@ssW@rd1010
     * @bodyParam c_password string required confirmation du mot de passe de l'utilisateur. Example:  p@ssW@rd1010
     *
     * @response  201 {
     *  "success": true,
     *  "data" : {
     *      "user": {
     *          "nom": "Abalo",
     *          "prenom": "Jack",
     *          "email": "exemple@exemple.com",
     *          "telephone": "90909090",
     *          "updated_at": "2022-10-12T16:17:48.000000Z",
     *          "created_at": "2022-10-12T16:17:48.000000Z",
     *           "id": 3,
     *           "user_extend_infos" : [
     *              {
     *              "id": 107,
     *               "user_id": 162,
     *               "role_id": 3,
     *               "parent_id": 1,
     *               "raison_sociale": null,
     *               "fichier": null,
     *                  "role" {
     *                      "value" : "admin",
     *                          "permissions" : []
     *                      }
     *                  }
     *              ]
     *          }
     *   }
     *  "message": "Utilisateur enregistrer avec success.",
     * }
     * @response  422 {
     *  "success": false,
     *  "data" : {
     *      "telephone" : "The telephone field is required.",
     *   }
     *  "message": "Erreur lors de la validation des données.",
     * }
     * @response  422 {
     *  "success": false,
     *  "data" : {
     *      "email": [
     *          "La valeur du champ adresse e-mail est déjà utilisée."
     *          ],
     *  "telephone": [
     *          "La valeur du champ telephone est déjà utilisée."
     *          ]
     *   },
     *  "message": "Erreur lors de la validation des données.",
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Erreur.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     *
     * @unauthenticated
     */


    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'nom' => 'required',
            'prenom' => 'required',
            'email' => 'required_without:telephone|email|unique:users',
            'telephone' => 'required_without:email|unique:users',
            'pays_id' => 'required',
            'role_id' => 'required',
            'password' => 'required',
            'c_password' => 'required|same:password',

        ]);
        if ($validator->fails()) {
            return $this->sendError('Erreur lors de la validation des données.', $validator->errors(), 422);
        }
        $input = $request->all();
        if (empty($request->email)) {
            $input['default_auth'] = 2;
            $default_auth = 2;
        } else {
            $input['default_auth'] = 1;
            $default_auth = 1;
        }
        $input['password'] = bcrypt($input['password']);
        try {
            DB::beginTransaction();
            $user = User::create($input);
            if ($default_auth == 1) {
                $urlsend = $user->sendEmailVerificationNotification();
            } else {
                $error = $this->generateSMSCode($user->id, $request->telephone, $request->pays_id);
                if ($error == false) {
                    DB::commit();
                } else {
                    DB::rollback();
                    return $this->sendError('Erreur.', ['error' => 'Erreur lors du traitement des données'], 500);
                }
            }
            DB::commit();
            $args['user'] = $user;
            LogActivity::addToLog('Enregistrement utilisateur', $args);
            return $this->sendResponse($args, 'Utilisateur enregistrer avec success.', 201);
        } catch (\Throwable $e) {
            DB::rollback();
            return $this->sendError('Erreur.', ['error' => 'Erreur lors du traitement des données' . $e], 500);
        }
    }

    /**
     * verification url de confirmation de l'email
     * @urlParam id number required l'id de l'utilisateur. Example: 2
     * Ce point de terminaison permet de verifier l'url de confirmation de l'email
     * @response  200 {
     *  "success":true,
     *  "message":"Votre mail a été confirmer avec sucèss.",
     *  "data":{
     *  }
     * }
     * @response  401 {
     *  "success":false,
     *  "message":"Erreur.",
     *  "data":{
     *      "error":"Cet email a \u00e9t\u00e9 deja confirmer"
     *  }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     *
     * @unauthenticated
     */

    public function verify($user_id, Request $request)
    {
        try {
            if (!$request->hasValidSignature()) {
                return $this->sendError('Erreur.', ['error' => 'URL non valide/expirée'], 401);
            }
            $user = User::findOrFail($user_id);

            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                return $this->sendResponse([], 'Votre mail a été confirmer avec sucèss', 200);
            } else {
                return $this->sendError('Cet email a été deja confirmer', ['error' => 'Cet email a été deja confirmer'], 401);
            }
        } catch (\Throwable $e) {
            return $this->sendError('Erreur.', ['error' => 'Erreur lors du traitement des données' . $e], 500);
        }
    }


    /**
     * verification code OTP de confirmation inscription
     *
     * Ce point de terminaison permet de verifier le code OTP  envoyer sur le numéro de telephone de l'utilisateur
     * @urlParam id number required l'id de l'utilisateur. Example: 2
     * @bodyParam verification_code int required le code otp précedement envoyer. Example: 665862
     * @response  200 {
     *  "success":true,
     *  "message":"Votre telephone a été confirmer avec sucèss",
     *  "data":{
     *  }
     * }
     * @response  401 {
     *  "success":false,
     *  "message":"Erreur.",
     *  "data":{
     *      "error":"code non valide"
     *  }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     *
     * @unauthenticated
     */
    public function confirmeTelephone($user_id, Request $request)
    {
        $data = $request->validate([
            'verification_code' => ['required', 'numeric'],
        ]);

        $user = User::findOrFail($user_id);
        $finalnumber = "+" . $user->telephone;
        try {
            $verification = $this->verifyOTP($finalnumber, $request->verification_code);
            if ($verification) {
                $user->telephone_verified_at = now();
                $user->save();
                return $this->sendResponse([], 'Votre telephone a été confirmer avec sucèss', 200);
            } else {
                return $this->sendError('Erreur.', ['error' => 'code non valide'], 401);
            }
        } catch (\Throwable $e) {
            return $this->sendError('Erreur.', ['error' => 'Erreur lors du traitement des données' . $e], 500);
        }
    }


    /**
     * Mot de passe oublié ? verification code OTP et creation mot de passe
     *
     * Ce point de terminaison permet de verifier le code OTP  envoyer sur le numéro de telephone de l'utilisateur, si le code est bon le nouveau mot de passe est creer
     * @bodyParam telephone int required le numéro de téléphone de l'utilisateur. Example: 90909090
     * @bodyParam verification_code int required le code otp précedement envoyer. Example: 665862
     * @bodyParam password string required mot de passe de l'utilisateur. Example: p@ssW@rd1010
     * @bodyParam c_password string required confirmation du mot de passe de l'utilisateur. Example:  p@ssW@rd1010
     * @response  200 {
     *  "success":true,
     *  "message":"Votre telephone a été confirmer avec sucèss",
     *  "data":{
     *  }
     * }
     * @response  401 {
     *  "success":false,
     *  "message":"Erreur.",
     *  "data":{
     *      "error":"code non valide"
     *  }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     *
     * @unauthenticated
     */
    public function confirmeTelephonePassRenitialise(Request $request)
    {
        $data = $request->validate([
            'verification_code' => ['required', 'numeric'],
            'telephone' => 'required',
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);

        $user = User::whereTelephone($request->telephone)->first();
        $finalnumber = "+" . $user->telephone;
        try {
            $verification = $this->verifyOTP($finalnumber, $request->verification_code);
            if ($verification) {
                // update user password
                $user->password = bcrypt($request->password);
                $user->save();
                return $this->sendResponse([], 'le mot de passe a été réinitialisé avec succès', 200);
            } else {
                return $this->sendError('Erreur.', ['error' => 'code non valide'], 401);
            }
        } catch (\Throwable $e) {
            return $this->sendError('Erreur.', ['error' => 'Erreur lors du traitement des données' . $e], 500);
        }
    }

    /**
     * renvoyer l'url de confirmation de l'email
     *
     * Ce point de terminaison permet de renvoyer  l'url de confirmation de l'email à l'utilisateur
     *
     * @response  200 {
     *  "success":true,
     *  "message":"Lien de vérification de l'e-mail envoyé sur votre identifiant de messagerie.",
     *  "data": []
     * }
     * @response  400 {
     *  "success":false,
     *  "message":"Verification Email.",
     *  "data":{
     *      "error":"Email déjà vérifié"
     *  }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Verification Email.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     *
     */
    public function resend()
    {
        try {
            if (auth()->user()->hasVerifiedEmail()) {
                return $this->sendError('Verification Email..', ['error' => 'Email déjà vérifié'], 400);
            }
            auth()->user()->sendEmailVerificationNotification();
            return $this->sendResponse([], 'Lien de vérification de l\'e-mail envoyé sur votre identifiant de messagerie.', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Verification Email.', ['error' => 'Erreur lors du traitement des données' . $e], 500);
        }
    }

    /**
     * renvoyer code OTP de confirmation du telephone
     *
     * Ce point de terminaison permet de renvoyer  OTP de confirmation du telephone à l'utilisateur
     *
     * @response  200 {
     *  "success":true,
     *  "message":"code otp envoyer sur votre numéro de telephone",
     *  "data": []
     * }
     * @response  400 {
     *  "success":false,
     *  "message":"Verification Email.",
     *  "data":{
     *      "error":"Telephone déjà vérifié"
     *  }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Verification Telephone.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     *
     */
    public function resendOTP()
    {
        try {

            if (auth()->user()->telephone_verified_at) {
                return $this->sendError('Verification Telephone..', ['error' => 'Telephone déjà vérifié'], 400);
            }
            $error = $this->generateSMSCode(auth()->user()->id, auth()->user()->telephone, auth()->user()->pays_id);
            if ($error == false) {
                return $this->sendResponse([], 'code otp envoyer sur votre numéro de telephone.', 200);
            } else {
                return $this->sendError('Verification Telephone.', ['error' => 'Erreur lors du traitement des données'], 500);
            }
        } catch (\Throwable $e) {
            return $this->sendError('Verification Telephone.', ['error' => 'Erreur lors du traitement des données' . $e], 500);
        }
    }

    /**
     * modification information utilisateur
     *
     * Ce point de terminaison permet de modifier les informations de l'utilisateur
     *
     * @bodyParam nom string required le nom de l'utilisateur. Example: Abalo
     * @bodyParam prenom string required le prenom de l'utilisateur. Example: Jack
     * @bodyParam email string required l'email de l'utilisateur. Example: exemple@exemple.com
     * @bodyParam telephone int required le numéro de téléphone de l'utilisateur. Example: 90909090
     * @bodyParam password string required mot de passe de l'utilisateur. Example: p@ssW@rd1010
     * @bodyParam c_password string required confirmation du mot de passe de l'utilisateur. Example:  p@ssW@rd1010
     *
     * @response  200 {
     *  "success": true,
     *  "data" : {
     *      "token" : "3|QD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1V",
     *      "user": {
     *          "nom": "Abalo",
     *          "prenom": "Jack",
     *          "email": "exemple@exemple.com",
     *          "telephone": "90909090",
     *          "updated_at": "2022-10-12T16:17:48.000000Z",
     *          "created_at": "2022-10-12T16:17:48.000000Z",
     *           "id": 3
     *          }
     *   }
     *  "message": "Utilisateur enregistrer avec success.",
     * }
     * @response  422 {
     *  "success": false,
     *  "data" : {
     *      "telephone" : "The telephone field is required.",
     *   }
     *  "message": "Erreur lors de la validation des données.",
     * }
     * @response  403 {
     *  "success": false,
     *  "data" : {
     *      "error" : "cet email existe deja dans la base de donnée",
     *   },
     *  "message": "Email existe.",
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     *
     */
    public function EditUser(Request $request)
    {

        $validator = Validator::make($request->all(), [

            'nom' => 'required',
            'prenom' => 'required',

        ]);
        if ($validator->fails()) {

            return $this->sendError('Erreur lors de la validation des données.', $validator->errors(), 422);
        }
        $input = $request->all();

        $input['password'] = bcrypt($input['password']);

        try {
            $user = User::create($input);
            $args['token'] = $user->createToken('MyApp')->plainTextToken;
            $args['user'] = $user;
            LogActivity::addToLog('modification information utilisateur', $args);
            return $this->sendResponse($args, 'Utilisateur enregistrer avec success.', 201);
        } catch (\Illuminate\Database\QueryException $e) {

            return $this->sendError('Email existe.', ['error' => 'cet email existe deja dans la base de donnée'], 403);
        } catch (\Throwable $e) {
            return $this->sendError('Error.', ['error' => 'Erreur lors du traitement des données'], 500);
        }
    }

    /**
     * requêtes de connexion.
     *
     * Ce point de terminaison permet a l'utilisateur de se connecter à la plateforme Haola+ a l'aide de son email/téléphone et son mot de passe.
     * @bodyParam username string required l'email ou le téléphone de l'utilisateur. Example: exemple@exemple.com
     * @bodyParam password string required mot de passe de l'utilisateur. Example: p@ssW@rd1010
     * @unauthenticated
     * @response  200 {
     *  "success": true,
     *  "data" : {
     *      "token" : "3|QD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1V",
     *      "user": {
     *          "id": 3,
     *           "nom": "Abalo",
     *           "prenom": "Jack",
     *           "telephone": "90909090",
     *           "firebase_token": "QD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1VQD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1VQD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1V",
     *           "email": "exemple@exemple.com",
     *           "email_verified_at": "2022-10-12T16:17:48.000000Z",
     *           "created_at": "2022-10-12T16:17:48.000000Z",
     *           "updated_at": "2022-10-12T16:17:48.000000Z",
     *           "deleted_at": null,
     *           "google_id": "QD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1V",
     *           "facebook_id": "QD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1V"
     *          }
     *   }
     *  "message": "Connexion effectuée avec succès.",
     * }
     *
     * @response  401 {
     *  "success": false,
     *  "message": "verification Email.",
     *  "data" : {
     *      "error" : "Lien de vérification de l'e-mail envoyé sur votre identifiant de messagerie",
     *   }
     * }
     * @response  401 {
     *  "success": false,
     *  "message": "verification Telephone.",
     *  "data" : {
     *      "error" : "Code otp envoyé sur votre numéro de telephone",
     *   }
     * }
     * @response  403 {
     *  "success": false,
     *  "message": "Erreur de connexion.",
     *  "data" : {
     *      "error" : "Email ou mot de passe incorrect",
     *   }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     */

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }
        try {
            $user = User::where('email', $request->username)->orWhere('telephone', $request->username)->first();
            if ($user && Hash::check($request->password, $user->password)) {
                //$user = Auth::user();
                if ($user->default_auth == 1 && !$user->hasVerifiedEmail()) {
                    return $this->sendError('verification Email.', ['error' => 'Lien de vérification de l\'e-mail envoyé sur votre identifiant de messagerie'], 401);
                } elseif ($user->default_auth == 2 && empty($user->telephone_verified_at)) {
                    return $this->sendError('verification telephone.', ['error' => 'code otp envoyé sur votre numéro de telephone'], 401);
                }
                $args['token'] = $user->createToken('MyApp')->plainTextToken;
                $args['user'] = $user;
                return $this->sendResponse($args, 'Connexion effectuée avec succès');
            } else {
                return $this->sendError('Erreur de connexion.', ['error' => 'Email/Telephone ou mot de passe incorrect'], 403);
            }
        } catch (\Throwable $e) {
            return $this->sendError('Error.', ['error' => 'Erreur lors du traitement des données ' . $e->getMessage()], 500);
        }
    }

    /**
     * Details de l'utilisateur.
     * Ce point de terminaison retourne toute les informations relatives à l'utilisateur connecté.
     * @response  200 {
     *  "success": true,
     *  "data" : {
     *      "user": {
     *          "id": 3,
     *           "nom": "Abalo",
     *           "prenom": "Jack",
     *           "telephone": "90909090",
     *           "firebase_token": "QD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1VQD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1VQD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1V",
     *           "email": "exemple@exemple.com",
     *           "email_verified_at": "2022-10-12T16:17:48.000000Z",
     *           "created_at": "2022-10-12T16:17:48.000000Z",
     *           "updated_at": "2022-10-12T16:17:48.000000Z",
     *           "deleted_at": null,
     *           "google_id": "QD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1V",
     *           "facebook_id": "QD2oEneUdcv08XQDxdBxCJaPYbIFNreb3XZmvk1V"
     *          }
     *   }
     *  "message": "Les informations de l'utilisateur.",
     * }
     *
     * @response  401 {
     *  "message": "Unauthenticated."
     * }
     */

    public function userInfo()
    {
        $args = array();
        $args['user'] = $user = auth()->user();
        return $this->sendResponse($args, 'Les informations de l\'utilisateur', 200);
    }


    /**
     * Mot de passe oublié ? envoi du code par email.
     *
     * Ce point de terminaison permet d'initialiser la réinitialisation du mot de passe, l'utilisateur fourni son adresse mail et un code générer lui est envoyé sur cet email.
     * @bodyParam email string required l'email de l'utilisateur. Example: exemple@exemple.com
     * @response  200 {
     *  "message": "Nous vous avons envoyé par courriel le lien de réinitialisation du mot de passe !",
     * }
     * @response  422 {
     *  "message": "Le champ adresse e-mail sélectionné est invalide",
     *  "errors" : {
     *      "email" : ["Le champ adresse e-mail sélectionné est invalide"],
     *   }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     *
     * @unauthenticated
     */
    public function ForgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users',
        ]);
        if ($request->fromFow && $request->fromFow == "accept") {
            $rancode = "999999";
        } else {
            $rancode = mt_rand(100000, 999999);
        }
        try {
            ResetCodePassword::where('email', $request->email)->delete();
            $data['code'] = $rancode;
            $codeData = ResetCodePassword::create($data);
            Mail::to($request->email)->send(new SendCodeResetPassword($codeData->code));
            return response(['message' => trans('passwords.sent')], 200);
        } catch (\Throwable $e) {
            return $this->sendError('Error.', ['error' => 'Erreur lors du traitement des données' . $e], 500);
        }
    }


    /**
     * Mot de passe oublié ? envoi du code par telepehone.
     *
     * Ce point de terminaison permet d'initialiser la réinitialisation du mot de passe, l'utilisateur fourni son numéro de téléphone et un code générer lui est envoyé sur le numéro.
     * @bodyParam telephone number required le numéro de téléphone de l'utilisateur. Example: 90909090
     * @response  200 {
     *  "success": true,
     *  "data": [],
     *  "message": "Code de rénitialisation envoyé avec succès.",
     * }
     * @response  422 {
     *  "message": "Le champ telephone sélectionné est invalide",
     *  "errors" : {
     *      "telephone" : ["Le champ telephone sélectionné est invalide"],
     *   }
     * }
     * @response  401 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Impossible d\'envoyer un code renitialisation à ce numéro",
     *   }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     *
     * @unauthenticated
     */

    public function ForgotPasswordOTP(Request $request)
    {
        $data = $request->validate([
            'telephone' => 'required|exists:users,telephone',
        ]);

        $user = User::whereTelephone($request->telephone)->first();
        if ($user and $user->default_auth == 2) {
            $error = $this->generateSMSCode($user->id, $user->telephone, $user->pays_id);
            if ($error == false) {
                return $this->sendResponse([], 'Code de rénitialisation envoyé avec succès.', 200);
            } else {
                return $this->sendError('Erreur.', ['error' => 'Erreur lors du traitement des données'], 500);
            }
        } else {
            return $this->sendError('Erreur.', ['error' => 'Impossible d\'envoyer un code renitialisation à ce numéro'], 401);
        }
    }


    /**
     * Mot de passe oublié ? verification du code.
     * Ce point de terminaison permet de verifier le code de réinitialisation du mot de passe précedamment envoyer dans son email.
     * @bodyParam code string required le precedemment envoyé par mail. Example: 894545
     * @response  200 {
     *  "message": "Votre code est valide.",
     *  "code" : "894545"
     * }
     * @response  422 {
     *  "message": "Votre code est expiré.",
     *  "errors" : {
     *      "code" : ["Votre code est expiré"],
     *   }
     * @response  422 {
     *  "message": "Le champ code sélectionné est invalide.",
     *  "errors" : {
     *      "code" : ["Le champ code sélectionné est invalide"],
     *   }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     * @unauthenticated
     */
    public function CodeCheck(Request $request)
    {
        $request->validate([
            'code' => 'required|exists:reset_code_passwords',
        ]);
        try {
            // find the code
            $passwordReset = ResetCodePassword::firstWhere('code', $request->code);
            // check if it does not expired: the time is one hour
            if ($passwordReset->created_at > now()->addHour()) {
                $passwordReset->delete();
                return response(['message' => trans('passwords.code_is_expire')], 422);
            }
            return response([
                'code' => $passwordReset->code,
                'message' => trans('passwords.code_is_valid')
            ], 200);
        } catch (\Throwable $e) {
            return $this->sendError('Error.', ['error' => 'Erreur lors du traitement des données'], 500);
        }
    }

    /**
     * Mot de passe oublié ? création d'un nouveau mot de passe.
     * Ce point de terminaison permet à l'utilisateur de définir un nouveau mot de passe à son compte
     * @bodyParam code int required le precedemment envoyé par mail. Example: 894545
     * @bodyParam password string required mot de passe de l'utilisateur. Example: p@ssW@rd1010
     * @bodyParam confirmed string required confirmation du mot de passe de l'utilisateur. Example:  p@ssW@rd1010
     *
     *
     * @response  200 {
     *  "message": "le mot de passe a été réinitialisé avec succès"
     * }
     * @response  422 {
     *  "message": "Le champ de confirmation mot de passe ne correspond pas.",
     *  "errors" : {
     *      "password" : ["Le champ de confirmation mot de passe ne correspond pas"],
     *   }
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     * @unauthenticated
     */
    public function NewPasswordSend(Request $request)
    {
        $request->validate([
            'code' => 'required|exists:reset_code_passwords',
            'password' => 'required|string|min:6',
        ]);
        try {
            // find the code
            $passwordReset = ResetCodePassword::firstWhere('code', $request->code);
            // check if it does not expired: the time is one hour
            if ($passwordReset->created_at > now()->addHour()) {
                $passwordReset->delete();
                return response(['message' => trans('passwords.code_is_expire')], 422);
            }
            // find user's email
            $user = User::firstWhere('email', $passwordReset->email);
            // update user password
            $user->password = bcrypt($request->password);
            $user->save();
            // delete current code
            $passwordReset->delete();
            return response(['message' => trans('passwords.pass_success_reset')], 200);
        } catch (\Throwable $e) {
            return $this->sendError('Error.', ['error' => 'Erreur lors du traitement des données'], 500);
        }
    }

    /**
     * Google login, redirection sur la page de google
     * @unauthenticated
     */
    /* public function redirectToGoogle()

     {
         return Socialite::driver('google')->redirect();

     }*/

    /**
     * Google login, traitement des informations de retour.
     * @unauthenticated
     */
    /*public function handleGoogleCallback(){

        try {
            $user = Socialite::driver('google')->user();
            $finduser = User::where('google_id', $user->id)->first();
            if($finduser){
                Auth::login($finduser);
                return redirect()->intended('dashboard');
            }else{

                $newUser = User::updateOrCreate(['email' => $user->email],[
                    'name' => $user->name,
                    'google_id'=> $user->id,
                    'password' => encrypt('123456dummy')

                ]);
                Auth::login($newUser);
                return redirect()->intended('dashboard');

            }
        } catch (\Exception $e) {
            dd($e->getMessage());
        }

    }*/

    /**
     * Facebook login, redirection sur la page de Facebook
     * @unauthenticated
     */
    /*  public function facebookRedirect()
      {
          return Socialite::driver('facebook')->redirect();
      }*/

    /**
     * Facebook login, traitement des informations de retour.
     * @unauthenticated
     */
    /*    public function loginWithFacebook()
        {
            try {
                $user = Socialite::driver('facebook')->user();
                $isUser = User::where('fb_id', $user->id)->first();
                if($isUser){
                    Auth::login($isUser);
                    return redirect('/dashboard');
                }else{
                    $createUser = User::create([
                        'name' => $user->name,
                        'email' => $user->email,
                        'fb_id' => $user->id,
                        'password' => encrypt('admin@123')
                    ]);

                    Auth::login($createUser);
                    return redirect('/dashboard');
                }

            } catch (\Exception $exception) {
                dd($exception->getMessage());
            }
        }*/

    /**
     * OTP SMS, Récupération du numéro de téléphone.
     * Ce point de terminaison de generer un code OTP qui est envoyé à l'utilisateur par SMS. l'utilisateur vas fournir son numéro de téléphone
     * @bodyParam mobile_no string required numéro de téléphone de l'utilisateur. Example: 95266366
     * @unauthenticated
     */

    public function generate(Request $request)
    {
        # Validate Data
        $request->validate([
            'mobile_no' => 'required|exists:users,telephone'
        ]);

        # Generate An OTP
        $verificationCode = $this->generateOtp($request->mobile_no);

        $message = "Votre code de vérification est - " . $verificationCode->otp;
        # Return With OTP

        return redirect()->route('otp.verification', ['user_id' => $verificationCode->user_id])->with('success', $message);
    }

    /**
     * OTP SMS, envoi du code sur le numero.
     * @unauthenticated
     */
    public function generateOtp($mobile_no)
    {
        $user = User::where('mobile_no', $mobile_no)->first();

        # User Does not Have Any Existing OTP
        $verificationCode = VerificationCode::where('user_id', $user->id)->latest()->first();

        $now = Carbon::now();

        if ($verificationCode && $now->isBefore($verificationCode->expire_at)) {
            return $verificationCode;
        }

        // Create a New OTP
        return VerificationCode::create([
            'user_id' => $user->id,
            'otp' => rand(123456, 999999),
            'expire_at' => Carbon::now()->addMinutes(10)
        ]);
    }

    /**
     * OTP SMS, Vérification code.
     * @bodyParam user_id string required l'identifiant de l'utilisateur. Example: 3
     * @bodyParam otp string required le code otp envoyé sur numéro de téléphone. Example: 896523
     * @unauthenticated
     */
    public function loginWithOtp(Request $request)
    {
        #Validation
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp' => 'required'
        ]);

        #Validation Logic
        $verificationCode = VerificationCode::where('user_id', $request->user_id)->where('otp', $request->otp)->first();

        $now = Carbon::now();
        if (!$verificationCode) {
            return redirect()->back()->with('error', 'Your OTP is not correct');
        } elseif ($verificationCode && $now->isAfter($verificationCode->expire_at)) {
            return redirect()->route('otp.login')->with('error', 'Your OTP has been expired');
        }

        $user = User::whereId($request->user_id)->first();

        if ($user) {
            // Expire The OTP
            $verificationCode->update([
                'expire_at' => Carbon::now()
            ]);

            Auth::login($user);

            return redirect('/home');
        }

        return redirect()->route('otp.login')->with('error', 'Your Otp is not correct');
    }

    /**
     * requêtes de déconnexion.
     *
     * Ce point de terminaison permet de déconnecter l'utilisateur actuel qui est connecté
     *
     * @response  200 {
     *  "success": true,
     *  "data" : {
     *
     *   }
     *  "message": "Déconnexion effectuée avec succès.",
     * }
     * @response  401 {
     *  "message": "Unauthenticated.",
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     **/

    public function logout(Request $request)
    {
        try {
            $user = Auth::user();
            $user->tokens()->delete();
            return $this->sendResponse(array(), 'Déconnexion effectuée avec succès', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Error.', ['error' => 'Erreur lors du traitement des données ' . $e->getMessage()], 500);
        }
    }
    /**
     * requêtes de suppression de compte.
     *
     * Ce point de terminaison permet à l'utilisateur actuel qui est connecté de supprimer son compte
     *
     * @response  200 {
     *  "success": true,
     *  "data" : {
     *
     *   }
     *  "message": "Suppresion effectuée avec succès.",
     * }
     * @response  401 {
     *  "message": "Unauthenticated.",
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     **/

    public function remove(Request $request)
    {
        try {
            $user = Auth::user();
            $user->tokens()->delete();
            $user->delete();
            return $this->sendResponse(array(), 'Suppresion effectuée avec succès', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Error.', ['error' => 'Erreur lors du traitement des données ' . $e->getMessage()], 500);
        }
    }

    /**
     * requêtes d'activation et désactivation d'un utilisateur.
     *
     * Ce point de terminaison permet d'activer ou désactiver  un utilisateur
     *
     * @urlParam id number required l'id de l'utilisateur. Example: 1
     * @urlParam status number required status à passer dans l'url, 1 pour activation et 0 pour désactivation. Example: 1
     *
     * @response  200 {
     *  "success": true,
     *  "data" : {
     *
     *   }
     *  "message": "Activation/Desactivation",
     * }
     * @response  401 {
     *  "message": "Unauthenticated.",
     * }
     * @response  500 {
     *  "success": false,
     *  "message": "Error.",
     *  "data" : {
     *      "error" : "Erreur lors du traitement des données",
     *   }
     * }
     **/

    public function ActiveDesactiveUser(Request $request, $user_id, $status)
    {
        try {

            User::whereId($user_id)->update(["status" => $status]);
            return $this->sendResponse(array(), 'Activation/Desactivation effectuée avec succès', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Error.', ['error' => 'Erreur lors du traitement des données '], 500);
        }
    }

    public function generateSMSCode($user_is, $telephone, $pays_id)
    {
        $finalnumber = "+" . $telephone;
        $code = rand(100000, 999999);
        UserSmsCode::updateOrCreate([
            'user_id' => $user_is,
            'otp' => $code,
            'telephone' => $finalnumber,
            'expire_at' => Carbon::now()->addMinutes(10)

        ]);
        $message = "votre code Haola+ est " . $code;
        try {
            $token = getenv("TWILIO_AUTH_TOKEN");
            $twilio_sid = getenv("TWILIO_SID");
            $twilio_verify_sid = getenv("TWILIO_VERIFY_SID");
            $twilio = new Client($twilio_sid, $token);
            $twilio->verify->v2->services($twilio_verify_sid)
                ->verifications
                ->create($finalnumber, "sms");
            $status = false;
        } catch (\Exception $e) {
            $status = true;
        }
        return $status;
    }


    protected function verifyOTP($telephone, $code)
    {
        /* Get credentials from .env */
        $token = getenv("TWILIO_AUTH_TOKEN");
        $twilio_sid = getenv("TWILIO_SID");
        $twilio_verify_sid = getenv("TWILIO_VERIFY_SID");
        $twilio = new Client($twilio_sid, $token);

        try {
            $verification = $twilio->verify->v2->services($twilio_verify_sid)
                ->verificationChecks
                ->create(["code" => $code, "to" => $telephone]);
            if ($verification->valid) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
