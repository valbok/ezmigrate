<?php
//
// Definition of migration class
//
// Created on: <10-Jan-2009 11:19:38 vd>
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
 * @file ezmigratetools.php
 */

/**
 * @brief Class for copying data between different eZ Publish databases.
 * @version 0.1
 */

class eZMigrateTools
{
    const URLALIASML_FILENAME      = 'kernel/classes/ezurlaliasml.php';
    const CONTENTCLASS_PH_FILENAME = 'kernel/classes/packagehandlers/ezcontentclass/ezcontentclasspackagehandler.php';
    const BACKUP_EXTENSION         = '.bk';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Creates and returns package
     */
    static public function createPackage( $packageName = 'Migrate package' )
    {
        return eZPackage::create( $packageName, false, false, false );
    }

    /**
     * Stores package using creation data.
     */
    static public function finalizePackage( $package, $creationData, $creatorID = 'ezcontentobject' )
    {
        $creator = eZPackageCreationHandler::instance( $creatorID );
        if ( !$package or !count( $creationData ) or !$creator )
        {
            return false;
        }

        $packageName = isset( $creationData['name'] )             ? $creationData['name']             : $package->attribute( 'name' );
        $userName    = isset( $creationData['packer'] )           ? $creationData['packer']           : 'Anonymous';
        $userEmail   = isset( $creationData['maintainer_email'] ) ? $creationData['maintainer_email'] : 'nospam@ez.no';

        $package->setAttribute( 'name', $packageName );
        $package->appendChange( $userName, $userEmail, 'Creation of package' );

        $creator->finalize( $package, false, $creationData );
    }

    /**
     * @return List of data that is needed to create package.
     */
    static public function getCreateParameters( $srcNodeIDList = array(), $packageName = false )
    {
        if ( empty( $srcNodeIDList ) )
        {
            return array();
        }

        $migrateINI   = eZINI::instance( 'migrate.ini' );
        $repositoryID = $migrateINI->hasVariable( 'CreatePackageSettings', 'RepositoryID' )
                        ? ( $migrateINI->variable( 'CreatePackageSettings', 'RepositoryID' ) == ''
                            ? false
                            : $migrateINI->variable( 'CreatePackageSettings', 'RepositoryID' )
                            )
                        : false;
        $type         = $migrateINI->hasVariable( 'CreatePackageSettings', 'Type' ) ? $migrateINI->variable( 'CreatePackageSettings', 'Type' ) : 'subtree';
        $nodeList     = array();

        foreach ( $srcNodeIDList as $nodeID )
        {
            $nodeList[] = array( 'id' => "$nodeID",
                                 'type' => $type );
        }

        $availableSiteAccesses = array();
        $ini                   = eZINI::instance();
        $availableSiteAccesses = $ini->variable( 'SiteAccessSettings', 'RelatedSiteAccessList' );
        $availableLanguages    = eZContentObject::translationList();
        $languageArray         = array();

        foreach ( $availableLanguages as $language )
        {
            $languageArray[] = $language->attribute( 'locale_code' );
        }

        $includeClasses   = $migrateINI->hasVariable( 'CreatePackageSettings', 'IncludeClasses' )
                            ? ( $migrateINI->variable( 'CreatePackageSettings', 'IncludeClasses' ) == 'true'
                                ? 1
                                : 0 )
			                : 1;
        $includeTemplates = $migrateINI->hasVariable( 'CreatePackageSettings', 'IncludeTemplates' )
                            ? ( $migrateINI->variable( 'CreatePackageSettings', 'IncludeTemplates' ) == 'true'
                                ? 1
                                : 0 )
		                    : 1;			
        $versions         = $migrateINI->hasVariable( 'CreatePackageSettings', 'Versions' )
                            ? $migrateINI->variable( 'CreatePackageSettings', 'Versions' )
		                    : 'current';			
        $nodeAssigment    = $migrateINI->hasVariable( 'CreatePackageSettings', 'NodeAssigment' )
                            ? $migrateINI->variable( 'CreatePackageSettings', 'NodeAssigment' )
		                    : 'selected';			
        $relatedObjects   = $migrateINI->hasVariable( 'CreatePackageSettings', 'RelatedObjects' )
                            ? $migrateINI->variable( 'CreatePackageSettings', 'RelatedObjects' )
		                    : 'selected';			
        $embedObjects     = $migrateINI->hasVariable( 'CreatePackageSettings', 'EmbedObjects' )
                            ? $migrateINI->variable( 'CreatePackageSettings', 'EmbedObjects' )
		                    : 'selected';			
        $objectOptions    = array( 'include_classes'   => $includeClasses,
                                   'include_templates' => $includeTemplates,
                                   'site_access_array' => $availableSiteAccesses,
                                   'versions'          => $versions,
                                   'language_array'    => $languageArray,
                                   'node_assignment'   => $nodeAssigment,
                                   'related_objects'   => $relatedObjects,
                                   'embed_objects'     => $embedObjects );

        $packageInfo      = array();
        $userID           = $migrateINI->hasVariable( 'CreatePackageSettings', 'UserID' )
                            ? $migrateINI->variable( 'CreatePackageSettings', 'UserID' )
                            : 14;	
        $user             = $userID ? eZUser::fetch( $userID ) : eZUser::currentUser();
        $userObject       = $user ? $user->attribute( 'contentobject' ) : false;
        $userName         = $userObject ? $userObject->name() : 'UnknownUser';
        $email            = $user ? $user->attribute( 'email' ) : 'nospam@ez.no';
        $licence          = $migrateINI->hasVariable( 'CreatePackageSettings', 'Licence' )
                            ? $migrateINI->variable( 'CreatePackageSettings', 'Licence' )
                            : 'GPL';
        // Install package, not import
        $installType      = 'install';
        $version          = $migrateINI->hasVariable( 'CreatePackageSettings', 'PackageVersion' )
                            ? $migrateINI->variable( 'CreatePackageSettings', 'PackageVersion' )
                            : '1.0';
        $maintainerRole   = $migrateINI->hasVariable( 'CreatePackageSettings', 'MaintainerRole' )
                            ? $migrateINI->variable( 'CreatePackageSettings', 'MaintainerRole' )
		                    : 'lead';
        $creationData     = array( 'node_list'         => $nodeList,
                                   'object_options'    => $objectOptions,
                                   'licence'           => $licence,
                                   'version'           => $version,
                                   'packer'            => $userName,
                                   'maintainer_person' => $userName,
                                   'maintainer_email'  => $email,
                                   'maintainer_role'   => $maintainerRole,
                                   'install_type'      => $installType );

        eZContentObjectPackageCreator::generatePackageInformation( $packageInfo, false, false, false, $creationData );

        if ( $packageName )
        {
            $packageInfo['name'] = $packageName;
        }

        return array_merge( $creationData, $packageInfo );
    }

