<?php

   /*
	*  NavListLookup.php © 2021 by Ben White
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
	*  This program creates a parser function with the magic word 'navlist'.
	*  The first few statements and the first and last functions register
	*  the other functions and the magic word with MediaWiki.
	*
	*  NOTE: Save this file in your ./extensions/hooks/ directory and place
	*  the following line in LocalSettings.php:

require_once('extensions/hooks/NavListLookup.php');

	*
    */

$wgExtensionFunctions[] = 'pfNavListLookup';

$wgHooks['LanguageGetMagic'][] = 'fnNavListLookupGetMagic';

function pfNavListLookup() {
	global $wgParser;

	$wgParser->setFunctionHook('navlist', 'fnNavListLookup');
}

function fnNavListLookup( &$parser, $templatename = '', $action = '', $lookup = '', $flag = '' ) {

	// Validate page title from given template name
	$title = Title::newFromText( $templatename, NS_TEMPLATE );
	if ( $title === null ) return "INVALID TEMPLATE PARAMETER";

	// Get template contents
	$article = new Article( $title, 0 );
	if ( !$article->exists() ) return "INVALID TEMPLATE NAME";
	$article->loadContent();

	// Strip out <noinclude> sections, <includeonly> and <onlyinclude> tags, and HTML comments
	$text = $article->getRawText();
	$text = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $text );
	$text = strtr( $text, array( '<includeonly>' => '', '</includeonly>' => '') );
	$text = strtr( $text, array( '<onlyinclude>' => '', '</onlyinclude>' => '') );
	$text = Sanitizer::removeHTMLcomments( $text );

	// Match all list items, accounting for optional page pipes
	preg_match_all( '/(?<=^|\n)\*([^|\n]+)(?:\|(.+))?(?:\n|$)/', $text, $matches );

	// Validate all list page titles
	for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
		$matches[1][$i] = fnNavListLookupVerifyTitle( $matches[1][$i] );
		if ( $matches[1][$i] === "INVALID LIST ITEM" ) $matches[2][$i] = '';
		$matches[2][$i] = trim( $matches[2][$i] );
	}

	// Refer to the documentation and examples at the bottom of this file
	// for assistance deciphering the core functionality
	$list = $matches[1];
	$find = array_map( 'strtolower', $list );
	$pipe = $matches[2];
	$size = count( $list );

	if ( $size == 0 ) return "INVALID LIST";

	if ( $action == 'size' ) {
		return $size;
	} elseif ( $action == 'first' || ( $action == '#' && $lookup == 'first' ) ) {
		$key = 0;
	} elseif ( $action == 'last' || ( $action == '#' && $lookup == 'last' ) ) {
		$key = $size - 1;
	} elseif ( $action == '#' ) {
		if ( !is_numeric( $lookup ) ) return "INVALID INDEX";

		$lookup = intval( $lookup );
		if ( abs( $lookup ) > 1000 ) return "INDEX OUT OF RANGE";

		$key = $lookup - 1;
	} else {
		if ( $lookup === '' ) {
			global $wgTitle;
			if ( !is_object( $wgTitle ) ) return "NO TITLE OBJECT";
			$lookup = $wgTitle->getPrefixedText();
		}
		$lookup = fnNavListLookupVerifyTitle( $lookup );
		$lookup = strtolower( $lookup );
		if ( $lookup === "invalid list item" /* lowercase */ || !in_array( $lookup, $find ) ) return "INVALID LOOKUP VALUE";

		if ( $action === '' ) {
			$offset = 0;
		} elseif ( $action == 'next' ) {
			$offset = 1;
		} elseif ( $action == 'prev' ) {
			$offset = -1;
		} else {
			if ( !is_numeric( $action ) ) return "INVALID ACTION";

			// Arbitrarily set maximum range
			$offset = intval( $action );
			if ( abs( $offset ) > 1000 ) return "ACTION OFFSET OUT OF RANGE";
		}

		$key = array_search( $lookup, $find ) + $offset;
	}

	$key %= $size;
	if ( $key < 0 ) $key += $size;

	if ( $flag == '#' ) {
		$ret = $key + 1;
	} elseif ( $pipe[$key] === '' || $flag == 'target' ) {
		$ret = $list[$key];
	} elseif ( $flag == 'pipe' ) {
		$ret = "$list[$key]|$pipe[$key]";
	} else {
		$ret = $pipe[$key];
	}

	return $ret;
}

