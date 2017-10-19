<?php

// Manage Time Interval
#######################
include_once(SYS_PATH.'/core/process/timezone.loader.php');


// Genearl -- DONE
##########

function req_maps_localization_coordinates()         //DONE
{
    return "SELECT MAX(lat) AS max_latitude, MIN(lat) AS min_latitude, MAX(lon) AS max_longitude, MIN(lon) as min_longitude FROM spawnpoints";

}

// Pokemon -- DONE
############

function req_pokemon_count()             //DONE
{
    return "SELECT COUNT(*) AS total FROM sightings WHERE expire_timestamp >= UNIX_TIMESTAMP()";
}

function req_pokemon_count_id()          //DONE
{
    return "SELECT pokemon_id FROM sightings WHERE expire_timestamp >= UNIX_TIMESTAMP()";

}

function req_mystic_pokemon($mythic_pokemon)    //DONE
{
    return "SELECT DISTINCT pokemon_id, CAST(encounter_id as CHAR(50)) as encounter_id, FROM_UNIXTIME(expire_timestamp) AS disappear_time, FROM_UNIXTIME(sightings.updated) AS last_modified, FROM_UNIXTIME(expire_timestamp) AS disappear_time_real,
				sightings.lat AS latitude, sightings.lon AS longitude, cp, atk_iv AS individual_attack, def_iv AS individual_defense, sta_iv AS individual_stamina
				FROM sightings, spawnpoints
				WHERE pokemon_id IN (".implode(",", $mythic_pokemon).") AND sightings.spawn_id = spawnpoints.spawn_id
				ORDER BY last_modified DESC
				LIMIT 0,12";
}

function req_all_pokemon()          //DONE
{
    return "SELECT DISTINCT pokemon_id, CAST(encounter_id as CHAR(50)) as encounter_id, FROM_UNIXTIME(expire_timestamp) AS disappear_time, FROM_UNIXTIME(updated) AS last_modified, FROM_UNIXTIME(expire_timestamp) AS disappear_time_real,
				sightings.lat AS latitude, sightings.lon AS longitude, cp, atk_iv AS individual_attack, def_iv AS individual_defense, sta_iv AS individual_stamina
				FROM sightings, spawnpoints
				WHERE sightings.spawn_id = spawnpoints.spawn_id
				ORDER BY last_modified DESC
				LIMIT 0,12";
}


// Single Pokemon
##########

function req_pokemon_total_count($pokemon_id)    //DONE
{
    return "SELECT COUNT(*) AS pokemon_spawns FROM sightings WHERE pokemon_id = '".$pokemon_id."'";
}

function req_pokemon_total_gym_protected($pokemon_id)  //DONE
{
    return "SELECT COUNT(DISTINCT(fort_id)) AS total FROM fort_sightings WHERE guard_pokemon_id = '".$pokemon_id."'";
}

function req_pokemon_last_seen($pokemon_id)     //DONE
{
    return "SELECT FROM_UNIXTIME(expire_timestamp) AS expire_timestamp, ROM_UNIXTIME(expire_timestamp) AS disappear_time_real, lat AS latitude, lon AS longitude
                FROM sightings
                WHERE pokemon_id = '".$pokemon_id."'
                ORDER BY expire_timestamp DESC
                LIMIT 0,1";
}

function req_pokemon_get_top_50($pokemon_id, $top_order_by, $top_direction)
{
    return "disappear_time AS distime, pokemon_id, disappear_time, latitude, longitude,
	            cp, individual_attack, individual_defense, individual_stamina,
	            ROUND(SUM(100*(individual_attack+individual_defense+individual_stamina)/45),1) AS IV, move_1, move_2, form
	            FROM pokemon
	            WHERE pokemon_id = '" . $pokemon_id . "' AND move_1 IS NOT NULL AND move_1 <> '0'
	            GROUP BY encounter_id
	            ORDER BY $top_order_by $top_direction, disappear_time DESC
	            LIMIT 0,50";
}

function req_pokemon_get_top_trainers($pokemon_id, $best_order_by, $best_direction)
{
    global $config;
    $trainer_blacklist = "";
    if (!empty($config->system->trainer_blacklist)) {
        $trainer_blacklist = " AND trainer_name NOT IN ('" . implode("','", $config->system->trainer_blacklist) . "')";
    }
    return "SELECT trainer_name, ROUND(SUM(100*(iv_attack+iv_defense+iv_stamina)/45),1) AS IV, move_1, move_2, cp,
				DATE_FORMAT(last_seen, '%Y-%m-%d') AS lasttime, last_seen
				FROM gympokemon
				WHERE pokemon_id = '" . $pokemon_id . "'" . $trainer_blacklist . "
				GROUP BY pokemon_uid
				ORDER BY $best_order_by $best_direction, trainer_name ASC
				LIMIT 0,50";
}

