<?php
class Utils {
    public static function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($range, $netmask) = explode('/', $range, 2);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $range_dec = ip2long($range);
            $ip_dec = ip2long($ip);
            $wildcard_dec = pow(2, (32 - $netmask)) - 1;
            $netmask_dec = ~ $wildcard_dec;
            return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip_bin = self::ip2bin($ip);
            $range_bin = self::ip2bin($range);
            return substr($ip_bin, 0, $netmask) === substr($range_bin, 0, $netmask);
        }

        return false;
    }

    private static function ip2bin($ip) {
        $packed = inet_pton($ip);
        $bin = "";
        for ($i = strlen($packed) - 1; $i >= 0; $i--) {
            $bin = str_pad(decbin(ord($packed[$i])), 8, '0', STR_PAD_LEFT) . $bin;
        }
        return $bin;
    }
}
?>
