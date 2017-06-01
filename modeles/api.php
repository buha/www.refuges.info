<?php
/**********************************************************************************************
Fonctions diverses utilisées par l'API lors de l'exportation
***********************************************************************************************/
require_once ("config.php");

function liste_icones_possibles()
{
    global $wri;
    $dossier_icones = opendir($wri['chemin_icones']) or erreur('Je ne trouve pas les icones',"la recherche a eu lieu dans ".$wri['chemin_icones']);
    while($entree = @readdir($dossier_icones)) 
    {
        if (is_file($wri['chemin_icones'].'/'.$entree)) 
            if (preg_match('/.png/',$entree))
                $icones[]=preg_replace("/.png/","",$entree);
    }
    closedir($dossier_icones);
    return $icones;
}

?>