function req_pokemon_slider_init()          //DONE
{
    return "SELECT MIN(FROM_UNIXTIME(expire_timestamp)) AS min, MAX(FROM_UNIXTIME(expire_timestamp)) AS max FROM sightings";
}

function req_pokemon_headmap_points($pokemon_id, $start, $end)         //DONE
{
    $where = " WHERE pokemon_id = ".$pokemon_id." "
        . "AND FROM_UNIXTIME(expire_timestamp) BETWEEN '".$start."' AND '".$end."'";
    return "SELECT lat AS latitude, lon AS longitude FROM sightings".$where." ORDER BY (FROM_UNIXTIME(expire_timestamp)) DESC LIMIT 10000";
}

function req_pokemon_graph_data($pokemon_id)
{
    return "SELECT COUNT(*) AS total,
        HOUR(FROM_UNIXTIME(disappear_time)) AS disappear_hour
        FROM (SELECT FROM_UNIXTIME(disappear_time) FROM sightings WHERE pokemon_id = '".$pokemon_id."' ORDER BY disappear_time LIMIT 10000) AS pokemonFiltered
        GROUP BY disappear_hour
        ORDER BY disappear_hour";
}

function req_pokemon_live_data_test($pokemon_id)     //DONE
{
    $where = " WHERE expire_timestamp >= UNIX_TIMESTAMP() AND pokemon_id = " . $pokemon_id;
    return "SELECT MAX(atk_iv) AS iv FROM sightings " . $where;
}

function req_pokemon_live_data($pokemon_id, $testIv, $post)
{
    global $mysqli;
    $inmap_pkms_filter = "";
    $where = " WHERE disappear_time >= UTC_TIMESTAMP() AND pokemon_id = " . $pokemon_id;
    if (isset($post['inmap_pokemons']) && ($post['inmap_pokemons'] != "")) {
        foreach ($post['inmap_pokemons'] as $inmap) {
            $inmap_pkms_filter .= "'" . $inmap . "',";
        }
        $inmap_pkms_filter = rtrim($inmap_pkms_filter, ",");
        $where .= " AND encounter_id NOT IN (" . $inmap_pkms_filter . ") ";
    }
    if ($testIv->iv != null && isset($post['ivMin']) && ($post['ivMin'] != "")) {
        $ivMin = mysqli_real_escape_string($mysqli, $post['ivMin']);
        $where .= " AND ((100/45)*(individual_attack+individual_defense+individual_stamina)) >= (" . $ivMin . ") ";
    }
    if ($testIv->iv != null && isset($post['ivMax']) && ($post['ivMax'] != "")) {
        $ivMax = mysqli_real_escape_string($mysqli, $post['ivMax']);
        $where .= " AND ((100/45)*(individual_attack+individual_defense+individual_stamina)) <=(" . $ivMax . ") ";
    }
    return "SELECT pokemon_id, encounter_id, latitude, longitude, disappear_time,
						disappear_time AS disappear_time_real,
						individual_attack, individual_defense, individual_stamina, move_1, move_2
						FROM pokemon " . $where . "
						ORDER BY disappear_time DESC
						LIMIT 5000";
}

function req_pokemon_count_24h()     //DONE
{
    return "SELECT pokemon_id, COUNT(*) AS spawns_last_day
    		FROM sightings
    		WHERE FROM_UNIXTIME(expire_timestamp) >= (SELECT MAX(FROM_UNIXTIME(expire_timestamp)) FROM sightings) - INTERVAL 1 DAY
    		GROUP BY pokemon_id
    		ORDER BY pokemon_id ASC";
}


// Pokestops
############

function req_pokestop_count()      //DONE
{
    return "SELECT COUNT(*) AS total FROM pokestops";
}

function req_pokestop_lure_count()    //DONE
{
    return "SELECT 0 AS total";
}

function req_pokestop_data()       //DONE
{
    return "SELECT lat as latitude, lon as longitude, null as lure_expiration, UTC_TIMESTAMP() AS now, null AS lure_expiration_real FROM pokestops ";
}

// Gyms
#######

function req_gym_count()    //DONE
{
    return "SELECT COUNT(DISTINCT(fort_id)) AS total FROM fort_sightings";
}

function req_gym_count_for_team($team_id)     //DONE
{
    return "SELECT COUNT(DISTINCT(fort_id)) AS total FROM fort_sightings WHERE team = '$team_id'";
}

function req_gym_guards_for_team($team_id)   //DONE
{
    return "SELECT COUNT(*) AS total, guard_pokemon_id FROM fort_sightings WHERE team = '$team_id' GROUP BY guard_pokemon_id ORDER BY total DESC LIMIT 0,3";
}

