<?php

session_start();
ob_start();

/**
 * Your vars
 */

if ( ! defined( 'DEBUG' ) ) define( 'DEBUG', true );

$cacheflag = false;
$sessionName = 'nathanwootendotnet';
$local = 'two.localhost';
$subfolder = '';
$host = 'localhost';
$dbname = 'site';
$username = 'nathanwooten';

$env = ( $local === $_SERVER[ 'SERVER_NAME' ] ? 0 : 1 );

/**
 * Global functions
 */

require_once dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'functions.php';

/**
 * Settings
 */

$dir = dirname( __FILE__ );

if ( ! defined( 'ROOT' ) ) define( 'ROOT', mutateUp( dirname( $dir ), $subfolder, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR );
if ( ! defined( 'LIB' ) ) define( 'LIB', ROOT . 'vendor' . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR );

/**
 * Error Handling
 */

ini_set( 'display_errors', ( DEBUG ? 1 : 0 ) );
set_error_handler( 'toException' );

/**
 * App
 */

run( url() );
