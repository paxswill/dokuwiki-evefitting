<?php
/**
 * DokuWiki Plugin evefitting (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Will Ross <paxswill@paxswill.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_evefitting_eftblock extends DokuWiki_Syntax_Plugin {

    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'protected';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType() {
        return 'normal';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 20;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('<EFT>\n?',$mode,'plugin_evefitting_eftblock');
    }

    public function postConnect() {
        $this->Lexer->addPattern('\[.*?\]\n','plugin_evefitting_eftblock');
        $this->Lexer->addExitPattern('</EFT>','plugin_evefitting_eftblock');
    }

    /**
     * Handle matches of the evefitting syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler){
        switch($state) {
            case DOKU_LEXER_ENTER:
            case DOKU_LEXER_EXIT:
                return array($state, '');
            case DOKU_LEXER_MATCHED:
            case DOKU_LEXER_UNMATCHED:
                return array($state, $match);
        }
        return array();
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer &$renderer, $data) {
        if($mode == 'xhtml') {
            list($state, $match) = $data;
            switch($state) {
                case DOKU_LEXER_ENTER:
                    $renderer->doc .= '<div class="eft-block">';
                    break;
                case DOKU_LEXER_MATCHED:
                    $renderer->doc .= '<span class="eft-header">';
                    $renderer->doc .= $match . '</span>';
                    break;
                case DOKU_LEXER_UNMATCHED:
                    $renderer->doc .= $renderer->_xmlEntities($match);
                    break;
                case DOKU_LEXER_EXIT:
                    $renderer->doc .= '</div>';
                    break;
            }
        }
        if($mode != 'xhtml') return false;

        return true;
    }
}

// vim:ts=4:sw=4:et:
