#!/usr/bin/php
<?php
/****
 * I needed a non-binary script that I could use to inspect remote disks.
 *
 * Since most *nix systems already have "df", this tool makes use of ssh + df
 * to figure out the free space remaining.
 *
 * Its output mimics "check_disk" (probably poorly).
 ****/
$shortopts = 'vh';
$longopts = array(
	'disk:',
	'crit:',
	'warn:',
	'remote-host:',
);
$options = getopt($shortopts, $longopts);

if( isset($options['h']) ){
	echo <<<EOT
check_single_disk.php

Usage:
  check_single_disk.php
    --remote-host  HOST          (optional, defaults to localhost)
    --disk         DEV_OR_MOUNT  (optional, defaults to '/')
    --crit         If below CRIT free space
                     (integer percentage), exit with CRITICAL (2)
    --warn         If below WARN free space
                     (integer percentage), exit with WARNING (1)
EOT;
	exit(4); // "unknown state"
}

function array_value(&$array, $key, $default_value = null) {
	return is_array($array) && isset($array[$key]) ? $array[$key] : $default_value;
}

$disk = escapeshellarg(array_value($options, 'disk', '/'));
$warn = intval(array_value($options, 'warn', 20));
$crit = intval(array_value($options, 'crit', 10));
$remote_host = array_value($options, 'remote-host', null);

if($remote_host){
	$esc_host = escapeshellarg($remote_host);
	exec("ssh {$esc_host} -C df -m {$disk}", $df_result, $df_exit);
}
else {
	exec("df -m {$disk}", $df_result, $df_exit);
}

if( !$df_exit ){
	preg_match('/^([^[:blank:]]+)[[:blank:]]+([^[:blank:]]+)[[:blank:]]+([^[:blank:]]+)[[:blank:]]+([^[:blank:]]+)[[:blank:]]+([^[:blank:]]+)[[:blank:]]+([^[:blank:]]+)$/', $df_result[1], $matches);
	array_shift($matches);
	$dev       = $matches[0];
	$blocks    = intval($matches[1]);
	$used      = intval($matches[2]);
	$free      = intval($matches[3]);
	$use_p     = intval($matches[4]); // percentage, as an integer
	$free_p    = 100 - $use_p;
	$mount     = $matches[5];

	if( $use_p == 100 ){
		echo "DISK FULL - free space: {$disk} {$free} MB ({$free_p}%);\n";
		exit(2);
	}

	if( $free_p <= $crit ){
		echo "DISK CRITICAL - free space: {$disk} {$free} MB ({$free_p}%);\n";
		exit(2);
	}

	if( $free_p <= $warn ){
		echo "DISK WARNING - free space: {$disk} {$free} MB ({$free_p}%);\n";
		exit(1);
	}

	echo "DISK OK - free space: {$disk} {$free} MB ({$free_p}%);\n";
	exit(0);
}
else {
	exit($df_exit);
}
