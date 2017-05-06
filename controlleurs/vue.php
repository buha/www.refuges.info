<?php 
// Permet d'afficher uniquement 1 fichier de vue
// Utilisé par PhpBB pour afficher le bandeau et le pied de page

$vue->type=$controlleur->url_decoupee[1];
$controlleur->avec_entete_et_pied=false;
?>
