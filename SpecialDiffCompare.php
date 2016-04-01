<?php

namespace DiffCompare;

use SpecialPage;

class SpecialDiffCompare extends SpecialPage {
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
					$this->showRecentChanges();
				}
		}
	}

	private function showDiff( $oldid, $newid ) {
		$diff = $this->wiki->getDiff( $oldid, $newid );

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
		$voteUrl = htmlspecialchars( $votePage->getLocalURL( $voteParams ) );
		$nextUrl = htmlspecialchars( $this->getPageTitle( 'next' )->getLocalURL() );

		$html = <<<HTML
<table style="width: 100%; text-align: center;">
<tr>
<td style="width: 33%"><button><a class="votelink" href="{$voteUrl}&amp;choice={$method1}">&lt; Left diff is better</a></button></td>
<td style="width: 34%">
	<button><a class="votelink" href="{$voteUrl}&amp;choice=none">Both are equally good/bad</a></button>
	<button><a class="votelink" href="{$nextUrl}">I abstain</a></button>
</td>
<td style="width: 33%"><button><a class="votelink" href="{$voteUrl}&amp;choice={$method2}">Right diff is better &gt;</a></button></td>
</tr>
</table>

<table style="width: 100%">
<tr>
	<td style="width: 50%">
		<table>{$text1}</table>
	</td>
	<td style="width: 50%">
		<table>{$text2}</table>
	</td>
</tr>

<td id="votesecret" colspan="2"><span class="mw-collapsible mw-collapsed" style="text-align: center; color: white;">$method1 ($time1 ms) | $method2 ($time2 ms)</span></td>

</table>
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
}
