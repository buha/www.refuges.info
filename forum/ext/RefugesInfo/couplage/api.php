<?php
// Interface API PhpBB 3.1+ pour refuges.info

define('IN_PHPBB', true);
$phpbb_root_path = '../../../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);

$user->session_begin();
$auth->acl($user->data);
$user->setup();

					// A ENLEVER APRES MIGRATION ***************************
					if (isset ($_GET['init'])) {
						$sql = "SELECT p.id_point, t.topic_id
								FROM points AS p
								JOIN phpbb_topics AS t ON t.topic_id_point = p.id_point";
						$result = $db->sql_query($sql);

						while ($row = $db->sql_fetchrow($result))
						{
							$sql = "UPDATE points SET topic_id = {$row['topic_id']} WHERE id_point = {$row['id_point']}";
					/*DCMM*/echo"<pre style='background-color:white;color:black;font-size:14px;'> = ".var_export($row,true).'</pre>';
					/*DCMM*/echo"<pre style='background-color:white;color:black;font-size:14px;'> = ".var_export($sql,true).'</pre>';
							$db->sql_query($sql);
						}
						$db->sql_freeresult($result);
						exit;
					}
					// FIN A ENLEVER APRES MIGRATION ***************************

// L'accés à cet API n'est autorisé que depuis le même serveur
// Aucune autre autorisation n'est vérifiée
// Les cookies n'étant pas transmis, l'utilisateur est "anonymous"
$request->enable_super_globals();
if ($_SERVER['REMOTE_ADDR'] != $_SERVER['SERVER_ADDR'])
	exit ('Forbidden access');

// Paramètres
$data = [
	'post_id' => request_var ('p', 0),
	'topic_id' => request_var ('t', 0),
	'forum_id' => request_var ('f', 0),
	'topic_title' => request_var ('s', ''),
	'message' => request_var ('m', ''),
	'username' => request_var ('u', 'refuges.info'),

// Données par défaut
	'enable_sig' => true,
	'enable_bbcode' => true,
	'enable_smilies' => true,
	'enable_urls' => true,
	'enable_indexing' => true,
	'notify' => false,
	'notify_set' => 0,
	'post_edit_locked' => 0,
	'icon_id' => 0,
	'bbcode_bitfield' => '',
	'bbcode_uid' => '',
	'forum_name' => '',
];

// Récupère les infos du topic
if ($data['topic_id']) {
	$sql = 'SELECT * FROM '.TOPICS_TABLE.' WHERE topic_id = '.$data['topic_id'];
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
	if ($row)
		$data = array_merge ($data, $row);
}

$data['message_md5'] = md5($data['message']);
$poll = [];

// Exécute les fonctions api
// Renvoie des données JSON si la requette s'est bien passée
// Un string message d'erreur sinon
switch (request_var ('api', '')) {
	case 'creer':
		submit_post ('post',
			$data['topic_title'],
			$data['username'],
			0, // topic_type
			$poll, $data
		);
		exit (json_encode ($data));

	case 'renommer':
		$data['topic_title'] =
		$data['post_subject'] = request_var ('s', '');

		if (!$data['topic_first_post_id'])
			exit ('ERROR : No post in topic '.$data['topic_id']);

		// Récupère les infos du premier post
		$sql = 'SELECT * FROM '.POSTS_TABLE.' WHERE post_id = '.$data['topic_first_post_id'];
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		if ($row) {
			$data = array_merge ($data, $row);
			$data['message'] = $data['post_text'];
		}

		submit_post ('edit',
			$data['topic_title'],
			$data['username'],
			0, // topic_type
			$poll, $data
		);
		exit (json_encode ($data));

	case 'transferer': // Transfert de commentaire
		// Traduit bbcodes
		$message_parser = new parse_message();
		$message_parser->message = $data['message'];
		$message_parser->parse(true, true, true);
		$data['message'] = $message_parser->message;

		// Si l'auteur du commentaire transféré était connecté, on force l'ID
		$user->data['user_id'] = max (1, request_var ('i', 1));

		submit_post ('reply',
			request_var ('s', ''),
			request_var ('u', ''),
			0, // topic_type
			$poll, $data
		);
		exit (json_encode ($data));

	case 'supprimer':
		// La liste des posts
		$sql = 'SELECT post_id FROM '.POSTS_TABLE.' WHERE topic_id = '.$data['topic_id'];
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
			$pids[] = $row['post_id'];
		$db->sql_freeresult($result);

		if (!isset ($pids))
			exit ('FORUM : Erreur supression topic inconnu '.$data['topic_id']);

		delete_posts('post_id', $pids); // On ne sait pas supprimer un topic: il faut supprimer une lie=ste de posts
		sync('forum'); // Et on nettoie un peu tout ça
		exit ('{}'); // Sortie OK (Json vide)
}

exit ('Rien à exécuter');
