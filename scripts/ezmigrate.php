<?php
//
// Definition of command line migration script
//
// Created on: <15-Jan-2009 11:19:38 vd>
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
 * @file ezmigrate.php
 */

/**
 * @brief Command line script that can copy/migrate eZ Publish content between two databases
 * @version 0.1
 */

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'debug-message' => '',
                                     'use-session' => true,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );

$script->startup();

$options = $script->getOptions( "[db-user:][db-password:][src-siteaccess:][dst-siteaccess:][src-node-id-list:][dst-parent-node-id:][just-create-package][package-name:]",
                                "",
                                array( 'db-user'             => 'Database user.',
                                       'db-password'         => 'Database password.',
                                       'src-siteaccess'      => 'Site access where source database settings are located.',
                                       'dst-siteaccess'      => 'Site access where destination database settings are located.',
                                       'src-node-id-list'    => 'Source subtree parent node ID list (separated by comma).',
                                       'dst-parent-node-id'  => 'Destination parent node ID.',
                                       'just-create-package' => 'Do not install, just create a package.',
                                       'package-name'        => 'Package name.' ) );

//if ( $options['src-siteaccess'] )
//  $script->SiteAccess = $options['src-siteaccess'];
$script->initialize();
// Have to include migration tools after initialization to fetch ini settings
$migrateINI = eZINI::instance( 'migrate.ini' );
$extenstionPath = $migrateINI->hasVariable( 'MigrateSettings', 'ExtensionPath' ) ? $migrateINI->variable( 'MigrateSettings', 'ExtensionPath' ) : false;
if ( !$extenstionPath )
{
    $cli->error( "Warning, migrate extension is not enabled for this installation. You must enable it before migration!\n"  );
    $script->shutdown( 1 );
}

include_once( eZExtension::baseDirectory() . '/' . $extenstionPath . '/classes/ezmigratetools.php' );

$creatorID          = 'ezcontentobject';
$dbUser             = $options['db-user']             ? $options['db-user']            : 'root';
$dbPassword         = $options['db-password']         ? $options['db-password']        : '';
$srcNodeListStr     = $options['src-node-id-list']    ? $options['src-node-id-list']   : false;
$dstParentNodeIDStr = $options['dst-parent-node-id']  ? $options['dst-parent-node-id'] : false;
$srcSiteAccess      = $options['src-siteaccess']      ? $options['src-siteaccess']     : false;
$dstSiteAccess      = $options['dst-siteaccess']      ? $options['dst-siteaccess']     : false;
$justCreatePackage  = $options['just-create-package'] ? true                           : false;
$packageName        = $options['package-name']        ? $options['package-name']       : false;
$useExistingClasses = $migrateINI->hasVariable( 'InstallPackageSettings', 'UseExistingClasses' )
                      ? $migrateINI->variable( 'InstallPackageSettings', 'UseExistingClasses' ) == 'true'
                      : true;
$nonInteractive     = $migrateINI->hasVariable( 'InstallPackageSettings', 'NonInteractive' )
                      ? ( $migrateINI->variable( 'InstallPackageSettings', 'NonInteractive' ) == 'true' ? true : false )
                      : false;
// Fetch action that should be done if an error exists while installing an object
$objectErrorAction  = $migrateINI->hasVariable( 'InstallPackageSettings', 'ObjectErrorChoosenAction' )
                      ? $migrateINI->variable( 'InstallPackageSettings', 'ObjectErrorChoosenAction' )
		              : 'skip';

/***************************
 *        CHECKS           *
 ***************************/

if ( !$srcSiteAccess )
{
    $cli->error( "Source siteaccess is not set.\n"  );
    $script->showHelp();
    $script->shutdown( 1 );
}

if ( !$justCreatePackage and !$dstSiteAccess )
{
    $cli->error( "Destination siteaccess is not set.\n"  );
    $script->showHelp();
    $script->shutdown( 1 );
}

