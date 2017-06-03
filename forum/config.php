<?php
// phpBB 3.2.x auto-generated configuration file
// Do not change anything in this file!

// Récupère les données locales
require(__DIR__.'/../config_privee.php');

$dbms = 'phpbb\\db\\driver\\postgres';
$dbhost = $wri['serveur_pgsql'];
$dbport = '';
$dbname = $wri['base_pgsql'];
$dbuser = $wri['utilisateur_pgsql'];
$dbpasswd = $wri['mot_de_passe_pgsql'];
$table_prefix = 'phpbb3_';
$phpbb_adm_relative_path = 'adm/';
$acm_type = 'phpbb\\cache\\driver\\file';

@define('PHPBB_INSTALLED', true);
// @define('PHPBB_DISPLAY_LOAD_TIME', true);

if (!@$wri['debug'])
	@define('PHPBB_ENVIRONMENT', 'production');

// @define('DEBUG_CONTAINER', true);
