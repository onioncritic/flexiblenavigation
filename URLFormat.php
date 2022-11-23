<?php

   /*
	*  URLFormat.php © 2021 by Ben White
	*
	*  This program is free software; you can redistribute it or modify it.
    *
	*  This program is distributed in the hope that it will be useful, but
	*  WITHOUT ANY WARRANTY; without even the implied warranty of
    *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
    *
    *  This program works in MediaWiki 1.15.4. Although it should work in at
    *  least some other versions of MediaWiki, no guarantee is made to that
    *  effect. It especially has NOT been tested in the newest versions of
    *  MediaWiki.
    *
    */

   /*
	*  This program creates a parser function with the magic word 'urlformat'.
	*  The first few statements and the first and last functions register
	*  the other functions and the magic word with MediaWiki.
	*
	*  NOTE: Save this file in your ./extensions/hooks/ directory and place
	*  the following line in LocalSettings.php:

require_once('extensions/hooks/URLFormat.php');

	*
    */

$wgExtensionFunctions[] = 'pfURLFormat';
 
$wgHooks['LanguageGetMagic'][] = 'fnURLFormatGetMagic';
 
function pfURLFormat() {
	global $wgParser;
 
	$wgParser->setFunctionHook('urlformat', 'fnURLFormat');
}

function fnURLFormat( &$parser, $text = '' ) {

	// Validate title
	if ( $text === '' ) {
		global $wgTitle;
		if ( !is_object( $wgTitle ) ) return "NO TITLE OBJECT";
		$text = $wgTitle->getPrefixedText();
	}

	// Convert all white space to spaces, remove (toon) at the end of a title,
	// convert all to lowercase, remove all nonalphanumeric/nonspace characters,
	// and then replace all remaining spaces with hyphens
	$text = preg_replace( '/\s+/', ' ', trim( $text ) );
	$text = preg_replace( '/ \(toon\)$/', '', $text );
	$text = strtolower( $text );
	$text = preg_replace( '/[^\w ]/', '', $text );
	$text = preg_replace( '/[ _]+/', '-', $text );
	
	// Special case
	if ( $text == "bottom-10" ) $text .= '-';
	
	return $text;
}

function fnURLFormatGetMagic( &$magicWords, $langCode ) {
	$magicWords['urlformat'] = array( 0, 'urlformat' );
	return true;
}

   /*
	*  DOCUMENTATION AND EXAMPLES
	*

{{#urlformat:}} is a parser function that formats text to be used in a link to the official site.

	┌───────────────────────────┐
	│                           │
	│  {{#urlformat: <text> }}  │
	│                           │
	└───────────────────────────┘

* text — Optional (default: the name of the page on which the parser function [or a containing template] is placed). 

The function makes the following conversions (in order):

* All multiple space is collapsed to single spaces and trimmed off the ends.
* The disambiguation "(toon)" is ignored at the end of the text.
* The text is converted to lowercase.
* Anything that is not a letter, number, underscore, or space is removed.
* Spaces and underscores are converted to hyphens. (Consecutive spaces or underscores are converted to a single hyphen.) 

The function currently has one special case: {{#urlformat:bottom 10}} returns bottom-10- (with a hyphen at the end) 
because the official site erroneously includes one in the URL.

This function was built to accommodate [[Strong Bad Emails]] but may be useful for other toons too. To make a valid
link, other elements such a path or a number may be necessary.

Examples
--------

{{#urlformat: some kinda robot}}         :  some-kinda-robot
{{#urlformat: bottom 10}}                :  bottom-10- (see special case above)
{{#urlformat: myths & legends}}          :  myths-legends
{{#urlformat: Cheat Commandos (toon) }}  :  cheat-commandos
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#urlformat:}}  :  some-kinda-robot if the parser function (or a containing template) is placed on the [[some kinda robot]] page
{{#urlformat:}}  :  bottom-10- if the parser function (or a containing template) is placed on the [[bottom 10]] page
{{#urlformat:}}  :  myths-legends if the parser function (or a containing template) is placed on the [[myths & legends]] page
{{#urlformat:}}  :  cheat-commandos if the parser function (or a containing template) is placed on the [[Cheat Commandos (toon)]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
[[HR:sbemails/1-{{#urlformat: some kinda robot }}]]   :  [[HR:sbemails/1-some-kinda-robot]]
[[HR:sbemails/50-{{#urlformat: {{sbemail|50} }}}]]    :  [[HR:sbemails/50-50-emails]]
[[HR:sbemails/145-{{#urlformat: myths & legends }}]]  :  [[HR:sbemails/145-myths-legends]]
[[HR:toons/{{#urlformat: Cheat Commandos (toon) }}]]  :  [[HR:toons/cheat-commandos]]
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
[[HR:sbemails/1-{{#urlformat:}}]]    :  [[HR:sbemails/1-some-kinda-robot]] if the parser function (or a containing template) is placed on the some kinda robot page
[[HR:sbemails/50-{{#urlformat:}}]]   :  [[HR:sbemails/50-50-emails]] if the parser function (or a containing template) is placed on the 50 emails page
[[HR:sbemails/145-{{#urlformat:}}]]  :  [[HR:sbemails/145-myths-legends]] if the parser function (or a containing template) is placed on the myths & legends page
[[HR:toons/{{#urlformat:}}]]         :  [[HR:toons/cheat-commandos]] if the parser function (or a containing template) is placed on the Cheat Commandos (toon) page

    *
    */