$srcPath   = eZDir::path( array( 'settings', 'siteaccess', $srcSiteAccess ) );
$ini       = eZINI::instance( 'site.ini', $srcPath, null, null, null, true, true );
$srcDBName = $ini->hasVariable( 'DatabaseSettings', 'Database' ) ? $ini->variable( 'DatabaseSettings', 'Database' ) : false;

if ( !$srcDBName )
{
    $cli->error( "Could not fetch source database from $srcSiteAccess siteaccess.\n"  );
    $script->showHelp();
    $script->shutdown( 1 );
}

if ( !$justCreatePackage )
{
    $dstPath   = eZDir::path( array( 'settings', 'siteaccess', $dstSiteAccess ) );
    $ini       = eZINI::instance( 'site.ini', $dstPath, null, null, null, true, true );
    $dstDBName = $ini->hasVariable( 'DatabaseSettings', 'Database' ) ? $ini->variable( 'DatabaseSettings', 'Database' ) : false;

    if ( !$dstDBName )
    {
        $cli->error( "Could not fetch destination database from $dstSiteAccess siteaccess.\n"  );
        $script->showHelp();
        $script->shutdown( 1 );
    }
}

if ( !$srcNodeListStr )
{
    $cli->error( "Source subtree parent node ID list is not set.\n"  );
    $script->showHelp();
    $script->shutdown( 1 );
}

if ( !$justCreatePackage and !$dstParentNodeIDStr )
{
    $cli->error( "Destination parent node ID is not set\n"  );
    $script->showHelp();
    $script->shutdown( 1 );
}

$srcNodeListExploded = explode( ',', $srcNodeListStr );
if ( !count( $srcNodeListExploded ) )
{
    $cli->error( "Source node ID list is empty.\n"  );
    $script->showHelp();
    $script->shutdown( 1 );
}

/*if ( !$nonInteractive and !in_array( $objectErrorAction, array( 'new' ) ) )
{
    $cli->error( "WARNING! You chose $objectErrorAction as an action when an error appears while installing of object/node.\n" .
                 "In case when the node already exists installing of this package can be failed!\n"  );
}
*/

$currentVersion = eZPublishSDK::version();
if ( !$justCreatePackage and version_compare( $currentVersion, '4.0.2' ) < 0 )
{
    $cli->error( "WARNING! You must apply the patch 'create_element_xml.patch' before running the script!\n" .
                  "In case if this patch is not applied and your attributes have special symbols (like ampersand) installation of the package will be failed!\n" );
}

$databaseParameters = array( 'user'     => $dbUser,
                             'password' => $dbPassword );

$srcDB = eZDB::instance( false, array_merge( array( 'database' => $srcDBName ), $databaseParameters ), true );
eZDB::setInstance( $srcDB );

if ( !$srcDB->isConnected() )
{
    $cli->error( 'Could not connect to database: ' );
    $cli->output( 'Name: ' . $srcDBName );
    $cli->output( 'User: ' . $dbUser );
    $cli->output( 'Password: ' . $dbPassword );
    $script->shutdown( 1 );
}

if ( !$justCreatePackage )
{
    $dstDB = eZDB::instance( false, array_merge( array( 'database' => $dstDBName ), $databaseParameters ), true );
    if ( !$dstDB->isConnected() )
    {
        $cli->error( 'Could not connect to database: ' );
        $cli->output( 'Name: ' . $dstDBName );
        $cli->output( 'User: ' . $dbUser );
        $cli->output( 'Password: ' . $dbPassword );
        $script->shutdown( 1 );
    }

    // Needs to check existence of destination node in second databsae
    // Set new database handler
    eZDB::setInstance( $dstDB );

    $dstNode = eZContentObjectTreeNode::fetch( $dstParentNodeIDStr );
    if ( !$dstNode )
    {
        $cli->error( 'Cannot fetch destination node by ID: ' . $dstParentNodeIDStr );
        $script->shutdown( 1 );
    }

    $dstParentNodeID = (int) $dstParentNodeIDStr;

    // Restore previous sdatabase
    eZDB::setInstance( $srcDB );
}

