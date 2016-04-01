<?php

use DiffCompare\Wiki;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __FILE__ ) . '/../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class LoadDiffs extends Maintenance {

	public function execute() {
		$lines = file( 'rc.txt' );
		$wiki = new Wiki();

		$count = 0;
		$different = 0;
		foreach ( $lines as $line ) {
			if ( !preg_match( '/(\d+)\s+(\d+)/', $line, $matches ) ) {
				continue;
			}
			$count++;
			$oldid = intval( $matches[1] );
			$newid = intval( $matches[2] );

			$diff = $wiki->generateDiff( $oldid, $newid );
			if ( $diff->text1 != $diff->text2 ) {
				$different++;
				$diff->saveToDB();
			}

			if ( $count % 100 == 0 ) {
				$this->output( "$count diffs, $different different\n" );
			}
		}
	}
}

$maintClass = 'LoadDiffs';
require_once( RUN_MAINTENANCE_IF_MAIN );
