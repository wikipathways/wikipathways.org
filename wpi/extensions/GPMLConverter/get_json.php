<html>
	 <head>
		  <title>PHP Test</title>
	 </head>
	 <body>
		 <?php
		ini_set('display_startup_errors',1);
		ini_set('display_errors',1);
		error_reporting(-1);
		include "GPMLConverter.php";
		$gpml_path = '/var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter/WP2864_79278.gpml';
		$identifier = 'WP2864';
		$version = '79278';
		$organism="Human";
		$pvjson=GPMLConverter::gpml2pvjson(array("gpml_path"=>$gpml_path, "identifier"=>$identifier, "version"=>$version, "organism"=>$organism));
		echo GPMLConverter::pvjson2svg($pvjson, array("static"=>false));
		 ?>
	 </body>
</html>