$srcNodeIDList = array();
$nameList = array();
foreach ( $srcNodeListExploded as $nodeID )
{
    if ( !is_numeric( $nodeID ) )
    {
        $cli->error( "'" . $nodeID . "' is not numeric. Skipping..." );
        continue;
    }

    $node = eZContentObjectTreeNode::fetch( $nodeID );
    if ( !$node )
    {
        $cli->error( 'Cannot fetch source node by ID: ' . $nodeID );
        continue;
    }

    $nameList[] = $node->object()->name();
    $srcNodeIDList[] = $nodeID;
}

$cli->output( 'Source nodes : ' . $cli->stylize( 'mark', implode( ', ',  $nameList ) ) . ' in database: ' . $cli->stylize( 'emphasize', $srcDBName ) );

if ( !$justCreatePackage )
{
    $cli->output( 'Target node  : ' . $cli->stylize( 'mark', $dstNode->object()->name() ) . ' in database: ' . $cli->stylize( 'emphasize', $dstDBName ) );
    $cli->output( 'Non interactive mode : ' . $cli->stylize( 'mark', ( $nonInteractive ? 'Yes' : 'No' ) ) );

    if ( $nonInteractive )
    {
        $cli->output( '    Installing of existing classes, nodes and objects will be skipped!' );
    }
    else
    {
        // Fetch action that should be done if an error exists while installing a class
        $classErrorAction  = eZMigrateTools::getClassErrorAction();
        switch ( $classErrorAction )
        {
            case eZContentClassPackageHandler::ACTION_NEW:
            {
                $classAction = 'If classes alerady exist new classes will be created';
            } break;

            case eZContentClassPackageHandler::ACTION_REPLACE:
            {
                $classAction = 'Existing classes will be replaced by new';
            } break;

            case eZContentClassPackageHandler::ACTION_SKIP:
            default:
            {
                $classAction = 'Installing of existing classes will be skipped';
            } break;
        }

        $cli->output( "    $classAction!" );

        // Fetch action that should be done if an error exists while installing object/node
        $objectErrorAction = eZMigrateTools::getObjectErrorAction();
        switch ( $objectErrorAction )
        {
            case eZContentObject::PACKAGE_NEW:
            {
                $objectAction = 'If nodes or objects already exist new instances will be created';
            } break;

            case eZContentObject::PACKAGE_REPLACE:
            {
                $objectAction = 'Existing objects will be replaced by a new instance. WARNING: Nodes will not be handled by this action';
            } break;

            case eZContentObject::PACKAGE_SKIP:
            default:
            {
                $objectAction = 'Installing of existing objects will be skipped. WARNING: Nodes will not be handled by this action';
            } break;
        }

        $cli->output( "    $objectAction!" );
    }

    if ( $useExistingClasses )
    {
        $cli->output( "Identifiers will be used for checking existence of classes if there is no classes by remote id!" );
    }
}

$cli->output( "\nMigration script start...\n" );

/****************************************
 * Fixing some bugs in eZ Publish 4.0.1 *
 * and do some changes in DB if needed  *
 ****************************************/

// If we should just create a package we do not need to fix eZ Publish
if ( !$justCreatePackage )
{
    $oldClassRemoteIDList = array();
    if ( $useExistingClasses )
    {
        $cli->output( 'Updating class remote id values in ' . $cli->stylize( 'emphasize', $srcDBName ) . ' database...' );

        $oldClassRemoteIDList = eZMigrateTools::updateClassRemoteID( $dstDB, $srcDB );
    }

    $cli->output( 'Fixing ' . $cli->stylize( 'mark', 'eZURLAliasML::setLangMaskAlwaysAvailable()' ) . ' to prevent transaction error...' );

    if ( !eZMigrateTools::fixURLAliasMLFile() )
    {
        $cli->error( 'Could not fix eZURLAliasML::setLangMaskAlwaysAvailable().' );
    }

    if ( $nonInteractive )
    {
        $cli->output( 'Fixing ' . $cli->stylize( 'mark', 'eZContentClassPackageHandler::install()') . ' to skip removing of existing classes...' );

        if ( !eZMigrateTools::fixContentClassPackageHandlerFile() )
        {
            $cli->error( 'Could not fix eZContentClassPackageHandler::install().' );
        }
    }
}

