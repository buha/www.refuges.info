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
				bb = layer.getBbox().split(',');
			layer.options.disabled = bb[2] - bb[0] > .5; // Inhibe la bbox au dessus de 0,5° d'ouverture en longitude
			return {
				data: req.replace(/{{bbox}}/g, bb[1] + ',' + bb[0] + ',' + bb[3] + ',' + bb[2])
			};
		},

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
						t.shop == 'supermarket' ? 'Supermarché' : '',
						t['contact:phone'], t['phone'],
						t.email ? '<a href="mailto:' + t.email + '">' + t.email + '</a>' : '',
						t['addr:street'] ? adresses.join(' ') : '',
						t.website ? '<a href="' + (t.website.search('http') ? 'http://' : '') + t.website + '">' + t.website + '</a>' : '',
						'<a class="popup-copy" href="http://www.openstreetmap.org/'+(d.center ? 'way' : 'node')+'/' + d.id + '">&copy;</a>'
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