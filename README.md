eZ Migrate extension v. 0.1alpha
================================
This extension is used to copy eZ Publish data between 2 databases.

Simplified logic
================
eZ Publish package system is used.

1. Create package with provided nodes.
2. Try to install created package to target database.

Requirements
============
o 	The version of eZ Publish installation should be 4.0.0 or higher.
o       You must have PHP 5.x CLI.

Installation
============
1. Download the tarball.

2. Copy the package to your root installation folder.

3. Unpack the files in the distribution.
   $ tar xfvz ezmigrate.tar.gz

4. Enable the eZ Migrate extension.
   Edit the file 'site.ini.append.php' located
   in the 'settings/override' directory. Add the following line under
   the [ExtensionSettings] section:

   ActiveExtensions[]=ezmigrate

5. Edit ./extension/ezmigrate/settings/migrate.ini.append.php file to configurate migration process.

6. If your eZ Publish version is 4.0.1 we must apply ./extension/ezmigrate/create_element_xml.patch patch 
   to fix problem with creating XML. If the patch is not applied XML will be wrong due to symbols like '&' in content of attributes.

Usage
=====
!!!!      WARNING      !!!
!! BACKUP YOUR DATABASES !
!!!!!! before using !!!!!!

Under installation root:
	$ [path_to_php_cli] ./extension/ezmigrate/scripts/ezmigrate.php --help
You will see the explanation of each option you need to use.

Commonly used command:
	$ php ./extension/ezmigrate/scripts/ezmigrate.php --logfiles --src-siteaccess=[SOURCE_SITEACCESS] \
         --dst-siteaccess=[DESTINATION_SITEACCESS] --src-node-id-list=[NODE_ID_LIST] --dst-parent-node-id=[PARENT_NODE_ID]
