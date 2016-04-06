<?php

namespace DiffCompare;

use Diff;
use Http;
use TableDiffFormatter;

class Wiki {
	const RETRIES = 3;
	private $baseUrl;

	public function __construct( $baseUrl = 'https://en.wikipedia.org/w' ) {
		$this->baseUrl = $baseUrl;
	}

	public function httpGet( $file, array $params ) {
		$url = "{$this->baseUrl}/$file.php?" . wfArrayToCgi( $params );
		for ( $i = 0; $i < self::RETRIES; $i++ ) {
			$result = Http::get( $url );
			if ( $result !== false ) {
				return $result;
			}
		}
		throw new NetworkException( "Error requesting $url\n" );
	}

	public function api( array $params ) {
		$params['format'] = 'json';
		$params['formatversion'] = 2;

		$json = $this->httpGet( 'api', $params );
		if ( !$json ) {
			die ( "API get failed\n" );
		}

		$data = json_decode( $json, true );
		if ( $data === null ) {
			die( 'json_decode() failed: ' . $json . "\n" );
		}
		return $data;
	}

	public function getRevision( $revid ) {
		$dbw = wfGetDB( DB_MASTER );
		$text = $dbw->selectField( 'external_revs', 'er_text', [ 'er_id' => $revid ], __METHOD__ );

		if ( $text !== false ) {
			return $text;
		}

		$text = $this->httpGet( 'index', [ 'oldid' => $revid, 'action' => 'raw' ] );
		if ( $text === false ) {
			die( "Text retrieval for oldid=$revid failed\n" );
		}

		$dbw->insert( 'external_revs', [ 'er_id' => $revid, 'er_text' => $text ] );

		return $text;
	}

	/**
	 * @param int $oldid
	 * @param int $newid
	 * @returns DiffResult
	 */
	public function getDiff( $oldid, $newid ) {
		$dbw = wfGetDB( DB_MASTER );
		$row = $dbw->selectRow( 'diffs', [ '*' ], [ 'diff_oldid' => $oldid, 'diff_newid' => $newid ] );
		if ( $row ) {
			return DiffResult::newFromRow( $row );
		}

		$diff = $this->generateDiff( $oldid, $newid );

		return $diff;
	}

	public function getRandomDiff() {
		$dbw = wfGetDB( DB_MASTER );
		$row = $dbw->selectRow( 'diffs', [ '*' ], [ 'diff_random >= RAND()' ], __METHOD__, [ 'ORDER BY' => 'diff_random' ] );
		if ( !$row ) {
			die( "Couldn't find a random diff o_0\n" );
		}

		return DiffResult::newFromRow( $row );
	}

	public function generateDiff( $oldid, $newid ) {
		$diff = new DiffResult( $oldid, $newid );
		$otext = $this->getRevision( $oldid );
		$ntext = $this->getRevision( $newid );
		list( $diff->text1, $diff->time1 ) = $this->diff( $otext, $ntext, false );
		list( $diff->text2, $diff->time2 ) = $this->diff( $otext, $ntext, 'wikidiff3' );

		return $diff;
	}

	private function diff( $old, $new, $method ) {
		global $wgExternalDiffEngine;

		$wgExternalDiffEngine = $method;

		$ota = explode( "\n", $old );
		$nta = explode( "\n", $new );

		$time = microtime( true );
		$diffs = new Diff( $ota, $nta );
		$formatter = new TableDiffFormatter();
		$difftext = $formatter->format( $diffs );
		$time = microtime( true ) - $time;
		return [ $difftext, intval( $time * 1000 ) ];
	}
}
