<?php

require_once('taggerati.php');

if(isset($_POST["action"])){
	switch($_POST["action"]){
		case "addtag":
			// clean the input
			$tag = addslashes(trim($_POST['tag']));
			$id = addslashes($_POST['ID']);
			
			// sanity checks
			if(get_option('taggerati_usertag') != 1)
				die();
			
			if(empty($tag) || strlen($tag) < 1)
				return;
			
			if(empty($id) || $id < 0)
				return;
		
			// original tags
			$alltags = tgr_get_namedtags_for_post($id);
		
			// add our new tag
			tgr_insert_tag($tag);
			tgr_insert_tag_for_post($tag, $id);
			
			// associate with old tags
			tgr_insert_related_tag_entries($tag, $alltags);
			
			// reverse associate
			foreach($alltags as $oldtag){
				tgr_insert_related_tag_entries($oldtag, array($tag));
			}
			
			// cleanup
			tgr_tag_cleanup();
			break;
	}
}

if(isset($_GET["type"]) && isset ($_GET["tag"]) && isset($_GET["showdesc"])) {
	switch($_GET["type"])  {
		case "technorati":
			tgr_do_rss('http://feeds.technorati.com/feed/posts/tag/' . str_replace(" ", "+", $_GET["tag"]), 'technorati.com', $_GET['tag'], $_GET["showdesc"]);
			break;
			
		case "yahoo":
			tgr_do_rss('http://api.search.yahoo.com/WebSearchService/rss/webSearch.xml?appid=yahoosearchwebrss&adult_ok=1&query='.str_replace(" ", "+", $_GET["tag"]), 'yahoo.com', $_GET['tag'], $_GET["showdesc"]);
			break;
			
		case "msn":
			tgr_do_rss('http://search.msn.com/results.aspx?format=rss&q='.str_replace(" ", "+", $_GET["tag"]), 'msn.com', $_GET['tag'], $_GET["showdesc"]);
			break;
			
		case "furl":
			tgr_do_rss('http://rss.furl.net/members/rss.xml?topic='.str_replace(" ", "+", $_GET["tag"]), 'furl.net', $_GET['tag'], $_GET["showdesc"]);
			break;

		case "tagzania":
			tgr_do_rss('http://www.tagzania.com/rss/tag/'.str_replace(" ", "+", $_GET["tag"]), 'tagzania.com', $_GET['tag'], $_GET["showdesc"]);
			break;

		case "feedmarker":
			tgr_do_rss('http://www.feedmarker.com/feed/tags/'.str_replace(" ", "+", $_GET["tag"]), 'feedmarker.com', $_GET['tag'], $_GET["showdesc"]);
			break;

		case "feedster":
			tgr_do_rss('http://www.feedster.com/search/type/rss/'.str_replace(" ", "+", $_GET["tag"]), 'feedster.com', $_GET['tag'], $_GET["showdesc"]);
			break;

		case "icerocket":
			tgr_do_rss('http://www.icerocket.com/search?tab=blog&q='.str_replace(" ", "+", $_GET["tag"]).'&rss=1', 'icerocket.com', $_GET['tag'], $_GET["showdesc"]);
			break;

		case "google":
			tgr_do_rss('http://blogsearch.google.com/blogsearch_feeds?hl=en&q='.str_replace(" ", "+", $_GET["tag"]).'&btnG=Search+Blogs&num=10&output=rss', 'blogsearch.google.com', $_GET['tag'], $_GET["showdesc"]);
			break;

		case "rawsugar":
tgr_do_rss('http://www.rawsugar.com/rss/search/'.str_replace(" ", "+", $_GET["tag"]), 'www.rawsugar.com', $_GET['tag'], $_GET["showdesc"]);
			break;

		case "shadows":
			tgr_do_rss('http://www.shadows.com/rss/tag/'.str_replace(" ", "+", $_GET["tag"]), 'shadows.com', $_GET['tag'], $_GET["showdesc"]);
			break;

		case "delicious":
			tgr_do_rss('http://del.icio.us/rss/tag/'.str_replace(" ", "+", $_GET["tag"]), 'del.icio.us', $_GET['tag'], $_GET["showdesc"]);
			break;

		case "digg":	
	tgr_do_rss('http://digg.com/rss_search?area=all&type=both&age=7&section=news&search=' . str_replace(" ", "+", $_GET["tag"]), 'digg', $_GET['tag'], $show_desc);
  		break;

		case "43things":
			tgr_do_rss('http://www.43things.com/rss/goals/tag?name='.str_replace(" ", "+", $_GET["tag"]), '43things.com', $_GET['tag'], $_GET["showdesc"]);
			break;
	}
}
?>
