<?php

/*
Plugin Name: Taggerati
Plugin URI: http://www.i-marco.nl/wp/wordpress/
Description: The most comprehensive AND easy to use local Tags engine for bloggers. Previously only available for Pivot users, now also available for WordPress
Author: Marco van Hylckama Vlieg, Elliott Back
Version: 1.4.0
Author URI: http://www.i-marco.nl/weblog/
*/

/*  Copyright 2005  Marco van Hylckama Vlieg  (email : marco@i-marco.nl)
 
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
	Contains an integrated version of the rssLinkList plugin by
	Bill Rawlinson. It's function names have been changed to
	prevent collision effects if users have the original rssLinkList
	plugin installed.
 
	RSSLinkList home: http://rawlinson.us/blog/index.php?p=212
*/

if(!defined('ABSPATH')){
	require_once('../../../wp-config.php');
	require_once(ABSPATH . 'wp-includes/wp-db.php');
}

require_once('taggerati-lastrss.php');
require_once('taggerati-rsslist.php');
require_once('taggerati-search.php');
require_once('taggerati-extract-terms.php');

// install database tables
function tgr_install() {
	global $table_prefix, $wpdb, $user_level;

	$table1_name = $table_prefix . 'taggerati_tags';
	$table2_name = $table_prefix . 'taggerati_tag_post';
	$table3_name = $table_prefix . 'taggerati_tag_rel';

	// security check

	get_currentuserinfo();
	if ($user_level < 8) {
		return;
	}

	// create tables, if not done already
	if ($wpdb->get_var("show tables like '$table1_name'") != $table1_name) {
		$sql = 'CREATE TABLE '.$table1_name.' (
				       id mediumint(9) NOT NULL AUTO_INCREMENT,
				       tag VARCHAR(100) NOT NULL,
				       UNIQUE KEY id (id)
				       );';

		$sql2 = 'CREATE TABLE '.$table2_name.' (tag INT not null , post INT not null , UNIQUE (tag, post));';

		$sql3 = 'CREATE TABLE '.$table3_name.' (
				        tag MEDIUMINT(9) NOT NULL,
				        rel MEDIUMINT(9) NOT NULL, UNIQUE (tag, rel)
				        );';

		require_once (ABSPATH . 'wp-admin/upgrade-functions.php');
		
		dbDelta($sql);
		dbDelta($sql2);
		dbDelta($sql3);
		
		// add options
		
		add_option('taggerati_tagpage', '');
		add_option('taggerati_showflickr', '1');
		add_option('taggerati_showreader', '1');
		add_option('taggerati_usereaderajax', '1');
		add_option('taggerati_usereaderdescriptions', '0');
		add_option('taggerati_autotag', '0');
		add_option('taggerati_autotagsearch', '0');
		add_option('taggerati_usertag', '0');
		add_option('taggerati_cloud_maxsize', '250');
		add_option('taggerati_cloud_minsize', '90');
		add_option('taggerati_sortorder', 'desc');
		add_option('taggerati_numflickr', 5);
		add_option('taggerati_listsize', 5);
		add_option('taggerati_flickrsize', 's');
		
		// add mod_rewrite rules, if wp 1.5
		if(get_bloginfo('version') < 2){
			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
			$rule = array();
			$rule [] = "<IfModule mod_rewrite.c>\n";
   			$rule [] = "RewriteEngine On\n";
   			$rule [] = "RewriteRule ^tags/(.*)$ ?page_id=tags&tag=$1\n";
   			$rule [] = "</IfModule>\n";
   	
			insert_with_markers(ABSPATH . '.htaccess', 'Taggerati', $rule);
		}

		// chmod rssCache folder, if possible
		@chmod(ABSPATH . 'wp-content/plugins/Taggerati/rssCache', 777);
		
		// add a <tag></tag> quicktag
		if(file_exists(ABSPATH . 'wp-admin/quicktags.js')){
			$data = explode("\n", implode('', file(ABSPATH . 'wp-admin/quicktags.js')));
			if(!preg_match("|new edButton\('ed_tag'|si", implode("\n", $data))){
				$f = fopen(ABSPATH . 'wp-admin/quicktags.js', 'w');
				$linebreak = '';
				$flag = false;
				$eol = false;
			
			      foreach($data as $line){
      					if(preg_match("|new[\s]+edButton\(\'ed_li\'|si", $line))
      						$flag = true;
      		
				      	if($flag && preg_match("|\);|si", $line))
      						$eol = true;
      		
				      	fwrite($f, $linebreak . $line);
      		
				      	if($flag && $eol){
      						fwrite($f, $linebreak . "\n" . "edButtons[edButtons.length] = new edButton('ed_tag', 'tag', '<tag>','</tag>','t');");				
      						$flag = false;
      						$eol = false;
      					}
      		
      					$linebreak = "\n";
      				}
     	 
      				fclose($f);
	    		}
		}
	}
}

// WP 2.0 rewrite rules

function tgr_rewrite_rules($wp_rewrite) {
   $newrules = array();
   $newrules['tags/(.*)[/]?$'] = 'index.php?pagename=tags&tag=$matches[1]';
   $wp_rewrite->rules = array_merge($newrules, $wp_rewrite->rules);
}

function tgr_addvars($vars) {
	$vars [] = 'tag';
	return $vars;
}

if(get_bloginfo('version') >= 2){
	add_filter('query_vars', 'tgr_addvars');
	add_filter('generate_rewrite_rules','tgr_rewrite_rules');
}

