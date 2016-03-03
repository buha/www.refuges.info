/*
 * Copyright (c) 2016 Dominique Cavailhez
 * Affichage de la m�t�o d'un point
 *
 * Source: http://www.prevision-meteo.ch/services
 * http://www.prevision-meteo.ch/uploads/pdf/recuperation-donnees-meteo.pdf
 */

function meteo_fcst(data) {
	var html = '<div>' +
		'<p>' + data.day_long + '</p>' +
		'<p>' + data.tmin + ' � ' + data.tmax + '�</p>',
		intervalles_par_jour = Math.min(6, Math.floor(document.getElementById('meteo').offsetWidth / 5 /* Jours pr�sent�s */ / 31 /* Pixels par ic�ne */ )),
		heures_par_intervalle = Math.round(24 / intervalles_par_jour);

	// D�termination des heures de T� min & max
	var h_t_min = 0,
		h_t_max = 0;
	for (h = 0; h < 24; h++) {
		if (data.hourly_data[h + 'H00'].TMP2m < data.hourly_data[h_t_min + 'H00'].TMP2m) h_t_min = h;
		if (data.hourly_data[h + 'H00'].TMP2m > data.hourly_data[h_t_max + 'H00'].TMP2m) h_t_max = h;
	}

	for (debut_intervalle = 0; debut_intervalle < 24; debut_intervalle += heures_par_intervalle) {
		var precipitation = 0,
			h_pluie_max, pluie_max,
			heure = debut_intervalle + (intervalles_par_jour == 2 ? 0 : Math.floor(heures_par_intervalle / 2));
		if (debut_intervalle <= h_t_min && h_t_min < debut_intervalle + heures_par_intervalle) heure = h_t_min; // On prend cette heure si c'est la temp�rature la plus basse
		if (debut_intervalle <= h_t_max && h_t_max < debut_intervalle + heures_par_intervalle) heure = h_t_max; // On prend cette heure si c'est la temp�rature la plus �lev�e
		for (h = debut_intervalle; h < Math.min(24, debut_intervalle + heures_par_intervalle); h++) {
			var data_heure = data.hourly_data[h + 'H00'];
			precipitation += data_heure.APCPsfc;
			if (pluie_max < data_heure.APCPsfc) {
				pluie_max = data_heure.APCPsfc;
				heure = h; // On prend cette heure si c'est la pluie la plus intense
			}
		}
		var data_heure = data.hourly_data[heure + 'H00'],
			comment =
			data_heure.CONDITION + '\n' +
			Math.round(data_heure.TMP2m) +
			(Math.round(data_heure.WNDCHILL2m) < Math.round(data_heure.TMP2m) ? '� ressenti ' + Math.round(data_heure.WNDCHILL2m) : '') +
			'� � ' + heure + 'h\n' +
			'Humidit� ' + data_heure.RH2m + '%\n' +
			(!precipitation ? '' : 'Pr�cipitation ' + Math.round(precipitation) + 'mm de ' + debut_intervalle + ' � ' + Math.min(24, debut_intervalle + heures_par_intervalle) + 'h\n') +
			'Vent ' + data_heure.WNDDIRCARD10 + ' ' + data_heure.WNDSPD10m + 'km/h' + (data_heure.WNDGUST10m < data_heure.WNDSPD10m ? '' : ' rafales � ' + data_heure.WNDGUST10m + 'km/h');
		html += '<div title="' + comment + '"><img width="30" src="' + data_heure.ICON + '" /></div>';
	}
	return html + '</div>';
}

var xhttp;

function meteo_draw() {
	var html = '';
	if (xhttp.readyState == 4 && xhttp.status == 200) {
		var jsonObj = JSON.parse(xhttp.responseText);
		for (k in jsonObj) {
			var f = window['meteo_' + k.split('_')[0]];
			if (typeof f == 'function')
				html += f(jsonObj[k]);
		}
	}
	document.getElementById('meteo').innerHTML = html;
}

function meteo() {
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = meteo_draw;
	xhttp.open('GET', 'http://www.prevision-meteo.ch/services/json/lat=<?=$vue->point->latitude?>lng=<?=$vue->point->longitude?>', true);
	xhttp.send();
	window.onresize = meteo_draw;
}

function meteo_run(el) {
	var dd = document.createElement("dd"),
		dt = el.parentElement;
	dt.innerHTML = '<dt><?=$vue->nom_debut_majuscule?> &copy; <a href="http://www.prevision-meteo.ch/">prevision-meteo.ch</a></dt>';
	dd.innerHTML = '<div id="meteo"></div><br style="clear:both" />';
	dt.parentNode.insertBefore(dd, dt.nextSibling);
	meteo();
}