<?php

namespace DiffCompare;

use stdClass;

class DiffResult {
	public $oldid, $newid, $text1, $text2, $time1, $time2;

	public function __construct( $oldid, $newid ) {
		$this->oldid = $oldid;
		$this->newid = $newid;
	}

	public function saveToDB() {
		wfGetDB( DB_MASTER )->insert( 'diffs',
			[
				'diff_oldid' => $this->oldid,
				'diff_newid' => $this->newid,
				'diff_text1' => $this->text1,
				'diff_time1' => $this->time1,
				'diff_text2' => $this->text2,
				'diff_time2' => $this->time2,
				'diff_random' => floatval( mt_rand() ) / mt_getrandmax(),
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
	}

	public static function newFromRow( stdClass $row ) {
		$diff = new self( $row->diff_oldid, $row->diff_newid );
		$diff->text1 = $row->diff_text1;
		$diff->text2 = $row->diff_text2;
		$diff->time1 = $row->diff_time1;
		$diff->time2 = $row->diff_time2;

		return $diff;
	}
}