// replace <tag> tags with proper HTML when rendering a post
function tgr_format_tags($content) {
	$tagpage = get_option('taggerati_tagpage');
	$content = preg_replace('/\<tag\>(.*?)\<\/tag\>/', '<a href="'.$tagpage.'$1" title="local tag: $1" rel="tag">$1</a>', $content);
	$content = preg_replace('/\<itag\>(.*?)\<\/itag\>/', '$1', $content);
	$content = preg_replace('/\<tag url=\"(.*?)\"\>(.*?)\<\/tag\>/', '<a href="$1" title="tagged external link: $1 with tag: $2" rel="tag">$2</a>', $content);
	return $content;
}

// find all tags used in a posting
// returns: array("tag1", "tag2", "tag3")
function tgr_find_tags($content) {
	// The normal tags

	$phase1 = explode('<tag', $content);
	// strip fucked entries
	$realphase1 = array ();
	foreach ($phase1 as $suspect) {
		if (strstr($suspect, '</tag>') != false) {
			array_push($realphase1, $suspect);
		}
	}
	$phase1 = $realphase1;
	$tagsforpost = array ();
	foreach ($phase1 as $rawtag) {
		$phase2 = explode('</tag>', $rawtag);
		$phase3 = explode('>', $phase2[0]);
		$tag = $phase3[1];
		if ((strlen($tag) > 0) && (!strstr($tag, '>')) && (!strstr($tag, '<')))
			$tagsforpost[] = $tag;
	}

	// The invisible tags

	$phase1 = explode('<itag', $content);
	// strip fucked entries
	$realphase1 = array ();
	foreach ($phase1 as $suspect) {
		if (strstr($suspect, '</itag>') != false) {
			array_push($realphase1, $suspect);
		}
	}
	$phase1 = $realphase1;
	foreach ($phase1 as $rawtag) {
		$phase2 = explode('</itag>', $rawtag);
		$phase3 = explode('>', $phase2[0]);
		$tag = $phase3[1];
		if ((strlen($tag) > 0) && (!strstr($tag, '>')) && (!strstr($tag, '<')))
			$tagsforpost[] = $tag;
	}
	
	// Yahoo's tags
	if(get_option('taggerati_autotag') == 1){
		$y_tags = sem_extract_terms($content);
		if(is_array($y_tags)){
			foreach($y_tags as $tag){
				if(!in_array($tag, $tagsforpost)){
					$tagsforpost [] = $tag;
				}
			}
		}
	}
	
	return $tagsforpost;
}

// insert a new tag
// check if it doesn't exist already
function tgr_insert_tag($thetag) {
	global $table_prefix, $wpdb;
	
	$table_name = $table_prefix . 'taggerati_tags';

	if (tgr_get_tag_id($thetag) > 0) {
		return true;
	} else {
		$sql = 'INSERT INTO '.$table_name.' (tag) VALUES ("'.$thetag.'")';
		$wpdb->query($sql);
	}
}

// get the ID for a tag. returns 0 if the tag doesn't exist
function tgr_get_tag_id($thetag) {
	global $table_prefix, $wpdb;

	$table_name = $table_prefix.'taggerati_tags';
	$sql = 'SELECT id from '.$table_name.' WHERE tag="'.$thetag.'"';
	$result = $wpdb->get_row($sql, ARRAY_A, 0);
	
	if ($result['id'] > 0) {
		return $result['id'];
	} else {
		return 0;
	}
}

// get all related tags. There are two forms:
// tgr_get_related_tags($thetagid, 'id') returns array(1,2,5,8)  (example)
// tgr_get_related_tags($thetagid, 'array') returns array(1 => 'sometag', 2 => 'some_other_tag');
function tgr_get_related_tags($thetagid, $type) {
	global $table_prefix, $wpdb, $user_level, $post;

	$tags_table_name = $table_prefix . 'taggerati_tags';
	$rel_table_name = $table_prefix . 'taggerati_tag_rel';

	if ($type == 'array') {
		$sql = 'SELECT '.$tags_table_name.'.id as id, '.$tags_table_name.'.tag as tag from '.$tags_table_name.' INNER JOIN '.$rel_table_name.' on '.$rel_table_name.'.rel='.$tags_table_name.'.id where '.$rel_table_name.'.tag='.$thetagid;
		$result = $wpdb->get_results($sql, ARRAY_A);
		if (sizeof($result) == 0) {
			return array ();
		} else {
			return $result;
		}
	} else {
		$sql = 'SELECT '.$tags_table_name.'.id as id from '.$tags_table_name.' INNER JOIN '.$rel_table_name.' on '.$rel_table_name.'.rel='.$tags_table_name.'.id where '.$rel_table_name.'.tag='.$thetagid;
		$result = $wpdb->get_results($sql, ARRAY_N);
		if (sizeof($result) == 0) {
			return array ();
		} else {
			$resultids = array ();
			foreach ($result as $thisentry) {
				$resultids[] = $thisentry;
			}
		}
	}
}

// inserts related tag entries for a certain tag, based on all tags found in the posting
function tgr_insert_related_tag_entries($thetag, $alltags) {
	global $table_prefix, $wpdb;
	
	$rel_table_name = $table_prefix.'taggerati_tag_rel';
	$thetagid = tgr_get_tag_id($thetag);
	// ?? $related = tgr_get_related_tags($thetagid, 'id');
	
	foreach ($alltags as $thistag) {
		$thistagid = tgr_get_tag_id($thistag);
		if ($thetagid != $thistagid) {
			$sql = 'REPLACE INTO '.$rel_table_name.' (tag, rel) VALUES ('.$thetagid.', '.$thistagid.')';
			$wpdb->query($sql);
		}
	}
}

