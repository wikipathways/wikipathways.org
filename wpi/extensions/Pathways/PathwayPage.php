<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$wgHooks['ParserBeforeStrip'][] = array('renderPathwayPage');
# TODO can we get rid of this? We used to use it for the Java applet, but
# we're not using that anymore.
$wgHooks['BeforePageDisplay'][] = array('addPreloaderScript');

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
			$text = $page->getContent();
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

function addPreloaderScript(&$out) {
	global $wgTitle, $wgUser, $wgScriptPath;
	/*
	if($wgTitle->getNamespace() == NS_PATHWAY && $wgUser->isLoggedIn() &&
		strstr( $out->getHTML(), "pwImage" ) !== false ) {
		$base = $wgScriptPath . "/wpi/applet/";
		$class = "org.wikipathways.applet.Preloader.class";

		$out->addHTML("<applet code='$class' codebase='$base'
			width='1' height='1' name='preloader'></applet>");
	}
	//*/
	return true;
}

class PathwayPage {
	private $pathway;
	private $data;
	static $msgLoaded = false;

	function __construct($pathway) {
		$this->pathway = $pathway;
		$this->data = $pathway->getPathwayData();

		global $wgMessageCache;
		if(!self::$msgLoaded) {
			$wgMessageCache->addMessages( array(
					'private_warning' => '{{SERVER}}{{SCRIPTPATH}}/skins/common/images/lock.png This pathway will not be visible to other users until $DATE. ' .
					'To make it publicly available before that time, <span class="plainlinks">[{{fullurl:{{FULLPAGENAMEE}}|action=manage_permissions}} change the permissions]</span>.'
				), 'en' );
			self::$msgLoaded = true;
		}
	}

	function getContent() {
		$pathway = $this->pathway;
		/* TODO keep this for anything?
		// We only show the "View at WikiPathways" image link when we're not at WikiPathways.
		if (preg_match("/^.*\.wikipathways\.org$/i", $_SERVER['HTTP_HOST']) == true) {
		}
		//*/
		$rendererBySectionMap = array(
			"navbars" => function($display) {
				// personal, title, left navbar, actions
				if ($display) {
					// do nothing
				} else {
					$this->hideNavbars();
				}
			},
			"privacy-status" => function($display) {
				if ($display) {
					return $this->privateWarning();
				} else {
					// do nothing
				}
			},
			"title" => function($display) {
				if ($display) {
					return $this->titleEditor();
				} else {
					// do nothing
				}
			},
			"authors" => function($display) {
				if ($display) {
					return '{{Template:AuthorInfo}}';
				} else {
					// do nothing
				}
			},
			"edit" => function($display) {
				if ($display) {
					$this->addEditCaption();
				} else {
					// do nothing
				}
			},
			"download" => function($display) {
				if ($display) {
					$this->addDownloadCaption();
				} else {
					// do nothing
				}
			},
			"diagram" => function($display) {
				if ($display) {
					return $this->diagram();
				} else {
					// do nothing
				}
			},
			"description" => function($display) {
				if ($display) {
					return $this->descriptionText();
				} else {
					// do nothing
				}
			},
			"quality-tags" => function($display) {
				if ($display) {
					return $this->curationTags();
				} else {
					// do nothing
				}
			},
			"ontology-tags" => function($display) {
				if ($display) {
					return $this->ontologyTags();

				} else {
					// do nothing
				}
			},
			"bibliography" => function($display) {
				if ($display) {
					return $this->bibliographyText();
				} else {
					// do nothing
				}
			},
			"history" => function($display) {
				if ($display) {
					// TODO this returns both history and xrefs
					return '{{Template:PathwayPage:Bottom}}';
				} else {
					$this->hideHistory();

				}
			},
			"xrefs" => function($display) {
				if ($display) {
					// TODO this returns both history and xrefs. see "history" property above.
					//return '{{Template:PathwayPage:Bottom}}';
				} else {
					$this->hideXrefs();

				}
			},
			"view-at-wikipathways" => function($display) {
				if ($display) {
					$this->showViewAtWikiPathways();
				} else {
					// do nothing
				}
			}
		);
		$sectionsByView = array(
			"normal" => [
				"navbars",
				"privacy-status",
				"title",
				"authors",
				//*
				"diagram",
				"edit",
				"download",
				//*/
				"description",
				"quality-tags",
				"ontology-tags",
				"bibliography",
				"history",
				"xrefs"
			],
			"widget" => [
				"diagram",
				"view-at-wikipathways"
			]
		);
		$view = isset($_GET["view"]) ? $_GET["view"] : "normal";
		$enabledSections = $sectionsByView[$view];
		$text = '';
		foreach($rendererBySectionMap as $section => $renderer) {
			$rendered = $renderer(in_array($section, $enabledSections));
			if (isset($rendered)) {
				$text .= $rendered;
			}
		}
		return $text;
	}

	function titleEditor() {
		$title = $this->pathway->getName();
		return "<pageEditor id='pageTitle' type='title'>$title</pageEditor>";
	}

