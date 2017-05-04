<?php
// Ce fichier centralise tous les "hooks" qui viennent modifier le comportement de PhpBB pour s'interfacer avec refuges.info
// Il s'exécute dans le contexte de PhpBB 3.1+ (plateforme Synphony) qui est incompatible avec le modèle MVC et autoload des classes PHP de refuges.info
// Attention: Le code suivant s'exécute dans un "namespace" bien défini

namespace RefugesInfo\couplage\event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

if (!defined('IN_PHPBB')) exit;

class listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents () {
		return [
//			'core.modify_posting_parameters' => 'api',
			'core.viewtopic_assign_template_vars_before' => 'viewtopic_assign_template_vars_before',
//			'core.modify_posting_auth' => 'modify_posting_auth',
			'core.posting_modify_submission_errors' => 'posting_modify_submission_errors',
//			'core.posting_modify_submit_post_after' => 'posting_modify_submit_post_after',
//			'core.posting_modify_template_vars' => 'posting_modify_template_vars',
//			'core.functions.redirect' => 'functions_redirect',
			'core.page_footer' => 'page_footer',
		];
	}

	// On arrive ici quand on appelle posting.php avec un paramètre 'api' (GET ou POST)
	// Dans ce cas, posting.php n'a servi qu'à initialiser les contextes
	// et on ne ressortira pas de cette fonction
	// Sinon, posting.php continuera à se dérouler normalement
	function WWWWapi () {
		global $request, $db, $phpbb_root_path, $phpEx;

		// Vérifie que la requette provient bien de la même machine
		$request->enable_super_globals();
		if ($_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR']) {

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

//*DCMM*/echo"<pre style='background-color:white;color:black;font-size:14px;'>data = ".var_export($data,true).'</pre>';
//*DCMM*/echo"<pre style='background-color:white;color:black;font-size:14px;'>data = ".var_export($data,true).'</pre>';



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

						include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
						// On ne sait pas supprimer un topic: il faut supprimer une lie=ste de posts
						delete_posts('post_id', $pids);
						// Et on nettoie un peu tout ça
						sync('forum');
					
						exit ('{}'); // Sortie OK (Json vide)
			}
		}
	}

	function viewtopic_assign_template_vars_before ($vars) {
		global $db, $template;

		// Lien entre fiche et forum refuges
		$sql = "SELECT id_point FROM points WHERE topic_id = ".$vars['topic_data']['topic_id'];
		$result = $db->sql_query ($sql);
		$row = $db->sql_fetchrow ($result);
		$db->sql_freeresult($result);
		$template->assign_var('ID_POINT', $row['id_point']);
	}

	function WWWmodify_posting_auth ($vars) {
		global $user, $request;
		$request->enable_super_globals();

		// Autorise à tout si la requette provient bien du même serveur
		if ($_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR'])
			$vars['is_authed'] = true;
	}

	// Permet la saisie d'un POST avec un texte vide
	function posting_modify_submission_errors($vars) {
		global $user;
		$error = $vars['error'];

		foreach ($error AS $k=>$v)
			if ($v == $user->lang['TOO_FEW_CHARS'])
				unset ($error[$k]);

		$vars['error'] = $error;
	}

	function WWWposting_modify_submit_post_after ($vars) {
		if (isset ($_GET['rt']))
			echo json_encode ($vars['data']); // Aprés une action sur un post, on rend les data du post sous format JSON
	}

	function WWWposting_modify_template_vars ($vars) {
		if (isset ($_GET['rt']) && $vars['error'])
			echo '"'.addslashes (implode ("\n", $vars['error'])).'"'; // Rend les erreurs s'il y en a
	}

	function WWWfunctions_redirect ($vars) {
		if (isset ($_GET['nr']))
			$vars['return'] = true; // Ne redirige pas la page aprés une modif
	}

	function page_footer ($vars) {
		global $template, $request, $user, $auth;
		$request->enable_super_globals();

//		if ($_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR'])
//			send_status_line(200, 'OK'); // Evite l'envoi de status différents

		// Inclusion du bandeau
		// Les fichiers template du bandeau et du pied de page étant au format "MVC+template type refuges.info",
		// il s'agit de les évaluer dans leur contexte PHP et d'introduire le code HTML résultat dans une variable des templates de PhpBB V3.2

		// Restitution des variables
		include dirname (__FILE__).'/../../../../../includes/config.php';
		if ($user->data['user_id'] > 1) {
			$_SESSION['id_utilisateur'] = $user->data['user_id'];
			$_SESSION['login_utilisateur'] = $user->data['username'];
			$_SESSION['niveau_moderation'] = $auth->acl_gets('m_edit');
		}
		// Expansion du contenu des fichiers pour inclusion dans les event templates 
		ob_start();
		include dirname (__FILE__).'/../../../../../vues/_bandeau.html';
		$template->assign_var('BANDEAU', ob_get_clean());

		ob_start();
		include dirname (__FILE__).'/../../../../../vues/_pied.html';
		$template->assign_var('PIED', str_replace (['</body>','</html>'], '', ob_get_clean()));
	}
}
