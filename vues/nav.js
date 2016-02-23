<?// Code Javascript de la page des cartes

// Ce fichier ne doit contenir que du code javascript destiné à être inclus dans la page
// $vue contient les données passées par le fichier PHP
// $config les données communes à tout WRI
?>

// Style de base des polylines édités
L.Polyline = L.Polyline.extend({
	options: {
		color: 'blue',
		weight: 4,
		opacity: 1,
	}
});

var map,
	type_points = '<?=$_COOKIE['type_points'] ? $_COOKIE['type_points'] : ''?>',
	arg_massifs = '<?=$vue->polygone->id_polygone?>',
	layerSwitcher,
	baseLayers = {
		'Refuges.info':new L.TileLayer.OSM.MRI(),
		'OSM fr':      new L.TileLayer.OSM.FR(),
		'Outdoors':    new L.TileLayer.OSM.Outdoors(),
		'IGN':         new L.TileLayer.IGN({k: '<?=$config['ign_key']?>', l:'GEOGRAPHICALGRIDSYSTEMS.MAPS'}),
		'IGN Express': new L.TileLayer.IGN({k: '<?=$config['ign_key']?>', l:'GEOGRAPHICALGRIDSYSTEMS.MAPS.SCAN-EXPRESS.CLASSIQUE'}),
		'SwissTopo':   new L.TileLayer.SwissTopo({l:'ch.swisstopo.pixelkarte-farbe'}),
		'Autriche':    new L.TileLayer.Kompass({l:'Touristik'}),
		'Espagne':     new L.TileLayer.WMS.IDEE(),
		'Italie':      new L.TileLayer.WMS.IGM(),
		'Angleterre':  new L.TileLayer.OSOpenSpace('<?=$config['os_key']?>', {}),
		'Photo Bing':  new L.BingLayer('<?=$config['bing_key']?>', {type:'Aerial'}),
		'Photo IGN':   new L.TileLayer.IGN({k: '<?=$config['ign_key']?>', l:'ORTHOIMAGERY.ORTHOPHOTOS'})
	},

<?if ( $vue->mode_affichage == 'edit' ){?>
	// Dessine tous les massifs pour servir de gabari au nouveau
	massifLayer = new L.GeoJSON.Ajax(
		'<?=$config['sous_dossier_installation']?>api/polygones', {
			argsGeoJSON: {
				type_polygon: 1,
				type_geom: 'polylines', // La surface à l'intérieur des massifs reste cliquable
				time: <?=time()?> // Inhibe le cache
			},
			style: function(feature) {
				return {
					color: 'blue',
					weight: 2,
					opacity: 0.6,
					fillOpacity: 0
				}
			}
		}
	),
<?}else if ( $vue->mode_affichage == 'zone' ){?>
	// Affiche tous les massifs d'une zone (en différentes couleurs)
	massifLayer = new L.GeoJSON.Ajax(
		'<?=$config['sous_dossier_installation']?>api/polygones', {
			argsGeoJSON: {
				type_polygon: 1,
				intersection: '<?=$vue->polygone->id_polygone?>'
			},
			style: function(feature) {
				return {
					title: feature.properties.nom,
					popupAnchor: [-1, -4],
					url: feature.properties.lien,
					color: 'black',
					fillColor: feature.properties.couleur,
					weight: 1,
					fillOpacity: 0.3
				}
			}
		}
	),
<?}else{?>
	// Affiche le contour d'un seul massif 
	massifLayer = new L.GeoJSON.Ajax(
		'<?=$config['sous_dossier_installation']?>api/polygones', {
			argsGeoJSON: {
				massif: '<?=$vue->polygone->id_polygone?>',
				type_geom: 'polylines', // La surface à l'intérieur des massifs reste cliquable
			},
			style: function(feature) {
				return {
					color: 'blue',
					weight: 2,
					opacity: 1,
					fillOpacity: 0
				}
			}
		}
	),
<?}?>

	// Points de refuges.info
	poiWRI = new L.GeoJSON.Ajax.WRIpoi({
		urlRoot: '<?=$config['sous_dossier_installation']?>',
		urlGeoJSON: 'api/bbox',
		argsGeoJSON: {
			type_points: type_points
		},
		disabled: !type_points
	}),
	// Points appartenant à un massif
	poiMassif = new L.GeoJSON.Ajax.WRIpoi({
		urlRoot: '<?=$config['sous_dossier_installation']?>',
		urlGeoJSON: 'api/massif',
		argsGeoJSON: {
			type_points: type_points,
			massif: arg_massifs
		},
		disabled: !type_points
	}),
	poiLayer = <?if ( $vue->polygone->id_polygone ) {?>poiMassif<?}else{?>poiWRI<?}?>,

	// Points via chemineur.fr
	poiCHEM = new L.GeoJSON.Ajax.chem(),
	poiPRC = new L.GeoJSON.Ajax.chem({
		argsGeoJSON: {
			site: 'prc'
		},
		urlRootRef: 'http://www.pyrenees-refuges.com/fr/affiche.php?numenr='
	}),
	poiC2C = new L.GeoJSON.Ajax.chem({
		argsGeoJSON: {
			site: 'c2c'
		},
		urlRootRef: 'http://www.camptocamp.org/huts/'
	}),

	// Points de https://overpass-turbo.eu/
	poiOVER = new L.GeoJSON.Ajax.OSMoverpass();

