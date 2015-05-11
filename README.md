"# dokuwiki-usfm-import" 
To use this plugin, first, copy files to lib/plugins/usfmimport.  Next, create a new page under a namespace you have delete permission.  Enter {{usfmimport}} to the top of the page.  A form will appear after you save the page.  If not, reload the page and purge the cache.  Use the form to upload and decompress zip file containing .usfm files.  The form will give you the option to choose the file and enter a new namespace.

Known problems:  
- When an user try to upload to a namespace where he does not have upload permission, the error message doesn't show up correctly.
- The overwrite feature still needs more testing
- currently, if the destination namespace does not exist, the plugin will error out rather than creating the namespace
- Files are decompressed within a subnamespace named after the zip file under the destination namespace.  For example, if the upload zip file is named aaa.zip and destination namespace is ns1, the resulting decompressed files will be located in ns1:aaa:<files>  
