<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/util.php';

$client = new \Github\Client();
$token = file_get_contents(__DIR__ . '/token.txt');
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);
$paginator = new \Github\ResultPager($client);

$debug = false;

echo "---- Fetching GitHub data to find relevant PRs ----" . PHP_EOL;

echo 'Searching for open Migration PRs reviewed by matks'.PHP_EOL;
$migrationPullRequests = $client->api('search')
    ->issues('type:pr is:open label:migration review:required reviewed-by:matks repo:prestashop/prestashop');

$pullRequestsToCheck = parseGitHubDataToExtractPullRequestIDs($migrationPullRequests);
//$pullRequestsToCheck = getPullRequestToCheckIDs();
echo sprintf('Found %d eligible PRs to re-approve', count($pullRequestsToCheck)) . PHP_EOL;

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

if (count($pullRequestsWithDismissedReview) > 0) {
    echo "Dismissed:" . PHP_EOL;

    foreach ($pullRequestsWithDismissedReview as $pullRequestID) {
        echo sprintf(
            '- PR %d dismissed ; %s',
            $pullRequestID,
            printClickableLink(
                'https://github.com/PrestaShop/PrestaShop/pull/'.$pullRequestID,
                '(click here to see the PR)'
            )
        ).PHP_EOL;
    }

    echo PHP_EOL;
}

if (count($pullRequestsToApprove) > 0) {
    echo "To approve:" . PHP_EOL;

    approvePullRequests($pullRequestsToApprove, $client);
}