    /**
     * @return An action that should be done when class already exists.
     */
    static public function getClassErrorAction()
    {
        $migrateINI       = eZINI::instance( 'migrate.ini' );
        // Fetch action that should be done if an error exists while installing a class
        $classErrorAction = $migrateINI->hasVariable( 'InstallPackageSettings', 'ClassErrorChoosenAction' )
                            ? $migrateINI->variable( 'InstallPackageSettings', 'ClassErrorChoosenAction' )
		                    : 'skip';

        switch ( $classErrorAction  )
        {
            case "new":
            {
                $classChoosenAction = eZContentClassPackageHandler::ACTION_NEW;
            } break;

            case "replace":
            {
                $classChoosenAction = eZContentClassPackageHandler::ACTION_REPLACE;
            } break;

            case "skip":
            default:
            {
                $classChoosenAction = eZContentClassPackageHandler::ACTION_SKIP;
            } break;
        }

        return $classChoosenAction;
    }

    /**
     * @return An action that should be done when an object or a node already exists.
     */
    static public function getObjectErrorAction()
    {
        $migrateINI        = eZINI::instance( 'migrate.ini' );
        // Fetch action that should be done if an error exists while installing an object
        $objectErrorAction = $migrateINI->hasVariable( 'InstallPackageSettings', 'ObjectErrorChoosenAction' )
                             ? $migrateINI->variable( 'InstallPackageSettings', 'ObjectErrorChoosenAction' )
		                     : 'skip';

        switch ( $objectErrorAction  )
        {
            case "new":
            {
                $objectChoosenAction = eZContentObject::PACKAGE_NEW;
            } break;

            case "replace":
            {
                $objectChoosenAction = eZContentObject::PACKAGE_REPLACE;
            } break;

            case "skip":
            default:
            {
                $objectChoosenAction = eZContentObject::PACKAGE_SKIP;
            } break;
        }

        return $objectChoosenAction;
    }

