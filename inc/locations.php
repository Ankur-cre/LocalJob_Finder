<?php
if (!function_exists('get_states')) {
    function get_states() {
        global $db;
        $res = $db->query("SELECT DISTINCT state FROM master_locations WHERE is_active=1 ORDER BY state ASC");
        $states = [];
        while($row = $res->fetch_assoc()) $states[] = $row['state'];
        return $states;
    }
}

if (!function_exists('get_districts')) {
    function get_districts($state) {
        global $db;
        $stmt = $db->prepare("SELECT DISTINCT district FROM master_locations WHERE state=? AND is_active=1 ORDER BY district ASC");
        $stmt->bind_param('s', $state);
        $stmt->execute();
        $res = $stmt->get_result();
        $districts = [];
        while($row = $res->fetch_assoc()) $districts[] = $row['district'];
        return $districts;
    }
}

if (!function_exists('get_cities')) {
    function get_cities($state, $district) {
        global $db;
        $stmt = $db->prepare("SELECT city FROM master_locations WHERE state=? AND district=? AND is_active=1 ORDER BY city ASC");
        $stmt->bind_param('ss', $state, $district);
        $stmt->execute();
        $res = $stmt->get_result();
        $cities = [];
        while($row = $res->fetch_assoc()) $cities[] = $row['city'];
        return $cities;
    }
}

if (!function_exists('get_all_states')) {
    function get_all_states() {
        return get_states();
    }
}
?>