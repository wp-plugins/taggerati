<?php
/* This is a version of Extract Terms for Taggerrati */

/*
 * Terms of use
 * ------------
 * Except where otherwise noted, this software is:
 * - Copyright 2005, Denis de Bernardy
 * - Licensed under the terms of the CC/GNU GPL
 *   http://creativecommons.org/licenses/GPL/2.0/
 * - Provided as is, with NO WARRANTY whatsoever
**/

// cache dir must be writable by the server
$YT_CACHE_PATH = ABSPATH . 'wp-content/plugins/Taggerati/rssCache/';

// cache timeout in seconds (default: one week)
$YT_CACHE_TIMEOUT = 60 * 60 * 24 * 7;

/*
 * sem_extract_terms()
 * -------------------
 * retrieves a post's Yahoo! terms
**/

function sem_extract_terms($context = null) {
	global $YT_CACHE_PATH, $YT_CACHE_TIMEOUT;
	
	if (empty($context))
		return;

	// Clean up context
	$context = preg_replace( "/\r/", "", $context );
	$context =  trim( strip_tags( $context ) );

	$vars = array(
		"appid" => "WordPress/Extract Terms Plugin (http://www.semiologic.com)",
		"context" => $context,
		"query" => $query);


	// Generate cache file name
	$cache_file = $YT_CACHE_PATH . "yt-". md5( $context );
	clearstatcache(); // reset file cache status, in case of multiple calls

	// Retrieve and cache the results if they do not exist
	if(@file_exists($cache_file) && (@filemtime($cache_file) + $YT_CACHE_TIMEOUT) > time()){
		$xml = file_get_contents( $cache_file );
	} else {
		// Process content
		foreach($vars as $key => $value){
			$content .= urlencode($key) ."=". urlencode($value) . ((++$i < sizeof($vars)) ? "&" : "");
		}
		
		$content_length = strlen($content);
	
		// Build header
		$headers = "POST /ContentAnalysisService/V1/termExtraction HTTP/1.1
Accept: */*
Content-Type: application/x-www-form-urlencoded; charset=". get_settings('blog_charset') ."
User-Agent: WordPress/Extract Terms Plugin (http://www.semiologic.com)
Host: api.search.yahoo.com
Connection: Keep-Alive
Cache-Control: no-cache
Content-Length: ". $content_length ."

";
	
		// Open socket connection
		$fp = fsockopen( "api.search.yahoo.com", 80 );
		
		// Discard the call if it times out
		if ( !$fp )
			return;
	
		// Send headers and content
		fputs($fp, $headers);
		fputs($fp, $content);
	
		// Retrieve the result
		$xml = '';
		
		while(!feof($fp)){
			$xml .= fgets( $fp, 1024 );
		}
		
		fclose( $fp );
    
		// Clean up the result
		$xml = preg_replace("/^[^<]*|[^>]*$/", "", $xml);
	
		// Cache the result
		$res_file = fopen($cache_file, "w+"); 
		fwrite($res_file, $xml);
		fclose($res_file);
	}

	// Parse the XML

	if($xml)
		$dom = domxml_open_mem($xml);


	// Bypass the call if there is nothing to fetch
	if(!$dom)
		return;

	// Traverse the dom and fetch the terms
	$terms = array();

	$root = $dom->document_element();
	$node = $root->first_child();

	if($node->tagname == 'Result'){ // don't process errors
		while($node){
			$terms[] = $node->get_content();
			$node = $node->next_sibling();
		}
	}
	
	return $terms;
}

/*
 * sem_clean_yt_cache()
 * --------------------
 * cleans up yt cache files
**/

function sem_clean_yt_cache() {
	global $YT_CACHE_PATH, $YT_CACHE_TIMEOUT;

	if((get_option('sem_clean_yt_cache') + $YT_CACHE_TIMEOUT) < time()){
		foreach(glob($YT_CACHE_PATH . "yt-*") as $cache_file){
			if((filemtime($cache_file) + $YT_CACHE_TIMEOUT) < time()){
				unlink($cache_file);
			}
		}
	
		update_option('sem_clean_yt_cache', time());
	}
}

?>