	function privateWarning() {
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

	function curationTags() {
		$tags = "\n== Quality Tags ==\n" .
			"<CurationTags></CurationTags>";
		return $tags;
	}

	function diagram() {
		global $wgUser, $wgRequest, $wgOut;
		$pathway = $this->pathway;
		$jsonData = $pathway->getJson();
		if (!$jsonData) {
			$pngPath = $pathway->getFileURL(FILETYPE_PNG, false);
			return "<p>Note: only able to display static pathway diagram. Interactive diagram temporarily disabled for this pathway.</p><br>$pngPath";
		}
		$wgOut->addHTML('<script type="text/javascript" src="/wpi/js/pvjs/pvjs.js"></script>');
		$diagramInitScript = <<<SCRIPT
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
SCRIPT;
		$wgOut->addHTML($diagramInitScript);
		$diagramContainer = $this->getDiagramContainer();
		$finder = new DomXPath($diagramContainer);
		return $finder->query("//div[@class='diagram-container']")->item(0)->C14N() . '<br>';

		//return $finder->query("//div[@class='diagram-container']")->item(0)->C14N() . '<br>';
		//echo $finder->query('//body')->item(0)->C14N();
		//return $finder->query('/body')->saveHTML();
		//return $diagramContainer->saveHTML();
	}

	function descriptionText() {
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


	function ontologyTags() {
		global $wpiEnableOtag;
		if($wpiEnableOtag) {
			$otags = "\n== Ontology Terms ==\n" .
				"<OntologyTags></OntologyTags>";
			return $otags;
		}
	}


	function bibliographyText() {
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

	function getDiagramContainer() {
		global $wgOut;

		$view = isset($_GET["view"]) ? $_GET["view"] : "normal";
		$height = $view == "normal" ? "600px" : "100%";

		$style = <<<STYLE
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
STYLE;
		$wgOut->addHTML($style);

		if (!isset($this->diagramContainer)) {
			$pathway = $this->pathway;
			$svgUnified = $pathway->getSvgUnified();
			$diagramContainer = new DOMDocument("1.0","UTF-8");
			$diagramContainerString = <<<TEXT
<html><body>
	<div class="diagram-container">
		<div class="kaavioContainer"></div>
			<!-- TODO DOMDocument::loadHTML() appears unable to display SVG inline
			$svgUnified
			-->
		<div id="diagram-footer"></div>
	</div>
</body></html> 
TEXT;

			$diagramContainer->loadHTML($diagramContainerString);
			$this->diagramContainer = $diagramContainer;
			return $diagramContainer;
		} else {
			return $this->diagramContainer;
		}
	}

	function appendToDiagramFooter($htmlString) {
		$diagramContainer = $this->getDiagramContainer();
		$diagramFooter = $diagramContainer->getElementById('diagram-footer');
		$docFrag = $diagramContainer->createDocumentFragment();
		$docFrag->appendXML($htmlString);
		$diagramFooter->appendChild($docFrag);
	}

	static function getDownloadURL($pathway, $type) {
		if($pathway->getActiveRevision()) {
			$oldid = "&oldid={$pathway->getActiveRevision()}";
		}
		return WPI_SCRIPT_URL . "?action=downloadFile&type=$type&pwTitle={$pathway->getTitleObject()->getFullText()}{$oldid}";
	}

	function addDownloadCaption() {
		$pathway = $this->pathway;
		//Create dropdown action menu
		global $wgOut;
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
		$this->appendToDiagramFooter('<div id="download-button"></div>');

		//$this->appendToDiagramFooter($dropdown);
	}

	function addEditCaption() {
		$pathway = $this->pathway;
		global $wgOut, $wgUser;
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

		$editButton = '<div id="edit-button" style="float:left;"></div>';
		$this->appendToDiagramFooter($editButton);
	}

	function hideNavbars() {
		global $wgOut;
		//$wgOut->clearHTML();
		$wgOut->setArticleBodyOnly(true);
		//$wgOut->addHTML($this->diagram());

		/*
		$script = <<<SCRIPT
<script type="text/javascript">
	window.addEventListener('DOMContentLoaded', function() {
		var body = document.querySelector('body');
		body.removeAttribute('class');
		var globalWrapper = document.querySelector('#globalWrapper');
		// if we wanted to keep the title and organism:
		//var content = document.querySelector('#content');
		var content = document.querySelector('.diagram-container');
		content.setAttribute('class', '');
		content.style.top = 0;
		content.style.left = 0;
		content.style.border = 0;
		content.style.margin = 0;
		content.style.padding = 0;
		globalWrapper.replaceWith(content);
	});
</script>
SCRIPT;
		$wgOut->addScript($script);
		//*/
	}

	function hideHistory() {
		global $wgOut;

		$script = <<<SCRIPT
<script type="text/javascript">
	window.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('[name="External_references"], [name="External_references"] + h2, [name="Datanodes"], [name="Datanodes"] + h3, [name="Datanodes"] + h3 + table, [name="Datanodes"] + h3 + table + table, [name="Annotated_Interactions"] + h3, [name="Annotated_Interactions"] + h3 + p')
			.forEach(function(el) {
				el.style.visibility = 'hidden';
			});
	});
</script>
SCRIPT;
		$wgOut->addScript($script);
	}

	function hideXrefs() {
		global $wgOut;
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

	function showViewAtWikiPathways() {
		global $wgOut, $wgScriptPath;
		$pathway = $this->pathway;
		$wgOut->addHTML('<div style="position:absolute;overflow:visible;bottom:0;left:15px;">' .
			'<div id="logolink">' .
			'<a id="wplink" target="top" href="'.$pathway->getFullUrl().'">View at ' .
			'<img style="border:none" src="' . $wgScriptPath . '/skins/common/images/wikipathways_name.png" /></a>' .
			'</div>' .
			'</div>');
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
}
