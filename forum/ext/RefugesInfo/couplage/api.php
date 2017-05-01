<?php
// Ce fichier contient la fonction qui permet d'exécuter des actions agissant sur le forum à partir du code de refuges.info
// Il s'exécute dans le contexte du modèle MVC et autoload des classes PHP de refuges.info
// qui est incompatible avec celui de PhpBB 3.1+ (plateforme Synphony)
// Les actions du forum sont faites en simulant l'appel d'une URL du forum

function submit_forum( $cmd, $get, $post )
{
	global $user_data, $config, $pdo;

	$time = time() - 10; // Pour ne pas se faire passer pour un robot, on simule une attente de 1à secondes

	// Génére et enregistre une clé de validation
	$confirm_key = substr( strtoupper( $_COOKIE[$config['cookie_prefix'].'_sid'] ), 0, 10);
	$sql = "UPDATE phpbb3_users
	SET user_last_confirm_key = '$confirm_key'
	WHERE user_id = ".$_COOKIE[$config['cookie_prefix'].'_u'];
	$pdo->query($sql);

	$url =
		$_SERVER['REQUEST_SCHEME'].'://'.
		$_SERVER['SERVER_NAME'].
		$config['lien_forum'].
		"$cmd.php";
	$get += [ // Ajout des paramètres exploités par event/listener.php pour modifier le retour de l'URL
		'rt' => '', // Rend les data du post sous format JSON
		'nd' => '', // N'affiche pas la page
		'nr' => '', // Ne redirige pas la page aprés une modif
		'f' => $config['forum_refuges'], // Le n° du forum des refuges
		'confirm_key' => $confirm_key,
		'confirm_uid' => $_COOKIE[$config['cookie_prefix'].'_u'],
		'sess' => $_COOKIE[$config['cookie_prefix'].'_sid'],
	];
	$post += [
		'post' => 'Envoyer',
		'creation_time' => $time,
		'form_token' => sha1( $time.$user_data->user_form_salt.$cmd ), //.$user->session_id
	];

	// On soumet l'url via file_get_contents
	$rep = file_get_contents(
		$url.'?'.http_build_query( $get ),
		false,
		stream_context_create( ['http' => [
			'method'  => 'POST',
			'header'  => implode( "\n", [
				'User-Agent: '.$_SERVER['HTTP_USER_AGENT'], // On simule le même agent pour ne pas se faire repérer par la patrouille
				'Content-type: application/x-www-form-urlencoded',
				'Cookie: '.http_build_query( $_COOKIE, null, ';' ), // On envoie les mêmes cookies
			]),
			'content' => http_build_query( $post ), // Arguments POST
		]])
	);

	// On trace tout ça en cas de bug
	file_put_contents ($config['racine_projet'].'forum/SUBMIT_FORUM.LOG', implode (PHP_EOL, [
		date('r'),
		$url,
		'GET = '.var_export($get,true),
		'POST = '.var_export($post,true),
		'COOKIE = '.var_export($_COOKIE,true),
		'Reponse = '.var_export($rep,true),
		PHP_EOL
	]), FILE_APPEND);

	// L'url étant sensée retourner du code JSON, on le décode en PHP avant de le retourner
	$json = json_decode( $rep );
	return is_object ($json) ? $json : $rep;
}
?>
