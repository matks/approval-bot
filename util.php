<?php

/**
 * @return int[]
 */
function getPullRequestToCheckIDsFromFile()
{
    $pullRequestsToCheckFileContent = file_get_contents(__DIR__ . '/prs.txt');

    $matches = [];
    $pattern = '#(\d+)#';
    preg_match_all($pattern, $pullRequestsToCheckFileContent, $matches);
    $pullRequestsToCheck = $matches[0];

    return $pullRequestsToCheck;
}

/**
 * @param array $pullRequestsToCheck
 * @param \Github\Client $client
 * @param \Github\ResultPager $paginator
 * @param bool $debug
 *
 * @return array
 */
function checkWhetherPullRequestsMustBeReapproved(
    array $pullRequestsToCheck,
    \Github\Client $client,
    \Github\ResultPager $paginator,
    $debug)
{
    $pullRequestsToApprove = [];
    $pullRequestsAlreadyApproved = [];
    $pullRequestsWithDismissedReview = [];

    foreach ($pullRequestsToCheck as $pullRequestID) {

        echo "Checking status of PR " . $pullRequestID;

        $pullRequest = $client->api('pull_request')->show('prestashop', 'prestashop', $pullRequestID);

        echo sprintf(
                ' "%s" by @%s',
                $pullRequest['title'],
                $pullRequest['user']['login']
            ) . PHP_EOL;

        if ($pullRequest['user']['login'] === 'matks') {
            $status = 'authored by matks';
            $pullRequestsAlreadyApproved[] = $pullRequestID;
            continue;
        }

        $headSha = $pullRequest['head']['sha'];
        if ($debug) {
            echo "PR " . $pullRequestID . ' head sha is ' . $headSha . PHP_EOL;
        }
        $reviewRequestsApi = $client->api('pull_request')->reviews();

        $parameters = ['prestashop', 'prestashop', $pullRequestID];
        $reviewRequests = $paginator->fetchAll($reviewRequestsApi, 'all', $parameters);

        $status = null;

        foreach ($reviewRequests as $reviewRequest) {

            $userLogin = $reviewRequest['user']['login'];
            $reviewStatus = $reviewRequest['state'];
            $reviewedCommitID = $reviewRequest['commit_id'];

            if ('matks' !== $userLogin) {
                continue;
            }

            if ($reviewStatus === 'DISMISSED') {
                if ($status === null) {
                    $status = 'dismissed';
                }
                $pullRequestsWithDismissedReview[] = $pullRequestID;
            }

            if ($reviewStatus !== 'APPROVED') {
                continue;
            }

            if ($reviewedCommitID !== $headSha) {
                if ($debug) {
                    echo 'Matks approved an old commit of PR ' . $pullRequestID . ' (sha: ' . $reviewedCommitID . ')' . PHP_EOL;
                }
                if ($status !== 'already approved') {
                    $status = 'to approve';
                }
                $pullRequestsToApprove[] = $pullRequestID;
            } else {
                if ($debug) {
                    echo 'Matks approved head commit of PR ' . $pullRequestID . PHP_EOL;
                }
                $status = 'already approved';
                $pullRequestsAlreadyApproved[] = $pullRequestID;
            }
        }

        if ($status === null) {
            $status = 'ignored (never approved by matks)';
        }

        echo sprintf('- status is %s', $status) . PHP_EOL;
    }

    $pullRequestsAlreadyApproved = array_unique($pullRequestsAlreadyApproved);
    $pullRequestsToApprove = array_unique($pullRequestsToApprove);
    $pullRequestsWithDismissedReview = array_unique($pullRequestsWithDismissedReview);

    return [
        $pullRequestsToApprove,
        $pullRequestsAlreadyApproved,
        $pullRequestsWithDismissedReview
    ];
}

/**
 * @param array $migrationPullRequests
 *
 * @return int[]
 */
function parseGitHubDataToExtractPullRequestIDs(array $migrationPullRequests)
{
    if (empty($migrationPullRequests)) {
        return [];
    }
    if (empty($migrationPullRequests['items'])) {
        return [];
    }

    $pullRequestIds = array_map(function ($pullRequestData) {
        return $pullRequestData['number'];
    }, $migrationPullRequests['items']);

    return $pullRequestIds;
}

/**
 * @param string $link
 * @param string $text
 *
 * @return string
 */
function printClickableLink($link, $text)
{
    return "\033]8;;" . $link . "\033\\" . $text . "\033]8;;\033\\";
}

/**
 * @param array $pullRequestsToApprove
 * @param \Github\Client $client
 */
function approvePullRequests(array $pullRequestsToApprove, \Github\Client $client)
{
    foreach ($pullRequestsToApprove as $pullRequestID) {
        echo '- Approving PR ' . $pullRequestID;

        try {
            $client->api('pull_request')->reviews()->create(
                'prestashop', 'prestashop', $pullRequestID,
                array('event' => 'APPROVE')
            );
        } catch (\Github\Exception\ValidationFailedException $e) {
            echo '... failed' . PHP_EOL;
            continue;
        }

        echo '... success' . PHP_EOL;
    }
}