// Returns all the tags for a given post
// {{tag1}, {tag2}, ... {tagn}}
function tgr_get_tags_for_post($postid) {
	global $table_prefix, $wpdb;

	$tags_table_name = $table_prefix . 'taggerati_tag_post';
	$sql = 'SELECT tag from ' . $tags_table_name . ' WHERE post=' . $postid;
	$result = $wpdb->get_results($sql, ARRAY_N);
	$taglist = array ();

	if(sizeof($result) > 0)   {
		foreach ($result as $thisresult) {
			$taglist[] = $thisresult;
		}
	}
	return $taglist;
}

// Returns all the named tags for a given post
// {tag1, tag2, ..., tagn}
function tgr_get_namedtags_for_post($postid) {
	global $table_prefix, $wpdb;

	$tags_table_name = $table_prefix . 'taggerati_tag_post';
	$tagnames_table_name = $table_prefix . 'taggerati_tags';
	$sql = 'SELECT t_name.tag from ' . $tags_table_name . ' t_id, ' . $tagnames_table_name . ' t_name WHERE t_id.post=' . $postid . ' AND t_id.tag = t_name.id';
	$result = $wpdb->get_results($sql, ARRAY_N);
	$taglist = array ();
	
	foreach ($result as $thisresult) {
		$taglist[] = $thisresult[0];
	}
	
	return $taglist;
}

function tgr_get_all_tags() {
	global $table_prefix, $wpdb;
	$tagnames_table_name = $table_prefix . 'taggerati_tags';	
	$sql = 'SELECT tag FROM '.$tagnames_table_name.' order by tag';
	$result = $wpdb->get_results($sql, ARRAY_N);
	$taglist = array ();	
	foreach ($result as $thisresult) {
		$taglist[] = $thisresult[0];
	}
	return $taglist;	
}

function tgr_tagsearch() {
		$aAllTags = tgr_get_all_tags();
		$sOut = "var tagsArray = [\n";
		foreach($aAllTags as $sThisTag)  {
			$sOut .= "    \"".$sThisTag."\","."\n";
		}
		$sOut .= "];\n";
		echo $sOut;
		echo 'var tagDataSource = new YAHOO.widget.DS_JSArray(tagsArray)';
		echo "\n";
		echo 'var oAutoComp = new YAHOO.widget.AutoComplete("tgTextInput","searchContainer", tagDataSource);';
		echo "\n";  
		echo "oAutoComp.queryDelay = 0;\n";  
		echo "oAutoComp.typeAhead = true;\n";  
		echo 'oAutoComp.prehighlightClassName = "yui-ac-prehighlight"; ';
		echo "\n";  
		echo "oAutoComp.useShadow = false;\n";
}

function tgr_tagsearchJS() {
	$sPath = get_bloginfo('url').'/wp-content/plugins/Taggerati/';
	echo '<link rel="stylesheet" type="text/css" href="'.$sPath.'yui-autocomplete/autocomplete.css" />';
	echo "\n";
	echo '<script src="'.$sPath.'yui-autocomplete/yahoo.js" type="text/javascript"></script>';	
	echo "\n";
	echo '<script src="'.$sPath.'yui-autocomplete/dom.js" type="text/javascript"></script>';	
	echo "\n";
	echo '<script src="'.$sPath.'yui-autocomplete/event.js" type="text/javascript"></script>';	
	echo "\n";
	echo '<script src="'.$sPath.'yui-autocomplete/autocomplete.js" type="text/javascript"></script>';	
	echo "\n";
}

// inserts tag->post relational entry
function tgr_insert_tag_for_post($thetag, $postid) {
	global $table_prefix, $wpdb, $user_level;

	$tagsinpost = tgr_get_tags_for_post($postid);
	$tags_table_name = $table_prefix . 'taggerati_tag_post';
	$thetagid = tgr_get_tag_id($thetag);
	
	if (!in_array($thetagid, $tagsinpost)) {
		$sql = 'REPLACE INTO ' . $tags_table_name . ' (tag, post) VALUES (' . $thetagid . ', ' . $postid . ')';
		$wpdb->query($sql);
	}
}

function tgr_process_tags($post_id) {
	global $post, $wpdb, $table_prefix;
	$tags_post_table_name = $table_prefix . 'taggerati_tag_post';

	// reset tags -> post relationships because we might have
	// removed tags after an edit.

	$resetsql = 'DELETE FROM '.$tags_post_table_name.' WHERE post='.$post_id;
	$wpdb->query($resetsql);

	$thePost = $wpdb->get_row('SELECT post_content from '.$wpdb->posts.' WHERE ID='.$post_id, ARRAY_A);
	$theContent = $thePost['post_content'];
	$alltags = tgr_find_tags($theContent);
	
	foreach ($alltags as $thetag) {
		tgr_insert_tag($thetag);
		tgr_insert_tag_for_post($thetag, $post_id);
	}
	
	foreach ($alltags as $thetag) {
		tgr_insert_related_tag_entries($thetag, $alltags);
	}

	// call the janitor
	tgr_tag_cleanup();
}

// Check for orphaned tags and clean up
function tgr_tag_cleanup() {
	global $table_prefix, $wpdb, $user_level;

	$tags_post_table_name = $table_prefix . 'taggerati_tag_post';
	$tags_table_name = $table_prefix . 'taggerati_tags';
	$tags_rel_table_name = $table_prefix . 'taggerati_tag_rel';

	$sql = 'SELECT id from '.$tags_table_name;
	$tagsresult = $wpdb->get_results($sql, ARRAY_N);

	foreach ($tagsresult as $tagid) {
		$sql = 'SELECT post from '.$tags_post_table_name.' WHERE tag='.$tagid[0];
		$result = $wpdb->get_results($sql, ARRAY_N);
		if (sizeof($result) == 0) {
			$sql = 'DELETE FROM '.$tags_table_name.' WHERE id='.$tagid[0];
			$wpdb->query($sql);
			$sql = 'DELETE FROM '.$tags_rel_table_name.' WHERE tag='.$tagid[0];
			$wpdb->query($sql);
		}
	}
}

