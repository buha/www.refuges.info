/*
 * Copyright (c) 2016 Dominique Cavailhez
 * https://github.com/Dominique92/Leaflet.GeoJSON.Ajax
 *
 * Couches geoJSON pour www.refuges.info
 */

// Points d'interêt refuges.info
L.GeoJSON.Ajax.WRIpoi = L.GeoJSON.Ajax.extend({
	options: {
		urlGeoJSON: 'api/bbox',
		argsGeoJSON: {
			type_points: 'all'
		},
		bbox: true,
		style: function(feature) {
			var prop = [];
			if (feature.properties.coord.alt)
				prop.push (feature.properties.coord.alt+'m');
			if (feature.properties.places.valeur)
				prop.push (feature.properties.places.valeur+'<img src="'+this.options.urlRoot+'images/lit.png"/>');
			this.options.disabled = !this.options.argsGeoJSON.type_points;
			return {
				url: feature.properties.lien,
				iconUrl: this.options.urlRoot+'images/icones/' + feature.properties.type.icone + '.png',
				iconAnchor: [8, 4],
				title: '<a href="'+feature.properties.lien+'">'+feature.properties.nom+'</a>' +
					(prop.length
						? '<div style=text-align:center>'+prop.join(' ')+'</div>'
						: ''
					),
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
				title: feature.properties.nom + ' <a href="'+this.options.urlRootRef + feature.properties.id+'">&copy;</a>',
				url: this.options.urlRootRef + feature.properties.id,
				iconUrl: 'http://v2.chemineur.fr/prod/chemtype/' + feature.properties.type.icone + '.png',
				iconAnchor: [8, 4],
				labelClass: 'carte-site-etiquette',
				remanent: true,
				degroup: 12
			}
		}
	}
});

// Points d'interêt OSM overpass
L.GeoJSON.Ajax.OSMoverpass = L.GeoJSON.Ajax.extend({
	options: {
		urlGeoJSON: 'http://overpass-api.de/api/interpreter',
		maxLatAperture: 0.5, // (Latitude degrees) The layer will only be displayed if it's zooms to less than this latitude aperture degrees.

		// Url args calculation
		argsGeoJSON: function(layer) {
			var req =
'[out:json][timeout:25];'+
'('+
	'node["tourism"~"hotel|camp_site"]({{bbox}});'+
	'way["tourism"~"hotel|camp_site"]({{bbox}});'+
	'node["shop"~"supermarket|convenience"]({{bbox}});'+
	'way["shop"~"supermarket|convenience"]({{bbox}});'+
	'node["amenity"~"parking"]({{bbox}});'+
	'way["amenity"~"parking"]({{bbox}});'+
');'+
'out 100 center;'+
'>;';

			// Tuning off the layer when too low zoom level
			var bounds = layer._map.getBounds();
			layer.options.disabled = bounds._northEast.lng - bounds._southWest.lng > layer.options.maxLatAperture;

			var elChoixOVER = document.getElementById('choixOVER');
			if (elChoixOVER) { //  Grisage du choix "Service" de la carte NAV
				elChoixOVER.className = layer.options.disabled ? 'layer-zoom-out' : '';
//				elChoixOVER.style.color = layer.options.disabled ? 'orange' : '';
//				elChoixOVER.title = layer.options.disabled ? 'Zoomer sur la carte pour voir les points' : '';
			}

			return {
				data: req.replace(/{{bbox}}/g, bounds._southWest.lat + ',' + bounds._southWest.lng + ',' + bounds._northEast.lat + ',' + bounds._northEast.lng)
			};
		},
		bbox: true,

		// Convert received data in geoJson format
		tradJson: function(data) {
			var geo = [];
			for (e in data.elements) {
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
					c = ' <a href="http://www.openstreetmap.org/'+(d.center ? 'way' : 'node')+'/' + d.id + '">&copy;</a>',
					popup = [
						t.name ? '<b>' + t.name + (t.stars ? ' ' + '*'.repeat(t.stars) : '') + '</b>' : '',
						t.tourism == 'hotel' ? 'H&ocirc;tel' + (t.rooms ? ' ' + t.rooms + ' chambres' : '') + c : '',
						t.tourism == 'camp_site' ? 'Camping ' + (t.place ? t.place + ' places' : '') + c : '',
						t.shop == 'convenience' ? 'Alimentation' + c : '',
						t.shop == 'supermarket' ? 'Supermarch&egrave;' + c : '',
						t.amenity == 'parking' ? 'Parking' + (t.capacity ? t.capacity + ' places' : '') + c: '',
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
		style: {
			iconAnchor: [8, 4],
			labelClass: 'carte-service-etiquette',
			remanent: true,
			degroup: 12
		}
	}
});