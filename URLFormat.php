<?php

class URLFormat {

	/**
	 * Register parser hook
	 *
	 * @param $parser Parser
	 * @return bool
	 */
	public static function URLFormat( &$parser ) {
		$parser->setHook( 'urlformat', __CLASS__.'::parserHook' );
		return true;
	}
	 static function parserHook( $parser ) {
		global $wgURLFormat

		function URLFormat( &$parser, $text = '' ) {

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
	
	return $text;
		}
	}
}