function tgr_post_cleanup($postid) {
	global $table_prefix, $wpdb, $user_level;
	$tags_post_table_name = $table_prefix.'taggerati_tag_post';
	$sql = 'DELETE FROM '.$tags_post_table_name.' WHERE post='.$postid;
	$wpdb->query($sql);
}

function tgr_delete_aftermath($postid) {
	tgr_post_cleanup($postid);
	tgr_tag_cleanup();
}

function tgr_tagsinpost() {
	global $table_prefix, $post, $wpdb, $user_level;
	
	$tags_post_table_name = $table_prefix.'taggerati_tag_post';
	$tags_table_name = $table_prefix.'taggerati_tags';
	$tagpage = get_option('taggerati_tagpage');
	$sql = 'SELECT '.$tags_table_name.'.tag as tag FROM '.$tags_table_name.' INNER JOIN '.$tags_post_table_name.' ON '.$tags_post_table_name.'.tag='.$tags_table_name.'.id WHERE '.$tags_post_table_name.'.post='.$post->ID;
	$results = $wpdb->get_results($sql, ARRAY_A);
	
	if (sizeof($results) > 0) {
		echo '<span id="taggeratitaglist">';
		foreach ($results as $thisresult) {
			echo '<a href="' . $tagpage . $thisresult['tag'] . '">' . $thisresult['tag'] . '</a>';
			echo ' ';
		}
		echo '</span>';
	}
	
	// TODO: ajaxify
	if(get_option('taggerati_usertag') == 1){
		// The appropriate script
		echo '<script type="text/javascript" src="' . get_bloginfo('url'). '/wp-content/plugins/Taggerati/taggerati-ajax.js"></script>';
	
		// The AJAX form
		echo '<span style="display:inline; margin: 0px 4px 0px 4px;">';
		echo '<input type="text" size="8" id="taggeratiusertag" value="" style="border: 1px dashed #3c8fe4; padding: 2px; margin-right:4px;" />';
		echo '<button type="button" value="Add" onclick="javascript:addTag(\'' .get_bloginfo('url'). '/wp-content/plugins/Taggerati/taggerati-ajax.php\', document.getElementById(\'taggeratiusertag\').value, \'' . $post->ID . '\', \'' . $tagpage . '\');">Add</button>';
		echo '</span>';
	}
}

function tgr_posts_with_tags($nolimit = false) {
	global $table_prefix, $post, $wpdb, $user_level;
	$tags_post_table_name = $table_prefix.'taggerati_tag_post';

	$taglist = tgr_find_tags($post->post_content);
	foreach ($taglist as $tag) {
		$sql = 'SELECT post from '.$tags_post_table_name.' WHERE tag='.tgr_get_tag_id($tag).' AND post != '.$post->ID;
		$result = $wpdb->get_results($sql, ARRAY_N);
		if (sizeof($result) > 0) {
			tgr_posts_with_tag($tag, true, $nolimit);
		}
	}
}

// Calls the taggerati search feature
function taggerati_search_log(){
	global $post;
	taggerati_search_log_to_db($post->ID);
}

function tgr_posts_with_tag($tag, $excludeself = false, $nolimit = true) {
	global $table_prefix, $post, $wpdb, $user_level;
	
	$tags_post_table_name = $table_prefix.'taggerati_tag_post';
	$tagid = tgr_get_tag_id($tag);
	$sql = 'SELECT post from '.$tags_post_table_name.' WHERE tag='.$tagid;
	
	if ($excludeself) {
		$sql .= ' AND '.$tags_post_table_name.'.post != '.$post->ID;
	}
	
	$sql .= " ORDER BY ".$tags_post_table_name.".post ".get_option('taggerati_sortorder');
	
	if ((get_option('taggerati_listsize') > 0) && !$nolimit) {
		$sql .= " LIMIT ".get_option('taggerati_listsize');
	}
	
	$result = $wpdb->get_results($sql, ARRAY_N);
	
	if (sizeof($result) > 0) {
		$postslist = array ();
		echo '<div class="tgrblock">';
		echo '<strong>Other posts tagged with \''.$tag.'\'</strong>';
		echo '<ul>';
		foreach ($result as $thispost) {
			$thepost = get_post($thispost[0]);
			echo '<li><a href="'.get_permalink($thepost->ID).'">'.$thepost->post_title.'</a></li>';
		}
		echo '</ul>';
		echo '</div>';
	}
}

function tgr_do_rss($feedurl, $feedname, $tag, $show_description = false) {

	$output = _tgr_rssLinkList(array ('show_description' => $show_description, 'rss_feed_url' => $feedurl, 'description_seperator' => '<br />'));
	$output = html_entity_decode($output);
	
	if ($output == '') {
		if ($feedname == 'delicious') {
			$feedname = 'del.icio.us';
		}
		$output = '<div class="tgrblock">Nothing found on <strong>'.$feedname.'</strong> for \'<strong>'.$tag.'\'</strong></div>';
	} else {
		$output = 'Latest on <strong>\''.$feedname.'\'</strong> for \'<strong>'.$tag.'</strong>\':<ul class="taggeratilist">'.$output.'</ul>';
	}
	echo $output;
}

