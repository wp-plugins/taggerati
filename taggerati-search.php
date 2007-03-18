<?php

/* This is a version of WordPress From/Where for Taggerati */

require_once('taggerati.php');

function taggerati_search_log_to_db($id = null) {

	// if empty, return
	if (empty ($id) || get_option('taggerati_autotagsearch') != 1)
		return;

	$search = true;
	if ((isset ($_SERVER['HTTP_REFERER'])) && (strlen(trim($_SERVER['HTTP_REFERER'])) > 0)) {
		$keywords = array ();
		$url = urldecode($_SERVER['HTTP_REFERER']);

		/* All the search engines that are nice enough to use q= */
		if (
				eregi("www\.google", $url) || 
				eregi("blogsearch\.google", $url) || 
				eregi("blogs\.icerocket\.com", $url) ||
				eregi("www\.alltheweb", $url) ||
				eregi("search\.msn", $url)
			)
			
			preg_match("'(\?|&|&amp;)q=(.*?)(&|&amp;|$)'si", " $url ", $keywords);

		// Technorati
		// http://technorati.com/search/elliott+back
		else if(eregi("technorati\.com/search/", $url))
			preg_match("'/search/(.*?)'si", " $url ", $keywords);
			
		// Yahoo 
		// http://search.yahoo.com/search?p=%22Antioch+University+Los+Angeles%22+elliott
		else if ((eregi("yahoo\.com", $url)) or (eregi("search\.yahoo", $url)))
			preg_match("'(\?|&|&amp;)p=(.*?)(&|&amp;|$)'si", " $url ", $keywords);

		// Looksmart 
		else if (eregi("looksmart\.com", $url))
			preg_match("'(\?|&|&amp;)qt=(.*?)(&|&amp;|$)'si", " $url ", $keywords);

		// Netscape
		// http://search.netscape.com/ns/boomframe.jsp?query=american+inter+university
		else if (eregi("search.netscape.com", $url))
			preg_match("'(\?|&|&amp;)query=(.*?)(&|&amp;|$)'si", " $url ", $keywords);

		// None
		else
			$search = false;
	}

	// Get keywords
	$kw = urldecode(trim($keywords[2]));

	// Actually have keywords, and from a search engine
	if (strlen($kw) < 1 || !$search)
		return;

	// original tags
	$alltags = tgr_get_namedtags_for_post($id);
		
	// add our new tag
	tgr_insert_tag($kw);
	tgr_insert_tag_for_post($kw, $id);
			
	// associate with old tags
	tgr_insert_related_tag_entries($kw, $alltags);
			
	// reverse associate
	foreach($alltags as $oldtag){
		tgr_insert_related_tag_entries($oldtag, array($kw));
	}
			
	// cleanup
	tgr_tag_cleanup();
}
?>