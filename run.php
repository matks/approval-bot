<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/util.php';

$client = new \Github\Client();
$token = file_get_contents(__DIR__ . '/token.txt');
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);
$paginator = new Github\ResultPager($client);

$debug = false;

echo "---- Fetching GitHub data to find relevant PRs ----" . PHP_EOL;

$migrationPullRequestsApproved = $client->api('search')
    ->issues('type:pr is:open label:migration reviewed-by:matks');

$pullRequestsToCheck = parseGitHubDataToExtractPullRequestIDs($migrationPullRequestsApproved);
//$pullRequestsToCheck = getPullRequestToCheckIDs();

echo sprintf('Found %d eligible PRs to re-approve', count($pullRequestsToCheck)) . PHP_EOL;
echo PHP_EOL;
echo "---- Checking PR status, whether they are eligible for re-approval ----" . PHP_EOL;

list(
    $pullRequestsToApprove,
    $pullRequestsAlreadyApproved) = checkWhetherPullRequestsMustBeReapproved(
    $pullRequestsToCheck,
    $client,
    $paginator,
    $debug
);

$pullRequestsToApprove = array_diff($pullRequestsToApprove, $pullRequestsAlreadyApproved);

echo PHP_EOL;
echo "---- Automatic approval processing ----" . PHP_EOL;
if (count($pullRequestsAlreadyApproved) > 0) {
    echo "Already approved:" . PHP_EOL;

    foreach ($pullRequestsAlreadyApproved as $pullRequestID) {
        echo '- Ignoring PR ' . $pullRequestID . " as it's already approved" . PHP_EOL;
    }

    echo PHP_EOL;
}
if (count($pullRequestsToApprove) > 0) {
    echo "To approve:" . PHP_EOL;

    foreach ($pullRequestsToApprove as $pullRequestID) {
        echo '- Approving PR ' . $pullRequestID;
        $client->api('pull_request')->reviews()->create(
            'prestashop', 'prestashop', $pullRequestID,
            array('event' => 'APPROVE')
        );
        echo '... success' . PHP_EOL;
    }
}
