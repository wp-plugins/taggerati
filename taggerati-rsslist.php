<?php

/*
      DESCRIPTION:  This plugin fetches RSS feeds from the url you provide 
			displays them on your blog. It can be used to manage "hot links" sections 
			or anything else you can grab via an RSS feed.
 
      INSPIRATION: The initial idea for this plugin came from the del.icio.us 
			plugin that can be found at http://chrismetcalf.net.
 
      LICENSE: This program is free software; you can redistribute it and/or 
			modify it under the terms of the GNU General Public License (GPL) as 
			published by the Free Software Foundation; either version 2 of the 
			License, or (at your option) any later version.
 
*/

require_once('taggerati.php');
require_once('taggerati-lastrss.php');

function tgr_getLinkListSettings() {
	/*
	CONFIGURATION SETTINGS 
	----------------------
	
	lastRSSPath 		relative path to the lastRSS.php file.  By default
	we assume it is in the wp-content directory - not in the plugins subdirectory.
	
	cacheDirectory		relative path to where you want your feeds cached at.  by default we assume
	you are creating a directory, rssCache, under your wp-content directory. Make
	sure you chmod 777 the rssCache directory!
	
	cacheTimeout		how long should your cache file live in seconds?  By default it is 21600 or 6 hours.
	most sites prefer you use caching so please make sure you do!
	
	connectionTimeout	how long should we attempt to grab the remote rssfile in seconds? If it timesout we
	just don't show its feed; and we will try again next time.  By default this is 20 
	seconds as we dont really want to keep the users waiting all day for a feed to show up.
	
	CDATA			how do you want to handle feeds that include CDATA.  CDATA is more complicated content in an rss feed.
	for instance, espn.com's feed includes CDATA in the link title.  You have three options for processing CDATA.
	*	content		get CDATA content (without CDATA tag).  THis is the DEFAULT SETTING
	
	*	nochange	don't make any changes (get CDATA content including CDATA tag); this will result in
	the cdata not being displayed on the page do to the format of the CDATA tags; but it 
	will be in the page's source
	
	*	strip		completely strip CDATA information - this just gets rid of it so it wont be displayed
	or in the pages source code.  NOT RECOMMENDED
	
	showRssLinkListJS	TRUE by default and will include a small block of JS in your header.  If it is false the JS will not be 						included. If you want the $new_window = 'true' option to use the JS then this must also be true.  Otherwise 					both true and simple will hardcode the target="_blank" into the new window links
	*/

	/* DEFINE THE SETTINGS -- EDIT AS YOU NEED */
	$lastRSSPath = 'wp-content/plugins/Taggerati/taggerati-lastrss.php';
	$cacheDirectory = ABSPATH . '/wp-content/plugins/Taggerati/rssCache';
	$cacheTimeout = 60 * 60 * 24 * 7; // 1 week default
	$connectionTimeout = 5;	// 5 second default timeout
	$CDATA = 'content';
	$showRSSLinkListJS = true;

	/* build an array out of the settings and send them back; don't edit this part */
	$settings = array ('lastRSSPath' => $lastRSSPath, 'cacheDirectory' => $cacheDirectory, 'cacheTimeout' => $cacheTimeout, 'connectionTimeout' => $connectionTimeout, 'CDATA' => $CDATA, 'showRSSLinkListJS' => $showRSSLinkListJS);

	return $settings;
}

function getLinkListDefaults() {
	/*
		DEFAULT FEED SETTINGS:
		(only apply to calls to _rssLinkList and not rssLinkList)
		----------------------
		* rss_feed_url: The url to get a feed from.
		
		* num_items: How many items to display; default is 15. If you want to show 
			all items, set to 0
			
		* show_description: True or false - should we show the item's description.
			By default this is true.
			
		* random: True or false - should we show  random selection of items? By 
			default this is false. Obviously, if num_items=0 this will have no effect.
			
		* before: What should we print before each item? By default this is an <li> 
			or opening html tag for a list item.
			
		* after: What should we print after each item? By default this is an </li> 
			or closing html tag for a list item.
			
		* description_seperator: What do we put between an item and it's 
			description?  By default it is a hyphen.
			
		* encoding: True or false.  Set to true if you see wierd square like 
			characters in your page output.  This helps, but does not totally solve 
			internationalization issues.
	
		* sort: One of three options telling us how to sort your items:
		
					1)	none	Don't sort them at all, just leave them in the order 
										they are in.  (DEFAULT SETTING)

					2)	asc		Sort alphabetically by the title of the item

					3)	desc	Sort in reverse alphabetical order by the title of the item.
	
		* new_window: True or false or simple.  set to true if you want the links 
			to open in a new window target="_blank" using "true" adds the target in a 
			standards complaint way.  Using simple will add it in a simple manner
			that bypasses javascript but will not validate as xhtml strict.

		* ignore_cache: Use only under special circumstances such as testing a feed.
			Setting to true will get you banned from some feed providers if you fetch 
			too often!  If you provide a number (instead of true or false) it will use 
			that value (in seconds) as the cache timeout setting.
		----------------------
	*/

	$rss_feed_url = 'http://del.icio.us/rss';
	$num_items = 10;
	$show_description = false;
	$random = false;
	$before = '<li>';
	$after = '</li>';
	$description_seperator = ' - ';
	$encoding = false;
	$sort = 'none';
	$new_window = false;
	$ignore_cache = false;

	$defaults = array ('rss_feed_url' => $rss_feed_url, 'num_items' => $num_items, 'show_description' => $show_description, 'random' => $random, 'before' => $before, 'after' => $after, 'description_seperator' => $description_seperator, 'encoding' => $encoding, 'sort' => $sort, 'new_window' => $new_window, 'ignore_cache' => $ignore_cache);

	return $defaults;
}

