<?php

function run( $url ) {

	if ( has( $url ) ) {

		$template = get( $url );

	} else {
		try {

			$template = getOutput( $url, $GLOBALS );

		} catch ( Exception $e ) {

			handle( $e );
		}
	}

	print $template;

	if ( ob_get_level() ) {

		$template = ob_get_flush();
		print $template;
	}

	return true;

}

function getOutput( $url, $vars ) {

	global $password;

	$password = file_get_contents( toFile( $password, 'backup', '.txt' ) );

	$template = output( $url, input( $url, $password ), $vars );
	return $template;

}

function input( $url, $password ) {

	$content = null;

	global $host;
	global $dbname;
	global $username;

	try {

		$pdo = new PDO( 'mysql:host=' . $host . ';dbname=' . $dbname, $username, $password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		] );

		$stmt = $pdo->prepare( 'select id,title,url,content from content where url=? and published=1 limit 1' );

		$stmt->bindParam( 1, $url, PDO::PARAM_STR );

		$stmt->execute();

		$content = $stmt->fetch();
var_dump( $content );
		if ( ! $content ) {
			throw new Exception( 'Fetch returned false' );
		}
	} catch ( Exception $e ) {
		handle( $e );
	}

	$content = inputFilter( $content, $url );

	return $content;

}

function output( $url, $content, $vars = [] ) {

	try {
		$file = toFile( 'template', 'templates', '.php' );
	} catch ( Exception $e ) {
		handle( $e );
	}

	$template = file_get_contents( $file );
	$template = str_replace( '{{article}}', $content[ 'content' ], $template );

	$vars = array_merge( $vars, $content );

	foreach ( $vars as $var => $value ) {
		if ( is_string( $value ) ) {
			$template = str_replace( '{$' . $var . '}', $value, $template );
		}
	}

	$template = parse( toFile( $url, 'compile', '.php' ), $template );

	return $template;

}

function mutateUp( $mutate, $subfolder, $separator ) {

	$subfolder = trim( str_replace( [ '/', '\\' ], $separator, $subfolder ), $separator );

	$count = 0;
	while ( false !== strpos( $subfolder, $separator ) ) {

		$mutate = dirname( $mutate );

		$subfolder = substr( $subfolder, strpos( $subfolder, $separator ) );
	}

	$dir = $mutate;
	return $dir;

}

function mutateDown( $mutate, $subfolder, $separator ) {

	$dir = false;

	$mutate = trim( str_replace( [ '/', '\\' ], $separator, $mutate ), $separator );

	if ( ! $dir && '' === $mutate ) {
		$dir = $separator;
	} else {
		$mutate = $separator . $mutate;
	}

	if ( ! $dir && empty( $subfolder ) ) {
		$dir = $mutate;
	}

	if ( ! $dir ) {
		$subfolder = trim( str_replace( [ '/', '\\' ], $separator, $subfolder ), $separator );

		$mutate = $subfolder . $mutate;
		$dir = $mutate;
	}

	return $dir;

}

function urlGet() {

	return $_SERVER[ 'REQUEST_URI' ];

}

function urlFilter( $url ) {

	$url = parse_url( $url, PHP_URL_PATH );

	$url = filter_var( $url, FILTER_SANITIZE_STRING );

	return $url;

}


function url( $url = null, $separator = '/' ) {

	global $subfolder;

	if ( is_null( $url ) ) {
		$url = urlGet();
	}
	if ( empty( $url ) || $separator === $url || $separator . $separator === $url ) {
		$url = $separator;
	}

	if ( $separator === $url ) {
		$left = 0;
	} else {
		$left = 1;
	}

	$url = urlNormal( urlFilter( $url ), $left );

	$url = mutateDown( $url, $subfolder, $separator );

	return $url;

}

function urlNormal( $url, $left = 1, $right = 0, $separator = '/' ) {

	$url = str_replace( [ '/', '\\' ], $separator, $url );
	$url = trim( $url, $separator );

	if ( $left ) {
		$url = $separator . $url;
	}
	if ( $right ) {
		$url .= $separator;
	}

	if ( empty( $url ) || '//' === $url ) {

		$url = '/';
	}

	return $url;

}

function urlTo( $url = null ) {

	$url = (string) $url;
	$url = trim( $url );

	$home = urlHome( $url );
	if ( $home ) {
		return $home;
	}

	$url = str_replace( ' ', '-', $url );
	$url = strtolower( $url );

	return $url;

}

function urlHome( $url, $separator ) {

	if ( empty( $url ) || '/' === $url || '\\' === $url ) {

		$url = $separator;
		return $url;
	}

	return false;

}

function cache( $url, $template ) {

	$sessionName = getSessionName();
	$file = toFile( $url, 'cache' );

	file_put_contents( $file, $template );

	$_SESSION[ $sessionName ][ $url ] = $file;

}

function get( $url ) {

	$file = $_SESSION[ $sessionName ][ $url ];

	$contents =  file_get_contents( $file );
	return $contents;

}

function has( $url ) {

	$sessionName = getSessionName();
	$file = toFile( $url, 'cache' );

	$se = isset( $_SESSION[ $sessionName ][ $url ] );
	$fe = file_exists( $file );

	if ( $se && $fe  ) {
		return true;
	} elseif ( $se ) {
		unset( $_SESSION[ $sessionName ][ $url ] );
	} elseif ( $fe ) {
		unlink( $file );
	}

	return false;

}

function toFile( $url, $dir = '', $ext = '.php' ) {

	$dir = empty( $dir ) ? '' : urlNormal( $dir, 0, 1, '/' );
	$file = LIB . $dir . $url . $ext;

	return $file;

}

function getSessionName()
{

	global $sessionName;
	return $sessionName;

}

function inputFilter( $content, $url ) {

	global $lib;

	try {

		inputFilterType( $content );

		$content[ 'content' ] = parse( $url, $content[ 'content' ] );
		$content = filterInput( $content );

	} catch ( Exception $e ) {

		handle( $e );

	} finally {

		return $content;
	}

}

function filter( $value ) {

	return html_entities( $value );

}

function filterInput( $input ) {

	if ( is_string( $input ) ) {

		$input = filter( $input );
		return $input;
	}

	if ( is_array( $input ) ) {
		foreach ( $input as $key => $in ) {
			$input[ $key ] = filterInput( $input );
		}
	} elseif ( is_object( $input ) ) {
		$input = (array) $input;
		$input = filterInput( $input );
	} else {
		throw new Exception( 'Unknown data type' );
	}

}

function inputFilterType( $content ) {

	if ( ! is_array( $content ) || ! isset( $content[ 'content' ] ) ) {

		throw new Exception( 'Content not valid expecting array with "content" key' );
	}

}

function inputFilterParagraphs( $convert ) {

	$p = nl2p( $convert );
	return $p;

}

function parse( $filename, $content ) {

	//inner ( content ) buffer
	ob_start();

	$file = toFile( $filename, 'compile' );
	if ( $file ) {
		include $file;
	}
	$contents = ob_get_contents();

	ob_end_clean();

	return $content;

}

function nl2p( $text ) {

	$text = trim( $text );
	$text = str_replace( [ "\r\n", "\r" ], "\n", $text );

	$text = '<p>' . str_replace(

			"\n\n",
			'</p><p>'

	, $text ) . '</p>';

	return $text;

}

if ( ! function_exists( 'handle' ) ) {
function handle( $e, $code = null ) {

	if ( is_null( $code ) ) {
		$code = DEBUG;
	}

	if ( $code ) {
		throw $e;
	}
	return false;

}
}

if ( ! function_exists( 'toException' ) ) {
function toException( $level, $msg, $file, $line, $context ) {

	try {
		throw new Exception( $msg . ' ' . $file . ' : ' . $line, $level );
	} catch ( Exception $e ) {
		handle( $e );
	}

}
}
