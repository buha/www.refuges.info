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
			'core.viewtopic_assign_template_vars_before' => 'viewtopic_assign_template_vars_before',
			'core.posting_modify_submission_errors' => 'posting_modify_submission_errors',
			'core.posting_modify_submit_post_after' => 'posting_modify_submit_post_after',
			'core.posting_modify_template_vars' => 'posting_modify_template_vars',
			'core.functions.redirect' => 'functions_redirect',
			'core.page_footer' => 'page_footer',
		];
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

	// Permet la saisie d'un POST avec un texte vide
	function posting_modify_submission_errors($vars) {
		global $user;
		$error = $vars['error'];

		foreach ($error AS $k=>$v)
			if ($v == $user->lang['TOO_FEW_CHARS'])
				unset ($error[$k]);

		$vars['error'] = $error;
	}

	function posting_modify_submit_post_after ($vars) {
		if (isset ($_GET['rt']))
			echo json_encode ($vars['data']); // Aprés une action sur un post, on rend les data du post sous format JSON
	}

	function posting_modify_template_vars ($vars) {
		if (isset ($_GET['rt']) && $vars['error'])
			echo '"'.addslashes (implode ("\n", $vars['error'])).'"'; // Rend les erreurs s'il y en a
	}

	function functions_redirect ($vars) {
		if (isset ($_GET['nr']))
			$vars['return'] = true; // Ne redirige pas la page aprés une modif
	}

	function page_footer ($vars) {
		global $template, $request, $user, $auth;

		if (isset ($_GET['nd']))
			$vars['page_footer_override'] = true; // Termine sans afficher

		// Inclusion du bandeau
		// Les fichiers template du bandeau et du pied de page étant au format "MVC+template type refuges.info",
		// il s'agit de les évaluer dans leur contexte PHP et d'introduire le code HTML résultat dans une variable des templates de PhpBB V3.2

		// Restitution des variables
		$request->enable_super_globals();
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
