<?php

namespace DiffCompare;

use SpecialPage;

class SpecialDiffCompare extends SpecialPage {
	private static $voteMapping = [
		'DairikiDiff' => 1,
		'none' => 0,
		'wikidiff3' => -1,
	];

	/** @var Wiki */
	private $wiki;

	public function __construct() {
		parent::__construct( 'DiffCompare' );
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->setPageTitle( 'Compare diff quality' );

		$this->getOutput()->addModuleStyles( 'mediawiki.action.history.diff' );

		$this->wiki = new Wiki();

		$req = $this->getContext()->getRequest();

		switch ( $subPage ) {
			case 'next':
				$this->nextDiff();
				break;
			case 'vote':
				$this->recordVote();
				break;
			default:
				$oldid = $req->getInt( 'oldid' );
				$newid = $req->getInt( 'newid' );
				if ( $oldid && $newid ) {
					$this->showDiff( $oldid, $newid );
				} else {
					$this->nextDiff();
				}
		}
	}

	private function showDiff( $oldid, $newid ) {
		$diff = $this->wiki->getDiff( $oldid, $newid );
		if ( $diff->text1 !== $diff->text2 ) {
			$diff->saveToDB();
		}

		$order = mt_rand( 0, 1 );
		if ( $order == 0 ) {
			$method1 = 'DairikiDiff';
			$text1 = $diff->text1;
			$time1 = $diff->time1;
			$method2 = 'wikidiff3';
			$text2 = $diff->text2;
			$time2 = $diff->time2;
		} else {
			$method2 = 'DairikiDiff';
			$text2 = $diff->text1;
			$time2 = $diff->time1;
			$method1 = 'wikidiff3';
			$text1 = $diff->text2;
			$time1 = $diff->time2;
		}

		$votePage = $this->getPageTitle( 'vote' );
		$voteParams = [ 'oldid' => $oldid, 'newid' => $newid ];
		$voteUrl = $votePage->getLocalURL( $voteParams );
		$nextUrl = $this->getPageTitle( 'next' )->getLocalURL();
		$diffOfDiffs = $this->compareDiffs( $text1, $text2 );

		$this->getOutput()->enableOOUI();

		$buttonLeft = new \OOUI\ButtonWidget( [
			'href' => "{$voteUrl}&choice={$method1}",
			'label' => '< Left diff is better',
		] );
		$buttonSame = new \OOUI\ButtonWidget( [
			'href' => "{$voteUrl}&choice=none",
			'label' => 'Both are equally good/bad',
		] );
		$buttonRight = new \OOUI\ButtonWidget( [
			'href' => "{$voteUrl}&choice={$method2}",
			'label' => 'Right diff is better >',
		] );
		$buttonSkip = new \OOUI\ButtonWidget( [
			'href' => $nextUrl,
			'label' => 'I abstain',
		] );

		$html = <<<HTML
(Links to <a href="https://en.wikipedia.org/w/index.php?oldid=$oldid">old revision</a>, <a href="https://en.wikipedia.org/w/index.php?oldid=$newid">new revision</a>)
<table style="width: 100%; text-align: center;">
<tr>
<td style="width: 33%">$buttonLeft</td>
<td style="width: 34%">
	$buttonSame
	$buttonSkip
</td>
<td style="width: 33%">$buttonRight</td>
</tr>
</table>

<table style="width: 100%">
<tr>
	<td style="width: 50%; vertical-align: top;">
		<table>{$text1}</table>
	</td>
	<td style="width: 50%; vertical-align: top;">
		<table>{$text2}</table>
	</td>
</tr>

<td id="votesecret" colspan="2"><span class="mw-collapsible mw-collapsed" style="text-align: center; color: white;">$method1 ($time1 ms) | $method2 ($time2 ms)</span></td>

</table>

$diffOfDiffs
HTML;

		$this->getOutput()->addHTML( $html );
	}

	private function nextDiff() {
		$diff = $this->wiki->getRandomDiff();
		$url = $this->getPageTitle()->getFullURL( [
			'oldid' => $diff->oldid,
			'newid' => $diff->newid,
		] );
		$this->getOutput()->redirect( $url );
		$this->getOutput()->enableClientCache( false );
	}

	private function recordVote() {
		$req = $this->getRequest();
		$vote = $req->getText( 'choice' );
		if ( !isset ( self::$voteMapping[$vote] ) ) {
			die( 'Unrecognized vote' );
		}
		$oldid = $req->getInt( 'oldid' );
		$newid = $req->getInt( 'newid' );
		if ( !$oldid || !$newid ) {
			die( 'oldid and newid are required' );
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'diff_votes',
			[
				'dv_oldid' => $oldid,
				'dv_newid' => $newid,
				'dv_user' => $this->getUser()->getName(),
				'dv_vote' => self::$voteMapping[$vote],
				'dv_timestamp' => $dbw->timestamp(),
			],
			__METHOD__
		);

		$this->nextDiff();
	}

	private function getRecentChange() {
		$json = $this->wiki->api( [
			'action' => 'query',
			'list' => 'recentchanges',
			'rcprop' => 'ids|sizes',
			'rclimit' => 50,
			'rctype' => 'edit',
			'rcshow' => '!bot|!minor',
		] );
		if ( !isset( $json['query']['recentchanges'] ) ) {
			die( 'Error retrieveing recent changes' );
		}
		$changes = $json['query']['recentchanges'];

		$size = function( array $change ) {
			return abs( $change['newlen'] - $change['oldlen'] );
		};

		usort( $changes, function( $a, $b ) use ( $size ) {
			return $size( $b ) - $size( $a );
		} );

		// Grab random change outta top 3
		$num = min( count( $changes ), 3 );

		if ( !$num ) {
			die( 'No changes. Suspicious!' );
		}

		return $changes[ mt_rand( 0, $num - 1 ) ];
	}

	private function compareDiffs( &$text1, &$text2 ) {
		if ( $text1 == $text2 ) {
			return '';
		}
		$lines1 = explode( "\n", $text1 );
		$lines2 = explode( "\n", $text2 );

		$html = '';
		$limit = min( count( $lines1 ), count( $lines2 ) );
		for ( $i = 0; $i < $limit; $i++ ) {
			if ( $lines1[$i] != $lines2[$i] ) {
				$html .= wikidiff2_do_diff( $lines1[$i], $lines2[$i], 0 );
				$lines1[$i] = preg_replace( '/^<tr><td /', '<tr><td style="background: yellow;" ', $lines1[$i] );
				$lines2[$i] = preg_replace( '/^<tr><td /', '<tr><td style="background: yellow;" ', $lines2[$i] );
			}
		}
		if ( $html ) {
			$html = "<h1>Differences between diffs</h1><table id='mw-mf-diffview'>$html</table>";
			$text1 = implode( "\n", $lines1 );
			$text2 = implode( "\n", $lines2 );
		}

		return $html;
	}
}
