<?php 
/*******************************************************************************
Ecran d'accueil

Contient le code PHP de la page
Le code html est dans /vues/*.html
Le code javascript est dans /vues/*.js
Les variables sont passées dans l'objet $vue->...
*******************************************************************************/

require_once ("nouvelle.php");
require_once ("polygone.php");

$vue->titre = 'Carte et informations sur les refuges, cabanes et abris de montagne';
$vue->description='Base de donnee de refuges, abris, gites, sommets et divers points en montagne avec cartes satellite, descriptions et coordonnees GPS';

$vue->java_lib_foot [] = $config['url_chemin_leaflet'].($config['debug']?'src/':'').'leaflet.js?' .filemtime($config['chemin_leaflet'].'leaflet.js');
$vue->css           [] = $config['url_chemin_leaflet'].'leaflet.css?'.filemtime($config['chemin_leaflet'].'leaflet.css');

$conditions_notre_zone = new stdClass;
$conditions_notre_zone->ids_polygones=$config['id_zone_accueil'];
$polygones=infos_polygones($conditions_notre_zone);
$vue->bbox=$polygones[0]->bbox;

// liens vers les zones
$conditions = new stdClass;
$conditions->ids_polygone_type=$config['id_zone'];
$zones=infos_polygones($conditions);

// Ajoute les liens vers les autres zones
if ($zones)
  foreach ($zones as $zone)
    $vue->zones [$zone->nom_polygone] = lien_polygone($zone)."?mode_affichage=zone";

// Nouvelles
$vue->commentaires = $commentaires;
$vue->stat = stat_site ();

// Préparation de la liste des photos récentes
$conditions = new stdclass();
$conditions->limite=25;
$conditions->avec_photo=True;
$conditions->avec_infos_point=True;
$commentaires_avec_photos_recentes=infos_commentaires($conditions);
// ce re-parcours du tableau à pour but de rajouter le lien et le nom formaté, on pourrait sans doute s'en passer en mettant $vue->photos_recentes=$commentaires_avec_photos_recentes mais il faudrait
// alors déporter ces deux actions dans la vue. Hésitation entre rangement et factorisation. sly 2015
foreach ($commentaires_avec_photos_recentes as $commentaire_avec_photo_recente)
{
    $commentaire_avec_photo_recente->lien=lien_point($commentaire_avec_photo_recente)."#C$commentaire_avec_photo_recente->id_commentaire";
    $commentaire_avec_photo_recente->nom=bbcode2html($commentaire_avec_photo_recente->nom);
    $vue->photos_recentes[]=$commentaire_avec_photo_recente;
}
$vue->contenu_accueil=wiki_page_html("contenu_accueil");

// Préparation de la liste des nouvelles générales
$conditions_commentaires_generaux = new stdClass;
$conditions_commentaires_generaux->ids_points=$config['numero_commentaires_generaux'];
$conditions_commentaires_generaux->limite=2;
$vue->nouvelles_generales=infos_commentaires($conditions_commentaires_generaux);

// Préparation de la liste des nouveaux commentaires
$vue->nouveaux_commentaires=nouvelles(9,"commentaires");
foreach ($vue->nouveaux_commentaires as $id => $nouvelle)
{
    $vue->nouveaux_commentaires[$id]['date_formatee']=date("d/m/y", $nouvelle['date']);
}
// Préparation de la liste des nouveaux points rentrés
$vue->nouveaux_points=nouvelles(3,"points");
foreach ($vue->nouveaux_points as $id => $point)
{
    $vue->nouveaux_points[$id]['date_formatee']=date("d/m/y", $point['date']);
}

$vue->nouvelles_generales=wiki_page_html("nouvelles_generales");

?>
