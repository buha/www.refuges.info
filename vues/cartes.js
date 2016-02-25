/*
 * Copyright (c) 2016 Dominique Cavailhez
 * https://github.com/Dominique92/Leaflet.GeoJSON.Ajax
 *
 * Couches geoJSON pour www.refuges.info
 */

if (typeof type_points == 'undefined')
	var type_points = 'all';

// Points d'interêt refuges.info
L.GeoJSON.Ajax.wriPoi = L.GeoJSON.Ajax.extend({
	options: {
		urlGeoJSON: sous_dossier_installation+'api/bbox',
		argsGeoJSON: {
			type_points: type_points
		},
		bbox: true,
		disabled: !type_points,
		style: function(feature) {
			var prop = [];
			if (feature.properties.coord.alt)
				prop.push(feature.properties.coord.alt + 'm');
			if (feature.properties.places.valeur)
				prop.push(feature.properties.places.valeur + '<img src="' + sous_dossier_installation + 'images/lit.png"/>');
			this.options.disabled = !this.options.argsGeoJSON.type_points;
			return {
				url: feature.properties.lien,
				iconUrl: sous_dossier_installation + 'images/icones/' + feature.properties.type.icone + '.png',
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

L.GeoJSON.Ajax.wriPoi.dansMassif = L.GeoJSON.Ajax.wriPoi.extend({
	urlGeoJSON: sous_dossier_installation+'api/massif',
	argsGeoJSON: {
		type_points: null,
		massif: typeof arg_massifs == 'string' ? arg_massifs : ''
	},
	disabled: !type_points
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

		// Url args calculation
		argsGeoJSON: function() {
			this.oz = document.getElementById('over-zoom') || document.createElement('div');
			this.ow = document.getElementById('over-wait') || document.createElement('div');
			this.st = document.getElementsByName('service_type[]');

			// Efface les icones d'avancement
			this.oz.style.display = 'none';
			this.ow.style.display = 'none';
			this.options.disabled = true;

			// Services sélectionnés
			var services = {};
			if (this.st.length) {
				for (var e = 0; e < this.st.length; e++)
					if (this.st[e].checked) {
						var val = this.st[e].value.split('~');
						if (typeof services[val[0]] == 'undefined')
							services[val[0]] = val[1];
						else
							services[val[0]] += '|'+val[1];
					}
				if (!Object.keys(services).length) // Pas de sélection
					return false;
			} else
				services = { // Quand il n'y a pas de coches (fiche point)
					tourism: 'hotel|camp_site',
					shop: 'supermarket|convenience',
					amenity: 'parking'
				};

			// Zoom trop large
			var b = this._map.getBounds();
			if (b._northEast.lng - b._southWest.lng > this.options.maxLatAperture) {
				this.oz.style.display = ''; // On affiche la loupe rouge
				return false;
			}

			this.ow.style.display = ''; // On affiche le sablier
			this.options.disabled = false;

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
			this.ow.style.display = 'none'; // Les datas sont arrivées: on efface le sablier

			var geo = [];
			for (var e in data.elements) {
				var d = data.elements[e],
					t = d.tags,
					iconUrl =
						t.tourism == 'hotel' ? 'hotel' :
						t.tourism == 'camp_site' ? 'camping' :
						t.shop == 'convenience' || t.shop == 'convenience' ? 'ravitaillement' :
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

				if (d.lon && d.lat && iconUrl)
					geo.push({
						type: 'Feature',
						id: d.id,
						properties: {
							iconUrl: '/images/icones/' + iconUrl + '.png',
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
		style: {
			iconAnchor: [8, 4],
			labelClass: 'carte-service-etiquette',
			remanent: true,
			degroup: 12
		}
	},

	error429: function() { // Too many requests or request timed out
		this.ow.style.display = 'none'; // On efface le sablier
		this.oz.style.display = ''; // On affiche la loupe rouge
	},

	error504: function() { // Gateway request timed out
		this.error429();
	}
});
