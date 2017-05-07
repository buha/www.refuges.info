<?php
/***
Contrôleur qui prépare la vue pour les pages de gestion
***/

$vue->type = 'page_introuvable';

if ($_SESSION['niveau_moderation'])
	switch ($controlleur->url_decoupee[1]) {
		case 'modifier_modeles':
			$vue->type = 'gestion/modifier_modeles';

			$query="
				SELECT id_point, nom_type
				FROM points
				NATURAL JOIN point_type
				WHERE modele=1
				ORDER BY importance DESC";

			$res = $pdo->query($query);
			while ($mod = $res->fetch())
				$vue->types_points[$mod->id_point] = $mod->nom_type;
			break;

		case 'commentaires_attente_correction':
			$vue->type = 'gestion/commentaires_attente_correction';

			// On applique la validation des commentaires cochés
			$conditions = new stdclass;
			if ($_POST["corrections_faites"]!="")
				foreach ($_POST['commentaires_corriges'] as $id_commentaire) {
					$conditions->avec_points_censure=True; // Ici, en secteur modération, il est possible qu'un modérateur souhaite modifier le commentaire d'une fiche censurée
					$conditions->ids_commentaires=$id_commentaire;
					$commentaires=infos_commentaires($conditions);
					if ($commentaires->erreur)
						print($commentaires->message);
					else {
						$commentaire=$commentaires[0]; // On est censé récupérer qu'un seul commentaire vu qu'on a donner qu'une condition l'id
						$commentaire->demande_correction=0; 
						$retour=modification_ajout_commentaire($commentaire);
						if ($retour->erreur)
							print($retour->message);
					}
				}

			// On cherche la liste des attentes de corrections
			$conditions_attente_correction = new stdclass;
			$conditions_attente_correction->demande_correction=True;
			$conditions_attente_correction->avec_infos_point=True;
			$conditions_attente_correction->avec_points_censure=True;
			$vue->commentaires_attente_correction=infos_commentaires($conditions_attente_correction);

			// Petit tablo pour afficher le message de cause
			$vue->liste_causes = [
				 1 => "apporte peut-être de l'information à la fiche (selon un internaute)",
				-1 => "n'a peut-être aucun intérêt (selon un internaute)",
				 2 => "contient un mot pouvant faire penser à une réservation",
				 3 => "concerne un %s et n'a pas été traitée par un modérateur",
			];
	}
?>
