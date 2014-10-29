<?php
/**********************************************************************************************
Pour ajouter un commentaire rattaché à un point
**********************************************************************************************/

require_once ("commentaire.php");
require_once ("point.php");
require_once ("wiki.php");
require_once ("mise_en_forme_texte.php");

$commentaire = new stdClass();
$conditions_commentaire = new stdClass();

setlocale(LC_TIME, "fr_FR");
// les modérateurs ont droit d'ajouter des commentaires aux points censuré
if ( $_SESSION['niveau_moderation']>=1 )
    $conditions_commentaire->avec_points_censure=True;
    
$commentaire->id_point=$controlleur->url_decoupee[1];
$conditions_commentaire->ids_points=$commentaire->id_point;
$p=infos_points($conditions_commentaire);
$point=$p[0];
if (!$point->erreur)
{
    if (!isset($_SESSION['id_utilisateur'])) // non connecté ? un message d'information s'affichera, et on présentera un CAPTCHA
    {
        $vue->non_connecte=True;
        $vue->captcha=True;
    }
    
    // on vient de valider notre formulaire, faisons le nécessaire
    if ($_POST['action']!="") 
    {
        $commentaire->texte=stripslashes($_POST['texte']);
        $commentaire->auteur_commentaire=stripslashes($_POST['auteur_commentaire']);
        $commentaire->texte_propre=protege($commentaire->texte);
        $vue->lettre_verification=$_POST["lettre_verification"];
        
        // peut être un robot ?
        if ( ($vue->lettre_verification!="f") AND !isset($_SESSION['id_utilisateur']) )
        {
            $vue->erreur_captcha=True;
            $vue->lettre_verification="";
        }
        else if (bloquage_internaute($_POST['auteur_commentaire']))  // utilisateur dont l'adresse IP est bannie
            $vue->banni=True;
        else
        {
            // Variables du commentaire à ajouter, presque plus de tests à faire, tout est dans la fonction d'ajout de
            // commentaires
            
            if (is_uploaded_file  ( $file_path=$_FILES['comment_photo']['tmp_name']  ) )
                $commentaire->photo['originale']=$file_path;
            $commentaire->demande_correction=$_POST['demande_correction'];
            $commentaire->id_createur_commentaire=$_SESSION['id_utilisateur'];
            // Transmission des info en cas d'erreur au modèle
            $vue->messages=modification_ajout_commentaire($commentaire);
            
            // ça semble avoir marché, on vide juste son texte qu'il puisse ressaisir un commentaire
            if (!$vue->messages->erreur)
                $commentaire->texte_propre="";
            
            // Nettoyage de la photo envoyée qu'elle fût ou non insérer correctement comme commentaire
            if (is_uploaded_file  ( $file_path))
                unlink($file_path);
        }
    }
    // Qu'on arrive juste ou que l'on vienne de rentrer un point, on affiche le formulaire (rappel paramètres si erreur, vide si nouveau commentaire de +)
  
  $quel_point="$point->article_defini $point->nom_type : ".protege($point->nom);
    $vue->titre="Ajout d'un commentaire sur $quel_point";
    $vue->lien_point=lien_point($point);
    $vue->lien_texte_retour="Retour à $quel_point";
    $vue->point_existe=True;
    $vue->commentaire=$commentaire;
    $vue->lien_wiki_que_mettre=lien_wiki('que_mettre');
    $vue->lien_wiki_restriction_licence=lien_wiki('restriction_licence');
}
else // Une erreur est survenue, ne permettons pas d'ajouter un commentaire dans le vent !
{  
    header("HTTP/1.0 404 Not Found");
    $vue->type="page_introuvable";
    $vue->titre="Impossible d'ajouter un commentaire car : $point->message";
}
?> 
