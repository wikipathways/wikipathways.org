<?php
/**
Entry point for a pathway viewer widget that can be included in other pages.

This page will display the interactive pathway viewer for a given pathway. It takes the following parameters:
- identifier: the pathway identifier (e.g. WP4)
- version: the version (revision) number of a specific version of the pathway (optional, leave out to display the newest version)

You can include a pathway viewer in another website using an iframe:

<iframe src ="http://www.wikipathways.org/pathways/WP4?diagram-only=true" width="500" height="500" style="overflow:hidden;"></iframe>
*/

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

require_once('wpi.php');
parse_str($_SERVER['QUERY_STRING'], $params);

$identifier = isset($params['identifier']) ? $params['identifier'] : $params['id'];
$version = isset($params['version']) ? $params['version'] : isset($params['rev']) ? $params['rev'] : 0;

# NOTE: we convert any query params that still use the old highlighter format
#       to the new format in order to maintain backwards compatibility.
$labelOrLabels = isset($params['label']) ? $params['label'] : null;
$xrefOrXrefs = isset($params['xref']) ? $params['xref'] : null;
$colorString = isset($params['colors']) ? $params['colors'] : null;

unset($params['id']);
unset($params['rev']);
unset($params['colors']);
unset($params['xref']);
unset($params['label']);

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

}
$paramString = (count($params) == 0 ? "" : ("?" . http_build_query($params))) ;
header("Location: /index.php/Pathway:" . $identifier . $paramString);
exit();
?>
