<?php
/**
Entry point for a pathway viewer widget that can be included in other pages.

This page will display the interactive pathway viewer for a given pathway. It takes the following parameters:
- identifier: the pathway identifier (e.g. WP4)
- version: the version (revision) number of a specific version of the pathway (optional, leave out to display the newest version)

You can include a pathway viewer in another website using an iframe:

<iframe src ="http://www.wikipathways.org/pathways/WP4?diagram-only=true" width="500" height="500" style="overflow:hidden;"></iframe>
*/
require_once('wpi.php');
require_once('extensions/PathwayViewer/PathwayViewer.php');
header("X-XSS-Protection: 0");
?>
<!DOCTYPE HTML>
<html lang="en">
<head>
<meta charset="UTF-8">
<style  type="text/css">
	a#wplink {
	text-decoration:none;
	font-family:serif;
	color:black;
	font-size:12px;
	}
	#logolink {
		float:right;
		top:-20px;
		left: -10px;
		position:relative;
		z-index:2;
		opacity: 0.5;
	}
	html, body {
		width:100%;
		height:100%;
	}
	#pvjs-widget {
		top:0;
		left:0;
		font-size:12px;
		width:100%;
		height:inherit;
	}
</style>
<?php
//Initialize javascript
echo '<script type="text/javascript" src="' . $jsJQuery . '"></script>' . "\n";

$identifier = isset($_REQUEST['identifier']) ? $_REQUEST['identifier'] : $_REQUEST['id'];
$version = isset($_REQUEST['version']) ? $_REQUEST['version'] : isset($_REQUEST['rev']) ? $_REQUEST['rev'] : 0;

$pathway = Pathway::newFromTitle($identifier);
if($version) {
		$pathway->setActiveRevision($version);
}

/*
The widget used this format for highlighting up until 2017:
Xref
http://www.wikipathways.org/wpi/PathwayWidget.php?id=<PathwayId>&xref=<XrefId>,<XrefDataSource>&colors=<color[,color...]>&rev=<VersionNumber>
Label
http://www.wikipathways.org/wpi/PathwayWidget.php?id=<PathwayId>&label=<TextContent>&colors=<color[,color...]>&rev=<VersionNumber>
To highlight multiple Xrefs and/or Labels, add "[]" to query param names, e.g.: foo[]=bar1&foo[]=bar2

where:
	color: a name, e.g., red, or a hexadecimal, e.g., %23FF0000
		("%23" is the URL-encoded version of "#")
	XrefId: the identifier specified for a DataNode Xref by the pathway author,
		e.g., 1234
	XrefDataSource: the BridgeDb conventional name specified for a DataNode Xref
		by the pathway author, e.g., Entrez Gene
	TextContent: the name or label of the entity

In 2017, we switched to this format:
http://www.wikipathways.org/pathways/WP4?diagram-only=true&<color>=<target>[,<target>...][&<color>=<target>[,<target>...]][&version=<VersionNumber>]

where:
	color: a name, e.g., red, or a hexadecimal, e.g., FF0000 (no "#")
	target can be any of the following:
		Entity ID, ie., GraphId in GPML
		<XrefDataSource>:<XrefId> for a DataNode Xref
			XrefDataSource:
				BridgeDb conventional name specified by the pathway author,
					e.g., Ensembl, Entrez Gene, HMDB, etc.
				ensembl (GeneProducts only)
				ncbigene (GeneProducts only)
				wikidata
			XrefId: the identifier for a DataNode Xref, e.g., 1234
		Entity Type, e.g., DataNode, Metabolite, Interaction, Mitochondria, or
			one of the other words from the GPML or WP vocabs.
		TextContent: the name or label of the entity (URL-encoded)

http://www.wikipathways.org/wpi/PathwayWidget.php?id=WP87 =>
http://www.wikipathways.org/pathways/WP4?diagram-only=true

http://www.wikipathways.org/wpi/PathwayWidget.php?id=WP87&rev=7772 =>
http://www.wikipathways.org/pathways/WP4?diagram-only=true&version=7772

http://www.wikipathways.org/wpi/PathwayWidget.php?id=WP4&label=CRH&xref=8525,Entrez%20Gene&colors=green,blue =>
http://www.wikipathways.org/pathways/WP4?green=CRH&blue=ncbigene:8525&diagram-only=true


http://www.wikipathways.org/wpi/PathwayWidget.php?id=WP4&label=APC&xref=HMDB00193,HMDB&colors=green,blue =>
http://www.wikipathways.org/pathways/WP4?green=APC&blue=HMDB:HMDB00193&diagram-only=true

http://www.wikipathways.org/wpi/PathwayWidget.php?id=WP4&label[]=APC&label[]=TP53&label[]=ATP&colors=green,red,blue =>
http://www.wikipathways.org/pathways/WP4?green=APC&red=TP53&blue=ATP&diagram-only=true

http://www.wikipathways.org/wpi/PathwayWidget.php?id=WP4&xref[]=324,Entrez Gene&xref[]=HMDB00193,HMDB&colors=purple =>
http://www.wikipathways.org/pathways/WP4?purple=Entrez Gene:324,HMDB:HMDB00193&diagram-only=true

http://www.wikipathways.org/wpi/PathwayWidget.php?id=WP4&xref[]=324,Entrez Gene&xref=HMDB00193,HMDB&colors=purple&rev=7772 =>
http://www.wikipathways.org/pathways/WP4?purple=Entrez Gene:324,HMDB:HMDB00193&version=7772&diagram-only=true

http://dev.wikipathways.org/wpi/PathwayWidget.php?id=WP710&xref[]=324,Entrez%20Gene&xref[]=HMDB00193,HMDB&colors=purple&label=PC =>
http://dev.wikipathways.org/wpi/PathwayWidget.php?id=WP710&purple=PC,Entrez Gene:324,HMDB:HMDB00193
*/

