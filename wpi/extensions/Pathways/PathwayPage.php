<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$wgHooks['ParserBeforeStrip'][] = array('renderPathwayPage');
# TODO can we get rid of this? We used to use it for the Java applet, but
# we're not using that anymore.
function renderPathwayPage(&$parser, &$text, &$strip_state) {
	global $wgUser, $wgRequest, $wgOut;

	$title = $parser->getTitle();
	$oldId = $wgRequest->getVal( "oldid" );
	if( $title && $title->getNamespace() == NS_PATHWAY &&
		preg_match("/^\s*\<\?xml/", $text)) {
		$parser->disableCache();

		try {
			$pathway = Pathway::newFromTitle($title);
			if($oldId) {
				$pathway->setActiveRevision($oldId);
			}
			$pathway->updateCache(FILETYPE_IMG); //In case the image page is removed
			$page = new PathwayPage($pathway);
			$text = $page->render();
		} catch(Exception $e) { //Return error message on any exception
			$text = <<<ERROR
= Error rendering pathway page =
This revision of the pathway probably contains invalid GPML code. If this happens to the most recent revision, try reverting
the pathway using the pathway history displayed below or contact the site administrators (see [[WikiPathways:About]]) to resolve this problem.
=== Pathway history ===
<pathwayHistory></pathwayHistory>
=== Error details ===
<pre>
{$e}
</pre>
ERROR;

		}
	}
	return true;
}

class PathwayPage {
	private $pathway;
	private $data;
	static $msgLoaded = false;
	static $sectionNames = array(
		"Navbars",
		"PrivateWarning",
		"Title",
		"Diagram",
		"DiagramFooter",
		"AuthorInfo",
		"Description",
		"QualityTags",
		"OntologyTags",
		"Bibliography",
		"History",
		"Xrefs",
		"LinkToFullPathwayPage"
	);
	static $sectionNamesByView = array(
		"normal" => [
			"Navbars",
			"PrivateWarning",
			"Title",
			"Diagram",
			"DiagramFooter",
			"AuthorInfo",
			"Description",
			"QualityTags",
			"OntologyTags",
			"Bibliography",
			"History",
			"Xrefs"
		],
		"widget" => [
			"Diagram",
			"LinkToFullPathwayPage"
		]
	);

	function __construct($pathway) {
		global $wgMessageCache;

		$this->pathway = $pathway;
		$this->data = $pathway->getPathwayData();
		$view = isset($_GET["view"]) ? $_GET["view"] : "normal";
		$this->view = $view;

		if(!self::$msgLoaded) {
			$wgMessageCache->addMessages( array(
					'private_warning' => '{{SERVER}}{{SCRIPTPATH}}/skins/common/images/lock.png This pathway will not be visible to other users until $DATE. ' .
					'To make it publicly available before that time, <span class="plainlinks">[{{fullurl:{{FULLPAGENAMEE}}|action=manage_permissions}} change the permissions]</span>.'
				), 'en' );
			self::$msgLoaded = true;
		}

		/* TODO keep this for anything?
		// We only show the "View at WikiPathways" image link when we're not at WikiPathways.
		if (preg_match("/^.*\.wikipathways\.org$/i", $_SERVER['HTTP_HOST']) == true) {
		}
		//*/
	}


	static function formatPubMed($text) {
		$link = "http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?db=pubmed&cmd=Retrieve&dopt=AbstractPlus&list_uids=";
		if(preg_match_all("/PMID: ([0-9]+)/", $text, $ids)) {
			foreach($ids[1] as $id) {
				$text = str_replace($id, "[$link$id $id]", $text);
			}
		}
		return $text;
	}

	function render() {
		global $wgOut;

		$view = $this->view;
		$enabledSectionNames = self::$sectionNamesByView[$this->view];

		if (!in_array("Navbars", $enabledSectionNames)) {
			$wgOut->setArticleBodyOnly(true);
		}

		$text = '';
		$html = '';
		$sectionNames = self::$sectionNames;
		foreach($sectionNames as $sectionName) {
			if (in_array($sectionName, $enabledSectionNames) && method_exists($this, $sectionName)) {
				if (in_array($sectionName, array("Diagram", "PrivateWarning"))) {
					$html .= $this::$sectionName();
				} else {
					$text .= $this::$sectionName();
				}
			}
		}

		$height = $view == "normal" ? "600px" : "100%";

		$diagramContainer = new DOMDocument("1.0","UTF-8");
		$diagramContainerString = <<<HTML
<!DOCTYPE html>
<html>
  <head>
	<!-- This meta bit is only used because we are creating a static file, not usually needed. -->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<style type="text/css">
	.diagram-container {
		width: 100%;
		height: $height;
		margin: 0px;
		padding: 0px;
	}
	.kaavioContainer {
		width: 100%;
		height: inherit;
		min-height: inherit;
		margin: 0px;
		padding: 0px;
	}
	</style>
  </head>
  <body>
	$html
  </body>
</html>
HTML;

		if (!in_array("History", $enabledSectionNames)) {
			$hideScript = <<<SCRIPT
<script type="text/javascript">
	window.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('[name="History"], [name="History"] + h2, [name="History"] + h2 + table, , [name="History"] + h2 + table + form')
			.forEach(function(el) {
				el.style.visibility = 'hidden';
			});
	});
</script>
SCRIPT;
			$wgOut->addScript($$hideScript);
		}


		$diagramContainer->loadHTML($diagramContainerString);

		$wgOut->addHTML($diagramContainerString);
		return $text;
	}

	function AuthorInfo() {
		global $wgOut;
		// TODO this is a kludge. There should be a better way to position this before the diagram.
		$script = <<<SCRIPT
<script type="text/javascript">
window.addEventListener('DOMContentLoaded', function() {
	jQuery( "#authorInfoContainer" ).insertBefore( $( ".diagram-container" ) );
});
</script>
SCRIPT;
		$wgOut->addScript($script);
		return '{{Template:AuthorInfo}}';
	}

	function Title() {
		$title = $this->pathway->getName();
		return "<pageEditor id='pageTitle' type='title'>$title</pageEditor>";
	}

	function PrivateWarning() {
		global $wgScriptPath, $wgLang;

		$warn = '';
		if(!$this->pathway->isPublic()) {
			$url = SITE_URL;
			$msg = wfMsg('private_warning');

			$pp = $this->pathway->getPermissionManager()->getPermissions();
			$expdate = $pp->getExpires();
			$expdate = $wgLang->date($expdate, true);
			$msg = str_replace('$DATE', $expdate, $msg);
			$warn = "<div class='private_warn'>$msg</div>";
		}
		return $warn;
	}

	function QualityTags() {
		$tags = "\n== Quality Tags ==\n" .
			"<CurationTags></CurationTags>";
		return $tags;
	}

	function Diagram() {
		global $wgUser, $wgRequest, $wgOut;
		$pathway = $this->pathway;
		$jsonData = $pathway->getJson();
		if (!$jsonData) {
			$pngPath = $pathway->getFileURL(FILETYPE_PNG, false);
			return "<p>Note: only able to display static pathway diagram. Interactive diagram temporarily disabled for this pathway.</p><br>$pngPath";
		}

		$svg = $pathway->convertJsonToSvg($jsonData);

		return <<<HTML
<div class="diagram-container">
	<div class="kaavioContainer">
		$svg
	</div>
</div>
<script type="text/javascript" src="/wpi/js/pvjs/pvjs.js"></script>
<script type="text/javascript">
	if (window.hasOwnProperty("XrefPanel")) {
	      XrefPanel.show = function(elm, id, datasource, species, symbol) {
		jqelm = $(elm);
		if(XrefPanel.currentTriggerDialog) {
		  XrefPanel.currentTriggerDialog.dialog("close");
		  XrefPanel.currentTriggerDialog.dialog("destroy");
		}
		jqcontent = XrefPanel.create(id, datasource, species, symbol);
		var x = jqelm.offset().left - $(window).scrollLeft();
		var y = jqelm.offset().top - $(window).scrollTop();
		jqdialog = jqcontent.dialog({
		  position: [x,y]
		});
		XrefPanel.currentTriggerDialog = jqdialog;
	      }
	}

	var pvjsInput = $jsonData;
	pvjsInput.onReady = function() {};
	window.addEventListener('load', function() {
		pvjs.Pvjs(".kaavioContainer", pvjsInput);
	});
</script>
HTML;
	}

	function Description() {
		//Get WikiPathways description
		$content = $this->data->getWikiDescription();

		$description = $content;
		if(!$description) {
			$description = "<I>No description</I>";
		}
		$description = "== Description ==\n<div id='descr'>"
			 . $description . "</div>";

		$description .= "<pageEditor id='descr' type='description'>$content</pageEditor>\n";

		//Get additional comments
		$comments = '';
		foreach($this->data->getGpml()->Comment as $comment) {
			if(	$comment['Source'] == COMMENT_WP_DESCRIPTION ||
				$comment['Source'] == COMMENT_WP_CATEGORY)
			{
				continue; //Skip description and category comments
			}
			$text = (string)$comment;
			$text = html_entity_decode($text);
			$text = nl2br($text);
			$text = PathwayPage::formatPubMed($text);
			if(!$text) continue;
			$comments .= "; " . $comment['Source'] . " : " . $text . "\n";
		}
		if($comments) {
			$description .= "\n=== Comments ===\n<div id='comments'>\n$comments<div>";
		}
		return $description;
	}


	function OntologyTags() {
		global $wpiEnableOtag;
		if($wpiEnableOtag) {
			$otags = "\n== Ontology Terms ==\n" .
				"<OntologyTags></OntologyTags>";
			return $otags;
		}
	}


	function Bibliography() {
		global $wgUser;

		$out = "<pathwayBibliography></pathwayBibliography>";
		//No edit button for now, show help on how to add bibliography instead
		//$button = $this->editButton('javascript:;', 'Edit bibliography', 'bibEdit');
		#&$parser, $idClick = 'direct', $idReplace = 'pwThumb', $new = '', $pwTitle = '', $type = 'editor'
		$help = '';
		if($wgUser->isLoggedIn()) {
			$help = "{{Template:Help:LiteratureReferences}}";
		}
		return "\n== Bibliography ==\n$out\n$help";
			//"<div id='bibliography'><div style='float:right'>$button</div>\n" .
			//"$out</div>\n{{#editApplet:bibEdit|bibliography|0||bibliography|0|250px}}";
	}

	static function getDownloadURL($pathway, $type) {
		if($pathway->getActiveRevision()) {
			$oldid = "&oldid={$pathway->getActiveRevision()}";
		}
		return WPI_SCRIPT_URL . "?action=downloadFile&type=$type&pwTitle={$pathway->getTitleObject()->getFullText()}{$oldid}";
	}

	function DiagramFooter() {
		global $wgOut, $wgUser;
		$pathway = $this->pathway;

		//Create edit button
		$pathwayURL = $pathway->getTitleObject()->getPrefixedURL();
		//AP20070918
		$helpUrl = Title::newFromText("Help:Known_problems")->getFullUrl();
		$helpLink = '<div style="float:left;"><a href="' . $helpUrl . '"> not working?</a></div>';
		if ($wgUser->isLoggedIn() && $pathway->getTitleObject()->userCan('edit')) {
			$identifier = $pathway->getIdentifier();
			$version = $pathway->getLatestRevision(); 
			// see http://www.ericmmartin.com/projects/simplemodal/
			$wgOut->addScript('<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/simplemodal/1.4.4/jquery.simplemodal.min.js"></script>');
			//*
			// this should just be a button, but the button class only works for "a" elements with text inside.
			$openInPathVisioScript = <<<SCRIPT
<script type="text/javascript">
window.addEventListener('DOMContentLoaded', function() {
	document.querySelector('#edit-button').innerHTML = '<a id="download-from-page" href="#" onclick="return false;" class="button"><span>Launch Editor</span></a>{$helpLink}';
	$("#download-from-page").click(function() {
		$.modal('<div id="jnlp-instructions" style="width: 610px; height:616px; cursor:pointer;" onClick="$.modal.close()"><img id="jnlp-instructions-diagram" src="/skins/wikipathways/jnlp-instructions.png" alt="The JNLP will download to your default folder. Right-click the JNLP file and select Open."> </div>',
		{
			overlayClose: true,
			overlayCss: {backgroundColor: "gray"},
			opacity: 50
		});
		// We need the kludge below, because the image doesn't display in FF otherwise.
		window.setTimeout(function() {
			$('#jnlp-instructions-diagram').attr('src', '/skins/wikipathways/jnlp-instructions.png');
		}, 10);
		// server must set Content-Disposition: attachment
		// TODO why do the ampersand symbols below get parsed as HTML entities? Disabling this line and using the minimal line below for now, but we shouldn't have to do this..
		//window.location = "{SITE_URL}/wpi/extensions/PathwayViewer/pathway-jnlp.php?identifier={$identifier}&version={$version}&filename=WikiPathwaysEditor";
		window.location = "{SITE_URL}/wpi/extensions/PathwayViewer/pathway-jnlp.php?identifier={$identifier}";
	});
});
</script>
SCRIPT;
			$wgOut->addScript($openInPathVisioScript);

		} else {
			if(!$wgUser->isLoggedIn()) {
				$hrefbtn = SITE_URL . "/index.php?title=Special:Userlogin&returnto=$pathwayURL";
				$label = "Log in to edit pathway";
			} else if(wfReadOnly()) {
				$hrefbtn = "";
				$label = "Database locked";
			} else if(!$pathway->getTitleObject()->userCan('edit')) {
				$hrefbtn = "";
				$label = "Editing is disabled";
			}
			$script = <<<SCRIPT
<script type="text/javascript">
window.addEventListener('DOMContentLoaded', function() {
	document.querySelector('#edit-button').innerHTML = '<a href="{$hrefbtn}" title="{$label}" id="edit" class="button"><span>{$label}</span></a>{$helpLink}';
});
</script>
SCRIPT;
			$wgOut->addScript($script);
		}

		//Create dropdown action menu
		$download = array(
						'PathVisio (.gpml)' => self::getDownloadURL($pathway, 'gpml'),
						'Scalable Vector Graphics (.svg)' => self::getDownloadURL($pathway, 'svg'),
						'Gene list (.txt)' => self::getDownloadURL($pathway, 'txt'),
						'Biopax level 3 (.owl)' => self::getDownloadURL($pathway, 'owl'),
						'Eu.Gene (.pwf)' => self::getDownloadURL($pathway, 'pwf'),
						'Png image (.png)' => self::getDownloadURL($pathway, 'png'),
						'Acrobat (.pdf)' => self::getDownloadURL($pathway, 'pdf'),
		);
		$downloadlist = '';
		foreach(array_keys($download) as $key) {
			$downloadlist .= '<li><a href="' . $download[$key] . '">' . $key . '</a></li>';
		}
		$dropdown = <<<DROPDOWN
<div style="float:right;">
	<ul id="nav" name="nav">
		<li>
			<a href="#nogo2" class="button buttondown"><span>Download</span></a>
			<ul>
				$downloadlist
			</ul>
		</li>
	</ul>
</div>
DROPDOWN;
			$dropdown = str_replace("\n", "", $dropdown);
			$script = <<<SCRIPT
<script type="text/javascript">
window.addEventListener('DOMContentLoaded', function() {
	document.querySelector('#download-button').innerHTML = '{$dropdown}';
});
</script>
SCRIPT;
		$wgOut->addScript($script);

		$html = <<<HTML
<div id="diagram-footer">
	<div id="edit-button" style="float:left;"></div>
	<div id="download-button"></div>
</div>
<br>
HTML;

		return $html;
	}

	function History() {
		return "\n{{Template:PathwayPage:History}}";
	}

	function Xrefs() {
		return "\n{{Template:PathwayPage:Xrefs}}";
	}

	function LinkToFullPathwayPage() {
		global $wgOut, $wgScriptPath;
		$pathway = $this->pathway;
		$wgOut->addHTML('<div style="position:absolute;overflow:visible;bottom:0;left:15px;">' .
			'<div id="logolink">' .
			'<a id="wplink" target="top" href="'.$pathway->getFullUrl().'">View at ' .
			'<img style="border:none" src="' . $wgScriptPath . '/skins/common/images/wikipathways_name.png" /></a>' .
			'</div>' .
			'</div>');
	}
}