/*********************************************
	DONT EDIT BELOW THIS LINE
*********************************************/

function pc_assign_defaults($array) {
	$defaults = getLinkListDefaults();
	$a = array ();
	foreach ($defaults as $d => $v) {
		$a[$d] = isset ($array[$d]) ? $array[$d] : $v;
	}

	return $a;
}

function _tgr_rssLinkList($params) {
	/* this interface was created to support NAMED parameters */
	$params = pc_assign_defaults($params);
	return tgr_rssLinkList($params['rss_feed_url'], $params['num_items'], $params['show_description'], $params['random'], $params['before'], $params['after'], $params['description_seperator'], $params['encoding'], $params['sort'], $params['new_window'], $params['ignore_cache']);

}

function tgr_rssLinkListBuilder($rss_feed_url = "http://del.icio.us/rss", $num_items = 10, $show_description = true, $random = false, $before = "<li>", $after = "</li>", $description_seperator = " - ", $encoding = false, $sort = 'none', $new_window = false, $ignore_cache = false) {

	$settings = tgr_getLinkListSettings();

	if (!class_exists("tgr_lastRSS")) {
		/* added this odd if statement because it was reported that
		if lastRSS was already added by another plugin it might get included again here and cause
		a warning/error */
		require_once $settings["lastRSSPath"];
	}

	// create lastRSS object
	$rss = new tgr_lastRSS;

	// setup transparent cache
	$rss->cache_dir = $settings["cacheDirectory"];
	if ($ignore_cache) {
		if (is_numeric($ignore_cache)) {
			$rss->cache_time = $ignore_cache;
		} else {
			$rss->cache_time = 0;
		}
	} else {
		$rss->cache_time = $settings["cacheTimeout"];
	}

	$rss->connection_time = $settings["connectionTimeout"];
	$rss->CDATA = $settings["CDATA"];

	$rssList = '';

	if ($rs = $rss->get($rss_feed_url)) {
		// here we can work with RSS fields
		$items = $rs['items'];

		if ($random) {
			// We want a random selection, so lets shuffle it
			shuffle($items);
		}

		// Slice off the number of items that we want
		if ($num_items > 0) {
			$items = array_slice($items, 0, $num_items);
		}

		/**********************
			Now that we have potentially randomized and cut down our list
			we will sort the remainders if we need to
		***********************/
		// make sure we are not getting messed up just because
		// someone typed in caps.
		$sort = strtolower($sort);
		if ($sort == 'asc' || $sort == 'desc') {
			//Order alpha by title
			foreach ($items as $item) {
				$sortBy[] = $item['title'];
			}
			//Make titles lowercase
			//otherwise capitals will come before lowercase
			$sortByLower = array_map('strtolower', $sortBy);

			if ($sort == 'asc') {
				array_multisort($sortByLower, SORT_ASC, SORT_STRING, $items);
			} else
				if ($sort == 'desc') {
					array_multisort($sortByLower, SORT_DESC, SORT_STRING, $items);
				}
		}

		// explicitly set this because $new_window could be "simple"
		$target = '';
		if ($new_window == true && $settings["showRSSLinkListJS"]) {
			$target = ' rel="external" ';
		}
		/*else if($new_window == true || $new_window = 'simple'){
		 $target=' target="_blank" ';
		}*/

		// Loop through the items and build the output list

		foreach ($items as $item) {

			// Link title is the text shown in the list
			$thisLink = '';
			$thisTitle = $item['title'];
			if ($encoding) {
				$thisTitle = utf8_encode($thisTitle);
			}

			// Description and linkTitle (attribute of the anchor tag)
			$thisDescription = '';
			$linkTitle = '';
			if (isset ($item['description'])) {
				$linkTitle = $item['description'];
				if ($encoding) {
					$linkTitle = utf8_encode($linkTitle);
				}

				if ($show_description) {
					// bulid the description and convert any special HTML stuff back into HTML
					if (strlen(trim($thisTitle))) {
						$thisDescription = $description_seperator;
					}
					$thisDescription = $thisDescription."<span class=\"rssLinkListItemDesc\">" . strip_tags(strtr($linkTitle, array_flip(get_html_translation_table(HTML_ENTITIES)))) . "</span>";
				}
			}

			// only build the hyperlink if a link is provided..
			if (strlen(trim($item['link'])) && strlen(trim($thisTitle))) {
				$thisLink = '<span class="rssLinkListItemTitle"><a href="'.$item['link'].'"'.$target.'>'.$thisTitle.'</a></span>';
			}
			elseif (strlen(trim($item['link'])) && $show_description) {
				// if we don't have a title but we do have a description we want to show.. link the description
				$thisLink = '<span class="rssLinkListItemTitle"><a href="'.$item['link'].'"'.$target.'>'.$thisDescription.'</a></span>';
				$thisDescription = '';
			} else {
				$thisLink = '<span class="rssLinkListItemTitle">'.$thisTitle.'</span>';
			}

			$rssList .= $before.$thisLink.$thisDescription.$after."\n";
		}

	} else {
		$rssList .= 'requested list not available';
	}

	return $rssList;

}

