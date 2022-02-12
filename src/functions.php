<?php

function run( $url ) {

	$url = url( $url );

	if ( has( $url ) ) {

		$template = get( $url );

	} else {
		try {

			$template = output( $url, input( $url, file_get_contents( toFile( 'logo', 'backup', '.txt' ) ) ), $GLOBALS );

		} catch ( Exception $e ) {

			handle( $e );
		}
	}

	print $template;
	$template = ob_get_flush();
	print $template;

	return true;

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
	foreach ( array_merge( $vars, $content ) as $var => $value ) {

		$template = str_replace( '{$' . $var . '}', $value, $template );
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

	$subfolder = trim( str_replace( [ '/', '\\' ], $separator, $subfolder ), $separator );

	$mutate = $subfolder . $separator . trim( str_replace( [ '/', '\\' ], $separator, $mutate ), $separator ) . $separator;

	$dir = $mutate;
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

	$url = urlNormal( urlFilter( $url ? $url : urlGet() ) );

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

	return $url;

}

function toSlug( $url ) {

	$url = trim( $url );

	if ( '' === $url || '/' === $url ) {
		$url = 'home';
	}

	$url = str_replace( ' ', '-', $url );
	$url = strtolower( $url );

	return $url;

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
