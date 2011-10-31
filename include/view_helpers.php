<?php

/**
 * Escapes the specified text so it can be safely inserted as HTML tag content.
 * It's UTF-8 safe.
 * 
 * Since this function is made for HTML content it does not escape double
 * quotes ("). If you want to insert something as an attribute value use the
 * ha() function.
 * 
 * This is a shortcut mimicing the Ruby on Rails "h" helper.
 */
function h($text_to_escape){
	return htmlspecialchars($text_to_escape, ENT_NOQUOTES, 'UTF-8');
}

/**
 * Escapes the specified text so it can be safely inserted into an HTML attribute.
 * It's UTF-8 safe.
 * 
 * This is a shortcut mimicing the Ruby on Rails "h" helper.
 */
function ha($text_to_escape){
	return htmlspecialchars($text_to_escape, ENT_QUOTES, 'UTF-8');
}

/**
 * General localization helper to lookup entries from a language file. The argument list
 * is processed by the function itself.
 * 
 * Some things to keep in mind:
 * - If the key resolves to something else than a string in the language file (e.g. an array) the
 *   data from the language file is returned as it is. No `printf` string substitution is performed.
 *   This allows you to fetch arrays directly from the language file (used for the list of suggestions
 *   shown on error pages.
 * - If no format arguments are specified even strings are returned without processing. This way
 *   you can fetch the raw strings out of a language file. This is used to pass them to JavaScript.
 */
function l(){
	global $_LOCALE;
	
	$entry = $_LOCALE;
	$args = func_get_args();
	
	for($i = 0; $i < count($args); $i++){
		$key = $args[$i];
		if ( array_key_exists($key, $entry) ) {
			$entry = $entry[$key];
			if ( ! is_array($entry) )
				break;
		} else {
			$entry = 'Missing entry in language file: ' . join(' → ', array_slice($args, 0, $i + 1));
			break;
		}
	}
	
	$format_args = array_slice($args, $i + 1);
	return (is_string($entry) and count($format_args) > 0) ? vsprintf($entry, $format_args) : $entry;
}

/**
 * A small helper function that pipes the output of `l()` though `h()`. This makes sure the
 * output can be safely inserted as HTML tag content.
 */
function lh(){
	return h( call_user_func_array('l', func_get_args()) );
}

/**
 * A small helper function that pipes the output of `l()` though `ha()`. This makes sure the
 * output can be safely inserted as an HTML attribute value.
 */
function lha(){
	return ha( call_user_func_array('l', func_get_args()) );
}


/**
 * Converts the specified number of bytes into a more human readable format like KiByte
 * or MiByte. The function name is inspired by Rails but the implementation is was written
 * completely from scratch.
 */
function number_to_human_size($bytes){
	$border = 1024 * 1.5;
	if ($bytes < $border)
		return sprintf('%u Byte', $bytes);
	
	$bytes /= 1024;
	if ($bytes < $border)
		return sprintf('%u KiByte', $bytes);
	
	$bytes /= 1024;
	return sprintf('%.1f MiByte', $bytes);
}

?>