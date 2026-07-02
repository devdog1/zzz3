<?php
class BirdManager {
    private $db;

    // Use paths that will work with symbolic links or absolute paths in /opt/blackhole
    private $static_file    = '/opt/blackhole/etc/bird/bird_static.conf';
    private $static_file_v6 = '/opt/blackhole/etc/bird/bird_static_v6.conf';
    private $peers_file     = '/opt/blackhole/etc/bird/dynamic/peers.conf';
    private $global_file    = '/opt/blackhole/etc/bird/dynamic/global.conf';

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

        // Update Static Routes (Blackholes)
        $static_content_v4 = "";
        $static_content_v6 = "";
        $blocks = $this->db->fetchAll("SELECT ip_address, type FROM blocks WHERE expires_at > DATETIME('now') OR expires_at IS NULL");
        foreach ($blocks as $row) {
            if ($row['type'] === 'IPv4') {
                $static_content_v4 .= "route " . $row['ip_address'] . "/32 drop;\n";
            } else {
                $static_content_v6 .= "route " . $row['ip_address'] . "/128 drop;\n";
            }
        }
        file_put_contents($this->static_file, $static_content_v4);
        file_put_contents($this->static_file_v6, $static_content_v6);

        // Update Peers
        $peers_content = "";
        $peers = $this->db->fetchAll("SELECT * FROM peers");
        foreach ($peers as $row) {
            $peers_content .= "protocol bgp peer_" . $row['id'] . " {\n";
            $peers_content .= "    description \"" . addslashes($row['description']) . "\";\n";
            $peers_content .= "    local as LOCAL_AS;\n";
            $peers_content .= "    neighbor " . $row['ip_address'] . " as " . $row['as_number'] . ";\n";
            $peers_content .= "    ipv4 {\n";
            $peers_content .= "        import filter denyAll;\n";
            $peers_content .= "        export filter Out;\n";
            $peers_content .= "    };\n";
            $peers_content .= "    ipv6 {\n";
            $peers_content .= "        import filter denyAll;\n";
            $peers_content .= "        export filter Out;\n";
            $peers_content .= "    };\n";
            $peers_content .= "}\n\n";
        }
        file_put_contents($this->peers_file, $peers_content);

        exec('sudo /usr/sbin/birdc configure', $output, $return_var);
        return $return_var === 0;
    }
}