/***********************************
 *       Content Migration         *
 ***********************************/

$cli->output( 'Initializing package data...' );

$package = eZMigrateTools::createPackage();
if ( !$package )
{
    $cli->error( 'Could not create package object.' );
    $script->shutdown( 1 );
}

$createParameters = eZMigrateTools::getCreateParameters( $srcNodeIDList, $packageName );
if ( !$createParameters )
{
    $cli->error( 'Could not fetch create parameters.' );
    $script->shutdown( 1 );
}

$cli->output( 'Creating a package...' );

eZMigrateTools::finalizePackage( $package, $createParameters );

$cli->output( '    The package ' . $cli->stylize( 'mark', $createParameters['name'] ) . ' has been created.' );

/***** Installing *****/

if ( !$justCreatePackage )
{
    $cli->output( 'Installing this package to database: ' . $cli->stylize( 'emphasize', $dstDBName ) );
foreach( $GLOBALS as $key => $val)
{
if (strpos($key,'eZContentLanguage') !== false || strpos($key,'eZINI')!== false)
{
echo ("unseting GLOBALS['$key']\n");
unset($GLOBALS[$key]);

}
}
    eZDB::setInstance( $dstDB );

    // Change site access to get needed ini settings like var or storage folder
//    changeAccess( array( 'name' => $dstSiteAccess,
//                         'type' => EZ_ACCESS_TYPE_STATIC ) );

unset( $GLOBALS['eZScriptInstance'] );

$script = eZScript::instance( array( 'debug-message' => '',
                                     'use-session' => true,
                                     'use-modules' => true,
                                     'use-extensions' => true ) );

$script->startup();

if ( $options['dst-siteaccess'] )
  $script->SiteAccess = $options['dst-siteaccess'];
$script->initialize();
var_dump($script);

    $dstDB->begin();

    if ( eZMigrateTools::installPackage( $package, $createParameters, $dstParentNodeID ) )
    {
        $cli->output( 'Package ' . $cli->stylize( 'mark', $createParameters['name'] ) . ' sucessfully installed!' );
        $dstDB->commit();
    }
    else
    {
        $cli->error( 'Failed to install package ' . $cli->stylize( 'emphasize', $createParameters['name'] ) );
        $dstDB->rollback();
    }
}

/****************************
 *     Reverting fixes      *
 ****************************/

$cli->output( '' );

if ( !$justCreatePackage )
{
    if ( $useExistingClasses and count( $oldClassRemoteIDList ) )
    {
        $cli->output( 'Restoring class remote id values in ' . $cli->stylize( 'emphasize', $srcDBName ) . ' database...' );
        eZMigrateTools::restoreClassRemoteID( $srcDB, $oldClassRemoteIDList );
    }

    $cli->output( 'Reverting changes in ' . $cli->stylize( 'mark', 'eZURLAliasML::setLangMaskAlwaysAvailable()' ) . '...' );

    if ( !eZMigrateTools::revertURLAliasMLFile() )
    {
        $cli->error( 'Could not revert changes in eZURLAliasML::setLangMaskAlwaysAvailable().' );
    }

    if ( $nonInteractive )
    {
        $cli->output( 'Reverting changes in ' . $cli->stylize( 'mark', 'eZContentClassPackageHandler::install()' ) . '...' );

        if ( !eZMigrateTools::revertContentClassPackageHandlerFile() )
        {
            $cli->error( 'Could not revert changes in eZContentClassPackageHandler::install().' );
        }
    }
}

$cli->output( '' );
$cli->output( 'Migration script complete.' );

$script->shutdown();

?>