function fnNavListLookupVerifyTitle( $text ) {
	$title = Title::newFromText( $text );
	return ( $title !== null ) ? $title->getText() : "INVALID LIST ITEM";
}

function fnNavListLookupGetMagic( &$magicWords, $langCode ) {
	$magicWords['navlist'] = array( 0, 'navlist' );
	return true;
}

   /*
	*  DOCUMENTATION AND EXAMPLES
	*

{{#navlist:}} looks up a value from a list on a template and returns a value from the list, usually the next
or previous value in the list, for use in navigation templates.

	┌───────────────────────────────────────────────────────────────────────┐
	│                                                                       │
	│  {{#navlist: <template name> | <action> | <lookup value> | <flag> }}  │
	│                                                                       │
	└───────────────────────────────────────────────────────────────────────┘

* template name — Must be a name of a valid wiki template (Template: prefix not necessary). A malformed
  template name or nonexistent template will cause an error.
* action — Optional (default: 0). Can be next, prev, first, last, size, an integer, or # (literal number sign).
* lookup value (action not #) — Not case sensitive; optional (default: the name of the page on which the parser
  function [or a containing template] is placed). If specified, must be a valid list item. If not specified,
  the name of the page on which the parser function (or a containing template) is placed must be a valid list item.
* lookup value (action #) — Must be an integer.
* flag — Optional (default: no flag). Can be target, pipe, or # (literal number sign).

Action
------
* next or 1 or +1 returns the value in the list immediately after the lookup value. If there is no value after
  the lookup value, it returns the first value in the list.
* prev or -1 returns the value in the list immediately before the lookup value. If there is no value before the
  lookup value, it returns the last value in the list.
* first returns the first value in the list. In this case the lookup value has no effect.
* last returns the last value in the list. In this case the lookup value has no effect.
* size returns the total number of items in the list. In this case the lookup value and flags have no effect.
* n or +n returns the nth item after the lookup value (converted to an integer, limit: 1000). If it reaches the
  end of the list, it will wrap around to the beginning.
* -n returns the nth item before the lookup value (converted to an integer, limit: -1000). If it reaches the
  beginning of the list, it will wrap around to the end.
* 0 or a numeric value that converts to 0 returns the lookup value itself.
* # (literal number sign) with required lookup value integer n returns the nth item in the list (converted to an
  integer, limit: ±1000). Lookup value 0 returns the last item in the list. The list repeats backward and forward
  to accommodate a negative lookup value or a lookup value greater than the size of the list (lookup value -1 returns
  the next-to-last item in the list; lookup value <total size + 1> returns the first item in the list; etc.).
* An invalid action or a numeric value that's out of range returns an error.

Flag
----
Refer to the template setup below.

* # (literal number sign) returns the order in the list (index) of the lookup value.
* Otherwise, if no pipe or no return value, then the flags below have no effect. The function returns the page name.
* A pipe with a return value and:
  * no flag (or unrecognized flag) returns the return value.
  * target returns the page name.
  * pipe returns "Page name|Return value".

Template setup
--------------
The template should consist of a simple bulleted list. A template without a list will cause an error. Each item in
the list must potentially be a valid wiki title (although it doesn't necessarily have to be a valid wiki page).
Do not use brackets. Any invalid characters in the page name portion of a list item will cause an error.

A pipe can be used to return a value other than the page name.

The template can have other paragraphs or explanatory text. Only list items (wherever they appear outside <noinclude>
sections) are parsed.

	┌──────────────────────────────┐
	│                              │
	│  * List of                   │
	│  * Page                      │
	│  * Names                     │
	│  * Page name | Return value  │
	│                              │
	│  <noinclude>                 │
	│                              │
	│  Explanatory text.           │
	│                              │
	│  * This list                 │
	│  * Will NOT be included      │
	│                              │
	│  </noinclude>                │
	│                              │
	└──────────────────────────────┘

Examples
--------

The {{charnav-lookup}} template contains the following list:

	┌──────────────────────────────┐
	│                              │
	│  * Homestar Runner           │
	│  * Strong Bad                │
	│  * The Cheat                 │
	│  * Strong Mad                │
	│  * Strong Sad                │
	│  * Pom Pom                   │
	│  * Marzipan                  │
	│  * Coach Z                   │
	│  * Bubs                      │
	│  * The King of Town          │
	│  * The Poopsmith             │
	│  * Homsar                    │
	│                              │
	└──────────────────────────────┘

{{#navlist: charnav-lookup | next | Homestar Runner }}  :  Strong Bad
{{#navlist: charnav-lookup | next }}                    :  Strong Bad if the parser function (or a containing template) is placed on the [[Homestar Runner]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | prev | Strong Bad }}  :  Homestar Runner
{{#navlist: charnav-lookup | prev }}               :  Homestar Runner if the parser function (or a containing template) is placed on the [[Strong Bad]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | next | Homsar }}  :  Homestar Runner
{{#navlist: charnav-lookup | next }}           :  Homestar Runner if the parser function (or a containing template) is placed on the [[Homsar]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | prev | Homestar Runner }}  :  Homsar
{{#navlist: charnav-lookup | prev }}                    :  Homsar if the parser function (or a containing template) is placed on the [[Homestar Runner]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | +2 | Homestar Runner }}   :  The Cheat
{{#navlist: charnav-lookup | 2.9 | Homestar Runner }}  :  The Cheat
{{#navlist: charnav-lookup | -2 | Homestar Runner }}   :  The Poopsmith
{{#navlist: charnav-lookup | 954 | Homestar Runner }}  :  Marzipan
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | 0 | Homestar Runner }}  :  Homestar Runner
{{#navlist: charnav-lookup | | Homestar Runner }}    :  Homestar Runner
{{#navlist: charnav-lookup }}                        :  Homestar Runner if the parser function (or a containing template) is placed on the [[Homestar Runner]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: Template:charnav-lookup | next | Homestar Runner }}  :  Strong Bad (Using the Template: prefix is valid)
{{#navlist: not-a-real-tempate | next | Homestar Runner }}       :  INVALID TEMPLATE NAME
{{#navlist: charnav | next | Homestar Runner }}                  :  INVALID LIST (Note that {{charnav}} is a valid template but doesn't contain a list)
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | invalid | Homestar Runner }}  :  INVALID ACTION
{{#navlist: charnav-lookup | 10000 | Homestar Runner }}    :  ACTION OFFSET OUT OF RANGE
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | next | Not in the List }}  :  INVALID LOOKUP VALUE
{{#navlist: charnav-lookup | next }}                    :  INVALID LOOKUP VALUE if the parser function (or a containing template) is not placed on a page in the list
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | first }}      :  Homestar Runner
{{#navlist: charnav-lookup | # | first }}  :  Homestar Runner
{{#navlist: charnav-lookup | # | 1 }}      :  Homestar Runner
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | last }}      :  Homsar
{{#navlist: charnav-lookup | # | last }}  :  Homsar
{{#navlist: charnav-lookup | # | 0 }}     :  Homsar
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | # | 5 }}     :  Strong Sad
{{#navlist: charnav-lookup | # | -5 }}    :  Marzipan
{{#navlist: charnav-lookup | # | 500 }}   :  Coach Z
{{#navlist: charnav-lookup | # | 5000 }}  :  INDEX OUT OF RANGE
{{#navlist: charnav-lookup | # | five }}  :  INVALID INDEX
{{#navlist: charnav-lookup | # }}         :  INVALID INDEX
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | 0 | Homestar Runner | # }}  :  1
{{#navlist: charnav-lookup | | Homestar Runner | # }}    :  1
{{#navlist: charnav-lookup | | | # }}                    :  1 if the parser function (or a containing template) is placed on the [[Homestar Runner]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | next | Homestar Runner | # }}  :  2
{{#navlist: charnav-lookup | next | | # }}                  :  2 if the parser function (or a containing template) is placed on the [[Homestar Runner]] page
{{#navlist: charnav-lookup | prev | Homestar Runner | # }}  :  12
{{#navlist: charnav-lookup | prev | | # }}                  :  12 if the parser function (or a containing template) is placed on the [[Homestar Runner]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | | Pom Pom | # }}           :  6
{{#navlist: charnav-lookup | | the king of town | # }}  :  10
{{#navlist: charnav-lookup | | Not in the List | # }}   :  INVALID LOOKUP VALUE
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: charnav-lookup | size }}  :  12

───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────

The {{hrenav-lookup}} template contains the following list:

	┌─────────────────────────────────┐
	│                                 │
	│  * Hremail 49                   │
	│  * Hremail 24                   │
	│  * Hremail 62                   │
	│  * Hremail 2000                 │
	│  * Hremail 7                    │
	│  * hremail 3184 | Hremail 3184  │
	│                                 │
	└─────────────────────────────────┘

{{#navlist: hrenav-lookup | prev | Hremail 49 }}           :  Hremail 3184
{{#navlist: hrenav-lookup | prev | Hremail 49 | target }}  :  hremail 3184
{{#navlist: hrenav-lookup | prev | Hremail 49 | pipe }}    :  hremail 3184|Hremail 3184
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
[[{{#navlist: hrenav-lookup | prev | Hremail 49 | pipe }}]]  :  [[hremail 3184|Hremail 3184]], which generates the piped link that appears [[Hremail 3184]]
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: hrenav-lookup | prev | }}           :  Hremail 3184 if the parser function (or a containing template) is placed on the [[Hremail 49]] page
{{#navlist: hrenav-lookup | prev | | target }}  :  hremail 3184 if the parser function (or a containing template) is placed on the [[Hremail 49]] page
{{#navlist: hrenav-lookup | prev | | pipe }}    :  hremail 3184|Hremail 3184 if the parser function (or a containing template) is placed on the [[Hremail 49]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: hrenav-lookup | next | Hremail 49 }}           :  Hremail 24
{{#navlist: hrenav-lookup | next | Hremail 49 | target }}  :  Hremail 24
{{#navlist: hrenav-lookup | next | Hremail 49 | pipe }}    :  Hremail 24
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: hrenav-lookup | next | }}           :  Hremail 24 if the parser function (or a containing template) is placed on the [[Hremail 49]] page
{{#navlist: hrenav-lookup | next | | target }}  :  Hremail 24 if the parser function (or a containing template) is placed on the [[Hremail 49]] page
{{#navlist: hrenav-lookup | next | | pipe }}    :  Hremail 24 if the parser function (or a containing template) is placed on the [[Hremail 49]] page
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
[[{{#navlist: hrenav-lookup | next | Hremail 49 | pipe }}]]  :  [[Hremail 24]], which generates the regular link [[Hremail 24]] because there is no pipe
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
{{#navlist: hrenav-lookup | # | last }}        :  Hremail 3184
{{#navlist: hrenav-lookup | # | 6 }}           :  Hremail 3184
{{#navlist: hrenav-lookup | # | 6 | target }}  :  hremail 3184
{{#navlist: hrenav-lookup | # | 6 | pipe }}    :  hremail 3184|Hremail 3184
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------
[[{{#navlist: hrenav-lookup | # | 6 | pipe }}]]  :  [[hremail 3184|Hremail 3184]], which generates the piped link that appears [[Hremail 3184]]

    *
    */
