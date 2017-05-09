<?php 
// Conteneur standard de l'entête et pied de page

switch ($vue->status) {
	case 403:
		$vue->http_status_txt = 'Forbidden ';
		$vue->titre = "Vous n'avez pas les droits d'accès à la page \"$controlleur->url_base\"";
		break;

	case 404:
		$vue->http_status_txt = 'Not Found';
		$vue->titre = "Impossible d'accéder à la page \"$controlleur->url_base\" : vous n'y êtes pas autorisé !";
}
?>
