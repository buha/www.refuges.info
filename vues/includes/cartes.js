/*
 * Copyright (c) 2016 Dominique Cavailhez
 * https://github.com/Dominique92/Leaflet.GeoJSON.Ajax
 *
 * Couches geoJSON pour www.refuges.info
 */

var baseLayers = {
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
};

// Points d'interêt refuges.info
L.GeoJSON.Ajax.wriPoi = L.GeoJSON.Ajax.extend({
	options: {
		urlGeoJSON: '<?=$config['sous_dossier_installation']?>api/bbox',
		argsGeoJSON: {
			type_points: 'all'
		},
		bbox: true,
		style: function(feature) {
			var prop = [];
			if (feature.properties.coord.alt)
				prop.push(feature.properties.coord.alt + 'm');
			if (feature.properties.places.valeur)
				prop.push(feature.properties.places.valeur + '<img src="' + '<?=$config['sous_dossier_installation']?>images/lit.png"/>');
			this.options.disabled = !this.options.argsGeoJSON.type_points;
			return {
				url: feature.properties.lien,
				iconUrl: '<?=$config['sous_dossier_installation']?>images/icones/' + feature.properties.type.icone + '.png',
				iconAnchor: [8, 4],
				title: '<a href="' + feature.properties.lien + '">' + feature.properties.nom + '</a>' +
					(prop.length ? '<div style=text-align:center>' + prop.join(' ') + '</div>' : ''),
				labelClass: 'carte-point-etiquette',
				remanent: true,
				degroup: 12 // Spread the icons when the cursor hovers on a busy area.
			};
		}
	}
});

// Points d'interêt via chemineur.fr
L.GeoJSON.Ajax.chem = L.GeoJSON.Ajax.extend({
	options: {
		urlGeoJSON: 'http://v2.chemineur.fr/prod/chem/json.php',
		urlRootRef: 'http://chemineur.fr/point/',
		bbox: true,
		style: function(feature) {
			return {
				title: feature.properties.nom + ' <a href="' + this.options.urlRootRef + feature.properties.id + '">&copy;</a>',
				url: this.options.urlRootRef + feature.properties.id,
				iconUrl: 'http://v2.chemineur.fr/prod/chemtype/' + feature.properties.type.icone + '.png',
				iconAnchor: [8, 4],
				labelClass: 'carte-site-etiquette',
				remanent: true,
				degroup: 12
			};
		}
	}
});

// Points d'interêt OSM overpass
L.GeoJSON.Ajax.OSMoverpass = L.GeoJSON.Ajax.extend({

	// Convert received data in geoJson format
	options: {
		urlGeoJSON: 'http://overpass-api.de/api/interpreter',
		bbox: true,
		maxLatAperture: 0.2, // (Latitude degrees) The layer will only be displayed if it's zooms to less than this latitude aperture degrees.
		services: {
			tourism: 'hotel|camp_site',
			shop: 'supermarket|convenience',
			amenity: 'parking'
		},

		// Url args calculation
		argsGeoJSON: function() {
			// Affiche le status: none | zoom | wait | some | zero
			if (!this.os)
				this.os = document.getElementById('overpass-status') || document.createElement('div');

			// Services sélectionnés
			var st = document.getElementsByName('service_type[]'),
				services = {};
			if (st.length) {
				for (var e = 0; e < st.length; e++)
					if (st[e].checked) {
						var val = st[e].value.split('~');
						if (typeof services[val[0]] == 'undefined')
							services[val[0]] = val[1];
						else
							services[val[0]] += '|'+val[1];
					}
				if (!Object.keys(services).length) { // Pas de sélection
					this.options.disabled = true;
					this.os.className = 'over-none';
					return false;
				}
			} else // Pas de coches: services par défaut
				services = this.options.services;

			// Zoom trop large
			var b = this._map.getBounds();
			if (b._northEast.lng - b._southWest.lng > this.options.maxLatAperture) {
				this.options.disabled = true;
				this.os.className = 'over-zoom';
				return false;
			}

			this.options.disabled = false;
			this.os.className = 'over-wait';

			// Calcul de la requette
			var r = '[out:json][timeout:25];(\n',
				bbox = b._southWest.lat + ',' + b._southWest.lng + ',' + b._northEast.lat + ',' + b._northEast.lng;
			for (var s in services) {
				var x = '["' + s + '"~"' + services[s] + '"](' + bbox + ');\n';
				r += 'node' + x + 'way' + x;
			}
			return {
				data: r + ');out 100 center;>;'
			};
		},

		// Convert received data in geoJson format
		tradJson: function(data) {
			this.os.className = data.elements.length
				? 'over-some'
				: 'over-zero';

			var geo = [];
			for (var e in data.elements) {
				var d = data.elements[e],
					t = d.tags,
					icon =
						t.tourism == 'hotel' ? 'hotel' :
						t.tourism == 'camp_site' ? 'camping' :
						t.shop == 'convenience' ? 'ravitaillement' :
						t.amenity == 'parking' ? 'parking' :
						null,
					adresses = [
						t['addr:housenumber'],
						t['addr:street'],
						t['addr:postcode'],
						t['addr:city']
					],
					c = ' <a href="http://www.openstreetmap.org/' + (d.center ? 'way' : 'node') + '/' + d.id + '">&copy;</a>',
					popup = [
						t.name ? '<b>' + t.name + '</b>' : '',
						t.tourism == 'hotel'
							? 'H&ocirc;tel' + (t.stars ? ' ' + '*'.repeat(t.stars) : '') + (t.rooms ? ' ' + t.rooms + ' chambres' : '') + c
							: '',
						t.tourism == 'camp_site' ? 'Camping ' + (t.place ? t.place + ' places' : '') + c : '',
						t.shop == 'convenience' ? 'Alimentation' + c : '',
						t.shop == 'supermarket' ? 'Supermarch&egrave;' + c : '',
						t.amenity == 'parking' ? 'Parking' + (t.capacity ? t.capacity + ' places' : '') + c : '',
						t['contact:phone'], t['phone'],
						t.email ? '<a href="mailto:' + t.email + '">' + t.email + '</a>' : '',
						t['addr:street'] ? adresses.join(' ') : '',
						t.website ? '<a href="' + (t.website.search('http') ? 'http://' : '') + t.website + '">' + t.website + '</a>' : ''
					];
				if (d.center) // Cas des éléments décrits par leurs contours
					Object.assign(d, d.center);

				if (d.lon && d.lat && icon)
					geo.push({
						type: 'Feature',
						id: d.id,
						properties: {
							icon: icon,
							title: '<p>' + popup.join('</p><p>') + '</p>'
						},
						geometry: {
							type: 'Point',
							coordinates: [d.lon, d.lat]
						}
					});
			}
			return geo;
		},

		// Finalement, on assigne les éléments spécifiques du style d'affichage des pixels
		style: function(feature) {
			return {
				iconUrl: '/images/icones/' + feature.properties.icon + '.png',
				iconAnchor: [8, 4],
				labelClass: 'carte-service-etiquette',
				remanent: true,
				degroup: 12
			};
		}
	},

	error429: function() { // Too many requests or request timed out
		this.os.className = 'over-zoom';
	},

	error504: function() { // Gateway request timed out
		this.os.className = 'over-zoom';
	}
});