    /**
     * Installs package.
     * Data will be installed to \a $topNode using \a $createParameters.
     */
    static public function installPackage( $package, $createParameters, $topNode )
    {
        if ( !$package )
        {
            return false;
        }

        $migrateINI          = eZINI::instance( 'migrate.ini' );
        $userID              = $migrateINI->hasVariable( 'InstallPackageSettings', 'UserID' )
                               ? $migrateINI->variable( 'InstallPackageSettings', 'UserID' )
                               : 14;
        $user                = $userID ? eZUser::fetch( $userID ) : eZUser::currentUser();
        $contentObjectID     = $user ? $user->attribute( 'contentobject_id' ) : 0;
        $siteAccessMap       = isset( $createParameters['object_options']['site_access_array'] ) ? $createParameters['object_options']['site_access_array'] : '';
        $restoreDates        = $migrateINI->hasVariable( 'InstallPackageSettings', 'RestoreDates' )
                               ? $migrateINI->variable( 'InstallPackageSettings', 'RestoreDates' ) == 'true'
		                       : 1;

        // Is needed to choose needed action if errors
        $nonInteractive      = $migrateINI->hasVariable( 'InstallPackageSettings', 'NonInteractive' )
                               ? ( $migrateINI->variable( 'InstallPackageSettings', 'NonInteractive' ) == 'true' ? true : false )
		                       : false;

        // Fetch action that should be done if an error exists while installing a class
        $classChoosenAction  = self::getClassErrorAction();

        // Fetch action that should be done if an error exists while installing an object
        $objectChoosenAction = self::getObjectErrorAction();

        $installParameters    = array( 'site_access_map'       => array( '*' => $siteAccessMap ),
                                       'top_nodes_map'         => array( '*' => $topNode ),
                                       'design_map'            => array( '*' => $siteAccessMap ),
                                       'restore_dates'         => $restoreDates,
                                       'user_id'               => $contentObjectID,
                                       'non-interactive'       => $nonInteractive,
                                       'language_map'          => $package->defaultLanguageMap(),
                                       'error_default_actions' => array ( 'ezcontentclass'  => array( eZContentClassPackageHandler::ERROR_EXISTS => $classChoosenAction ),
                                                                          'ezcontentobject' => array( eZContentObject::PACKAGE_ERROR_EXISTS => $objectChoosenAction ) )
                                      );

        return $package->install( $installParameters );
    }

    /**
     * Compares and updates class remote id values in \a $srcDB.
     * If remote_id differs but identifier the same, update remote_id of class based on \a $dstDB.
     *
     * @return list of old remote id values with class id as a key
     */
    static public function updateClassRemoteID( $dstDB, $srcDB )
    {
        $classRemoteIDList = array();
        if ( !$srcDB->isConnected() or !$dstDB->isConnected() )
        {
            return $classRemoteIDList;
        }

        // Should keep global instance
        $dbTMP = eZDB::instance();

        // Target database
        eZDB::setInstance( $dstDB );
        $dstClassList = eZContentClass::fetchAllClasses();

        // All changes will be performed in source database
        eZDB::setInstance( $srcDB );
        $srcClassList = eZContentClass::fetchAllClasses();

        foreach ( $srcClassList as $srcClass )
        {
            $srcID = $srcClass->attribute( 'identifier' );
            $srcRemoteID = $srcClass->attribute( 'remote_id' );
            foreach ( $dstClassList as $dstClass )
            {
                $dstID = $dstClass->attribute( 'identifier' );
                $dstRemoteID = $dstClass->attribute( 'remote_id' );
                if ( ( $srcID == $dstID ) and ( $srcRemoteID != $dstRemoteID ) )
                {
                    // Update source database to use target remote ids
                    $srcClass->setAttribute( 'remote_id', $dstRemoteID );
                    $srcClass->store();
                    // Store old remote id value to restore it later
                    $classRemoteIDList[$srcClass->attribute( 'id' )] = $srcRemoteID;
                }
            }

        }

        // Restore database
        eZDB::setInstance( $dbTMP );

        return $classRemoteIDList;
    }