# NOTE: we convert any query params that still use the old highlighter format
#       to the new format in order to maintain backwards compatibility.
$labelOrLabels = isset($_REQUEST['label']) ? $_REQUEST['label'] : null;
$xrefOrXrefs = isset($_REQUEST['xref']) ? $_REQUEST['xref'] : null;
$colorString = isset($_REQUEST['colors']) ? $_REQUEST['colors'] : null;

if ((!is_null($labelOrLabels) || !is_null($xrefOrXrefs)) && !is_null($colorString)) {
	if (!is_null($labelOrLabels)) {
		$labels = array();
		if (is_array($labelOrLabels)) {
			foreach ($labelOrLabels as $label) {
				array_push($labels, $label);
			}
		} else {
			array_push($labels, $labelOrLabels);
		}
	}


	if (!is_null($xrefOrXrefs)){
		$xrefs = array();
		if (is_array($xrefOrXrefs)){
			foreach ($xrefOrXrefs as $xref) {
				array_push($xrefs, $xref);
			}
		} else {
			array_push($xrefs, $xrefOrXrefs);
		}
	}

	$selectors = array();
	foreach ($labels as $label) {
		array_push($selectors, $label);
	}
	foreach ($xrefs as $xref) {
		$xrefParts = explode(",", $xref);
		$dbId = $xrefParts[0];
		$dbName = $xrefParts[1];
		array_push($selectors, $dbName . ":" . $dbId);
	}

	parse_str($_SERVER['QUERY_STRING'], $params);
	unset($params['colors']);
	unset($params['xref']);
	unset($params['label']);
	$colors = explode(",",$colorString);
	if (count($selectors) != count($colors)) {
		// if color list is not the same length as selector list, then just use first color
		$firstColor = $colors[0];
		$params[$firstColor] = join(",", $selectors);
	} else {
		for($i=0; $i <count($selectors); $i++){
			$params[$colors[$i]] = $selectors[$i];
		}
	}
	$paramString = http_build_query($params);
	echo '<script>' .
		'history.replaceState("", "", "?' . $paramString . '");' .
	'</script>';
}
?>
<title>WikiPathways Pathway Viewer</title>
</head>
<body>
<!--
	<div class="kaavio-container-container" style="width: 100%; height: 100%; margin: 0px; padding: 0px;">
		<div class="kaavio-container">
			<?php
/*
			$unified_cache_path = dirname(__FILE__) . "/../unified";
			$unified_svg_path = $unified_cache_path . '/' . $identifier . ".svg";
			#echo file_get_contents($unified_svg_path);
//*/
			?>
		</div>
	</div>
-->
	<?php
	// We only show the "View at WikiPathways" image link when we're not at WikiPathways.
	if (preg_match("/^.*\.wikipathways\.org$/i", $_SERVER['HTTP_HOST']) == false) {
		echo '<div style="position:absolute;height:0px;overflow:visible;bottom:0;left:15px;">' .
			'<div id="logolink">' .
				'<a id="wplink" target="top" href="'.$pathway->getFullUrl().'">View at ' .
				'<img style="border:none" src="' . $wgScriptPath . '/skins/common/images/wikipathways_name.png" /></a>' .
			'</div>' .
		'</div>';
	}
	?>
	<script src="/wpi/js/kaavio.js"></script>

	<?php
	$unified_json_path = $unified_cache_path . '/' . $identifier . ".json";
	$unified_json = file_get_contents($unified_json_path);
	?>
	<script>
		var pvjsInput = <?php echo $unified_json ?>;
		pvjsInput.onReady = function() {};
		ReactDOM.render(
			React.createElement(
				Kaavio.Kaavio,
				pvjsInput
			),
			document.querySelector('.kaavio-container-container')
		);
	</script>
</body>
</html>
