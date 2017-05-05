<?php
// Ce fichier centralise les "hooks" qui viennent modifier le comportement de PhpBB pour s'interfacer avec refuges.info
// Il s'exécute dans le contexte de PhpBB 3.1+ (plateforme Synphony)
// qui est incompatible avec le modèle MVC et autoload des classes PHP de refuges.info
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
			'core.page_footer' => 'page_footer',
		];
	}

	// Récupération du numéro de la fiche liée à un topic du forum refuges
	function viewtopic_assign_template_vars_before ($vars) {
		global $db, $template;

		if ($vars['topic_data']['topic_id']) {
			$sql = "SELECT id_point FROM points WHERE topic_id = ".$vars['topic_data']['topic_id'];
			$result = $db->sql_query ($sql);
			$row = $db->sql_fetchrow ($result);
			$db->sql_freeresult($result);
			if ($row)
				$template->assign_var('ID_POINT', $row['id_point']);
		}
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

	// Inclusion du bandeau
	// Les fichiers template du bandeau et du pied de page étant au format "MVC+template type refuges.info",
	// il s'agit de les évaluer dans leur contexte PHP et d'introduire le code HTML résultant
	// dans des variables des templates de PhpBB V3.2
	function page_footer ($vars) {
		global $template, $request, $user, $auth;
		$request->enable_super_globals();

		// On récupère le HTML de la page d'entrée de WRI
		$rep = file_get_contents(
			$a='http://'.$_SERVER['SERVER_NAME'].preg_replace('/forum.*/i','',$_SERVER['REQUEST_URI']), // L'URL d'entrée de refuges.info
			false,
			stream_context_create( ['http' => [
				'header' =>'Cookie: '.http_build_query( $_COOKIE, null, ';' ), // On envoie les mêmes cookies
			]])
		);
		// On découpe finement le bandeau et le pied
		$reps = preg_split('/<div id="(|fin-)(entete|basdepage)">/', $rep);

		// On les inclue dans des variables template PhpBB pour inclusion dans les event templates 
		$template->assign_var('BANDEAU', '<div id="entete">'.$reps[1]);
		$template->assign_var('PIED',  '<div id="basdepage">'.$reps[3]);
	}
}
