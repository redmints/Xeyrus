<?php

namespace App\Http\Controllers;

use App\Http\Controllers;

use Symfony\Component\Process\Process;
use Illuminate\Http\Request;
use Session;
use App\Utilisateur;
use Config;
use App\M_Projet;
use App\Droit;

class Ide extends Controller
{
    public function getForm()
    {
        //Déclaration des constantes
        $constants = Config::get('constants');
        $unwanted_array = $constants["UNWANTED_ARRAY"];
        //Définition de l'erreur
        $erreur = 0;
        //Récupération de l'id utilisateur
        $id_utilisateur = Session::get('id_utilisateur');
        //Si l'utilisateur est connecté
        if(isset($id_utilisateur))
        {
            //Récupération des infos de l'utilisateur
            $utilisateur = Utilisateur::where('id', $id_utilisateur)->first();
            //Et si l'id du projet est spécifié
            if(isset($_GET["id_projet"]) && !empty($_GET["id_projet"]))
            {
                //Récupération des infos de l'utilisateur
                $utilisateur = Utilisateur::where('id', $id_utilisateur)->first();
                //De l'id du projet
                $id_projet = $_GET["id_projet"];
                $return_code = 1;
                //Récupération des infos du projet en bdd
                $projet = M_Projet::where('id', $id_projet)->first();
                //Si le projet existe
                if(isset($projet))
                {
                    //On récupère les droits que l'utilisateur a sur ce projet
                    $droit = Droit::where('id_utilisateur', $id_utilisateur)->where('id_projet', $id_projet)->first();
                    //Si l'utilisateur est spécifié dans les droits du projet
                    if(isset($droit))
                    {
                        //On récupère les droits du créateur du projet
                        $droit_createur = Droit::where('id_projet', $projet->id)->where('role', $constants["ROLE_ADMIN"])->first();
                        //Puis on en déduit ses infos
                        $createur = Utilisateur::where('id', $droit_createur->id_utilisateur)->first();

                        //Si la machine est éteinte
                        if($projet->port == 0)
                        {
                            //Allocation du port à utiliser
                            $port = 0; //Port par défaut
                            for($i = 49152; $i < 65535; $i++)
                            {
                                $dbPort = M_Projet::where('port', $i)->first();
                                if(!isset($dbPort))
                                {
                                    $port = $i;
                                    break;
                                }
                            }
                            //Démarrage de l'instance
                            $process = new Process(['../docker/run.sh', $projet->id, $port]);
                            $return_code = $process->run();
                            //On met à jour le port en bdd si ça s'est bien passé
                            if($return_code == 0)
                            {
                                $projet->port = $port;
                                $projet->save();

                                $user = preg_replace("/[^a-zA-Z0-9]+/", "", strtolower(strtr($utilisateur->prenom, $unwanted_array )).strtolower(strtr($utilisateur->nom, $unwanted_array )));
                                $mdp = base64_encode(strtolower($utilisateur->unix_password));
                                $fs = new FileSystem;
                                $fs->FileSystem($projet->port,$projet->nom,$user,$mdp);

                                $dir_project = "/home/".$user; //.$projet->nom
                                $arborescence_projet = $fs->dir2json($dir_project);


                                //Redirection vers la vue ide
                                return view('ide', compact('utilisateur', 'projet', 'unwanted_array', 'arborescence_projet'));
                            }
                            else
                            {
                                $erreur = $constants["RUN_ERROR"];
                            }
                        }
                        else
                        {
                            $user = preg_replace("/[^a-zA-Z0-9]+/", "", strtolower(strtr($utilisateur->prenom, $unwanted_array )).strtolower(strtr($utilisateur->nom, $unwanted_array )));
                            $mdp = base64_encode(strtolower($utilisateur->unix_password));
                            $fs = new FileSystem;
                            $fs->FileSystem($projet->port,$projet->nom,$user,$mdp);

                            $dir_project = "/home/".$user; //.$projet->nom
                            $arborescence_projet = $fs->dir2json($dir_project);

                            return view('ide', compact('utilisateur', 'projet', 'unwanted_array', 'arborescence_projet'));
                        }
                    }
                    else
                    {
                        //L'utilisateur n'a pas les droits sur le projet
                        $erreur = $constants["ACCESS_DENIED"];
                    }
                }
                else
                {
                    //Le projet spécifié est introuvable
                    $erreur = $constants["INVALID_PROJECT"];
                }
            }
            else
            {
                //Aucun projet n'est spécifié
                $erreur = $constants["INVALID_PROJECT"];
            }
        }
        else
        {
            //Sinon, on redirige vers la page de connexion
            return view('login');
        }
        //On redirige l'utilisateur vers la page d'acceuil avec son erreur
        return redirect('/?erreur='.$erreur);
    }

    public function postForm()
    {
        //Déclaration des constantes
        $constants = Config::get('constants');
    }
}
