<?php 
// Permet d'afficher uniquement 1 fichier de vue
// Utilisé par PhpBB pour afficher le bandeau et le pied de page
$vue->type=$controlleur->url_decoupee[1];
$controlleur->avec_entete_et_pied=false;
$vue->zones_pour_bandeau=remplissage_zones_bandeau();
$vue->lien_wiki=prepare_lien_wiki_du_bandeau();
$vue->demande_correction=info_demande_correction ();
?>
