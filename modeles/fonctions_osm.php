<?php
/**********************************************************************************************
Fonctions pour gérer les points supplémentaires en provenance d'openstreetmap (osm)
- accès
- import
- insertion
sly 29/11/2012
**********************************************************************************************/
require_once ('config.php');
require_once ("fonctions_bdd.php");
require_once ('fonctions_mise_en_forme_texte.php');
require_once ("fonctions_gestion_erreurs.php");
require_once ("fonctions_exportations.php");

$config['xapi_url_poi']="http://api.openstreetmap.fr/osm2node?";
$tags_cache=array();

/*
Fonction de récupération génériques des poi dans la base osm 
On lui passe l'objet $conditions_recherche contenant :
->k le nom de la clé attendu au format openstreetmap (amenity, tourism, ...)
->v la valeur de la clé (hotel, drinking_water)
Une connaissance des tags osm est nécessaire, Utilisez préférablement la fonction appelant celle-ci qui est 
"simplifiée"
->bbox La bbox sous la forme de l'objet bbox habituel avec : ->latitude_maximum, ->longitude_minimum, ...
Le nombre d'objet à récupérer au maximum (optionnel)
->limite
*/
function recuperation_poi_osm($conditions_recherche)
{
  // FIXME BIDOUILLE sly : La multiplication par 5 est commlètement arbitraire, c'est juste que la requête
  // ne retourne pas un nombre de point, mais un nombre de clés (point/tag) et comme la moyenne est à
  // ~4 tag par point, on prend un poil au dessus, et on limite en php ensuite
  if (isset($conditions_recherche->limite))
  {
    $limite=5*$conditions_recherche->limite;
    $limite_sql="LIMIT 0,$limite";
  }
  if (isset($conditions_recherche->tag_condition))
  {
  $tag_condition="(";
  foreach ($conditions_recherche->tag_condition as $couple)
    foreach ($couple as $cle => $valeur)
      $tag_condition.="(osm_tags.k='$cle' and osm_tags.v='$valeur') or ";
  $tag_condition.=" 1=0)";
  }
  else
    $tag_condition="1=1";
  
  $query_recherche=
"select osm_pois2.id_osm_poi,osm_pois2.latitude,osm_pois2.longitude,osm_tags2.k,osm_tags2.v
  from osm_tags,osm_pois_tags,osm_pois,
  osm_tags as osm_tags2,osm_pois_tags as osm_pois_tags2,osm_pois as osm_pois2 
  where 
  
  $tag_condition and
  osm_tags.id_osm_tag=osm_pois_tags.id_osm_tag and 
  
  osm_pois_tags.id_osm_poi=osm_pois.id_osm_poi and 
  osm_pois.id_osm_poi=osm_pois2.id_osm_poi and
  
  osm_pois2.id_osm_poi=osm_pois_tags2.id_osm_poi and
  osm_pois_tags2.id_osm_tag=osm_tags2.id_osm_tag and
  
  osm_pois2.longitude<$conditions_recherche->longitude_maximum and
  osm_pois2.longitude>$conditions_recherche->longitude_minimum and
  osm_pois2.latitude<$conditions_recherche->latitude_maximum and
  osm_pois2.latitude>$conditions_recherche->latitude_minimum
  $limite_sql";
  //die($query_recherche);
  $res=mysql_query($query_recherche);
  $compte=0;
  while ($point=mysql_fetch_object($res))
  {
    $id=$point->id_osm_poi;
    
    if ($id!=$old_id) // BIDOUILLE de la limite : on vient de trouver un nouveau point correspondant
    {
      $compte++;
    if ($compte==$conditions_recherche->limite)
      break;
    }
    $points[$id]->site='osm'; // Dominique: permet de rechercher les icones et styles correspondantes à OSM
    $points[$id]->latitude=$point->latitude;
    $points[$id]->longitude=$point->longitude;
    if ($point->k=="tourism" and $point->v=="hotel")
      $points[$id]->nom_icone="hotel";
    elseif($point->k=="tourism" and $point->v=="camp_site")
      $points[$id]->nom_icone="camping";
    elseif($point->k=="shop" and ($point->v=="supermarket" or $point->v=="convenience" ))
      $points[$id]->nom_icone="superette";
    elseif($point->k=="tourism" and $point->v=="guest_house")
      $points[$id]->nom_icone="chambre-hotes";
    else
      ;
    if ($point->k=="name")
      $points[$id]->nom=$point->v;
    if ($point->k=="phone")
      $points[$id]->telephone=$point->v;
    if ($point->k=="website")
      $points[$id]->site_web=$point->v;
    if ($point->k=="description")
      $points[$id]->description=$point->v;
    if ($point->k=="opening_hours") 
      $points[$id]->horaires_ouvertures=$point->v; // FIXME : à convertir en français
      $old_id=$id;
  }
  return $points;
}
/*
Cette fonction insère un tag osm (clé+valeur) dans notre base et renvoi son id.
S'il y est déjà, on ne l'insère pas, mais on renvoi l'id quand même.
(Cette fonction utilise un cache externe $tags_cache)
On lui passe $tag->k (clé du tag) et $tag->v (valeur du tag)
*/
function insert_ou_recupere_tag($tag)
{
  // J'ai pas réussi à le passer par référence lui, je pige pas
  global $tags_cache;
 // déjà dans le cache ?
 if (isset($tags_cache[$tag->k][$tag->v]))
 return $tags_cache[$tag->k][$tag->v];
  // test s'il n'y est pas déjà
  $query_is_tag="select id_osm_tag from osm_tags where k='".mysql_real_escape_string($tag->k)."' and v='".mysql_real_escape_string($tag->v)."'";
  $res=mysql_query($query_is_tag);
  if (mysql_num_rows($res)==1)
    $tag_present=mysql_fetch_object($res);
  else
  {
    $query_insert_tag="Insert into osm_tags set k='".mysql_real_escape_string($tag->k)."', v='".mysql_real_escape_string($tag->v)."'";
    mysql_query($query_insert_tag);
    $tag_present->id_osm_tag=mysql_insert_id();
  }
  //Mise en cache
  $tags_cache[$tag->k][$tag->v]=$tag_present->id_osm_tag;
  return $tag_present->id_osm_tag;
}