function tgr_tag_page() {
  if(get_option('taggerati_usereaderajax') == 1){
  	echo '<script type="text/javascript" src="'.get_bloginfo('url').'/wp-content/plugins/Taggerati/prototype.js"></script>';
  	echo '<script type="text/javascript">';
  	echo '/*<![CDATA[ */';
  	echo 'function doList(type, tag)  {';
  	echo 'if(type == \'delicious\')  { ttype = \'del.icio.us\'; } else { ttype = type; }';
  	echo '$(\'tgrrsslist\').innerHTML = \'<img src="'.get_bloginfo('url').'/wp-content/plugins/Taggerati/loading_\' + ttype + \'.gif" alt=""/>\';';
  	echo 'var pars = \'type=\' + type + \'&tag=\' + tag + \'&showdesc=' . (get_option('taggerati_usereaderdescriptions') == 1 ? true : false) . '\';';
  	echo 'var url = \''.get_bloginfo('url').'/wp-content/plugins/Taggerati/taggerati-ajax.php\';';
  	echo 'var myAjax = new Ajax.Updater(\'tgrrsslist\', url, {method: \'get\', parameters: pars});';
  	echo '}';
  	echo '/* ]]> */';
  	echo '</script>';
  } else {
  	echo '<script type="text/javascript">';
  	echo '/*<![CDATA[ */';
  	echo 'function showList(id) {';
  	echo 'var ids = new Array("tgr_technorati", "tgr_yahoo", "tgr_msn", "tgr_furl", "tgr_tagzania", "tgr_feedmarker", "tgr_feedster", "tgr_icerocket", "tgr_google", "tgr_rawsugar", "tgr_shadows", "tgr_delicious", "tgr_digg", "tgr_43things");';
  	echo 'for(var i = 0; i < ids.length; i++){';
  	echo 'var e = document.getElementById(ids[i]);';
  	echo 'if(!e) {} else { e.style.display = \'none\'; }';
  	echo '}';
		echo 'var e = document.getElementById(id); ';
  	echo 'if(!e) {} else { e.style.display = \'block\'; }';
  	echo '}';
  	echo '/* ]]> */';
  	echo '</script>';
  }

	if(empty($_GET['tag']))
		$_GET['tag'] = get_query_var('tag');

	if (str_replace('/', '', $_GET['tag']) != '') {
		echo '<div class="tgrblock"><strong>This is the tag page for \''.str_replace('/', '', strtolower($_GET["tag"])).'\'</strong>.</div>';
	} else {
		echo '<div class="tgrblock">This is the local tags universe for this website.</div>';
	}

	$tagpage = get_option('taggerati_tagpage');

	if (strlen(str_replace('/', '', $_GET['tag'])) == 0) {
		echo '<div class="tgrblock">';
		echo tgr_tag_cloud();
		echo '</div>';
	} else {

		$relatedtags = tgr_get_related_tags(tgr_get_tag_id(str_replace('/', '', $_GET['tag'])), 'array');

		if (sizeof($relatedtags) > 0) {
			echo '<h3>Related Tags</h3>';
			echo '<div class="tgrblock">';
			foreach ($relatedtags as $thetag) {
				echo '<a href="'.$tagpage.$thetag['tag'].'">'.$thetag['tag'].'</a> ';
			}
			echo '</div>';
		}

		echo tgr_posts_with_tag($_GET['tag'], false);

		if (get_option('taggerati_showflickr') == 1) {
			echo '<h3>Flickr on \''.$_GET['tag'].'\'</h3>
					<div class="tgrblock flickrblock">
						<!-- Start of Flickr Badge -->
						<script type="text/javascript" src="http://www.flickr.com/badge_code_v2.gne?show_name=1&amp;count='.get_option('taggerati_numflickr').'&amp;display=latest&amp;size='.get_option('taggerati_flickrsize').'&amp;layout=h&amp;source=all_tag&amp;tag='.str_replace('/', '', $_GET['tag']).'"></script>
					</div>
						';
		}

		if (get_option('taggerati_showreader') == 1) {
			$no_trail_tag = str_replace('/', '', $_GET['tag']);
			$blog_url = get_bloginfo('url');
				
			echo '<h3>External Feeds for \''.$_GET['tag'].'\'</h3>';
			echo '<div class="tgrblock">';
  		echo '<p><small>click icon for a list of links on \'' . $no_trail_tag . '\'</small></p>';
  			
			if(get_option('taggerati_usereaderajax') == 1){
  			echo '<a href="javascript:doList(\'technorati\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url .  '/wp-content/plugins/Taggerati/technorati.png" alt="Technorati" /></a> ';
  			echo '<a href="javascript:doList(\'delicious\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/delicious.png" alt="del.icio.us" /></a> ';
  			echo '<a href="javascript:doList(\'digg\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/digg.png" alt="digg" /></a> ';
				echo '<a href="javascript:doList(\'furl\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/furl.png" alt="Furl" /></a> ';
  			echo '<a href="javascript:doList(\'google\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/google.png" alt="Google" /></a> ';
  			echo '<a href="javascript:doList(\'msn\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/msn.png" alt="MSN" /></a> ';
  			echo '<a href="javascript:doList(\'yahoo\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/yahoo.png" alt="MSN" /></a> ';
  			echo '<a href="javascript:doList(\'feedster\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/feedster.png" alt="Feedster" /></a> ';
  			echo '<a href="javascript:doList(\'icerocket\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/icerocket.png" alt="Icerocket" /></a> ';
  			echo '<a href="javascript:doList(\'rawsugar\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/rawsugar.png" alt="RawSugar" /></a> ';
  			echo '<a href="javascript:doList(\'tagzania\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/tagzania.png" alt="Tagzania" /></a> ';
  			echo '<a href="javascript:doList(\'shadows\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/shadows.png" alt="Shadows" /></a> ';
  			echo '<a href="javascript:doList(\'feedmarker\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/feedmarker.png" alt="Feedmarker" /></a> ';
  			echo '<a href="javascript:doList(\'43things\', \'' . $no_trail_tag . '\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/43things.png" alt="43 Things" /></a>';
  			echo '<div id="tgrrsslist"></div>';
  		} else {
  			$show_desc = (get_option('taggerati_usereaderdescriptions') == 1 ? true : false);
  		
  			echo '<a href="javascript:showList(\'tgr_technorati\');"><img src="' . $blog_url .  '/wp-content/plugins/Taggerati/technorati.png" alt="Technorati" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_delicious\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/delicious.png" alt="del.icio.us" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_digg\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/digg.png" alt="digg" /></a> ';
				echo '<a href="javascript:showList(\'tgr_furl\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/furl.png" alt="Furl" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_google\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/google.png" alt="Google" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_msn\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/msn.png" alt="MSN" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_yahoo\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/yahoo.png" alt="MSN" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_feedster\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/feedster.png" alt="Feedster" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_icerocket\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/icerocket.png" alt="Icerocket" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_rawsugar\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/rawsugar.png" alt="RawSugar" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_tagzania\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/tagzania.png" alt="Tagzania" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_shadows\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/shadows.png" alt="Shadows" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_feedmarker\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/feedmarker.png" alt="Feedmarker" /></a> ';
  			echo '<a href="javascript:showList(\'tgr_43things\');"><img src="' . $blog_url . '/wp-content/plugins/Taggerati/43things.png" alt="43 Things" /></a></p>';
  			
  			echo '<div id="tgr_technorati">';
  			tgr_do_rss('http://feeds.technorati.com/feed/posts/tag/' . str_replace(" ", "+", $_GET["tag"]), 'technorati.com', $_GET['tag'], $show_desc);
  			echo '</div>';
  			
  			echo '<div id="tgr_yahoo" style="display:none;">';
  			tgr_do_rss('http://api.search.yahoo.com/WebSearchService/rss/webSearch.xml?appid=yahoosearchwebrss&adult_ok=1&query='.str_replace(" ", "+", $_GET["tag"]), 'yahoo.com', $_GET['tag'], $show_desc);
  			echo '</div>';
  			
  			echo '<div id="tgr_msn" style="display:none;">';
  			tgr_do_rss('http://search.msn.com/results.aspx?format=rss&q='.str_replace(" ", "+", $_GET["tag"]), 'msn.com', $_GET['tag'], $show_desc);
  			echo '</div>';
					
  			echo '<div id="tgr_furl" style="display:none;">';
  			tgr_do_rss('http://rss.furl.net/members/rss.xml?topic='.str_replace(" ", "+", $_GET["tag"]), 'furl.net', $_GET['tag'], $show_desc);
  			echo '</div>';
  
  			echo '<div id="tgr_tagzania" style="display:none;">';
  			tgr_do_rss('http://www.tagzania.com/rss/tag/'.str_replace(" ", "+", $_GET["tag"]), 'tagzania.com', $_GET['tag'], $show_desc);
  			echo '</div>';

  			echo '<div id="tgr_feedmarker" style="display:none;">';
  			tgr_do_rss('http://www.feedmarker.com/feed/tags/'.str_replace(" ", "+", $_GET["tag"]), 'feedmarker.com', $_GET['tag'], $show_desc);
  			echo '</div>';

  			echo '<div id="tgr_feedster" style="display:none;">';
  			tgr_do_rss('http://www.feedster.com/search/type/rss/'.$_GET['tag'].'&sort=relevance&ie=UTF-8&hl=&content=full&type=rss&limit=15&db='.str_replace(" ", "+", $_GET["tag"]), 'feedster.com', $_GET['tag'], $show_desc);
  			echo '</div>';

  			echo '<div id="tgr_icerocket" style="display:none;">';
  			tgr_do_rss('http://www.icerocket.com/search?tab=blog&q='.str_replace(" ", "+", $_GET["tag"]).'&rss=1', 'icerocket.com', $_GET['tag'], $show_desc);
  			echo '</div>';

				echo '<div id="tgr_google" style="display:none;">';
				tgr_do_rss('http://blogsearch.google.com/blogsearch_feeds?hl=en&q='.str_replace(" ", "+", $_GET["tag"]).'&btnG=Search+Blogs&num=10&output=rss', 'blogsearch.google.com', $_GET['tag'], $show_desc);
				echo '</div>';

  			echo '<div id="tgr_rawsugar" style="display:none;">';
  			tgr_do_rss('http://www.rawsugar.com/rss/search/'.str_replace(" ", "+", $_GET["tag"]), 'rawsugar.com', $_GET['tag'], $show_desc);
  			echo '</div>';

  			echo '<div id="tgr_shadows" style="display:none;">';
  			tgr_do_rss('http://www.shadows.com/rss/tag/'.str_replace(" ", "+", $_GET["tag"]), 'shadows.com', $_GET['tag'], $show_desc);
  			echo '</div>';
					
  			echo '<div id="tgr_delicious" style="display:none;">';
  			tgr_do_rss('http://del.icio.us/rss/tag/'.str_replace(" ", "+", $_GET["tag"]), 'del.icio.us', $_GET['tag'], $show_desc);
  			echo '</div>';
  			
  			echo '<div id="tgr_digg" style="display:none;">';
  			tgr_do_rss('http://digg.com/rss_search?area=all&type=both&age=7&section=news&search=' . str_replace(" ", "+", $_GET["tag"]), 'digg', $_GET['tag'], $show_desc);
  			echo '</div>';

  			echo '<div id="tgr_43things" style="display:none;">';
  			tgr_do_rss('http://www.43things.com/rss/goals/tag?name='.str_replace(" ", "+", $_GET["tag"]), '43things.com', $_GET['tag'], $show_desc);
  			echo '</div>';
  		}
		echo "</div>\n";
		}
	}
}

