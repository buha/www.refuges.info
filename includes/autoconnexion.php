<?php
/*******************************************************************************************************
fonctions de gestion de l'autoconnexion des utilisateurs permettant de coupler le forum et le site
Fichier à inclure si besoin d'auto-loger sur une page
comme une session est démarrée, c'est à faire avant tout affichage

Afin de simplifier grandement la gestion des utilisateurs
Il n'y a plus de table moderateur, le niveau de moderation
d'un utilisateur est récupérée dans la table phpbb_users
Ensuite sont stocké dans la session ( accessible par toutes
les pages ):
$_SESSION['login_utilisateur']
$_SESSION['id_utilisateur'] ( celui de la table phpbb_users )
$_SESSION['niveau_moderation'] ayant pour signification
0 = utilisateur normal
1 = modérateur général du site
2 = programmeur du site
3 = administrateur du site

REMARQUE :
En lisant ce code vous allez vous dire qu'il est dommage
d'inclure si souvent dans les pages, la raison est qu'il
faut vérifier à chaque fois que l'utilisateur ne s'est pas
déconnecté du forum.
l'idéal serait sûrement de vider la session au niveau du forum

29/08/2007 sly création initiale gestion de la connexion par cookie permanent
03/09/2007 sly gestion du cas de la connexion temporaire par session phpBB
14/09/2007 sly remplissage plus logique et plus complet de la session
15/02/13 jmb : PDO migration , petite etoile mise en commentaire ? g pas compris
*********************************************************************************************/

require_once ("config.php");
require_once ("bdd.php");
require_once ("gestion_erreur.php");
require_once ("commentaire.php");

			
/*** 
	fonction de reconnaissance d'un utilisateur déjà connecté sur le forum, on lui épargne le double login 
	On peut être connecté à phpBB de deux façon :
	1) avec leur système interne de session ( Ils-n'auraient pas pu faire comme tout le monde chez phpBB ?? )
	2) avec le cookie permanent
***/
// on vide les traces qu'on a sur l'utilisateur
function vider_session()
{
  foreach (array('login_utilisateur','id_utilisateur','niveau_moderation') as $variable)
  {
    if (isset($_SESSION[$variable]))
      unset($_SESSION[$variable]);
  }
}

/*** 
Cette fonction a pour rôle "d'auto-connecter" les utilisateurs s'ils ont un cookie phpbb sur le reste du site wri
elle est donc lancé sur chaque page qui pourrait nécessiter d'être connecté
***/
function auto_login_phpbb_users()
{
  global $pdo, $user_data;
  vider_session();

  // Il faut forcer le préfixe des noms de cookies du forum à 'phpbb3_wri'
  // On pourrait aussi aller le chercher dans phpbb3_config cookie_name mais, bof, ça va plus vite !
  $cookie_name = 'phpbb3_wri';

  $user_id = @$_COOKIE[$cookie_name.'_u'];
  if ($user_id <= 1) // Pas connecté ou anonymous
    return FALSE;

  $sql = "SELECT username, group_name, user_form_salt
    FROM phpbb3_users AS u
      JOIN phpbb3_sessions AS s ON u.user_id = s.session_user_id
      JOIN phpbb3_groups AS g USING (group_id)
    WHERE user_id = ".$user_id."
      AND session_id = '".$_COOKIE[$cookie_name.'_sid']."'";
  $res = $pdo->query($sql);
  if (!$res)
    return FALSE;

  $user_data = $res->fetch();

  /* on rempli notre session */
  $_SESSION['id_utilisateur']=$user_id;
  $_SESSION['login_utilisateur']=$user_data->username;

  switch ($user_data->group_name)
  {
    case 'REGISTERED':
    case 'REGISTERED_COPPA':
    case 'NEWLY_REGISTERED':
      $_SESSION['niveau_moderation']=0; break; // 0 = rien
    case 'Modérateurs':
    case 'GLOBAL_MODERATORS':
      $_SESSION['niveau_moderation']=1; break; // 1 = modérateur
    // 2 = programmeur, ça n'existe plus pour l'instant
    case 'ADMINISTRATORS':
      $_SESSION['niveau_moderation']=3; break; // 3 = admin
    default:
      return FALSE; // S'il n'y a un autre niveau (Bot, ...) on, préfère dire qu'on n'est pas connecté
  }

  return TRUE;
}

// Fonction qui va permettre ensuite d'afficher la "petite étoile :*" en haut à coté du nom du modérateur
// Pour le prévenir si un commentaire est en attente avec une demande de correction
// FIXME : cette fonction n'a rien à faire dans autoconnexion.php
function info_demande_correction () 
{
    $conditions_attente_correction = new stdclass;
    $conditions_attente_correction->demande_correction=True;
    $conditions_attente_correction->avec_points_censure=True;
    $commentaires_attente_correction=infos_commentaires($conditions_attente_correction);
    if (count($commentaires_attente_correction)>0)
        return true;
    else
        return false;
}
// FIXME : pas mieux que info_demande_correction tout ça est lié au bandeau et devrait filer dans un autre fichier
function remplissage_zones_bandeau()
{
    global $config;
    // Ajoute les liens vers les autres zones
    $conditions = new stdClass;
    $conditions->ids_polygone_type=$config['id_zone'];
    $zones=infos_polygones($conditions);
    if ($zones)
        foreach ($zones as $zone)
            $array_zones [ucfirst($zone->nom_polygone)] = lien_polygone($zone)."?mode_affichage=zone";
    return $array_zones;
}

// fonction qui va permettre d'exécuter une commande sur le forum
// l'autoload des classes PHP du modèle MVC/WRI étant incompatible avec celui de PhpBB basé sur Symphony,
// les modifications du forum sont faites en simulant l'appel d'une URL du forum
// le forum devra être paramétré de la façon suivante :
//    GÉNÉRAL / Paramètres de cookie / Nom du cookie = phpbb3_wri
//    GÉNÉRAL / Paramètres de sécurité / Validation de session IP = Aucune
//    GÉNÉRAL / Paramètres de sécurité / Valider le navigateur : non
// TODO : mettre ce fichier à un endroit plus adequat mais inclu par modeles/point.php et modeles/commentaire.php 
function submit_forum( $cmd, $get, $post )
{
	global $user_data, $config;

	$time = time() - 10; // Pour ne pas se faire passer pour un robot, on simule une attente de 10 secondes
	$url =
		$_SERVER['REQUEST_SCHEME'].'://'.
		$_SERVER['SERVER_NAME'].
		$config['lien_forum'].
		"$cmd.php";
	$get += [ // ajout des paramètres exploités par forum/ext/refugesInfo/wri/event/listener.php pour modifier le retour de l'URL
		'rt' => '', // Rend les data du post sous format JSON
		'nd' => '', // N'affiche pas la page
		'nr' => '', // Ne redirige pas la page aprés une modif
	];
	$post += [
		'post' => 'Envoyer',
		'creation_time' => $time,
		'form_token' => sha1( $time.$user_data->user_form_salt.$cmd ), //.$user->session_id
	];

	// On soumet l'url via file_get_contents
	$result = file_get_contents(
		$url.'?'.http_build_query( $get ),
		false,
		stream_context_create( ['http' => [
			'method'  => 'POST',
			'header'  => implode( "\n", [
				'Content-type: application/x-www-form-urlencoded',
				'Cookie: '.http_build_query( $_COOKIE, null, ';' ), // On envoie les mêmes cookies
			]),
			'content' => http_build_query( $post ), // Arguments POST
		]])
	);
	// L'url étant sensée retourner du code JSON, on le décode en PHP avant de le retourner
	return json_decode( $result );
}
?>
