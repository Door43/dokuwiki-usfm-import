<?php
/**
 * USFMIMPORT Action Plugin:   Handle Upload and temporarily disabling cache of page.
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Yvonne Lu <yvonnel@leapinglaptop.com>
 * 
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once DOKU_PLUGIN . 'action.php';
require_once(DOKU_INC . 'inc/media.php');
require_once(DOKU_INC . 'inc/infoutils.php');

//define for debug
define ('RUN_STATUS', 'SERVER');

class action_plugin_usfmimport extends DokuWiki_Action_Plugin {

    var $fh=NULL;
    //var $tmpdir = NULL;
    
    function getInfo() {
        return array(
            'author' => 'Yvonne Lu',
            'email' => 'yvonnel@leapinglaptop.com',
            'date' => '2015-4-27',
            'name' => 'usfmimport plugin',
            'desc' => 'usfmimport plugin uploads a zip of usfm file to a given namespace then unzip it
            			Basic syntax: {{usfmimport}}',
            'url' => '',
        );
    }

    /**
     * Register its handlers with the DokuWiki's event controller
     */
    function register(&$controller) {
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, '_handle_function_cache');
        //Parser_cache_use
        //Description: manipulate the cache validityDefaultAction: determine whether or not cached data should be used
        $controller->register_hook('ACTION_HEADERS_SEND', 'BEFORE', $this, '_handle_function_upload');
        //ACTION_HEADERS_SEND
        //This event is signalled by act_dispatch() in inc/actions.php after preparing the headers and before loading the template.
        $controller->register_hook('MEDIA_UPLOAD_FINISH', 'BEFORE', $this, '_handle_media_upload');
    }

    function _handle_media_upload (&$event, $param) {
        global $INPUT;
        global $lang;
        
        //check form source
        $source = trim($INPUT->post->str('source')); 
        $this->showDebug('_handle_media_upload: source= '.$source);
        if ($source != "usfmimport"){ return false;} //not called from usfmimport plugin
        
        $event->preventDefault(); //this will prevent media_save
        
        $this->showDebug('_handle_media_upload: data 0= '.$event->data[0]);
        $this->showDebug('_handle_media_upload: data 1= '.$event->data[1]);
        $this->showDebug('_handle_media_upload: data 2= '.$event->data[2]);
        $this->showDebug('_handle_media_upload: data 3= '.$event->data[3]);
        //upload zip files only
        $file_type = $event->data[3];
        
        if (strpos($file_type, "zip")===FALSE) {
            msg ($this->getlang('wrong_ft'), -1);
            return false; //process this only for zip file only
        }
        
        //check authorization on namespace that user wants to upload to
        
        $new_ns = trim($INPUT->post->str('new_ns'));
        $this->showDebug('_handle_media_upload: new_ns= '.$new_ns);
        
        $new_fn = str_replace("media", "pages", $event->data[1]);
        $this->showDebug('_handle_media_upload: new_fn= '.$new_fn);
        if (!(@is_dir (dirname($new_fn)))) {
            //namespace does not exist, can't check permission, return
            msg (sprintf($this->getlang('no_ns'), $new_ns), -1);
            return;
            
        }
        //check new namespace permission
        $auth = auth_quickaclcheck($new_ns . ':*');

        if($auth >= AUTH_UPLOAD) {
      
            //check if directory containin unzipped file already exist
           
            $new_dir = str_replace(".zip", "", $new_fn); 
            if(@file_exists($new_dir)) {
                if (!$_POST['ow']){
                    msg($lang['uploadexist'],0);
                    return false;
                }else {
                    if ($auth < AUTH_DELETE) {
                        
                        msg(sprintf($lang['deletefail'], $new_fn), -1); 
                        return false;
                    } else {
                        //delete directory
                        io_rmdir($new_dir);
                    }    
                }
            }
           
            //upload zip file
            if (@move_uploaded_file($event->data[0], $new_fn )){
            //if (move_uploaded_file($event->data[0], $this->tmpdir."/".basename($event->data[1]) )){
                    $this->showDebug('_handle_media_upload: after move_uploaded_file');
                    //unzip
                    if($this->decompress($new_fn)) {
                        msg($this->getLang('decompr_succ'), 1);
                        
                        //delete zip
                        unlink($new_fn);
                        
                    } else {
                        msg($this->getLang('decompr_err'), -1);
                    }

            }else {
                 msg($lang['uploadfail'], -1);
            }
           
        }else {
            msg (sprintf($this->getlang('no_perm'), $new_ns), -1);
            return;
        }
        
        
      
    }
    
    function _handle_function_cache(&$event, $param) {
        
        $this->showDebug('_hook_funcion_cache');
        
        if($_FILES['upload']['tmp_name']) {
            $event->preventDefault();
            $event->stopPropagation();
            $event->result = false;
        }

        $namespace = p_get_metadata($event->data->page, 'has_upload_form');
       
        if(!empty($namespace)) {
            $event->data->key .= '|ACL' . auth_quickaclcheck($namespace);
            $event->data->cache = getCacheName($event->data->key, $event->data->ext);
        }
        
        
    }

    function _handle_function_upload(&$event, $param) {
        global $lang;
        global $INPUT;
        global $ACT;
        
        $this->showDebug('_handle_funcion_upload');
        
        //check calling source
        $source = trim($INPUT->post->str('source')); 
        $this->showDebug('_handle_media_upload: source= '.$source);
        if ($source !="usfmimport") return; //not called from usfmimport plugin
        
        $this->showDebug('_hook_funcion_upload after usfmimport check');
        // get namespace to display (either direct or from deletion order) 
        $NS=$INPUT->post->str('new_ns');
        $NS =cleanID($NS);
        
        // check auth
        $AUTH = auth_quickaclcheck("$NS:*");
        if($AUTH < AUTH_UPLOAD) {
            //msg($lang['uploadfail'], -1);
            msg (sprintf($this->getlang['no_perm'], $NS), -1);
            return false;
        }

        // handle upload
        if($_FILES['upload']['tmp_name']) {
            $_POST['mediaid'] = $INPUT->post->str('new_name');
            
            $ret = media_upload($NS, $AUTH);
           
            $this->showDebug('_hook_function_upload: after media_upload');
        }
       
    }
    
     private function showDebug($data) {
        if (strcmp(RUN_STATUS, 'DEBUG')==0){
            if ($this->fh==NULL) {
                $this->fh=fopen("usfmimport.txt", "a");
            }
            fwrite($this->fh, $data.PHP_EOL);
            fclose($this->fh);
            $this->fh=NULL;
        }
        
    }
    
    /**
     * Decompress an archive (adopted from plugin manager)
     *
     * 
     */
    function decompress($file) {

        $target = str_replace ('.zip', '', $file);
        
       
        require_once(DOKU_INC."inc/ZipLib.class.php");
        
       

        $zip = new ZipLib();
        
        $ok  = $zip->Extract($file, $target);

        if($ok) {
            $this->showDebug('decompress worked');
            
            //rename .usfm to .usfm.txt
            $exfiles = $zip->get_List($file);
            foreach($exfiles as $exfile) {
                
                if (utf8_strpos($exfile['filename'], ".usfm")){
                    //rename .usfm to .usfm.txt, ignore all other file types
                    $new_name=  str_replace(".usfm", ".usfm.txt", $exfile['filename']);
                    $new_dest = $target.DIRECTORY_SEPARATOR.$new_name;
                    $old_dest = $target.DIRECTORY_SEPARATOR.$exfile['filename'];
                    
                    if(!@io_rename($old_dest,$new_dest)){
                         msg (springf($this->getlang['rn_failed'], $old_dest), -1 );
                    }
                    //$ret=io_rename($old_dest, $new_dest);
                    //$this->showDebug("io_rename returns:  ".($ret)?'true':'false');
                            
                                        
                }
            }
            
            
           
            return true;
        } else {
            return false;
        }

    }
}