function req_gym_count_cp_for_team($team_id)
{
    return "SELECT COUNT(DISTINCT(fs.fort_id)) AS total, ROUND((SUM(gd.cp),0) / COUNT(DISTINCT(fs.fort_id))) AS average_points
        FROM fort_sightings fs
        JOIN gym_defenders gd ON fs.fort_id = gd.fort_id
        WHERE fs.team = '$team_id'";
}

function req_gym_data()       //DONE
{
    return "SELECT fort_id AS gym_id, team AS team_id, lat AS latitude, lon AS longitude, FROM_UNIXTIME(updated) AS last_scanned, (6 - slots_available) AS level
				FROM forts, fort_sightings
				WHERE forts.id = fort_sightings.fort_id";
}

function req_gym_data_simple($gym_id)
{
    return "SELECT gym_id, team_id, guard_pokemon_id, latitude, longitude, last_scanned AS last_scanned, total_cp, (6 - slots_available) AS level
				FROM gym WHERE gym_id='" . $gym_id . "'";
}

function req_gym_defender_for($gym_id)
{
    return "SELECT gymdetails.name AS name, gymdetails.description AS description, gymdetails.url AS url, gym.team_id AS team,
	            gym.last_scanned AS last_scanned, gym.guard_pokemon_id AS guard_pokemon_id, gym.total_cp AS total_cp, (6 - gym.slots_available) AS level
			    FROM gymdetails
			    LEFT JOIN gym ON gym.gym_id = gymdetails.gym_id
			    WHERE gym.gym_id='" . $gym_id . "'";
}

function req_gym_defender_stats_for($gym_id)
{
    return "SELECT DISTINCT gympokemon.pokemon_uid, pokemon_id, iv_attack, iv_defense, iv_stamina, MAX(cp) AS cp, gymmember.gym_id
			    FROM gympokemon INNER JOIN gymmember ON gympokemon.pokemon_uid=gymmember.pokemon_uid
			    GROUP BY gympokemon.pokemon_uid, pokemon_id, iv_attack, iv_defense, iv_stamina, gym_id
			    HAVING gymmember.gym_id='" . $gym_id . "'
			    ORDER BY cp DESC";
}

// Trainer
##########

function req_trainers($get)
{
    global $config, $mysqli;
	$name = "";
	$page = "0";
	$where = "";
	$order = "";
	$team = 0;
	$ranking = 0;
    if (isset($get['name'])) {
        $trainer_name = mysqli_real_escape_string($mysqli, $get['name']);
        $where = " HAVING name LIKE '%" . $trainer_name . "%'";
    }
    if (isset($get['team']) && $get['team'] != 0) {
        $team = mysqli_real_escape_string($mysqli, $get['team']);
        $where .= ($where == "" ? " HAVING" : " AND") . " team = " . $team;
    }
    if (!empty($config->system->trainer_blacklist)) {
        $where .= ($where == "" ? " HAVING" : " AND") . " name NOT IN ('" . implode("','", $config->system->trainer_blacklist) . "')";
    }
    if (isset($get['page'])) {
        $page = mysqli_real_escape_string($mysqli, $get['page']);
    }
    if (isset($get['ranking'])) {
        $ranking = mysqli_real_escape_string($mysqli, $get['ranking']);
    }

    switch ($ranking) {
        case 1:
            $order = " ORDER BY active DESC, level DESC";
            break;
        case 2:
            $order = " ORDER BY maxCp DESC, level DESC";
            break;
        default:
            $order = " ORDER BY level DESC, active DESC";
    }
    $order .= ", last_seen DESC, name ";
    $limit = " LIMIT " . ($page * 10) . ",10 ";
    return "SELECT trainer.*, COUNT(actives_pokemons.trainer_name) AS active, max(actives_pokemons.cp) AS maxCp
				FROM trainer
				LEFT JOIN (SELECT DISTINCT gympokemon.pokemon_id, gympokemon.pokemon_uid, gympokemon.trainer_name, gympokemon.cp, DATEDIFF(UTC_TIMESTAMP(), gympokemon.last_seen) AS last_scanned
				FROM gympokemon
				INNER JOIN (SELECT gymmember.pokemon_uid, gymmember.gym_id FROM gymmember GROUP BY gymmember.pokemon_uid, gymmember.gym_id HAVING gymmember.gym_id <> '') AS filtered_gymmember
				ON gympokemon.pokemon_uid = filtered_gymmember.pokemon_uid) AS actives_pokemons ON actives_pokemons.trainer_name = trainer.name
				GROUP BY trainer.name " . $where . $order . $limit;
}

