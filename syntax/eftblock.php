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

    private $db_dsn = null;
    private $db_username = null;
    private $db_password = null;
    private $db = null;
    private $itemLookupStmt = null;

    public function checkDB() {
        // Refresh the DB handle and statement whenever the config changes
        if($this->db_dsn != $this->getConf('dsn') ||
           $this->db_username != $this->getConf('username') ||
           $this->db_password != $this->getConf('password') ||
           $this->itemLookupStmt == null) {
            // (Re)create DB connection and statement
            try {
                $this->db = new PDO(
                    $this->getConf('dsn'),
                    $this->getConf('username'),
                    $this->getConf('password'),
                    array(PDO::ATTR_PERSISTENT => true)
                );
            } catch(PDOException $exc) {
                dbglog("DB connection failed: " . $exc->getMessage());
                return false;
            }
            // This query (and some of the logic in the convertEFT function)
            // are derived from Fuzzysteve's Ship.js project
            // (https://github.com/fuzzysteve/Ship.js).
            $sql = "select invTypes.typeid,typename,COALESCE(effectid,categoryID) effectid from invTypes left join dgmTypeEffects on (dgmTypeEffects.typeid=invTypes.typeid and effectid in (6,8,11,12,13,18,2663,3772)), invGroups where invTypes.typename=? and invTypes.groupid=invGroups.groupid";
            $this->itemLookupStmt = $this->db->prepare($sql);
            $this->db_dsn = $this->getConf('dsn');
            $this->db_username = $this->getConf('username');
            $this->db_password = $this->getConf('password');
        }
        return true;
    }

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
                return array('state' => $state);
            case DOKU_LEXER_UNMATCHED:
                return array(
                    'state' => $state,
                    'eftblock' => $match,
                    'dna' => $this->convertEFT($match),
                );
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


            switch($data['state']) {
                case DOKU_LEXER_ENTER:
                    $renderer->doc .= '<div class="eft-block">';
                    break;
                case DOKU_LEXER_UNMATCHED:
                    $cleaned = $renderer->_xmlEntities($data['eftblock']);
                    $renderer->doc .= $cleaned;
                    break;
                case DOKU_LEXER_EXIT:
                    $renderer->doc .= '</div>';
                    break;
            }
        }
        if($mode != 'xhtml') return false;

        return true;
    }

    // The values are the effectIDs used in the SDE.
    const EVE_TYPE_SHIP = 6;
    const EVE_TYPE_HIGH = 12;
    const EVE_TYPE_MID = 13;
    const EVE_TYPE_LOW = 11;
    const EVE_TYPE_RIG = 2663;
    const EVE_TYPE_SUBSYSTEM = 3772;
    const EVE_TYPE_CHARGE = 8;
    const EVE_TYPE_DRONE = 18;

    private function convertEFT($eftFit) {
        /*
         *
         * $eftFit is the raw input output of the copy to pasteboard function
         * from EFT. The format is:
        [{ship_type}, {fit_name}]

        {low_slots}
        ...
        [Empty Low slot]

        {med_slots}
        ...
        [Empty Med slot]

        {high_slots}
        ...
        [Empty High slot]

        {rigs}
        ...
        [Empty Rig slot]

        {subsystems}
        ...
        [Empty Subsystem slot]


        {drones}

        * Text enclosed in braces is variable text. Unused slots/rigs are
        * specified by special tokens. There are two lines between the last
        * high slot (or subsystem on T3 cruisers) and the start of the drones
        * section.
        */

        // Normalize \r\n to \n, just in case
        $nixBreaks = str_replace( "\r\n", "\n", $eftFit );

        // split the fit into seperate lines
        $lines = explode("\n", $nixBreaks);

        // The first line is the ship type and fitting name in a special format
        $firstLine = array_shift($lines);
        // Trim the brackets off
        $trimmed = substr($firstLine, 1, -1);
        // Extract the ship name and the fit name
        list($shipName, $fitName) = explode(", ", $trimmed);

        // Parse the items
        $items = array(
            self::EVE_TYPE_SHIP => array(),
            self::EVE_TYPE_SUBSYSTEM => array(),
            self::EVE_TYPE_HIGH => array(),
            self::EVE_TYPE_MID => array(),
            self::EVE_TYPE_LOW => array(),
            self::EVE_TYPE_RIG => array(),
            self::EVE_TYPE_DRONE => array(),
            self::EVE_TYPE_CHARGE => array(),
        );

        $tokens = array(array('name' => $shipName));
        foreach($lines as $line) {
            $trimmed = trim($line);
            if(preg_match("/^(.+) x(\d+)$/", $trimmed, $matches)) {
                // Drones are special, they're written as "Drone Name x5"
                $tokens[] = array(
                    'name' => $matches[1],
                    'quantity' => $matches[2],
                );
            } else if($trimmed[0] != '[' || $trimmed != '') {
                // Skip lines starting with a bracket, as they're things like,
                // "[Empty High Slot]".
                $exploded = explode(",", $trimmed, 2);
                foreach($exploded as $exploded_token) {
                    $tokens[] = array(
                        'name' => $exploded_token,
                        'quantity' => 1,
                    );
                }
            }
        }
        // Lookup the type's ID and effect category (aka what kind of item is
        // it). Then sort each item into their categories.
        if(!$this->checkDB()) {
            dbglog("DB connection failed");
            return '';
        }
        foreach($tokens as &$token) {
            $this->itemLookupStmt->execute(array($token['name']));
            if($row = $this->itemLookupStmt->fetchObject()) {
                if((int)$row->effectid == 0) {
                    dbglog("Found 0 effectid: " . $token['name']);
                }
                $token['id'] = (int)$row->typeid;
                $items[(int)$row->effectid][] = $token;
            } else {
                $error = $this->itemLookupStmt->errorInfo();
                $errorMsg = "Database fetch failed: SQLSTATE[" . $error[0];
                $errorMsg .= "] [" . $error[1] . "] " . $error[2];
                dbglog($errorMsg);
            }
        }

        $dna = '';
        foreach(array_keys($items) as $itemType) {
            if($itemType == self::EVE_TYPE_SHIP) {
                // EVE_TYPE_SHIP should be first
                $dna = $items[$itemType][0]['id'] . ":";
            } else {
                $group = &$items[$itemType];
                // Coalesce multiple contiguous items together
                $index = 0;
                $nextIndex = 1;
                while(count($group) - 1 > $index) {
                    $current = &$group[$index];
                    $next = &$group[$index + 1];
                    if($current['name'] == $next['name']) {
                        $current['quantity'] += $next['quantity'];
                        array_splice($group, $index + 1, 1);
                    } else {
                        $index++;
                    }
                }
                // Add the items to the DNA string
                $dna = trim($dna, ":") . ":";
                foreach($group as $item) {
                    $dna .= $item['id'] . ";" . $item['quantity'] . ":";
                }
            }
        }
        // DNA has to end in ::
        $dna = trim($dna, ":") . "::";

        return $dna;
    }
}

// vim:ts=4:sw=4:et:
