<?php
require_once( "$IP/wpi/extensions/GPMLConverter/src/GPMLConverter.php" );
require_once( "$IP/wpi/extensions/XrefPanel.php" );

$wgHooks['ParserBeforeStrip'][] = array('renderPathwayPage');

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
			XrefPanel::xref();
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
	static $sectionNamesByReturnType = array(
		"html" => [
			"Diagram",
			"LinkToFullPathwayPage",
			"PrivateWarning",
			"DiagramFooter",
		],
		"text" => [
			"AuthorInfo",
			"Title",
			"Description",
			"Bibliography",
			"QualityTags",
			"OntologyTags",
			"Xrefs",
			// is History text or other?
			"History",
		],
		"none" => [
			"Navbars",
		]
	);

	static function formatPubMed($text) {
		$link = "http://www.ncbi.nlm.nih.gov/entrez/query.fcgi?db=pubmed&cmd=Retrieve&dopt=AbstractPlus&list_uids=";
		if(preg_match_all("/PMID: ([0-9]+)/", $text, $ids)) {
			foreach($ids[1] as $id) {
				$text = str_replace($id, "[$link$id $id]", $text);
			}
		}
		return $text;
	}

	static function getDownloadURL($pathway, $type) {
		if($pathway->getActiveRevision()) {
			$oldid = "&oldid={$pathway->getActiveRevision()}";
		}
		return WPI_SCRIPT_URL . "?action=downloadFile&type=$type&pwTitle={$pathway->getTitleObject()->getFullText()}{$oldid}";
	}

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
	}

	function render() {
		global $wgServer, $wgScriptPath, $wgOut, $wpiJavascriptSources, $wpiJavascriptSnippets, $wpiEnableOtag;

		$format = isset($_GET["format"]) ? $_GET["format"] : "html";
		if ($format !== "html") {
			$wgOut->setArticleBodyOnly(true);
			header("Access-Control-Allow-Origin: *");

			$pathway = $this->pathway;
			if ($format == "json") {
				$jsonData = $pathway->getPvjson();
			} else if ($format == "svg") {
				$svg = $pathway->getSvg();
			}
		}

		$view = $this->view;
		$enabledSectionNames = self::$sectionNamesByView[$this->view];

		if ($wpiEnableOtag !== in_array('OntologyTags', $enabledSectionNames)) {
			wfDebug('$wpiEnableOtag is '.$wpiEnableOtag.', but current view '.$this->view.' calls for the opposite.');
		}

		$text = '';
		$html = '';
		$sectionNames = self::$sectionNames;
		$htmlSections = self::$sectionNamesByReturnType["html"];
		$textSections = self::$sectionNamesByReturnType["text"];
		foreach($sectionNames as $sectionName) {
			if (method_exists($this, $sectionName)) {
				$enabled = in_array($sectionName, $enabledSectionNames);
				$section = $this::$sectionName($enabled);
				if ($enabled) {
					if (in_array($sectionName, $htmlSections)) {
						$html .= $section;
					} else if (in_array($sectionName, $textSections)) {
						$text .= $section;
					}
				}
			}
		}

		/*
		# NOTE: excluding optional tags, as recommended by Google Style Guide:
		# https://google.github.io/styleguide/htmlcssguide.html#Optional_Tags
		$diagramContainerString = <<<HTML
<!DOCTYPE html>
<!--
<meta charset="UTF-8">
-->
$html
HTML;
		//*/

		$wgOut->addHTML($html);
		return $text;
	}

	function AuthorInfo($show) {
		global $wgOut;

		if (!$show) {
			return '';
		}

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

	function Title($show) {
		if (!$show) {
			return '';
		}

		$title = $this->pathway->getName();
		return "<pageEditor id='pageTitle' type='title'>$title</pageEditor>";
	}

	function PrivateWarning($show) {
		global $wgScriptPath, $wgLang;

		if (!$show) {
			return '';
		}

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

	function QualityTags($show) {
		if (!$show) {
			return '';
		}

		$tags = "\n== Quality Tags ==\n" .
			"<CurationTags></CurationTags>";
		return $tags;
	}

	function Diagram($show) {
		global $wgUser, $wgRequest, $wgOut;

		if (!$show) {
			return '';
		}

		$height = $this->view == "normal" ? "600px" : "100%";

		$pathway = $this->pathway;
		$jsonData = $pathway->getPvjson();
		if (!$jsonData) {
			$pngPath = $pathway->getFileURL(FILETYPE_PNG, false);

			return <<<HTML
<div class="diagram-container">
	<div class="Container">
		<img src="$pngPath" style="height: inherit;">
	</div>
</div>
<div>
	<p>Note: Could not render interactive diagram. Displaying static pathway diagram instead.</p>
</div>
HTML;
		}

		$svg = $pathway->getSvg();

		return <<<HTML
<style type="text/css">
.diagram-container {
  background: #fefefe;
  font-family: "Roboto";
  position: relative;
  width: 100%;
  height: $height;
  /* To avoid covering up the resize handle (grab area) */
  border-bottom-right-radius: 1em 1em;
  overflow: hidden;
}

.Container {
  width: inherit;
  height: inherit;
  /* To avoid covering up the resize handle (grab area) */
  border-bottom-right-radius: inherit;
  overflow: inherit;
}

/* this is the svg */
.Diagram {
  width: inherit;
  height: inherit;
  overflow: inherit;
  color-interpolation: auto;
  image-rendering: auto;
  shape-rendering: auto;
  vector-effect: non-scaling-stroke;
}
</style>
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

	window.addEventListener('load', function() {
		var theme;
		if (!!window.URLSearchParams) {
			var urlParams = new URLSearchParams(window.location.search);
			if (!!urlParams && urlParams.get && urlParams.get('theme')) {
				theme = urlParams.get('theme').replace(/[^a-zA-Z0-9]/, '');
			}
		}
		var jsonData = $jsonData;
		new Pvjs(".Container", {theme: theme || 'plain', pathway: jsonData.pathway, entitiesById: jsonData.entitiesById, onReady: function() {}});
	});
</script>
<div class="diagram-container">
	<div class="Container">
		$svg
	</div>
</div>
<script type="text/javascript" src="/wpi/extensions/GPMLConverter/pvjs.vanilla.js"></script>
HTML;
	}

	function Description($show) {
		if (!$show) {
			return '';
		}

		//Get WikiPathways description
		$content = $this->data->getWikiDescription();

		$description = $content;
		if(!$description) {
			$description = "<I>No description</I>";
		}
		$description = "\n== Description ==\n<div id='descr'>"
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


	function OntologyTags($show) {
		if (!$show) {
			return '';
		}

		$otags = "\n== Ontology Terms ==\n" .
			"<OntologyTags></OntologyTags>";
		return $otags;
	}


	function Bibliography($show) {
		global $wgUser;

		if (!$show) {
			return '';
		}

		$out = "<pathwayBibliography></pathwayBibliography>";
		//No edit button for now, show help on how to add bibliography instead
		//$button = $this->editButton('javascript:;', 'Edit bibliography', 'bibEdit');
		#&$parser, $idClick = 'direct', $idReplace = 'pwThumb', $new = '', $pwTitle = '', $type = 'editor'
		$help = '';
		if($wgUser->isLoggedIn()) {
			$help = "{{Template:Help:LiteratureReferences}}";
		}
		return "\n== Bibliography ==\n$out\n$help";
	}

	function DiagramFooter($show) {
		global $wgOut, $wgUser;

		if (!$show) {
			return '';
		}

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
		//window.location = "{$SITE_URL}/wpi/extensions/PathwayViewer/pathway-jnlp.php?identifier={$identifier}&version={$version}&filename=WikiPathwaysEditor";
		window.location = "{$SITE_URL}/wpi/extensions/PathwayViewer/pathway-jnlp.php?identifier={$identifier}";
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

	function History($show) {
		global $wgOut;

		if (!$show) {
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
			$wgOut->addScript($hideScript);
			return '';
		}

		return "\n{{Template:PathwayPage:History}}";
	}

	function Xrefs($show) {
		if (!$show) {
			return '';
		}
		return "\n{{Template:PathwayPage:Xrefs}}";
	}

	function LinkToFullPathwayPage($show) {
		global $wgOut, $wgScriptPath;

		if (!$show) {
			return '';
		}

		$pathway = $this->pathway;
		$fullUrl = $pathway->getFullUrl();
		$html = <<<HTML
<div id="link-to-full-pathway-page" style="position:fixed; bottom:10; left:10px; z-index: 9; overflow:visible;">
	<p id="logolink">
		<a id="wplink" target="top" href="{$fullUrl}">
			View at <img style="border:none" src="{$wgScriptPath}/skins/common/images/wikipathways_name.png" />
		</a>
	</p>
</div>
HTML;
		return $html;
	}

	function Navbars($show) {
		global $wgOut, $wgScriptPath, $wgServer, $wgScriptPath, $wpiJavascriptSources, $wpiJavascriptSnippets;

		if (!$show) {
			$wgOut->setArticleBodyOnly(true);
			// the following is needed; the XrefPanel::xref() call above is not sufficient alone.
			// TODO can we move this to XrefPanel::xref()?
			XrefPanel::addXrefPanelScripts();
			$wgOut->addHTML('<script type="text/javascript">var wgServer="'.$wgServer.'"; var wgScriptPath="'.$wgScriptPath.'";</script>');
			$wgOut->addHTML('<script type="text/javascript" src="'.$wgServer.'/skins/wikipathways/jquery-1.8.3.min.js"></script>');
			$wgOut->addHTML('<link rel="stylesheet" href="/skins/wikipathways/main.css?164" type="text/css">');
			$wgOut->addHTML('<link rel="stylesheet" href="/wpi/js/jquery-ui/jquery-ui-1.8.10.custom.css?164" type="text/css">');
			foreach($wpiJavascriptSources as $wpiJavascriptSource) {
				$wgOut->addHTML('<script type="text/javascript" src="'.$wpiJavascriptSource.'"></script>');
			}
			foreach($wpiJavascriptSnippets as $wpiJavascriptSnippet) {
				$wgOut->addHTML('<script type="text/javascript">'.$wpiJavascriptSnippet.'</script>');
			}
		}
	}

}