window.addEventListener('load', function() {
	map = new L.Map('nav_bloc_carte', {
		layers: [
				baseLayers['<?=$config["carte_base"]?>'] || // Sinon le fond de carte par défaut
				baseLayers[Object.keys(baseLayers)[0]], // Sinon la première couche définie
			massifLayer
		]
	});

	map.setView([45.6, 6.7], 6);
	new L.Control.Permalink.Cookies({
		text: 'Permalien',
		layers: new L.Control.Layers(baseLayers).addTo(map) // Le controle de changement de couche de carte avec la liste des cartes dispo
	}).addTo(map);

<?if ( $vue->polygone->bbox ){?>
	var bboxs = [<?=$vue->polygone->bbox?>]; // BBox au format Openlayers [left, bottom, right, top] = [west, south, east, north]
	map.fitBounds([ // Bbox au format Leaflet
		[bboxs[1], bboxs[0]], // South West
		[bboxs[3], bboxs[2]]  // North East
	]);
<?}?>

	new L.Control.Scale().addTo(map);
	new L.Control.Coordinates().addTo(map);

	<?if ( $vue->mode_affichage != 'zone' ){?>
		new L.Control.Fullscreen().addTo(map);
		new L.Control.OSMGeocoder({
			position: 'topleft'
		}).addTo(map);
		new L.Control.Gps().addTo(map);
		var fl = L.Control.fileLayerLoad().addTo(map);
	<?}?>

	<?if ( !$vue->mode_affichage ){?>
		poiLayer.addTo(map);
		poiOVER.addTo(map);
	<?}?>

	<?if ( $vue->mode_affichage == 'edit' ){?>
		// Editeur et aide de l'éditeur
		var edit = new L.Control.Draw.Plus({
			draw: {
				polygon: true
			},
			edit: {
				remove: true
			},
			editType: 'MultiPolygon', // Force le format de sortie geoGson
		}).addTo(map);
		fl.loader.on ('data:loaded', function (args){
			this._map.fire('draw:created', { // Rend la trace éditable
				layer: args.layer
			});
		}, fl);
		
		massifLayer.addTo(edit.snapLayers); // Permet de "coller" aux tracés des autres massifs
	<?}?>
	maj_poi(); // Initialise la coche [de]cocher
});
/*************************************************************************************************************************************/
function switch_massif (combo) {
    if (combo.checked) {
        document.getElementById ('titrepage') .firstChild.nodeValue = "<?echo addslashes($vue->titre)?>"; 
		map.addLayer(massifLayer);
		map.removeLayer(poiLayer);
		map.addLayer(poiLayer = poiMassif);
    } else {
        document.getElementById ('titrepage') .firstChild.nodeValue = "Navigation sur les cartes"; 
		map.removeLayer(massifLayer);
		map.removeLayer(poiLayer);
		map.addLayer(poiLayer = poiWRI);
    }
}
/*************************************************************************************************************************************/
function maj_poi (c) {
    // Calcule l'argument d'extration filtre de points
    var poitypes = document.getElementsByName ('point_type[]'),
		check_types = document.getElementsByName ('check-types-input'),
		allchecked = true;

    type_points = '';
    for (var i=0; i < poitypes.length; i++) {
		if (c && check_types.length)
			poitypes[i].checked = check_types[0].checked;
        if (poitypes[i].checked)
            type_points += (type_points ? ',' : '') + poitypes[i].value;
		else
			allchecked = false;
	}
	check_types[0].checked = allchecked;
    // L'écrit dans un cookie pour se les rappeler au prochain affichage de cette page
    document.cookie = 'type_points=' + escape (type_points) + ';path=/';

	// On reparamètre les couches POI
	poiWRI.options.argsGeoJSON.type_points = 
	poiMassif.options.argsGeoJSON.type_points =
		type_points;
	poiLayer.options.disabled = !type_points;

	// Et on réaffiche la couche courante
	poiLayer.reload();
}
/*************************************************************************************************************************************/
function maj_autres_site(e,l) {
	if(e.checked)
		map.addLayer(l);
	else
		map.removeLayer(l);
}
