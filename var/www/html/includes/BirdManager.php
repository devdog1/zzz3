<?php
class BirdManager {
    private $db;
    private $v4_file = '/etc/bird/dynamic/blackholes_v4.conf';
    private $v6_file = '/etc/bird/dynamic/blackholes_v6.conf';
    private $peers_file = '/etc/bird/dynamic/peers.conf';
    private $global_file = '/etc/bird/dynamic/global.conf';

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function updateConfig() {
        // Update Global Settings
        $router_id = $this->db->getSetting('router_id', '1.1.1.1');
        $local_as = $this->db->getSetting('local_as', '65000');
        $global_content = "router id $router_id;\n";
        $global_content .= "define LOCAL_AS = $local_as;\n";
        file_put_contents($this->global_file, $global_content);

        // Update Blackholes
        $v4_content = "";
        $v6_content = "";
        $blocks = $this->db->fetchAll("SELECT ip_address, type FROM blocks WHERE expires_at > DATETIME('now') OR expires_at IS NULL");
        foreach ($blocks as $row) {
            if ($row['type'] === 'IPv4') {
                $v4_content .= "route " . $row['ip_address'] . "/32 blackhole;\n";
            } else {
                $v6_content .= "route " . $row['ip_address'] . "/128 blackhole;\n";
            }
        }
        file_put_contents($this->v4_file, $v4_content);
        file_put_contents($this->v6_file, $v6_content);

        // Update Peers
        $peers_content = "";
        $peers = $this->db->fetchAll("SELECT * FROM peers");
        foreach ($peers as $row) {
            $peers_content .= "protocol bgp peer_" . $row['id'] . " from rr_clients {\n";
            $peers_content .= "    neighbor " . $row['ip_address'] . " as " . $row['as_number'] . ";\n";
            $peers_content .= "    description \"" . addslashes($row['description']) . "\";\n";
            $peers_content .= "}\n\n";
        }
        file_put_contents($this->peers_file, $peers_content);

        exec('sudo /usr/sbin/birdc configure', $output, $return_var);
        return $return_var === 0;
    }
}
