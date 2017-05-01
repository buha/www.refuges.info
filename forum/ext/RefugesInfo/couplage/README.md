RefugesInfo.couplage
====================

ARCHITECTURE
============
Les fichiers /forum/... proviennent de http://www.phpbb-fr.com/telechargements/ pack complet à l'exception de :
* /forum/config.php qui est livré avec refuges.info
* /forum/docs/... qui n'est pas installé
* /forum/install/... qui n'est pas installé
* /forum/ext/RefugesInfo/couplage/... qui contient une extension PhpBB V3.1+ livrée avec refuges.info
Son architecture est spécifiée ici : https://area51.phpbb.com/docs/dev/31x/extensions/tutorial_basics.html

L'autoload des classes PHP du modèle MVC/WRI étant incompatible avec celui de PhpBB basé sur Symphony,
un code ne peut s'exécuter que dans l'un ou l'autre des contextes.
* Les lectures d'infos du forum sont faites directement en PgSQL dans la base.
* Les modifications du forum sont faites en simulant l'appel d'une URL du forum (via la fonction submit_forum du fichier /forum/ext/RefugesInfo/couplage/api.php).

INSTALLATION
============
* Download http://www.phpbb-fr.com/telechargements/ pack complet
* Copy * sauf config.php docs install
* Les tables du forum sont préfixées phpbb3_

PARAMETRES DE CONFIGURATION
===========================
* Créer l’index de recherche pour « phpBB Native Fulltext » depuis la page Index de recherche.
* GÉNÉRAL / Paramètres des fichiers joints / Quota total de fichiers joints : 0
* GÉNÉRAL / Paramètres des fichiers joints / Taille maximale du fichier : 10 Mo
* GÉNÉRAL / Régler paramètres d'enregistrement : Mettre une question de confirmation
* GÉNÉRAL / Paramètres de cookie / Nom du cookie = phpbb3_wri
* GÉNÉRAL / Paramètres de sécurité / Validation de session IP = Aucune
* GÉNÉRAL / Paramètres de charge : NON anniversaires / liste moderateurs
* PERSONNALISER : activer Couplage "refuges.info"
