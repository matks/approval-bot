<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/util.php';

$client = new \Github\Client();
$token = file_get_contents(__DIR__ . '/token.txt');
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);
$paginator = new \Github\ResultPager($client);

$debug = false;

// ------------------------------------------------------------------------------------

echo "---- Fetching GitHub data to find relevant PRs ----" . PHP_EOL;

echo 'Searching for open approved waiting for QA PRs'.PHP_EOL;
$waitingForQAPullRequests = $client->api('search')
    ->issues('type:pr is:open label:"waiting for QA" review:approved repo:prestashop/prestashop');

$pullRequestsToCheck = parseGitHubDataToExtractPullRequestIDs($waitingForQAPullRequests);

echo sprintf('Found %d eligible PRs to rapprove', count($pullRequestsToCheck)) . PHP_EOL;

echo PHP_EOL;
echo "---- Checking PR status, whether they are eligible for re-approval ----" . PHP_EOL;

list(
    $pullRequestsToApprove,
    $pullRequestsAlreadyApproved,
    $pullRequestsWithDismissedReview
    ) = checkWhetherPullRequestsMustBeReapproved(
    $pullRequestsToCheck,
    $client,
    $paginator,
    $debug
);

$pullRequestsToApprove = array_diff($pullRequestsToCheck, $pullRequestsAlreadyApproved);

echo PHP_EOL;
echo "---- Automatic approval processing ----" . PHP_EOL;

if (count($pullRequestsToApprove) > 0) {
    echo "To approve:" . PHP_EOL;

    approvePullRequests($pullRequestsToApprove, $client);
}
