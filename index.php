<?php 
/*******************************************************************************
Ce fichier index.php est le fichier de point d'entrée de tout le site qu'on a codé nous
(ou presque, reste des vielleries toujours pas converties)
 
Il charge des trucs absoument généraux à tout le site mais par défaut, son seul 
rôle consiste à charger la config, et le fichier de ./routes/ pour mapper les urls
On fera un petit effort pour ne lui faire faire qu'un minimum de choses car il peut 
très bien être appelé pour des routes extrêmement simples qui ne font qu'ouvrir une vue html 
toute bête ou des controlleurs n'ayant pas besoin de session par exemple
*******************************************************************************/


//DCMM TEST A METTRE AILLEURS
if(0){/////////////////////////////
define('IN_PHPBB', true);
$phpbb_root_path = 'forum/';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
//include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
// Start session management
$user->session_begin();
//$auth->acl($user->data);
//$user->setup('viewforum');
$request->enable_super_globals();
}//////////////////////////////////////////////


//*DCMM*/echo"<pre style='background-color:white;color:black;font-size:14px;'> = ".var_export($user->data,true).'</pre>';

// quasi obligatoire pour tout le site
require_once ('includes/config.php');

// pas nécessaire à tout le monde, mais pas gros et nécessaire à presque tous
require_once ('wiki.php');
require_once ('gestion_erreur.php');
require_once ('autoconnexion.php');

// On "démarre" le site
require_once ('generales.routes.php');

?>