function tgr_tag_cloud($numtags = 0) {
	global $table_prefix, $wpdb;	
	$tags_post_table_name = $table_prefix.'taggerati_tag_post';
	$tags_table_name = $table_prefix.'taggerati_tags';
	$tagpage = get_option('taggerati_tagpage');
	$sql = 'select '.$tags_post_table_name.'.tag as tag, '.$tags_table_name.'.tag as name, count(*) as number from '.$tags_post_table_name.' inner join '.$tags_table_name.' on '.$tags_post_table_name.'.tag='.$tags_table_name.'.id GROUP BY tag order by number desc';
	$result = $wpdb->get_results($sql, ARRAY_A);
	$currentsize = get_option('taggerati_cloud_maxsize');
	$decrease_size = round($currentsize / 30);
	$cloudarray = array ();
	$tagcount = 0;

	if (sizeof($result) > 0) {
		foreach ($result as $thistag) {
			$tagcount ++;
			$thistag['name'] = strtolower($thistag['name']);

			if ($thistag['number'] <= $currentnum) {
				$currentsize = $currentsize - $decrease_size;
			}

			if ($currentsize < get_option('taggerati_cloud_minsize')) {
				$currentsize = get_option('taggerati_cloud_minsize');
			}

			$cloudarray[$thistag['name']] = '<span style="font-size:'.$currentsize.'%;"><a href="'.$tagpage.$thistag['name'].'">'.$thistag['name'].'</a></span> ';
			$currentnum = $thistag['number'];

			if (($numtags > 0) && (($tagcount -1) >= $numtags)) {
				break;
			}
		}

		$cloudarrray = ksort($cloudarray);
		echo '<div id="taggerati-tagcloud">';

		foreach ($cloudarray as $tag => $thisspan) {
			echo $thisspan;
		}
	} else {
		echo '<p>No tag data available to generate a tag cloud.</p>';
	}
	
	echo '<form id="taggeratiautocomplete" method="get" action="'.get_bloginfo('url').'/tags/">';
	echo "<fieldset><legend>search tags:</legend>";
	echo '<input id="tgTextInput" type="text" name="tag" />';  
	echo '</fieldset>';
	echo '<div id="searchContainer"></div>';
	$sPath = get_bloginfo('url').'/wp-content/plugins/Taggerati/';
	echo "\n\n\n\n\n";
	echo '<script src="'.$sPath.'tagsearchJS.php" type="text/javascript"></script>';
	echo "\n\n\n\n\n";
	echo "</form>\n";
	echo '<p id="tgrfooter"><small>Taggerati for WordPress by <a href="http://www.i-marco.nl/weblog/">Marco van Hylckama Vlieg</a> 
	and <a href="http://elliottback.com/wp/">Elliott Back</a></small></p>';
	echo "</div>";
}

