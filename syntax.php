<?php
/**
 * usfmimport plugin, allows users with correct permission to upload of a zip 
 * of usfm files from within a wikipage to a defined namespace.
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 *
 * @author Yvonne Lu <yvonnel@leapinglaptop.com>
 *
 */

if(!defined('NL')) define('NL', "\n");
if(!defined('DOKU_INC')) define('DOKU_INC', dirname(__FILE__) . '/../../');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');
require_once(DOKU_INC . 'inc/media.php');
require_once(DOKU_INC . 'inc/auth.php');

require_once(DOKU_INC . 'inc/infoutils.php');

//define for debug
define ('RUN_STATUS', 'SERVER');

class syntax_plugin_usfmimport extends DokuWiki_Syntax_Plugin {

    var $fh=NULL; //debug file handle
    
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

    function getType() {
        return 'substition';
    }

    function getSort() {
        return 32;
    }

    function connectTo($mode) {
        //$this->Lexer->addSpecialPattern('\{\{usfmimport>.+?\}\}', $mode, 'plugin_usfmimport');
        $this->Lexer->addSpecialPattern('\{\{usfmimport\}\}', $mode, 'plugin_usfmimport');
    }

    function handle($match, $state, $pos, &$handler) {
        global $ID;

        

        $options['overwrite'] = TRUE;
        $options['renameable'] = TRUE;

        
        $ns = getNS($ID);
       

        return array('uploadns' => hsc($ns), 'para' => $options);
    }

    function render($mode, &$renderer, $data) {
        
        $this->showDebug('in render $mode='.$mode);
        
        if($mode == 'xhtml') {
            //check auth
            $auth = auth_quickaclcheck($data['uploadns'] . ':*');

            if($auth >= AUTH_READ) {
                $renderer->doc .= $this->upload_plugin_uploadform($data['uploadns'], $auth, $data['para']);
                $renderer->info['cache'] = false;
            }
            return true;
        } else if($mode == 'metadata') {
            $renderer->meta['has_upload_form'] = $data['uploadns'] . ':*';
            return true;
        }
        return false;
    }

    /**
     * Print the media upload form if permissions are correct
     *
     * adepted from upload plugin
     * 
     * @author Yvonne Lu <yvonnel@leapinglaptop.com>
     *
     */
    function upload_plugin_uploadform($ns, $auth, $options) {
        global $ID;
        global $lang;
        $html = '';

        if($auth < AUTH_UPLOAD) return;

        $params = array();
        //$params['id'] = 'upload_plugin';
        $params['id'] = 'usfmimport_plugin';
        $params['action'] = wl($ID);
        $params['method'] = 'post';
        $params['enctype'] = 'multipart/form-data';
        $params['class'] = 'upload__plugin';

        // Modification of the default dw HTML upload form
        $form = new Doku_Form($params);
        $form->startFieldset($this->getlang('zip_upload'));
        $form->addElement(formSecurityToken());
        $form->addHidden('page', hsc($ID));
        $form->addHidden('ns', hsc($ns));
        $form->addHidden('source', hsc("usfmimport")); //add source of call, used in action to ignore anything not from this form
        //function form_makeTextField($name, $value='', $label=null, $id='', $class='', $attrs=array()) {
        $form->addElement(form_makeFileField('upload', $lang['txt_upload'] . ':', 'upload__file'));
        $form->addElement(form_makeTextField('new_ns', hsc($ns), $this->getlang('new_ns') . ':', 'upload__ns')); //new namespace
        if($options['renameable']) {
            // don't name this field here "id" because it is misinterpreted by DokuWiki if the upload form is not in media manager
            $form->addElement(form_makeTextField('new_name', '', $lang['txt_filename'] . ':', 'upload__name'));
        }

        if($auth >= AUTH_DELETE) {
            if($options['overwrite']) {
                //$form->addElement(form_makeCheckboxField('ow', 1, $lang['txt_overwrt'], 'dw__ow', 'check'));
                // circumvent wrong formatting in doku_form
                $form->addElement(
                    '<label class="check" for="dw__ow">' .
                    '<span>' . $lang['txt_overwrt'] . '</span>' .
                    '<input type="checkbox" id="dw__ow" name="ow" value="1"/>' .
                    '</label>'
                );
            }
        }
        $form->addElement(form_makeButton('submit', '', $lang['btn_upload']));
        $form->endFieldset();

        $html .= '<div class="upload_plugin"><p>' . NL;
        $html .= '<h3>USFM IMPORT</h3>';
        $html .= $form->getForm();
        $html .= '</p></div>' . NL;
        return $html;
    }
    
    private function showDebug($data) {
        if (strcmp(RUN_STATUS, 'DEBUG')==0){
            if ($this->fh==NULL) {
                $this->fh=fopen("usfmimport.txt", "a");
            }
            fwrite($this->fh, $data.PHP_EOL);
            fclose($this->fh);
            $this->fh = NULL;
        }
        

        
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
