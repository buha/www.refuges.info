<?php
/**
* Interface API PhpBB 3.1+ pour refuges.info
*
* @copyright (c) Dominique Cavailhez 2017
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

//DCMM enlever ? / Debug ?
error_reporting(E_ALL);
ini_set('display_errors', 'on');

define('IN_PHPBB', true);
$phpbb_root_path = '../../../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

// Les includes
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
//include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();
$request->enable_super_globals();

// L'accés à cet API n'est autorisé que depuis le même serveur
// Aucune autre autorisation n'est vérifiée
// Les cookies n'étant pas transmis, l'utilisateur est "anonymous"
if(0)//DCMM À ENLEVER APRÉS TESTS
if ($_SERVER['REMOTE_ADDR'] != $_SERVER['SERVER_ADDR'])
	exit ('Forbidden access');

					// A ENLEVER APRES MIGRATION
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
					// FIN A ENLEVER APRES MIGRATION

// Données par défaut
$poll = [];
$data = [
	'post_id' => request_var ('p', 0),
	'topic_id' => request_var ('t', 0),
	'forum_id' => request_var ('f', 0),
	'topic_title' => request_var ('s', ''),
	'message' => request_var ('m', ''),
	'username' => request_var ('u', 'refuges.info'),
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

if ($data['topic_id']) {
	// Récupère les infos du topic
	$sql = 'SELECT * FROM '.TOPICS_TABLE.' WHERE topic_id = '.$data['topic_id'];
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
	if ($row) {
		$data = array_merge ($data, $row);

		// Récupère les infos du premier post
		$sql = 'SELECT * FROM '.POSTS_TABLE.' WHERE post_id = '.$data['topic_first_post_id'];
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		if ($row) {
			$data = array_merge ($data, $row);
			$data['message'] = $data['post_text'];
		}
	}
}

$data['message_md5'] = md5($data['message']);

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
		submit_post ('edit',
			$data['topic_title'],
			$data['username'],
			0, // topic_type
			$poll, $data
		);
		exit (json_encode ($data));

	case 'transferer': // Transfert de commentaire

//*DCMM*/echo"<pre style='background-color:white;color:black;font-size:14px;'>data = ".var_export($data,true).'</pre>';
//*DCMM*/echo"<pre style='background-color:white;color:black;font-size:14px;'>data = ".var_export($data,true).'</pre>';
		break;

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












//////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Les paramètres
$mode = request_var ('m', ''); // post | edit | delete
$forum_id = request_var ('f', 0);
$topic_id = request_var ('t', 0);
$post_id = request_var ('p', 0);
$topic_name = urldecode (@$_GET['tn']); // Permet de récupérer les accents
$post_text = urldecode (@$_GET['pt']); // Idem
$image = request_var ('i', '');

// Force topic_id
if ($post_id) {
	$sql = 'SELECT topic_id FROM '.POSTS_TABLE.' WHERE post_id = '.$post_id;
	$result = $db->sql_query($sql);
	$t_id = (int) $db->sql_fetchfield('topic_id');
	$db->sql_freeresult($result);
	$topic_id = $t_id ?: $topic_id ;
}

// Force forum_id
//DCMM TODO : forcer à 4 !! / Retrouver de la base.
if ($topic_id) {
	$sql = 'SELECT forum_id FROM '.TOPICS_TABLE.' WHERE topic_id = '.$topic_id;
	$result = $db->sql_query($sql);
	$f_id = (int) $db->sql_fetchfield('forum_id');
	$db->sql_freeresult($result);
	$forum_id = $f_id ?: $forum_id ;
}

////////////////////////////////////////////////////

// On vérifie qu'on est bien connecté et qu'on a bien le droit à cette action pour ce forum
if (!$auth->acl_gets('f_'.$mode, $forum_id)) {
	echo 'PAS ASSEZ DE DROITS';
	exit;
}

// Si on édite un topic_id sans post_id, on édite le premier post
if ($mode == 'edit' && !$post_id) {
	$sql = 'SELECT topic_first_post_id FROM '.TOPICS_TABLE.' WHERE topic_id = '.$topic_id;
	$result = $db->sql_query_limit($sql, 1);
	$post_id = (int) $db->sql_fetchfield('topic_first_post_id');
	$db->sql_freeresult($result);
}