function tgr_options_update() {
	if (isset ($_POST['update'])) {
		update_option('taggerati_tagpage', $_POST["tagpage"]);
		update_option('taggerati_showflickr', $_POST["showflickr"]);
		update_option('taggerati_numflickr', $_POST["numflickr"]);
		update_option('taggerati_autotag', $_POST["autotag"]);
		update_option('taggerati_autotagsearch', $_POST["autotagsearch"]);
		update_option('taggerati_usertag', $_POST["usertag"]);
		update_option('taggerati_listsize', $_POST["listsize"]);
		update_option('taggerati_sortorder', $_POST["sortorder"]);
		update_option('taggerati_flickrsize', $_POST["flickrsize"]);
		update_option('taggerati_showreader', $_POST["showreader"]);
		update_option('taggerati_usereaderajax', $_POST["usereaderajax"]);
		update_option('taggerati_usereaderdescriptions', $_POST["usereaderdescriptions"]);
		update_option('taggerati_cloud_maxsize', $_POST["cloudmaxsize"]);
		update_option('taggerati_cloud_minsize', $_POST["cloudminsize"]);
		echo '<script type="text/javascript">alert(\'Options updated!\');</script>';
	}
}

function tgr_options_editor() {
	tgr_options_update();

	echo '<div class="wrap">';
	echo '<h2>Taggerati Settings</h2>';
	echo '<table cellspacing="3" cellpadding="3">';
	echo '<form action="options-general.php?page=Taggerati/taggerati.php" method="post">';
	echo '<tr><th scope="row" align="right" style="vertical-align:top;width:150px;">Tag Page</th><td>';
	echo '<input type="text" size="100" name="tagpage" value="'.get_option('taggerati_tagpage').'" />';
	echo '<input type="hidden" name="update" value="yes" />';
	echo '<p>You <strong>MUST</strong> create a tag page and enter the full url path here (e.g. \'<strong>/blog/?page_id=5&tag=</strong>\' or if you use non-crufty urls use \'<strong>/blog/tags/</strong>\' ).  Don\'t panic! <a href="../wp-content/plugins/Taggerati/INSTALL.txt">Read the instructions</a> first. You need to do some setup before you can use Taggerati!</p>';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row" style="text-align:right;vertical-align:top;">';
	echo 'Autotagging';
	echo '</th>';

	echo '<td>';
	echo '<input type="checkbox" name="autotag" value="1"' . (get_option('taggerati_autotag') == 1 ? ' checked' : '') . '/>';
	echo '&nbsp;Use Yahoo term extraction to automatically tag posts. (requires DOMXML support in your PHP install)';
	echo '</td>';
	echo '</tr>';
	
	echo '<tr>';
	echo '<td>';
	echo '&nbsp;';
	echo '</td>';
	echo '<td>';
	echo '<input type="checkbox" name="autotagsearch" value="1"' . (get_option('taggerati_autotagsearch') == 1 ? ' checked' : '') . '>&nbsp;Use search engine keywords to tag your posts.';
	echo '</td>';
	echo '</tr>';
	
	echo '<tr>';
	echo '<td>';
	echo '&nbsp;';
	echo '</td>';
	echo '<td>';
	echo '<input type="checkbox" name="usertag" value="1"' . (get_option('taggerati_usertag') == 1 ? ' checked' : '') . '>&nbsp;Allow the collective public web to tag your posts.';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row" style="text-align:right;vertical-align:top;">';
	echo 'Lists size';
	echo '</th>';

	echo '<td>';
	echo 'Show max. ';
	echo '<input type="text" size="3" name="listsize" value="'.get_option('taggerati_listsize').'" />';
	echo ' items in lists showing other posts with a tag. (0 = all posts) with ';
	echo '<select name="sortorder">';
	echo '<option value="desc" ' . (get_option('taggerati_sortorder') == 'desc' ? 'selected' : '') . '>newest first</option>';
	echo '<option value="asc" ' . (get_option('taggerati_sortorder') == 'asc' ? 'selected' : '') . '>oldest first</option>';
	echo '</select>';
	echo '<br /><small>Note: Tag page will still show full lists.</small>';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<th scope="row" style="text-align:right;">';
	echo 'Tag page tweaks';
	echo '</th>';

	echo '<td>';
	echo '<input type="checkbox" name="showflickr" value="1"' . (get_option('taggerati_showflickr') == 1 ? ' checked' : '') . '/>';
	echo '&nbsp;Show <input type="text" name="numflickr" value="' . get_option('taggerati_numflickr') . '" size="3" />';
	echo '<select name="flickrsize">';
	echo '<option value="s"' . (get_option('taggerati_flickrsize') == 's' ? ' selected' : '') . '>small square sized</option>';
	echo '<option value="t"' . (get_option('taggerati_flickrsize') == 't' ? ' selected' : '') . '>thumbnail sized</option>';
	echo '<option value="m"' . (get_option('taggerati_flickrsize') == 'm' ? ' selected' : '') . '>large</option>';
	echo '</select>';
	echo ' Flickr images on tagpage';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<td>';
	echo '&nbsp;';
	echo '</td>';
	echo '<td>';
	echo '<input type="checkbox" name="showreader" value="1"' . (get_option('taggerati_showreader') == 1 ? ' checked' : '') . '>&nbsp;Show feedreader on tagpage';
	echo '</td>';
	echo '</tr>';
	
	echo '<tr>';
	echo '<td>';
	echo '&nbsp;';
	echo '</td>';
	echo '<td>';
	echo '<input type="checkbox" name="usereaderdescriptions" value="1"' . (get_option('taggerati_usereaderdescriptions') == 1 ? ' checked' : '') . '>&nbsp;Show RSS item descriptions';
	echo '</td>';
	echo '</tr>';
	
	echo '<tr>';
	echo '<td>';
	echo '&nbsp;';
	echo '</td>';
	echo '<td>';
	echo '<input type="checkbox" name="usereaderajax" value="1"' . (get_option('taggerati_usereaderajax') == 1 ? ' checked' : '') . '>&nbsp;Use AJAX instead of HTML on tagpage';
	echo '</td>';
	echo '</tr>';
	
	echo '<tr>';
	echo '<th scope="row" style="text-align:right;">';
	echo 'Cloud tweaks';
	echo '</th>';

	echo '<td>';
	echo 'Tag cloud max fontsize: ';
	echo '<input type="text" size="3" name="cloudmaxsize" value="' . get_option('taggerati_cloud_maxsize') . '"/>';
	echo '<strong>%</strong>';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<td>';
	echo '&nbsp;';
	echo '</td>';

	echo '<td>';
	echo 'Tag cloud min fontsize: ';
	echo '<input type="text" size="3" name="cloudminsize" value="' . get_option('taggerati_cloud_minsize') . '"/>';
	echo '<strong>%</strong>';
	echo '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<td>';
	echo '</td>';

	echo '<td>';
	echo '<p class="submit" style="text-align:left;">';
	echo '<input type="submit" value="Save Settings" />';
	echo '</p>';
	echo '</td>';
	echo '</tr>';
	echo '</form>';

	echo '<tr>';
	echo '<td colspan="2">';
	echo '<strong>';
	echo 'WP-Taggerati v1.3';
	echo '</strong>';
	echo ' by <a href="http://www.i-marco.nl/weblog/">Marco van Hylckama Vlieg</a> (<a href="mailto:marco@i-marco.nl">marco@i-marco.nl</a>) - <a href="http://www.i-marco.nl/wp/wordpress/">Marco\'s Wordpress Stuff</a>';
	echo ' and <a href="http://elliottback.com/wp/">Elliott Back</a>';
	echo '</td>';
	echo '</tr>';

	echo '</table>';
	echo '</div>';
}

function tgr_add_options_to_admin() {
	add_options_page('Taggerati', 'Taggerati', 8, __FILE__, 'tgr_options_editor');
}

if (function_exists('add_action')) {
	add_action('admin_menu', 'tgr_add_options_to_admin');
	add_action('shutdown', 'sem_clean_yt_cache');
}

if (isset ($_GET['activate']) && $_GET['activate'] == 'true') {
	add_action('init', 'tgr_install');
}

if (function_exists('add_filter')) {
	add_filter('the_content', 'tgr_format_tags');
}

if (function_exists('add_action')) {
	add_action('publish_post', 'tgr_process_tags', 1);
	add_action('delete_post', 'tgr_delete_aftermath', 1);
	add_action('wp_footer', 'taggerati_search_log');
}

$settings = tgr_getLinkListSettings();
if (function_exists('add_action') && $settings["tgr_showRSSLinkListJS"]) {
	add_action('wp_head', 'tgr_rssLinkList_JS');
}
if(function_exists('add_action')) {
	add_action('wp_head', 'tgr_tagsearchJS');	
}
?>
