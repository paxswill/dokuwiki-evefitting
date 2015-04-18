<?php
/**
 * DokuWiki Plugin evefitting (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Will Ross <paxswill@paxswill.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_evefitting_toolbar extends DokuWiki_Action_Plugin {

    public function register(Doku_Event_Handler $controller) {
       $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_toolbar');
    }

    public function insert_toolbar(Doku_Event &$event, $param) {
        $event->data[] = array (
            'type' => 'format',
            'title' => 'EFT block',
            'icon' => '../../plugins/evefitting/fitting.png',
            'open' => '<EFT>',
            'close' => '</EFT>',
            'sample' => '\n',
        );
    }
}

// vim:ts=4:sw=4:et:
