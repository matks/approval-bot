<?php

require_once __DIR__ . '/vendor/autoload.php';

$client = new \Github\Client();
$token = file_get_contents(__DIR__.'/token.txt');
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);

$pullRequestsToCheckFileContent = file_get_contents(__DIR__.'/prs.txt');

$matches = [];
$pattern = '#(\d+)#';
preg_match_all($pattern , $pullRequestsToCheckFileContent, $matches);
$pullRequestsToCheck = $matches[0];

$pullRequestsToApprove = [];
$pullRequestsAlreadyApproved = [];

$paginator  = new Github\ResultPager($client);

foreach ($pullRequestsToCheck as $pullRequestID) {

	echo "Checking status of PR ".$pullRequestID.PHP_EOL;

	$pullRequest = $client->api('pull_request')->show('prestashop', 'prestashop', $pullRequestID);

	$headSha = $pullRequest['head']['sha'];
	echo "PR ".$pullRequestID.' head sha is '.$headSha.PHP_EOL;
	$reviewRequestsApi = $client->api('pull_request')->reviews();

	$parameters = ['prestashop', 'prestashop', $pullRequestID];
	$reviewRequests = $paginator->fetchAll($reviewRequestsApi, 'all', $parameters);

	foreach ($reviewRequests as $reviewRequest) {

		$userLogin = $reviewRequest['user']['login'];
		$reviewedCommitID = $reviewRequest['commit_id'];
		
		if ('matks' === $userLogin) {

			if ($reviewedCommitID !== $headSha) {
				echo 'Matks reviewed an old commit of PR '.$pullRequestID.' (sha: '.$reviewedCommitID.')'.PHP_EOL;
				$pullRequestsToApprove[] = $pullRequestID;
			} else {
				echo 'Matks reviewed head commit of PR '.$pullRequestID.PHP_EOL;
				$pullRequestsAlreadyApproved[] = $pullRequestID;
			}

		}
	}
}

$pullRequestsAlreadyApproved = array_unique($pullRequestsAlreadyApproved);
$pullRequestsToApprove = array_unique($pullRequestsToApprove);

echo PHP_EOL;
echo "Summary:".PHP_EOL;

foreach ($pullRequestsAlreadyApproved as $pullRequestID) {
	echo '- Ignoring PR '.$pullRequestID." as it's already approved".PHP_EOL;
}

foreach ($pullRequestsToApprove as $pullRequestID) {
	if (in_array($pullRequestID, $pullRequestsAlreadyApproved)) {
		continue;
	}
	
	echo '- Approving PR '.$pullRequestID.PHP_EOL;
	$client->api('pull_request')->reviews()->create(
		'prestashop', 'prestashop', $pullRequestID,
		array('event' => 'APPROVE')
	);
}