// Structure par défaut pour la création d'un nouveau post
$post_data = [
	'forum_id' => $forum_id,
	'topic_id' => $topic_id, // 0 = le créer
	'post_id' => $post_id, // 0 = le créer
	'post_subject' => '',
	'post_text' => $post_text,
	'post_time' => time(),
	'poster_id' => $user->data['user_id'],
	'icon_id' => 0,
	'bbcode_uid' => '',
	'bbcode_bitfield' => '',
	'enable_bbcode' => true,
	'enable_smilies' => true,
	'enable_urls' => true,
	'enable_magic_url' => true,
	'enable_sig' => true,
	'topic_type' => POST_NORMAL,
	'topic_visibility' => true,
	'post_visibility' => true,
	'enable_indexing' => true,
	'post_edit_locked' => false,
	'notify_set' => false,
	'notify' => false,
	'notify' => false,
];

// On lit les données du forum s'il est spécifié et existe
if ($forum_id) {
	$sql = "SELECT *
		FROM ".FORUMS_TABLE."
		WHERE forum_id = ".$forum_id;
	$result = $db->sql_query_limit($sql, 1);
	$post_data += $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
}

// On lit les données du topic s'il est spécifié et existe
if ($topic_id) {
	$sql = "SELECT *
		FROM ".TOPICS_TABLE."
		WHERE topic_id = ".$topic_id;
	$result = $db->sql_query_limit($sql, 1);
	$post_data += $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
}

// On lit les données du post s'il est spécifié et existe
if ($post_id) {
	$sql = "SELECT *
		FROM ".POSTS_TABLE."
		WHERE post_id = ".$post_id;
	$result = $db->sql_query_limit($sql, 1);
	$post_data += $db->sql_fetchrow($result);
	$db->sql_freeresult($result);
}

// On incorpore les modifs souhaitées
if ($topic_name)
	$post_data ['post_subject'] = $topic_name;

$post_data['post_text'] = $post_text ?: $post_data['post_text'] ?: ' '; // Vide non autorisé

if ($image) {
	// On déplace l'image dans phpbb/files
  $tmp = str_replace ('.jpeg', '-.jpeg', $image);
	copy ($image, $tmp); // On upload une copie car upload détruit l'original !
	$u = upload_attachment (null, $forum_id, true, $tmp);
	if ($u['error']) {
		echo "Image '$image' attachment error : ".implode (', ', $u['error']);
		exit;
	}

	// On crée l'enregistrement de l'attachement de l'image
	$sql_ary = array(
		'physical_filename'	=> $u['physical_filename'],
		'real_filename'		=> $u['real_filename'],
		'extension'			=> $u['extension'],
		'mimetype'			=> $u['mimetype'],
		'filesize'			=> $u['filesize'],
		'filetime'			=> $u['filetime'],
		'thumbnail'			=> $u['thumbnail'],
		'attach_comment'	=> '',
		'is_orphan'			=> 1,
		'in_message'		=> false,
		'poster_id'			=> $user->data['user_id'],
	);
	$db->sql_query('INSERT INTO ' . ATTACHMENTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary));

	// On complète les infos du post
	$post_data['attachment_data'] = [$sql_ary + ['attach_id' => $db->sql_nextid()]];
}

// On y va !
switch ($mode) {

	case 'post':
//forum_point_ajout point ext/RefugesInfo/wri/api.php?m=post&f=2&tn=Nom%20de%20la%20cabane

	case 'edit':
//forum_mise_a_jour_nom ext/RefugesInfo/wri/api.php?m=edit&t=3&tn=Nom%20de%20la%20cabane

	case 'reply':
//transfert_forum commentaire ext/RefugesInfo/wri/api.php?m=reply&t=5&pt=texte&i=img.jpg

		$post_data['message'] = $post_data['post_text'];
		$post_data['message_md5'] = md5($post_data['post_text']);
		$post_data['topic_title'] = $post_data['post_subject'];

		// Pas de sondages dans ces fonctions. Variable passée par adresse : doit être déclarée séparément de l'appel
		$poll = [];

		submit_post(
			$mode,
			$post_data['post_subject'],
			$user->data['username'],
			$post_data['topic_type'],
			$poll,
			$post_data
		);

		// On rend le résultat
		echo json_encode ($post_data);
		exit;

	case 'delete':
//forum_supprime_topic ext/RefugesInfo/wri/api.php?m=delete&t=2
		if ($topic_id)
			delete_topics('topic_id', [$topic_id]);
		echo '{"status":"OK"}';
		exit;

	default:
}

echo 'ACTION INCONNUE';
