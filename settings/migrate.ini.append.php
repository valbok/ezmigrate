<?php /* #?ini charset="iso-8859-1"?

[CreatePackageSettings]
# Empty means local repository will be used
RepositoryID=
# 'subtree' whole subtree will be copied to new database.
# 'node' olny node will be imported.
Type=subtree
# Include class definition
IncludeClasses=true
# Include templates
IncludeTemplates=false
# Which version should be imported
Versions=current
# 'selected' only selected node assigments will be imported.
# 'main' only main node will be imported
NodeAssignment=selected
# 'selected' related objects will be imported as well.
RelatedObjects=selected
# 'selected' embed objects will be imported as well.
EmbedObjects=selected
# Which user should create package
UserID=14
# Role of this user
MaintainerRole=lead
# Licence of package
Licence=GPL
PackageVersion=1.0

[InstallPackageSettings]
# Content object id of user that will install created package
UserID=14
RestoreDates=true
# Interactive mode. 
# If 'true' and there are some conflicts, 
# for example, if a class or an object or a node already exists, it will be skipped for installing.
# If it is 'false' behaviour will depend on ClassErrorChoosenAction and ObjectErrorChoosenAction.
NonInteractive=true
# It's used when NonInteractive is 'false'!
#
# If some errors/conflicts while installing a class there are few ways to resolve it:
#    skip    - skip installing.
#    new     - keep existing class and create new one.
#    replace - replace existing class by new one.
ClassErrorChoosenAction=skip
# If some errors/conflicts while installing an object or a node
# skip|new|replace the same as for classes.
# NOTE: nodes will handle 'new' only.
ObjectErrorChoosenAction=new
# NOTE: Checking for existence of a class is produced by checking its remote id.
#       If there is no class with this remote id it means imported class doesn't exist!
#       And new class will be created with unique identifier.
#       NonInteractive will be used if class exists with remote id, 
#       UseExistingClasses is needed if class doesn't exist with this remote id.
# 'true' means don't create new class if its identifier already exists.
# Otherwise new class will be created with another identifier,
# E.g. If identifier is 'folder' and class with this id already exists in new database
#      but remote id differs.
#      So if UseExistingClasses is 'false', new class will be created with identifier 'folder_1'
#      if it's 'true' existing class folder will be used, installing of this class will be skipped.
UseExistingClasses=true

[MigrateSettings]
ExtensionPath=ezmigrate

*/ ?>
