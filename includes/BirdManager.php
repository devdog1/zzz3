<?php
class BirdManager {
    private $db;
    private $configDir = '/etc/bird/dynamic/';

    public function __construct($db) {
        $this->db = $db;
    }

    public function updateBirdConfig() {
        $settings = $this->db->getSettings();
        $routerId = $settings['router_id'] ?? '1.1.1.1';
        $asNumber = $settings['as_number'] ?? '65000';

        // Write global.conf
        $globalConf = "define BIRD_ROUTER_ID = $routerId;\n";
        $globalConf .= "define BIRD_AS = $asNumber;\n";
        file_put_contents($this->configDir . 'global.conf', $globalConf);

        // Write blackholes_v4.conf
        $v4 = "";
        $blocks = $this->db->query("SELECT ip FROM blocks WHERE ip NOT LIKE '%:%'");
        while ($row = $blocks->fetchArray(SQLITE3_ASSOC)) {
            $v4 .= "route " . $row['ip'] . "/32 blackhole;\n";
        }
        file_put_contents($this->configDir . 'blackholes_v4.conf', $v4);

        // Write blackholes_v6.conf
        $v6 = "";
        $blocks = $this->db->query("SELECT ip LIKE '%:%'");
        $blocks = $this->db->query("SELECT ip FROM blocks WHERE ip LIKE '%:%'");
        while ($row = $blocks->fetchArray(SQLITE3_ASSOC)) {
            $v6 .= "route " . $row['ip'] . "/128 blackhole;\n";
        }
        file_put_contents($this->configDir . 'blackholes_v6.conf', $v6);

        // Write peers.conf
        $peers = "";
        $res = $this->db->query("SELECT * FROM peers");
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $peers .= "protocol bgp peer_" . $row['id'] . " from rr_client {\n";
            $peers .= "    neighbor " . $row['ip'] . " as " . $row['as_number'] . ";\n";
            $peers .= "    description \"" . addslashes($row['description']) . "\";\n";
            $peers .= "}\n\n";
        }
        file_put_contents($this->configDir . 'peers.conf', $peers);

        // Reload BIRD
        exec('sudo /usr/sbin/birdc configure', $output, $returnVar);
        return $returnVar === 0;
    }
}
