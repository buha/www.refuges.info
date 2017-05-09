<?php
/**************************************************
 *              ROUTEUR de la gestion
 * Ce fichier appelle juste le bon controlleur.
 * La vue et le mod�le sont appell�s par le controlleur.
 * cf. : http://bpesquet.developpez.com/tutoriels/php/evoluer-architecture-mvc/images/3.png
 *
 * Changelog :
 *   * 08/05/2017 - Dom - Version intiale :
 *          d�coupage URL, redirection sur le bon
 *          controleur.
 * 
**************************************************/

// Par d�faut
$controlleur->type = 'page_simple';
$vue->status = 403; // (uniquement affich� par page_simple)

// C'est le point unique qui contr�le les autorisations de toutes les URL /gestion...
switch ($controlleur->url_decoupee[1]) {
	case 'moderation':
		$commentaire = infos_commentaire($_REQUEST['id_commentaire'],true);
		if ($commentaire->id_createur_commentaire == $_SESSION['id_utilisateur'])
			$controlleur->type = 'gestion/moderation';

	case 'modifier_modeles':
	case 'commentaires_attente_correction':
		if ($_SESSION['niveau_moderation'])
			$controlleur->type = 'gestion/'.$controlleur->url_decoupee[1];
		break;

	default:
		$vue->status = 404;
}

?>
