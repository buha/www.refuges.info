var layer_overpass = new L.GeoJSON.Ajax(
	'http://overpass-api.de/api/interpreter', {
		// Url args calculation
		argsGeoJSON: function(layer) {
			var req = `
[out:json][timeout:25];
(
	node["tourism"~"hotel|camp_site"]({{bbox}});
	way["tourism"~"hotel|camp_site"]({{bbox}});
	node["shop"~"supermarket|convenience"]({{bbox}});
	way["shop"~"supermarket|convenience"]({{bbox}});
);
out 100 center;
>;
				`,
				bounds = layer._map.getBounds(),
				elChoixOVER = document.getElementById('choixOVER');

			layer.options.disabled = bounds._northEast.lng - bounds._southWest.lng > 0.5;
			if (elChoixOVER) { //  Grisage du choix "Service" de la carte NAV
				elChoixOVER.style.color = layer.options.disabled ? 'orange' : '';
				elChoixOVER.title = layer.options.disabled ? 'Zoomer sur la carte pour voir les points' : '';
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
						d.tags.tourism == 'hotel' ? 'hotel' :
						d.tags.tourism == 'camp_site' ? 'camping' :
						d.tags.shop == 'convenience' ? 'ravitaillement' :
						d.tags.shop == 'supermarket' ? 'ravitaillement' :
						null,
					adresses = [
						t['addr:housenumber'],
						t['addr:street'],
						t['addr:postcode'],
						t['addr:city']
					],
					popup = [
						t.name ? '<b>' + t.name + (t.stars ? ' ' + '*'.repeat(t.stars) : '') + '</b>' : '',
						t.tourism == 'hotel' ? 'H&ocirc;tel' + (t.rooms ? ' ' + t.rooms + ' chambres' : '') : '',
						t.tourism == 'camp_site' ? 'Camping ' + (t.place ? t.place + ' places' : '') : '',
						t.shop == 'convenience' ? 'Alimentation' : '',
						t.shop == 'supermarket' ? 'Supermarch&egrave;' : '',
						t['contact:phone'], t['phone'],
						t.email ? '<a href="mailto:' + t.email + '">' + t.email + '</a>' : '',
						t['addr:street'] ? adresses.join(' ') : '',
						t.website ? '<a href="' + (t.website.search('http') ? 'http://' : '') + t.website + '">' + t.website + '</a>' : '',
						'<a class="popup-copyright" href="http://www.openstreetmap.org/'+(d.center ? 'way' : 'node')+'/' + d.id + '">&copy;</a>'
					];
				if (d.center) // Cas des éléments décrits par leurs contours
					Object.assign(d, d.center);
				
				if (d.lon && d.lat && iconUrl)
					geo.push({
						type: 'Feature',
						id: d.id,
						properties: {
							iconUrl: '/images/icones/' + iconUrl + '.png',
							title: t.name,
							popup: '<p>' + popup.join('</p><p>') + '</p>'											
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
			iconAnchor: [8, 8],
			popupAnchor: [-1, -9],
			degroup: 12
		}
	}
);