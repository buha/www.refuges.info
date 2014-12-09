<?php

/********************************************
 * Ici on traite l'URL de l'api
 * exemple pour le test :
 * http://leo.refuges.info/api/polygones?format=gml
********************************************/
include_once("point.php");
include_once("mise_en_forme_texte.php");

/****************************************/
// Ça permet de mettre convertir tout un objet
function updatebbcode2html(&$html) { $html=bbcode2html($html,0,1,0); }
function updatebbcode2markdown(&$html) { $html=bbcode2markdown($html); }
function updatebbcode2txt(&$html) { $html=bbcode2txt($html); }
function updatebool2char(&$html) { if($html===FALSE) { $html='0'; } elseif($html===TRUE) { $html='1'; } }
/****************************************/

// Dans un premier temps on met en place l'objet contenant la requête
$req = new stdClass();
$req->page = $cible; // Ici on récupère la page (point, bbox, massif, contribution...)
$req->format = $_GET['format'];
$req->massif = $_GET['massif'];
$req->type_polygones = $_GET['type_polygon'];
$req->bbox = $_GET['bbox'];

// Ici c'est les valeurs possibles
$val = new stdClass();
$val->format = array("geojson", "gml");

/****************************** VALEURS PAR DÉFAUT - PARAMS FACULTATIFS ******************************/

// On teste chaque champ pour voir si la valeur est correcte, sinon valeur par défaut
if(!in_array($req->format,$val->format))
    $req->format = "geojson";
// On vérifie que la liste de massif est correcte
$temp = explode(",", $req->massif);
foreach ($temp as $massif) {
    if(!is_numeric($massif)) { $req->massif = ""; }
}
// On vérifie que la liste des types de polygones est correcte
$temp = explode(",", $req->type_polygones);
foreach ($temp as $type_polygone) {
    if(!is_numeric($type_polygone)) { $req->type_polygones = ""; }
}
// On vérifie que la bbox est correcte
$temp = explode(",", $req->bbox);
if(!((count($temp)==4 &&
    is_numeric($temp[0]) &&
    is_numeric($temp[1]) &&
    is_numeric($temp[2]) &&
    is_numeric($temp[3])) ||
    $req->bbox == "world")) {
    $req->bbox = "world";
}

/****************************** REQUÊTE RÉCUPÉRATION POLYS ******************************/

$params = new stdClass();

if($req->bbox != "world") { // Si on a world, on ne passe pas de paramètre à postgis
	list($ouest,$sud,$est,$nord) = explode(",", $req->bbox);
	$params->geometrie = "ST_SetSRID(ST_MakeBox2D(ST_Point($ouest, $sud), ST_Point($est ,$nord)),4326)";
}
unset($ouest,$sud,$est,$nord);
if($req->massif != "")
	$params->ids_polygones=$req->massif;
if($req->type_polygones != "")
	$params->ids_polygone_type=$req->type_polygones;
$params->avec_geometrie=$req->format;

$polygones_bruts = new stdClass();
$polygones = new stdClass();

$polygones_bruts=infos_polygones($params);

/****************************** INFOS GÉNÉRALES ******************************/

$i = 0;
foreach($polygones_bruts as $polygone)
{
	$polygones->$i = new stdClass();
	// génère une couleur aléatoire
	$couleur = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
	$polygones->$i->nom = $polygone->nom_polygone;
	$polygones->$i->id = $polygone->id_polygone;
	$polygones->$i->type['id'] = $polygone->id_polygone_type;
	$polygones->$i->type['type'] = $polygone->type_polygone;
	$polygones->$i->type['categorie'] = $polygone->categorie_polygone_type;
	$geo = "geometrie_".$req->format;
	$polygones->$i->geometrie = $polygone->$geo;
	$polygones->$i->partitif = $polygone->article_partitif;
	$polygones->$i->bbox = $polygone->bbox;
	$polygones->$i->lien = lien_polygone($polygone,False);
	$polygones->$i->couleur = $couleur;
	$i++;
}
$nombre_polygones = $i;

/****************************** FORMATAGE DES CHAMPS ******************************/

array_walk_recursive($polygones, 'updatebool2char'); // Remplace les False et True en 0 ou 1

/****************************** FORMAT VUE ******************************/

switch ($req->format) {
    case 'geojson':
        include('../vues/api/polygones.vue.json');
        break;
    case 'gml':
        include('../vues/api/polygones.vue.gml');
        break;
    default:
        include('../vues/api/polygones.vue.json');
        break;
}

?>
