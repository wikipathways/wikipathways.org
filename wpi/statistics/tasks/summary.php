<?php

registerTask('summary', 'Summary::run');

class Summary {
	static function run($file, $times) {
		$tsCurr = date('YmdHis');
		$date = date('Y/m/d', wfTimestamp(TS_UNIX, $tsCurr));

		$fout = fopen($file, 'w');

		//pathways
		$pathways = StatPathway::getSnapshot($tsCurr);
		$pwCount = count($pathways);

		fwrite($fout, "<p>Last update: $date</p>");
		$pws = <<<PATHWAYS
<h3>Number of pathways: $pwCount</h3>
PATHWAYS;
		fwrite($fout, $pws);

		$uOne = 0;

		$eTotal = 0;
		$eTotalBots = 0;

		$exclude = WikiPathwaysStatistics::getExcludeByTag();
		$users = StatUser::getSnapshot($tsCurr);

		foreach($users as $u) {
			$edits = $u->getPageEdits($tsCurr);

			if(count($edits) > 0) $uOne += 1;

			$mwu = User::newFromId($u->getId());
			if($mwu->isBot()) {
				$eTotalBots += count($edits);
			} else {
				$eTotal += count($edits);
			}
		}
		$eTotalAll = $eTotal + $eTotalBots;

		$usr = <<<USERS
<h3>Number of active users: $uOne</h3>
USERS;
		$edt = <<<EDITS
<h3>Number of edits: $eTotalAll</h3><ul>
<li>User edits:<b> $eTotal</b>
<li>Automated edits:<b> $eTotalBots</b>
</ul>
EDITS;

		fwrite($fout, $usr);
		fwrite($fout, $edt);

		fclose($fout);
	}
}
