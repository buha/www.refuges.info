<?php
/*********************************************
Ce fichier centralise tous les "hooks"
qui viennent modifier le comportement de PhpBB
pour s'interfacer avec refuges.info
*********************************************/

namespace RefugesInfo\wri\event;
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
		if (isset ($_GET['nd']))
			$vars['page_footer_override'] = true; // Termine sans afficher
	}
}