/*
On lui donne une bbox object en paramètre et elle insère dans les tables osm

bbox doit être composé de ces 4 champs là :
$bbox->latitude_minimum $bbox->latitude_maximum $bbox->longitude_maximum $bbox->longitude_minimum
On peut par exemple lui envoyer un object polygone qui contient tout ça
et des conditions xapi, résultat :
"*[tourism=hotel][bbox=6.5,45.5,7,46]"
Cette conction consomme beaucoup de mémoire car tout est stoquer en RAM avant d'être dumper dans la base, c'est plus efficace au niveau base
mais beaucoup moins en terme de consomation mémoire.
*/
function importation_osm_poi($bbox,$xapi_condition)
{
  global $config;
  global $tags_cache;
  $xapi_p=fopen($config['xapi_url_poi'].$xapi_condition."[bbox=$bbox->longitude_minimum,$bbox->latitude_minimum,$bbox->longitude_maximum,$bbox->latitude_maximum]","r");
  if (!$xapi_p)
    die("Connexion impossible");
  $osm_xml="";
  while (($buffer = fgets($xapi_p, 4096)) !== false) 
    $osm_xml.=$buffer;
  if (!feof($xapi_p)) 
    die("Error: unexpected fgets() fail\n");
  
  $osm = simplexml_load_string($osm_xml);
  if (isset($osm->node))
  {
    foreach ( $osm->node as $node )
    {
      if (isset($node->tag))
        {
	$pois_osm[(string)$node["id"]]=array("latitude" => (string)$node["lat"], (string)"longitude" => (string) $node["lon"]);
	foreach ( $node->tag as $tag )
	{
	  //Obligé de transtyper car la fonction simplexml_load_string gère les attributs de manière curieuse https://bugs.php.net/bug.php?id=29500
	  $tag_a_ajouter->k=(string) $tag['k'];
	  $tag_a_ajouter->v=(string) $tag['v'];
          $id_tag=insert_ou_recupere_tag($tag_a_ajouter); // Cette fonction s'occupe de remplir $tags_cache qui dispose d'un cache des tags (elle retourne l'id)
          $pois_osm[(string)$node["id"]]["tags"][]=$id_tag;
	}
      }
    }
  }
  else
    return erreur("Aucun POI récupéré depuis OSM");
  //print_r($pois_osm);
  foreach ($pois_osm as $id_poi => $poi)
  {
    foreach ($poi['tags'] as $tag)
      $sql_values_tags_poi.="($id_poi,$tag),";
    $sql_values_poi.="($id_poi,$poi[latitude],$poi[longitude]),";
  }
  $insert_poi="Insert ignore into osm_pois (id_osm_poi,latitude,longitude) values ".trim($sql_values_poi,",");
  mysql_query($insert_poi);
  print($insert_poi."\n");
  $insert_poi_tags="Insert ignore into osm_pois_tags (id_osm_poi,id_osm_tag) values ".trim($sql_values_tags_poi,",");
  mysql_query($insert_poi_tags);
//print($insert_poi_tags."\n");
  return ok("Poi OSM importés avec succès (on espère)");
}

?>