    /**
     * Restores class remote id values in \a $srcDB database
     */
    static public function restoreClassRemoteID( $srcDB, $classRemoteIDList )
    {
       if ( empty( $classRemoteIDList ) )
       {
           return;
       }

       // Should keep global instance
       $dbTMP = eZDB::instance();

       // All changes will be performed in source database
       eZDB::setInstance( $srcDB );

       foreach ( $classRemoteIDList as $classID => $remoteID )
       {
           $class = eZContentClass::fetch( $classID );
           if ( !$class )
           {
               continue;
           }

           $class->setAttribute( 'remote_id', $remoteID );
           $class->store();
       }

       // Restore database
       eZDB::setInstance( $dbTMP );
    }

    /*************************
     *      EZP HACKS        *
     *************************/

    /**
     * Fixes wrong eZURLAliasML::setLangMaskAlwaysAvailable()
     *
     * @note if imported object doesn't have nodes due to parent node was not installed yet,
     *       transaction error will be appeared if the object is always available.
     */
    static public function fixURLAliasMLFile()
    {
        // If backup file exists replace original.
        if ( file_exists( eZMigrateTools::URLALIASML_FILENAME . eZMigrateTools::BACKUP_EXTENSION ) )
        {
            self::revertURLAliasMLFile();
        }

        // Backup original file
        if ( !eZFileHandler::copy( eZMigrateTools::URLALIASML_FILENAME, eZMigrateTools::URLALIASML_FILENAME . eZMigrateTools::BACKUP_EXTENSION ) )
        {
            return false;
        }

        $wrongCode = 'foreach ( $actionName as $actionItem )';
        $rightCode = 'if ( !count( $actionName ) ) { return; }
            foreach ( $actionName as $actionItem )';

        $content   = eZFile::getContents( eZMigrateTools::URLALIASML_FILENAME );

        // Fix wrong code
        $content   = str_replace( $wrongCode, $rightCode, $content );

        return eZFile::create( eZMigrateTools::URLALIASML_FILENAME, false, $content );
    }

    /**
     * Revertes changes in urlaliasml file
     */
    static public function revertURLAliasMLFile()
    {
        // If file doesn't exist do nothing
        if ( !file_exists( eZMigrateTools::URLALIASML_FILENAME . eZMigrateTools::BACKUP_EXTENSION ) )
        {
            return true;
        }

        // Restore original file
        return rename( eZMigrateTools::URLALIASML_FILENAME . eZMigrateTools::BACKUP_EXTENSION, eZMigrateTools::URLALIASML_FILENAME );
    }

    /**
     * Fixes eZContentClassPackageHandler::install(),
     * if non interactive mode is handled existing classes will not be removed.
     */
    static public function fixContentClassPackageHandlerFile()
    {
        // If backup file exists replace original.
        if ( file_exists( eZMigrateTools::CONTENTCLASS_PH_FILENAME . eZMigrateTools::BACKUP_EXTENSION ) )
        {
            self::revertContentClassPackageHandlerFile();
        }

        // Backup original file
        if ( !eZFileHandler::copy( eZMigrateTools::CONTENTCLASS_PH_FILENAME, eZMigrateTools::CONTENTCLASS_PH_FILENAME . eZMigrateTools::BACKUP_EXTENSION ) )
        {
            return false;
        }

        $content   = eZFile::getContents( eZMigrateTools::CONTENTCLASS_PH_FILENAME );

        // Remove NON_INTERACTIVE
        $wrongCode = 'case eZPackage::NON_INTERACTIVE:';
        $richtCode = '';

        $content   = str_replace( $wrongCode, $rightCode, $content );

        // Add NON_INTERACTIVE to skip action
        $wrongCode = 'case self::ACTION_SKIP:';
        $rightCode = 'case eZPackage::NON_INTERACTIVE:
            case self::ACTION_SKIP:';

        $content   = str_replace( $wrongCode, $rightCode, $content );

        return eZFile::create( eZMigrateTools::CONTENTCLASS_PH_FILENAME, false, $content );
    }

    /**
     * Revertes changes in
     */
    static public function revertContentClassPackageHandlerFile()
    {
        // If file doesn't exist do nothing
        if ( !file_exists( eZMigrateTools::CONTENTCLASS_PH_FILENAME . eZMigrateTools::BACKUP_EXTENSION ) )
        {
            return true;
        }

        // Restore original file
        return rename( eZMigrateTools::CONTENTCLASS_PH_FILENAME . eZMigrateTools::BACKUP_EXTENSION, eZMigrateTools::CONTENTCLASS_PH_FILENAME );
    }
}

?>
