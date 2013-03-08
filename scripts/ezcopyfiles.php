<?php
//
// Definition of command line file copy script
//
// Created on: <31-Jan-2009 11:19:38 vd>
//
// Copyright (C) 2002-2008 Nexus Consulting as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE included in
// the packaging of this file.
//
// Licencees holding a valid "eZ publish professional licence" version 2
// may use this file in accordance with the "eZ publish professional licence"
// version 2 Agreement provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" version 2 is available at
// http://ez.no/ez_publish/licences/professional/ and in the file
// PROFESSIONAL_LICENCE included in the packaging of this file.
// For pricing of this licence please contact us via e-mail to licence@ez.no.
// Further contact information is available at http://ez.no/company/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/**
 * @file ezcopyfiles.php
 */

/**
 * @brief Copy files between two var folders
 * @version 0.1
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'debug-message' => '',
                                     'use-session' => true,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );

$script->startup();

$options = $script->getOptions( "[db-user:][db-password:][db-name:][src-var:][dst-var:]",
                                "",
                                array( 'db-user'             => 'Database user.',
                                       'db-password'         => 'Database password.',
                                       'db-name'             => 'Database name.',
                                       'src-var'             => 'Source var folder with \'storage\' sub folder.',
                                       'dst-var'             => 'Destination var folder.' ) );
$script->initialize();

$storage     = 'storage';
$dbUser      = $options['db-user']     ? $options['db-user']     : 'root';
$dbPassword  = $options['db-password'] ? $options['db-password'] : '';
$dbName      = $options['db-name']     ? $options['db-name']     : false;
$srcVar      = $options['src-var']     ? $options['src-var']     : false;
$dstVar      = $options['dst-var']     ? $options['dst-var']     : false;

/***************************
 *        CHECKS           *
 ***************************/

if ( !$dbName )
{
    $cli->error( "Database name is not set.\n"  );
    $script->showHelp();
    $script->shutdown( 1 );
}

if ( !$srcVar )
{
    $cli->error( "Source var directory is not set.\n"  );
    $script->showHelp();
    $script->shutdown( 1 );
}

if ( !$dstVar )
{
    $cli->error( "Destination var directory is not set.\n"  );
    $script->showHelp();
    $script->shutdown( 1 );
}

$srcStorageDir = eZDir::path( array( $srcVar, $storage ) );
$dstStorageDir = eZDir::path( array( $dstVar, $storage ) );

if ( !file_exists( $srcStorageDir ) ) 
{
    $cli->error( "$srcStorageDir folder doesn't exist!"  );
    $script->shutdown( 1 );
}

// Make sure that result path exists
eZDir::mkdir( $dstStorageDir, false, true );

$databaseParameters = array( 'user'     => $dbUser,
                             'password' => $dbPassword,
                             'database' => $dbName );

$db = eZDB::instance( false, $databaseParameters, true );

if ( !$db->isConnected() )
{
    $cli->error( 'Could not connect to database: ' );
    $cli->output( 'Name: ' . $dbName );
    $cli->output( 'User: ' . $dbUser );
    $cli->output( 'Password: ' . $dbPassword );
    $script->shutdown( 1 );
}

/*********************/
//    Copy files     //
/*********************/

$cli->output( 'Copying files...' );
$counter = 0;
$startTime = microtimeFloat();

$fileList = eZDir::recursiveFindRelative( $srcStorageDir, '', '(.*)' );
$fileCount = count( $fileList );

foreach ( $fileList as $file ) 
{
    $srcFile = eZDir::path( array( $srcStorageDir, $file ) );
    $targetFile = eZDir::path( array( $dstStorageDir, $file ) );
        
    eZDir::mkdir( eZDir::dirpath( $targetFile ), false, true );
    if ( !eZFileHandler::copy( $srcFile, $targetFile ) )
    {
        $cli->error( "Couldn't copy file '$srcFile' to '$targetFile'" );
    }

    $counter = displayProgress( $nodeStartTime, $counter, $fileCount );
}

/*********************/
//  Update database  //
/*********************/

$cli->output( "\n\nUpdating database..." );

$eZImageFileSQL = "UPDATE ezimagefile SET filepath = REPLACE(filepath, '$srcVar', '$dstVar');";
$eZImageSQL = "UPDATE ezcontentobject_attribute SET data_text = REPLACE(data_text,'$srcVar','$dstVar') WHERE data_type_string='ezimage';";
if ( !$db->query( $eZImageFileSQL ) )
{
    $cli->error( "Couldn't execute sql:\n$eZImageFileSQL" );
}

if ( !$db->query( $eZImageSQL ) )
{
    $cli->error( "Couldn't execute sql:\n$eZImageSQL" );
}

$cli->output( "\nCopy files script complete." );

$script->shutdown();

/**
 * @static
 * @private
 *
 * @brief Displays progress when a script is performing
 */
function displayProgress( $startTime, $currentCount, $totalCount )
{
    if ( $currentCount )
    {
        $endTime = microtime( true );
        $relTime = ( $endTime - $startTime ) / $currentCount;
        $totalTime = ( $relTime * (float)($totalCount - $currentCount) );
        $percent = number_format( ( $currentCount * 100.0 ) / ( $totalCount ), 2 );

        $timeLeft = formatTime( $totalTime );

        $items = $currentCount . '/' . $totalCount;
        echo "\r " . $percent . "% " . $timeLeft . ' ' . $items;
    }
    ++$currentCount;
    flush();

    return $currentCount;
}

/**
 * @static
 * @private
 *
 * @return time
 */
function microtimeFloat()
{
    $mtime = microtime();
    $tTime = explode( " ", $mtime );
    return $tTime[1] + $tTime[0];
}

/**
 * @static
 * @private
 *
 * @brief Formats time
 * @return time
 */
function formatTime( $totalTime )
{
    $timeSeconds = (int)( $totalTime % 60 );
    $timeMinutes = (int)( ( $totalTime / 60.0 ) % 60 );
    $timeHours = (int)( $totalTime / ( 60.0 * 60.0 ) );
    $timeLeftArray = array();
    if ( $timeHours > 0 )
        $timeLeftArray[] = $timeHours . "h";
    if ( $timeMinutes > 0 )
        $timeLeftArray[] = $timeMinutes . "m";
    $timeLeftArray[] = $timeSeconds . "s";

    return implode( " ", $timeLeftArray );
}

?>