function tgr_rssLinkList($rss_feed_url = "http://del.icio.us/rss", $num_items = 10, $show_description = true, $random = false, $before = "<li>", $after = "</li>", $description_seperator = " - ", $encoding = false, $sort = 'none', $new_window = false, $ignore_cache = false) {
		// display the final list
	return tgr_rssLinkListBuilder($rss_feed_url, $num_items, $show_description, $random, $before, $after, $description_seperator, $encoding, $sort, $new_window, $ignore_cache);
}

function tgr_rssLinkListFilter($text) {
	return preg_replace_callback("/<!--rss:(.*)-->/", "tgr_rssMatcher", $text);
}

function tgr_rssMatcher($matches) {
	// get the settings passed in
	$filterSetting = explode(",", $matches[1]);
	$params = array ('rss_feed_url' => $matches[1]);

	// determine if we have more than just a url
	/* loop over the array and break each element up into a sub array like:
			subArray[0] = key
			subArray[1] = value
		*/

	if (count($filterSetting) > 1) {
		foreach ($filterSetting as $setting) {
			$setting = explode(":=", $setting);
			$keyVal = $setting[0];
			$valVal = $setting[1];
			if ($valVal == 'true' || $valVal == '1') {
				$valVal = true;
			}
			elseif ($valVal == 'false' || $valVal == '0') {
				$valVal = false;
			}
			// make sure before and after tags are no longer escaped
			$valVal = html_entity_decode($valVal);

			$params[$keyVal] = $valVal;
		}
	} else {
		// handle the origional default settings for when the filter was first added to the plugin

		$params['num_items'] = 0;
		$params['show_description'] = false;
		$params['random'] = false;
		$params['before'] = '<li>';
		$params['after'] = '</li>';
		$params['description_seperator'] = ' - ';
		$params['encoding'] = false;
		$params['sort'] = 'asc';
		$params['new_window'] = false;
		$params['ignore_cache'] = false;
	}

	$params = pc_assign_defaults($params);

	return rssLinkListBuilder($params['rss_feed_url'], $params['num_items'], $params['show_description'], $params['random'], $params['before'], $params['after'], $params['description_seperator'], $params['encoding'], $params['sort'], $params['new_window'], $params['ignore_cache']);

}

if (function_exists('add_filter')) {
	add_filter('the_content', 'tgr_rssLinkListFilter');
}

function tgr_rssLinkList_JS() {

	$jsstring = '<script type="text/javascript"><!--
     function addEvent(elm, evType, fn, useCapture){
		 	if (elm.addEventListener){
       elm.addEventListener(evType, fn, useCapture);
			 return true;
			} else if (elm.attachEvent){
     		var r = elm.attachEvent("on"+evType, fn);
     		return r;
	    } else {
     		alert("Handler could not be removed");
    	}
	  }
		
		function externalLinks() {
  		if (!document.getElementsByTagName) {
				return;
		  }
			
			var anchors = document.getElementsByTagName("a");
			
			for (var i=0; i<anchors.length; i++) {
				var anchor = anchors[i];
				if (anchor.getAttribute("href") && anchor.getAttribute("rel") == "external")
		    	anchor.setAttribute("target","_blank");
	    }
	  }
	
		addEvent(window, "load", externalLinks);
		//-->
	</script>';

	echo $jsstring;
}
?>