function req_trainer_active_pokemon($name)
{
    return "(SELECT DISTINCT gympokemon.pokemon_id, gympokemon.pokemon_uid, gympokemon.cp, DATEDIFF(UTC_TIMESTAMP(), gympokemon.last_seen) AS last_scanned, gympokemon.trainer_name, gympokemon.iv_defense, gympokemon.iv_stamina, gympokemon.iv_attack, filtered_gymmember.gym_id, filtered_gymmember.deployment_time as deployment_time, '1' AS active
	            FROM gympokemon INNER JOIN
				(SELECT gymmember.pokemon_uid, gymmember.gym_id, gymmember.deployment_time FROM gymmember GROUP BY gymmember.pokemon_uid, gymmember.deployment_time, gymmember.gym_id HAVING gymmember.gym_id <> '') AS filtered_gymmember
				ON gympokemon.pokemon_uid = filtered_gymmember.pokemon_uid
				WHERE gympokemon.trainer_name='" . $name . "'
				ORDER BY gympokemon.cp DESC)";
}

function req_trainer_inactive_pokemon($name)
{
    return "(SELECT DISTINCT gympokemon.pokemon_id, gympokemon.pokemon_uid, gympokemon.cp, DATEDIFF(UTC_TIMESTAMP(), gympokemon.last_seen) AS last_scanned, gympokemon.trainer_name, gympokemon.iv_defense, gympokemon.iv_stamina, gympokemon.iv_attack, null AS gym_id, filtered_gymmember.deployment_time as deployment_time, '0' AS active
				FROM gympokemon LEFT JOIN
				(SELECT * FROM gymmember HAVING gymmember.gym_id <> '') AS filtered_gymmember
				ON gympokemon.pokemon_uid = filtered_gymmember.pokemon_uid
				WHERE filtered_gymmember.pokemon_uid IS NULL AND gympokemon.trainer_name='" . $name . "'
				ORDER BY gympokemon.cp DESC)";
}

function req_trainer_ranking($trainer)
{
    global $config;
    $reqRanking = "SELECT COUNT(1) AS rank FROM trainer WHERE level = " . $trainer->level;
    if (!empty($config->system->trainer_blacklist)) {
        $reqRanking .= " AND name NOT IN ('" . implode("','", $config->system->trainer_blacklist) . "')";
    }
    return $reqRanking;
}

function req_trainer_levels_for_team($teamid)
{
    global $config;
    $reqLevels = "SELECT level, count(level) AS count FROM trainer WHERE team = '" . $teamid . "'";
    if (!empty($config->system->trainer_blacklist)) {
        $reqLevels .= " AND name NOT IN ('" . implode("','", $config->system->trainer_blacklist) . "')";
    }
    $reqLevels .= " GROUP BY level";
    return $reqLevels;
}

// Raids -- DONE
########

function req_raids_data($page)
{
    $limit = " LIMIT " . ($page * 10) . ",10";
    return "SELECT raids.fort_id AS gym_id, raids.level AS level, raids.pokemon_id AS pokemon_id, raids.cp AS cp, raids.move_1 AS move_1, raids.move_2 AS move_2, FROM_UNIXTIME(raids.time_spawn) AS spawn, FROM_UNIXTIME(raids.time_battle) AS start, FROM_UNIXTIME(raids.time_end) AS end, FROM_UNIXTIME(fort_sightings.updated) AS last_scanned, forts.name, forts.lat AS latitude, forts.lon as longitude FROM raids
				JOIN forts ON forts.id = raids.fort_id
				JOIN fort_sightings ON fort_sightings.fort_id = raids.fort_id
				WHERE raids.time_end > UNIX_TIMESTAMP()
				ORDER BY raids.level DESC, raids.time_battle" . $limit;
}

// Captcha -- DONE
##########

function req_captcha_count()
{
    return "SELECT 0 as total";

}

// Test -- DONE
#######

function req_tester_pokemon()
{
    return "SELECT COUNT(*) AS total FROM sightings";
}

function req_tester_gym()
{
    return "SELECT COUNT(*) AS total FROM fort_sightings";
}

function req_tester_pokestop()
{
    return "SELECT COUNT(*) AS total FROM pokestops";
}

// Nests
########

function req_map_data()      //DONE
{
    global $config;
    $pokemon_exclude_sql = "";
    if (!empty($config->system->nest_exclude_pokemon)) {
        $pokemon_exclude_sql = "AND p.pokemon_id NOT IN (" . implode(",", $config->system->nest_exclude_pokemon) . ")";
    }
    return "SELECT p.pokemon_id, p.lat AS latitude, p.lon AS longitude, count(p.pokemon_id) AS total_pokemon, s.updated, coalesce(duration,30)*60 as duration
          FROM sightings p
          INNER JOIN spawnpoints s ON (p.spawn_id = s.spawn_id)
          WHERE p.expire_timestamp > UNIX_TIMESTAMP() - 86400
          " . $pokemon_exclude_sql . "
          GROUP BY p.spawn_id, p.pokemon_id
          HAVING COUNT(p.pokemon_id) >= 6
          ORDER BY p.pokemon_id";
}
